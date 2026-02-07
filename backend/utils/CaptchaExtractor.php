<?php
/**
 * 验证码提取工具类
 * 用于从短信文本中提取验证码
 */

class CaptchaExtractor {
    /**
     * 从文本中提取验证码
     * @param string $text
     * @return string|null
     */
    public static function extract($text) {
        // 常见验证码模式
        $patterns = [
            // 数字验证码（6位）
            '/[验证码|校验码|密码|code|CODE|Code]\s*[:：]?\s*(\d{6})/',
            // 数字验证码（4-8位）
            '/[验证码|校验码|密码|code|CODE|Code]\s*[:：]?\s*(\d{4,8})/',
            // 纯数字验证码（4-8位）
            '/\b(\d{4,8})\b/',
            // 字母数字混合验证码
            '/[验证码|校验码|密码|code|CODE|Code]\s*[:：]?\s*([a-zA-Z0-9]{4,8})/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    /**
     * 验证是否为有效的验证码
     * @param string $code
     * @return bool
     */
    public static function isValid($code) {
        if (empty($code)) {
            return false;
        }
        
        // 验证码长度应在4-8位之间
        $length = strlen($code);
        if ($length < 4 || $length > 8) {
            return false;
        }
        
        // 验证码应只包含字母和数字
        return ctype_alnum($code);
    }
    
    /**
     * 从URL获取短信内容
     * @param string $url
     * @return string|null
     */
    public static function fetchFromUrl($url) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            
            if (curl_errno($ch)) {
                curl_close($ch);
                return null;
            }
            
            curl_close($ch);
            
            return $response;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * 从URL获取并提取验证码
     * @param string $url
     * @return array|null
     */
    public static function fetchAndExtract($url) {
        $text = self::fetchFromUrl($url);
        if ($text === null) {
            return null;
        }
        
        $code = self::extract($text);
        
        return [
            'original_text' => $text,
            'code' => $code
        ];
    }
}
