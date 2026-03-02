<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/config.php';
require_once '../../utils/Database.php';
require_once '../../utils/JWT.php';
require_once '../../utils/Response.php';

$response = new Response();
$config = require '../../config/config.php';
$jwt = new JWT($config['jwt']['secret'], $config['jwt']['algorithm']);

$token = null;
$headers = getallheaders();
if (isset($headers['Authorization'])) {
    $authHeader = $headers['Authorization'];
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
    }
}

if (!$token) {
    $response->error('请先登录', 401);
    exit;
}

$payload = $jwt->validateToken($token);
if (!$payload || !isset($payload['user_id'])) {
    $response->error('Token无效或已过期', 401);
    exit;
}
$userId = $payload['user_id'];

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $keys = $db->fetchAll(
            "SELECT id, name, api_key, api_secret, status, expires_at, created_at, last_used_at 
             FROM api_keys 
             WHERE user_id = ? 
             ORDER BY created_at DESC", 
            [$userId]
        );
        $response->success(['list' => $keys ?: []]);
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $name = isset($data['name']) ? trim($data['name']) : 'API密钥';
        
        $apiKey = 'sk_' . bin2hex(random_bytes(24));
        $apiSecret = bin2hex(random_bytes(32));
        
        $id = $db->insert('api_keys', [
            'user_id' => $userId,
            'name' => $name,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'status' => 'active'
        ]);
        
        $response->success([
            'id' => $id,
            'name' => $name,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ], 'API密钥生成成功');
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($id <= 0) {
            $response->error('无效的密钥ID');
            exit;
        }
        
        $result = $db->delete('api_keys', ['id' => $id, 'user_id' => $userId]);
        
        if ($result > 0) {
            $response->success(null, 'API密钥删除成功');
        } else {
            $response->error('密钥不存在或无权删除');
        }
        break;

    default:
        $response->error('不支持的请求方法', 405);
        break;
}
