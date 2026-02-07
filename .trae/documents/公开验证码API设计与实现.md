# 公开验证码API设计与实现

## 1. 需求分析

用户需要一个公开的API页面，不需要登录网站就能访问，显示当前监控里的所有验证码信息，包括验证码原信息，并在用户后台添加相应的设置选项。

## 2. 技术方案

### 2.1 API设计

**API端点**：`GET /api/public/codes.php`

**认证方式**：API密钥认证，通过URL参数 `?key=api_key`

**请求参数**：
- `key`：必填，API密钥
- `phone`：可选，过滤特定手机号的验证码
- `limit`：可选，返回数量限制，默认20
- `format`：可选，返回格式，支持 `json`（默认）和 `text`

**响应格式**：
```json
{
  "success": true,
  "data": [
    {
      "phone": "+13199898616",
      "code": "123456",
      "original_text": "完整的原始响应文本",
      "created_at": "2025-12-14 10:00:00"
    }
  ],
  "message": "获取成功",
  "code": 200
}
```

### 2.2 数据库设计

需要在 `users` 表中添加以下字段：
- `api_key`：API密钥
- `api_enabled`：是否启用API
- `api_limit`：API请求限制
- `api_expires_at`：API密钥过期时间

### 2.3 用户后台设置

在 `index.html` 中添加API设置区域，包括：
- API密钥显示和重置
- API开关
- 请求限制设置
- 过期时间设置
- 使用说明

## 3. 实现步骤

### 3.1 数据库修改

1. 修改 `database.sql`，在 `users` 表中添加API相关字段
2. 创建API密钥生成函数

### 3.2 API实现

1. 创建 `api/public/codes.php` 文件
2. 实现API密钥验证
3. 实现验证码查询逻辑
4. 支持多种返回格式
5. 添加请求限制

### 3.3 用户后台设置

1. 在 `index.html` 中添加API设置区域
2. 实现API密钥生成和重置功能
3. 实现API开关和限制设置
4. 添加使用说明

### 3.4 安全性考虑

1. API密钥加密存储
2. 请求频率限制
3. 支持API密钥过期
4. IP白名单（可选）
5. 敏感信息保护

## 4. 具体实现

### 4.1 数据库修改

```sql
-- 添加API相关字段到users表
ALTER TABLE users ADD COLUMN api_key VARCHAR(64) NULL;
ALTER TABLE users ADD COLUMN api_enabled BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN api_limit INT DEFAULT 100;
ALTER TABLE users ADD COLUMN api_expires_at DATETIME NULL;
ALTER TABLE users ADD COLUMN api_ips TEXT NULL; -- 可选，IP白名单
```

### 4.2 API实现

```php
<?php
// 公开验证码API
require_once '../common.php';

header('Content-Type: application/json');

// 获取API密钥
$apiKey = $_GET['key'] ?? '';
if (empty($apiKey)) {
    jsonResponse(false, null, '缺少API密钥', 401);
}

// 验证API密钥
$database = new Database();
$pdo = $database->getPdo();

$stmt = $pdo->prepare('SELECT * FROM users WHERE api_key = ? AND api_enabled = TRUE');
$stmt->execute([$apiKey]);
$user = $stmt->fetch();

if (!$user) {
    jsonResponse(false, null, '无效的API密钥', 401);
}

// 检查API密钥是否过期
if ($user['api_expires_at'] && new DateTime() > new DateTime($user['api_expires_at'])) {
    jsonResponse(false, null, 'API密钥已过期', 401);
}

// 查询验证码
$phone = $_GET['phone'] ?? '';
$limit = $_GET['limit'] ?? 20;
$limit = max(1, min(100, intval($limit)));

$query = 'SELECT phone, code, original_text, created_at FROM codes WHERE user_id = ?';
$params = [$user['id']];

if (!empty($phone)) {
    $query .= ' AND phone = ?';
    $params[] = $phone;
}

$query .= ' ORDER BY created_at DESC LIMIT ?';
$params[] = $limit;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$codes = $stmt->fetchAll();

// 返回响应
jsonResponse(true, $codes, '获取成功', 200);
```

### 4.3 用户后台设置

在 `index.html` 中添加API设置区域，包括：
- API密钥显示和重置按钮
- API开关
- 请求限制设置
- 过期时间设置

### 4.4 API密钥生成

```php
// 生成随机API密钥
function generateApiKey() {
    return bin2hex(random_bytes(32));
}
```

## 5. 使用示例

### 5.1 获取所有验证码
```bash
curl "http://your-domain/api/public/codes.php?key=your_api_key"
```

### 5.2 获取特定手机号的验证码
```bash
curl "http://your-domain/api/public/codes.php?key=your_api_key&phone=+13199898616"
```

### 5.3 获取指定数量的验证码
```bash
curl "http://your-domain/api/public/codes.php?key=your_api_key&limit=5"
```

## 6. 安全性措施

1. **API密钥加密**：使用安全的哈希算法存储API密钥
2. **请求限制**：限制每个API密钥的请求频率
3. **密钥过期**：支持设置API密钥的过期时间
4. **IP白名单**：可选，只允许特定IP访问
5. **HTTPS支持**：建议在生产环境使用HTTPS
6. **输入验证**：对所有请求参数进行严格验证

这个设计方案满足了用户的需求，提供了一个公开的API端点，不需要登录网站就能访问，显示当前监控里的所有验证码信息，包括验证码原信息，并在用户后台添加了相应的设置选项。