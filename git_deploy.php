<?php
/**
 * Git 自动部署脚本
 * 通过 Webhook 触发 git pull
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== Git 自动部署 ===\n\n";

// 切换到网站根目录
$webRoot = dirname(__DIR__);
echo "网站根目录: $webRoot\n\n";

// 执行 git pull
$commands = [
    'cd ' . $webRoot . ' && git status',
    'cd ' . $webRoot . ' && git pull origin main 2>&1',
];

foreach ($commands as $cmd) {
    echo "执行: $cmd\n";
    $output = shell_exec($cmd);
    echo $output . "\n";
}

// 执行修复
echo "\n=== 执行修复 ===\n";

// 删除错误的 index.php
$indexPhp = $webRoot . '/index.php';
if (file_exists($indexPhp)) {
    if (@unlink($indexPhp)) {
        echo "✅ 已删除 index.php\n";
    } else {
        echo "❌ 无法删除 index.php\n";
    }
}

// 创建 .htaccess
$htaccess = $webRoot . '/.htaccess';
if (!file_exists($htaccess)) {
    @file_put_contents($htaccess, "DirectoryIndex index.html index.php\n");
    echo "✅ 已创建 .htaccess\n";
}

echo "\n=== 完成 ===\n";
