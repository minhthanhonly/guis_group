const WebSocket = require('ws');
const http = require('http');

// Create HTTP server
const server = http.createServer((req, res) => {
    // Handle CORS
    res.writeHead(200, {
        'Access-Control-Allow-Origin': 'http://localhost:8080',
        'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type'
    });
    res.end();
});

// Create WebSocket server
const wss = new WebSocket.Server({ server });

// Store connected users
const connectedUsers = new Map();

// Handle WebSocket connections
wss.on('connection', (ws, req) => {
    console.log('New connection attempt');
    console.log('Request URL:', req.url);
    
    try {
        // Extract user ID from the URL query parameters
        const url = new URL(req.url, `http://${req.headers.host}`);
        const userId = url.searchParams.get('userId');
        
        console.log('User ID from request:', userId);
        
        if (!userId) {
            console.log('No user ID provided, closing connection');
            ws.close(1008, 'User ID is required');
            return;
        }
        const wsId = Date.now();
        console.log('WebSocket ID:', wsId);



        // Add user to connected users
        connectedUsers.set(wsId, { ws, userId });
        console.log(`User ${userId} connected successfully`);

        // Broadcast user online status to all connected users
        broadcastUserStatus(userId, true);

        // Handle messages from client
        ws.on('message', (message) => {
            try {
                const data = JSON.parse(message);
                console.log(`Received message from ${userId}:`, data);
            } catch (error) {
                console.error('Error parsing message:', error);
            }
        });

        // Handle user disconnection
        ws.on('close', () => {
            // Remove this specific connection
            connectedUsers.delete(wsId);
            console.log(`One connection closed for user ${userId}`);

            // Check if user still has other active connections before broadcasting offline status
            let userStillConnected = false;
            connectedUsers.forEach((client) => {
                if (client.ws !== ws && client.userId === userId) {
                    userStillConnected = true;
                }
            });

            // Only broadcast offline status if user has no other active connections
            if (!userStillConnected) {
                console.log(`User ${userId} fully disconnected - no active connections remain`);
                broadcastUserStatus(userId, false);
            }
        });

        // Handle errors
        ws.on('error', (error) => {
            console.error(`Error for user ${userId}:`, error);
            connectedUsers.delete(wsId);
             // Check if user still has other active connections before broadcasting offline status
             let userStillConnected = false;
             connectedUsers.forEach((client) => {
                 if (client.ws !== ws && client.userId === userId) {
                     userStillConnected = true;
                 }
             });
 
             // Only broadcast offline status if user has no other active connections
             if (!userStillConnected) {
                 console.log(`User ${userId} fully disconnected - no active connections remain`);
                 broadcastUserStatus(userId, false);
             }
        });
    } catch (error) {
        console.error('Error in connection handler:', error);
        ws.close(1011, 'Internal server error');
    }
});

// Function to broadcast user status to all connected users
function broadcastUserStatus(userId, isOnline) {
    const message = JSON.stringify({
        type: 'userStatus',
        userId,
        isOnline,
        timestamp: new Date().toISOString()
    });

    wss.clients.forEach((client) => {
        if (client.readyState === WebSocket.OPEN) {
            client.send(message);
        }
    });
}

// Start the server
const PORT = 3001;
server.listen(PORT, () => {
    console.log(`WebSocket server is running on port ${PORT}`);
});
