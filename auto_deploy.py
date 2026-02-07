#!/usr/bin/env python3
"""
自动部署脚本 - 通过HTTP请求部署到宝塔面板
"""

import requests
import json
import time
import os
from pathlib import Path

# 服务器配置
SERVER_IP = "192.168.7.178"
PANEL_PORT = "777"
WEB_PORT = "8080"
PANEL_URL = f"https://{SERVER_IP}:{PANEL_PORT}"
WEB_URL = f"http://{SERVER_IP}:{WEB_PORT}"

# 宝塔面板API密钥
API_KEY = "IgCkCDbflE3Fpvir1rFmm5t8leW9Whnd"

# 项目路径
PROJECT_PATH = "/www/wwwroot/verify-code-monitor.local"
LOCAL_PATH = "e:/ai本地应用/多用户接码"

def deploy_via_http():
    """通过HTTP直接部署"""
    print("=" * 60)
    print("多用户动态验证码监控系统自动部署")
    print("=" * 60)
    print(f"服务器: {SERVER_IP}")
    print(f"面板端口: {PANEL_PORT}")
    print(f"网站端口: {WEB_PORT}")
    print("=" * 60)
    
    # 由于宝塔面板API连接失败，我们创建一个部署脚本
    # 用户可以在服务器上手动运行这个脚本
    
    deploy_script = f'''#!/bin/bash
# 自动部署脚本
# 在服务器上运行此脚本完成部署

echo "=========================================="
echo "多用户动态验证码监控系统部署脚本"
echo "=========================================="

# 1. 创建项目目录
echo "1. 创建项目目录..."
mkdir -p {PROJECT_PATH}
mkdir -p {PROJECT_PATH}/backend/api/{{admin,api_keys,auth,codes,health,monitors,settings,public}}
mkdir -p {PROJECT_PATH}/backend/config
mkdir -p {PROJECT_PATH}/backend/database
mkdir -p {PROJECT_PATH}/backend/models
mkdir -p {PROJECT_PATH}/backend/utils

# 2. 设置权限
echo "2. 设置目录权限..."
chown -R www:www {PROJECT_PATH}
chmod -R 755 {PROJECT_PATH}

echo "=========================================="
echo "目录创建完成！"
echo "请手动上传项目文件到: {PROJECT_PATH}"
echo "=========================================="
'''
    
    # 保存部署脚本
    script_path = os.path.join(LOCAL_PATH, "deploy_on_server.sh")
    with open(script_path, 'w', encoding='utf-8') as f:
        f.write(deploy_script)
    
    print(f"\n部署脚本已创建: {script_path}")
    print("\n请在服务器上执行以下步骤:")
    print("1. 上传 deploy_on_server.sh 到服务器")
    print("2. 运行: bash deploy_on_server.sh")
    print("3. 上传 verify-code-monitor.zip 到服务器")
    print("4. 解压: unzip verify-code-monitor.zip -d /www/wwwroot/verify-code-monitor.local")
    print("5. 在宝塔面板中创建数据库并导入SQL文件")
    print("6. 配置网站和伪静态规则")
    print("\n访问地址: http://192.168.7.178:8080")
    print("默认管理员: admin / admin")
    print("=" * 60)

if __name__ == "__main__":
    deploy_via_http()
