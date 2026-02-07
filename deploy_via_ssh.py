#!/usr/bin/env python3
"""
通过SSH自动部署多用户动态验证码监控系统
"""

import socket
import time
import os
from pathlib import Path

# 服务器配置
SERVER_HOST = '192.168.7.178'
SERVER_PORT = 1022
SERVER_USER = 'root'
SERVER_PASS = 'C^74+ek@dN'
PROJECT_PATH = '/www/wwwroot/verify-code-monitor.local'
LOCAL_PATH = 'e:/ai本地应用/多用户接码'

def create_deployment_script():
    """创建服务器端部署脚本"""
    
    # 创建部署脚本内容
    deploy_script = f'''#!/bin/bash
# 多用户动态验证码监控系统自动部署脚本
# 在服务器上运行

echo "=========================================="
echo "多用户动态验证码监控系统部署脚本"
echo "=========================================="
echo ""

# 1. 创建项目目录
echo "1. 创建项目目录..."
mkdir -p {PROJECT_PATH}
mkdir -p {PROJECT_PATH}/backend/api/admin
mkdir -p {PROJECT_PATH}/backend/api/api_keys
mkdir -p {PROJECT_PATH}/backend/api/auth
mkdir -p {PROJECT_PATH}/backend/api/codes
mkdir -p {PROJECT_PATH}/backend/api/health
mkdir -p {PROJECT_PATH}/backend/api/monitors
mkdir -p {PROJECT_PATH}/backend/api/settings
mkdir -p {PROJECT_PATH}/backend/api/public
mkdir -p {PROJECT_PATH}/backend/config
mkdir -p {PROJECT_PATH}/backend/database
mkdir -p {PROJECT_PATH}/backend/models
mkdir -p {PROJECT_PATH}/backend/utils
echo "✓ 目录创建完成"
echo ""

# 2. 设置权限
echo "2. 设置目录权限..."
chown -R www:www {PROJECT_PATH}
chmod -R 755 {PROJECT_PATH}
echo "✓ 权限设置完成"
echo ""

# 3. 检查是否有项目文件
echo "3. 检查项目文件..."
if [ -f "{PROJECT_PATH}/index.html" ]; then
    echo "✓ 项目文件已存在"
else
    echo "⚠ 项目文件不存在，请上传 verify-code-monitor.zip 到服务器并解压"
    echo "  解压命令: unzip verify-code-monitor.zip -d {PROJECT_PATH}"
fi
echo ""

echo "=========================================="
echo "目录准备完成！"
echo "=========================================="
echo ""
echo "下一步操作:"
echo "1. 上传 verify-code-monitor.zip 到服务器"
echo "2. 解压: unzip verify-code-monitor.zip -d {PROJECT_PATH}"
echo "3. 在宝塔面板中创建数据库并导入SQL文件"
echo "4. 配置网站和伪静态规则"
echo ""
echo "访问地址: http://192.168.7.178:8080"
echo "默认管理员: admin / admin"
echo "=========================================="
'''
    
    # 保存部署脚本到本地
    script_path = os.path.join(LOCAL_PATH, 'deploy_on_server.sh')
    with open(script_path, 'w', encoding='utf-8') as f:
        f.write(deploy_script)
    
    print(f'✓ 部署脚本已创建: {script_path}')
    return script_path

def create_remote_deploy_script():
    """创建远程执行脚本"""
    
    # 创建完整的远程部署脚本
    remote_script = f'''#!/bin/bash
# 远程部署脚本

echo "=========================================="
echo "开始远程部署..."
echo "=========================================="

# 安装expect（如果没有）
if ! command -v expect &> /dev/null; then
    echo "安装 expect..."
    yum install -y expect || apt-get install -y expect
fi

# 创建项目目录
mkdir -p {PROJECT_PATH}
mkdir -p {PROJECT_PATH}/backend/api/{{admin,api_keys,auth,codes,health,monitors,settings,public}}
mkdir -p {PROJECT_PATH}/backend/config
mkdir -p {PROJECT_PATH}/backend/database
mkdir -p {PROJECT_PATH}/backend/models
mkdir -p {PROJECT_PATH}/backend/utils

# 设置权限
chown -R www:www {PROJECT_PATH}
chmod -R 755 {PROJECT_PATH}

echo "✓ 目录创建完成"
echo ""
echo "请手动上传项目文件并配置数据库"
echo "=========================================="
'''
    
    return remote_script

def test_connection():
    """测试服务器连接"""
    print("=" * 60)
    print("多用户动态验证码监控系统自动部署")
    print("=" * 60)
    print(f"服务器: {SERVER_HOST}:{SERVER_PORT}")
    print(f"用户名: {SERVER_USER}")
    print("=" * 60)
    print("")
    
    # 测试端口连接
    print("测试服务器连接...")
    try:
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(5)
        result = sock.connect_ex((SERVER_HOST, SERVER_PORT))
        sock.close()
        
        if result == 0:
            print(f"✓ 端口 {SERVER_PORT} 连接成功")
            return True
        else:
            print(f"✗ 端口 {SERVER_PORT} 连接失败")
            return False
    except Exception as e:
        print(f"✗ 连接错误: {e}")
        return False

def main():
    """主函数"""
    # 测试连接
    if not test_connection():
        print("\n无法连接到服务器，请检查网络设置")
        return
    
    print("\n✓ 服务器连接正常")
    print("")
    
    # 创建部署脚本
    script_path = create_deployment_script()
    
    print("\n" + "=" * 60)
    print("部署准备完成！")
    print("=" * 60)
    print("")
    print("由于当前环境没有SSH客户端，请使用以下方法完成部署:")
    print("")
    print("方法1: 使用其他SSH客户端")
    print("  1. 使用PuTTY、Xshell或Windows Terminal连接服务器")
    print(f"     主机: {SERVER_HOST}")
    print(f"     端口: {SERVER_PORT}")
    print(f"     用户名: {SERVER_USER}")
    print(f"     密码: {SERVER_PASS}")
    print("  2. 上传 deploy_on_server.sh 到服务器")
    print("  3. 运行: bash deploy_on_server.sh")
    print("  4. 上传 verify-code-monitor.zip 到服务器")
    print(f"  5. 解压: unzip verify-code-monitor.zip -d {PROJECT_PATH}")
    print("  6. 在宝塔面板中配置数据库和网站")
    print("")
    print("方法2: 使用宝塔面板文件管理器")
    print("  1. 登录宝塔面板 (https://192.168.7.178:777)")
    print("  2. 使用文件管理器上传 verify-code-monitor.zip")
    print(f"  3. 解压到 {PROJECT_PATH}")
    print("  4. 创建数据库并导入SQL文件")
    print("  5. 配置网站和伪静态规则")
    print("")
    print("=" * 60)
    print(f"访问地址: http://192.168.7.178:8080")
    print("默认管理员: admin / admin")
    print("=" * 60)

if __name__ == "__main__":
    main()
