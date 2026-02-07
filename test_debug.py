#!/usr/bin/env python3
"""
调试测试 - 检查API响应
"""

import requests
import json

BASE_URL = "http://192.168.7.178:8080/backend/api"

# 测试用户注册
print("=" * 60)
print("测试用户注册")
print("=" * 60)

try:
    response = requests.post(
        f"{BASE_URL}/auth/index.php/register",
        json={
            "username": "testuser123",
            "email": "test123@example.com",
            "password": "123456"
        },
        headers={"Content-Type": "application/json"},
        timeout=10
    )
    print(f"状态码: {response.status_code}")
    print(f"响应头: {dict(response.headers)}")
    print(f"响应内容: {response.text[:1000]}")
    
    # 尝试解析JSON
    try:
        data = response.json()
        print(f"JSON数据: {json.dumps(data, indent=2, ensure_ascii=False)}")
    except:
        print("响应不是有效的JSON")
        
except Exception as e:
    print(f"错误: {e}")
    import traceback
    traceback.print_exc()

# 测试直接访问index.php
print("\n" + "=" * 60)
print("测试直接访问 auth/index.php")
print("=" * 60)

try:
    response = requests.get(
        f"{BASE_URL}/auth/index.php",
        timeout=10
    )
    print(f"状态码: {response.status_code}")
    print(f"响应内容: {response.text[:1000]}")
except Exception as e:
    print(f"错误: {e}")
