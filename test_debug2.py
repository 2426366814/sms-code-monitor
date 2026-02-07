#!/usr/bin/env python3
"""
调试测试
"""

import requests

BASE_URL = "http://192.168.7.178:8080/backend/api"

# 登录
print("1. 登录...")
response = requests.post(
    f"{BASE_URL}/auth/index.php/login",
    json={"email": "test123@example.com", "password": "123456"},
    headers={"Content-Type": "application/json"}
)
data = response.json()
token = data["data"]["token"]
print(f"Token: {token[:50]}...")

# 测试监控项API
print("\n2. 测试监控项API...")
response = requests.get(
    f"{BASE_URL}/monitors/index.php",
    headers={"Authorization": f"Bearer {token}"}
)
print(f"状态码: {response.status_code}")
print(f"响应头: {dict(response.headers)}")
print(f"响应内容: {response.text[:500]}")
