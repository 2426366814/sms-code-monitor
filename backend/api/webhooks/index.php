<?php
/**
 * Webhook API
 * 用于管理用户的Webhook推送配置
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/JWT.php';
require_once __DIR__ . '/../../utils/Response.php';

class WebhookManager {
    private $db;
    private $pdo;
    private $userId;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->pdo = Database::getInstance()->getPdo();
        $this->authenticate();
        $this->ensureTableExists();
    }
    
    private function authenticate() {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (empty($authHeader)) {
            Response::error('Authorization header required', 401);
        }
        
        $token = str_replace('Bearer ', '', $authHeader);
        $config = require __DIR__ . '/../../config/config.php';
        $jwt = new JWT($config['jwt']['secret'], $config['jwt']['algorithm']);
        
        try {
            $payload = $jwt->validateToken($token);
            if (!$payload) {
                Response::error('Invalid token', 401);
            }
            $this->userId = $payload['user_id'];
        } catch (Exception $e) {
            Response::error('Token validation failed', 401);
        }
    }
    
    private function ensureTableExists() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS webhooks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                url VARCHAR(500) NOT NULL,
                secret VARCHAR(255) NULL,
                is_active TINYINT(1) DEFAULT 1,
                events TEXT NULL COMMENT '触发事件类型',
                last_triggered_at DATETIME NULL,
                last_status VARCHAR(20) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_is_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    public function handle() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_GET['path'] ?? '';
        $parts = array_filter(explode('/', $path));
        
        $webhookId = intval($parts[0] ?? 0);
        $action = $parts[1] ?? '';
        
        switch ($method) {
            case 'GET':
                if ($webhookId && $action === 'logs') {
                    $this->getWebhookLogs($webhookId);
                } elseif ($webhookId) {
                    $this->getWebhook($webhookId);
                } else {
                    $this->listWebhooks();
                }
                break;
            case 'POST':
                if ($webhookId && $action === 'test') {
                    $this->testWebhook($webhookId);
                } else {
                    $this->createWebhook();
                }
                break;
            case 'PUT':
                if ($webhookId) {
                    $this->updateWebhook($webhookId);
                }
                break;
            case 'DELETE':
                if ($webhookId) {
                    $this->deleteWebhook($webhookId);
                }
                break;
            default:
                Response::error('Method not allowed', 405);
        }
    }
    
    private function listWebhooks() {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM webhooks WHERE user_id = :user_id ORDER BY created_at DESC"
        );
        $stmt->execute([':user_id' => $this->userId]);
        $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        Response::success(['list' => $webhooks], 'Success');
    }
    
    private function getWebhook($id) {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM webhooks WHERE id = :id AND user_id = :user_id"
        );
        $stmt->execute([':id' => $id, ':user_id' => $this->userId]);
        $webhook = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$webhook) {
            Response::error('Webhook not found', 404);
        }
        
        Response::success($webhook, 'Success');
    }
    
    private function createWebhook() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $name = trim($input['name'] ?? '');
        $url = trim($input['url'] ?? '');
        $secret = $input['secret'] ?? null;
        $events = $input['events'] ?? ['code_received'];
        $isActive = intval($input['is_active'] ?? 1);
        
        if (empty($name)) {
            Response::error('Webhook name is required', 400);
        }
        
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            Response::error('Valid URL is required', 400);
        }
        
        $stmt = $this->pdo->prepare(
            "INSERT INTO webhooks (user_id, name, url, secret, events, is_active, created_at)
             VALUES (:user_id, :name, :url, :secret, :events, :is_active, NOW())"
        );
        
        $stmt->execute([
            ':user_id' => $this->userId,
            ':name' => $name,
            ':url' => $url,
            ':secret' => $secret,
            ':events' => json_encode($events),
            ':is_active' => $isActive
        ]);
        
        $id = $this->pdo->lastInsertId();
        
        Response::success([
            'id' => intval($id),
            'name' => $name,
            'url' => $url
        ], 'Webhook created successfully');
    }
    
    private function updateWebhook($id) {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM webhooks WHERE id = :id AND user_id = :user_id"
        );
        $stmt->execute([':id' => $id, ':user_id' => $this->userId]);
        $webhook = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$webhook) {
            Response::error('Webhook not found', 404);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $updates = [];
        $params = [':id' => $id, ':user_id' => $this->userId];
        
        $allowedFields = ['name', 'url', 'secret', 'is_active', 'events'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                if ($field === 'events') {
                    $updates[] = "$field = :$field";
                    $params[":$field"] = json_encode($input[$field]);
                } else {
                    $updates[] = "$field = :$field";
                    $params[":$field"] = $input[$field];
                }
            }
        }
        
        if (empty($updates)) {
            Response::error('No fields to update', 400);
        }
        
        $sql = "UPDATE webhooks SET " . implode(', ', $updates) . " WHERE id = :id AND user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        Response::success(null, 'Webhook updated successfully');
    }
    
    private function deleteWebhook($id) {
        $stmt = $this->pdo->prepare(
            "DELETE FROM webhooks WHERE id = :id AND user_id = :user_id"
        );
        $stmt->execute([':id' => $id, ':user_id' => $this->userId]);
        
        if ($stmt->rowCount() > 0) {
            Response::success(null, 'Webhook deleted successfully');
        } else {
            Response::error('Webhook not found', 404);
        }
    }
    
    private function testWebhook($id) {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM webhooks WHERE id = :id AND user_id = :user_id"
        );
        $stmt->execute([':id' => $id, ':user_id' => $this->userId]);
        $webhook = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$webhook) {
            Response::error('Webhook not found', 404);
        }
        
        $testPayload = [
            'event' => 'test',
            'timestamp' => date('c'),
            'data' => [
                'phone' => '13800138000',
                'code' => '123456',
                'message' => 'This is a test webhook payload'
            ]
        ];
        
        $startTime = microtime(true);
        $result = $this->sendWebhook($webhook, $testPayload);
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        
        Response::success([
            'webhook_id' => $id,
            'status' => $result['success'] ? 'success' : 'failed',
            'response_time_ms' => $responseTime,
            'error' => $result['error'] ?? null
        ], 'Webhook test completed');
    }
    
    private function getWebhookLogs($id) {
        // 验证webhook所有权
        $stmt = $this->pdo->prepare(
            "SELECT id FROM webhooks WHERE id = :id AND user_id = :user_id"
        );
        $stmt->execute([':id' => $id, ':user_id' => $this->userId]);
        if (!$stmt->fetch()) {
            Response::error('Webhook not found', 404);
        }
        
        // 检查日志表是否存在
        $tableExists = $this->db->fetchOne("SHOW TABLES LIKE 'webhook_logs'");
        
        if (!$tableExists) {
            Response::success(['list' => []], 'Success');
            return;
        }
        
        $stmt = $this->pdo->prepare(
            "SELECT * FROM webhook_logs WHERE webhook_id = :id ORDER BY created_at DESC LIMIT 20"
        );
        $stmt->execute([':id' => $id]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        Response::success(['list' => $logs], 'Success');
    }
    
    public function sendWebhook($webhook, $payload) {
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'User-Agent: SMS-Monitor-Webhook/1.0'
        ];
        
        if (!empty($webhook['secret'])) {
            $signature = hash_hmac('sha256', json_encode($payload), $webhook['secret']);
            $headers[] = 'X-Webhook-Signature: ' . $signature;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $webhook['url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // 更新webhook状态
        $this->pdo->prepare(
            "UPDATE webhooks SET last_triggered_at = NOW(), last_status = ? WHERE id = ?"
        )->execute([$error ? 'failed' : 'success', $webhook['id']]);
        
        return [
            'success' => !$error && $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'error' => $error
        ];
    }
}

try {
    $manager = new WebhookManager();
    $manager->handle();
} catch (Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
