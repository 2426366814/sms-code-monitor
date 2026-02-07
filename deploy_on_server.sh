#!/bin/bash
# 多用户动态验证码监控系统自动部署脚本
# 在服务器上运行

echo "=========================================="
echo "多用户动态验证码监控系统部署脚本"
echo "=========================================="
echo ""

# 1. 创建项目目录
echo "1. 创建项目目录..."
mkdir -p /www/wwwroot/verify-code-monitor.local
mkdir -p /www/wwwroot/verify-code-monitor.local/backend/api/admin
mkdir -p /www/wwwroot/verify-code-monitor.local/backend/api/api_keys
mkdir -p /www/wwwroot/verify-code-monitor.local/backend/api/auth
mkdir -p /www/wwwroot/verify-code-monitor.local/backend/api/codes
mkdir -p /www/wwwroot/verify-code-monitor.local/backend/api/health
mkdir -p /www/wwwroot/verify-code-monitor.local/backend/api/monitors
mkdir -p /www/wwwroot/verify-code-monitor.local/backend/api/settings
mkdir -p /www/wwwroot/verify-code-monitor.local/backend/api/public
mkdir -p /www/wwwroot/verify-code-monitor.local/backend/config
mkdir -p /www/wwwroot/verify-code-monitor.local/backend/database
mkdir -p /www/wwwroot/verify-code-monitor.local/backend/models
mkdir -p /www/wwwroot/verify-code-monitor.local/backend/utils
echo "✓ 目录创建完成"
echo ""

# 2. 设置权限
echo "2. 设置目录权限..."
chown -R www:www /www/wwwroot/verify-code-monitor.local
chmod -R 755 /www/wwwroot/verify-code-monitor.local
echo "✓ 权限设置完成"
echo ""

# 3. 检查是否有项目文件
echo "3. 检查项目文件..."
if [ -f "/www/wwwroot/verify-code-monitor.local/index.html" ]; then
    echo "✓ 项目文件已存在"
else
    echo "⚠ 项目文件不存在，请上传 verify-code-monitor.zip 到服务器并解压"
    echo "  解压命令: unzip verify-code-monitor.zip -d /www/wwwroot/verify-code-monitor.local"
fi
echo ""

echo "=========================================="
echo "目录准备完成！"
echo "=========================================="
echo ""
echo "下一步操作:"
echo "1. 上传 verify-code-monitor.zip 到服务器"
echo "2. 解压: unzip verify-code-monitor.zip -d /www/wwwroot/verify-code-monitor.local"
echo "3. 在宝塔面板中创建数据库并导入SQL文件"
echo "4. 配置网站和伪静态规则"
echo ""
echo "访问地址: http://192.168.7.178:8080"
echo "默认管理员: admin / admin"
echo "=========================================="
