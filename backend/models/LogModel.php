<?php
require_once __DIR__ . '/BaseModel.php';

class LogModel extends BaseModel {
    public function __construct() {
        parent::__construct();
        $this->setTable('logs');
    }

    public function log($userId, $action, $details = null, $ip = null) {
        return $this->insert([
            'user_id' => $userId,
            'action' => $action,
            'details' => $details ? json_encode($details) : null,
            'ip_address' => $ip,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function getByUserId($userId, $limit = 100) {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
        return $this->db->fetchAll($sql, [$userId, $limit]);
    }

    public function getRecentLogs($limit = 100) {
        $sql = "SELECT l.*, u.username FROM {$this->table} l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT ?";
        return $this->db->fetchAll($sql, [$limit]);
    }
}
