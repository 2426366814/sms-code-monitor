import paramiko
import os
import time

SERVER_IP = "134.185.111.25"
SERVER_PORT = 1022
SERVER_USER = "root"
SERVER_PASS = "C^74+ek@dN"
REMOTE_PATH = "/home/wwwroot/jm.91wz.org"
LOCAL_PATH = r"e:\ai本地应用\多用户接码"

files_to_upload = [
    ("backend/api/statistics/index.php", "backend/api/statistics/index.php"),
    ("backend/api/webhooks/index.php", "backend/api/webhooks/index.php"),
    ("backend/api/codes/index.php", "backend/api/codes/index.php"),
    ("backend/api/admin/index.php", "backend/api/admin/index.php"),
    ("backend/models/CodeModel.php", "backend/models/CodeModel.php"),
]

def create_ssh_client():
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
    local_full_path = os.path.join(LOCAL_PATH, local_file)
    remote_full_path = os.path.join(REMOTE_PATH, remote_file).replace("\\", "/")
    
    print(f"上传: {local_file} -> {remote_file}")
    
    remote_dir = os.path.dirname(remote_full_path)
    try:
        sftp.stat(remote_dir)
    except:
        print(f"创建目录: {remote_dir}")
        ssh = create_ssh_client()
        ssh.exec_command(f"mkdir -p {remote_dir}")
        ssh.close()
    
    sftp.put(local_full_path, remote_full_path)
    print(f"✓ 上传完成: {local_file}")

def restart_websocket(ssh):
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
        print(f"\n连接服务器: {SERVER_IP}:{SERVER_PORT}")
        ssh = create_ssh_client()
        sftp = ssh.open_sftp()
        print("✓ 连接成功！\n")
        
        for local_file, remote_file in files_to_upload:
            upload_file(sftp, local_file, remote_file)
        
        sftp.close()
        restart_websocket(ssh)
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
