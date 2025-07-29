const { createApp } = Vue;

createApp({
    data() {
        return {
            parentProject: null,
            childProjects: [],
            loading: true,
            isEditMode: false,
            originalParentProject: null,
            companies: [],
            branches: [],
            contacts: [],
            users: [],
            guisReceiverDisplayName: '', // Add this to store the display name
            type1Tagify: null,
            type2Tagify: null,
            constructionBranchTagify: null,
            materials_layout: false,
            materials_rental: false,
            materials_contract: false,
            materials_tac: false,
            materials_other: false,
            validationErrors: {
                company_name: '',
                project_name: ''
            },
            statuses: [
                { value: 'draft', label: '下書き', color: 'secondary' },
                { value: 'under_contract', label: '契約中', color: 'info' },
                { value: 'in_progress', label: '進行中', color: 'primary' },
                { value: 'completed', label: '完了', color: 'success' },
                { value: 'cancelled', label: 'キャンセル', color: 'danger' }
            ],
            projectStatuses: [
                { value: 'draft', label: '下書き', color: 'secondary' },
                { value: 'open', label: 'オープン', color: 'info' },
                { value: 'confirming', label: '確認中', color: 'warning' },
                { value: 'in_progress', label: '進行中', color: 'primary' },
                { value: 'paused', label: '一時停止', color: 'warning' },
                { value: 'completed', label: '完了', color: 'success' },
                { value: 'cancelled', label: 'キャンセル', color: 'danger' }
            ]
        }
    },
    methods: {
        async loadParentProject() {
            try {
                const response = await axios.get(`/api/index.php?model=parentproject&method=getById&id=${PARENT_PROJECT_ID}`);
                if (response.data) {
                    this.parentProject = response.data;
                    
                    // Load GUIS receiver display name if exists
                    if (this.parentProject.guis_receiver) {
                        await this.loadGuisReceiverDisplayName();
                    }
                } else {
                    showMessage('親プロジェクトが見つかりません。', true);
                    window.location.href = 'index.php';
                }
            } catch (error) {
                console.error('Error loading parent project:', error);
                showMessage('親プロジェクトの読み込みに失敗しました。', true);
                window.location.href = 'index.php';
            }
        },
        async loadChildProjects() {
            try {
                const response = await axios.get(`/api/index.php?model=parentproject&method=getChildProjects&parent_project_id=${PARENT_PROJECT_ID}`);
                if (response.data) {
                    this.childProjects = response.data;
                }
            } catch (error) {
                console.error('Error loading child projects:', error);
                this.childProjects = [];
            }
        },
        getStatusLabel(status) {
            const s = this.statuses.find(s => s.value === status);
            return s ? s.label : status;
        },
        getStatusBadgeClass(status) {
            const s = this.statuses.find(s => s.value === status);
            return `bg-${s?.color || 'secondary'}`;
        },
        getProjectStatusLabel(status) {
            const s = this.projectStatuses.find(s => s.value === status);
            return s ? s.label : status;
        },
        getProjectStatusBadgeClass(status) {
            const s = this.projectStatuses.find(s => s.value === status);
            return `bg-${s?.color || 'secondary'}`;
        },
        formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('ja-JP');
        },
        formatDateTime(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleString('ja-JP', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        },
        hasMaterial(materialsString, materialName) {
            if (!materialsString) return false;
            const materials = materialsString.split(',').map(m => m.trim());
            return materials.includes(materialName);
        },
        toggleEditMode() {
            this.isEditMode = true;
            this.originalParentProject = JSON.parse(JSON.stringify(this.parentProject));
            this.loadInitialData();
            this.parseMaterials();
            this.$nextTick(async () => {
                await this.loadBranches();
                await this.loadContacts();
                this.initSelect2();
                this.initDatePickers();
                this.initTagify();
            });
        },
        cancelEdit() {
            this.isEditMode = false;
            this.parentProject = JSON.parse(JSON.stringify(this.originalParentProject));
            this.validationErrors = {
                company_name: '',
                project_name: ''
            };
            
            // Destroy Select2 instances to prevent duplicates
            this.destroySelect2Instances();
        },
        async loadInitialData() {
            await Promise.all([
                this.loadCompanies(),
                this.loadUsers()
            ]);
        },
        async loadCompanies() {
            try {
                const response = await axios.get('/api/index.php?model=customer&method=list_companies');
                if (response.data && response.data.status === 'success') {
                    this.companies = response.data.data || [];
                }
            } catch (error) {
                console.error('Error loading companies:', error);
            }
        },
        async loadUsers() {
            try {
                const response = await axios.get('/api/index.php?model=user&method=searchMembers');
                if (response.data && response.data.status === 'success') {
                    this.users = response.data.data || [];
                }
                // Ensure current GUIS receiver value is included
                if (this.parentProject.guis_receiver && !this.users.find(u => u.user_name === this.parentProject.guis_receiver)) {
                    this.users.push({ id: 0, user_name: this.parentProject.guis_receiver });
                }
            } catch (error) {
                console.error('Error loading users:', error);
            }
        },
        onCompanyChange() {
            // Clear branch and contact when company changes
            this.parentProject.branch_name = '';
            this.parentProject.contact_name = '';
            
            // Update branch select2 - trigger to reload data
            const $branch = $('#branch_name');
            if ($branch.length && $branch.data('select2')) {
                $branch.val(null).trigger('change');
                // Force reload of branch data
                $branch.select2('destroy');
                this.initBranchSelect2();
            }
            
            // Update contact select2
            const $contact = $('#contact_name');
            if ($contact.length && $contact.data('select2')) {
                $contact.val(null).trigger('change');
                $contact.select2('destroy');
                this.initContactSelect2();
            }
        },
        async loadBranches() {
            if (!this.parentProject.company_name) {
                this.branches = [];
                return;
            }
            try {
                const response = await axios.get(`/api/index.php?model=customer&method=list_branches_by_company&company_name=${encodeURIComponent(this.parentProject.company_name)}`);
                if (response.data && response.data.status === 'success') {
                    this.branches = response.data.data || [];
                }
                // Don't include current branch value when company changes - let user select fresh
            } catch (error) {
                console.error('Error loading branches:', error);
                this.branches = [];
            }
        },
        onBranchChange() {
            // Clear contact when branch changes
            this.parentProject.contact_name = '';
            
            // Update contact select2
            const $contact = $('#contact_name');
            if ($contact.length && $contact.data('select2')) {
                $contact.val(null).trigger('change');
                $contact.select2('destroy');
                this.initContactSelect2();
            }
        },
        async loadContacts() {
            if (!this.parentProject.company_name || !this.parentProject.branch_name) {
                this.contacts = [];
                return;
            }
            try {
                const response = await axios.get(`/api/index.php?model=customer&method=list_contacts_by_company_branch&company_name=${encodeURIComponent(this.parentProject.company_name)}&branch_name=${encodeURIComponent(this.parentProject.branch_name)}`);
                if (response.data && response.data.status === 'success') {
                    this.contacts = response.data.data || [];
                }
                // Don't include current contact value when branch changes - let user select fresh
            } catch (error) {
                console.error('Error loading contacts:', error);
                this.contacts = [];
            }
        },
        initSelect2() {
            // Initialize Select2 dropdowns
            this.initCompanySelect2();
            this.initBranchSelect2();
            this.initContactSelect2();
            this.initGuisReceiverSelect2();
        },
        initCompanySelect2() {
            const $company = $('#company_name');
            if ($company.length) {
                $company.select2({
                    placeholder: '選択してください',
                    dropdownParent: $company.parent(),
                    allowClear: true,
                    minimumResultsForSearch: 0,
                    ajax: {
                        url: '/api/index.php?model=customer&method=list_companies',
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
                                        id: item.company_name,
                                        text: item.company_name
                                    };
                                })
                            };
                        }
                    }
                }).on('select2:select', (e) => {
                    this.parentProject.company_name = e.params.data.id;
                    this.onCompanyChange();
                }).on('select2:clear', () => {
                    this.parentProject.company_name = '';
                    this.onCompanyChange();
                });

                // Set current value if exists
                if (this.parentProject.company_name) {
                    // Add the current option to the select
                    const option = new Option(this.parentProject.company_name, this.parentProject.company_name, true, true);
                    $company.append(option).trigger('change');
                }
            }
        },
        initBranchSelect2() {
            const $branch = $('#branch_name');
            if ($branch.length) {
                $branch.select2({
                    placeholder: '選択してください',
                    dropdownParent: $branch.parent(),
                    allowClear: true,
                    minimumResultsForSearch: 0,
                    ajax: {
                        url: '/api/index.php?model=customer&method=list_branches_by_company',
                        dataType: 'json',
                        delay: 250,
                        data: (params) => {
                            return {
                                search: params.term,
                                page: params.page || 1,
                                company_name: this.parentProject.company_name
                            };
                        },
                        processResults: function(data) {
                            return {
                                results: data.data.map(function(item) {
                                    return {
                                        id: item.branch,
                                        text: item.branch
                                    };
                                })
                            };
                        }
                    }
                }).on('select2:select', (e) => {
                    this.parentProject.branch_name = e.params.data.id;
                    this.onBranchChange();
                }).on('select2:clear', () => {
                    this.parentProject.branch_name = '';
                    this.onBranchChange();
                });

                // Set current value if exists
                if (this.parentProject.branch_name) {
                    // Add the current option to the select
                    const option = new Option(this.parentProject.branch_name, this.parentProject.branch_name, true, true);
                    $branch.append(option).trigger('change');
                }
            }
        },
        initContactSelect2() {
            const $contact = $('#contact_name');
            if ($contact.length) {
                $contact.select2({
                    placeholder: '選択してください',
                    dropdownParent: $contact.parent(),
                    allowClear: true,
                    minimumResultsForSearch: 0,
                    ajax: {
                        url: '/api/index.php?model=customer&method=list_contacts_by_company_branch',
                        dataType: 'json',
                        delay: 250,
                        data: (params) => {
                            return {
                                search: params.term,
                                page: params.page || 1,
                                company_name: this.parentProject.company_name,
                                branch_name: this.parentProject.branch_name
                            };
                        },
                        processResults: function(data) {
                            return {
                                results: data.data.map(function(item) {
                                    return {
                                        id: item.name,
                                        text: item.name
                                    };
                                })
                            };
                        }
                    }
                }).on('select2:select', (e) => {
                    this.parentProject.contact_name = e.params.data.id;
                }).on('select2:clear', () => {
                    this.parentProject.contact_name = '';
                });

                // Set current value if exists
                if (this.parentProject.contact_name) {
                    // Add the current option to the select
                    const option = new Option(this.parentProject.contact_name, this.parentProject.contact_name, true, true);
                    $contact.append(option).trigger('change');
                }
            }
        },
        initGuisReceiverSelect2() {
            const $guisReceiver = $('#guis_receiver');
            if ($guisReceiver.length) {
                $guisReceiver.select2({
                    placeholder: '選択してください',
                    dropdownParent: $guisReceiver.parent(),
                    allowClear: true,
                    minimumResultsForSearch: 0,
                    ajax: {
                        url: '/api/index.php?model=user&method=searchMembers',
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
                                        id: item.userid,
                                        text: item.realname
                                    };
                                })
                            };
                        }
                    }
                }).on('select2:select', (e) => {
                    this.parentProject.guis_receiver = e.params.data.id;
                }).on('select2:clear', () => {
                    this.parentProject.guis_receiver = '';
                });

                // Set current value if exists - load the user name from API
                if (this.parentProject.guis_receiver) {
                    this.loadGuisReceiverDisplayName();
                }
            }
        },
        async loadGuisReceiverDisplayName() {
            try {
                const response = await axios.get('/api/index.php?model=user&method=searchMembers');
                if (response.data && response.data.data) {
                    const user = response.data.data.find(u => u.userid === this.parentProject.guis_receiver);
                    if (user) {
                        // Set display name for view mode
                        this.guisReceiverDisplayName = user.realname;
                        
                        // Update Select2 dropdown if in edit mode
                        const $guisReceiver = $('#guis_receiver');
                        if ($guisReceiver.length && $guisReceiver.data('select2')) {
                            // Clear existing options and add the current user
                            $guisReceiver.empty();
                            const option = new Option(user.realname, this.parentProject.guis_receiver, true, true);
                            $guisReceiver.append(option).trigger('change');
                        }
                    }
                }
            } catch (error) {
                console.error('Error loading GUIS receiver display name:', error);
            }
        },
        initDatePickers() {
            // Request date picker (with time)
            const requestDateOptions = {
                enableTime: true,
                dateFormat: "Y/m/d H:i",
                time_24hr: true,
                allowInput: true,
                locale: "ja",
                defaultHour: 9,
                defaultMinute: 0,
                onChange: (selectedDates, dateStr, instance) => {
                    // Only update if this is actually the request date picker
                    if (instance.input && instance.input.id === 'request_date_picker') {
                        this.parentProject.request_date = dateStr;
                    }
                }
            };

            const requestDateEl = document.getElementById('request_date_picker');
            if (requestDateEl) {
                if (requestDateEl._flatpickr) requestDateEl._flatpickr.destroy();
                flatpickr(requestDateEl, requestDateOptions);
            }

            // Desired delivery date picker (date only)
            const desiredDeliveryOptions = {
                enableTime: false,
                dateFormat: "Y/m/d",
                allowInput: true,
                locale: "ja",
                onChange: (selectedDates, dateStr, instance) => {
                    // Only update if this is actually the desired delivery date picker
                    if (instance.input && instance.input.id === 'desired_delivery_date_picker') {
                        this.parentProject.desired_delivery_date = dateStr;
                    }
                }
            };

            const desiredDeliveryEl = document.getElementById('desired_delivery_date_picker');
            if (desiredDeliveryEl) {
                if (desiredDeliveryEl._flatpickr) desiredDeliveryEl._flatpickr.destroy();
                flatpickr(desiredDeliveryEl, desiredDeliveryOptions);
            }
        },
        // Helper method to safely update Flatpickr instances
        _safeUpdateFlatpickr(elementId, dateStr, dataField) {
            const targetEl = document.getElementById(elementId);
            
            // Update the data
           // this.parentProject[dataField] = dateStr;
            //Update the flatpickr instance
            if (targetEl && targetEl._flatpickr) {
                targetEl._flatpickr.setDate(dateStr);
            }
        },
        
        // Set current date and time for request date
        setCurrentDateTime() {
            const now = new Date();
            const dateStr = now.getFullYear() + '/' + 
                String(now.getMonth() + 1).padStart(2, '0') + '/' + 
                String(now.getDate()).padStart(2, '0') + ' ' + 
                String(now.getHours()).padStart(2, '0') + ':' + 
                String(now.getMinutes()).padStart(2, '0');
            
            this._safeUpdateFlatpickr('request_date_picker', dateStr, 'request_date');
        },
        
        // Set today's date for desired delivery date
        setTodayDate() {
            const today = new Date();
            const dateStr = today.getFullYear() + '/' + 
                String(today.getMonth() + 1).padStart(2, '0') + '/' + 
                String(today.getDate()).padStart(2, '0');
            
            this._safeUpdateFlatpickr('desired_delivery_date_picker', dateStr, 'desired_delivery_date');
        },
        initTagify() {
            // Initialize Tagify after Vue is mounted
            this.$nextTick(() => {
                // --- Tagify for Type1 (種類1) ---
                const type1Input = document.querySelector('#type1_tags');
                if (type1Input && window.Tagify && !type1Input._tagify) {
                    if (this.type1Tagify) {
                        try {
                            this.type1Tagify.destroy();
                        } catch (e) {
                            console.log('Error destroying existing type1Tagify:', e);
                        }
                    }
                    this.type1Tagify = new Tagify(type1Input, {
                        whitelist: ['TAC', '特注'],
                        maxTags: 5,
                        dropdown: {
                            maxItems: 20,
                            classname: "tags-look-type1",
                            enabled: 0,
                            closeOnSelect: true
                        },
                    });
                    const updateType1 = () => {
                        this.parentProject.type1 = this.type1Tagify.value.map(tag => tag.value).join(',');
                    };
                    this.type1Tagify.on('add', updateType1);
                    this.type1Tagify.on('remove', updateType1);
                    
                    // Set initial value if exists
                    if (this.parentProject.type1) {
                        const tags = this.parentProject.type1.split(',').map(tag => tag.trim()).filter(tag => tag);
                       // this.type1Tagify.addTags(tags);
                    }
                }
                
                // --- Tagify for Type2 (種類2) ---
                const type2Input = document.querySelector('#type2_tags');
                if (type2Input && window.Tagify && !type2Input._tagify) {
                    if (this.type2Tagify) {
                        try {
                            this.type2Tagify.destroy();
                        } catch (e) {
                            console.log('Error destroying existing type2Tagify:', e);
                        }
                    }
                    this.type2Tagify = new Tagify(type2Input, {
                        whitelist: ['共同', '集合'],
                        maxTags: 5,
                        dropdown: {
                            maxItems: 20,
                            classname: "tags-look-type2",
                            enabled: 0,
                            closeOnSelect: true
                        },
                    });
                    const updateType2 = () => {
                        this.parentProject.type2 = this.type2Tagify.value.map(tag => tag.value).join(',');
                    };
                    this.type2Tagify.on('add', updateType2);
                    this.type2Tagify.on('remove', updateType2);
                    
                    // Set initial value if exists
                    if (this.parentProject.type2) {
                        const tags = this.parentProject.type2.split(',').map(tag => tag.trim()).filter(tag => tag);
                        //this.type2Tagify.addTags(tags);
                    }
                }
                
                // --- Tagify for Construction Branch (工事支店) ---
                const constructionBranchInput = document.querySelector('#construction_branch_tags');
                if (constructionBranchInput && window.Tagify && !constructionBranchInput._tagify) {
                    if (this.constructionBranchTagify) {
                        try {
                            this.constructionBranchTagify.destroy();
                        } catch (e) {
                            console.log('Error destroying existing constructionBranchTagify:', e);
                        }
                    }
                    this.constructionBranchTagify = new Tagify(constructionBranchInput, {
                        whitelist: ['北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
                '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
                '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
                '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
                '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
                '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
                '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'],
                        maxTags: 5,
                        dropdown: {
                            maxItems: 20,
                            classname: "tags-look-construction-branch",
                            enabled: 0,
                            closeOnSelect: true
                        },
                    });
                    const updateConstructionBranch = () => {
                        this.parentProject.construction_branch = this.constructionBranchTagify.value.map(tag => tag.value).join(',');
                    };
                    this.constructionBranchTagify.on('add', updateConstructionBranch);
                    this.constructionBranchTagify.on('remove', updateConstructionBranch);
                    
                    // Set initial value if exists
                    if (this.parentProject.construction_branch) {
                        const tags = this.parentProject.construction_branch.split(',').map(tag => tag.trim()).filter(tag => tag);
                        //this.constructionBranchTagify.addTags(tags);
                    }
                }
            });
        },
        clearTagifyTags(fieldName) {
            if (fieldName === 'type1' && this.type1Tagify) {
                this.type1Tagify.removeAllTags();
            } else if (fieldName === 'type2' && this.type2Tagify) {
                this.type2Tagify.removeAllTags();
            } else if (fieldName === 'construction_branch' && this.constructionBranchTagify) {
                this.constructionBranchTagify.removeAllTags();
            }
        },
        destroySelect2Instances() {
            // Destroy company Select2
            const $company = $('#company_name');
            if ($company.length && $company.data('select2')) {
                $company.select2('destroy');
            }
            
            // Destroy branch Select2
            const $branch = $('#branch_name');
            if ($branch.length && $branch.data('select2')) {
                $branch.select2('destroy');
            }
            
            // Destroy contact Select2
            const $contact = $('#contact_name');
            if ($contact.length && $contact.data('select2')) {
                $contact.select2('destroy');
            }
            
            // Destroy GUIS receiver Select2
            const $guisReceiver = $('#guis_receiver');
            if ($guisReceiver.length && $guisReceiver.data('select2')) {
                $guisReceiver.select2('destroy');
            }
        },
        parseMaterials() {
            if (!this.parentProject.materials) {
                this.materials_layout = false;
                this.materials_rental = false;
                this.materials_contract = false;
                this.materials_tac = false;
                this.materials_other = false;
                return;
            }
            const materials = this.parentProject.materials.split(',').map(m => m.trim());
            this.materials_layout = materials.includes('配置図');
            this.materials_rental = materials.includes('家賃審査書');
            this.materials_contract = materials.includes('契約図');
            this.materials_tac = materials.includes('TAC図');
            this.materials_other = materials.includes('その他');
        },
        validateParentProjectForm() {
            this.validationErrors = {
                company_name: '',
                project_name: ''
            };
            let valid = true;
            
            if (!this.parentProject.company_name) {
                this.validationErrors.company_name = '会社名は必須です';
                valid = false;
            }
            
            if (!this.parentProject.project_name) {
                this.validationErrors.project_name = '案件名は必須です';
                valid = false;
            }
            
            return valid;
        },
        async saveParentProject() {
            if (!this.validateParentProjectForm()) {
                return;
            }

            // Convert checkbox materials to comma-separated string
            const materialsArray = [];
            if (this.materials_layout) materialsArray.push('配置図');
            if (this.materials_rental) materialsArray.push('家賃審査書');
            if (this.materials_contract) materialsArray.push('契約図');
            if (this.materials_tac) materialsArray.push('TAC図');
            if (this.materials_other) materialsArray.push('その他');
            this.parentProject.materials = materialsArray.join(',');

            try {
                const formData = new FormData();
                formData.append('id', this.parentProject.id);
                formData.append('company_name', this.parentProject.company_name || '');
                formData.append('branch_name', this.parentProject.branch_name || '');
                formData.append('contact_name', this.parentProject.contact_name || '');
                formData.append('guis_receiver', this.parentProject.guis_receiver || '');
                formData.append('request_date', this.parentProject.request_date || '');
                formData.append('construction_number', this.parentProject.construction_number || '');
                formData.append('project_number', this.parentProject.project_number || '');
                formData.append('project_name', this.parentProject.project_name || '');
                formData.append('construction_branch', this.parentProject.construction_branch || '');
                formData.append('scale', this.parentProject.scale || '');
                formData.append('type1', this.parentProject.type1 || '');
                formData.append('type2', this.parentProject.type2 || '');
                formData.append('request_type', this.parentProject.request_type || '');
                formData.append('desired_delivery_date', this.parentProject.desired_delivery_date || '');
                formData.append('materials', this.parentProject.materials);
                formData.append('structural_office', this.parentProject.structural_office || '');
                formData.append('notes', this.parentProject.notes || '');
                formData.append('status', this.parentProject.status || 'draft');
                
                const response = await axios.post('/api/index.php?model=parentproject&method=update', formData);
                if (response.data && response.data.status == 'success') {
                    // Show success message with SweetAlert2
                    Swal.fire({
                        title: '成功',
                        text: '親プロジェクトを更新しました。',
                        icon: 'success',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#3085d6'
                    }).then(() => {
                        this.isEditMode = false;
                        this.destroySelect2Instances();
                        this.loadParentProject();
                    });
                } else {
                    showMessage(response.data?.error || '更新に失敗しました。', true);
                }
            } catch (error) {
                console.error('Error saving parent project:', error);
                showMessage('更新に失敗しました。', true);
            }
        },
        getStatusButtonClass(status) {
            const s = this.statuses.find(s => s.value === status);
            return `btn-${s?.color || 'secondary'}`;
        },
        selectStatus(status) {
            this.parentProject.status = status;
            // Close dropdown
            const dropdownElement = document.querySelector('#statusDropdown');
            if (dropdownElement) {
                const dropdown = bootstrap.Dropdown.getInstance(dropdownElement);
                if (dropdown) {
                    dropdown.hide();
                }
            }
        },
        async generateProjectNumber() {
            try {
                const response = await axios.get('/api/index.php?model=parentproject&method=generateProjectNumber');
                if (response.data && response.data.status === 'success') {
                   this.parentProject.project_number = response.data.project_number;
                   // showMessage('プロジェクト番号を生成しました', false);
                } else {
                    showMessage('プロジェクト番号の生成に失敗しました', true);
                }
            } catch (error) {
                console.error('Error generating project number:', error);
                showMessage('プロジェクト番号の生成に失敗しました', true);
            }
        },

        async deleteParentProject() {
            try {
                const result = await Swal.fire({
                    title: '確認',
                    text: 'この親プロジェクトを削除しますか？子プロジェクトがある場合は削除できません。',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: '削除',
                    cancelButtonText: 'キャンセル'
                });
                
                if (result.isConfirmed && this.parentProject) {
                    const formData = new FormData();
                    formData.append('id', this.parentProject.id);
                    
                    const response = await axios.post('/api/index.php?model=parentproject&method=delete', formData);
                    
                    if (response.data && response.data.status === 'success') {
                        Swal.fire({
                            title: '成功',
                            text: '親プロジェクトを削除しました。',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            window.location.href = 'index.php';
                        });
                    } else {
                        showMessage(response.data?.error || '削除に失敗しました。', true);
                    }
                }
            } catch (error) {
                console.error('Error deleting parent project:', error);
                showMessage('削除に失敗しました。', true);
            }
        }
    },
    async mounted() {
        await this.loadParentProject();
        await this.loadChildProjects();
        this.loading = false;
    }
}).mount('#app'); 