// Firebase Notification Manager
class NotificationManager {
    constructor() {
        this.notifications = [];
        this.isConnected = false;
        this.firebase = null;
        this.database = null;
        this.userId = USER_ID || '';
        this.userRole = USER_ROLE || '';
        this.notificationPermission = 'default';
        this.originalTitle = document.title;
        this.flashInterval = null;
        this.isFlashing = false;
        this.bridgeHealthUrl = 'http://127.0.0.1:34567/health';
        this.bridgeShowUrlCandidates = [
            'http://127.0.0.1:34567/window/show',
            'http://127.0.0.1:34567/show-window',
            'http://127.0.0.1:34567/focus-window'
        ];
        this.bridgeNotifyUrl = 'http://127.0.0.1:34567/get_notify';
        this.bridgeStatus = {
            ok: false,
            checkedAt: 0
        };
        this.autoRefreshMs = 30000;
        this.autoRefreshTimer = null;
        this.isSyncingLatest = false;
        this.init();
    }
    
    async init() {
        try {
            
            // Request notification permission
            await this.requestNotificationPermission();
            
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
            this.startAutoRefreshNotifications();
            this.setupMarkAll();
            
            // Thông báo đã sẵn sàng
            document.dispatchEvent(new Event('notificationManagerReady'));
            
            // Setup page visibility listener để dừng flash khi user quay lại tab
            this.setupPageVisibilityListener();
            this.setupElectronWindowTrigger();
            
        } catch (error) {
            console.error('Failed to initialize Firebase Notification Manager:', error);
        }
    }

    startAutoRefreshNotifications() {
        if (!this.userId || this.autoRefreshTimer) return;
        this.autoRefreshTimer = setInterval(async () => {
            await this.syncLatestNotification();
            this.renderNotificationList();
        }, this.autoRefreshMs);
    }
    
    setupPageVisibilityListener() {
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                // User quay lại tab, dừng flash và restore title
                this.stopFlashingTitle();
            }
        });
        
        // Dừng flash khi window được focus
        window.addEventListener('focus', () => {
            this.stopFlashingTitle();
        });
    }

    async isNotificationBridgeOpen() {
        const now = Date.now();
        // Cache ngắn để tránh gọi health endpoint quá dày.
        if ((now - this.bridgeStatus.checkedAt) < 5000) {
            return this.bridgeStatus.ok === true;
        }

        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 1500);
        try {
            const response = await fetch(this.bridgeHealthUrl, {
                method: 'GET',
                signal: controller.signal
            });
            if (!response.ok) {
                this.bridgeStatus = { ok: false, checkedAt: now };
                return false;
            }
            const body = await response.json();
            const isOk = !!(body && body.ok === true);
            this.bridgeStatus = { ok: isOk, checkedAt: now };
            return isOk;
        } catch (e) {
            this.bridgeStatus = { ok: false, checkedAt: now };
            return false;
        } finally {
            clearTimeout(timeout);
        }
    }

    async callBridgeAction(url, method) {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 1200);
        try {
            const options = {
                method,
                signal: controller.signal
            };
            if (method === 'POST') {
                options.headers = { 'Content-Type': 'application/json' };
                options.body = JSON.stringify({ action: 'show-window' });
            }

            const response = await fetch(url, options);
            console.log(response);
            if (!response.ok) return false;
            if (response.status === 204) return true;

            const text = await response.text();
            console.log(text);
            if (!text) return true;
            try {
                const body = JSON.parse(text);
                console.log(body);
                return body.ok !== false;
            } catch (e) {
                return true;
            }
        } catch (e) {
            return false;
        } finally {
            clearTimeout(timeout);
        }
    }

    async requestElectronShowWindow() {
        for (const url of this.bridgeShowUrlCandidates) {
            const postOk = await this.callBridgeAction(url, 'POST');
            if (postOk) return true;
            const getOk = await this.callBridgeAction(url, 'GET');
            if (getOk) return true;
        }
        return false;
    }

    async notifyBridgeNewNotification() {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 1200);
        try {
            const postRes = await fetch(this.bridgeNotifyUrl, {
                method: 'POST',
                signal: controller.signal
            });
            if (postRes.ok) return true;
        } catch (e) {
            // ignore and fallback to GET
        } finally {
            clearTimeout(timeout);
        }

        try {
            const getRes = await fetch(this.bridgeNotifyUrl, {
                method: 'GET'
            });
            return getRes.ok;
        } catch (e) {
            return false;
        }
    }

    setupElectronWindowTrigger() {
        const trigger = document.getElementById('open_electron_window_trigger');
        const menu = document.getElementById('notification_list');
        const dropdownRoot = trigger ? trigger.closest('.dropdown') : null;
        if (!trigger || !menu || !dropdownRoot) return;

        const openDropdown = () => {
            menu.classList.add('show');
            dropdownRoot.classList.add('show');
            trigger.setAttribute('aria-expanded', 'true');
        };
        const closeDropdown = () => {
            menu.classList.remove('show');
            dropdownRoot.classList.remove('show');
            trigger.setAttribute('aria-expanded', 'false');
        };
        const toggleDropdown = () => {
            if (menu.classList.contains('show')) {
                closeDropdown();
            } else {
                openDropdown();
            }
        };

        document.addEventListener('click', (event) => {
            if (!dropdownRoot.contains(event.target)) {
                closeDropdown();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeDropdown();
            }
        });

        trigger.addEventListener('click', async (event) => {
            // Chặn bubbling mặc định; dropdown được toggle thủ công để tránh mở đồng thời với app.
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }

            const now = Date.now();
            const hasFreshBridgeStatus = (now - this.bridgeStatus.checkedAt) < 5000;
            // Không có trạng thái bridge mới -> mở dropdown ngay để không bị delay.
            if (!hasFreshBridgeStatus) {
                toggleDropdown();

                // Thử show app ở background để lần click sau phản hồi nhanh hơn.
                this.requestElectronShowWindow().then((shown) => {
                    if (shown) {
                        closeDropdown();
                    }
                });
                return;
            }

            if (!this.bridgeStatus.ok) {
                toggleDropdown();
                return;
            }

            const shown = await this.requestElectronShowWindow();
            if (shown) {
                closeDropdown();
            } else {
                // Bridge có thể đang chạy nhưng chưa hỗ trợ endpoint show/focus -> fallback mở dropdown.
                toggleDropdown();
            }
        });
    }
    
    /**
     * Flash window title để thu hút sự chú ý khi có notification mới
     */
    startFlashingTitle(notificationTitle = '新しい通知') {
        // Chỉ flash nếu tab không active
        if (document.hidden || !document.hasFocus()) {
            if (this.isFlashing) return; // Đã đang flash rồi
            
            this.isFlashing = true;
            let isOriginal = true;
            
            this.flashInterval = setInterval(() => {
                if (document.hidden || !document.hasFocus()) {
                    document.title = isOriginal ? `🔔 ${notificationTitle} - ${this.originalTitle}` : this.originalTitle;
                    isOriginal = !isOriginal;
                } else {
                    // Tab đã active, dừng flash
                    this.stopFlashingTitle();
                }
            }, 1000); // Nhấp nháy mỗi 1 giây
        }
    }
    
    /**
     * Dừng flash và restore title gốc
     */
    stopFlashingTitle() {
        if (this.flashInterval) {
            clearInterval(this.flashInterval);
            this.flashInterval = null;
        }
        this.isFlashing = false;
        document.title = this.originalTitle;
    }
    
    async loadFirebaseSDK() {
        return new Promise((resolve, reject) => {
            
            // Check if Firebase is already loaded
            if (window.firebase) {
                resolve();
                return;
            }
            
            // Load Firebase SDK v9 (modular version)
            const script = document.createElement('script');
            script.src = 'https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js';
            script.onload = () => {
                const dbScript = document.createElement('script');
                dbScript.src = 'https://www.gstatic.com/firebasejs/9.22.0/firebase-database-compat.js';
                dbScript.onload = () => {
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
            const response = await fetch('/api/NotificationAPI.php?method=get_config', {
            });
            const config = await response.json();
            
            if (config.error) {
                console.error('Firebase config error:', config.error);
                return null;
            }
            
            return config;
        } catch (error) {
            console.error('Failed to get Firebase config:', error);
            return null;
        }
    }
    
    // async handleNotification(notification) {
    //     console.log(notification);
    //     if (notification && notification.data.notification_id) {
    //         // Kiểm tra nếu notification_id đã có trong danh sách thì không fetch lại
    //         if (this.notifications.some(n => n.id == notification.data.notification_id)) {
    //             return;
    //         }
    //         const notif = await this.fetchNotificationDetail(notification.data.notification_id);
    //         if (notif) {
    //             this.notifications.unshift(notif);
    //             this.updateNotificationCount();
    //             this.renderNotificationList();
    //             this.showWindowsNotification(notif);
    //         }
    //     }
    // }
    
    // // Lấy danh sách thông báo mới nhất từ API
    // async fetchNotificationsFromAPI() {
    //     if (!this.userId) return;
    //     try {
    //         const response = await fetch(`/api/NotificationAPI.php?method=get_notifications&user_id=${encodeURIComponent(this.userId)}&limit=20`);
    //         const result = await response.json();
    //         if (result.notifications) {
    //             this.notifications = result.notifications;
    //             this.updateNotificationCount();
    //             this.renderNotificationList();
    //         }
    //     } catch (e) {
    //         console.error('Failed to fetch notifications from API', e);
    //     }
    // }
    
    // Lấy chi tiết 1 notification từ API (theo notification_id)
    async fetchNotificationDetail(notification_id) {
        if (!notification_id) return null;
        try {
            // API get_notifications trả về list, nên lấy 1 bản ghi
            const response = await fetch(`/api/NotificationAPI.php?method=get_notifications&user_id=${encodeURIComponent(this.userId)}&limit=1`, {
            });
            const result = await response.json();
            if (result.notifications && result.notifications.length > 0) {
                // Tìm đúng notification_id
                return result.notifications.find(n => n.id == notification_id);
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
    
    getLocalizedText(notification) {
        const data = notification.data ? (typeof notification.data === 'string' ? JSON.parse(notification.data) : notification.data) : {};
        let title = notification.title || '通知';
        let message = notification.message || '';

        try {
            const lang = (typeof i18next !== 'undefined' && i18next.language) ? i18next.language : '';
            if (lang) {
                if (lang.startsWith('vi')) {
                    if (data.title_vi) title = data.title_vi;
                    if (data.message_vi) message = data.message_vi;
                } else if (lang.startsWith('ja')) {
                    if (data.title_ja) title = data.title_ja;
                    if (data.message_ja) message = data.message_ja;
                }
            }
        } catch (e) {
            // fallback: keep original title/message
        }

        return { title, message, data };
    }

    renderNotificationList() {
        const list = this.notifications || [];
        const projectUl = document.getElementById('notification_list_project');
        const soumuUl = document.getElementById('notification_list_soumu');
        if (!projectUl || !soumuUl) return;

        // Chia notification theo event:
        // - event bắt đầu bằng 'form' hoặc 'other' => 総務
        // - còn lại => 案件
        const projectList = [];
        const soumuList = [];
        list.forEach((n) => {
            const ev = (n.event || '').toString();
            if (ev.startsWith('form') || ev.startsWith('other')) {
                soumuList.push(n);
            } else {
                projectList.push(n);
            }
        });

        const renderTo = (ul, items) => {
            ul.innerHTML = '';
            if (!items || items.length === 0) {
                ul.innerHTML = `
                    <li class="list-group-item list-group-item-action dropdown-notifications-item marked-as-read">
                        <div class="d-flex">
                            <div class="flex-grow-1">
                                <h6 class="small mb-1">通知はありません 🎉</h6>
                            </div>
                        </div>
                    </li>
                `;
                return;
            }
            items.forEach((n) => {
                const { title, message, data } = this.getLocalizedText(n);
                const li = document.createElement('li');
                let data_parsed = n.data ? (typeof n.data === 'string' ? JSON.parse(n.data) : n.data) : {};
                li.className = `list-group-item list-group-item-action dropdown-notifications-item${n.is_read == 1 ? ' marked-as-read' : ''}`;
                li.innerHTML = `
                    <div class="d-flex">
                        <div class="flex-shrink-0 me-3 justify-content-between">
                            <div class="avatar mb-1">
                                <img src="${data.avatar || '/assets/img/avatars/1.png'}" alt class="rounded-circle" />
                            </div>
                            <span class="badge bg-warning" style="font-size: 10px;">${data_parsed.is_important == 1 ? '重要' : ''}</span>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">${this.escapeHtml(title)}</h6>
                            <small class="mb-1 d-block text-body">${this.escapeHtml(message)}</small>
                            <small class="text-body-secondary">${n.created_at ? n.created_at : ''}</small>
                           
                        </div>
                        <div class="flex-shrink-0 dropdown-notifications-actions">
                            <a href="javascript:void(0)" class="dropdown-notifications-read"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                title="${n.is_read == 1 ? '未読にする' : '既読にする'}"
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
                    if (data.url) {
                        window.location.href = data.url;
                    }
                });
                ul.appendChild(li);
            });
        };

        renderTo(projectUl, projectList);
        renderTo(soumuUl, soumuList);

        this.updateNotificationDot();
        this.updateNotificationCount();
        
        // Dừng flash nếu không còn notification chưa đọc
        const unreadCount = this.notifications.filter(n => n.is_read == 0).length;
        if (unreadCount === 0) {
            this.stopFlashingTitle();
        }
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
        if (this.isSyncingLatest) return;
        this.isSyncingLatest = true;
        // Lấy 20 notification mới nhất từ API khi load trang
        try {
            const response = await fetch(`/api/NotificationAPI.php?method=get_notifications&user_id=${encodeURIComponent(this.userId)}&limit=20`, {
            });
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
        } finally {
            this.isSyncingLatest = false;
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
                    this.notifyBridgeNewNotification(notif);

                    const bridgeOpen = await this.isNotificationBridgeOpen();
                    if (!bridgeOpen) {
                        this.showWindowsNotification(notif);
                    }
                    this.showToastNotification(notif);
                    // Flash window title để thu hút sự chú ý (theo ngôn ngữ hiện tại)
                    if (!bridgeOpen) {
                        const localized = this.getLocalizedText(notif);
                        this.startFlashingTitle(localized.title || '新しい通知');
                    }
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
            const activeTab = document.querySelector('#notification_list .nav-tabs .nav-link.active');
            let target = 'project';
            if (activeTab && activeTab.id === 'notification_tab_soumu_button') {
                target = 'soumu';
            }
            const unread = this.notifications.filter(n => {
                if (n.is_read != 0) return false;
                const ev = (n.event || '').toString();
                const isSoumu = ev.startsWith('form') || ev.startsWith('other');
                return target === 'soumu' ? isSoumu : !isSoumu;
            });
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
            // Dừng flash sau khi đánh dấu tất cả là đã đọc (nếu không còn unread)
            const unreadCount = this.notifications.filter(n => n.is_read == 0).length;
            if (unreadCount === 0) {
                this.stopFlashingTitle();
            }
        });
    }

    updateNotificationDot() {
        const dot = document.getElementById('notification_dot');
        if (!dot) return;
        const hasUnread = this.notifications.some(n => n.is_read == 0);
        dot.style.display = hasUnread ? 'inline-block' : 'none';
    }
    
    updateNotificationCount() {
        const list = this.notifications || [];
        const totalUnread = list.filter(n => n.is_read == 0).length;

        // Tổng số notification chưa đọc (badge ở header)
        const countElement = document.getElementById('notification_count');
        if (countElement) {
            countElement.style.display = totalUnread > 0 ? 'inline' : 'none';
            countElement.textContent = totalUnread > 0 ? totalUnread + ' New' : '';
        }

        // Số lượng cho từng tab
        const projectBadge = document.getElementById('notification_count_project');
        const soumuBadge = document.getElementById('notification_count_soumu');
        let projectUnread = 0;
        let soumuUnread = 0;

        list.forEach((n) => {
            if (n.is_read != 0) return;
            const ev = (n.event || '').toString();
            if (ev.startsWith('form') || ev.startsWith('other')) {
                soumuUnread++;
            } else {
                projectUnread++;
            }
        });

        if (projectBadge) {
            projectBadge.style.display = projectUnread > 0 ? 'inline' : 'none';
            projectBadge.textContent = projectUnread > 0 ? projectUnread : '';
        }
        if (soumuBadge) {
            soumuBadge.style.display = soumuUnread > 0 ? 'inline' : 'none';
            soumuBadge.textContent = soumuUnread > 0 ? soumuUnread : '';
        }
    }

    /**
     * Request notification permission from user
     */
    async requestNotificationPermission() {
        if (!('Notification' in window)) {
            console.log('This browser does not support desktop notification');
            return;
        }

        if (Notification.permission === 'default') {
            try {
                const permission = await Notification.requestPermission();
                this.notificationPermission = permission;
                console.log('Notification permission:', permission);
            } catch (error) {
                console.error('Error requesting notification permission:', error);
            }
        } else {
            this.notificationPermission = Notification.permission;
        }
    }

    /**
     * Show Windows desktop notification
     */
    showWindowsNotification(notification) {

        if (!('Notification' in window) || this.notificationPermission !== 'granted') {
            return;
        }
        // Don't show notification if page is focused (user is actively using the app)
        if (document.hasFocus()) {
            return;
        }
        

        try {
            const { title, message, data } = this.getLocalizedText(notification);
            const notificationOptions = {
                body: message || '新しい通知があります',
                icon: data.avatar || '/assets/img/avatars/1.png',
                badge: '/assets/img/favicon/favicon.ico',
                tag: `notification_${notification.id}`,
                requireInteraction: false,
                silent: false,
                data: {
                    notification_id: notification.id,
                    url: data.url || '',
                    project_id: data.project_id || '',
                    task_id: data.task_id || ''
                }
            };

            // Add actions if available
            if (data.url) {
                // notificationOptions.actions = [
                //     {
                //         action: 'view',
                //         title: '表示',
                //         icon: '/assets/img/icons/misc/view.png'
                //     },
                //     {
                //         action: 'dismiss',
                //         title: '閉じる'
                //     }
                // ];
            }

            const desktopNotification = new Notification(title || '通知', notificationOptions);

            // Handle notification click
            desktopNotification.onclick = (event) => {
                event.preventDefault();
                desktopNotification.close();
                
                // Focus the window
                window.focus();
                
                // Navigate to the notification URL if available
                if (data.url) {
                    window.location.href = data.url;
                }
                
                // Mark as read
                this.markAsRead(notification.id);
            };

            // Handle notification action clicks
            desktopNotification.onactionclick = (event) => {
                event.preventDefault();
                desktopNotification.close();
                
                if (event.action === 'view' && data.url) {
                    window.focus();
                    window.location.href = data.url;
                    this.markAsRead(notification.id);
                }
            };

            // Auto close after 5 seconds
            setTimeout(() => {
                desktopNotification.close();
            }, 5000);

        } catch (error) {
            console.error('Error showing Windows notification:', error);
        }
    }

    /**
     * Show a custom toast notification in the browser
     */
    showToastNotification(notification) {
        const { title, message, data } = this.getLocalizedText(notification);
        
        // Create toast container if it doesn't exist
        let toastContainer = document.getElementById('toast-notification-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-notification-container';
            toastContainer.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 400px;
            `;
            document.body.appendChild(toastContainer);
        }

        // Create toast element
        const toast = document.createElement('div');
        toast.className = 'notification-toast';
        toast.style.cssText = `
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideInRight 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
        `;

        toast.innerHTML = `
            <img src="${data.avatar || '/assets/img/avatars/1.png'}" 
                 style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;" />
            <div style="flex: 1;">
                <div style="font-weight: 600; margin-bottom: 4px;">${this.escapeHtml(title || '通知')}</div>
                <div style="font-size: 14px; color: #666;">${this.escapeHtml(message || '')}</div>
            </div>
            <button onclick="this.parentElement.remove()" 
                    style="background: none; border: none; font-size: 18px; cursor: pointer; color: #999;">×</button>
        `;

        // Add click handler
        toast.addEventListener('click', (e) => {
            if (e.target.tagName === 'BUTTON') return;
            
            if (data.url) {
                window.location.href = data.url;
            }
            this.markAsRead(notification.id);
            toast.remove();
        });

        // Add to container
        toastContainer.appendChild(toast);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);
    }

    registerConnectedUser() {
        if (!this.userId || !this.database) return;
        const ref = this.database.ref('connected_users/' + this.userId);
        ref.set(true);
        ref.onDisconnect().remove();
        // Đảm bảo xóa khi unload trang (trường hợp onDisconnect không kịp)
        window.addEventListener('beforeunload', () => {
            if (this.autoRefreshTimer) {
                clearInterval(this.autoRefreshTimer);
                this.autoRefreshTimer = null;
            }
            ref.remove();
        });
    }

    listenConnectedUsers() {
        if (!this.database) return;
        const ref = this.database.ref('connected_users');
        ref.on('value', (snapshot) => {
            const connected = snapshot.val() || {};
            const userIds = Object.keys(connected);
            //save to session storage
            sessionStorage.setItem('connected_users', JSON.stringify(userIds));
            // Tìm tất cả .avatar có data-userid
            document.querySelectorAll('.avatar[data-userid]').forEach(avatar => {
                const uid = avatar.getAttribute('data-userid');
                if (userIds.includes(uid)) {
                    avatar.classList.add('avatar-online');
                    avatar.classList.remove('avatar-offline');
                } else {
                    avatar.classList.remove('avatar-online');
                    avatar.classList.add('avatar-offline');
                }
            });
        });
    }

    // Lắng nghe realtime comment cho project (Firebase)
    listenProjectCommentRealtime(projectId, reloadCallback) {
        if (!this.database || !projectId || typeof reloadCallback !== 'function') return;
        const channel = 'project_' + projectId;
        const ref = this.database.ref('notifications/' + channel);
        
        // Lắng nghe thay đổi của last_comment_id
        ref.on('value', (snapshot) => {
            const data = snapshot.val();
            if (data && data.last_comment_id) {
                reloadCallback({
                    last_comment_id: data.last_comment_id,
                    thread_id: data.thread_id || null,
                    timestamp: data.timestamp || Date.now()
                });
            }
        });
    }
    
    listenTaskCommentRealtime(taskId, reloadCallback) {
        if (!this.database || !taskId || typeof reloadCallback !== 'function') return;
        const channel = 'task_' + taskId;
        const ref = this.database.ref('notifications/' + channel);
        
        // Lắng nghe thay đổi của last_comment_id
        ref.on('value', (snapshot) => {
            const data = snapshot.val();
            if (data && data.last_comment_id) {
                reloadCallback({
                    last_comment_id: data.last_comment_id,
                    timestamp: Date.now()
                });
            }
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