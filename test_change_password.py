#!/usr/bin/env python3
"""
测试用户修改密码功能
"""

import requests

BASE_URL = "http://192.168.7.178:8080/backend/api"

def test_change_password():
    print("=" * 60)
    print("测试用户修改密码功能")
    print("=" * 60)
    
    # 1. 用户登录
    print("\n1. 用户登录...")
    response = requests.post(
        f"{BASE_URL}/auth/index.php/login",
        json={"email": "test123@example.com", "password": "123456"},
        headers={"Content-Type": "application/json"}
    )
    data = response.json()
    if data.get("code") != 200:
        print(f"✗ 登录失败: {data.get('message')}")
        return
    
    token = data["data"]["token"]
    print("✓ 登录成功")
    
    # 2. 修改密码
    print("\n2. 修改密码...")
    response = requests.post(
        f"{BASE_URL}/auth/index.php/change-password",
        json={
            "old_password": "123456",
            "new_password": "newpassword123"
        },
        headers={
            "Content-Type": "application/json",
            "Authorization": f"Bearer {token}"
        }
    )
    data = response.json()
    if data.get("code") == 200:
        print("✓ 密码修改成功")
    else:
        print(f"✗ 密码修改失败: {data.get('message')}")
        return
    
    # 3. 使用新密码登录
    print("\n3. 使用新密码登录...")
    response = requests.post(
        f"{BASE_URL}/auth/index.php/login",
        json={"email": "test123@example.com", "password": "newpassword123"},
        headers={"Content-Type": "application/json"}
    )
    data = response.json()
    if data.get("code") == 200:
        print("✓ 新密码登录成功")
        new_token = data["data"]["token"]
    else:
        print(f"✗ 新密码登录失败: {data.get('message')}")
        return
    
    # 4. 改回原来的密码
    print("\n4. 改回原来的密码...")
    response = requests.post(
        f"{BASE_URL}/auth/index.php/change-password",
        json={
            "old_password": "newpassword123",
            "new_password": "123456"
        },
        headers={
            "Content-Type": "application/json",
            "Authorization": f"Bearer {new_token}"
        }
    )
    data = response.json()
    if data.get("code") == 200:
        print("✓ 密码改回成功")
    else:
        print(f"✗ 密码改回失败: {data.get('message')}")
    
    print("\n" + "=" * 60)
    print("测试完成！")
    print("=" * 60)

if __name__ == "__main__":
    test_change_password()
