class NotificationManager {
    constructor() {
        this.pusher = null;
        this.channel = null;
        this.notifications = [];
        this.isConnected = false;
        this.currentUserId = USER_ID || '';
        this.currentUserRole = USER_ROLE || '';
        this.pusherConfig = null;
        console.log('NotificationManager initialized with USER_ID:', this.currentUserId, 'USER_ROLE:', this.currentUserRole);
        this.init();
    }

    init() {
        console.log('Initializing NotificationManager...');
        this.createNotificationContainer();
        this.loadPusherConfig();
        this.setupEventListeners();
    }

    createNotificationContainer() {
        // Create notification container if it doesn't exist
        if (!document.getElementById('notification-container')) {
            const container = document.createElement('div');
            container.id = 'notification-container';
            container.className = 'notification-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 400px;
                max-height: 80vh;
                overflow-y: auto;
            `;
            document.body.appendChild(container);
            console.log('Notification container created');
        } else {
            console.log('Notification container already exists');
        }
    }

    async loadPusherConfig() {
        console.log('Loading Pusher configuration from server...');
        try {
            // Get Pusher configuration from server
            const response = await fetch('/api/NotificationAPI.php?action=config');
            console.log('Config response status:', response.status);
            
            if (response.ok) {
                const config = await response.json();
                console.log('Pusher config received from server:', config);
                this.pusherConfig = {
                    key: config.key,
                    cluster: config.cluster,
                    authEndpoint: '/api/NotificationAPI.php?action=auth'
                };
                console.log('Pusher config set:', this.pusherConfig);
                this.connectPusher();
            } else {
                console.error('Failed to load Pusher configuration, status:', response.status);
                // Fallback to default config
                this.pusherConfig = {
                    key: 'your_key',
                    cluster: 'ap1',
                    authEndpoint: '/api/NotificationAPI.php?action=auth'
                };
                console.log('Using fallback config:', this.pusherConfig);
                this.connectPusher();
            }
        } catch (error) {
            console.error('Error loading Pusher configuration:', error);
            // Fallback to default config
            this.pusherConfig = {
                key: 'your_key',
                cluster: 'ap1',
                authEndpoint: '/api/NotificationAPI.php?action=auth'
            };
            console.log('Using fallback config due to error:', this.pusherConfig);
            this.connectPusher();
        }
    }

    connectPusher() {
        console.log('Connecting to Pusher with config:', this.pusherConfig);
        try {
            // Load Pusher library if not already loaded
            if (typeof Pusher === 'undefined') {
                console.log('Pusher library not loaded, loading now...');
                this.loadPusherLibrary();
                return;
            }

            console.log('Creating Pusher instance...');
            this.pusher = new Pusher(this.pusherConfig.key, {
                cluster: this.pusherConfig.cluster,
                authEndpoint: this.pusherConfig.authEndpoint,
                auth: {
                    headers: {
                        'X-CSRF-Token': this.getCSRFToken()
                    }
                }
            });

            console.log('Pusher instance created, subscribing to notifications channel...');
            // Subscribe to notification channel
            this.channel = this.pusher.subscribe('notifications');
            console.log('Subscribed to channel:', this.channel.name);

            this.pusher.connection.bind('connected', () => {
                this.isConnected = true;
                console.log('✅ Pusher connected successfully');
            });

            this.pusher.connection.bind('disconnected', () => {
                this.isConnected = false;
                console.log('❌ Pusher disconnected');
            });

            this.pusher.connection.bind('error', (error) => {
                console.error('❌ Pusher connection error:', error);
                this.isConnected = false;
            });

            // Bind to notification events with detailed logging
            console.log('Binding to notification events...');
            
            this.channel.bind('request_created', (data) => {
                console.log('📨 Received request_created event:', data);
                this.handleEvent({ type: 'request_created', ...data });
            });

            this.channel.bind('request_updated', (data) => {
                console.log('📨 Received request_updated event:', data);
                this.handleEvent({ type: 'request_updated', ...data });
            });

            this.channel.bind('request_status_changed', (data) => {
                console.log('📨 Received request_status_changed event:', data);
                this.handleEvent({ type: 'request_status_changed', ...data });
            });

            this.channel.bind('request_comment_added', (data) => {
                console.log('📨 Received request_comment_added event:', data);
                this.handleEvent({ type: 'request_comment_added', ...data });
            });

            this.channel.bind('project_updated', (data) => {
                console.log('📨 Received project_updated event:', data);
                this.handleEvent({ type: 'project_updated', ...data });
            });

            this.channel.bind('task_updated', (data) => {
                console.log('📨 Received task_updated event:', data);
                this.handleEvent({ type: 'task_updated', ...data });
            });

            this.channel.bind('comment_added', (data) => {
                console.log('📨 Received comment_added event:', data);
                this.handleEvent({ type: 'comment_added', ...data });
            });

            this.channel.bind('time_entry_updated', (data) => {
                console.log('📨 Received time_entry_updated event:', data);
                this.handleEvent({ type: 'time_entry_updated', ...data });
            });

            this.channel.bind('test_event', (data) => {
                console.log('📨 Received test_event:', data);
                this.handleEvent({ type: 'test_event', ...data });
            });

            console.log('✅ All event bindings set up successfully');

        } catch (error) {
            console.error('❌ Failed to connect to Pusher:', error);
        }
    }

    loadPusherLibrary() {
        console.log('Loading Pusher library from CDN...');
        const script = document.createElement('script');
        script.src = 'https://js.pusher.com/8.2.0/pusher.min.js';
        script.onload = () => {
            console.log('✅ Pusher library loaded successfully');
            this.connectPusher();
        };
        script.onerror = () => {
            console.error('❌ Failed to load Pusher library');
        };
        document.head.appendChild(script);
    }

    getCSRFToken() {
        // Get CSRF token from meta tag or cookie
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
            const token = metaTag.getAttribute('content');
            console.log('CSRF token from meta tag:', token ? 'found' : 'not found');
            return token;
        }
        
        // Try to get from cookie
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'csrf_token') {
                console.log('CSRF token from cookie:', value ? 'found' : 'not found');
                return value;
            }
        }
        
        console.log('No CSRF token found');
        return '';
    }

    handleEvent(event) {
        console.log('🔍 Processing event:', event);
        
        // Filter events based on user permissions and relevance
        if (!this.shouldShowNotification(event)) {
            console.log('❌ Event filtered out - not showing notification');
            return;
        }

        console.log('✅ Event passed filter, creating notification...');
        const notification = this.createNotification(event);
        this.showNotification(notification);
    }

    shouldShowNotification(event) {
        console.log('🔍 Checking if should show notification for event:', event.type);
        
        // Don't show notifications for current user's own actions
        if (event.user_id === this.currentUserId) {
            console.log('❌ Filtered out - current user\'s own action');
            return false;
        }

        // Show different notifications based on user role
        switch (event.type) {
            case 'request_created':
            case 'request_updated':
            case 'request_status_changed':
            case 'request_comment_added':
                // Show to administrators and the request owner
                const shouldShow = this.currentUserRole === 'administrator' || event.user_id === this.currentUserId;
                console.log('Request event - should show:', shouldShow, '(role:', this.currentUserRole, ', user_id:', event.user_id, ')');
                return shouldShow;
            
            case 'project_updated':
            case 'task_updated':
            case 'comment_added':
            case 'time_entry_updated':
                // Show to project members (you can add more specific logic here)
                console.log('Project/Task event - showing to all users');
                return true;
            
            default:
                console.log('Default event - showing to all users');
                return true;
        }
    }

    createNotification(event) {
        console.log('📝 Creating notification for event:', event.type);
        const notification = {
            id: Date.now() + Math.random(),
            event: event,
            timestamp: new Date(),
            message: this.getMessage(event),
            type: this.getNotificationType(event)
        };
        console.log('Notification created:', notification);
        return notification;
    }

    getMessage(event) {
        switch (event.type) {
            case 'test_event':
                return event.message || 'Test notification';
            
            case 'request_created':
                return `新しい${this.getRequestTypeLabel(event.request_type)}申請が作成されました`;
            
            case 'request_updated':
                return `${this.getRequestTypeLabel(event.request_type)}申請が更新されました`;
            
            case 'request_status_changed':
                const statusLabel = event.action === 'approved' ? '承認' : '却下';
                return `${this.getRequestTypeLabel(event.request_type)}申請が${statusLabel}されました`;
            
            case 'request_comment_added':
                return `${this.getRequestTypeLabel(event.request_type)}申請にコメントが追加されました`;
            
            case 'project_updated':
                return 'プロジェクトが更新されました';
            
            case 'task_updated':
                return 'タスクが更新されました';
            
            case 'comment_added':
                return 'タスクにコメントが追加されました';
            
            case 'time_entry_updated':
                return '時間記録が更新されました';
            
            default:
                return '新しい通知があります';
        }
    }

    getRequestTypeLabel(type) {
        switch (type) {
            case 'leave': return '休暇届';
            case 'overtime': return '残業申請';
            case 'business_trip': return '出張申請';
            default: return type;
        }
    }

    getNotificationType(event) {
        switch (event.type) {
            case 'request_status_changed':
                return event.action === 'approved' ? 'success' : 'warning';
            case 'request_created':
            case 'request_updated':
                return 'info';
            case 'request_comment_added':
            case 'comment_added':
                return 'info';
            default:
                return 'info';
        }
    }

    showNotification(notification) {
        console.log('🎨 Showing notification:', notification.message);
        const container = document.getElementById('notification-container');
        const element = document.createElement('div');
        element.className = `notification notification-${notification.type}`;
        element.style.cssText = `
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            animation: slideIn 0.3s ease-out;
            position: relative;
        `;

        element.innerHTML = `
            <div class="notification-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-weight: bold; color: #333;">${notification.message}</span>
                <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; cursor: pointer; font-size: 18px; color: #999;">×</button>
            </div>
            <div class="notification-time" style="font-size: 12px; color: #666;">
                ${this.formatTime(notification.timestamp)}
            </div>
        `;

        container.appendChild(element);
        console.log('✅ Notification displayed in UI');

        // Auto remove after 10 seconds
        setTimeout(() => {
            if (element.parentNode) {
                element.style.animation = 'slideOut 0.3s ease-in';
                setTimeout(() => {
                    if (element.parentNode) {
                        element.remove();
                        console.log('🗑️ Notification auto-removed');
                    }
                }, 300);
            }
        }, 10000);
    }

    formatTime(date) {
        const now = new Date();
        const diff = now - date;
        
        if (diff < 60000) { // Less than 1 minute
            return '今';
        } else if (diff < 3600000) { // Less than 1 hour
            return Math.floor(diff / 60000) + '分前';
        } else if (diff < 86400000) { // Less than 1 day
            return Math.floor(diff / 3600000) + '時間前';
        } else {
            return date.toLocaleDateString('ja-JP');
        }
    }

    setupEventListeners() {
        // Add CSS animations
        if (!document.getElementById('notification-styles')) {
            const style = document.createElement('style');
            style.id = 'notification-styles';
            style.textContent = `
                @keyframes slideIn {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                
                @keyframes slideOut {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                }
                
                .notification-success {
                    border-left: 4px solid #28a745;
                }
                
                .notification-warning {
                    border-left: 4px solid #ffc107;
                }
                
                .notification-info {
                    border-left: 4px solid #17a2b8;
                }
            `;
            document.head.appendChild(style);
            console.log('✅ Notification styles added');
        }
    }

    disconnect() {
        if (this.pusher) {
            console.log('🔌 Disconnecting from Pusher...');
            this.pusher.disconnect();
            this.isConnected = false;
        }
    }

    // Method to trigger notifications from other parts of the application
    static triggerNotification(event) {
        console.log('🔔 Static triggerNotification called with:', event);
        if (window.notificationManager) {
            window.notificationManager.handleEvent(event);
        } else {
            console.error('❌ NotificationManager not initialized');
        }
    }
}

// Initialize notification manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 DOM loaded, initializing NotificationManager...');
    window.notificationManager = new NotificationManager();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.notificationManager) {
        console.log('🧹 Cleaning up NotificationManager...');
        window.notificationManager.disconnect();
    }
}); 