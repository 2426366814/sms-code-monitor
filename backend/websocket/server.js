/**
 * WebSocket Server for Verification Code Monitoring System
 * Real-time push notifications for new verification codes
 */

require('dotenv').config({ path: __dirname + '/../config/.env' });
const WebSocket = require('ws');
const mysql = require('mysql2/promise');
const jwt = require('jsonwebtoken');

const PORT = process.env.WS_PORT || 8080;
const DB_CONFIG = {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASS || process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'verification_code_db',
    waitForConnections: true,
    connectionLimit: 10
};

const JWT_SECRET = process.env.JWT_SECRET || 'your-secret-key';

let pool;
const clients = new Map();

async function initDatabase() {
    try {
        pool = mysql.createPool(DB_CONFIG);
        console.log('[DB] Database connection pool created');
    } catch (error) {
        console.error('[DB] Failed to create connection pool:', error);
        process.exit(1);
    }
}

function verifyToken(token) {
    try {
        return jwt.verify(token, JWT_SECRET);
    } catch (error) {
        return null;
    }
}

async function getUserMonitors(userId) {
    try {
        const [rows] = await pool.execute(
            'SELECT phone, last_code, last_extracted_code, status, updated_at FROM monitors WHERE user_id = ? ORDER BY updated_at DESC',
            [userId]
        );
        return rows;
    } catch (error) {
        console.error('[DB] Error fetching monitors:', error);
        return [];
    }
}

async function broadcastToUser(userId, data) {
    const userClients = clients.get(userId);
    if (userClients && userClients.size > 0) {
        const message = JSON.stringify(data);
        userClients.forEach(client => {
            if (client.readyState === WebSocket.OPEN) {
                client.send(message);
            }
        });
        console.log(`[WS] Broadcast to user ${userId}: ${userClients.size} clients`);
    }
}

async function broadcastNewCode(userId, phone, code) {
    await broadcastToUser(userId, {
        type: 'new_code',
        data: {
            phone,
            code,
            timestamp: new Date().toISOString()
        }
    });
}

async function broadcastMonitorUpdate(userId, monitors) {
    await broadcastToUser(userId, {
        type: 'monitors_update',
        data: monitors
    });
}

function setupHeartbeat(ws) {
    ws.isAlive = true;
    ws.on('pong', () => {
        ws.isAlive = true;
    });
}

function startHeartbeatInterval(wss) {
    setInterval(() => {
        wss.clients.forEach(ws => {
            if (ws.isAlive === false) {
                const userId = ws.userId;
                if (userId && clients.has(userId)) {
                    clients.get(userId).delete(ws);
                    if (clients.get(userId).size === 0) {
                        clients.delete(userId);
                    }
                }
                return ws.terminate();
            }
            ws.isAlive = false;
            ws.ping();
        });
    }, 30000);
}

async function handleConnection(ws, req) {
    setupHeartbeat(ws);
    
    const url = new URL(req.url, `http://${req.headers.host}`);
    const token = url.searchParams.get('token');
    
    if (!token) {
        console.log('[WS] Connection rejected: No token provided');
        ws.send(JSON.stringify({ type: 'error', message: 'Authentication required' }));
        ws.close();
        return;
    }
    
    const payload = verifyToken(token);
    if (!payload) {
        console.log('[WS] Connection rejected: Invalid token');
        ws.send(JSON.stringify({ type: 'error', message: 'Invalid token' }));
        ws.close();
        return;
    }
    
    const userId = payload.user_id;
    ws.userId = userId;
    
    if (!clients.has(userId)) {
        clients.set(userId, new Set());
    }
    clients.get(userId).add(ws);
    
    console.log(`[WS] Client connected: User ${userId} (Total: ${clients.get(userId).size} connections)`);
    
    const monitors = await getUserMonitors(userId);
    ws.send(JSON.stringify({
        type: 'connected',
        message: 'WebSocket connection established',
        data: monitors
    }));
    
    ws.on('message', async (message) => {
        try {
            const data = JSON.parse(message.toString());
            
            switch (data.type) {
                case 'ping':
                    ws.send(JSON.stringify({ type: 'pong', timestamp: Date.now() }));
                    break;
                    
                case 'get_monitors':
                    const userMonitors = await getUserMonitors(userId);
                    ws.send(JSON.stringify({
                        type: 'monitors_update',
                        data: userMonitors
                    }));
                    break;
                    
                default:
                    ws.send(JSON.stringify({ type: 'error', message: 'Unknown message type' }));
            }
        } catch (error) {
            console.error('[WS] Error handling message:', error);
            ws.send(JSON.stringify({ type: 'error', message: 'Invalid message format' }));
        }
    });
    
    ws.on('close', () => {
        if (userId && clients.has(userId)) {
            clients.get(userId).delete(ws);
            if (clients.get(userId).size === 0) {
                clients.delete(userId);
            }
        }
        console.log(`[WS] Client disconnected: User ${userId}`);
    });
    
    ws.on('error', (error) => {
        console.error(`[WS] Error for user ${userId}:`, error.message);
    });
}

async function startPolling() {
    console.log('[POLL] Starting database polling for new codes...');
    
    let lastCheckTime = new Date();
    
    setInterval(async () => {
        try {
            const [newCodes] = await pool.execute(
                'SELECT m.user_id, m.phone, m.last_code, m.last_extracted_code, m.updated_at FROM monitors m WHERE m.updated_at > ? AND m.last_code IS NOT NULL AND m.last_code != ""',
                [lastCheckTime]
            );
            
            if (newCodes.length > 0) {
                console.log(`[POLL] Found ${newCodes.length} new codes`);
                
                const userCodes = new Map();
                newCodes.forEach(code => {
                    if (!userCodes.has(code.user_id)) {
                        userCodes.set(code.user_id, []);
                    }
                    userCodes.get(code.user_id).push(code);
                });
                
                for (const [userId, codes] of userCodes) {
                    for (const codeInfo of codes) {
                        await broadcastNewCode(userId, codeInfo.phone, codeInfo.last_extracted_code || codeInfo.last_code);
                    }
                    const userMonitors = await getUserMonitors(userId);
                    await broadcastMonitorUpdate(userId, userMonitors);
                }
            }
            
            lastCheckTime = new Date();
        } catch (error) {
            console.error('[POLL] Error during polling:', error);
        }
    }, 1000);
}

async function main() {
    await initDatabase();
    
    const wss = new WebSocket.Server({ port: PORT });
    
    console.log(`[WS] WebSocket server started on port ${PORT}`);
    
    startHeartbeatInterval(wss);
    
    wss.on('connection', handleConnection);
    
    startPolling();
    
    process.on('SIGINT', async () => {
        console.log('\n[WS] Shutting down...');
        wss.clients.forEach(client => client.close());
        await pool.end();
        process.exit(0);
    });
}

main().catch(console.error);
