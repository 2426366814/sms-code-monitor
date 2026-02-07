#!/usr/bin/env python3
"""
部署脚本 - 使用 aaPanel API 更新服务器配置
"""

import sys
import json
sys.path.append('C:/Users/Administrator/.trae-cn/skills/aapanel-api-skill')

from aapanel_client import AAPanelClient

# 配置
PANEL_URL = "https://192.168.7.178:777"
API_KEY = "IgCkCDbflE3Fpvir1rFmm5t8leW9Whnd"
WEB_ROOT = "/www/wwwroot/verify-code-monitor.local"

# 初始化客户端
client = AAPanelClient(PANEL_URL, API_KEY)

# 新的 Nginx 配置
NGINX_CONFIG = '''server
{
    listen 8080;
    server_name verify-code-monitor.local;
    index index.php index.html index.htm default.php default.htm default.html;
    root /www/wwwroot/verify-code-monitor.local;

    error_page 404 /404.html;
    error_page 502 /502.html;

    # 前端页面路由
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Backend API - PHP文件处理（使用正则匹配，优先级更高）
    location ~ ^/backend/.*\\.php(/|$) {
        fastcgi_pass unix:/tmp/php-cgi-74.sock;
        fastcgi_index index.php;
        include fastcgi.conf;
        include pathinfo.conf;
    }

    # Backend API - 目录访问
    location /backend/ {
        try_files $uri $uri/ =404;
    }

    #PHP-INFO-START
    include enable-php-74.conf;
    #PHP-INFO-END

    #禁止访问的文件或目录
    location ~ ^/(\\.user.ini|\\.htaccess|\\.git|\\.env|\\.svn|\\.project|LICENSE|README.md)     
    {
        return 404;
    }

    location ~ \\.well-known{
        allow all;
    }

    if ( $uri ~ "^/\\.well-known/.*\\.(php|jsp|py|js|css|lua|ts|go|zip|tar\\.gz|rar|7z|sql|bak)$" ) {
        return 403;
    }

    location ~ .*\\.(gif|jpg|jpeg|png|bmp|swf)$
    {
        expires      30d;
        error_log /dev/null;
        access_log /dev/null;
    }

    location ~ .*\\.(js|css)?$
    {
        expires      12h;
        error_log /dev/null;
        access_log /dev/null;
    }
    access_log  /www/wwwlogs/verify-code-monitor.local.log;
    error_log  /www/wwwlogs/verify-code-monitor.local.error.log;
}
'''

# BaseModel.php 修复内容
BASEMODEL_CONTENT = '''<?php
/**
 * 基础模型类
 * 所有模型的基类
 */

require_once __DIR__ . '/../utils/Database.php';

class BaseModel {
    protected $db;
    protected $table;
    
    /**
     * 构造函数
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 获取数据库实例
     * @return Database
     */
    protected function getDb() {
        return $this->db;
    }
    
    /**
     * 获取表名
     * @return string
     */
    protected function getTable() {
        return $this->table;
    }
    
    /**
     * 根据ID获取记录
     * @param int $id
     * @return array|null
     */
    public function getById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * 获取所有记录
     * @param array $where
     * @param array $order
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAll($where = [], $order = [], $limit = null, $offset = null) {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        // 构建WHERE子句
        if (!empty($where)) {
            $whereClause = [];
            foreach ($where as $key => $value) {
                $whereClause[] = "{$key} = ?";
                $params[] = $value;
            }
            $sql .= ' WHERE ' . implode(' AND ', $whereClause);
        }
        
        // 构建ORDER BY子句
        if (!empty($order)) {
            $orderClause = [];
            foreach ($order as $field => $direction) {
                $orderClause[] = "{$field} {$direction}";
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderClause);
        }
        
        // 构建LIMIT子句
        if ($limit !== null) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
            
            if ($offset !== null) {
                $sql .= " OFFSET ?";
                $params[] = $offset;
            }
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * 插入记录
     * @param array $data
     * @return int
     */
    public function insert($data) {
        return $this->db->insert($this->table, $data);
    }
    
    /**
     * 更新记录
     * @param array $data
     * @param array $where
     * @return int
     */
    public function update($data, $where) {
        return $this->db->update($this->table, $data, $where);
    }
    
    /**
     * 删除记录
     * @param array $where
     * @return int
     */
    public function delete($where) {
        return $this->db->delete($this->table, $where);
    }
    
    /**
     * 获取记录数
     * @param array $where
     * @return int
     */
    public function count($where = []) {
        return $this->db->count($this->table, $where);
    }
    
    /**
     * 检查表是否存在
     * @return bool
     */
    public function tableExists() {
        return $this->db->tableExists($this->table);
    }
}
'''

def update_nginx_config():
    """更新 Nginx 配置"""
    print("=" * 60)
    print("步骤 1: 更新 Nginx 配置")
    print("=" * 60)
    
    # 备份原配置
    backup_cmd = f"cp /www/server/panel/vhost/nginx/verify-code-monitor.local.conf /www/server/panel/vhost/nginx/verify-code-monitor.local.conf.bak"
    result = client.exec_shell(backup_cmd)
    print(f"✓ 备份原配置: {result.get('msg', '成功')}")
    
    # 写入新配置
    config_path = "/www/server/panel/vhost/nginx/verify-code-monitor.local.conf"
    write_cmd = f"cat > {config_path} << 'EOF'\n{NGINX_CONFIG}\nEOF"
    result = client.exec_shell(write_cmd)
    print(f"✓ 写入新配置: {result.get('msg', '成功')}")
    
    # 测试配置
    result = client.exec_shell("nginx -t")
    print(f"✓ 测试配置: {result.get('msg', '成功')}")
    
    # 重载 Nginx
    result = client.exec_shell("/etc/init.d/nginx reload")
    print(f"✓ 重载 Nginx: {result.get('msg', '成功')}")
    
    print()

def update_php_files():
    """更新 PHP 文件"""
    print("=" * 60)
    print("步骤 2: 更新 PHP 文件")
    print("=" * 60)
    
    # 更新 BaseModel.php
    base_model_path = f"{WEB_ROOT}/backend/models/BaseModel.php"
    write_cmd = f"cat > {base_model_path} << 'EOF'\n{BASEMODEL_CONTENT}\nEOF"
    result = client.exec_shell(write_cmd)
    print(f"✓ 更新 BaseModel.php: {result.get('msg', '成功')}")
    
    # 修复 admin/index.php
    admin_file = f"{WEB_ROOT}/backend/api/admin/index.php"
    fix_cmd = f"sed -i 's/new Database(\$config\[.database.\])/Database::getInstance()/g' {admin_file}"
    result = client.exec_shell(fix_cmd)
    fix_cmd2 = f"sed -i 's/new UserModel(\$database)/new UserModel()/g' {admin_file}"
    result = client.exec_shell(fix_cmd2)
    print(f"✓ 修复 admin/index.php")
    
    # 修复 codes/index.php
    codes_file = f"{WEB_ROOT}/backend/api/codes/index.php"
    fix_cmd = f"sed -i 's/new Database(\$config\[.database.\])/Database::getInstance()/g' {codes_file}"
    result = client.exec_shell(fix_cmd)
    fix_cmd2 = f"sed -i 's/new MonitorModel(\$database)/new MonitorModel()/g' {codes_file}"
    result = client.exec_shell(fix_cmd2)
    fix_cmd3 = f"sed -i 's/new UserModel(\$database)/new UserModel()/g' {codes_file}"
    result = client.exec_shell(fix_cmd3)
    print(f"✓ 修复 codes/index.php")
    
    # 修复 monitors/index.php
    monitors_file = f"{WEB_ROOT}/backend/api/monitors/index.php"
    fix_cmd = f"sed -i 's/new Database(\$config\[.database.\])/Database::getInstance()/g' {monitors_file}"
    result = client.exec_shell(fix_cmd)
    fix_cmd2 = f"sed -i 's/new MonitorModel(\$database)/new MonitorModel()/g' {monitors_file}"
    result = client.exec_shell(fix_cmd2)
    fix_cmd3 = f"sed -i 's/new UserModel(\$database)/new UserModel()/g' {monitors_file}"
    result = client.exec_shell(fix_cmd3)
    print(f"✓ 修复 monitors/index.php")
    
    print()

def verify_deployment():
    """验证部署"""
    print("=" * 60)
    print("步骤 3: 验证部署")
    print("=" * 60)
    
    # 检查后端 API
    result = client.exec_shell(f"curl -s http://127.0.0.1:8080/backend/api/auth/index.php")
    print(f"✓ 测试后端 API:")
    print(f"  响应: {result.get('msg', '无响应')[:200]}...")
    
    # 检查文件是否存在
    result = client.exec_shell(f"ls -la {WEB_ROOT}/backend/api/auth/")
    print(f"✓ 检查文件:")
    print(f"  {result.get('msg', '')}")
    
    print()

def main():
    """主函数"""
    print("\n" + "=" * 60)
    print("  多用户动态验证码监控系统 - 自动部署脚本")
    print("=" * 60 + "\n")
    
    try:
        # 更新 Nginx 配置
        update_nginx_config()
        
        # 更新 PHP 文件
        update_php_files()
        
        # 验证部署
        verify_deployment()
        
        print("=" * 60)
        print("✓ 部署完成！")
        print("=" * 60)
        print("\n请访问: http://192.168.7.178:8080")
        print("测试 API: http://192.168.7.178:8080/backend/api/auth/index.php")
        
    except Exception as e:
        print(f"\n✗ 部署失败: {e}")
        import traceback
        traceback.print_exc()

if __name__ == "__main__":
    main()
