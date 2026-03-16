# 多用户动态验证码监控系统 - 开发文档

## 项目概述

**项目名称**: 多用户动态验证码监控系统  
**生产环境**: https://jm.91wz.org  
**远程服务器**: root@134.185.111.25:1022  
**数据库**: MySQL (jm)  
**技术栈**: PHP 7.4 + Node.js 20 + WebSocket + MySQL

---

## 架构说明

### 当前架构 (v2.0 - 2026-03-11)

```
┌─────────────────────────────────────────────────────────────┐
│                    前端 (Vue.js + HTML)                      │
│  - index.html (用户面板)                                     │
│  - admin.html (管理员面板)                                   │
│  - WebSocket 客户端 (实时接收验证码)                          │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                 后端服务 (PHP + Node.js)                     │
│                                                              │
│  ┌──────────────────┐    ┌──────────────────────────────┐  │
│  │   PHP API 服务    │    │   Node.js WebSocket 服务     │  │
│  │   (Nginx + PHP)   │    │   (PM2 托管, 端口 8080)      │  │
│  │                   │    │                              │  │
│  │  - 用户认证       │    │  - WebSocket 连接管理        │  │
│  │  - 监控项管理     │    │  - MonitorService (监控服务)  │  │
│  │  - 验证码历史     │    │  - 验证码实时推送            │  │
│  │  - 管理员功能     │    │  - 分批处理 (50个/批)        │  │
│  └──────────────────┘    └──────────────────────────────┘  │
│           │                          │                       │
│           └──────────┬───────────────┘                       │
│                      ▼                                       │
│           ┌──────────────────────┐                          │
│           │   MySQL 数据库 (jm)   │                          │
│           │   - users            │                          │
│           │   - monitors         │                          │
│           │   - verification_codes│                          │
│           │   - api_keys         │                          │
│           └──────────────────────┘                          │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                 外部 SMS API 服务                            │
│  - https://sms.91wz.org/api/record?token=xxx                │
│  - 返回格式: {"code":1,"data":{"code":"验证码内容"}}         │
└─────────────────────────────────────────────────────────────┘
```

### 核心组件

| 组件 | 文件路径 | 说明 |
|------|----------|------|
| 前端用户面板 | `/index.html` | 用户登录、监控列表、验证码弹窗 |
| 前端管理员面板 | `/admin.html` | 用户管理、系统管理 |
| PHP API | `/backend/api/` | RESTful API 接口 |
| WebSocket 服务 | `/backend/websocket/server.js` | 实时通信 + 监控服务 |
| 监控服务模块 | `/backend/websocket/monitor-service.js` | 高性能验证码监控 |

---

## 数据库表结构

### users 表
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE,
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    is_admin BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### monitors 表
```sql
CREATE TABLE monitors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    phone VARCHAR(20),
    url VARCHAR(500),
    last_code TEXT,
    last_extracted_code VARCHAR(20),
    code_timestamp BIGINT,
    status ENUM('success', 'no-code', 'error'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### verification_codes 表
```sql
CREATE TABLE verification_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    monitor_id INT,
    phone VARCHAR(20),
    code VARCHAR(20),
    message TEXT,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## API 接口

### 认证接口 (`/backend/api/auth/index.php`)

| Action | Method | 说明 |
|--------|--------|------|
| login | POST | 用户登录 |
| register | POST | 用户注册 |
| logout | POST | 用户登出 |
| me | GET | 获取当前用户信息 |

### 监控接口 (`/backend/api/monitors/index.php`)

| Method | 说明 |
|--------|------|
| GET | 获取用户监控列表 |
| POST | 添加监控项 |
| PUT | 更新监控项 |
| DELETE | 删除监控项 |

### 管理员接口 (`/backend/api/admin/index.php`)

| Action | Method | 说明 |
|--------|--------|------|
| users | GET | 获取用户列表 |
| stats | GET | 获取系统统计 |

---

## WebSocket 协议

### 连接
```
ws://localhost:8080?token=JWT_TOKEN
```

### 消息类型

#### 服务端 -> 客户端

```javascript
// 连接成功
{ "type": "connected", "message": "WebSocket connection established" }

// 新验证码
{ "type": "new_code", "data": { "phone": "+85212345678", "code": "123456" } }

// 监控列表更新
{ "type": "monitors_update", "data": [...] }

// 心跳响应
{ "type": "pong", "timestamp": 1234567890 }
```

#### 客户端 -> 服务端

```javascript
// 心跳
{ "type": "ping" }

// 获取监控列表
{ "type": "get_monitors" }
```

---

## 配置文件

### 环境配置 (`/backend/config/.env`)

```env
# 数据库配置
DB_HOST=localhost
DB_USER=your_db_user
DB_PASS=your_db_password
DB_NAME=jm

# JWT 配置
JWT_SECRET=your-jwt-secret-key

# WebSocket 配置
WS_PORT=8080
```

### Nginx 配置

```nginx
server {
    listen 443 ssl;
    server_name jm.91wz.org;
    root /home/wwwroot/jm.91wz.org;
    
    # API 路由重写
    location /api/ {
        rewrite ^/api/(.*)$ /backend/api/$1.php?$query_string last;
    }
    
    # WebSocket 代理
    location /ws/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
```

---

## 部署说明

### PM2 服务管理

```bash
# 查看服务状态
pm2 status

# 查看日志
pm2 logs websocket-server

# 重启服务
pm2 restart websocket-server

# 停止服务
pm2 stop websocket-server
```

### 服务启动

```bash
# 启动 WebSocket 服务 (包含监控服务)
cd /home/wwwroot/jm.91wz.org/backend/websocket
pm2 start server.js --name websocket-server
```

---

## 变更历史

### 2026-03-11 - 架构升级 v2.0

**变更内容:**
- 将 PHP Cron 监控服务重构为 Node.js 常驻进程
- 新增 `monitor-service.js` 高性能监控模块
- 更新 `server.js` 整合监控服务

**变更原因:**
- PHP Cron 模式导致服务器过载 (负载 11.88)
- 每次启动新进程开销大
- 500+ 监控项同时处理导致内存不足

**变更效果:**
- 服务器负载降低 97% (11.88 -> 0.25)
- 内存使用降低 42% (600MB+ -> 348MB)
- 刷新间隔从 30秒 优化到 5秒
- 处理时间稳定在 2秒内

**删除文件:**
- `/backend/monitor_service.php` (已删除)

**新增文件:**
- `/backend/websocket/monitor-service.js`

**修改文件:**
- `/backend/websocket/server.js`

---

## 开发注意事项

1. **禁止本地安装 Playwright** - 使用全局库 `D:\playwright-data\lib\playwright-global.js`
2. **敏感信息** - 不要在代码中硬编码密码、密钥
3. **数据库连接** - 使用连接池，不要频繁创建连接
4. **监控服务** - 已整合到 WebSocket 服务，不要单独运行
5. **Cron 任务** - 已清除，监控服务由 Node.js 常驻进程处理

---

## 联系方式

- 开发者: Claude AI Assistant
- 更新时间: 2026-03-11
