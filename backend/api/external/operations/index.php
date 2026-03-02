<?php
/**
 * 平台操作API
 * 包含手动触发获取、健康检查、重试机制
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/Database.php';
require_once __DIR__ . '/../../../utils/JWT.php';

class PlatformOperations {
    private $db;
    private $userId;
    private $pdo;
    
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
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'fetch':
                $this->manualFetch();
                break;
            case 'health':
                $this->healthCheck();
                break;
            case 'status':
                $this->getFetchStatus();
                break;
            case 'retry':
                $this->retryFailed();
                break;
            case 'stats':
                $this->getStats();
                break;
            case 'webhook':
                $this->configureWebhook();
                break;
            case 'schedule':
                $this->configureSchedule();
                break;
            default:
                $this->error(400, 'Invalid action');
        }
    }
    
    private function manualFetch() {
        $input = json_decode(file_get_contents('php://input'), true);
        $platformId = intval($input['platform_id'] ?? $_GET['platform_id'] ?? 0);
        
        if ($platformId) {
            $result = $this->fetchFromPlatform($platformId);
        } else {
            $result = $this->fetchAllPlatforms();
        }
        
        $this->success($result);
    }
    
    private function fetchFromPlatform($platformId) {
        $platform = $this->db->fetchOne(
            "SELECT * FROM sms_platforms WHERE id = ? AND user_id = ? AND is_active = 1",
            [$platformId, $this->userId]
        );
        
        if (!$platform) {
            $this->error(404, 'Platform not found or inactive');
        }
        
        $startTime = microtime(true);
        $codes = [];
        $errors = [];
        
        try {
            $codes = $this->fetchCodesFromPlatform($platform);
            $this->logFetch($platformId, 'success', count($codes));
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
            $this->logFetch($platformId, 'failed', 0, $e->getMessage());
        }
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        return [
            'platform_id' => $platformId,
            'platform_name' => $platform['name'],
            'codes_found' => count($codes),
            'codes' => $codes,
            'errors' => $errors,
            'duration_ms' => $duration
        ];
    }
    
    private function fetchAllPlatforms() {
        $platforms = $this->db->fetchAll(
            "SELECT * FROM sms_platforms WHERE user_id = ? AND is_active = 1",
            [$this->userId]
        );
        
        $results = [
            'total_platforms' => count($platforms),
            'total_codes' => 0,
            'platforms' => []
        ];
        
        foreach ($platforms as $platform) {
            try {
                $codes = $this->fetchCodesFromPlatform($platform);
                $results['platforms'][] = [
                    'platform_id' => $platform['id'],
                    'platform_name' => $platform['name'],
                    'codes_found' => count($codes),
                    'status' => 'success'
                ];
                $results['total_codes'] += count($codes);
                $this->logFetch($platform['id'], 'success', count($codes));
            } catch (Exception $e) {
                $results['platforms'][] = [
                    'platform_id' => $platform['id'],
                    'platform_name' => $platform['name'],
                    'codes_found' => 0,
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
                $this->logFetch($platform['id'], 'failed', 0, $e->getMessage());
            }
        }
        
        return $results;
    }
    
    private function fetchCodesFromPlatform($platform) {
        $codes = [];
        
        switch ($platform['platform_type']) {
            case 'yima':
                $codes = $this->fetchFromYima($platform);
                break;
            case 'jiecode':
                $codes = $this->fetchFromJiecode($platform);
                break;
            case 'custom':
                $codes = $this->fetchFromCustom($platform);
                break;
            default:
                throw new Exception("Unknown platform type: {$platform['platform_type']}");
        }
        
        foreach ($codes as $code) {
            $this->saveCode($platform['user_id'], $code, $platform['name']);
        }
        
        return $codes;
    }
    
    private function fetchFromYima($platform) {
        $url = rtrim($platform['api_url'], '/') . '/api/sms';
        $params = [
            'key' => $platform['api_key'],
            'action' => 'getMessages'
        ];
        
        $response = $this->httpGet($url . '?' . http_build_query($params));
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['data'])) {
            throw new Exception('Invalid response from Yima API');
        }
        
        $codes = [];
        foreach ($data['data'] as $msg) {
            if (preg_match('/验证码[：:]\s*(\d{4,8})/', $msg['content'], $matches)) {
                $codes[] = [
                    'phone' => $msg['phone'] ?? '',
                    'code' => $matches[1],
                    'content' => $msg['content'],
                    'time' => $msg['time'] ?? date('Y-m-d H:i:s')
                ];
            }
        }
        
        return $codes;
    }
    
    private function fetchFromJiecode($platform) {
        $url = rtrim($platform['api_url'], '/') . '/api/sms';
        $params = [
            'token' => $platform['api_key'],
            'action' => 'list'
        ];
        
        $response = $this->httpGet($url . '?' . http_build_query($params));
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['list'])) {
            throw new Exception('Invalid response from Jiecode API');
        }
        
        $codes = [];
        foreach ($data['list'] as $msg) {
            if (preg_match('/码[：:]\s*(\d{4,8})/', $msg['content'], $matches)) {
                $codes[] = [
                    'phone' => $msg['mobile'] ?? '',
                    'code' => $matches[1],
                    'content' => $msg['content'],
                    'time' => $msg['create_time'] ?? date('Y-m-d H:i:s')
                ];
            }
        }
        
        return $codes;
    }
    
    private function fetchFromCustom($platform) {
        $url = $platform['api_url'];
        $headers = [];
        
        if ($platform['api_key']) {
            $headers[] = 'Authorization: Bearer ' . $platform['api_key'];
        }
        
        $response = $this->httpGet($url, $headers);
        $data = json_decode($response, true);
        
        if (!$data) {
            throw new Exception('Invalid JSON response from custom API');
        }
        
        $codes = [];
        $messages = $data['messages'] ?? $data['data'] ?? $data['sms'] ?? [];
        
        foreach ($messages as $msg) {
            $content = $msg['content'] ?? $msg['text'] ?? $msg['message'] ?? '';
            if (preg_match('/(\d{4,8})/', $content, $matches)) {
                $codes[] = [
                    'phone' => $msg['phone'] ?? $msg['mobile'] ?? '',
                    'code' => $matches[1],
                    'content' => $content,
                    'time' => $msg['time'] ?? $msg['created_at'] ?? date('Y-m-d H:i:s')
                ];
            }
        }
        
        return $codes;
    }
    
    private function saveCode($userId, $code, $source) {
        $existing = $this->db->fetchOne(
            "SELECT id FROM verification_codes WHERE phone = ? AND code = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [$code['phone'], $code['code']]
        );
        
        if ($existing) {
            return;
        }
        
        $this->db->insert('verification_codes', [
            'user_id' => $userId,
            'monitor_id' => 0,
            'phone' => $code['phone'],
            'code' => $code['code'],
            'message' => $code['content'],
            'source_url' => $source,
            'created_at' => $code['time']
        ]);
    }
    
    private function healthCheck() {
        $platformId = intval($_GET['platform_id'] ?? 0);
        
        if ($platformId) {
            $result = $this->checkPlatformHealth($platformId);
        } else {
            $platforms = $this->db->fetchAll(
                "SELECT id, name, api_url, platform_type FROM sms_platforms WHERE user_id = ?",
                [$this->userId]
            );
            
            $result = [
                'total' => count($platforms),
                'healthy' => 0,
                'unhealthy' => 0,
                'platforms' => []
            ];
            
            foreach ($platforms as $platform) {
                $health = $this->checkPlatformHealth($platform['id']);
                $result['platforms'][] = $health;
                if ($health['status'] === 'healthy') {
                    $result['healthy']++;
                } else {
                    $result['unhealthy']++;
                }
            }
        }
        
        $this->success($result);
    }
    
    private function checkPlatformHealth($platformId) {
        $platform = $this->db->fetchOne(
            "SELECT * FROM sms_platforms WHERE id = ? AND user_id = ?",
            [$platformId, $this->userId]
        );
        
        if (!$platform) {
            return [
                'platform_id' => $platformId,
                'status' => 'not_found',
                'message' => 'Platform not found'
            ];
        }
        
        $startTime = microtime(true);
        $status = 'healthy';
        $message = 'OK';
        $responseTime = 0;
        
        try {
            $url = rtrim($platform['api_url'], '/') . '/health';
            $response = $this->httpGet($url, [], 5);
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            if (!$response) {
                $status = 'unhealthy';
                $message = 'Empty response';
            }
        } catch (Exception $e) {
            $status = 'unhealthy';
            $message = $e->getMessage();
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        }
        
        $this->db->update('sms_platforms', [
            'last_check' => date('Y-m-d H:i:s'),
            'health_status' => $status,
            'response_time' => $responseTime
        ], ['id' => $platformId]);
        
        return [
            'platform_id' => $platformId,
            'platform_name' => $platform['name'],
            'status' => $status,
            'message' => $message,
            'response_time_ms' => $responseTime,
            'last_check' => date('Y-m-d H:i:s')
        ];
    }
    
    private function getFetchStatus() {
        $logs = $this->db->fetchAll(
            "SELECT fl.*, sp.name as platform_name 
             FROM fetch_logs fl 
             LEFT JOIN sms_platforms sp ON fl.platform_id = sp.id 
             WHERE sp.user_id = ? 
             ORDER BY fl.created_at DESC 
             LIMIT 20",
            [$this->userId]
        );
        
        $this->success([
            'logs' => $logs,
            'total' => count($logs)
        ]);
    }
    
    private function retryFailed() {
        $input = json_decode(file_get_contents('php://input'), true);
        $logId = intval($input['log_id'] ?? 0);
        
        if (!$logId) {
            $this->error(400, 'Log ID is required');
        }
        
        $log = $this->db->fetchOne(
            "SELECT fl.*, sp.user_id FROM fetch_logs fl 
             JOIN sms_platforms sp ON fl.platform_id = sp.id 
             WHERE fl.id = ? AND sp.user_id = ?",
            [$logId, $this->userId]
        );
        
        if (!$log) {
            $this->error(404, 'Log not found');
        }
        
        $result = $this->fetchFromPlatform($log['platform_id']);
        
        $this->success([
            'original_log' => $log,
            'retry_result' => $result
        ]);
    }
    
    private function getStats() {
        $stats = [
            'platforms' => [
                'total' => $this->db->fetchOne("SELECT COUNT(*) as count FROM sms_platforms WHERE user_id = ?", [$this->userId])['count'],
                'active' => $this->db->fetchOne("SELECT COUNT(*) as count FROM sms_platforms WHERE user_id = ? AND is_active = 1", [$this->userId])['count']
            ],
            'phones' => [
                'total' => $this->db->fetchOne("SELECT COUNT(*) as count FROM platform_phones WHERE user_id = ?", [$this->userId])['count'],
                'waiting' => $this->db->fetchOne("SELECT COUNT(*) as count FROM platform_phones WHERE user_id = ? AND status = 'waiting'", [$this->userId])['count'],
                'received' => $this->db->fetchOne("SELECT COUNT(*) as count FROM platform_phones WHERE user_id = ? AND status = 'received'", [$this->userId])['count']
            ],
            'codes' => [
                'today' => $this->db->fetchOne("SELECT COUNT(*) as count FROM verification_codes WHERE user_id = ? AND DATE(created_at) = CURDATE()", [$this->userId])['count'],
                'week' => $this->db->fetchOne("SELECT COUNT(*) as count FROM verification_codes WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [$this->userId])['count'],
                'month' => $this->db->fetchOne("SELECT COUNT(*) as count FROM verification_codes WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [$this->userId])['count']
            ],
            'fetch_logs' => [
                'success' => $this->db->fetchOne("SELECT COUNT(*) as count FROM fetch_logs fl JOIN sms_platforms sp ON fl.platform_id = sp.id WHERE sp.user_id = ? AND fl.status = 'success'", [$this->userId])['count'],
                'failed' => $this->db->fetchOne("SELECT COUNT(*) as count FROM fetch_logs fl JOIN sms_platforms sp ON fl.platform_id = sp.id WHERE sp.user_id = ? AND fl.status = 'failed'", [$this->userId])['count']
            ]
        ];
        
        $this->success($stats);
    }
    
    private function configureWebhook() {
        $input = json_decode(file_get_contents('php://input'), true);
        $platformId = intval($input['platform_id'] ?? 0);
        $webhookUrl = trim($input['webhook_url'] ?? '');
        $events = $input['events'] ?? ['code_received'];
        
        if (!$platformId) {
            $this->error(400, 'Platform ID is required');
        }
        
        $this->verifyPlatform($platformId);
        
        $this->db->update('sms_platforms', [
            'webhook_url' => $webhookUrl,
            'webhook_events' => json_encode($events)
        ], ['id' => $platformId, 'user_id' => $this->userId]);
        
        $this->success([
            'platform_id' => $platformId,
            'webhook_url' => $webhookUrl,
            'events' => $events
        ], 'Webhook configured successfully');
    }
    
    private function configureSchedule() {
        $input = json_decode(file_get_contents('php://input'), true);
        $platformId = intval($input['platform_id'] ?? 0);
        $autoFetch = intval($input['auto_fetch'] ?? 0);
        $fetchInterval = intval($input['fetch_interval'] ?? 60);
        
        if (!$platformId) {
            $this->error(400, 'Platform ID is required');
        }
        
        $this->verifyPlatform($platformId);
        
        $this->db->update('sms_platforms', [
            'auto_fetch' => $autoFetch,
            'fetch_interval' => max(30, $fetchInterval)
        ], ['id' => $platformId, 'user_id' => $this->userId]);
        
        $this->success([
            'platform_id' => $platformId,
            'auto_fetch' => $autoFetch,
            'fetch_interval' => $fetchInterval
        ], 'Schedule configured successfully');
    }
    
    private function logFetch($platformId, $status, $codeCount, $error = null) {
        $this->db->insert('fetch_logs', [
            'platform_id' => $platformId,
            'status' => $status,
            'codes_found' => $codeCount,
            'error_message' => $error,
            'created_at' => date('Y-m-d H:i:s')
        ]);
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
    
    private function httpGet($url, $headers = [], $timeout = 10) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("HTTP request failed: $error");
        }
        
        return $response;
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
    $ops = new PlatformOperations();
    $ops->handle();
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
