const { createApp } = Vue;

createApp({
    data() {
        return {
            projectId: PROJECT_ID,
            project: null,
            department: null,
            managers: [],
            members: [],
            tasks: [],
            comments: [],
            team_list: [],
            newComment: '',
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
            categories: [],
            companies: [],
            contacts: [],
            newProject: {
                category_id: '',
                company_name: '',
                customer_id: '',
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
            customFields: [],
            departmentCustomFieldSets: [],
            // Comment pagination
            commentsPage: 1,
            commentsPerPage: 20,
            loadingOlderComments: false,
            hasMoreComments: true,
            showLoadMoreButton: false, // Track if we should show the load more button
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
        displayedComments() {
            // Sort comments by date ascending (oldest first)
            const sortedComments = [...this.comments].sort((a, b) => 
                new Date(a.created_at) - new Date(b.created_at)
            );
            
            // Return all loaded comments (pagination is handled by the API)
            return sortedComments;
        },
    },
    methods: {
        async loadProject() {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getById&id=${this.projectId}`);
                this.project = response.data;
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
        async loadComments() {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getComments&project_id=${this.projectId}&page=${this.commentsPage}&per_page=${this.commentsPerPage}`);
                const newComments = response.data || [];
                
                if (this.commentsPage === 1) {
                    // First load - replace all comments
                    this.comments = newComments;
                    
                    // Scroll to bottom after Vue rendering is complete (initial load)
                    this.$nextTick(() => {
                        setTimeout(() => {
                            const element = document.querySelector('#chat-history-project');
                            if (element && element.scrollHeight > 0) {
                                element.scrollTop = element.scrollHeight;
                            }
                        }, 100);
                        this.setConnectedUsers();
                    });
                } else {
                    // Loading more - prepend older comments
                    // Store current scroll position and height
                    const element = document.querySelector('#chat-history-project');
                    const scrollHeightBefore = element ? element.scrollHeight : 0;
                    
                    this.comments = [...newComments, ...this.comments];
                    
                    // Restore scroll position after loading older comments
                    this.$nextTick(() => {
                        if (element) {
                            const scrollHeightAfter = element.scrollHeight;
                            const scrollDifference = scrollHeightAfter - scrollHeightBefore;
                            element.scrollTop = scrollDifference;
                        }
                    });
                }
                
                // Check if there are more comments
                this.hasMoreComments = newComments.length === this.commentsPerPage;
                
                this.loadingOlderComments = false;
              
            } catch (error) {
                console.error('Error loading comments:', error);
                this.loadingOlderComments = false;
            }
        },
        async loadMoreComments() {
            if (this.loadingOlderComments || !this.hasMoreComments) return;
            
            this.loadingOlderComments = true;
            this.showLoadMoreButton = false; // Hide button while loading
            
            try {
                this.commentsPage++;
                await this.loadComments();
            } catch (error) {
                console.error('Error loading more comments:', error);
                this.commentsPage--; // Revert page number on error
            } finally {
                this.loadingOlderComments = false;
            }
        },
        handleScroll(event) {
            const element = event.target;
            
            // Check if user is at the top of the scroll area
            const isAtTop = element.scrollTop === 0;
            this.showLoadMoreButton = isAtTop && this.hasMoreComments && !this.loadingOlderComments;
            
            // Load more comments when scrolling to the top
            if (isAtTop && this.hasMoreComments && !this.loadingOlderComments) {
                this.loadMoreComments();
            }
        },
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
        async addComment() {
            if (!this.newComment.trim()) return;
            try {
                const formData = new FormData();
                formData.append('project_id', this.projectId);
                formData.append('content', this.newComment.trim());
                formData.append('user_id', USER_ID);
                await axios.post('/api/index.php?model=project&method=addComment', formData);
                this.newComment = '';
                
                // Reset pagination and reload comments to show the new comment
                this.commentsPage = 1;
                this.hasMoreComments = true;
                this.showLoadMoreButton = false; // Reset button state
                await this.loadComments();
                
                // Scroll to bottom after Vue rendering is complete
                this.$nextTick(() => {
                    setTimeout(() => {
                        const element = document.querySelector('#chat-history-project');
                        console.log('Add comment scroll height:', element ? element.scrollHeight : 0);
                        if (element && element.scrollHeight > 0) {
                            element.scrollTop = element.scrollHeight;
                        }
                    }, 100);
                });
            } catch (error) {
                console.error('Error adding comment:', error);
                alert('コメントの追加に失敗しました。');
            }
        },
        async deleteProject() {
            if (!confirm('本当にこのプロジェクトを削除しますか？')) {
                return;
            }
            try {
                const formData = new FormData();
                formData.append('model', 'project');
                formData.append('method', 'delete');
                formData.append('id', this.projectId);
                await axios.post('/api/index.php', formData);
                alert('プロジェクトが削除されました。');
                window.location.href = 'index.php';
            } catch (error) {
                console.error('Error deleting project:', error);
                alert('プロジェクトの削除に失敗しました。');
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
                category_id: this.newProject.category_id,
                company_name: this.newProject.company_name,
                customer_id: this.newProject.customer_id,
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
                custom_fields: customFieldsData
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
            this.updatePriority();
        },
        async updatePriority() {
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
            try {
                const formData = new FormData();
                formData.append('id', this.projectId);
                formData.append('priority', this.project.priority);
                const response = await axios.post('/api/index.php?model=project&method=updatePriority', formData);
                if (response.data && response.data.success !== false) {
                    // Show success message
                    showMessage('優先度の更新に完了しました。');
                } else {
                    showMessage('優先度の更新に失敗しました。', true);
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
            if (!name) return '?';
            // Check if name contains Japanese characters
            const hasJapanese = /[\u3040-\u309f\u30a0-\u30ff\u4e00-\u9faf]/.test(name);
            if (hasJapanese) {
                // For Japanese names, take first 2 characters
                return name.substring(0, 2);
            } else {
                // For English names, take first letter of each word
                const initials = name.split(' ').map(n => n.charAt(0)).join('');
                return initials.toUpperCase();
            }
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
            this.newProject.company_name = '';
            this.newProject.customer_id = '';
            this.loadCompaniesByCategory();
            this.contacts = [];
        },
        onCompanyChange() {
            this.newProject.customer_id = '';
            this.loadContactsByCompany();
        },
        async updateProgress() {
            if (this.isEditMode) {
                return;
            }
            try {
                const formData = new FormData();
                formData.append('id', this.projectId);
                formData.append('progress', this.project.progress);
                const response = await axios.post('/api/index.php?model=project&method=updateProgress', formData);
                if (response.data && response.data.success === false) {
                    alert('進捗率の更新に失敗しました。');
                }
            } catch (error) {
                console.error('Error updating progress:', error);
                alert('進捗率の更新に失敗しました。');
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
        initTagify() {
            if (!this.isEditMode) return;
            const input = document.getElementById('team_tags');
            if (!input) return;
            // Destroy previous Tagify instance if exists
            if (input._tagify) {
                input._tagify.destroy();
            }
            // Gán giá trị team đã chọn
            const tags = (this.project.team_list || []).map(t => ({ value: t.name, id: t.id }));
            // Danh sách tất cả team cho whitelist
            const whitelist = this.filteredTeams.map(t => ({ value: t.name, id: t.id }));
            this.tagify = new Tagify(input, {
                whitelist: whitelist,
                enforceWhitelist: true,
                dropdown: { enabled: 0 }
            });
            this.tagify.addTags(tags);
            // Xử lý khi xóa team thì xóa member của team đó
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
                    enforceWhitelist: true,
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
                    enforceWhitelist: true,
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
            if (!this.isManager) {
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
            // Save custom field set id and values
            this.project.department_custom_fields_set_id = this.project.department_custom_fields_set_id || '';
            this.project.custom_fields = JSON.stringify(this.prepareCustomFieldsForSave());
            try {
                const formData = new FormData();
                formData.append('id', this.projectId);
                formData.append('name', this.project.name || '');
                formData.append('building_branch', this.project.building_branch || '');
                formData.append('building_size', this.project.building_size || '');
                formData.append('building_type', this.project.building_type || '');
                formData.append('building_number', this.project.building_number || '');
                formData.append('project_number', this.project.project_number || '');
                formData.append('progress', this.project.progress || '');
                formData.append('priority', this.project.priority || '');
                formData.append('status', this.project.status || '');
                formData.append('customer_id', this.newProject.customer_id || '');
                formData.append('members', this.newProject.members || '');
                formData.append('managers', this.newProject.managers || '');
                formData.append('teams', this.newProject.teams || '');
                formData.append('start_date', this.toAPIDate(this.project.start_date));
                formData.append('end_date', this.toAPIDate(this.project.end_date));
                formData.append('project_order_type', this.project.project_order_type);
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
        clearTagifyTags(field) {
            if (field === 'members' && this.membersTagify) {
                this.membersTagify.removeAllTags();
            } else if (field === 'manager' && this.managerTagify) {
                this.managerTagify.removeAllTags();
            } else if (field === 'team' && this.tagify) {
                this.tagify.removeAllTags();
            }
        },
        initQuillEditor() {
            if (this.quillInstance || !this.isEditMode) return;
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
            this.quillInstance = new Quill(el, {
                bounds: el,
                placeholder: 'Type Something...',
                modules: {
                    syntax: true,
                    toolbar: toolbarOptions
                },
                theme: 'snow'
            });
            if (this.project.description) {
                const html = this.decodeHtmlEntities(this.project.description);
                this.quillInstance.root.innerHTML = html;
            }
            
            // Add debounce to prevent too frequent updates
            let debounceTimer;
            this.quillInstance.on('text-change', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    const html = this.quillInstance.getSemanticHTML();
                    this.project.description = html;
                    // Also update hidden textarea for v-model
                    const textarea = document.getElementById('quill_description_textarea');
                    if (textarea) {
                        textarea.value = html;
                        const event = new Event('input', { bubbles: true });
                        textarea.dispatchEvent(event);
                    }
                }, 300); // 300ms debounce
            });
        },
        destroyQuillEditor() {
            if (this.quillInstance) {
                this.quillInstance = null;
            }
        },
        decodeHtmlEntities(str) {
            const txt = document.createElement('textarea');
            txt.innerHTML = str;
            return txt.value;
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
            if (!this.newProject.category_id) {
                this.companies = [];
                return;
            }
            const params = new URLSearchParams({
                category_id: this.newProject.category_id
            });
            if (this.project.department_id) {
                params.append('department_id', this.project.department_id);
            }
            const res = await axios.get(`/api/index.php?model=customer&method=list_companies_by_category&${params.toString()}`);
            if (res.data && res.data.data) {
                this.companies = res.data.data;
            }
        },
        async loadContactsByCompany() {
            if (!this.newProject.company_name) {
                this.contacts = [];
                return;
            }
            const params = new URLSearchParams({
                company_name: this.newProject.company_name
            });
            if (this.project.department_id) {
                params.append('department_id', this.project.department_id);
            }
            const res = await axios.get(`/api/index.php?model=customer&method=list_contacts_by_company&${params.toString()}`);
            if (res.data && res.data.data) {
                this.contacts = res.data.data;
            }
        },
    },
    watch: {
        isEditMode(newVal) {
            if (newVal) {
                this.$nextTick(() => {
                    this.initQuillEditor();
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
                    // --- Add select2 initialization for detail page ---
                    // 会社名 (category_id)
                    const $category = $('#category_id');
                    if ($category.length) {
                        $category.select2({
                            placeholder: '選択してください',
                            dropdownParent: $category.parent(),
                            allowClear: true,
                            minimumResultsForSearch: 0,
                            ajax: {
                                url: '/api/index.php?model=customer&method=list_categories',
                                dataType: 'json',
                                delay: 250,
                                data: function(params) {
                                    return {
                                        search: params.term,
                                        page: params.page || 1
                                    };
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
                            this.newProject.category_id = e.params.data.id;
                            this.onCategoryChange();
                        });
                    }
                    // 支店名 (company_name)
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
                                        category_id: this.newProject.category_id
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
                            this.newProject.company_name = e.params.data.id;
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
                                        company_name: this.newProject.company_name
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
                            this.newProject.customer_id = e.params.data.id;
                        });
                    }
                    // --- Tagify for project_order_type ---
                    const input = document.querySelector('#project_order_type');
                    if (input && window.Tagify) {
                        if (this.projectOrderTypeTagify) {
                            this.projectOrderTypeTagify.destroy();
                        }
                        this.projectOrderTypeTagify = new Tagify(input, {
                            whitelist: ['新規', '修正', '免震', '耐震', '計画変更'],
                            maxTags: 5,
                            dropdown: {
                                maxItems: 20,
                                classname: "tags-look",
                                enabled: 0,
                                closeOnSelect: true
                            },
                        });
                        // Set default value
                        let tags = [];
                        if (typeof this.project.project_order_type === 'string' && this.project.project_order_type) {
                            tags = this.project.project_order_type.split(',').map(s => s.trim()).filter(Boolean);
                        }
                        if (tags.length > 0) {
                          //  this.projectOrderTypeTagify.addTags(tags);
                        }
                        const updateOrderType = () => {
                            this.project.project_order_type = this.projectOrderTypeTagify.value.map(tag => tag.value).join(',');
                        };
                        this.projectOrderTypeTagify.on('add', updateOrderType);
                        this.projectOrderTypeTagify.on('remove', updateOrderType);
                    }
                });
                window.addEventListener('beforeunload', this.handleBeforeUnload);
            } else {
                this.destroyQuillEditor();
                $('#category_id').select2('destroy');
                $('#company_name').select2('destroy');
                $('#customer_id').select2('destroy');
                // Destroy Tagify for project_order_type
                if (this.projectOrderTypeTagify) {
                    this.projectOrderTypeTagify.destroy();
                    this.projectOrderTypeTagify = null;
                }
                window.removeEventListener('beforeunload', this.handleBeforeUnload);
            }
        },
        'project.department_id': function() {
            this.loadDepartmentCustomFieldSets();
            // Load companies and contacts for the selected department
            if (this.project && this.project.department_id) {
                this.loadCompanies();
                this.loadContacts();
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
        await this.loadAllTeams();
        await this.loadCategories();
        await this.loadProject();
        if (this.project) {
            this.newProject.category_id = this.project.category_id;
            this.newProject.company_name = this.project.company_name;
            this.newProject.customer_id = this.project.customer_id;
        }
        
        // Only load companies and contacts if department is set
        if (this.project && this.project.department_id) {
            await this.loadCompanies();
            await this.loadContacts();
        }
        
        this.loadTasks();
        this.loadComments();
        this.loadAllUsers();
        await this.loadDepartmentCustomFieldSets();
        this.$nextTick(() => {
            this.initTooltips();
            this.initTagify();
            this.initDatePickers();
        });
    },
    updated() {
        this.$nextTick(() => {
            this.initTooltips();
        });
    }
}).mount('#app'); 