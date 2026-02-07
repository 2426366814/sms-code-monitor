<?php
/**
 * 监控项模型类
 * 用于处理监控项相关的数据操作
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/../utils/Database.php';

class MonitorModel extends BaseModel {
    protected $table = 'monitors';
    
    /**
     * 根据用户ID获取监控项列表
     * @param int $userId
     * @param array $params
     * @return array
     */
    public function getMonitorsByUserId($userId, $params = []) {
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $limit = isset($params['limit']) ? (int)$params['limit'] : 10;
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ?";
        $sqlParams = [$userId];
        
        if (!empty($params['status'])) {
            $sql .= " AND status = ?";
            $sqlParams[] = $params['status'];
        }
        
        if (!empty($params['keyword'])) {
            $sql .= " AND (name LIKE ? OR description LIKE ?)";
            $keyword = '%' . $params['keyword'] . '%';
            $sqlParams[] = $keyword;
            $sqlParams[] = $keyword;
        }
        
        $sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
        $sqlParams[] = $limit;
        $sqlParams[] = $offset;
        
        return $this->db->fetchAll($sql, $sqlParams);
    }
    
    /**
     * 获取用户监控项总数
     * @param int $userId
     * @param array $params
     * @return int
     */
    public function getMonitorsCountByUserId($userId, $params = []) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE user_id = ?";
        $sqlParams = [$userId];
        
        if (!empty($params['status'])) {
            $sql .= " AND status = ?";
            $sqlParams[] = $params['status'];
        }
        
        if (!empty($params['keyword'])) {
            $sql .= " AND (name LIKE ? OR description LIKE ?)";
            $keyword = '%' . $params['keyword'] . '%';
            $sqlParams[] = $keyword;
            $sqlParams[] = $keyword;
        }
        
        $result = $this->db->fetchOne($sql, $sqlParams);
        return $result ? (int)$result['count'] : 0;
    }
    
    /**
     * 根据ID获取监控项
     * @param int $id
     * @param int $userId
     * @return array|null
     */
    public function getMonitorById($id, $userId = null) {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        $params = [$id];
        
        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        return $this->db->fetchOne($sql, $params);
    }
    
    /**
     * 创建监控项
     * @param array $data
     * @return int
     */
    public function createMonitor($data) {
        return $this->insert($data);
    }
    
    /**
     * 更新监控项
     * @param int $id
     * @param array $data
     * @param int $userId
     * @return int
     */
    public function updateMonitor($id, $data, $userId = null) {
        $where = ['id' => $id];
        
        if ($userId !== null) {
            $where['user_id'] = $userId;
        }
        
        return $this->update($data, $where);
    }
    
    /**
     * 删除监控项
     * @param int $id
     * @param int $userId
     * @return int
     */
    public function deleteMonitor($id, $userId = null) {
        $where = ['id' => $id];
        
        if ($userId !== null) {
            $where['user_id'] = $userId;
        }
        
        return $this->delete($where);
    }
    
    /**
     * 更新监控项状态
     * @param int $id
     * @param string $status
     * @param int $userId
     * @return int
     */
    public function updateMonitorStatus($id, $status, $userId = null) {
        $data = ['status' => $status];
        return $this->updateMonitor($id, $data, $userId);
    }
    
    /**
     * 获取所有活跃的监控项
     * @return array
     */
    public function getActiveMonitors() {
        $sql = "SELECT * FROM {$this->table} WHERE status = 'active'";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * 获取用户监控项列表（别名）
     * @param int $userId
     * @param array $params
     * @return array
     */
    public function getUserMonitors($userId, $params = []) {
        return $this->getMonitorsByUserId($userId, $params);
    }
    
    /**
     * 获取用户监控项总数（别名）
     * @param int $userId
     * @param array $params
     * @return int
     */
    public function getUserMonitorsCount($userId, $params = []) {
        return $this->getMonitorsCountByUserId($userId, $params);
    }
}
