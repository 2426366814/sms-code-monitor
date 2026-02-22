<?php
/**
 * 系统修复API
 * 用于添加缺失的字段和删除错误文件
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/Database.php';

try {
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    $results = [];
    
    // === 文件系统修复 ===
    $webRoot = dirname(__DIR__, 2); // backend/api -> backend -> root
    
    // 删除错误的 index.php
    $indexPhpPath = $webRoot . '/index.php';
    if (file_exists($indexPhpPath)) {
        $content = file_get_contents($indexPhpPath);
        // 检查是否是错误的文件（包含 require_once 和 config.php）
        if (strpos($content, 'require_once') !== false && strpos($content, 'config.php') !== false) {
            if (@unlink($indexPhpPath)) {
                $results[] = '✅ 已删除错误的 index.php';
            } else {
                $results[] = '❌ 无法删除 index.php (权限不足)';
            }
        } else {
            $results[] = 'index.php 存在但可能是有效文件';
        }
    } else {
        $results[] = '✅ index.php 不存在';
    }
    
    // 创建 .htaccess
    $htaccessPath = $webRoot . '/.htaccess';
    if (!file_exists($htaccessPath)) {
        @file_put_contents($htaccessPath, "DirectoryIndex index.html index.php\n");
        $results[] = '✅ 已创建 .htaccess';
    }
    
    // === 数据库修复 ===
    
    // 检查并添加description字段
    $stmt = $pdo->query("SHOW COLUMNS FROM monitors LIKE 'description'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE monitors ADD COLUMN description VARCHAR(255) DEFAULT NULL COMMENT '描述'");
        $results[] = 'description字段已添加';
    } else {
        $results[] = 'description字段已存在';
    }
    
    // 检查并添加url字段
    $stmt = $pdo->query("SHOW COLUMNS FROM monitors LIKE 'url'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE monitors ADD COLUMN url VARCHAR(500) DEFAULT NULL COMMENT 'API URL'");
        $results[] = 'url字段已添加';
    } else {
        $results[] = 'url字段已存在';
    }
    
    // 返回表结构
    $stmt = $pdo->query("DESCRIBE monitors");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'messages' => $results,
        'columns' => $columns
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
