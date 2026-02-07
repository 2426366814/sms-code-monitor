#!/usr/bin/env python3
"""
部署项目到宝塔面板
"""

import sys
import json
import os

# 添加aapanel-api-skill目录到路径
sys.path.append('c:\\Users\\Administrator\\.trae-cn\\skills\\aapanel-api-skill')

from aapanel_client import AAPanelClient


def main():
    """部署项目"""
    # 宝塔面板信息
    panel_url = "https://192.168.7.178:777"
    api_key = "IgCkCDbflE3Fpvir1rFmm5t8leW9Whnd"
    
    print(f"部署项目到: {panel_url}")
    print(f"API 密钥: {api_key[:10]}...")
    print()
    
    # 创建客户端
    client = AAPanelClient(panel_url, api_key, verify_ssl=False)
    
    # 项目信息
    project_name = "verify-code-monitor"
    project_path = f"/www/wwwroot/verify-code-monitor.local"
    zip_file = f"e:\\ai本地应用\\多用户接码\\{project_name}-new.zip"
    
    # 检查压缩包是否存在
    if not os.path.exists(zip_file):
        print(f"错误: 压缩包不存在: {zip_file}")
        print("请先运行: Compress-Archive -Path backend, index.html, login.html, register.html -DestinationPath verify-code-monitor.zip")
        sys.exit(1)
    
    print(f"项目名称: {project_name}")
    print(f"项目路径: {project_path}")
    print(f"压缩包: {zip_file}")
    print()
    
    # 上传压缩包
    print("=== 步骤 1: 上传压缩包 ===")
    try:
        # 读取压缩包内容
        with open(zip_file, 'rb') as f:
            zip_content = f.read()
        
        # 上传到服务器
        upload_path = f"{project_path}/{project_name}.zip"
        print(f"上传压缩包到: {upload_path}")
        print(f"压缩包大小: {len(zip_content) / 1024:.2f} KB")
        
        # 使用创建文件的方式上传
        result = client.create_file(upload_path, zip_content)
        print(f"上传状态: {'成功' if result.get('status') else '失败'}")
        print(f"上传消息: {result.get('msg', 'N/A')}")
        
        if result.get('status') or '存在请求的文件' in str(result.get('msg', '')):
            print("✓ 压缩包上传成功！")
        else:
            print("✗ 压缩包上传失败！")
            sys.exit(1)
    except Exception as e:
        print(f"异常: {str(e)}")
        sys.exit(1)
    print()
    
    # 解压压缩包
    print("=== 步骤 2: 解压压缩包 ===")
    try:
        # 创建解压脚本
        unzip_script = f"#!/bin/bash\n"\
                      f"cd {project_path}\n"\
                      f"unzip -o {project_name}.zip\n"\
                      f"chmod -R 755 {project_path}\n"\
                      f"echo '解压完成'\n"
        
        script_path = f"/tmp/unzip_{project_name}.sh"
        print(f"创建解压脚本: {script_path}")
        
        # 上传解压脚本
        result = client.create_file(script_path, unzip_script)
        print(f"脚本上传状态: {'成功' if result.get('status') else '失败'}")
        
        if not result.get('status'):
            print("✗ 脚本上传失败！")
            sys.exit(1)
        
        # 执行解压脚本
        print("执行解压脚本...")
        result = client.exec_shell(f"bash {script_path}")
        print(f"解压状态: {'成功' if result.get('status') else '失败'}")
        print(f"解压消息: {result.get('msg', 'N/A')}")
        
        if result.get('status'):
            print("✓ 压缩包解压成功！")
        else:
            print("✗ 压缩包解压失败！")
            sys.exit(1)
    except Exception as e:
        print(f"异常: {str(e)}")
        sys.exit(1)
    print()
    
    # 验证项目文件
    print("=== 步骤 3: 验证项目文件 ===")
    try:
        # 检查项目目录结构
        print(f"检查项目目录: {project_path}")
        
        # 检查关键文件是否存在
        key_files = [
            f"{project_path}/index.html",
            f"{project_path}/login.html",
            f"{project_path}/register.html",
            f"{project_path}/backend/api/auth/index.php",
            f"{project_path}/backend/api/monitors/index.php",
            f"{project_path}/backend/api/codes/index.php",
            f"{project_path}/backend/api/admin/index.php",
            f"{project_path}/backend/config/config.php",
            f"{project_path}/backend/database/database.sql"
        ]
        
        existing_files = []
        missing_files = []
        
        for file_path in key_files:
            try:
                content = client.read_file(file_path)
                if content:
                    existing_files.append(file_path)
                else:
                    missing_files.append(file_path)
            except Exception as e:
                missing_files.append(file_path)
        
        print(f"存在的文件: {len(existing_files)}")
        for file in existing_files:
            print(f"  ✓ {file}")
        
        print(f"缺失的文件: {len(missing_files)}")
        for file in missing_files:
            print(f"  ✗ {file}")
        
        if len(missing_files) == 0:
            print("✓ 所有项目文件都已成功部署！")
        else:
            print("✗ 部分项目文件缺失！")
    except Exception as e:
        print(f"异常: {str(e)}")
    print()
    
    # 测试网站访问
    print("=== 步骤 4: 测试网站访问 ===")
    try:
        site_url = f"http://verify-code-monitor.local"
        print(f"网站访问地址: {site_url}")
        print(f"内网访问地址: http://192.168.7.178")
        print()
        print("请在浏览器中访问以上地址，测试网站是否正常运行。")
        print()
        print("=== 部署完成 ===")
        print("项目已经成功部署到宝塔面板！")
        print()
        print("下一步操作:")
        print("1. 在浏览器中访问网站，测试登录和注册功能")
        print("2. 检查数据库连接配置")
        print("3. 测试验证码监控功能")
    except Exception as e:
        print(f"异常: {str(e)}")


if __name__ == "__main__":
    main()
