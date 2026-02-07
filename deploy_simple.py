#!/usr/bin/env python3
"""
简单部署项目到宝塔面板
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
    project_path = f"/www/wwwroot/verify-code-monitor.local"
    
    # 上传index.html文件
    print("=== 上传 index.html ===")
    try:
        with open("e:\\ai本地应用\\多用户接码\\index.html", 'r', encoding='utf-8') as f:
            content = f.read()
        
        result = client.create_file(f"{project_path}/index.html", content)
        print(f"状态: {'成功' if result.get('status') else '失败'}")
        print(f"消息: {result.get('msg', 'N/A')}")
    except Exception as e:
        print(f"异常: {str(e)}")
    print()
    
    # 上传login.html文件
    print("=== 上传 login.html ===")
    try:
        with open("e:\\ai本地应用\\多用户接码\\login.html", 'r', encoding='utf-8') as f:
            content = f.read()
        
        result = client.create_file(f"{project_path}/login.html", content)
        print(f"状态: {'成功' if result.get('status') else '失败'}")
        print(f"消息: {result.get('msg', 'N/A')}")
    except Exception as e:
        print(f"异常: {str(e)}")
    print()
    
    # 上传register.html文件
    print("=== 上传 register.html ===")
    try:
        with open("e:\\ai本地应用\\多用户接码\\register.html", 'r', encoding='utf-8') as f:
            content = f.read()
        
        result = client.create_file(f"{project_path}/register.html", content)
        print(f"状态: {'成功' if result.get('status') else '失败'}")
        print(f"消息: {result.get('msg', 'N/A')}")
    except Exception as e:
        print(f"异常: {str(e)}")
    print()
    
    # 上传后端文件
    backend_files = [
        "backend/api/auth/index.php",
        "backend/api/monitors/index.php",
        "backend/api/codes/index.php",
        "backend/api/admin/index.php",
        "backend/api/health/index.php",
        "backend/config/config.php",
        "backend/database/database.sql",
        "backend/models/BaseModel.php",
        "backend/models/MonitorModel.php",
        "backend/models/UserModel.php",
        "backend/utils/CaptchaExtractor.php",
        "backend/utils/Database.php",
        "backend/utils/JWT.php",
        "backend/utils/Response.php"
    ]
    
    for file_path in backend_files:
        print(f"=== 上传 {file_path} ===")
        try:
            local_path = f"e:\\ai本地应用\\多用户接码\\{file_path}"
            remote_path = f"{project_path}/{file_path}"
            
            with open(local_path, 'r', encoding='utf-8') as f:
                content = f.read()
            
            result = client.create_file(remote_path, content)
            print(f"状态: {'成功' if result.get('status') else '失败'}")
            print(f"消息: {result.get('msg', 'N/A')}")
        except Exception as e:
            print(f"异常: {str(e)}")
        print()
    
    # 验证项目文件
    print("=== 验证项目文件 ===")
    try:
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
    print("=== 测试网站访问 ===")
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
