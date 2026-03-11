import paramiko
import os
import time

# 服务器配置
SERVER_IP = "134.185.111.25"
SERVER_PORT = 1022
SERVER_USER = "root"
SERVER_PASS = "C^74+ek@dN"
REMOTE_PATH = "/home/wwwroot/jm.91wz.org"
LOCAL_PATH = r"e:\ai本地应用\多用户接码"

# 要上传的文件列表
files_to_upload = [
    ("backend/api/monitors/index.php", "backend/api/monitors/index.php"),
    ("backend/websocket/server.js", "backend/websocket/server.js"),
    ("backend/api/auth/index.php", "backend/api/auth/index.php"),
    ("index.html", "index.html"),
    ("login.html", "login.html"),
    ("register.html", "register.html"),
    ("admin.html", "admin.html"),
]

def create_ssh_client():
    """创建SSH客户端"""
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(
        hostname=SERVER_IP,
        port=SERVER_PORT,
        username=SERVER_USER,
        password=SERVER_PASS,
        timeout=30
    )
    return ssh

def upload_file(sftp, local_file, remote_file):
    """上传单个文件"""
    local_full_path = os.path.join(LOCAL_PATH, local_file)
    remote_full_path = os.path.join(REMOTE_PATH, remote_file).replace("\\", "/")
    
    print(f"上传: {local_file} -> {remote_file}")
    
    # 确保远程目录存在
    remote_dir = os.path.dirname(remote_full_path)
    try:
        sftp.stat(remote_dir)
    except:
        print(f"创建目录: {remote_dir}")
        # 使用SSH命令创建目录
        ssh = create_ssh_client()
        ssh.exec_command(f"mkdir -p {remote_dir}")
        ssh.close()
    
    # 上传文件
    sftp.put(local_full_path, remote_full_path)
    print(f"✓ 上传完成: {local_file}")

def restart_websocket(ssh):
    """重启WebSocket服务"""
    print("\n重启 WebSocket 服务...")
    
    commands = [
        f"cd {REMOTE_PATH}/backend/websocket",
        "pm2 restart websocket-server",
        "pm2 status"
    ]
    
    for cmd in commands:
        print(f"执行: {cmd}")
        stdin, stdout, stderr = ssh.exec_command(cmd)
        output = stdout.read().decode('utf-8')
        error = stderr.read().decode('utf-8')
        
        if output:
            print(output)
        if error:
            print(f"错误: {error}")
        
        time.sleep(1)

def main():
    print("=" * 60)
    print("多用户接码系统 - 文件上传工具")
    print("=" * 60)
    
    try:
        # 创建SSH连接
        print(f"\n连接服务器: {SERVER_IP}:{SERVER_PORT}")
        ssh = create_ssh_client()
        sftp = ssh.open_sftp()
        print("✓ 连接成功！\n")
        
        # 上传文件
        for local_file, remote_file in files_to_upload:
            upload_file(sftp, local_file, remote_file)
        
        # 关闭SFTP
        sftp.close()
        
        # 重启WebSocket服务
        restart_websocket(ssh)
        
        # 关闭SSH
        ssh.close()
        
        print("\n" + "=" * 60)
        print("✓ 所有操作完成！")
        print("=" * 60)
        
    except Exception as e:
        print(f"\n✗ 错误: {str(e)}")
        import traceback
        traceback.print_exc()

if __name__ == "__main__":
    main()
