# 多用户动态验证码监控系统 - 项目记忆

## 项目信息

- **项目名称**: 多用户动态验证码监控系统
- **域名**: jm.91wz.org
- **服务器**: 134.185.111.25:1022
- **宝塔面板**: https://134.185.111.25:777/cccpcccp
- **站点目录**: /home/wwwroot/jm.91wz.org
- **SSH登录**: root@134.185.111.25:1022 (密钥登录，无需密码)

## 2026-02-12 修复记录

### 问题1: JWT令牌过期时间太短
**状态**: ✅ 已修复

**解决方案**:
- 修改 `backend/config/config.php`
- 将 `expires_in` 从 3600秒(1小时) 改为 315360000秒(10年)
- 将 `refresh_expires_in` 从 86400秒(1天) 改为 315360000秒(10年)

### 问题2: 数据库字段缺失
**状态**: ✅ 已修复

**问题描述**:
- 服务器上的 `monitors` 表缺少 `description` 字段
- 错误信息: `Unknown column 'description' in 'field list'`

**解决方案**:
1. 创建了 `backend/api/fix-db.php` 修复脚本
2. 添加了 `description` 和 `url` 字段到 monitors 表
3. 访问 `https://jm.91wz.org/backend/api/fix-db.php` 执行修复

### 问题3: 批量添加功能
**状态**: ✅ 已修复

**修改内容**:
- 移除了单个添加功能，只保留批量添加
- 修复了 `phone` vs `phone_number` 字段名不一致问题
- 数据库表使用 `phone` 字段，代码已同步修改
- 更新了 `backend/api/monitors/index.php`
- 更新了 `backend/models/MonitorModel.php`

### 问题4: 刷新功能修复
**状态**: ✅ 已修复

**问题描述**:
- 单个刷新和刷新全部按钮报错"手机号不能为空"
- 后端路由无法正确处理 `index.php?id=X&action=refresh` 格式
- 后端路由无法正确处理 `index.php/refresh-all` 格式

**解决方案**:
1. 修改了 `backend/api/monitors/index.php`
2. 添加了对 `action=refresh` 和 `id` 参数的处理
3. 改进了路由逻辑，支持多种URL格式：
   - `index.php?path=xxx` (通过path参数)
   - `index.php/xxx` (通过PATH_INFO)
   - 从REQUEST_URI解析路径
4. 添加了 `refreshMonitor()` 函数处理单个刷新
5. 引入了 `CodeModel` 来获取最新验证码

### 问题5: 状态显示为"未知"
**状态**: ✅ 已修复

**问题描述**:
- 监控项状态显示为"未知"和"需要检查"
- 后端创建监控时使用 `'status' => 'active'`
- 但前端期望的状态是：'success', 'error', 'no-code', 'loading'
- 数据库中现有记录的状态为空字符串

**解决方案**:
1. 修改 `backend/api/monitors/index.php`
2. 将创建监控时的默认状态从 `'active'` 改为 `'no-code'`
3. 创建了 `backend/api/fix-status.php` 修复脚本
4. 将数据库中所有空状态更新为 `'no-code'`
5. 访问 `https://jm.91wz.org/backend/api/fix-status.php` 执行修复

### 问题6: SSH密钥登录
**状态**: ✅ 已设置

**配置信息**:
- 本地密钥位置: `C:\Users\Administrator\.ssh\id_rsa`
- 服务器公钥位置: `/root/.ssh/authorized_keys`
- 以后SSH/SCP无需输入密码

### 问题7: API URL为空
**状态**: ✅ 已修复

**问题描述**:
- 添加监控时，API URL没有保存到数据库
- 前端发送：`{ phone_number: phone, url: url }`
- 后端 `addMonitor()` 函数只保存了 `phone` 和 `status`，没有保存 `url`

**解决方案**:
1. 修改 `backend/api/monitors/index.php`
2. 在 `addMonitor()` 函数中，从 `$data` 获取 `url` 并保存到数据库
3. 代码变更：
```php
$monitorData = [
    'user_id' => $payload['user_id'],
    'phone' => $data['phone_number'],
    'status' => 'no-code'
];

// 保存API URL（如果提供了）
if (isset($data['url']) && !empty($data['url'])) {
    $monitorData['url'] = $data['url'];
}

$monitorId = $monitorModel->createMonitor($monitorData);
```

### 问题8: 管理员页面状态显示为"停止"
**状态**: ✅ 已修复

**问题描述**:
- 管理员后台页面将 `no-code` 状态映射为"停止"
- 数据库状态：`no-code`
- 用户中心页面：显示"无验证码" ✓
- 管理员后台页面：显示"停止" ✗

**解决方案**:
1. 修改 `admin.html`
2. 添加 `getStatusBadge()` 函数，统一状态显示：
```javascript
function getStatusBadge(status) {
    const statusMap = {
        'active': { text: '正常', class: 'badge-success' },
        'success': { text: '有验证码', class: 'badge-success' },
        'error': { text: '请求错误', class: 'badge-danger' },
        'no-code': { text: '无验证码', class: 'badge-warning' },
        'loading': { text: '加载中', class: 'badge-info' }
    };
    
    const config = statusMap[status] || { text: '停止', class: 'badge-secondary' };
    
    return `<span class="badge ${config.class}">
        <span class="badge-dot"></span>
        ${config.text}
    </span>`;
}
```
3. 在监控列表渲染时使用 `${getStatusBadge(m.status)}`

### 问题9: 管理员批量添加权限错误
**状态**: ✅ 已修复

**问题描述**:
- 管理员在批量添加手机号页面显示"无管理员权限"错误
- 后端 `backend/api/bulk/index.php` 检查的是 `$user['role'] !== 'admin'`
- 但数据库中使用的是 `is_admin` 字段（值为1表示管理员）

**解决方案**:
1. 修改 `backend/api/bulk/index.php`
2. 将权限检查从 `$user['role'] !== 'admin'` 改为 `empty($user['is_admin'])`
3. 代码变更：
```php
// 检查用户角色（使用 is_admin 字段）
$user = $userModel->getUserById($payload['user_id']);
if (!$user || empty($user['is_admin'])) {
    $response->error('无管理员权限', 403);
    return false;
}
```

### 问题10: 管理员批量添加功能权限调整
**状态**: ✅ 已调整

**问题描述**:
- 根据需求，管理员不需要添加手机号功能
- 管理员只能查看和管理所有用户的手机号和验证码
- 普通用户才能添加手机号

**解决方案**:
1. 修改 `admin.html` 的批量添加手机号页面
2. 将输入表单替换为权限说明提示
3. 提示内容：
   - 管理员账号只能**查看和管理**所有用户的手机号和验证码
   - 不能添加新的手机号
   - 如需添加手机号，请使用**普通用户账号**登录后操作

## 快速命令

```powershell
# SSH连接
C:\Windows\System32\OpenSSH\ssh.exe -p 1022 root@134.185.111.25

# 上传文件
C:\Windows\System32\OpenSSH\scp.exe -P 1022 <本地文件> root@134.185.111.25:<远程路径>

# 数据库修复
访问: https://jm.91wz.org/backend/api/fix-db.php

# 状态修复
访问: https://jm.91wz.org/backend/api/fix-status.php

# 查看用户列表
访问: https://jm.91wz.org/backend/api/check-users.php

# 查看管理员列表
访问: https://jm.91wz.org/backend/api/check-admin.php

# 查看监控列表
访问: https://jm.91wz.org/backend/api/check-monitors.php
```

## 测试数据

香港手机号测试数据:
```
+85255614436|https://sms.91wz.org/api/record?token=gvq3z4p2jvn3n6oy6nzu98rlmz63kh6h824
+85260746739|https://sms.91wz.org/api/record?token=ja6kvrkrg99uns4gd9s8zfi9sgt4i35k4rf
+85291249934|https://sms.91wz.org/api/record?token=hdihi1o1vl1nqyizxoorx2voz1ed69mrhaj
+85253575088|https://sms.91wz.org/api/record?token=fh4wsw6ygidiou22r1c4pp89f6sglr53td4
```

## 功能测试结果

### ✅ 管理员功能测试
- ✅ 管理员登录 - 通过 (2426366814 / ck123456@)
- ✅ 控制面板 - 正常显示系统概览
- ✅ 用户管理 - 显示所有用户列表
- ✅ 添加用户 - 成功添加新用户 testnewuser
- ✅ 编辑用户 - 弹窗正常显示
- ✅ 监控管理 - 显示所有监控项
- ✅ API URL显示 - 正确显示URL
- ✅ 状态显示 - 显示"无验证码"
- ✅ 批量添加手机号页面 - 显示权限说明（管理员不能添加）

### ✅ 普通用户功能测试
- ✅ 普通用户登录 - 通过 (cccp / ck123456@)
- ✅ 添加监控 - 成功添加香港手机号
- ✅ 刷新全部 - 正常工作
- ✅ 状态显示 - 显示"无验证码"
- ✅ API URL保存 - 正确保存到数据库

### ✅ 系统功能
- ✅ 批量添加香港手机号 - 通过
- ✅ 刷新全部监控项 - 通过
- ✅ 状态显示为"无验证码" - 通过 (之前显示"未知")
- ✅ JWT令牌10年有效期 - 已设置
- ✅ SSH密钥登录 - 已配置
- ✅ 自动刷新功能 - 正常工作（无401错误）
- ✅ API URL保存 - 已修复
- ✅ 管理员权限检查 - 已修复

## 可用账号

### 管理员
- **2426366814** / `ck123456@` - 系统管理员
  - 权限：查看所有用户、查看所有监控、管理用户、系统设置
  - 不能：添加手机号

### 普通用户
- **cccp** / `ck123456@` - 普通用户
- **testnewuser** / `testpass123` - 普通用户（刚创建）
- **testuser2026** / `ck123456@` - 普通用户
  - 权限：添加手机号、查看自己的监控、接收验证码

## 已知问题

### 小错误（不影响主要功能）
- 控制台偶尔显示 `Cannot read properties of null (reading 'focus')` JavaScript错误
- 这是前端焦点管理的小问题，不影响功能使用

## 注意事项

1. ✅ 所有修复已完成并上传到服务器
2. ✅ 数据库已修复
3. ✅ 批量添加功能测试通过
4. ✅ 刷新功能测试通过
5. ✅ 状态显示已修复
6. ✅ SSH密钥登录已配置
7. ✅ JWT令牌已设置为10年有效期
8. ✅ 管理员功能全部正常
9. ✅ 普通用户功能全部正常
10. ✅ API URL保存已修复
11. ✅ 管理员页面状态显示已修复
12. ✅ 管理员权限检查已修复
13. ✅ 管理员批量添加功能已禁用（显示权限说明）
