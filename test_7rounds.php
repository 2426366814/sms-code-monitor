<?php
/**
 * 7轮循环验证测试脚本
 * - 自动获取新token
 * - 完整CRUD测试
 * - 7轮循环验证
 */

$baseUrl = 'https://jm.91wz.org/api';
$username = 'admin';
$password = 'admin123';

function makeRequest($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init();
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => $method,
    ]);
    
    if ($data && in_array($method, ['POST', 'PUT'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true),
        'error' => $error
    ];
}

function login($baseUrl, $username, $password) {
    $result = makeRequest("$baseUrl/auth/login", 'POST', [
        'username' => $username,
        'password' => $password
    ]);
    
    if ($result['code'] === 200 && isset($result['body']['data']['token'])) {
        return $result['body']['data']['token'];
    }
    
    echo "登录失败: " . json_encode($result) . "\n";
    return null;
}

function runTests($baseUrl, $token, $round) {
    $passed = 0;
    $failed = 0;
    $results = [];
    
    $tests = [
        // 1. 系统检查
        'system_check' => function() use ($baseUrl) {
            $result = makeRequest("$baseUrl/system/check");
            return $result['code'] === 200;
        },
        
        // 2. 健康检测
        'health_check' => function() use ($baseUrl) {
            $result = makeRequest("$baseUrl/system/health");
            return $result['code'] === 200;
        },
        
        // 3. 获取监控列表
        'monitors_list' => function() use ($baseUrl, $token) {
            $result = makeRequest("$baseUrl/monitors", 'GET', null, $token);
            return $result['code'] === 200;
        },
        
        // 4. 创建监控
        'monitor_create' => function() use ($baseUrl, $token, &$results) {
            $result = makeRequest("$baseUrl/monitors", 'POST', [
                'name' => '测试监控_' . time(),
                'url' => 'https://test.example.com/api',
                'method' => 'GET',
                'interval' => 300,
                'timeout' => 30
            ], $token);
            
            if ($result['code'] === 200 && isset($result['body']['data']['id'])) {
                $results['created_id'] = $result['body']['data']['id'];
                return true;
            }
            return false;
        },
        
        // 5. 获取单个监控
        'monitor_get' => function() use ($baseUrl, $token, &$results) {
            if (!isset($results['created_id'])) return false;
            $result = makeRequest("$baseUrl/monitors/{$results['created_id']}", 'GET', null, $token);
            return $result['code'] === 200;
        },
        
        // 6. 更新监控
        'monitor_update' => function() use ($baseUrl, $token, &$results) {
            if (!isset($results['created_id'])) return false;
            $result = makeRequest("$baseUrl/monitors/{$results['created_id']}", 'PUT', [
                'name' => '更新监控_' . time(),
                'url' => 'https://updated.example.com/api',
                'method' => 'POST',
                'interval' => 600,
                'timeout' => 60
            ], $token);
            return $result['code'] === 200;
        },
        
        // 7. 获取统计数据
        'statistics' => function() use ($baseUrl, $token) {
            $result = makeRequest("$baseUrl/statistics/overview", 'GET', null, $token);
            return $result['code'] === 200;
        },
        
        // 8. 获取用户信息
        'user_info' => function() use ($baseUrl, $token) {
            $result = makeRequest("$baseUrl/user/profile", 'GET', null, $token);
            return $result['code'] === 200;
        },
        
        // 9. 获取标签列表
        'tags_list' => function() use ($baseUrl, $token) {
            $result = makeRequest("$baseUrl/tags", 'GET', null, $token);
            return $result['code'] === 200;
        },
        
        // 10. 删除监控
        'monitor_delete' => function() use ($baseUrl, $token, &$results) {
            if (!isset($results['created_id'])) return false;
            $result = makeRequest("$baseUrl/monitors/{$results['created_id']}", 'DELETE', null, $token);
            return $result['code'] === 200;
        }
    ];
    
    echo "  === 第 $round 轮测试 ===\n";
    
    foreach ($tests as $name => $test) {
        try {
            $success = $test();
            if ($success) {
                $passed++;
                echo "    ✓ $name\n";
            } else {
                $failed++;
                echo "    ✗ $name\n";
            }
        } catch (Exception $e) {
            $failed++;
            echo "    ✗ $name (异常: {$e->getMessage()})\n";
        }
    }
    
    $rate = round(($passed / ($passed + $failed)) * 100, 1);
    echo "  第 $round 轮: 通过率 $rate%\n";
    
    return ['passed' => $passed, 'failed' => $failed, 'rate' => $rate];
}

// 主程序
echo "========================================\n";
echo "  7轮循环验证测试\n";
echo "========================================\n\n";

echo "[1] 获取认证Token...\n";
$token = login($baseUrl, $username, $password);

if (!$token) {
    echo "错误: 无法获取Token，测试终止\n";
    exit(1);
}
echo "Token获取成功: " . substr($token, 0, 20) . "...\n\n";

$totalPassed = 0;
$totalFailed = 0;
$roundResults = [];

for ($i = 1; $i <= 7; $i++) {
    $result = runTests($baseUrl, $token, $i);
    $totalPassed += $result['passed'];
    $totalFailed += $result['failed'];
    $roundResults[$i] = $result;
    echo "\n";
}

echo "========================================\n";
echo "  测试汇总\n";
echo "========================================\n";
echo "总通过: $totalPassed | 总失败: $totalFailed\n";
$totalRate = round(($totalPassed / ($totalPassed + $totalFailed)) * 100, 1);
echo "通过率: $totalRate%\n\n";

if ($totalRate >= 90) {
    echo "✅ 测试通过！系统运行正常\n";
    exit(0);
} else {
    echo "❌ 测试未达标，需要检查问题\n";
    exit(1);
}
