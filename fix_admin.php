<?php
/**
 * 修复管理员权限脚本
 */

require_once 'backend/config/config.php';
require_once 'backend/utils/Database.php';

$config = require 'backend/config/config.php';
$database = Database::getInstance();

try {
    // 更新admin用户为管理员
    $sql = "UPDATE users SET is_admin = 1 WHERE username = 'admin'";
    $result = $database->query($sql);
    
    if ($result) {
        echo "管理员权限修复成功！\n";
        
        // 验证更新
        $sql = "SELECT id, username, is_admin FROM users WHERE username = 'admin'";
        $user = $database->fetchOne($sql);
        
        if ($user) {
            echo "用户信息：\n";
            echo "ID: " . $user['id'] . "\n";
            echo "用户名: " . $user['username'] . "\n";
            echo "是否管理员: " . ($user['is_admin'] ? '是' : '否') . "\n";
        }
    } else {
        echo "修复失败\n";
    }
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
