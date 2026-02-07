#!/usr/bin/env python3
"""
API 功能测试脚本
测试多用户动态验证码监控系统的所有API功能
"""

import requests
import json
import sys

# API 基础URL
BASE_URL = "http://192.168.7.178:8080/backend/api"

# 测试数据
TEST_USER = {
    "username": "testuser123",
    "email": "test123@example.com",
    "password": "123456"
}

# 存储token
TOKENS = {
    "user": None,
    "admin": None
}

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
        print(f"  响应: {json.dumps(response, indent=2, ensure_ascii=False)[:500]}")
    if error:
        print(f"  错误: {error}")

def test_health_check():
    """测试健康检查API"""
    print_header("1. 健康检查测试")
    
    try:
        response = requests.get(f"{BASE_URL}/health/index.php?type=all", timeout=10)
        data = response.json()
        success = data.get("code") == 200
        print_result("健康检查", success, data)
        return success
    except Exception as e:
        print_result("健康检查", False, error=str(e))
        return False

def test_user_register():
    """测试用户注册"""
    print_header("2. 用户注册测试")
    
    try:
        response = requests.post(
            f"{BASE_URL}/auth/index.php/register",
            json=TEST_USER,
            headers={"Content-Type": "application/json"},
            timeout=10
        )
        data = response.json()
        success = data.get("code") == 200
        print_result("用户注册", success, data)
        return success
    except Exception as e:
        print_result("用户注册", False, error=str(e))
        return False

def test_user_login():
    """测试用户登录"""
    print_header("3. 用户登录测试")
    
    try:
        response = requests.post(
            f"{BASE_URL}/auth/index.php/login",
            json={
                "email": TEST_USER["email"],
                "password": TEST_USER["password"]
            },
            headers={"Content-Type": "application/json"},
            timeout=10
        )
        data = response.json()
        success = data.get("code") == 200
        
        if success:
            TOKENS["user"] = data.get("data", {}).get("token")
            print(f"  获取到用户Token: {TOKENS['user'][:30]}...")
        
        print_result("用户登录", success, data)
        return success
    except Exception as e:
        print_result("用户登录", False, error=str(e))
        return False

def test_get_current_user():
    """测试获取当前用户信息"""
    print_header("4. 获取当前用户信息测试")
    
    if not TOKENS["user"]:
        print_result("获取当前用户", False, error="没有用户token")
        return False
    
    try:
        response = requests.get(
            f"{BASE_URL}/auth/index.php/me",
            headers={"Authorization": f"Bearer {TOKENS['user']}"},
            timeout=10
        )
        data = response.json()
        success = data.get("code") == 200
        print_result("获取当前用户", success, data)
        return success
    except Exception as e:
        print_result("获取当前用户", False, error=str(e))
        return False

def test_refresh_token():
    """测试刷新token"""
    print_header("5. 刷新Token测试")
    
    if not TOKENS["user"]:
        print_result("刷新Token", False, error="没有用户token")
        return False
    
    try:
        response = requests.post(
            f"{BASE_URL}/auth/index.php/refresh",
            json={"token": TOKENS["user"]},
            headers={"Content-Type": "application/json"},
            timeout=10
        )
        data = response.json()
        success = data.get("code") == 200
        print_result("刷新Token", success, data)
        return success
    except Exception as e:
        print_result("刷新Token", False, error=str(e))
        return False

def test_logout():
    """测试用户登出"""
    print_header("6. 用户登出测试")
    
    try:
        response = requests.post(
            f"{BASE_URL}/auth/index.php/logout",
            headers={"Content-Type": "application/json"},
            timeout=10
        )
        data = response.json()
        success = data.get("code") == 200
        print_result("用户登出", success, data)
        return success
    except Exception as e:
        print_result("用户登出", False, error=str(e))
        return False

def test_admin_login():
    """测试管理员登录"""
    print_header("7. 管理员登录测试")
    
    # 假设管理员账号是 admin/admin 或者使用第一个用户作为管理员
    try:
        # 先尝试用刚注册的用户登录（在测试环境中可能也是管理员）
        response = requests.post(
            f"{BASE_URL}/auth/index.php/login",
            json={
                "email": TEST_USER["email"],
                "password": TEST_USER["password"]
            },
            headers={"Content-Type": "application/json"},
            timeout=10
        )
        data = response.json()
        success = data.get("code") == 200
        
        if success:
            TOKENS["admin"] = data.get("data", {}).get("token")
            print(f"  获取到管理员Token: {TOKENS['admin'][:30]}...")
        
        print_result("管理员登录", success, data)
        return success
    except Exception as e:
        print_result("管理员登录", False, error=str(e))
        return False

def test_admin_get_users():
    """测试管理员获取用户列表"""
    print_header("8. 管理员获取用户列表测试")
    
    if not TOKENS["admin"]:
        print_result("获取用户列表", False, error="没有管理员token")
        return False
    
    try:
        response = requests.get(
            f"{BASE_URL}/admin/index.php/users",
            headers={"Authorization": f"Bearer {TOKENS['admin']}"},
            timeout=10
        )
        data = response.json()
        success = data.get("code") == 200
        print_result("获取用户列表", success, data)
        return success
    except Exception as e:
        print_result("获取用户列表", False, error=str(e))
        return False

def test_monitor_api():
    """测试监控项API"""
    print_header("9. 监控项API测试")
    
    if not TOKENS["user"]:
        print_result("监控项API", False, error="没有用户token")
        return False
    
    try:
        # 获取监控项列表
        response = requests.get(
            f"{BASE_URL}/monitors/index.php",
            headers={"Authorization": f"Bearer {TOKENS['user']}"},
            timeout=10
        )
        data = response.json()
        success = data.get("code") == 200
        print_result("获取监控项列表", success, data)
        return success
    except Exception as e:
        print_result("监控项API", False, error=str(e))
        return False

def test_codes_api():
    """测试验证码API"""
    print_header("10. 验证码API测试")
    
    if not TOKENS["user"]:
        print_result("验证码API", False, error="没有用户token")
        return False
    
    try:
        # 获取验证码列表
        response = requests.get(
            f"{BASE_URL}/codes/index.php",
            headers={"Authorization": f"Bearer {TOKENS['user']}"},
            timeout=10
        )
        data = response.json()
        success = data.get("code") == 200
        print_result("获取验证码列表", success, data)
        return success
    except Exception as e:
        print_result("验证码API", False, error=str(e))
        return False

def main():
    """主测试函数"""
    print("\n" + "=" * 60)
    print("  多用户动态验证码监控系统 - API功能测试")
    print("=" * 60)
    print(f"\n测试服务器: http://192.168.7.178:8080")
    print(f"测试时间: {__import__('datetime').datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    
    results = []
    
    # 基础测试
    results.append(("健康检查", test_health_check()))
    
    # 用户认证测试
    results.append(("用户注册", test_user_register()))
    results.append(("用户登录", test_user_login()))
    results.append(("获取当前用户", test_get_current_user()))
    results.append(("刷新Token", test_refresh_token()))
    results.append(("用户登出", test_logout()))
    
    # 重新登录以获取token
    test_user_login()
    
    # 管理员测试
    results.append(("管理员登录", test_admin_login()))
    results.append(("获取用户列表", test_admin_get_users()))
    
    # 监控项和验证码测试
    results.append(("监控项API", test_monitor_api()))
    results.append(("验证码API", test_codes_api()))
    
    # 打印测试总结
    print("\n" + "=" * 60)
    print("  测试总结")
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
    
    # 返回退出码
    return 0 if passed == total else 1

if __name__ == "__main__":
    sys.exit(main())
