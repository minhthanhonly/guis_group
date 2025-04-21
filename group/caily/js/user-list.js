class UserList {
    constructor() {
        this.container = document.querySelector('.user-list-container');
        this.toggleBtn = document.querySelector('.user-list-toggle');
        this.userList = document.querySelector('.user-list');
        this.isHidden = true;
        if(this.isHidden) {
            this.container.classList.add('hidden');
        }
        
        this.init();
    }

    init() {
        this.toggleBtn.addEventListener('click', () => this.toggleUserList());
        this.loadUsers();
       
    }

    connectWebSocket() {
        if (userId) {
            const wsUrl = 'ws://localhost:3001?userId=' + userId;
            let ws;
            let reconnectAttempts = 0;
            const maxReconnectAttempts = 5;
            const reconnectDelay = 3000; // 3 seconds
            console.log('Attempting to connect to WebSocket...');
            ws = new WebSocket(wsUrl);

            ws.onopen = function() {
                console.log('WebSocket connection opened successfully');
                reconnectAttempts = 0; // Reset reconnect attempts on successful connection
            };

            ws.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    console.log('WebSocket message received:', data);
                    // Handle the message based on its type
                    if (data.type === 'userStatus') {
                        console.log(`User ${data.userId} is ${data.isOnline ? 'online' : 'offline'}`);
                        this.setUserStatus(data.userId, data.isOnline ? 'online' : 'offline');
                    }
                } catch (error) {
                    console.error('Error parsing WebSocket message:', error);
                }
            };

            ws.onclose = function(event) {
                console.log('WebSocket connection closed:', event.code, event.reason);
                
                // Attempt to reconnect if we haven't exceeded max attempts
                if (reconnectAttempts < maxReconnectAttempts) {
                    reconnectAttempts++;
                    console.log(`Attempting to reconnect (${reconnectAttempts}/${maxReconnectAttempts})...`);
                    setTimeout(connectWebSocket, reconnectDelay);
                } else {
                    console.error('Max reconnection attempts reached');
                }
            };

            ws.onerror = function(error) {
                console.error('WebSocket error:', error);
            };
    
        } else {
            console.error('No user ID available for WebSocket connection');
        }
    }

    toggleUserList() {
        this.isHidden = !this.isHidden;
        this.container.classList.toggle('hidden', this.isHidden);
        this.toggleBtn.innerHTML = this.isHidden ? '👥' : '✕';
    }

    async loadUsers() {
        try {
            const response = await fetch('/api/index.php?model=user&method=getList');
            const data = await response.json();
            if (data.list) {
                this.renderUsers(data.list);
                this.connectWebSocket();
            }
        } catch (error) {
            console.error('Error loading users:', error);
        }
    }

    renderUsers(users) {
        this.userList.innerHTML = Object.values(users).map(user => `
            <div class="user-item">
                <div class="user-avatar">${this.getInitials(user.realname)}</div>
                <div class="user-info">
                    <div class="user-name">${user.realname}</div>
                    <div class="user-status">
                        <span class="status-indicator" data-status="offline" data-user-id="${user.userid}"></span>
                         <span class="status-text" data-user-id="${user.userid}">Offline</span>
                    </div>
                </div>
            </div>
        `).join('');
    }

    getInitials(name) {
        return name
            .split(' ')
            .map(word => word[0])
            .join('')
            .toUpperCase();
    }

    setUserStatus(userId, status) {
        const statusIndicator = document.querySelector(`.status-indicator[data-user-id="${userId}"]`);
        const statusText = document.querySelector(`.status-text[data-user-id="${userId}"]`);
        statusIndicator.setAttribute('data-status', status);
        statusText.textContent = status;
    }
} 

document.addEventListener('DOMContentLoaded', () => {
    // add button to the bottom of the page
    const button = document.createElement('button');
    button.innerHTML = 'User List';
    button.classList.add('user-list-toggle');
    document.body.appendChild(button);


    // add list
    const list = document.createElement('div');
    list.classList.add('user-list');

    // add container
    const container = document.createElement('div');
    container.classList.add('user-list-container');
 
    document.body.appendChild(container);

    container.appendChild(list);
    new UserList();
});
