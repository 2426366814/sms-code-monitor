<?php
/**
 * 认证API
 * 用于用户注册、登录和认证
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
$userModel = new UserModel();
$jwt = new JWT($config['jwt']['secret'], $config['jwt']['algorithm']);

// 处理不同的API端点
switch ($method) {
    case 'POST':
        // 处理POST请求
        $data = json_decode(file_get_contents('php://input'), true);
        
        switch ($parts[0] ?? '') {
            case 'register':
                // 用户注册
                registerUser($data, $userModel, $jwt, $response);
                break;
            case 'login':
                // 用户登录
                loginUser($data, $userModel, $jwt, $response);
                break;
            case 'logout':
                // 用户登出
                logoutUser($response);
                break;
            case 'refresh':
                // 刷新令牌
                refreshToken($data, $jwt, $response);
                break;
            case 'change-password':
                // 修改密码
                changePassword($data, $jwt, $userModel, $response);
                break;
            default:
                $response->error('无效的API端点', 404);
                break;
        }
        break;
    case 'GET':
        // 处理GET请求
        switch ($parts[0] ?? '') {
            case 'me':
                // 获取当前用户信息
                getCurrentUser($jwt, $userModel, $response);
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
 * 用户注册
 * @param array $data 请求数据
 * @param UserModel $userModel 用户模型
 * @param JWT $jwt JWT对象
 * @param Response $response 响应对象
 */
function registerUser($data, $userModel, $jwt, $response) {
    // 验证请求数据
    if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
        $response->error('缺少必要的注册信息', 400);
        return;
    }

    // 验证数据格式
    if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
        $response->error('用户名、邮箱和密码不能为空', 400);
        return;
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $response->error('邮箱格式不正确', 400);
        return;
    }

    if (strlen($data['password']) < 6) {
        $response->error('密码长度不能少于6位', 400);
        return;
    }

    try {
        // 检查用户名是否已存在
        if ($userModel->getUserByUsername($data['username'])) {
            $response->error('用户名已存在', 400);
            return;
        }

        // 检查邮箱是否已存在
        if ($userModel->getUserByEmail($data['email'])) {
            $response->error('邮箱已被注册', 400);
            return;
        }

        // 创建用户
        $userId = $userModel->createUser([
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'status' => 'active'
        ]);

        if (!$userId) {
            $response->error('用户创建失败', 500);
            return;
        }

        // 获取创建的用户信息
        $user = $userModel->getUserById($userId);
        if (!$user) {
            $response->error('用户创建失败', 500);
            return;
        }

        // 生成JWT令牌
        $token = $jwt->generateToken([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'is_admin' => !empty($user['is_admin'])
        ]);

        // 返回响应
        $response->success([
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'status' => $user['status'],
                'created_at' => $user['created_at']
            ],
            'token' => $token
        ], '注册成功');

    } catch (Exception $e) {
        $response->error('注册失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 用户登录
 * @param array $data 请求数据
 * @param UserModel $userModel 用户模型
 * @param JWT $jwt JWT对象
 * @param Response $response 响应对象
 */
function loginUser($data, $userModel, $jwt, $response) {
    // 验证请求数据
    if ((!isset($data['email']) && !isset($data['username'])) || !isset($data['password'])) {
        $response->error('缺少必要的登录信息', 400);
        return;
    }

    // 获取登录标识（邮箱或用户名）
    $loginId = isset($data['email']) ? $data['email'] : (isset($data['username']) ? $data['username'] : '');
    
    // 验证数据格式
    if (empty($loginId) || empty($data['password'])) {
        $response->error('用户名/邮箱和密码不能为空', 400);
        return;
    }

    try {
        // 查找用户（支持邮箱或用户名登录）
        if (filter_var($loginId, FILTER_VALIDATE_EMAIL)) {
            // 如果是邮箱格式，通过邮箱查找
            $user = $userModel->getUserByEmail($loginId);
        } else {
            // 如果是用户名，通过用户名查找
            $user = $userModel->getUserByUsername($loginId);
        }
        
        if (!$user) {
            $response->error('邮箱或密码错误', 401);
            return;
        }

        // 检查用户状态
        if ($user['status'] !== 'active') {
            $response->error('用户账号已被禁用', 403);
            return;
        }

        // 验证密码
        if (!password_verify($data['password'], $user['password_hash'])) {
            $response->error('邮箱或密码错误', 401);
            return;
        }

        // 更新最后登录时间
        $userModel->updateLastLogin($user['id']);

        // 生成JWT令牌
        $token = $jwt->generateToken([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'is_admin' => !empty($user['is_admin'])
        ]);

        // 返回响应
        $response->success([
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'status' => $user['status'],
                'is_admin' => !empty($user['is_admin']),
                'last_login' => $user['last_login'],
                'created_at' => $user['created_at']
            ],
            'token' => $token
        ], '登录成功');

    } catch (Exception $e) {
        $response->error('登录失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 用户登出
 * @param Response $response 响应对象
 */
function logoutUser($response) {
    // 由于使用JWT，服务端不需要特殊处理
    // 客户端需要删除本地存储的令牌
    $response->success(null, '登出成功');
}

/**
 * 刷新令牌
 * @param array $data 请求数据
 * @param JWT $jwt JWT对象
 * @param Response $response 响应对象
 */
function refreshToken($data, $jwt, $response) {
    // 验证请求数据
    if (!isset($data['token'])) {
        $response->error('缺少令牌', 400);
        return;
    }

    try {
        // 验证令牌
        $payload = $jwt->validateToken($data['token']);
        if (!$payload) {
            $response->error('无效的令牌', 401);
            return;
        }

        // 生成新令牌
        $newToken = $jwt->generateToken([
            'user_id' => $payload['user_id'],
            'username' => $payload['username'],
            'email' => $payload['email']
        ]);

        // 返回响应
        $response->success(['token' => $newToken], '令牌刷新成功');

    } catch (Exception $e) {
        $response->error('令牌刷新失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 修改密码
 * @param array $data 请求数据
 * @param JWT $jwt JWT对象
 * @param UserModel $userModel 用户模型
 * @param Response $response 响应对象
 */
function changePassword($data, $jwt, $userModel, $response) {
    // 验证请求数据
    if (!isset($data['old_password']) || !isset($data['new_password'])) {
        $response->error('缺少必要的密码信息', 400);
        return;
    }

    // 验证新密码长度
    if (strlen($data['new_password']) < 6) {
        $response->error('新密码长度不能少于6位', 400);
        return;
    }

    // 获取Authorization头
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (empty($authHeader)) {
        $response->error('缺少认证令牌', 401);
        return;
    }

    // 提取令牌
    $token = str_replace('Bearer ', '', $authHeader);
    if (empty($token)) {
        $response->error('无效的认证令牌', 401);
        return;
    }

    try {
        // 验证令牌
        $payload = $jwt->validateToken($token);
        if (!$payload) {
            $response->error('无效的认证令牌', 401);
            return;
        }

        // 获取用户信息
        $user = $userModel->getUserById($payload['user_id']);
        if (!$user) {
            $response->error('用户不存在', 404);
            return;
        }

        // 验证旧密码
        if (!password_verify($data['old_password'], $user['password_hash'])) {
            $response->error('旧密码错误', 401);
            return;
        }

        // 更新密码
        $result = $userModel->updateUser($user['id'], [
            'password_hash' => password_hash($data['new_password'], PASSWORD_DEFAULT)
        ]);

        if (!$result) {
            $response->error('密码修改失败', 500);
            return;
        }

        // 返回响应
        $response->success(null, '密码修改成功');

    } catch (Exception $e) {
        $response->error('密码修改失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 获取当前用户信息
 * @param JWT $jwt JWT对象
 * @param UserModel $userModel 用户模型
 * @param Response $response 响应对象
 */
function getCurrentUser($jwt, $userModel, $response) {
    // 获取Authorization头
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (empty($authHeader)) {
        $response->error('缺少认证令牌', 401);
        return;
    }

    // 提取令牌
    $token = str_replace('Bearer ', '', $authHeader);
    if (empty($token)) {
        $response->error('无效的认证令牌', 401);
        return;
    }

    try {
        // 验证令牌
        $payload = $jwt->validateToken($token);
        if (!$payload) {
            $response->error('无效的认证令牌', 401);
            return;
        }

        // 获取用户信息
        $user = $userModel->getUserById($payload['user_id']);
        if (!$user) {
            $response->error('用户不存在', 404);
            return;
        }

        // 返回响应
        $response->success([
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'status' => $user['status'],
            'last_login' => $user['last_login'],
            'created_at' => $user['created_at']
        ]);

    } catch (Exception $e) {
        $response->error('获取用户信息失败: ' . $e->getMessage(), 500);
    }
}
?>