import paramiko

# 服务器配置
hostname = '134.185.111.25'
port = 1022
username = 'root'
password = 'C^74+ek@dN'

# 文件路径
remote_file = '/home/wwwroot/jm.91wz.org/admin.html'
local_file = r'e:\ai本地应用\多用户接码\admin_check.html'

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
    
    # 下载文件
    print(f'正在下载 {remote_file} 到 {local_file}...')
    sftp.get(remote_file, local_file)
    print('下载成功！')
    
    # 关闭连接
    sftp.close()
    ssh.close()
    
    print('所有操作完成！')
    
except Exception as e:
    print(f'错误: {e}')
    import traceback
    traceback.print_exc()
