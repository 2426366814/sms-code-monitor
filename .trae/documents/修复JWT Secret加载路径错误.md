**问题分析**：
登录时出现"JWT secret must be set through environment variable"错误，原因是`.env`文件路径配置错误。

**根本原因**：
在`config.php`中，`.env`文件加载路径为`__DIR__ . '/../../.env'`，但`__DIR__`是`backend/config/`，所以实际路径是`backend/.env`，而`.env`文件实际位于项目根目录。

**修复方案**：

1. 修改`config.php`中`.env`文件的加载路径
2. 确保所有环境变量能正确加载

**修复文件**：

* `backend/config/config.php`：修改`.env`文件路径

**修复内容**：

* 将第7行的`EnvUtil::load(__DIR__ . '/../../.env');`改为`EnvUtil::load(__DIR__ . '/../../../.env');`

* 这样路径就会从`backend/config/../../../.env`正确指向项目根目录的`.env`文件

**预期效果**：

* JWT Secret能正确从`.env`文件加载

* 登录功能恢复正常

* 所有依赖环境变量的功能正常工作

