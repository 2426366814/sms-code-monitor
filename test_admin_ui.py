#!/usr/bin/env python3
"""
使用Playwright测试管理员界面
"""

from playwright.sync_api import sync_playwright
import time

def test_admin_ui():
    with sync_playwright() as p:
        # 启动浏览器
        browser = p.chromium.launch(headless=False)
        page = browser.new_page()
        
        print("=" * 60)
        print("测试管理员界面")
        print("=" * 60)
        
        # 1. 打开管理员页面
        print("\n1. 打开管理员页面...")
        page.goto("http://192.168.7.178:8080/admin.html")
        page.wait_for_load_state("networkidle")
        time.sleep(1)
        
        # 截图
        page.screenshot(path="admin_login.png")
        print("✓ 已截图: admin_login.png")
        
        # 2. 检查登录表单元素
        print("\n2. 检查登录表单...")
        try:
            username_input = page.locator("#loginUsername")
            password_input = page.locator("#loginPassword")
            login_button = page.locator("button[onclick='login()']")
            
            if username_input.is_visible():
                print("✓ 用户名输入框存在")
            else:
                print("✗ 用户名输入框不存在")
                
            if password_input.is_visible():
                print("✓ 密码输入框存在")
            else:
                print("✗ 密码输入框不存在")
                
            if login_button.is_visible():
                print("✓ 登录按钮存在")
            else:
                print("✗ 登录按钮不存在")
        except Exception as e:
            print(f"✗ 检查登录表单出错: {e}")
        
        # 3. 填写登录信息
        print("\n3. 填写登录信息...")
        try:
            page.fill("#loginUsername", "admin")
            page.fill("#loginPassword", "admin")
            print("✓ 已填写用户名和密码")
        except Exception as e:
            print(f"✗ 填写登录信息出错: {e}")
        
        # 4. 点击登录
        print("\n4. 点击登录...")
        try:
            page.click("button[onclick='login()']")
            time.sleep(2)  # 等待登录响应
            
            # 检查是否登录成功
            if page.locator("#mainSection").is_visible():
                print("✓ 登录成功，显示主界面")
                page.screenshot(path="admin_main.png")
                print("✓ 已截图: admin_main.png")
            else:
                print("✗ 登录失败，未显示主界面")
                error_text = page.locator("#loginError").text_content()
                print(f"  错误信息: {error_text}")
                page.screenshot(path="admin_login_error.png")
                print("✓ 已截图: admin_login_error.png")
                browser.close()
                return
        except Exception as e:
            print(f"✗ 登录出错: {e}")
            browser.close()
            return
        
        # 5. 测试批量添加手机号功能
        print("\n5. 测试批量添加手机号功能...")
        try:
            # 确保在手机号标签页
            phones_tab = page.locator(".nav-tab", has_text="批量添加手机号")
            if phones_tab.is_visible():
                phones_tab.click()
                time.sleep(0.5)
                print("✓ 切换到手机号标签页")
            
            # 填写手机号列表
            phone_list = page.locator("#phoneList")
            if phone_list.is_visible():
                phone_list.fill("13800138001\n13800138002\n13800138003")
                print("✓ 已填写手机号列表")
                
                # 点击导入按钮
                page.click("button[onclick='bulkAddPhones()']")
                time.sleep(2)  # 等待响应
                
                # 检查结果
                result_div = page.locator("#phoneResult")
                if result_div.is_visible():
                    result_text = result_div.inner_html()
                    print(f"✓ 批量添加结果: {result_text[:200]}...")
                    page.screenshot(path="admin_bulk_phones.png")
                    print("✓ 已截图: admin_bulk_phones.png")
                else:
                    print("✗ 未显示结果")
            else:
                print("✗ 手机号输入框不存在")
        except Exception as e:
            print(f"✗ 测试批量添加手机号出错: {e}")
        
        # 6. 测试批量添加API密钥功能
        print("\n6. 测试批量添加API密钥功能...")
        try:
            # 切换到API密钥标签页
            apikeys_tab = page.locator(".nav-tab", has_text="批量添加API密钥")
            if apikeys_tab.is_visible():
                apikeys_tab.click()
                time.sleep(0.5)
                print("✓ 切换到API密钥标签页")
            
            # 填写API密钥列表
            apikey_list = page.locator("#apiKeyList")
            if apikey_list.is_visible():
                apikey_list.fill("ak_test_123456\nak_test_789012")
                print("✓ 已填写API密钥列表")
                
                # 点击导入按钮
                page.click("button[onclick='bulkAddApiKeys()']")
                time.sleep(2)  # 等待响应
                
                # 检查结果
                result_div = page.locator("#apiKeyResult")
                if result_div.is_visible():
                    result_text = result_div.inner_html()
                    print(f"✓ 批量添加结果: {result_text[:200]}...")
                    page.screenshot(path="admin_bulk_apikeys.png")
                    print("✓ 已截图: admin_bulk_apikeys.png")
                else:
                    print("✗ 未显示结果")
            else:
                print("✗ API密钥输入框不存在")
        except Exception as e:
            print(f"✗ 测试批量添加API密钥出错: {e}")
        
        # 7. 检查浏览器控制台错误
        print("\n7. 检查浏览器控制台错误...")
        logs = page.evaluate("() => { return window.consoleLogs || []; }")
        if logs:
            print(f"发现 {len(logs)} 条控制台日志")
            for log in logs[:5]:
                print(f"  {log}")
        else:
            print("✓ 未发现控制台错误")
        
        print("\n" + "=" * 60)
        print("测试完成！")
        print("=" * 60)
        
        # 关闭浏览器
        browser.close()

if __name__ == "__main__":
    test_admin_ui()
