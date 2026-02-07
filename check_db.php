<?php
/**
 * 检查数据库表结构
 */

require_once 'backend/config/config.php';
require_once 'backend/utils/Database.php';

$config = require 'backend/config/config.php';
$database = Database::getInstance();

try {
    // 检查users表结构
    $sql = "DESCRIBE users";
    $columns = $database->fetchAll($sql);
    
    echo "users表结构：\n";
    echo str_repeat("-", 50) . "\n";
    foreach ($columns as $col) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }
    
    // 检查是否有is_admin字段
    $hasIsAdmin = false;
    foreach ($columns as $col) {
        if ($col['Field'] == 'is_admin') {
            $hasIsAdmin = true;
            break;
        }
    }
    
    if (!$hasIsAdmin) {
        echo "\n缺少is_admin字段，正在添加...\n";
        $sql = "ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0 AFTER status";
        $database->query($sql);
        echo "is_admin字段添加成功！\n";
        
        // 设置admin为管理员
        $sql = "UPDATE users SET is_admin = 1 WHERE username = 'admin'";
        $database->query($sql);
        echo "admin用户已设置为管理员！\n";
    }
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
