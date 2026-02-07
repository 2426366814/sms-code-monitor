<?php
/**
 * 批量导入API
 * 用于批量添加手机号和API密钥
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 加载配置文件
require_once '../../config/config.php';
require_once '../../utils/Database.php';
require_once '../../utils/JWT.php';
require_once '../../utils/Response.php';
require_once '../../models/UserModel.php';

// 初始化响应对象
$response = new Response();

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

// 获取请求路径
$path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
$path = trim($path, '/');
$parts = explode('/', $path);

// 初始化数据库连接
$config = require '../../config/config.php';
$database = Database::getInstance();
$userModel = new UserModel();
$jwt = new JWT($config['jwt']['secret'], $config['jwt']['algorithm']);

// 验证管理员权限
function verifyAdmin($jwt, $userModel, $response) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (empty($authHeader)) {
        $response->error('缺少认证令牌', 401);
        return false;
    }

    $token = str_replace('Bearer ', '', $authHeader);
    if (empty($token)) {
        $response->error('无效的认证令牌', 401);
        return false;
    }

    try {
        $payload = $jwt->validateToken($token);
        if (!$payload) {
            $response->error('无效的认证令牌', 401);
            return false;
        }

        // 检查用户角色
        $user = $userModel->getUserById($payload['user_id']);
        if (!$user || $user['role'] !== 'admin') {
            $response->error('无管理员权限', 403);
            return false;
        }

        return $payload;
    } catch (Exception $e) {
        $response->error('认证失败: ' . $e->getMessage(), 500);
        return false;
    }
}

// 处理不同的API端点
switch ($method) {
    case 'POST':
        // 验证管理员权限
        $payload = verifyAdmin($jwt, $userModel, $response);
        if (!$payload) {
            return;
        }

        switch ($parts[0] ?? '') {
            case 'phones':
                // 批量添加手机号
                bulkAddPhones($payload['user_id'], $database, $response);
                break;
            case 'api-keys':
                // 批量添加API密钥
                bulkAddApiKeys($payload['user_id'], $database, $response);
                break;
            default:
                $response->error('无效的API端点', 404);
                break;
        }
        break;

    default:
        $response->error('不支持的请求方法', 405);
        break;
}

/**
 * 批量添加手机号监控项
 * @param int $userId 用户ID
 * @param Database $database 数据库对象
 * @param Response $response 响应对象
 */
function bulkAddPhones($userId, $database, $response) {
    try {
        // 获取请求数据
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['phones']) || !is_array($data['phones']) || empty($data['phones'])) {
            $response->error('请提供手机号列表', 400);
            return;
        }

        $phones = $data['phones'];
        $results = [
            'success' => [],
            'failed' => []
        ];

        foreach ($phones as $phoneData) {
            // 支持字符串或对象格式
            if (is_string($phoneData)) {
                $phone = trim($phoneData);
                $url = '';
            } elseif (is_array($phoneData) && isset($phoneData['phone'])) {
                $phone = trim($phoneData['phone']);
                $url = isset($phoneData['url']) ? trim($phoneData['url']) : '';
            } else {
                $results['failed'][] = [
                    'data' => $phoneData,
                    'reason' => '格式错误'
                ];
                continue;
            }

            // 验证手机号
            if (empty($phone)) {
                $results['failed'][] = [
                    'phone' => $phone,
                    'reason' => '手机号为空'
                ];
                continue;
            }

            // 检查手机号是否已存在
            $existing = $database->fetchOne(
                "SELECT id FROM monitors WHERE user_id = ? AND phone = ?",
                [$userId, $phone]
            );

            if ($existing) {
                $results['failed'][] = [
                    'phone' => $phone,
                    'reason' => '手机号已存在'
                ];
                continue;
            }

            // 插入手机号
            try {
                $database->query(
                    "INSERT INTO monitors (user_id, phone, url, status) VALUES (?, ?, ?, 'no-code')",
                    [$userId, $phone, $url]
                );
                $results['success'][] = [
                    'phone' => $phone,
                    'id' => $database->getPdo()->lastInsertId()
                ];
            } catch (Exception $e) {
                $results['failed'][] = [
                    'phone' => $phone,
                    'reason' => '数据库错误: ' . $e->getMessage()
                ];
            }
        }

        $response->success([
            'total' => count($phones),
            'success_count' => count($results['success']),
            'failed_count' => count($results['failed']),
            'results' => $results
        ], '批量添加完成');

    } catch (Exception $e) {
        $response->error('批量添加失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 批量添加API密钥
 * @param int $userId 用户ID
 * @param Database $database 数据库对象
 * @param Response $response 响应对象
 */
function bulkAddApiKeys($userId, $database, $response) {
    try {
        // 获取请求数据
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['api_keys']) || !is_array($data['api_keys']) || empty($data['api_keys'])) {
            $response->error('请提供API密钥列表', 400);
            return;
        }

        $apiKeys = $data['api_keys'];
        $results = [
            'success' => [],
            'failed' => []
        ];

        foreach ($apiKeys as $keyData) {
            // 支持字符串或对象格式
            if (is_string($keyData)) {
                $name = 'API Key';
                $apiKey = trim($keyData);
                $apiSecret = '';
            } elseif (is_array($keyData)) {
                $name = isset($keyData['name']) ? trim($keyData['name']) : 'API Key';
                $apiKey = isset($keyData['api_key']) ? trim($keyData['api_key']) : '';
                $apiSecret = isset($keyData['api_secret']) ? trim($keyData['api_secret']) : '';
            } else {
                $results['failed'][] = [
                    'data' => $keyData,
                    'reason' => '格式错误'
                ];
                continue;
            }

            // 验证API密钥
            if (empty($apiKey)) {
                $results['failed'][] = [
                    'api_key' => $apiKey,
                    'reason' => 'API密钥为空'
                ];
                continue;
            }

            // 检查API密钥是否已存在
            $existing = $database->fetchOne(
                "SELECT id FROM api_keys WHERE api_key = ?",
                [$apiKey]
            );

            if ($existing) {
                $results['failed'][] = [
                    'api_key' => $apiKey,
                    'reason' => 'API密钥已存在'
                ];
                continue;
            }

            // 插入API密钥
            try {
                $database->query(
                    "INSERT INTO api_keys (user_id, name, api_key, api_secret, status) VALUES (?, ?, ?, ?, 'active')",
                    [$userId, $name, $apiKey, $apiSecret]
                );
                $results['success'][] = [
                    'name' => $name,
                    'api_key' => $apiKey,
                    'id' => $database->getPdo()->lastInsertId()
                ];
            } catch (Exception $e) {
                $results['failed'][] = [
                    'api_key' => $apiKey,
                    'reason' => '数据库错误: ' . $e->getMessage()
                ];
            }
        }

        $response->success([
            'total' => count($apiKeys),
            'success_count' => count($results['success']),
            'failed_count' => count($results['failed']),
            'results' => $results
        ], '批量添加完成');

    } catch (Exception $e) {
        $response->error('批量添加失败: ' . $e->getMessage(), 500);
    }
}
