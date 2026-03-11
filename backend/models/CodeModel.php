<?php
/**
 * 验证码模型类
 * 用于处理验证码相关的数据操作
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/../utils/Database.php';

class CodeModel extends BaseModel {
    protected $table = 'codes';
    
    /**
     * 根据用户ID获取验证码列表
     * @param int $userId
     * @param array $params
     * @return array
     */
    public function getCodesByUserId($userId, $params = []) {
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $limit = isset($params['limit']) ? (int)$params['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT c.*, m.phone, m.url 
                FROM {$this->table} c 
                INNER JOIN monitors m ON c.monitor_id = m.id 
                WHERE m.user_id = ?";
        $sqlParams = [$userId];
        
        if (!empty($params['monitor_id'])) {
            $sql .= " AND c.monitor_id = ?";
            $sqlParams[] = $params['monitor_id'];
        }
        
        $sql .= " ORDER BY c.id DESC LIMIT ? OFFSET ?";
        $sqlParams[] = $limit;
        $sqlParams[] = $offset;
        
        return $this->db->fetchAll($sql, $sqlParams);
    }
    
    /**
     * 获取用户验证码总数
     * @param int $userId
     * @param array $params
     * @return int
     */
    public function getCodesCountByUserId($userId, $params = []) {
        $sql = "SELECT COUNT(*) as count 
                FROM {$this->table} c 
                INNER JOIN monitors m ON c.monitor_id = m.id 
                WHERE m.user_id = ?";
        $sqlParams = [$userId];
        
        if (!empty($params['monitor_id'])) {
            $sql .= " AND c.monitor_id = ?";
            $sqlParams[] = $params['monitor_id'];
        }
        
        $result = $this->db->fetchOne($sql, $sqlParams);
        return $result ? (int)$result['count'] : 0;
    }
    
    /**
     * 根据ID获取验证码
     * @param int $id
     * @param int $userId
     * @return array|null
     */
    public function getCodeById($id, $userId = null) {
        if ($userId !== null) {
            $sql = "SELECT c.* FROM {$this->table} c 
                    INNER JOIN monitors m ON c.monitor_id = m.id 
                    WHERE c.id = ? AND m.user_id = ?";
            return $this->db->fetchOne($sql, [$id, $userId]);
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * 创建验证码
     * @param array $data
     * @return int
     */
    public function createCode($data) {
        return $this->insert($data);
    }
    
    /**
     * 更新验证码
     * @param int $id
     * @param array $data
     * @param int $userId
     * @return int
     */
    public function updateCode($id, $data, $userId = null) {
        if ($userId !== null) {
            $sql = "UPDATE {$this->table} c 
                    INNER JOIN monitors m ON c.monitor_id = m.id 
                    SET ";
            $setParts = [];
            foreach ($data as $key => $value) {
                $setParts[] = "c.{$key} = ?";
            }
            $sql .= implode(', ', $setParts);
            $sql .= " WHERE c.id = ? AND m.user_id = ?";
            
            $params = array_values($data);
            $params[] = $id;
            $params[] = $userId;
            
            $stmt = $this->db->getPdo()->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        }
        
        return $this->update($data, ['id' => $id]);
    }
    
    /**
     * 删除验证码
     * @param int $id
     * @param int $userId
     * @return int
     */
    public function deleteCode($id, $userId = null) {
        if ($userId !== null) {
            $sql = "DELETE c FROM {$this->table} c 
                    INNER JOIN monitors m ON c.monitor_id = m.id 
                    WHERE c.id = ? AND m.user_id = ?";
            $stmt = $this->db->getPdo()->prepare($sql);
            $stmt->execute([$id, $userId]);
            return $stmt->rowCount();
        }
        
        return $this->delete(['id' => $id]);
    }
    
    /**
     * 根据监控项ID获取验证码
     * @param int $monitorId
     * @param int $limit
     * @return array
     */
    public function getCodesByMonitorId($monitorId, $limit = 10) {
        $sql = "SELECT * FROM {$this->table} WHERE monitor_id = ? ORDER BY id DESC LIMIT ?";
        return $this->db->fetchAll($sql, [$monitorId, $limit]);
    }
    
    /**
     * 获取最新的验证码
     * @param int $monitorId
     * @return array|null
     */
    public function getLatestCode($monitorId) {
        $sql = "SELECT * FROM {$this->table} WHERE monitor_id = ? ORDER BY id DESC LIMIT 1";
        return $this->db->fetchOne($sql, [$monitorId]);
    }
}
