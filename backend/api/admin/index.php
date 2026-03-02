<?php
/**
 * 管理员API
 * 用于管理员管理用户、监控项等
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 加载配置文件
require_once '../../config/config.php';
require_once '../../utils/Database.php';
require_once '../../utils/JWT.php';
require_once '../../utils/Response.php';
require_once '../../models/UserModel.php';
require_once '../../models/MonitorModel.php';

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

// 获取请求动作
$action = isset($_GET['action']) ? $_GET['action'] : '';

// 初始化数据库连接
$config = require '../../config/config.php';
$database = Database::getInstance();
$userModel = new UserModel();
$monitorModel = new MonitorModel();
$jwt = new JWT($config['jwt']['secret'], $config['jwt']['algorithm']);

// 验证管理员权限
function verifyAdmin($jwt) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (empty($authHeader)) {
        Response::error('缺少认证令牌', 401);
        return false;
    }

    $token = str_replace('Bearer ', '', $authHeader);
    if (empty($token)) {
        Response::error('无效的认证令牌', 401);
        return false;
    }

    try {
        $payload = $jwt->validateToken($token);
        if (!$payload) {
            Response::error('无效的认证令牌', 401);
            return false;
        }
        
        // 检查是否是管理员
        if (!isset($payload['is_admin']) || !$payload['is_admin']) {
            Response::error('无权访问', 403);
            return false;
        }
        
        return $payload;
    } catch (Exception $e) {
        Response::error('认证失败: ' . $e->getMessage(), 500);
        return false;
    }
}

// 处理不同的API动作
if ($method === 'DELETE' && $action === 'delete-user') {
    $payload = verifyAdmin($jwt);
    if (!$payload) return;
    deleteUser($database);
} elseif ($method === 'POST' && $action === 'reset-password') {
    $payload = verifyAdmin($jwt);
    if (!$payload) return;
    resetUserPassword($database);
} elseif ($method === 'POST' && $action === 'toggle-user-status') {
    $payload = verifyAdmin($jwt);
    if (!$payload) return;
    toggleUserStatus($database);
} elseif ($method === 'POST' && $action === 'update-user') {
    $payload = verifyAdmin($jwt);
    if (!$payload) return;
    updateUser($database, $payload);
} elseif ($method === 'POST' && $action === 'add-user') {
    $payload = verifyAdmin($jwt);
    if (!$payload) return;
    addUser($database);
} else {
    switch ($action) {
        case 'users':
            $payload = verifyAdmin($jwt);
            if (!$payload) return;
            getUsers($database);
            break;
        
        case 'user-count':
            $payload = verifyAdmin($jwt);
            if (!$payload) return;
            getUserCount($database);
            break;
        
        case 'monitor-count':
            $payload = verifyAdmin($jwt);
            if (!$payload) return;
            getMonitorCount($database);
            break;
        
        case 'apikey-count':
            $payload = verifyAdmin($jwt);
            if (!$payload) return;
            getApiKeyCount($database);
            break;
        
        case 'today-code-count':
            $payload = verifyAdmin($jwt);
            if (!$payload) return;
            getTodayCodeCount($database);
            break;
        
        case 'monitors':
            $payload = verifyAdmin($jwt);
            if (!$payload) return;
            getMonitors($database);
            break;
        
        case 'system-info':
            $payload = verifyAdmin($jwt);
            if (!$payload) return;
            getSystemInfo($database);
            break;
        
        case 'codes':
            $payload = verifyAdmin($jwt);
            if (!$payload) return;
            getAllCodes($database);
            break;
        
        case 'all-apikeys':
            $payload = verifyAdmin($jwt);
            if (!$payload) return;
            getAllApiKeys($database);
            break;
        
        case 'statistics':
            $payload = verifyAdmin($jwt);
            if (!$payload) return;
            getStatistics($database);
            break;
        
        case 'logs':
            $payload = verifyAdmin($jwt);
            if (!$payload) return;
            getSystemLogs($database);
            break;
        
        case 'all-platforms':
            $payload = verifyAdmin($jwt);
            if (!$payload) return;
            getAllPlatforms($database);
            break;
        
        case 'delete-apikey':
            $payload = verifyAdmin($jwt);
            if (!$payload) return;
            deleteApiKey($database);
            break;
        
        case 'toggle-apikey':
            $payload = verifyAdmin($jwt);
            if (!$payload) return;
            toggleApiKeyStatus($database);
            break;
        
        case 'delete-monitor':
            $payload = verifyAdmin($jwt);
            if (!$payload) return;
            deleteMonitor($database);
            break;
        
        case 'clear-all-monitors':
            $payload = verifyAdmin($jwt);
            if (!$payload) return;
            clearAllMonitors($database);
            break;
        
        case 'save-settings':
            $payload = verifyAdmin($jwt);
            if (!$payload) return;
            saveSettings($database);
            break;
        
        case 'clear-old-codes':
            $payload = verifyAdmin($jwt);
            if (!$payload) return;
            clearOldCodes($database);
            break;
        
        case 'clear-all-data':
            $payload = verifyAdmin($jwt);
            if (!$payload) return;
            clearAllData($database);
            break;
        
        case 'export-all':
            $payload = verifyAdmin($jwt);
            if (!$payload) return;
            exportAllData($database);
            break;
        
        default:
            Response::error('无效的操作', 400);
            break;
    }
}

/**
 * 获取用户列表
 */
function getUsers($database) {
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT id, username, email, status, created_at FROM users ORDER BY id DESC LIMIT ? OFFSET ?";
        $users = $database->fetchAll($query, [$limit, $offset]);
        
        $countQuery = "SELECT COUNT(*) as count FROM users";
        $countResult = $database->fetchOne($countQuery);
        $total = $countResult ? (int)$countResult['count'] : 0;
        
        Response::success([
            'list' => $users,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ], '获取成功');
    } catch (Exception $e) {
        Response::error('获取用户列表失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 获取用户总数
 */
function getUserCount($database) {
    try {
        $query = "SELECT COUNT(*) as count FROM users";
        $result = $database->fetchOne($query);
        $count = $result ? (int)$result['count'] : 0;
        Response::success(['count' => $count], '获取成功');
    } catch (Exception $e) {
        Response::error('获取用户数量失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 获取监控总数
 */
function getMonitorCount($database) {
    try {
        $query = "SELECT COUNT(*) as count FROM monitors";
        $result = $database->fetchOne($query);
        $count = $result ? (int)$result['count'] : 0;
        Response::success(['count' => $count], '获取成功');
    } catch (Exception $e) {
        Response::error('获取监控数量失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 获取API密钥总数
 */
function getApiKeyCount($database) {
    try {
        $query = "SELECT COUNT(*) as count FROM api_keys";
        $result = $database->fetchOne($query);
        $count = $result ? (int)$result['count'] : 0;
        Response::success(['count' => $count], '获取成功');
    } catch (Exception $e) {
        Response::error('获取API密钥数量失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 获取今日验证码数量
 */
function getTodayCodeCount($database) {
    try {
        $today = date('Y-m-d');
        $query = "SELECT COUNT(*) as count FROM verification_codes WHERE DATE(created_at) = ?";
        $result = $database->fetchOne($query, [$today]);
        $count = $result ? (int)$result['count'] : 0;
        Response::success(['count' => $count], '获取成功');
    } catch (Exception $e) {
        Response::error('获取验证码数量失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 获取监控列表
 */
function getMonitors($database) {
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT m.*, u.username as user_name FROM monitors m JOIN users u ON m.user_id = u.id ORDER BY m.id DESC LIMIT ? OFFSET ?";
        $monitors = $database->fetchAll($query, [$limit, $offset]);
        
        $countQuery = "SELECT COUNT(*) as count FROM monitors";
        $countResult = $database->fetchOne($countQuery);
        $total = $countResult ? (int)$countResult['count'] : 0;
        
        Response::success([
            'list' => $monitors,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ], '获取成功');
    } catch (Exception $e) {
        Response::error('获取监控列表失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 删除用户
 */
function deleteUser($database) {
    try {
        $userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if (!$userId) {
            Response::error('缺少用户ID', 400);
            return;
        }
        
        // 不能删除自己
        $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        $token = str_replace('Bearer ', '', $authHeader);
        $config = require '../../config/config.php';
        $jwt = new JWT($config['jwt']['secret'], $config['jwt']['algorithm']);
        $payload = $jwt->validateToken($token);
        
        if ($payload && $payload['user_id'] == $userId) {
            Response::error('不能删除当前登录的用户', 400);
            return;
        }
        
        // 删除用户相关的监控项
        $database->query("DELETE FROM monitors WHERE user_id = ?", [$userId]);
        
        // 删除用户
        $stmt = $database->query("DELETE FROM users WHERE id = ?", [$userId]);
        $result = $stmt->rowCount() > 0;
        
        if ($result) {
            Response::success(null, '用户删除成功');
        } else {
            Response::error('用户删除失败', 500);
        }
    } catch (Exception $e) {
        Response::error('删除用户失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 重置用户密码
 */
function resetUserPassword($database) {
    try {
        $userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $data = json_decode(file_get_contents('php://input'), true);
        $newPassword = isset($data['new_password']) ? $data['new_password'] : '';
        
        if (!$userId) {
            Response::error('缺少用户ID', 400);
            return;
        }
        
        if (empty($newPassword) || strlen($newPassword) < 6) {
            Response::error('新密码长度不能少于6位', 400);
            return;
        }
        
        // 检查用户是否存在
        $user = $database->fetchOne("SELECT id FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            Response::error('用户不存在', 404);
            return;
        }
        
        // 更新密码
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $database->query("UPDATE users SET password_hash = ? WHERE id = ?", [$passwordHash, $userId]);
        $result = $stmt->rowCount() > 0;
        
        if ($result) {
            Response::success(null, '密码重置成功');
        } else {
            Response::error('密码重置失败', 500);
        }
    } catch (Exception $e) {
        Response::error('重置密码失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 切换用户状态（启用/禁用）
 */
function toggleUserStatus($database) {
    try {
        $userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if (!$userId) {
            Response::error('缺少用户ID', 400);
            return;
        }
        
        // 不能禁用自己
        $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        $token = str_replace('Bearer ', '', $authHeader);
        $config = require '../../config/config.php';
        $jwt = new JWT($config['jwt']['secret'], $config['jwt']['algorithm']);
        $payload = $jwt->validateToken($token);
        
        if ($payload && $payload['user_id'] == $userId) {
            Response::error('不能禁用当前登录的用户', 400);
            return;
        }
        
        // 获取当前用户状态
        $user = $database->fetchOne("SELECT status FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            Response::error('用户不存在', 404);
            return;
        }
        
        // 切换状态
        $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
        $stmt = $database->query("UPDATE users SET status = ? WHERE id = ?", [$newStatus, $userId]);
        $result = $stmt->rowCount() > 0;
        
        if ($result) {
            $statusText = $newStatus === 'active' ? '启用' : '禁用';
            Response::success(['status' => $newStatus], "用户已{$statusText}");
        } else {
            Response::error('状态更新失败', 500);
        }
    } catch (Exception $e) {
        Response::error('切换用户状态失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 更新用户信息
 */
function updateUser($database, $currentUser) {
    try {
        $userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$userId) {
            Response::error('缺少用户ID', 400);
            return;
        }
        
        // 检查用户是否存在
        $user = $database->fetchOne("SELECT id, is_admin FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            Response::error('用户不存在', 404);
            return;
        }
        
        // 防止取消自己的管理员权限
        if ($userId == $currentUser['user_id'] && isset($data['is_admin']) && !$data['is_admin']) {
            Response::error('不能取消自己的管理员权限', 403);
            return;
        }
        
        $updates = [];
        $params = [];
        
        // 更新用户名
        if (isset($data['username']) && !empty($data['username'])) {
            // 检查用户名是否已存在（排除当前用户）
            $existingUser = $database->fetchOne(
                "SELECT id FROM users WHERE username = ? AND id != ?",
                [$data['username'], $userId]
            );
            if ($existingUser) {
                Response::error('用户名已被其他用户使用', 400);
                return;
            }
            $updates[] = "username = ?";
            $params[] = $data['username'];
        }
        
        // 更新邮箱
        if (isset($data['email']) && !empty($data['email'])) {
            // 检查邮箱是否已存在（排除当前用户）
            $existingUser = $database->fetchOne(
                "SELECT id FROM users WHERE email = ? AND id != ?",
                [$data['email'], $userId]
            );
            if ($existingUser) {
                Response::error('邮箱已被其他用户使用', 400);
                return;
            }
            $updates[] = "email = ?";
            $params[] = $data['email'];
        }
        
        // 更新密码
        if (isset($data['password']) && !empty($data['password'])) {
            if (strlen($data['password']) < 6) {
                Response::error('密码长度不能少于6位', 400);
                return;
            }
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
            $updates[] = "password_hash = ?";
            $params[] = $passwordHash;
        }
        
        // 更新管理员状态
        if (isset($data['is_admin'])) {
            $updates[] = "is_admin = ?";
            $params[] = $data['is_admin'] ? 1 : 0;
        }
        
        // 更新用户状态
        if (isset($data['is_active'])) {
            $updates[] = "status = ?";
            $params[] = $data['is_active'] ? 'active' : 'inactive';
        }
        
        if (empty($updates)) {
            Response::error('没有要更新的内容', 400);
            return;
        }
        
        $params[] = $userId;
        $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $database->query($query, $params);
        $result = $stmt->rowCount() > 0;
        
        if ($result) {
            Response::success(null, '用户信息更新成功');
        } else {
            Response::error('用户信息更新失败', 500);
        }
    } catch (Exception $e) {
        Response::error('更新用户信息失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 添加新用户
 */
function addUser($database) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // 验证必填字段
        if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
            Response::error('缺少必要的用户信息', 400);
            return;
        }
        
        $username = trim($data['username']);
        $email = trim($data['email']);
        $password = $data['password'];
        $isAdmin = isset($data['is_admin']) ? (bool)$data['is_admin'] : false;
        
        // 验证数据
        if (empty($username) || empty($email) || empty($password)) {
            Response::error('用户名、邮箱和密码不能为空', 400);
            return;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('邮箱格式不正确', 400);
            return;
        }
        
        if (strlen($password) < 6) {
            Response::error('密码长度不能少于6位', 400);
            return;
        }
        
        // 检查用户名是否已存在
        $existingUser = $database->fetchOne("SELECT id FROM users WHERE username = ?", [$username]);
        if ($existingUser) {
            Response::error('用户名已存在', 400);
            return;
        }
        
        // 检查邮箱是否已存在
        $existingUser = $database->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existingUser) {
            Response::error('邮箱已被注册', 400);
            return;
        }
        
        // 创建用户
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $userData = [
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'status' => 'active',
            'is_admin' => $isAdmin ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $userId = $database->insert('users', $userData);
        
        if ($userId) {
            Response::success(['id' => $userId], '用户添加成功');
        } else {
            Response::error('用户添加失败', 500);
        }
    } catch (Exception $e) {
        Response::error('添加用户失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 获取系统信息
 */
function getSystemInfo($database) {
    try {
        $info = [
            'php_version' => PHP_VERSION,
            'server_time' => date('Y-m-d H:i:s'),
            'server_timezone' => date_default_timezone_get(),
            'database_type' => 'MySQL',
            'system_version' => 'v2.0.0'
        ];
        
        Response::success($info, '获取成功');
    } catch (Exception $e) {
        Response::error('获取系统信息失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 获取所有验证码记录
 */
function getAllCodes($database) {
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = ($page - 1) * $limit;
        $phone = isset($_GET['phone']) ? $_GET['phone'] : '';
        
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if (!empty($phone)) {
            $whereClause .= " AND vc.phone LIKE ?";
            $params[] = "%$phone%";
        }
        
        $query = "SELECT vc.*, u.username FROM verification_codes vc 
                  LEFT JOIN users u ON vc.user_id = u.id 
                  $whereClause 
                  ORDER BY vc.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $codes = $database->fetchAll($query, $params);
        
        $countQuery = "SELECT COUNT(*) as count FROM verification_codes vc $whereClause";
        $countParams = [];
        if (!empty($phone)) {
            $countParams[] = "%$phone%";
        }
        $countResult = $database->fetchOne($countQuery, $countParams);
        $total = $countResult ? (int)$countResult['count'] : 0;
        
        Response::success([
            'list' => $codes,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ], '获取成功');
    } catch (Exception $e) {
        Response::error('获取验证码记录失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 获取所有用户的API密钥
 */
function getAllApiKeys($database) {
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT ak.*, u.username FROM api_keys ak 
                  LEFT JOIN users u ON ak.user_id = u.id 
                  ORDER BY ak.created_at DESC LIMIT ? OFFSET ?";
        $apiKeys = $database->fetchAll($query, [$limit, $offset]);
        
        // 隐藏部分密钥
        foreach ($apiKeys as &$key) {
            $key['api_key_masked'] = substr($key['api_key'], 0, 8) . '...' . substr($key['api_key'], -4);
        }
        
        $countQuery = "SELECT COUNT(*) as count FROM api_keys";
        $countResult = $database->fetchOne($countQuery);
        $total = $countResult ? (int)$countResult['count'] : 0;
        
        Response::success([
            'list' => $apiKeys,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ], '获取成功');
    } catch (Exception $e) {
        Response::error('获取API密钥失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 获取统计数据
 */
function getStatistics($database) {
    try {
        $today = date('Y-m-d');
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $monthStart = date('Y-m-01');
        
        // 今日验证码
        $todayCodes = $database->fetchOne(
            "SELECT COUNT(*) as count FROM verification_codes WHERE DATE(created_at) = ?",
            [$today]
        );
        
        // 本周验证码
        $weekCodes = $database->fetchOne(
            "SELECT COUNT(*) as count FROM verification_codes WHERE DATE(created_at) >= ?",
            [$weekStart]
        );
        
        // 本月验证码
        $monthCodes = $database->fetchOne(
            "SELECT COUNT(*) as count FROM verification_codes WHERE DATE(created_at) >= ?",
            [$monthStart]
        );
        
        // 活跃用户（7天内有活动）
        $activeUsers = $database->fetchOne(
            "SELECT COUNT(DISTINCT user_id) as count FROM verification_codes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // 每日趋势（最近7天）
        $dailyTrend = $database->fetchAll(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM verification_codes 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
             GROUP BY DATE(created_at) 
             ORDER BY date ASC"
        );
        
        // 平台分布
        $platformDistribution = $database->fetchAll(
            "SELECT source_url as platform, COUNT(*) as count 
             FROM verification_codes 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY source_url 
             ORDER BY count DESC 
             LIMIT 10"
        );
        
        Response::success([
            'today_codes' => (int)($todayCodes['count'] ?? 0),
            'week_codes' => (int)($weekCodes['count'] ?? 0),
            'month_codes' => (int)($monthCodes['count'] ?? 0),
            'active_users' => (int)($activeUsers['count'] ?? 0),
            'daily_trend' => $dailyTrend,
            'platform_distribution' => $platformDistribution
        ], '获取成功');
    } catch (Exception $e) {
        Response::error('获取统计数据失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 获取系统日志
 */
function getSystemLogs($database) {
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = ($page - 1) * $limit;
        
        // 检查日志表是否存在
        $tableExists = $database->fetchOne("SHOW TABLES LIKE 'system_logs'");
        
        if (!$tableExists) {
            // 创建日志表
            $database->query("
                CREATE TABLE IF NOT EXISTS system_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NULL,
                    username VARCHAR(50) NULL,
                    action VARCHAR(100) NOT NULL,
                    details TEXT NULL,
                    ip_address VARCHAR(45) NULL,
                    user_agent VARCHAR(255) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_action (action),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            Response::success([
                'list' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit
            ], '获取成功');
            return;
        }
        
        $query = "SELECT * FROM system_logs ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $logs = $database->fetchAll($query, [$limit, $offset]);
        
        $countQuery = "SELECT COUNT(*) as count FROM system_logs";
        $countResult = $database->fetchOne($countQuery);
        $total = $countResult ? (int)$countResult['count'] : 0;
        
        Response::success([
            'list' => $logs,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ], '获取成功');
    } catch (Exception $e) {
        Response::error('获取系统日志失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 获取所有平台配置
 */
function getAllPlatforms($database) {
    try {
        $query = "SELECT sp.*, u.username FROM sms_platforms sp 
                  LEFT JOIN users u ON sp.user_id = u.id 
                  ORDER BY sp.created_at DESC";
        $platforms = $database->fetchAll($query);
        
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
        
        Response::success([
            'list' => $platforms,
            'stats' => $stats
        ], '获取成功');
    } catch (Exception $e) {
        Response::error('获取平台配置失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 删除API密钥
 */
function deleteApiKey($database) {
    try {
        $keyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if (!$keyId) {
            Response::error('缺少API密钥ID', 400);
            return;
        }
        
        $stmt = $database->query("DELETE FROM api_keys WHERE id = ?", [$keyId]);
        $result = $stmt->rowCount() > 0;
        
        if ($result) {
            Response::success(null, 'API密钥删除成功');
        } else {
            Response::error('API密钥不存在', 404);
        }
    } catch (Exception $e) {
        Response::error('删除API密钥失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 切换API密钥状态
 */
function toggleApiKeyStatus($database) {
    try {
        $keyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if (!$keyId) {
            Response::error('缺少API密钥ID', 400);
            return;
        }
        
        $key = $database->fetchOne("SELECT status FROM api_keys WHERE id = ?", [$keyId]);
        if (!$key) {
            Response::error('API密钥不存在', 404);
            return;
        }
        
        $newStatus = $key['status'] === 'active' ? 'inactive' : 'active';
        $stmt = $database->query("UPDATE api_keys SET status = ? WHERE id = ?", [$newStatus, $keyId]);
        
        Response::success(['status' => $newStatus], '状态更新成功');
    } catch (Exception $e) {
        Response::error('更新状态失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 删除监控项
 */
function deleteMonitor($database) {
    try {
        $monitorId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if (!$monitorId) {
            Response::error('缺少监控项ID', 400);
            return;
        }
        
        $database->query("DELETE FROM verification_codes WHERE monitor_id = ?", [$monitorId]);
        $stmt = $database->query("DELETE FROM monitors WHERE id = ?", [$monitorId]);
        $result = $stmt->rowCount() > 0;
        
        if ($result) {
            Response::success(null, '监控项删除成功');
        } else {
            Response::error('监控项不存在', 404);
        }
    } catch (Exception $e) {
        Response::error('删除监控项失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 清空所有监控项
 */
function clearAllMonitors($database) {
    try {
        $database->query("DELETE FROM verification_codes");
        $database->query("DELETE FROM monitors");
        Response::success(null, '所有监控项已清空');
    } catch (Exception $e) {
        Response::error('清空监控项失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 保存系统设置
 */
function saveSettings($database) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data)) {
            Response::error('没有设置数据', 400);
            return;
        }
        
        $tableExists = $database->fetchOne("SHOW TABLES LIKE 'system_settings'");
        
        if (!$tableExists) {
            $database->query("
                CREATE TABLE IF NOT EXISTS system_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) NOT NULL UNIQUE,
                    setting_value TEXT,
                    description VARCHAR(255),
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        
        foreach ($data as $key => $value) {
            $existing = $database->fetchOne("SELECT id FROM system_settings WHERE setting_key = ?", [$key]);
            
            if ($existing) {
                $database->query("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
            } else {
                $database->query("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)", [$key, $value]);
            }
        }
        
        Response::success(null, '设置保存成功');
    } catch (Exception $e) {
        Response::error('保存设置失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 清理旧验证码
 */
function clearOldCodes($database) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $days = isset($data['days']) ? (int)$data['days'] : 30;
        
        if ($days < 1) {
            $days = 30;
        }
        
        $stmt = $database->query("DELETE FROM verification_codes WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)", [$days]);
        $deleted = $stmt->rowCount();
        
        Response::success(['deleted' => $deleted], "已清理 {$deleted} 条旧验证码");
    } catch (Exception $e) {
        Response::error('清理验证码失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 清空所有数据
 */
function clearAllData($database) {
    try {
        $database->query("DELETE FROM verification_codes");
        $database->query("DELETE FROM monitors");
        $database->query("DELETE FROM api_keys");
        $database->query("DELETE FROM sms_platforms");
        $database->query("DELETE FROM platform_phones");
        
        Response::success(null, '所有数据已清空');
    } catch (Exception $e) {
        Response::error('清空数据失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 导出所有数据
 */
function exportAllData($database) {
    try {
        $users = $database->fetchAll("SELECT id, username, email, status, is_admin, created_at FROM users");
        $monitors = $database->fetchAll("SELECT * FROM monitors");
        $codes = $database->fetchAll("SELECT * FROM verification_codes ORDER BY created_at DESC LIMIT 1000");
        $apiKeys = $database->fetchAll("SELECT id, user_id, name, status, created_at FROM api_keys");
        $platforms = $database->fetchAll("SELECT * FROM sms_platforms");
        
        $exportData = [
            'export_time' => date('Y-m-d H:i:s'),
            'users' => $users,
            'monitors' => $monitors,
            'codes' => $codes,
            'api_keys' => $apiKeys,
            'platforms' => $platforms
        ];
        
        Response::success($exportData, '导出成功');
    } catch (Exception $e) {
        Response::error('导出数据失败: ' . $e->getMessage(), 500);
    }
}
?>
