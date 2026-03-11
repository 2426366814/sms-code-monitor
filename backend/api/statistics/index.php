<?php
/**
 * 用户统计API
 * 用于获取用户的统计数据
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/JWT.php';
require_once __DIR__ . '/../../utils/Response.php';

class UserStatistics {
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
    
    public function handle() {
        $action = $_GET['action'] ?? 'overview';
        
        switch ($action) {
            case 'overview':
                $this->getOverview();
                break;
            case 'trend':
                $this->getTrend();
                break;
            case 'platforms':
                $this->getPlatformStats();
                break;
            default:
                Response::error('Invalid action', 400);
        }
    }
    
    private function getOverview() {
        $today = date('Y-m-d');
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $monthStart = date('Y-m-01');
        
        $todayCodes = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM codes c 
             INNER JOIN monitors m ON c.monitor_id = m.id 
             WHERE m.user_id = :user_id AND DATE(c.created_at) = :today",
            [':user_id' => $this->userId, ':today' => $today]
        );
        
        $weekCodes = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM codes c 
             INNER JOIN monitors m ON c.monitor_id = m.id 
             WHERE m.user_id = :user_id AND DATE(c.created_at) >= :week_start",
            [':user_id' => $this->userId, ':week_start' => $weekStart]
        );
        
        $monthCodes = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM codes c 
             INNER JOIN monitors m ON c.monitor_id = m.id 
             WHERE m.user_id = :user_id AND DATE(c.created_at) >= :month_start",
            [':user_id' => $this->userId, ':month_start' => $monthStart]
        );
        
        $totalCodes = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM codes c 
             INNER JOIN monitors m ON c.monitor_id = m.id 
             WHERE m.user_id = :user_id",
            [':user_id' => $this->userId]
        );
        
        $totalPhones = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM monitors WHERE user_id = :user_id",
            [':user_id' => $this->userId]
        );
        
        $activeMonitors = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM monitors WHERE user_id = :user_id AND status = 'success'",
            [':user_id' => $this->userId]
        );
        
        $totalApiKeys = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM api_keys WHERE user_id = :user_id",
            [':user_id' => $this->userId]
        );
        
        $tableExists = $this->db->fetchOne("SHOW TABLES LIKE 'sms_platforms'");
        $totalPlatforms = ['count' => 0];
        if ($tableExists) {
            $totalPlatforms = $this->db->fetchOne(
                "SELECT COUNT(*) as count FROM sms_platforms WHERE user_id = :user_id",
                [':user_id' => $this->userId]
            );
        }
        
        Response::success([
            'today_codes' => (int)($todayCodes['count'] ?? 0),
            'week_codes' => (int)($weekCodes['count'] ?? 0),
            'month_codes' => (int)($monthCodes['count'] ?? 0),
            'total_codes' => (int)($totalCodes['count'] ?? 0),
            'total_phones' => (int)($totalPhones['count'] ?? 0),
            'active_monitors' => (int)($activeMonitors['count'] ?? 0),
            'total_api_keys' => (int)($totalApiKeys['count'] ?? 0),
            'total_platforms' => (int)($totalPlatforms['count'] ?? 0)
        ], 'Success');
    }
    
    private function getTrend() {
        $days = intval($_GET['days'] ?? 7);
        $days = min(30, max(1, $days));
        
        $trend = $this->db->fetchAll(
            "SELECT DATE(c.created_at) as date, COUNT(*) as count 
             FROM codes c 
             INNER JOIN monitors m ON c.monitor_id = m.id 
             WHERE m.user_id = :user_id AND c.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY DATE(c.created_at) 
             ORDER BY date ASC",
            [':user_id' => $this->userId, ':days' => $days]
        );
        
        $result = [];
        $dateMap = [];
        
        foreach ($trend as $item) {
            $dateMap[$item['date']] = (int)$item['count'];
        }
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $result[] = [
                'date' => $date,
                'count' => $dateMap[$date] ?? 0
            ];
        }
        
        Response::success(['trend' => $result], 'Success');
    }
    
    private function getPlatformStats() {
        $platformStats = $this->db->fetchAll(
            "SELECT m.url as platform, COUNT(*) as count 
             FROM codes c 
             INNER JOIN monitors m ON c.monitor_id = m.id 
             WHERE m.user_id = :user_id AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY m.url 
             ORDER BY count DESC 
             LIMIT 10",
            [':user_id' => $this->userId]
        );
        
        Response::success(['platforms' => $platformStats], 'Success');
    }
}

try {
    $stats = new UserStatistics();
    $stats->handle();
} catch (Exception $e) {
    Response::error('Internal server error: ' . $e->getMessage(), 500);
}
