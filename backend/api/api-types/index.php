<?php
/**
 * API类型管理API
 * 用于获取支持的API类型和配置
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 加载配置文件
require_once '../../config/config.php';
require_once '../../utils/Response.php';
require_once '../../utils/ApiAdapter.php';

// 初始化响应对象
$response = new Response();

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

// 处理不同的API端点
switch ($method) {
    case 'GET':
        // 获取API类型列表
        getApiTypes($response);
        break;
    
    default:
        $response->error('不支持的请求方法', 405);
        break;
}

/**
 * 获取支持的API类型列表
 * @param Response $response 响应对象
 */
function getApiTypes($response) {
    try {
        $types = ApiAdapter::getSupportedApiTypes();
        
        // 添加API格式示例
        $typesWithExamples = [
            [
                'type' => 'sms8',
                'name' => 'SMS8 API',
                'description' => 'sms8.net平台API',
                'url_pattern' => 'api.sms8.net',
                'url_example' => 'https://api.sms8.net/api/record?token=YOUR_TOKEN',
                'response_format' => 'JSON',
                'response_example' => json_encode([
                    'code' => 0,
                    'msg' => 'success',
                    'data' => [
                        'code' => '123456',
                        'code_time' => '2024-01-15 14:30:00',
                        'expired_date' => ''
                    ]
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ],
            [
                'type' => 'generic_json',
                'name' => '通用JSON API',
                'description' => '通用JSON格式API',
                'url_pattern' => '*',
                'url_example' => 'https://api.example.com/code?phone=PHONE&key=API_KEY',
                'response_format' => 'JSON',
                'response_example' => json_encode([
                    'code' => '123456',
                    'time' => '2024-01-15 14:30:00',
                    'message' => 'success'
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ],
            [
                'type' => 'raw_text',
                'name' => '纯文本API',
                'description' => '返回纯文本格式的API',
                'url_pattern' => '*',
                'url_example' => 'https://api.example.com/getcode?token=TOKEN',
                'response_format' => 'TEXT',
                'response_example' => "Your verification code is: 123456\nTime: 2024-01-15 14:30:00"
            ]
        ];
        
        $response->success([
            'types' => $typesWithExamples
        ], '获取成功');
        
    } catch (Exception $e) {
        $response->error('获取API类型失败: ' . $e->getMessage(), 500);
    }
}
