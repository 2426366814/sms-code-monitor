<?php
/**
 * 手机号码管理API
 * 用于管理平台下的手机号码
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
require_once __DIR__ . '/../../../utils/Response.php';

class PhoneManager {
    private $db;
    private $userId;
    
    public function __construct() {
        $this->db = Database::getInstance();
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
        $phoneId = intval($parts[1] ?? 0);
        
        switch ($method) {
            case 'GET':
                if ($phoneId) {
                    $this->getPhone($platformId, $phoneId);
                } elseif ($platformId) {
                    $this->listPhones($platformId);
                } else {
                    $this->listAllPhones();
                }
                break;
            case 'POST':
                if ($platformId) {
                    $this->addPhone($platformId);
                } else {
                    $this->batchAddPhones();
                }
                break;
            case 'PUT':
                if ($phoneId) {
                    $this->updatePhone($platformId, $phoneId);
                }
                break;
            case 'DELETE':
                if ($phoneId) {
                    $this->deletePhone($platformId, $phoneId);
                } elseif ($platformId) {
                    $this->deleteAllPhones($platformId);
                }
                break;
            default:
                $this->error(405, 'Method not allowed');
        }
    }
    
    private function listAllPhones() {
        $page = max(1, intval($_GET['page'] ?? 1));
        $pageSize = min(100, max(1, intval($_GET['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;
        
        $where = "WHERE pp.user_id = ?";
        $params = [$this->userId];
        
        if ($phone = $_GET['phone'] ?? null) {
            $where .= " AND pp.phone LIKE ?";
            $params[] = "%$phone%";
        }
        
        if ($status = $_GET['status'] ?? null) {
            $where .= " AND pp.status = ?";
            $params[] = $status;
        }
        
        $countSql = "SELECT COUNT(*) FROM platform_phones pp $where";
        $total = $this->db->fetchOne($countSql, $params)['COUNT(*)'];
        
        $params[] = $offset;
        $params[] = $pageSize;
        
        $sql = "SELECT pp.*, sp.name as platform_name 
                FROM platform_phones pp 
                LEFT JOIN sms_platforms sp ON pp.platform_id = sp.id 
                $where 
                ORDER BY pp.created_at DESC 
                LIMIT ?, ?";
        
        $phones = $this->db->fetchAll($sql, $params);
        
        $this->success([
            'list' => $phones,
            'pagination' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => ceil($total / $pageSize)
            ]
        ]);
    }
    
    private function listPhones($platformId) {
        $this->verifyPlatform($platformId);
        
        $page = max(1, intval($_GET['page'] ?? 1));
        $pageSize = min(100, max(1, intval($_GET['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;
        
        $sql = "SELECT * FROM platform_phones 
                WHERE platform_id = ? AND user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?, ?";
        
        $phones = $this->db->fetchAll($sql, [$platformId, $this->userId, $offset, $pageSize]);
        
        $countSql = "SELECT COUNT(*) FROM platform_phones WHERE platform_id = ? AND user_id = ?";
        $total = $this->db->fetchOne($countSql, [$platformId, $this->userId])['COUNT(*)'];
        
        $this->success([
            'platform_id' => $platformId,
            'list' => $phones,
            'pagination' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => ceil($total / $pageSize)
            ]
        ]);
    }
    
    private function getPhone($platformId, $phoneId) {
        $this->verifyPlatform($platformId);
        
        $phone = $this->db->fetchOne(
            "SELECT * FROM platform_phones WHERE id = ? AND platform_id = ? AND user_id = ?",
            [$phoneId, $platformId, $this->userId]
        );
        
        if (!$phone) {
            $this->error(404, 'Phone not found');
        }
        
        $this->success($phone);
    }
    
    private function addPhone($platformId) {
        $this->verifyPlatform($platformId);
        
        $input = json_decode(file_get_contents('php://input'), true);
        $phone = trim($input['phone'] ?? '');
        
        if (empty($phone)) {
            $this->error(400, 'Phone number is required');
        }
        
        if (!preg_match('/^\d{10,15}$/', $phone)) {
            $this->error(400, 'Invalid phone number format');
        }
        
        $existing = $this->db->fetchOne(
            "SELECT id FROM platform_phones WHERE platform_id = ? AND phone = ? AND user_id = ?",
            [$platformId, $phone, $this->userId]
        );
        
        if ($existing) {
            $this->error(400, 'Phone already exists in this platform');
        }
        
        $id = $this->db->insert('platform_phones', [
            'platform_id' => $platformId,
            'user_id' => $this->userId,
            'phone' => $phone,
            'status' => 'waiting',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $this->success([
            'id' => $id,
            'phone' => $phone,
            'platform_id' => $platformId,
            'status' => 'waiting'
        ], 'Phone added successfully');
    }
    
    private function batchAddPhones() {
        $input = json_decode(file_get_contents('php://input'), true);
        $platformId = intval($input['platform_id'] ?? 0);
        $phones = $input['phones'] ?? [];
        
        if (!$platformId) {
            $this->error(400, 'Platform ID is required');
        }
        
        $this->verifyPlatform($platformId);
        
        if (empty($phones) || !is_array($phones)) {
            $this->error(400, 'Phones array is required');
        }
        
        $added = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($phones as $phone) {
            $phone = trim($phone);
            
            if (!preg_match('/^\d{10,15}$/', $phone)) {
                $errors[] = ['phone' => $phone, 'error' => 'Invalid format'];
                $skipped++;
                continue;
            }
            
            $existing = $this->db->fetchOne(
                "SELECT id FROM platform_phones WHERE platform_id = ? AND phone = ? AND user_id = ?",
                [$platformId, $phone, $this->userId]
            );
            
            if ($existing) {
                $skipped++;
                continue;
            }
            
            try {
                $this->db->insert('platform_phones', [
                    'platform_id' => $platformId,
                    'user_id' => $this->userId,
                    'phone' => $phone,
                    'status' => 'waiting',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $added++;
            } catch (Exception $e) {
                $errors[] = ['phone' => $phone, 'error' => $e->getMessage()];
                $skipped++;
            }
        }
        
        $this->success([
            'added' => $added,
            'skipped' => $skipped,
            'errors' => $errors
        ], "Batch import completed: $added added, $skipped skipped");
    }
    
    private function updatePhone($platformId, $phoneId) {
        $this->verifyPlatform($platformId);
        
        $input = json_decode(file_get_contents('php://input'), true);
        $status = $input['status'] ?? null;
        
        if (!in_array($status, ['waiting', 'received', 'expired', 'disabled'])) {
            $this->error(400, 'Invalid status');
        }
        
        $phone = $this->db->fetchOne(
            "SELECT id FROM platform_phones WHERE id = ? AND platform_id = ? AND user_id = ?",
            [$phoneId, $platformId, $this->userId]
        );
        
        if (!$phone) {
            $this->error(404, 'Phone not found');
        }
        
        $this->db->update('platform_phones', 
            ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => $phoneId]
        );
        
        $this->success(['status' => $status], 'Phone updated successfully');
    }
    
    private function deletePhone($platformId, $phoneId) {
        $this->verifyPlatform($platformId);
        
        $result = $this->db->delete('platform_phones', [
            'id' => $phoneId,
            'platform_id' => $platformId,
            'user_id' => $this->userId
        ]);
        
        if ($result > 0) {
            $this->success(null, 'Phone deleted successfully');
        } else {
            $this->error(404, 'Phone not found');
        }
    }
    
    private function deleteAllPhones($platformId) {
        $this->verifyPlatform($platformId);
        
        $result = $this->db->delete('platform_phones', [
            'platform_id' => $platformId,
            'user_id' => $this->userId
        ]);
        
        $this->success(['deleted' => $result], 'All phones deleted successfully');
    }
    
    private function verifyPlatform($platformId) {
        $platform = $this->db->fetchOne(
            "SELECT id FROM sms_platforms WHERE id = ? AND user_id = ?",
            [$platformId, $this->userId]
        );
        
        if (!$platform) {
            $this->error(404, 'Platform not found');
        }
    }
    
    private function success($data, $message = 'Success') {
        echo json_encode([
            'success' => true,
            'message' => $message,
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
    $manager = new PhoneManager();
    $manager->handle();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 500,
            'message' => 'Internal server error: ' . $e->getMessage()
        ]
    ]);
}
