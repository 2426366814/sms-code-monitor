<?php
/**
 * API测试脚本
 */

// 测试数据库连接
echo "=== 测试数据库连接 ===\n";

try {
    $config = require 'backend/config/config.php';
    $dbConfig = $config['database'];
    
    echo "数据库配置:\n";
    echo "  主机: {$dbConfig['host']}\n";
    echo "  端口: {$dbConfig['port']}\n";
    echo "  数据库: {$dbConfig['database']}\n";
    echo "  用户名: {$dbConfig['username']}\n";
    
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ 数据库连接成功\n";
    
    // 检查用户表
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  用户表记录数: {$result['count']}\n";
    
    // 检查默认用户
    $stmt = $pdo->query("SELECT username, role FROM users WHERE username IN ('admin', 'user')");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "  默认用户:\n";
    foreach ($users as $user) {
        echo "    - {$user['username']} ({$user['role']})\n";
    }
    
} catch (Exception $e) {
    echo "✗ 数据库连接失败: " . $e->getMessage() . "\n";
}

echo "\n=== 测试API响应 ===\n";

// 测试登录API
$loginData = [
    'username' => 'user',
    'password' => 'user123'
];

$ch = curl_init('http://localhost:8080/backend/api/auth/index.php/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "登录API测试:\n";
echo "  HTTP状态码: {$httpCode}\n";
echo "  响应内容: " . substr($response, 0, 200) . "\n";

if ($httpCode == 200) {
    $data = json_decode($response, true);
    if ($data && $data['success']) {
        echo "✓ 登录API正常工作\n";
    } else {
        echo "✗ 登录API返回错误: " . ($data['message'] ?? '未知错误') . "\n";
    }
} else {
    echo "✗ 登录API请求失败\n";
}

echo "\n=== 测试完成 ===\n";
