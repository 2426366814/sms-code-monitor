<?php
/**
 * 修复测试数据 - 确保status是success
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/backend/config/config.php';
require_once __DIR__ . '/backend/utils/Database.php';

echo "=== 修复测试数据 ===\n\n";

try {
    $db = Database::getInstance();
    
    // 查找有last_extracted_code但status不是success的监控项
    $monitors = $db->fetchAll(
        "SELECT * FROM monitors WHERE last_extracted_code IS NOT NULL AND status != 'success'"
    );
    
    if (empty($monitors)) {
        echo "没有需要修复的监控项\n";
    } else {
        echo "找到 " . count($monitors) . " 个需要修复的监控项:\n";
        foreach ($monitors as $m) {
            echo "  - ID: {$m['id']}, 手机: {$m['phone']}, 当前状态: {$m['status']}, 验证码: {$m['last_extracted_code']}\n";
            
            // 更新为success
            $db->update('monitors', ['status' => 'success'], ['id' => $m['id']]);
            echo "    ✅ 已修复为 success\n";
        }
    }
    
    echo "\n=== 验证修复结果 ===\n";
    $fixed = $db->fetchAll("SELECT id, phone, status, last_extracted_code, code_timestamp FROM monitors WHERE status = 'success'");
    if ($fixed) {
        foreach ($fixed as $m) {
            echo "ID: {$m['id']}, 手机: {$m['phone']}, 状态: {$m['status']}, 验证码: {$m['last_extracted_code']}, 时间戳: {$m['code_timestamp']}\n";
        }
    }
    
    echo "\n✅ 完成！\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
