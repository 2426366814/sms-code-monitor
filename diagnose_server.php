#!/usr/bin/env python3
"""
服务器诊断脚本
检查服务器上的PHP文件状态
"""

import sys
sys.path.append('C:/Users/Administrator/.trae-cn/skills/aapanel-api-skill')

from aapanel_client import AAPanelClient
import json

# 配置
PANEL_URL = "https://192.168.7.178:777"
API_KEY = "IgCkCDbflE3Fpvir1rFmm5t8leW9Whnd"
WEB_ROOT = "/www/wwwroot/verify-code-monitor.local"

# 初始化客户端
client = AAPanelClient(PANEL_URL, API_KEY)

print("=" * 60)
print("服务器PHP文件诊断")
print("=" * 60)

# 检查关键文件是否存在
files_to_check = [
    "backend/utils/Database.php",
    "backend/models/BaseModel.php",
    "backend/models/UserModel.php",
    "backend/api/auth/index.php",
    "backend/config/config.php"
]

print("\n1. 检查文件是否存在:")
for file in files_to_check:
    path = f"{WEB_ROOT}/{file}"
    result = client.read_file(path)
    if result:
        print(f"  ✓ {file} - 存在 ({len(result)} 字符)")
    else:
        print(f"  ✗ {file} - 不存在或为空")

# 尝试读取UserModel.php的前几行
print("\n2. 检查UserModel.php内容:")
usermodel_content = client.read_file(f"{WEB_ROOT}/backend/models/UserModel.php")
if usermodel_content:
    lines = usermodel_content.split('\n')[:20]
    print("  前20行:")
    for i, line in enumerate(lines, 1):
        print(f"    {i}: {line}")
else:
    print("  无法读取文件")

# 检查BaseModel.php
print("\n3. 检查BaseModel.php内容:")
basemodel_content = client.read_file(f"{WEB_ROOT}/backend/models/BaseModel.php")
if basemodel_content:
    lines = basemodel_content.split('\n')[:15]
    print("  前15行:")
    for i, line in enumerate(lines, 1):
        print(f"    {i}: {line}")
else:
    print("  无法读取文件")

print("\n" + "=" * 60)
print("诊断完成")
print("=" * 60)
