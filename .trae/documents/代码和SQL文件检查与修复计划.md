## 代码和SQL文件检查与修复计划

### 一、发现的问题

1. **SQL文件问题**：
   - 第198行的INSERT语句有重复，并且ON DUPLICATE KEY UPDATE语法有问题，应该只插入一次，然后在单独的语句中处理重复情况

2. **临时文件问题**：
   - 存在一些临时创建的文件需要清理：
     - `fix_login.php`
     - `verify_password.php`

3. **代码逻辑问题**：
   - 需要确保ApiKeyModel的自动创建表逻辑被正确调用

### 二、修复步骤

1. **修复SQL文件**：
   - 修改第198行的INSERT语句，移除重复的插入和有问题的ON DUPLICATE KEY UPDATE语法

2. **清理临时文件**：
   - 删除`fix_login.php`
   - 删除`verify_password.php`

3. **检查API密钥加载逻辑**：
   - 确保ApiKeyModel的自动创建表逻辑被正确调用

4. **打包系统文件**：
   - 打包所有必要的文件，生成最终的部署包

### 三、修复方案

1. **修复SQL文件**：
   ```sql
   -- 修改前
   INSERT INTO `users` (`username`, `email`, `password_hash`, `status`) VALUES
   ('admin', 'admin@example.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'active'),
   ('admin', 'admin@example.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'active') ON DUPLICATE KEY UPDATE password_hash='$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm';

   -- 修改后
   INSERT IGNORE INTO `users` (`username`, `email`, `password_hash`, `status`) VALUES
   ('admin', 'admin@example.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'active');
   
   -- 单独处理密码更新
   UPDATE `users` SET `password_hash` = '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm' WHERE `username` = 'admin';
   ```

2. **清理临时文件**：
   - 使用`DeleteFile`工具删除临时文件

3. **打包系统文件**：
   - 使用`tar.exe`重新打包系统文件，生成最终的部署包

### 四、预期效果

1. 修复后，SQL文件语法正确，没有重复插入和语法错误
2. 系统临时文件被清理，代码结构更清晰
3. API密钥加载逻辑正确，能够自动创建必要的表
4. 生成的部署包包含所有必要的文件，能够正常部署和运行