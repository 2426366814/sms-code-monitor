#!/usr/bin/env python3
"""
部署修复 - 使用 edit_file API
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
print("部署 UserModel.php 修复 (使用 edit_file)")
print("=" * 60)

# 读取修复后的 UserModel.php
with open('backend/models/UserModel.php', 'r', encoding='utf-8') as f:
    usermodel_content = f.read()

# 使用 edit_file 更新
usermodel_path = f"{WEB_ROOT}/backend/models/UserModel.php"
result = client.edit_file(usermodel_path, usermodel_content)
print(f"✓ 更新 UserModel.php")
print(f"  结果: {result}")

print("\n✓ 部署完成！")
print("=" * 60)
