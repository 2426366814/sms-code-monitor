<?php
/**
 * Rate Limiter
 * 
 * Prevents brute force attacks by limiting login attempts
 */

class RateLimiter {
    private static $attemptsFile = null;
    
    /**
     * Get attempts storage file path
     */
    private static function getStorageFile($identifier) {
        $dir = sys_get_temp_dir() . '/rate_limiter';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . '/' . md5($identifier) . '.json';
    }
    
    /**
     * Get attempts data
     */
    private static function getAttempts($identifier) {
        $file = self::getStorageFile($identifier);
        if (!file_exists($file)) {
            return ['count' => 0, 'first_attempt' => 0, 'locked_until' => 0];
        }
        
        $data = json_decode(file_get_contents($file), true);
        if (!$data) {
            return ['count' => 0, 'first_attempt' => 0, 'locked_until' => 0];
        }
        
        return $data;
    }
    
    /**
     * Save attempts data
     */
    private static function saveAttempts($identifier, $data) {
        $file = self::getStorageFile($identifier);
        file_put_contents($file, json_encode($data));
    }
    
    /**
     * Check if identifier is locked out
     */
    public static function isLockedOut($identifier, $maxAttempts = 5, $lockoutDuration = 900) {
        $data = self::getAttempts($identifier);
        $now = time();
        
        // Check if locked
        if ($data['locked_until'] > $now) {
            $remaining = $data['locked_until'] - $now;
            return [
                'locked' => true,
                'remaining_seconds' => $remaining,
                'message' => "登录失败次数过多，请 {$remaining} 秒后再试"
            ];
        }
        
        // Check if attempts window expired (reset after lockout duration)
        if ($data['first_attempt'] > 0 && ($now - $data['first_attempt']) > $lockoutDuration) {
            self::clearAttempts($identifier);
            return ['locked' => false];
        }
        
        return ['locked' => false, 'attempts' => $data['count']];
    }
    
    /**
     * Record a failed attempt
     */
    public static function recordFailedAttempt($identifier, $maxAttempts = 5, $lockoutDuration = 900) {
        $data = self::getAttempts($identifier);
        $now = time();
        
        // Reset if window expired
        if ($data['first_attempt'] > 0 && ($now - $data['first_attempt']) > $lockoutDuration) {
            $data = ['count' => 0, 'first_attempt' => 0, 'locked_until' => 0];
        }
        
        $data['count']++;
        
        if ($data['first_attempt'] === 0) {
            $data['first_attempt'] = $now;
        }
        
        // Lock if max attempts reached
        if ($data['count'] >= $maxAttempts) {
            $data['locked_until'] = $now + $lockoutDuration;
        }
        
        self::saveAttempts($identifier, $data);
        
        return [
            'attempts' => $data['count'],
            'remaining' => max(0, $maxAttempts - $data['count']),
            'locked' => $data['locked_until'] > $now
        ];
    }
    
    /**
     * Clear attempts on successful login
     */
    public static function clearAttempts($identifier) {
        $file = self::getStorageFile($identifier);
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    /**
     * Get remaining attempts
     */
    public static function getRemainingAttempts($identifier, $maxAttempts = 5) {
        $data = self::getAttempts($identifier);
        return max(0, $maxAttempts - $data['count']);
    }
}
