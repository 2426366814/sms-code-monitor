"""
完整功能测试脚本
Phase 2: 功能完整性检查
- CRUD测试
- 搜索/筛选/排序/分页测试
"""

from playwright.sync_api import sync_playwright
import json
import time

BASE_URL = "https://jm.91wz.org"
ADMIN_USER = "admin"
ADMIN_PASS = "admin"

class Phase2Tester:
    def __init__(self):
        self.results = {
            "total_passed": 0,
            "total_failed": 0,
            "tests": []
        }
        self.token = None
        self.test_monitor_id = None
        
    def record_test(self, name, passed, message=""):
        self.results["tests"].append({
            "name": name,
            "passed": passed,
            "message": message
        })
        if passed:
            self.results["total_passed"] += 1
            print(f"  ✓ {name}")
        else:
            self.results["total_failed"] += 1
            print(f"  ✗ {name}: {message}")
    
    def run_all_tests(self):
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True)
            context = browser.new_context(ignore_https_errors=True)
            page = context.new_page()
            
            print("\n" + "="*60)
            print("  Phase 2: 功能完整性检查")
            print("="*60)
            
            # 1. 登录测试
            print("\n【1. 认证功能测试】")
            self.test_login(page)
            
            # 2. 监控CRUD测试
            print("\n【2. 监控CRUD测试】")
            self.test_monitor_crud(page)
            
            # 3. 搜索筛选测试
            print("\n【3. 搜索筛选测试】")
            self.test_search_filter(page)
            
            # 4. 排序分页测试
            print("\n【4. 排序分页测试】")
            self.test_sort_pagination(page)
            
            # 5. API密钥测试
            print("\n【5. API密钥测试】")
            self.test_api_keys(page)
            
            # 6. 统计数据测试
            print("\n【6. 统计数据测试】")
            self.test_statistics(page)
            
            # 7. 健康检测测试
            print("\n【7. 健康检测测试】")
            self.test_health_check(page)
            
            # 8. 验证码历史测试
            print("\n【8. 验证码历史测试】")
            self.test_code_history(page)
            
            browser.close()
        
        return self.results
    
    def test_login(self, page):
        # 测试登录页面
        page.goto(f"{BASE_URL}/login.html", wait_until="networkidle", timeout=60000)
        page.wait_for_timeout(1000)
        
        # 填写登录表单
        page.fill("#username", ADMIN_USER)
        page.fill("#password", ADMIN_PASS)
        page.click("button[type='submit']")
        page.wait_for_timeout(3000)
        
        # 获取token
        self.token = page.evaluate("() => localStorage.getItem('token')")
        
        if self.token:
            self.record_test("用户登录", True, "Token获取成功")
        else:
            self.record_test("用户登录", False, "Token获取失败")
    
    def test_monitor_crud(self, page):
        if not self.token:
            self.record_test("监控CRUD", False, "未登录")
            return
        
        # Create - 创建监控
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
                        url: 'https://test.example.com/api/sms'
                    })
                });
                return await response.json();
            } catch (e) {
                return {error: e.message};
            }
        }""", self.token)
        
        if create_result and create_result.get("success"):
            self.test_monitor_id = create_result.get("data", {}).get("id")
            self.record_test("Create - 创建监控", True, f"ID: {self.test_monitor_id}")
        else:
            self.record_test("Create - 创建监控", False, str(create_result))
            return
        
        # Read - 读取监控列表
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
            self.record_test("Read - 读取监控列表", True, f"共{count}条")
        else:
            self.record_test("Read - 读取监控列表", False, str(read_result))
        
        # Read Single - 读取单个监控
        if self.test_monitor_id:
            single_result = page.evaluate("""async ({token, id}) => {
                try {
                    const response = await fetch('/backend/api/monitors/index.php?id=' + id, {
                        headers: {'Authorization': 'Bearer ' + token}
                    });
                    return await response.json();
                } catch (e) {
                    return {error: e.message};
                }
            }""", {"token": self.token, "id": self.test_monitor_id})
            
            if single_result and single_result.get("success"):
                self.record_test("Read - 读取单个监控", True)
            else:
                self.record_test("Read - 读取单个监控", False, str(single_result))
        
        # Update - 更新监控
        if self.test_monitor_id:
            update_result = page.evaluate("""async ({token, id}) => {
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
            }""", {"token": self.token, "id": self.test_monitor_id})
            
            if update_result and update_result.get("success"):
                self.record_test("Update - 更新监控", True)
            else:
                self.record_test("Update - 更新监控", False, str(update_result))
        
        # Delete - 删除监控
        if self.test_monitor_id:
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
            }""", {"token": self.token, "id": self.test_monitor_id})
            
            if delete_result and delete_result.get("success"):
                self.record_test("Delete - 删除监控", True)
            else:
                self.record_test("Delete - 删除监控", False, str(delete_result))
    
    def test_search_filter(self, page):
        if not self.token:
            self.record_test("搜索筛选", False, "未登录")
            return
        
        # 测试状态筛选
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
            self.record_test("Filter - 状态筛选", True)
        else:
            self.record_test("Filter - 状态筛选", False, str(filter_result))
    
    def test_sort_pagination(self, page):
        if not self.token:
            self.record_test("排序分页", False, "未登录")
            return
        
        # 测试分页
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
            self.record_test("Pagination - 分页查询", True)
        else:
            self.record_test("Pagination - 分页查询", False, str(pagination_result))
    
    def test_api_keys(self, page):
        if not self.token:
            self.record_test("API密钥", False, "未登录")
            return
        
        # 获取API密钥列表
        keys_result = page.evaluate("""async (token) => {
            try {
                const response = await fetch('/backend/api/apikeys/index.php', {
                    headers: {'Authorization': 'Bearer ' + token}
                });
                return await response.json();
            } catch (e) {
                return {error: e.message};
            }
        }""", self.token)
        
        if keys_result and keys_result.get("success"):
            self.record_test("API Keys - 获取列表", True)
        else:
            self.record_test("API Keys - 获取列表", False, str(keys_result))
    
    def test_statistics(self, page):
        if not self.token:
            self.record_test("统计数据", False, "未登录")
            return
        
        # 获取统计数据
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
            self.record_test("Statistics - 统计数据", True, 
                f"今日:{data.get('today_codes',0)} 总计:{data.get('total_codes',0)}")
        else:
            self.record_test("Statistics - 统计数据", False, str(stats_result))
    
    def test_health_check(self, page):
        page.goto(f"{BASE_URL}/health.html", wait_until="networkidle", timeout=60000)
        page.wait_for_timeout(1000)
        
        # 测试PHP环境检测
        php_result = page.evaluate("""async () => {
            try {
                const response = await fetch('/backend/api/health/index.php?type=php');
                return await response.json();
            } catch (e) {
                return {error: e.message};
            }
        }""")
        
        if php_result and php_result.get("success"):
            self.record_test("Health - PHP环境检测", True)
        else:
            self.record_test("Health - PHP环境检测", False, str(php_result))
        
        # 测试数据库检测
        db_result = page.evaluate("""async () => {
            try {
                const response = await fetch('/backend/api/health/index.php?type=database');
                return await response.json();
            } catch (e) {
                return {error: e.message};
            }
        }""")
        
        if db_result and db_result.get("success"):
            self.record_test("Health - 数据库检测", True)
        else:
            self.record_test("Health - 数据库检测", False, str(db_result))
    
    def test_code_history(self, page):
        if not self.token:
            self.record_test("验证码历史", False, "未登录")
            return
        
        # 获取验证码历史
        history_result = page.evaluate("""async (token) => {
            try {
                const response = await fetch('/backend/api/codes/index.php', {
                    headers: {'Authorization': 'Bearer ' + token}
                });
                return await response.json();
            } catch (e) {
                return {error: e.message};
            }
        }""", self.token)
        
        if history_result and history_result.get("success"):
            self.record_test("Code History - 验证码历史", True)
        else:
            self.record_test("Code History - 验证码历史", False, str(history_result))


if __name__ == "__main__":
    tester = Phase2Tester()
    results = tester.run_all_tests()
    
    print("\n" + "="*60)
    print("  Phase 2 测试汇总")
    print("="*60)
    print(f"总通过: {results['total_passed']}")
    print(f"总失败: {results['total_failed']}")
    
    total = results['total_passed'] + results['total_failed']
    rate = round((results['total_passed'] / total) * 100, 1) if total > 0 else 0
    print(f"通过率: {rate}%")
    
    if rate >= 80:
        print("\n✅ Phase 2 测试通过！")
    else:
        print("\n❌ Phase 2 测试未达标")
    
    # 保存结果
    with open("phase2_results.json", "w", encoding="utf-8") as f:
        json.dump(results, f, ensure_ascii=False, indent=2)
    print("\n结果已保存到 phase2_results.json")
