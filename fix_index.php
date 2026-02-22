<?php
/**
 * 修复脚本 - 删除错误的 index.php 文件
 * 通过 Web 访问此脚本来修复问题
 */

header('Content-Type: application/json; charset=utf-8');

$results = [];

// 检查并删除 index.php
$indexPhpPath = __DIR__ . '/index.php';
$indexHtmlPath = __DIR__ . '/index.html';

$results['index_php_exists'] = file_exists($indexPhpPath);
$results['index_html_exists'] = file_exists($indexHtmlPath);

if (file_exists($indexPhpPath)) {
    // 尝试删除
    if (unlink($indexPhpPath)) {
        $results['action'] = 'index.php deleted successfully';
    } else {
        $results['action'] = 'Failed to delete index.php (permission denied)';
    }
} else {
    $results['action'] = 'index.php does not exist';
}

// 检查 .htaccess 或创建一个
$htaccessPath = __DIR__ . '/.htaccess';
if (!file_exists($htaccessPath)) {
    $htaccessContent = "DirectoryIndex index.html index.php\n";
    if (file_put_contents($htaccessPath, $htaccessContent)) {
        $results['htaccess'] = 'Created .htaccess with index.html priority';
    }
} else {
    $results['htaccess'] = '.htaccess already exists';
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
