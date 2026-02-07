# 手动部署指南

## 问题说明

aaPanel API 的文件编辑功能存在bug，无法通过API自动部署修复后的文件。需要手动将修复后的文件上传到服务器。

## 需要修复的文件

### 1. UserModel.php (最重要)
**服务器路径**: `/www/wwwroot/verify-code-monitor.local/backend/models/UserModel.php`

**本地文件**: `e:\ai本地应用\多用户接码\backend\models\UserModel.php`

**问题**: 服务器上此文件为空或不存在，导致 `Class 'UserModel' not found` 错误

**修复内容**: 添加了以下方法：
- `getUserByUsername()` - 根据用户名获取用户
- `getUserByEmail()` - 根据邮箱获取用户
- `getUserById()` - 根据ID获取用户
- `createUser()` - 创建用户
- `updateUser()` - 更新用户
- `deleteUser()` - 删除用户
- `getUsers()` - 获取用户列表
- `getUserCount()` - 获取用户总数

### 2. BaseModel.php
**服务器路径**: `/www/wwwroot/verify-code-monitor.local/backend/models/BaseModel.php`

**本地文件**: `e:\ai本地应用\多用户接码\backend\models\BaseModel.php`

**问题**: 缺少 `require_once __DIR__ . '/../utils/Database.php';`

**修复内容**: 在文件开头添加 Database.php 的引用

---

## 部署方法

### 方法1: 宝塔面板文件管理器 (推荐)

1. **登录宝塔面板**
   - 打开浏览器访问: `https://192.168.7.178:777`
   - 输入用户名和密码登录

2. **进入文件管理器**
   - 点击左侧菜单的"文件"

3. **导航到目标目录**
   - 点击 `www` → `wwwroot` → `verify-code-monitor.local` → `backend` → `models`

4. **编辑 UserModel.php**
   - 找到 `UserModel.php` 文件
   - 如果文件不存在，点击"新建文件"创建
   - 如果文件存在但为空，点击"编辑"
   - 将本地 `backend/models/UserModel.php` 的内容复制粘贴到编辑器中
   - 点击"保存"

5. **编辑 BaseModel.php**
   - 找到 `BaseModel.php` 文件
   - 点击"编辑"
   - 在 `<?php` 后的注释下面添加：
   ```php
   require_once __DIR__ . '/../utils/Database.php';
   ```
   - 点击"保存"

### 方法2: 宝塔终端 + 命令行

1. **登录宝塔面板**
   - 打开浏览器访问: `https://192.168.7.178:777`

2. **打开终端**
   - 点击左侧菜单的"终端"

3. **创建/编辑 UserModel.php**
   ```bash
   cd /www/wwwroot/verify-code-monitor.local/backend/models/
   
   # 使用 nano 编辑
   nano UserModel.php
   
   # 或者使用 vim
   vim UserModel.php
   ```

4. **粘贴内容**
   - 将本地 `backend/models/UserModel.php` 的内容复制
   - 在终端中粘贴（右键点击或按 Shift+Insert）
   - 保存并退出

5. **编辑 BaseModel.php**
   ```bash
   nano BaseModel.php
   ```
   - 在 `<?php` 后的注释下面添加：
   ```php
   require_once __DIR__ . '/../utils/Database.php';
   ```
   - 保存并退出

### 方法3: SFTP/SCP 上传

如果您有 SFTP 客户端（如 FileZilla、WinSCP）：

1. **连接服务器**
   - 主机: `192.168.7.178`
   - 端口: `22`
   - 用户名: `root`
   - 密码: 您的root密码

2. **导航到目录**
   - 远程路径: `/www/wwwroot/verify-code-monitor.local/backend/models/`

3. **上传文件**
   - 将本地的 `UserModel.php` 上传到服务器
   - 编辑 `BaseModel.php`，添加 `require_once` 语句

---

## 验证部署

部署完成后，测试API是否正常工作：

```bash
# 测试用户注册
curl -X POST http://192.168.7.178:8080/backend/api/auth/index.php/register \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","email":"test@example.com","password":"123456"}'

# 测试用户登录
curl -X POST http://192.168.7.178:8080/backend/api/auth/index.php/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"123456"}'
```

如果返回JSON格式的成功响应（`{"code":200,...}`），说明部署成功。

---

## 修复后的 UserModel.php 完整内容

```php
<?php
/**
 * 用户模型类
 * 用于处理用户相关的数据操作
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/../utils/Database.php';

class UserModel extends BaseModel {
    protected $table = 'users';

    /**
     * 根据用户名或邮箱获取用户
     * @param string $identifier
     * @return array|null
     */
    public function getByUsernameOrEmail($identifier) {
        $sql = "SELECT * FROM {$this->table} WHERE username = ? OR email = ?";
        return $this->db->fetchOne($sql, [$identifier, $identifier]);
    }

    /**
     * 根据用户名获取用户
     * @param string $username
     * @return array|null
     */
    public function getByUsername($username) {
        $sql = "SELECT * FROM {$this->table} WHERE username = ?";
        return $this->db->fetchOne($sql, [$username]);
    }

    /**
     * 根据用户名获取用户（别名）
     * @param string $username
     * @return array|null
     */
    public function getUserByUsername($username) {
        return $this->getByUsername($username);
    }

    /**
     * 根据邮箱获取用户
     * @param string $email
     * @return array|null
     */
    public function getByEmail($email) {
        $sql = "SELECT * FROM {$this->table} WHERE email = ?";
        return $this->db->fetchOne($sql, [$email]);
    }

    /**
     * 根据邮箱获取用户（别名）
     * @param string $email
     * @return array|null
     */
    public function getUserByEmail($email) {
        return $this->getByEmail($email);
    }

    /**
     * 根据ID获取用户（别名）
     * @param int $id
     * @return array|null
     */
    public function getUserById($id) {
        return $this->getById($id);
    }

    /**
     * 创建用户（别名）
     * @param array $data
     * @return int
     */
    public function createUser($data) {
        return $this->insert($data);
    }

    /**
     * 更新用户（别名）
     * @param int $userId
     * @param array $data
     * @return int
     */
    public function updateUser($userId, $data) {
        return $this->update($data, ['id' => $userId]);
    }

    /**
     * 删除用户（别名）
     * @param int $userId
     * @return int
     */
    public function deleteUser($userId) {
        return $this->delete(['id' => $userId]);
    }

    /**
     * 更新用户最后登录时间
     * @param int $userId
     * @return int
     */
    public function updateLastLogin($userId) {
        $data = ['last_login' => date('Y-m-d H:i:s')];
        $where = ['id' => $userId];
        return $this->update($data, $where);
    }

    /**
     * 获取用户列表（别名）
     * @param string $status
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getUsers($status = '', $limit = 10, $offset = 0) {
        $params = [
            'status' => $status,
            'limit' => $limit,
            'page' => ($offset / $limit) + 1
        ];
        return $this->getUserList($params);
    }

    /**
     * 获取用户总数（别名）
     * @param string $status
     * @return int
     */
    public function getUserCount($status = '') {
        $params = ['status' => $status];
        return $this->getUserListCount($params);
    }

    /**
     * 获取用户列表
     * @param array $params
     * @return array
     */
    public function getUserList($params) {
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $limit = isset($params['limit']) ? (int)$params['limit'] : 10;
        $offset = ($page - 1) * $limit;

        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $sqlParams = [];

        if (!empty($params['status'])) {
            $sql .= " AND status = ?";
            $sqlParams[] = $params['status'];
        }

        $sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
        $sqlParams[] = $limit;
        $sqlParams[] = $offset;

        return $this->db->fetchAll($sql, $sqlParams);
    }

    /**
     * 获取用户列表总数
     * @param array $params
     * @return int
     */
    public function getUserListCount($params) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE 1=1";
        $sqlParams = [];

        if (!empty($params['status'])) {
            $sql .= " AND status = ?";
            $sqlParams[] = $params['status'];
        }

        $result = $this->db->fetchOne($sql, $sqlParams);
        return $result ? (int)$result['count'] : 0;
    }
}
```

---

## 修复后的 BaseModel.php 修改

在文件开头添加一行：

```php
<?php
/**
 * 基础模型类
 * 所有模型的基类
 */

require_once __DIR__ . '/../utils/Database.php';  // <-- 添加这一行

class BaseModel {
    // ... 其余代码不变
}
```

---

## 部署后测试

部署完成后，运行以下测试：

```bash
# 1. 健康检查
curl "http://192.168.7.178:8080/backend/api/health/index.php?type=all"

# 2. 用户注册
curl -X POST http://192.168.7.178:8080/backend/api/auth/index.php/register \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","email":"test@example.com","password":"123456"}'

# 3. 用户登录
curl -X POST http://192.168.7.178:8080/backend/api/auth/index.php/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"123456"}'

# 4. 获取用户列表（需要管理员token）
curl http://192.168.7.178:8080/backend/api/admin/index.php/users \
  -H "Authorization: Bearer YOUR_TOKEN"
```
