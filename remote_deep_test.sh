#!/bin/bash

BASE_URL="https://jm.91wz.org"

echo "=========================================="
echo "    远程完整深度检测 - 基于开发文档"
echo "=========================================="
echo ""
echo "测试环境: $BASE_URL"
echo "测试时间: $(date)"
echo ""

# 获取管理员Token
echo "=== Phase 1: 认证API测试"
echo "------------------------------------------"
echo ""
echo "1. 管理员登录..."
LOGIN_RESULT=$(curl -s -X POST "$BASE_URL/backend/api/auth/index.php?action=login" \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin"}')
echo "$LOGIN_RESULT" | head -c 400
echo ""

# 解析Token
ADMIN_TOKEN=$(echo "$LOGIN_RESULT" | grep -o '"token":"[^"]*' | cut -d'"' -f4')
echo "Token: ${ADMIN_TOKEN:0:50}..."
echo ""
echo "2. 健康检查..."
curl -s "$BASE_URL/backend/api/health/index.php" | head -c 400
echo ""
echo ""

# === Phase 2: 监控API测试
echo "=== Phase 2: 监控API测试"
echo "------------------------------------------"
echo ""
echo "1. 获取监控列表..."
curl -s "$BASE_URL/backend/api/monitors/index.php" \
  -H "Authorization: Bearer $ADMIN_TOKEN" | head -c 400
echo ""
echo ""
echo "2. 获取验证码记录..."
curl -s "$BASE_URL/backend/api/codes/index.php" \
  -H "Authorization: Bearer $ADMIN_TOKEN" | head -c 400
echo ""
echo ""

# === Phase 3: 管理员API测试
echo "=== Phase 3: 管理员API测试"
echo "------------------------------------------"
echo ""
echo "1. 获取用户列表..."
curl -s "$BASE_URL/backend/api/admin/index.php?action=users" \
  -H "Authorization: Bearer $ADMIN_TOKEN" | head -c 400
echo ""
echo ""
echo "2. 获取监控列表..."
curl -s "$BASE_URL/backend/api/admin/index.php?action=monitors" \
  -H "Authorization: Bearer $ADMIN_TOKEN" | head -c 400
echo ""
echo ""
echo "3. 系统统计..."
curl -s "$BASE_URL/backend/api/admin/index.php?action=statistics" \
  -H "Authorization: Bearer $ADMIN_TOKEN" | head -c 400
echo ""
echo ""

# === Phase 4: 安全测试
echo "=== Phase 4: 安全测试"
echo "------------------------------------------"
echo ""
echo "1. SQL注入测试..."
curl -s -X POST "$BASE_URL/backend/api/auth/index.php?action=login" \
  -H "Content-Type: application/json" \
  -d '{"username":"admin'\"'\"' OR '1'='1","password":"admin"}' | head -c 400
echo ""
echo ""
echo "2. XSS测试..."
curl -s -X POST "$BASE_URL/backend/api/auth/index.php?action=register" \
  -H "Content-Type: application/json" \
  -d '{"username":"<script>alert(1)</script>","email":"xss@test.com","password":"test123"}' | head -c 400
echo ""
echo ""

# === Phase 5: 权限隔离测试
echo "=== Phase 5: 权限隔离测试"
echo "------------------------------------------"
echo ""
echo "1. 普通用户登录..."
USER_LOGIN=$(curl -s -X POST "$BASE_URL/backend/api/auth/index.php?action=login" \
  -H "Content-Type: application/json" \
  -d '{"username":"deep_v837_user","password":"test123456"}')
echo "$USER_LOGIN" | head -c 400
echo ""
USER_TOKEN=$(echo "$USER_LOGIN" | grep -o '"token":"[^"]*' | cut -d'"' -f4')
echo ""
echo "2. 普通用户访问管理员API..."
curl -s "$BASE_URL/backend/api/admin/index.php?action=users" \
  -H "Authorization: Bearer $USER_TOKEN" | head -c 400
echo ""
echo ""
echo "3. 无Token访问..."
curl -s "$BASE_URL/backend/api/monitors/index.php" | head -c 400
echo ""
echo ""

# === Phase 6: WebSocket服务检查
echo "=== Phase 6: WebSocket服务检查"
echo "------------------------------------------"
echo ""
echo "检查通过SSH..."

echo ""
echo "=========================================="
echo "    检测完成!"
echo "=========================================="
