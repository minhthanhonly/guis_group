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
                { value: 'quoted', label: '已报价', color: 'info' },
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
            quotationValidationErrors: {}, // Add field-level validation errors
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
                status: '下書き',
                items: [],
                total_amount: 0,
                tax_rate: 10,
                total_with_tax: 0,
                delivery_date: '御打ち合わせの上',
                delivery_location: '貴社指定場所',
                payment_method: '電子納品',
                valid_until_type: '1_month',
                valid_until: '',
                notes: '',
                parent_project_id: PARENT_PROJECT_ID
            },
            selectedQuotation: null,
            creatingQuotation: false,
            quotationFormBackup: null,
            // Child project selection for quotation
            selectedChildProjectIds: [],
            allChildProjectsSelected: false,
            someChildProjectsSelected: false,
            // Price list data
            priceListProducts: [],
            filteredPriceListProducts: [],
            selectedPriceListType: '',
            priceListSearchTerm: '',
            priceListModal: null,
            // Multiple product selection
            selectedProducts: [],
            allSelected: false,
            // Price list pagination and UX
            priceListPage: 1,
            priceListPageSize: 50,
            highlightedIndex: 0,
            lastSelectedIndexGlobal: null,
            filterDebounceTimer: null,
            // Quantities per selected product id
            selectedProductQuantities: {},
            // Set editing state
            editingSet: null,
            setEditModal: null,
            // Optional custom name for the selected set
            selectedSetName: '',
            // Order items selection (for bulk delete)
            selectedOrderItemIndexes: []
        }
    },
    watch: {
        'newQuotation.items': {
            handler(newItems, oldItems) {
                // Ensure product_code is empty for set products
                if (newItems && Array.isArray(newItems)) {
                    newItems.forEach(item => {
                        if (item && item.is_set && item.product_code && item.product_code.trim() !== '') {
                            item.product_code = '';
                        }
                    });
                }
                
                // Auto-validate project_id selection
                this.validateProjectIdSelection();
            },
            deep: true
        },
        'quotationValidationErrors': {
            handler(newErrors, oldErrors) {
                // Validation errors changed
            },
            deep: true
        },
        'childProjects': {
            handler(newProjects, oldProjects) {
                // Ensure amounts are properly formatted and displayed
                if (newProjects && Array.isArray(newProjects)) {
                    newProjects.forEach(project => {
                        // Ensure amount field exists and is properly formatted
                        if (project && typeof project.amount === 'undefined') {
                            project.amount = project.total_amount || 0;
                        }
                    });
                }
            },
            deep: true
        }
    },
    computed: {
        paginatedPriceListProducts() {
            const startIndex = (this.priceListPage - 1) * this.priceListPageSize;
            const endIndex = startIndex + this.priceListPageSize;
            return this.filteredPriceListProducts.slice(startIndex, endIndex);
        },
        totalPriceListPages() {
            const total = Math.ceil((this.filteredPriceListProducts.length || 0) / (this.priceListPageSize || 1));
            return Math.max(total, 1);
        },
        defaultSetName() {
            const count = this.selectedProducts.length || 0;
            return `商品セット (${count}件)`;
        },
        displayedSetName: {
            get() {
                return (this.selectedSetName && this.selectedSetName.trim()) ? this.selectedSetName : this.defaultSetName;
            },
            set(value) {
                this.selectedSetName = value;
            }
        },
        alreadyAddedProductIds() {
            const idSet = new Set();
            const items = this.newQuotation?.items || [];
            
            for (const item of items) {
                if (item && item.is_set && item.set_json) {
                    let products = [];
                    
                    // Handle both object and JSON string cases
                    if (typeof item.set_json === 'object' && item.set_json !== null) {
                        // set_json is already an object
                        products = Array.isArray(item.set_json) ? item.set_json : [];
                    } else if (typeof item.set_json === 'string') {
                        // set_json is a JSON string, try to parse it
                        try {
                            const parsed = JSON.parse(item.set_json);
                            products = Array.isArray(parsed) ? parsed : [];
                        } catch (e) {
                            console.error('Error parsing set_json in alreadyAddedProductIds:', e);
                            products = [];
                        }
                    }
                    
                    if (Array.isArray(products)) {
                        products.forEach(p => { 
                            if (p && p.id != null) idSet.add(p.id); 
                        });
                    }
                } else if (item && item.product_id != null) {
                    idSet.add(item.product_id);
                }
            }
            
            return idSet;
        },
        allOrderItemsSelected() {
            const total = this.newQuotation?.items?.length || 0;
            const selected = this.selectedOrderItemIndexes.length;
            return total > 0 && selected === total;
        },
        selectedChildProjectsForDropdown() {
            // Filter child projects to only show the selected ones for the dropdown
            return this.childProjects.filter(project => 
                this.selectedChildProjectIds.includes(project.id)
            );
        },
        hasSetProducts() {
            // Check if any items in newQuotation are sets
            return this.newQuotation?.items?.some(item => item.is_set) || false;
        },
        hasSetProductsInView() {
            // Check if any items in selectedQuotation are sets
            return this.selectedQuotation?.items?.some(item => item.is_set) || false;
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
                            // Auto-calculate valid_until if not custom
                            if (this.newQuotation.valid_until_type !== 'custom') {
                                this.onValidUntilTypeChange();
                            }
                        }
                    });
                    
                    // Set initial date if available
                    if (this.newQuotation.issue_date) {
                        issueDateEl._flatpickr.setDate(this.newQuotation.issue_date);
                    }
                }
                
                // Initialize delivery date picker
                const deliveryDateEl = document.getElementById('quotation_delivery_date');
                if (deliveryDateEl) {
                    if (deliveryDateEl._flatpickr) {
                        deliveryDateEl._flatpickr.destroy();
                    }
                    deliveryDateEl._flatpickr = flatpickr(deliveryDateEl, {
                        dateFormat: 'Y-m-d',
                        locale: 'ja',
                        allowInput: true,
                        clickOpens: true,
                        onChange: (selectedDates, dateStr) => {
                            this.newQuotation.delivery_date = dateStr;
                        },
                        onClose: (selectedDates, dateStr, instance) => {
                            // If no date is selected, set to default value
                            if (!dateStr || dateStr === '') {
                                this.newQuotation.delivery_date = '御打ち合わせの上';
                            }
                        }
                    });
                    
                    // Set initial date if available and it's a valid date
                    if (this.newQuotation.delivery_date && this.newQuotation.delivery_date !== '御打ち合わせの上') {
                        try {
                            const date = new Date(this.newQuotation.delivery_date);
                            if (!isNaN(date.getTime())) {
                                deliveryDateEl._flatpickr.setDate(this.newQuotation.delivery_date);
                            }
                        } catch (e) {
                            // Ignore invalid date errors
                        }
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
            // Clear any existing validation errors
            this.quotationValidationErrors = {};
            
            if (this.quotationFormBackup) {
                // Restore previous form data if available, otherwise reset
                this.newQuotation = JSON.parse(JSON.stringify(this.quotationFormBackup));
                
                // Ensure all items have _oldProjectId property initialized and product_code is empty for set products
                if (this.newQuotation.items && this.newQuotation.items.length > 0) {
                    this.newQuotation.items.forEach(item => {
                        if (!item.hasOwnProperty('_oldProjectId')) {
                            item._oldProjectId = item.project_id || '';
                        }
                        // Ensure product_code is empty for set products
                        if (item.is_set && item.product_code && item.product_code.trim() !== '') {
                            item.product_code = '';
                        }
                    });
                }
                
                // Restore child project selection
                if (this.quotationFormBackup.selectedChildProjectIds) {
                    this.selectedChildProjectIds = [...this.quotationFormBackup.selectedChildProjectIds];
                    this.updateChildProjectSelection();
                }
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

        generateQuotationNumber() {
            // Generate quotation number in format G-1A2C3D
            // G-4A2C3D where:
            // 4 = last digit of year (2024 → 4)
            // A = month as letter (1=A, 2=B, 3=C, ..., 12=L)
            // 2 = day (01-31)
            // C = hour as letter (0=A, 1=B, 2=C, ..., 23=X)
            // 3 = minute (00-59)
            // D = second (00-59)
            const now = new Date();
            const year = now.getFullYear().toString().slice(-2); // Last digit of year
            const month = (now.getMonth() + 1).toString().padStart(2, '0');
            const day = now.getDate().toString().padStart(2, '0');
            const hour = now.getHours().toString().padStart(2, '0');
            const minute = now.getMinutes().toString().padStart(2, '0');
            const second = now.getSeconds().toString().padStart(2, '0');
            
            // Convert numbers to letters (1=A, 2=B, 3=C, etc.)
            const monthLetter = String.fromCharCode(64 + parseInt(month)); // 1=A, 2=B, 3=C, etc.
            const dayLetter = String.fromCharCode(64 + parseInt(day)); // 1=A, 2=B, 3=C, etc.
            const hourLetter = String.fromCharCode(65 + parseInt(hour)); // 0=A, 1=B, 2=C, etc.
            
            return `G-${year}${monthLetter}${dayLetter}${hourLetter}${minute}${second}`;
        },

        resetQuotationForm() {
            this.newQuotation = {
                issue_date: new Date().toISOString().split('T')[0],
                quotation_number: this.generateQuotationNumber(),
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
                status: '下書き',
                items: [], // Ensure this is always an empty array
                total_amount: 0,
                tax_rate: 10, // Initialized
                total_with_tax: 0,
                delivery_date: '御打ち合わせの上', // Set default value
                delivery_location: '貴社指定場所', // Initialized
                payment_method: '電子納品', // Initialized
                valid_until_type: '1_month', // Initialized
                valid_until: '',
                notes: '',
                parent_project_id: PARENT_PROJECT_ID
            };
            this.selectedContactSeal = null;
            this.quotationValidationErrors = {}; // Clear validation errors
            
            // Reset child project selection
            this.selectedChildProjectIds = [];
            this.allChildProjectsSelected = false;
            this.someChildProjectsSelected = false;
            
            // Reset child project total amounts
            this.childProjects.forEach(project => {
                project.total_amount = 0;
            });
            
            this.loadQuotationBranches();
            this.loadQuotationUsers();
            this.onValidUntilTypeChange(); // Calculate initial valid_until date
        },

        addOrderItem() {
            const newItem = {
                project_id: '', // Add project_id field
                _oldProjectId: '', // Track previous project_id for proper total calculation
                title: '',
                product_code: '',
                quantity: 1,
                unit: '枚',
                unit_price: 0,
                amount: 0,
                notes: ''
            };
            
            // Use spread operator to ensure reactivity
            this.newQuotation.items = [...this.newQuotation.items, newItem];
            
            // Validate project_id selection after adding new item
            this.$nextTick(() => {
                this.validateProjectIdSelection();
            });
        },

        quickSelectProjectNumber(projectId) {
            // Find the first order item that doesn't have a project_id set
            const firstUnassignedItem = this.newQuotation.items.find(item => !item.project_id);
            
            if (firstUnassignedItem) {
                // Set the project_id for the first unassigned item
                firstUnassignedItem.project_id = projectId;
                firstUnassignedItem._oldProjectId = projectId;
                
                // Update the project total amount
                this.updateChildProjectTotalAmount(projectId);
                
                // Calculate the item amount
                const itemIndex = this.newQuotation.items.indexOf(firstUnassignedItem);
                if (itemIndex !== -1) {
                    this.calculateItemAmount(itemIndex);
                }
                
                showMessage(`プロジェクト番号 "${this.getProjectDisplayName(projectId)}" が設定されました。`, false);
                // Validate project_id selection after update
                this.validateProjectIdSelection();
            } else {
                // If all items have project_id, show a message
                showMessage('すべての商品明細にプロジェクト番号が設定されています。新しい商品明細を追加してください。', true);
            }
        },

        quickSelectProjectNumberForCheckedItems(projectId) {
            // Check if there are any selected items
            if (this.selectedOrderItemIndexes.length === 0) {
                showMessage('チェックされた商品がありません。先に商品を選択してください。', true);
                return;
            }

            let updatedCount = 0;
            const projectDisplayName = this.getProjectDisplayName(projectId);

            // Update only the checked items
            this.selectedOrderItemIndexes.forEach(index => {
                if (index >= 0 && index < this.newQuotation.items.length) {
                    const item = this.newQuotation.items[index];
                    const oldProjectId = item.project_id;
                    
                    // Set the project_id for the checked item
                    item.project_id = projectId;
                    item._oldProjectId = projectId;
                    
                    // Update the project total amount
                    this.updateChildProjectTotalAmount(projectId);
                    
                    // If the item had a different project_id before, update that project's total too
                    if (oldProjectId && oldProjectId !== projectId) {
                        this.updateChildProjectTotalAmount(oldProjectId);
                    }
                    
                    // Calculate the item amount
                    this.calculateItemAmount(index);
                    
                    updatedCount++;
                }
            });

            if (updatedCount > 0) {
                showMessage(`プロジェクト番号 "${projectDisplayName}" が ${updatedCount} 件のチェック済み商品に設定されました。`, false);
                // Validate project_id selection after update
                this.validateProjectIdSelection();
            } else {
                showMessage('チェックされた商品の更新に失敗しました。', true);
            }
        },

        clearProjectNumbersForCheckedItems() {
            // Check if there are any selected items
            if (this.selectedOrderItemIndexes.length === 0) {
                showMessage('チェックされた商品がありません。先に商品を選択してください。', true);
                return;
            }

            let updatedCount = 0;

            // Clear project_id for all checked items
            this.selectedOrderItemIndexes.forEach(index => {
                if (index >= 0 && index < this.newQuotation.items.length) {
                    const item = this.newQuotation.items[index];
                    const oldProjectId = item.project_id;
                    
                    // Clear the project_id for the checked item
                    item.project_id = '';
                    item._oldProjectId = '';
                    
                    // If the item had a project_id before, update that project's total
                    if (oldProjectId) {
                        this.updateChildProjectTotalAmount(oldProjectId);
                    }
                    
                    updatedCount++;
                }
            });

            if (updatedCount > 0) {
                showMessage(`${updatedCount} 件のチェック済み商品のプロジェクト番号がクリアされました。`, false);
                // Validate project_id selection after clearing
                this.validateProjectIdSelection();
            } else {
                showMessage('チェックされた商品の更新に失敗しました。', true);
            }
        },

        getProjectDisplayName(projectId) {
            const project = this.childProjects.find(p => p.id == projectId);
            return project ? (project.project_number || project.name) : `ID: ${projectId}`;
        },

        removeOrderItem(index) {
            const itemToRemove = this.newQuotation.items[index];
            const projectId = itemToRemove ? itemToRemove.project_id : null;
            
            // Use spread operator to ensure reactivity
            this.newQuotation.items = this.newQuotation.items.filter((_, i) => i !== index);
            
            this.calculateTotalAmount();
            
            // Update project total amount if the removed item had a project_id
            if (projectId) {
                this.updateChildProjectTotalAmount(projectId);
            }
            
            // Clean up selection after removal
            this.selectedOrderItemIndexes = this.selectedOrderItemIndexes
                .filter(i => i !== index)
                .map(i => (i > index ? i - 1 : i));
        },

        calculateItemAmount(index) {
            const item = this.newQuotation.items[index];
            
            if (item) {
                const quantity = item.quantity || 0;
                const unitPrice = item.unit_price || 0;
                const amount = quantity * unitPrice;
                
                item.amount = amount;
                
                // Update project total amount if project_id is set
                if (item.project_id) {
                    this.updateChildProjectTotalAmount(item.project_id);
                }
            }
            
            this.calculateTotalAmount();
        },

        calculateTotalAmount() {
            const total = this.newQuotation.items.reduce((sum, item) => {
                const amount = item.amount || 0;
                return sum + amount;
            }, 0);
            
            this.newQuotation.total_amount = total;
            this.newQuotation.total_with_tax = Math.floor(this.newQuotation.total_amount * (1 + (this.newQuotation.tax_rate || 0) / 100));
        },

        onValidUntilTypeChange() {
            const issueDate = new Date(this.newQuotation.issue_date);
            let validUntilDate = new Date(issueDate);
            
            switch (this.newQuotation.valid_until_type) {
                case '1_week':
                    validUntilDate.setDate(issueDate.getDate() + 7);
                    break;
                case '1_month':
                    validUntilDate.setMonth(issueDate.getMonth() + 1);
                    break;
                case 'custom':
                    // Keep the existing valid_until value if it exists
                    if (!this.newQuotation.valid_until) {
                        this.newQuotation.valid_until = '';
                    }
                    return; // Don't update the date for custom
                default:
                    return;
            }
            
            this.newQuotation.valid_until = validUntilDate.toISOString().split('T')[0];
        },



        openDeliveryDatePicker() {
            const deliveryDateEl = document.getElementById('quotation_delivery_date');
            if (deliveryDateEl && deliveryDateEl._flatpickr) {
                deliveryDateEl._flatpickr.open();
            }
        },

        clearDeliveryDate() {
            this.newQuotation.delivery_date = '御打ち合わせの上';
        },

        onDeliveryDateInput(event) {
            const value = event.target.value;
            // If the user clears the field, set it to default value
            if (!value || value.trim() === '') {
                this.newQuotation.delivery_date = '御打ち合わせの上';
            }
        },

        checkItemsReady() {
            if (!this.newQuotation.items || !Array.isArray(this.newQuotation.items) || this.newQuotation.items.length === 0) {
                showMessage('商品明細が追加されていません。先に商品を選択してください。', true);
                return false;
            }
            
            return true;
        },

        async createQuotationWithDelay() {
            // Add a small delay to ensure Vue.js has updated the array
            await new Promise(resolve => setTimeout(resolve, 200));
            
            // Now call the original createQuotation function
            await this.createQuotation();
        },

        async createQuotation() {
            // Check if items are ready before proceeding
            if (!this.checkItemsReady()) {
                return;
            }
            
            if (!this.validateQuotationForm()) {
                return;
            }

            this.creatingQuotation = true;
            // Clear previous validation errors
            this.quotationValidationErrors = {};

            try {
                const formData = new FormData();
                // Add quotation data - send all fields including empty ones
                Object.keys(this.newQuotation).forEach(key => {
                    if (key === 'items') {
                        // Create a deep copy of items and properly handle set_json
                        const itemsCopy = this.newQuotation[key].map(item => {
                            if (item.is_set && item.set_json) {
                                // Ensure set_json is properly handled
                                const itemCopy = { ...item };
                                
                                if (typeof item.set_json === 'object') {
                                    // If object, stringify it
                                    itemCopy.set_json = JSON.stringify(item.set_json);
                                } else if (typeof item.set_json === 'string') {
                                    // If already a string, validate and use as is
                                    try {
                                        JSON.parse(item.set_json); // Validate it's valid JSON
                                        itemCopy.set_json = item.set_json;
                                    } catch (e) {
                                        // Try to re-stringify if it's invalid
                                        itemCopy.set_json = JSON.stringify(item.set_json);
                                    }
                                } else {
                                    // Unknown type, stringify it
                                    itemCopy.set_json = JSON.stringify(item.set_json);
                                }
                                return itemCopy;
                            }
                            return item;
                        });
                        
                        const itemsJson = JSON.stringify(itemsCopy);
                        formData.append(key, itemsJson);
                        
                        // Debug: Check if items JSON is valid
                        try {
                            const parsed = JSON.parse(itemsJson);
                        } catch (e) {
                            console.error('Items JSON is invalid:', e);
                        }
                    } else {
                        // Send all fields, including empty strings and null values
                        const value = this.newQuotation[key] !== null ? this.newQuotation[key] : '';
                        formData.append(key, value);
                    }
                });
                
                // Debug: Check if items is actually in FormData
                const itemsEntry = formData.get('items');
                
                // Debug: Try to parse items back to see if it's valid JSON
                try {
                    const parsedItems = JSON.parse(itemsEntry);
                } catch (parseError) {
                    console.error('Error parsing items from FormData:', parseError);
                }
              
                // Debug: Check if FormData has the expected content
                const formDataArray = Array.from(formData.entries());
                
                const response = await axios.post('/api/index.php?model=quotation&method=create', formData);
                
                if (response.data && response.data.status === 'success') {
                    // Clear backup and close modal
                    this.quotationFormBackup = null;
                    const modal = bootstrap.Modal.getInstance(document.getElementById('createQuotationModal'));
                    modal.hide();
                    
                    // Update selected child projects status based on quotation status
                    if (this.selectedChildProjectIds.length > 0) {
                        await this.updateSelectedChildProjectsStatus();
                    }
                    
                    // Update child project amounts based on quotation items
                    await this.updateChildProjectAmountsAfterQuotation();
                    
                    // Reload child projects to show updated amounts
                    await this.loadChildProjects();
                    
                    // Reload quotations
                    await this.loadQuotations();
                    
                    // Reset the quotation form after successful creation
                    this.resetQuotationForm();
                    
                    showMessage('見積書を作成しました', false);
                } else {
                    // Handle field-level validation errors
                    if (response.data && response.data.errors) {
                        this.quotationValidationErrors = response.data.errors;
                    } else {
                        showMessage(response.data?.message || 'エラーが発生しました', true);
                    }
                }
            } catch (error) {
                console.error('Error creating quotation:', error);
                if (error.response) {
                    console.error('Error response data:', error.response.data);
                }
                showMessage('エラーが発生しました', true);
            } finally {
                this.creatingQuotation = false;
            }
        },

        backupQuotationForm() {
            // Backup current form data before closing
            this.quotationFormBackup = JSON.parse(JSON.stringify(this.newQuotation));
            // Also backup child project selection
            this.quotationFormBackup.selectedChildProjectIds = [...this.selectedChildProjectIds];
        },

        clearQuotationFormBackup() {
            // Clear backup and reset form
            this.quotationFormBackup = null;
            this.resetQuotationForm();
        },
        
        destroyQuotationDatePickers() {
            // Destroy issue date picker
            const issueDateEl = document.getElementById('quotation_issue_date');
            if (issueDateEl && issueDateEl._flatpickr) {
                issueDateEl._flatpickr.destroy();
            }
            
            // Destroy delivery date picker
            const deliveryDateEl = document.getElementById('quotation_delivery_date');
            if (deliveryDateEl && deliveryDateEl._flatpickr) {
                deliveryDateEl._flatpickr.destroy();
            }
            
            // Destroy valid until date picker
            const validUntilEl = document.getElementById('quotation_valid_until');
            if (validUntilEl && validUntilEl._flatpickr) {
                validUntilEl._flatpickr.destroy();
            }
        },

        validateQuotationForm() {
            let isValid = true;
            
            // Clear previous validation errors
            this.quotationValidationErrors = {};
            
            if (!this.newQuotation.issue_date) {
                this.quotationValidationErrors.issue_date = '発行日は必須です';
                isValid = false;
            }
            
            if (!this.newQuotation.quotation_number) {
                this.quotationValidationErrors.quotation_number = '見積番号は必須です';
                isValid = false;
            }
            
            if (!this.newQuotation.sender_company) {
                this.quotationValidationErrors.sender_company = '発注者会社名は必須です';
                isValid = false;
            }
            
            if (!this.newQuotation.receiver_company) {
                this.quotationValidationErrors.receiver_company = '受注者会社名は必須です';
                isValid = false;
            }
            
            // Improved items validation
            if (!this.newQuotation.items || !Array.isArray(this.newQuotation.items) || this.newQuotation.items.length === 0) {
                this.quotationValidationErrors.items = '商品明細は必須です';
                isValid = false;
            } else {
                // Validate that all items have project_id selected
                const itemsWithoutProject = this.newQuotation.items.filter(item => !item.project_id || item.project_id.trim() === '');
                if (itemsWithoutProject.length > 0) {
                    this.quotationValidationErrors.items = `${itemsWithoutProject.length}件の商品にプロジェクト番号が選択されていません`;
                    isValid = false;
                }
            }
            
            // Validate child project selection
            if (!this.selectedChildProjectIds || this.selectedChildProjectIds.length === 0) {
                this.quotationValidationErrors.childProjects = '子プロジェクトの選択は必須です';
                isValid = false;
            }
            
            return isValid;
        },

        validateProjectIdSelection() {
            // Clear previous items validation error
            if (this.quotationValidationErrors.items && this.quotationValidationErrors.items.includes('プロジェクト番号が選択されていません')) {
                delete this.quotationValidationErrors.items;
            }
            
            // Check if all items have project_id selected
            if (this.newQuotation.items && Array.isArray(this.newQuotation.items) && this.newQuotation.items.length > 0) {
                const itemsWithoutProject = this.newQuotation.items.filter(item => !item.project_id || item.project_id.trim() === '');
                if (itemsWithoutProject.length > 0) {
                    this.quotationValidationErrors.items = `${itemsWithoutProject.length}件の商品にプロジェクト番号が選択されていません`;
                }
            }
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
                    // Update child project amounts after deletion (reset to 0 for affected projects)
                    await this.resetChildProjectAmountsAfterQuotationDeletion(quotation);
                    
                    // Reload child projects to show updated amounts
                    await this.loadChildProjects();
                    
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
            // Take only the integer part of the number
            const integerNumber = Math.floor(number);
            return new Intl.NumberFormat('ja-JP').format(integerNumber);
        },

        // Update quotation status
        async updateQuotationStatus(quotationId, status) {
            try {
                const formData = new FormData();    
                formData.append('quotation_id', quotationId);
                formData.append('status', status);
                const response = await axios.post('/api/index.php?model=quotation&method=updateStatus', formData);
                
                if (response.data && response.data.status === 'success') {
                    // Update the local quotation data
                    const quotation = this.quotations.find(q => q.id === quotationId);
                    if (quotation) {
                        quotation.status = status;
                    }
                    
                    // Reload quotations to get updated data
                    await this.loadQuotations();
                    
                    // Update child project amounts if status change affects project totals
                    if (status === '承認済み' || status === '発行済み') {
                        await this.updateChildProjectAmountsFromQuotation(quotationId);
                        // Reload child projects to show updated amounts
                        await this.loadChildProjects();
                    }
                    
                    showMessage('見積書のステータスが更新されました。', false);
                } else {
                    showMessage('ステータスの更新に失敗しました。', true);
                }
            } catch (error) {
                console.error('Error updating quotation status:', error);
                showMessage('ステータスの更新中にエラーが発生しました。', true);
            }
        },

        // Get CSS class for quotation status badge
        getQuotationStatusBadgeClass(status) {
            switch (status) {
                case '下書き':
                    return 'bg-draft';
                case '発行済み':
                    return 'bg-issued';
                case '承認済み':
                    return 'bg-approved';
                case '却下':
                    return 'bg-rejected';
                case '調整':
                    return 'bg-adjustment';
                default:
                    return 'bg-draft';
            }
        },

        formatPrice(price) {
            // Take only the integer part of the price
            const integerPrice = Math.floor(price);
            return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(integerPrice);
        },
        
        // Price list methods
        async loadPriceListData() {
            try {
                const productsResponse = await axios.get('/api/index.php?model=pricelist&method=getAllProducts');
                
                if (productsResponse.data) {
                    this.priceListProducts = productsResponse.data;
                    
                    this.filterPriceListProducts();
                } else {
                    console.warn('No data received from API');
                }
            } catch (error) {
                console.error('Error loading price list data:', error);
                console.error('Response:', error.response);
                // Add some sample data for testing if API fails
                this.priceListProducts = [
                    { id: 1, code: '100', name: 'サンプル商品1', type: '新規', unit: '枚', price: 1000, cost: 800 },
                    { id: 2, code: '101', name: 'サンプル商品2', type: '修正', unit: '枚', price: 2000, cost: 1600 },
                    { id: 3, code: '102', name: 'サンプル商品3', type: 'その他', unit: '枚', price: 1500, cost: 1200 }
                ];
                this.filterPriceListProducts();
            }
        },
        
        filterPriceListProducts() {
            let filtered = [...this.priceListProducts];
            
            // Filter by type
            if (this.selectedPriceListType) {
                filtered = filtered.filter(product => 
                    (product.type || '') === this.selectedPriceListType
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
            
            // Do not reset selected products when filter/search changes
            // Only reset pagination and highlight for better UX
            this.priceListPage = 1;
            this.highlightedIndex = 0;
            this.updateAllSelectedStatus();
        },

        scheduleFilterPriceListProducts() {
            if (this.filterDebounceTimer) {
                clearTimeout(this.filterDebounceTimer);
            }
            this.filterDebounceTimer = setTimeout(() => {
                this.filterPriceListProducts();
                this.filterDebounceTimer = null;
            }, 200);
        },
        
        showPriceListModal() {
            if (this.priceListProducts.length === 0) {
                this.loadPriceListData();
            }
            
            // Reset selection when opening modal
            this.selectedProducts = [];
            this.allSelected = false;
            this.priceListPage = 1;
            this.highlightedIndex = 0;
            this.selectedProductQuantities = {};
            
            this.priceListModal.show();
        },
        
        selectPriceListProduct(product) {
        // Clean and validate product data
        const cleanPrice = this.cleanPriceValue(product.price);
        
        // Add the selected product as a new order item
        const newItem = {
            title: product.name || '',
            product_code: product.code || '',
            product_name: product.name || '',
            product_id: product.id,
            quantity: 1,
            unit: product.unit || '枚',
            unit_price: cleanPrice,
            amount: cleanPrice,
            notes: product.notes || ''
        };
        
        this.newQuotation.items.push(newItem);
        
        this.calculateTotalAmount();
        
        // Close the modal
        this.priceListModal.hide();
        
        // Reset filters
        this.selectedPriceListType = '';
        this.priceListSearchTerm = '';
        this.filterPriceListProducts();
    },
            
        // Multiple product selection methods
        toggleProductSelection(product, event) {
            // Ignore if product is already added to quotation (individually or in any set)
            if (this.isProductAlreadyAdded(product)) {
                return;
            }
            
            if (event && event.shiftKey && this.lastSelectedIndexGlobal !== null) {
                const currentIndex = this.filteredPriceListProducts.findIndex(p => p.id === product.id);
                if (currentIndex !== -1) {
                    const start = Math.min(this.lastSelectedIndexGlobal, currentIndex);
                    const end = Math.max(this.lastSelectedIndexGlobal, currentIndex);
                    for (let i = start; i <= end; i++) {
                        const id = this.filteredPriceListProducts[i].id;
                        if (this.isProductAlreadyAdded({ id })) continue;
                        if (!this.selectedProducts.includes(id)) {
                            this.selectedProducts.push(id);
                            if (!this.selectedProductQuantities[id]) this.selectedProductQuantities[id] = 1;
                        }
                    }
                }
            } else {
                const index = this.selectedProducts.indexOf(product.id);
                if (index > -1) {
                    this.selectedProducts.splice(index, 1);
                    delete this.selectedProductQuantities[product.id];
                } else {
                    this.selectedProducts.push(product.id);
                    if (!this.selectedProductQuantities[product.id]) this.selectedProductQuantities[product.id] = 1;
                }
            }
            
            this.lastSelectedIndexGlobal = this.filteredPriceListProducts.findIndex(p => p.id === product.id);
            this.updateAllSelectedStatus();
        },
        
        toggleSelectAll() {
            const pageIds = this.paginatedPriceListProducts
                .filter(p => !this.isProductAlreadyAdded(p))
                .map(p => p.id);
            
            const allOnPageSelected = pageIds.every(id => this.selectedProducts.includes(id));
            
            if (allOnPageSelected) {
                // Deselect only current page ids
                this.selectedProducts = this.selectedProducts.filter(id => {
                    const keep = !pageIds.includes(id);
                    if (!keep) delete this.selectedProductQuantities[id];
                    return keep;
                });
            } else {
                // Add current page ids
                pageIds.forEach(id => {
                    if (!this.selectedProducts.includes(id)) this.selectedProducts.push(id);
                    if (!this.selectedProductQuantities[id]) this.selectedProductQuantities[id] = 1;
                });
            }
            
            this.updateAllSelectedStatus();
        },
        
        updateAllSelectedStatus() {
            const pageIds = this.paginatedPriceListProducts
                .filter(p => !this.isProductAlreadyAdded(p))
                .map(p => p.id);
            
            this.allSelected = pageIds.length > 0 && pageIds.every(id => this.selectedProducts.includes(id));
        },
        
        selectSingleProduct(product) {
            // Single product selection (original behavior)
            this.selectPriceListProduct(product);
        },
        
        removeFromSelection(productId) {
            const index = this.selectedProducts.indexOf(productId);
            if (index > -1) {
                this.selectedProducts.splice(index, 1);
                delete this.selectedProductQuantities[productId];
            }
            
            this.updateAllSelectedStatus();
        },
        
        // Helper function to clean price values
        cleanPriceValue(price) {
            if (price === null || price === undefined || price === '') {
                return 0;
            }
            // Convert to number and handle edge cases
            const priceStr = String(price).replace(/[^\d.-]/g, ''); // Remove non-numeric chars except dots and minus
            const cleanPrice = parseFloat(priceStr);
            return isNaN(cleanPrice) ? 0 : cleanPrice;
        },
        
        getSelectedProductsList() {
            const selected = this.priceListProducts.filter(p => this.selectedProducts.includes(p.id));
            return selected;
        },
        
        getSelectedProductsTotal() {
            return this.getSelectedProductsList().reduce((total, product) => {
                const qty = this.selectedProductQuantities[product.id] || 1;
                const cleanPrice = this.cleanPriceValue(product.price);
                return total + (cleanPrice * qty);
            }, 0);
        },
        
        addSelectedProductsAsSet() {
            const selectedProducts = this.getSelectedProductsList();
            if (selectedProducts.length === 0) return;
            
            // Build set details to persist as JSON
            const setDetails = selectedProducts.map(p => ({
                id: p.id,
                code: p.code,
                name: p.name,
                unit: p.unit,
                price: p.price,
                quantity: this.selectedProductQuantities[p.id] || 1
            }));

            // Create a set item with combined information
            const fallbackTitle = `商品セット (${selectedProducts.length}件)`;
            const computedTitle = (this.selectedSetName && this.selectedSetName.trim()) ? this.selectedSetName.trim() : fallbackTitle;
            const setItem = {
                project_id: '', // Add project_id field for consistency
                _oldProjectId: '', // Add _oldProjectId field for consistency
                title: computedTitle,
                product_code: '', // Set empty for set products
                product_name: selectedProducts.map(p => {
                    const qty = this.selectedProductQuantities[p.id] || 1;
                    return `${p.name} x${qty}`;
                }).join(' + '),
                quantity: 1,
                unit: '式',
                unit_price: this.getSelectedProductsTotal(),
                amount: this.getSelectedProductsTotal(),
                notes: '',
                is_set: true,
                set_json: setDetails // Store as object, not stringified
            };
            
            // Use Vue.set or spread operator to ensure reactivity
            this.newQuotation.items = [...this.newQuotation.items, setItem];
            
            // Force Vue to update the reactive array
            this.$nextTick(() => {
                this.calculateTotalAmount();
            });
            
            // Close the modal and reset
            this.priceListModal.hide();
            this.selectedProducts = [];
            this.allSelected = false;
            this.selectedSetName = '';
            this.selectedPriceListType = '';
            this.priceListSearchTerm = '';
            this.filterPriceListProducts();
        },
        
        // --- Set editing ---
        showEditSetModal(itemIndex) {
            const item = this.newQuotation.items[itemIndex];
            
            if (!item || !item.is_set) {
                return;
            }
            
            let products = [];
            
            // Handle both object and JSON string cases
            if (typeof item.set_json === 'object' && item.set_json !== null) {
                // set_json is already an object
                products = Array.isArray(item.set_json) ? item.set_json : [];
            } else if (typeof item.set_json === 'string') {
                // set_json is a JSON string, try to parse it
                try {
                    const parsed = JSON.parse(item.set_json || '[]');
                    products = Array.isArray(parsed) ? parsed : [];
                } catch (e) {
                    products = [];
                }
            } else {
                products = [];
            }
            
            this.editingSet = { index: itemIndex, products };
            
            if (!this.setEditModal) {
                const el = document.getElementById('editSetModal');
                
                if (el) {
                    this.setEditModal = new bootstrap.Modal(el);
                    
                    // Bind stacking handlers once
                    el.addEventListener('shown.bs.modal', () => {
                        // Raise z-index of edit modal
                        // Raise the last backdrop slightly above base backdrop
                        const backdrops = document.querySelectorAll('.modal-backdrop');
                        const lastBackdrop = backdrops[backdrops.length - 1];
                        if (lastBackdrop) lastBackdrop.classList.add('edit-set-backdrop');
                    }, { once: false });
                    el.addEventListener('hidden.bs.modal', () => {
                        const backdrops = document.querySelectorAll('.modal-backdrop.edit-set-backdrop');
                        backdrops.forEach(b => b.classList.remove('edit-set-backdrop'));
                    }, { once: false });
                }
            }
            
            if (this.setEditModal) {
                this.setEditModal.show();
            }
        },
        updateEditingSetQty(productId, value) {
            if (!this.editingSet) return;
            const target = this.editingSet.products.find(p => p.id === productId);
            if (!target) return;
            let qty = Number(value);
            if (!Number.isFinite(qty) || qty <= 0) qty = 1;
            target.quantity = Math.floor(qty);
        },
        removeFromEditingSet(productId) {
            if (!this.editingSet) return;
            this.editingSet.products = this.editingSet.products.filter(p => p.id !== productId);
        },
        getEditingSetTotal() {
            if (!this.editingSet) return 0;
            return this.editingSet.products.reduce((sum, p) => sum + (p.price || 0) * (p.quantity || 1), 0);
        },
        saveEditedSet() {
            if (!this.editingSet) {
                return;
            }
            
            const { index, products } = this.editingSet;
            const item = this.newQuotation.items[index];
            if (!item) {
                return;
            }
            
            const total = products.reduce((sum, p) => sum + (p.price || 0) * (p.quantity || 1), 0);
            item.product_code = ''; // Set empty for set products
            item.product_name = products.map(p => `${p.name} x${(p.quantity || 1)}`).join(' + ');
            item.unit_price = total;
            item.amount = total;
            // Do not auto-fill notes when editing set
            item.is_set = true;
            item.set_json = JSON.stringify(products);
            item.title = `商品セット (${products.length}件)`;
            
            this.calculateTotalAmount();
            
            if (this.setEditModal) {
                this.setEditModal.hide();
            }
        },

        // --- Order items bulk selection/deletion ---
        toggleSelectOrderItem(index) {
            const pos = this.selectedOrderItemIndexes.indexOf(index);
            if (pos >= 0) {
                this.selectedOrderItemIndexes.splice(pos, 1);
            } else {
                this.selectedOrderItemIndexes.push(index);
            }
        },
        selectAllOrderItems() {
            if (this.allOrderItemsSelected) {
                this.selectedOrderItemIndexes = [];
            } else {
                this.selectedOrderItemIndexes = (this.newQuotation.items || []).map((_, idx) => idx);
            }
        },
        deleteSelectedOrderItems() {
            if (!this.selectedOrderItemIndexes.length) return;
            
            const toDelete = new Set(this.selectedOrderItemIndexes);
            this.newQuotation.items = (this.newQuotation.items || []).filter((_, idx) => !toDelete.has(idx));
            
            this.selectedOrderItemIndexes = [];
            this.calculateTotalAmount();
        },

        addSelectedProductsIndividually() {
            const selectedProducts = this.getSelectedProductsList();
            if (selectedProducts.length === 0) return;
            
            const newItems = [];
            selectedProducts.forEach(product => {
                const cleanPrice = this.cleanPriceValue(product.price);
                const quantity = this.selectedProductQuantities[product.id] || 1;
                const item = {
                    title: product.name,
                    product_code: product.code,
                    product_name: product.name,
                    product_id: product.id,
                    quantity: quantity,
                    unit: product.unit,
                    unit_price: cleanPrice,
                    amount: cleanPrice * quantity,
                    notes: product.notes || ''
                };
                newItems.push(item);
            });
            
            // Use spread operator to ensure reactivity
            this.newQuotation.items = [...this.newQuotation.items, ...newItems];
            
            this.calculateTotalAmount();
            // Close and reset
            this.priceListModal.hide();
            this.selectedProducts = [];
            this.allSelected = false;
            this.selectedProductQuantities = {};
            this.selectedPriceListType = '';
            this.priceListSearchTerm = '';
            this.filterPriceListProducts();
        },

        // Pagination controls
        goToPrevPriceListPage() {
            if (this.priceListPage > 1) {
                this.priceListPage -= 1;
                this.highlightedIndex = 0;
                this.updateAllSelectedStatus();
            }
        },
        goToNextPriceListPage() {
            if (this.priceListPage < this.totalPriceListPages) {
                this.priceListPage += 1;
                this.highlightedIndex = 0;
                this.updateAllSelectedStatus();
            }
        },
        onChangePriceListPageSize() {
            // Reset to first page when page size changes
            this.priceListPage = 1;
            this.highlightedIndex = 0;
            this.updateAllSelectedStatus();
        },
        selectAllFiltered() {
            this.selectedProducts = this.filteredPriceListProducts
                .filter(p => !this.isProductAlreadyAdded(p))
                .map(p => p.id);
            
            const map = {};
            this.filteredPriceListProducts
                .filter(p => !this.isProductAlreadyAdded(p))
                .forEach(p => { map[p.id] = this.selectedProductQuantities[p.id] || 1; });
            
            this.selectedProductQuantities = map;
            
            this.updateAllSelectedStatus();
        },

        normalizeSelectedQuantity(productId) {
            let qty = Number(this.selectedProductQuantities[productId] || 1);
            if (!Number.isFinite(qty) || qty <= 0) qty = 1;
            
            this.selectedProductQuantities[productId] = Math.floor(qty);
        },

        // Keyboard navigation inside price list modal
        handlePriceListKeydown(e) {
            if (!document.body.classList.contains('modal-open')) return;
            
            const list = this.paginatedPriceListProducts;
            if (!list || list.length === 0) return;
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.highlightedIndex = Math.min(this.highlightedIndex + 1, list.length - 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.highlightedIndex = Math.max(this.highlightedIndex - 1, 0);
            } else if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const product = list[this.highlightedIndex];
                if (product && !this.isProductAlreadyAdded(product)) {
                    this.toggleProductSelection(product, e);
                }
            }
        },

        isProductAlreadyAdded(product) {
            if (!product || product.id == null) return false;
            return this.alreadyAddedProductIds.has(product.id);
        },
        
        // Debug method to set test data
        setTestData() {
            this.priceListProducts = [
                { id: 1, code: '100', name: 'サンプル商品1', type: '新規', unit: '枚', price: 1000, cost: 800 },
                { id: 2, code: '101', name: 'サンプル商品2', type: '修正', unit: '枚', price: 2000, cost: 1600 },
                { id: 3, code: '102', name: 'サンプル商品3', type: 'その他', unit: '枚', price: 1500, cost: 1200 },
                { id: 4, code: '103', name: 'サンプル商品4', type: '新規', unit: '枚', price: 3000, cost: 2400 },
                { id: 5, code: '104', name: 'サンプル商品5', type: '修正', unit: '枚', price: 2500, cost: 2000 }
            ];
            this.filterPriceListProducts();
        },

        testFormData() {
            const testData = new FormData();
            testData.append('test_field', 'test_value');
            testData.append('test_array', JSON.stringify([1, 2, 3]));
            
            // Test JSON parsing
            try {
                const parsedArray = JSON.parse(testData.get('test_array'));
            } catch (e) {
                console.error('Error parsing test array:', e);
            }
        },

        async testRequest() {
            try {
                const testData = new FormData();
                testData.append('test_field', 'test_value');
                testData.append('test_array', JSON.stringify([1, 2, 3]));
                
                const response = await axios.post('/api/index.php?model=quotation&method=create', testData);
            } catch (error) {
                console.error('Test request error:', error);
                if (error.response) {
                    console.error('Test response data:', error.response.data);
                }
            }
        },

        async testAlternativeRequest() {
            try {
                // Test with URLSearchParams instead of FormData
                const testData = new URLSearchParams();
                testData.append('test_field', 'test_value');
                testData.append('test_array', JSON.stringify([1, 2, 3]));
                
                const response = await axios.post('/api/index.php?model=quotation&method=create', testData, {
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }
                });
            } catch (error) {
                console.error('Alternative test request error:', error);
                if (error.response) {
                    console.error('Alternative test response data:', error.response.data);
                }
            }
        },

        // Child project selection methods
        selectAllChildProjects() {
            this.selectedChildProjectIds = this.childProjects.map(project => project.id);
            this.updateChildProjectSelection();
        },
        
        deselectAllChildProjects() {
            this.selectedChildProjectIds = [];
            this.updateChildProjectSelection();
        },
        
        toggleAllChildProjects() {
            if (this.allChildProjectsSelected) {
                this.deselectAllChildProjects();
            } else {
                this.selectAllChildProjects();
            }
        },
        
        updateChildProjectSelection() {
            const total = this.childProjects.length;
            const selected = this.selectedChildProjectIds.length;
            
            this.allChildProjectsSelected = selected === total && total > 0;
            this.someChildProjectsSelected = selected > 0 && selected < total;
            

            
            // Clear project_id from order items that are no longer linked to selected projects
            this.clearUnlinkedOrderItems();
        },
        
        async updateSelectedChildProjectsStatus() {
            try {
                if (this.selectedChildProjectIds.length === 0) {
                    return;
                }
                
                const quotationStatus = this.newQuotation.status;
                let newStatus = 'open'; // Default status
                
                // Map quotation status to project status
                switch (quotationStatus) {
                    case '下書き':
                        newStatus = 'draft';
                        break;
                    case '発行済み':
                        newStatus = 'quoted';
                        break;
                    case '承認済み':
                        newStatus = 'confirming';
                        break;
                    case '却下':
                        newStatus = 'cancelled';
                        break;
                    case '調整':
                        newStatus = 'in_progress';
                        break;
                    default:
                        newStatus = 'open';
                }
                
                let successCount = 0;
                let errorCount = 0;
                
                // Update each selected child project status
                for (const projectId of this.selectedChildProjectIds) {
                    try {
                        const formData = new FormData();
                        formData.append('id', projectId);
                        formData.append('status', newStatus);
                        
                        const response = await axios.post('/api/index.php?model=project&method=updateStatus', formData);
                        
                        if (response.data && response.data.status === 'success') {
                            successCount++;
                        } else {
                            console.warn(`Failed to update child project ${projectId} status:`, response.data?.message);
                            errorCount++;
                        }
                    } catch (error) {
                        console.error(`Error updating child project ${projectId} status:`, error);
                        errorCount++;
                    }
                }
                
                // Reload child projects to reflect the changes
                await this.loadChildProjects();
                
                // Show appropriate message based on results
                if (successCount > 0 && errorCount === 0) {
                    showMessage(`${successCount}件の子プロジェクトのステータスが「${this.getProjectStatusLabel(newStatus)}」に更新されました。`, false);
                } else if (successCount > 0 && errorCount > 0) {
                    showMessage(`${successCount}件の子プロジェクトのステータスが更新されましたが、${errorCount}件の更新に失敗しました。`, true);
                } else if (successCount === 0) {
                    showMessage('子プロジェクトのステータスの更新に失敗しました。', true);
                }
                
            } catch (error) {
                console.error('Error updating selected child projects status:', error);
                showMessage('子プロジェクトのステータスの更新中にエラーが発生しました。', true);
            }
        },

        async updateChildProjectAmountsAfterQuotation() {
            try {
                if (!this.newQuotation.items || this.newQuotation.items.length === 0) {
                    return;
                }

                // Group items by project_id and calculate totals
                const projectAmounts = {};
                this.newQuotation.items.forEach(item => {
                    if (item.project_id && item.amount) {
                        const projectId = item.project_id;
                        if (!projectAmounts[projectId]) {
                            projectAmounts[projectId] = 0;
                        }
                        projectAmounts[projectId] += item.amount || 0;
                    }
                });

                // Update each child project's total_amount in the database
                let successCount = 0;
                let errorCount = 0;

                for (const [projectId, totalAmount] of Object.entries(projectAmounts)) {
                    try {
                        const formData = new FormData();
                        formData.append('id', projectId);
                        formData.append('amount', totalAmount);
                        
                        const response = await axios.post('/api/index.php?model=project&method=updateAmount', formData);
                        
                        if (response.data && response.data.status === 'success') {
                            successCount++;
                            
                            // Also update local childProjects array
                            const localProject = this.childProjects.find(p => p.id == projectId);
                            if (localProject) {
                                localProject.total_amount = totalAmount;
                                localProject.amount = totalAmount; // Update both fields for compatibility
                            }
                        } else {
                            console.warn(`Failed to update child project ${projectId} total_amount:`, response.data?.message);
                            errorCount++;
                        }
                    } catch (error) {
                        console.error(`Error updating child project ${projectId} total_amount:`, error);
                        errorCount++;
                    }
                }

                // Force Vue reactivity update
                this.$forceUpdate();

                // Show appropriate message based on results
                if (successCount > 0 && errorCount === 0) {
                    showMessage(`${successCount}件の子プロジェクトの金額が更新されました。`, false);
                } else if (successCount > 0 && errorCount > 0) {
                    showMessage(`${successCount}件の子プロジェクトの金額が更新されましたが、${errorCount}件の更新に失敗しました。`, true);
                } else if (successCount === 0) {
                    showMessage('子プロジェクトの金額の更新に失敗しました。', true);
                }

            } catch (error) {
                console.error('Error updating child project amounts after quotation:', error);
                showMessage('子プロジェクトの金額の更新中にエラーが発生しました。', true);
            }
        },

        async updateChildProjectAmountsFromQuotation(quotationId) {
            try {
                // Find the quotation
                const quotation = this.quotations.find(q => q.id === quotationId);
                if (!quotation || !quotation.items || quotation.items.length === 0) {
                    return;
                }

                // Group items by project_id and calculate totals
                const projectAmounts = {};
                quotation.items.forEach(item => {
                    if (item.project_id && item.amount) {
                        const projectId = item.project_id;
                        if (!projectAmounts[projectId]) {
                            projectAmounts[projectId] = 0;
                        }
                        projectAmounts[projectId] += item.amount || 0;
                    }
                });

                // Update each child project's amount in the database
                let successCount = 0;
                let errorCount = 0;

                for (const [projectId, totalAmount] of Object.entries(projectAmounts)) {
                    try {
                        const formData = new FormData();
                        formData.append('id', projectId);
                        formData.append('amount', totalAmount);
                        
                        const response = await axios.post('/api/index.php?model=project&method=updateAmount', formData);
                        
                        if (response.data && response.data.status === 'success') {
                            successCount++;
                            
                            // Also update local childProjects array
                            const localProject = this.childProjects.find(p => p.id == projectId);
                            if (localProject) {
                                localProject.total_amount = totalAmount;
                                localProject.amount = totalAmount; // Update both fields for compatibility
                            }
                        } else {
                            console.warn(`Failed to update child project ${projectId} amount:`, response.data?.message);
                            errorCount++;
                        }
                    } catch (error) {
                        console.error(`Error updating child project ${projectId} amount:`, error);
                        errorCount++;
                    }
                }

                // Force Vue reactivity update
                this.$forceUpdate();

                // Show appropriate message based on results
                if (successCount > 0 && errorCount === 0) {
                    showMessage(`${successCount}件の子プロジェクトの金額が更新されました。`, false);
                } else if (successCount > 0 && errorCount > 0) {
                    showMessage(`${successCount}件の子プロジェクトの金額が更新されましたが、${errorCount}件の更新に失敗しました。`, true);
                } else if (successCount === 0) {
                    showMessage('子プロジェクトの金額の更新に失敗しました。', true);
                }

            } catch (error) {
                console.error('Error updating child project amounts from quotation:', error);
                showMessage('子プロジェクトの金額の更新中にエラーが発生しました。', true);
            }
        },

        async resetChildProjectAmountsAfterQuotationDeletion(quotation) {
            try {
                if (!quotation || !quotation.items || quotation.items.length === 0) {
                    return;
                }

                // Get unique project IDs from the deleted quotation
                const projectIds = [...new Set(quotation.items
                    .filter(item => item.project_id)
                    .map(item => item.project_id))];

                if (projectIds.length === 0) {
                    return;
                }

                // Reset amount to 0 for each affected project
                let successCount = 0;
                let errorCount = 0;

                for (const projectId of projectIds) {
                    try {
                        const formData = new FormData();
                        formData.append('id', projectId);
                        formData.append('amount', 0);
                        
                        const response = await axios.post('/api/index.php?model=project&method=updateAmount', formData);
                        
                        if (response.data && response.data.status === 'success') {
                            successCount++;
                            
                            // Also update local childProjects array
                            const localProject = this.childProjects.find(p => p.id == projectId);
                            if (localProject) {
                                localProject.total_amount = 0;
                                localProject.amount = 0; // Update both fields for compatibility
                            }
                        } else {
                            console.warn(`Failed to reset child project ${projectId} amount:`, response.data?.message);
                            errorCount++;
                        }
                    } catch (error) {
                        console.error(`Error resetting child project ${projectId} amount:`, error);
                        errorCount++;
                    }
                }

                // Force Vue reactivity update
                this.$forceUpdate();

                // Show appropriate message based on results
                if (successCount > 0 && errorCount === 0) {
                    showMessage(`${successCount}件の子プロジェクトの金額がリセットされました。`, false);
                } else if (successCount > 0 && errorCount > 0) {
                    showMessage(`${successCount}件の子プロジェクトの金額がリセットされましたが、${errorCount}件のリセットに失敗しました。`, true);
                } else if (successCount === 0) {
                    showMessage('子プロジェクトの金額のリセットに失敗しました。', true);
                }

            } catch (error) {
                console.error('Error resetting child project amounts after quotation deletion:', error);
                showMessage('子プロジェクトの金額のリセット中にエラーが発生しました。', true);
            }
        },

        updateProjectTotalAmount(projectId, itemIndex) {
            const item = this.newQuotation.items[itemIndex];
            if (!item) {
                return;
            }
            
            // Get the old project ID before the change
            const oldProjectId = item._oldProjectId || null;
            
            // Store the new project ID for future reference
            item._oldProjectId = projectId;
            
            // Calculate item amount first
            this.calculateItemAmount(itemIndex);
            
            // If there was a previous project, update its total amount (decrement)
            if (oldProjectId && oldProjectId !== projectId) {
                this.updateChildProjectTotalAmount(oldProjectId);
            }
            
            // Update new project total amount (increment)
            if (projectId) {
                this.updateChildProjectTotalAmount(projectId);
            }
        },

        updateChildProjectTotalAmount(projectId) {
            // Find the project in childProjects
            const project = this.childProjects.find(p => p.id == projectId);
            if (!project) {
                return;
            }
            
            // Calculate total amount for this project from all items
            const projectItems = this.newQuotation.items.filter(item => item.project_id == projectId);
            const projectTotal = projectItems.reduce((sum, item) => sum + (item.amount || 0), 0);
            
            // Update the project's total_amount in childProjects array
            project.total_amount = projectTotal;
            
            // Force Vue reactivity update
            this.$forceUpdate();
        },
        
        clearUnlinkedOrderItems() {
            // Clear project_id from order items that are no longer linked to selected projects
            if (!this.newQuotation.items || this.newQuotation.items.length === 0) {
                return;
            }
            
            let clearedCount = 0;
            this.newQuotation.items.forEach((item, index) => {
                if (item.project_id && !this.selectedChildProjectIds.includes(parseInt(item.project_id))) {
                    item.project_id = '';
                    item._oldProjectId = ''; // Reset old project ID when clearing
                    clearedCount++;
                    
                    // Recalculate item amount since project link was removed
                    this.calculateItemAmount(index);
                }
            });
            
            if (clearedCount > 0) {
                // Update total amounts for all child projects
                this.childProjects.forEach(project => {
                    this.updateChildProjectTotalAmount(project.id);
                });
            }
        },

        updateEditingSetTotal() {
            // This function updates the total amount for the editing set
            // It's called when quantities are changed in the edit set modal
            if (this.editingSet && this.editingSet.products) {
                // The total will be calculated automatically by the template
                // This function can be extended if additional logic is needed
                this.$forceUpdate();
            }
        },

        removeProductFromSet(productId) {
            // Remove a product from the editing set
            if (this.editingSet && this.editingSet.products) {
                this.editingSet.products = this.editingSet.products.filter(p => p.id !== productId);
                this.updateEditingSetTotal();
            }
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

            // Keyboard navigation for price list modal
            const priceListModalEl = document.getElementById('priceListModal');
            if (priceListModalEl) {
                priceListModalEl.addEventListener('shown.bs.modal', () => {
                    this.highlightedIndex = 0;
                    document.addEventListener('keydown', this.handlePriceListKeydown);
                });
                priceListModalEl.addEventListener('hidden.bs.modal', () => {
                    document.removeEventListener('keydown', this.handlePriceListKeydown);
                });
            }
        } catch (error) {
            console.error('Error in mounted:', error);
        } finally {
            this.loading = false;
        }
    }
}).mount('#app'); 