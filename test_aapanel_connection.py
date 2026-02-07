#!/usr/bin/env python3
"""
测试宝塔面板连接
"""

import sys
import json

# 添加aapanel-api-skill目录到路径
sys.path.append('c:\\Users\\Administrator\\.trae-cn\\skills\\aapanel-api-skill')

from aapanel_client import AAPanelClient


def main():
    """测试连接"""
    # 宝塔面板信息
    panel_url = "https://192.168.7.178:777"
    api_key = "IgCkCDbflE3Fpvir1rFmm5t8leW9Whnd"
    
    print(f"测试连接到: {panel_url}")
    print(f"API 密钥: {api_key[:10]}...")
    print()
    
    # 创建客户端
    client = AAPanelClient(panel_url, api_key, verify_ssl=False)
    
    # 测试获取系统信息
    print("=== 测试 1: 获取系统信息 ===")
    try:
        system_info = client.get_system_info()
        print(f"状态: {'成功' if system_info.get('status') else '失败'}")
        if system_info.get('status'):
            print(f"系统: {system_info.get('data', {}).get('os', 'N/A')}")
            print(f"CPU: {system_info.get('data', {}).get('cpu', 'N/A')} 核")
            print(f"内存: {system_info.get('data', {}).get('ram', 'N/A')} MB")
            print(f"运行时间: {system_info.get('data', {}).get('uptime', 'N/A')}")
        else:
            print(f"错误: {system_info.get('msg', '未知错误')}")
    except Exception as e:
        print(f"异常: {str(e)}")
    print()
    
    # 测试获取网站列表
    print("=== 测试 2: 获取网站列表 ===")
    try:
        sites = client.get_sites()
        print(f"状态: 成功")
        print(f"找到 {len(sites)} 个网站")
        for site in sites:
            print(f"  - {site.get('name', 'N/A')} ({site.get('path', 'N/A')})")
    except Exception as e:
        print(f"异常: {str(e)}")
    print()
    
    # 测试获取数据库列表
    print("=== 测试 3: 获取数据库列表 ===")
    try:
        databases = client.get_databases()
        print(f"状态: 成功")
        print(f"找到 {len(databases)} 个数据库")
        for db in databases:
            print(f"  - {db.get('name', 'N/A')}")
    except Exception as e:
        print(f"异常: {str(e)}")
    print()
    
    # 测试获取目录列表
    print("=== 测试 4: 获取目录列表 ===")
    try:
        dir_list = client.get_dir_list("/www/wwwroot")
        print(f"状态: 成功")
        print(f"找到 {len(dir_list)} 个文件/目录")
        for item in dir_list[:10]:  # 只显示前10个
            item_type = "目录" if item.get('is_dir') else "文件"
            print(f"  - {item.get('name', 'N/A')} ({item_type})")
    except Exception as e:
        print(f"异常: {str(e)}")
    print()
    
    print("=== 测试完成 ===")


if __name__ == "__main__":
    main()
