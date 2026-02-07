<?php
/**
 * 验证码API
 * 用于获取历史验证码和导出数据
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
require_once '../../models/MonitorModel.php';
require_once '../../models/UserModel.php';

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

// 初始化数据库连接
$config = require '../../config/config.php';
$database = Database::getInstance();
$monitorModel = new MonitorModel();
$userModel = new UserModel();
$jwt = new JWT($config['jwt']['secret'], $config['jwt']['algorithm']);

// 验证用户认证
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
        // 获取历史验证码
        $payload = verifyAuth($jwt);
        if (!$payload) {
            return;
        }
        getCodes($payload, $database);
        break;
    
    case 'POST':
        // 处理POST请求
        $payload = verifyAuth($jwt);
        if (!$payload) {
            return;
        }
        
        // 检查是否是导出请求
        if (isset($_GET['action']) && $_GET['action'] === 'export') {
            exportCodes($payload, $database);
        } else {
            Response::error('无效的请求', 400);
        }
        break;
    
    default:
        Response::error('不支持的请求方法', 405);
        break;
}

/**
 * 获取历史验证码
 * @param array $payload JWT负载
 * @param Database $database 数据库对象
 */
function getCodes($payload, $database) {
    try {
        // 获取查询参数
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $monitorId = isset($_GET['monitor_id']) ? (int)$_GET['monitor_id'] : 0;
        
        // 计算偏移量
        $offset = ($page - 1) * $limit;
        
        // 构建查询
        $query = "SELECT c.id, c.monitor_id, c.code, c.original_text, c.extracted_time, c.created_at " .
                 "FROM codes c " .
                 "JOIN monitors m ON c.monitor_id = m.id " .
                 "WHERE m.user_id = ?";
        $params = [$payload['user_id']];
        
        // 添加监控项过滤
        if ($monitorId) {
            $query .= " AND c.monitor_id = ?";
            $params[] = $monitorId;
        }
        
        // 添加排序和分页
        $query .= " ORDER BY c.extracted_time DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        // 执行查询
        $codes = $database->fetchAll($query, $params);
        
        // 获取总数
        $countQuery = "SELECT COUNT(*) as count FROM codes c JOIN monitors m ON c.monitor_id = m.id WHERE m.user_id = ?";
        $countParams = [$payload['user_id']];
        
        if ($monitorId) {
            $countQuery .= " AND c.monitor_id = ?";
            $countParams[] = $monitorId;
        }
        
        $countResult = $database->fetchOne($countQuery, $countParams);
        $total = $countResult ? (int)$countResult['count'] : 0;
        
        // 返回响应
        Response::success([
            'list' => $codes,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ], '获取成功');
        
    } catch (Exception $e) {
        Response::error('获取验证码失败: ' . $e->getMessage(), 500);
    }
}

/**
 * 导出验证码数据
 * @param array $payload JWT负载
 * @param Database $database 数据库对象
 */
function exportCodes($payload, $database) {
    try {
        // 获取请求数据
        $data = json_decode(file_get_contents('php://input'), true);
        
        // 获取监控项ID列表
        $monitorIds = isset($data['monitor_ids']) && is_array($data['monitor_ids']) ? $data['monitor_ids'] : [];
        $startTime = isset($data['start_time']) ? $data['start_time'] : '';
        $endTime = isset($data['end_time']) ? $data['end_time'] : '';
        
        // 构建查询
        $query = "SELECT c.id, c.monitor_id, c.code, c.original_text, c.extracted_time, c.created_at " .
                 "FROM codes c " .
                 "JOIN monitors m ON c.monitor_id = m.id " .
                 "WHERE m.user_id = ?";
        $params = [$payload['user_id']];
        
        // 添加监控项过滤
        if (!empty($monitorIds)) {
            $placeholders = rtrim(str_repeat('?,', count($monitorIds)), ',');
            $query .= " AND c.monitor_id IN ($placeholders)";
            $params = array_merge($params, $monitorIds);
        }
        
        // 添加时间范围过滤
        if ($startTime) {
            $query .= " AND c.created_at >= ?";
            $params[] = $startTime;
        }
        if ($endTime) {
            $query .= " AND c.created_at <= ?";
            $params[] = $endTime;
        }
        
        // 添加排序
        $query .= " ORDER BY c.created_at DESC";
        
        // 执行查询
        $codes = $database->fetchAll($query, $params);
        
        // 生成导出文件
        $exportData = [
            'export_time' => date('Y-m-d H:i:s'),
            'total_count' => count($codes),
            'data' => $codes
        ];
        
        // 返回响应
        Response::success([
            'data' => $exportData,
            'message' => '导出成功'
        ], '导出成功');
        
    } catch (Exception $e) {
        Response::error('导出验证码失败: ' . $e->getMessage(), 500);
    }
}
?>
