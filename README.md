# 多用户动态验证码监控系统 (SMS Code Monitor)

[![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.6+-4479A1.svg)](https://mysql.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Version](https://img.shields.io/badge/Version-1.5.0-blue.svg)](https://github.com/2426366814/sms-code-monitor)

一个功能完善的多用户动态验证码监控系统，支持多用户管理、手机号监控、验证码自动提取、外部API接入和历史记录管理。

## ✨ 核心功能

### 📱 监控功能
| 功能 | 说明 |
|------|------|
| 自动刷新 | 支持1-10秒的刷新间隔 |
| 一键复制 | 支持复制手机号、验证码、原始信息 |
| 状态指示 | 显示有验证码、无验证码、请求错误等状态 |
| 批量操作 | 支持批量添加、删除、导出监控项 |
| 数据导出 | 支持导出监控项数据为JSON格式 |
| 通知提示 | 收到新验证码时显示提示 |

### 👤 用户功能
| 功能 | 说明 |
|------|------|
| 用户注册/登录 | 支持邮箱和用户名登录 |
| 密码管理 | 用户可自行修改密码 |
| 监控管理 | 添加、编辑、删除监控手机号 |
| 历史记录 | 查看验证码获取历史 |
| API密钥管理 | 生成和管理API密钥 |

### 👨‍💼 管理员功能
| 功能 | 说明 |
|------|------|
| 用户管理 | 查看、编辑、禁用/启用、删除用户 |
| 密码重置 | 管理员可重置用户密码 |
| 数据统计 | 查看用户数、监控数、验证码数等统计 |
| 监控管理 | 查看所有用户的监控项 |
| 系统设置 | 配置系统参数 |

### 🔌 外部API功能
| 功能 | 说明 |
|------|------|
| 对外聚合API | 提供给外部应用调用的统一API |
| 外部平台接入 | 支持接入第三方接码平台 |
| 号码管理 | 管理外部平台的手机号码 |
| Webhook推送 | 支持验证码变更时推送通知 |

### 🔐 安全特性
| 特性 | 说明 |
|------|------|
| 环境变量配置 | 敏感信息使用.env文件管理 |
| JWT认证 | 安全的Token认证机制 |
| 密码加密 | 使用password_hash加密存储 |
| 时序攻击防护 | 使用hash_equals验证 |
| SQL注入防护 | 使用PDO预处理语句 |

## 🚀 快速开始

### 环境要求

- PHP 7.4+
- MySQL 5.6+
- Nginx/Apache
- SSL证书（推荐）

### 安装步骤

#### 1. 克隆项目

```bash
git clone https://github.com/2426366814/sms-code-monitor.git
cd sms-code-monitor
```

#### 2. 配置数据库

创建数据库并导入SQL文件：

```bash
mysql -u root -p
CREATE DATABASE sms_monitor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sms_monitor;
SOURCE backend/database/database.sql;
```

#### 3. 配置环境变量

复制环境变量模板：

```bash
cp backend/config/.env.example backend/config/.env
```

编辑 `backend/config/.env`，修改配置：

```env
# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=sms_monitor
DB_USER=your_db_user
DB_PASS=your_db_password

# JWT Configuration
JWT_SECRET=your_random_secret_key_here
JWT_ALGORITHM=HS256
JWT_EXPIRES_IN=86400

# System Configuration
SYSTEM_NAME=SMS Code Monitor
SYSTEM_VERSION=1.5.0
APP_ENV=production
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
    location ~ \.env {
        deny all;
    }
}
```

#### 5. 设置权限

```bash
chmod -R 755 backend/
chmod -R 777 backend/logs/
chmod 600 backend/config/.env
```

#### 6. 创建管理员账号

```sql
INSERT INTO users (username, email, password_hash, status, is_admin, created_at) 
VALUES ('admin', 'admin@example.com', '$2y$10$your_hashed_password', 'active', 1, NOW());
```

## 📁 项目结构

```
sms-code-monitor/
├── backend/                       # 后端API
│   ├── api/                      # API接口
│   │   ├── auth/                 # 认证API
│   │   ├── admin/                # 管理员API
│   │   ├── monitors/             # 监控管理API
│   │   ├── codes/                # 验证码API
│   │   ├── apikeys/              # API密钥管理
│   │   ├── health/               # 健康检测API
│   │   ├── statistics/           # 统计API
│   │   ├── webhooks/             # Webhook推送
│   │   ├── external/             # 外部API
│   │   │   ├── v1/               # 对外聚合API v1
│   │   │   ├── platforms/        # 外部平台接入
│   │   │   └── phones/           # 号码管理
│   │   └── check-cccp.php        # 系统检查API
│   ├── config/                   # 配置文件
│   │   ├── config.php            # 主配置文件
│   │   ├── .env                  # 环境变量配置
│   │   └── .env.example          # 环境变量示例
│   ├── models/                   # 数据模型
│   │   ├── BaseModel.php
│   │   ├── UserModel.php
│   │   ├── MonitorModel.php
│   │   └── CodeModel.php
│   ├── utils/                    # 工具类
│   │   ├── Database.php          # 数据库工具
│   │   ├── JWT.php               # JWT工具
│   │   ├── Response.php          # 响应工具
│   │   ├── EnvLoader.php         # 环境变量加载器
│   │   ├── CaptchaExtractor.php  # 验证码提取
│   │   └── ApiAdapter.php        # API适配器
│   ├── database/                 # 数据库脚本
│   ├── logs/                     # 日志目录
│   ├── monitor_service.php       # 监控服务
│   └── auto_fetch_service.php    # 自动获取服务
├── index.html                    # 用户主界面
├── admin.html                    # 管理员后台
├── login.html                    # 登录页面
├── register.html                 # 注册页面
├── health.html                   # 健康检测页面
├── README.md                     # 项目说明
└── 项目开发文档.md               # 详细开发文档
```

## 🔧 API文档

### 认证接口

#### 用户注册
```http
POST /backend/api/auth/index.php?action=register
Content-Type: application/json

{
    "username": "testuser",
    "email": "test@example.com",
    "password": "password123"
}
```

#### 用户登录
```http
POST /backend/api/auth/index.php?action=login
Content-Type: application/json

{
    "username": "testuser",
    "password": "password123"
}
```

#### 获取当前用户信息
```http
GET /backend/api/auth/index.php?action=me
Authorization: Bearer {token}
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
    "phone": "13800138000",
    "url": "https://api.example.com/sms",
    "description": "测试手机号"
}
```

#### 批量添加监控
```http
POST /backend/api/monitors/index.php?action=batch-add
Authorization: Bearer {token}
Content-Type: application/json

{
    "monitors": [
        {"phone": "13800138001", "url": "https://api.example.com/sms"},
        {"phone": "13800138002", "url": "https://api.example.com/sms"}
    ]
}
```

### 外部API接口

#### 获取验证码列表
```http
GET /backend/api/external/v1/index.php?path=codes
X-API-Key: {api_key}
```

#### 获取监控列表
```http
GET /backend/api/external/v1/index.php?path=monitors
X-API-Key: {api_key}
```

### 系统检查API

#### 获取系统状态
```http
GET /backend/api/check-cccp.php
```

**响应示例：**
```json
{
    "success": true,
    "data": {
        "timestamp": "2026-03-02 21:27:08",
        "server": {
            "php_version": "7.4.33",
            "timezone": "PRC",
            "memory_limit": "128M"
        },
        "database": {
            "status": "ok",
            "version": "5.6.50-log"
        },
        "tables": {
            "users": {"exists": true, "count": 11},
            "monitors": {"exists": true, "count": 26},
            "verification_codes": {"exists": true, "count": 3313}
        }
    },
    "message": "检查完成"
}
```

## 🔒 安全说明

1. **环境变量配置** - 敏感信息使用.env文件管理，不硬编码
2. **JWT安全** - Token有效期24小时，密钥使用环境变量
3. **密码安全** - 使用password_hash加密，支持时序攻击防护
4. **SQL注入防护** - 使用PDO预处理语句
5. **SSL验证** - 生产环境自动启用SSL验证
6. **文件权限** - .env文件权限设置为600

## 📊 性能优化

- **并行请求** - 使用curl_multi并行获取多个监控项
- **数据库索引** - 关键字段建立索引
- **缓存机制** - 支持Redis缓存热点数据
- **日志轮转** - 自动清理过期日志

## 📝 更新日志

### v1.5.0 (2026-03-02)
- 🔐 安全重大更新 - 环境变量配置系统
- ✨ 新增EnvLoader环境变量加载器
- ✨ JWT过期时间优化为24小时
- ✨ SSL验证根据环境自动切换
- ✨ 新增check-cccp.php系统检查API
- 📝 更新项目文档

### v1.4.0 (2026-03-02)
- ⚡ 性能重大优化 - curl_multi并行请求
- ✨ 新增monitor_service.php监控服务
- ✨ 新增auto_fetch_service.php自动获取服务
- ✨ 新增statistics API统计接口
- ✨ 新增webhooks API推送接口

### v1.3.0 (2026-03-01)
- 🐛 修复验证码弹窗显示问题
- 🐛 修复监控列表排序问题
- 🐛 修复用户状态切换问题
- ✨ 新增批量操作功能

### v1.2.0 (2026-02-28)
- ✨ 后端API扩展 - 管理员API、Webhook等
- ✨ 新增外部API接入功能
- ✨ 新增API密钥管理

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

- **GitHub** - [2426366814](https://github.com/2426366814)

## 🙏 致谢

- 感谢所有贡献者的支持
- 感谢开源社区的贡献

---

⭐ 如果这个项目对你有帮助，请给个Star支持一下！
