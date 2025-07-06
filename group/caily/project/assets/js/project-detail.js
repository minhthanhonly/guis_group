const { createApp } = Vue;

const vueApp = createApp({
    data() {
        return {
            projectId: typeof PROJECT_ID !== 'undefined' ? PROJECT_ID : this.getProjectIdFromUrl(),
            project: null,
            department: null,
            managers: [],
            members: [],
            tasks: [],
            team_list: [],
            isEditMode: false,
            originalProject: null,
            stats: {
                totalTasks: 0,
                completedTasks: 0,
                timeTracked: 0,
                totalDays: 0
            },
            statuses: [
                { value: 'draft', label: '下書き', color: 'secondary' },
                { value: 'open', label: 'オープン', color: 'info' },
                { value: 'confirming', label: '確認中', color: 'warning' },
                { value: 'in_progress', label: '進行中', color: 'primary' },
                { value: 'paused', label: '一時停止', color: 'warning' },
                { value: 'completed', label: '完了', color: 'success' },
                { value: 'cancelled', label: 'キャンセル', color: 'danger' }
            ],
            priorities: [
                { value: 'low', label: '低', color: 'secondary' },
                { value: 'medium', label: '中', color: 'primary' },
                { value: 'high', label: '高', color: 'warning' },
                { value: 'urgent', label: '緊急', color: 'danger' }
            ],
            estimateStatuses: [
                { value: '未発行', label: '未発行', color: 'secondary' },
                { value: '発行済み', label: '発行済み', color: 'info' },
                { value: '承認済み', label: '承認済み', color: 'success' },
                { value: '却下', label: '却下', color: 'danger' },
                { value: '調整', label: '調整', color: 'warning' }
            ],
            invoiceStatuses: [
                { value: '未発行', label: '未発行', color: 'secondary' },
                { value: '発行済み', label: '発行済み', color: 'info' },
                { value: '承認済み', label: '承認済み', color: 'success' },
                { value: '却下', label: '却下', color: 'danger' },
                { value: '調整', label: '調整', color: 'warning' }
            ],
            categories: [],
            companies: [],
            contacts: [],
            category_id: '',
            company_name: '',
            customer_id: '',
            newProject: {
                members: '',
                managers: '',
                teams: '',
            },
            allTeams: [],
            showMemberModal: false,
            memberSelectType: '', // 'manager' or 'member'
            memberSelected: [],
            allUsers: [], // all users for selection
            departmentUsers: [],
            membersTagify: null,
            prevTeamIds: [],
            managerTagify: null,
            quillInstance: null,
            projectOrderTypeTagify: null,
            buildingBranchTagify: null,
            customFields: [],
            departmentCustomFieldSets: [],
            // Danh sách các tỉnh/thành phố của Nhật Bản
            japanPrefectures: [
                '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
                '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
                '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
                '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
                '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
                '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
                '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'
            ],
            // Notes functionality
            notes: [],
            showNoteModal: false,
            isNoteEditMode: false,
            editingNote: {
                id: null,
                title: '',
                content: '',
                is_important: false,
                user_id: null
            },
            // Project status update loading
            isUpdatingStatus: false,
            // Debounce timer for amount updates
            amountUpdateTimer: null,
            tagsUpdateTimer: null,
            projectTagsTagify: null,
            // Quill editor content storage (separate from Vue reactivity)
            quillContent: '',
            userPermissions: null,
            // mention-related variables removed
            logs: [], // Thêm biến lưu log lịch sử
            // Current user data
            currentUser: {
                userid: typeof USER_ID !== 'undefined' ? USER_ID : null,
                realname: '',
                user_image: null,
            },
        }
    },
    computed: {
        isManager() {
            if(USER_ROLE == `administrator`) return true;
            if (!this.managers) return false;
            return this.managers.some(m => String(m.user_id) === String(USER_AUTH_ID));
        },
        filteredTeams() {
            if (!this.project || !this.project.department_id) return this.allTeams;
            return this.allTeams.filter(team => String(team.department_id) === String(this.project.department_id));
        },
        selectedCustomFieldSet() {
            if (!this.project || !this.project.department_custom_fields_set_id) return null;
            return this.departmentCustomFieldSets.find(set => String(set.id) === String(this.project.department_custom_fields_set_id)) || null;
        },
        canViewProject() {
            if(USER_ROLE == 'administrator') return true;
            if (!this.project) return false;
            if(this.project.created_by &&this.project.created_by == USER_ID) return true;
            if(this.managers && this.managers.some(m => String(m.user_id) === String(USER_AUTH_ID))) return true;
            if(this.members && this.members.some(m => String(m.user_id) === String(USER_AUTH_ID))) return true;
            if(this.hasPermission('project_manager')) return true;
            if(this.hasPermission('project_director')) return true;
            return false;
        },
        sortedLogs() {
            if (!this.logs) return [];
            // Sắp xếp giảm dần theo thời gian
            return [...this.logs].sort((a, b) => (b.time > a.time ? 1 : -1));
        },
    },
    methods: {
        getProjectIdFromUrl() {
            // Lấy project ID từ URL nếu không có biến PROJECT_ID
            const urlParams = new URLSearchParams(window.location.search);
            const id = urlParams.get('id');
            if (id) return parseInt(id);
            
            // Hoặc lấy từ pathname
            const pathMatch = window.location.pathname.match(/\/project\/detail\.php\?id=(\d+)/);
            if (pathMatch) return parseInt(pathMatch[1]);
            
            // Fallback: lấy từ URL hiện tại
            const currentUrl = window.location.href;
            const urlMatch = currentUrl.match(/[?&]id=(\d+)/);
            if (urlMatch) return parseInt(urlMatch[1]);
            
            console.error('Could not determine project ID from URL');
            return null;
        },
        async getUserPermissions(departmentId) {
            try {
                const response = await axios.get(`/api/index.php?model=department&method=get_user_permission_by_department&department_id=${departmentId}`);
                this.userPermissions = response.data;
                return this.userPermissions;
            } catch (error) {
                console.error('Error loading user permissions:', error);
                this.userPermissions = null;
                return null;
            }
        },
        // Check if user has specific permission
        hasPermission(permission) {
            if(USER_ROLE == 'administrator') return true;
            if (!this.userPermissions) return false;
            return this.userPermissions[permission] == 1;
        },
        // Check if user can perform project actions
        canAddProject() {
            return this.hasPermission('project_add');
        },
        canEditProject() {
            if(this.isManager) return true;
            return this.hasPermission('project_edit');
        },
        canDeleteProject() {
            if(this.isManager) return true;
            return this.hasPermission('project_delete');
        },
        canManageProject() {
            if(this.isManager) return true;
            return this.hasPermission('project_manager');
        },
        canCommentProject() {
            if(this.isManager) return true;
            return this.hasPermission('project_comment');
        },
        
        // Phương thức để dịch label động
        translateLabel(label) {
            if (typeof i18next !== 'undefined' && i18next.isInitialized) {
                return i18next.t(label) || label;
            }
            return label;
        },
        
        async loadProject() {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getById&id=${this.projectId}`);
                this.project = response.data;
                await this.getUserPermissions(this.project.department_id);
                
                if (this.project.teams) {
                    await this.loadTeamListByIds(this.project.teams);
                } else {
                    this.project.team_list = [];
                }
                if (this.project.department_id) {
                    this.loadDepartment();
                }
                await this.loadMembers();
                // Ensure Tagify is updated after loading project and team_list
                this.$nextTick(() => { 
                    this.initTagify(); 
                    this.setConnectedUsers();
                    
                });
            } catch (error) {
                console.error('Error loading project:', error);
                alert('プロジェクトの読み込みに失敗しました。');
            }
        },
        setConnectedUsers() {
            const connectedUsers = JSON.parse(sessionStorage.getItem('connected_users'));
            if (connectedUsers) {
                document.querySelectorAll('.avatar[data-userid]').forEach(avatar => {
                    const uid = avatar.getAttribute('data-userid');
                    if (connectedUsers.includes(uid)) {
                        avatar.classList.add('avatar-online');
                        avatar.classList.remove('avatar-offline');
                    } else {
                        avatar.classList.remove('avatar-online');
                        avatar.classList.add('avatar-offline');
                    }
                });
            }
        },
        async loadTeamListByIds(teamIdsStr) {
            try {
                const ids = teamIdsStr.split(',').map(id => id.trim()).filter(Boolean);
                if (ids.length === 0) {
                    this.project.team_list = [];
                    return;
                }
                const res = await axios.get(`/api/index.php?model=team&method=listbyids&ids=${ids.join(',')}`);
                if (res.data && Array.isArray(res.data)) {
                    this.project.team_list = res.data;
                } else {
                    this.project.team_list = [];
                }
            } catch (e) {
                this.project.team_list = [];
            }
        },
        async loadDepartment() {
            try {
                const response = await axios.get(`/api/index.php?model=department&method=get&id=${this.project.department_id}`);
                this.department = response.data;
            } catch (error) {
                console.error('Error loading department:', error);
            }
        },
        async loadMembers() {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getMembers&project_id=${this.projectId}`);
                this.members = response.data || [];
                this.managers = this.members.filter(m => m && m.role === 'manager');
                const managerIds = this.managers.map(m => m.user_id);
                this.members = this.members.filter(m => m && m.role === 'member' && !managerIds.includes(m.user_id));
                
                // Update current user data after loading members
                this.loadCurrentUser();
            } catch (error) {
                console.error('Error loading members:', error);
            }
        },
        async loadTasks() {
            // try {
            //     const response = await axios.get(`/api/index.php?model=task&method=list&project_id=${this.projectId}`);
            //     this.tasks = response.data || [];
            //     this.calculateStats();
            // } catch (error) {
            //     console.error('Error loading tasks:', error);
            // }
        },
        // Comment functionality is now handled by CommentComponent
        // calculateStats() {
        //     this.stats.totalTasks = this.tasks.length;
        //     this.stats.completedTasks = this.tasks.filter(t => t.status === 'completed').length;
        //     this.stats.timeTracked = this.tasks.reduce((sum, task) => sum + parseFloat(task.actual_hours || 0), 0);
        //     if (this.project && this.project.start_date) {
        //         const start = new Date(this.project.start_date);
        //         const end = this.project.actual_end_date ? new Date(this.project.actual_end_date) : new Date();
        //         const diffTime = Math.abs(end - start);
        //         this.stats.totalDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        //     }
        // },
        // Comment component event handlers
        onCommentAdded(event) {
            console.log('Comment added:', event);
            this.showNotification('コメントが追加されました', 'success');
        },
        
        onCommentError(event) {
            console.error('Comment error:', event);
            this.showNotification(event.message || 'エラーが発生しました', 'error');
        },
        async deleteProject() {
            const swal = await Swal.fire({
                title: '本当にこのプロジェクトを削除しますか？',
                icon: 'warning',
                showCancelButton: true,
            });
            if (swal.isConfirmed) {
                try {
                    const formData = new FormData();
                    formData.append('id', this.projectId);
                    const response = await axios.post('/api/index.php?model=project&method=delete', formData);
                    if(response.data.status == 'success'){
                        await Swal.fire({
                            title: 'プロジェクトが削除されました。',
                            icon: 'success',
                        }).then(() => {
                            window.location.href = 'index.php';
                        });
                    } else {
                        showMessage('プロジェクトの削除に失敗しました。', true);
                    }
                } catch (error) {
                    console.error('Error deleting project:', error);
                    showMessage('プロジェクトの削除に失敗しました。', true);
                }
            }
        },
        copyProject() {
            // Get the current custom fields data
            let customFieldsData = [];
            let raw = this.project.custom_fields;
            if (typeof raw === 'string' && raw.includes('&quot;')) {
                raw = raw.replace(/&quot;/g, '"');
            }
            if (typeof raw === 'string') {
                try { customFieldsData = JSON.parse(raw); } catch (e) { customFieldsData = []; }
            } else if (Array.isArray(raw)) {
                customFieldsData = raw;
            }
            
            // Prepare project data for copying
            const projectData = {
                category_id: this.project.category_id,
                company_name: this.project.company_name,
                customer_id: this.project.customer_id,
                project_number: this.project.project_number,
                name: this.project.name + ' (コピー)',
                department_id: this.project.department_id,
                building_branch: this.project.building_branch,
                building_size: this.project.building_size,
                building_type: this.project.building_type,
                building_number: this.project.building_number,
                priority: this.project.priority,
                status: 'draft', // Set to draft for new copy
                project_order_type: this.project.project_order_type,
                start_date: this.project.start_date,
                end_date: this.project.end_date,
                progress: 0, // Reset progress
                description: this.project.description,
                department_custom_fields_set_id: this.project.department_custom_fields_set_id,
                teams: this.project.teams,
                managers: this.managers.map(m => m.user_id).join(','),
                members: this.members.map(m => m.user_id).join(','),
                custom_fields: customFieldsData,
                tags: this.project.tags
            };
            
            // Store the data in sessionStorage
            sessionStorage.setItem('copyProjectData', JSON.stringify(projectData));
            
            // Redirect to create page with department_id if available
            const url = this.project.department_id 
                ? `create.php?department_id=${this.project.department_id}` 
                : 'create.php';
            window.location.href = url;
        },
        formatDate(date) {
            if (!date) return '-';
            return moment(date).format('YYYY/MM/DD');
        },
        formatDateForInput(date) {
            if (!date) return '';
            return moment(date).format('YYYY-MM-DD');
        },
        formatDateTime(datetime) {
            if (!datetime) return '-';
            return moment(datetime).format('YYYY/MM/DD HH:mm');
        },
        formatCurrency(amount) {
            if (!amount) return '¥0';
            return '¥' + parseInt(amount).toLocaleString();
        },
        getStatusLabel(status) {
            const s = this.statuses.find(s => s.value === status);
            return s ? s.label : status;
        },
        getStatusBadgeClass(status) {
            const s = this.statuses.find(s => s.value === status);
            return `bg-${s?.color || 'secondary'}`;
        },
        getPriorityLabel(priority) {
            const p = this.priorities.find(p => p.value === priority);
            return p ? p.label : priority;
        },
        getPriorityBadgeClass(priority) {
            const p = this.priorities.find(p => p.value === priority);
            return `bg-${p?.color || 'secondary'}`;
        },
        getRoleLabel(role) {
            const roles = {
                'manager': 'マネージャー',
                'member': 'メンバー',
                'viewer': '閲覧者'
            };
            return roles[role] || role;
        },
        getRoleBadgeClass(role) {
            const roleColors = {
                'manager': 'bg-primary',
                'member': 'bg-info',
                'viewer': 'bg-secondary'
            };
            return roleColors[role] || 'bg-secondary';
        },
        async updateStatus() {
            // Close dropdown
            const dropdownElement = document.querySelector('#statusDropdown');
            if (dropdownElement) {
                const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                if (dropdown) {
                    dropdown.hide();
                }
            }
            if (this.isEditMode) {
                return;
            }
            try {
                const formData = new FormData();
                formData.append('id', this.projectId);
                formData.append('status', this.project.status);
                formData.append('name', this.project.name);
                formData.append('project_number', this.project.project_number);
                const response = await axios.post('/api/index.php?model=project&method=updateStatus', formData);
                if (response.data && response.data.success !== false) {
                    showMessage('ステータスの更新に完了しました。');
                } else {
                    showMessage('ステータスの更新に失敗しました。', true);
                }
            } catch (error) {
                console.error('Error updating status:', error);
                showMessage('ステータスの更新に失敗しました。', true);
            }
        },
        selectStatus(status) {
            this.project.status = status;
            this.updateStatus();
            // Close dropdown
            const dropdownElement = document.querySelector('#statusDropdown');
            if (dropdownElement) {
                const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                if (dropdown) {
                    dropdown.hide();
                }
            }
        },
        getStatusButtonClass(status) {
            const s = this.statuses.find(s => s.value === status);
            return `btn-${s?.color || 'secondary'}`;
        },
        getPriorityButtonClass(priority) {
            const p = this.priorities.find(p => p.value === priority);
            return `btn-${p?.color || 'secondary'}`;
        },
        selectPriority(priority) {
            this.project.priority = priority;
            // Close dropdown
            const dropdownElement = document.querySelector('#priorityDropdown');
            if (dropdownElement) {
                const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                if (dropdown) {
                    dropdown.hide();
                }
            }
            if (this.isEditMode) {
                return;
            }
            this.updatePriority();
        },
        async updatePriority() {
            try {
                const formData = new FormData();
                formData.append('id', this.projectId);
                formData.append('priority', this.project.priority);
                const response = await axios.post('/api/index.php?model=project&method=updatePriority', formData);
                if (response.data) {
                    showMessage('優先度を更新しました。');
                }
            } catch (error) {
                console.error('Error updating priority:', error);
                showMessage('優先度の更新に失敗しました。', true);
            }
        },
        getAvatarSrc(member) {
            // Trả về đường dẫn ảnh từ user_image, fallback nếu không có
            return '/assets/upload/avatar/' + member.user_image || '';
        },
        handleAvatarError(member) {
            member.avatarError = true;
        },
        getInitials(name) {
            return getAvatarName(name);
        },
        initTooltips() {
            // Dispose previous tooltips to avoid duplicates
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(el => {
                if (el._tooltipInstance) {
                    el._tooltipInstance.dispose();
                }
                el._tooltipInstance = new bootstrap.Tooltip(el);
            });
        },
        async loadCategories() {
            const res = await axios.get('/api/index.php?model=customer&method=list_categories');
            if (res.data && res.data.data) {
                this.categories = res.data.data;
            }
        },
        async loadCompanies() {
            if (!this.project.department_id) {
                this.companies = [];
                return;
            }
            const res = await axios.get(`/api/index.php?model=customer&method=list_companies_by_department&department_id=${this.project.department_id}`);
            if (res.data && res.data.data) {
                this.companies = res.data.data;
            }
        },
        async loadContacts() {
            if (!this.project.department_id) {
                this.contacts = [];
                return;
            }
            const res = await axios.get(`/api/index.php?model=customer&method=list_contacts_by_department&department_id=${this.project.department_id}`);
            if (res.data && res.data.data) {
                this.contacts = res.data.data;
            }
        },
        onCategoryChange() {
            this.project.company_name = '';
            this.project.customer_id = '';
            this.loadCompaniesByCategory();
            this.contacts = [];
        },
        onCompanyChange() {
            this.project.customer_id = '';
            this.loadContactsByCompany();
        },
        async updateProgress() {
            try {
                const formData = new FormData();
                formData.append('id', this.projectId);
                formData.append('progress', this.project.progress);
                const response = await axios.post('/api/index.php?model=project&method=updateProgress', formData);
                if (response.data) {
                    showMessage('進捗率を更新しました。');
                }
            } catch (error) {
                console.error('Error updating progress:', error);
                showMessage('進捗率の更新に失敗しました。', true);
            }
        },
        async updateProjectStatus() {
            if (this.isUpdatingStatus) return; // Prevent multiple simultaneous updates
            if (!this.isManager) return; // Only managers can update status
            
            this.isUpdatingStatus = true;
            try {
                const formData = new FormData();
                formData.append('id', this.projectId);
                formData.append('amount', this.project.amount || 0);
                formData.append('estimate_status', this.project.estimate_status || '未発行');
                formData.append('invoice_status', this.project.invoice_status || '未発行');
                const response = await axios.post('/api/index.php?model=project&method=updateProjectStatus', formData);
                if (response.data && response.data.status === 'success') {
                    showMessage('プロジェクトステータスを更新しました。');
                } else {
                    showMessage('プロジェクトステータスの更新に失敗しました。', true);
                }
            } catch (error) {
                console.error('Error updating project status:', error);
                showMessage('プロジェクトステータスの更新に失敗しました。', true);
            } finally {
                this.isUpdatingStatus = false;
            }
        },
        updateAmount() {
            if (!this.isManager) return; // Only managers can update amount
            // Debounce the amount update to avoid too many API calls
            clearTimeout(this.amountUpdateTimer);
            this.amountUpdateTimer = setTimeout(() => {
                this.updateProjectStatus();
            }, 1000); // Wait 1 second after user stops typing
        },
        updateTags() {
            // Get tags from Tagify instance
            if (this.projectTagsTagify) {
                const tags = this.projectTagsTagify.value.map(tag => tag.value).join(',');
                this.project.tags = tags;
            }
            
            // Debounce the tags update to avoid too many API calls
            clearTimeout(this.tagsUpdateTimer);
            this.tagsUpdateTimer = setTimeout(() => {
                this.saveProjectTags();
            }, 1000); // Wait 1 second after user stops typing
        },
        async saveProjectTags() {
            try {
                const formData = new FormData();
                formData.append('id', this.projectId);
                formData.append('tags', this.project.tags || '');
                await axios.post('/api/index.php?model=project&method=updatePojectTags', formData);
            } catch (error) {
                console.error('Error saving project tags:', error);
            }
        },
        clearTagifyTags(field) {
            if (field === 'project_tags') {
                if (this.projectTagsTagify) {
                    this.projectTagsTagify.removeAllTags();
                }
            } else if (field === 'team') {
                if (this.tagify) {
                    this.tagify.removeAllTags();
                }
            } else if (field === 'manager') {
                if (this.managerTagify) {
                    this.managerTagify.removeAllTags();
                }
            } else if (field === 'members') {
                if (this.membersTagify) {
                    this.membersTagify.removeAllTags();
                }
            } else if (field === 'building_branch') {
                if (this.buildingBranchTagify) {
                    this.buildingBranchTagify.removeAllTags();
                }
            } else if (field === 'project_order_type') {
                if (this.projectOrderTypeTagify) {
                    this.projectOrderTypeTagify.removeAllTags();
                }
            }
        },
        async loadAllTeams() {
            try {
                const res = await axios.get('/api/index.php?model=team&method=list');
                if (res.data && Array.isArray(res.data)) {
                    this.allTeams = res.data;
                } else {
                    this.allTeams = [];
                }
            } catch (e) {
                this.allTeams = [];
            }
        },

        // initTagify() {
        //     if (!this.isEditMode) return;
        //     const input = document.getElementById('team_tags');
        //     if (!input) return;
        //     // Destroy previous Tagify instance if exists
        //     if (input._tagify) {
        //         input._tagify.destroy();
        //     }
        //     // Gán giá trị team đã chọn
        //     const tags = (this.project.team_list || []).map(t => ({ value: t.name, id: t.id }));
        //     // Danh sách tất cả team cho whitelist
        //     const whitelist = this.filteredTeams.map(t => ({ value: t.name, id: t.id }));
        //     this.tagify = new Tagify(input, {
        //         whitelist: whitelist,
        //         enforceWhitelist: true,
        //         dropdown: { enabled: 0 }
        //     });
        //     this.tagify.addTags(tags);
        //     // Xử lý khi xóa team thì xóa member của team đó
           
        //     this.tagify.on('change', this.onTeamTagsChange.bind(this));
        // },
        initTagify() {
            this.$nextTick(() => {
                setTimeout(() => {
                    // --- Tagify for team selection ---
                    const teamInput = document.getElementById('team_tags');
                    if (teamInput && window.Tagify && !teamInput._tagify) {
                        if (this.tagify) {
                            try {
                                this.tagify.destroy();
                            } catch (e) {
                                console.log('Error destroying existing tagify:', e);
                            }
                        }
                        // Gán giá trị team đã chọn
                        const tags = (this.project.team_list || []).map(t => ({ value: t.name, id: t.id }));
                        // Danh sách tất cả team cho whitelist
                        const whitelist = this.filteredTeams.map(t => ({ value: t.name, id: t.id }));
                        this.tagify = new Tagify(teamInput, {
                            whitelist: whitelist,
                            enforceWhitelist: false,
                            dropdown: {
                                maxItems: 20,
                                enabled: 0,
                                closeOnSelect: true
                            },
                        });
                        this.tagify.addTags(tags);
                        this.tagify.on('remove', async (e) => {
                            const removedTeamId = e.detail.data.id;
                            if (!removedTeamId || !this.membersTagify) return;
                            // Lấy danh sách user của team vừa bị xóa
                            try {
                                const res = await axios.get(`/api/index.php?model=team&method=get&id=${removedTeamId}`);
                                if (res.data && Array.isArray(res.data.members)) {
                                    const teamMemberIds = res.data.members.map(m => String(m.user_id));
                                    // Xóa các member này khỏi membersTagify
                                    const remain = this.membersTagify.value.filter(tag => !teamMemberIds.includes(String(tag.id)));
                                    this.membersTagify.removeAllTags();
                                    this.membersTagify.addTags(remain);
                                }
                            } catch (err) {}
                        });
                        // Xử lý khi thêm team thì thêm member của team đó
                        this.tagify.on('add', async (e) => {
                            const addedTeamId = e.detail.data.id;
                            if (!addedTeamId || !this.membersTagify) return;
                            try {
                                const res = await axios.get(`/api/index.php?model=team&method=get&id=${addedTeamId}`);
                                if (res.data && Array.isArray(res.data.members)) {
                                    const teamMembers = res.data.members.map(m => ({ id: m.user_id, value: m.user_name }));
                                    // Lọc ra các member chưa có trong Tagify
                                    const currentIds = this.membersTagify.value.map(tag => String(tag.id));
                                    const toAdd = teamMembers.filter(m => !currentIds.includes(String(m.id)));
                                    this.membersTagify.addTags(toAdd);
                                }
                            } catch (err) {}
                        });
                        this.tagify.on('change', this.onTeamTagsChange.bind(this));
                    }
                    
                    // --- Tagify for project_order_type ---
                    const orderTypeInput = document.querySelector('#project_order_type');
                    if (orderTypeInput && window.Tagify && !orderTypeInput._tagify) {
                        if (this.projectOrderTypeTagify) {
                            try {
                                this.projectOrderTypeTagify.destroy();
                            } catch (e) {
                                console.log('Error destroying existing projectOrderTypeTagify:', e);
                            }
                        }
                        this.projectOrderTypeTagify = new Tagify(orderTypeInput, {
                            whitelist: ['新規', '修正', '免震', '耐震', '計画変更'],
                            maxTags: 5,
                            dropdown: {
                                maxItems: 20,
                                classname: "tags-look-project-order-type",
                                enabled: 0,
                                closeOnSelect: true
                            },
                        });
                        // Set default value
                        let tags = [];
                        if (typeof this.project.project_order_type === 'string' && this.project.project_order_type) {
                            tags = this.project.project_order_type.split(',').map(s => s.trim()).filter(Boolean);
                        }
                        // if (tags.length > 0) {
                        //     this.projectOrderTypeTagify.addTags(tags);
                        // }
                        const updateOrderType = () => {
                            this.project.project_order_type = this.projectOrderTypeTagify.value.map(tag => tag.value).join(',');
                        };
                        this.projectOrderTypeTagify.on('add', updateOrderType);
                        this.projectOrderTypeTagify.on('remove', updateOrderType);
                    }
                    
                    // --- Tagify for building_branch ---
                    const buildingBranchInput = document.querySelector('#building_branch');
                    if (buildingBranchInput && window.Tagify && !buildingBranchInput._tagify) {
                        if (this.buildingBranchTagify) {
                            try {
                                this.buildingBranchTagify.destroy();
                            } catch (e) {
                                console.log('Error destroying existing buildingBranchTagify:', e);
                            }
                        }
                        this.buildingBranchTagify = new Tagify(buildingBranchInput, {
                            whitelist: this.japanPrefectures,
                            maxTags: 10,
                            dropdown: {
                                maxItems: 20,
                                classname: "tags-look-building-branch",
                                enabled: 0,
                                closeOnSelect: true
                            },
                        });
                        // Set default value
                        let buildingBranchTags = [];
                        if (typeof this.project.building_branch === 'string' && this.project.building_branch) {
                            buildingBranchTags = this.project.building_branch.split(',').map(s => s.trim()).filter(Boolean);
                        }
                        // if (buildingBranchTags.length > 0) {
                        //     this.buildingBranchTagify.addTags(buildingBranchTags);
                        // }
                        const updateBuildingBranch = () => {
                            this.project.building_branch = this.buildingBranchTagify.value.map(tag => tag.value).join(',');
                        };
                        this.buildingBranchTagify.on('add', updateBuildingBranch);
                        this.buildingBranchTagify.on('remove', updateBuildingBranch);
                    } else {
                    }
                }, 100);
            });
        },
        initProjectTagsTagify() {
            const input = document.getElementById('project_tags');
            if (!input) return;
            
            // Destroy previous Tagify instance if exists
            if (input._tagify) {
                input._tagify.destroy();
            }
            
            // Initialize Tagify for project tags
            const tagify = new Tagify(input );
            
            // // Add existing tags if any
            // if (this.project.tags) {
            //     const tags = this.project.tags.split(',').map(tag => tag.trim()).filter(tag => tag);
            //     tagify.addTags(tags);
            // }
            
            // // Store reference
            this.projectTagsTagify = tagify;
            
            // Add event listeners for auto-save
            tagify.on('add', () => {
                this.updateTags();
            });
            
            tagify.on('remove', () => {
                this.updateTags();
            });
            
            tagify.on('change', () => {
                this.updateTags();
            });
        },
        async onTeamTagsChange(e) {
            const selected = this.tagify.value; // [{value, id}]
            const ids = selected.map(t => t.id).join(',');
            this.newProject.teams = ids;
            
            // try {
            //     const formData = new FormData();
            //     formData.append('id', this.projectId);
            //     formData.append('teams', ids);
            //     const res = await axios.post('/api/index.php?model=project&method=updateTeams', formData);
            //     if (res.data && res.data.success !== false) {
            //         // Reload lại team_list để hiển thị badge đúng
            //         await this.loadTeamListByIds(ids);
            //         // Chỉ tự động thêm member nếu team thay đổi so với ban đầu hoặc trước đó không có tag nào
            //         const originalTeamIds = (this.originalProject && this.originalProject.team_list) ? this.originalProject.team_list.map(t => String(t.id)).sort() : [];
            //         const newTeamIds = selected.map(t => String(t.id)).sort();
            //         const isChanged = originalTeamIds.length !== newTeamIds.length || originalTeamIds.some((id, idx) => id !== newTeamIds[idx]);
            //         const wasEmpty = !this.prevTeamIds || this.prevTeamIds.length === 0;
            //         if (isChanged || wasEmpty) {
            //             await this.addTeamMembersToMembers(newTeamIds);
            //         }
            //         // Cập nhật prevTeamIds cho lần sau
            //         this.prevTeamIds = [...newTeamIds];
            //     } else {
            //         alert('チームの更新に失敗しました。');
            //     }
            // } catch (error) {
            //     alert('チームの更新に失敗しました。');
            // }
        },
        async addTeamMembersToMembers(teamIds) {
            // Lấy toàn bộ user trong department nếu chưa có
            await this.loadDepartmentUsers();
            let memberIds = [];
            for (const teamId of teamIds) {
                try {
                    const res = await axios.get(`/api/index.php?model=team&method=get&id=${teamId}`);
                    if (res.data && Array.isArray(res.data.members)) {
                        for (const m of res.data.members) {
                            if (!memberIds.includes(m.user_id)) {
                                memberIds.push(m.user_id);
                            }
                        }
                    }
                } catch (e) {}
            }
            // Add vào Tagify members (không trùng)
            if (this.membersTagify) {
                const current = this.membersTagify.value.map(t => t.id);
                const toAdd = memberIds.filter(id => !current.includes(id));
                const allMembers = (this.departmentUsers || []).filter(u => toAdd.includes(u.user_id || u.id));
                this.membersTagify.addTags(allMembers.map(u => ({
                    id: u.user_id,
                    value: u.user_name
                })));
            }
        },
        initDatePickers() {
            if (!this.isEditMode) return;
            const options = {
                enableTime: true,
                dateFormat: "Y/m/d H:i",
                time_24hr: true,
                allowInput: true,
                locale: "ja",
                onChange: (selectedDates, dateStr, instance) => {
                    const id = instance.input.id;
                    if (id === 'start_date_picker') this.project.start_date = dateStr;
                    if (id === 'end_date_picker') this.project.end_date = dateStr;
                    // if (id === 'actual_end_date_picker') this.project.actual_end_date = dateStr;
                }
            };
            ['start_date_picker', 'end_date_picker', 'actual_end_date_picker'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    if (el._flatpickr) el._flatpickr.destroy();
                    flatpickr(el, options);
                }
            });
        },
        async initManagerMembersTagify() {
            // Lấy toàn bộ user trong department
            await this.loadDepartmentUsers();
            const allMembers = (this.departmentUsers || []).map(u => ({
                user_id: u.id,
                id: u.id,
                value: u.user_name,
                name: u.user_name
            }));
            // Khởi tạo Tagify cho manager
            const managerInput = document.getElementById('manager_tags');
            if (managerInput && window.Tagify) {
                if (managerInput._tagify) managerInput._tagify.destroy();
                const tagify = new Tagify(managerInput, {
                    whitelist: allMembers,
                    maxTags: 10,
                    enforceWhitelist: false,
                    dropdown: { maxItems: 20, enabled: 0, closeOnSelect: true }
                });
                tagify.addTags((this.managers || []).map(m => ({ id: m.user_id, value: m.user_name })));
                tagify.on('change', e => {
                    // Không lưu ngay, chỉ cập nhật UI
                    const selected = tagify.value.map(t => t.id);
                    this.newProject.managers = selected;
                });
                this.managerTagify = tagify;
            }
            // Khởi tạo Tagify cho members
            const membersInput = document.getElementById('members_tags');
            if (membersInput && window.Tagify) {
                if (membersInput._tagify) membersInput._tagify.destroy();
                const tagify = new Tagify(membersInput, {
                    whitelist: allMembers,
                    maxTags: 20,
                    enforceWhitelist: false,
                    dropdown: { maxItems: 20, enabled: 0, closeOnSelect: true }
                });
                tagify.addTags((this.members || []).map(m => ({ id: m.user_id, value: m.user_name })));
                tagify.on('change', e => {
                    // Không lưu ngay, chỉ cập nhật UI
                    const selected = tagify.value.map(t => t.id);
                    this.newProject.members = selected;
                });
                this.membersTagify = tagify;
            }
        },
        toggleEditMode() {
            if (!this.canEditProject()) {
                showMessage('管理者のみプロジェクトを編集できます。', true);
                return;
            }
            this.isEditMode = true;
            this.originalProject = { ...this.project };
            // Format dates for input fields (keep as yyyy/MM/dd HH:mm)
            if (this.project.start_date) {
                this.project.start_date = this.formatDateTime(this.project.start_date);
            } else {
                this.project.start_date = '';
            }
            if (this.project.end_date) {
                this.project.end_date = this.formatDateTime(this.project.end_date);
            } else {
                this.project.end_date = '';
            }
            // if (this.project.actual_end_date) {
            //     this.project.actual_end_date = this.formatDateTime(this.project.actual_end_date);
            // } else {
            //     this.project.actual_end_date = '';
            // }
            // Lưu lại prevTeamIds khi vào edit mode
            this.prevTeamIds = (this.project.team_list || []).map(t => String(t.id)).sort();
            // Sync custom fields
            this.$nextTick(() => {
                this.initDatePickers();
                this.initTagify();
                this.initManagerMembersTagify();
            });
        },
        toAPIDate(str) {
            if (!str) return '';
            return str.replace(/\//g, '-');
        },
        prepareCustomFieldsForSave() {
            if (!this.selectedCustomFieldSet) return [];
            return this.selectedCustomFieldSet.fields.map((f, idx) => {
                let value = '';
                if (f.type === 'checkbox') {
                    value = Array.isArray(this.customFields[idx].valueArr) ? this.customFields[idx].valueArr.join(',') : '';
                } else {
                    value = this.customFields[idx]?.value || '';
                }
                // Save full field structure
                return {
                    label: f.label,
                    type: f.type,
                    options: f.options,
                    value
                };
            });
        },
        async saveProject() {
            // Use the stored quill content instead of syncing from editor
            if (this.quillContent !== undefined) {
                this.project.description = this.quillContent;
            }
            
            // Save custom field set id and values
            this.project.department_custom_fields_set_id = this.project.department_custom_fields_set_id || '';
            this.project.custom_fields = JSON.stringify(this.prepareCustomFieldsForSave());
            try {
                const formData = new FormData();
                formData.append('id', this.project.id);
                formData.append('name', this.project.name);
                formData.append('building_branch', this.project.building_branch);
                formData.append('building_size', this.project.building_size);
                formData.append('building_type', this.project.building_type);
                formData.append('building_number', this.project.building_number);
                formData.append('project_number', this.project.project_number);
                formData.append('progress', this.project.progress);
                formData.append('priority', this.project.priority || '');
                formData.append('status', this.project.status);
                formData.append('teams', this.newProject.teams);
                formData.append('members', this.newProject.members || '');
                formData.append('managers', this.newProject.managers || '');
                formData.append('start_date', this.toAPIDate(this.project.start_date));
                formData.append('end_date', this.toAPIDate(this.project.end_date));
                formData.append('project_order_type', this.project.project_order_type);
                formData.append('customer_id', this.project.customer_id);
                formData.append('amount', this.project.amount);
                formData.append('estimate_status', this.project.estimate_status);
                formData.append('invoice_status', this.project.invoice_status);
                formData.append('tags', this.project.tags);
                formData.append('department_custom_fields_set_id', this.project.department_custom_fields_set_id);
                formData.append('custom_fields', this.project.custom_fields);
                formData.append('description', this.project.description || '');
                const response = await axios.post('/api/index.php?model=project&method=update', formData);
                if (response.data && response.data.status == 'success') {
                    this.isEditMode = false;
                    this.originalProject = null;
                    showMessage('プロジェクトを更新しました。');
                    await this.loadProject();
                } else {
                    showMessage('プロジェクトの更新に失敗しました。', true);
                }
            } catch (error) {
                console.error('Error saving project:', error);
                showMessage('プロジェクトの更新に失敗しました。', true);
            }
        },
        cancelEdit() {
            // Sync quill content before canceling
            if (this.quillInstance && this.quillContent !== undefined) {
                this.project.description = this.quillContent;
            }
            
            this.isEditMode = false;
            this.project = { ...this.originalProject };
            this.loadMembers(); // Restore managers and members from backend for correct avatars
        },
        getCategoryName(id) {
            const cat = this.categories.find(c => String(c.id) === String(id));
            return cat ? cat.name : '-';
        },
        getContactName(id) {
            const contact = this.contacts.find(c => String(c.id) === String(id));
            return contact ? contact.name : '-';
        },
        async loadAllUsers() {
            // Load all users for selection modal
            try {
                const res = await axios.get('/api/index.php?model=user&method=getList');
                this.allUsers = res.data.list || [];
            } catch (e) {
                this.allUsers = [];
            }
        },
        openMemberSelect(type) {
            this.memberSelectType = type;
            this.showMemberModal = true;
            if (type === 'manager') {
                // Lấy toàn bộ user
                this.loadAllUsers();
                this.memberSelected = this.managers.map(m => m.userid);
            } else {
                // Chỉ lấy user thuộc các team đã chọn
                this.loadTeamMembersForModal();
                this.memberSelected = this.members.map(m => m.userid);
            }
        },
        async loadTeamMembersForModal() {
            // Lấy danh sách team đã chọn
            let teamIds = [];
            if (Array.isArray(this.project.team_list)) {
                teamIds = this.project.team_list.map(t => t.id);
            } else if (typeof this.project.teams === 'string') {
                teamIds = this.project.teams.split(',').map(id => id.trim()).filter(Boolean);
            }
            let allMembers = [];
            let seen = new Set();
            for (const teamId of teamIds) {
                try {
                    const res = await axios.get(`/api/index.php?model=team&method=get&id=${teamId}`);
                    if (res.data && Array.isArray(res.data.members)) {
                        for (const m of res.data.members) {
                            if (!seen.has(m.user_id)) {
                                allMembers.push({
                                    userid: m.user_id,
                                    user_name: m.user_name,
                                    user_image: m.user_image
                                });
                                seen.add(m.user_id);
                            }
                        }
                    }
                } catch (e) {}
            }
            this.allUsers = allMembers;
        },
        toggleMemberSelect(userId) {
            const idx = this.memberSelected.indexOf(userId);
            if (idx === -1) {
                this.memberSelected.push(userId);
            } else {
                this.memberSelected.splice(idx, 1);
            }
        },
        async confirmMemberSelect() {
            this.showMemberModal = false;
            if (this.memberSelectType === 'manager') {
                // Chỉ cập nhật UI, không lưu ngay
                this.managers = this.departmentUsers.filter(u => this.memberSelected.includes(String(u.user_id || u.id)));
            } else {
                this.members = this.departmentUsers.filter(u => this.memberSelected.includes(String(u.user_id || u.id)));
            }
        },
        async loadDepartmentUsers() {
            if (!this.project.department_id) return [];
            try {
                const res = await axios.get(`/api/index.php?model=department&method=get_users&department_id=${this.project.department_id}`);
                this.departmentUsers = res.data || [];
                return this.departmentUsers;
            } catch (e) {
                this.departmentUsers = [];
                return [];
            }
        },
        // Notes functionality
        async loadNotes() {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getNotes&project_id=${this.projectId}`);
                if (response.data && response.data.status === 'success') {
                    this.notes = response.data.data || [];
                } else {
                    this.notes = [];
                }
            } catch (error) {
                console.error('Error loading notes:', error);
                this.notes = [];
            }
        },
        openNoteModal(note = null) {
            this.showNoteModal = true;
            this.isNoteEditMode = false;
            
            if (note) {
                // Edit existing note
                this.editingNote = {
                    id: note.id,
                    title: note.title,
                    content: note.content,
                    is_important: note.is_important == 1,
                    user_id: note.user_id
                };
            } else {
                // Create new note
                this.editingNote = {
                    id: null,
                    title: '',
                    content: '',
                    is_important: false,
                    user_id: null
                };
            }
        },
        closeNoteModal() {
            this.showNoteModal = false;
            this.isNoteEditMode = false;
            this.editingNote = {
                id: null,
                title: '',
                content: '',
                is_important: false,
                user_id: null
            };
        },
        async saveNote() {
            if (!this.editingNote.title.trim()) {
                this.showNotification('タイトルを入力してください', 'error');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('project_id', this.projectId);
                formData.append('title', this.editingNote.title.trim());
                formData.append('content', this.editingNote.content || '');
                formData.append('is_important', this.editingNote.is_important ? 1 : 0);
                
                let response;
                if (this.editingNote.id) {
                    // Update existing note
                    formData.append('id', this.editingNote.id);
                    response = await axios.post('/api/index.php?model=project&method=updateNote', formData);
                } else {
                    // Create new note
                    response = await axios.post('/api/index.php?model=project&method=addNote', formData);
                }
                
                if (response.data && response.data.status === 'success') {
                    this.showNotification('メモが保存されました', 'success');
                    this.closeNoteModal();
                    await this.loadNotes();
                } else {
                    this.showNotification('メモの保存に失敗しました', 'error');
                }
            } catch (error) {
                console.error('Error saving note:', error);
                this.showNotification('メモの保存に失敗しました', 'error');
            }
        },
        async deleteNote(noteId) {
            if (!confirm('このメモを削除しますか？')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('id', noteId);
                
                const response = await axios.post('/api/index.php?model=project&method=deleteNote', formData);
                
                if (response.data && response.data.status === 'success') {
                    this.showNotification('メモが削除されました', 'success');
                    await this.loadNotes();
                } else {
                    this.showNotification('メモの削除に失敗しました', 'error');
                }
            } catch (error) {
                console.error('Error deleting note:', error);
                this.showNotification('メモの削除に失敗しました', 'error');
            }
        },
        getNotePreview(content) {
            if (!content) return '';
            // Remove HTML tags and limit to 100 characters
            const textContent = content.replace(/<[^>]*>/g, '');
            return textContent.length > 100 ? textContent.substring(0, 100) + '...' : textContent;
        },
        canDeleteNote(note) {
            // Only note creator or managers can delete notes
            return this.isManager || (note.user_id && String(note.user_id) === String(USER_AUTH_ID));
        },
        canEditNote(note) {
            // Only note creator or managers can edit notes
            return this.isManager || (note.user_id && String(note.user_id) === String(USER_AUTH_ID));
        },
        // Estimate status methods
        getEstimateStatusLabel(status) {
            const statusObj = this.estimateStatuses.find(s => s.value === status);
            return statusObj ? statusObj.label : '未発行';
        },
        getEstimateStatusBadgeClass(status) {
            const statusObj = this.estimateStatuses.find(s => s.value === status);
            return statusObj ? `bg-${statusObj.color}` : 'bg-secondary';
        },
        getEstimateStatusButtonClass(status) {
            const statusObj = this.estimateStatuses.find(s => s.value === status);
            return statusObj ? `btn-${statusObj.color}` : 'btn-secondary';
        },
        selectEstimateStatus(status) {
            this.project.estimate_status = status;
            this.updateProjectStatus();
            // Close dropdown
            const dropdownElement = document.querySelector('#estimateStatusDropdown');
            if (dropdownElement) {
                const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                if (dropdown) {
                    dropdown.hide();
                }
            }
        },
        // Invoice status methods
        getInvoiceStatusLabel(status) {
            const statusObj = this.invoiceStatuses.find(s => s.value === status);
            return statusObj ? statusObj.label : '未発行';
        },
        getInvoiceStatusBadgeClass(status) {
            const statusObj = this.invoiceStatuses.find(s => s.value === status);
            return statusObj ? `bg-${statusObj.color}` : 'bg-secondary';
        },
        getInvoiceStatusButtonClass(status) {
            const statusObj = this.invoiceStatuses.find(s => s.value === status);
            return statusObj ? `btn-${statusObj.color}` : 'btn-secondary';
        },
        selectInvoiceStatus(status) {
            this.project.invoice_status = status;
            this.updateProjectStatus();
            // Close dropdown
            const dropdownElement = document.querySelector('#invoiceStatusDropdown');
            if (dropdownElement) {
                const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                if (dropdown) {
                    dropdown.hide();
                }
            }
        },
        showNotification(message, type = 'info') {
            // Use showMessage function if available, otherwise use alert
            if (typeof showMessage === 'function') {
                showMessage(message, type === 'error');
            } else {
                alert(message);
            }
        },
        initQuillEditor() {
            if (this.quillInstance || !this.isEditMode) return;
            
            // Use a longer delay to ensure all other components are initialized first
            setTimeout(() => {
                const toolbarOptions = [
                    [
                        { font: [] },
                        { size: [] }
                    ],
                    ['bold', 'italic', 'underline', 'strike'],
                    [
                        { color: [] },
                        { background: [] }
                    ],
                    [
                        { script: 'super' },
                        { script: 'sub' }
                    ],
                    [
                        { header: '1' },
                        { header: '2' }, 'blockquote' ],
                    [
                        { list: 'ordered' },
                        { indent: '-1' },
                        { indent: '+1' }
                    ],
                    [{ direction: 'rtl' }, { align: [] }],
                    ['link', 'image', 'video', 'formula'],
                    ['clean']
                ];
                const el = document.getElementById('quill_description');
                if (!el) return;
                
                // Destroy existing instance if any
                if (this.quillInstance) {
                    try {
                        this.quillInstance = null;
                    } catch (e) {
                        console.log('Error destroying existing quill instance:', e);
                    }
                }
                
                this.quillInstance = new Quill(el, {
                    bounds: el,
                    placeholder: 'Type Something...',
                    modules: {
                        syntax: true,
                        toolbar: {
                            container: toolbarOptions,
                            handlers: {
                                image: () => this.imageHandler()
                            }
                        }
                    },
                    theme: 'snow'
                });
                
                // Set initial content
                if (this.project.description) {
                    const html = this.decodeHtmlEntities(this.project.description);
                    this.quillInstance.root.innerHTML = html;
                }
                
                // Store content in a separate variable, not in Vue data
                this.quillContent = this.quillInstance.getSemanticHTML();
                
                // Simple text-change handler without debounce
                this.quillInstance.on('text-change', () => {
                    this.quillContent = this.quillInstance.getSemanticHTML();
                });
                
                // Prevent focus loss by stopping event propagation on toolbar clicks
                const toolbar = this.quillInstance.getModule('toolbar');
                if (toolbar && toolbar.container) {
                    toolbar.container.addEventListener('mousedown', (e) => {
                        e.stopPropagation();
                    });
                    toolbar.container.addEventListener('click', (e) => {
                        e.stopPropagation();
                    });
                }
                
                // Focus the editor after initialization
                // setTimeout(() => {
                //     if (this.quillInstance) {
                //         this.quillInstance.focus();
                //     }
                // }, 100);
                
            }, 400); // Increased delay to ensure other components are initialized first
        },
        destroyQuillEditor() {
            if (this.quillInstance) {
                try {
                    // Remove event listeners from toolbar
                    const toolbar = this.quillInstance.getModule('toolbar');
                    if (toolbar && toolbar.container) {
                        toolbar.container.removeEventListener('mousedown', (e) => {
                            e.stopPropagation();
                        });
                        toolbar.container.removeEventListener('click', (e) => {
                            e.stopPropagation();
                        });
                    }
                    
                    // Clear the editor content
                    this.quillInstance.setText('');
                    
                    // Clear the stored content
                    this.quillContent = '';
                    
                    // Destroy the instance
                    this.quillInstance = null;
                } catch (e) {
                    console.log('Error destroying quill editor:', e);
                    this.quillInstance = null;
                }
            }
        },
        decodeHtmlEntities(str) {
            const txt = document.createElement('textarea');
            txt.innerHTML = str;
            return txt.value;
        },
        
        // Image handler for Quill editor
        imageHandler() {
            const input = document.createElement('input');
            input.setAttribute('type', 'file');
            input.setAttribute('accept', 'image/*');
            input.click();
            
            input.onchange = async () => {
                const file = input.files[0];
                if (file) {
                    try {
                        // Kiểm tra kích thước file (max 5MB)
                        if (file.size > 5 * 1024 * 1024) {
                            this.showNotification('ファイルサイズは5MB以下にしてください。', 'error');
                            return;
                        }
                        
                        // Upload ảnh
                        const uploadUrl = '/api/quill-image-upload.php';
                        let response;
                        
                        // Debug: log project ID
                        console.log('Project ID for upload:', this.projectId);
                        
                        if (window.swManager && window.swManager.swRegistration) {
                            // Sử dụng Service Worker với project_id
                            response = await window.swManager.uploadFile(file, uploadUrl, { project_id: this.projectId });
                        } else {
                            // Fallback to regular upload
                            const formData = new FormData();
                            formData.append('image', file);
                            formData.append('project_id', this.projectId);
                            const uploadResponse = await axios.post(uploadUrl, formData, {
                                headers: {
                                    'Content-Type': 'multipart/form-data'
                                }
                            });
                            response = uploadResponse.data;
                        }
                        
                        if (response.success) {
                            // Chèn ảnh vào cuối editor mà không dùng getSelection
                            requestAnimationFrame(() => {
                                try {
                                    if (this.quillInstance && this.quillInstance.root) {
                                        // Lấy độ dài hiện tại của nội dung
                                        const length = this.quillInstance.getLength();
                                        
                                        // Chèn ảnh ở cuối
                                        this.quillInstance.insertEmbed(length - 1, 'image', response.url);
                                        this.quillInstance.insertText(length, '\n');
                                        
                                        // Focus vào editor
                                        this.quillInstance.focus();
                                        
                                        // Scroll xuống cuối
                                        if (this.quillInstance.scrollingContainer) {
                                            this.quillInstance.scrollingContainer.scrollTop = this.quillInstance.scrollingContainer.scrollHeight;
                                        }
                                    }
                                } catch (error) {
                                    console.error('Error inserting image:', error);
                                    // Fallback: append trực tiếp vào HTML
                                    if (this.quillInstance && this.quillInstance.root) {
                                        const imageHtml = `<p><img src="${response.url}" alt="Uploaded image" style="max-width: 100%; height: auto;"></p>`;
                                        this.quillInstance.root.innerHTML += imageHtml;
                                    }
                                }
                            });
                        } else {
                            this.showNotification('画像のアップロードに失敗しました: ' + (response.error || 'Unknown error'), 'error');
                        }
                    } catch (error) {
                        console.error('Error uploading image:', error);
                        this.showNotification('画像のアップロードに失敗しました。', 'error');
                    }
                }
            };
        },
        removeImagePlaceholder() {
            try {
                if (this.quillInstance && this.quillInstance.root) {
                    const content = this.quillInstance.getContents();
                    let placeholderIndex = -1;
                    
                    // Tìm vị trí của placeholder
                    for (let i = 0; i < content.ops.length; i++) {
                        if (content.ops[i].insert === '📷') {
                            placeholderIndex = i;
                            break;
                        }
                    }
                    
                    if (placeholderIndex !== -1) {
                        // Xóa placeholder
                        this.quillInstance.deleteText(placeholderIndex, 1);
                    }
                }
            } catch (error) {
                console.error('Error removing placeholder:', error);
            }
        },
        handleBeforeUnload(event) {
            event.preventDefault();
            event.returnValue = '編集中の内容が保存されていません。本当にページを離れますか？';
            return event.returnValue;
        },
        async loadDepartmentCustomFieldSets() {
            if (!this.project || !this.project.department_id) {
                this.departmentCustomFieldSets = [];
                return;
            }
            try {
                const res = await axios.get('/api/index.php?model=department&method=getCustomFields');
                if (Array.isArray(res.data)) {
                    this.departmentCustomFieldSets = res.data.filter(set => String(set.department_id) === String(this.project.department_id));
                } else {
                    this.departmentCustomFieldSets = [];
                }
            } catch (e) {
                this.departmentCustomFieldSets = [];
            }
        },
        getDepartmentCustomFieldSetName(id) {
            const set = this.departmentCustomFieldSets.find(s => String(s.id) === String(id));
            return set ? set.name : '-';
        },
        getCustomFieldValue(label) {
            if (!this.project || !this.project.custom_fields) return '';
            let arr = [];
            let raw = this.project.custom_fields;
            if (typeof raw === 'string' && raw.includes('&quot;')) {
                raw = raw.replace(/&quot;/g, '"');
            }
            if (typeof raw === 'string') {
                try { arr = JSON.parse(raw); } catch (e) { arr = []; }
            } else if (Array.isArray(raw)) {
                arr = raw;
            }
            const found = arr.find(f => f.label && f.label.trim() === label.trim());
            return found ? found.value : '';
        },
        getCustomFieldsForView() {
            // Parse and return array of fields (with type/options/value)
            let arr = [];
            let raw = this.project?.custom_fields;
            if (typeof raw === 'string' && raw.includes('&quot;')) {
                raw = raw.replace(/&quot;/g, '"');
            }
            if (typeof raw === 'string') {
                try { arr = JSON.parse(raw); } catch (e) { arr = []; }
            } else if (Array.isArray(raw)) {
                arr = raw;
            }
            return arr;
        },
        async loadCompaniesByCategory() {
            if (!this.project.category_id) {
                this.companies = [];
                return;
            }
            const params = new URLSearchParams({
                category_id: this.project.category_id
            });
            // if (this.project.department_id) {
            //     params.append('department_id', this.project.department_id);
            // }
            const res = await axios.get(`/api/index.php?model=customer&method=list_companies_by_category&${params.toString()}`);
            if (res.data && res.data.data) {
                this.companies = res.data.data;
            }
        },
        async loadContactsByCompany() {
            if (!this.project.company_name) {
                this.contacts = [];
                return;
            }
            const params = new URLSearchParams({
                company_name: this.project.company_name
            });
            // if (this.project.department_id) {
            //     params.append('department_id', this.project.department_id);
            // }
            const res = await axios.get(`/api/index.php?model=customer&method=list_contacts_by_company&${params.toString()}`);
            if (res.data && res.data.data) {
                this.contacts = res.data.data;
            }
            
            // Mention functionality methods
            await this.loadMentionUsers();
        },
        // Mention functionality methods
        async loadMentionUsers() {
            try {
                // Load department users and administrators
                const response = await axios.get(`/api/index.php?model=user&method=getMentionUsers&department_id=${this.project.department_id}`);
                this.mentionUsers = response.data || [];
            } catch (error) {
                console.error('Error loading mention users:', error);
                this.mentionUsers = [];
            }
        },
        async loadLogs() {
            try {
                const res = await axios.get(`/api/index.php?model=project&method=getLogs&project_id=${this.projectId}`);
                if (res.data && Array.isArray(res.data)) {
                    this.logs = res.data;
                } else {
                    this.logs = [];
                }
            } catch (e) {
                this.logs = [];
            }
        },
        loadCurrentUser() {
            // Set basic user data from global variables
            this.currentUser.userid = typeof USER_ID !== 'undefined' ? USER_ID : null;
            this.currentUser.realname =  typeof USER_NAME !== 'undefined' ? USER_NAME : 'User';
            this.currentUser.user_image = typeof USER_IMAGE !== 'undefined' ? USER_IMAGE : null;
        },
        historyIcon(action) {
            switch(action) {
                case 'created': return 'fa fa-pencil-alt text-primary';
                case 'approved': return 'fa fa-check-circle text-success';
                case 'rejected': return 'fa fa-times-circle text-danger';
                case 'draft': return 'fa fa-edit text-warning';
                case 'updated': return 'fa fa-sync text-info';
                case 'status_changed': return 'fa fa-random text-primary';
                case 'comment': return 'fa fa-comment-dots text-secondary';
                case 'member_added': return 'fa fa-user-plus text-success';
                case 'member_removed': return 'fa fa-user-minus text-danger';
                case 'deleted': return 'fa fa-trash text-danger';
                case 'date_updated': return 'fa fa-calendar-alt text-info';
                case 'priority_updated': return 'fa fa-exclamation text-warning';
                default: return 'fa fa-history';
            }
        },
        actionLabel(action) {
            switch(action) {
                case 'created': return '作成';
                case 'approved': return '承認';
                case 'rejected': return '却下';
                case 'draft': return '下書き';
                case 'updated': return '更新';
                case 'status_changed': return 'ステータス変更';
                case 'comment': return 'コメント';
                case 'member_added': return 'メンバー追加';
                case 'member_removed': return 'メンバー削除';
                default: return action;
            }
        },
        getLogBadgeClass(log, field) {
            if (log.action === 'status_changed') {
                return 'badge ' + this.getStatusBadgeClass(log[field]);
            }
            if (log.action === 'priority_updated') {
                return 'badge ' + this.getPriorityBadgeClass(log[field]);
            }
            return field === 'value1' ? 'badge bg-secondary' : 'badge bg-primary';
        },
        getLogBadgeLabel(log, field) {
            if (log.action === 'status_changed') {
                return this.getStatusLabel(log[field]);
            }
            if (log.action === 'priority_updated') {
                return this.getPriorityLabel(log[field]);
            }
            return log[field];
        },
        
        // Upload progress handling methods
        updateUploadProgress(fileName, progress) {
            // Update progress bar if exists
            const progressBar = document.querySelector(`[data-file="${fileName}"] .progress-bar`);
            if (progressBar) {
                progressBar.style.width = progress + '%';
                progressBar.textContent = progress + '%';
            }
        },
        
        handleUploadSuccess(fileName, data) {
            console.log('Upload successful:', fileName, data);
            // Remove progress indicator if exists
            const progressContainer = document.querySelector(`[data-file="${fileName}"]`);
            if (progressContainer) {
                progressContainer.remove();
            }
        },
        
        handleUploadError(fileName, error) {
            console.error('Upload failed:', fileName, error);
            // Remove progress indicator and show error
            const progressContainer = document.querySelector(`[data-file="${fileName}"]`);
            if (progressContainer) {
                progressContainer.remove();
            }
            this.showNotification(`アップロードに失敗しました: ${fileName}`, 'error');
        },
        
        // Comment functionality moved to CommentComponent
    },
    watch: {
        isEditMode(newVal) {
            if (newVal) {
                this.$nextTick(() => {
                    // Sync customFields from project.custom_fields or set
                    let saved = [];
                    let raw = this.project.custom_fields;
                    if (typeof raw === 'string' && raw.includes('&quot;')) {
                        raw = raw.replace(/&quot;/g, '"');
                    }
                    if (typeof raw === 'string') {
                        try { saved = JSON.parse(raw); } catch (e) { saved = []; }
                    } else if (Array.isArray(raw)) {
                        saved = raw;
                    }
                    if (saved.length && saved[0] && saved[0].type) {
                        this.customFields = saved.map(f => {
                            if (f.type === 'checkbox') {
                                let arr = [];
                                if (f.value) arr = f.value.split(',').map(s => s.trim()).filter(Boolean);
                                return { ...f, valueArr: arr };
                            } else {
                                return { ...f };
                            }
                        });
                    } else if (this.selectedCustomFieldSet) {
                        this.customFields = this.selectedCustomFieldSet.fields.map(f => {
                            const found = saved.find(v => v && v.label && v.label.trim() === f.label.trim());
                            if (f.type === 'checkbox') {
                                let arr = [];
                                if (found && found.value) arr = found.value.split(',').map(s => s.trim()).filter(Boolean);
                                return { label: f.label, type: f.type, options: f.options, value: arr.join(','), valueArr: arr };
                            } else {
                                return { label: f.label, type: f.type, options: f.options, value: found ? found.value : '' };
                            }
                        });
                    }
                    // Initialize Select2 dropdowns
                    $('#category_id').select2({
                        placeholder: '選択してください',
                        dropdownParent: $('#category_id').parent(),
                        allowClear: true,
                        minimumResultsForSearch: 0,
                        ajax: {
                            url: '/api/index.php?model=customer&method=list_categories',
                            dataType: 'json',
                            delay: 250,
                            data: (params) => {
                                const data = {
                                    search: params.term,
                                    page: params.page || 1
                                };
                                if (this.project.department_id) {
                                    data.department_id = this.project.department_id;
                                }
                                return data;
                            },
                            processResults: function(data) {
                                return {
                                    results: data.data.map(function(item) {
                                        return {
                                            id: item.id,
                                            text: item.name
                                        };
                                    })
                                };
                            }
                        }
                    }).on('select2:select', (e) => {
                        this.project.category_id = e.params.data.id;
                        this.onCategoryChange();
                    });
                    
                    // Company name (company_name)
                    const $company = $('#company_name');
                    if ($company.length) {
                        $company.select2({
                            placeholder: '選択してください',
                            dropdownParent: $company.parent(),
                            allowClear: true,
                            minimumResultsForSearch: 0,
                            ajax: {
                                url: '/api/index.php?model=customer&method=list_companies_by_category',
                                dataType: 'json',
                                delay: 250,
                                data: (params) => {
                                    const data = {
                                        search: params.term,
                                        page: params.page || 1,
                                        category_id: this.project.category_id
                                    };
                                    if (this.project.department_id) {
                                        data.department_id = this.project.department_id;
                                    }
                                    return data;
                                },
                                processResults: function(data) {
                                    return {
                                        results: data.data.map(function(item) {
                                            return {
                                                id: item.company_name,
                                                text: item.company_name
                                            };
                                        })
                                    };
                                }
                            }
                        }).on('select2:select', (e) => {
                            this.project.company_name = e.params.data.id;
                            this.onCompanyChange();
                        });
                    }
                    // 担当者名 (customer_id)
                    const $customer = $('#customer_id');
                    if ($customer.length) {
                        $customer.select2({
                            placeholder: '選択してください',
                            dropdownParent: $customer.parent(),
                            allowClear: true,
                            minimumResultsForSearch: 0,
                            ajax: {
                                url: '/api/index.php?model=customer&method=list_contacts_by_company',
                                dataType: 'json',
                                delay: 250,
                                data: (params) => {
                                    const data = {
                                        search: params.term,
                                        page: params.page || 1,
                                        company_name: this.project.company_name
                                    };
                                    if (this.project.department_id) {
                                        data.department_id = this.project.department_id;
                                    }
                                    return data;
                                },
                                processResults: function(data) {
                                    return {
                                        results: data.data.map(function(item) {
                                            return {
                                                id: item.id,
                                                text: item.name
                                            };
                                        })
                                    };
                                }
                            }
                        }).on('select2:select', (e) => {
                            this.project.customer_id = e.params.data.id;
                        });
                    }
                    
                    // Initialize Tagify with delay to ensure DOM is ready
                    setTimeout(() => {
                        console.log('Initializing Tagify...');
                        
                        // Clear any existing tagify instances first
                        document.querySelectorAll('.tagify').forEach(el => {
                            if (el._tagify) {
                                try {
                                    el._tagify.destroy();
                                } catch (e) {
                                    console.log('Error destroying existing tagify:', e);
                                }
                            }
                        });
                        
                        // --- Tagify for project_order_type ---
                        const input = document.querySelector('#project_order_type');
                        if (input && window.Tagify && !input._tagify) {
                            if (this.projectOrderTypeTagify) {
                                try {
                                    this.projectOrderTypeTagify.destroy();
                                } catch (e) {
                                    console.log('Error destroying existing projectOrderTypeTagify:', e);
                                }
                            }
                            this.projectOrderTypeTagify = new Tagify(input, {
                                whitelist: ['新規', '修正', '免震', '耐震', '計画変更'],
                                maxTags: 5,
                                dropdown: {
                                    maxItems: 20,
                                    classname: "tags-look-project-order-type",
                                    enabled: 0,
                                    closeOnSelect: true
                                },
                            });
                            // Set default value
                            let tags = [];
                            if (typeof this.project.project_order_type === 'string' && this.project.project_order_type) {
                                tags = this.project.project_order_type.split(',').map(s => s.trim()).filter(Boolean);
                            }
                            // if (tags.length > 0) {
                            //     this.projectOrderTypeTagify.addTags(tags);
                            // }
                            const updateOrderType = () => {
                                this.project.project_order_type = this.projectOrderTypeTagify.value.map(tag => tag.value).join(',');
                            };
                            this.projectOrderTypeTagify.on('add', updateOrderType);
                            this.projectOrderTypeTagify.on('remove', updateOrderType);
                        }
                        
                        // --- Tagify for building_branch ---
                        const buildingBranchInput = document.querySelector('#building_branch');
                        if (buildingBranchInput && window.Tagify && !buildingBranchInput._tagify) {
                            if (this.buildingBranchTagify) {
                                try {
                                    this.buildingBranchTagify.destroy();
                                } catch (e) {
                                    console.log('Error destroying existing buildingBranchTagify:', e);
                                }
                            }
                            this.buildingBranchTagify = new Tagify(buildingBranchInput, {
                                whitelist: this.japanPrefectures,
                                maxTags: 10,
                                dropdown: {
                                    maxItems: 20,
                                    classname: "tags-look-building-branch",
                                    enabled: 0,
                                    closeOnSelect: true
                                },
                            });
                            // Set default value
                            let buildingBranchTags = [];
                            if (typeof this.project.building_branch === 'string' && this.project.building_branch) {
                                buildingBranchTags = this.project.building_branch.split(',').map(s => s.trim()).filter(Boolean);
                            }
                            // if (buildingBranchTags.length > 0) {
                            //     this.buildingBranchTagify.addTags(buildingBranchTags);
                            // }
                            const updateBuildingBranch = () => {
                                this.project.building_branch = this.buildingBranchTagify.value.map(tag => tag.value).join(',');
                            };
                            this.buildingBranchTagify.on('add', updateBuildingBranch);
                            this.buildingBranchTagify.on('remove', updateBuildingBranch);
                        } else {
                        }
                        
                        // Initialize Quill editor after all other components are ready
                        setTimeout(() => {
                            this.initQuillEditor();
                        }, 100);
                        
                    }, 100);
                });
                window.addEventListener('beforeunload', this.handleBeforeUnload);
            } else {
                this.destroyQuillEditor();
                $('#category_id').select2('destroy');
                $('#company_name').select2('destroy');
                $('#customer_id').select2('destroy');
                
                // Destroy Tagify for project_order_type
                if (this.projectOrderTypeTagify) {
                    try {
                        this.projectOrderTypeTagify.destroy();
                    } catch (e) {
                        console.log('Error destroying projectOrderTypeTagify:', e);
                    }
                    this.projectOrderTypeTagify = null;
                }
                
                // Destroy Tagify for building_branch
                if (this.buildingBranchTagify) {
                    try {
                        this.buildingBranchTagify.destroy();
                    } catch (e) {
                        console.log('Error destroying buildingBranchTagify:', e);
                    }
                    this.buildingBranchTagify = null;
                }
                
                window.removeEventListener('beforeunload', this.handleBeforeUnload);
            }
        },
        'project.department_id': function() {
            this.loadDepartmentCustomFieldSets();
            // Load companies and contacts for the selected department
            if (this.project && this.project.department_id) {
                this.loadCompaniesByCategory();
                this.loadContactsByCompany();
            } else {
                // Clear companies and contacts when no department is selected
                this.companies = [];
                this.contacts = [];
            }
        },
        'project.department_custom_fields_set_id': function(newVal) {
            if (this.isEditMode && newVal && this.selectedCustomFieldSet) {
                // Try to load from project.custom_fields (full structure)
                let saved = [];
                let raw = this.project.custom_fields;
                if (typeof raw === 'string' && raw.includes('&quot;')) {
                    raw = raw.replace(/&quot;/g, '"');
                }
                if (typeof raw === 'string') {
                    try { saved = JSON.parse(raw); } catch (e) { saved = []; }
                } else if (Array.isArray(raw)) {
                    saved = raw;
                }
                // If saved has type/options, use it directly, else fallback to set
                if (saved.length && saved[0] && saved[0].type) {
                    this.customFields = saved.map(f => {
                        if (f.type === 'checkbox') {
                            let arr = [];
                            if (f.value) arr = f.value.split(',').map(s => s.trim()).filter(Boolean);
                            return { ...f, valueArr: arr };
                        } else {
                            return { ...f };
                        }
                    });
                } else {
                    this.customFields = this.selectedCustomFieldSet.fields.map(f => {
                        const found = saved.find(v => v && v.label && v.label.trim() === f.label.trim());
                        if (f.type === 'checkbox') {
                            let arr = [];
                            if (found && found.value) arr = found.value.split(',').map(s => s.trim()).filter(Boolean);
                            return { label: f.label, type: f.type, options: f.options, value: arr.join(','), valueArr: arr };
                        } else {
                            return { label: f.label, type: f.type, options: f.options, value: found ? found.value : '' };
                        }
                    });
                }
            }
        },
        'customFields': {
            handler(newVal, oldVal) {
                // Keep value and valueArr in sync for checkboxes
                if (!this.selectedCustomFieldSet) return;
                
                // Only process if there are actual changes
                if (JSON.stringify(newVal) === JSON.stringify(oldVal)) return;
                
                this.selectedCustomFieldSet.fields.forEach((f, idx) => {
                    if (f.type === 'checkbox' && this.customFields[idx]) {
                        // If valueArr changes, update value
                        if (Array.isArray(this.customFields[idx].valueArr)) {
                            this.customFields[idx].value = this.customFields[idx].valueArr.join(',');
                        } else if (typeof this.customFields[idx].value === 'string') {
                            this.customFields[idx].valueArr = this.customFields[idx].value.split(',').map(s => s.trim()).filter(Boolean);
                        }
                    }
                });
            },
            deep: true
        },
    },
    async mounted() {
        await this.loadProject();
        await this.loadCategories();
        await this.loadAllTeams();
        await this.loadDepartmentCustomFieldSets();
        await this.loadNotes();
        await this.loadLogs();
        // Load current user data after members are loaded
        this.loadCurrentUser();
        this.initTooltips();
        
        // Initialize mention manager
        this.$nextTick(() => {
            if (window.mentionManager) {
                // If MentionManager already exists, set department ID and rebind
                if (this.project && this.project.department_id) {
                    window.mentionManager.setDepartmentId(this.project.department_id);
                }
                // Force rebind to ensure it picks up the contenteditable element
                window.mentionManager.bindToInputs();
            } else {
                // Create new MentionManager instance
                window.mentionManager = new MentionManager({
                    departmentId: this.project ? this.project.department_id : null
                });
            }
            
            this.initProjectTagsTagify();
        });
        
        // Add beforeunload event listener
        //window.addEventListener('beforeunload', this.handleBeforeUnload);
        
        // Add upload progress event listeners
        window.addEventListener('uploadProgress', (event) => {
            const { progress, fileName } = event.detail;
            this.updateUploadProgress(fileName, progress);
        });
        
        window.addEventListener('uploadSuccess', (event) => {
            const { data, fileName } = event.detail;
            this.handleUploadSuccess(fileName, data);
        });
        
        window.addEventListener('uploadError', (event) => {
            const { error, fileName } = event.detail;
            this.handleUploadError(fileName, error);
        });

        // Initialize Tagify for team selection
        // if (document.getElementById('team_tags')) {
        //     new Tagify(document.getElementById('team_tags'), {
        //         whitelist: (this.project.team_list || []).map(team => ({ value: team.id, text: team.name })),
        //         enforceWhitelist: true,
        //         mode: 'select',
        //         templates: {
        //             tag: function(tagData) {
        //                 return `
        //                     <tag title="${tagData.value}"
        //                         contenteditable='false'
        //                         spellcheck='false'
        //                         class='tagify__tag ${tagData.class ? tagData.class : ""}'
        //                         tabindex="0"
        //                         role="option"
        //                         aria-label="${tagData.value}"
        //                         aria-selected="false">
        //                         <x title='' class='tagify__tag__removeBtn' role='button' aria-label='remove tag'></x>
        //                         <div>
        //                             <div class='tagify__tag__avatar-wrap'>
        //                                 <img onerror="this.style.visibility='hidden'" src="">
        //                             </div>
        //                             <div class='tagify__tag__text'>
        //                                 <span>${tagData.text}</span>
        //                             </div>
        //                         </div>
        //                     </tag>
        //                 `
        //             },
        //             dropdownItem: function(tagData) {
        //                 return `
        //                     <div class='tagify__dropdown__item ${tagData.class ? tagData.class : ""}'
        //                          tabindex="0"
        //                          role="option"
        //                          aria-label="${tagData.value}">
        //                         <span>${tagData.text}</span>
        //                     </div>
        //                 `
        //             }
        //         }
        //     });
        // }

    },
    updated() {
        this.$nextTick(() => {
            this.initTooltips();
        });
    }
});

// Register Comment Component
vueApp.component('comment-component', window.CommentComponent);

// Mount the Vue app
vueApp.mount('#app'); 