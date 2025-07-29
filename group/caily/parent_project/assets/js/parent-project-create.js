const { createApp } = Vue;

createApp({
    data() {
        return {
            parentProject: {
                company_name: '',
                branch_name: '',
                contact_name: '',
                guis_receiver: '',
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
                project_name: ''
            },
            type1Tagify: null,
            type2Tagify: null,
            constructionBranchTagify: null
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
            
            try {
                const formData = new FormData();
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
                formData.append('status', this.parentProject.status || 'draft');
                
                const response = await axios.post('/api/index.php?model=parentproject&method=create', formData);
                if (response.data && response.data.status == 'success') {
                    // Show success message with SweetAlert2
                    Swal.fire({
                        title: '成功',
                        text: '親プロジェクトを作成しました。',
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
                    showMessage('親プロジェクトの作成に失敗しました。', true);
                }
            } catch (error) {
                console.error('Error creating parent project:', error);
                showMessage('親プロジェクトの作成に失敗しました。', true);
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
                    showMessage('プロジェクト番号を生成しました', false);
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
            });
        }
    },
    mounted() {
        // Initialize the form with default values
        this.parentProject = {
            company_name: '',
            branch_name: '',
            contact_name: '',
            guis_receiver: '',
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
        
        // Initialize Select2 after Vue is mounted
        this.$nextTick(() => {
            this.initSelect2();
        });
    }
}).mount('#app'); 