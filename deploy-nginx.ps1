# 部署Nginx配置到远程服务器
# 使用宝塔面板的API或直接文件操作

$serverIP = "120.26.219.50"
$serverUser = "root"
$remotePath = "/www/server/panel/vhost/nginx/verify-code-monitor.local.conf"
$localConfig = Get-Content -Path ".\nginx_vhost.conf" -Raw

Write-Host "=== Nginx配置部署脚本 ===" -ForegroundColor Green
Write-Host ""
Write-Host "由于Windows环境无法直接使用SSH，请手动执行以下步骤：" -ForegroundColor Yellow
Write-Host ""
Write-Host "1. 登录到服务器: ssh root@$serverIP" -ForegroundColor Cyan
Write-Host ""
Write-Host "2. 备份现有配置:" -ForegroundColor Cyan
Write-Host "   cp $remotePath ${remotePath}.bak" -ForegroundColor Gray
Write-Host ""
Write-Host "3. 创建新的配置文件，内容如下:" -ForegroundColor Cyan
Write-Host "   nano $remotePath" -ForegroundColor Gray
Write-Host ""
Write-Host "=== 配置文件内容 ===" -ForegroundColor Green
Write-Host $localConfig -ForegroundColor White
Write-Host "=== 配置文件内容结束 ===" -ForegroundColor Green
Write-Host ""
Write-Host "4. 测试Nginx配置:" -ForegroundColor Cyan
Write-Host "   nginx -t" -ForegroundColor Gray
Write-Host ""
Write-Host "5. 重载Nginx配置:" -ForegroundColor Cyan
Write-Host "   systemctl reload nginx" -ForegroundColor Gray
Write-Host "   或者: /etc/init.d/nginx reload" -ForegroundColor Gray
Write-Host ""
Write-Host "6. 检查后端API是否可访问:" -ForegroundColor Cyan
Write-Host "   curl http://$serverIP`:8080/backend/api/auth/index.php" -ForegroundColor Gray
Write-Host ""
Write-Host "=== 备选方案 ===" -ForegroundColor Yellow
Write-Host "如果上述配置仍返回404，请尝试以下调试步骤：" -ForegroundColor Yellow
Write-Host ""
Write-Host "A. 检查文件是否存在:" -ForegroundColor Cyan
Write-Host "   ls -la /www/wwwroot/verify-code-monitor.local/backend/api/auth/" -ForegroundColor Gray
Write-Host ""
Write-Host "B. 检查PHP-FPM是否运行:" -ForegroundColor Cyan
Write-Host "   ps aux | grep php-fpm" -ForegroundColor Gray
Write-Host "   ls -la /tmp/php-cgi-74.sock" -ForegroundColor Gray
Write-Host ""
Write-Host "C. 查看Nginx错误日志:" -ForegroundColor Cyan
Write-Host "   tail -f /www/wwwlogs/verify-code-monitor.local.error.log" -ForegroundColor Gray
Write-Host ""
Write-Host "D. 创建测试PHP文件:" -ForegroundColor Cyan
Write-Host "   echo '<?php phpinfo(); ?>' > /www/wwwroot/verify-code-monitor.local/test.php" -ForegroundColor Gray
Write-Host "   curl http://$serverIP`:8080/test.php" -ForegroundColor Gray
Write-Host ""
Write-Host "E. 简化配置测试 - 直接在server块中添加:" -ForegroundColor Cyan
Write-Host @"
   location ~ \\.php$ {
       fastcgi_pass unix:/tmp/php-cgi-74.sock;
       fastcgi_index index.php;
       include fastcgi.conf;
   }
"@ -ForegroundColor Gray
Write-Host ""
