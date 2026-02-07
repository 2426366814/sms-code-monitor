#!/usr/bin/env python3
"""
检查服务器上的文件内容 - 方法2
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
print("检查服务器上的 UserModel.php 内容")
print("=" * 60)

# 直接读取文件内容
result = client.exec_shell(f"cat {WEB_ROOT}/backend/models/UserModel.php | head -50")
print("文件前50行:")
print(result.get('msg', '无法读取'))
