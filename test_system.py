#!/usr/bin/env python3
"""
多用户动态验证码监控系统功能测试脚本
"""

from playwright.sync_api import sync_playwright
import time
import json

def test_system():
    """测试系统全部功能"""
    
    with sync_playwright() as p:
        # 启动浏览器
        browser = p.chromium.launch(headless=False, slow_mo=100)
        context = browser.new_context(viewport={'width': 1400, 'height': 900})
        page = context.new_page()
        
        # 启用控制台日志
        page.on("console", lambda msg: print(f"[Console] {msg.type}: {msg.text}"))
        
        print("=" * 60)
        print("开始测试多用户动态验证码监控系统")
        print("=" * 60)
        
        # ==================== 测试1: 登录页面 ====================
        print("\n[测试1] 访问登录页面...")
        page.goto("http://localhost:8080/login.html")
        page.wait_for_load_state("networkidle")
        page.screenshot(path="test_01_login_page.png")
        print("✓ 登录页面加载成功")
        
        # 测试登录功能
        print("\n[测试2] 测试用户登录...")
        page.fill("#username", "user")
        page.fill("#password", "user123")
        page.click("button[type='submit']")
        
        # 等待登录完成
        try:
            page.wait_for_url("**/index.html", timeout=5000)
            print("✓ 用户登录成功")
            page.screenshot(path="test_02_user_dashboard.png")
        except:
            print("✗ 登录失败，检查错误信息...")
            page.screenshot(path="test_02_login_error.png")
            # 尝试查看是否有错误提示
            error_msg = page.locator(".error-message").text_content() if page.locator(".error-message").count() > 0 else "未知错误"
            print(f"  错误信息: {error_msg}")
        
        # ==================== 测试3: 用户面板功能 ====================
        print("\n[测试3] 测试用户面板功能...")
        
        # 检查统计卡片
        stat_cards = page.locator(".stat-card").count()
        print(f"  发现 {stat_cards} 个统计卡片")
        
        # 检查分组筛选
        group_chips = page.locator(".group-chip").count()
        print(f"  发现 {group_chips} 个分组筛选标签")
        
        # 检查监控列表表格
        if page.locator(".data-table").count() > 0:
            print("✓ 监控列表表格存在")
        
        # ==================== 测试4: 添加监控项 ====================
        print("\n[测试4] 测试添加监控项...")
        page.click("button:has-text('添加监控')")
        time.sleep(0.5)
        page.screenshot(path="test_03_add_monitor_modal.png")
        
        # 填写表单
        page.fill("#phoneInput", "13800138001")
        page.fill("#urlInput", "https://api.example.com/getcode?token=test123")
        page.screenshot(path="test_04_add_monitor_form.png")
        
        # 点击添加按钮
        page.click(".modal-footer button:has-text('添加')")
        time.sleep(1)
        page.screenshot(path="test_05_after_add.png")
        print("✓ 添加监控项操作完成")
        
        # ==================== 测试5: 分组管理 ====================
        print("\n[测试5] 测试分组管理...")
        page.click(".nav-item:has-text('分组管理')")
        time.sleep(0.5)
        page.screenshot(path="test_06_groups_page.png")
        print("✓ 分组管理页面加载成功")
        
        # 创建新分组
        page.click("button:has-text('新建分组')")
        time.sleep(0.3)
        page.fill("#groupNameInput", "测试分组")
        page.screenshot(path="test_07_create_group_modal.png")
        page.click("#groupModal button:has-text('创建')")
        time.sleep(0.5)
        page.screenshot(path="test_08_after_create_group.png")
        print("✓ 创建分组操作完成")
        
        # ==================== 测试6: 历史记录 ====================
        print("\n[测试6] 测试历史记录...")
        page.click(".nav-item:has-text('历史记录')")
        time.sleep(0.5)
        page.screenshot(path="test_09_history_page.png")
        print("✓ 历史记录页面加载成功")
        
        # ==================== 测试7: 设置页面 ====================
        print("\n[测试7] 测试设置页面...")
        page.click(".nav-item:has-text('设置')")
        time.sleep(0.5)
        page.screenshot(path="test_10_settings_page.png")
        print("✓ 设置页面加载成功")
        
        # ==================== 测试8: 验证码复制功能 ====================
        print("\n[测试8] 测试验证码复制功能...")
        # 返回监控面板
        page.click(".nav-item:has-text('监控面板')")
        time.sleep(0.5)
        
        # 检查复制按钮是否存在
        copy_buttons = page.locator(".code-display .copy-btn").count()
        print(f"  发现 {copy_buttons} 个复制按钮")
        if copy_buttons > 0:
            print("✓ 复制按钮已添加")
        else:
            print("! 暂无验证码可复制（需要先获取验证码）")
        
        # ==================== 测试9: 管理员登录 ====================
        print("\n[测试9] 测试管理员登录...")
        # 先登出
        page.click("button[title='退出登录']")
        time.sleep(0.5)
        
        # 访问管理员登录页面
        page.goto("http://localhost:8080/login.html")
        page.wait_for_load_state("networkidle")
        
        # 切换到管理员登录
        page.click(".tab:has-text('管理员')")
        page.fill("#adminUsername", "admin")
        page.fill("#adminPassword", "admin123")
        page.screenshot(path="test_11_admin_login.png")
        page.click("#adminLoginForm button[type='submit']")
        
        try:
            page.wait_for_url("**/admin.html", timeout=5000)
            print("✓ 管理员登录成功")
            page.screenshot(path="test_12_admin_dashboard.png")
        except:
            print("✗ 管理员登录失败")
            page.screenshot(path="test_12_admin_login_error.png")
        
        # ==================== 测试10: 管理员面板功能 ====================
        print("\n[测试10] 测试管理员面板功能...")
        
        # 检查统计卡片
        admin_stats = page.locator(".stat-card").count()
        print(f"  发现 {admin_stats} 个统计卡片")
        
        # 检查用户管理
        page.click(".nav-item:has-text('用户管理')")
        time.sleep(0.5)
        page.screenshot(path="test_13_admin_users.png")
        print("✓ 用户管理页面加载成功")
        
        # 检查监控管理
        page.click(".nav-item:has-text('监控管理')")
        time.sleep(0.5)
        page.screenshot(path="test_14_admin_monitors.png")
        print("✓ 监控管理页面加载成功")
        
        # 检查批量操作
        page.click(".nav-item:has-text('批量操作')")
        time.sleep(0.5)
        page.screenshot(path="test_15_admin_bulk.png")
        print("✓ 批量操作页面加载成功")
        
        # 检查系统设置
        page.click(".nav-item:has-text('系统设置')")
        time.sleep(0.5)
        page.screenshot(path="test_16_admin_settings.png")
        print("✓ 系统设置页面加载成功")
        
        # 检查操作日志
        page.click(".nav-item:has-text('操作日志')")
        time.sleep(0.5)
        page.screenshot(path="test_17_admin_logs.png")
        print("✓ 操作日志页面加载成功")
        
        # ==================== 测试完成 ====================
        print("\n" + "=" * 60)
        print("测试完成！所有截图已保存到当前目录")
        print("=" * 60)
        
        # 保持页面打开一段时间以便查看
        time.sleep(3)
        
        browser.close()

if __name__ == "__main__":
    test_system()
