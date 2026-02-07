<?php
/**
 * 配置文件模板
 * 复制此文件为 config.php 并修改相应配置
 */

return [
    // 数据库配置
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'sms_monitor',
        'username' => 'sms_user',
        'password' => 'your_password_here',
        'charset' => 'utf8mb4'
    ],
    
    // JWT配置
    'jwt' => [
        'secret' => 'your-secret-key-change-this-to-a-random-string',
        'algorithm' => 'HS256',
        'expiration' => 86400  // 24小时
    ],
    
    // 系统配置
    'system' => [
        'name' => '多用户动态验证码监控系统',
        'version' => '2.0.0',
        'debug' => false,
        'timezone' => 'Asia/Shanghai'
    ],
    
    // 安全配置
    'security' => [
        'password_min_length' => 6,
        'max_login_attempts' => 5,
        'lockout_duration' => 900  // 15分钟
    ],
    
    // 监控配置
    'monitor' => [
        'auto_refresh_interval' => 5,  // 秒
        'max_monitors_per_user' => 100,
        'code_retention_days' => 30
    ],
    
    // API配置
    'api' => [
        'rate_limit' => 100,  // 每分钟请求数
        'cors_origin' => '*'
    ]
];
