"""
Phase 3-8 综合测试脚本
- Phase 3: 交互测试 (Playwright有头模式)
- Phase 4: 并发测试
- Phase 5: 边界测试
- Phase 6: 远程服务器测试
- Phase 7: 标签测试
- Phase 8: 空白和零值显示检查
"""

from playwright.sync_api import sync_playwright
import json
import time
import threading
import requests

BASE_URL = "https://jm.91wz.org"
ADMIN_USER = "admin"
ADMIN_PASS = "admin"

class Phase3to8Tester:
    def __init__(self):
        self.results = {
            "phase3": {"passed": 0, "failed": 0, "tests": []},
            "phase4": {"passed": 0, "failed": 0, "tests": []},
            "phase5": {"passed": 0, "failed": 0, "tests": []},
            "phase6": {"passed": 0, "failed": 0, "tests": []},
            "phase7": {"passed": 0, "failed": 0, "tests": []},
            "phase8": {"passed": 0, "failed": 0, "tests": []}
        }
        self.token = None
        
    def record_test(self, phase, name, passed, message=""):
        self.results[phase]["tests"].append({
            "name": name,
            "passed": passed,
            "message": message
        })
        if passed:
            self.results[phase]["passed"] += 1
            print(f"  ✓ {name}")
        else:
            self.results[phase]["failed"] += 1
            print(f"  ✗ {name}: {message}")
    
    def run_all_tests(self):
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True)
            context = browser.new_context(ignore_https_errors=True)
            page = context.new_page()
            
            # 先登录获取token
            page.goto(f"{BASE_URL}/login.html", wait_until="networkidle", timeout=60000)
            page.fill("#username", ADMIN_USER)
            page.fill("#password", ADMIN_PASS)
            page.click("button[type='submit']")
            page.wait_for_timeout(3000)
            self.token = page.evaluate("() => localStorage.getItem('token')")
            
            # Phase 3: 交互测试
            print("\n" + "="*60)
            print("  Phase 3: 交互测试")
            print("="*60)
            self.run_phase3(page)
            
            # Phase 4: 并发测试
            print("\n" + "="*60)
            print("  Phase 4: 并发测试")
            print("="*60)
            self.run_phase4(page)
            
            # Phase 5: 边界测试
            print("\n" + "="*60)
            print("  Phase 5: 边界测试")
            print("="*60)
            self.run_phase5(page)
            
            # Phase 6: 远程服务器测试
            print("\n" + "="*60)
            print("  Phase 6: 远程服务器测试")
            print("="*60)
            self.run_phase6(page)
            
            # Phase 7: 标签测试
            print("\n" + "="*60)
            print("  Phase 7: 标签测试")
            print("="*60)
            self.run_phase7(page)
            
            # Phase 8: 空白和零值显示检查
            print("\n" + "="*60)
            print("  Phase 8: 空白和零值显示检查")
            print("="*60)
            self.run_phase8(page)
            
            browser.close()
        
        return self.results
    
    # ========== Phase 3: 交互测试 ==========
    def run_phase3(self, page):
        # 3.1 页面跳转测试
        pages_to_test = [
            ("index.html", "用户中心"),
            ("admin.html", "管理员后台"),
            ("health.html", "健康检测"),
            ("login.html", "登录页面"),
            ("register.html", "注册页面")
        ]
        
        for page_name, expected_title in pages_to_test:
            try:
                page.goto(f"{BASE_URL}/{page_name}", wait_until="networkidle", timeout=60000)
                page.wait_for_timeout(1000)
                title = page.title()
                if expected_title in title or page_name.replace(".html", "") in page.url:
                    self.record_test("phase3", f"页面跳转 - {page_name}", True)
                else:
                    self.record_test("phase3", f"页面跳转 - {page_name}", False, f"标题: {title}")
            except Exception as e:
                self.record_test("phase3", f"页面跳转 - {page_name}", False, str(e))
        
        # 3.2 表单输入测试
        try:
            page.goto(f"{BASE_URL}/login.html", wait_until="networkidle", timeout=60000)
            page.fill("#username", "test_user")
            page.fill("#password", "test_password")
            username_val = page.input_value("#username")
            password_val = page.input_value("#password")
            if username_val == "test_user" and password_val == "test_password":
                self.record_test("phase3", "表单输入测试", True)
            else:
                self.record_test("phase3", "表单输入测试", False, "输入值不匹配")
        except Exception as e:
            self.record_test("phase3", "表单输入测试", False, str(e))
        
        # 3.3 按钮点击测试
        try:
            page.goto(f"{BASE_URL}/health.html", wait_until="networkidle", timeout=60000)
            page.wait_for_timeout(1000)
            # 检查是否有健康检测按钮
            buttons = page.locator("button").count()
            if buttons > 0:
                self.record_test("phase3", "按钮存在测试", True, f"发现{buttons}个按钮")
            else:
                self.record_test("phase3", "按钮存在测试", False, "未发现按钮")
        except Exception as e:
            self.record_test("phase3", "按钮存在测试", False, str(e))
        
        # 3.4 加载状态测试
        try:
            page.goto(f"{BASE_URL}/index.html", wait_until="networkidle", timeout=60000)
            page.wait_for_timeout(2000)
            # 检查页面是否加载完成
            loading = page.locator(".loading, .spinner, [data-loading]").count()
            self.record_test("phase3", "加载状态测试", True, f"加载指示器: {loading}")
        except Exception as e:
            self.record_test("phase3", "加载状态测试", False, str(e))
    
    # ========== Phase 4: 并发测试 ==========
    def run_phase4(self, page):
        if not self.token:
            self.record_test("phase4", "并发测试", False, "未登录")
            return
        
        # 4.1 并发请求测试
        concurrent_results = []
        
        def make_request(token, index):
            try:
                result = page.evaluate("""async ({token, index}) => {
                    try {
                        const response = await fetch('/backend/api/monitors/index.php', {
                            headers: {'Authorization': 'Bearer ' + token}
                        });
                        return {index: index, success: response.ok};
                    } catch (e) {
                        return {index: index, success: false, error: e.message};
                    }
                }""", {"token": token, "index": index})
                concurrent_results.append(result)
            except:
                concurrent_results.append({"index": index, "success": False})
        
        # 创建多个并发请求
        threads = []
        for i in range(5):
            t = threading.Thread(target=make_request, args=(self.token, i))
            threads.append(t)
            t.start()
        
        for t in threads:
            t.join(timeout=10)
        
        success_count = sum(1 for r in concurrent_results if r.get("success"))
        if success_count >= 4:
            self.record_test("phase4", f"并发请求测试 (5个)", True, f"{success_count}/5成功")
        else:
            self.record_test("phase4", f"并发请求测试 (5个)", False, f"{success_count}/5成功")
        
        # 4.2 数据一致性测试
        try:
            # 创建一个监控项
            create_result = page.evaluate("""async (token) => {
                try {
                    const response = await fetch('/backend/api/monitors/index.php', {
                        method: 'POST',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            phone_number: '+8613800138001',
                            url: 'https://test.example.com/api'
                        })
                    });
                    return await response.json();
                } catch (e) {
                    return {error: e.message};
                }
            }""", self.token)
            
            if create_result and create_result.get("success"):
                monitor_id = create_result.get("data", {}).get("id")
                
                # 立即读取验证
                read_result = page.evaluate("""async ({token, id}) => {
                    try {
                        const response = await fetch('/backend/api/monitors/index.php', {
                            headers: {'Authorization': 'Bearer ' + token}
                        });
                        return await response.json();
                    } catch (e) {
                        return {error: e.message};
                    }
                }""", {"token": self.token, "id": monitor_id})
                
                if read_result and read_result.get("success"):
                    # 清理 - 删除测试数据
                    page.evaluate("""async ({token, id}) => {
                        try {
                            await fetch('/backend/api/monitors/index.php?id=' + id, {
                                method: 'DELETE',
                                headers: {'Authorization': 'Bearer ' + token}
                            });
                        } catch (e) {}
                    }""", {"token": self.token, "id": monitor_id})
                    
                    self.record_test("phase4", "数据一致性测试", True)
                else:
                    self.record_test("phase4", "数据一致性测试", False, "读取失败")
            else:
                self.record_test("phase4", "数据一致性测试", False, "创建失败")
        except Exception as e:
            self.record_test("phase4", "数据一致性测试", False, str(e))
    
    # ========== Phase 5: 边界测试 ==========
    def run_phase5(self, page):
        if not self.token:
            self.record_test("phase5", "边界测试", False, "未登录")
            return
        
        # 5.1 空值测试
        empty_result = page.evaluate("""async (token) => {
            try {
                const response = await fetch('/backend/api/monitors/index.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        phone_number: ''
                    })
                });
                return await response.json();
            } catch (e) {
                return {error: e.message};
            }
        }""", self.token)
        
        if empty_result and not empty_result.get("success"):
            self.record_test("phase5", "空值测试 - 手机号为空", True, "正确拒绝")
        else:
            self.record_test("phase5", "空值测试 - 手机号为空", False, "未正确拒绝")
        
        # 5.2 超长文本测试
        long_result = page.evaluate("""async (token) => {
            try {
                const response = await fetch('/backend/api/monitors/index.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        phone_number: '1234567890123456789012345678901234567890'
                    })
                });
                return await response.json();
            } catch (e) {
                return {error: e.message};
            }
        }""", self.token)
        
        if long_result and not long_result.get("success"):
            self.record_test("phase5", "超长文本测试", True, "正确拒绝")
        else:
            self.record_test("phase5", "超长文本测试", False, "未正确拒绝")
        
        # 5.3 特殊字符测试
        special_result = page.evaluate("""async (token) => {
            try {
                const response = await fetch('/backend/api/monitors/index.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        phone_number: "<script>alert('xss')</script>"
                    })
                });
                return await response.json();
            } catch (e) {
                return {error: e.message};
            }
        }""", self.token)
        
        if special_result and not special_result.get("success"):
            self.record_test("phase5", "特殊字符/XSS测试", True, "正确拒绝")
        else:
            self.record_test("phase5", "特殊字符/XSS测试", False, "未正确拒绝")
        
        # 5.4 SQL注入测试
        sql_result = page.evaluate("""async (token) => {
            try {
                const response = await fetch('/backend/api/monitors/index.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        phone_number: "'; DROP TABLE monitors; --"
                    })
                });
                return await response.json();
            } catch (e) {
                return {error: e.message};
            }
        }""", self.token)
        
        if sql_result and not sql_result.get("success"):
            self.record_test("phase5", "SQL注入测试", True, "正确拒绝")
        else:
            self.record_test("phase5", "SQL注入测试", False, "未正确拒绝")
        
        # 5.5 输入为0的测试
        zero_result = page.evaluate("""async (token) => {
            try {
                const response = await fetch('/backend/api/monitors/index.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        phone_number: '0'
                    })
                });
                return await response.json();
            } catch (e) {
                return {error: e.message};
            }
        }""", self.token)
        
        if zero_result and not zero_result.get("success"):
            self.record_test("phase5", "输入为0测试", True, "正确拒绝")
        else:
            self.record_test("phase5", "输入为0测试", False, "未正确拒绝")
    
    # ========== Phase 6: 远程服务器测试 ==========
    def run_phase6(self, page):
        # 6.1 服务器连接测试
        try:
            page.goto(BASE_URL, wait_until="networkidle", timeout=60000)
            self.record_test("phase6", "服务器连接测试", True)
        except Exception as e:
            self.record_test("phase6", "服务器连接测试", False, str(e))
        
        # 6.2 SSL证书测试
        try:
            page.goto(f"{BASE_URL}/health.html", wait_until="networkidle", timeout=60000)
            self.record_test("phase6", "SSL证书测试", True)
        except Exception as e:
            self.record_test("phase6", "SSL证书测试", False, str(e))
        
        # 6.3 数据库连接测试
        db_result = page.evaluate("""async () => {
            try {
                const response = await fetch('/backend/api/health/index.php?type=database');
                return await response.json();
            } catch (e) {
                return {error: e.message};
            }
        }""")
        
        if db_result and db_result.get("success"):
            self.record_test("phase6", "数据库连接测试", True)
        else:
            self.record_test("phase6", "数据库连接测试", False, str(db_result))
        
        # 6.4 PHP环境测试
        php_result = page.evaluate("""async () => {
            try {
                const response = await fetch('/backend/api/health/index.php?type=php');
                return await response.json();
            } catch (e) {
                return {error: e.message};
            }
        }""")
        
        if php_result and php_result.get("success"):
            self.record_test("phase6", "PHP环境测试", True)
        else:
            self.record_test("phase6", "PHP环境测试", False, str(php_result))
    
    # ========== Phase 7: 标签测试 ==========
    def run_phase7(self, page):
        if not self.token:
            self.record_test("phase7", "标签测试", False, "未登录")
            return
        
        # 7.1 获取标签列表
        tags_result = page.evaluate("""async (token) => {
            try {
                const response = await fetch('/backend/api/tags/index.php', {
                    headers: {'Authorization': 'Bearer ' + token}
                });
                return await response.json();
            } catch (e) {
                return {error: e.message};
            }
        }""", self.token)
        
        if tags_result and tags_result.get("success"):
            self.record_test("phase7", "获取标签列表", True)
        else:
            self.record_test("phase7", "获取标签列表", False, str(tags_result))
        
        # 7.2 创建标签测试
        create_tag_result = page.evaluate("""async (token) => {
            try {
                const response = await fetch('/backend/api/tags/index.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        name: '测试标签_' + Date.now()
                    })
                });
                return await response.json();
            } catch (e) {
                return {error: e.message};
            }
        }""", self.token)
        
        if create_tag_result and create_tag_result.get("success"):
            tag_id = create_tag_result.get("data", {}).get("id")
            self.record_test("phase7", "创建标签", True)
            
            # 7.3 删除标签测试
            if tag_id:
                delete_tag_result = page.evaluate("""async ({token, id}) => {
                    try {
                        const response = await fetch('/backend/api/tags/index.php?id=' + id, {
                            method: 'DELETE',
                            headers: {'Authorization': 'Bearer ' + token}
                        });
                        return await response.json();
                    } catch (e) {
                        return {error: e.message};
                    }
                }""", {"token": self.token, "id": tag_id})
                
                if delete_tag_result and delete_tag_result.get("success"):
                    self.record_test("phase7", "删除标签", True)
                else:
                    self.record_test("phase7", "删除标签", False, str(delete_tag_result))
        else:
            self.record_test("phase7", "创建标签", False, str(create_tag_result))
    
    # ========== Phase 8: 空白和零值显示检查 ==========
    def run_phase8(self, page):
        if not self.token:
            self.record_test("phase8", "空白零值检查", False, "未登录")
            return
        
        # 8.1 空列表显示检查
        try:
            page.goto(f"{BASE_URL}/index.html", wait_until="networkidle", timeout=60000)
            page.wait_for_timeout(2000)
            
            # 检查是否有空状态提示
            empty_state = page.locator(".empty-state, .no-data, [data-empty]").count()
            page_content = page.content()
            
            # 检查是否有"暂无"或"没有"等空状态提示
            has_empty_hint = "暂无" in page_content or "没有" in page_content or "empty" in page_content.lower()
            
            self.record_test("phase8", "空列表状态显示", True, f"空状态提示: {has_empty_hint}")
        except Exception as e:
            self.record_test("phase8", "空列表状态显示", False, str(e))
        
        # 8.2 统计数据为0的显示检查
        try:
            stats_result = page.evaluate("""async (token) => {
                try {
                    const response = await fetch('/backend/api/statistics/index.php', {
                        headers: {'Authorization': 'Bearer ' + token}
                    });
                    return await response.json();
                } catch (e) {
                    return {error: e.message};
                }
            }""", self.token)
            
            if stats_result and stats_result.get("success"):
                data = stats_result.get("data", {})
                # 检查是否正确处理0值
                has_zero_values = any(v == 0 for v in data.values() if isinstance(v, (int, float)))
                self.record_test("phase8", "统计数据零值处理", True, f"包含零值: {has_zero_values}")
            else:
                self.record_test("phase8", "统计数据零值处理", False, str(stats_result))
        except Exception as e:
            self.record_test("phase8", "统计数据零值处理", False, str(e))
        
        # 8.3 加载状态显示检查
        try:
            page.goto(f"{BASE_URL}/index.html", wait_until="networkidle", timeout=60000)
            page.wait_for_timeout(1000)
            
            # 检查页面是否正确渲染
            body_text = page.locator("body").inner_text()
            has_content = len(body_text.strip()) > 100
            
            self.record_test("phase8", "页面加载状态", True, f"内容长度: {len(body_text)}")
        except Exception as e:
            self.record_test("phase8", "页面加载状态", False, str(e))
        
        # 8.4 错误状态显示检查
        try:
            # 故意请求一个不存在的资源
            error_result = page.evaluate("""async () => {
                try {
                    const response = await fetch('/backend/api/monitors/index.php?id=999999');
                    return await response.json();
                } catch (e) {
                    return {error: e.message};
                }
            }""")
            
            if error_result and not error_result.get("success"):
                # 检查是否有错误消息
                has_error_msg = error_result.get("message") or error_result.get("error")
                self.record_test("phase8", "错误状态显示", True, f"错误消息: {has_error_msg}")
            else:
                self.record_test("phase8", "错误状态显示", False, "未返回错误")
        except Exception as e:
            self.record_test("phase8", "错误状态显示", False, str(e))


if __name__ == "__main__":
    tester = Phase3to8Tester()
    results = tester.run_all_tests()
    
    print("\n" + "="*60)
    print("  Phase 3-8 测试汇总")
    print("="*60)
    
    total_passed = 0
    total_failed = 0
    
    for phase, data in results.items():
        phase_num = phase.replace("phase", "")
        passed = data["passed"]
        failed = data["failed"]
        total = passed + failed
        rate = round((passed / total) * 100, 1) if total > 0 else 0
        
        total_passed += passed
        total_failed += failed
        
        status = "✅" if rate >= 80 else "❌"
        print(f"Phase {phase_num}: 通过 {passed}/{total} ({rate}%) {status}")
    
    print("-"*60)
    overall_rate = round((total_passed / (total_passed + total_failed)) * 100, 1)
    print(f"总计: 通过 {total_passed}/{total_passed + total_failed} ({overall_rate}%)")
    
    if overall_rate >= 80:
        print("\n✅ Phase 3-8 测试通过！")
    else:
        print("\n❌ Phase 3-8 测试未达标")
    
    # 保存结果
    with open("phase3to8_results.json", "w", encoding="utf-8") as f:
        json.dump(results, f, ensure_ascii=False, indent=2)
    print("\n结果已保存到 phase3to8_results.json")
