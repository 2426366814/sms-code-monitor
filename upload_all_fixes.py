#!/usr/bin/env python3
import paramiko
import scp
import os

# 服务器配置
HOST = '134.185.111.25'
PORT = 1022
USERNAME = 'root'
PASSWORD = 'C^74+ek@dN'
REMOTE_DIR = '/home/wwwroot/jm.91wz.org'

print('=== 上传所有修复文件 ===')

# 本地文件列表 - 所有修改过的文件
files_to_upload = [
    'backend/api/monitors/index.php',
    'backend/websocket/server.js',
    'index.html',
    'login.html',
    'register.html',
    'admin.html',
    'backend/api/auth/index.php',
    'add_test_code.php',
    'fix_test_data.php'
]

try:
    # 创建SSH客户端
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    
    print(f'连接服务器...')
    ssh.connect(HOST, port=PORT, username=USERNAME, password=PASSWORD)
    print('✅ 连接成功！\n')
    
    # 创建SCP客户端
    with scp.SCPClient(ssh.get_transport()) as scp_client:
        for local_file in files_to_upload:
            if os.path.exists(local_file):
                print(f'上传 {local_file} ...')
                
                # 转换为Linux路径
                remote_file_path = local_file.replace('\\', '/')
                remote_dir = os.path.dirname(remote_file_path)
                
                # 确保远程目录存在
                if remote_dir:
                    full_remote_dir = f'{REMOTE_DIR}/{remote_dir}'
                    ssh.exec_command(f'mkdir -p {full_remote_dir}')
                
                remote_path = f'{REMOTE_DIR}/{remote_file_path}'
                scp_client.put(local_file, remote_path)
                print(f'✅ {local_file} 上传成功！')
            else:
                print(f'⚠️  {local_file} 不存在，跳过')
    
    print('\n=== 上传完成！===')
    
    # 重启WebSocket服务
    print(f'\n正在重启WebSocket服务...')
    stdin, stdout, stderr = ssh.exec_command('cd /home/wwwroot/jm.91wz.org/backend/websocket && pm2 restart websocket-server')
    print(stdout.read().decode('utf-8'))
    print(stderr.read().decode('utf-8'))
    
    # 检查PM2状态
    print(f'\n检查PM2服务状态...')
    stdin, stdout, stderr = ssh.exec_command('pm2 status')
    print(stdout.read().decode('utf-8'))
    
    ssh.close()
    print('\n✅ 所有操作完成！')
    
except Exception as e:
    print(f'❌ 错误: {e}')
    import traceback
    traceback.print_exc()
