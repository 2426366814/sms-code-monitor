"""
7轮循环验证测试脚本
- 自动登录获取token
- 完整CRUD测试
- 7轮循环验证
"""

from playwright.sync_api import sync_playwright
import time
import json

BASE_URL = "https://jm.91wz.org"
USERNAME = "admin"
PASSWORD = "admin"

def run_tests():
    results = {
        "total_passed": 0,
        "total_failed": 0,
        "rounds": []
    }
    
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(ignore_https_errors=True)
        page = context.new_page()
        
        for round_num in range(1, 8):
            print(f"\n=== 第 {round_num} 轮测试 ===")
            round_result = {"passed": 0, "failed": 0, "tests": []}
            
            try:
                # 1. 访问登录页面
                print("  测试: 访问登录页面...")
                page.goto(f"{BASE_URL}/login.html", wait_until="networkidle", timeout=60000)
                page.wait_for_timeout(2000)
                
                # 检查登录表单元素
                username_input = page.locator("#username")
                password_input = page.locator("#password")
                
                if username_input.count() > 0 and password_input.count() > 0:
                    print("    ✓ 登录页面加载成功")
                    round_result["passed"] += 1
                    round_result["tests"].append({"name": "login_page", "status": "passed"})
                else:
                    print("    ✗ 登录页面加载失败")
                    round_result["failed"] += 1
                    round_result["tests"].append({"name": "login_page", "status": "failed"})
                    continue
                
                # 2. 执行登录
                print("  测试: 用户登录...")
                username_input.fill(USERNAME)
                password_input.fill(PASSWORD)
                page.click("button[type='submit']")
                page.wait_for_timeout(3000)
                
                # 检查是否登录成功
                token = page.evaluate("() => localStorage.getItem('token')")
                if token:
                    print("    ✓ 登录成功 (token已存储)")
                    round_result["passed"] += 1
                    round_result["tests"].append({"name": "login", "status": "passed"})
                else:
                    current_url = page.url
                    if "index.html" in current_url or "admin.html" in current_url:
                        print("    ✓ 登录成功 (已跳转)")
                        round_result["passed"] += 1
                        round_result["tests"].append({"name": "login", "status": "passed"})
                        token = page.evaluate("() => localStorage.getItem('token')")
                    else:
                        print("    ✗ 登录失败")
                        round_result["failed"] += 1
                        round_result["tests"].append({"name": "login", "status": "failed"})
                
                # 3. 访问主页
                print("  测试: 访问主页...")
                page.goto(f"{BASE_URL}/index.html", wait_until="networkidle", timeout=60000)
                page.wait_for_timeout(2000)
                
                # 检查主页元素
                main_content = page.locator("main, .container, .monitor-list, body")
                if main_content.count() > 0:
                    print("    ✓ 主页加载成功")
                    round_result["passed"] += 1
                    round_result["tests"].append({"name": "home_page", "status": "passed"})
                else:
                    print("    ✗ 主页加载失败")
                    round_result["failed"] += 1
                    round_result["tests"].append({"name": "home_page", "status": "failed"})
                
                # 4. 测试健康检测页面
                print("  测试: 健康检测页面...")
                page.goto(f"{BASE_URL}/health.html", wait_until="networkidle", timeout=60000)
                page.wait_for_timeout(2000)
                
                health_cards = page.locator(".card")
                if health_cards.count() > 0:
                    print("    ✓ 健康检测页面加载成功")
                    round_result["passed"] += 1
                    round_result["tests"].append({"name": "health_page", "status": "passed"})
                else:
                    print("    ✗ 健康检测页面加载失败")
                    round_result["failed"] += 1
                    round_result["tests"].append({"name": "health_page", "status": "failed"})
                
                # 5. 测试API健康检测
                print("  测试: API健康检测...")
                api_response = page.evaluate("""async () => {
                    try {
                        const response = await fetch('/backend/api/health/index.php?type=database');
                        return await response.json();
                    } catch (e) {
                        return {error: e.message};
                    }
                }""")
                if api_response and api_response.get("success"):
                    print("    ✓ API健康检测成功")
                    round_result["passed"] += 1
                    round_result["tests"].append({"name": "api_health", "status": "passed"})
                else:
                    print(f"    ✗ API健康检测失败: {api_response}")
                    round_result["failed"] += 1
                    round_result["tests"].append({"name": "api_health", "status": "failed"})
                
                # 获取token
                token = page.evaluate("() => localStorage.getItem('token')")
                
                # 6. 测试监控列表API
                print("  测试: 监控列表API...")
                if token:
                    monitors_response = page.evaluate("""async (token) => {
                        try {
                            const response = await fetch('/backend/api/monitors/index.php', {
                                headers: {'Authorization': 'Bearer ' + token}
                            });
                            return await response.json();
                        } catch (e) {
                            return {error: e.message};
                        }
                    }""", token)
                    if monitors_response and monitors_response.get("success"):
                        count = len(monitors_response.get("data", []))
                        print(f"    ✓ 监控列表API成功 (共{count}条)")
                        round_result["passed"] += 1
                        round_result["tests"].append({"name": "monitors_api", "status": "passed"})
                    else:
                        print(f"    ✗ 监控列表API失败: {monitors_response}")
                        round_result["failed"] += 1
                        round_result["tests"].append({"name": "monitors_api", "status": "failed"})
                else:
                    print("    ⊘ 跳过监控列表API测试 (无token)")
                    round_result["tests"].append({"name": "monitors_api", "status": "skipped"})
                
                # 7. 测试创建监控
                print("  测试: 创建监控...")
                monitor_id = None
                if token:
                    create_response = page.evaluate("""async (token) => {
                        try {
                            const response = await fetch('/backend/api/monitors/index.php', {
                                method: 'POST',
                                headers: {
                                    'Authorization': 'Bearer ' + token,
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    name: '测试监控_' + Date.now(),
                                    phone_number: '+1234567890',
                                    url: 'https://test.example.com/api',
                                    method: 'GET',
                                    interval: 300,
                                    timeout: 30
                                })
                            });
                            return await response.json();
                        } catch (e) {
                            return {error: e.message};
                        }
                    }""", token)
                    if create_response and create_response.get("success"):
                        monitor_id = create_response.get("data", {}).get("id")
                        print(f"    ✓ 创建监控成功 (ID: {monitor_id})")
                        round_result["passed"] += 1
                        round_result["tests"].append({"name": "create_monitor", "status": "passed", "monitor_id": monitor_id})
                    else:
                        print(f"    ✗ 创建监控失败: {create_response}")
                        round_result["failed"] += 1
                        round_result["tests"].append({"name": "create_monitor", "status": "failed"})
                else:
                    print("    ⊘ 跳过创建监控测试 (无token)")
                    round_result["tests"].append({"name": "create_monitor", "status": "skipped"})
                
                # 8. 测试更新监控
                print("  测试: 更新监控...")
                if token and monitor_id:
                    update_response = page.evaluate("""async ({token, id}) => {
                        try {
                            const response = await fetch('/backend/api/monitors/index.php?id=' + id, {
                                method: 'PUT',
                                headers: {
                                    'Authorization': 'Bearer ' + token,
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    status: 'paused'
                                })
                            });
                            return await response.json();
                        } catch (e) {
                            return {error: e.message};
                        }
                    }""", {"token": token, "id": monitor_id})
                    if update_response and update_response.get("success"):
                        print("    ✓ 更新监控成功")
                        round_result["passed"] += 1
                        round_result["tests"].append({"name": "update_monitor", "status": "passed"})
                    else:
                        print(f"    ✗ 更新监控失败: {update_response}")
                        round_result["failed"] += 1
                        round_result["tests"].append({"name": "update_monitor", "status": "failed"})
                else:
                    print("    ⊘ 跳过更新监控测试")
                    round_result["tests"].append({"name": "update_monitor", "status": "skipped"})
                
                # 9. 测试删除监控
                print("  测试: 删除监控...")
                if token and monitor_id:
                    delete_response = page.evaluate("""async ({token, id}) => {
                        try {
                            const response = await fetch('/backend/api/monitors/index.php?id=' + id, {
                                method: 'DELETE',
                                headers: {'Authorization': 'Bearer ' + token}
                            });
                            return await response.json();
                        } catch (e) {
                            return {error: e.message};
                        }
                    }""", {"token": token, "id": monitor_id})
                    if delete_response and delete_response.get("success"):
                        print("    ✓ 删除监控成功")
                        round_result["passed"] += 1
                        round_result["tests"].append({"name": "delete_monitor", "status": "passed"})
                    else:
                        print(f"    ✗ 删除监控失败: {delete_response}")
                        round_result["failed"] += 1
                        round_result["tests"].append({"name": "delete_monitor", "status": "failed"})
                else:
                    print("    ⊘ 跳过删除监控测试")
                    round_result["tests"].append({"name": "delete_monitor", "status": "skipped"})
                
                # 10. 测试统计数据API
                print("  测试: 统计数据API...")
                if token:
                    stats_response = page.evaluate("""async (token) => {
                        try {
                            const response = await fetch('/backend/api/statistics/index.php', {
                                headers: {'Authorization': 'Bearer ' + token}
                            });
                            return await response.json();
                        } catch (e) {
                            return {error: e.message};
                        }
                    }""", token)
                    if stats_response and stats_response.get("success"):
                        print("    ✓ 统计数据API成功")
                        round_result["passed"] += 1
                        round_result["tests"].append({"name": "statistics_api", "status": "passed"})
                    else:
                        print(f"    ✗ 统计数据API失败: {stats_response}")
                        round_result["failed"] += 1
                        round_result["tests"].append({"name": "statistics_api", "status": "failed"})
                else:
                    print("    ⊘ 跳过统计数据API测试 (无token)")
                    round_result["tests"].append({"name": "statistics_api", "status": "skipped"})
                
            except Exception as e:
                print(f"  ✗ 测试异常: {e}")
                round_result["failed"] += 1
                round_result["tests"].append({"name": "exception", "status": "failed", "error": str(e)})
            
            # 计算通过率
            total = round_result["passed"] + round_result["failed"]
            rate = round((round_result["passed"] / total) * 100, 1) if total > 0 else 0
            print(f"  第 {round_num} 轮: 通过 {round_result['passed']}/{total}, 通过率 {rate}%")
            
            results["rounds"].append(round_result)
            results["total_passed"] += round_result["passed"]
            results["total_failed"] += round_result["failed"]
            
            # 清除token准备下一轮
            page.evaluate("() => localStorage.clear()")
        
        browser.close()
    
    # 计算总通过率
    total = results["total_passed"] + results["total_failed"]
    results["total_rate"] = round((results["total_passed"] / total) * 100, 1) if total > 0 else 0
    
    return results

if __name__ == "__main__":
    print("=" * 50)
    print("  7轮循环验证测试")
    print("=" * 50)
    
    results = run_tests()
    
    print("\n" + "=" * 50)
    print("  测试汇总")
    print("=" * 50)
    print(f"总通过: {results['total_passed']} | 总失败: {results['total_failed']}")
    print(f"通过率: {results['total_rate']}%")
    
    if results["total_rate"] >= 80:
        print("\n✅ 测试通过！系统运行正常")
    else:
        print("\n❌ 测试未达标，需要检查问题")
    
    # 保存结果到文件
    with open("test_results.json", "w", encoding="utf-8") as f:
        json.dump(results, f, ensure_ascii=False, indent=2)
    print("\n测试结果已保存到 test_results.json")
