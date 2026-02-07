<?php
/**
 * 健康检测API
 * 用于检测系统运行环境和功能状态
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
require_once '../../utils/Response.php';

// 获取检测类型
$type = isset($_GET['type']) ? $_GET['type'] : 'all';

// 初始化响应对象
$response = new Response();

// 检测结果
$result = [];

switch ($type) {
    case 'php':
        $result = checkPHPEnvironment();
        break;
    case 'database':
        $result = checkDatabaseConnection();
        break;
    case 'functions':
        $result = checkProjectFunctions();
        break;
    case 'all':
    default:
        $result['php'] = checkPHPEnvironment();
        $result['database'] = checkDatabaseConnection();
        $result['functions'] = checkProjectFunctions();
        $result['overall'] = [
            'status' => $result['php']['status'] === 'ok' && $result['database']['status'] === 'ok' && $result['functions']['status'] === 'ok' ? 'ok' : 'error',
            'message' => $result['php']['status'] === 'ok' && $result['database']['status'] === 'ok' && $result['functions']['status'] === 'ok' ? '系统运行正常' : '系统存在问题'
        ];
        break;
}

// 返回响应
$response->success($result);

/**
 * 检测PHP环境
 * @return array 检测结果
 */
function checkPHPEnvironment() {
    $result = [
        'status' => 'ok',
        'message' => 'PHP环境正常',
        'data' => [
            'version' => phpversion(),
            'extensions' => [],
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'error_reporting' => ini_get('error_reporting')
        ]
    ];

    // 检查PHP版本
    if (version_compare(phpversion(), '7.4', '<')) {
        $result['status'] = 'error';
        $result['message'] = 'PHP版本过低，需要PHP 7.4+';
    }

    // 检查必要的扩展
    $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'curl', 'openssl'];
    foreach ($requiredExtensions as $ext) {
        $result['data']['extensions'][$ext] = extension_loaded($ext) ? 'enabled' : 'disabled';
        if (!extension_loaded($ext)) {
            $result['status'] = 'error';
            $result['message'] = "缺少必要的PHP扩展: $ext";
        }
    }

    // 检查内存限制
    if (ini_get('memory_limit') !== '-1' && convertToBytes(ini_get('memory_limit')) < 128 * 1024 * 1024) {
        $result['status'] = 'warning';
        $result['message'] = 'PHP内存限制过低，建议至少128M';
    }

    // 检查执行时间
    if (ini_get('max_execution_time') < 30) {
        $result['status'] = 'warning';
        $result['message'] = 'PHP执行时间限制过低，建议至少30秒';
    }

    return $result;
}

/**
 * 检测数据库连接
 * @return array 检测结果
 */
function checkDatabaseConnection() {
    $result = [
        'status' => 'ok',
        'message' => '数据库连接正常',
        'data' => []
    ];

    try {
        // 测试数据库连接
        $database = Database::getInstance();
        $pdo = $database->getPdo();

        // 测试数据库权限
        $stmt = $pdo->query('SHOW TABLES');
        if ($stmt) {
            $result['data']['tables'] = $stmt->rowCount();
        }

        // 测试数据库版本
        $stmt = $pdo->query('SELECT VERSION() as version');
        if ($stmt) {
            $version = $stmt->fetch(PDO::FETCH_ASSOC);
            $result['data']['version'] = $version['version'];
        }

    } catch (Exception $e) {
        $result['status'] = 'error';
        $result['message'] = '数据库连接失败: ' . $e->getMessage();
    }

    return $result;
}

/**
 * 检测项目功能
 * @return array 检测结果
 */
function checkProjectFunctions() {
    $result = [
        'status' => 'ok',
        'message' => '项目功能正常',
        'data' => [
            'files' => [],
            'directories' => []
        ]
    ];

    // 检查必要的文件
    $requiredFiles = [
        '../../config/config.php',
        '../../utils/Database.php',
        '../../utils/JWT.php',
        '../../utils/Response.php',
        '../../utils/CaptchaExtractor.php',
        '../../models/BaseModel.php',
        '../../models/UserModel.php',
        '../../models/MonitorModel.php',
        '../../database/database.sql'
    ];

    foreach ($requiredFiles as $file) {
        $result['data']['files'][$file] = file_exists($file) ? 'exists' : 'missing';
        if (!file_exists($file)) {
            $result['status'] = 'error';
            $result['message'] = "缺少必要的文件: $file";
        }
    }

    // 检查必要的目录
    $requiredDirs = [
        '../../api',
        '../../api/admin',
        '../../api/api_keys',
        '../../api/auth',
        '../../api/codes',
        '../../api/health',
        '../../api/monitors',
        '../../api/settings',
        '../../api/public'
    ];

    foreach ($requiredDirs as $dir) {
        $result['data']['directories'][$dir] = is_dir($dir) ? 'exists' : 'missing';
        if (!is_dir($dir)) {
            $result['status'] = 'error';
            $result['message'] = "缺少必要的目录: $dir";
        }
    }

    return $result;
}

/**
 * 将内存限制字符串转换为字节数
 * @param string $val 内存限制字符串
 * @return int 字节数
 */
function convertToBytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}
?>