const { createApp } = Vue;

const vueApp = createApp({
    data() {
        return {
            permission: {},
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
                { value: 'draft', label: '受付', color: 'secondary' },
                { value: 'open', label: '納期検討', color: 'info' },
                { value: 'confirming', label: '仮受', color: 'info' },
                { value: 'quotation', label: '見積', color: 'info' },
                { value: 'contract', label: '請負', color: 'info' },
                { value: 'in_progress', label: '進行中', color: 'primary' },
                { value: 'completed', label: '完了', color: 'success' },
                { value: 'paused', label: '一時停止', color: 'warning' },
                { value: 'cancelled', label: '中止', color: 'danger' }
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
                needs_confirmation: false,
                user_id: null
            },
            quillNoteInstance: null,
            quillNoteContent: '',
            // Project status update loading
            isUpdatingStatus: false,
            savingProject: false,
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
            validationErrors: {
                category_id: '',
                company_name: '',
                customer_id: '',
                project_number: '',
                name: ''
            },
            timeRemainingTimer: null,
            autoRefreshTimer: null,
            savingCustomFieldLabel: null,
        }
    },
    computed: {
        isManager() {
            if(USER_ROLE == `administrator`) return true;
            if (!this.managers) return false;
            return this.managers.some(m => String(m.user_id) === String(USER_AUTH_ID)) || this.permission.can_manage_project || (this.permission.rule && this.permission.rule.project_edit == 1);
        },
        filteredTeams() {
            if (!this.project || !this.project.department_id) return this.allTeams;
            return this.allTeams.filter(team => String(team.department_id) === String(this.project.department_id));
        },
        selectedCustomFieldSet() {
            // Always return null since we now use all sets from department
            return null;
        },
        allDepartmentCustomFieldSets() {
            // Return all custom field sets for the department
            if (!this.project || !this.project.department_id) return [];
            return this.departmentCustomFieldSets || [];
        },
        canViewProject() {
            // Administrator can always view
            if (typeof USER_ROLE !== 'undefined' && USER_ROLE === 'administrator') return true;
            
            // Check permission from API
            if (this.permission && this.permission.is_member) return true;
            
            // Check if user is creator of the project
            if (this.project && this.project.created_by && typeof USER_ID !== 'undefined') {
                if (String(this.project.created_by) === String(USER_ID)) return true;
            }
            
            // Check if user is manager or member
            if (this.managers && typeof USER_AUTH_ID !== 'undefined') {
                if (this.managers.some(m => String(m.user_id) === String(USER_AUTH_ID))) return true;
            }
            if (this.members && typeof USER_AUTH_ID !== 'undefined') {
                if (this.members.some(m => String(m.user_id) === String(USER_AUTH_ID))) return true;
            }
            
            // Check if user has project_manager or project_director permission
            if (this.permission && this.permission.rule) {
                if (this.permission.rule.project_manager == 1 || this.permission.rule.project_director == 1) return true;
            }
            
            // Check if user is in the same department (even if not a member)
            if (this.permission && this.permission.is_in_department) return true;
            
            return false;
        },
        canAddNote() {
            return this.permission.can_manage_project || this.permission.is_member;
        },
        isProjectMember() {
            // Check if current user is already a member or manager
            if (typeof USER_AUTH_ID === 'undefined') return false;
            
            if (this.managers && this.managers.some(m => String(m.user_id) === String(USER_AUTH_ID))) return true;
            if (this.members && this.members.some(m => String(m.user_id) === String(USER_AUTH_ID))) return true;
            
            return false;
        },
        canJoinProject() {
            // User can join if:
            // 1. They are not yet a member
            // 2. They are in the same department as the project
            if (this.isProjectMember) return false;

            // check if administrator
            if (typeof USER_ROLE !== 'undefined' && USER_ROLE === 'administrator') return true;
            
            // Check if user is in the same department as the project
            if (!this.project || !this.project.department_id) return false;
            
            // User must be in the same department
            return this.permission && this.permission.is_in_department == 1;
        },
        canEditProject() {
            return this.permission.can_manage_project || (this.permission.is_member && this.permission.rule && this.permission.rule.project_edit == 1);
        },
        canAddProject() {
            return this.permission.can_manage_project || (this.permission.rule && this.permission.rule.project_add == 1);
        },
        canDeleteProject() {
            return this.permission.can_manage_project || (this.permission.rule && this.permission.rule.project_delete == 1);
        },
        canCommentProject() {
            return this.permission.can_manage_project || (this.permission.rule && this.permission.rule.project_comment == 1);
        },
        canDocumentProject() {
            return this.permission.can_manage_project || (this.permission.rule && this.permission.rule.project_director == 1);
        },
        sortedLogs() {
            if (!this.logs) return [];
            // Sắp xếp giảm dần theo thời gian
            return [...this.logs].sort((a, b) => (b.time > a.time ? 1 : -1));
        },
    },
    methods: {
        async loadPermission() {
            try {
                const response = await axios.get('/api/index.php?model=task&method=getPermission&project_id=' + this.projectId);
                this.permission = response.data || [];
            } catch (error) {
                console.error('Error loading permission:', error);
            }
        },
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
                // Cho phép AI lấy dữ liệu dự án hiện tại đang xem
                if (typeof window !== 'undefined' && this.project) {
                    window.__chatPageContext = window.__chatPageContext || {};
                    window.__chatPageContext.project_id = this.projectId;
                    window.__chatPageContext.page = 'project_detail';
                    window.__chatPageContext.page_project = this.project;
                }
                // Load parent project information if this is a child project
                if (this.project.parent_project_id) {
                    await this.loadParentProjectInfo();
                }
                
                // Khởi tạo trạng thái CAILY納期状況 / GUIS納期状況 từ cột riêng trong DB
                this.project.caily_nouki_status = this.project.caily_nouki_status || '';
                this.project.guis_nouki_status = this.project.guis_nouki_status || '';
                
                this.calculateStats();
                
                if (this.project.teams) {
                    this.loadTeamListByIds(this.project.teams);
                } else {
                    this.project.team_list = [];
                }
               
                this.loadMembers();
                // Ensure Tagify is updated after loading project and team_list
                this.$nextTick(() => { 
                    //this.initTagify(); 
                    this.setConnectedUsers();
                    this.initVietnamTimeTooltips();
                });
                
            } catch (error) {
                console.error('Error loading project:', error);
                alert('プロジェクトの読み込みに失敗しました。');
            }
        },
        
        async toggleFavorite() {
            if (!this.project || !this.project.id) return;
            
            try {
                const formData = new FormData();
                formData.append('project_id', this.project.id);
                
                const response = await axios.post('/api/index.php?model=project&method=toggleFavorite', formData);
                
                if (response.data && response.data.status === 'success') {
                    // Update the project's favorite status (convert boolean to number for consistency)
                    this.project.is_favorite = response.data.is_favorite ? 1 : 0;
                } else {
                    if (typeof showMessage === 'function') {
                        showMessage(response.data?.message || '操作に失敗しました。', true);
                    } else {
                        alert(response.data?.message || '操作に失敗しました。');
                    }
                }
            } catch (error) {
                console.error('Error toggling favorite:', error);
                if (typeof showMessage === 'function') {
                    showMessage('操作に失敗しました。', true);
                } else {
                    alert('操作に失敗しました。');
                }
            }
        },
        
        async loadParentProjectInfo() {
            try {
                const response = await axios.get(`/api/index.php?model=parentproject&method=getById&id=${this.project.parent_project_id}`);
                const parentProject = response.data;
                
                // Copy parent project information to child project
                this.project.company_name = parentProject.company_name;
                this.project.branch_name = parentProject.branch_name;
                this.project.contact_name = parentProject.contact_name; // 担当様 from parent project
                this.project.building_name = parentProject.project_name; // お施主様名 from parent project
                this.project.building_number = parentProject.construction_number;
                this.project.building_size = parentProject.scale;
                this.project.building_type = parentProject.type1;
                this.project.building_branch = parentProject.construction_branch;
                this.project.type1 = parentProject.type1;
                this.project.type2 = parentProject.type2;
                
                // Additional fields from parent project
                this.project.guis_receiver = parentProject.guis_receiver; // GUIS　受付者
                this.project.structural_office = parentProject.structural_office; // 構造事務所
                this.project.materials = parentProject.materials; // 資料
                this.project.notes = parentProject.notes; // 備考
                
                // Load GUIS receiver display name if exists
                if (this.project.guis_receiver) {
                    await this.loadGuisReceiverDisplayName();
                }
                
            } catch (error) {
                console.error('Error loading parent project info:', error);
            }
        },
        
        async loadGuisReceiverDisplayName() {
            try {
                const response = await axios.get('/api/index.php?model=user&method=searchMembers');
                if (response.data && response.data.data) {
                    const user = response.data.data.find(u => u.userid === this.project.guis_receiver);
                    if (user) {
                        // Set display name for view mode
                        this.project.guis_receiver_display_name = user.realname;
                    }
                }
            } catch (error) {
                console.error('Error loading GUIS receiver display name:', error);
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
        calculateStats() {
            this.stats.totalTasks = this.project.task_count || 0;
            // this.stats.completedTasks = this.tasks.filter(t => t.status === 'completed').length;
            // this.stats.timeTracked = this.tasks.reduce((sum, task) => sum + parseFloat(task.actual_hours || 0), 0);
            if (this.project && this.project.start_date) {
                let start = new Date(this.project.start_date);
                let end = this.project.end_date ? new Date(this.project.end_date) : new Date();
                if(this.project.actual_end_date){
                    end = new Date(this.project.actual_end_date);
                }
                const diffTime = Math.abs(end - start);
                this.stats.totalDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            }
        },
        // Comment component event handlers
        onCommentAdded(event) {
            this.showNotification('コメントが追加されました', 'success');
        },
        
        onCommentError(event) {
            this.showNotification(event.message || 'エラーが発生しました', 'error');
        },

        onCommentMessage(event) {
            this.showNotification(event.message || 'エラーが発生しました', 'info');
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
                // company_name: this.project.company_name,
                // customer_id: this.project.customer_id,
                // project_number: this.project.project_number,
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
        getTimeRemaining() {
            if (!this.project || !this.project.end_date || this.project.status === 'completed' ||
                 this.project.status === 'deleted' ||
                 this.project.status === 'draft' || this.project.status === 'cancelled') {
                return null;
            }
            
            const now = moment.tz('Asia/Tokyo');
            const endDate = moment.tz(this.project.end_date, 'Asia/Tokyo');
            
            // Kiểm tra ngôn ngữ hiện tại
            const isVietnamese = typeof i18next !== 'undefined' && i18next.isInitialized && i18next.language === 'vi';
            
            // Lấy các nhãn đã dịch
            const dayLabel = this.translateLabel('日');
            const hourLabel = this.translateLabel('時間');
            const minuteLabel = this.translateLabel('分');
            const overdueLabel = this.translateLabel('超過');
            const remainingLabel = this.translateLabel('残り');
            
            // Hàm helper để format số và đơn vị với khoảng cách cho tiếng Việt
            const formatUnit = (value, label) => {
                if (isVietnamese) {
                    return `${value} ${label}`;
                } else {
                    return `${value}${label}`;
                }
            };
            
            // Hàm helper để format text với khoảng cách cho tiếng Việt
            const formatTimeText = (parts) => {
                if (isVietnamese) {
                    return parts.filter(p => p).join(' ');
                } else {
                    return parts.filter(p => p).join('');
                }
            };
            
            if (endDate.isBefore(now)) {
                // Đã quá hạn
                const diff = now.diff(endDate);
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                
                if (days > 0) {
                    return {
                        text: formatTimeText([formatUnit(days, dayLabel), formatUnit(hours, hourLabel), formatUnit(minutes, minuteLabel), overdueLabel]),
                        class: 'bg-danger',
                        isOverdue: true
                    };
                } else if (hours > 0) {
                    return {
                        text: formatTimeText([formatUnit(hours, hourLabel), formatUnit(minutes, minuteLabel), overdueLabel]),
                        class: 'bg-danger',
                        isOverdue: true
                    };
                } else {
                    return {
                        text: formatTimeText([formatUnit(minutes, minuteLabel), overdueLabel]),
                        class: 'bg-danger',
                        isOverdue: true
                    };
                }
            } else {
                // Còn thời gian
                const diff = endDate.diff(now);
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                
                if (days > 0) {
                    return {
                        text: formatTimeText([remainingLabel, formatUnit(days, dayLabel), formatUnit(hours, hourLabel), formatUnit(minutes, minuteLabel)]),
                        class: 'bg-label-info',
                        isOverdue: false
                    };
                } else if (hours > 0) {
                    return {
                        text: formatTimeText([remainingLabel, formatUnit(hours, hourLabel), formatUnit(minutes, minuteLabel)]),
                        class: hours <= 24 ? 'bg-label-warning' : 'bg-label-info',
                        isOverdue: false
                    };
                } else {
                    return {
                        text: formatTimeText([remainingLabel, formatUnit(minutes, minuteLabel)]),
                        class: 'bg-label-warning',
                        isOverdue: false
                    };
                }
            }
        },
        /** Remaining time for a given date (e.g. caily_nouki, guis_nouki). Returns null if status is draft/paused/cancelled. */
        getTimeRemainingForDate(dateStr) {
            if (!this.project || !dateStr) return null;
            if (['draft', 'paused', 'cancelled', 'completed', 'deleted'].includes(String(this.project.status || '').toLowerCase())) return null;
            const now = moment.tz('Asia/Tokyo');
            const endDate = moment.tz(dateStr, 'Asia/Tokyo');
            if (!endDate.isValid()) return null;
            const isVietnamese = typeof i18next !== 'undefined' && i18next.isInitialized && i18next.language === 'vi';
            const dayLabel = this.translateLabel('日');
            const hourLabel = this.translateLabel('時間');
            const minuteLabel = this.translateLabel('分');
            const overdueLabel = this.translateLabel('超過');
            const remainingLabel = this.translateLabel('残り');
            const formatUnit = (value, label) => isVietnamese ? `${value} ${label}` : `${value}${label}`;
            const formatTimeText = (parts) => isVietnamese ? parts.filter(p => p).join(' ') : parts.filter(p => p).join('');
            if (endDate.isBefore(now)) {
                const diff = now.diff(endDate);
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                if (days > 0) {
                    return { text: formatTimeText([formatUnit(days, dayLabel), formatUnit(hours, hourLabel), formatUnit(minutes, minuteLabel), overdueLabel]), class: 'bg-danger', isOverdue: true };
                } else if (hours > 0) {
                    return { text: formatTimeText([formatUnit(hours, hourLabel), formatUnit(minutes, minuteLabel), overdueLabel]), class: 'bg-danger', isOverdue: true };
                } else {
                    return { text: formatTimeText([formatUnit(minutes, minuteLabel), overdueLabel]), class: 'bg-danger', isOverdue: true };
                }
            } else {
                const diff = endDate.diff(now);
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                if (days > 0) {
                    return { text: formatTimeText([remainingLabel, formatUnit(days, dayLabel), formatUnit(hours, hourLabel), formatUnit(minutes, minuteLabel)]), class: 'bg-label-info', isOverdue: false };
                } else if (hours > 0) {
                    return { text: formatTimeText([remainingLabel, formatUnit(hours, hourLabel), formatUnit(minutes, minuteLabel)]), class: hours <= 24 ? 'bg-label-warning' : 'bg-label-info', isOverdue: false };
                } else {
                    return { text: formatTimeText([remainingLabel, formatUnit(minutes, minuteLabel)]), class: 'bg-label-warning', isOverdue: false };
                }
            }
        },
        formatDateForInput(date) {
            if (!date) return '';
            return moment(date).format('YYYY-MM-DD');
        },
        formatDateTime(datetime) {
            if (!datetime) return '-';
            return moment(datetime).format('YYYY/MM/DD HH:mm');
        },
        /** Tooltip giờ VN khi hover lên giờ Nhật: "VN hh:ii" (dùng chung với main.js) */
        getVietnamTimeTooltip(jpDateTimeStr) {
            return typeof window.formatVietnamTimeTooltip === 'function' ? window.formatVietnamTimeTooltip(jpDateTimeStr) : '';
        },
        /** Khởi tạo Bootstrap tooltip cho ô có data-time (giờ JST → tooltip VN) */
        initVietnamTimeTooltips() {
            this.$nextTick(() => {
                const app = document.getElementById('app');
                if (!app || typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
                app.querySelectorAll('[data-bs-toggle="tooltip"][data-time]').forEach(el => {
                    if (!document.contains(el)) return;
                    try {
                        const t = bootstrap.Tooltip.getInstance(el);
                        if (t) t.dispose();
                    } catch (e) { /* element may be detached */ }
                    if (el.getAttribute('data-bs-title')) {
                        try { new bootstrap.Tooltip(el); } catch (e) { /* skip */ }
                    }
                });
            });
        },
        formatShortDateTime(datetime) {
            if (!datetime) return '-';
            return moment(datetime).format('M月D日 HH:mm');
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
            if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(el => {
                if (!document.contains(el)) return;
                try {
                    const t = bootstrap.Tooltip.getInstance(el);
                    if (t) t.dispose();
                } catch (e) { /* element may be detached */ }
                try {
                    new bootstrap.Tooltip(el);
                } catch (e) { /* skip invalid elements */ }
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
            if (!this.canDocumentProject) return; // Only managers can update status
            
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
                setTimeout(async () => {
                    // Kiểm tra Tagify library đã được load chưa
                    if (!window.Tagify) {
                        console.log('Tagify library not loaded yet, retrying...');
                        setTimeout(() => {
                            this.initTagify();
                        }, 100);
                        return;
                    }
                    
                    // --- Tagify for team selection ---
                    const teamInput = document.getElementById('team_tags');
                    if (teamInput && window.Tagify && !teamInput._tagify) {
                        // allTeams đã được load sẵn trong toggleEditMode
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
                                maxItems: 1000,
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
                        // Xử lý khi thêm team thì thêm member của team đó và team leader vào manager
                        this.tagify.on('add', async (e) => {
                            const addedTeamId = e.detail.data.id;
                            if (!addedTeamId) return;
                            try {
                                const res = await axios.get(`/api/index.php?model=team&method=get&id=${addedTeamId}`);
                                if (res.data && Array.isArray(res.data.members)) {
                                    if (this.membersTagify) {
                                        const teamMembers = res.data.members.map(m => ({ id: m.user_id, value: m.user_name }));
                                        const currentIds = this.membersTagify.value.map(tag => String(tag.id));
                                        const toAdd = teamMembers.filter(m => !currentIds.includes(String(m.id)));
                                        this.membersTagify.addTags(toAdd);
                                    }
                                    const leaders = res.data.members.filter(m => m.leader == 1 || m.leader === '1');
                                    if (leaders.length && this.managerTagify) {
                                        const leaderTags = leaders.map(m => ({ id: m.user_id, value: m.user_name || '' }));
                                        const managerCurrentIds = this.managerTagify.value.map(tag => String(tag.id));
                                        const leadersToAdd = leaderTags.filter(m => !managerCurrentIds.includes(String(m.id)));
                                        this.managerTagify.addTags(leadersToAdd);
                                    }
                                }
                            } catch (err) {}
                        });
                        this.tagify.on('change', this.onTeamTagsChange.bind(this));
                    } else if (!teamInput) {
                        // Nếu element chưa tồn tại, thử lại sau 100ms
                        setTimeout(() => {
                            this.initTagify();
                        }, 100);
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
                            whitelist: ['新規', '修正', '免震', '耐震', '計画変更', '契約図', '実施図'],
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
                    } else if (!orderTypeInput) {
                        // Nếu element chưa tồn tại, thử lại sau 100ms
                        setTimeout(() => {
                            this.initTagify();
                        }, 100);
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
            const optionsStart = {
                enableTime: true,
                dateFormat: "Y/m/d H:i",
                time_24hr: true,
                allowInput: true,
                locale: "ja",
                defaultHour: 9,
                defaultMinute: 0,
                onChange: (selectedDates, dateStr, instance) => {
                    if (instance.input.id === 'start_date_picker') this.project.start_date = dateStr;
                }
            };
            const optionsEnd = {
                enableTime: true,
                dateFormat: "Y/m/d H:i",
                time_24hr: true,
                allowInput: true,
                locale: "ja",
                defaultHour: 18,
                defaultMinute: 0,
                onChange: (selectedDates, dateStr, instance) => {
                    if (instance.input.id === 'end_date_picker') this.project.end_date = dateStr;
                }
            };

            const elStart = document.getElementById('start_date_picker');
            if (elStart) {
                if (elStart._flatpickr) elStart._flatpickr.destroy();
                flatpickr(elStart, optionsStart);
            }
            const elEnd = document.getElementById('end_date_picker');
            if (elEnd) {
                if (elEnd._flatpickr) elEnd._flatpickr.destroy();
                flatpickr(elEnd, optionsEnd);
            }
            const optionsDatetime = {
                enableTime: true,
                dateFormat: "Y/m/d H:i",
                time_24hr: true,
                allowInput: true,
                locale: "ja",
                defaultHour: 18,
                defaultMinute: 0
            };
            ['caily_nouki_picker', 'guis_nouki_picker'].forEach((id, i) => {
                const key = id.replace('_picker', '');
                const el = document.getElementById(id);
                if (el) {
                    if (el._flatpickr) el._flatpickr.destroy();
                    flatpickr(el, {
                        ...optionsDatetime,
                        onChange: (selectedDates, dateStr) => { this.project[key] = dateStr; }
                    });
                }
            });
            // Initialize actual_end_date picker
            const elActualEnd = document.getElementById('actual_end_date_picker');
            if (elActualEnd) {
                if (elActualEnd._flatpickr) elActualEnd._flatpickr.destroy();
                flatpickr(elActualEnd, {
                    enableTime: true,
                    dateFormat: "Y/m/d H:i",
                    time_24hr: true,
                    allowInput: true,
                    locale: "ja",
                    defaultHour: 18,
                    defaultMinute: 0,
                    onChange: (selectedDates, dateStr) => { this.project.actual_end_date = dateStr; }
                });
            }
            // Initialize custom field datetime pickers
            this.initCustomFieldDatePickers();
        },
        initCustomFieldDatePickers() {
            if (!this.isEditMode || !this.customFields) {
                console.log('initCustomFieldDatePickers: early return', { isEditMode: this.isEditMode, customFields: this.customFields });
                return;
            }
            this.$nextTick(() => {
                // Similar to project-list.js: flatpickr will automatically parse the value from input
                // Vue's v-model already sets the value to the input element
                this.customFields.forEach((field, idx) => {
                    if (field.type === 'datetime') {
                        const el = document.getElementById('custom_datetime_' + idx);
                        if (!el) {
                            return;
                        }
                        
                        if (el._flatpickr) el._flatpickr.destroy();

                        
                        // Ensure the input has the value from field.value (v-model should have set it, but double-check)
                        const fieldValue = field.value || '';
                        if (fieldValue && el.value !== fieldValue) {
                            el.value = fieldValue;
                        }
                        
                        // Initialize flatpickr - it will automatically parse the value from the input
                        const fp = flatpickr(el, {
                            enableTime: true,
                            dateFormat: "Y/m/d H:i",
                            time_24hr: true,
                            allowInput: true,
                            locale: "ja",
                            defaultHour: 18,
                            defaultMinute: 0,
                            onChange: (selectedDates, dateStr) => {
                                this.customFields[idx].value = dateStr;
                            }
                        });
                        
                    }
                });
            });
        },
        async initManagerMembersTagify() {
            // departmentUsers đã được load sẵn trong toggleEditMode; chỉ gọi khi chưa có
            if (!this.departmentUsers || this.departmentUsers.length === 0) {
                await this.loadDepartmentUsers();
            }
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
                    enforceWhitelist: false,
                    dropdown: { maxItems: 1000, enabled: 0, closeOnSelect: true }
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
                    enforceWhitelist: false,
                    dropdown: { maxItems: 1000, enabled: 0, closeOnSelect: true }
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
            if (!this.canEditProject) {
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
            ['caily_nouki', 'guis_nouki'].forEach(k => {
                this.project[k] = this.project[k] ? this.formatDateTime(this.project[k]) : '';
            });
            if (this.project.actual_end_date) {
                this.project.actual_end_date = this.formatDateTime(this.project.actual_end_date);
            } else {
                this.project.actual_end_date = '';
            }
            // Lưu lại prevTeamIds khi vào edit mode
            this.prevTeamIds = (this.project.team_list || []).map(t => String(t.id)).sort();
            // Preload teams + department users song song để Tagify không phải chờ API khi init
            Promise.all([
                this.loadAllTeams(),
                this.project.department_id ? this.loadDepartmentUsers() : Promise.resolve([])
            ]).then(() => {
                this.$nextTick(() => {
                    this.initDatePickers();
                    setTimeout(() => {
                        this.initTagify();
                        this.initManagerMembersTagify();
                        this.initCustomFieldDatePickers();
                    }, 200);
                });
            });
        },
        toAPIDate(str) {
            if (str == null || str === '') return '';
            if (typeof str !== 'string') str = String(str);
            return str.replace(/\//g, '-');
        },
        prepareCustomFieldsForSave() {
            // Merge all fields from all department custom field sets
            if (!this.allDepartmentCustomFieldSets || this.allDepartmentCustomFieldSets.length === 0) return [];
            if (!this.customFields || this.customFields.length === 0) return [];
            
            // Create a map of label -> value for quick lookup
            const valueMap = {};
            this.customFields.forEach(field => {
                if (field.label) {
                    if (field.type === 'checkbox') {
                        valueMap[field.label.trim()] = Array.isArray(field.valueArr) ? field.valueArr.join(',') : '';
                    } else {
                        valueMap[field.label.trim()] = field.value || '';
                    }
                }
            });
            
            // Collect all fields from all sets
            const allFields = [];
            this.allDepartmentCustomFieldSets.forEach(set => {
                if (set.fields && Array.isArray(set.fields)) {
                    set.fields.forEach(f => {
                        // Avoid duplicates by label
                        if (!allFields.find(existing => existing.label && existing.label.trim() === f.label.trim())) {
                            allFields.push({
                                label: f.label,
                                type: f.type,
                                options: f.options,
                                value: valueMap[f.label.trim()] || ''
                            });
                        }
                    });
                }
            });
            
            return allFields;
        },
        async quickUpdateNoukiStatus(kind) {
            if (!this.project || !this.project.id) return;
            const prevCaily = this.project.caily_nouki_status;
            const prevGuis = this.project.guis_nouki_status;
            try {
                const formData = new FormData();
                formData.append('id', this.project.id);
                if (kind === 'caily') {
                    formData.append('caily_nouki_status', this.project.caily_nouki_status || '');
                } else if (kind === 'guis') {
                    formData.append('guis_nouki_status', this.project.guis_nouki_status || '');
                }
                const response = await axios.post('/api/index.php?model=project&method=update', formData);
                if (!response.data || response.data.status !== 'success') {
                    this.project.caily_nouki_status = prevCaily;
                    this.project.guis_nouki_status = prevGuis;
                    if (typeof showMessage === 'function') {
                        showMessage('納期状況の更新に失敗しました。', true);
                    }
                }
            } catch (e) {
                this.project.caily_nouki_status = prevCaily;
                this.project.guis_nouki_status = prevGuis;
                console.error('Error updating nouki status quickly:', e);
                if (typeof showMessage === 'function') {
                    showMessage('納期状況の更新に失敗しました。', true);
                }
            }
        },
        async saveProject() {
            if (!this.project) {
                if (typeof showMessage === 'function') showMessage('プロジェクトデータが読み込まれていません。', true);
                return;
            }
            if (!this.validateProjectForm()) {
                const msg = this.validationErrors.name || this.validationErrors.project_number || '入力内容を確認してください。';
                if (typeof showMessage === 'function') showMessage(msg, true);
                else if (typeof this.showNotification === 'function') this.showNotification(msg, 'error');
                return;
            }
            // Use the stored quill content instead of syncing from editor
            if (this.quillContent !== undefined) {
                this.project.description = this.quillContent;
            }
            
            // Save custom field values (no need to save set_id since we use all sets)
            this.project.custom_fields = JSON.stringify(this.prepareCustomFieldsForSave());
            this.savingProject = true;
            try {
                const formData = new FormData();
                formData.append('id', this.project.id);
                formData.append('name', this.project.name);
                // formData.append('building_branch', this.project.building_branch);
                // formData.append('building_size', this.project.building_size);
                // formData.append('building_type', this.project.building_type);
                // formData.append('building_number', this.project.building_number);
                formData.append('project_number', this.project.project_number);
                formData.append('progress', this.project.progress);
                formData.append('priority', this.project.priority || '');
                formData.append('status', this.project.status);
                // Use Tagify current value (kể cả khi rỗng) hoặc project khi chưa có tagify
                let teamsVal = '';
                if (this.tagify) {
                    teamsVal = (this.tagify.value || []).map(t => String(t.id)).join(',');
                } else if (this.newProject.teams !== undefined && this.newProject.teams !== null) {
                    teamsVal = String(this.newProject.teams);
                } else if (this.project.teams) {
                    teamsVal = typeof this.project.teams === 'string' ? this.project.teams : String(this.project.teams || '');
                }
                let managersVal = this.newProject.managers || '';
                if (this.managerTagify && this.managerTagify.value && this.managerTagify.value.length) {
                    managersVal = this.managerTagify.value.map(t => String(t.id)).join(',');
                } else if (!managersVal && this.managers && this.managers.length) {
                    managersVal = this.managers.map(m => String(m.user_id)).join(',');
                }
                let membersVal = this.newProject.members || '';
                if (this.membersTagify && this.membersTagify.value && this.membersTagify.value.length) {
                    membersVal = this.membersTagify.value.map(t => String(t.id)).join(',');
                } else if (!membersVal && this.members && this.members.length) {
                    membersVal = this.members.map(m => String(m.user_id)).join(',');
                }
                formData.append('teams', teamsVal);
                formData.append('members', membersVal);
                formData.append('managers', managersVal);
                formData.append('start_date', this.toAPIDate(this.project.start_date));
                formData.append('end_date', this.toAPIDate(this.project.end_date));
                formData.append('actual_end_date', this.toAPIDate(this.project.actual_end_date) || '');
                formData.append('tantou', this.project.tantou || '');
                formData.append('caily_nouki', this.toAPIDate(this.project.caily_nouki) || '');
                formData.append('guis_nouki', this.toAPIDate(this.project.guis_nouki) || '');
                formData.append('caily_nouki_status', this.project.caily_nouki_status || '');
                formData.append('guis_nouki_status', this.project.guis_nouki_status || '');
                formData.append('project_order_type', this.project.project_order_type);
                formData.append('customer_id', this.project.customer_id);
                // formData.append('amount', this.project.amount);
                // formData.append('estimate_status', this.project.estimate_status);
                // formData.append('invoice_status', this.project.invoice_status);
                formData.append('tags', this.project.tags);
                // No need to send department_custom_fields_set_id since we use all sets from department
                formData.append('custom_fields', this.project.custom_fields);
                formData.append('description', this.project.description || '');
                const response = await axios.post('/api/index.php?model=project&method=update', formData);
                if (response.data && response.data.status == 'success') {
                    this.isEditMode = false;
                    this.originalProject = null;
                    showMessage('プロジェクトを更新しました。');
                    // Hoãn loadProject để trình duyệt kịp vẽ thông báo trước khi xử lý nặng
                    setTimeout(() => { this.loadProject(); }, 0);
                } else {
                    showMessage('プロジェクトの更新に失敗しました。', true);
                }
            } catch (error) {
                console.error('Error saving project:', error);
                if (typeof showMessage === 'function') {
                    showMessage('プロジェクトの更新に失敗しました。', true);
                } else {
                    alert('プロジェクトの更新に失敗しました。');
                }
            } finally {
                this.savingProject = false;
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
            this.initVietnamTimeTooltips();
        },
        async confirmKadaiProject() {
            try {
                const swal = await Swal.fire({
                    title: 'プロジェクトを承認しますか？',
                    text: 'このプロジェクトを正式に受け入れて、通常のプロジェクトとして開始します。',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '承認',
                    cancelButtonText: 'キャンセル',
                    confirmButtonColor: '#28a745'
                });

                if (swal.isConfirmed) {
                    const formData = new FormData();
                    formData.append('id', this.projectId);
                    const response = await axios.post('/api/index.php?model=project&method=confirm', formData);
                    if (response.data && response.data.status === 'success') {
                        showMessage('プロジェクトを承認しました。');
                        setTimeout(() => { this.loadProject(); }, 0);
                    } else {
                        showMessage(response.data?.message || 'プロジェクトの承認に失敗しました。', true);
                    }
                }
            } catch (error) {
                console.error('Error confirming kadai project:', error);
                showMessage('プロジェクトの承認中にエラーが発生しました。', true);
            }
        },
        async joinProject() {
            if (!this.canJoinProject) return;
            
            try {
                const swal = await Swal.fire({
                    title: 'プロジェクトに参加しますか？',
                    text: 'このプロジェクトのメンバーとして参加します。',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '参加',
                    cancelButtonText: 'キャンセル',
                    confirmButtonColor: '#0d6efd'
                });

                if (swal.isConfirmed) {
                    if (typeof USER_AUTH_ID === 'undefined') {
                        showMessage('ユーザー情報が取得できませんでした。', true);
                        return;
                    }
                    
                    const formData = new FormData();
                    formData.append('project_id', this.projectId);
                    formData.append('user_id', USER_AUTH_ID);
                    formData.append('role', 'member');
                    
                    const response = await axios.post('/api/index.php?model=project&method=addMemberApi', formData);
                    
                    if (response.data && response.data.status === 'success') {
                        showMessage('プロジェクトに参加しました。');
                        const self = this;
                        setTimeout(async () => {
                            await self.loadProject();
                            await self.loadPermission();
                        }, 0);
                    } else {
                        showMessage(response.data?.message || 'プロジェクトへの参加に失敗しました。', true);
                    }
                }
            } catch (error) {
                console.error('Error joining project:', error);
                showMessage('プロジェクトへの参加中にエラーが発生しました。', true);
            }
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
                    // Notes content từ server có thể đã là HTML thuần (từ Quill), không cần decode
                    // Chỉ decode nếu có HTML entities bị escape
                    this.notes = (response.data.data || []).map(note => {
                        if (note.content && (note.content.indexOf('&lt;') !== -1 || note.content.indexOf('&gt;') !== -1)) {
                            // Nếu có HTML entities thì decode
                            note.content = this.decodeHtmlEntities(note.content);
                        }
                        return note;
                    });
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
                // Edit existing note (decode HTML entities so &lt; shows as < in textarea)
                this.editingNote = {
                    id: note.id,
                    title: note.title,
                    content: note.content ? this.decodeHtmlEntities(note.content) : '',
                    is_important: note.is_important == 1,
                    needs_confirmation: note.needs_confirmation == 1,
                    user_id: note.user_id
                };
            } else {
                // Create new note
                this.editingNote = {
                    id: null,
                    title: '',
                    content: '',
                    is_important: false,
                    needs_confirmation: false,
                    user_id: null
                };
            }
            // Init Quill editor nếu ở edit mode
            if (!note) {
                this.isNoteEditMode = true;
                this.$nextTick(() => {
                    this.initQuillNoteEditor();
                });
            }
        },
        closeNoteModal() {
            this.showNoteModal = false;
            this.isNoteEditMode = false;
            this.destroyQuillNoteEditor();
            this.editingNote = {
                id: null,
                title: '',
                content: '',
                is_important: false,
                needs_confirmation: false,
                user_id: null
            };
            this.quillNoteContent = '';
        },
        async saveNote() {
            // Lấy nội dung từ Quill editor nếu có, nếu không dùng editingNote.content
            const rawContent = (this.quillNoteContent && this.quillNoteContent.trim()) || (this.editingNote.content || '').trim();
            if (!rawContent) {
                this.showNotification('内容を入力してください', 'error');
                return;
            }
            // Auto-generate title from content (first line, max 50 chars, strip HTML tags)
            let title = (this.editingNote.title || '').trim();
            if (!title) {
                const textContent = rawContent.replace(/<[^>]*>/g, '').trim();
                title = textContent.split(/\r?\n/)[0].slice(0, 50) || 'メモ';
            }
            
            try {
                const formData = new FormData();
                formData.append('project_id', this.projectId);
                formData.append('title', title);
                formData.append('content', rawContent);
                formData.append('is_important', this.editingNote.is_important ? 1 : 0);
                formData.append('needs_confirmation', this.editingNote.needs_confirmation ? 1 : 0);
                
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
            return this.isManager || (note.user_id && String(note.user_id) === String(USER_ID));
        },
        canEditNote(note) {
            // Only note creator or managers can edit notes
            return this.isManager || (note.user_id && String(note.user_id) === String(USER_ID));
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
        
        // Quotation status methods
        getQuotationStatusLabel(status) {
            const statusLabels = {
                '下書き': '下書き',
                '発行済み': '発行済み',
                '承認済み': '承認済み',
                '却下': '却下',
                '調整': '調整',
                'キャンセル': 'キャンセル',
                '未発行': '未発行'
            };
            return statusLabels[status] || '未発行';
        },
        getQuotationStatusBadgeClass(status) {
            const statusClasses = {
                '下書き': 'bg-secondary',
                '発行済み': 'bg-primary',
                '承認済み': 'bg-success',
                '却下': 'bg-danger',
                '調整': 'bg-warning',
                'キャンセル': 'bg-dark',
                '未発行': 'bg-light text-dark'
            };
            return statusClasses[status] || 'bg-light text-dark';
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
            showMessage(message, type === 'error');
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
                        // syntax: true,
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
                    this.addZoomToDescriptionImages();
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
            this.addZoomToDescriptionImages();
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
        initQuillNoteEditor() {
            if (this.quillNoteInstance || !this.isNoteEditMode || !this.showNoteModal) return;
            setTimeout(() => {
                const toolbarOptions = [
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ color: [] }, { background: [] }],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ header: '1' }, { header: '2' }, 'blockquote'],
                    ['link', 'clean']
                ];
                const el = document.getElementById('quill_note_content_detail');
                if (!el) return;
                if (this.quillNoteInstance) {
                    try {
                        this.quillNoteInstance = null;
                    } catch (e) {}
                }
                this.quillNoteInstance = new Quill(el, {
                    bounds: el,
                    placeholder: 'メモの詳細を入力してください...',
                    modules: {
                        toolbar: {
                            container: toolbarOptions
                        }
                    },
                    theme: 'snow'
                });
                if (this.editingNote.content) {
                    const html = this.decodeHtmlEntities ? this.decodeHtmlEntities(this.editingNote.content) : this.editingNote.content;
                    this.quillNoteInstance.root.innerHTML = html;
                }
                this.quillNoteContent = this.quillNoteInstance.getSemanticHTML();
                this.quillNoteInstance.on('text-change', () => {
                    this.quillNoteContent = this.quillNoteInstance.getSemanticHTML();
                });
            }, 200);
        },
        destroyQuillNoteEditor() {
            if (this.quillNoteInstance) {
                try {
                    this.quillNoteInstance = null;
                } catch (e) {}
            }
            this.quillNoteContent = '';
        },
        decodeNoteHtml(str) {
            if (!str) return '';
            // Nếu đã là HTML thuần thì không cần decode
            // Chỉ decode nếu có HTML entities
            if (str.indexOf('&lt;') !== -1 || str.indexOf('&gt;') !== -1 || str.indexOf('&amp;') !== -1) {
                const txt = document.createElement('textarea');
                txt.innerHTML = str;
                return txt.value;
            }
            return str;
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
            // Merge all fields from all department custom field sets with saved values
            const allFieldsFromSets = [];
            if (this.allDepartmentCustomFieldSets && this.allDepartmentCustomFieldSets.length > 0) {
                this.allDepartmentCustomFieldSets.forEach(set => {
                    if (set.fields && Array.isArray(set.fields)) {
                        set.fields.forEach(f => {
                            // Avoid duplicates by label
                            if (!allFieldsFromSets.find(existing => existing.label && existing.label.trim() === f.label.trim())) {
                                allFieldsFromSets.push({
                                    label: f.label,
                                    type: f.type,
                                    // Chuẩn hóa options thành string để template có thể gọi .split(',')
                                    options: Array.isArray(f.options) ? f.options.join(',') : (f.options != null ? String(f.options) : '')
                                });
                            }
                        });
                    }
                });
            }
            
            // Parse saved values
            let saved = [];
            let raw = this.project?.custom_fields;
            if (typeof raw === 'string' && raw.includes('&quot;')) {
                raw = raw.replace(/&quot;/g, '"');
            }
            if (typeof raw === 'string') {
                try { saved = JSON.parse(raw); } catch (e) { saved = []; }
            } else if (Array.isArray(raw)) {
                saved = raw;
            }
            
            // Create value map from saved data
            const savedValueMap = {};
            saved.forEach(f => {
                if (f.label) {
                    savedValueMap[f.label.trim()] = f;
                }
            });
            
            // Merge: use fields from sets, fill values from saved data
            return allFieldsFromSets.map(f => {
                const savedField = savedValueMap[f.label.trim()];
                return {
                    label: f.label,
                    type: f.type,
                    options: f.options,
                    value: savedField ? savedField.value : ''
                };
            });
        },
        async updateCustomFieldValue(label, value) {
            if (!this.project || !label) return;
            const newValue = (value != null) ? String(value).trim() : '';
            let saved = [];
            let raw = this.project.custom_fields;
            if (typeof raw === 'string' && raw.includes('&quot;')) raw = raw.replace(/&quot;/g, '"');
            try {
                saved = typeof raw === 'string' ? JSON.parse(raw || '[]') : (Array.isArray(raw) ? raw : []);
            } catch (e) { saved = []; }
            const updated = saved.filter(f => f && f.label && String(f.label).trim() !== String(label).trim());
            updated.push({ label: label, value: newValue });
            if (this.savingCustomFieldLabel === label) return;
            this.savingCustomFieldLabel = label;
            try {
                const formData = new FormData();
                formData.append('id', this.project.id);
                formData.append('custom_fields', JSON.stringify(updated));
                const res = await axios.post('/api/index.php?model=project&method=update', formData);
                if (res.data && res.data.status === 'success') {
                    this.project.custom_fields = JSON.stringify(updated);
                    if (typeof showMessage === 'function') showMessage('保存しました');
                } else {
                    showMessage(res.data?.message || res.data?.error || '更新に失敗しました。', true);
                }
            } catch (err) {
                console.error('Custom field save:', err);
                showMessage(err.response?.data?.message || '更新に失敗しました。', true);
            } finally {
                this.savingCustomFieldLabel = null;
            }
        },
        isCustomFieldCheckboxChecked(fieldLabel, opt) {
            const val = this.getCustomFieldValue(fieldLabel);
            return (val || '').split(',').map(s => s.trim()).includes(opt);
        },
        onCustomFieldCheckboxChange(field, opt, checked) {
            const current = (this.getCustomFieldValue(field.label) || '').split(',').map(s => s.trim()).filter(Boolean);
            if (checked) {
                if (!current.includes(opt)) current.push(opt);
            } else {
                const i = current.indexOf(opt);
                if (i !== -1) current.splice(i, 1);
            }
            this.updateCustomFieldValue(field.label, current.join(','));
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
        addZoomToDescriptionImages() {
            this.$nextTick(() => {
                // View mode
                const descEls = document.querySelectorAll('.project-description, #project-description, .ql-editor');
                descEls.forEach(el => {
                    el.querySelectorAll('img:not([data-zoom])').forEach(img => {
                        img.setAttribute('data-zoom', '');
                    });
                });
            });
        },
        validateProjectForm() {
            this.validationErrors = {
                // category_id: '',
                // company_name: '',
                // customer_id: '',
                project_number: '',
                name: ''
            };
            let valid = true;
            // if (!this.project.category_id) {
            //     this.validationErrors.category_id = '顧客カテゴリーは必須です';
            //     valid = false;
            // }
            // if (!this.project.company_name) {
            //     this.validationErrors.company_name = '会社名は必須です';
            //     valid = false;
            // }
            // if (!this.project.customer_id) {
            //     this.validationErrors.customer_id = '担当者名は必須です';
            //     valid = false;
            // }
            // project_number は必須チェックを削除（フィールド削除に合わせて任意とする）
            if (!this.project.name) {
                this.validationErrors.name = 'プロジェクト名は必須です';
                valid = false;
            }
            // Validate start_date > end_date
            if (this.project.start_date && this.project.end_date && new Date(this.project.start_date) > new Date(this.project.end_date)) {
                this.showNotification('開始日は終了日より前にしてください', 'error');
                valid = false;
            }
            return valid;
        },
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
                    // Merge all fields from all department custom field sets
                    const allFieldsFromSets = [];
                    if (this.allDepartmentCustomFieldSets && this.allDepartmentCustomFieldSets.length > 0) {
                        this.allDepartmentCustomFieldSets.forEach(set => {
                            if (set.fields && Array.isArray(set.fields)) {
                                set.fields.forEach(f => {
                                    // Avoid duplicates by label
                                    if (!allFieldsFromSets.find(existing => existing.label && existing.label.trim() === f.label.trim())) {
                                        allFieldsFromSets.push({
                                            label: f.label,
                                            type: f.type,
                                            options: Array.isArray(f.options) ? f.options.join(',') : (f.options != null ? String(f.options) : '')
                                        });
                                    }
                                });
                            }
                        });
                    }
                    // Nếu có dữ liệu custom_fields với cấu trúc đầy đủ (có type), merge với saved values
                    if (saved.length && saved[0]) {
                        // Create value map from saved data
                        const savedValueMap = {};
                        saved.forEach(f => {
                            if (f.label) {
                                savedValueMap[f.label.trim()] = f;
                            }
                        });
                        
                        // Merge: use fields from sets, fill values from saved data
                        this.customFields = allFieldsFromSets.map(f => {
                            const savedField = savedValueMap[f.label.trim()];
                            if (f.type === 'checkbox') {
                                let arr = [];
                                if (savedField && savedField.value) {
                                    arr = savedField.value.split(',').map(s => s.trim()).filter(Boolean);
                                }
                                return { label: f.label, type: f.type, options: f.options, value: arr.join(','), valueArr: arr };
                            } else if (f.type === 'datetime') {
                                // Get value directly from saved field, no parsing needed
                                // Flatpickr will handle parsing when initialized (similar to project-list.js)
                                const value = savedField && savedField.value ? String(savedField.value).trim() : '';
                                return { 
                                    label: f.label, 
                                    type: f.type, 
                                    options: f.options, 
                                    value: value 
                                };
                            } else {
                                return { 
                                    label: f.label, 
                                    type: f.type, 
                                    options: f.options, 
                                    value: savedField ? savedField.value : '' 
                                };
                            }
                        });
                        
                        // Initialize datetime pickers after customFields is set
                        this.$nextTick(() => {
                            setTimeout(() => {
                                this.initCustomFieldDatePickers();
                            }, 100);
                        });
                    } else if (allFieldsFromSets.length > 0) {
                        // No saved data, just use fields from all sets
                        this.customFields = allFieldsFromSets.map(f => {
                            if (f.type === 'checkbox') {
                                return { label: f.label, type: f.type, options: f.options, value: '', valueArr: [] };
                            } else {
                                return { label: f.label, type: f.type, options: f.options, value: '' };
                            }
                        });
                        
                        // Initialize datetime pickers after customFields is set
                        this.$nextTick(() => {
                            setTimeout(() => {
                                this.initCustomFieldDatePickers();
                            }, 100);
                        });
                    } else {
                        this.customFields = [];
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
                    }).on('select2:clear', () => {
                        this.project.category_id = '';
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
                        }).on('select2:clear', () => {
                            this.project.company_name = '';
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
                        }).on('select2:clear', () => {
                            this.project.customer_id = '';
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
                                whitelist: ['新規', '修正', '免震', '耐震', '計画変更', '契約図', '実施図'],
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
                        } else if (!input) {
                            // Nếu element chưa tồn tại, thử lại sau 100ms
                            setTimeout(() => {
                                this.initTagify();
                            }, 100);
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
        'allDepartmentCustomFieldSets': {
            handler(newVal, oldVal) {
                // When department custom field sets change, reload custom fields if in edit mode
                if (this.isEditMode && newVal && newVal.length > 0) {
                    this.$nextTick(() => {
                        // Re-sync custom fields by re-running the sync logic
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
                        
                        const allFieldsFromSets = [];
                        newVal.forEach(set => {
                            if (set.fields && Array.isArray(set.fields)) {
                                set.fields.forEach(f => {
                                    if (!allFieldsFromSets.find(existing => existing.label && existing.label.trim() === f.label.trim())) {
                                        allFieldsFromSets.push(f);
                                    }
                                });
                            }
                        });
                        
                        if (allFieldsFromSets.length > 0) {
                            const savedValueMap = {};
                            saved.forEach(f => {
                                if (f && f.label) {
                                    // Handle both formats: {label, value} or {label, type, value}
                                    savedValueMap[f.label.trim()] = f;
                                }
                            });
                            
                            console.log('allDepartmentCustomFieldSets watcher: setting customFields', {
                                allFieldsFromSetsCount: allFieldsFromSets.length,
                                savedCount: saved.length,
                                savedValueMap: savedValueMap
                            });
                            
                            this.customFields = allFieldsFromSets.map(f => {
                                const savedField = savedValueMap[f.label.trim()];
                                console.log('allDepartmentCustomFieldSets watcher: mapping field', {
                                    label: f.label,
                                    type: f.type,
                                    savedField: savedField,
                                    savedFieldValue: savedField ? savedField.value : null
                                });
                                
                                if (f.type === 'checkbox') {
                                    let arr = [];
                                    if (savedField && savedField.value) {
                                        arr = savedField.value.split(',').map(s => s.trim()).filter(Boolean);
                                    }
                                    return { label: f.label, type: f.type, options: f.options, value: arr.join(','), valueArr: arr };
                            } else if (f.type === 'datetime') {
                                // Get value directly from saved field, no parsing needed
                                // Flatpickr will handle parsing when initialized (similar to project-list.js)
                                const value = savedField && savedField.value ? String(savedField.value).trim() : '';
                                console.log('allDepartmentCustomFieldSets watcher: datetime field', {
                                    label: f.label,
                                    savedField: savedField,
                                    value: value
                                });
                                return { 
                                    label: f.label, 
                                    type: f.type, 
                                    options: f.options, 
                                    value: value 
                                };
                                } else {
                                    return { 
                                        label: f.label, 
                                        type: f.type, 
                                        options: f.options, 
                                        value: savedField ? savedField.value : '' 
                                    };
                                }
                            });
                            
                            // Initialize datetime pickers after customFields is set
                            this.$nextTick(() => {
                                setTimeout(() => {
                                    this.initCustomFieldDatePickers();
                                }, 100);
                            });
                        }
                    });
                }
            },
            deep: true
        },
        'customFields': {
            handler(newVal, oldVal) {
                // Keep value and valueArr in sync for checkboxes
                if (!this.customFields || this.customFields.length === 0) return;
                
                // Only process if there are actual changes
                if (JSON.stringify(newVal) === JSON.stringify(oldVal)) return;
                
                this.customFields.forEach((field, idx) => {
                    if (field.type === 'checkbox') {
                        // If valueArr changes, update value
                        if (Array.isArray(field.valueArr)) {
                            this.customFields[idx].value = field.valueArr.join(',');
                        } else if (typeof field.value === 'string') {
                            this.customFields[idx].valueArr = field.value.split(',').map(s => s.trim()).filter(Boolean);
                        }
                    }
                });
                // Initialize datetime pickers when customFields change
                if (this.isEditMode) {
                    this.$nextTick(() => {
                        setTimeout(() => {
                            this.initCustomFieldDatePickers();
                        }, 100);
                    });
                }
            },
            deep: true
        },
    },
    async mounted() {
        await this.loadPermission();
        // if(!this.permission.is_member){
        //     this.showMessage('権限がありません。', true);
        //     setTimeout(() => {
        //         window.location.href = 'index.php';
        //     }, 1000);
        //     return;
        // }
        await this.loadProject();
        this.loadCategories();
        this.loadDepartmentCustomFieldSets();
        this.loadNotes();
        this.loadLogs();
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

        window.addEventListener('ai-action-success', (event) => {
            const { action, actions, response } = event.detail || {};
            
            // Support both single action (backward compatible) and multiple actions
            const allActions = actions && Array.isArray(actions) ? actions : (action ? [action] : []);
            
            if (allActions.length === 0) return;
            
            // Check if any action affects the current project
            let shouldReload = false;
            const memberManagerTeamActions = [
                'project_add_member', 'project_add_manager',
                'project_remove_member', 'project_remove_manager',
                'project_set_teams', 'project_add_team', 'project_remove_team',
                'project_clear_teams', 'project_clear_members', 'project_clear_managers',
                'project_clear_all_members', 'project_clear_all', 'project_add_team_members'
            ];
            
            for (const act of allActions) {
                const pid = act && (act.id || (act.params && act.params.project_id));
                if (pid && String(pid) === String(this.projectId)) {
                    // If action affects members/managers/teams, reload data
                    if (memberManagerTeamActions.includes(act.type)) {
                        shouldReload = true;
                        break;
                    }
                    // For other project actions, also reload
                    if (act.type && act.type.startsWith('project_')) {
                        shouldReload = true;
                        break;
                    }
                }
            }
            
            if (shouldReload) {
                // Reload project data and members to reflect changes
                this.loadProject().then(() => {
                    // Also reload members separately to ensure avatars are updated
                    this.loadMembers();
                });
            }
        });

        // Start timer to update time remaining every minute
        // this.timeRemainingTimer = setInterval(() => {
        //     // Force Vue to re-render the time remaining badge
        //     this.$forceUpdate();
        // }, 60000); // Update every minute

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
        this.addZoomToDescriptionImages();
        
        // Auto-refresh project information and history every 10 seconds if not in edit mode
        this.autoRefreshTimer = setInterval(() => {
            // Only refresh if not in edit mode
            if (!this.isEditMode) {
                this.loadProject();
                this.loadLogs();
            }
        }, 60000); // 60 seconds

    },
    updated() {
        this.$nextTick(() => {
            this.initTooltips();
            this.addZoomToDescriptionImages();
        });
    },
    beforeUnmount() {
        // Clean up timers
        if (this.timeRemainingTimer) {
            clearInterval(this.timeRemainingTimer);
        }
        if (this.autoRefreshTimer) {
            clearInterval(this.autoRefreshTimer);
        }
    }
});

// Register Comment Component
vueApp.component('comment-component', window.CommentComponent);

// Mount the Vue app
vueApp.mount('#app'); 