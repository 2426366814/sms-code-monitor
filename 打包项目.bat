@echo off
chcp 65001 >nul
echo ============================================
echo 多用户动态验证码监控系统打包工具
echo ============================================
echo.

REM 删除旧的打包文件
if exist verify-code-monitor.zip del verify-code-monitor.zip
if exist verify-code-monitor.tar.gz del verify-code-monitor.tar.gz

echo 正在打包项目...

REM 使用PowerShell打包
powershell -Command "Compress-Archive -Path 'index.html','login.html','register.html','backend' -DestinationPath verify-code-monitor.zip -Force"

if exist verify-code-monitor.zip (
    echo.
    echo ============================================
    echo 打包成功！
    echo 文件名: verify-code-monitor.zip
    echo 路径: %CD%\verify-code-monitor.zip
    echo ============================================
    echo.
    echo 部署步骤:
    echo 1. 将 verify-code-monitor.zip 上传到服务器
    echo 2. 解压到 /www/wwwroot/verify-code-monitor.local/
    echo 3. 配置数据库
    echo 4. 访问 http://192.168.7.178:8080
    echo ============================================
) else (
    echo 打包失败！
)

pause
