const fetch = require('node-fetch');

const BASE_URL = 'https://jm.91wz.org';

async function testApi() {
    console.log('=== 开始测试多用户动态验证码监控系统 API ===\n');
    
    // 1. 测试登录
    console.log('1. 测试登录功能...');
    try {
        const loginResponse = await fetch(`${BASE_URL}/backend/api/auth/index.php?action=login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                username: 'admin',
                password: 'admin'
            })
        });
        
        const loginData = await loginResponse.json();
        console.log('   登录响应:', loginData);
        
        if (!loginData.success) {
            console.log('❌ 登录失败！');
            return;
        }
        
        const token = loginData.data.token;
        console.log('✅ 登录成功！Token:', token.substring(0, 30) + '...\n');
        
        // 2. 测试获取监控列表
        console.log('2. 测试获取监控列表...');
        const monitorsResponse = await fetch(`${BASE_URL}/backend/api/monitors/index.php`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const monitorsData = await monitorsResponse.json();
        console.log('   监控列表响应:', monitorsData);
        
        if (monitorsData.success && monitorsData.data && monitorsData.data.list) {
            console.log('✅ 获取监控列表成功，共有', monitorsData.data.list.length, '个监控项\n');
        } else {
            console.log('⚠️ 获取监控列表可能有问题\n');
        }
        
        // 3. 测试添加监控
        console.log('3. 测试添加监控...');
        const testPhone = '1380013' + Math.floor(Math.random() * 10000);
        const addResponse = await fetch(`${BASE_URL}/backend/api/monitors/index.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({
                phone_number: testPhone,
                url: 'https://example.com/test'
            })
        });
        
        const addData = await addResponse.json();
        console.log('   添加监控响应:', addData);
        
        if (addData.success) {
            console.log('✅ 添加监控成功！新增ID:', addData.data.id, '\n');
            const newMonitorId = addData.data.id;
            
            // 4. 测试删除监控
            console.log('4. 测试删除监控...');
            const deleteResponse = await fetch(`${BASE_URL}/backend/api/monitors/index.php?id=${newMonitorId}`, {
                method: 'DELETE',
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            });
            
            const deleteData = await deleteResponse.json();
            console.log('   删除监控响应:', deleteData);
            
            if (deleteData.success) {
                console.log('✅ 删除监控成功！\n');
            } else {
                console.log('❌ 删除监控失败！\n');
            }
        } else {
            console.log('⚠️ 添加监控可能有问题（可能是手机号已存在）\n');
        }
        
        console.log('\n=== API测试完成 ===');
        
    } catch (error) {
        console.error('❌ 测试过程出错:', error);
    }
}

testApi();
