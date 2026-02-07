# Nginx配置部署指南

## 问题描述
后端API请求 `/backend/api/auth/index.php` 返回404错误，需要修复Nginx配置。

## 解决方案

### 步骤1: 登录服务器
```bash
ssh root@120.26.219.50
```

### 步骤2: 备份现有配置
```bash
cp /www/server/panel/vhost/nginx/verify-code-monitor.local.conf /www/server/panel/vhost/nginx/verify-code-monitor.local.conf.bak
```

### 步骤3: 编辑Nginx配置文件
```bash
nano /www/server/panel/vhost/nginx/verify-code-monitor.local.conf
```

将内容替换为以下配置（方案A - 推荐）：

```nginx
server
{
    listen 8080;
    server_name verify-code-monitor.local;
    index index.php index.html index.htm default.php default.htm default.html;
    root /www/wwwroot/verify-code-monitor.local;

    #ERROR-PAGE-START
    error_page 404 /404.html;
    error_page 502 /502.html;
    #ERROR-PAGE-END

    # 前端页面路由
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Backend API - PHP文件处理（使用正则匹配，优先级更高）
    location ~ ^/backend/.*\.php(/|$) {
        fastcgi_pass unix:/tmp/php-cgi-74.sock;
        fastcgi_index index.php;
        include fastcgi.conf;
        include pathinfo.conf;
    }

    # Backend API - 目录访问
    location /backend/ {
        try_files $uri $uri/ =404;
    }

    #PHP-INFO-START
    include enable-php-74.conf;
    #PHP-INFO-END

    #禁止访问的文件或目录
    location ~ ^/(\.user.ini|\.htaccess|\.git|\.env|\.svn|\.project|LICENSE|README.md)     
    {
        return 404;
    }

    location ~ \.well-known{
        allow all;
    }

    if ( $uri ~ "^/\.well-known/.*\.(php|jsp|py|js|css|lua|ts|go|zip|tar\.gz|rar|7z|sql|bak)$" ) {
        return 403;
    }

    location ~ .*\.(gif|jpg|jpeg|png|bmp|swf)$
    {
        expires      30d;
        error_log /dev/null;
        access_log /dev/null;
    }

    location ~ .*\.(js|css)?$
    {
        expires      12h;
        error_log /dev/null;
        access_log /dev/null;
    }
    access_log  /www/wwwlogs/verify-code-monitor.local.log;
    error_log  /www/wwwlogs/verify-code-monitor.local.error.log;
}
```

### 备选方案B（如果方案A不工作）
如果上述配置仍返回404，尝试使用简化版本：

```nginx
server
{
    listen 8080;
    server_name verify-code-monitor.local;
    index index.php index.html index.htm default.php default.htm default.html;
    root /www/wwwroot/verify-code-monitor.local;

    error_page 404 /404.html;
    error_page 502 /502.html;

    # 前端页面路由
    location / {
        try_files $uri $uri/ /index.html;
    }

    # 通用PHP文件处理 - 包含backend目录下的PHP文件
    location ~ [^/]\.php(/|$) {
        fastcgi_pass unix:/tmp/php-cgi-74.sock;
        fastcgi_index index.php;
        include fastcgi.conf;
        include pathinfo.conf;
    }

    # Backend目录访问
    location /backend/ {
        try_files $uri $uri/ =404;
    }

    #禁止访问的文件或目录
    location ~ ^/(\.user.ini|\.htaccess|\.git|\.env|\.svn|\.project|LICENSE|README.md)     
    {
        return 404;
    }

    location ~ \.well-known{
        allow all;
    }

    access_log  /www/wwwlogs/verify-code-monitor.local.log;
    error_log  /www/wwwlogs/verify-code-monitor.local.error.log;
}
```

### 步骤4: 测试Nginx配置
```bash
nginx -t
```

### 步骤5: 重载Nginx配置
```bash
systemctl reload nginx
# 或者
/etc/init.d/nginx reload
```

### 步骤6: 验证后端API
```bash
curl http://120.26.219.50:8080/backend/api/auth/index.php
```

## 故障排除

### 检查文件是否存在
```bash
ls -la /www/wwwroot/verify-code-monitor.local/backend/api/auth/
```

### 检查PHP-FPM是否运行
```bash
ps aux | grep php-fpm
ls -la /tmp/php-cgi-74.sock
```

### 查看Nginx错误日志
```bash
tail -f /www/wwwlogs/verify-code-monitor.local.error.log
```

### 创建测试PHP文件
```bash
echo '<?php phpinfo(); ?>' > /www/wwwroot/verify-code-monitor.local/test.php
curl http://120.26.219.50:8080/test.php
```

### 检查rewrite配置
确保 `/www/server/panel/vhost/rewrite/verify-code-monitor.local.conf` 不会干扰backend：
```bash
cat /www/server/panel/vhost/rewrite/verify-code-monitor.local.conf
```

内容应该是：
```nginx
# 前端页面伪静态规则
location / {
    if (!-e $request_filename) {
        rewrite ^(.*)$ /index.html last;
    }
}
```

## 配置说明

### 关键修改点

1. **正则匹配优先**: `location ~ ^/backend/.*\.php(/|$)` 使用正则匹配，Nginx会优先处理
2. **分离PHP处理**: 专门为backend目录下的PHP文件配置处理规则
3. **目录访问支持**: `location /backend/` 允许访问backend目录下的静态文件
4. **include位置**: `include enable-php-74.conf` 放在后面，避免与自定义规则冲突

### 工作原理

- 当请求 `/backend/api/auth/index.php` 时，Nginx首先匹配 `location ~ ^/backend/.*\.php(/|$)`
- 这个正则匹配优先级高于 `location /`，所以请求会被正确转发到PHP-FPM
- PHP-FPM处理PHP文件并返回结果，而不是返回404
