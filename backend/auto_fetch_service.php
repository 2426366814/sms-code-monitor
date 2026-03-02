<?php
/**
 * 自动获取验证码服务
 * 从配置的接码平台自动获取验证码
 * 
 * 使用方法: php auto_fetch_service.php
 * 建议使用cron定时执行: * * * * * php /path/to/auto_fetch_service.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/Database.php';

class AutoFetchService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
    }
    
    public function run() {
        echo "[" . date('Y-m-d H:i:s') . "] 开始自动获取验证码服务\n";
        
        $platforms = $this->getActivePlatforms();
        
        foreach ($platforms as $platform) {
            try {
                $this->fetchFromPlatform($platform);
            } catch (Exception $e) {
                echo "平台 {$platform['name']} 获取失败: " . $e->getMessage() . "\n";
            }
        }
        
        echo "[" . date('Y-m-d H:i:s') . "] 自动获取服务完成\n";
    }
    
    private function getActivePlatforms() {
        $stmt = $this->db->query("
            SELECT p.*, u.username 
            FROM sms_platforms p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.is_active = 1 AND p.auto_fetch = 1
            AND (
                p.last_fetch_at IS NULL 
                OR TIMESTAMPDIFF(SECOND, p.last_fetch_at, NOW()) >= p.fetch_interval
            )
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function fetchFromPlatform($platform) {
        echo "正在从 {$platform['name']} 获取验证码...\n";
        
        $platformType = $platform['platform_type'];
        
        switch ($platformType) {
            case 'yima':
                $this->fetchFromYima($platform);
                break;
            case 'jiecode':
                $this->fetchFromJiecode($platform);
                break;
            case 'custom':
                $this->fetchFromCustom($platform);
                break;
            default:
                echo "未知平台类型: {$platformType}\n";
        }
        
        $this->updateLastFetch($platform['id']);
    }
    
    private function fetchFromYima($platform) {
        $apiUrl = $platform['api_url'];
        $apiKey = $platform['api_key'];
        
        $url = rtrim($apiUrl, '/') . "/api/sms/getList?token={$apiKey}";
        
        $response = $this->httpGet($url);
        $data = json_decode($response, true);
        
        if ($data && isset($data['data'])) {
            foreach ($data['data'] as $sms) {
                $this->saveCode($platform['user_id'], [
                    'phone' => $sms['mobile'] ?? $sms['phone'] ?? '',
                    'code' => $this->extractCode($sms['content'] ?? $sms['sms'] ?? ''),
                    'content' => $sms['content'] ?? $sms['sms'] ?? '',
                    'source' => 'yima_' . $platform['name']
                ]);
            }
            echo "从易码平台获取 " . count($data['data']) . " 条验证码\n";
        }
    }
    
    private function fetchFromJiecode($platform) {
        $apiUrl = $platform['api_url'];
        $apiKey = $platform['api_key'];
        
        $url = rtrim($apiUrl, '/') . "/api/sms?token={$apiKey}";
        
        $response = $this->httpGet($url);
        $data = json_decode($response, true);
        
        if ($data && isset($data['list'])) {
            foreach ($data['list'] as $sms) {
                $this->saveCode($platform['user_id'], [
                    'phone' => $sms['phone'] ?? '',
                    'code' => $sms['code'] ?? $this->extractCode($sms['content'] ?? ''),
                    'content' => $sms['content'] ?? '',
                    'source' => 'jiecode_' . $platform['name']
                ]);
            }
            echo "从接码平台获取 " . count($data['list']) . " 条验证码\n";
        }
    }
    
    private function fetchFromCustom($platform) {
        $apiUrl = $platform['api_url'];
        $apiKey = $platform['api_key'];
        $apiSecret = $platform['api_secret'];
        
        $url = $apiUrl;
        if (strpos($url, '?') === false) {
            $url .= "?api_key={$apiKey}";
        } else {
            $url .= "&api_key={$apiKey}";
        }
        
        if ($apiSecret) {
            $timestamp = time();
            $sign = md5($apiKey . $apiSecret . $timestamp);
            $url .= "&timestamp={$timestamp}&sign={$sign}";
        }
        
        $response = $this->httpGet($url);
        $data = json_decode($response, true);
        
        if ($data && isset($data['data'])) {
            $list = $data['data']['list'] ?? $data['data'] ?? [];
            foreach ($list as $sms) {
                $this->saveCode($platform['user_id'], [
                    'phone' => $sms['phone'] ?? $sms['mobile'] ?? '',
                    'code' => $sms['code'] ?? $this->extractCode($sms['content'] ?? $sms['sms'] ?? ''),
                    'content' => $sms['content'] ?? $sms['sms'] ?? '',
                    'source' => 'custom_' . $platform['name']
                ]);
            }
            echo "从自定义平台获取 " . count($list) . " 条验证码\n";
        }
    }
    
    private function extractCode($content) {
        $patterns = [
            '/验证码[是为：:\s]*(\d{4,8})/i',
            '/code[是为：:\s]*(\d{4,8})/i',
            '/(\d{4,8})/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $code = $matches[1];
                if ($this->isValidCode($code)) {
                    return $code;
                }
            }
        }
        
        return '';
    }
    
    private function isValidCode($code) {
        $len = strlen($code);
        if ($len < 4 || $len > 8) return false;
        if (preg_match('/^20[2-9]\d$/', $code)) return false;
        if ($len === 8 && preg_match('/^20\d{6}$/', $code)) return false;
        return true;
    }
    
    private function saveCode($userId, $data) {
        if (empty($data['phone']) || empty($data['code'])) {
            return;
        }
        
        $stmt = $this->db->prepare("
            SELECT id FROM verification_codes 
            WHERE user_id = ? AND phone = ? AND code = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$userId, $data['phone'], $data['code']]);
        
        if ($stmt->fetch()) {
            return;
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO verification_codes (user_id, phone, code, source_url, content, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $data['phone'], $data['code'], $data['source'], $data['content']]);
        
        echo "  保存验证码: {$data['phone']} -> {$data['code']}\n";
    }
    
    private function updateLastFetch($platformId) {
        $stmt = $this->db->prepare("UPDATE sms_platforms SET last_fetch_at = NOW() WHERE id = ?");
        $stmt->execute([$platformId]);
    }
    
    private function httpGet($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}

$service = new AutoFetchService();
$service->run();
