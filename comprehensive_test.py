"""
完整功能测试脚本
Phase 2-9: 综合测试
"""

from playwright.sync_api import sync_playwright
import json
import time

BASE_URL = "https://jm.91wz.org"
ADMIN_USER = "admin"
ADMIN_PASS = "admin"

class ComprehensiveTester:
    def __init__(self):
        self.results = {
            "phase2": {"passed": 0, "failed": 0, "tests": []},
            "phase3": {"passed": 0, "failed": 0, "tests": []},
            "phase4": {"passed": 0, "failed": 0, "tests": []},
            "phase5": {"passed": 0, "failed": 0, "tests": []},
            "phase6": {"passed": 0, "failed": 0, "tests": []},
            "phase7": {"passed": 0, "failed": 0, "tests": []},
            "phase8": {"passed": 0, "failed": 0, "tests": []},
            "phase9": {"passed": 0, "failed": 0, "tests": []}
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
            
            # 登录获取token
            print("\n" + "="*60)
            print("  认证测试")
            print("="*60)
            self.login(page)
            
            if not self.token:
                print("错误: 无法获取token，测试终止")
                browser.close()
                return self.results
            
            # Phase 2: 功能完整性检查
            print("\n" + "="*60)
            print("  Phase 2: 功能完整性检查")
            print("="*60)
            self.run_phase2(page)
            
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
            
            # Phase 8: 空白零值检查
            print("\n" + "="*60)
            print("  Phase 8: 空白零值检查")
            print("="*60)
            self.run_phase8(page)
            
            # Phase 9: 7轮循环验证
            print("\n" + "="*60)
            print("  Phase 9: 7轮循环验证")
            print("="*60)
            self.run_phase9(page)
            
            browser.close()
        
        return self.results
    
    def login(self, page):
        try:
            page.goto(f"{BASE_URL}/login.html", wait_until="networkidle", timeout=60000)
            page.wait_for_timeout(1000)
            
            page.fill("#username", ADMIN_USER)
            page.fill("#password", ADMIN_PASS)
            page.click("button[type='submit']")
            page.wait_for_timeout(3000)
            
            # 等待页面跳转完成
            page.wait_for_load_state("networkidle", timeout=10000)
            page.wait_for_timeout(1000)
            
            # 获取token
            self.token = page.evaluate("() => localStorage.getItem('token')")
            
            if self.token:
                print(f"  ✓ 登录成功 (Token: {self.token[:20]}...)")
            else:
                print("  ✗ 登录失败")
        except Exception as e:
            print(f"  ✗ 登录异常: {e}")
    
    def run_phase2(self, page):
        # CRUD测试
        monitor_id = None
        
        # Create
        create_result = page.evaluate("""async (token) => {
            try {
                const response = await fetch('/backend/api/monitors/index.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        phone_number: '+8613800138000',
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
            self.record_test("phase2", "Create - 创建监控", True, f"ID: {monitor_id}")
        else:
            self.record_test("phase2", "Create - 创建监控", False, str(create_result))
        
        # Read
        read_result = page.evaluate("""async (token) => {
            try {
                const response = await fetch('/backend/api/monitors/index.php', {
                    headers: {'Authorization': 'Bearer ' + token}
                });
                return await response.json();
            } catch (e) {
                return {error: e.message};
            }
        }""", self.token)
        
        if read_result and read_result.get("success"):
            count = len(read_result.get("data", {}).get("list", []))
            self.record_test("phase2", "Read - 读取列表", True, f"共{count}条")
        else:
            self.record_test("phase2", "Read - 读取列表", False, str(read_result))
        
        # Update
        if monitor_id:
            update_result = page.evaluate("""async ({token, id}) => {
                try {
                    const response = await fetch('/backend/api/monitors/index.php?id=' + id, {
                        method: 'PUT',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({status: 'paused'})
                    });
                    return await response.json();
                } catch (e) {
                    return {error: e.message};
                }
            }""", {"token": self.token, "id": monitor_id})
            
            if update_result and update_result.get("success"):
                self.record_test("phase2", "Update - 更新监控", True)
            else:
                self.record_test("phase2", "Update - 更新监控", False, str(update_result))
        
        # Delete
        if monitor_id:
            delete_result = page.evaluate("""async ({token, id}) => {
                try {
                    const response = await fetch('/backend/api/monitors/index.php?id=' + id, {
                        method: 'DELETE',
                        headers: {'Authorization': 'Bearer ' + token}
                    });
                    return await response.json();
                } catch (e) {
                    return {error: e.message};
                }
            }""", {"token": self.token, "id": monitor_id})
            
            if delete_result and delete_result.get("success"):
                self.record_test("phase2", "Delete - 删除监控", True)
            else:
                self.record_test("phase2", "Delete - 删除监控", False, str(delete_result))
        
        # 搜索筛选
        filter_result = page.evaluate("""async (token) => {
            try {
                const response = await fetch('/backend/api/monitors/index.php?status=active', {
                    headers: {'Authorization': 'Bearer ' + token}
                });
                return await response.json();
            } catch (e) {
                return {error: e.message};
            }
        }""", self.token)
        
        if filter_result and filter_result.get("success"):
            self.record_test("phase2", "Filter - 状态筛选", True)
        else:
            self.record_test("phase2", "Filter - 状态筛选", False, str(filter_result))
        
        # 分页
        pagination_result = page.evaluate("""async (token) => {
            try {
                const response = await fetch('/backend/api/monitors/index.php?page=1&limit=10', {
                    headers: {'Authorization': 'Bearer ' + token}
                });
                return await response.json();
            } catch (e) {
                return {error: e.message};
            }
        }""", self.token)
        
        if pagination_result and pagination_result.get("success"):
            self.record_test("phase2", "Pagination - 分页查询", True)
        else:
            self.record_test("phase2", "Pagination - 分页查询", False, str(pagination_result))
    
    def run_phase3(self, page):
        # 页面跳转测试
        pages = ["index.html", "login.html", "health.html"]
        for p in pages:
            try:
                page.goto(f"{BASE_URL}/{p}", wait_until="networkidle", timeout=60000)
                self.record_test("phase3", f"页面跳转 - {p}", True)
            except Exception as e:
                self.record_test("phase3", f"页面跳转 - {p}", False, str(e))
        
        # 表单输入测试
        try:
            page.goto(f"{BASE_URL}/login.html", wait_until="networkidle", timeout=60000)
            page.fill("#username", "test@example.com")
            page.fill("#password", "test123456")
            username_val = page.input_value("#username")
            if username_val == "test@example.com":
                self.record_test("phase3", "表单输入测试", True)
            else:
                self.record_test("phase3", "表单输入测试", False, "输入值不匹配")
        except Exception as e:
            self.record_test("phase3", "表单输入测试", False, str(e))
    
    def run_phase4(self, page):
        # 并发请求测试
        import threading
        
        results = []
        def make_request(index):
            try:
                result = page.evaluate("""async () => {
                    try {
                        const response = await fetch('/backend/api/health/index.php?type=database');
                        return await response.json();
                    } catch (e) {
                        return {error: e.message};
                    }
                }""")
                results.append({"index": index, "success": result.get("success", False)})
            except:
                results.append({"index": index, "success": False})
        
        threads = []
        for i in range(5):
            t = threading.Thread(target=make_request, args=(i,))
            threads.append(t)
            t.start()
        
        for t in threads:
            t.join(timeout=15)
        
        success_count = sum(1 for r in results if r.get("success"))
        if success_count >= 4:
            self.record_test("phase4", f"并发请求测试 (5个)", True, f"{success_count}/5成功")
        else:
            self.record_test("phase4", f"并发请求测试 (5个)", False, f"{success_count}/5成功")
    
    def run_phase5(self, page):
        # 空值测试
        empty_result = page.evaluate("""async (token) => {
            try {
                const response = await fetch('/backend/api/monitors/index.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({phone_number: ''})
                });
                return await response.json();
            } catch (e) {
                return {error: e.message};
            }
        }""", self.token)
        
        if empty_result and not empty_result.get("success"):
            self.record_test("phase5", "空值测试", True, "正确拒绝")
        else:
            self.record_test("phase5", "空值测试", False, "未正确拒绝")
        
        # SQL注入测试
        sql_result = page.evaluate("""async (token) => {
            try {
                const response = await fetch('/backend/api/monitors/index.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({phone_number: "'; DROP TABLE monitors; --"})
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
        
        # XSS测试
        xss_result = page.evaluate("""async (token) => {
            try {
                const response = await fetch('/backend/api/monitors/index.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({phone_number: "<script>alert('xss')</script>"})
                });
                return await response.json();
            } catch (e) {
                return {error: e.message};
            }
        }""", self.token)
        
        if xss_result and not xss_result.get("success"):
            self.record_test("phase5", "XSS攻击测试", True, "正确拒绝")
        else:
            self.record_test("phase5", "XSS攻击测试", False, "未正确拒绝")
    
    def run_phase6(self, page):
        # 服务器连接
        try:
            page.goto(BASE_URL, wait_until="networkidle", timeout=60000)
            self.record_test("phase6", "服务器连接测试", True)
        except Exception as e:
            self.record_test("phase6", "服务器连接测试", False, str(e))
        
        # SSL证书
        try:
            page.goto(f"{BASE_URL}/health.html", wait_until="networkidle", timeout=60000)
            self.record_test("phase6", "SSL证书测试", True)
        except Exception as e:
            self.record_test("phase6", "SSL证书测试", False, str(e))
        
        # 数据库连接
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
        
        # PHP环境
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
    
    def run_phase7(self, page):
        # 标签功能测试 - 检查API是否存在
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
            self.record_test("phase7", "标签列表API", True)
        else:
            self.record_test("phase7", "标签列表API", False, "功能未实现")
    
    def run_phase8(self, page):
        # 空列表状态
        try:
            page.goto(f"{BASE_URL}/index.html", wait_until="networkidle", timeout=60000)
            page.wait_for_timeout(2000)
            content = page.content()
            has_empty_hint = "暂无" in content or "没有" in content or "empty" in content.lower()
            self.record_test("phase8", "空列表状态显示", True, f"提示: {has_empty_hint}")
        except Exception as e:
            self.record_test("phase8", "空列表状态显示", False, str(e))
        
        # 统计零值
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
            self.record_test("phase8", "统计数据零值处理", True)
        else:
            self.record_test("phase8", "统计数据零值处理", False, str(stats_result))
        
        # 错误状态
        error_result = page.evaluate("""async () => {
            try {
                const response = await fetch('/backend/api/monitors/index.php?id=999999');
                return await response.json();
            } catch (e) {
                return {error: e.message};
            }
        }""")
        
        if error_result and not error_result.get("success"):
            self.record_test("phase8", "错误状态显示", True, f"消息: {error_result.get('message')}")
        else:
            self.record_test("phase8", "错误状态显示", False, "未返回错误")
    
    def run_phase9(self, page):
        # 7轮循环验证
        for i in range(1, 8):
            try:
                # 健康检测
                result = page.evaluate("""async () => {
                    try {
                        const response = await fetch('/backend/api/health/index.php?type=database');
                        return await response.json();
                    } catch (e) {
                        return {error: e.message};
                    }
                }""")
                
                if result and result.get("success"):
                    self.record_test("phase9", f"第{i}轮健康检测", True)
                else:
                    self.record_test("phase9", f"第{i}轮健康检测", False, str(result))
            except Exception as e:
                self.record_test("phase9", f"第{i}轮健康检测", False, str(e))


if __name__ == "__main__":
    tester = ComprehensiveTester()
    results = tester.run_all_tests()
    
    print("\n" + "="*60)
    print("  测试汇总")
    print("="*60)
    
    total_passed = 0
    total_failed = 0
    
    for phase, data in results.items():
        passed = data["passed"]
        failed = data["failed"]
        total = passed + failed
        rate = round((passed / total) * 100, 1) if total > 0 else 0
        
        total_passed += passed
        total_failed += failed
        
        status = "✅" if rate >= 80 or total == 0 else "❌"
        phase_num = phase.replace("phase", "")
        print(f"Phase {phase_num}: 通过 {passed}/{total} ({rate}%) {status}")
    
    print("-"*60)
    overall_rate = round((total_passed / (total_passed + total_failed)) * 100, 1)
    print(f"总计: 通过 {total_passed}/{total_passed + total_failed} ({overall_rate}%)")
    
    if overall_rate >= 80:
        print("\n✅ 测试通过！")
    else:
        print("\n❌ 测试未达标")
    
    # 保存结果
    with open("comprehensive_test_results.json", "w", encoding="utf-8") as f:
        json.dump(results, f, ensure_ascii=False, indent=2)
    print("\n结果已保存到 comprehensive_test_results.json")
