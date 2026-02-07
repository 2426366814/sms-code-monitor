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
        $sql = "SELECT * FROM {$this->table}";
        $where = [];
        $bindParams = [];
        
        if (!empty($params['status'])) {
            $where[] = "status = ?";
            $bindParams[] = $params['status'];
        }
        
        if (!empty($params['search'])) {
            $where[] = "(username LIKE ? OR email LIKE ?)";
            $bindParams[] = '%' . $params['search'] . '%';
            $bindParams[] = '%' . $params['search'] . '%';
        }
        
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        if (!empty($params['page']) && !empty($params['limit'])) {
            $offset = ($params['page'] - 1) * $params['limit'];
            $sql .= " LIMIT ? OFFSET ?";
            $bindParams[] = $params['limit'];
            $bindParams[] = $offset;
        }
        
        return $this->db->fetchAll($sql, $bindParams);
    }
    
    /**
     * 获取用户列表总数
     * @param array $params
     * @return int
     */
    public function getUserListCount($params) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $where = [];
        $bindParams = [];
        
        if (!empty($params['status'])) {
            $where[] = "status = ?";
            $bindParams[] = $params['status'];
        }
        
        if (!empty($params['search'])) {
            $where[] = "(username LIKE ? OR email LIKE ?)";
            $bindParams[] = '%' . $params['search'] . '%';
            $bindParams[] = '%' . $params['search'] . '%';
        }
        
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        
        $result = $this->db->fetchOne($sql, $bindParams);
        return $result['count'] ?? 0;
    }
}
