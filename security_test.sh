#!/bin/bash
# 安全测试脚本 - SQL注入和XSS测试

BASE_URL="https://jm.haiwaijia.cn"

echo "=========================================="
echo "       安全边界测试 - SQL注入/XSS"
echo "=========================================="

echo ""
echo "=== 1. SQL注入测试 ==="

echo ""
echo "1.1 登录接口SQL注入测试:"
curl -s -X POST "${BASE_URL}/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@test.com'"'"' OR '"'"'1'"'"'='"'"'1","password":"test"}' | jq . 2>/dev/null || echo "响应不是JSON"

echo ""
echo "1.2 用户搜索SQL注入测试:"
curl -s "${BASE_URL}/api/admin/users?search=admin'"'; DROP TABLE users;--" \
  -H "Cookie: PHPSESSID=test" | jq . 2>/dev/null || echo "响应不是JSON"

echo ""
echo "1.3 接码记录查询注入测试:"
curl -s "${BASE_URL}/api/admin/codes?phone='"'"' UNION SELECT * FROM users--" | jq . 2>/dev/null || echo "响应不是JSON"

echo ""
echo "=== 2. XSS攻击测试 ==="

echo ""
echo "2.1 用户名XSS测试:"
curl -s -X POST "${BASE_URL}/api/admin/users" \
  -H "Content-Type: application/json" \
  -d '{"username":"<script>alert(1)</script>","email":"xss@test.com","password":"test123"}' | jq . 2>/dev/null || echo "响应不是JSON"

echo ""
echo "2.2 备注字段XSS测试:"
curl -s -X POST "${BASE_URL}/api/admin/monitors" \
  -H "Content-Type: application/json" \
  -d '{"name":"<img src=x onerror=alert(1)>","url":"https://test.com"}' | jq . 2>/dev/null || echo "响应不是JSON"

echo ""
echo "=== 3. 边界值测试 ==="

echo ""
echo "3.1 空值测试:"
curl -s -X POST "${BASE_URL}/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"","password":""}' | jq . 2>/dev/null || echo "响应不是JSON"

echo ""
echo "3.2 超长字符串测试:"
LONG_STR=$(python3 -c "print('A'*10000)")
curl -s -X POST "${BASE_URL}/api/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"${LONG_STR}@test.com\",\"password\":\"test\"}" | jq . 2>/dev/null || echo "响应不是JSON"

echo ""
echo "3.3 特殊字符测试:"
curl -s -X POST "${BASE_URL}/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"test+special@example.com","password":"p@ss!w0rd#$%"}' | jq . 2>/dev/null || echo "响应不是JSON"

echo ""
echo "=== 4. 认证绕过测试 ==="

echo ""
echo "4.1 无Token访问管理接口:"
curl -s "${BASE_URL}/api/admin/users" | jq . 2>/dev/null || echo "响应不是JSON"

echo ""
echo "4.2 无效Token测试:"
curl -s "${BASE_URL}/api/admin/users" \
  -H "Authorization: Bearer invalid_token_12345" | jq . 2>/dev/null || echo "响应不是JSON"

echo ""
echo "=== 5. CSRF测试 ==="

echo ""
echo "5.1 无CSRF Token的POST请求:"
curl -s -X POST "${BASE_URL}/api/admin/users" \
  -H "Content-Type: application/json" \
  -d '{"username":"csrf_test","email":"csrf@test.com","password":"test123"}' | jq . 2>/dev/null || echo "响应不是JSON"

echo ""
echo "=========================================="
echo "       安全测试完成"
echo "=========================================="
