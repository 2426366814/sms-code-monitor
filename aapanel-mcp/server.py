#!/usr/bin/env python3
"""
aaPanel MCP Server
将 aaPanel API 封装为 MCP 服务，用于 Trae 自动化部署网站、修改代码和测试

使用方法:
    python server.py --panel-url http://your-panel:8888 --api-key your-api-key
"""

import asyncio
import json
import sys
import os
from pathlib import Path
from typing import Dict, List, Any, Optional

# 添加父目录到路径
sys.path.append(str(Path(__file__).parent))


class AAPanelClient:
    """宝塔面板客户端"""
    
    def __init__(self, panel_url: str, api_key: str):
        """
        初始化客户端
        
        Args:
            panel_url: 宝塔面板地址
            api_key: API 密钥
        """
        self.panel_url = panel_url.rstrip('/')
        self.api_key = api_key
        self.session = None
    
    def get_system_info(self):
        """获取系统信息"""
        import requests
        import time
        
        # 构建请求参数
        data = {
            'request_token': time.time(),
            'action': 'GetSystemTotal',
            'args': json.dumps({})
        }
        
        # 添加签名
        import hashlib
        sign_str = f"{data['action']}|{data['request_token']}|{data['args']}"
        sign = hashlib.md5(f"{sign_str}:{self.api_key}".encode()).hexdigest()
        data['sign'] = sign
        
        # 发送请求
        # 尝试不同的 API 路径格式
        api_paths = ['/api', '/aapanel/api', '/bt/api']
        
        for api_path in api_paths:
            url = f"{self.panel_url}{api_path}"
            try:
                print(f"尝试 API 路径: {url}", file=sys.stderr)
                response = requests.post(url, data=data, verify=False, timeout=10)
                print(f"API 响应状态码: {response.status_code}", file=sys.stderr)
                
                # 尝试解析 JSON
                try:
                    result = response.json()
                    print(f"API 响应 JSON: {json.dumps(result, indent=2, ensure_ascii=False)[:200]}...", file=sys.stderr)
                    if result.get('status'):
                        return {
                            "status": True,
                            "data": result.get('data', {})
                        }
                    else:
                        return {
                            "status": False,
                            "error": result.get('msg', 'Unknown error')
                        }
                except json.JSONDecodeError:
                    print(f"API 响应内容: {response.text[:200]}...", file=sys.stderr)
                    continue
                    
            except Exception as e:
                print(f"API 调用异常: {str(e)}", file=sys.stderr)
                continue
        
        return {
            "status": False,
            "error": "所有 API 路径都尝试失败"
        }
    
    def get_disk_info(self):
        """获取磁盘信息"""
        import requests
        import time
        
        # 构建请求参数
        data = {
            'request_token': time.time(),
            'action': 'GetDiskInfo',
            'args': json.dumps({})
        }
        
        # 添加签名
        import hashlib
        sign_str = f"{data['action']}|{data['request_token']}|{data['args']}"
        sign = hashlib.md5(f"{sign_str}:{self.api_key}".encode()).hexdigest()
        data['sign'] = sign
        
        # 发送请求
        url = f"{self.panel_url}/api"
        try:
            response = requests.post(url, data=data, verify=False)
            result = response.json()
            if result.get('status'):
                return {
                    "status": True,
                    "data": result.get('data', {})
                }
            else:
                return {
                    "status": False,
                    "error": result.get('msg', 'Unknown error')
                }
        except Exception as e:
            return {
                "status": False,
                "error": str(e)
            }
    
    def get_network_info(self):
        """获取网络信息"""
        import requests
        import time
        
        # 构建请求参数
        data = {
            'request_token': time.time(),
            'action': 'GetNetWork',
            'args': json.dumps({})
        }
        
        # 添加签名
        import hashlib
        sign_str = f"{data['action']}|{data['request_token']}|{data['args']}"
        sign = hashlib.md5(f"{sign_str}:{self.api_key}".encode()).hexdigest()
        data['sign'] = sign
        
        # 发送请求
        url = f"{self.panel_url}/api"
        try:
            response = requests.post(url, data=data, verify=False)
            result = response.json()
            if result.get('status'):
                return {
                    "status": True,
                    "data": result.get('data', {})
                }
            else:
                return {
                    "status": False,
                    "error": result.get('msg', 'Unknown error')
                }
        except Exception as e:
            return {
                "status": False,
                "error": str(e)
            }


class AAPanelMCP:
    """aaPanel MCP 服务"""
    
    def __init__(self, panel_url: str, api_key: str):
        """
        初始化 MCP 服务
        
        Args:
            panel_url: 宝塔面板地址，如 http://192.168.1.100:8888
            api_key: API 密钥
        """
        self.client = AAPanelClient(panel_url, api_key)
        self.server = None
        self.setup_handlers()
    
    def setup_handlers(self):
        """设置 MCP 处理器"""
        pass
    
    async def run(self):
        """运行 MCP 服务器"""
        pass


def main():
    """主入口"""
    import argparse
    
    parser = argparse.ArgumentParser(description="aaPanel MCP Server")
    parser.add_argument("--panel-url", required=True, help="宝塔面板 URL，如 http://192.168.1.100:8888")
    parser.add_argument("--api-key", required=True, help="API 密钥")
    parser.add_argument("--env-file", help="从环境文件加载配置")
    
    args = parser.parse_args()
    
    # 如果指定了环境文件，从文件加载
    if args.env_file and os.path.exists(args.env_file):
        with open(args.env_file, 'r', encoding='utf-8') as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith('#') and '=' in line:
                    key, value = line.split('=', 1)
                    os.environ[key] = value
        
        panel_url = os.environ.get('AAPANEL_URL', args.panel_url)
        api_key = os.environ.get('AAPANEL_API_KEY', args.api_key)
    else:
        panel_url = args.panel_url
        api_key = args.api_key
    
    print(f"启动 aaPanel MCP Server...", file=sys.stderr)
    print(f"面板地址: {panel_url}", file=sys.stderr)
    
    # 尝试使用不同的端口
    ports = ['777', '8888', '888']
    protocols = ['http', 'https']
    
    for protocol in protocols:
        for port in ports:
            # 构建新的面板地址
            new_panel_url = f"{protocol}://192.168.7.178:{port}"
            print(f"\n尝试 {protocol.upper()} 协议，端口 {port}: {new_panel_url}", file=sys.stderr)
            
            # 直接测试连接并获取系统信息
            client = AAPanelClient(new_panel_url, api_key)
            
            print(f"\n=== 获取系统信息 ({protocol.upper()}:{port}) ===", file=sys.stderr)
            system_info = client.get_system_info()
            print(json.dumps(system_info, indent=2, ensure_ascii=False), file=sys.stderr)
            
            # 如果成功获取系统信息，就停止尝试
            if system_info.get('status'):
                print(f"\n成功连接到宝塔面板: {new_panel_url}", file=sys.stderr)
                return


if __name__ == "__main__":
    main()