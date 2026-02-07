#!/usr/bin/env python3
"""
最终完整功能测试
"""

import requests
import json

BASE_URL = "http://192.168.7.178:8080/backend/api"
TOKEN = None

def test_all():
    print("=" * 60)
    print("最终完整功能测试")
    print("=" * 60)
    
    # 1. 用户登录
    print("\n1. 用户登录...")
    response = requests.post(
        f"{BASE_URL}/auth/index.php/login",
        json={"email": "test123@example.com", "password": "123456"},
        headers={"Content-Type": "application/json"}
    )
    data = response.json()
    if data.get("code") == 200:
        global TOKEN
        TOKEN = data["data"]["token"]
        print("✓ 登录成功")
        print(f"  Token: {TOKEN[:50]}...")
    else:
        print(f"✗ 登录失败: {data.get('message')}")
        return
    
    # 2. 获取当前用户
    print("\n2. 获取当前用户...")
    response = requests.get(
        f"{BASE_URL}/auth/index.php/me",
        headers={"Authorization": f"Bearer {TOKEN}"}
    )
    data = response.json()
    print(f"✓ 用户信息: {data.get('data', {}).get('username')}")
    
    # 3. 获取监控项列表
    print("\n3. 获取监控项列表...")
    response = requests.get(
        f"{BASE_URL}/monitors/index.php",
        headers={"Authorization": f"Bearer {TOKEN}"}
    )
    data = response.json()
    if data.get("code") == 200:
        monitors = data.get("data", {}).get("list", [])
        print(f"✓ 获取到 {len(monitors)} 个监控项")
    else:
        print(f"✗ 获取监控项失败: {data.get('message')}")
    
    # 4. 获取验证码列表
    print("\n4. 获取验证码列表...")
    response = requests.get(
        f"{BASE_URL}/codes/index.php",
        headers={"Authorization": f"Bearer {TOKEN}"}
    )
    data = response.json()
    if data.get("code") == 200:
        codes = data.get("data", {}).get("list", [])
        print(f"✓ 获取到 {len(codes)} 个验证码")
    else:
        print(f"✗ 获取验证码失败: {data.get('message')}")
    
    # 5. 管理员获取用户列表
    print("\n5. 管理员获取用户列表...")
    response = requests.get(
        f"{BASE_URL}/admin/index.php/users",
        headers={"Authorization": f"Bearer {TOKEN}"}
    )
    data = response.json()
    if data.get("code") == 200:
        users = data.get("data", {}).get("list", [])
        print(f"✓ 获取到 {len(users)} 个用户")
        for user in users[:3]:
            print(f"  - {user.get('username')} ({user.get('role')})")
    else:
        print(f"✗ 获取用户列表失败: {data.get('message')}")
    
    print("\n" + "=" * 60)
    print("测试完成！")
    print("=" * 60)

if __name__ == "__main__":
    test_all()
