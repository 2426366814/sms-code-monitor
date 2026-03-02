<?php
/**
 * 验证码监控后台服务
 * 自动刷新所有活跃监控项并提取验证码
 * 
 * 使用方法：
 * php monitor_service.php          # 手动运行一次
 * php monitor_service.php --daemon # 守护进程模式
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/monitor_service.log');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/utils/Database.php';
require_once __DIR__ . '/utils/Response.php';

class MonitorService {
    private $db;
    private $config;
    private $logFile;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->config = require __DIR__ . '/config/config.php';
        $this->logFile = __DIR__ . '/logs/monitor_service.log';
        
        // 确保日志目录存在
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * 记录日志
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
        echo $logMessage;
    }
    
    /**
     * 发送HTTP请求
     */
    private function sendRequest($url) {
        $ch = curl_init();
        $isProduction = ($this->config['system']['env'] ?? 'production') === 'production';
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => $isProduction,
            CURLOPT_SSL_VERIFYHOST => $isProduction ? 2 : 0,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("HTTP请求失败: {$error}");
        }
        
        if ($httpCode >= 400) {
            throw new Exception("HTTP错误: {$httpCode}");
        }
        
        return $response;
    }
    
    /**
     * 并行发送多个HTTP请求（使用curl_multi）
     */
    private function sendParallelRequests($urls) {
        $mh = curl_multi_init();
        $handles = [];
        $results = [];
        
        // 创建所有curl句柄
        $isProduction = ($this->config['system']['env'] ?? 'production') === 'production';
        foreach ($urls as $id => $url) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_SSL_VERIFYPEER => $isProduction,
                CURLOPT_SSL_VERIFYHOST => $isProduction ? 2 : 0,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$id] = $ch;
        }
        
        // 并行执行所有请求
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);
        
        // 收集结果
        foreach ($handles as $id => $ch) {
            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            $results[$id] = [
                'response' => $response,
                'httpCode' => $httpCode,
                'error' => $error
            ];
            
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        
        curl_multi_close($mh);
        return $results;
    }
    
    /**
     * 提取验证码
     */
    private function extractCode($response, $phone = '') {
        // 先解析JSON响应
        $jsonData = json_decode($response, true);
        
        // 如果是API响应格式，优先从 data.code 字段提取
        if ($jsonData && isset($jsonData['data']['code'])) {
            $apiCode = $jsonData['data']['code'];
            if (!empty($apiCode) && is_string($apiCode)) {
                // 从API返回的code字段中提取验证码数字
                if (preg_match('/\b(\d{4,8})\b/', $apiCode, $matches)) {
                    $code = $matches[1];
                    if ($this->isValidCode($code)) {
                        return [
                            'status' => 'success',
                            'code' => $code,
                            'message' => '验证码提取成功'
                        ];
                    }
                }
            }
        }
        
        // 多种验证码提取模式（按优先级排序）
        $patterns = [
            // 英文格式 - 最常见
            '/code\s+(?:is\s+)?(\d{4,8})/i',
            '/verification\s+code\s+(?:is\s+)?(\d{4,8})/i',
            '/(\d{4,8})\s+is\s+your\s+(?:verification\s+)?code/i',
            // 中文格式
            '/验证码[是为：:\s]*(\d{4,8})/i',
            '/动态码[是为：:\s]*(\d{4,8})/i',
            // 带方括号的格式
            '/\[.*?\].*?(\d{4,8})/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $response, $matches)) {
                $code = $matches[1];
                if ($this->isValidCode($code)) {
                    return [
                        'status' => 'success',
                        'code' => $code,
                        'message' => '验证码提取成功'
                    ];
                }
            }
        }
        
        return [
            'status' => 'no-code',
            'code' => '',
            'message' => '未找到验证码'
        ];
    }
    
    /**
     * 验证是否为有效的验证码
     */
    private function isValidCode($code) {
        // 长度检查
        $len = strlen($code);
        if ($len < 4 || $len > 8) {
            return false;
        }
        
        // 排除年份（2020-2099）
        if (preg_match('/^20[2-9]\d$/', $code)) {
            return false;
        }
        
        // 排除日期格式（如 20260301）
        if ($len === 8 && preg_match('/^20\d{6}$/', $code)) {
            return false;
        }
        
        // 排除时间戳开头的数字
        if (preg_match('/^1[6-9]\d{8,}$/', $code)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 刷新单个监控项
     */
    public function refreshMonitor($monitor) {
        $monitorId = $monitor['id'];
        $phone = $monitor['phone'];
        $url = $monitor['url'];
        $oldCode = $monitor['last_extracted_code'] ?? '';
        
        $this->log("刷新监控 #{$monitorId} - 手机: {$phone}");
        
        try {
            // 发送请求
            $response = $this->sendRequest($url);
            
            // 提取验证码
            $extracted = $this->extractCode($response, $phone);
            
            $now = time();
            $nowStr = date('Y-m-d H:i:s');
            
            // 更新监控状态
            $updateData = [
                'status' => $extracted['status'],
                'last_code' => mb_substr($response, 0, 1000),
                'last_extracted_code' => $extracted['code'],
                'last_update' => $nowStr,
                'last_update_timestamp' => $now * 1000,
                'message' => $extracted['message']
            ];
            
            // 只有当验证码发生变化时才更新 code_timestamp
            // 这样前端可以用 code_timestamp 判断是否是新验证码
            if ($extracted['status'] === 'success' && !empty($extracted['code']) && $extracted['code'] !== $oldCode) {
                $updateData['code_timestamp'] = $now * 1000;
                $updateData['code_time_str'] = $nowStr;
                $this->log("验证码变化: {$oldCode} -> {$extracted['code']}", 'INFO');
            }
            
            $this->db->update('monitors', $updateData, ['id' => $monitorId]);
            
            // 如果成功提取到验证码，保存到历史记录
            if ($extracted['status'] === 'success' && !empty($extracted['code'])) {
                $this->saveCode($monitor, $extracted['code'], $response);
                $this->log("验证码提取成功: {$extracted['code']}", 'SUCCESS');
            } else {
                $this->log("未找到验证码", 'WARN');
            }
            
            return $extracted;
            
        } catch (Exception $e) {
            $this->log("刷新失败: " . $e->getMessage(), 'ERROR');
            
            // 更新错误状态
            $this->db->update('monitors', [
                'status' => 'error',
                'message' => $e->getMessage(),
                'last_update' => date('Y-m-d H:i:s')
            ], ['id' => $monitorId]);
            
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 保存验证码到历史记录
     */
    private function saveCode($monitor, $code, $response) {
        try {
            $this->db->insert('verification_codes', [
                'user_id' => $monitor['user_id'],
                'monitor_id' => $monitor['id'],
                'phone' => $monitor['phone'],
                'code' => $code,
                'message' => mb_substr($response, 0, 500),
                'source_url' => $monitor['url'],
                'received_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            $this->log("保存验证码失败: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * 刷新所有活跃监控（并行模式）
     */
    public function refreshAllMonitors() {
        $this->log("========== 开始并行刷新所有监控 ==========");
        
        // 清理24小时前的验证码记录
        $this->cleanupOldCodes();
        
        // 获取所有监控（不限制状态，刷新所有）
        $monitors = $this->db->fetchAll(
            "SELECT * FROM monitors ORDER BY updated_at DESC"
        );
        
        if (empty($monitors)) {
            $this->log("没有监控项需要刷新");
            return;
        }
        
        $this->log("共发现 " . count($monitors) . " 个监控项，开始并行请求...");
        
        // 准备URL列表
        $urls = [];
        foreach ($monitors as $monitor) {
            $urls[$monitor['id']] = $monitor['url'];
        }
        
        // 并行发送所有请求
        $startTime = microtime(true);
        $responses = $this->sendParallelRequests($urls);
        $elapsed = round((microtime(true) - $startTime) * 1000);
        $this->log("并行请求完成，耗时: {$elapsed}ms");
        
        // 处理每个响应
        $stats = [
            'total' => count($monitors),
            'success' => 0,
            'no-code' => 0,
            'error' => 0
        ];
        
        foreach ($monitors as $monitor) {
            $id = $monitor['id'];
            $result = isset($responses[$id]) ? $responses[$id] : null;
            
            if ($result && !$result['error'] && $result['httpCode'] < 400) {
                $this->processResponse($monitor, $result['response'], $stats);
            } else {
                $errorMsg = $result ? ($result['error'] ?: "HTTP {$result['httpCode']}") : '无响应';
                $this->updateMonitorStatus($monitor['id'], 'error', $errorMsg);
                $stats['error']++;
            }
        }
        
        $this->log("========== 刷新完成 ==========");
        $this->log("统计: 成功={$stats['success']}, 无验证码={$stats['no-code']}, 错误={$stats['error']}");
        
        return $stats;
    }
    
    /**
     * 处理API响应
     */
    private function processResponse($monitor, $response, &$stats) {
        $now = time();
        $nowStr = date('Y-m-d H:i:s', $now);
        
        // 提取验证码
        $extracted = $this->extractCode($response, $monitor['phone']);
        
        $updateData = [
            'last_code' => mb_substr($response, 0, 500),
            'last_update' => $nowStr,
            'last_update_timestamp' => $now * 1000,
            'updated_at' => $nowStr
        ];
        
        // 获取旧的验证码
        $oldCode = $monitor['last_extracted_code'] ?? '';
        
        if ($extracted['status'] === 'success') {
            $code = $extracted['code'];
            
            // 只有验证码变化时才更新code_timestamp
            if ($code !== $oldCode) {
                $updateData['last_extracted_code'] = $code;
                $updateData['code_timestamp'] = $now * 1000;
                $updateData['code_time_str'] = $nowStr;
                $updateData['status'] = 'success';
                $updateData['message'] = '验证码提取成功';
                
                // 保存到验证码历史表
                $this->saveCode($monitor, $code, $response);
                
                $this->log("手机 {$monitor['phone']} 新验证码: {$code}");
            } else {
                $updateData['status'] = 'success';
                $updateData['message'] = '验证码未变化';
            }
            $stats['success']++;
        } else {
            $updateData['status'] = 'no-code';
            $updateData['message'] = $extracted['message'] ?? '无验证码';
            $stats['no-code']++;
        }
        
        // 更新数据库
        $this->updateMonitor($monitor['id'], $updateData);
    }
    
    /**
     * 更新监控项状态
     */
    private function updateMonitorStatus($id, $status, $message) {
        $nowStr = date('Y-m-d H:i:s');
        $this->updateMonitor($id, [
            'status' => $status,
            'message' => $message,
            'last_update' => $nowStr,
            'last_update_timestamp' => time() * 1000,
            'updated_at' => $nowStr
        ]);
    }
    
    /**
     * 更新监控项
     */
    private function updateMonitor($id, $data) {
        $sets = [];
        $values = [];
        foreach ($data as $key => $value) {
            $sets[] = "`{$key}` = ?";
            $values[] = $value;
        }
        $values[] = $id;
        
        $sql = "UPDATE monitors SET " . implode(', ', $sets) . " WHERE id = ?";
        $this->db->query($sql, $values);
    }
    
    /**
     * 清理24小时前的验证码记录
     */
    private function cleanupOldCodes() {
        try {
            $deleted = $this->db->query(
                "DELETE FROM verification_codes WHERE received_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
            $count = $deleted ? $deleted->rowCount() : 0;
            if ($count > 0) {
                $this->log("已清理 {$count} 条24小时前的验证码记录");
            }
        } catch (Exception $e) {
            $this->log("清理过期验证码失败: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * 守护进程模式
     */
    public function runDaemon($interval = 1) {
        $this->log("启动守护进程模式，刷新间隔: {$interval}秒");
        
        while (true) {
            $this->refreshAllMonitors();
            $this->log("等待 {$interval} 秒...");
            sleep($interval);
        }
    }
}

// 主程序
$service = new MonitorService();

// 检查命令行参数
$isDaemon = in_array('--daemon', $argv ?? []);
$interval = 1;

// 解析间隔参数
foreach ($argv ?? [] as $arg) {
    if (preg_match('/--interval=(\d+)/', $arg, $matches)) {
        $interval = (int)$matches[1];
    }
}

if ($isDaemon) {
    $service->runDaemon($interval);
} else {
    $service->refreshAllMonitors();
}
