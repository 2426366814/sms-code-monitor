#!/usr/bin/env python3
"""
部署文件到服务器 - 使用 create_file API
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
print("部署修复文件到服务器")
print("=" * 60)

# 1. 创建 UserModel.php
print("\n1. 创建 UserModel.php...")
with open('backend/models/UserModel.php', 'r', encoding='utf-8') as f:
    usermodel_content = f.read()

usermodel_path = f"{WEB_ROOT}/backend/models/UserModel.php"
result = client.create_file(usermodel_path)
print(f"  创建文件结果: {result}")

# 然后写入内容
result = client.edit_file(usermodel_path, usermodel_content)
print(f"  写入内容结果: {result}")

# 2. 更新 BaseModel.php - 添加 require_once
print("\n2. 更新 BaseModel.php...")
basemodel_path = f"{WEB_ROOT}/backend/models/BaseModel.php"

# 读取现有的 BaseModel.php
existing_basemodel = client.read_file(basemodel_path)
if existing_basemodel:
    # 检查是否已经有 require_once
    if 'require_once' not in existing_basemodel:
        # 在 <?php 后添加 require_once
        new_content = existing_basemodel.replace(
            '<?php\n/**',
            '<?php\n/**\n * 基础模型类\n * 所有模型的基类\n */\n\nrequire_once __DIR__ . \'/../utils/Database.php\';\n\n/**'
        )
        # 移除重复的注释
        new_content = new_content.replace(
            '/**\n * 基础模型类\n * 所有模型的基类\n */\n\n/**',
            '/**'
        )
        result = client.edit_file(basemodel_path, new_content)
        print(f"  更新结果: {result}")
    else:
        print("  BaseModel.php 已经有 require_once，跳过")
else:
    print("  无法读取 BaseModel.php")

# 3. 验证部署
print("\n3. 验证部署...")
usermodel_check = client.read_file(usermodel_path)
if usermodel_check and len(usermodel_check) > 1000:
    print(f"  ✓ UserModel.php 部署成功 ({len(usermodel_check)} 字符)")
else:
    print(f"  ✗ UserModel.php 部署失败")

basemodel_check = client.read_file(basemodel_path)
if basemodel_check and 'require_once' in basemodel_check:
    print(f"  ✓ BaseModel.php 更新成功")
else:
    print(f"  ✗ BaseModel.php 更新失败")

print("\n" + "=" * 60)
print("部署完成")
print("=" * 60)
