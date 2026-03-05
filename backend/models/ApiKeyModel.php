<?php
require_once __DIR__ . '/BaseModel.php';

class ApiKeyModel extends BaseModel {
    public function __construct() {
        parent::__construct();
        $this->setTable('api_keys');
    }

    public function getByUserId($userId) {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ? ORDER BY created_at DESC";
        return $this->db->fetchAll($sql, [$userId]);
    }

    public function getByKey($apiKey) {
        $sql = "SELECT * FROM {$this->table} WHERE api_key = ?";
        return $this->db->fetchOne($sql, [$apiKey]);
    }

    public function createKey($userId, $keyName, $apiKey) {
        return $this->insert([
            'user_id' => $userId,
            'key_name' => $keyName,
            'api_key' => $apiKey,
            'created_at' => date('Y-m-d H:i:s'),
            'last_used' => null,
            'is_active' => 1
        ]);
    }

    public function deactivateKey($keyId, $userId) {
        return $this->update(['is_active' => 0], ['id' => $keyId, 'user_id' => $userId]);
    }

    public function updateLastUsed($apiKey) {
        return $this->update(['last_used' => date('Y-m-d H:i:s')], ['api_key' => $apiKey]);
    }
}
