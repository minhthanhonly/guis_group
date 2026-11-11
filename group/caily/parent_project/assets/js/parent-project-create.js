const { createApp } = Vue;

createApp({
    mixins: [window.ParentProjectCustomersMixin],
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
                city: '',
                type1: '',
                type2: '',
                type3: '',
                request_type: '',
                desired_delivery_date: '',
                request_design: false,
                request_equipment: false,
                request_3d_equipment: false,
                request_energy_saving: false,
                request_3d: false,
                materials_layout: false,
                materials_rental: false,
                materials_contract: false,
                materials_tac: false,
                materials_other: false,
                materials_memo: '',
                application_review_agency: '',
                bels_zeh_grade: '',
                energy_materials_memo: '',
                structural_office: '',
                notes: '',
                status: 'draft'
            },
            energyMaterialOptions: [
                '意匠図',
                '設備図',
                '配置図',
                '案内図',
                '敷地求積図',
                '確認申請書',
                '設備仕様書',
                'その他'
            ],
            energyMaterialFlags: {
                '意匠図': false,
                '設備図': false,
                '配置図': false,
                '案内図': false,
                '敷地求積図': false,
                '確認申請書': false,
                '設備仕様書': false,
                'その他': false
            },
            sharedHousingFeatureLabel: '共同住宅（共用部計算なし）',
            featureOptions: [
                '施主宅付き',
                'マルチルームあり',
                '店舗併用住宅',
                '事務所併用住宅',
                '複合用途',
                '共同住宅（共用部計算なし）',
                '補助金案件',
                '増築',
                '用途変更'
            ],
            featureFlags: {
                '施主宅付き': false,
                'マルチルームあり': false,
                '店舗併用住宅': false,
                '事務所併用住宅': false,
                '複合用途': false,
                '共同住宅（共用部計算なし）': false,
                '補助金案件': false,
                '増築': false,
                '用途変更': false
            },
            belsZehGradeOptions: [
                { value: '', label: '' },
                { value: '0', label: '0 : 該当なし（非ZEH）' },
                { value: '1', label: '1 : ZEH' },
                { value: '2', label: '2 : Nearly ZEH' },
                { value: '3', label: '3 : ZEH Oriented' },
                { value: '4', label: '4 : ZEH-M' },
                { value: '5', label: '5 : Nearly ZEH-M' },
                { value: '6', label: '6 : ZEH-M Oriented' },
                { value: '7', label: '7 : ZEH Ready' },
                { value: '8', label: '8 : DK-ZEH' },
                { value: '9', label: '9 : 関西限定DK-ZEH' }
            ],
            nonResidentialMainUseOptions: [
                { value: '08340', label: '08340 工場' },
                { value: '08440', label: '08440 物品販売店舗' },
                { value: '08450', label: '08450 飲食店' },
                { value: '08470', label: '08470 事務所' },
                { value: '08510', label: '08510 倉庫業を営む倉庫' },
                { value: '08520', label: '08520 倉庫業を営まない倉庫' },
                { value: '08400', label: '08400 ホテル・旅館' },
                { value: '08260', label: '08260 病院' },
                { value: '08550', label: '08550 集会場・公会堂' },
                { value: '08990', label: '08990 その他' }
            ],
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
                desired_delivery_date: '',
                requests: ''
            },
            type1Tagify: null,
            type2Tagify: null,
            applicationReviewAgencyTagify: null,
            constructionBranchTagify: null,
            scaleTagify: null,
            cityTagify: null,
            structuralOfficeTagify: null,
            applicationReviewAgencyWhitelist: (window.ParentProjectReviewAgency
                && Array.isArray(window.ParentProjectReviewAgency.DEFAULT_WHITELIST))
                ? window.ParentProjectReviewAgency.DEFAULT_WHITELIST.slice()
                : [
                    '日本ERI',
                    'ERIソリューション',
                    'ビューローベリタスジャパン',
                    '日本住宅保証検査機構（JIO）',
                    'ハウスプラス住宅保証',
                    '東京建築検査機構（TBTC）',
                    'ユーディーアイ確認検査',
                    '日本確認検査センター',
                    'JCI'
                ],
            // Customer modal data
            categories: [],
            departments: [],
            departmentNameMap: {},
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
                branch: '',
                guis_department: ''
            },
            selectedCustomer: null,
            updatingCustomer: false,
            duplicateDismissedKey: '',
            duplicatePromptPromise: null,
        }
    },
    computed: {
        hasNonResidentialType() {
            return String(this.parentProject.type2 || '')
                .split(',')
                .map(function(v) { return v.trim(); })
                .filter(Boolean)
                .includes('非住宅');
        },
        hasSharedHousingType() {
            return String(this.parentProject.type2 || '')
                .split(',')
                .map(function(v) { return v.trim(); })
                .filter(Boolean)
                .includes('共同');
        },
        visibleFeatureOptions() {
            const self = this;
            return this.featureOptions.filter(function(opt) {
                if (opt === self.sharedHousingFeatureLabel) {
                    return self.hasSharedHousingType;
                }
                return true;
            });
        },
        hasEnergySavingRequest() {
            return !!this.parentProject.request_energy_saving;
        }
    },
    watch: {
        'parentProject.type2'() {
            if (!this.hasSharedHousingType && this.featureFlags) {
                this.featureFlags[this.sharedHousingFeatureLabel] = false;
            }
        },
        'parentProject.request_energy_saving'(enabled) {
            if (!enabled) {
                this.parentProject.application_review_agency = '';
                this.parentProject.bels_zeh_grade = '';
                this.parentProject.energy_materials_memo = '';
                this.energyMaterialFlags = this.createEmptyEnergyMaterialFlags();
                this.destroyApplicationReviewAgencyTagify();
            } else {
                this.$nextTick(() => this.initApplicationReviewAgencyTagify());
            }
        },
        'energyMaterialFlags.その他'(enabled) {
            if (!enabled && this.parentProject) {
                this.parentProject.energy_materials_memo = '';
            }
        },
        'parentProject.materials_other'(enabled) {
            if (!enabled && this.parentProject) {
                this.parentProject.materials_memo = '';
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
                desired_delivery_date: '',
                requests: ''
            };
            let valid = true;
            
            // At least one customer; the primary one fills company / branch / contact
            if (!this.validateCustomerList()) {
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

            if (!this.hasAnyRequestSelected()) {
                this.validationErrors.requests = '依頼は1つ以上選択してください';
                valid = false;
            }
            
            return valid;
        },
        hasAnyRequestSelected() {
            return !!(
                this.parentProject.request_design
                || this.parentProject.request_equipment
                || this.parentProject.request_3d_equipment
                || this.parentProject.request_energy_saving
                || this.parentProject.request_3d
            );
        },
        clearRequestsValidation() {
            if (this.hasAnyRequestSelected()) {
                this.validationErrors.requests = '';
            }
        },
        tText(key) {
            if (typeof i18next !== 'undefined' && typeof i18next.t === 'function') {
                return i18next.t(key) || key;
            }
            return key;
        },
        escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },
        getDuplicateCheckKey() {
            const construction = String(this.parentProject.construction_number || '').trim();
            const projectName = String(this.parentProject.project_name || '').trim();
            return construction + '|' + projectName;
        },
        getDuplicateMatchedLabels(duplicate) {
            const labels = [];
            const inputConstruction = String(this.parentProject.construction_number || '').trim();
            const inputName = String(this.parentProject.project_name || '').trim();
            const matchedBy = Array.isArray(duplicate?.matched_by) ? duplicate.matched_by : [];
            const matchedConstruction = matchedBy.includes('construction_number')
                || (inputConstruction && String(duplicate?.construction_number || '').trim() === inputConstruction);
            const matchedName = matchedBy.includes('project_name')
                || (inputName && String(duplicate?.project_name || '').trim() === inputName);
            if (matchedName) {
                labels.push(this.tText('お施主様名'));
            }
            if (matchedConstruction) {
                labels.push(this.tText('工事番号'));
            }
            return labels;
        },
        buildDuplicateWarningHtml(duplicate) {
            const matchedLabels = this.getDuplicateMatchedLabels(duplicate);
            const rows = [
                matchedLabels.length
                    ? `<div><strong>${this.escapeHtml(this.tText('一致項目'))}:</strong> ${this.escapeHtml(matchedLabels.join(' / '))}</div>`
                    : '',
                `<div><strong>${this.escapeHtml(this.tText('お施主様名'))}:</strong> ${this.escapeHtml(duplicate?.project_name || '-')}</div>`,
                `<div><strong>${this.escapeHtml(this.tText('工事番号'))}:</strong> ${this.escapeHtml(duplicate?.construction_number || '-')}</div>`,
                `<div><strong>${this.escapeHtml(this.tText('管理番号'))}:</strong> ${this.escapeHtml(duplicate?.project_number || '-')}</div>`
            ].filter(Boolean).join('');
            return `
                <p class="mb-2">${this.escapeHtml(this.tText('既に同じ建物があります。その建物を使用しますか？'))}</p>
                <div class="text-start small">${rows}</div>
            `;
        },
        async findDuplicateBuilding() {
            const construction = String(this.parentProject.construction_number || '').trim();
            const projectName = String(this.parentProject.project_name || '').trim();
            if (!construction && !projectName) {
                return null;
            }
            try {
                const response = await axios.get('/api/index.php', {
                    params: {
                        model: 'parentproject',
                        method: 'findDuplicate',
                        construction_number: construction,
                        project_name: projectName
                    }
                });
                const data = response && response.data ? response.data : {};
                if (data.status === 'success' && data.duplicate && data.duplicate.id) {
                    if (!Array.isArray(data.duplicate.matched_by) && Array.isArray(data.matched_by)) {
                        data.duplicate.matched_by = data.matched_by;
                    }
                    return data.duplicate;
                }
            } catch (error) {
                console.error('Error checking duplicate parent project:', error);
            }
            return null;
        },
        async confirmUseExistingBuilding(duplicate) {
            const message = this.tText('既に同じ建物があります。その建物を使用しますか？');
            if (typeof Swal === 'undefined' || !Swal.fire) {
                return window.confirm(message);
            }
            const result = await Swal.fire({
                icon: 'warning',
                title: this.tText('確認'),
                html: this.buildDuplicateWarningHtml(duplicate || {}),
                showCancelButton: true,
                confirmButtonText: this.tText('はい'),
                cancelButtonText: this.tText('いいえ'),
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#6c757d',
                reverseButtons: true
            });
            return !!(result && result.isConfirmed);
        },
        async promptDuplicateBuildingIfNeeded() {
            if (this.duplicatePromptPromise) {
                return this.duplicatePromptPromise;
            }
            this.duplicatePromptPromise = (async () => {
                const checkKey = this.getDuplicateCheckKey();
                if (checkKey === '|' || checkKey === this.duplicateDismissedKey) {
                    return { duplicate: null, useExisting: false };
                }
                const duplicate = await this.findDuplicateBuilding();
                if (!duplicate || !duplicate.id) {
                    return { duplicate: null, useExisting: false };
                }
                const useExisting = await this.confirmUseExistingBuilding(duplicate);
                if (useExisting) {
                    return { duplicate: duplicate, useExisting: true };
                }
                this.duplicateDismissedKey = checkKey;
                return { duplicate: duplicate, useExisting: false };
            })();
            try {
                return await this.duplicatePromptPromise;
            } finally {
                this.duplicatePromptPromise = null;
            }
        },
        async onDuplicateFieldBlur() {
            const result = await this.promptDuplicateBuildingIfNeeded();
            if (result.useExisting && result.duplicate && result.duplicate.id) {
                window.location.href = 'detail.php?id=' + encodeURIComponent(result.duplicate.id);
            }
        },
        async saveParentProject() {
            if (!this.validateParentProjectForm()) {
                return;
            }
            const duplicateResult = await this.promptDuplicateBuildingIfNeeded();
            if (duplicateResult.useExisting && duplicateResult.duplicate && duplicateResult.duplicate.id) {
                window.location.href = 'detail.php?id=' + encodeURIComponent(duplicateResult.duplicate.id);
                return;
            }
            
            try {
                this.syncPrimaryCustomerFields();
                const formData = new FormData();
                formData.append('company_name', this.parentProject.company_name || '');
                formData.append('branch_name', this.parentProject.branch_name || '');
                formData.append('contact_name', this.parentProject.contact_name || '');
                formData.append('customer_id', this.parentProject.customer_id || '');
                // Full customer list; the server takes the primary customer's names from the master
                this.appendCustomersToFormData(formData);
                formData.append('guis_receiver', this.parentProject.guis_receiver || '');
                formData.append('request_date', this.parentProject.request_date || '');
                formData.append('construction_number', this.parentProject.construction_number || '');
                formData.append('project_number', this.parentProject.project_number || '');
                formData.append('project_name', this.parentProject.project_name || '');
                formData.append('construction_branch', this.parentProject.construction_branch || '');
                formData.append('scale', this.parentProject.scale || '');
                formData.append('city', this.parentProject.city || '');
                formData.append('type1', this.parentProject.type1 || '');
                formData.append('type2', this.parentProject.type2 || '');
                formData.append('type3', this.hasNonResidentialType ? (this.parentProject.type3 || '') : '');
                formData.append('request_type', this.parentProject.request_type || '');
                formData.append('desired_delivery_date', this.parentProject.desired_delivery_date || '');
                // Convert checkbox requests to comma-separated string
                const requestsArray = [];
                if (this.parentProject.request_design) requestsArray.push('意匠');
                if (this.parentProject.request_equipment) requestsArray.push('設備');
                if (this.parentProject.request_3d_equipment) requestsArray.push('3D設備');
                if (this.parentProject.request_energy_saving) requestsArray.push('省エネ');
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
                formData.append(
                    'materials_memo',
                    this.parentProject.materials_other ? (this.parentProject.materials_memo || '') : ''
                );
                formData.append('features', this.buildFeaturesString());
                formData.append(
                    'application_review_agency',
                    this.hasEnergySavingRequest ? (this.parentProject.application_review_agency || '') : ''
                );
                formData.append(
                    'bels_zeh_grade',
                    this.hasEnergySavingRequest ? (this.parentProject.bels_zeh_grade || '') : ''
                );
                formData.append(
                    'energy_materials',
                    this.hasEnergySavingRequest ? this.buildEnergyMaterialsString() : ''
                );
                formData.append(
                    'energy_materials_memo',
                    this.hasEnergySavingRequest && this.energyMaterialFlags['その他']
                        ? (this.parentProject.energy_materials_memo || '')
                        : ''
                );
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
                    showParentProjectError('建物の登録に失敗しました。', response && response.data);
                }
            } catch (error) {
                console.error('Error creating parent project:', error);
                showParentProjectError('建物の作成に失敗しました。', error);
            }
        },
        initSelect2() {
            // Initialize GUIS Receiver Select2
            this.initGuisReceiverSelect2();
            
            // Initialize date pickers
            this.initDatePickers();
            
            // Initialize Tagify
            this.initTagify();
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
        destroyApplicationReviewAgencyTagify() {
            if (this.applicationReviewAgencyTagify) {
                try { this.applicationReviewAgencyTagify.destroy(); } catch (e) { /* ignore */ }
                this.applicationReviewAgencyTagify = null;
            }
            const input = document.querySelector('#application_review_agency_tags');
            if (input) {
                input.value = '';
                if (input._tagify) {
                    try { input._tagify.destroy(); } catch (e) { /* ignore */ }
                    input._tagify = null;
                }
            }
        },
        async refreshApplicationReviewAgencyWhitelist() {
            if (window.ParentProjectReviewAgency && typeof window.ParentProjectReviewAgency.loadMergedWhitelist === 'function') {
                this.applicationReviewAgencyWhitelist = await window.ParentProjectReviewAgency.loadMergedWhitelist();
            }
            return this.applicationReviewAgencyWhitelist;
        },
        async initApplicationReviewAgencyTagify() {
            const input = document.querySelector('#application_review_agency_tags');
            if (!input || !window.Tagify) return;
            await this.refreshApplicationReviewAgencyWhitelist();
            this.destroyApplicationReviewAgencyTagify();
            input.value = '';
            this.applicationReviewAgencyTagify = new Tagify(input, {
                whitelist: this.applicationReviewAgencyWhitelist.slice(),
                enforceWhitelist: false,
                maxTags: 1,
                dropdown: {
                    maxItems: 40,
                    classname: 'tags-look-review-agency',
                    enabled: 0,
                    closeOnSelect: true
                }
            });
            const updateAgency = () => {
                this.parentProject.application_review_agency = this.applicationReviewAgencyTagify.value
                    .map(tag => tag.value)
                    .join(',');
            };
            this.applicationReviewAgencyTagify.on('add', (e) => {
                updateAgency();
                const value = e && e.detail && e.detail.data ? e.detail.data.value : '';
                if (value && window.ParentProjectReviewAgency) {
                    window.ParentProjectReviewAgency.saveLocalCustom(value);
                    this.applicationReviewAgencyWhitelist = window.ParentProjectReviewAgency.buildWhitelist(
                        this.applicationReviewAgencyWhitelist
                    );
                    this.applicationReviewAgencyTagify.settings.whitelist = this.applicationReviewAgencyWhitelist.slice();
                    this.applicationReviewAgencyTagify.whitelist = this.applicationReviewAgencyWhitelist.slice();
                }
            });
            this.applicationReviewAgencyTagify.on('remove', updateAgency);
            const current = String(this.parentProject.application_review_agency || '').trim();
            if (current) {
                this.applicationReviewAgencyTagify.addTags(current);
            }
        },
        async loadTagifyWhitelists() {
            const T = window.ParentProjectTagifyOptions;
            if (!T || typeof T.loadMergedWhitelist !== 'function') {
                return {
                    type2: ['長屋', '共同', '戸建', '非住宅', '集合'],
                    scale: ['W2F3J', 'W2F4J', 'W2F5J', 'W2F6J', 'W3F3J', 'W3F4J', 'W3F5J', 'W3F6J', 'RC3F3J', 'RC3F4J', 'RC3F5J', 'RC3F6J', 'RC3F7J', 'RC3F8J'],
                    city: [],
                    structural_office: []
                };
            }
            const [type2, scale, city, structural_office] = await Promise.all([
                T.loadMergedWhitelist('type2'),
                T.loadMergedWhitelist('scale'),
                T.loadMergedWhitelist('city'),
                T.loadMergedWhitelist('structural_office')
            ]);
            return { type2, scale, city, structural_office };
        },
        bindTagifyField(tagify, field, onUpdate) {
            if (!tagify) return;
            if (window.ParentProjectTagifyOptions && typeof window.ParentProjectTagifyOptions.bindSaveOnAdd === 'function') {
                window.ParentProjectTagifyOptions.bindSaveOnAdd(tagify, field, onUpdate);
            } else {
                tagify.on('add', onUpdate);
                tagify.on('remove', onUpdate);
            }
        },
        async initTagify() {
            // Initialize Tagify after Vue is mounted
            await this.$nextTick();
            if (!window.Tagify) return;
            const whitelists = await this.loadTagifyWhitelists();

            // --- Tagify for Type1 (種類1) ---
            const type1Input = document.querySelector('#type1_tags');
            if (type1Input && !type1Input._tagify) {
                if (this.type1Tagify) {
                    try { this.type1Tagify.destroy(); } catch (e) { /* ignore */ }
                }
                this.type1Tagify = new Tagify(type1Input, {
                    whitelist: ['TAC', '特注'],
                    maxTags: 5,
                    dropdown: { maxItems: 20, classname: 'tags-look-type1', enabled: 0, closeOnSelect: true }
                });
                const updateType1 = () => {
                    this.parentProject.type1 = this.type1Tagify.value.map(tag => tag.value).join(',');
                };
                this.type1Tagify.on('add', updateType1);
                this.type1Tagify.on('remove', updateType1);
            }

            // --- Tagify for Type2 (住宅種類) ---
            const type2Input = document.querySelector('#type2_tags');
            if (type2Input && !type2Input._tagify) {
                if (this.type2Tagify) {
                    try { this.type2Tagify.destroy(); } catch (e) { /* ignore */ }
                }
                this.type2Tagify = new Tagify(type2Input, {
                    whitelist: whitelists.type2.slice(),
                    enforceWhitelist: false,
                    maxTags: 1,
                    dropdown: { maxItems: 40, classname: 'tags-look-type2', enabled: 0, closeOnSelect: true }
                });
                this.bindTagifyField(this.type2Tagify, 'type2', () => {
                    this.parentProject.type2 = this.type2Tagify.value.map(tag => tag.value).join(',');
                    if (!this.hasNonResidentialType) {
                        this.parentProject.type3 = '';
                    }
                });
            }

            // --- Tagify for Construction Branch (工事支店) ---
            const constructionBranchInput = document.querySelector('#construction_branch_tags');
            if (constructionBranchInput && !constructionBranchInput._tagify) {
                if (this.constructionBranchTagify) {
                    try { this.constructionBranchTagify.destroy(); } catch (e) { /* ignore */ }
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
                    dropdown: { maxItems: 20, classname: 'tags-look-construction-branch', enabled: 0, closeOnSelect: true }
                });
                const updateConstructionBranch = () => {
                    this.parentProject.construction_branch = this.constructionBranchTagify.value.map(tag => tag.value).join(',');
                };
                this.constructionBranchTagify.on('add', updateConstructionBranch);
                this.constructionBranchTagify.on('remove', updateConstructionBranch);
            }

            // --- Tagify for Scale (建物規模) ---
            const scaleInput = document.querySelector('#scale_tags');
            if (scaleInput && !scaleInput._tagify) {
                if (this.scaleTagify) {
                    try { this.scaleTagify.destroy(); } catch (e) { /* ignore */ }
                }
                this.scaleTagify = new Tagify(scaleInput, {
                    whitelist: whitelists.scale.slice(),
                    enforceWhitelist: false,
                    maxTags: 5,
                    dropdown: { maxItems: 40, classname: 'tags-look-scale', enabled: 0, closeOnSelect: true }
                });
                this.bindTagifyField(this.scaleTagify, 'scale', () => {
                    this.parentProject.scale = this.scaleTagify.value.map(tag => tag.value).join(',');
                });
            }

            // --- Tagify for City (市区町村) ---
            const cityInput = document.querySelector('#city_tags');
            if (cityInput && !cityInput._tagify) {
                if (this.cityTagify) {
                    try { this.cityTagify.destroy(); } catch (e) { /* ignore */ }
                }
                this.cityTagify = new Tagify(cityInput, {
                    whitelist: whitelists.city.slice(),
                    enforceWhitelist: false,
                    maxTags: 1,
                    dropdown: { maxItems: 40, classname: 'tags-look-city', enabled: 0, closeOnSelect: true }
                });
                this.bindTagifyField(this.cityTagify, 'city', () => {
                    this.parentProject.city = this.cityTagify.value.map(tag => tag.value).join(',');
                });
                const cityVal = String(this.parentProject.city || '').trim();
                if (cityVal) this.cityTagify.addTags(cityVal);
            }

            // --- Tagify for Structural office (構造事務所) ---
            const structuralOfficeInput = document.querySelector('#structural_office_tags');
            if (structuralOfficeInput && !structuralOfficeInput._tagify) {
                if (this.structuralOfficeTagify) {
                    try { this.structuralOfficeTagify.destroy(); } catch (e) { /* ignore */ }
                }
                this.structuralOfficeTagify = new Tagify(structuralOfficeInput, {
                    whitelist: whitelists.structural_office.slice(),
                    enforceWhitelist: false,
                    maxTags: 1,
                    dropdown: { maxItems: 40, classname: 'tags-look-structural-office', enabled: 0, closeOnSelect: true }
                });
                this.bindTagifyField(this.structuralOfficeTagify, 'structural_office', () => {
                    this.parentProject.structural_office = this.structuralOfficeTagify.value.map(tag => tag.value).join(',');
                });
                const officeVal = String(this.parentProject.structural_office || '').trim();
                if (officeVal) this.structuralOfficeTagify.addTags(officeVal);
            }
        },

        // Customer modal methods
        async loadDepartments() {
            try {
                const response = await axios.get('/api/index.php?model=department&method=list_department');
                this.departments = response.data || [];
                const map = {};
                this.departments.forEach((department) => {
                    if (department && department.id != null) {
                        map[String(department.id)] = department.name || '';
                    }
                });
                this.departmentNameMap = map;
            } catch (error) {
                console.error('Error loading departments:', error);
                showMessage('部署の読み込みに失敗しました。', true);
            }
        },

        async loadCategories() {
            try {
                const response = await axios.get('/api/index.php?model=customer&method=list_category');
                this.categories = response.data;
                if (!this.newCustomer.category_id) {
                    this.newCustomer.category_id = 2;
                }
            } catch (error) {
                console.error('Error loading categories:', error);
                showMessage('カテゴリーの読み込みに失敗しました。', true);
            }
        },

        async showCustomerInfoModal(customer) {
            await this.loadDepartments();
            if (this.categories.length === 0) {
                await this.loadCategories();
            }

            if (typeof customer.guis_department === 'string') {
                customer.guis_department = customer.guis_department ? customer.guis_department.split(',').map((id) => id.trim()) : [];
            } else if (!Array.isArray(customer.guis_department)) {
                customer.guis_department = [];
            }

            this.selectedCustomer = { ...customer };
            $('#customerInfoModal').modal('show');

            setTimeout(() => {
                const selectElement = $(this.$refs.customerGuisDepartmentSelect);
                if (selectElement.length) {
                    if (selectElement.hasClass('select2-hidden-accessible')) {
                        selectElement.select2('destroy');
                    }
                    selectElement.select2({
                        placeholder: '部署を選択してください',
                        allowClear: true,
                        width: '100%',
                        dropdownParent: $('#customerInfoModal')
                    });
                    selectElement.val(this.selectedCustomer.guis_department).trigger('change');
                    selectElement.off('change.customerInfoModal').on('change.customerInfoModal', (event) => {
                        const val = $(event.target).val();
                        this.selectedCustomer.guis_department = val ? val : [];
                    });
                }
            }, 300);
        },

        async updateCustomer() {
            this.customerErrors = { company_name: '', name: '', branch: '', guis_department: '' };
            let hasError = false;

            if (!this.selectedCustomer.company_name) {
                this.customerErrors.company_name = '会社名は必須です。';
                hasError = true;
            }
            if (!this.selectedCustomer.name) {
                this.customerErrors.name = '担当者名は必須です。';
                hasError = true;
            }
            if (!this.selectedCustomer.branch || this.selectedCustomer.branch.trim() === '') {
                this.customerErrors.branch = '支店名は必須です。';
                hasError = true;
            }
            if (!this.selectedCustomer.guis_department || this.selectedCustomer.guis_department.length === 0) {
                this.customerErrors.guis_department = '自社担当部署名は必須です。';
                hasError = true;
            }
            if (hasError) {
                return;
            }

            const confirmResult = await Swal.fire({
                title: '確認',
                text: 'お客様情報を更新すると、このお客様の情報を利用している他の建物の情報もすべて更新されます。更新しますか？',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '更新する',
                cancelButtonText: 'キャンセル',
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#6c757d'
            });
            if (!confirmResult.isConfirmed) {
                return;
            }

            this.updatingCustomer = true;

            try {
                const customerData = { ...this.selectedCustomer };
                if (Array.isArray(customerData.guis_department)) {
                    customerData.guis_department = customerData.guis_department.join(',');
                }

                const response = await axios.post(
                    `/api/index.php?model=customer&method=edit_customer&id=${this.selectedCustomer.id}`,
                    customerData,
                    { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }
                );

                if (response.data.status === 'success') {
                    showMessage('顧客情報を更新しました。');

                    this.applyCustomerMasterUpdateToList(this.selectedCustomer);
                    this.syncPrimaryCustomerFields();
                    this.customerSearchLoaded = false;

                    $('#customerInfoModal').modal('hide');
                    this.selectedCustomer = null;
                } else {
                    showParentProjectError(response.data.message_code || '顧客情報の更新に失敗しました。', response && response.data);
                }
            } catch (error) {
                console.error('Error updating customer:', error);
                showParentProjectError('顧客情報の更新に失敗しました。', error);
            } finally {
                this.updatingCustomer = false;
            }
        },

        searchAddressSelectedCustomer() {
            if (!this.selectedCustomer) {
                return;
            }
            const postalCode = this.selectedCustomer.zip;
            if (postalCode && postalCode.length >= 7) {
                const apiUrl = `https://zipcloud.ibsnet.co.jp/api/search?zipcode=${postalCode}`;
                axios.get(apiUrl)
                    .then((response) => {
                        if (response.data.results && response.data.results.length > 0) {
                            const result = response.data.results[0];
                            this.selectedCustomer.address1 = result.address1 || '';
                            this.selectedCustomer.address2 = result.address2 || '';
                        } else {
                            showMessage('郵便番号が見つかりません。', true);
                        }
                    })
                    .catch((error) => {
                        console.error('Error searching address:', error);
                        showMessage('住所の検索に失敗しました。', true);
                    });
            } else {
                showMessage('郵便番号が正しくありません。', true);
            }
        },

        openNewCustomerModal() {
            this.resetCustomerData({
                company_name: this.parentProject.company_name,
                branch: this.parentProject.branch_name,
            });
            $('#customerModal').modal('show');
        },

        resetCustomerData(prefill = {}) {
            const companyName = (prefill.company_name || '').trim();
            const branchName = (prefill.branch || '').trim();
            this.newCustomer = {
                company_name: companyName || '大東建託株式会社',
                company_name_kana: '',
                name: '',
                name_kana: '',
                branch: branchName || '本社',
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
                category_id: 2,
                guis_department: []
            };
            this.customerErrors = { company_name: '', name: '', branch: '', guis_department: '' };
        },

        async saveCustomer() {
            // Reset errors
            this.customerErrors = { company_name: '', name: '', branch: '', guis_department: '' };
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
            if (!this.newCustomer.category_id) {
                this.newCustomer.category_id = 2;
            }

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
                    
                    const created = {
                        id: response.data.id || null,
                        company_name: this.newCustomer.company_name,
                        branch: this.newCustomer.branch,
                        name: this.newCustomer.name
                    };

                    $('#customerModal').modal('hide');
                    this.resetCustomerData();

                    // Add the new customer to the customer list (first one becomes the primary)
                    await this.addNewCustomerToList(created);
                    this.loadCategories();
                } else {
                    showParentProjectError(response.data.message_code || '顧客の保存に失敗しました。', response && response.data);
                }
            } catch (error) {
                console.error('Error saving customer:', error);
                showParentProjectError('顧客の保存に失敗しました。', error);
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

        parseCopiedRequests(requests) {
            const list = String(requests || '').split(',').map((r) => r.trim()).filter(Boolean);
            this.parentProject.request_design = list.includes('意匠');
            this.parentProject.request_equipment = list.includes('設備');
            this.parentProject.request_3d_equipment = list.includes('3D設備');
            this.parentProject.request_energy_saving = list.includes('省エネ');
            this.parentProject.request_3d = list.includes('3D');
        },
        parseCopiedMaterials(materials) {
            const list = String(materials || '').split(',').map((m) => m.trim()).filter(Boolean);
            this.parentProject.materials_layout = list.includes('配置図');
            this.parentProject.materials_rental = list.includes('家賃審査書');
            this.parentProject.materials_contract = list.includes('契約図');
            this.parentProject.materials_tac = list.includes('TAC図');
            this.parentProject.materials_other = list.includes('その他');
        },
        createEmptyFeatureFlags() {
            const flags = {};
            this.featureOptions.forEach((opt) => { flags[opt] = false; });
            return flags;
        },
        buildFeaturesString() {
            return this.visibleFeatureOptions.filter((opt) => this.featureFlags[opt]).join(',');
        },
        parseCopiedFeatures(features) {
            const list = String(features || '').split(',').map((f) => f.trim()).filter(Boolean);
            const flags = this.createEmptyFeatureFlags();
            list.forEach((item) => {
                if (Object.prototype.hasOwnProperty.call(flags, item)) {
                    flags[item] = true;
                }
            });
            this.featureFlags = flags;
        },
        createEmptyEnergyMaterialFlags() {
            const flags = {};
            this.energyMaterialOptions.forEach((opt) => { flags[opt] = false; });
            return flags;
        },
        buildEnergyMaterialsString() {
            return this.energyMaterialOptions.filter((opt) => this.energyMaterialFlags[opt]).join(',');
        },
        parseCopiedEnergyMaterials(energyMaterials) {
            const list = String(energyMaterials || '').split(',').map((m) => m.trim()).filter(Boolean);
            const flags = this.createEmptyEnergyMaterialFlags();
            list.forEach((item) => {
                if (Object.prototype.hasOwnProperty.call(flags, item)) {
                    flags[item] = true;
                }
            });
            this.energyMaterialFlags = flags;
        },
        applyTagifyValuesFromParentProject() {
            const apply = (tagify, value) => {
                if (!tagify) {
                    return;
                }
                try {
                    tagify.removeAllTags();
                    const tags = String(value || '').split(',').map((s) => s.trim()).filter(Boolean);
                    if (tags.length) {
                        tagify.addTags(tags);
                    }
                } catch (error) {
                    console.error('Error applying Tagify values:', error);
                }
            };
            apply(this.scaleTagify, this.parentProject.scale);
            apply(this.type1Tagify, this.parentProject.type1);
            apply(this.type2Tagify, this.parentProject.type2);
            apply(this.cityTagify, this.parentProject.city);
            apply(this.structuralOfficeTagify, this.parentProject.structural_office);
            if (this.hasEnergySavingRequest) {
                this.$nextTick(() => {
                    this.initApplicationReviewAgencyTagify();
                });
            }
            apply(this.constructionBranchTagify, this.parentProject.construction_branch);
        },
        setSelect2Value($select, value, text) {
            if (!$select || !$select.length || value === undefined || value === null || value === '') {
                return;
            }
            const stringValue = String(value);
            if (!$select.find('option').filter(function () { return String($(this).val()) === stringValue; }).length) {
                $select.append(new Option(text || stringValue, stringValue, true, true));
            }
            $select.val(stringValue);
            if ($select.data('select2')) {
                $select.trigger('change');
            }
        },
        // Customers of a copied building: full list when present, otherwise the single (primary) customer
        async applyCopiedCustomers(data) {
            const source = data || {};
            if (Array.isArray(source.customers) && source.customers.length > 0) {
                this.setCustomerListFromServer(source.customers);
            } else if (source.customer_id) {
                const customer = await this.fetchCustomerMasterById(source.customer_id);
                this.setCustomerListFromServer(customer
                    ? [{ ...customer, customer_id: customer.id, is_primary: 1 }]
                    : []);
            } else if (source.company_name || source.contact_name) {
                this.setCustomerListFromServer([{
                    customer_id: null,
                    is_primary: 1,
                    company_name: source.company_name || '',
                    branch_name: source.branch_name || '',
                    contact_name: source.contact_name || ''
                }]);
            } else {
                this.setCustomerListFromServer([]);
            }
            this.syncPrimaryCustomerFields();
        },
        async loadCopiedParentProjectData() {
            const raw = sessionStorage.getItem('copyParentProjectData');
            if (!raw) {
                return false;
            }
            try {
                const data = JSON.parse(raw);
                sessionStorage.removeItem('copyParentProjectData');

                this.parentProject.construction_number = data.construction_number || '';
                this.parentProject.project_name = data.project_name || '';
                this.parentProject.construction_branch = data.construction_branch || '';
                this.parentProject.scale = data.scale || '';
                this.parentProject.type1 = data.type1 || '';
                this.parentProject.type2 = data.type2 || '';
                this.parentProject.type3 = data.type3 || '';
                this.parentProject.request_type = data.request_type || '';
                this.parentProject.structural_office = data.structural_office || '';
                this.parentProject.application_review_agency = data.application_review_agency || '';
                this.parentProject.bels_zeh_grade = (data.bels_zeh_grade != null && data.bels_zeh_grade !== '')
                    ? String(data.bels_zeh_grade)
                    : '';
                this.parentProject.materials_memo = data.materials_memo || '';
                this.parentProject.energy_materials_memo = data.energy_materials_memo || '';
                this.parentProject.notes = data.notes || '';
                this.parentProject.status = data.status || 'draft';
                this.parentProject.desired_delivery_date = '';
                this.parentProject.guis_receiver = window.currentUser?.id || this.parentProject.guis_receiver || '';

                this.parseCopiedRequests(data.requests);
                this.parseCopiedMaterials(data.materials);
                this.parseCopiedFeatures(data.features);
                this.parseCopiedEnergyMaterials(data.energy_materials);
                this.clearRequestsValidation();

                await new Promise((resolve) => setTimeout(resolve, 300));
                await this.applyCopiedCustomers(data);

                this.setCurrentDateTime();
                this.applyTagifyValuesFromParentProject();

                const $guisReceiver = $('#guis_receiver');
                if ($guisReceiver.length && this.parentProject.guis_receiver) {
                    this.setSelect2Value(
                        $guisReceiver,
                        this.parentProject.guis_receiver,
                        window.currentUser?.name || this.parentProject.guis_receiver
                    );
                }

                if (typeof showMessage === 'function') {
                    showMessage(this.tText('コピーした建物データを読み込みました。'));
                }
                return true;
            } catch (error) {
                console.error('Error loading copied parent project data:', error);
                sessionStorage.removeItem('copyParentProjectData');
                if (typeof showMessage === 'function') {
                    showMessage(this.tText('コピーした建物データの読み込みに失敗しました。'), true);
                }
                return false;
            }
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
            type3: '',
            request_type: '',
            desired_delivery_date: '',
            request_design: false,
            request_equipment: false,
            request_3d_equipment: false,
            request_energy_saving: false,
            request_3d: false,
            materials_layout: false,
            materials_rental: false,
            materials_contract: false,
            materials_tac: false,
            materials_other: false,
            materials: '',
            materials_memo: '',
            application_review_agency: '',
            bels_zeh_grade: '',
            energy_materials_memo: '',
            structural_office: '',
            notes: '',
            status: 'draft'
        };
        this.featureFlags = this.createEmptyFeatureFlags();
        this.energyMaterialFlags = this.createEmptyEnergyMaterialFlags();
        
        // Auto-generate project number on page load
        await this.generateProjectNumber();
        
        // Load customer data
        this.loadDepartments();
        this.loadCategories();
        // Don't load customers initially - only when company and branch are selected
        
        // Initialize Select2 after Vue is mounted
        this.$nextTick(async () => {
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

            await this.loadCopiedParentProjectData();
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