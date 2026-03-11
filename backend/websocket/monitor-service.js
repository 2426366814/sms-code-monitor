/**
 * Monitor Service - High Performance Verification Code Monitor
 * 
 * Features:
 * - Batch processing (50 monitors per batch)
 * - Connection pooling
 * - Memory efficient
 * - 5 second refresh interval
 */

require('dotenv').config({ path: __dirname + '/../config/.env' });
const mysql = require('mysql2/promise');

const DB_CONFIG = {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASS || process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'jm',
    waitForConnections: true,
    connectionLimit: 5,
    queueLimit: 0
};

class MonitorService {
    constructor() {
        this.pool = null;
        this.batchSize = 50;
        this.refreshInterval = 5000;
        this.isRunning = false;
        this.stats = {
            total: 0,
            success: 0,
            noCode: 0,
            error: 0
        };
        this.onCodeReceived = null;
    }

    async init() {
        this.pool = mysql.createPool(DB_CONFIG);
        console.log('[MonitorService] Database pool created');
    }

    async getActiveMonitors() {
        const [rows] = await this.pool.execute(
            'SELECT id, user_id, phone, url, last_extracted_code FROM monitors ORDER BY updated_at DESC'
        );
        return rows;
    }

    async fetchCode(url) {
        return new Promise((resolve) => {
            const https = require('https');
            const http = require('http');
            
            const client = url.startsWith('https') ? https : http;
            const timeout = setTimeout(() => {
                resolve({ status: 'error', code: '', message: 'Timeout' });
            }, 10000);

            client.get(url, (res) => {
                let data = '';
                res.on('data', chunk => data += chunk);
                res.on('end', () => {
                    clearTimeout(timeout);
                    try {
                        const result = this.extractCode(data);
                        resolve(result);
                    } catch (e) {
                        resolve({ status: 'error', code: '', message: e.message });
                    }
                });
            }).on('error', (e) => {
                clearTimeout(timeout);
                resolve({ status: 'error', code: '', message: e.message });
            });
        });
    }

    extractCode(response) {
        try {
            const jsonData = JSON.parse(response);
            
            if (jsonData && jsonData.data && jsonData.data.code) {
                const apiCode = jsonData.data.code;
                if (apiCode && typeof apiCode === 'string') {
                    const match = apiCode.match(/\b(\d{4,8})\b/);
                    if (match && this.isValidCode(match[1])) {
                        return {
                            status: 'success',
                            code: match[1],
                            message: 'Verification code extracted',
                            rawResponse: response
                        };
                    }
                }
            }
        } catch (e) {
            // Not JSON, try regex patterns
        }

        const patterns = [
            /code\s+(?:is\s+)?(\d{4,8})/i,
            /verification\s+code\s+(?:is\s+)?(\d{4,8})/i,
            /(\d{4,8})\s+is\s+your\s+(?:verification\s+)?code/i,
            /验证码[是为：:\s]*(\d{4,8})/i,
            /动态码[是为：:\s]*(\d{4,8})/i,
            /\[.*?\].*?(\d{4,8})/i,
        ];

        for (const pattern of patterns) {
            const match = response.match(pattern);
            if (match && this.isValidCode(match[1])) {
                return {
                    status: 'success',
                    code: match[1],
                    message: 'Verification code extracted',
                    rawResponse: response
                };
            }
        }

        return {
            status: 'no-code',
            code: '',
            message: 'No verification code found',
            rawResponse: response
        };
    }

    isValidCode(code) {
        const len = code.length;
        if (len < 4 || len > 8) return false;
        if (/^20[2-9]\d$/.test(code)) return false;
        if (len === 8 && /^20\d{6}$/.test(code)) return false;
        if (/^1[6-9]\d{8,}$/.test(code)) return false;
        return true;
    }

    async updateMonitor(monitor, extracted) {
        const now = Math.floor(Date.now() / 1000);
        const nowStr = new Date().toISOString().slice(0, 19).replace('T', ' ');
        
        const updateData = {
            last_code: extracted.rawResponse ? extracted.rawResponse.substring(0, 500) : '',
            last_update: nowStr,
            last_update_timestamp: now * 1000,
            updated_at: nowStr,
            status: extracted.status,
            message: extracted.message
        };

        if (extracted.status === 'success' && extracted.code && extracted.code !== monitor.last_extracted_code) {
            updateData.last_extracted_code = extracted.code;
            updateData.code_timestamp = now * 1000;
            updateData.code_time_str = nowStr;
            
            await this.saveCodeHistory(monitor, extracted.code, extracted.rawResponse);
            
            if (this.onCodeReceived) {
                this.onCodeReceived(monitor.user_id, monitor.phone, extracted.code);
            }
        }

        await this.pool.execute(
            `UPDATE monitors SET 
                last_code = ?, last_update = ?, last_update_timestamp = ?, 
                updated_at = ?, status = ?, message = ?,
                last_extracted_code = ?, code_timestamp = ?, code_time_str = ?
            WHERE id = ?`,
            [
                updateData.last_code,
                updateData.last_update,
                updateData.last_update_timestamp,
                updateData.updated_at,
                updateData.status,
                updateData.message,
                updateData.last_extracted_code || monitor.last_extracted_code,
                updateData.code_timestamp || 0,
                updateData.code_time_str || '',
                monitor.id
            ]
        );
    }

    async saveCodeHistory(monitor, code, rawResponse) {
        try {
            await this.pool.execute(
                `INSERT INTO verification_codes (user_id, monitor_id, phone, code, message, source_url, received_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)`,
                [monitor.user_id, monitor.id, monitor.phone, code, rawResponse?.substring(0, 500) || '', monitor.url, new Date().toISOString().slice(0, 19).replace('T', ' ')]
            );
        } catch (e) {
            // Ignore duplicate errors
        }
    }

    async processBatch(monitors) {
        const results = [];
        
        for (let i = 0; i < monitors.length; i += this.batchSize) {
            const batch = monitors.slice(i, i + this.batchSize);
            
            const promises = batch.map(async (monitor) => {
                try {
                    const extracted = await this.fetchCode(monitor.url);
                    await this.updateMonitor(monitor, extracted);
                    
                    if (extracted.status === 'success') {
                        this.stats.success++;
                    } else if (extracted.status === 'no-code') {
                        this.stats.noCode++;
                    } else {
                        this.stats.error++;
                    }
                    
                    return { monitor, extracted };
                } catch (e) {
                    this.stats.error++;
                    return { monitor, error: e.message };
                }
            });
            
            await Promise.all(promises);
            
            // Small delay between batches to reduce load
            if (i + this.batchSize < monitors.length) {
                await new Promise(r => setTimeout(r, 100));
            }
        }
        
        return results;
    }

    async runOnce() {
        const startTime = Date.now();
        
        this.stats = { total: 0, success: 0, noCode: 0, error: 0 };
        
        const monitors = await this.getActiveMonitors();
        this.stats.total = monitors.length;
        
        console.log(`[MonitorService] Processing ${monitors.length} monitors...`);
        
        await this.processBatch(monitors);
        
        const elapsed = Date.now() - startTime;
        console.log(`[MonitorService] Done in ${elapsed}ms - Success: ${this.stats.success}, NoCode: ${this.stats.noCode}, Error: ${this.stats.error}`);
        
        return this.stats;
    }

    start() {
        if (this.isRunning) return;
        this.isRunning = true;
        
        console.log(`[MonitorService] Starting with ${this.batchSize} batch size, ${this.refreshInterval}ms interval`);
        
        // Run immediately
        this.runOnce();
        
        // Then run on interval
        this.intervalId = setInterval(() => {
            this.runOnce();
        }, this.refreshInterval);
    }

    stop() {
        this.isRunning = false;
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
        console.log('[MonitorService] Stopped');
    }

    async close() {
        this.stop();
        if (this.pool) {
            await this.pool.end();
        }
    }
}

module.exports = MonitorService;
