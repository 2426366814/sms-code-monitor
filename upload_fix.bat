@echo off
chcp 65001 >nul
echo ========================================
echo   上传修复文件到服务器
echo ========================================
echo.

echo 正在上传 backend/api/monitors/index.php ...
"C:\Program Files\Git\usr\bin\scp.exe" -P 1022 -o StrictHostKeyChecking=no "E:\ai本地应用\多用户接码\backend\api\monitors\index.php" root@134.185.111.25:/home/wwwroot/jm.91wz.org/backend/api/monitors/index.php
if errorlevel 1 (
    echo ❌ 上传失败！
) else (
    echo ✅ 上传成功！
)

echo.
echo 正在上传 backend/websocket/server.js ...
"C:\Program Files\Git\usr\bin\scp.exe" -P 1022 -o StrictHostKeyChecking=no "E:\ai本地应用\多用户接码\backend\websocket\server.js" root@134.185.111.25:/home/wwwroot/jm.91wz.org/backend/websocket/server.js
if errorlevel 1 (
    echo ❌ 上传失败！
) else (
    echo ✅ 上传成功！
)

echo.
echo 正在上传 index.html ...
"C:\Program Files\Git\usr\bin\scp.exe" -P 1022 -o StrictHostKeyChecking=no "E:\ai本地应用\多用户接码\index.html" root@134.185.111.25:/home/wwwroot/jm.91wz.org/index.html
if errorlevel 1 (
    echo ❌ 上传失败！
) else (
    echo ✅ 上传成功！
)

echo.
echo ========================================
echo   文件上传完成！
echo ========================================
echo.
echo 正在重启WebSocket服务...
"C:\Program Files\Git\usr\bin\ssh.exe" -p 1022 -o StrictHostKeyChecking=no root@134.185.111.25 "cd /home/wwwroot/jm.91wz.org/backend/websocket && pm2 restart websocket-server && pm2 status"

echo.
echo ========================================
echo   全部完成！
echo ========================================
pause
