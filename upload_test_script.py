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

print('=== 上传测试脚本 ===')

# 本地文件列表
files_to_upload = [
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
                remote_path = f'{REMOTE_DIR}/{local_file}'
                scp_client.put(local_file, remote_path)
                print(f'✅ {local_file} 上传成功！')
            else:
                print(f'⚠️  {local_file} 不存在，跳过')
    
    print('\n=== 上传完成！===')
    print(f'\n正在运行测试脚本...')
    
    # 运行修复脚本
    print(f'\n正在运行修复脚本...')
    stdin, stdout, stderr = ssh.exec_command(f'cd {REMOTE_DIR} && php fix_test_data.php')
    print('\n=== 修复脚本输出 ===')
    print(stdout.read().decode('utf-8'))
    print(stderr.read().decode('utf-8'))
    
    ssh.close()
    print('\n✅ 所有操作完成！')
    
except Exception as e:
    print(f'❌ 错误: {e}')
    import traceback
    traceback.print_exc()
