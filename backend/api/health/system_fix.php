<?php
/**
 * 系统修复 API
 * 用于修复服务器上的问题
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$results = [
    'time' => date('Y-m-d H:i:s'),
    'actions' => []
];

// 获取网站根目录
$webRoot = dirname(dirname(dirname(__DIR__))); // 从 backend/api/health 向上3级

$results['web_root'] = $webRoot;

// 1. 检查并删除错误的 index.php
$indexPhpPath = $webRoot . '/index.php';
if (file_exists($indexPhpPath)) {
    // 检查文件内容，确认是错误的文件
    $content = file_get_contents($indexPhpPath);
    if (strpos($content, 'require_once') !== false && strpos($content, 'config.php') !== false) {
        if (@unlink($indexPhpPath)) {
            $results['actions'][] = 'Deleted erroneous index.php';
        } else {
            $results['actions'][] = 'Failed to delete index.php (permission denied)';
        }
    } else {
        $results['actions'][] = 'index.php exists but may be valid, skipping';
    }
} else {
    $results['actions'][] = 'index.php does not exist';
}

// 2. 确认 index.html 存在
$indexHtmlPath = $webRoot . '/index.html';
if (file_exists($indexHtmlPath)) {
    $results['actions'][] = 'index.html exists - OK';
} else {
    $results['actions'][] = 'WARNING: index.html not found!';
}

// 3. 创建 .htaccess
$htaccessPath = $webRoot . '/.htaccess';
$htaccessContent = "# Auto-generated\nDirectoryIndex index.html index.php\n";

if (!file_exists($htaccessPath)) {
    if (@file_put_contents($htaccessPath, $htaccessContent)) {
        $results['actions'][] = 'Created .htaccess';
    }
} else {
    $results['actions'][] = '.htaccess already exists';
}

// 4. 最终状态
$results['final_status'] = [
    'index_php' => file_exists($indexPhpPath) ? 'exists' : 'deleted',
    'index_html' => file_exists($indexHtmlPath) ? 'exists' : 'missing',
    'htaccess' => file_exists($htaccessPath) ? 'exists' : 'missing'
];

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
