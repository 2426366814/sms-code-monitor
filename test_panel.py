#!/usr/bin/env python3
"""
测试宝塔面板连接
"""

import requests
import json
import time
import hashlib
import urllib3
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

def test_panel_connection():
    panel_url = 'https://192.168.7.178:777'
    api_key = 'IgCkCDbflE3Fpvir1rFmm5t8leW9Whnd'
    
    # 构建请求参数
    data = {
        'request_token': time.time(),
        'action': 'GetSystemTotal',
        'args': json.dumps({})
    }
    
    # 添加签名
    sign_str = f"{data['action']}|{data['request_token']}|{data['args']}"
    sign = hashlib.md5(f"{sign_str}:{api_key}".encode()).hexdigest()
    data['sign'] = sign
    
    # 尝试不同的API路径
    api_paths = ['/api', '/aapanel/api', '/bt/api', '/panel/api']
    
    for api_path in api_paths:
        url = f'{panel_url}{api_path}'
        try:
            print(f'尝试: {url}')
            response = requests.post(url, data=data, verify=False, timeout=10)
            print(f'状态码: {response.status_code}')
            print(f'响应: {response.text[:500]}')
            print('---')
        except Exception as e:
            print(f'错误: {e}')
            print('---')

if __name__ == '__main__':
    test_panel_connection()
