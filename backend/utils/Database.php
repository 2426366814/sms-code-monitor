<?php
/**
 * 数据库工具类
 * 用于处理数据库连接和操作
 */

class Database {
    private static $instance = null;
    private $pdo;
    private $config;
    
    /**
     * 私有构造函数
     */
    private function __construct() {
        $this->config = require __DIR__ . '/../config/config.php';
        $this->connect();
    }
    
    /**
     * 获取数据库实例
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 连接数据库
     * @throws Exception
     */
    private function connect() {
        $dbConfig = $this->config['database'];
        
        try {
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->pdo = new PDO(
                $dsn,
                $dbConfig['username'],
                $dbConfig['password'],
                $options
            );
        } catch (PDOException $e) {
            throw new Exception('数据库连接失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取PDO实例
     * @return PDO
     */
    public function getPdo() {
        return $this->pdo;
    }
    
    /**
     * 执行查询
     * @param string $sql
     * @param array $params
     * @return PDOStatement
     */
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * 获取单行数据
     * @param string $sql
     * @param array $params
     * @return array|null
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * 获取多行数据
     * @param string $sql
     * @param array $params
     * @return array
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * 执行插入操作
     * @param string $table
     * @param array $data
     * @return int
     */
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        $fieldsStr = implode(', ', $fields);
        
        $sql = "INSERT INTO {$table} ({$fieldsStr}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }
    
    /**
     * 执行更新操作
     * @param string $table
     * @param array $data
     * @param array $where
     * @return int
     */
    public function update($table, $data, $where) {
        $setClause = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $setClause[] = "{$key} = :{$key}";
            $params[':' . $key] = $value;
        }
        
        $whereClause = [];
        foreach ($where as $key => $value) {
            $whereClause[] = "{$key} = :where_{$key}";
            $params[':where_' . $key] = $value;
        }
        
        $setClauseStr = implode(', ', $setClause);
        $whereClauseStr = implode(' AND ', $whereClause);
        
        $sql = "UPDATE {$table} SET {$setClauseStr} WHERE {$whereClauseStr}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }
    
    /**
     * 执行删除操作
     * @param string $table
     * @param array $where
     * @return int
     */
    public function delete($table, $where) {
        $whereClause = [];
        $params = [];
        
        foreach ($where as $key => $value) {
            $whereClause[] = "{$key} = :{$key}";
            $params[':' . $key] = $value;
        }
        
        $whereClauseStr = implode(' AND ', $whereClause);
        $sql = "DELETE FROM {$table} WHERE {$whereClauseStr}";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }
    
    /**
     * 开始事务
     */
    public function beginTransaction() {
        $this->pdo->beginTransaction();
    }
    
    /**
     * 提交事务
     */
    public function commit() {
        $this->pdo->commit();
    }
    
    /**
     * 回滚事务
     */
    public function rollBack() {
        $this->pdo->rollBack();
    }
    
    /**
     * 获取记录数
     * @param string $table
     * @param array $where
     * @return int
     */
    public function count($table, $where = []) {
        $whereClause = [];
        $params = [];
        
        if (!empty($where)) {
            foreach ($where as $key => $value) {
                $whereClause[] = "{$key} = :{$key}";
                $params[':' . $key] = $value;
            }
            $whereClauseStr = ' WHERE ' . implode(' AND ', $whereClause);
        } else {
            $whereClauseStr = '';
        }
        
        $sql = "SELECT COUNT(*) as count FROM {$table}{$whereClauseStr}";
        $result = $this->fetchOne($sql, $params);
        return $result['count'] ?? 0;
    }
    
    /**
     * 检查表是否存在
     * @param string $table
     * @return bool
     */
    public function tableExists($table) {
        try {
            $sql = "SHOW TABLES LIKE ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$table]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}
