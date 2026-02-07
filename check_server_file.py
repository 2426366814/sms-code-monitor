#!/usr/bin/env python3
"""
检查服务器上的文件内容
"""

import sys
sys.path.append('C:/Users/Administrator/.trae-cn/skills/aapanel-api-skill')

from aapanel_client import AAPanelClient

# 配置
PANEL_URL = "https://192.168.7.178:777"
API_KEY = "IgCkCDbflE3Fpvir1rFmm5t8leW9Whnd"
WEB_ROOT = "/www/wwwroot/verify-code-monitor.local"

# 初始化客户端
client = AAPanelClient(PANEL_URL, API_KEY)

print("=" * 60)
print("检查服务器上的 UserModel.php")
print("=" * 60)

# 检查 UserModel.php 中的方法
result = client.exec_shell(f"grep -n 'function getUserByUsername' {WEB_ROOT}/backend/models/UserModel.php")
print(f"getUserByUsername 方法:")
print(f"  {result.get('msg', '未找到')}")

result = client.exec_shell(f"grep -n 'function getUserByEmail' {WEB_ROOT}/backend/models/UserModel.php")
print(f"getUserByEmail 方法:")
print(f"  {result.get('msg', '未找到')}")

result = client.exec_shell(f"grep -n 'function createUser' {WEB_ROOT}/backend/models/UserModel.php")
print(f"createUser 方法:")
print(f"  {result.get('msg', '未找到')}")

# 检查文件行数
result = client.exec_shell(f"wc -l {WEB_ROOT}/backend/models/UserModel.php")
print(f"\n文件行数:")
print(f"  {result.get('msg', '未知')}")

# 检查 PHP 错误日志
print("\n" + "=" * 60)
print("检查 PHP 错误日志")
print("=" * 60)
result = client.exec_shell("tail -20 /www/wwwlogs/php_error.log")
print(f"{result.get('msg', '无日志')}")
