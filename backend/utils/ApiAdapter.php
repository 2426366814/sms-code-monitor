<?php
/**
 * API适配器类
 * 支持多种API格式的验证码获取和解析
 */

class ApiAdapter {
    
    /**
     * API类型定义
     */
    const API_TYPE_SMS8 = 'sms8';
    const API_TYPE_GENERIC_JSON = 'generic_json';
    const API_TYPE_RAW_TEXT = 'raw_text';
    const API_TYPE_CUSTOM = 'custom';
    
    /**
     * 支持的API格式配置
     */
    private static $apiConfigs = [
        self::API_TYPE_SMS8 => [
            'name' => 'SMS8 API',
            'url_pattern' => 'api.sms8.net',
            'method' => 'GET',
            'response_type' => 'json',
            'code_path' => 'data.code',
            'time_path' => 'data.code_time',
            'success_check' => ['path' => 'code', 'value' => 0],
            'error_path' => 'msg'
        ],
        self::API_TYPE_GENERIC_JSON => [
            'name' => '通用JSON API',
            'url_pattern' => '*',
            'method' => 'GET',
            'response_type' => 'json',
            'code_path' => 'code',
            'time_path' => 'time',
            'success_check' => null,
            'error_path' => 'message'
        ],
        self::API_TYPE_RAW_TEXT => [
            'name' => '纯文本API',
            'url_pattern' => '*',
            'method' => 'GET',
            'response_type' => 'text',
            'code_regex' => '/\b\d{4,8}\b/',
            'time_regex' => null
        ]
    ];
    
    /**
     * 检测API类型
     * @param string $url API URL
     * @return string API类型
     */
    public static function detectApiType($url) {
        foreach (self::$apiConfigs as $type => $config) {
            if ($config['url_pattern'] !== '*' && strpos($url, $config['url_pattern']) !== false) {
                return $type;
            }
        }
        return self::API_TYPE_GENERIC_JSON;
    }
    
    /**
     * 获取API配置
     * @param string $type API类型
     * @return array|null 配置信息
     */
    public static function getApiConfig($type) {
        return isset(self::$apiConfigs[$type]) ? self::$apiConfigs[$type] : null;
    }
    
    /**
     * 获取所有支持的API类型
     * @return array API类型列表
     */
    public static function getSupportedApiTypes() {
        $types = [];
        foreach (self::$apiConfigs as $key => $config) {
            $types[] = [
                'type' => $key,
                'name' => $config['name'],
                'url_pattern' => $config['url_pattern']
            ];
        }
        return $types;
    }
    
    /**
     * 发送请求并解析响应
     * @param string $url API URL
     * @param string $apiType API类型（可选，自动检测）
     * @return array 解析结果
     */
    public static function fetchAndParse($url, $apiType = null) {
        // 自动检测API类型
        if ($apiType === null) {
            $apiType = self::detectApiType($url);
        }
        
        $config = self::getApiConfig($apiType);
        if (!$config) {
            $apiType = self::API_TYPE_GENERIC_JSON;
            $config = self::getApiConfig($apiType);
        }
        
        try {
            // 发送请求
            $response = self::sendRequest($url, $config['method']);
            
            if (empty($response)) {
                return [
                    'success' => false,
                    'code' => '',
                    'timestamp' => 0,
                    'time_str' => '',
                    'message' => 'API无响应',
                    'raw_response' => ''
                ];
            }
            
            // 根据响应类型解析
            if ($config['response_type'] === 'json') {
                return self::parseJsonResponse($response, $config);
            } else {
                return self::parseTextResponse($response, $config);
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'code' => '',
                'timestamp' => 0,
                'time_str' => '',
                'message' => '请求失败: ' . $e->getMessage(),
                'raw_response' => ''
            ];
        }
    }
    
    /**
     * 发送HTTP请求
     * @param string $url 请求URL
     * @param string $method 请求方法
     * @return string 响应内容
     */
    private static function sendRequest($url, $method = 'GET') {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        
        // 设置请求头
        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP错误码: {$httpCode}");
        }
        
        return $response;
    }
    
    /**
     * 解析JSON响应
     * @param string $response 响应内容
     * @param array $config API配置
     * @return array 解析结果
     */
    private static function parseJsonResponse($response, $config) {
        $data = json_decode($response, true);
        
        if ($data === null) {
            return [
                'success' => false,
                'code' => '',
                'timestamp' => 0,
                'time_str' => '',
                'message' => 'JSON解析失败',
                'raw_response' => $response
            ];
        }
        
        // 检查是否成功
        if ($config['success_check'] !== null) {
            $checkPath = $config['success_check']['path'];
            $checkValue = $config['success_check']['value'];
            $actualValue = self::getValueByPath($data, $checkPath);
            
            if ($actualValue !== $checkValue) {
                $errorMsg = self::getValueByPath($data, $config['error_path']) ?: 'API返回错误';
                return [
                    'success' => false,
                    'code' => '',
                    'timestamp' => 0,
                    'time_str' => '',
                    'message' => $errorMsg,
                    'raw_response' => $response
                ];
            }
        }
        
        // 提取验证码
        $code = self::getValueByPath($data, $config['code_path']);
        
        // 提取时间
        $timeStr = '';
        if ($config['time_path']) {
            $timeStr = self::getValueByPath($data, $config['time_path']);
        }
        
        // 如果没有时间，使用当前时间
        if (empty($timeStr)) {
            $timeStr = date('Y-m-d H:i:s');
        }
        
        // 解析时间戳
        $timestamp = self::parseTimeString($timeStr);
        
        return [
            'success' => !empty($code),
            'code' => $code ?: '',
            'timestamp' => $timestamp,
            'time_str' => $timeStr,
            'message' => !empty($code) ? '获取成功' : '未找到验证码',
            'raw_response' => $response
        ];
    }
    
    /**
     * 解析文本响应
     * @param string $response 响应内容
     * @param array $config API配置
     * @return array 解析结果
     */
    private static function parseTextResponse($response, $config) {
        $code = '';
        
        // 使用正则表达式提取验证码
        if (isset($config['code_regex']) && $config['code_regex']) {
            if (preg_match($config['code_regex'], $response, $matches)) {
                $code = $matches[0];
            }
        } else {
            // 默认提取4-8位数字
            if (preg_match('/\b\d{4,8}\b/', $response, $matches)) {
                $code = $matches[0];
            }
        }
        
        // 提取时间
        $timeStr = date('Y-m-d H:i:s');
        if (isset($config['time_regex']) && $config['time_regex']) {
            if (preg_match($config['time_regex'], $response, $matches)) {
                $timeStr = $matches[0];
            }
        }
        
        return [
            'success' => !empty($code),
            'code' => $code,
            'timestamp' => time() * 1000,
            'time_str' => $timeStr,
            'message' => !empty($code) ? '提取成功' : '未找到验证码',
            'raw_response' => $response
        ];
    }
    
    /**
     * 根据路径获取数组值
     * @param array $data 数据数组
     * @param string $path 路径，如 "data.code"
     * @return mixed 值
     */
    private static function getValueByPath($data, $path) {
        if (empty($path)) {
            return null;
        }
        
        $keys = explode('.', $path);
        $value = $data;
        
        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }
        
        return $value;
    }
    
    /**
     * 解析时间字符串为时间戳
     * @param string $timeStr 时间字符串
     * @return int 时间戳（毫秒）
     */
    private static function parseTimeString($timeStr) {
        // 尝试多种时间格式
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y/m/d H:i:s',
            'd-m-Y H:i:s',
            'H:i:s',
            'H:i'
        ];
        
        foreach ($formats as $format) {
            $timestamp = DateTime::createFromFormat($format, $timeStr);
            if ($timestamp !== false) {
                return $timestamp->getTimestamp() * 1000;
            }
        }
        
        // 尝试strtotime
        $timestamp = strtotime($timeStr);
        if ($timestamp !== false) {
            return $timestamp * 1000;
        }
        
        return time() * 1000;
    }
    
    /**
     * 添加自定义API配置
     * @param string $type API类型标识
     * @param array $config 配置数组
     */
    public static function addApiConfig($type, $config) {
        self::$apiConfigs[$type] = $config;
    }
}
