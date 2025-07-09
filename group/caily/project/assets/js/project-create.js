const { createApp } = Vue;

createApp({
    data() {
        return {
            projectId: null, // No project ID for creation
            project: {
                name: '',
                project_number: '',
                building_branch: '',
                building_size: '',
                building_type: '',
                building_number: '',
                progress: 0,
                priority: 'medium',
                status: 'open',
                department_id: '',
                start_date: '',
                end_date: '',
                project_order_type: '',
                department_custom_fields_set_id: '',
                custom_fields: '',
                description: '',
                tags: ''
            },
            department: null,
            managers: [],
            members: [],
            tasks: [],
            comments: [],
            team_list: [],
            newComment: '',
            isEditMode: true, // Always in edit mode for creation
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
            departments: [],
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
            memberSelectType: '',
            memberSelected: [],
            allUsers: [],
            departmentUsers: [],
            membersTagify: null,
            prevTeamIds: [],
            managerTagify: null,
            quillInstance: null,
            projectOrderTypeTagify: null,
            buildingBranchTagify: null,
            projectTagsTagify: null,
            customFields: [],
            departmentCustomFieldSets: [],
            copiedCustomFields: [],
            // Quill editor content storage (separate from Vue reactivity)
            quillContent: '',
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
            // Debounce timer for tags updates
            tagsUpdateTimer: null,
            // Thêm biến validationErrors để lưu lỗi nhập liệu
            validationErrors: {
                category_id: '',
                company_name: '',
                customer_id: '',
                name: '',
                project_number: ''
            },
        }
    },
    computed: {
        isManager() {
            if(USER_ROLE == `administrator`) return true;
            return true; // Allow creation for all users
        },
        filteredTeams() {
            if (!this.project || !this.project.department_id) return this.allTeams;
            return this.allTeams.filter(team => String(team.department_id) === String(this.project.department_id));
        },
        selectedCustomFieldSet() {
            if (!this.project || !this.project.department_custom_fields_set_id) return null;
            return this.departmentCustomFieldSets.find(set => String(set.id) === String(this.project.department_custom_fields_set_id)) || null;
        },
        presetDepartmentId() {
            return PRESET_DEPARTMENT_ID && PRESET_DEPARTMENT_ID > 0;
        },
    },
    methods: {
        async loadProject() {
            // For creation, initialize with default values
            this.project = {
                name: '',
                project_number: '',
                building_branch: '',
                building_size: '',
                building_type: '',
                building_number: '',
                progress: 0,
                priority: 'medium',
                status: 'open',
                department_id: PRESET_DEPARTMENT_ID || '',
                start_date: '',
                end_date: '',
                project_order_type: '',
                department_custom_fields_set_id: '',
                custom_fields: '',
                description: '',
                tags: ''
            };
            this.project.team_list = [];
            
            // Check for copied project data
            this.loadCopiedProjectData();
        },
        decodeHtmlEntities(str) {
            const txt = document.createElement('textarea');
            txt.innerHTML = str;
            return txt.value;
        },
        loadCopiedProjectData() {
            const copiedData = sessionStorage.getItem('copyProjectData');
            if (copiedData) {
                try {
                    const data = JSON.parse(copiedData);
                    console.log('Loaded copied project data:', data);
                    
                    // Populate project data
                    this.project.name = data.name || '';
                    this.project.project_number = data.project_number || '';
                    this.project.building_branch = data.building_branch || '';
                    this.project.building_size = data.building_size || '';
                    this.project.building_type = data.building_type || '';
                    this.project.building_number = data.building_number || '';
                    this.project.priority = data.priority || 'medium';
                    this.project.status = data.status || 'draft';
                    this.project.department_id = data.department_id || PRESET_DEPARTMENT_ID || '';
                    this.project.start_date = data.start_date || '';
                    this.project.end_date = data.end_date || '';
                    this.project.progress = data.progress || 0;
                    this.project.project_order_type = data.project_order_type || '';
                    this.project.department_custom_fields_set_id = data.department_custom_fields_set_id || '';
                    this.project.description = data.description || '';
                    this.project.tags = data.tags || '';
                    
                    // Populate newProject data
                    this.newProject.category_id = data.category_id || '';
                    this.newProject.company_name = data.company_name || '';
                    this.newProject.customer_id = data.customer_id || '';
                    this.newProject.teams = data.teams || '';
                    this.newProject.managers = data.managers || '';
                    this.newProject.members = data.members || '';
               
                    
                    // Store custom fields data for later processing
                    if (data.custom_fields && Array.isArray(data.custom_fields)) {
                        this.copiedCustomFields = data.custom_fields;
                    }
                    
                    // Clear the copied data from sessionStorage
                    sessionStorage.removeItem('copyProjectData');
                    
                    
                    // Trigger loading of related data based on copied data
                    this.$nextTick(async () => {
                        if (this.newProject.category_id) {
                            await this.loadCompaniesByCategory();
                        }
                        if (this.newProject.company_name) {
                            await this.loadContactsByCompany();
                        }
                        
                        // Set Quill editor content if description exists
                        if (this.project.description) {
                            this.$nextTick(() => {
                                this.setQuillContent(this.project.description);
                            });
                        }
                    });
                } catch (error) {
                    console.error('Error loading copied project data:', error);
                    sessionStorage.removeItem('copyProjectData');
                }
            }
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
        async loadDepartments() {
            try {
                const res = await axios.get('/api/index.php?model=department&method=list');
                if (res.data && Array.isArray(res.data)) {
                    this.departments = res.data;
                } else {
                    this.departments = [];
                }
            } catch (e) {
                this.departments = [];
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
        onDepartmentChange() {
            this.loadDepartmentCustomFieldSets();
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
        getStatusLabel(status) {
            const s = this.statuses.find(s => s.value === status);
            return s ? s.label : status;
        },
        getStatusButtonClass(status) {
            const s = this.statuses.find(s => s.value === status);
            return `btn-${s?.color || 'secondary'}`;
        },
        getPriorityLabel(priority) {
            const p = this.priorities.find(p => p.value === priority);
            return p ? p.label : priority;
        },
        getPriorityButtonClass(priority) {
            const p = this.priorities.find(p => p.value === priority);
            return `btn-${p?.color || 'secondary'}`;
        },
        selectStatus(status) {
            this.project.status = status;
            // Close dropdown
            const dropdownElement = document.querySelector('#statusDropdown');
            if (dropdownElement) {
                const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                if (dropdown) {
                    dropdown.hide();
                }
            }
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
        },
        toAPIDate(str) {
            if (!str) return '';
            return str.replace(/\//g, '-');
        },
        prepareCustomFieldsForSave() {
            if (!this.selectedCustomFieldSet) return [];
            return this.selectedCustomFieldSet.fields.map((f, idx) => {
                let value = '';
                const customField = this.customFields[idx];
                
                if (customField) {
                    if (f.type === 'checkbox') {
                        value = Array.isArray(customField.valueArr) ? customField.valueArr.join(',') : '';
                    } else {
                        value = customField.value || '';
                    }
                }
                
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
            // Sử dụng validateProjectForm thay cho showMessage
            if (!this.validateProjectForm()) {
                // Có lỗi nhập liệu, không gửi form
                return;
            }
            
            // Save custom field set id and values
            this.project.department_custom_fields_set_id = this.project.department_custom_fields_set_id || '';
            this.project.custom_fields = JSON.stringify(this.prepareCustomFieldsForSave());
            
            try {
                const formData = new FormData();
                formData.append('name', this.project.name || '');
                formData.append('building_branch', this.project.building_branch || '');
                formData.append('building_size', this.project.building_size || '');
                formData.append('building_type', this.project.building_type || '');
                formData.append('building_number', this.project.building_number || '');
                formData.append('progress', this.project.progress || '');
                formData.append('priority', this.project.priority || '');
                formData.append('status', this.project.status || '');
                formData.append('category_id', this.newProject.category_id || '');
                formData.append('company_name', this.newProject.company_name || '');
                formData.append('customer_id', this.newProject.customer_id || '');
                formData.append('department_id', this.project.department_id || '');
                formData.append('members', this.newProject.members || '');
                formData.append('managers', this.newProject.managers || '');
                formData.append('teams', this.newProject.teams || '');
                formData.append('start_date', this.toAPIDate(this.project.start_date));
                formData.append('end_date', this.toAPIDate(this.project.end_date));
                formData.append('project_order_type', this.project.project_order_type);
                formData.append('department_custom_fields_set_id', this.project.department_custom_fields_set_id);
                formData.append('custom_fields', this.project.custom_fields);
                formData.append('description', this.project.description || '');
                formData.append('project_number', this.project.project_number || '');
                formData.append('tags', this.project.tags || '');
                
                const response = await axios.post('/api/index.php?model=project&method=create', formData);
                if (response.data && response.data.status == 'success') {
                    // Show success message with SweetAlert2
                    Swal.fire({
                        title: '成功',
                        text: 'プロジェクトを作成しました。',
                        icon: 'success',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#3085d6'
                    }).then((result) => {
                        // Redirect after user closes the message
                        if (response.data.project_id) {
                            window.location.href = `detail.php?id=${response.data.project_id}`;
                        } else {
                            window.location.href = 'index.php';
                        }
                    });
                } else {
                    showMessage('プロジェクトの作成に失敗しました。', true);
                }
            } catch (error) {
                console.error('Error creating project:', error);
                showMessage('プロジェクトの作成に失敗しました。', true);
            }
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
        clearTagifyTags(field) {
            if (field === 'members' && this.membersTagify) {
                this.membersTagify.removeAllTags();
            } else if (field === 'manager' && this.managerTagify) {
                this.managerTagify.removeAllTags();
            } else if (field === 'team' && this.tagify) {
                this.tagify.removeAllTags();
            } else if (field === 'building_branch' && this.buildingBranchTagify) {
                this.buildingBranchTagify.removeAllTags();
            } else if (field === 'project_order_type' && this.projectOrderTypeTagify) {
                this.projectOrderTypeTagify.removeAllTags();
            } else if (field === 'project_tags' && this.projectTagsTagify) {
                this.projectTagsTagify.removeAllTags();
            }
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
                // No need to save tags during creation, just store in project object
            }, 1000); // Wait 1 second after user stops typing
        },
        initDatePickers() {
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
        },
        initQuillEditor() {
            if (this.quillInstance) return;
            
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
                setTimeout(() => {
                    if (this.quillInstance) {
                        this.quillInstance.focus();
                    }
                }, 100);
                
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
        initSelect2() {
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
                }).on('select2:clear', () => {
                    this.newProject.category_id = '';
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
                }).on('select2:clear', () => {
                    this.newProject.company_name = '';
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
                }).on('select2:clear', () => {
                    this.newProject.customer_id = '';
                });
            }
        },
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
                        // Initialize with empty tags
                        const tags = (this.filteredTeams || []).filter(t => this.newProject.teams.includes(t.id)).map(t => ({ value: t.name, id: t.id }));
                        // Danh sách tất cả team cho whitelist
                        const whitelist = this.filteredTeams.map(t => ({ value: t.name, id: t.id }));
                        this.tagify = new Tagify(teamInput, {
                            whitelist: whitelist,
                            enforceWhitelist: true,
                            dropdown: {
                                maxItems: 20,
                                enabled: 0,
                                closeOnSelect: true
                            },
                        });
                        this.tagify.addTags(tags);
                        // Xử lý khi xóa team thì xóa member của team đó
                        this.tagify.on('remove', async (e) => {
                            const removedTeamId = e.detail.data.id;
                            if (!removedTeamId || !this.membersTagify) return;
                            try {
                                const res = await axios.get(`/api/index.php?model=team&method=get&id=${removedTeamId}`);
                                if (res.data && Array.isArray(res.data.members)) {
                                    const teamMemberIds = res.data.members.map(m => String(m.user_id));
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
                        const updateBuildingBranch = () => {
                            this.project.building_branch = this.buildingBranchTagify.value.map(tag => tag.value).join(',');
                        };
                        this.buildingBranchTagify.on('add', updateBuildingBranch);
                        this.buildingBranchTagify.on('remove', updateBuildingBranch);
                    }
                    
                    // --- Tagify for project tags ---
                    const projectTagsInput = document.querySelector('#project_tags');
                    if (projectTagsInput && window.Tagify && !projectTagsInput._tagify) {
                        if (this.projectTagsTagify) {
                            try {
                                this.projectTagsTagify.destroy();
                            } catch (e) {
                                console.log('Error destroying existing projectTagsTagify:', e);
                            }
                        }
                        this.projectTagsTagify = new Tagify(projectTagsInput);
                        
                        // Add event listeners for auto-save
                        this.projectTagsTagify.on('add', () => {
                            this.updateTags();
                        });
                        
                        this.projectTagsTagify.on('remove', () => {
                            this.updateTags();
                        });
                        
                        this.projectTagsTagify.on('change', () => {
                            this.updateTags();
                        });
                    }
                    
                }, 100);
            });
        },
        async onTeamTagsChange(e) {
            const selected = this.tagify.value;
            const ids = selected.map(t => t.id).join(',');
            this.newProject.teams = ids;
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
        async generateProjectNumber() {
            try {
                const res = await axios.get(`/api/index.php?model=project&method=generateProjectNumber&&department_id=${this.project.department_id}`);
                this.project.project_number = res.data;
            } catch (e) {
                showMessage('プロジェクト番号の生成に失敗しました', true);
            }
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
                tagify.on('change', e => {
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
                tagify.on('change', e => {
                    const selected = tagify.value.map(t => t.id);
                    this.newProject.members = selected;
                });
                this.membersTagify = tagify;
            }
        },
        clearManagerMemberTagify() {
            // Clear manager Tagify
            const managerInput = document.getElementById('manager_tags');
            if (managerInput && managerInput._tagify) {
                managerInput._tagify.destroy();
                this.managerTagify = null;
            }
            // Clear member Tagify
            const membersInput = document.getElementById('members_tags');
            if (membersInput && membersInput._tagify) {
                membersInput._tagify.destroy();
                this.membersTagify = null;
            }
        },
        async populateTeamTags() {
            if (!this.tagify || !this.newProject.teams) return;
            
            try {
                const teamIds = this.newProject.teams.split(',').map(id => id.trim()).filter(Boolean);
                if (teamIds.length === 0) return;
                
                const res = await axios.get(`/api/index.php?model=team&method=listbyids&ids=${teamIds.join(',')}`);
                if (res.data && Array.isArray(res.data)) {
                    const teamTags = res.data.map(team => ({ value: team.name, id: team.id }));
                    this.tagify.addTags(teamTags);
                }
            } catch (error) {
                console.error('Error populating team tags:', error);
            }
        },
        async populateManagerTags() {
            if (!this.managerTagify || !this.newProject.managers) return;
            
            try {
                const managerIds = this.newProject.managers.split(',').map(id => id.trim()).filter(Boolean);
                if (managerIds.length === 0) return;
                
                // Use department users that are already loaded
                const managerTags = this.departmentUsers
                    .filter(user => managerIds.includes(String(user.id)))
                    .map(user => ({ 
                        id: user.id, 
                        value: user.user_name,
                        user_id: user.id,
                        name: user.user_name
                    }));
                
                if (managerTags.length > 0) {
                    this.managerTagify.addTags(managerTags);
                    console.log('Manager tags populated:', managerTags);
                }
            } catch (error) {
                console.error('Error populating manager tags:', error);
            }
        },
        async populateMemberTags() {
            if (!this.membersTagify || !this.newProject.members) return;
            
            try {
                const memberIds = this.newProject.members.split(',').map(id => id.trim()).filter(Boolean);
                if (memberIds.length === 0) return;
                
                // Use department users that are already loaded
                const memberTags = this.departmentUsers
                    .filter(user => memberIds.includes(String(user.id)))
                    .map(user => ({ 
                        id: user.id, 
                        value: user.user_name,
                        user_id: user.id,
                        name: user.user_name
                    }));
                
                if (memberTags.length > 0) {
                    this.membersTagify.addTags(memberTags);
                    console.log('Member tags populated:', memberTags);
                }
            } catch (error) {
                console.error('Error populating member tags:', error);
            }
        },
        loadCopiedCustomFields() {
            if (!this.copiedCustomFields || !this.selectedCustomFieldSet) {
                console.log('No copied custom fields or selected custom field set');
                return;
            }
            
            try {
                console.log('Loading copied custom fields:', this.copiedCustomFields);
                console.log('Selected custom field set:', this.selectedCustomFieldSet);
                
                // Map copied custom fields to the current custom field set
                this.selectedCustomFieldSet.fields.forEach((field, idx) => {
                    const copiedField = this.copiedCustomFields.find(cf => cf.label === field.label);
                    console.log(`Field ${field.label}:`, copiedField);
                    
                    if (copiedField && this.customFields[idx]) {
                        if (field.type === 'checkbox') {
                            // Handle checkbox fields
                            if (Array.isArray(copiedField.valueArr)) {
                                this.customFields[idx].valueArr = copiedField.valueArr;
                                this.customFields[idx].value = copiedField.valueArr.join(',');
                            } else if (typeof copiedField.value === 'string') {
                                this.customFields[idx].valueArr = copiedField.value.split(',').map(s => s.trim()).filter(Boolean);
                                this.customFields[idx].value = copiedField.value;
                            }
                        } else {
                            // Handle other field types
                            this.customFields[idx].value = copiedField.value || '';
                        }
                        
                        console.log(`Set field ${field.label} to:`, this.customFields[idx]);
                    }
                });
                
                console.log('Copied custom fields loaded successfully');
            } catch (error) {
                console.error('Error loading copied custom fields:', error);
            }
        },
        initializeCustomFields() {
            if (this.selectedCustomFieldSet) {
                this.customFields = this.selectedCustomFieldSet.fields.map(f => {
                    if (f.type === 'checkbox') {
                        return { label: f.label, type: f.type, options: f.options, value: '', valueArr: [] };
                    } else {
                        return { label: f.label, type: f.type, options: f.options, value: '' };
                    }
                });
                
                console.log('Custom fields initialized:', this.customFields);
                
                // Load copied custom fields if available
                if (this.copiedCustomFields && this.copiedCustomFields.length > 0) {
                    this.$nextTick(() => {
                        this.loadCopiedCustomFields();
                    });
                }
            }
        },
        setQuillContent(content) {
            if (this.quillInstance && content) {
                this.quillInstance.root.innerHTML = content;
                this.quillContent = this.quillInstance.getSemanticHTML();
                console.log('Quill content set:', content);
            }
        },
        // Thêm hàm validateProjectForm giống detail
        validateProjectForm() {
            this.validationErrors = {
                category_id: '',
                company_name: '',
                customer_id: '',
                name: '',
                project_number: ''
            };
            let valid = true;
            if (!this.newProject.category_id) {
                this.validationErrors.category_id = '顧客カテゴリーは必須です';
                valid = false;
            }
            if (!this.newProject.company_name) {
                this.validationErrors.company_name = '会社名は必須です';
                valid = false;
            }
            if (!this.newProject.customer_id) {
                this.validationErrors.customer_id = '担当者名は必須です';
                valid = false;
            }
            if (!this.project.project_number) {
                this.validationErrors.project_number = 'プロジェクト番号は必須です';
                valid = false;
            }
            if (!this.project.name) {
                this.validationErrors.name = 'プロジェクト名は必須です';
                valid = false;
            }
            if (this.project.start_date && this.project.end_date && new Date(this.project.start_date) > new Date(this.project.end_date)) {
                showMessage('開始日は終了日より前にしてください', true);
                valid = false;
            }
            return valid;
        },
        // Image handler giống project-detail.js
        imageHandler() {
            if (!this.projectId) {
                if (typeof showMessage === 'function') showMessage('まずプロジェクトを保存してください。その後で画像をアップロードできます。', true);
                return;
            }
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
                            if (typeof showMessage === 'function') showMessage('ファイルサイズは5MB以下にしてください。', true);
                            return;
                        }
                        // Upload ảnh
                        const uploadUrl = '/api/quill-image-upload.php';
                        let response;
                        if (window.swManager && window.swManager.swRegistration) {
                            // Sử dụng Service Worker với project_id nếu có
                            response = await window.swManager.uploadFile(file, uploadUrl, { project_id: this.projectId });
                        } else {
                            // Fallback to regular upload
                            const formData = new FormData();
                            formData.append('image', file);
                            if (this.projectId) formData.append('project_id', this.projectId);
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
                            if (typeof showMessage === 'function') showMessage('画像のアップロードに失敗しました: ' + (response.error || 'Unknown error'), true);
                        }
                    } catch (error) {
                        console.error('Error uploading image:', error);
                        if (typeof showMessage === 'function') showMessage('画像のアップロードに失敗しました。', true);
                    }
                }
            };
        },
    },
    watch: {
        'project.department_id': function(newVal) {
            this.loadDepartmentCustomFieldSets();
            // Load companies and contacts for the selected department
            if (newVal) {
                this.loadCompanies();
                this.loadContacts();
                // Reinitialize manager/member Tagify when department changes
                this.$nextTick(() => {
                    this.initManagerMembersTagify();
                });
            } else {
                // Clear companies, contacts and manager/member Tagify when no department is selected
                this.companies = [];
                this.contacts = [];
                this.clearManagerMemberTagify();
            }
        },
        'project.department_custom_fields_set_id': function(newVal) {
            if (newVal && this.selectedCustomFieldSet) {
                this.initializeCustomFields();
            }
        },
        'customFields': {
            handler(newVal, oldVal) {
                if (!this.selectedCustomFieldSet) return;
                
                // Only process if there are actual changes
                if (JSON.stringify(newVal) === JSON.stringify(oldVal)) return;
                
                this.selectedCustomFieldSet.fields.forEach((f, idx) => {
                    if (f.type === 'checkbox' && this.customFields[idx]) {
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
        // Load basic data first
        await this.loadAllTeams();
        await this.loadCategories();
        await this.loadDepartments();
        
        // Load project data (this will also load copied data if available)
        await this.loadProject();
        
        // Load related data based on the project data (including copied data)
        if (this.project.department_id) {
            await this.loadCompanies();
            await this.loadContacts();
            await this.loadDepartmentCustomFieldSets();
            await this.loadDepartmentUsers(); // Load department users first
            
            // Initialize custom fields if a set is selected
            if (this.project.department_custom_fields_set_id && this.selectedCustomFieldSet) {
                this.initializeCustomFields();
            }
        }
        
        // Initialize all UI components
        this.$nextTick(async () => {
            this.initDatePickers();
            this.initQuillEditor();
            this.initSelect2();
            this.initTagify();
            
            // Initialize manager/member Tagify after everything else is loaded
            if (this.project.department_id) {
                await this.initManagerMembersTagify();
                
                // If we have copied data, populate the Tagify components
                if (this.newProject.teams) {
                    await this.populateTeamTags();
                }
                if (this.newProject.managers) {
                    await this.populateManagerTags();
                }
                if (this.newProject.members) {
                    await this.populateMemberTags();
                }
            }
            
            // Handle copied custom fields after custom field sets are loaded
            if (this.copiedCustomFields && this.copiedCustomFields.length > 0) {
                this.$nextTick(() => {
                    this.loadCopiedCustomFields();
                });
            }
            
            // Set Quill editor content if description exists (for copied projects)
            if (this.project.description) {
                this.$nextTick(() => {
                    this.setQuillContent(this.decodeHtmlEntities(this.project.description));
                });
            }
        });
    }
}).mount('#app'); 