<?php
require_once __DIR__ . '/BaseModel.php';

class StatModel extends BaseModel {
    public function __construct() {
        parent::__construct();
        $this->setTable('statistics');
    }

    public function record($userId, $type, $value = 1) {
        $today = date('Y-m-d');
        $sql = "INSERT INTO {$this->table} (user_id, type, value, date) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE value = value + ?";
        return $this->db->query($sql, [$userId, $type, $value, $today, $value]);
    }

    public function getUserStats($userId, $days = 30) {
        $sql = "SELECT type, SUM(value) as total, date FROM {$this->table} 
                WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY type, date ORDER BY date DESC";
        return $this->db->fetchAll($sql, [$userId, $days]);
    }

    public function getGlobalStats($days = 30) {
        $sql = "SELECT type, SUM(value) as total, date FROM {$this->table}
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY type, date ORDER BY date DESC";
        return $this->db->fetchAll($sql, [$days]);
    }
}
