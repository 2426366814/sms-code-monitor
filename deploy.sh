#!/bin/bash
# 多用户动态验证码监控系统部署脚本
# 用于在宝塔面板上自动部署项目

# 配置
PROJECT_NAME="verify-code-monitor"
PROJECT_PATH="/www/wwwroot/${PROJECT_NAME}.local"
DB_NAME="jm"
DB_USER="jm"
DB_PASS="ck123456@"

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}开始部署多用户动态验证码监控系统...${NC}"

# 1. 创建项目目录
echo -e "${YELLOW}1. 创建项目目录...${NC}"
mkdir -p ${PROJECT_PATH}

# 2. 创建必要的子目录
echo -e "${YELLOW}2. 创建子目录...${NC}"
mkdir -p ${PROJECT_PATH}/backend/api/{admin,api_keys,auth,codes,health,monitors,settings,public}
mkdir -p ${PROJECT_PATH}/backend/config
mkdir -p ${PROJECT_PATH}/backend/database
mkdir -p ${PROJECT_PATH}/backend/models
mkdir -p ${PROJECT_PATH}/backend/utils

# 3. 设置目录权限
echo -e "${YELLOW}3. 设置目录权限...${NC}"
chown -R www:www ${PROJECT_PATH}
chmod -R 755 ${PROJECT_PATH}

# 4. 创建数据库
echo -e "${YELLOW}4. 创建数据库...${NC}"
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -u root -p -e "FLUSH PRIVILEGES;"

# 5. 导入数据库
echo -e "${YELLOW}5. 导入数据库...${NC}"
mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} < ${PROJECT_PATH}/backend/database/database.sql

echo -e "${GREEN}部署完成！${NC}"
echo -e "${GREEN}访问地址: http://192.168.7.178:8080${NC}"
echo -e "${GREEN}默认管理员: admin / admin${NC}"
