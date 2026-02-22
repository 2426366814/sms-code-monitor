<?php
/**
 * 紧急修复 - 删除错误的 index.php
 * 通过 WebShell 方式执行
 */

// 设置响应头
header('Content-Type: text/plain; charset=utf-8');

echo "=== 紧急修复脚本 ===\n\n";

// 获取网站根目录
$root = dirname(__DIR__, 3); // backend/api/ -> backend -> root
echo "网站根目录: $root\n\n";

// 要删除的文件
$targetFile = $root . '/index.php';

echo "目标文件: $targetFile\n";

if (file_exists($targetFile)) {
    echo "文件存在，尝试删除...\n";
    
    // 检查文件内容
    $content = file_get_contents($targetFile);
    echo "文件大小: " . strlen($content) . " bytes\n";
    
    // 尝试删除
    if (unlink($targetFile)) {
        echo "✅ 成功删除 index.php\n";
    } else {
        echo "❌ 删除失败 (权限不足)\n";
        
        // 尝试修改权限
        echo "尝试修改权限...\n";
        if (chmod($targetFile, 0777)) {
            if (unlink($targetFile)) {
                echo "✅ 修改权限后成功删除\n";
            } else {
                echo "❌ 仍然无法删除\n";
            }
        } else {
            echo "❌ 无法修改权限\n";
        }
    }
} else {
    echo "✅ index.php 不存在，无需删除\n";
}

// 检查 index.html
$htmlFile = $root . '/index.html';
if (file_exists($htmlFile)) {
    echo "\n✅ index.html 存在\n";
} else {
    echo "\n❌ index.html 不存在！\n";
}

// 创建 .htaccess
$htaccess = $root . '/.htaccess';
$htaccessContent = "DirectoryIndex index.html index.php\n";

if (!file_exists($htaccess)) {
    if (file_put_contents($htaccess, $htaccessContent)) {
        echo "\n✅ 已创建 .htaccess\n";
    }
} else {
    echo "\n.htaccess 已存在\n";
}

echo "\n=== 修复完成 ===\n";
