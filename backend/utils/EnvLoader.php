<?php
/**
 * 环境变量加载器
 * 从.env文件加载环境变量
 */

class EnvLoader {
    private static $loaded = false;
    private static $variables = [];
    
    /**
     * 加载.env文件
     * @param string $path .env文件路径
     */
    public static function load($path) {
        if (self::$loaded) {
            return;
        }
        
        if (!file_exists($path)) {
            return;
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                
                $value = self::parseValue($value);
                
                self::$variables[$key] = $value;
                
                if (!array_key_exists($key, $_ENV)) {
                    $_ENV[$key] = $value;
                }
                
                if (!array_key_exists($key, $_SERVER)) {
                    $_SERVER[$key] = $value;
                }
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * 解析值
     * @param string $value
     * @return mixed
     */
    private static function parseValue($value) {
        // Remove quotes
        if (($value[0] === '"' && $value[strlen($value) - 1] === '"') ||
            ($value[0] === "'" && $value[strlen($value) - 1] === "'")) {
            $value = substr($value, 1, -1);
        }
        
        // Convert boolean
        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }
        
        // Convert null
        if (strtolower($value) === 'null') {
            return null;
        }
        
        return $value;
    }
    
    /**
     * 获取环境变量
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get($key, $default = null) {
        if (isset(self::$variables[$key])) {
            return self::$variables[$key];
        }
        
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        
        return $default;
    }
}
