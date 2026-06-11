const { createApp } = Vue;

createApp({
    data() {
        return {
            parentProject: {
                company_name: '',
                branch_name: '',
                contact_name: '',
                customer_id: '',
                guis_receiver: window.currentUser?.id || '',
                request_date: '',
                construction_number: '',
                project_number: '',
                project_name: '',
                construction_branch: '',
                scale: '',
                type1: '',
                type2: '',
                request_type: '',
                desired_delivery_date: '',
                request_design: false,
                request_equipment: false,
                request_energy_saving: false,
                request_other: false,
                request_3d: false,
                materials_layout: false,
                materials_rental: false,
                materials_contract: false,
                materials_tac: false,
                materials_other: false,
                structural_office: '',
                notes: '',
                status: 'draft'
            },
            statuses: [
                { value: 'draft', label: '下書き', color: 'secondary' },
                { value: 'under_contract', label: '契約中', color: 'info' },
                { value: 'in_progress', label: '進行中', color: 'primary' },
                { value: 'completed', label: '完了', color: 'success' },
                { value: 'cancelled', label: 'キャンセル', color: 'danger' }
            ],
            validationErrors: {
                company_name: '',
                branch_name: '',
                contact_name: '',
                guis_receiver: '',
                project_name: '',
                project_number: '',
                request_date: '',
                desired_delivery_date: ''
            },
            type1Tagify: null,
            type2Tagify: null,
            constructionBranchTagify: null,
            scaleTagify: null,
            // Customer modal data
            categories: [],
            departments: [],
            customers: [],
            newCustomer: {
                company_name: '大東建託株式会社',
                company_name_kana: '',
                name: '',
                name_kana: '',
                branch: '',
                position: '',
                department: '',
                title: '',
                tel: '',
                fax: '',
                phone: '',
                email: '',
                zip: '',
                address1: '',
                address2: '',
                memo: '',
                status: 1,
                category_id: 0,
                guis_department: [],
            },
            customerErrors: {
                company_name: '',
                name: '',
                guis_department: ''
            }
        }
    },
    methods: {
        getStatusLabel(status) {
            const s = this.statuses.find(s => s.value === status);
            return s ? s.label : status;
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
        validateParentProjectForm() {
            this.validationErrors = {
                company_name: '',
                branch_name: '',
                contact_name: '',
                guis_receiver: '',
                project_name: '',
                project_number: '',
                request_date: '',
                desired_delivery_date: ''
            };
            let valid = true;
            
            if (!this.parentProject.company_name) {
                this.validationErrors.company_name = '会社名は必須です';
                valid = false;
            }
            
            if (!this.parentProject.branch_name) {
                this.validationErrors.branch_name = '支店名は必須です';
                valid = false;
            }
            
            if (!this.parentProject.contact_name) {
                this.validationErrors.contact_name = '担当様は必須です';
                valid = false;
            }
            
            if (!this.parentProject.guis_receiver) {
                this.validationErrors.guis_receiver = 'GUIS受付者は必須です';
                valid = false;
            }
            
            if (!this.parentProject.project_name) {
                this.validationErrors.project_name = 'お施主様名は必須です';
                valid = false;
            }
            
            if (!this.parentProject.project_number) {
                this.validationErrors.project_number = '管理番号は必須です';
                valid = false;
            }
            
            if (!this.parentProject.request_date) {
                this.validationErrors.request_date = '依頼日は必須です';
                valid = false;
            }
            
            // if (!this.parentProject.desired_delivery_date) {
            //     this.validationErrors.desired_delivery_date = '希望納期は必須です';
            //     valid = false;
            // }
            
            return valid;
        },
        async saveParentProject() {
            if (!this.validateParentProjectForm()) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('company_name', this.parentProject.company_name || '');
                formData.append('branch_name', this.parentProject.branch_name || '');
                formData.append('contact_name', this.parentProject.contact_name || '');
                formData.append('customer_id', this.parentProject.customer_id || '');
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
                // Convert checkbox requests to comma-separated string
                const requestsArray = [];
                if (this.parentProject.request_design) requestsArray.push('意匠');
                if (this.parentProject.request_equipment) requestsArray.push('設備');
                if (this.parentProject.request_energy_saving) requestsArray.push('省エネ');
                if (this.parentProject.request_other) requestsArray.push('その他');
                if (this.parentProject.request_3d) requestsArray.push('3D');
                formData.append('requests', requestsArray.join(','));
                // Convert checkbox materials to comma-separated string
                const materialsArray = [];
                if (this.parentProject.materials_layout) materialsArray.push('配置図');
                if (this.parentProject.materials_rental) materialsArray.push('家賃審査書');
                if (this.parentProject.materials_contract) materialsArray.push('契約図');
                if (this.parentProject.materials_tac) materialsArray.push('TAC図');
                if (this.parentProject.materials_other) materialsArray.push('その他');
                formData.append('materials', materialsArray.join(','));
                formData.append('structural_office', this.parentProject.structural_office || '');
                formData.append('notes', this.parentProject.notes || '');
                formData.append('status', this.parentProject.status || 'in_progress');
                
                const response = await axios.post('/api/index.php?model=parentproject&method=create', formData);
                if (response.data && response.data.status == 'success') {
                    // Show success message with SweetAlert2
                    Swal.fire({
                        title: '成功',
                        text: '建築物を登録しました。',
                        icon: 'success',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#3085d6'
                    }).then((result) => {
                        // Redirect after user closes the message
                        if (response.data.parent_project_id) {
                            window.location.href = `detail.php?id=${response.data.parent_project_id}`;
                        } else {
                            window.location.href = 'index.php';
                        }
                    });
                } else {
                    showMessage('建物の登録に失敗しました。', true);
                }
            } catch (error) {
                console.error('Error creating parent project:', error);
                showMessage('建物の作成に失敗しました。', true);
            }
        },
        initSelect2() {
            // Initialize company name Select2 with default data
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
                            const results = data.data.map(function(item) {
                                return {
                                    id: item.company_name,
                                    text: item.company_name
                                };
                            });
                            // 大東を含むオプションを先頭に
                            results.sort((a, b) => {
                                const aHas = (a.text || '').includes('大東');
                                const bHas = (b.text || '').includes('大東');
                                if (aHas && !bHas) return -1;
                                if (!aHas && bHas) return 1;
                                return 0;
                            });
                            return { results };
                        }
                    }
                }).on('select2:select', (e) => {
                    this.parentProject.company_name = e.params.data.id;
                    this.validationErrors.company_name = ''; // Clear validation error
                    this.onCompanyChange();
                }).on('select2:clear', () => {
                    this.parentProject.company_name = '';
                    this.onCompanyChange();
                });

                // Load initial companies data
                this.loadInitialCompanies();
            }

            // Initialize branch name Select2
            this.initBranchSelect2();

            // Initialize contact name Select2
            this.initContactSelect2();
            
            // Initialize GUIS Receiver Select2
            this.initGuisReceiverSelect2();
            
            // Initialize date pickers
            this.initDatePickers();
            
            // Initialize Tagify
            this.initTagify();
        },
        async loadInitialCompanies() {
            try {
                const response = await axios.get('/api/index.php?model=customer&method=list_companies');
                if (response.data && response.data.data) {
                    const companies = response.data.data.map(item => ({
                        id: item.company_name,
                        text: item.company_name
                    }));
                    // 大東を含むオプションを先頭に
                    companies.sort((a, b) => {
                        const aHas = (a.text || '').includes('大東');
                        const bHas = (b.text || '').includes('大東');
                        if (aHas && !bHas) return -1;
                        if (!aHas && bHas) return 1;
                        return 0;
                    });

                    // Add options to company select2
                    const $company = $('#company_name');
                    if ($company.length && $company.data('select2')) {
                        companies.forEach(company => {
                            const option = new Option(company.text, company.id, false, false);
                            $company.append(option);
                        });
                    }
                }
            } catch (error) {
                console.error('Error loading initial companies:', error);
            }
        },
        onCompanyChange() {
            // Clear branch and contact when company changes
            this.parentProject.branch_name = '';
            this.parentProject.contact_name = '';
            this.parentProject.customer_id = '';
            
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
        onBranchChange() {
            // Clear contact when branch changes
            this.parentProject.contact_name = '';
            this.parentProject.customer_id = '';
            
            // Update contact select2
            const $contact = $('#contact_name');
            if ($contact.length && $contact.data('select2')) {
                $contact.val(null).trigger('change');
                $contact.select2('destroy');
                this.initContactSelect2();
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
                    this.validationErrors.branch_name = ''; // Clear validation error
                    this.onBranchChange();
                }).on('select2:clear', () => {
                    this.parentProject.branch_name = '';
                    this.onBranchChange();
                });
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
                                        id: item.id,
                                        text: item.name
                                    };
                                })
                            };
                        }
                    }
                }).on('select2:select', (e) => {
                    this.parentProject.contact_name = e.params.data.text;
                    this.parentProject.customer_id = e.params.data.id;
                    this.validationErrors.contact_name = ''; // Clear validation error
                }).on('select2:clear', () => {
                    this.parentProject.contact_name = '';
                    this.parentProject.customer_id = '';
                });
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
                    this.validationErrors.guis_receiver = ''; // Clear validation error
                }).on('select2:clear', () => {
                    this.parentProject.guis_receiver = '';
                });

                // Load initial users data
                this.loadInitialUsers();
            }
        },
        async loadInitialUsers() {
            try {
                const response = await axios.get('/api/index.php?model=user&method=searchMembers');
                if (response.data && response.data.data) {
                    const users = response.data.data.map(item => ({
                        id: item.userid,
                        text: item.realname
                    }));
                    
                    // Add options to guis receiver select2
                    const $guisReceiver = $('#guis_receiver');
                    if ($guisReceiver.length && $guisReceiver.data('select2')) {
                        users.forEach(user => {
                            const option = new Option(user.text, user.id, false, false);
                            $guisReceiver.append(option);
                        });
                        
                        // Set the current user as selected if available
                        if (window.currentUser?.id && this.parentProject.guis_receiver === window.currentUser.id) {
                            $guisReceiver.val(window.currentUser.id).trigger('change');
                        }
                    }
                }
            } catch (error) {
                console.error('Error loading initial users:', error);
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
                    if (instance.input.id === 'request_date_picker') this.parentProject.request_date = dateStr;
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
                    if (instance.input.id === 'desired_delivery_date_picker') this.parentProject.desired_delivery_date = dateStr;
                }
            };

            const desiredDeliveryEl = document.getElementById('desired_delivery_date_picker');
            if (desiredDeliveryEl) {
                if (desiredDeliveryEl._flatpickr) desiredDeliveryEl._flatpickr.destroy();
                flatpickr(desiredDeliveryEl, desiredDeliveryOptions);
            }
        },
        setCurrentDateTime() {
            const now = new Date();
            const formattedDate = now.getFullYear() + '/' + 
                String(now.getMonth() + 1).padStart(2, '0') + '/' + 
                String(now.getDate()).padStart(2, '0') + ' ' + 
                String(now.getHours()).padStart(2, '0') + ':' + 
                String(now.getMinutes()).padStart(2, '0');
            
            this.parentProject.request_date = formattedDate;
            
            // Update the flatpickr instance
            const el = document.getElementById('request_date_picker');
            if (el && el._flatpickr) {
                el._flatpickr.setDate(formattedDate);
            }
        },
        setTodayDate() {
            const today = new Date();
            const formattedDate = today.getFullYear() + '/' + 
                String(today.getMonth() + 1).padStart(2, '0') + '/' + 
                String(today.getDate()).padStart(2, '0');
            
            this.parentProject.desired_delivery_date = formattedDate;
            
            // Update the flatpickr instance
            const el = document.getElementById('desired_delivery_date_picker');
            if (el && el._flatpickr) {
                el._flatpickr.setDate(formattedDate);
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
                }
                
                // --- Tagify for Scale (建物規模) ---
                const scaleInput = document.querySelector('#scale_tags');
                if (scaleInput && window.Tagify && !scaleInput._tagify) {
                    if (this.scaleTagify) {
                        try {
                            this.scaleTagify.destroy();
                        } catch (e) {
                            console.log('Error destroying existing scaleTagify:', e);
                        }
                    }
                    this.scaleTagify = new Tagify(scaleInput, {
                        whitelist: ['W2F3J', 'W2F4J', 'W2F5J', 'W2F6J', 'W3F3J', 'W3F4J', 'W3F5J', 'W3F6J', 'RC3F3J', 'RC3F4J', 'RC3F5J', 'RC3F6J', 'RC3F7J', 'RC3F8J'],
                        maxTags: 5,
                        dropdown: {
                            maxItems: 20,
                            classname: "tags-look-scale",
                            enabled: 0,
                            closeOnSelect: true
                        },
                    });
                    const updateScale = () => {
                        this.parentProject.scale = this.scaleTagify.value.map(tag => tag.value).join(',');
                    };
                    this.scaleTagify.on('add', updateScale);
                    this.scaleTagify.on('remove', updateScale);
                }
            });
        },

        // Customer modal methods
        async loadDepartments() {
            try {
                const response = await axios.get('/api/index.php?model=department&method=list_department');
                this.departments = response.data;
            } catch (error) {
                console.error('Error loading departments:', error);
                showMessage('部署の読み込みに失敗しました。', true);
            }
        },

        async loadCategories() {
            try {
                const response = await axios.get('/api/index.php?model=customer&method=list_category');
                this.categories = response.data;
                if (this.categories.length > 0) {
                    this.newCustomer.category_id = this.categories[0].id;
                }
            } catch (error) {
                console.error('Error loading categories:', error);
                showMessage('カテゴリーの読み込みに失敗しました。', true);
            }
        },

        async loadCustomers() {
            try {
                // Only load customers if both company and branch are selected
                if (!this.parentProject.company_name || !this.parentProject.branch_name) {
                    this.customers = [];
                    return;
                }

                // Load customers from all categories for the contact_name dropdown
                // First get all categories, then get customers from each category
                const categoriesResponse = await axios.get('/api/index.php?model=customer&method=list_category');
                if (categoriesResponse.data && categoriesResponse.data.length > 0) {
                    this.customers = [];
                    for (const category of categoriesResponse.data) {
                        try {
                            const customersResponse = await axios.get(`/api/index.php?model=customer&method=list_customer&category_id=${category.id}`);
                            if (customersResponse.data.status === 'success' && customersResponse.data.data) {
                                // Filter customers by company and branch
                                const filteredCustomers = customersResponse.data.data.filter(customer => 
                                    customer.company_name === this.parentProject.company_name && 
                                    customer.branch === this.parentProject.branch_name
                                );
                                this.customers = this.customers.concat(filteredCustomers);
                            }
                        } catch (error) {
                            console.error(`Error loading customers for category ${category.id}:`, error);
                        }
                    }
                }
            } catch (error) {
                console.error('Error loading customers:', error);
                showMessage('顧客の読み込みに失敗しました。', true);
            }
        },

        openNewCustomerModal() {
            this.resetCustomerData();
            $('#customerModal').modal('show');
        },

        resetCustomerData() {
            this.newCustomer = {
                company_name: '大東建託株式会社',
                company_name_kana: '',
                name: '',
                name_kana: '',
                branch: '本社',
                position: '',
                department: '',
                title: '',
                tel: '',
                fax: '',
                phone: '',
                email: '',
                zip: '',
                address1: '',
                address2: '',
                memo: '',
                status: 1,
                category_id: this.categories.length > 0 ? this.categories[0].id : 0,
                guis_department: []
            };
            this.customerErrors = { company_name: '', name: '', guis_department: '' };
        },

        async saveCustomer() {
            // Reset errors
            this.customerErrors = { company_name: '', name: '', guis_department: '' };
            let hasError = false;
            
            if (!this.newCustomer.company_name) {
                this.customerErrors.company_name = '会社名は必須です。';
                hasError = true;
            }
            if (!this.newCustomer.name) {
                this.customerErrors.name = '担当者名は必須です。';
                hasError = true;
            }
            if (!this.newCustomer.guis_department || this.newCustomer.guis_department.length === 0) {
                this.customerErrors.guis_department = '自社担当部署名は必須です。';
                hasError = true;
            }
            if (hasError) return;

            // Set default branch to "本社" if empty
            if (!this.newCustomer.branch || this.newCustomer.branch.trim() === '') {
                this.newCustomer.branch = '本社';
            }

            try {
                const response = await axios.post('/api/index.php?model=customer&method=add_customer', this.newCustomer, {
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }
                });
                
                if (response.data.status == 'success') {
                    showMessage('顧客を保存しました。');
                    
                    // Store customer data BEFORE doing anything else (since API doesn't return customer ID)
                    const customerData = {
                        company_name: this.newCustomer.company_name,
                        branch: this.newCustomer.branch,
                        name: this.newCustomer.name,
                        id: null // We'll find this after reloading customers
                    };
                    
                    // Close modal
                    $('#customerModal').modal('hide');
                    
                    // Reset form data AFTER storing the data
                    this.resetCustomerData();
                    
                    // Set the form values
                    this.parentProject.company_name = customerData.company_name;
                    this.parentProject.branch_name = customerData.branch;
                    this.parentProject.contact_name = customerData.name;
                    // customer_id will be set after we find the customer
                    
                    // Refresh data first, then update Select2
                    Promise.all([
                        this.loadCategories(),
                        // Don't call loadCustomers here as it will be called by updateAllSelect2WithCustomer
                    ]).then(() => {
                        // Update Select2 dropdowns after data is refreshed
                        setTimeout(() => {
                            this.updateAllSelect2WithCustomer(customerData);
                        }, 500);
                    }).catch(error => {
                        console.error('Error in Promise.all:', error);
                    });
                } else {
                    showMessage(response.data.message_code, true);
                }
            } catch (error) {
                console.error('Error saving customer:', error);
                showMessage('顧客の保存に失敗しました。', true);
            }
        },

        searchAddressCustomer() {
            const postalCode = this.newCustomer.zip;
            if (postalCode.length >= 7) {
                const apiUrl = `https://zipcloud.ibsnet.co.jp/api/search?zipcode=${postalCode}`;
                axios.get(apiUrl)
                    .then(response => {
                        if (response.data.results && response.data.results.length > 0) {
                            const result = response.data.results[0];
                            this.newCustomer.address1 = result.address1;
                            this.newCustomer.address2 = result.address2;
                        } else {
                            showMessage('郵便番号が見つかりません。', true);
                        }
                    })
                    .catch(error => {
                        console.error('Error searching address:', error);
                        showMessage('住所の検索に失敗しました。', true);
                    });
            } else {
                showMessage('郵便番号が正しくありません。', true);
            }
        },

        onContactNameChange() {
            // Find the selected customer and set the customer_id
            const selectedCustomer = this.customers.find(c => c.name === this.parentProject.contact_name);
            if (selectedCustomer) {
                this.parentProject.customer_id = selectedCustomer.id;
            } else {
                this.parentProject.customer_id = '';
            }
        },

        onCompanyNameChange() {
            // Clear branch and contact when company changes
            this.parentProject.branch_name = '';
            this.parentProject.contact_name = '';
            this.parentProject.customer_id = '';
            this.customers = []; // Clear customers list
            
            // Update Select2 dropdowns
            this.$nextTick(() => {
                const $branch = $('#branch_name');
                if ($branch.length && $branch.data('select2')) {
                    $branch.val(null).trigger('change');
                }
                
                const $contact = $('#contact_name');
                if ($contact.length && $contact.data('select2')) {
                    $contact.val(null).trigger('change');
                }
            });
        },

        onBranchNameChange() {
            // Clear contact when branch changes
            this.parentProject.contact_name = '';
            this.parentProject.customer_id = '';
            
            // Load customers for the selected company and branch
            this.loadCustomers();
            
            // Update contact Select2
            this.$nextTick(() => {
                const $contact = $('#contact_name');
                if ($contact.length && $contact.data('select2')) {
                    $contact.val(null).trigger('change');
                }
            });
        },

        // Helper method to update Select2 with new options
        updateSelect2WithNewOption(selectId, value, text) {
            const $select = $(selectId);
            if ($select.length && $select.data('select2')) {
                // Remove existing option if it exists
                $select.find(`option[value="${value}"]`).remove();
                
                // Add new option
                const newOption = new Option(text, value, false, false);
                $select.append(newOption);
                
                // Set value and trigger change
                $select.val(value).trigger('change');
                
                // Force Select2 to refresh
                $select.select2('destroy');
                $select.select2({
                    placeholder: '選択してください',
                    dropdownParent: $select.parent(),
                    allowClear: true,
                    minimumResultsForSearch: 0
                });
                $select.val(value).trigger('change');
            }
        },

        updateAllSelect2WithCustomer(customerData) {
            // Update company name Select2
            const $company = $('#company_name');
            if ($company.length) {
                // Check if option already exists
                if (!$company.find(`option[value="${customerData.company_name}"]`).length) {
                    $company.append(new Option(customerData.company_name, customerData.company_name, false, false));
                }
                $company.val(customerData.company_name).trigger('change');
            }
            
            // Update branch name Select2
            setTimeout(() => {
                const $branch = $('#branch_name');
                if ($branch.length) {
                    // Check if option already exists
                    if (!$branch.find(`option[value="${customerData.branch}"]`).length) {
                        $branch.append(new Option(customerData.branch, customerData.branch, false, false));
                    }
                    $branch.val(customerData.branch).trigger('change');
                }
                
                // Load customers for this company/branch combination and then update contact
                setTimeout(() => {
                    this.loadCustomers().then(() => {
                        const $contact = $('#contact_name');
                        if ($contact.length) {
                            // Enable the contact dropdown since we now have company and branch
                            $contact.prop('disabled', false);
                            
                            // Find the customer by name and company/branch (since we don't have ID from API)
                            let targetCustomer = this.customers.find(c => 
                                c.name === customerData.name && 
                                c.company_name === customerData.company_name && 
                                c.branch === customerData.branch
                            );
                            
                            // If we found the customer, update the customerData with the real ID
                            if (targetCustomer) {
                                customerData.id = targetCustomer.id;
                            }
                            
                            // Clear existing options except the first one (placeholder)
                            $contact.find('option:not(:first)').remove();
                            
                            // Add all customers as options
                            this.customers.forEach(customer => {
                                const option = new Option(customer.name, customer.name, false, false);
                                option.setAttribute('data-customer-id', customer.id);
                                $contact.append(option);
                            });
                            
                            // Set the value to the new customer
                            $contact.val(customerData.name).trigger('change');
                            
                            // Update Vue model with the correct customer ID
                            this.parentProject.contact_name = customerData.name;
                            this.parentProject.customer_id = customerData.id;
                        }
                    }).catch(error => {
                        console.error('Error loading customers:', error);
                    });
                }, 300);
            }, 200);
        }
    },



    async mounted() {
        // Initialize the form with default values
        this.parentProject = {
            company_name: '',
            branch_name: '',
            contact_name: '',
            customer_id: '',
            guis_receiver: window.currentUser?.id || '',
            request_date: '',
            construction_number: '',
            project_number: '',
            project_name: '',
            construction_branch: '',
            scale: '',
            type1: '',
            type2: '',
            request_type: '',
            desired_delivery_date: '',
            materials: '',
            structural_office: '',
            notes: '',
            status: 'draft'
        };
        
        // Auto-generate project number on page load
        await this.generateProjectNumber();
        
        // Load customer data
        this.loadDepartments();
        this.loadCategories();
        // Don't load customers initially - only when company and branch are selected
        
        // Initialize Select2 after Vue is mounted
        this.$nextTick(() => {
            this.initSelect2();
            
            // Initialize select2 for guis_department in customer modal
            const guisDepartmentSelect = this.$refs.guisDepartmentSelect;
            if (guisDepartmentSelect) {
                $(guisDepartmentSelect).select2();
                $(guisDepartmentSelect).on('change', (event) => {
                    const val = $(event.target).val();
                    this.newCustomer.guis_department = val ? val : [];
                });
            }
        });
        
        // Add event listener for modal hide
        const customerModal = document.getElementById('customerModal');
        if (customerModal) {
            customerModal.addEventListener('hide.bs.modal', () => {
                this.resetCustomerData();
            });
        }
    }
}).mount('#app'); 