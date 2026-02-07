#!/usr/bin/env python3
"""
测试批量添加功能
"""

import requests

BASE_URL = "http://192.168.7.178:8080/backend/api"

def test_bulk():
    print("=" * 60)
    print("测试批量添加功能")
    print("=" * 60)
    
    # 1. 管理员登录
    print("\n1. 管理员登录...")
    response = requests.post(
        f"{BASE_URL}/auth/index.php/login",
        json={"username": "admin", "password": "admin"},
        headers={"Content-Type": "application/json"}
    )
    data = response.json()
    if data.get("code") != 200:
        print(f"✗ 登录失败: {data.get('message')}")
        return
    
    token = data["data"]["token"]
    print("✓ 登录成功")
    
    # 2. 批量添加手机号
    print("\n2. 批量添加手机号...")
    response = requests.post(
        f"{BASE_URL}/bulk/index.php/phones",
        json={
            "phones": [
                "13800138001",
                "13800138002",
                {"phone": "13800138003", "url": "http://example.com/3"},
                "13800138001",  # 重复的，应该失败
            ]
        },
        headers={
            "Content-Type": "application/json",
            "Authorization": f"Bearer {token}"
        }
    )
    data = response.json()
    if data.get("code") == 200:
        print(f"✓ 批量添加手机号成功")
        print(f"  总计: {data['data']['total']}")
        print(f"  成功: {data['data']['success_count']}")
        print(f"  失败: {data['data']['failed_count']}")
        for item in data['data']['results']['success']:
            print(f"  ✓ {item['phone']}")
        for item in data['data']['results']['failed']:
            print(f"  ✗ {item.get('phone', item.get('data'))} - {item['reason']}")
    else:
        print(f"✗ 批量添加手机号失败: {data.get('message')}")
    
    # 3. 批量添加API密钥
    print("\n3. 批量添加API密钥...")
    response = requests.post(
        f"{BASE_URL}/bulk/index.php/api-keys",
        json={
            "api_keys": [
                "ak_test_123456",
                {"name": "生产环境", "api_key": "ak_prod_abcdef", "api_secret": "secret123"},
                "ak_test_123456",  # 重复的，应该失败
            ]
        },
        headers={
            "Content-Type": "application/json",
            "Authorization": f"Bearer {token}"
        }
    )
    data = response.json()
    if data.get("code") == 200:
        print(f"✓ 批量添加API密钥成功")
        print(f"  总计: {data['data']['total']}")
        print(f"  成功: {data['data']['success_count']}")
        print(f"  失败: {data['data']['failed_count']}")
        for item in data['data']['results']['success']:
            print(f"  ✓ {item['name']}: {item['api_key']}")
        for item in data['data']['results']['failed']:
            print(f"  ✗ {item.get('api_key', item.get('data'))} - {item['reason']}")
    else:
        print(f"✗ 批量添加API密钥失败: {data.get('message')}")
    
    print("\n" + "=" * 60)
    print("测试完成！")
    print("=" * 60)

if __name__ == "__main__":
    test_bulk()
