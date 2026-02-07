#!/usr/bin/env python3
"""
管理员功能测试脚本
测试管理员登录、修改密码、添加用户、修改用户密码、修改用户名等功能
"""

import requests
import json
import sys

# API 基础URL
BASE_URL = "http://192.168.7.178:8080/backend/api"

# 管理员账号（假设ID为1的用户是管理员）
ADMIN_USER = {
    "email": "test123@example.com",  # 使用之前注册的用户
    "password": "123456"
}

# 测试用的普通用户
TEST_USER = {
    "username": "normaluser001",
    "email": "normal001@example.com",
    "password": "123456"
}

# 存储token
ADMIN_TOKEN = None
TEST_USER_ID = None

def print_header(title):
    """打印测试标题"""
    print("\n" + "=" * 60)
    print(f"  {title}")
    print("=" * 60)

def print_result(test_name, success, response=None, error=None):
    """打印测试结果"""
    status = "✓ PASS" if success else "✗ FAIL"
    print(f"\n{status} - {test_name}")
    if response:
        print(f"  响应: {json.dumps(response, indent=2, ensure_ascii=False)[:800]}")
    if error:
        print(f"  错误: {error}")

def test_admin_login():
    """1. 测试管理员登录"""
    global ADMIN_TOKEN
    print_header("1. 管理员登录测试")
    
    try:
        response = requests.post(
            f"{BASE_URL}/auth/index.php/login",
            json=ADMIN_USER,
            headers={"Content-Type": "application/json"},
            timeout=10
        )
        
        print(f"  状态码: {response.status_code}")
        print(f"  响应内容: {response.text[:500]}")
        
        # 尝试解析JSON
        try:
            data = response.json()
            success = data.get("code") == 200
            
            if success:
                ADMIN_TOKEN = data.get("data", {}).get("token")
                print(f"  获取到管理员Token: {ADMIN_TOKEN[:50]}...")
            
            print_result("管理员登录", success, data)
            return success
        except:
            print_result("管理员登录", False, error="响应不是有效的JSON")
            return False
            
    except Exception as e:
        print_result("管理员登录", False, error=str(e))
        return False

def test_admin_get_users():
    """2. 测试管理员获取用户列表"""
    print_header("2. 管理员获取用户列表测试")
    
    if not ADMIN_TOKEN:
        print_result("获取用户列表", False, error="没有管理员token")
        return False
    
    try:
        response = requests.get(
            f"{BASE_URL}/admin/index.php/users",
            headers={"Authorization": f"Bearer {ADMIN_TOKEN}"},
            params={"page": 1, "limit": 10},
            timeout=10
        )
        
        print(f"  状态码: {response.status_code}")
        
        try:
            data = response.json()
            success = data.get("code") == 200
            
            if success and data.get("data", {}).get("list"):
                users = data["data"]["list"]
                print(f"  获取到 {len(users)} 个用户")
                global TEST_USER_ID
                # 保存第一个非管理员用户的ID用于后续测试
                for user in users:
                    if user.get("id") != 1:
                        TEST_USER_ID = user.get("id")
                        print(f"  选择测试用户ID: {TEST_USER_ID}")
                        break
            
            print_result("获取用户列表", success, data)
            return success
        except:
            print(f"  响应内容: {response.text[:500]}")
            print_result("获取用户列表", False, error="响应不是有效的JSON")
            return False
            
    except Exception as e:
        print_result("获取用户列表", False, error=str(e))
        return False

def test_admin_add_user():
    """3. 测试管理员添加用户"""
    print_header("3. 管理员添加用户测试")
    
    if not ADMIN_TOKEN:
        print_result("添加用户", False, error="没有管理员token")
        return False
    
    try:
        response = requests.post(
            f"{BASE_URL}/admin/index.php/users",
            json=TEST_USER,
            headers={
                "Authorization": f"Bearer {ADMIN_TOKEN}",
                "Content-Type": "application/json"
            },
            timeout=10
        )
        
        print(f"  状态码: {response.status_code}")
        
        try:
            data = response.json()
            success = data.get("code") == 200
            
            if success:
                global TEST_USER_ID
                TEST_USER_ID = data.get("data", {}).get("id")
                print(f"  新用户ID: {TEST_USER_ID}")
            
            print_result("添加用户", success, data)
            return success
        except:
            print(f"  响应内容: {response.text[:500]}")
            print_result("添加用户", False, error="响应不是有效的JSON")
            return False
            
    except Exception as e:
        print_result("添加用户", False, error=str(e))
        return False

def test_admin_update_user():
    """4. 测试管理员修改用户信息（用户名）"""
    print_header("4. 管理员修改用户名测试")
    
    if not ADMIN_TOKEN:
        print_result("修改用户名", False, error="没有管理员token")
        return False
    
    if not TEST_USER_ID:
        print_result("修改用户名", False, error="没有测试用户ID")
        return False
    
    try:
        new_username = f"updated_user_{TEST_USER_ID}"
        response = requests.put(
            f"{BASE_URL}/admin/index.php/users/{TEST_USER_ID}",
            json={"username": new_username},
            headers={
                "Authorization": f"Bearer {ADMIN_TOKEN}",
                "Content-Type": "application/json"
            },
            timeout=10
        )
        
        print(f"  状态码: {response.status_code}")
        print(f"  修改用户名: {new_username}")
        
        try:
            data = response.json()
            success = data.get("code") == 200
            print_result("修改用户名", success, data)
            return success
        except:
            print(f"  响应内容: {response.text[:500]}")
            print_result("修改用户名", False, error="响应不是有效的JSON")
            return False
            
    except Exception as e:
        print_result("修改用户名", False, error=str(e))
        return False

def test_admin_reset_password():
    """5. 测试管理员重置用户密码"""
    print_header("5. 管理员重置用户密码测试")
    
    if not ADMIN_TOKEN:
        print_result("重置密码", False, error="没有管理员token")
        return False
    
    if not TEST_USER_ID:
        print_result("重置密码", False, error="没有测试用户ID")
        return False
    
    try:
        new_password = "newpassword123"
        response = requests.post(
            f"{BASE_URL}/admin/index.php/users/{TEST_USER_ID}/reset-password",
            json={"password": new_password},
            headers={
                "Authorization": f"Bearer {ADMIN_TOKEN}",
                "Content-Type": "application/json"
            },
            timeout=10
        )
        
        print(f"  状态码: {response.status_code}")
        print(f"  新密码: {new_password}")
        
        try:
            data = response.json()
            success = data.get("code") == 200
            print_result("重置密码", success, data)
            
            if success:
                # 验证新密码可以登录
                print("  验证新密码登录...")
                login_response = requests.post(
                    f"{BASE_URL}/auth/index.php/login",
                    json={
                        "email": TEST_USER["email"],
                        "password": new_password
                    },
                    headers={"Content-Type": "application/json"},
                    timeout=10
                )
                try:
                    login_data = login_response.json()
                    login_success = login_data.get("code") == 200
                    print(f"  新密码登录: {'成功' if login_success else '失败'}")
                except:
                    print(f"  新密码登录验证失败")
            
            return success
        except:
            print(f"  响应内容: {response.text[:500]}")
            print_result("重置密码", False, error="响应不是有效的JSON")
            return False
            
    except Exception as e:
        print_result("重置密码", False, error=str(e))
        return False

def test_admin_delete_user():
    """6. 测试管理员删除用户"""
    print_header("6. 管理员删除用户测试")
    
    if not ADMIN_TOKEN:
        print_result("删除用户", False, error="没有管理员token")
        return False
    
    if not TEST_USER_ID:
        print_result("删除用户", False, error="没有测试用户ID")
        return False
    
    try:
        response = requests.delete(
            f"{BASE_URL}/admin/index.php/users/{TEST_USER_ID}",
            headers={"Authorization": f"Bearer {ADMIN_TOKEN}"},
            timeout=10
        )
        
        print(f"  状态码: {response.status_code}")
        print(f"  删除用户ID: {TEST_USER_ID}")
        
        try:
            data = response.json()
            success = data.get("code") == 200
            print_result("删除用户", success, data)
            return success
        except:
            print(f"  响应内容: {response.text[:500]}")
            print_result("删除用户", False, error="响应不是有效的JSON")
            return False
            
    except Exception as e:
        print_result("删除用户", False, error=str(e))
        return False

def main():
    """主测试函数"""
    print("\n" + "=" * 60)
    print("  管理员功能测试")
    print("=" * 60)
    print(f"\n测试服务器: http://192.168.7.178:8080")
    print(f"测试时间: {__import__('datetime').datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    
    results = []
    
    # 管理员登录
    results.append(("管理员登录", test_admin_login()))
    
    # 获取用户列表
    results.append(("获取用户列表", test_admin_get_users()))
    
    # 添加用户
    results.append(("添加用户", test_admin_add_user()))
    
    # 修改用户名
    results.append(("修改用户名", test_admin_update_user()))
    
    # 重置密码
    results.append(("重置用户密码", test_admin_reset_password()))
    
    # 删除用户
    results.append(("删除用户", test_admin_delete_user()))
    
    # 打印测试总结
    print("\n" + "=" * 60)
    print("  管理员功能测试总结")
    print("=" * 60)
    
    passed = sum(1 for _, result in results if result)
    total = len(results)
    
    print(f"\n通过: {passed}/{total}")
    print(f"失败: {total - passed}/{total}")
    print(f"成功率: {passed/total*100:.1f}%")
    
    print("\n详细结果:")
    for name, result in results:
        status = "✓" if result else "✗"
        print(f"  {status} {name}")
    
    print("\n" + "=" * 60)
    
    return 0 if passed == total else 1

if __name__ == "__main__":
    sys.exit(main())
