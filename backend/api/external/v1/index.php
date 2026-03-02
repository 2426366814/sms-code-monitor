<?php
/**
 * 对外API接口 - v1
 * 提供统一的验证码获取服务
 * 
 * 认证方式: API Key (Header: X-API-Key)
 * 
 * 接口列表:
 * - GET  /api/v1/codes        获取验证码列表
 * - GET  /api/v1/codes/{phone} 获取指定号码验证码
 * - POST /api/v1/callback     接收外部推送
 * - GET  /api/v1/platforms    获取平台列表
 * - GET  /api/v1/phones       获取可用号码
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/Database.php';
require_once __DIR__ . '/../../../models/BaseModel.php';

class ExternalAPI {
    private $db;
    private $apiKey;
    private $userId;
    
    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
        $this->authenticate();
    }
    
    private function authenticate() {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
        
        if (!$apiKey) {
            $this->error(401, 'API Key is required');
        }
        
        $stmt = $this->db->prepare("
            SELECT ak.*, u.username 
            FROM api_keys ak 
            JOIN users u ON ak.user_id = u.id 
            WHERE ak.api_key = ? AND ak.status = 'active' 
            AND (ak.expires_at IS NULL OR ak.expires_at > NOW())
        ");
        $stmt->execute([$apiKey]);
        $keyData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$keyData) {
            $this->error(401, 'Invalid or expired API Key');
        }
        
        $this->apiKey = $apiKey;
        $this->userId = $keyData['user_id'];
        
        $this->logRequest($keyData['id']);
    }
    
    private function logRequest($keyId) {
        $stmt = $this->db->prepare("
            INSERT INTO api_logs (api_key_id, endpoint, method, ip_address, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $keyId,
            $_SERVER['REQUEST_URI'],
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REMOTE_ADDR']
        ]);
    }
    
    public function handle() {
        $path = $_GET['path'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'];
        
        $pathParts = array_filter(explode('/', $path));
        $endpoint = $pathParts[0] ?? 'codes';
        $param = $pathParts[1] ?? null;
        
        switch ($endpoint) {
            case 'codes':
                if ($method === 'GET') {
                    if ($param) {
                        $this->getCodeByPhone($param);
                    } else {
                        $this->getCodes();
                    }
                }
                break;
                
            case 'callback':
                if ($method === 'POST') {
                    $this->handleCallback();
                }
                break;
                
            case 'platforms':
                if ($method === 'GET') {
                    $this->getPlatforms();
                }
                break;
                
            case 'phones':
                if ($method === 'GET') {
                    $this->getPhones();
                }
                break;
                
            default:
                $this->error(404, 'Endpoint not found');
        }
    }
    
    private function getCodes() {
        $page = max(1, intval($_GET['page'] ?? 1));
        $pageSize = min(100, max(1, intval($_GET['page_size'] ?? 20)));
        $phone = $_GET['phone'] ?? null;
        $platform = $_GET['platform'] ?? null;
        
        $where = "WHERE user_id = ?";
        $params = [$this->userId];
        
        if ($phone) {
            $where .= " AND phone LIKE ?";
            $params[] = "%$phone%";
        }
        
        if ($platform) {
            $where .= " AND source_url LIKE ?";
            $params[] = "%$platform%";
        }
        
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM verification_codes $where");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();
        
        $offset = ($page - 1) * $pageSize;
        $params[] = $offset;
        $params[] = $pageSize;
        
        $stmt = $this->db->prepare("
            SELECT id, phone, code, source_url, created_at 
            FROM verification_codes 
            $where 
            ORDER BY created_at DESC 
            LIMIT ?, ?
        ");
        $stmt->execute($params);
        $codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->success([
            'list' => $codes,
            'pagination' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => ceil($total / $pageSize)
            ]
        ]);
    }
    
    private function getCodeByPhone($phone) {
        $stmt = $this->db->prepare("
            SELECT id, phone, code, source_url, created_at 
            FROM verification_codes 
            WHERE user_id = ? AND phone = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$this->userId, $phone]);
        $codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($codes)) {
            $this->error(404, 'No codes found for this phone number');
        }
        
        $this->success([
            'phone' => $phone,
            'codes' => $codes,
            'latest_code' => $codes[0]['code'] ?? null
        ]);
    }
    
    private function handleCallback() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $input = $_POST;
        }
        
        $phone = $input['phone'] ?? $input['mobile'] ?? null;
        $code = $input['code'] ?? $input['sms_code'] ?? $input['verify_code'] ?? null;
        $platform = $input['platform'] ?? $input['source'] ?? 'callback';
        $message = $input['content'] ?? $input['sms_content'] ?? $input['message'] ?? '';
        
        if (!$phone || !$code) {
            $this->error(400, 'phone and code are required');
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO verification_codes (user_id, monitor_id, phone, code, message, source_url, created_at)
            VALUES (?, 0, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$this->userId, $phone, $code, $message, $platform]);
        
        $this->success([
            'message' => 'Code received successfully',
            'phone' => $phone,
            'code' => $code,
            'id' => $this->db->lastInsertId()
        ]);
    }
    
    private function getPlatforms() {
        $stmt = $this->db->prepare("
            SELECT id, name, api_url, is_active, created_at 
            FROM sms_platforms 
            WHERE user_id = ? AND is_active = 1
        ");
        $stmt->execute([$this->userId]);
        $platforms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->success(['platforms' => $platforms]);
    }
    
    private function getPhones() {
        $stmt = $this->db->prepare("
            SELECT DISTINCT phone, MAX(created_at) as last_code_time
            FROM verification_codes 
            WHERE user_id = ? 
            GROUP BY phone 
            ORDER BY last_code_time DESC
        ");
        $stmt->execute([$this->userId]);
        $phones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->success(['phones' => $phones]);
    }
    
    private function success($data) {
        echo json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('c')
        ]);
        exit;
    }
    
    private function error($code, $message) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message
            ],
            'timestamp' => date('c')
        ]);
        exit;
    }
}

try {
    $api = new ExternalAPI();
    $api->handle();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 500,
            'message' => 'Internal server error'
        ]
    ]);
}
