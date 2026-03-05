<?php
require_once __DIR__ . '/BaseModel.php';

class AccessCodeModel extends BaseModel {
    public function __construct() {
        parent::__construct();
        $this->setTable('access_codes');
    }

    public function getByCode($code) {
        $sql = "SELECT * FROM {$this->table} WHERE code = ?";
        return $this->db->fetchOne($sql, [$code]);
    }

    public function createCode($code, $type = 'single', $maxUses = 1, $expiresAt = null) {
        return $this->insert([
            'code' => $code,
            'type' => $type,
            'max_uses' => $maxUses,
            'used_count' => 0,
            'expires_at' => $expiresAt,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function useCode($code) {
        $sql = "UPDATE {$this->table} SET used_count = used_count + 1 WHERE code = ?";
        return $this->db->query($sql, [$code]);
    }

    public function isValid($code) {
        $accessCode = $this->getByCode($code);
        if (!$accessCode) return false;
        if (!$accessCode['is_active']) return false;
        if ($accessCode['expires_at'] && strtotime($accessCode['expires_at']) < time()) return false;
        if ($accessCode['type'] === 'single' && $accessCode['used_count'] >= $accessCode['max_uses']) return false;
        return true;
    }
}
