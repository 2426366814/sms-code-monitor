# 完整部署指南

## 已完成的修复

### 1. Nginx配置修复
- 创建了新的Nginx配置文件，正确处理backend目录下的PHP文件
- 使用正则匹配 `location ~ ^/backend/.*\.php(/|$)` 优先处理API请求

### 2. 数据库连接修复
修复了以下文件中的数据库连接问题：
- `backend/models/BaseModel.php` - 添加了Database.php的require语句
- `backend/api/admin/index.php` - 改为使用 `Database::getInstance()`
- `backend/api/codes/index.php` - 改为使用 `Database::getInstance()`
- `backend/api/monitors/index.php` - 改为使用 `Database::getInstance()`

## 部署步骤

### 步骤1: 登录服务器
```bash
ssh root@120.26.219.50
```

### 步骤2: 更新Nginx配置
```bash
# 备份现有配置
cp /www/server/panel/vhost/nginx/verify-code-monitor.local.conf /www/server/panel/vhost/nginx/verify-code-monitor.local.conf.bak

# 编辑配置文件
nano /www/server/panel/vhost/nginx/verify-code-monitor.local.conf
```

将内容替换为：
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

### 步骤3: 测试并重载Nginx
```bash
nginx -t
systemctl reload nginx
```

### 步骤4: 更新后端PHP文件

需要将以下修复后的文件上传到服务器：

1. **backend/models/BaseModel.php**
   - 添加了 `require_once __DIR__ . '/../utils/Database.php';`

2. **backend/api/admin/index.php**
   - 将 `new Database($config['database'])` 改为 `Database::getInstance()`
   - 将 `new UserModel($database)` 改为 `new UserModel()`

3. **backend/api/codes/index.php**
   - 将 `new Database($config['database'])` 改为 `Database::getInstance()`
   - 将 `new MonitorModel($database)` 改为 `new MonitorModel()`
   - 将 `new UserModel($database)` 改为 `new UserModel()`

4. **backend/api/monitors/index.php**
   - 将 `new Database($config['database'])` 改为 `Database::getInstance()`
   - 将 `new MonitorModel($database)` 改为 `new MonitorModel()`
   - 将 `new UserModel($database)` 改为 `new UserModel()`

使用以下命令上传文件（在本地执行）：
```bash
# 使用scp上传文件（如果有ssh/scp工具）
scp backend/models/BaseModel.php root@120.26.219.50:/www/wwwroot/verify-code-monitor.local/backend/models/
scp backend/api/admin/index.php root@120.26.219.50:/www/wwwroot/verify-code-monitor.local/backend/api/admin/
scp backend/api/codes/index.php root@120.26.219.50:/www/wwwroot/verify-code-monitor.local/backend/api/codes/
scp backend/api/monitors/index.php root@120.26.219.50:/www/wwwroot/verify-code-monitor.local/backend/api/monitors/
```

如果没有scp工具，可以手动复制文件内容到服务器。

### 步骤5: 验证修复

1. **测试后端API访问**
```bash
curl http://120.26.219.50:8080/backend/api/auth/index.php
```
应该返回JSON响应，而不是404错误。

2. **测试健康检查API**
```bash
curl "http://120.26.219.50:8080/backend/api/health/index.php?type=all"
```

3. **查看Nginx错误日志**
```bash
tail -f /www/wwwlogs/verify-code-monitor.local.error.log
```

4. **查看PHP错误日志**
```bash
tail -f /www/wwwlogs/php_error.log
```

## 测试计划

### 1. 用户注册测试
```bash
curl -X POST http://120.26.219.50:8080/backend/api/auth/index.php/register \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","email":"test@example.com","password":"123456"}'
```

### 2. 用户登录测试
```bash
curl -X POST http://120.26.219.50:8080/backend/api/auth/index.php/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"123456"}'
```

### 3. 获取当前用户信息
```bash
curl http://120.26.219.50:8080/backend/api/auth/index.php/me \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### 4. 管理员获取用户列表
```bash
curl http://120.26.219.50:8080/backend/api/admin/index.php/users \
  -H "Authorization: Bearer ADMIN_TOKEN_HERE"
```

## 故障排除

### 如果仍然返回404
1. 检查文件是否存在：
```bash
ls -la /www/wwwroot/verify-code-monitor.local/backend/api/auth/
```

2. 检查PHP-FPM是否运行：
```bash
ps aux | grep php-fpm
ls -la /tmp/php-cgi-74.sock
```

3. 创建测试PHP文件：
```bash
echo '<?php echo "PHP is working"; ?>' > /www/wwwroot/verify-code-monitor.local/test.php
curl http://120.26.219.50:8080/test.php
```

### 如果返回500错误
1. 查看PHP错误日志：
```bash
tail -f /www/wwwlogs/php_error.log
```

2. 检查数据库配置：
```bash
cat /www/wwwroot/verify-code-monitor.local/backend/config/config.php
```

3. 检查数据库连接：
```bash
curl "http://120.26.219.50:8080/backend/api/health/index.php?type=database"
```

## 文件清单

已修复的文件列表：
- `nginx_vhost.conf` - Nginx配置文件
- `backend/models/BaseModel.php` - 基础模型类
- `backend/api/admin/index.php` - 管理员API
- `backend/api/codes/index.php` - 验证码API
- `backend/api/monitors/index.php` - 监控项API
