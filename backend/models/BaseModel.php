<?php
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
