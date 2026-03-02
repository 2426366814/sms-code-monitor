<?php
/**
 * 系统检查脚本 - check-cccp.php
 * 用于检查系统运行状态、数据库连接、用户信息等
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/Response.php';

$action = $_GET['action'] ?? 'all';

try {
    $db = Database::getInstance();
    $results = [];
    
    switch ($action) {
        case 'db':
            $results = checkDatabase($db);
            break;
        case 'users':
            $results = checkUsers($db);
            break;
        case 'monitors':
            $results = checkMonitors($db);
            break;
        case 'codes':
            $results = checkCodes($db);
            break;
        case 'tables':
            $results = checkTables($db);
            break;
        case 'all':
        default:
            $results = [
                'timestamp' => date('Y-m-d H:i:s'),
                'server' => [
                    'php_version' => PHP_VERSION,
                    'server_time' => date('Y-m-d H:i:s'),
                    'timezone' => date_default_timezone_get(),
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time')
                ],
                'database' => checkDatabase($db),
                'tables' => checkTables($db),
                'users' => checkUsers($db),
                'monitors' => checkMonitors($db),
                'codes' => checkCodes($db)
            ];
            break;
    }
    
    Response::success($results, '检查完成');
    
} catch (Exception $e) {
    Response::error('检查失败: ' . $e->getMessage(), 500);
}

function checkDatabase($db) {
    try {
        $pdo = $db->getPdo();
        $stmt = $pdo->query('SELECT VERSION() as version');
        $version = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'status' => 'ok',
            'message' => '数据库连接正常',
            'version' => $version['version'] ?? 'unknown'
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

function checkTables($db) {
    $tables = ['users', 'monitors', 'verification_codes', 'api_keys', 'sms_platforms'];
    $result = [];
    
    foreach ($tables as $table) {
        try {
            $count = $db->count($table);
            $result[$table] = [
                'exists' => true,
                'count' => $count
            ];
        } catch (Exception $e) {
            $result[$table] = [
                'exists' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    return $result;
}

function checkUsers($db) {
    try {
        $total = $db->count('users');
        $admins = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_admin = 1");
        $active = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
        
        return [
            'total' => $total,
            'admins' => (int)($admins['count'] ?? 0),
            'active' => (int)($active['count'] ?? 0)
        ];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function checkMonitors($db) {
    try {
        $total = $db->count('monitors');
        $byStatus = $db->fetchAll("SELECT status, COUNT(*) as count FROM monitors GROUP BY status");
        
        $statusCount = [];
        foreach ($byStatus as $row) {
            $statusCount[$row['status']] = (int)$row['count'];
        }
        
        return [
            'total' => $total,
            'by_status' => $statusCount
        ];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function checkCodes($db) {
    try {
        $total = $db->count('verification_codes');
        $today = $db->fetchOne("SELECT COUNT(*) as count FROM verification_codes WHERE DATE(created_at) = CURDATE()");
        $week = $db->fetchOne("SELECT COUNT(*) as count FROM verification_codes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        
        return [
            'total' => $total,
            'today' => (int)($today['count'] ?? 0),
            'this_week' => (int)($week['count'] ?? 0)
        ];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}
