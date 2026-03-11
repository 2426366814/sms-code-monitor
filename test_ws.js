const WebSocket = require('ws');
const ws = new WebSocket('wss://jm.91wz.org/ws?token=test');
ws.on('open', () => console.log('WebSocket connected!'));
ws.on('message', (data) => { console.log('Received:', data.toString()); ws.close(); });
ws.on('error', (err) => console.log('Error:', err.message));
setTimeout(() => process.exit(0), 5000);
