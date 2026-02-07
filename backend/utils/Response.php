<?php
/**
 * 响应工具类
 * 用于处理API响应格式的统一
 */

class Response {
    /**
     * 成功响应
     * @param array $data
     * @param string $message
     * @param int $code
     */
    public static function success($data = [], $message = 'Success', $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'code' => $code
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * 错误响应
     * @param string $message
     * @param int $code
     * @param mixed $data
     */
    public static function error($message = 'Error', $code = 400, $data = null) {
        http_response_code($code);
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => false,
            'data' => $data,
            'message' => $message,
            'code' => $code
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * 未授权响应
     * @param string $message
     */
    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, 401);
    }
    
    /**
     * 禁止访问响应
     * @param string $message
     */
    public static function forbidden($message = 'Forbidden') {
        self::error($message, 403);
    }
    
    /**
     * 资源不存在响应
     * @param string $message
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, 404);
    }
    
    /**
     * 服务器错误响应
     * @param string $message
     */
    public static function serverError($message = 'Internal server error') {
        self::error($message, 500);
    }
    
    /**
     * 验证错误响应
     * @param array $errors
     * @param string $message
     */
    public static function validationError($errors, $message = 'Validation error') {
        self::error($message, 422, $errors);
    }
    
    /**
     * 处理CORS请求
     */
    public static function handleCORS() {
        $config = require __DIR__ . '/../config/config.php';
        
        if ($config['api']['cors']['enabled']) {
            $allowedOrigins = $config['api']['cors']['allowed_origins'];
            $allowedMethods = $config['api']['cors']['allowed_methods'];
            $allowedHeaders = $config['api']['cors']['allowed_headers'];
            $maxAge = $config['api']['cors']['max_age'];
            
            if (isset($_SERVER['HTTP_ORIGIN']) && in_array('*', $allowedOrigins)) {
                header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
            } elseif (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
                header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
            }
            
            header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods));
            header('Access-Control-Allow-Headers: ' . implode(', ', $allowedHeaders));
            header('Access-Control-Max-Age: ' . $maxAge);
            
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                http_response_code(204);
                exit;
            }
        }
    }
}
