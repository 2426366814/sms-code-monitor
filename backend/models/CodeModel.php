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
        
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ?";
        $sqlParams = [$userId];
        
        if (!empty($params['status'])) {
            $sql .= " AND status = ?";
            $sqlParams[] = $params['status'];
        }
        
        if (!empty($params['monitor_id'])) {
            $sql .= " AND monitor_id = ?";
            $sqlParams[] = $params['monitor_id'];
        }
        
        $sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
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
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE user_id = ?";
        $sqlParams = [$userId];
        
        if (!empty($params['status'])) {
            $sql .= " AND status = ?";
            $sqlParams[] = $params['status'];
        }
        
        if (!empty($params['monitor_id'])) {
            $sql .= " AND monitor_id = ?";
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
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        $params = [$id];
        
        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        return $this->db->fetchOne($sql, $params);
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
        $where = ['id' => $id];
        
        if ($userId !== null) {
            $where['user_id'] = $userId;
        }
        
        return $this->update($data, $where);
    }
    
    /**
     * 删除验证码
     * @param int $id
     * @param int $userId
     * @return int
     */
    public function deleteCode($id, $userId = null) {
        $where = ['id' => $id];
        
        if ($userId !== null) {
            $where['user_id'] = $userId;
        }
        
        return $this->delete($where);
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
