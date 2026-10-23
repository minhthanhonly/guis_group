const { createApp } = Vue;

const SERVER_TASK_TIMEZONE = 'Asia/Tokyo';
const VIETNAM_TASK_TIMEZONE = 'Asia/Ho_Chi_Minh';
const TASK_DATETIME_MOMENT_FORMAT = 'YYYY/M/D HH:mm';
const TASK_DATETIME_JA_DISPLAY_FORMAT = 'YYYY年M月D日 HH:mm';
const TASK_DATE_MOMENT_FORMAT = 'YYYY/M/D';
const TASK_DATE_JA_DISPLAY_FORMAT = 'YYYY年M月D日';
const TASK_DATETIME_PARSE_FORMATS = [
    'YYYY-MM-DD HH:mm:ss',
    'YYYY-MM-DD HH:mm',
    'YYYY/M/D HH:mm',
    'YYYY/MM/DD HH:mm',
    'YYYY/M/D H:mm',
    'YYYY/MM/DD H:mm'
];

createApp({
    data() {
        return {
            parentProjects: [],
            searchKeyword: '',
            requestFilter: '',
            requestFilterOptions: [
                { value: '', label: 'すべて', color: 'secondary' },
                { value: '意匠', label: '意匠', color: 'primary' },
                { value: '設備', label: '設備', color: 'info' },
                { value: '3D設備', label: '3D設備', color: 'success' },
                { value: '省エネ', label: '省エネ', color: 'lime' },
                { value: 'その他', label: 'その他', color: 'dark' }
            ],
            statusFilter: 'all',
            favoritesOnly: false,
            currentPage: 1,
            pageSize: 50,
            totalRecords: 0,
            loading: false,
            isProjectManager: typeof IS_PROJECT_MANAGER !== 'undefined' ? IS_PROJECT_MANAGER : false,
            isAdministrator: typeof IS_ADMIN !== 'undefined' ? !!IS_ADMIN : false,
            deletingParentProjectId: null,
            copyingParentProjectId: null,
            permission: [],
            sortColumn: 'created_at',
            sortDirection: 'DESC', // 'ASC' or 'DESC'
            selectedParentProject: null,
            childProjectsForModal: [],
            loadingChildProjects: false,
            // Column visibility
            availableColumns: [
                { key: 'project_number', label: '管理番号', visible: true },
                { key: 'project_name', label: 'お施主様名', visible: true },
                { key: 'construction_number', label: '工事番号', visible: true },
                { key: 'company_name', label: '会社名', visible: true },
                { key: 'branch_name', label: '支店名', visible: true },
                { key: 'contact_name', label: '担当様', visible: true },
                { key: 'scale', label: '規模', visible: false },
                { key: 'type1', label: '種類1', visible: false },
                { key: 'type2', label: '住宅種類', visible: false },
                { key: 'requests', label: '依頼', visible: true },
                { key: 'child_project_count', label: '件数', visible: true },
                { key: 'nearest_nouki', label: '納期', visible: true },
                { key: 'created_by_name', label: '作成者', visible: true },
                { key: 'notes', label: 'メモ', visible: true },
                { key: 'request_date', label: '依頼日', visible: false },
                { key: 'created_at', label: '作成日', visible: false },
            ],
            // Notes for parent projects (メモ)
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
            currentNoteParentProjectId: null,
            contextMenuParentProjectId: null,
            parentProjectContextMenuVisible: false,
            parentProjectContextMenuX: 0,
            parentProjectContextMenuY: 0,
            parentProjectContextMenuProject: null,
            highlightedParentProjectId: null,
            editParentProjectLoading: false,
            editParentProjectSaving: false,
            editParentProjectErrors: {
                company_name: '',
                project_name: ''
            },
            editingParentProject: {
                id: null,
                project_name: '',
                project_number: '',
                construction_number: '',
                company_name: '',
                branch_name: '',
                contact_name: '',
                customer_id: '',
                guis_receiver: '',
                request_date: '',
                scale: '',
                structural_office: '',
                type1: '',
                type2: '',
                type3: '',
                status: 'draft',
                requests: '',
                materials: '',
                features: '',
                application_review_agency: '',
                bels_zeh_grade: '0'
            },
            belsZehGradeOptions: [
                { value: '0', label: '0 : 該当なし（非ZEH）' },
                { value: '1', label: '1 : ZEH' },
                { value: '2', label: '2 : Nearly ZEH' },
                { value: '3', label: '3 : ZEH Oriented' },
                { value: '4', label: '4 : ZEH-M' },
                { value: '5', label: '5 : Nearly ZEH-M' },
                { value: '6', label: '6 : ZEH-M Oriented' }
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
            editParentRequestFlags: {
                design: false,
                equipment: false,
                equipment3d: false,
                energy: false,
                other: false
            },
            editParentMaterialFlags: {
                layout: false,
                rental: false,
                contract: false,
                tac: false,
                other: false
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
            editParentFeatureFlags: {
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
            editParentType1Tagify: null,
            editParentType2Tagify: null,
            editParentReviewAgencyTagify: null,
            applicationReviewAgencyWhitelist: [
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
            columnVisibilityStorageKey: 'parent_project_column_visibility',
            filterStorageKey: 'parent_project_list_filters',
            searchTimer: null,
            // Auto refresh
            autoRefreshTimer: null,
            autoRefreshInterval: 60000, // 60s
            statuses: [
                { value: 'draft', label: '下書き', color: 'secondary' },
                { value: 'under_contract', label: '契約中', color: 'info' },
                { value: 'in_progress', label: '進行中', color: 'primary' },
                { value: 'completed', label: '完了', color: 'success' },
                { value: 'cancelled', label: 'キャンセル', color: 'danger' }
            ],
            childProjectStatuses: [
                { value: 'draft', label: '受付', color: 'secondary' },
                { value: 'open', label: '納期検討', color: 'info' },
                { value: 'confirming', label: '仮受', color: 'info' },
                { value: 'quotation', label: '見積', color: 'info' },
                { value: 'contract', label: '請負', color: 'info' },
                { value: 'waiting_documents', label: '資料待ち', color: 'warning' },
                { value: 'in_progress', label: '進行中', color: 'primary' },
                { value: 'waiting_invoice', label: '請求待ち', color: 'dark' },
                { value: 'completed', label: '完了', color: 'success' },
                { value: 'paused', label: '一時停止', color: 'warning' },
                { value: 'cancelled', label: '中止', color: 'danger' }
            ]
        }
    },
    computed: {
        filteredParentProjects() {
            return this.parentProjects;
        },
        totalPages() {
            return Math.ceil(this.totalRecords / this.pageSize);
        },
        startRecord() {
            return (this.currentPage - 1) * this.pageSize + 1;
        },
        endRecord() {
            return Math.min(this.currentPage * this.pageSize, this.totalRecords);
        },
        visiblePages() {
            const pages = [];
            const maxVisible = 5;
            let start = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
            let end = Math.min(this.totalPages, start + maxVisible - 1);
            
            if (end - start + 1 < maxVisible) {
                start = Math.max(1, end - maxVisible + 1);
            }
            
            for (let i = start; i <= end; i++) {
                pages.push(i);
            }
            
            return pages;
        },
        canManagePriceList() {
            return this.hasDirectorPermission('project_director_stat');
        },
        canCreateParentProject() {
            return this.hasDepartmentPermission('project_add');
        },
        editHasNonResidentialType() {
            return String(this.editingParentProject.type2 || '')
                .split(',')
                .map(function(v) { return v.trim(); })
                .filter(Boolean)
                .includes('非住宅');
        },
        editHasSharedHousingType() {
            return String(this.editingParentProject.type2 || '')
                .split(',')
                .map(function(v) { return v.trim(); })
                .filter(Boolean)
                .includes('共同');
        },
        editVisibleFeatureOptions() {
            const self = this;
            return this.featureOptions.filter(function(opt) {
                if (opt === self.sharedHousingFeatureLabel) {
                    return self.editHasSharedHousingType;
                }
                return true;
            });
        },
        editHasEnergySavingRequest() {
            return !!this.editParentRequestFlags.energy;
        }
    },
    watch: {
        'editingParentProject.type2'() {
            if (!this.editHasSharedHousingType && this.editParentFeatureFlags) {
                this.editParentFeatureFlags[this.sharedHousingFeatureLabel] = false;
            }
        },
        'editParentRequestFlags.energy'(enabled) {
            if (!enabled && this.editingParentProject) {
                this.editingParentProject.application_review_agency = '';
                this.editingParentProject.bels_zeh_grade = '0';
                this.destroyEditParentReviewAgencyTagify();
            } else if (enabled) {
                this.$nextTick(() => this.initEditParentReviewAgencyTagify());
            }
        }
    },
    methods: {
        hasDirectorPermission(field) {
            if (typeof IS_ADMIN !== 'undefined' && IS_ADMIN) return true;
            if (!this.permission || this.permission.length === 0) return false;
            return this.permission.some((rule) => rule[field] === '1' || rule[field] === 1);
        },
        hasDepartmentPermission(field) {
            if (typeof IS_ADMIN !== 'undefined' && IS_ADMIN) {
                return true;
            }
            if (!this.permission || this.permission.length === 0) {
                return false;
            }
            return this.permission.some((rule) => rule[field] === '1' || rule[field] === 1);
        },
        normalizeSearchKeyword(value) {
            if (value === undefined || value === null) {
                return '';
            }
            return String(value).trim();
        },
        async loadParentProjects() {
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    draw: 1,
                    start: (this.currentPage - 1) * this.pageSize,
                    length: this.pageSize,
                    search: this.normalizeSearchKeyword(this.searchKeyword),
                    request_filter: this.requestFilter || '',
                    status: this.statusFilter === 'all' ? '' : this.statusFilter,
                    favorites_only: this.favoritesOnly ? '1' : '0',
                    order_column: this.sortColumn,
                    order_dir: this.sortDirection
                });
                
                const response = await axios.get(`/api/index.php?model=parentproject&method=list&${params.toString()}`);
                if (response.data && response.data.data) {
                    this.parentProjects = response.data.data;
                    this.totalRecords = response.data.recordsTotal || 0;
                }
            } catch (error) {
                console.error('Error loading parent projects:', error);
                showMessage('親プロジェクトの読み込みに失敗しました。', true);
            } finally {
                this.loading = false;
                // Re-translate i18n elements after Vue updates DOM
                this.$nextTick(() => {
                    this.translateI18n();
                    this.updateStickyTableHeader(true);
                });
            }
        },
        // Column visibility helpers
        isColumnVisible(key) {
            const col = this.availableColumns.find(c => c.key === key);
            // Nếu không tìm thấy, mặc định hiển thị
            return !col || col.visible;
        },
        loadColumnVisibilityFromStorage() {
            try {
                const raw = localStorage.getItem(this.columnVisibilityStorageKey);
                if (!raw) return;
                const stored = JSON.parse(raw);
                this.availableColumns = this.availableColumns.map(col => {
                    if (Object.prototype.hasOwnProperty.call(stored, col.key)) {
                        return { ...col, visible: !!stored[col.key] };
                    }
                    return col;
                });
            } catch (e) {
                console.error('Error loading column visibility:', e);
            }
        },
        saveColumnVisibilityToStorage() {
            try {
                const map = {};
                this.availableColumns.forEach(col => {
                    map[col.key] = !!col.visible;
                });
                localStorage.setItem(this.columnVisibilityStorageKey, JSON.stringify(map));
            } catch (e) {
                console.error('Error saving column visibility:', e);
            }
        },
        onColumnVisibilityChange() {
            this.saveColumnVisibilityToStorage();
            this.$nextTick(() => this.updateStickyTableHeader(true));
        },
        loadFiltersFromStorage() {
            try {
                const raw = localStorage.getItem(this.filterStorageKey);
                if (!raw) return;
                const stored = JSON.parse(raw);
                if (!stored || typeof stored !== 'object') return;

                if (typeof stored.searchKeyword === 'string') {
                    this.searchKeyword = stored.searchKeyword;
                }

                const allowedRequests = this.requestFilterOptions.map((o) => o.value);
                if (typeof stored.requestFilter === 'string' && allowedRequests.includes(stored.requestFilter)) {
                    this.requestFilter = stored.requestFilter;
                }

                if (typeof stored.favoritesOnly === 'boolean') {
                    this.favoritesOnly = stored.favoritesOnly;
                }
            } catch (e) {
                console.error('Error loading parent project filters:', e);
            }
        },
        saveFiltersToStorage() {
            try {
                localStorage.setItem(this.filterStorageKey, JSON.stringify({
                    searchKeyword: this.searchKeyword || '',
                    requestFilter: this.requestFilter || '',
                    favoritesOnly: !!this.favoritesOnly
                }));
            } catch (e) {
                console.error('Error saving parent project filters:', e);
            }
        },
        // ----- Notes (メモ) helpers & actions -----
        parseNotesDisplay(notesDisplay) {
            if (!notesDisplay || typeof notesDisplay !== 'string') return [];
            return notesDisplay
                .split(' | ')
                .map(raw => raw.trim())
                .filter(raw => raw !== '')
                .map(raw => {
                    const idx = raw.indexOf('::');
                    if (idx === -1) return { id: null, content: raw };
                    const id = raw.substring(0, idx);
                    const content = raw.substring(idx + 2);
                    return { id, content };
                });
        },
        onParentProjectContextMenu(event, project) {
            if (!project || !project.id) return;
            this.contextMenuParentProjectId = project.id;
            this.parentProjectContextMenuProject = project;
            this.parentProjectContextMenuX = event.pageX;
            this.parentProjectContextMenuY = event.pageY;
            this.parentProjectContextMenuVisible = true;
        },
        closeParentProjectContextMenu() {
            this.parentProjectContextMenuVisible = false;
            this.parentProjectContextMenuProject = null;
            this.contextMenuParentProjectId = null;
        },
        isHighlightedParentProject(project) {
            if (!project || this.highlightedParentProjectId == null) return false;
            return String(project.id) === String(this.highlightedParentProjectId);
        },
        clearParentProjectHighlight() {
            this.highlightedParentProjectId = null;
        },
        goToParentProjectDetailFromContextMenu() {
            const project = this.parentProjectContextMenuProject;
            this.closeParentProjectContextMenu();
            if (!project || !project.id) return;
            window.location.href = 'detail.php?id=' + encodeURIComponent(project.id);
        },
        async openParentProjectEditFromContextMenu() {
            const project = this.parentProjectContextMenuProject;
            this.closeParentProjectContextMenu();
            if (!project || !project.id || !this.isProjectManager) return;
            await this.openEditParentProjectModal(project.id);
        },
        parseEditParentRequests(requestsStr) {
            const requests = String(requestsStr || '').split(',').map(r => r.trim()).filter(Boolean);
            this.editParentRequestFlags = {
                design: requests.includes('意匠'),
                equipment: requests.includes('設備'),
                equipment3d: requests.includes('3D設備'),
                energy: requests.includes('省エネ'),
                other: requests.includes('その他')
            };
        },
        parseEditParentMaterials(materialsStr) {
            const materials = String(materialsStr || '').split(',').map(m => m.trim()).filter(Boolean);
            this.editParentMaterialFlags = {
                layout: materials.includes('配置図'),
                rental: materials.includes('家賃審査書'),
                contract: materials.includes('契約図'),
                tac: materials.includes('TAC図'),
                other: materials.includes('その他')
            };
        },
        buildEditParentRequestsString() {
            const arr = [];
            if (this.editParentRequestFlags.design) arr.push('意匠');
            if (this.editParentRequestFlags.equipment) arr.push('設備');
            if (this.editParentRequestFlags.equipment3d) arr.push('3D設備');
            if (this.editParentRequestFlags.energy) arr.push('省エネ');
            if (this.editParentRequestFlags.other) arr.push('その他');
            return arr.join(',');
        },
        buildEditParentMaterialsString() {
            const arr = [];
            if (this.editParentMaterialFlags.layout) arr.push('配置図');
            if (this.editParentMaterialFlags.rental) arr.push('家賃審査書');
            if (this.editParentMaterialFlags.contract) arr.push('契約図');
            if (this.editParentMaterialFlags.tac) arr.push('TAC図');
            if (this.editParentMaterialFlags.other) arr.push('その他');
            return arr.join(',');
        },
        createEmptyFeatureFlags() {
            const flags = {};
            this.featureOptions.forEach((opt) => { flags[opt] = false; });
            return flags;
        },
        parseEditParentFeatures(featuresStr) {
            const list = String(featuresStr || '').split(',').map((f) => f.trim()).filter(Boolean);
            const flags = this.createEmptyFeatureFlags();
            list.forEach((item) => {
                if (Object.prototype.hasOwnProperty.call(flags, item)) {
                    flags[item] = true;
                }
            });
            this.editParentFeatureFlags = flags;
        },
        buildEditParentFeaturesString() {
            return this.editVisibleFeatureOptions.filter((opt) => this.editParentFeatureFlags[opt]).join(',');
        },
        destroyEditParentWidgets() {
            ['#edit_pp_company_name', '#edit_pp_branch_name', '#edit_pp_contact_name', '#edit_pp_guis_receiver'].forEach((sel) => {
                const $el = $(sel);
                if ($el.length && $el.data('select2')) {
                    try { $el.select2('destroy'); } catch (e) {}
                }
                $el.empty().append('<option value="">選択してください</option>');
            });
            const dateEl = document.getElementById('edit_pp_request_date');
            if (dateEl && dateEl._flatpickr) {
                try { dateEl._flatpickr.destroy(); } catch (e) {}
            }
            [this.editParentType1Tagify, this.editParentType2Tagify, this.editParentReviewAgencyTagify].forEach((t) => {
                if (t && typeof t.destroy === 'function') {
                    try { t.destroy(); } catch (e) {}
                }
            });
            this.editParentType1Tagify = null;
            this.editParentType2Tagify = null;
            this.editParentReviewAgencyTagify = null;
            ['#edit_pp_type1_tags', '#edit_pp_type2_tags', '#edit_pp_application_review_agency_tags'].forEach((sel) => {
                const el = document.querySelector(sel);
                if (el) el.value = '';
            });
        },
        setEditParentCurrentDateTime() {
            const now = new Date();
            const y = now.getFullYear();
            const m = String(now.getMonth() + 1).padStart(2, '0');
            const d = String(now.getDate()).padStart(2, '0');
            const hh = String(now.getHours()).padStart(2, '0');
            const mm = String(now.getMinutes()).padStart(2, '0');
            const dateStr = `${y}/${m}/${d} ${hh}:${mm}`;
            this.editingParentProject.request_date = dateStr;
            const dateEl = document.getElementById('edit_pp_request_date');
            if (dateEl && dateEl._flatpickr) {
                dateEl._flatpickr.setDate(dateStr, true);
            } else if (dateEl) {
                dateEl.value = dateStr;
            }
        },
        clearEditParentTagifyTags(fieldName) {
            if (fieldName === 'type1' && this.editParentType1Tagify) this.editParentType1Tagify.removeAllTags();
            if (fieldName === 'type2' && this.editParentType2Tagify) this.editParentType2Tagify.removeAllTags();
            if (fieldName === 'application_review_agency' && this.editParentReviewAgencyTagify) {
                this.editParentReviewAgencyTagify.removeAllTags();
            }
        },
        destroyEditParentReviewAgencyTagify() {
            if (this.editParentReviewAgencyTagify) {
                try { this.editParentReviewAgencyTagify.destroy(); } catch (e) { /* ignore */ }
                this.editParentReviewAgencyTagify = null;
            }
            const input = document.querySelector('#edit_pp_application_review_agency_tags');
            if (input) {
                input.value = '';
                if (input.tagify) {
                    try { input.tagify.destroy(); } catch (e) { /* ignore */ }
                }
            }
        },
        initEditParentReviewAgencyTagify() {
            const input = document.querySelector('#edit_pp_application_review_agency_tags');
            if (!input || !window.Tagify) return;
            this.destroyEditParentReviewAgencyTagify();
            input.value = '';
            this.editParentReviewAgencyTagify = new Tagify(input, {
                whitelist: this.applicationReviewAgencyWhitelist.slice(),
                enforceWhitelist: false,
                maxTags: 1,
                dropdown: {
                    maxItems: 20,
                    classname: 'tags-look-review-agency',
                    enabled: 0,
                    closeOnSelect: true
                }
            });
            const updateAgency = () => {
                this.editingParentProject.application_review_agency = this.editParentReviewAgencyTagify.value
                    .map(t => t.value)
                    .join(',');
            };
            this.editParentReviewAgencyTagify.on('add', updateAgency);
            this.editParentReviewAgencyTagify.on('remove', updateAgency);
            const current = String(this.editingParentProject.application_review_agency || '').trim();
            if (current) {
                this.editParentReviewAgencyTagify.addTags(current);
            }
        },
        initEditParentDatePicker() {
            const dateEl = document.getElementById('edit_pp_request_date');
            if (!dateEl || typeof flatpickr === 'undefined') return;
            if (dateEl._flatpickr) dateEl._flatpickr.destroy();
            flatpickr(dateEl, {
                enableTime: true,
                dateFormat: 'Y/m/d H:i',
                time_24hr: true,
                allowInput: true,
                locale: 'ja',
                defaultHour: 9,
                defaultMinute: 0,
                onChange: (selectedDates, dateStr) => {
                    this.editingParentProject.request_date = dateStr;
                }
            });
            if (this.editingParentProject.request_date) {
                dateEl._flatpickr.setDate(this.editingParentProject.request_date, false);
            }
        },
        initEditParentTagify() {
            const type1Input = document.querySelector('#edit_pp_type1_tags');
            if (type1Input && window.Tagify) {
                if (type1Input.tagify) { try { type1Input.tagify.destroy(); } catch (e) {} }
                type1Input.value = '';
                this.editParentType1Tagify = new Tagify(type1Input, {
                    whitelist: ['TAC', '特注'],
                    maxTags: 5,
                    dropdown: { maxItems: 20, classname: 'tags-look-type1', enabled: 0, closeOnSelect: true }
                });
                const updateType1 = () => {
                    this.editingParentProject.type1 = this.editParentType1Tagify.value.map(t => t.value).join(',');
                };
                this.editParentType1Tagify.on('add', updateType1);
                this.editParentType1Tagify.on('remove', updateType1);
                const type1Val = String(this.editingParentProject.type1 || '').trim();
                if (type1Val) this.editParentType1Tagify.addTags(type1Val);
            }
            const type2Input = document.querySelector('#edit_pp_type2_tags');
            if (type2Input && window.Tagify) {
                if (type2Input.tagify) { try { type2Input.tagify.destroy(); } catch (e) {} }
                type2Input.value = '';
                this.editParentType2Tagify = new Tagify(type2Input, {
                    whitelist: ['長屋', '共同', '戸建', '非住宅', '集合'],
                    maxTags: 1,
                    dropdown: { maxItems: 20, classname: 'tags-look-type2', enabled: 0, closeOnSelect: true }
                });
                const updateType2 = () => {
                    this.editingParentProject.type2 = this.editParentType2Tagify.value.map(t => t.value).join(',');
                    if (!this.editHasNonResidentialType) {
                        this.editingParentProject.type3 = '';
                    }
                };
                this.editParentType2Tagify.on('add', updateType2);
                this.editParentType2Tagify.on('remove', updateType2);
                const type2Val = String(this.editingParentProject.type2 || '').trim();
                if (type2Val) this.editParentType2Tagify.addTags(type2Val);
            }
            if (this.editHasEnergySavingRequest) {
                this.initEditParentReviewAgencyTagify();
            }
        },
        initEditParentSelect2() {
            const modalEl = document.getElementById('editParentProjectModal');
            const dropdownParent = modalEl ? $(modalEl) : $(document.body);
            const $company = $('#edit_pp_company_name');
            const $branch = $('#edit_pp_branch_name');
            const $contact = $('#edit_pp_contact_name');
            const $guis = $('#edit_pp_guis_receiver');

            if ($company.length) {
                if ($company.data('select2')) $company.select2('destroy');
                $company.empty().append('<option value="">選択してください</option>');
                $company.select2({
                    placeholder: '選択してください',
                    dropdownParent,
                    allowClear: true,
                    minimumResultsForSearch: 0,
                    ajax: {
                        url: '/api/index.php?model=customer&method=list_companies',
                        dataType: 'json',
                        delay: 250,
                        data: (params) => ({ search: params.term, page: params.page || 1 }),
                        processResults: (data) => {
                            const list = (data.data || []).slice().sort((a, b) => {
                                const nameA = (a.company_name || '').toString();
                                const nameB = (b.company_name || '').toString();
                                const hasA = nameA.includes('大東');
                                const hasB = nameB.includes('大東');
                                if (hasA && !hasB) return -1;
                                if (!hasA && hasB) return 1;
                                return nameA.localeCompare(nameB);
                            });
                            return {
                                results: list.map((item) => ({ id: item.company_name, text: item.company_name }))
                            };
                        }
                    }
                }).on('select2:select', (e) => {
                    this.editingParentProject.company_name = e.params.data.id;
                    this.onEditParentCompanyChange();
                }).on('select2:clear', () => {
                    this.editingParentProject.company_name = '';
                    this.onEditParentCompanyChange();
                });
                if (this.editingParentProject.company_name) {
                    const opt = new Option(this.editingParentProject.company_name, this.editingParentProject.company_name, true, true);
                    $company.append(opt).trigger('change');
                }
            }

            if ($branch.length) {
                if ($branch.data('select2')) $branch.select2('destroy');
                $branch.empty().append('<option value="">選択してください</option>');
                $branch.select2({
                    placeholder: '選択してください',
                    dropdownParent,
                    allowClear: true,
                    minimumResultsForSearch: 0,
                    ajax: {
                        url: '/api/index.php?model=customer&method=list_branches_by_company',
                        dataType: 'json',
                        delay: 250,
                        data: (params) => ({
                            search: params.term,
                            page: params.page || 1,
                            company_name: this.editingParentProject.company_name
                        }),
                        processResults: (data) => ({
                            results: (data.data || []).map((item) => ({ id: item.branch, text: item.branch }))
                        })
                    }
                }).on('select2:select', (e) => {
                    this.editingParentProject.branch_name = e.params.data.id;
                    this.onEditParentBranchChange();
                }).on('select2:clear', () => {
                    this.editingParentProject.branch_name = '';
                    this.onEditParentBranchChange();
                });
                if (this.editingParentProject.branch_name) {
                    const opt = new Option(this.editingParentProject.branch_name, this.editingParentProject.branch_name, true, true);
                    $branch.append(opt).trigger('change');
                }
            }

            if ($contact.length) {
                if ($contact.data('select2')) $contact.select2('destroy');
                $contact.empty().append('<option value="">選択してください</option>');
                $contact.select2({
                    placeholder: '選択してください',
                    dropdownParent,
                    allowClear: true,
                    minimumResultsForSearch: 0,
                    ajax: {
                        url: '/api/index.php?model=customer&method=list_contacts_by_company_branch',
                        dataType: 'json',
                        delay: 250,
                        data: (params) => ({
                            search: params.term,
                            page: params.page || 1,
                            company_name: this.editingParentProject.company_name,
                            branch_name: this.editingParentProject.branch_name
                        }),
                        processResults: (data) => ({
                            results: (data.data || []).map((item) => ({
                                id: item.name,
                                text: item.name,
                                customer_id: item.id
                            }))
                        })
                    }
                }).on('select2:select', (e) => {
                    this.editingParentProject.contact_name = e.params.data.id;
                    if (e.params.data.customer_id) {
                        this.editingParentProject.customer_id = e.params.data.customer_id;
                    }
                }).on('select2:clear', () => {
                    this.editingParentProject.contact_name = '';
                });
                if (this.editingParentProject.contact_name) {
                    const opt = new Option(this.editingParentProject.contact_name, this.editingParentProject.contact_name, true, true);
                    $contact.append(opt).trigger('change');
                }
            }

            if ($guis.length) {
                if ($guis.data('select2')) $guis.select2('destroy');
                $guis.empty().append('<option value="">選択してください</option>');
                $guis.select2({
                    placeholder: '選択してください',
                    dropdownParent,
                    allowClear: true,
                    minimumResultsForSearch: 0,
                    ajax: {
                        url: '/api/index.php?model=user&method=searchMembers',
                        dataType: 'json',
                        delay: 250,
                        data: (params) => ({ search: params.term, page: params.page || 1 }),
                        processResults: (data) => ({
                            results: (data.data || []).map((item) => ({
                                id: item.userid,
                                text: item.realname
                            }))
                        })
                    }
                }).on('select2:select', (e) => {
                    this.editingParentProject.guis_receiver = e.params.data.id;
                }).on('select2:clear', () => {
                    this.editingParentProject.guis_receiver = '';
                });
                if (this.editingParentProject.guis_receiver) {
                    this.loadEditParentGuisReceiverDisplay();
                }
            }
        },
        async loadEditParentGuisReceiverDisplay() {
            try {
                const response = await axios.get('/api/index.php?model=user&method=searchMembers');
                const list = (response.data && response.data.data) ? response.data.data : [];
                const userid = String(this.editingParentProject.guis_receiver || '');
                const user = list.find((u) => String(u.userid) === userid);
                const $guis = $('#edit_pp_guis_receiver');
                if ($guis.length && $guis.data('select2') && user) {
                    $guis.empty();
                    const option = new Option(user.realname, userid, true, true);
                    $guis.append(option).trigger('change');
                }
            } catch (error) {
                console.error('Error loading GUIS receiver display name:', error);
            }
        },
        onEditParentCompanyChange() {
            this.editingParentProject.branch_name = '';
            this.editingParentProject.contact_name = '';
            const $branch = $('#edit_pp_branch_name');
            const $contact = $('#edit_pp_contact_name');
            if ($branch.length && $branch.data('select2')) {
                $branch.val(null).trigger('change');
            }
            if ($contact.length && $contact.data('select2')) {
                $contact.val(null).trigger('change');
            }
        },
        onEditParentBranchChange() {
            this.editingParentProject.contact_name = '';
            const $contact = $('#edit_pp_contact_name');
            if ($contact.length && $contact.data('select2')) {
                $contact.val(null).trigger('change');
            }
        },
        async openEditParentProjectModal(parentProjectId) {
            this.editParentProjectErrors = { company_name: '', project_name: '' };
            this.editParentProjectLoading = true;
            this.destroyEditParentWidgets();
            this.editingParentProject = {
                id: parentProjectId,
                project_name: '',
                project_number: '',
                construction_number: '',
                company_name: '',
                branch_name: '',
                contact_name: '',
                customer_id: '',
                guis_receiver: '',
                request_date: '',
                scale: '',
                structural_office: '',
                type1: '',
                type2: '',
                type3: '',
                status: 'draft',
                requests: '',
                materials: '',
                features: '',
                application_review_agency: '',
                bels_zeh_grade: '0'
            };
            this.parseEditParentRequests('');
            this.parseEditParentMaterials('');
            this.parseEditParentFeatures('');
            const modalEl = document.getElementById('editParentProjectModal');
            if (modalEl) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
            try {
                const response = await axios.get('/api/index.php?model=parentproject&method=getById&id=' + encodeURIComponent(parentProjectId));
                const data = response.data || {};
                this.editingParentProject = {
                    id: data.id || parentProjectId,
                    project_name: data.project_name || '',
                    project_number: data.project_number || '',
                    construction_number: data.construction_number || '',
                    company_name: data.company_name || '',
                    branch_name: data.branch_name || '',
                    contact_name: data.contact_name || '',
                    customer_id: data.customer_id || '',
                    guis_receiver: data.guis_receiver || '',
                    request_date: data.request_date || '',
                    scale: data.scale || '',
                    structural_office: data.structural_office || '',
                    type1: data.type1 || '',
                    type2: data.type2 || '',
                    type3: data.type3 || '',
                    status: data.status || 'draft',
                    requests: data.requests || '',
                    materials: data.materials || '',
                    features: data.features || '',
                    application_review_agency: data.application_review_agency || '',
                    bels_zeh_grade: (data.bels_zeh_grade != null && data.bels_zeh_grade !== '')
                        ? String(data.bels_zeh_grade)
                        : '0'
                };
                this.parseEditParentRequests(this.editingParentProject.requests);
                this.parseEditParentMaterials(this.editingParentProject.materials);
                this.parseEditParentFeatures(this.editingParentProject.features);
                this.editParentProjectLoading = false;
                await this.$nextTick();
                this.initEditParentSelect2();
                this.initEditParentDatePicker();
                this.initEditParentTagify();
                if (typeof window.applyDataI18n === 'function') {
                    window.applyDataI18n(modalEl);
                } else if (typeof this.translateI18n === 'function') {
                    this.translateI18n();
                }
            } catch (error) {
                console.error('Error loading parent project for edit:', error);
                if (typeof showMessage === 'function') {
                    showMessage('建物情報の読み込みに失敗しました。', true);
                }
                if (modalEl) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }
                this.editParentProjectLoading = false;
            }
        },
        async saveParentProjectFromModal() {
            this.editParentProjectErrors = { company_name: '', project_name: '' };
            const company = String(this.editingParentProject.company_name || '').trim();
            const name = String(this.editingParentProject.project_name || '').trim();
            let hasError = false;
            if (!company) {
                this.editParentProjectErrors.company_name = '会社名は必須です';
                hasError = true;
            }
            if (!name) {
                this.editParentProjectErrors.project_name = 'お施主様名は必須です';
                hasError = true;
            }
            if (hasError) return;

            this.editParentProjectSaving = true;
            try {
                const formData = new FormData();
                formData.append('id', this.editingParentProject.id);
                formData.append('company_name', company);
                formData.append('branch_name', this.editingParentProject.branch_name || '');
                formData.append('contact_name', this.editingParentProject.contact_name || '');
                formData.append('customer_id', this.editingParentProject.customer_id || '');
                formData.append('guis_receiver', this.editingParentProject.guis_receiver || '');
                formData.append('request_date', this.editingParentProject.request_date || '');
                formData.append('project_name', name);
                formData.append('project_number', this.editingParentProject.project_number || '');
                formData.append('construction_number', this.editingParentProject.construction_number || '');
                formData.append('scale', this.editingParentProject.scale || '');
                formData.append('structural_office', this.editingParentProject.structural_office || '');
                formData.append('type1', this.editingParentProject.type1 || '');
                formData.append('type2', this.editingParentProject.type2 || '');
                formData.append('type3', this.editHasNonResidentialType ? (this.editingParentProject.type3 || '') : '');
                formData.append('status', this.editingParentProject.status || 'draft');
                formData.append('requests', this.buildEditParentRequestsString());
                formData.append('materials', this.buildEditParentMaterialsString());
                formData.append('features', this.buildEditParentFeaturesString());
                formData.append(
                    'application_review_agency',
                    this.editHasEnergySavingRequest ? (this.editingParentProject.application_review_agency || '') : ''
                );
                formData.append(
                    'bels_zeh_grade',
                    this.editHasEnergySavingRequest ? (this.editingParentProject.bels_zeh_grade || '0') : '0'
                );
                const response = await axios.post('/api/index.php?model=parentproject&method=update', formData);
                if (response.data && response.data.status === 'success') {
                    const savedId = this.editingParentProject.id;
                    this.highlightedParentProjectId = savedId != null ? Number(savedId) || savedId : null;
                    if (typeof showMessage === 'function') {
                        showMessage(response.data.message || '建物情報を更新しました。', false);
                    }
                    const modalEl = document.getElementById('editParentProjectModal');
                    if (modalEl) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    }
                    this.destroyEditParentWidgets();
                    await this.loadParentProjects();
                } else {
                    showParentProjectError('更新に失敗しました。', response && response.data);
                }
            } catch (error) {
                console.error('Error saving parent project:', error);
                showParentProjectError('更新に失敗しました。', error);
            } finally {
                this.editParentProjectSaving = false;
            }
        },
        addNoteFromContextMenu() {
            const parentId = this.contextMenuParentProjectId
                || (this.parentProjectContextMenuProject && this.parentProjectContextMenuProject.id);
            this.closeParentProjectContextMenu();
            if (!parentId) return;
            this.openNoteModalFromList(parentId, null);
        },
        async loadNotesForParentProject(parentProjectId) {
            try {
                const response = await axios.get(`/api/index.php?model=parentproject&method=getNotes&parent_project_id=${parentProjectId}`);
                if (response.data && response.data.status === 'success') {
                    this.notes = response.data.data || [];
                } else {
                    this.notes = [];
                }
            } catch (error) {
                console.error('Error loading parent project notes:', error);
                this.notes = [];
            }
        },
        openNoteModalFromList(parentProjectId, noteContent = null) {
            this.currentNoteParentProjectId = parentProjectId;
            this.showNoteModal = true;
            this.isNoteEditMode = true;
            this.editingNote = {
                id: null,
                title: '',
                content: '',
                is_important: false,
                user_id: null
            };
            this.loadNotesForParentProject(parentProjectId).then(() => {
                if (noteContent) {
                    const trimmed = noteContent.trim();
                    const match = this.notes.find(n => (n.content || '').trim() === trimmed);
                    if (match) {
                        this.editingNote = {
                            id: match.id,
                            title: match.title,
                            content: match.content,
                            is_important: match.is_important == 1,
                            user_id: match.user_id
                        };
                    } else {
                        this.editingNote.content = noteContent;
                    }
                }
            });
        },
        openNoteEdit(project, note) {
            this.currentNoteParentProjectId = project.id;
            this.showNoteModal = true;
            this.isNoteEditMode = true;
            this.editingNote = {
                id: note.id,
                title: '',
                content: note.content,
                is_important: false,
                user_id: note.user_id
            };
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
            const rawContent = (this.editingNote.content || '').trim();
            if (!rawContent) {
                showMessage('内容を入力してください', true);
                return;
            }
            let title = (this.editingNote.title || '').trim();
            if (!title) {
                title = rawContent.split(/\r?\n/)[0].slice(0, 50) || 'メモ';
            }
            try {
                const formData = new FormData();
                formData.append('parent_project_id', this.currentNoteParentProjectId);
                formData.append('title', title);
                formData.append('content', rawContent);
                formData.append('is_important', this.editingNote.is_important ? 1 : 0);
                let response;
                if (this.editingNote.id) {
                    formData.append('id', this.editingNote.id);
                    response = await axios.post('/api/index.php?model=parentproject&method=updateNote', formData);
                } else {
                    response = await axios.post('/api/index.php?model=parentproject&method=addNote', formData);
                }
                if (response.data && response.data.status === 'success') {
                    showMessage('メモが保存されました');
                    this.closeNoteModal();
                    this.loadParentProjects();
                } else {
                    showParentProjectError(response.data?.error || 'メモの保存に失敗しました', response && response.data);
                }
            } catch (error) {
                console.error('Error saving parent project note:', error);
                showParentProjectError('メモの保存に失敗しました', error);
            }
        },
        async deleteNoteFromList(project, noteId) {
            if (!confirm('このメモを削除しますか？')) return;
            try {
                const formData = new FormData();
                formData.append('id', noteId);
                const response = await axios.post('/api/index.php?model=parentproject&method=deleteNote', formData);
                if (response.data && response.data.status === 'success') {
                    showMessage('メモが削除されました');
                    this.loadParentProjects();
                } else {
                    showParentProjectError(response.data?.error || 'メモの削除に失敗しました', response && response.data);
                }
            } catch (error) {
                console.error('Error deleting parent project note:', error);
                showParentProjectError('メモの削除に失敗しました', error);
            }
        },
        translateI18n() {
            // Call localize function if it exists (from main.js)
            if (typeof localize === 'function') {
                localize();
            } else if (typeof i18next !== 'undefined' && i18next.isInitialized) {
                // Fallback: manually translate all data-i18n elements
                const i18nList = document.querySelectorAll('[data-i18n]');
                i18nList.forEach(function (item) {
                    item.innerHTML = i18next.t(item.dataset.i18n);
                });
            }
        },
        translatePlaceholder(key) {
            // Translate placeholder text using i18next
            if (typeof i18next !== 'undefined' && i18next.isInitialized) {
                return i18next.t(key) || key;
            }
            return key;
        },
        sortBy(column) {
            // Map frontend column names to database column names
            const columnMap = {
                'project_number': 'project_number',
                'project_name': 'project_name',
                'construction_number': 'construction_number',
                'company_name': 'company_name',
                'scale': 'scale',
                'type1': 'type1',
                'type2': 'type2',
                'requests': 'requests',
                'request_date': 'request_date',
                'child_project_count': 'child_project_count',
                'created_by_name': 'created_by_name',
                'created_at': 'created_at'
            };
            
            const dbColumn = columnMap[column] || column;
            
            if (this.sortColumn === dbColumn) {
                // Toggle direction if same column
                this.sortDirection = this.sortDirection === 'ASC' ? 'DESC' : 'ASC';
            } else {
                // New column, default to ascending
                this.sortColumn = dbColumn;
                this.sortDirection = 'ASC';
            }
            // Reset to first page when sorting
            this.currentPage = 1;
            // Reload data with new sort
            this.loadParentProjects();
        },
        getSortIcon(column) {
            // Map frontend column names to database column names
            const columnMap = {
                'project_number': 'project_number',
                'project_name': 'project_name',
                'construction_number': 'construction_number',
                'company_name': 'company_name',
                'scale': 'scale',
                'type1': 'type1',
                'type2': 'type2',
                'requests': 'requests',
                'request_date': 'request_date',
                'child_project_count': 'child_project_count',
                'created_by_name': 'created_by_name',
                'created_at': 'created_at'
            };
            
            const dbColumn = columnMap[column] || column;
            
            if (this.sortColumn !== dbColumn) {
                return 'fa-sort text-muted';
            }
            return this.sortDirection === 'ASC' ? 'fa-sort-up' : 'fa-sort-down';
        },
        onSearch() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => {
                this.currentPage = 1;
                this.saveFiltersToStorage();
                this.loadParentProjects();
            }, 500);
        },
        onSearchBlur() {
            const trimmed = this.normalizeSearchKeyword(this.searchKeyword);
            if (this.searchKeyword !== trimmed) {
                this.searchKeyword = trimmed;
                this.onSearch();
            }
        },
        clearSearch() {
            this.searchKeyword = '';
            this.onSearch();
        },
        selectRequestFilter(value) {
            this.requestFilter = value;
            this.onRequestFilterChange();
        },
        getRequestColor(request) {
            const opt = this.requestFilterOptions.find((o) => o.value && o.value === request);
            return opt ? opt.color : 'secondary';
        },
        getRequestBadgeClass(request) {
            return `bg-${this.getRequestColor(request)}`;
        },
        getRequestUnfulfilledBadgeClass(request) {
            return 'bg-warning text-dark';
        },
        /** Map department name → 依頼 type (same rules as parentproject.php) */
        mapDepartmentNameToRequestType(departmentName) {
            const name = String(departmentName || '').trim();
            if (!name) return '';
            const rules = [
                ['3D設備', '3D設備'],
                ['技術課設備', '3D設備'],
                ['省エネ', '省エネ'],
                ['意匠', '意匠'],
                ['設備', '設備']
            ];
            for (let i = 0; i < rules.length; i++) {
                if (name.indexOf(rules[i][0]) !== -1) return rules[i][1];
            }
            return 'その他';
        },
        getDepartmentNoukiBadgeClass(departmentName) {
            return this.getRequestBadgeClass(this.mapDepartmentNameToRequestType(departmentName));
        },
        getDepartmentNoukiBadgeLabel(departmentName) {
            return this.mapDepartmentNameToRequestType(departmentName) || '-';
        },
        /** Combined badge text: e.g. 意匠- 修正 */
        getNearestNoukiCombinedLabel(item) {
            const dept = this.getDepartmentNoukiBadgeLabel(item && item.department_name);
            const orderTypes = this.splitProjectOrderTypes(item && item.project_order_type);
            if (!orderTypes.length) return dept;
            return dept + '- ' + orderTypes.join(',');
        },
        isParentRequestFulfilled(project, requestType) {
            const type = String(requestType || '').trim();
            if (!type || !project) return false;
            const fulfilled = project.fulfilled_request_types;
            if (!Array.isArray(fulfilled)) return false;
            return fulfilled.some((t) => String(t).trim() === type);
        },
        onRequestFilterChange() {
            this.currentPage = 1;
            this.saveFiltersToStorage();
            this.loadParentProjects();
        },
        onStatusFilterChange() {
            this.currentPage = 1;
            this.loadParentProjects();
        },
        onFavoritesFilterChange() {
            this.currentPage = 1;
            this.saveFiltersToStorage();
            this.loadParentProjects();
        },
        async openChildProjectsWindow(project) {
            this.selectedParentProject = project;
            this.childProjectsForModal = [];
            this.loadingChildProjects = true;
            try {
                const response = await axios.get(`/api/index.php?model=parentproject&method=getChildProjects&parent_project_id=${project.id}`);
                if (response.data) {
                    this.childProjectsForModal = response.data;
                }
            } catch (error) {
                console.error('Error loading child projects for parent project:', project.id, error);
                showMessage('子プロジェクトの読み込みに失敗しました。', true);
            } finally {
                this.loadingChildProjects = false;
                const modalEl = document.getElementById('childProjectsModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.show();
                }
            }
        },
        async toggleFavorite(project) {
            try {
                const formData = new FormData();
                formData.append('parent_project_id', project.id);
                
                const response = await axios.post('/api/index.php?model=parentproject&method=toggleFavorite', formData);
                
                if (response.data && response.data.status === 'success') {
                    // Update the project's favorite status (convert boolean to number for consistency)
                    project.is_favorite = response.data.is_favorite ? 1 : 0;
                } else {
                    showParentProjectError(response.data?.message || '操作に失敗しました。', response && response.data);
                }
            } catch (error) {
                console.error('Error toggling favorite:', error);
                showParentProjectError('操作に失敗しました。', error);
            }
        },
        async clearAllFavorites() {
            try {
                const result = await Swal.fire({
                    title: '確認',
                    text: 'すべてのお気に入りを削除しますか？',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: '削除',
                    cancelButtonText: 'キャンセル'
                });
                
                if (result.isConfirmed) {
                    const response = await axios.post('/api/index.php?model=parentproject&method=clearAllFavorites');
                    
                    if (response.data && response.data.status === 'success') {
                        // Uncheck the favorites filter
                        this.favoritesOnly = false;
                        this.saveFiltersToStorage();
                        // Reload the list to refresh favorite status
                        this.loadParentProjects();
                    } else {
                        showParentProjectError(response.data?.message || '削除に失敗しました。', response && response.data);
                    }
                }
            } catch (error) {
                console.error('Error clearing all favorites:', error);
                showParentProjectError('削除に失敗しました。', error);
            }
        },
        changePage(page) {
            if (page >= 1 && page <= this.totalPages) {
                this.currentPage = page;
                this.loadParentProjects();
            }
        },
        getStatusLabel(status) {
            const s = this.statuses.find(s => s.value === status)
                || this.childProjectStatuses.find(s => s.value === status);
            return s ? s.label : status;
        },
        getStatusBadgeClass(status) {
            const s = this.statuses.find(s => s.value === status)
                || this.childProjectStatuses.find(s => s.value === status);
            return `bg-${s?.color || 'secondary'}`;
        },
        getOrderTypeBadgeClass(orderType) {
            const type = String(orderType || '').trim();
            switch (type) {
                case '修正':
                    return 'bg-warning';
                case '新規':
                    return 'bg-primary';
                case '新規修正':
                    return 'bg-success';
                case '変更':
                    return 'bg-danger';
                default:
                    return 'bg-info';
            }
        },
        splitProjectOrderTypes(raw) {
            if (!raw) return [];
            if (Array.isArray(raw)) {
                return raw.map((v) => String(v).trim()).filter(Boolean);
            }
            return String(raw).split(',').map((v) => v.trim()).filter(Boolean);
        },
        getManagerName(managerString) {
            if (!managerString) return '';
            const parts = managerString.split(':');
            const name = parts[1] || parts[0] || '';
            const userid = parts[3] || parts[0] || '';
            const ruby = parts[4] || '';
            if (typeof getUserDisplayName === 'function') {
                return getUserDisplayName(name, { userid: userid, user_ruby: ruby }) || name;
            }
            return name;
        },
        getManagerImage(managerString) {
            if (!managerString) return '';
            const parts = managerString.split(':');
            return parts[2] || '';
        },
        getManagerInitials(managerString) {
            if (!managerString) return '?';
            const parts = managerString.split(':');
            const authId = parts[0] || '';
            const name = parts[1] || parts[0] || '';
            const userid = parts[3] || authId;
            const ruby = parts[4] || '';
            return this.getInitials(name, userid, ruby);
        },
        getRemainingManagers(managerIdString) {
            if (!managerIdString) return '';
            const managers = managerIdString.split('|').filter(m => m.trim() !== '');
            if (managers.length <= 1) return '';
            const remaining = managers.slice(1).map(manager => {
                return this.getManagerName(manager);
            }).filter(name => name).join(', ');
            return remaining;
        },
        getInitials(name, userid, ruby) {
            if (!name && !userid) return '?';
            if (typeof getAvatarName === 'function') {
                return getAvatarName(name || '', { userid: userid || '', user_ruby: ruby || '' });
            }
            try {
                const hasJapanese = /[\u3040-\u309f\u30a0-\u30ff\u4e00-\u9faf]/.test(name);
                if (hasJapanese) {
                    return name.substring(0, 2);
                } else {
                    const words = name.trim().split(' ');
                    const lastWord = words[words.length - 1];
                    return lastWord;
                }
            } catch (error) {
                return name.charAt(0).toUpperCase();
            }
        },
        isVietnameseLocale() {
            return typeof i18next !== 'undefined'
                && i18next.isInitialized
                && String(i18next.language || '').startsWith('vi');
        },
        getTaskDisplayTimezone() {
            return this.isVietnameseLocale() ? VIETNAM_TASK_TIMEZONE : SERVER_TASK_TIMEZONE;
        },
        getTaskDateTimeDisplayFormat() {
            return this.isVietnameseLocale()
                ? TASK_DATETIME_MOMENT_FORMAT
                : TASK_DATETIME_JA_DISPLAY_FORMAT;
        },
        getTaskDateDisplayFormat() {
            return this.isVietnameseLocale()
                ? TASK_DATE_MOMENT_FORMAT
                : TASK_DATE_JA_DISPLAY_FORMAT;
        },
        parseTaskDateTime(date, timezone = SERVER_TASK_TIMEZONE) {
            if (!date) return null;
            const raw = String(date).trim();
            if (!raw || raw === '0000-00-00 00:00:00' || raw === '0000-00-00') return null;
            if (typeof moment === 'undefined') return null;
            if (moment.tz) {
                for (const fmt of TASK_DATETIME_PARSE_FORMATS) {
                    const parsed = moment.tz(raw, fmt, timezone);
                    if (parsed.isValid()) return parsed;
                }
                const loose = moment.tz(raw, timezone);
                return loose.isValid() ? loose : null;
            }
            const fallback = moment(raw, TASK_DATETIME_PARSE_FORMATS, true);
            return fallback.isValid() ? fallback : null;
        },
        formatTaskDateTimeInDisplayTz(date) {
            const parsed = this.parseTaskDateTime(date);
            if (!parsed) return '-';
            const localized = moment.tz
                ? parsed.clone().tz(this.getTaskDisplayTimezone())
                : parsed;
            return localized.format(this.getTaskDateTimeDisplayFormat());
        },
        formatTaskDateInDisplayTz(date) {
            const parsed = this.parseTaskDateTime(date);
            if (!parsed) return '-';
            const localized = moment.tz
                ? parsed.clone().tz(this.getTaskDisplayTimezone())
                : parsed;
            return localized.format(this.getTaskDateDisplayFormat());
        },
        formatDate(date) {
            return this.formatTaskDateTimeInDisplayTz(date);
        },
        formatDate2(date) {
            return this.formatTaskDateInDisplayTz(date);
        },
        formatDateTime(date) {
            return this.formatTaskDateTimeInDisplayTz(date);
        },
        isNewParentProject(createdAt) {
            if (!createdAt) {
                return false;
            }
            const created = this.parseTaskDateTime(createdAt, SERVER_TASK_TIMEZONE);
            if (!created) {
                return false;
            }
            const now = (typeof moment !== 'undefined' && moment.tz)
                ? moment.tz(SERVER_TASK_TIMEZONE)
                : (typeof moment !== 'undefined' ? moment() : null);
            if (!now) {
                return false;
            }
            return now.diff(created, 'hours', true) < 8;
        },
        formatPrice(amount) {
            const n = Number(amount) || 0;
            return '¥' + n.toLocaleString('ja-JP');
        },
        async toggleChildFavorite(child) {
            try {
                const formData = new FormData();
                formData.append('project_id', child.id);
                
                const response = await axios.post('/api/index.php?model=project&method=toggleFavorite', formData);
                
                if (response.data && response.data.status === 'success') {
                    child.is_favorite = response.data.is_favorite ? 1 : 0;
                } else {
                    showParentProjectError(response.data?.message || '操作に失敗しました。', response && response.data);
                }
            } catch (error) {
                console.error('Error toggling child project favorite:', error);
                showParentProjectError('操作に失敗しました。', error);
            }
        },
        async deleteParentProject(projectOrId) {
            if (!this.isAdministrator) {
                showMessage('管理者のみ削除できます。', true);
                    return;
                }
            const project = (projectOrId && typeof projectOrId === 'object')
                ? projectOrId
                : this.parentProjects.find(p => p.id == projectOrId);
            const id = project ? project.id : projectOrId;
            if (!id) return;

            const childCount = project && project.child_project_count != null
                ? Number(project.child_project_count)
                : 0;
            const name = project && project.project_name ? project.project_name : ('ID ' + id);
            const escapeHtml = (s) => String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
            const safeName = escapeHtml(name);
            const warnText = childCount > 0
                ? `建物「${safeName}」と関連する案件（${childCount}件）およびそのデータがすべて完全削除されます。この操作は取り消せません。`
                : `建物「${safeName}」を完全削除します。この操作は取り消せません。`;

            try {
                const result = await Swal.fire({
                    title: '建物の完全削除',
                    html: `<p class="text-start mb-2">${warnText}</p>
                           <p class="text-start text-danger small mb-2">続行するには <strong>DELETE</strong> と入力してください。</p>`,
                    icon: 'warning',
                    input: 'text',
                    inputPlaceholder: 'DELETE',
                    inputAttributes: {
                        autocomplete: 'off',
                        autocapitalize: 'off',
                        spellcheck: 'false'
                    },
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: '削除する',
                    cancelButtonText: 'キャンセル',
                    focusConfirm: false,
                    preConfirm: (value) => {
                        if (String(value || '').trim() !== 'DELETE') {
                            Swal.showValidationMessage('確認のため DELETE と入力してください');
                            return false;
                        }
                        return 'DELETE';
                    }
                });

                if (!result.isConfirmed) return;

                this.deletingParentProjectId = id;
                    const formData = new FormData();
                    formData.append('id', id);
                formData.append('confirm', 'DELETE');
                    
                    const response = await axios.post('/api/index.php?model=parentproject&method=delete', formData);
                    if (response.data && response.data.status === 'success') {
                    const deletedChildren = response.data.deleted_children != null
                        ? response.data.deleted_children
                        : childCount;
                    await Swal.fire({
                        title: '削除完了',
                        text: deletedChildren > 0
                            ? `建物を削除しました（案件 ${deletedChildren} 件も削除）。`
                            : '建物を削除しました。',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                        this.loadParentProjects();
                    } else {
                    showParentProjectError(response.data?.error || response.data?.message || '削除に失敗しました。', response && response.data);
                }
            } catch (error) {
                console.error('Error deleting parent project:', error);
                showParentProjectError('削除に失敗しました。', error);
            } finally {
                this.deletingParentProjectId = null;
            }
        },
        async loadPermission() {
            try {
                const response = await axios.get('/api/index.php?model=department&method=get_user_permissions');
                this.permission = response.data || [];
            } catch (error) {
                console.error('Error loading permission:', error);
                this.permission = [];
            }
        },
        buildCopyParentProjectPayload(src) {
            const source = src || {};
            return {
                company_name: source.company_name || '',
                branch_name: source.branch_name || '',
                contact_name: source.contact_name || '',
                customer_id: source.customer_id || '',
                construction_number: source.construction_number || '',
                project_name: source.project_name || '',
                construction_branch: source.construction_branch || '',
                scale: source.scale || '',
                type1: source.type1 || '',
                type2: source.type2 || '',
                type3: source.type3 || '',
                request_type: source.request_type || '',
                structural_office: source.structural_office || '',
                features: source.features || '',
                application_review_agency: source.application_review_agency || '',
                bels_zeh_grade: (source.bels_zeh_grade != null && source.bels_zeh_grade !== '')
                    ? String(source.bels_zeh_grade)
                    : '0',
                notes: source.notes || '',
                requests: String(source.requests || '').trim(),
                materials: String(source.materials || '').trim(),
                status: 'draft'
            };
        },
        async copyParentProject(project) {
            if (!project || !project.id) {
                return;
            }
            if (!this.canCreateParentProject) {
                showMessage('権限がありません。', true);
                return;
            }
            this.copyingParentProjectId = project.id;
            try {
                const response = await axios.get('/api/index.php', {
                    params: {
                        model: 'parentproject',
                        method: 'getById',
                        id: project.id
                    }
                });
                const source = response && response.data && response.data.id ? response.data : project;
                const payload = this.buildCopyParentProjectPayload(source);
                sessionStorage.setItem('copyParentProjectData', JSON.stringify(payload));
                window.location.href = 'create.php';
            } catch (error) {
                console.error('Error copying parent project:', error);
                showMessage('建物のコピーに失敗しました。', true);
            } finally {
                this.copyingParentProjectId = null;
            }
        },
        /** Space + drag to pan the table horizontally (same UX as project list) */
        setupSpaceDragScroll() {
            if (this._spaceDragScrollBound) return;
            this._spaceDragScrollBound = true;
            let spaceHeld = false;
            let dragging = false;
            let startX = 0;
            let startScrollLeft = 0;
            const getScrollContainer = () => document.getElementById('parentProjectsTableScroll');
            const setPanClass = (on, draggingNow) => {
                const el = getScrollContainer();
                if (!el) return;
                el.classList.toggle('parent-projects-space-pan', !!on);
                el.classList.toggle('is-dragging', !!draggingNow);
            };
            document.addEventListener('keydown', (e) => {
                if (e.key !== ' ' && e.which !== 32) return;
                const target = e.target || e.srcElement;
                if (!target) return;
                const tag = (target.tagName || '').toLowerCase();
                if (tag === 'input' || tag === 'textarea' || tag === 'select' ||
                    target.isContentEditable || (target.closest && target.closest('.ql-editor'))) {
                    return;
                }
                e.preventDefault();
                spaceHeld = true;
                setPanClass(true, false);
            });
            document.addEventListener('keyup', (e) => {
                if (e.key !== ' ' && e.which !== 32) return;
                spaceHeld = false;
                dragging = false;
                setPanClass(false, false);
            });
            document.addEventListener('mousedown', (e) => {
                if (!spaceHeld || e.which !== 1) return;
                const container = getScrollContainer();
                if (!container || !container.contains(e.target)) return;
                e.preventDefault();
                dragging = true;
                startX = e.clientX;
                startScrollLeft = container.scrollLeft;
                setPanClass(true, true);
            });
            document.addEventListener('mousemove', (e) => {
                if (!dragging) return;
                const container = getScrollContainer();
                if (!container) return;
                container.scrollLeft = startScrollLeft + (startX - e.clientX);
                this.syncStickyHeaderScroll();
            });
            document.addEventListener('mouseup', () => {
                dragging = false;
                setPanClass(spaceHeld, false);
            });
        },
        /** Fixed table header while page scrolls (same UX as project list) */
        setupStickyTableHeader() {
            if (this._stickyHeaderBound) return;
            this._stickyHeaderBound = true;
            this._stickyHeadWrap = null;
            this._stickyHeadTable = null;
            this._stickyHeadSignature = '';

            // capture:true — layout often scrolls inside nested containers (same as project list)
            const onScroll = (e) => {
                if (e.target && e.target.id === 'parentProjectsTableScroll') {
                    this.syncStickyHeaderScroll();
                    return;
                }
                this.updateStickyTableHeader(false);
            };
            window.addEventListener('scroll', onScroll, true);
            window.addEventListener('resize', () => this.updateStickyTableHeader(true));

            this.$nextTick(() => this.updateStickyTableHeader(true));
        },
        ensureStickyHeaderElements() {
            if (this._stickyHeadWrap) return;
            const wrap = document.createElement('div');
            wrap.id = 'parentProjectsStickyHead';
            wrap.className = 'parent-projects-sticky-head';
            wrap.setAttribute('aria-hidden', 'true');
            const table = document.createElement('table');
            table.className = 'table parent-projects-table parent-projects-sticky-table mb-0';
            wrap.appendChild(table);
            document.body.appendChild(wrap);
            wrap.addEventListener('click', (e) => {
                const th = e.target && e.target.closest ? e.target.closest('th') : null;
                if (!th || !th.parentNode) return;
                const idx = Array.prototype.indexOf.call(th.parentNode.children, th);
                const container = document.getElementById('parentProjectsTableScroll');
                const origThead = container && container.querySelector('thead');
                const origTh = origThead && origThead.querySelectorAll('th')[idx];
                if (origTh) origTh.click();
            });
            this._stickyHeadWrap = wrap;
            this._stickyHeadTable = table;
        },
        getStickyHeaderSignature(thead, table) {
            const ths = thead ? thead.querySelectorAll('th') : [];
            const labels = Array.prototype.map.call(ths, (th) => (th.textContent || '').trim()).join('|');
            return labels + '#' + (table ? table.offsetWidth : 0) + '#' + ths.length;
        },
        rebuildStickyHeaderClone(table, thead) {
            this.ensureStickyHeaderElements();
            const stickyTable = this._stickyHeadTable;
            stickyTable.innerHTML = '';
            stickyTable.appendChild(thead.cloneNode(true));
            stickyTable.style.width = table.offsetWidth + 'px';
            stickyTable.style.minWidth = table.offsetWidth + 'px';
            const srcThs = thead.querySelectorAll('th');
            const dstThs = stickyTable.querySelectorAll('th');
            srcThs.forEach((th, i) => {
                if (!dstThs[i]) return;
                const w = th.getBoundingClientRect().width;
                dstThs[i].style.width = w + 'px';
                dstThs[i].style.minWidth = w + 'px';
                dstThs[i].style.maxWidth = w + 'px';
                dstThs[i].style.boxSizing = 'border-box';
            });
            this._stickyHeadSignature = this.getStickyHeaderSignature(thead, table);
        },
        syncStickyHeaderScroll() {
            const container = document.getElementById('parentProjectsTableScroll');
            if (!container || !this._stickyHeadWrap) return;
            this._stickyHeadWrap.scrollLeft = container.scrollLeft;
        },
        /** Pixels below fixed navbar (and test banner) — same idea as project list 4.6rem */
        getStickyHeaderTopOffset() {
            const navbar = document.querySelector('.layout-navbar');
            if (navbar) {
                const bottom = navbar.getBoundingClientRect().bottom;
                if (bottom > 0) return Math.ceil(bottom);
            }
            const banner = document.querySelector('.env-test-banner');
            const bannerH = banner ? banner.getBoundingClientRect().height : 0;
            const rootFs = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
            return Math.ceil(bannerH + 4.6 * rootFs);
        },
        updateStickyTableHeader(forceRebuild) {
            const container = document.getElementById('parentProjectsTableScroll');
            const table = container && container.querySelector('table.parent-projects-table');
            const thead = table && table.tHead;
            if (!container || !table || !thead || this.loading) {
                if (this._stickyHeadWrap) this._stickyHeadWrap.style.display = 'none';
                return;
            }

            this.ensureStickyHeaderElements();
            const wrap = this._stickyHeadWrap;
            const rect = container.getBoundingClientRect();
            const headH = thead.offsetHeight || 40;
            const navOffset = this.getStickyHeaderTopOffset();
            // Stick once thead would go under the navbar (same timing idea as project list)
            const shouldStick = rect.top < navOffset && rect.bottom > navOffset + headH;

            if (!shouldStick) {
                wrap.style.display = 'none';
                return;
            }

            const signature = this.getStickyHeaderSignature(thead, table);
            if (forceRebuild || signature !== this._stickyHeadSignature || !this._stickyHeadTable.tHead) {
                this.rebuildStickyHeaderClone(table, thead);
            }

            wrap.style.display = 'block';
            wrap.style.top = navOffset + 'px';
            wrap.style.zIndex = '1002'; // above layout-page::before (1001), below modals
            wrap.style.left = Math.round(rect.left) + 'px';
            wrap.style.width = Math.round(rect.width) + 'px';
            wrap.scrollLeft = container.scrollLeft;
        }
    },
    mounted() {
        this.loadColumnVisibilityFromStorage();
        this.loadFiltersFromStorage();
        this.loadPermission();
        this.loadParentProjects();
        this.setupSpaceDragScroll();
        this.setupStickyTableHeader();
        document.addEventListener('click', () => {
            this.closeParentProjectContextMenu();
        });
        const editParentModalEl = document.getElementById('editParentProjectModal');
        if (editParentModalEl) {
            editParentModalEl.addEventListener('hidden.bs.modal', () => {
                this.destroyEditParentWidgets();
            });
        }
        // Auto refresh danh sách parent project (giống project/index.php)
        if (!this.autoRefreshTimer) {
            this.autoRefreshTimer = setInterval(() => {
                if (!this.loading) {
                    this.loadParentProjects();
                }
            }, this.autoRefreshInterval);
        }
    },
    updated() {
        // Re-translate i18n elements after any DOM update
        this.$nextTick(() => {
            this.translateI18n();
        });
    }
}).mount('#app'); 