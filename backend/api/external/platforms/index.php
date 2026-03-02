<?php
/**
 * 外部平台管理API v2.0
 * 支持两种类型：
 * - type1: 手机号码+网址形式（一个手机号对应一个API）
 * - type2: 直接网址形式（一个API对应多个手机号）
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/Database.php';
require_once __DIR__ . '/../../../utils/JWT.php';

class PlatformManager {
    private $db;
    private $pdo;
    private $userId;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->pdo = Database::getInstance()->getPdo();
        $this->authenticate();
    }
    
    private function authenticate() {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (empty($authHeader)) {
            $this->error(401, 'Authorization header required');
        }
        
        $token = str_replace('Bearer ', '', $authHeader);
        $config = require __DIR__ . '/../../../config/config.php';
        $jwt = new JWT($config['jwt']['secret'], $config['jwt']['algorithm']);
        
        try {
            $payload = $jwt->validateToken($token);
            if (!$payload) {
                $this->error(401, 'Invalid token');
            }
            $this->userId = $payload['user_id'];
        } catch (Exception $e) {
            $this->error(401, 'Token validation failed');
        }
    }
    
    public function handle() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_GET['path'] ?? '';
        $parts = array_filter(explode('/', $path));
        
        $platformId = intval($parts[0] ?? 0);
        $action = $parts[1] ?? '';
        
        switch ($method) {
            case 'GET':
                if ($platformId && $action === 'test') {
                    $this->testPlatform($platformId);
                } elseif ($platformId && $action === 'fetch') {
                    $this->fetchFromPlatform($platformId);
                } elseif ($platformId && $action === 'logs') {
                    $this->getPlatformLogs($platformId);
                } elseif ($platformId) {
                    $this->getPlatform($platformId);
                } else {
                    $this->listPlatforms();
                }
                break;
            case 'POST':
                if ($platformId && $action === 'test') {
                    $this->testPlatform($platformId);
                } elseif ($platformId && $action === 'fetch') {
                    $this->fetchFromPlatform($platformId);
                } else {
                    $this->createPlatform();
                }
                break;
            case 'PUT':
                if ($platformId) {
                    $this->updatePlatform($platformId);
                }
                break;
            case 'DELETE':
                if ($platformId) {
                    $this->deletePlatform($platformId);
                }
                break;
            default:
                $this->error(405, 'Method not allowed');
        }
    }
    
    private function listPlatforms() {
        $type = $_GET['type'] ?? null;
        $isActive = $_GET['is_active'] ?? null;
        
        $where = "WHERE user_id = :user_id";
        $params = [':user_id' => $this->userId];
        
        if ($type && in_array($type, ['type1', 'type2'])) {
            $where .= " AND platform_type = :type";
            $params[':type'] = $type;
        }
        
        if ($isActive !== null) {
            $where .= " AND is_active = :is_active";
            $params[':is_active'] = intval($isActive);
        }
        
        $sql = "SELECT * FROM sms_platforms $where ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $platforms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 统计
        $stats = [
            'total' => count($platforms),
            'type1' => 0,
            'type2' => 0,
            'active' => 0
        ];
        
        foreach ($platforms as $p) {
            if ($p['platform_type'] === 'type1') $stats['type1']++;
            if ($p['platform_type'] === 'type2') $stats['type2']++;
            if ($p['is_active']) $stats['active']++;
        }
        
        $this->success([
            'platforms' => $platforms,
            'stats' => $stats
        ]);
    }
    
    private function getPlatform($id) {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM sms_platforms WHERE id = :id AND user_id = :user_id"
        );
        $stmt->execute([':id' => $id, ':user_id' => $this->userId]);
        $platform = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$platform) {
            $this->error(404, 'Platform not found');
        }
        
        // 获取最近获取日志
        $stmt = $this->pdo->prepare(
            "SELECT * FROM platform_fetch_logs WHERE platform_id = :id ORDER BY created_at DESC LIMIT 10"
        );
        $stmt->execute([':id' => $id]);
        $platform['recent_logs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->success($platform);
    }
    
    private function createPlatform() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // 验证必填字段
        $name = trim($input['name'] ?? '');
        $platformType = $input['platform_type'] ?? '';
        $apiUrl = trim($input['api_url'] ?? '');
        
        if (empty($name)) {
            $this->error(400, 'Platform name is required');
        }
        
        if (!in_array($platformType, ['type1', 'type2'])) {
            $this->error(400, 'Invalid platform type. Must be type1 or type2');
        }
        
        if (empty($apiUrl)) {
            $this->error(400, 'API URL is required');
        }
        
        // 类型1必须有手机号码
        if ($platformType === 'type1') {
            $phone = trim($input['phone'] ?? '');
            if (empty($phone)) {
                $this->error(400, 'Phone number is required for type1 platform');
            }
            if (!preg_match('/^\d{10,15}$/', $phone)) {
                $this->error(400, 'Invalid phone number format');
            }
        }
        
        // 插入数据
        $data = [
            'user_id' => $this->userId,
            'name' => $name,
            'platform_type' => $platformType,
            'api_url' => $apiUrl,
            'phone' => $platformType === 'type1' ? ($input['phone'] ?? null) : null,
            'api_key' => $input['api_key'] ?? null,
            'api_secret' => $input['api_secret'] ?? null,
            'is_active' => intval($input['is_active'] ?? 1),
            'auto_fetch' => intval($input['auto_fetch'] ?? 0),
            'fetch_interval' => max(30, intval($input['fetch_interval'] ?? 60)),
            'response_type' => $input['response_type'] ?? 'json',
            'phone_field' => $input['phone_field'] ?? 'phone',
            'code_field' => $input['code_field'] ?? 'code',
            'code_pattern' => $input['code_pattern'] ?? null,
            'data_path' => $input['data_path'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $fields = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO sms_platforms ($fields) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        
        $id = $this->pdo->lastInsertId();
        
        $this->success([
            'id' => intval($id),
            'name' => $name,
            'platform_type' => $platformType
        ], 'Platform created successfully');
    }
    
    private function updatePlatform($id) {
        // 检查平台是否存在
        $stmt = $this->pdo->prepare(
            "SELECT * FROM sms_platforms WHERE id = :id AND user_id = :user_id"
        );
        $stmt->execute([':id' => $id, ':user_id' => $this->userId]);
        $platform = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$platform) {
            $this->error(404, 'Platform not found');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        // 构建更新数据
        $updates = [];
        $params = [':id' => $id, ':user_id' => $this->userId];
        
        $allowedFields = [
            'name', 'api_url', 'phone', 'api_key', 'api_secret',
            'is_active', 'auto_fetch', 'fetch_interval',
            'response_type', 'phone_field', 'code_field', 'code_pattern', 'data_path'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $input[$field];
            }
        }
        
        if (empty($updates)) {
            $this->error(400, 'No fields to update');
        }
        
        $updates[] = "updated_at = NOW()";
        
        $sql = "UPDATE sms_platforms SET " . implode(', ', $updates) . " WHERE id = :id AND user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $this->success(null, 'Platform updated successfully');
    }
    
    private function deletePlatform($id) {
        $stmt = $this->pdo->prepare(
            "DELETE FROM sms_platforms WHERE id = :id AND user_id = :user_id"
        );
        $stmt->execute([':id' => $id, ':user_id' => $this->userId]);
        
        if ($stmt->rowCount() > 0) {
            $this->success(null, 'Platform deleted successfully');
        } else {
            $this->error(404, 'Platform not found');
        }
    }
    
    private function testPlatform($id) {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM sms_platforms WHERE id = :id AND user_id = :user_id"
        );
        $stmt->execute([':id' => $id, ':user_id' => $this->userId]);
        $platform = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$platform) {
            $this->error(404, 'Platform not found');
        }
        
        $startTime = microtime(true);
        $result = [
            'platform_id' => $id,
            'platform_name' => $platform['name'],
            'platform_type' => $platform['platform_type'],
            'status' => 'success',
            'message' => '',
            'response_time_ms' => 0,
            'sample_data' => null
        ];
        
        try {
            $response = $this->makeHttpRequest($platform);
            $result['response_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            
            // 解析响应
            $parsed = $this->parseResponse($response, $platform);
            $result['sample_data'] = $parsed;
            $result['message'] = 'Connection successful';
            
        } catch (Exception $e) {
            $result['status'] = 'failed';
            $result['message'] = $e->getMessage();
            $result['response_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
        }
        
        $this->success($result);
    }
    
    private function fetchFromPlatform($id) {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM sms_platforms WHERE id = :id AND user_id = :user_id AND is_active = 1"
        );
        $stmt->execute([':id' => $id, ':user_id' => $this->userId]);
        $platform = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$platform) {
            $this->error(404, 'Platform not found or inactive');
        }
        
        $startTime = microtime(true);
        $codesFound = 0;
        $codesNew = 0;
        $errors = [];
        
        try {
            $response = $this->makeHttpRequest($platform);
            $parsed = $this->parseResponse($response, $platform);
            
            if ($platform['platform_type'] === 'type1') {
                // 类型1: 单个手机号
                if (!empty($parsed['code'])) {
                    $codesFound = 1;
                    if ($this->saveCode($platform, $parsed)) {
                        $codesNew = 1;
                    }
                }
            } else {
                // 类型2: 多个手机号
                $codesFound = count($parsed);
                foreach ($parsed as $item) {
                    if ($this->saveCode($platform, $item)) {
                        $codesNew++;
                    }
                }
            }
            
            $status = 'success';
            
        } catch (Exception $e) {
            $status = 'failed';
            $errors[] = $e->getMessage();
        }
        
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        
        // 记录日志
        $this->logFetch($id, 'manual', $status, $codesFound, $codesNew, implode('; ', $errors), $responseTime);
        
        $this->success([
            'platform_id' => $id,
            'platform_name' => $platform['name'],
            'platform_type' => $platform['platform_type'],
            'status' => $status,
            'codes_found' => $codesFound,
            'codes_new' => $codesNew,
            'response_time_ms' => $responseTime,
            'errors' => $errors
        ]);
    }
    
    private function getPlatformLogs($id) {
        $page = max(1, intval($_GET['page'] ?? 1));
        $pageSize = min(100, max(1, intval($_GET['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;
        
        // 验证平台所有权
        $stmt = $this->pdo->prepare(
            "SELECT id FROM sms_platforms WHERE id = :id AND user_id = :user_id"
        );
        $stmt->execute([':id' => $id, ':user_id' => $this->userId]);
        if (!$stmt->fetch()) {
            $this->error(404, 'Platform not found');
        }
        
        $stmt = $this->pdo->prepare(
            "SELECT * FROM platform_fetch_logs WHERE platform_id = :id ORDER BY created_at DESC LIMIT :offset, :limit"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->success([
            'logs' => $logs,
            'pagination' => [
                'page' => $page,
                'page_size' => $pageSize
            ]
        ]);
    }
    
    private function makeHttpRequest($platform) {
        $ch = curl_init();
        
        $url = $platform['api_url'];
        $headers = [];
        
        // 添加认证头
        if (!empty($platform['api_key'])) {
            $headers[] = 'Authorization: Bearer ' . $platform['api_key'];
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("HTTP request failed: $error");
        }
        
        if ($httpCode >= 400) {
            throw new Exception("HTTP error: $httpCode");
        }
        
        return $response;
    }
    
    private function parseResponse($response, $platform) {
        $responseType = $platform['response_type'] ?? 'json';
        
        if ($responseType === 'json') {
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response');
            }
            
            // 处理数据路径
            if (!empty($platform['data_path'])) {
                $paths = explode('.', $platform['data_path']);
                foreach ($paths as $path) {
                    if (isset($data[$path])) {
                        $data = $data[$path];
                    }
                }
            }
            
            // 类型1: 返回单个对象
            if ($platform['platform_type'] === 'type1') {
                $phoneField = $platform['phone_field'] ?? 'phone';
                $codeField = $platform['code_field'] ?? 'code';
                
                $code = $data[$codeField] ?? null;
                
                // 如果没有直接的code字段，尝试用正则提取
                if (!$code && !empty($platform['code_pattern'])) {
                    $text = is_string($data) ? $data : json_encode($data);
                    if (preg_match('/' . $platform['code_pattern'] . '/', $text, $matches)) {
                        $code = $matches[1] ?? $matches[0];
                    }
                }
                
                return [
                    'phone' => $platform['phone'] ?? ($data[$phoneField] ?? ''),
                    'code' => $code,
                    'raw' => $data
                ];
            }
            
            // 类型2: 返回数组
            if (!is_array($data)) {
                $data = [$data];
            }
            
            if (!isset($data[0])) {
                $data = [$data];
            }
            
            $results = [];
            $phoneField = $platform['phone_field'] ?? 'phone';
            $codeField = $platform['code_field'] ?? 'code';
            
            foreach ($data as $item) {
                if (!is_array($item)) continue;
                
                $phone = $item[$phoneField] ?? '';
                $code = $item[$codeField] ?? '';
                
                // 尝试用正则提取
                if (!$code && !empty($platform['code_pattern'])) {
                    $text = is_string($item) ? $item : json_encode($item);
                    if (preg_match('/' . $platform['code_pattern'] . '/', $text, $matches)) {
                        $code = $matches[1] ?? $matches[0];
                    }
                }
                
                if ($phone && $code) {
                    $results[] = [
                        'phone' => $phone,
                        'code' => $code,
                        'raw' => $item
                    ];
                }
            }
            
            return $results;
            
        } elseif ($responseType === 'xml') {
            $xml = simplexml_load_string($response);
            if ($xml === false) {
                throw new Exception('Invalid XML response');
            }
            return json_decode(json_encode($xml), true);
            
        } else {
            // 纯文本，用正则提取
            if (!empty($platform['code_pattern'])) {
                if (preg_match_all('/' . $platform['code_pattern'] . '/', $response, $matches)) {
                    return ['code' => $matches[1][0] ?? $matches[0][0]];
                }
            }
            return ['raw' => $response];
        }
    }
    
    private function saveCode($platform, $data) {
        if (empty($data['phone']) || empty($data['code'])) {
            return false;
        }
        
        // 检查是否已存在（1小时内）
        $stmt = $this->pdo->prepare(
            "SELECT id FROM verification_codes 
             WHERE phone = :phone AND code = :code AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stmt->execute([':phone' => $data['phone'], ':code' => $data['code']]);
        if ($stmt->fetch()) {
            return false;
        }
        
        // 插入新验证码
        $stmt = $this->pdo->prepare(
            "INSERT INTO verification_codes (user_id, monitor_id, phone, code, message, source_url, created_at)
             VALUES (:user_id, 0, :phone, :code, :message, :source, NOW())"
        );
        
        $stmt->execute([
            ':user_id' => $platform['user_id'],
            ':phone' => $data['phone'],
            ':code' => $data['code'],
            ':message' => json_encode($data['raw'] ?? []),
            ':source' => $platform['name']
        ]);
        
        return true;
    }
    
    private function logFetch($platformId, $fetchType, $status, $codesFound, $codesNew, $errorMessage, $responseTime) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO platform_fetch_logs (platform_id, fetch_type, status, codes_found, codes_new, error_message, response_time, created_at)
             VALUES (:platform_id, :fetch_type, :status, :codes_found, :codes_new, :error_message, :response_time, NOW())"
        );
        
        $stmt->execute([
            ':platform_id' => $platformId,
            ':fetch_type' => $fetchType,
            ':status' => $status,
            ':codes_found' => $codesFound,
            ':codes_new' => $codesNew,
            ':error_message' => $errorMessage ?: null,
            ':response_time' => $responseTime
        ]);
    }
    
    private function success($data, $message = 'Success') {
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ], JSON_UNESCAPED_UNICODE);
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
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    $manager = new PlatformManager();
    $manager->handle();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 500,
            'message' => 'Internal server error: ' . $e->getMessage()
        ]
    ], JSON_UNESCAPED_UNICODE);
}
