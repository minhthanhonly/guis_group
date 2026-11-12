/**
 * 建物情報 / 建物編集 modal — standalone Vue app shared by:
 *   - parent_project/index.php  (建物編集, opens directly in edit mode)
 *   - project/detail.php        (建物情報 button)
 *   - project/index.php         (建物詳細 button of the project list)
 *
 * Markup: parent_project/partials/parent-project-quick-modal.php (include it OUTSIDE the host page's Vue root).
 * Needs: Vue 3, axios, jQuery + select2, Tagify, flatpickr, bootstrap, parent-project-customers-mixin.js,
 *        parent-project-tagify-options.js (optional), parent-project-error.js (optional).
 *
 * API:
 *   window.openParentProjectModal(parentProjectId, {
 *       mode: 'view' | 'edit',   // default 'view'; 'edit' is ignored without edit permission
 *       onSaved: function (id)   // called after a successful save
 *   })
 * A `parent-project:saved` event ({ detail: { id } }) is also dispatched on document.
 *
 * Edit permission: window.PARENT_PROJECT_QUICK_MODAL_CAN_EDIT (set by the partial from the session).
 */
(function () {
    'use strict';

    var HOST_ID = 'parent-project-quick-modal-app';
    var MODAL_ID = 'editParentProjectModal';
    var vm = null;

    var REQUEST_BADGE_CLASSES = {
        '意匠': 'bg-primary',
        '設備': 'bg-info',
        '3D設備': 'bg-success',
        '省エネ': 'bg-lime',
        '3D': 'bg-secondary',
        'その他': 'bg-dark'
    };
    // Department name -> request type it fulfils (same rules as project/assets/js/project-detail.js)
    var DEPARTMENT_REQUEST_RULES = [
        ['3D設備', '3D設備'],
        ['技術課設備', '3D設備'],
        ['省エネ', '省エネ'],
        ['意匠', '意匠'],
        ['設備', '設備']
    ];
    // Request types that have a checkbox in the edit form; any other stored value (e.g. 3D) is kept untouched
    var KNOWN_REQUEST_TYPES = ['意匠', '設備', '3D設備', '省エネ', 'その他'];

    function splitList(value) {
        return String(value || '')
            .split(',')
            .map(function (v) { return v.trim(); })
            .filter(Boolean);
    }

    function emptyProject() {
        return {
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
            city: '',
            structural_office: '',
            type1: '',
            type2: '',
            type3: '',
            status: 'draft',
            requests: '',
            materials: '',
            materials_memo: '',
            features: ''
        };
    }

    function notifyError(message, errorOrResponse) {
        if (typeof showParentProjectError === 'function') {
            showParentProjectError(message, errorOrResponse);
        } else if (typeof showMessage === 'function') {
            showMessage(message, true);
        }
    }

    function createAppInstance() {
        return Vue.createApp({
            mixins: [window.ParentProjectCustomersMixin],
            data: function () {
                var featureOptions = [
                    '施主宅付き',
                    'マルチルームあり',
                    '店舗併用住宅',
                    '事務所併用住宅',
                    '複合用途',
                    '共同住宅（共用部計算なし）',
                    '補助金案件',
                    '増築',
                    '用途変更'
                ];
                var featureFlags = {};
                featureOptions.forEach(function (opt) { featureFlags[opt] = false; });
                return {
                    canEdit: window.PARENT_PROJECT_QUICK_MODAL_CAN_EDIT === true,
                    // ids of this app's own customer modals (see the partial)
                    customerSearchModalId: 'qmCustomerSearchModal',
                    customerListModalId: 'qmCustomerListModal',
                    mode: 'view',            // 'view' | 'edit'
                    openedInMode: 'view',    // mode the modal was opened in: decides what cancel / save do
                    onSavedCallback: null,
                    guisReceiverName: '',
                    siblingProjects: [],
                    editParentProjectLoading: false,
                    editParentProjectSaving: false,
                    editParentProjectErrors: { company_name: '', project_name: '' },
                    editingParentProject: emptyProject(),
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
                    editParentRequestFlags: { design: false, equipment: false, equipment3d: false, energy: false, other: false },
                    editParentMaterialFlags: { layout: false, rental: false, contract: false, tac: false, other: false },
                    extraRequestTypes: [],
                    duplicateDismissedKey: '',
                    sharedHousingFeatureLabel: '共同住宅（共用部計算なし）',
                    featureOptions: featureOptions,
                    editParentFeatureFlags: featureFlags,
                    editParentType1Tagify: null,
                    editParentType2Tagify: null,
                    editParentScaleTagify: null,
                    editParentStructuralOfficeTagify: null,
                    editParentCityTagify: null,
                };
            },
            computed: {
                isEditMode: function () {
                    return this.mode === 'edit';
                },
                editHasNonResidentialType: function () {
                    return splitList(this.editingParentProject.type2).includes('非住宅');
                },
                editHasSharedHousingType: function () {
                    return splitList(this.editingParentProject.type2).includes('共同');
                },
                editVisibleFeatureOptions: function () {
                    var self = this;
                    return this.featureOptions.filter(function (opt) {
                        if (opt === self.sharedHousingFeatureLabel) {
                            return self.editHasSharedHousingType;
                        }
                        return true;
                    });
                },
                // ---- view mode ----
                viewRequestTypes: function () {
                    return splitList(this.editingParentProject.requests);
                },
                viewMaterials: function () {
                    return splitList(this.editingParentProject.materials);
                },
                viewFeatureLabels: function () {
                    var selected = splitList(this.editingParentProject.features);
                    var self = this;
                    return this.featureOptions.filter(function (opt) {
                        if (selected.indexOf(opt) === -1) return false;
                        if (opt === self.sharedHousingFeatureLabel && !self.editHasSharedHousingType) return false;
                        return true;
                    });
                },
                viewNonResidentialMainUseLabel: function () {
                    var code = String(this.editingParentProject.type3 || '');
                    if (!code) return '';
                    var found = this.nonResidentialMainUseOptions.find(function (opt) { return opt.value === code; });
                    return found ? found.label : code;
                },
                viewRequestDate: function () {
                    var raw = String(this.editingParentProject.request_date || '').trim();
                    if (!raw || raw.indexOf('0000') === 0) return '';
                    return raw.replace(/-/g, '/').slice(0, 16);
                }
            },
            watch: {
                'editingParentProject.type2': function () {
                    if (!this.editHasSharedHousingType && this.editParentFeatureFlags) {
                        this.editParentFeatureFlags[this.sharedHousingFeatureLabel] = false;
                    }
                },
            },
            methods: {
                splitList: splitList,

                // ---- ParentProjectCustomersMixin hooks ----
                getCustomerMirrorTarget: function () {
                    return this.editingParentProject;
                },
                // 顧客情報 -> 詳細: use the global customer modal (available on every page)
                showCustomerInfoModal: async function (customer) {
                    if (customer && customer.id && typeof window.openGlobalCustomerModal === 'function') {
                        this.keepModalOpenStateAfterHide('globalCustomerModal');
                        await window.openGlobalCustomerModal(customer.id);
                    }
                },

                // ---- view helpers ----
                getRequestBadgeClass: function (request) {
                    return REQUEST_BADGE_CLASSES[String(request || '').trim()] || 'bg-secondary';
                },
                getRequestUnfulfilledBadgeClass: function (request) {
                    return this.getRequestBadgeClass(request).replace(/^bg-/, 'badge-outline-');
                },
                // A request counts as fulfilled once a (non-deleted) project of the matching department exists
                isRequestFulfilled: function (requestType) {
                    var type = String(requestType || '').trim();
                    if (!type) return false;
                    return (this.siblingProjects || []).some(function (p) {
                        if (!p || String(p.status || '') === 'deleted') return false;
                        var name = String(p.department_name || '').trim();
                        for (var i = 0; i < DEPARTMENT_REQUEST_RULES.length; i++) {
                            if (name.indexOf(DEPARTMENT_REQUEST_RULES[i][0]) !== -1) {
                                return DEPARTMENT_REQUEST_RULES[i][1] === type;
                            }
                        }
                        return false;
                    });
                },
                parentDetailUrl: function () {
                    return this.editingParentProject.id
                        ? '/parent_project/detail.php?id=' + encodeURIComponent(this.editingParentProject.id)
                        : '#';
                },
                applyI18n: function () {
                    var modalEl = document.getElementById(MODAL_ID);
                    if (modalEl && typeof window.applyDataI18n === 'function') {
                        window.applyDataI18n(modalEl);
                    }
                },

                // ---- open / load ----
                open: async function (parentProjectId, options) {
                    var opts = options || {};
                    this.onSavedCallback = typeof opts.onSaved === 'function' ? opts.onSaved : null;
                    this.openedInMode = (opts.mode === 'edit' && this.canEdit) ? 'edit' : 'view';
                    this.destroyEditParentWidgets();
                    this.mode = 'view';
                    this.editParentProjectErrors = { company_name: '', project_name: '' };
                    this.duplicateDismissedKey = '';
                    this.editingParentProject = Object.assign(emptyProject(), { id: parentProjectId });
                    this.setCustomerListFromServer([]);
                    this.siblingProjects = [];
                    this.guisReceiverName = '';
                    this.editParentProjectLoading = true;

                    var modalEl = document.getElementById(MODAL_ID);
                    if (modalEl) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    }
                    var loaded = await this.loadParentProject(parentProjectId);
                    if (!loaded) {
                        if (modalEl) {
                            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                        }
                        return;
                    }
                    if (this.openedInMode === 'edit') {
                        await this.enterEditMode();
                    } else {
                        await this.$nextTick();
                        this.applyI18n();
                    }
                },
                loadParentProject: async function (parentProjectId) {
                    this.editParentProjectLoading = true;
                    try {
                        var id = encodeURIComponent(parentProjectId);
                        var results = await Promise.all([
                            axios.get('/api/index.php?model=parentproject&method=getById&id=' + id),
                            axios.get('/api/index.php?model=parentproject&method=getChildProjects&parent_project_id=' + id)
                                .catch(function (error) {
                                    console.error('Error loading child projects of the building:', error);
                                    return { data: [] };
                                })
                        ]);
                        var data = results[0].data || {};
                        if (!data.id) {
                            throw new Error('parent project not found');
                        }
                        var project = emptyProject();
                        Object.keys(project).forEach(function (key) {
                            if (data[key] != null) {
                                project[key] = data[key];
                            }
                        });
                        project.id = data.id;
                        project.status = data.status || 'draft';
                        this.editingParentProject = project;
                        this.setCustomerListFromServer(data.customers || []);
                        this.siblingProjects = Array.isArray(results[1].data) ? results[1].data : [];
                        this.parseEditParentRequests(project.requests);
                        this.parseEditParentMaterials(project.materials);
                        this.parseEditParentFeatures(project.features);
                        this.loadGuisReceiverName();
                        this.editParentProjectLoading = false;
                        return true;
                    } catch (error) {
                        console.error('Error loading parent project:', error);
                        if (typeof showMessage === 'function') {
                            showMessage('建物情報の読み込みに失敗しました。', true);
                        }
                        this.editParentProjectLoading = false;
                        return false;
                    }
                },
                loadGuisReceiverName: async function () {
                    var userid = String(this.editingParentProject.guis_receiver || '');
                    this.guisReceiverName = '';
                    if (!userid) {
                        return;
                    }
                    try {
                        var response = await axios.get('/api/index.php?model=user&method=searchMembers');
                        var list = (response.data && response.data.data) ? response.data.data : [];
                        var user = list.find(function (u) { return String(u.userid) === userid; });
                        if (user && String(this.editingParentProject.guis_receiver || '') === userid) {
                            this.guisReceiverName = user.realname || '';
                        }
                    } catch (error) {
                        console.error('Error loading GUIS receiver display name:', error);
                    }
                },

                // ---- view <-> edit ----
                enterEditMode: async function () {
                    if (!this.canEdit || this.editParentProjectLoading) {
                        return;
                    }
                    this.editParentProjectErrors = { company_name: '', project_name: '' };
                    this.customerListError = '';
                    this.mode = 'edit';
                    await this.$nextTick();
                    this.initEditParentSelect2();
                    this.initEditParentDatePicker();
                    this.initEditParentTagify();
                    this.applyI18n();
                },
                // キャンセル: opened for editing -> close; opened for viewing -> back to the (reloaded) view
                cancelEdit: async function () {
                    if (this.openedInMode === 'edit') {
                        this.closeModal();
                        return;
                    }
                    this.destroyEditParentWidgets();
                    this.mode = 'view';
                    await this.loadParentProject(this.editingParentProject.id);
                    await this.$nextTick();
                    this.applyI18n();
                },
                closeModal: function () {
                    var modalEl = document.getElementById(MODAL_ID);
                    if (modalEl) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    }
                },

                // ---- edit form helpers (moved from parent-project-index.js) ----
                parseEditParentRequests: function (requestsStr) {
                    var requests = splitList(requestsStr);
                    this.extraRequestTypes = requests.filter(function (r) {
                        return KNOWN_REQUEST_TYPES.indexOf(r) === -1;
                    });
                    this.editParentRequestFlags = {
                        design: requests.includes('意匠'),
                        equipment: requests.includes('設備'),
                        equipment3d: requests.includes('3D設備'),
                        energy: requests.includes('省エネ'),
                        other: requests.includes('その他')
                    };
                },
                parseEditParentMaterials: function (materialsStr) {
                    var materials = splitList(materialsStr);
                    this.editParentMaterialFlags = {
                        layout: materials.includes('配置図'),
                        rental: materials.includes('家賃審査書'),
                        contract: materials.includes('契約図'),
                        tac: materials.includes('TAC図'),
                        other: materials.includes('その他')
                    };
                },
                buildEditParentRequestsString: function () {
                    var arr = [];
                    if (this.editParentRequestFlags.design) arr.push('意匠');
                    if (this.editParentRequestFlags.equipment) arr.push('設備');
                    if (this.editParentRequestFlags.equipment3d) arr.push('3D設備');
                    if (this.editParentRequestFlags.energy) arr.push('省エネ');
                    if (this.editParentRequestFlags.other) arr.push('その他');
                    return arr.concat(this.extraRequestTypes || []).join(',');
                },
                buildEditParentMaterialsString: function () {
                    var arr = [];
                    if (this.editParentMaterialFlags.layout) arr.push('配置図');
                    if (this.editParentMaterialFlags.rental) arr.push('家賃審査書');
                    if (this.editParentMaterialFlags.contract) arr.push('契約図');
                    if (this.editParentMaterialFlags.tac) arr.push('TAC図');
                    if (this.editParentMaterialFlags.other) arr.push('その他');
                    return arr.join(',');
                },
                createEmptyFeatureFlags: function () {
                    var flags = {};
                    this.featureOptions.forEach(function (opt) { flags[opt] = false; });
                    return flags;
                },
                parseEditParentFeatures: function (featuresStr) {
                    var flags = this.createEmptyFeatureFlags();
                    splitList(featuresStr).forEach(function (item) {
                        if (Object.prototype.hasOwnProperty.call(flags, item)) {
                            flags[item] = true;
                        }
                    });
                    this.editParentFeatureFlags = flags;
                },
                buildEditParentFeaturesString: function () {
                    return this.editVisibleFeatureOptions.filter((opt) => this.editParentFeatureFlags[opt]).join(',');
                },
                destroyEditParentWidgets: function () {
                    ['#edit_pp_guis_receiver'].forEach(function (sel) {
                        var $el = $(sel);
                        if ($el.length && $el.data('select2')) {
                            try { $el.select2('destroy'); } catch (e) { /* ignore */ }
                        }
                        $el.empty().append('<option value="">選択してください</option>');
                    });
                    var dateEl = document.getElementById('edit_pp_request_date');
                    if (dateEl && dateEl._flatpickr) {
                        try { dateEl._flatpickr.destroy(); } catch (e) { /* ignore */ }
                    }
                    [
                        this.editParentType1Tagify,
                        this.editParentType2Tagify,
                        this.editParentScaleTagify,
                        this.editParentStructuralOfficeTagify,
                        this.editParentCityTagify
                    ].forEach(function (t) {
                        if (t && typeof t.destroy === 'function') {
                            try { t.destroy(); } catch (e) { /* ignore */ }
                        }
                    });
                    this.editParentType1Tagify = null;
                    this.editParentType2Tagify = null;
                    this.editParentScaleTagify = null;
                    this.editParentStructuralOfficeTagify = null;
                    this.editParentCityTagify = null;
                    [
                        '#edit_pp_type1_tags',
                        '#edit_pp_type2_tags',
                        '#edit_pp_scale_tags',
                        '#edit_pp_structural_office_tags',
                        '#edit_pp_city_tags'
                    ].forEach(function (sel) {
                        var el = document.querySelector(sel);
                        if (el) el.value = '';
                    });
                },
                setEditParentCurrentDateTime: function () {
                    var now = new Date();
                    var pad = function (n) { return String(n).padStart(2, '0'); };
                    var dateStr = now.getFullYear() + '/' + pad(now.getMonth() + 1) + '/' + pad(now.getDate())
                        + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes());
                    this.editingParentProject.request_date = dateStr;
                    var dateEl = document.getElementById('edit_pp_request_date');
                    if (dateEl && dateEl._flatpickr) {
                        dateEl._flatpickr.setDate(dateStr, true);
                    } else if (dateEl) {
                        dateEl.value = dateStr;
                    }
                },
                clearEditParentTagifyTags: function (fieldName) {
                    if (fieldName === 'type1' && this.editParentType1Tagify) this.editParentType1Tagify.removeAllTags();
                    if (fieldName === 'type2' && this.editParentType2Tagify) this.editParentType2Tagify.removeAllTags();
                    if (fieldName === 'scale' && this.editParentScaleTagify) this.editParentScaleTagify.removeAllTags();
                    if (fieldName === 'city' && this.editParentCityTagify) this.editParentCityTagify.removeAllTags();
                    if (fieldName === 'structural_office' && this.editParentStructuralOfficeTagify) {
                        this.editParentStructuralOfficeTagify.removeAllTags();
                    }
                },
                bindTagifyField: function (tagify, field, onUpdate) {
                    if (!tagify) return;
                    if (window.ParentProjectTagifyOptions && typeof window.ParentProjectTagifyOptions.bindSaveOnAdd === 'function') {
                        window.ParentProjectTagifyOptions.bindSaveOnAdd(tagify, field, onUpdate);
                    } else {
                        tagify.on('add', onUpdate);
                        tagify.on('remove', onUpdate);
                    }
                },
                loadTagifyWhitelists: async function () {
                    var T = window.ParentProjectTagifyOptions;
                    if (!T || typeof T.loadMergedWhitelist !== 'function') {
                        return {
                            type2: ['長屋', '共同', '戸建', '非住宅', '集合'],
                            scale: ['W2F3J', 'W2F4J', 'W2F5J', 'W2F6J', 'W3F3J', 'W3F4J', 'W3F5J', 'W3F6J', 'RC3F3J', 'RC3F4J', 'RC3F5J', 'RC3F6J', 'RC3F7J', 'RC3F8J'],
                            structural_office: [],
                            city: []
                        };
                    }
                    var lists = await Promise.all([
                        T.loadMergedWhitelist('type2'),
                        T.loadMergedWhitelist('scale'),
                        T.loadMergedWhitelist('structural_office'),
                        T.loadMergedWhitelist('city')
                    ]);
                    return { type2: lists[0], scale: lists[1], structural_office: lists[2], city: lists[3] || [] };
                },
                initEditParentDatePicker: function () {
                    var dateEl = document.getElementById('edit_pp_request_date');
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
                initEditParentTagify: async function () {
                    if (!window.Tagify) return;
                    var whitelists = await this.loadTagifyWhitelists();
                    var values = function (tagify) {
                        return tagify.value.map(function (t) { return t.value; }).join(',');
                    };

                    var type1Input = document.querySelector('#edit_pp_type1_tags');
                    if (type1Input) {
                        if (type1Input.tagify) { try { type1Input.tagify.destroy(); } catch (e) { /* ignore */ } }
                        type1Input.value = '';
                        this.editParentType1Tagify = new Tagify(type1Input, {
                            whitelist: ['TAC', '特注'],
                            maxTags: 5,
                            dropdown: { maxItems: 20, classname: 'tags-look-type1', enabled: 0, closeOnSelect: true }
                        });
                        var updateType1 = () => {
                            this.editingParentProject.type1 = values(this.editParentType1Tagify);
                        };
                        this.editParentType1Tagify.on('add', updateType1);
                        this.editParentType1Tagify.on('remove', updateType1);
                        var type1Val = String(this.editingParentProject.type1 || '').trim();
                        if (type1Val) this.editParentType1Tagify.addTags(type1Val);
                    }

                    var type2Input = document.querySelector('#edit_pp_type2_tags');
                    if (type2Input) {
                        if (type2Input.tagify) { try { type2Input.tagify.destroy(); } catch (e) { /* ignore */ } }
                        type2Input.value = '';
                        this.editParentType2Tagify = new Tagify(type2Input, {
                            whitelist: whitelists.type2.slice(),
                            enforceWhitelist: false,
                            maxTags: 1,
                            dropdown: { maxItems: 40, classname: 'tags-look-type2', enabled: 0, closeOnSelect: true }
                        });
                        this.bindTagifyField(this.editParentType2Tagify, 'type2', () => {
                            this.editingParentProject.type2 = values(this.editParentType2Tagify);
                            if (!this.editHasNonResidentialType) {
                                this.editingParentProject.type3 = '';
                            }
                        });
                        var type2Val = String(this.editingParentProject.type2 || '').trim();
                        if (type2Val) this.editParentType2Tagify.addTags(type2Val);
                    }

                    var scaleInput = document.querySelector('#edit_pp_scale_tags');
                    if (scaleInput) {
                        if (scaleInput.tagify) { try { scaleInput.tagify.destroy(); } catch (e) { /* ignore */ } }
                        scaleInput.value = '';
                        this.editParentScaleTagify = new Tagify(scaleInput, {
                            whitelist: whitelists.scale.slice(),
                            enforceWhitelist: false,
                            maxTags: 5,
                            dropdown: { maxItems: 40, classname: 'tags-look-scale', enabled: 0, closeOnSelect: true }
                        });
                        this.bindTagifyField(this.editParentScaleTagify, 'scale', () => {
                            this.editingParentProject.scale = values(this.editParentScaleTagify);
                        });
                        var scaleVal = String(this.editingParentProject.scale || '').trim();
                        if (scaleVal) this.editParentScaleTagify.addTags(scaleVal);
                    }

                    var structuralOfficeInput = document.querySelector('#edit_pp_structural_office_tags');
                    if (structuralOfficeInput) {
                        if (structuralOfficeInput.tagify) { try { structuralOfficeInput.tagify.destroy(); } catch (e) { /* ignore */ } }
                        structuralOfficeInput.value = '';
                        this.editParentStructuralOfficeTagify = new Tagify(structuralOfficeInput, {
                            whitelist: whitelists.structural_office.slice(),
                            enforceWhitelist: false,
                            maxTags: 1,
                            dropdown: { maxItems: 40, classname: 'tags-look-structural-office', enabled: 0, closeOnSelect: true }
                        });
                        this.bindTagifyField(this.editParentStructuralOfficeTagify, 'structural_office', () => {
                            this.editingParentProject.structural_office = values(this.editParentStructuralOfficeTagify);
                        });
                        var officeVal = String(this.editingParentProject.structural_office || '').trim();
                        if (officeVal) this.editParentStructuralOfficeTagify.addTags(officeVal);
                    }

                    var cityInput = document.querySelector('#edit_pp_city_tags');
                    if (cityInput) {
                        if (cityInput.tagify) { try { cityInput.tagify.destroy(); } catch (e) { /* ignore */ } }
                        cityInput.value = '';
                        this.editParentCityTagify = new Tagify(cityInput, {
                            whitelist: (whitelists.city || []).slice(),
                            enforceWhitelist: false,
                            maxTags: 1,
                            dropdown: { maxItems: 40, classname: 'tags-look-city', enabled: 0, closeOnSelect: true }
                        });
                        this.bindTagifyField(this.editParentCityTagify, 'city', () => {
                            this.editingParentProject.city = values(this.editParentCityTagify);
                        });
                        var cityVal = String(this.editingParentProject.city || '').trim();
                        if (cityVal) this.editParentCityTagify.addTags(cityVal);
                    }

                },
                initEditParentSelect2: function () {
                    var modalEl = document.getElementById(MODAL_ID);
                    var dropdownParent = modalEl ? $(modalEl) : $(document.body);
                    var $guis = $('#edit_pp_guis_receiver');
                    if (!$guis.length) {
                        return;
                    }
                    if ($guis.data('select2')) $guis.select2('destroy');
                    $guis.empty().append('<option value="">選択してください</option>');
                    $guis.select2({
                        placeholder: '選択してください',
                        dropdownParent: dropdownParent,
                        allowClear: true,
                        minimumResultsForSearch: 0,
                        ajax: {
                            url: '/api/index.php?model=user&method=searchMembers',
                            dataType: 'json',
                            delay: 250,
                            data: function (params) { return { search: params.term, page: params.page || 1 }; },
                            processResults: function (data) {
                                return {
                                    results: (data.data || []).map(function (item) {
                                        return { id: item.userid, text: item.realname };
                                    })
                                };
                            }
                        }
                    }).on('select2:select', (e) => {
                        this.editingParentProject.guis_receiver = e.params.data.id;
                    }).on('select2:clear', () => {
                        this.editingParentProject.guis_receiver = '';
                    });
                    if (this.editingParentProject.guis_receiver) {
                        this.loadEditParentGuisReceiverDisplay();
                    }
                },
                loadEditParentGuisReceiverDisplay: async function () {
                    try {
                        var response = await axios.get('/api/index.php?model=user&method=searchMembers');
                        var list = (response.data && response.data.data) ? response.data.data : [];
                        var userid = String(this.editingParentProject.guis_receiver || '');
                        var user = list.find(function (u) { return String(u.userid) === userid; });
                        var $guis = $('#edit_pp_guis_receiver');
                        if ($guis.length && $guis.data('select2') && user) {
                            $guis.empty();
                            var option = new Option(user.realname, userid, true, true);
                            $guis.append(option).trigger('change');
                        }
                    } catch (error) {
                        console.error('Error loading GUIS receiver display name:', error);
                    }
                },

                // ---- duplicate building check (same rule as the former inline edit of 建物詳細) ----
                getDuplicateCheckKey: function () {
                    return String(this.editingParentProject.construction_number || '').trim()
                        + '|' + String(this.editingParentProject.project_name || '').trim();
                },
                findDuplicateBuilding: async function () {
                    var construction = String(this.editingParentProject.construction_number || '').trim();
                    var projectName = String(this.editingParentProject.project_name || '').trim();
                    if (!construction && !projectName) {
                        return null;
                    }
                    try {
                        var response = await axios.get('/api/index.php', {
                            params: {
                                model: 'parentproject',
                                method: 'findDuplicate',
                                construction_number: construction,
                                project_name: projectName,
                                exclude_id: this.editingParentProject.id || ''
                            }
                        });
                        var data = response && response.data ? response.data : {};
                        if (data.status === 'success' && data.duplicate && data.duplicate.id) {
                            return data.duplicate;
                        }
                    } catch (error) {
                        console.error('Error checking duplicate parent project:', error);
                    }
                    return null;
                },
                // @returns {boolean} true when saving may go on
                confirmDuplicateBuildingIfNeeded: async function () {
                    var checkKey = this.getDuplicateCheckKey();
                    if (checkKey === '|' || checkKey === this.duplicateDismissedKey) {
                        return true;
                    }
                    var duplicate = await this.findDuplicateBuilding();
                    if (!duplicate) {
                        return true;
                    }
                    var question = '既に同じ建物があります。このまま保存しますか？';
                    var proceed;
                    if (typeof Swal === 'undefined' || !Swal.fire) {
                        proceed = window.confirm(question);
                    } else {
                        var esc = function (v) {
                            return String(v == null || v === '' ? '-' : v)
                                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                        };
                        var result = await Swal.fire({
                            icon: 'warning',
                            title: '確認',
                            html: '<p class="mb-2">' + esc(question) + '</p><div class="text-start small">'
                                + '<div><strong>お施主様名:</strong> ' + esc(duplicate.project_name) + '</div>'
                                + '<div><strong>工事番号:</strong> ' + esc(duplicate.construction_number) + '</div>'
                                + '<div><strong>管理番号:</strong> ' + esc(duplicate.project_number) + '</div></div>',
                            showCancelButton: true,
                            confirmButtonText: 'はい',
                            cancelButtonText: 'いいえ',
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#6c757d',
                            reverseButtons: true
                        });
                        proceed = !!(result && result.isConfirmed);
                    }
                    if (proceed) {
                        this.duplicateDismissedKey = checkKey;
                    }
                    return proceed;
                },

                // ---- save ----
                saveParentProjectFromModal: async function () {
                    if (!this.canEdit) {
                        return;
                    }
                    this.editParentProjectErrors = { company_name: '', project_name: '' };
                    this.syncPrimaryCustomerFields();
                    var company = String(this.editingParentProject.company_name || '').trim();
                    var name = String(this.editingParentProject.project_name || '').trim();
                    var hasError = false;
                    // At least one customer; the primary one fills company / branch / contact
                    if (!this.validateCustomerList()) {
                        hasError = true;
                    }
                    if (!name) {
                        this.editParentProjectErrors.project_name = 'お施主様名は必須です';
                        hasError = true;
                    }
                    if (hasError) return;
                    if (!(await this.confirmDuplicateBuildingIfNeeded())) return;

                    this.editParentProjectSaving = true;
                    try {
                        var p = this.editingParentProject;
                        var formData = new FormData();
                        formData.append('id', p.id);
                        formData.append('company_name', company);
                        formData.append('branch_name', p.branch_name || '');
                        formData.append('contact_name', p.contact_name || '');
                        formData.append('customer_id', p.customer_id || '');
                        // Full customer list; the server takes the primary customer's names from the master
                        this.appendCustomersToFormData(formData);
                        formData.append('guis_receiver', p.guis_receiver || '');
                        formData.append('request_date', p.request_date || '');
                        formData.append('project_name', name);
                        formData.append('project_number', p.project_number || '');
                        formData.append('construction_number', p.construction_number || '');
                        formData.append('scale', p.scale || '');
                        formData.append('city', p.city || '');
                        formData.append('structural_office', p.structural_office || '');
                        formData.append('type1', p.type1 || '');
                        formData.append('type2', p.type2 || '');
                        formData.append('type3', this.editHasNonResidentialType ? (p.type3 || '') : '');
                        formData.append('status', p.status || 'draft');
                        formData.append('requests', this.buildEditParentRequestsString());
                        formData.append('materials', this.buildEditParentMaterialsString());
                        formData.append('materials_memo', this.editParentMaterialFlags.other ? (p.materials_memo || '') : '');
                        formData.append('features', this.buildEditParentFeaturesString());
                        // 省エネ向け資料 / 審査機関 / BELS are edited on the 省エネ child project, not on the building:
                        // not sent here, so the legacy building columns keep their old values.
                        var response = await axios.post('/api/index.php?model=parentproject&method=update', formData);
                        if (response.data && response.data.status === 'success') {
                            var savedId = p.id;
                            if (typeof showMessage === 'function') {
                                showMessage(response.data.message || '建物情報を更新しました。', false);
                            }
                            this.destroyEditParentWidgets();
                            if (this.openedInMode === 'edit') {
                                this.closeModal();
                            } else {
                                this.mode = 'view';
                                await this.loadParentProject(savedId);
                                await this.$nextTick();
                                this.applyI18n();
                            }
                            this.notifySaved(savedId);
                        } else {
                            notifyError('更新に失敗しました。', response && response.data);
                        }
                    } catch (error) {
                        console.error('Error saving parent project:', error);
                        notifyError('更新に失敗しました。', error);
                    } finally {
                        this.editParentProjectSaving = false;
                    }
                },
                notifySaved: function (savedId) {
                    if (this.onSavedCallback) {
                        try {
                            this.onSavedCallback(savedId);
                        } catch (error) {
                            console.error('parent project onSaved callback failed:', error);
                        }
                    }
                    try {
                        document.dispatchEvent(new CustomEvent('parent-project:saved', { detail: { id: savedId } }));
                    } catch (error) {
                        /* CustomEvent unsupported: the callback above is enough */
                    }
                }
            },
            mounted: function () {
                var modalEl = document.getElementById(MODAL_ID);
                if (modalEl) {
                    modalEl.addEventListener('hidden.bs.modal', () => {
                        this.destroyEditParentWidgets();
                        this.mode = 'view';
                    });
                }
            }
        });
    }

    function getVm() {
        if (vm) {
            return vm;
        }
        var host = document.getElementById(HOST_ID);
        if (!host || typeof Vue === 'undefined' || typeof Vue.createApp !== 'function'
            || !window.ParentProjectCustomersMixin || typeof axios === 'undefined') {
            return null;
        }
        vm = createAppInstance().mount(host);
        window.__parentProjectQuickModalVm = vm;
        return vm;
    }

    window.openParentProjectModal = function (parentProjectId, options) {
        var id = parseInt(parentProjectId, 10);
        var instance = id > 0 ? getVm() : null;
        if (!instance) {
            if (typeof showMessage === 'function') {
                showMessage('建物情報を開けません。ページを再読み込みしてください。', true);
            }
            return Promise.resolve(false);
        }
        return instance.open(id, options).then(function () { return true; });
    };
})();
