# 部署指南

## 系统要求

- **操作系统**: Linux (Ubuntu 20.04+ / CentOS 7+)
- **Web服务器**: Nginx 1.18+ 或 Apache 2.4+
- **PHP**: 8.0+
- **数据库**: MySQL 5.7+ 或 MariaDB 10.3+
- **SSL**: 推荐使用HTTPS

## 快速部署

### 1. 安装依赖

#### Ubuntu/Debian
```bash
# 更新系统
sudo apt update && sudo apt upgrade -y

# 安装Nginx
sudo apt install -y nginx

# 安装PHP及扩展
sudo apt install -y php8.0 php8.0-fpm php8.0-mysql php8.0-curl php8.0-json php8.0-mbstring

# 安装MySQL
sudo apt install -y mysql-server

# 启动服务
sudo systemctl start nginx
sudo systemctl start php8.0-fpm
sudo systemctl start mysql

# 设置开机自启
sudo systemctl enable nginx
sudo systemctl enable php8.0-fpm
sudo systemctl enable mysql
```

#### CentOS/RHEL
```bash
# 安装EPEL和Remi仓库
sudo yum install -y epel-release
sudo yum install -y https://rpms.remirepo.net/enterprise/remi-release-7.rpm

# 安装Nginx
sudo yum install -y nginx

# 安装PHP 8.0
sudo yum install -y php80 php80-php-fpm php80-php-mysqlnd php80-php-curl php80-php-json php80-php-mbstring

# 安装MySQL
sudo yum install -y mysql-server

# 启动服务
sudo systemctl start nginx
sudo systemctl start php80-php-fpm
sudo systemctl start mysqld

# 设置开机自启
sudo systemctl enable nginx
sudo systemctl enable php80-php-fpm
sudo systemctl enable mysqld
```

### 2. 配置数据库

```bash
# 登录MySQL
sudo mysql -u root -p

# 创建数据库
CREATE DATABASE sms_monitor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 创建用户（可选，也可以使用root）
CREATE USER 'sms_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON sms_monitor.* TO 'sms_user'@'localhost';
FLUSH PRIVILEGES;

# 退出
EXIT;

# 导入数据库结构
cd /path/to/sms-code-monitor
mysql -u sms_user -p sms_monitor < database/schema.sql
```

### 3. 部署项目

```bash
# 克隆项目
cd /var/www
git clone https://github.com/yourusername/sms-code-monitor.git

# 设置权限
sudo chown -R www-data:www-data /var/www/sms-code-monitor
sudo chmod -R 755 /var/www/sms-code-monitor

# 配置config.php
cd sms-code-monitor
sudo cp backend/config/config.example.php backend/config/config.php
sudo nano backend/config/config.php
```

### 4. 配置Nginx

创建配置文件 `/etc/nginx/sites-available/sms-monitor`：

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/sms-code-monitor;
    index index.html;

    # 日志配置
    access_log /var/log/nginx/sms-monitor-access.log;
    error_log /var/log/nginx/sms-monitor-error.log;

    # 前端路由支持
    location / {
        try_files $uri $uri/ /index.html;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # API路由
    location /backend/api/ {
        try_files $uri $uri/ /backend/api/index.php?$query_string;
        
        # PHP处理
        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
        }
    }

    # PHP处理
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 禁止访问敏感文件
    location ~ /\. {
        deny all;
    }
    
    location ~ /backend/config/ {
        deny all;
    }
    
    location ~ /\.ht {
        deny all;
    }

    # 静态文件缓存
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 6M;
        access_log off;
        add_header Cache-Control "public, immutable";
    }
}
```

启用配置：
```bash
# 创建符号链接
sudo ln -s /etc/nginx/sites-available/sms-monitor /etc/nginx/sites-enabled/

# 检查配置
sudo nginx -t

# 重启Nginx
sudo systemctl restart nginx
```

### 5. 配置SSL（推荐）

使用Let's Encrypt免费证书：

```bash
# 安装Certbot
sudo apt install -y certbot python3-certbot-nginx

# 申请证书
sudo certbot --nginx -d your-domain.com

# 自动续期
sudo certbot renew --dry-run
```

### 6. 防火墙配置

```bash
# Ubuntu/Debian (UFW)
sudo ufw allow 'Nginx Full'
sudo ufw allow OpenSSH
sudo ufw enable

# CentOS/RHEL (Firewalld)
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

## Docker部署（可选）

### 使用Docker Compose

创建 `docker-compose.yml`：

```yaml
version: '3.8'

services:
  app:
    image: php:8.0-fpm
    container_name: sms-monitor-app
    volumes:
      - ./:/var/www/html
    working_dir: /var/www/html
    depends_on:
      - db
    networks:
      - sms-network

  nginx:
    image: nginx:alpine
    container_name: sms-monitor-nginx
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app
    networks:
      - sms-network

  db:
    image: mysql:5.7
    container_name: sms-monitor-db
    environment:
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_DATABASE: sms_monitor
      MYSQL_USER: sms_user
      MYSQL_PASSWORD: sms_password
    volumes:
      - db_data:/var/lib/mysql
      - ./database/schema.sql:/docker-entrypoint-initdb.d/schema.sql
    networks:
      - sms-network

volumes:
  db_data:

networks:
  sms-network:
    driver: bridge
```

启动：
```bash
docker-compose up -d
```

## 验证部署

1. **访问首页**: http://your-domain.com
2. **测试注册**: 创建测试账号
3. **测试登录**: 使用管理员账号登录
4. **测试功能**: 添加监控、查看验证码等

## 常见问题

### 1. 403 Forbidden
```bash
# 检查权限
sudo chown -R www-data:www-data /var/www/sms-code-monitor
sudo chmod -R 755 /var/www/sms-code-monitor
```

### 2. 502 Bad Gateway
```bash
# 检查PHP-FPM
sudo systemctl status php8.0-fpm
sudo systemctl restart php8.0-fpm
```

### 3. 数据库连接失败
```bash
# 检查配置
sudo nano /var/www/sms-code-monitor/backend/config/config.php

# 测试连接
mysql -u sms_user -p -e "USE sms_monitor; SHOW TABLES;"
```

### 4. API返回500
```bash
# 查看错误日志
sudo tail -f /var/log/nginx/sms-monitor-error.log
sudo tail -f /var/log/php8.0-fpm.log
```

## 维护

### 备份数据库
```bash
# 创建备份
mysqldump -u sms_user -p sms_monitor > backup_$(date +%Y%m%d).sql

# 恢复备份
mysql -u sms_user -p sms_monitor < backup_20260101.sql
```

### 更新项目
```bash
cd /var/www/sms-code-monitor
sudo git pull origin main
sudo chown -R www-data:www-data .
```

### 查看日志
```bash
# Nginx日志
sudo tail -f /var/log/nginx/sms-monitor-error.log

# PHP日志
sudo tail -f /var/log/php8.0-fpm.log
```

## 安全建议

1. **定期更新系统**: `sudo apt update && sudo apt upgrade`
2. **使用强密码**: 数据库密码和JWT密钥
3. **限制访问**: 使用防火墙限制不必要的端口
4. **定期备份**: 数据库和配置文件
5. **监控日志**: 定期检查错误日志
6. **使用HTTPS**: 生产环境必须使用SSL

## 性能优化

### Nginx优化
```nginx
# 在server块中添加
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_types text/plain text/css application/json application/javascript text/xml;

# 客户端缓存
location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
    expires 6M;
    access_log off;
}
```

### PHP优化
编辑 `/etc/php/8.0/fpm/php.ini`：
```ini
memory_limit = 256M
max_execution_time = 30
max_input_vars = 3000
upload_max_filesize = 64M
post_max_size = 64M
```

### MySQL优化
编辑 `/etc/mysql/mysql.conf.d/mysqld.cnf`：
```ini
[mysqld]
innodb_buffer_pool_size = 256M
max_connections = 100
query_cache_size = 64M
```

---

**部署完成！** 访问 http://your-domain.com 开始使用系统。
