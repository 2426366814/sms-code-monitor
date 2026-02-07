<?php
/**
 * 监控项API
 * 用于管理监控的手机号
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 加载配置文件
require_once '../../config/config.php';
require_once '../../utils/Database.php';
require_once '../../utils/JWT.php';
require_once '../../utils/Response.php';
require_once '../../models/MonitorModel.php';

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

// 获取请求路径
$path = isset($_GET['path']) ? $_GET['path'] : '';
$parts = explode('/', trim($path, '/'));

// 初始化数据库连接
$config = require '../../config/config.php';
$database = Database::getInstance();
$monitorModel = new MonitorModel();
$jwt = new JWT($config['jwt']['secret'], $config['jwt']['algorithm']);

// 验证认证
function verifyAuth($jwt) {
    $authHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if (empty($authHeader)) {
        Response::error('缺少认证令牌', 401);
        return false;
    }

    $token = str_replace('Bearer ', '', $authHeader);
    if (empty($token)) {
        Response::error('无效的认证令牌', 401);
        return false;
    }

    try {
        $payload = $jwt->validateToken($token);
        if (!$payload) {
            Response::error('无效的认证令牌', 401);
            return false;
        }
        return $payload;
    } catch (Exception $e) {
        Response::error('认证失败: ' . $e->getMessage(), 500);
        return false;
    }
}

// 处理不同的API端点
switch ($method) {
    case 'GET':
        // 处理GET请求
        switch ($parts[0] ?? '') {
            case '':
                // 获取监控列表
                $payload = verifyAuth($jwt);
                if (!$payload) return;
                getMonitors($payload, $monitorModel);
                break;
            default:
                Response::error('无效的API端点', 404);
                break;
        }
        break;
    case 'POST':
        // 处理POST请求
        $data = json_decode(file_get_contents('php://input'), true);
        switch ($parts[0] ?? '') {
            case '':
                // 添加监控
                $payload = verifyAuth($jwt);
                if (!$payload) return;
                addMonitor($data, $payload, $monitorModel);
                break;
            case 'batch':
                // 批量添加监控
                $payload = verifyAuth($jwt);
                if (!$payload) return;
                batchAddMonitors($data, $payload, $monitorModel);
                break;
            case 'refresh-all':
                // 刷新所有监控
                $payload = verifyAuth($jwt);
                if (!$payload) return;
                refreshAllMonitors($payload, $monitorModel);
                break;
            default:
                Response::error('无效的API端点', 404);
                break;
        }
        break;
    case 'PUT':
        // 处理PUT请求
        $data = json_decode(file_get_contents('php://input'), true);
        switch ($parts[0] ?? '') {
            case '':
                // 更新监控
                $payload = verifyAuth($jwt);
                if (!$payload) return;
                updateMonitor($data, $payload, $monitorModel);
                break;
            default:
                Response::error('无效的API端点', 404);
                break;
        }
        break;
    case 'DELETE':
        // 处理DELETE请求
        switch ($parts[0] ?? '') {
            case '':
                // 删除监控
                $payload = verifyAuth($jwt);
                if (!$payload) return;
                deleteMonitor($_GET, $payload, $monitorModel);
                break;
            default:
                Response::error('无效的API端点', 404);
                break;
        }
        break;
    default:
        Response::error('不支持的请求方法', 405);
        break;
}

/**
 * 获取监控列表
 */
function getMonitors($payload, $monitorModel) {
    try {
        $monitors = $monitorModel->getMonitorsByUserId($payload['user_id']);
        Response::success(['monitors' => $monitors], '获取成功');
    } catch (Exception $e) {
        Response::error('获取监控列表失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 添加监控
 */
function addMonitor($data, $payload, $monitorModel) {
    try {
        // 验证数据
        if (!isset($data['phone_number']) || empty($data['phone_number'])) {
            Response::error('手机号不能为空', 400);
            return;
        }

        // 验证手机号格式
        if (!preg_match('/^1[3-9]\d{9}$/', $data['phone_number'])) {
            Response::error('手机号格式不正确', 400);
            return;
        }

        // 检查是否已存在
        $existing = $monitorModel->getMonitorByPhoneNumber($data['phone_number'], $payload['user_id']);
        if ($existing) {
            Response::error('该手机号已在监控列表中', 400);
            return;
        }

        // 创建监控
        $monitorId = $monitorModel->createMonitor([
            'user_id' => $payload['user_id'],
            'phone_number' => $data['phone_number'],
            'description' => $data['description'] ?? '',
            'status' => 'active'
        ]);

        if ($monitorId) {
            Response::success(['id' => $monitorId], '添加成功');
        } else {
            Response::error('添加失败', 500);
        }
    } catch (Exception $e) {
        Response::error('添加监控失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 批量添加监控
 */
function batchAddMonitors($data, $payload, $monitorModel) {
    try {
        if (!isset($data['phone_numbers']) || !is_array($data['phone_numbers'])) {
            Response::error('手机号列表不能为空', 400);
            return;
        }

        $success = [];
        $failed = [];

        foreach ($data['phone_numbers'] as $phoneNumber) {
            // 验证手机号格式
            if (!preg_match('/^1[3-9]\d{9}$/', $phoneNumber)) {
                $failed[] = ['phone_number' => $phoneNumber, 'reason' => '格式不正确'];
                continue;
            }

            // 检查是否已存在
            $existing = $monitorModel->getMonitorByPhoneNumber($phoneNumber, $payload['user_id']);
            if ($existing) {
                $failed[] = ['phone_number' => $phoneNumber, 'reason' => '已存在'];
                continue;
            }

            // 创建监控
            $monitorId = $monitorModel->createMonitor([
                'user_id' => $payload['user_id'],
                'phone_number' => $phoneNumber,
                'description' => '',
                'status' => 'active'
            ]);

            if ($monitorId) {
                $success[] = $phoneNumber;
            } else {
                $failed[] = ['phone_number' => $phoneNumber, 'reason' => '添加失败'];
            }
        }

        Response::success([
            'success' => $success,
            'failed' => $failed,
            'success_count' => count($success),
            'failed_count' => count($failed)
        ], '批量添加完成');
    } catch (Exception $e) {
        Response::error('批量添加监控失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 更新监控
 */
function updateMonitor($data, $payload, $monitorModel) {
    try {
        if (!isset($data['id'])) {
            Response::error('缺少监控ID', 400);
            return;
        }

        $monitor = $monitorModel->getMonitorById($data['id']);
        if (!$monitor || $monitor['user_id'] != $payload['user_id']) {
            Response::error('监控项不存在', 404);
            return;
        }

        $updateData = [];
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        if (empty($updateData)) {
            Response::error('没有要更新的内容', 400);
            return;
        }

        $result = $monitorModel->updateMonitor($data['id'], $updateData);
        if ($result) {
            Response::success(null, '更新成功');
        } else {
            Response::error('更新失败', 500);
        }
    } catch (Exception $e) {
        Response::error('更新监控失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 删除监控
 */
function deleteMonitor($data, $payload, $monitorModel) {
    try {
        if (!isset($data['id'])) {
            Response::error('缺少监控ID', 400);
            return;
        }

        $monitor = $monitorModel->getMonitorById($data['id']);
        if (!$monitor || $monitor['user_id'] != $payload['user_id']) {
            Response::error('监控项不存在', 404);
            return;
        }

        $result = $monitorModel->deleteMonitor($data['id']);
        if ($result) {
            Response::success(null, '删除成功');
        } else {
            Response::error('删除失败', 500);
        }
    } catch (Exception $e) {
        Response::error('删除监控失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 刷新所有监控
 */
function refreshAllMonitors($payload, $monitorModel) {
    try {
        $monitors = $monitorModel->getMonitorsByUserId($payload['user_id']);
        
        // 更新所有监控项的状态
        foreach ($monitors as &$monitor) {
            // 检查是否有新验证码
            $latestCode = $monitorModel->getLatestCode($monitor['id']);
            if ($latestCode) {
                $monitor['latest_code'] = $latestCode['code'];
                $monitor['code_time'] = $latestCode['created_at'];
                $monitor['has_code'] = true;
            } else {
                $monitor['has_code'] = false;
            }
        }

        Response::success(['monitors' => $monitors], '刷新成功');
    } catch (Exception $e) {
        Response::error('刷新监控失败: ' . $e->getMessage(), 500);
    }
}
?>
