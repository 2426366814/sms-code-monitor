# 多用户动态验证码监控系统 (SMS Code Monitor)

[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1.svg)](https://mysql.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Version](https://img.shields.io/badge/Version-2.0.0-blue.svg)](https://github.com/yourusername/sms-code-monitor)

一个功能完善的多用户动态验证码监控系统，支持多用户管理、手机号监控、验证码自动提取和历史记录管理。

## ✨ 功能特性

### 用户功能
- 🔐 **用户注册/登录** - 支持邮箱和用户名登录
- 🔑 **修改密码** - 用户可自行修改密码
- 📱 **监控管理** - 添加、编辑、删除监控手机号
- 📊 **实时监控** - 自动刷新监控状态
- 📜 **历史记录** - 查看验证码获取历史
- 📤 **数据导出** - 支持导出验证码数据

### 管理员功能
- 👥 **用户管理** - 查看、编辑、禁用/启用、删除用户
- 🔒 **密码重置** - 管理员可重置用户密码
- 📈 **数据统计** - 查看用户数、监控数、验证码数等统计
- 🔍 **监控管理** - 查看所有用户的监控项

### 系统特性
- 🔒 **JWT认证** - 安全的Token认证机制
- 🛡️ **密码加密** - 使用password_hash加密存储
- 📝 **操作日志** - 记录用户操作历史
- 🎨 **响应式设计** - 支持PC和移动端访问
- 🔄 **自动刷新** - 支持定时自动刷新监控状态

## 🚀 快速开始

### 环境要求

- PHP 8.0+
- MySQL 5.7+
- Nginx/Apache
- SSL证书（推荐）

### 安装步骤

#### 1. 克隆项目

```bash
git clone https://github.com/yourusername/sms-code-monitor.git
cd sms-code-monitor
```

#### 2. 配置数据库

创建数据库并导入SQL文件：

```bash
mysql -u root -p
CREATE DATABASE sms_monitor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sms_monitor;
SOURCE database/schema.sql;
```

#### 3. 配置项目

复制配置文件模板：

```bash
cp backend/config/config.example.php backend/config/config.php
```

编辑 `backend/config/config.php`，修改数据库连接信息：

```php
'database' => [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'sms_monitor',
    'user' => 'your_db_user',
    'password' => 'your_db_password',
    'charset' => 'utf8mb4'
],
'jwt' => [
    'secret' => 'your-secret-key-here',  // 修改为一个随机字符串
    'algorithm' => 'HS256',
    'expiration' => 86400  // 24小时
]
```

#### 4. 配置Web服务器

**Nginx配置示例：**

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/sms-code-monitor;
    index index.html;

    # 前端路由支持
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API路由
    location /backend/api/ {
        try_files $uri $uri/ /backend/api/index.php?$query_string;
    }

    # PHP处理
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
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
}
```

**Apache配置示例（.htaccess）：**

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.html [L]
```

#### 5. 设置权限

```bash
chmod -R 755 backend/
chmod -R 777 backend/logs/  # 如果有日志目录
```

#### 6. 创建管理员账号

执行SQL创建初始管理员：

```sql
INSERT INTO users (username, email, password_hash, status, is_admin, created_at) 
VALUES ('admin', 'admin@example.com', '$2y$10$your_hashed_password', 'active', 1, NOW());
```

或使用默认管理员账号：
- 用户名：`admin`
- 密码：`admin123`

## 📁 项目结构

```
sms-code-monitor/
├── backend/                    # 后端API
│   ├── api/                   # API接口
│   │   ├── auth/             # 认证相关
│   │   ├── admin/            # 管理员接口
│   │   ├── monitors/         # 监控管理接口
│   │   └── codes/            # 验证码接口
│   ├── config/               # 配置文件
│   ├── models/               # 数据模型
│   ├── utils/                # 工具类
│   └── logs/                 # 日志目录
├── css/                      # 样式文件
├── js/                       # JavaScript文件
├── index.html                # 用户主界面
├── admin.html                # 管理员后台
├── login.html                # 登录页面
├── register.html             # 注册页面
├── README.md                 # 项目说明
└── LICENSE                   # 许可证
```

## 🔧 API文档

### 认证接口

#### 用户注册
```http
POST /backend/api/auth/index.php/register
Content-Type: application/json

{
    "username": "testuser",
    "email": "test@example.com",
    "password": "password123"
}
```

#### 用户登录
```http
POST /backend/api/auth/index.php/login
Content-Type: application/json

{
    "username": "testuser",
    "password": "password123"
}
```

#### 修改密码
```http
POST /backend/api/auth/index.php/change-password
Authorization: Bearer {token}
Content-Type: application/json

{
    "old_password": "oldpass123",
    "new_password": "newpass123"
}
```

### 监控接口

#### 获取监控列表
```http
GET /backend/api/monitors/index.php
Authorization: Bearer {token}
```

#### 添加监控
```http
POST /backend/api/monitors/index.php
Authorization: Bearer {token}
Content-Type: application/json

{
    "phone_number": "13800138000",
    "description": "测试手机号"
}
```

#### 删除监控
```http
DELETE /backend/api/monitors/index.php?id={monitor_id}
Authorization: Bearer {token}
```

### 管理员接口

#### 获取用户列表
```http
GET /backend/api/admin/index.php?action=users&page=1&limit=10
Authorization: Bearer {admin_token}
```

#### 更新用户信息
```http
POST /backend/api/admin/index.php?action=update-user&id={user_id}
Authorization: Bearer {admin_token}
Content-Type: application/json

{
    "username": "newname",
    "email": "newemail@example.com",
    "password": "newpassword123"
}
```

#### 切换用户状态
```http
POST /backend/api/admin/index.php?action=toggle-user-status&id={user_id}
Authorization: Bearer {admin_token}
```

#### 删除用户
```http
DELETE /backend/api/admin/index.php?action=delete-user&id={user_id}
Authorization: Bearer {admin_token}
```

## 🔒 安全说明

1. **修改默认密钥** - 部署前务必修改 `config.php` 中的 JWT secret
2. **使用HTTPS** - 生产环境必须使用HTTPS
3. **数据库安全** - 使用强密码，限制数据库访问IP
4. **定期备份** - 建议定期备份数据库
5. **更新依赖** - 定期更新PHP和MySQL到最新版本

## 🐛 常见问题

### 1. API返回500错误
- 检查PHP版本是否 >= 8.0
- 检查数据库连接配置
- 查看服务器错误日志

### 2. 无法登录
- 确认用户名/密码正确
- 检查用户状态是否为active
- 清除浏览器缓存

### 3. 监控不刷新
- 检查网络连接
- 确认自动刷新设置已开启
- 查看浏览器控制台错误

## 📝 更新日志

### v2.0.0 (2026-02-07)
- ✨ 全新界面设计
- ✨ 添加管理员用户管理功能
- ✨ 添加用户名/邮箱重复验证
- ✨ 优化API响应速度
- 🐛 修复多处已知问题

### v1.0.0 (2026-02-01)
- 🎉 初始版本发布
- ✨ 用户注册/登录
- ✨ 手机号监控管理
- ✨ 验证码历史记录

## 🤝 贡献指南

欢迎提交Issue和Pull Request！

1. Fork 本仓库
2. 创建你的特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交你的修改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 打开一个 Pull Request

## 📄 许可证

本项目基于 [MIT](LICENSE) 许可证开源。

## 👨‍💻 作者

- **Your Name** - *Initial work* - [YourGithub](https://github.com/yourusername)

## 🙏 致谢

- 感谢所有贡献者的支持
- 感谢开源社区的贡献

---

⭐ 如果这个项目对你有帮助，请给个Star支持一下！
