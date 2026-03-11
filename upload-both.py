import paramiko
import os

# 服务器配置
hostname = '134.185.111.25'
port = 1022
username = 'root'
password = 'C^74+ek@dN'

# 文件路径
files_to_upload = [
    (r'e:\ai本地应用\多用户接码\admin.html', '/home/wwwroot/jm.91wz.org/admin.html'),
    (r'e:\ai本地应用\多用户接码\index.html', '/home/wwwroot/jm.91wz.org/index.html')
]

print(f'正在连接到 {hostname}:{port}...')

# 创建SSH客户端
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

try:
    # 连接服务器
    ssh.connect(hostname, port=port, username=username, password=password)
    print('连接成功！')
    
    # 创建SFTP客户端
    sftp = ssh.open_sftp()
    
    # 上传文件
    for local_file, remote_file in files_to_upload:
        print(f'正在上传 {local_file} 到 {remote_file}...')
        sftp.put(local_file, remote_file)
        print(f'上传成功！')
        
        # 设置文件权限
        print('设置文件权限...')
        sftp.chmod(remote_file, 0o644)
        print('权限设置完成！')
    
    # 关闭连接
    sftp.close()
    ssh.close()
    
    print('所有操作完成！')
    
except Exception as e:
    print(f'错误: {e}')
    import traceback
    traceback.print_exc()
