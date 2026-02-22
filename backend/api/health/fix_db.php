<?php
/**
 * 数据库修复 API
 * 用于检查和修复远程服务器数据库问题
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../config/config.php';
require_once '../../utils/Database.php';

try {
    $db = Database::getInstance();
    $results = [];
    
    // 1. 检查 users 表结构
    $results['step1_check_users_table'] = [];
    $columns = $db->fetchAll("SHOW COLUMNS FROM users");
    $results['step1_check_users_table']['columns'] = array_map(function($col) {
        return $col['Field'] . ' (' . $col['Type'] . ')';
    }, $columns);
    
    // 2. 检查是否有 is_admin 字段
    $hasIsAdmin = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'is_admin') {
            $hasIsAdmin = true;
            break;
        }
    }
    
    // 3. 如果没有 is_admin 字段，添加它
    if (!$hasIsAdmin) {
        $results['step2_add_is_admin'] = 'Adding is_admin column...';
        try {
            $db->query("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0");
            $results['step2_add_is_admin'] = 'is_admin column added successfully';
        } catch (Exception $e) {
            $results['step2_add_is_admin'] = 'Error adding column: ' . $e->getMessage();
        }
    } else {
        $results['step2_add_is_admin'] = 'is_admin column already exists';
    }
    
    // 4. 检查现有用户
    $results['step3_check_users'] = [];
    $users = $db->fetchAll("SELECT id, username, email, is_admin, status FROM users");
    $results['step3_check_users']['count'] = count($users);
    $results['step3_check_users']['users'] = $users;
    
    // 5. 检查是否有 admin 用户
    $adminUser = $db->fetchOne("SELECT * FROM users WHERE username = 'admin'");
    
    if ($adminUser) {
        $results['step4_admin_user'] = 'Admin user exists';
        $results['step4_admin_user_data'] = [
            'id' => $adminUser['id'],
            'username' => $adminUser['username'],
            'email' => $adminUser['email'],
            'is_admin' => $adminUser['is_admin'] ?? 0,
            'status' => $adminUser['status']
        ];
        
        // 更新 admin 用户的密码和 is_admin
        $newPasswordHash = password_hash('admin', PASSWORD_DEFAULT);
        $db->query("UPDATE users SET password_hash = ?, is_admin = 1 WHERE username = 'admin'", [$newPasswordHash]);
        $results['step5_update_admin'] = 'Admin password updated to "admin"';
    } else {
        // 创建 admin 用户
        $results['step4_admin_user'] = 'Admin user does not exist, creating...';
        $newPasswordHash = password_hash('admin', PASSWORD_DEFAULT);
        $db->query(
            "INSERT INTO users (username, email, password_hash, status, is_admin, created_at, updated_at) VALUES (?, ?, ?, 'active', 1, NOW(), NOW())",
            ['admin', 'admin@example.com', $newPasswordHash]
        );
        $results['step5_create_admin'] = 'Admin user created with password "admin"';
    }
    
    // 6. 最终验证
    $finalCheck = $db->fetchOne("SELECT id, username, email, is_admin, status FROM users WHERE username = 'admin'");
    $results['step6_final_check'] = $finalCheck;
    
    // 7. 测试密码验证
    $testUser = $db->fetchOne("SELECT * FROM users WHERE username = 'admin'");
    $passwordValid = password_verify('admin', $testUser['password_hash']);
    $results['step7_password_test'] = [
        'password_to_test' => 'admin',
        'is_valid' => $passwordValid
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Database fix completed',
        'results' => $results
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
