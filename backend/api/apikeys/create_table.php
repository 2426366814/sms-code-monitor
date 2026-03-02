<?php
require_once '../../config/config.php';
require_once '../../utils/Database.php';

$db = Database::getInstance();

try {
    $sql = "CREATE TABLE IF NOT EXISTS api_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        api_key VARCHAR(64) NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_used_at DATETIME NULL,
        is_active TINYINT(1) DEFAULT 1,
        INDEX idx_user_id (user_id),
        INDEX idx_api_key (api_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->query($sql);
    echo json_encode(['success' => true, 'message' => 'API密钥表创建成功']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '创建表失败: ' . $e->getMessage()]);
}
