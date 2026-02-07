<?php
/**
 * JWT工具类
 * 用于处理用户认证和令牌生成
 */

class JWT {
    private $config;
    
    /**
     * 构造函数
     */
    public function __construct() {
        $this->config = require __DIR__ . '/../config/config.php';
    }
    
    /**
     * 生成令牌
     * @param array $payload
     * @param int $expiresIn
     * @return string
     */
    public function generateToken($payload, $expiresIn = null) {
        if ($expiresIn === null) {
            $expiresIn = $this->config['jwt']['expires_in'];
        }
        
        $header = [
            'alg' => $this->config['jwt']['algorithm'],
            'typ' => 'JWT'
        ];
        
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiresIn;
        
        $encodedHeader = $this->base64UrlEncode(json_encode($header));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac(
            'sha256',
            "{$encodedHeader}.{$encodedPayload}",
            $this->config['jwt']['secret'],
            true
        );
        
        $encodedSignature = $this->base64UrlEncode($signature);
        
        return "{$encodedHeader}.{$encodedPayload}.{$encodedSignature}";
    }
    
    /**
     * 验证令牌
     * @param string $token
     * @return array|null
     */
    public function validateToken($token) {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }
            
            list($encodedHeader, $encodedPayload, $encodedSignature) = $parts;
            
            $header = json_decode($this->base64UrlDecode($encodedHeader), true);
            $payload = json_decode($this->base64UrlDecode($encodedPayload), true);
            
            if ($header['alg'] !== $this->config['jwt']['algorithm']) {
                return null;
            }
            
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return null;
            }
            
            $expectedSignature = hash_hmac(
                'sha256',
                "{$encodedHeader}.{$encodedPayload}",
                $this->config['jwt']['secret'],
                true
            );
            
            $expectedEncodedSignature = $this->base64UrlEncode($expectedSignature);
            
            if (!hash_equals($encodedSignature, $expectedEncodedSignature)) {
                return null;
            }
            
            return $payload;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * 从令牌中提取用户信息
     * @param string $token
     * @return array|null
     */
    public function extractUserFromToken($token) {
        $payload = $this->validateToken($token);
        if ($payload && isset($payload['user_id'])) {
            return [
                'user_id' => $payload['user_id'],
                'username' => $payload['username'] ?? null,
                'email' => $payload['email'] ?? null
            ];
        }
        return null;
    }
    
    /**
     * Base64 URL编码
     * @param string $data
     * @return string
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL解码
     * @param string $data
     * @return string
     */
    private function base64UrlDecode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}
