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
            departments: [],
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
            ],
            // Child project modal data
            newChildProject: {
                name: '',
                department_id: '',
                project_number: '',
                description: '',
                start_date: '',
                end_date: '',
                project_order_type: '',
                parent_project_id: PARENT_PROJECT_ID,
                is_kadai: true,

            },
            editingChildProject: {
                id: null,
                name: '',
                department_id: '',
                project_number: '',
                description: '',
                start_date: '',
                end_date: '',
                project_order_type: '',
                parent_project_id: PARENT_PROJECT_ID,
                is_kadai: true,

            },
            childProjectValidationErrors: {
                name: '',
                department_id: '',
                project_number: '',
                start_date: '',
                end_date: ''
            },
            editChildProjectValidationErrors: {
                name: '',
                department_id: '',
                project_number: '',
                start_date: '',
                end_date: ''
            },
            creatingChildProject: false,
            updatingChildProject: false,
            // Quotation data
            quotations: [],
            selectedQuotation: null,
            creatingQuotation: false,
            quotationBranches: [],
            quotationUsers: [],
            selectedContactSeal: null,
            newQuotation: {
                issue_date: '',
                quotation_number: '',
                sender_company: '',
                sender_address: '',
                sender_contact: '',
                selected_branch_id: '',
                receiver_company: '',
                receiver_address: '',
                receiver_contact: '',
                receiver_tel: '',
                receiver_fax: '',
                receiver_registration_number: '',
                items: [],
                total_amount: 0,
                tax_rate: 10,
                total_with_tax: 0,
                delivery_location: '',
                payment_method: '',
                valid_until: '',
                notes: '',
                parent_project_id: PARENT_PROJECT_ID
            },
            selectedQuotation: null,
            creatingQuotation: false,
            quotationFormBackup: null,
            // Price list data
            priceListProducts: [],
            priceListDepartments: [],
            filteredPriceListProducts: [],
            selectedPriceListDepartment: '',
            priceListSearchTerm: '',
            priceListModal: null
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
                console.log('Loading child projects for parent project ID:', PARENT_PROJECT_ID);
                const response = await axios.get(`/api/index.php?model=parentproject&method=getChildProjects&parent_project_id=${PARENT_PROJECT_ID}`);
                console.log('Child projects response:', response.data);
                if (response.data) {
                    this.childProjects = response.data;
                    console.log('Child projects loaded:', this.childProjects);
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
        initializeQuotationDatePickers() {
            // Initialize Flatpickr for quotation date fields
            if (window.flatpickr) {
                // Initialize issue date picker
                const issueDateEl = document.getElementById('quotation_issue_date');
                if (issueDateEl) {
                    if (issueDateEl._flatpickr) {
                        issueDateEl._flatpickr.destroy();
                    }
                    issueDateEl._flatpickr = flatpickr(issueDateEl, {
                        dateFormat: 'Y-m-d',
                        locale: 'ja',
                        allowInput: true,
                        clickOpens: true,
                        onChange: (selectedDates, dateStr) => {
                            this.newQuotation.issue_date = dateStr;
                        }
                    });
                    
                    // Set initial date if available
                    if (this.newQuotation.issue_date) {
                        issueDateEl._flatpickr.setDate(this.newQuotation.issue_date);
                    }
                }
                
                // Initialize valid until date picker
                const validUntilEl = document.getElementById('quotation_valid_until');
                if (validUntilEl) {
                    if (validUntilEl._flatpickr) {
                        validUntilEl._flatpickr.destroy();
                    }
                    validUntilEl._flatpickr = flatpickr(validUntilEl, {
                        dateFormat: 'Y-m-d',
                        locale: 'ja',
                        allowInput: true,
                        clickOpens: true,
                        onChange: (selectedDates, dateStr) => {
                            this.newQuotation.valid_until = dateStr;
                        }
                    });
                    
                    // Set initial date if available
                    if (this.newQuotation.valid_until) {
                        validUntilEl._flatpickr.setDate(this.newQuotation.valid_until);
                    }
                }
            }
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
        },

        // Child project modal methods


        async showCreateChildProjectModal() {
            this.resetChildProjectForm();
            this.loadDepartments();
            

            
            this.generateChildProjectNumber();
            const modal = new bootstrap.Modal(document.getElementById('createChildProjectModal'));
            modal.show();
            
            this.$nextTick(() => {
                this.initializeChildProjectDatePickers();
                this.initializeChildProjectTagify();
            });
        },

        resetChildProjectForm() {
            this.newChildProject = {
                name: '',
                department_id: '',
                project_number: '',
                description: '',
                start_date: '',
                end_date: '',
                project_order_type: '',
                parent_project_id: PARENT_PROJECT_ID,
                is_kadai: true,

            };
            this.childProjectValidationErrors = {
                name: '',
                department_id: '',
                project_number: '',
                start_date: '',
                end_date: ''
            };
            
            // Destroy existing flatpickr instances if they exist
            const startPicker = document.getElementById('start_date_picker');
            const endPicker = document.getElementById('end_date_picker');
            
            if (startPicker && startPicker._flatpickr) {
                startPicker._flatpickr.destroy();
            }
            if (endPicker && endPicker._flatpickr) {
                endPicker._flatpickr.destroy();
            }
            
            // Destroy Tagify instance
            this.destroyChildProjectTagify();
        },

        async loadDepartments() {
            try {
                const response = await axios.get('/api/index.php?model=department&method=listByUser');
                if (response.data) {
                    this.departments = response.data;
                    // Generate project number after departments are loaded
                    this.generateChildProjectNumber();
                }
            } catch (error) {
                console.error('Error loading departments:', error);
                this.departments = [];
            }
        },
        
        initializeChildProjectDatePickers() {
            // Initialize start date picker
            const startDatePicker = document.getElementById('start_date_picker');
            if (startDatePicker) {
                flatpickr(startDatePicker, {
                    enableTime: true,
                    dateFormat: "Y/m/d H:i",
                    time_24hr: true,
                    locale: "ja",
                    allowInput: true,
                    clickOpens: true,
                    onChange: (selectedDates, dateStr) => {
                        this.newChildProject.start_date = dateStr;
                    }
                });
            }
            
            // Initialize end date picker
            const endDatePicker = document.getElementById('end_date_picker');
            if (endDatePicker) {
                flatpickr(endDatePicker, {
                    enableTime: true,
                    dateFormat: "Y/m/d H:i",
                    time_24hr: true,
                    locale: "ja",
                    allowInput: true,
                    clickOpens: true,
                    onChange: (selectedDates, dateStr) => {
                        this.newChildProject.end_date = dateStr;
                    }
                });
            }
        },
        
        initializeChildProjectTagify() {
            // Initialize Tagify for child project order type
            const orderTypeInput = document.querySelector('#child_project_order_type');
            if (orderTypeInput) {
                // Destroy existing instance if it exists
                if (orderTypeInput.tagify) {
                    orderTypeInput.tagify.destroy();
                }
                
                // Clear any existing content
                orderTypeInput.value = '';
                
                this.childProjectOrderTypeTagify = new Tagify(orderTypeInput, {
                    whitelist: ['新規', '修正', '免震', '耐震', '計画変更'],
                    maxTags: 5,
                    dropdown: {
                        maxItems: 20,
                        classname: "tags-look-project-order-type",
                        enabled: 0,
                        closeOnSelect: true
                    }
                });
                
                // Update the model when tags change
                const updateOrderType = () => {
                    this.newChildProject.project_order_type = this.childProjectOrderTypeTagify.value.map(tag => tag.value).join(',');
                };
                this.childProjectOrderTypeTagify.on('add', updateOrderType);
                this.childProjectOrderTypeTagify.on('remove', updateOrderType);
            }
        },
        
        clearChildProjectTagifyTags(fieldName) {
            if (fieldName === 'project_order_type' && this.childProjectOrderTypeTagify) {
                this.childProjectOrderTypeTagify.removeAllTags();
                this.newChildProject.project_order_type = '';
            }
        },
        
        destroyChildProjectTagify() {
            if (this.childProjectOrderTypeTagify) {
                this.childProjectOrderTypeTagify.destroy();
                this.childProjectOrderTypeTagify = null;
            }
        },
        
        async showEditChildProjectModal(project) {
            this.editingChildProject = {
                id: project.id,
                name: project.name || '',
                department_id: project.department_id || '',
                project_number: project.project_number || '',
                description: project.description || '',
                start_date: this.formatDateTimeForInput(project.start_date) || '',
                end_date: this.formatDateTimeForInput(project.end_date) || '',
                project_order_type: project.project_order_type || '',
                parent_project_id: PARENT_PROJECT_ID,
                is_kadai: true,

            };
            

            
            this.loadDepartments();
            const modal = new bootstrap.Modal(document.getElementById('editChildProjectModal'));
            modal.show();
            
            this.$nextTick(() => {
                this.initializeEditChildProjectDatePickers();
                this.initializeEditChildProjectTagify();
            });
        },
        
        initializeEditChildProjectDatePickers() {
            // Initialize flatpickr for edit modal date pickers
            const startPicker = document.getElementById('edit_start_date_picker');
            const endPicker = document.getElementById('edit_end_date_picker');
            
            if (startPicker) {
                if (startPicker._flatpickr) {
                    startPicker._flatpickr.destroy();
                }
                startPicker._flatpickr = flatpickr(startPicker, {
                    enableTime: true,
                    dateFormat: 'Y/m/d H:i',
                    locale: 'ja',
                    time_24hr: true
                });
            }
            
            if (endPicker) {
                if (endPicker._flatpickr) {
                    endPicker._flatpickr.destroy();
                }
                endPicker._flatpickr = flatpickr(endPicker, {
                    enableTime: true,
                    dateFormat: 'Y/m/d H:i',
                    locale: 'ja',
                    time_24hr: true
                });
            }
        },
        
        initializeEditChildProjectTagify() {
            const orderTypeInput = document.querySelector('#edit_child_project_order_type');
            if (orderTypeInput) {
                // Destroy existing instance if it exists
                if (orderTypeInput.tagify) {
                    orderTypeInput.tagify.destroy();
                }
                
                this.editChildProjectOrderTypeTagify = new Tagify(orderTypeInput, {
                    whitelist: ['新規', '修正', '免震', '耐震', '計画変更'],
                    maxTags: 5,
                    dropdown: {
                        maxItems: 20,
                        classname: "tags-look-project-order-type",
                        enabled: 0,
                        closeOnSelect: true
                    }
                });
                
                // Update the model when tags change
                const updateOrderType = () => {
                    const tags = this.editChildProjectOrderTypeTagify.value.map(tag => tag.value).join(',');
                    this.editingChildProject.project_order_type = tags;
                };
                
                this.editChildProjectOrderTypeTagify.on('add', updateOrderType);
                this.editChildProjectOrderTypeTagify.on('remove', updateOrderType);
            }
        },
        
        clearEditChildProjectTagifyTags(fieldName) {
            if (fieldName === 'project_order_type' && this.editChildProjectOrderTypeTagify) {
                this.editChildProjectOrderTypeTagify.removeAllTags();
                this.editingChildProject.project_order_type = '';
            }
        },
        
        destroyEditChildProjectTagify() {
            if (this.editChildProjectOrderTypeTagify) {
                this.editChildProjectOrderTypeTagify.destroy();
                this.editChildProjectOrderTypeTagify = null;
            }
        },
        
        validateEditChildProjectForm() {
            this.editChildProjectValidationErrors = {
                name: '',
                department_id: '',
                project_number: '',
                start_date: '',
                end_date: ''
            };
            
            let isValid = true;
            
            if (!this.editingChildProject.name.trim()) {
                this.editChildProjectValidationErrors.name = '課題名は必須です。';
                isValid = false;
            }
            
            if (!this.editingChildProject.department_id) {
                this.editChildProjectValidationErrors.department_id = '部署は必須です。';
                isValid = false;
            }
            
            if (!this.editingChildProject.project_number.trim()) {
                this.editChildProjectValidationErrors.project_number = 'プロジェクト番号は必須です。';
                isValid = false;
            }
            
            if (!this.editingChildProject.start_date) {
                this.editChildProjectValidationErrors.start_date = '開始日は必須です。';
                isValid = false;
            }
            
            if (!this.editingChildProject.end_date) {
                this.editChildProjectValidationErrors.end_date = '期限日は必須です。';
                isValid = false;
            }
            
            return isValid;
        },
        
        async updateChildProject() {
            if (!this.validateEditChildProjectForm()) {
                return;
            }

            this.updatingChildProject = true;

            try {
                const formData = new FormData();
                formData.append('id', this.editingChildProject.id);
                formData.append('name', this.editingChildProject.name);
                formData.append('department_id', this.editingChildProject.department_id);
                formData.append('project_number', this.editingChildProject.project_number);
                formData.append('description', this.editingChildProject.description || '');
                formData.append('start_date', this.editingChildProject.start_date);
                formData.append('end_date', this.editingChildProject.end_date);
                formData.append('project_order_type', this.editingChildProject.project_order_type || '');
                formData.append('parent_project_id', this.editingChildProject.parent_project_id);

                formData.append('is_kadai', '1');

                const response = await axios.post('/api/index.php?model=project&method=update', formData);

                if (response.data.success) {
                    showMessage('課題が正常に更新されました。');
                    
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editChildProjectModal'));
                    if (modal) {
                        modal.hide();
                    }
                    
                    // Reload child projects
                    await this.loadChildProjects();
                    
                    // Reset form
                    this.editingChildProject = {
                        id: null,
                        name: '',
                        department_id: '',
                        project_number: '',
                        description: '',
                        start_date: '',
                        end_date: '',
                        project_order_type: '',
                        parent_project_id: PARENT_PROJECT_ID,
                        is_kadai: true,
        
                    };
                } else if (response.data && response.data.message === 'Project number already exists') {
                    this.editChildProjectValidationErrors.project_number = 'このプロジェクト番号は既に存在します。';
                } else {
                    showMessage(response.data.message || '課題の更新に失敗しました。', true);
                }
            } catch (error) {
                console.error('Error updating child project:', error);
                showMessage('課題の更新に失敗しました。', true);
            } finally {
                this.updatingChildProject = false;
            }
        },
        
        formatDateTimeForInput(dateTimeString) {
            if (!dateTimeString) return '';
            const date = new Date(dateTimeString);
            if (isNaN(date.getTime())) return '';
            
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            
            return `${year}/${month}/${day} ${hours}:${minutes}`;
        },



        async generateChildProjectNumber() {
            try {
                // Get parent project number and existing child projects count
                const parentProjectNumber = this.parentProject ? this.parentProject.project_number : '';
                const childCount = this.childProjects.length;
                let nextNumber = childCount + 1;
                
                // Check if the generated number already exists and find the next available number
                let childProjectNumber = '';
                if (parentProjectNumber) {
                    let attempts = 0;
                    const maxAttempts = 10; // Prevent infinite loop
                    
                    while (attempts < maxAttempts) {
                        childProjectNumber = `${parentProjectNumber}-${nextNumber.toString().padStart(2, '0')}`;
                        
                        // Check if this number already exists in child projects
                        const exists = this.childProjects.some(project => 
                            project.project_number === childProjectNumber
                        );
                        
                        if (!exists) {
                            break; // Found available number
                        }
                        
                        nextNumber++;
                        attempts++;
                    }
                }
                
                this.newChildProject.project_number = childProjectNumber;
            } catch (error) {
                console.error('Error generating child project number:', error);
                showMessage('プロジェクト番号の生成に失敗しました', true);
            }
        },

        validateChildProjectForm() {
            this.childProjectValidationErrors = {
                name: '',
                department_id: '',
                project_number: '',
                start_date: '',
                end_date: ''
            };

            let isValid = true;

            if (!this.newChildProject.name || this.newChildProject.name.trim() === '') {
                this.childProjectValidationErrors.name = '案件名は必須です';
                isValid = false;
            }

            // Check department_id - handle both string and number types
            const departmentId = this.newChildProject.department_id;
            if (!departmentId || departmentId === '' || departmentId === null || departmentId === undefined) {
                this.childProjectValidationErrors.department_id = '部署は必須です';
                isValid = false;
            }

            if (!this.newChildProject.project_number || this.newChildProject.project_number.trim() === '') {
                this.childProjectValidationErrors.project_number = 'プロジェクト番号は必須です';
                isValid = false;
            }

            if (!this.newChildProject.start_date || this.newChildProject.start_date.trim() === '') {
                this.childProjectValidationErrors.start_date = '開始日は必須です';
                isValid = false;
            }

            if (!this.newChildProject.end_date || this.newChildProject.end_date.trim() === '') {
                this.childProjectValidationErrors.end_date = '期限日は必須です';
                isValid = false;
            }

            return isValid;
        },

        async createChildProject() {
            if (!this.validateChildProjectForm()) {
                return;
            }

            this.creatingChildProject = true;

            try {
                const formData = new FormData();
                formData.append('name', this.newChildProject.name);
                formData.append('department_id', this.newChildProject.department_id);
                formData.append('project_number', this.newChildProject.project_number);
                formData.append('description', this.newChildProject.description || '');
                formData.append('start_date', this.newChildProject.start_date || '');
                formData.append('end_date', this.newChildProject.end_date || '');
                formData.append('project_order_type', this.newChildProject.project_order_type || '');
                formData.append('parent_project_id', this.newChildProject.parent_project_id);

                formData.append('is_kadai', '1');
                formData.append('status', 'draft');

                const response = await axios.post('/api/index.php?model=project&method=create', formData);

                if (response.data && (response.data.success || response.data.status === 'success')) {
                    Swal.fire({
                        title: '成功',
                        text: '課題を作成しました。',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        // Close modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('createChildProjectModal'));
                        if (modal) {
                            modal.hide();
                        }
                        
                        // Reload child projects
                        this.loadChildProjects();
                        
                        // Reset form
                        this.resetChildProjectForm();
                    });
                } else if (response.data && response.data.message === 'Project number already exists') {
                    this.childProjectValidationErrors.project_number = 'このプロジェクト番号は既に存在します。';
                } else {
                    showMessage(response.data?.error || response.data?.message || '課題の作成に失敗しました。', true);
                }
            } catch (error) {
                console.error('Error creating child project:', error);
                showMessage('課題の作成に失敗しました。', true);
            } finally {
                this.creatingChildProject = false;
            }
        },

        // Quotation methods
        async loadQuotations() {
            try {
                const response = await axios.get(`/api/index.php?model=quotation&method=getByParentProject&parent_project_id=${PARENT_PROJECT_ID}`);
                if (response.data && Array.isArray(response.data)) {
                    this.quotations = response.data;
                } else {
                    this.quotations = [];
                }
            } catch (error) {
                console.error('Error loading quotations:', error);
                this.quotations = [];
            }
        },

        async loadQuotationBranches() {
            try {
                const response = await axios.get('/api/index.php?model=branch&method=list');
                if (response.data && Array.isArray(response.data)) {
                    this.quotationBranches = response.data;
                    // Set default selection to first branch if available
                    if (this.quotationBranches.length > 0 && !this.newQuotation.selected_branch_id) {
                        this.newQuotation.selected_branch_id = this.quotationBranches[0].id;
                        this.onBranchSelect();
                    }
                } else {
                    this.quotationBranches = [];
                }
            } catch (error) {
                console.error('Error loading quotation branches:', error);
                this.quotationBranches = [];
            }
        },

        onBranchSelect() {
            if (this.newQuotation.selected_branch_id) {
                const selectedBranch = this.quotationBranches.find(branch => branch.id == this.newQuotation.selected_branch_id);
                if (selectedBranch) {
                    this.newQuotation.receiver_company = selectedBranch.company_name || selectedBranch.name;
                    // Include postal_code in the address field
                    const addressParts = [];
                    if (selectedBranch.postal_code) {
                        addressParts.push(`〒${selectedBranch.postal_code}`);
                    }
                    if (selectedBranch.address1) {
                        addressParts.push(selectedBranch.address1);
                    }
                    if (selectedBranch.address2) {
                        addressParts.push(selectedBranch.address2);
                    }
                    this.newQuotation.receiver_address = addressParts.join(' ');
                    this.newQuotation.receiver_tel = selectedBranch.tel || '';
                    this.newQuotation.receiver_fax = selectedBranch.fax || '';
                    this.newQuotation.receiver_registration_number = selectedBranch.registration_number || '';
                }
            } else {
                // Clear fields if no branch is selected
                this.newQuotation.receiver_company = '';
                this.newQuotation.receiver_address = '';
                this.newQuotation.receiver_tel = '';
                this.newQuotation.receiver_fax = '';
                this.newQuotation.receiver_registration_number = '';
            }
        },

            async loadQuotationUsers() {
        try {
            const response = await axios.get('/api/index.php?model=user&method=searchMembers');
            if (response.data && response.data.status === 'success') {
                this.quotationUsers = response.data.data || [];
                // Set default selection to current user if available
                if (this.quotationUsers.length > 0 && !this.newQuotation.receiver_contact) {
                    const currentUser = this.quotationUsers.find(user => user.userid === CURRENT_USER_ID);
                    if (currentUser) {
                        this.newQuotation.receiver_contact = currentUser.realname;
                        // Load seal for the default user
                        await this.loadContactSeal(currentUser.userid);
                    }
                }
            } else {
                this.quotationUsers = [];
            }
        } catch (error) {
            console.error('Error loading quotation users:', error);
            this.quotationUsers = [];
        }
    },

            async loadContactSeal(userId) {
        try {
            const response = await axios.get(`/api/index.php?model=seal&method=getSealsByUser&user_id=${userId}`);
            if (response.data && response.data.length > 0) {
                // Get the first active seal for this user
                this.selectedContactSeal = response.data[0];
            } else {
                this.selectedContactSeal = null;
            }
        } catch (error) {
            console.error('Error loading contact seal:', error);
            this.selectedContactSeal = null;
        }
    },

    async onContactSelect() {
        // Clear previous seal
        this.selectedContactSeal = null;
        
        if (!this.newQuotation.receiver_contact) {
            return;
        }
        
        // Find the selected user to get their userid
        const selectedUser = this.quotationUsers.find(user => user.realname === this.newQuotation.receiver_contact);
        if (!selectedUser) {
            return;
        }
        
        // Load seal for the selected user
        await this.loadContactSeal(selectedUser.userid);
    },

        showCreateQuotationModal() {
            // Restore previous form data if available, otherwise reset
            if (this.quotationFormBackup) {
                this.newQuotation = JSON.parse(JSON.stringify(this.quotationFormBackup));
            } else {
                this.resetQuotationForm();
            }
            const modal = new bootstrap.Modal(document.getElementById('createQuotationModal'));
            modal.show();
            
            // Initialize Flatpickr for quotation date fields after modal is shown
            this.$nextTick(() => {
                this.initializeQuotationDatePickers();
                // Load branches if not already loaded
                if (this.quotationBranches.length === 0) {
                    this.loadQuotationBranches();
                }
                // Load users if not already loaded
                if (this.quotationUsers.length === 0) {
                    this.loadQuotationUsers();
                }
            });
        },

        resetQuotationForm() {
            this.newQuotation = {
                issue_date: new Date().toISOString().split('T')[0],
                quotation_number: `GUIS-${Date.now()}`,
                sender_company: this.parentProject?.company_name || '',
                sender_address: '',
                sender_contact: this.parentProject?.contact_name || '',
                selected_branch_id: '',
                receiver_company: '',
                receiver_address: '',
                receiver_contact: CURRENT_USER_NAME || '',
                receiver_tel: '',
                receiver_fax: '',
                receiver_registration_number: '',
                items: [],
                total_amount: 0,
                tax_rate: 10,
                total_with_tax: 0,
                delivery_location: '',
                payment_method: '',
                valid_until: '',
                notes: '',
                parent_project_id: PARENT_PROJECT_ID
            };
            this.selectedContactSeal = null;
            this.addOrderItem(); // Add one empty item by default
            // Load branches and set default selection
            this.loadQuotationBranches();
            // Load users and set default contact
            this.loadQuotationUsers();
        },

        addOrderItem() {
            this.newQuotation.items.push({
                title: '',
                product_code: '',
                product_name: '',
                quantity: 1,
                unit: '個',
                unit_price: 0,
                amount: 0,
                notes: ''
            });
        },

        removeOrderItem(index) {
            this.newQuotation.items.splice(index, 1);
            this.calculateTotalAmount();
        },

        calculateItemAmount(index) {
            const item = this.newQuotation.items[index];
            item.amount = (item.quantity || 0) * (item.unit_price || 0);
            this.calculateTotalAmount();
        },

        calculateTotalAmount() {
            this.newQuotation.total_amount = this.newQuotation.items.reduce((sum, item) => sum + (item.amount || 0), 0);
            this.newQuotation.total_with_tax = this.newQuotation.total_amount * (1 + (this.newQuotation.tax_rate || 0) / 100);
        },

        async createQuotation() {
            if (!this.validateQuotationForm()) {
                return;
            }

            this.creatingQuotation = true;

            try {
                const formData = new FormData();
                
                // Add quotation data
                Object.keys(this.newQuotation).forEach(key => {
                    if (key === 'items') {
                        formData.append(key, JSON.stringify(this.newQuotation[key]));
                    } else if (this.newQuotation[key] !== null && this.newQuotation[key] !== '') {
                        formData.append(key, this.newQuotation[key]);
                    }
                });

                const response = await axios.post('/api/index.php?model=quotation&method=create', formData);
                
                if (response.data && response.data.status === 'success') {
                    // Clear backup and close modal
                    this.quotationFormBackup = null;
                    const modal = bootstrap.Modal.getInstance(document.getElementById('createQuotationModal'));
                    modal.hide();
                    
                    // Reload quotations
                    await this.loadQuotations();
                    
                    showMessage('見積書を作成しました', false);
                } else {
                    showMessage(response.data?.message || 'エラーが発生しました', true);
                }
            } catch (error) {
                console.error('Error creating quotation:', error);
                showMessage('エラーが発生しました', true);
            } finally {
                this.creatingQuotation = false;
            }
        },

        backupQuotationForm() {
            // Backup current form data before closing
            this.quotationFormBackup = JSON.parse(JSON.stringify(this.newQuotation));
        },

        clearQuotationFormBackup() {
            // Clear backup and reset form
            this.quotationFormBackup = null;
            this.resetQuotationForm();
        },
        
        destroyQuotationDatePickers() {
            // Destroy Flatpickr instances for quotation date fields
            const issueDateEl = document.getElementById('quotation_issue_date');
            if (issueDateEl && issueDateEl._flatpickr) {
                issueDateEl._flatpickr.destroy();
            }
            
            const validUntilEl = document.getElementById('quotation_valid_until');
            if (validUntilEl && validUntilEl._flatpickr) {
                validUntilEl._flatpickr.destroy();
            }
        },

        validateQuotationForm() {
            let isValid = true;
            
            if (!this.newQuotation.issue_date) {
                showMessage('発行日は必須です', true);
                isValid = false;
            }
            
            if (!this.newQuotation.quotation_number) {
                showMessage('見積番号は必須です', true);
                isValid = false;
            }
            
            if (!this.newQuotation.sender_company) {
                showMessage('発注者会社名は必須です', true);
                isValid = false;
            }
            
            if (!this.newQuotation.receiver_company) {
                showMessage('受注者会社名は必須です', true);
                isValid = false;
            }
            
            if (this.newQuotation.items.length === 0) {
                showMessage('商品明細は必須です', true);
                isValid = false;
            }
            
            return isValid;
        },

        showQuotationModal(quotation) {
            this.selectedQuotation = quotation;
            const modal = new bootstrap.Modal(document.getElementById('viewQuotationModal'));
            modal.show();
        },

        editQuotation(quotation) {
            // TODO: Implement edit functionality
            showMessage('編集機能は準備中です', true);
        },

        async deleteQuotation(quotation) {
            try {
                const result = await Swal.fire({
                    title: '確認',
                    text: `「${quotation.quotation_number}」を削除しますか？`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: '削除',
                    cancelButtonText: 'キャンセル'
                });
                
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('id', quotation.id);
                    
                    const response = await axios.post('/api/index.php?model=quotation&method=delete', formData);
                    
                    if (response.data && response.data.status === 'success') {
                        await this.loadQuotations();
                        showMessage('見積書を削除しました', false);
                    } else {
                        showMessage(response.data?.message || 'エラーが発生しました', true);
                    }
                }
            } catch (error) {
                console.error('Error deleting quotation:', error);
                showMessage('エラーが発生しました', true);
            }
        },

        printQuotation() {
            window.print();
        },

        formatJapaneseDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            const year = date.getFullYear();
            const month = date.getMonth() + 1;
            const day = date.getDate();
            
            // Convert to Japanese era (Reiwa)
            const reiwaYear = year - 2018;
            return `令和${reiwaYear}年${month}月${day}日`;
        },

        formatNumber(number) {
            return new Intl.NumberFormat('ja-JP').format(number);
        },

        formatPrice(price) {
            return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(price);
        },
        
        // Price list methods
        async loadPriceListData() {
            try {
                const [productsResponse, departmentsResponse] = await Promise.all([
                    axios.get('/api/index.php?model=pricelist&method=getAllProducts'),
                    axios.get('/api/index.php?model=department&method=getAll')
                ]);
                
                if (productsResponse.data && departmentsResponse.data) {
                    this.priceListProducts = productsResponse.data;
                    this.priceListDepartments = departmentsResponse.data;
                    this.filterPriceListProducts();
                }
            } catch (error) {
                console.error('Error loading price list data:', error);
            }
        },
        
        filterPriceListProducts() {
            let filtered = [...this.priceListProducts];
            
            // Filter by department
            if (this.selectedPriceListDepartment) {
                filtered = filtered.filter(product => 
                    (product.department_id || '') == this.selectedPriceListDepartment
                );
            }
            
            // Filter by search term
            if (this.priceListSearchTerm) {
                const term = this.priceListSearchTerm.toLowerCase();
                filtered = filtered.filter(product => 
                    (product.code || '').toLowerCase().includes(term) ||
                    (product.name || '').toLowerCase().includes(term)
                );
            }
            
            this.filteredPriceListProducts = filtered;
        },
        
        showPriceListModal() {
            if (this.priceListProducts.length === 0) {
                this.loadPriceListData();
            }
            this.priceListModal.show();
        },
        
        selectPriceListProduct(product) {
            // Add the selected product as a new order item
            const newItem = {
                title: product.name,
                product_code: product.code,
                product_name: product.name,
                quantity: 1,
                unit: product.unit,
                unit_price: product.price,
                amount: product.price,
                notes: product.notes || ''
            };
            
            this.newQuotation.items.push(newItem);
            this.calculateTotalAmount();
            
            // Close the modal
            this.priceListModal.hide();
            
            // Reset filters
            this.selectedPriceListDepartment = '';
            this.priceListSearchTerm = '';
            this.filterPriceListProducts();
        }
    },
    async mounted() {
        try {
            await this.loadParentProject();
            await this.loadChildProjects();
            await this.loadQuotations();
            
            // Initialize price list modal
            this.priceListModal = new bootstrap.Modal(document.getElementById('priceListModal'));
            
            // Add event listeners for modal close events
            const createQuotationModal = document.getElementById('createQuotationModal');
            if (createQuotationModal) {
                createQuotationModal.addEventListener('hidden.bs.modal', () => {
                    // Backup form data when modal is closed
                    this.backupQuotationForm();
                    // Destroy Flatpickr instances
                    this.destroyQuotationDatePickers();
                });
            }
        } catch (error) {
            console.error('Error in mounted:', error);
        } finally {
            this.loading = false;
        }
    }
}).mount('#app'); 