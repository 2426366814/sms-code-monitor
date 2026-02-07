#!/usr/bin/env python3
"""
测试宝塔面板功能
"""

import sys
import json

# 添加aapanel-api-skill目录到路径
sys.path.append('c:\\Users\\Administrator\\.trae-cn\\skills\\aapanel-api-skill')

from aapanel_client import AAPanelClient


def main():
    """测试功能"""
    # 宝塔面板信息
    panel_url = "https://192.168.7.178:777"
    api_key = "IgCkCDbflE3Fpvir1rFmm5t8leW9Whnd"
    
    print(f"测试连接到: {panel_url}")
    print(f"API 密钥: {api_key[:10]}...")
    print()
    
    # 创建客户端
    client = AAPanelClient(panel_url, api_key, verify_ssl=False)
    
    # 测试创建网站
    print("=== 测试 1: 创建网站 ===")
    try:
        site_name = "verify-code-monitor.local"
        site_path = f"/www/wwwroot/{site_name}"
        
        print(f"创建网站: {site_name}")
        print(f"网站目录: {site_path}")
        
        result = client.create_site(site_name, site_path, php_version="74")
        print(f"状态: {'成功' if result.get('status') else '失败'}")
        print(f"消息: {result.get('msg', 'N/A')}")
        if result.get('status'):
            print("✓ 网站创建成功！")
        else:
            print("✗ 网站创建失败！")
    except Exception as e:
        print(f"异常: {str(e)}")
    print()
    
    # 测试创建数据库
    print("=== 测试 2: 创建数据库 ===")
    try:
        db_name = "verify_code"
        db_password = "password123"
        
        print(f"创建数据库: {db_name}")
        print(f"数据库密码: {db_password}")
        
        result = client.create_database(db_name, db_password)
        print(f"状态: {'成功' if result.get('status') else '失败'}")
        print(f"消息: {result.get('msg', 'N/A')}")
        if result.get('status'):
            print("✓ 数据库创建成功！")
        else:
            print("✗ 数据库创建失败！")
    except Exception as e:
        print(f"异常: {str(e)}")
    print()
    
    # 测试上传文件
    print("=== 测试 3: 上传文件 ===")
    try:
        # 创建测试文件内容
        test_content = """<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>多用户动态验证码监控系统</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f0f0f0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
        }
        p {
            color: #666;
            text-align: center;
            font-size: 18px;
        }
        .success {
            color: #4CAF50;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>多用户动态验证码监控系统</h1>
        <p>网站部署测试 <span class="success">成功！</span></p>
        <p>这是一个测试页面，说明网站已经成功部署到宝塔面板。</p>
    </div>
</body>
</html>"""
        
        # 上传文件到网站目录
        file_path = f"/www/wwwroot/verify-code-monitor.local/index.html"
        print(f"上传文件: {file_path}")
        
        result = client.create_file(file_path, test_content)
        print(f"状态: {'成功' if result.get('status') else '失败'}")
        print(f"消息: {result.get('msg', 'N/A')}")
        if result.get('status'):
            print("✓ 文件上传成功！")
        else:
            print("✗ 文件上传失败！")
    except Exception as e:
        print(f"异常: {str(e)}")
    print()
    
    # 测试读取文件
    print("=== 测试 4: 读取文件 ===")
    try:
        file_path = f"/www/wwwroot/verify-code-monitor.local/index.html"
        print(f"读取文件: {file_path}")
        
        content = client.read_file(file_path)
        print(f"状态: {'成功' if content else '失败'}")
        if content:
            print(f"文件大小: {len(content)} 字节")
            print(f"文件内容预览: {content[:100]}...")
            print("✓ 文件读取成功！")
        else:
            print("✗ 文件读取失败！")
    except Exception as e:
        print(f"异常: {str(e)}")
    print()
    
    # 测试获取网站列表（验证创建结果）
    print("=== 测试 5: 验证网站创建 ===")
    try:
        sites = client.get_sites()
        print(f"找到 {len(sites)} 个网站")
        for site in sites:
            print(f"  - {site.get('name', 'N/A')} ({site.get('path', 'N/A')})")
        if any(site.get('name') == 'verify-code-monitor.local' for site in sites):
            print("✓ 网站验证成功！")
        else:
            print("✗ 网站验证失败！")
    except Exception as e:
        print(f"异常: {str(e)}")
    print()
    
    # 测试获取数据库列表（验证创建结果）
    print("=== 测试 6: 验证数据库创建 ===")
    try:
        databases = client.get_databases()
        print(f"找到 {len(databases)} 个数据库")
        for db in databases:
            print(f"  - {db.get('name', 'N/A')}")
        if any(db.get('name') == 'verify_code' for db in databases):
            print("✓ 数据库验证成功！")
        else:
            print("✗ 数据库验证失败！")
    except Exception as e:
        print(f"异常: {str(e)}")
    print()
    
    print("=== 测试完成 ===")


if __name__ == "__main__":
    main()
