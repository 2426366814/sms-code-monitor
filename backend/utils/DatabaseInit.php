<?php

/**
 * 数据库初始化工具类
 * 用于自动创建所有必要的表和插入默认数据
 */
class DatabaseInit {
    
    /**
     * 初始化数据库
     * @param array $dbConfig 数据库配置
     * @return array 初始化结果
     */
    public static function init($dbConfig) {
        try {
            // 连接数据库
            $dsn = 'mysql:host=' . $dbConfig['host'] . ';dbname=' . $dbConfig['database'] . ';port=' . $dbConfig['port'] . ';charset=' . $dbConfig['charset'];
            $options = isset($dbConfig['options']) ? $dbConfig['options'] : [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
            
            // 读取SQL文件
            $sqlFile = __DIR__ . '/../config/database.sql';
            if (!file_exists($sqlFile)) {
                throw new Exception('数据库SQL文件不存在');
            }
            
            $sqlContent = file_get_contents($sqlFile);
            
            // 执行SQL语句，使用exec()方法执行所有语句，避免分割问题
            try {
                // 尝试执行所有SQL语句
                $pdo->exec($sqlContent);
                return [
                    'success' => true,
                    'message' => '数据库初始化成功'
                ];
            } catch (PDOException $e) {
                // 如果执行所有语句失败，尝试手动创建所有必要的表
                error_log('执行所有SQL语句失败: ' . $e->getMessage());
                
                // 创建所有必要的表
                self::createAllTables($pdo);
                
                // 插入默认数据
                self::insertDefaultData($pdo);
                
                return [
                    'success' => true,
                    'message' => '数据库初始化成功，但使用了手动创建表的方式'
                ];
            }
        } catch (Exception $e) {
            error_log('数据库初始化失败: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => '数据库初始化失败: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 手动创建所有必要的表
     * @param PDO $pdo 数据库连接对象
     */
    private static function createAllTables($pdo) {
        // 创建roles表
        $pdo->exec("CREATE TABLE IF NOT EXISTS `roles` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(50) NOT NULL UNIQUE,
            `description` TEXT,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 创建users表
        $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
            `last_login` TIMESTAMP NULL DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 创建permissions表
        $pdo->exec("CREATE TABLE IF NOT EXISTS `permissions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(50) NOT NULL UNIQUE,
            `description` TEXT,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 创建user_roles表
        $pdo->exec("CREATE TABLE IF NOT EXISTS `user_roles` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `role_id` INT NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `user_role_unique` (`user_id`, `role_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 创建role_permissions表
        $pdo->exec("CREATE TABLE IF NOT EXISTS `role_permissions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `role_id` INT NOT NULL,
            `permission_id` INT NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `role_permission_unique` (`role_id`, `permission_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 创建api_keys表
        $pdo->exec("CREATE TABLE IF NOT EXISTS `api_keys` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `api_key_id` VARCHAR(191) NOT NULL UNIQUE,
            `api_secret` VARCHAR(255) NOT NULL,
            `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            `expires_at` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `last_used_at` DATETIME DEFAULT NULL,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 创建monitors表
        $pdo->exec("CREATE TABLE IF NOT EXISTS `monitors` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `phone` VARCHAR(20) NOT NULL,
            `url` VARCHAR(255) NOT NULL,
            `status` ENUM('loading', 'success', 'no-code', 'error') NOT NULL DEFAULT 'no-code',
            `last_code` VARCHAR(255),
            `last_extracted_code` VARCHAR(10),
            `code_timestamp` BIGINT,
            `code_time_str` VARCHAR(50),
            `last_update` VARCHAR(50),
            `last_update_timestamp` BIGINT,
            `message` VARCHAR(255),
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 创建codes表
        $pdo->exec("CREATE TABLE IF NOT EXISTS `codes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `monitor_id` INT NOT NULL,
            `code` VARCHAR(10) NOT NULL,
            `original_text` TEXT NOT NULL,
            `extracted_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`monitor_id`) REFERENCES `monitors`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 创建logs表
        $pdo->exec("CREATE TABLE IF NOT EXISTS `logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `action` VARCHAR(50) NOT NULL,
            `details` TEXT,
            `ip_address` VARCHAR(45) NOT NULL,
            `user_agent` TEXT,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 创建stats表
        $pdo->exec("CREATE TABLE IF NOT EXISTS `stats` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `monitor_id` INT NOT NULL,
            `refresh_count` INT NOT NULL DEFAULT 0,
            `success_count` INT NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`monitor_id`) REFERENCES `monitors`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 创建system_settings表
        $pdo->exec("CREATE TABLE IF NOT EXISTS `system_settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `key` VARCHAR(50) NOT NULL UNIQUE,
            `value` TEXT,
            `description` TEXT,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 创建user_settings表
        $pdo->exec("CREATE TABLE IF NOT EXISTS `user_settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `notification_settings` TEXT NOT NULL,
            `api_settings` TEXT NOT NULL,
            `display_settings` TEXT NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `user_setting_unique` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 创建notifications表
        $pdo->exec("CREATE TABLE IF NOT EXISTS `notifications` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `type` VARCHAR(50) NOT NULL,
            `title` VARCHAR(100) NOT NULL,
            `content` TEXT NOT NULL,
            `status` ENUM('unread', 'read') NOT NULL DEFAULT 'unread',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 创建access_codes表
        $pdo->exec("CREATE TABLE IF NOT EXISTS `access_codes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `monitor_id` INT DEFAULT NULL,
            `access_code` VARCHAR(191) NOT NULL UNIQUE,
            `expires_at` DATETIME DEFAULT NULL,
            `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`monitor_id`) REFERENCES `monitors`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    
    /**
     * 插入默认数据
     * @param PDO $pdo 数据库连接对象
     */
    private static function insertDefaultData($pdo) {
        // 插入默认角色
        $pdo->exec("INSERT IGNORE INTO `roles` (`name`, `description`) VALUES
        ('admin', '系统管理员，拥有所有权限'),
        ('user', '普通用户，拥有基础权限')");
        
        // 插入默认权限 - 与database.sql一致，使用点表示法
        $pdo->exec("INSERT IGNORE INTO `permissions` (`name`, `description`) VALUES
        ('admin.access', '访问管理员面板'),
        ('user.manage', '管理用户'),
        ('role.manage', '管理角色和权限'),
        ('system.settings', '修改系统设置'),
        ('monitor.create', '创建监控项'),
        ('monitor.manage', '管理监控项'),
        ('code.view', '查看验证码'),
        ('statistics.view', '查看统计数据')");
        
        // 为管理员角色分配所有权限
        $pdo->exec("INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES
        (1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6), (1, 7), (1, 8),
        (2, 5), (2, 6), (2, 7), (2, 8)");
        
        // 插入默认管理员用户
        $passwordHash = '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm'; // admin
        $pdo->exec("INSERT IGNORE INTO `users` (`username`, `email`, `password_hash`, `status`) VALUES
        ('admin', 'admin@example.com', '$passwordHash', 'active')");
        
        // 为管理员用户分配管理员角色
        $pdo->exec("INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`) VALUES
        ((SELECT id FROM users WHERE username = 'admin'), 1)");
        
        // 插入默认系统设置
        $pdo->exec("INSERT IGNORE INTO `system_settings` (`key`, `value`, `description`) VALUES
        ('system_name', '多用户动态验证码监控系统', '系统名称'),
        ('system_version', '1.0.0', '系统版本'),
        ('max_monitors_per_user', '10', '每个用户最大监控项数量'),
        ('code_expiry_time', '300', '验证码过期时间（秒）')");
    }
    
    /**
     * 检查并初始化数据库
     * @param array $dbConfig 数据库配置
     * @return array 检查和初始化结果
     */
    public static function checkAndInit($dbConfig) {
        try {
            error_log("=== 开始数据库检查和初始化 ===");
            
            // 连接数据库
            $dsn = 'mysql:host=' . $dbConfig['host'] . ';dbname=' . $dbConfig['database'] . ';port=' . $dbConfig['port'] . ';charset=' . $dbConfig['charset'];
            $options = isset($dbConfig['options']) ? $dbConfig['options'] : [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
            error_log("数据库连接成功");
            
            // 检查所有必要的表是否存在
            $requiredTables = ['users', 'roles', 'permissions', 'user_roles', 'role_permissions', 'api_keys', 'monitors', 'codes', 'logs', 'stats', 'system_settings', 'user_settings', 'notifications', 'access_codes'];
            $allTablesExist = true;
            $missingTables = [];
            
            foreach ($requiredTables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() === 0) {
                    $allTablesExist = false;
                    $missingTables[] = $table;
                }
            }
            
            if (!$allTablesExist) {
                error_log("发现缺失的表: " . implode(', ', $missingTables));
                // 如果有表不存在，执行完整初始化
                self::createAllTables($pdo);
                self::insertDefaultData($pdo);
                return [
                    'success' => true,
                    'message' => '数据库初始化成功，创建了缺失的表: ' . implode(', ', $missingTables)
                ];
            } else {
                error_log("所有必要的表都已存在");
                // 检查是否有管理员用户
                $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
                $adminExists = $stmt->fetchColumn() > 0;
                
                if (!$adminExists) {
                    error_log("管理员用户不存在，创建管理员用户");
                    // 插入默认管理员用户
                    $passwordHash = '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm'; // admin
                    $pdo->exec("INSERT INTO users (username, email, password_hash, status) VALUES ('admin', 'admin@example.com', '$passwordHash', 'active')");
                    
                    // 为管理员用户分配管理员角色
                    $pdo->exec("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES ((SELECT id FROM users WHERE username = 'admin'), 1)");
                    
                    return [
                        'success' => true,
                        'message' => '管理员用户创建成功并分配了管理员角色'
                    ];
                } else {
                    // 检查管理员用户是否有管理员角色
                    $stmt = $pdo->query("SELECT COUNT(*) FROM user_roles WHERE user_id = (SELECT id FROM users WHERE username = 'admin') AND role_id = 1");
                    $hasAdminRole = $stmt->fetchColumn() > 0;
                    
                    if (!$hasAdminRole) {
                        error_log("管理员用户缺少管理员角色，分配角色");
                        // 为管理员用户分配管理员角色
                        $pdo->exec("INSERT INTO user_roles (user_id, role_id) VALUES ((SELECT id FROM users WHERE username = 'admin'), 1)");
                        return [
                            'success' => true,
                            'message' => '管理员用户已存在，为其分配了管理员角色'
                        ];
                    }
                }
            }
            
            error_log("数据库检查完成，无需进一步初始化");
            return [
                'success' => true,
                'message' => '数据库已初始化'
            ];
        } catch (Exception $e) {
            error_log('数据库检查失败: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => '数据库检查失败: ' . $e->getMessage()
            ];
        }
    }
}