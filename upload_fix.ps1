# 上传修复文件到服务器
$server = "134.185.111.25"
$port = "1022"
$user = "root"
$pass = "C^74+ek@dN"
$localPath = "e:\ai本地应用\多用户接码"
$remotePath = "/home/wwwroot/jm.91wz.org"

# 使用 plink 上传文件（需要先安装 PuTTY 或使用 Git 的 plink）
# 或者使用 PowerShell 的 SSH.NET
# 这里使用 Git 的 SCP 命令

Write-Host "正在上传文件..." -ForegroundColor Green

# 设置 Git 路径
$gitPath = "C:\Program Files\Git\bin"
$env:Path = "$gitPath;$env:Path"

# 上传 backend/api/monitors/index.php
Write-Host "上传 backend/api/monitors/index.php..."
& "$gitPath\scp.exe" -P $port -o StrictHostKeyChecking=no "$localPath\backend\api\monitors\index.php" "${user}@${server}:${remotePath}/backend/api/monitors/index.php"

# 上传 backend/websocket/server.js
Write-Host "上传 backend/websocket/server.js..."
& "$gitPath\scp.exe" -P $port -o StrictHostKeyChecking=no "$localPath\backend\websocket\server.js" "${user}@${server}:${remotePath}/backend/websocket/server.js"

# 上传 index.html
Write-Host "上传 index.html..."
& "$gitPath\scp.exe" -P $port -o StrictHostKeyChecking=no "$localPath\index.html" "${user}@${server}:${remotePath}/index.html"

Write-Host "文件上传完成！" -ForegroundColor Green

# 重启 WebSocket 服务
Write-Host "正在重启 WebSocket 服务..."
$sshCmd = @"
cd $remotePath/backend/websocket && pm2 restart websocket-server && pm2 status
"@

& "$gitPath\ssh.exe" -p $port -o StrictHostKeyChecking=no "${user}@${server}" $sshCmd

Write-Host "操作完成！" -ForegroundColor Green
