<?php
/**
 * 测试脚本：手动添加测试验证码数据
 * 用于验证验证码弹窗功能
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/backend/config/config.php';
require_once __DIR__ . '/backend/utils/Database.php';

echo "=== 添加测试验证码数据 ===\n\n";

try {
    $db = Database::getInstance();
    
    // 查找测试用户（先找 admin，再找其他用户）
    $user = $db->fetchOne("SELECT id FROM users WHERE username = ?", ['admin']);
    if (!$user) {
        $user = $db->fetchOne("SELECT id FROM users ORDER BY id ASC LIMIT 1");
    }
    
    if (!$user) {
        echo "错误：找不到任何用户\n";
        exit(1);
    }
    
    $userId = $user['id'];
    echo "找到用户 ID: {$userId}\n\n";
    
    // 查找该用户的监控项
    $monitors = $db->fetchAll("SELECT * FROM monitors WHERE user_id = ? ORDER BY id DESC", [$userId]);
    
    if (empty($monitors)) {
        echo "错误：该用户没有监控项，请先添加监控\n";
        exit(1);
    }
    
    echo "找到 " . count($monitors) . " 个监控项:\n";
    foreach ($monitors as $m) {
        echo "  - ID: {$m['id']}, 手机: {$m['phone']}, 状态: {$m['status']}\n";
    }
    echo "\n";
    
    // 选择第一个监控项来添加测试数据
    $monitor = $monitors[0];
    $monitorId = $monitor['id'];
    $phone = $monitor['phone'];
    
    echo "选择监控项: ID={$monitorId}, 手机={$phone}\n\n";
    
    // 生成测试验证码
    $testCode = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    $now = time();
    $nowStr = date('Y-m-d H:i:s');
    
    echo "生成测试验证码: {$testCode}\n";
    echo "时间戳: {$now} ({$nowStr})\n\n";
    
    // 更新监控项
    $updateData = [
        'status' => 'success',
        'last_extracted_code' => $testCode,
        'code_timestamp' => $now * 1000,
        'code_time_str' => $nowStr,
        'last_update' => $nowStr,
        'last_update_timestamp' => $now * 1000,
        'message' => '测试验证码'
    ];
    
    $db->update('monitors', $updateData, ['id' => $monitorId]);
    echo "✅ 已更新监控项状态\n";
    
    // 添加到验证码历史表
    $db->insert('verification_codes', [
        'user_id' => $userId,
        'monitor_id' => $monitorId,
        'phone' => $phone,
        'code' => $testCode,
        'message' => '测试数据',
        'source_url' => 'https://example.com/test',
        'received_at' => $nowStr
    ]);
    echo "✅ 已添加到验证码历史表\n\n";
    
    echo "=== 完成！===\n";
    echo "现在刷新前端页面，应该会弹出验证码通知！\n";
    echo "验证码: {$testCode}\n";
    echo "手机号: {$phone}\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
