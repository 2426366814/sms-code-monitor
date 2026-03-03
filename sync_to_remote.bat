@echo off
echo 同步本地文件到远程服务器...

pscp -r -P 1022 ^
    -pw "Aa112211" ^
    backend\utils\JWT.php ^
    root@134.185.111.25:/home/wwwroot/jm.91wz.org/backend/utils/

echo 同步完成！
