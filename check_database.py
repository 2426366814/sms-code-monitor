#!/usr/bin/env python3
"""
检查数据库表结构
"""

import sys
sys.path.append('C:/Users/Administrator/.trae-cn/skills/aapanel-api-skill')

from aapanel_client import AAPanelClient

# 配置
PANEL_URL = "https://192.168.7.178:777"
API_KEY = "IgCkCDbflE3Fpvir1rFmm5t8leW9Whnd"

# 初始化客户端
client = AAPanelClient(PANEL_URL, API_KEY)

print("=" * 60)
print("检查数据库表结构")
print("=" * 60)

# 执行SQL查询查看users表结构
result = client.exec_shell("mysql -u root -e \"USE verify_code_monitor; SHOW COLUMNS FROM users;\"")
print("\nusers表结构:")
print(result.get('msg', '查询失败'))

# 查看所有表
result = client.exec_shell("mysql -u root -e \"USE verify_code_monitor; SHOW TABLES;\"")
print("\n所有表:")
print(result.get('msg', '查询失败'))
