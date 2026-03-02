## 问题分析

### 问题1：API URL 为空
**原因**：后端代码在创建监控时没有保存 `url` 字段
- 前端发送：`{ phone_number: phone, url: url }`
- 后端 `addMonitor()` 和 `batchAddMonitors()` 函数只保存了 `phone` 和 `status`，没有保存 `url`

### 问题2：状态显示为"停止"
**原因**：管理员后台页面将 `no-code` 状态映射为"停止"
- 数据库状态：`no-code`
- 用户中心页面：显示"无验证码" ✓
- 管理员后台页面：显示"停止" ✗

## 修复方案

### 修复1：保存API URL
修改 `backend/api/monitors/index.php`：
1. 在 `addMonitor()` 函数中，从 `$data` 获取 `url` 并保存
2. 在 `batchAddMonitors()` 函数中，从 `$data` 获取 `url` 并保存

### 修复2：统一状态显示
修改 `admin.html`：
1. 将 `no-code` 状态的显示从"停止"改为"无验证码"
2. 确保与用户中心页面的显示一致

### 修复3：修复现有数据（可选）
创建修复脚本：
1. 对于没有URL的监控项，可以手动补充或删除重建

## 实施步骤
1. 修改后端API保存URL
2. 修改管理员前端状态显示
3. 上传到服务器
4. 测试验证