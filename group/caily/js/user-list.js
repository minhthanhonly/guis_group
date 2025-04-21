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
        
        // Update status every 30 seconds
        setInterval(() => this.loadUsers(), 30000);
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
                        <span class="status-indicator ${this.isUserOnline(user) ? 'status-online' : 'status-offline'}"></span>
                        ${this.isUserOnline(user) ? 'Online' : 'Offline'}
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

    isUserOnline(user) {
        // You can implement your own logic here based on your session tracking
        // For now, we'll use a random status for demonstration
        return Math.random() > 0.5;
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
