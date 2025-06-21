// Firebase Notification Manager
class NotificationManager {
    constructor() {
        this.notifications = [];
        this.isConnected = false;
        this.firebase = null;
        this.database = null;
        this.userId = USER_ID || '';
        this.userRole = USER_ROLE || '';
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
            
            // Đăng ký userId vào danh sách connected_users
            this.registerConnectedUser();
            
            // Lắng nghe thay đổi danh sách connected_users
            this.listenConnectedUsers();
            
            // Đọc last_notification_id khi load trang
            await this.syncLatestNotification();
            this.renderNotificationList();
            this.listenLastNotificationId();
            this.setupMarkAll();
            
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
    
    async handleNotification(notification) {
        if (notification && notification.data.notification_id) {
            // Kiểm tra nếu notification_id đã có trong danh sách thì không fetch lại
            if (this.notifications.some(n => n.id == notification.data.notification_id)) {
                return;
            }
            const notif = await this.fetchNotificationDetail(notification.data.notification_id);
            if (notif) {
                this.notifications.unshift(notif);
                this.updateNotificationCount();
                this.renderNotificationList();
            }
        }
    }
    
    // Lấy danh sách thông báo mới nhất từ API
    async fetchNotificationsFromAPI() {
        if (!this.userId) return;
        try {
            const response = await fetch(`/api/NotificationAPI.php?method=get_notifications&user_id=${encodeURIComponent(this.userId)}&limit=20`);
            const result = await response.json();
            if (result.notifications) {
                this.notifications = result.notifications;
                this.updateNotificationCount();
                this.renderNotificationList();
            }
        } catch (e) {
            console.error('Failed to fetch notifications from API', e);
        }
    }
    
    // Lấy chi tiết 1 notification từ API (theo notification_id)
    async fetchNotificationDetail(notification_id) {
        if (!notification_id) return null;
        try {
            // API get_notifications trả về list, nên lấy 1 bản ghi
            const response = await fetch(`/api/NotificationAPI.php?method=get_notifications&user_id=${encodeURIComponent(this.userId)}&limit=1`);
            const result = await response.json();
            if (result.notifications && result.notifications.length > 0) {
                // Tìm đúng notification_id
                return result.notifications.find(n => n.id == notification_id) || result.notifications[0];
            }
        } catch (e) {
            console.error('Failed to fetch notification detail', e);
        }
        return null;
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
    
    renderNotificationList() {
        const list = this.notifications || [];
        const ul = document.querySelector('#notification_list .dropdown-notifications-list ul');
        if (!ul) return;
        ul.innerHTML = '';
        if (list.length === 0) {
            ul.innerHTML = `
                <li class="list-group-item list-group-item-action dropdown-notifications-item marked-as-read">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <h6 class="small mb-1">通知はありません 🎉</h6>
                            <small class="mb-1 d-block text-body">作成中...</small>
                        </div>
                    </div>
                </li>
            `;
            this.updateNotificationDot();
            return;
        }
        list.forEach((n, idx) => {
            const data = n.data ? (typeof n.data === 'string' ? JSON.parse(n.data) : n.data) : {};
            const li = document.createElement('li');
            li.className = `list-group-item list-group-item-action dropdown-notifications-item${n.is_read == 1 ? ' marked-as-read' : ''}`;
            li.innerHTML = `
                <div class="d-flex">
                    <div class="flex-shrink-0 me-3">
                        <div class="avatar">
                            <img src="${data.avatar || '/assets/img/avatars/1.png'}" alt class="rounded-circle" />
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">${this.escapeHtml(n.title || '通知')}</h6>
                        <small class="mb-1 d-block text-body">${this.escapeHtml(n.message || '')}</small>
                        <small class="text-body-secondary">${n.created_at ? n.created_at : ''}</small>
                    </div>
                    <div class="flex-shrink-0 dropdown-notifications-actions">
                        <a href="javascript:void(0)" class="dropdown-notifications-read"
                            ><span class="badge badge-dot"></span
                        ></a>
                    </div>
                </div>
            `;
            // Đánh dấu đã đọc/hoặc chưa đọc khi click vào icon read
            li.querySelector('.dropdown-notifications-read').addEventListener('click', async (e) => {
                e.stopPropagation();
                if (n.is_read == 1) {
                    await this.markAsUnread(n.id);
                    n.is_read = 0;
                    li.classList.remove('marked-as-read');
                } else {
                    await this.markAsRead(n.id);
                    n.is_read = 1;
                    li.classList.add('marked-as-read');
                }
                this.renderNotificationList();
            });
            // Đánh dấu đã đọc khi click vào notification (trừ icon read)
            li.addEventListener('click', async (evt) => {
                if (evt.target.classList.contains('dropdown-notifications-read')) return;
                if (n.is_read == 0) {
                    await this.markAsRead(n.id);
                    n.is_read = 1;
                    li.classList.add('marked-as-read');
                }
                // if (data.url) {
                //     window.location.href = data.url;
                // }
            });
            ul.appendChild(li);
        });
        this.updateNotificationDot();
    }

    async markAsRead(notification_id) {
        if (!notification_id || !this.userId) return;
        try {
            await fetch('/api/NotificationAPI.php?method=mark_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `user_id=${encodeURIComponent(this.userId)}&notification_id=${encodeURIComponent(notification_id)}`
            });
        } catch (e) {
            // ignore
        }
    }

    async markAsUnread(notification_id) {
        if (!notification_id || !this.userId) return;
        try {
            await fetch('/api/NotificationAPI.php?method=mark_unread', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `user_id=${encodeURIComponent(this.userId)}&notification_id=${encodeURIComponent(notification_id)}`
            });
        } catch (e) {
            // ignore
        }
    }

    async syncLatestNotification() {
        if (!this.userId) return;
        // Lấy 20 notification mới nhất từ API khi load trang
        try {
            const response = await fetch(`/api/NotificationAPI.php?method=get_notifications&user_id=${encodeURIComponent(this.userId)}&limit=20`);
            const result = await response.json();
            if (result.notifications) {
                this.notifications = result.notifications;
                this.updateNotificationCount();
            } else {
                this.notifications = [];
                this.updateNotificationCount();
            }
        } catch (e) {
            this.notifications = [];
            this.updateNotificationCount();
        }
    }

    listenLastNotificationId() {
        if (!this.userId) return;
        const ref = this.database.ref('user_meta/user_' + this.userId + '/last_notification_id');
        ref.on('value', async (snapshot) => {
            const lastId = snapshot && snapshot.val();
            if (lastId && !this.notifications.some(n => n.id == lastId)) {
                const notif = await this.fetchNotificationDetail(lastId);
                if (notif) {
                    this.notifications.unshift(notif);
                    this.updateNotificationCount();
                    this.renderNotificationList();
                }
            }
        });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Thêm sự kiện cho nút mark all
    setupMarkAll() {
        const btn = document.querySelector('#mark_all');
        if (!btn) return;
        btn.addEventListener('click', async () => {
            const unread = this.notifications.filter(n => n.is_read == 0);
            if (unread.length === 0) return;
            const ids = unread.map(n => n.id);
            // Gọi API mark_read_multi
            await fetch('/api/NotificationAPI.php?method=mark_read_multi', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `user_id=${encodeURIComponent(this.userId)}&notification_ids=${encodeURIComponent(ids.join(','))}`
            });
            for (const n of unread) {
                n.is_read = 1;
            }
            this.renderNotificationList();
        });
    }

    updateNotificationDot() {
        const dot = document.getElementById('notification_dot');
        if (!dot) return;
        const hasUnread = this.notifications.some(n => n.is_read == 0);
        dot.style.display = hasUnread ? 'inline-block' : 'none';
    }

    registerConnectedUser() {
        if (!this.userId || !this.database) return;
        const ref = this.database.ref('connected_users/' + this.userId);
        ref.set(true);
        ref.onDisconnect().remove();
        // Đảm bảo xóa khi unload trang (trường hợp onDisconnect không kịp)
        window.addEventListener('beforeunload', () => {
            ref.remove();
        });
    }

    listenConnectedUsers() {
        if (!this.database) return;
        const ref = this.database.ref('connected_users');
        ref.on('value', (snapshot) => {
            const connected = snapshot.val() || {};
            const userIds = Object.keys(connected);
            // Tìm tất cả .avatar có data-userid
            document.querySelectorAll('.avatar[data-userid]').forEach(avatar => {
                const uid = avatar.getAttribute('data-userid');
                if (userIds.includes(uid)) {
                    avatar.classList.add('avatar-online');
                } else {
                    avatar.classList.remove('avatar-online');
                }
            });
        });
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