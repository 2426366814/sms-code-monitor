<?php
/**
 * CORS Configuration
 * 
 * Security: Only allow specific origins
 */

class CORS {
    private static $allowedOrigins = null;
    
    /**
     * Get allowed origins from environment
     */
    private static function getAllowedOrigins() {
        if (self::$allowedOrigins === null) {
            $envOrigins = getenv('CORS_ORIGINS');
            if ($envOrigins) {
                self::$allowedOrigins = array_map('trim', explode(',', $envOrigins));
            } else {
                // Default allowed origins (should be configured in production)
                self::$allowedOrigins = [
                    'https://jm.91wz.org',
                    'http://localhost:3000',
                    'http://localhost:8080',
                    'http://127.0.0.1:3000',
                    'http://127.0.0.1:8080'
                ];
            }
        }
        return self::$allowedOrigins;
    }
    
    /**
     * Set CORS headers based on request origin
     */
    public static function setHeaders() {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowedOrigins = self::getAllowedOrigins();
        
        // Check if origin is allowed
        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Credentials: true');
        } else {
            // For development, allow all (remove in production)
            $isProduction = getenv('APP_ENV') === 'production';
            if (!$isProduction && $origin) {
                header("Access-Control-Allow-Origin: $origin");
                header('Access-Control-Allow-Credentials: true');
            }
        }
        
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
        
        // Handle preflight request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
    
    /**
     * Check if request origin is allowed
     */
    public static function isOriginAllowed() {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowedOrigins = self::getAllowedOrigins();
        
        // Allow if no origin (mobile apps, curl, etc.)
        if (empty($origin)) {
            return true;
        }
        
        return in_array($origin, $allowedOrigins);
    }
}
