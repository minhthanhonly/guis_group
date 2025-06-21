// Firebase Notification Manager
class NotificationManager {
    constructor() {
        this.notifications = [];
        this.isConnected = false;
        this.firebase = null;
        this.database = null;
        this.userId = USER_ID || '';
        this.userRole = USER_ROLE || '';
        this.channels = [];
        
        this.init();
    }
    
    async init() {
        try {
            console.log('Initializing Firebase Notification Manager...');
            
            // Load Firebase SDK
            await this.loadFirebaseSDK();
            
            // Get Firebase config
            const config = await this.getFirebaseConfig();
            if (!config) {
                console.error('Failed to get Firebase configuration');
                return;
            }
            
            // Initialize Firebase
            this.firebase = firebase.initializeApp(config);
            this.database = this.firebase.database();
            
            // Setup channels based on user role
            this.setupChannels();
            
            // Start listening for notifications
            this.startListening();
            
            console.log('Firebase Notification Manager initialized');
            
        } catch (error) {
            console.error('Failed to initialize Firebase Notification Manager:', error);
        }
    }
    
    async loadFirebaseSDK() {
        return new Promise((resolve, reject) => {
            console.log('Loading Firebase SDK...');
            
            // Check if Firebase is already loaded
            if (window.firebase) {
                console.log('Firebase SDK already loaded');
                resolve();
                return;
            }
            
            // Load Firebase SDK v9 (modular version)
            const script = document.createElement('script');
            script.src = 'https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js';
            script.onload = () => {
                console.log('Firebase App SDK loaded');
                const dbScript = document.createElement('script');
                dbScript.src = 'https://www.gstatic.com/firebasejs/9.22.0/firebase-database-compat.js';
                dbScript.onload = () => {
                    console.log('Firebase Database SDK loaded');
                    resolve();
                };
                dbScript.onerror = (e) => {
                    console.error('Failed to load Firebase Database SDK');
                    reject(e);
                };
                document.head.appendChild(dbScript);
            };
            script.onerror = (e) => {
                console.error('Failed to load Firebase App SDK');
                reject(e);
            };
            document.head.appendChild(script);
        });
    }
    
    async getFirebaseConfig() {
        try {
            console.log('Getting Firebase configuration from API...');
            const response = await fetch('/api/NotificationAPI.php?method=get_config');
            const config = await response.json();
            
            if (config.error) {
                console.error('Firebase config error:', config.error);
                return null;
            }
            
            console.log('Firebase configuration retrieved successfully');
            return config;
        } catch (error) {
            console.error('Failed to get Firebase config:', error);
            return null;
        }
    }
    
    setupChannels() {
        this.channels = ['global']; // Always listen to global channel
        
        if (this.userId) {
            this.channels.push('user_' + this.userId);
        }
        
        if (this.userRole === 'administrator') {
            this.channels.push('admins');
        }
        
        console.log('Listening to channels:', this.channels);
    }
    
    startListening() {
        this.channels.forEach(channel => {
            console.log('Setting up listener for channel:', channel);
            const notificationsRef = this.database.ref('notifications/' + channel);
            
            notificationsRef.on('child_added', (snapshot) => {
                const notification = snapshot.val();
                if (notification && notification.event) {
                    console.log('Received notification on channel', channel, ':', notification);
                    this.handleNotification(notification);
                }
            });
            
            // Mark as connected
            this.isConnected = true;
        });
        
        // Listen for connection state changes
        const connectedRef = this.database.ref('.info/connected');
        connectedRef.on('value', (snap) => {
            this.isConnected = snap.val() === true;
            console.log('Firebase connection state:', this.isConnected ? 'connected' : 'disconnected');
        });
    }
    
    handleNotification(notification) {
        console.log('Processing notification:', notification.event);
        
        // Filter notifications based on user role and permissions
        if (!this.shouldShowNotification(notification)) {
            return;
        }
        
        // Add to notifications array
        this.notifications.unshift({
            id: Date.now() + Math.random(),
            ...notification,
            timestamp: notification.timestamp || Date.now()
        });
        
        // Keep only last 50 notifications
        if (this.notifications.length > 50) {
            this.notifications = this.notifications.slice(0, 50);
        }
        
        // Show notification
        this.showNotification(notification);
        
        // Update notification count
        this.updateNotificationCount();
    }
    
    shouldShowNotification(notification) {
        const event = notification.event;
        const data = notification.data || {};
        
        // Always show global notifications
        if (event === 'global_notification') {
            return true;
        }
        
        // Show user-specific notifications
        if (event === 'user_notification' && data.user_id === this.userId) {
            return true;
        }
        
        // Show admin notifications for administrators
        if (event === 'admin_notification' && this.userRole === 'administrator') {
            return true;
        }
        
        // Show project-related notifications
        if (event === 'project_update' || event === 'task_update') {
            return true;
        }
        
        // Show form-related notifications
        if (event === 'form_request_update' || event === 'form_comment') {
            return true;
        }
        
        return false;
    }
    
    showNotification(notification) {
        const event = notification.event;
        const data = notification.data || {};
        
        let title = '通知';
        let message = '';
        let type = 'info';
        
        switch (event) {
            case 'project_update':
                title = 'プロジェクト更新';
                message = `プロジェクト「${data.project_name || 'Unknown'}」が更新されました`;
                type = 'info';
                break;
                
            case 'task_update':
                title = 'タスク更新';
                message = `タスク「${data.task_name || 'Unknown'}」の状態が変更されました`;
                type = 'info';
                break;
                
            case 'form_request_update':
                title = '申請更新';
                message = `申請「${data.request_type || 'Unknown'}」の状態が変更されました`;
                type = data.status === 'approved' ? 'success' : 
                       data.status === 'rejected' ? 'error' : 'info';
                break;
                
            case 'form_comment':
                title = '新しいコメント';
                message = `${data.commenter || 'Someone'}がコメントを追加しました`;
                type = 'info';
                break;
                
            case 'time_entry':
                title = 'タイムエントリ';
                message = `新しいタイムエントリが追加されました`;
                type = 'info';
                break;
                
            case 'global_notification':
                title = data.title || '通知';
                message = data.message || '';
                type = data.type || 'info';
                break;
                
            default:
                title = data.title || '通知';
                message = data.message || '';
                type = data.type || 'info';
        }
        
        this.createNotificationElement(title, message, type, data);
    }
    
    createNotificationElement(title, message, type, data) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${this.getBootstrapType(type)} alert-dismissible fade show notification-toast`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border: none;
            border-radius: 8px;
            animation: slideInRight 0.3s ease-out;
        `;
        
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="alert-heading mb-1">${this.escapeHtml(title)}</h6>
                    <p class="mb-0 small">${this.escapeHtml(message)}</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        // Add click handler for navigation
        if (data.url) {
            notification.style.cursor = 'pointer';
            notification.addEventListener('click', () => {
                window.location.href = data.url;
            });
        }
        
        // Add to page
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }
        }, 5000);
    }
    
    getBootstrapType(type) {
        switch (type) {
            case 'success': return 'success';
            case 'error': return 'danger';
            case 'warning': return 'warning';
            case 'info': 
            default: return 'info';
        }
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    updateNotificationCount() {
        const count = this.notifications.length;
        const countElement = document.getElementById('notification-count');
        
        if (countElement) {
            countElement.textContent = count;
            countElement.style.display = count > 0 ? 'inline' : 'none';
        }
    }
    
    // Public methods
    getNotifications() {
        return this.notifications;
    }
    
    clearNotifications() {
        this.notifications = [];
        this.updateNotificationCount();
    }
    
    isFirebaseConnected() {
        return this.isConnected;
    }
    
    // Test method
    async testConnection() {
        try {
            const response = await fetch('/api/NotificationAPI.php?method=test');
            const result = await response.json();
            console.log('Firebase connection test:', result);
            return result;
        } catch (error) {
            console.error('Firebase connection test failed:', error);
            return { success: false, error: error.message };
        }
    }
}

// Initialize notification manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.notificationManager = new NotificationManager();
});

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .notification-toast {
        transition: all 0.3s ease;
    }
    
    .notification-toast:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.2) !important;
    }
`;
document.head.appendChild(style); 