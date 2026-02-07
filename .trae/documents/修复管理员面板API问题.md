## 修复计划

### 问题1：API端点不匹配
**修复内容**：
- 修改 admin.html 中的API调用URL
- 将 `/backend/api/users/index.php` 改为 `/backend/api/admin/index.php/users`
- 将 `/backend/api/api-keys/index.php` 改为 `/backend/api/admin/index.php/api-keys`

### 问题2：后端权限验证
**修复内容**：
- 修改 `/backend/api/admin/index.php` 中的 `verifyAdmin` 函数
- 从检查 `role === 'admin'` 改为检查 `is_admin === 1`

### 问题3：创建缺失的API端点
**修复内容**：
- 在 `/backend/api/admin/index.php` 中添加 `/api-keys` 路由
- 在 `/backend/api/admin/index.php` 中添加 `/codes` 统计路由
- 添加相应的处理函数

### 问题4：前端统计接口
**修复内容**：
- 修改前端统计数据的API调用
- 统一使用 `/backend/api/admin/index.php` 端点

### 测试计划
1. 测试管理员登录
2. 测试用户列表加载
3. 测试监控列表加载
4. 测试API密钥管理
5. 测试验证码统计
6. 验证所有功能正常工作