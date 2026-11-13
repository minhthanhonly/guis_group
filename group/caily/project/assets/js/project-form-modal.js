/**
 * Shared project (案件) form dialog. See project/partials/project-form-modal.php.
 *
 * The form itself (fields, validation, widgets, save) is window.ProjectFormMixin — the code
 * the project detail page runs. This file only adds what a dialog needs around it: open for
 * a given project id, load what the form expects, close, and tell the host page it saved.
 *
 *   window.openProjectFormModal(options)  -> Promise<boolean>
 *
 * options:
 *   projectId      edit this project
 *   mode: 'create' create a project instead (no projectId); needs parentProject
 *   parentProject  { id, project_name, customer_id } of the building (create)
 *   managerOnly    edit as a 管理 without full edit rights: the .pf-full-only fields are hidden
 *   siblings       the building's other projects (for the 省エネ drawing-share question)
 *   allowRestore   offer 復元 for a cancelled project
 *   onSaved(id, info)  info: { created, restored, departmentId, departmentName }
 *   onClosed()     closed without saving
 */
(function () {
    'use strict';

    var ROOT_ID = 'project-form-modal-app';
    var MODAL_ID = 'projectFormModal';
    var vm = null;

    function notifyError(message) {
        if (typeof window.showMessage === 'function') window.showMessage(message, true);
        else alert(message);
    }

    function buildApp() {
        return Vue.createApp({
            mixins: [window.ProjectDetailEnergyMixin, window.ProjectFormMixin].filter(Boolean),
            data: function () {
                return {
                    formLoading: false,
                    formOptions: {},
                    restoringProject: false
                };
            },
            mounted: function () {
                var self = this;
                var el = document.getElementById(MODAL_ID);
                if (!el) return;
                el.addEventListener('hidden.bs.modal', function () { self.afterFormHidden(); });
            },
            methods: {
                // ---- opening ----
                openForm: async function (options) {
                    var opts = options || {};
                    this.formOptions = opts;
                    this.resetFormState();
                    this.formCreateMode = opts.mode === 'create';
                    this.formManagerOnly = !this.formCreateMode && !!opts.managerOnly;
                    this.parentSiblingProjects = Array.isArray(opts.siblings) ? opts.siblings : [];
                    this.projectId = this.formCreateMode ? null : opts.projectId;
                    this.formLoading = true;
                    this.showFormModal();
                    try {
                        if (this.formCreateMode) {
                            await this.prepareNewProject(opts);
                        } else {
                            await Promise.all([this.loadPermission(), this.fetchFormProject()]);
                            if (!this.project || !this.project.id) {
                                throw new Error('project not found');
                            }
                        }
                        await Promise.all([this.loadDepartmentCustomFieldSets(), this.loadGuisReceiverForForm()]);
                    } catch (e) {
                        console.error('Project form: failed to load project', e);
                        this.formLoading = false;
                        this.hideFormModal();
                        notifyError('プロジェクトの読み込みに失敗しました。');
                        return false;
                    }
                    this.formLoading = false;
                    if (!this.canEditProject) {
                        this.hideFormModal();
                        notifyError('管理者のみプロジェクトを編集できます。');
                        return false;
                    }
                    // Same entry point as the detail page's 編集: snapshots the project, loads
                    // teams / users and creates the pickers, Tagify inputs and the editor.
                    this.toggleEditMode();
                    this.afterFormRendered();
                    return true;
                },
                /** Widgets the detail page sets up outside its form code, once the fields exist. */
                afterFormRendered: function () {
                    var self = this;
                    this.$nextTick(function () {
                        self.applyFormI18n();
                        setTimeout(function () {
                            if (!self.isEditMode) return;
                            self.initFormTagsTagify();
                            self.initGuisReceiverSelect();
                            self.applyFormI18n();
                        }, 400);
                    });
                },
                /** Create mode: an empty project of the given building; the user picks 部署 in the form. */
                prepareNewProject: async function (opts) {
                    var parent = opts.parentProject || {};
                    if (!parent.id) throw new Error('parentProject is required to create a project');
                    // The host only offers "create" to users who may; the server checks again.
                    this.permission = { can_manage_project: true, is_member: true, is_creator: true, rule: { project_edit: 1 } };
                    this.project = {
                        id: null,
                        version: 1,
                        name: parent.project_name || '',
                        project_number: '',
                        department_id: '',
                        department_name: '',
                        parent_project_id: parent.id,
                        customer_id: parent.customer_id || '',
                        is_kadai: 0,
                        status: '',
                        priority: 'medium',
                        progress: 0,
                        tantou: '',
                        project_order_type: '',
                        start_date: '',
                        end_date: '',
                        actual_end_date: '',
                        caily_nouki: '',
                        guis_nouki: '',
                        caily_nouki_status: '',
                        guis_nouki_status: '',
                        yotei: '',
                        teams: '',
                        team_list: [],
                        tags: '',
                        description: '',
                        custom_fields: '',
                        guis_receiver: '',
                        energy_materials: '',
                        energy_materials_memo: '',
                        application_review_agency: '',
                        bels_zeh_grade: '',
                        energy_drawing_share_status: '',
                        energy_drawing_share_reason: '',
                        energy_drawing_share_note: ''
                    };
                    this.syncYoteiDraftFromProject();
                    await this.loadFormDepartments();
                },
                loadFormDepartments: async function () {
                    try {
                        // every department that can own projects, not only the user's own
                        var response = await axios.get('/api/index.php?model=department&method=list_department');
                        var list = Array.isArray(response.data) ? response.data : ((response.data && response.data.data) || []);
                        this.formDepartments = list.filter(function (d) {
                            return d && (d.can_project == 1 || d.can_project === '1')
                                && (d.is_active == 1 || d.is_active === '1' || d.is_active === undefined);
                        });
                    } catch (e) {
                        console.error('Project form: failed to load departments', e);
                        this.formDepartments = [];
                    }
                },
                /**
                 * Create mode: 部署 decides the people to pick from, the custom fields, the 受注形態
                 * choices and the 省エネ fields, so the form is rebuilt for it. What was typed stays;
                 * チーム / 管理 / メンバー belong to the previous 部署 and are cleared.
                 */
                onFormDepartmentChange: async function () {
                    if (!this.formCreateMode || !this.project) return;
                    var self = this;
                    var project = this.project;
                    var dept = (this.formDepartments || []).find(function (d) { return String(d.id) === String(project.department_id); });
                    // keep what is only held by the widgets
                    this.syncProjectDateFieldsFromPickers();
                    var dates = this._serverProjectDates || {};
                    ['start_date', 'end_date', 'caily_nouki', 'guis_nouki', 'actual_end_date'].forEach(function (key) {
                        if (dates[key] != null) project[key] = dates[key];
                    });
                    if (this.quillContent !== undefined) project.description = this.quillContent;
                    if (this.projectOrderTypeTagify) {
                        project.project_order_type = (this.projectOrderTypeTagify.value || []).map(function (t) { return t.value; }).join(',');
                    }
                    var yotei = Object.assign({}, this.yoteiDraft);
                    var guisReceiver = Object.assign({}, this.guisReceiverForm);

                    project.department_name = dept ? (dept.name || '') : '';
                    project.teams = '';
                    project.team_list = [];
                    project.custom_fields = '';
                    this.managers = [];
                    this.members = [];
                    this.departmentUsers = [];
                    this.newProject = { members: '', managers: '', teams: '' };
                    var allowed = this.getProjectOrderTypeWhitelist();
                    project.project_order_type = String(project.project_order_type || '').split(',')
                        .map(function (v) { return v.trim(); })
                        .filter(function (v) { return v && allowed.indexOf(v) !== -1; }).join(',');
                    var status = (this.statuses || []).find(function (s) { return s.value === project.status; });
                    if (status && status.energyOnly && !this.isEnergyDepartmentProject) project.status = '';
                    // 省エネ departments do not use 担当 / 進捗率: a new project starts without them
                    if (this.isEnergyDepartmentProject) {
                        project.tantou = '';
                        project.progress = 0;
                    }

                    // rebuild the form for the new 部署
                    this.formLoading = true;
                    this.isEditMode = false;
                    await this.$nextTick();
                    this.destroyFormWidgets();
                    this.customFields = [];
                    this.validationErrors = {};
                    await this.loadDepartmentCustomFieldSets();
                    this.formLoading = false;
                    this.toggleEditMode();
                    this.yoteiDraft = yotei;
                    this.guisReceiverForm = guisReceiver;
                    this.afterFormRendered();
                },
                /** What the detail page's loadProject() prepares for the form, without the page-only parts. */
                fetchFormProject: async function () {
                    var response = await axios.get('/api/index.php?model=project&method=getById&id=' + encodeURIComponent(this.projectId));
                    var project = response.data;
                    if (!project || typeof project !== 'object' || !project.id) {
                        this.project = null;
                        return;
                    }
                    var version = Number(project.version);
                    project.version = Number.isFinite(version) && version > 0 ? version : 1;
                    var self = this;
                    // API values are entity-encoded; these are plain text shown in inputs
                    ['name', 'project_number', 'tags', 'project_order_type', 'payment_note', 'energy_drawing_share_note'].forEach(function (field) {
                        var value = project[field];
                        if (typeof value === 'string' && value.indexOf('&') !== -1) {
                            project[field] = self.decodeHtmlEntities(value);
                        }
                    });
                    project.caily_nouki_status = project.caily_nouki_status || '';
                    project.guis_nouki_status = project.guis_nouki_status || '';
                    project.energy_drawing_share_status = project.energy_drawing_share_status || '';
                    project.energy_drawing_share_reason = project.energy_drawing_share_reason || '';
                    project.energy_drawing_share_note = project.energy_drawing_share_note || '';
                    this.project = project;
                    this.syncYoteiDraftFromProject();
                    var secondary = [this.loadMembers()];
                    if (project.teams) secondary.push(this.loadTeamListByIds(project.teams));
                    else this.project.team_list = [];
                    await Promise.all(secondary);
                },
                resetFormState: function () {
                    this.destroyFormWidgets();
                    this.isEditMode = false;
                    this.project = null;
                    this.originalProject = null;
                    this.managers = [];
                    this.members = [];
                    this.customFields = [];
                    this.departmentCustomFieldSets = [];
                    this.validationErrors = {};
                    this.savingProject = false;
                    this.quillContent = undefined;
                    this.newProject = { members: '', managers: '', teams: '' };
                    this._serverProjectDates = null;
                    this._formSaved = false;
                    this.guisReceiverForm = { enabled: false, useParent: true, userid: '', parentUserid: '', parentName: '' };
                    this.formCreateMode = false;
                    this.formManagerOnly = false;
                    this.parentSiblingProjects = [];
                    this.departmentUsers = [];
                    this.permission = {};
                    this.restoringProject = false;
                },

                // ---- what the mixin calls on its host ----
                /**
                 * The mixin calls loadProject() in two situations:
                 *  - after a successful save (it has already left edit mode): the dialog is done;
                 *  - to recover from a version conflict (still in edit mode): reload the form.
                 */
                loadProject: async function () {
                    if (!this.isEditMode) {
                        this.finishSaved();
                        return;
                    }
                    await this.openForm(this.formOptions);
                },
                // Page-only features the form does not have
                addZoomToDescriptionImages: function () {},
                initVietnamTimeTooltips: function () {},
                loadMentionUsers: function () {},
                openParentProjectInfoModal: function () {},
                getProjectIdFromUrl: function () { return null; },
                /** タグ and 進捗率 are saved with the rest of the form here, not on their own as on the detail page. */
                updateTags: function () {},
                updateProgress: function () {},

                // ---- closing ----
                finishSaved: function () {
                    this._formSaved = true;
                    var project = this.project || {};
                    var id = project.id || this.projectId;
                    var info = {
                        created: !!this.formCreateMode,
                        restored: !!this._formRestored,
                        departmentId: project.department_id || '',
                        departmentName: project.department_name || ''
                    };
                    this._formRestored = false;
                    var opts = this.formOptions || {};
                    this.hideFormModal();
                    if (typeof opts.onSaved === 'function') {
                        try { opts.onSaved(id, info); } catch (e) { console.error('Project form: onSaved failed', e); }
                    }
                    try {
                        document.dispatchEvent(new CustomEvent('project:saved', { detail: Object.assign({ id: id }, info) }));
                    } catch (e) { /* ignore */ }
                },
                /** 復元: a cancelled project goes back to the status it had before. */
                restoreProject: async function () {
                    var project = this.project;
                    if (!project || !project.id || this.restoringProject || this.savingProject) return;
                    if (typeof Swal !== 'undefined') {
                        var result = await Swal.fire({
                            title: '案件依頼を復元しますか？',
                            text: '案件「' + (project.name || '') + '」を復元します。',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#28a745',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: '復元',
                            cancelButtonText: '取消'
                        });
                        if (!result.isConfirmed) return;
                    }
                    this.restoringProject = true;
                    try {
                        var formData = new FormData();
                        formData.append('id', project.id);
                        formData.append('status', project.previous_status || 'open');
                        var response = await axios.post('/api/index.php?model=project&method=updateStatus', formData);
                        if (this.isQuickEditSaved(response.data)) {
                            if (typeof window.showMessage === 'function') window.showMessage('案件依頼を復元しました。');
                            this._formRestored = true;
                            this.finishSaved();
                        } else {
                            var data = response.data || {};
                            notifyError(data.error || data.message || '復元に失敗しました');
                        }
                    } catch (e) {
                        console.error('Project form: restore failed', e);
                        notifyError('案件依頼の復元に失敗しました。');
                    } finally {
                        this.restoringProject = false;
                    }
                },
                cancelForm: function () {
                    if (this.savingProject) return;
                    if (this.isEditMode && this.originalProject) {
                        this.cancelEdit();
                    } else {
                        this.isEditMode = false;
                    }
                    this.hideFormModal();
                },
                afterFormHidden: function () {
                    if (this.isEditMode) {
                        // closed some other way while still editing: leave edit mode cleanly
                        if (this.originalProject) this.cancelEdit();
                        else this.isEditMode = false;
                    }
                    this.destroyFormWidgets();
                    var opts = this.formOptions || {};
                    if (!this._formSaved && typeof opts.onClosed === 'function') {
                        try { opts.onClosed(); } catch (e) { /* ignore */ }
                    }
                },
                destroyFormWidgets: function () {
                    var self = this;
                    this.destroyGuisReceiverSelect();
                    ['tagify', 'managerTagify', 'membersTagify', 'projectOrderTypeTagify', 'formTagsTagify'].forEach(function (key) {
                        var instance = self[key];
                        if (instance && typeof instance.destroy === 'function') {
                            try { instance.destroy(); } catch (e) { /* element already gone */ }
                        }
                        self[key] = null;
                    });
                    this.prevTeamIds = [];
                },

                // ---- widgets the detail page sets up outside its form code ----
                initFormTagsTagify: function () {
                    var root = document.getElementById(ROOT_ID);
                    var input = root ? root.querySelector('#project_tags') : null;
                    if (!input || !window.Tagify || this.formTagsTagify) return;
                    var self = this;
                    try {
                        this.formTagsTagify = new Tagify(input, {
                            originalInputValueFormat: function (values) {
                                return values.map(function (item) { return item.value; }).join(',');
                            }
                        });
                        this.formTagsTagify.on('change', function () {
                            self.project.tags = self.formTagsTagify.value.map(function (item) { return item.value; }).join(',');
                        });
                    } catch (e) {
                        console.warn('Project form: tags input could not be enhanced', e);
                    }
                },
                applyFormI18n: function () {
                    var root = document.getElementById(ROOT_ID);
                    if (root && typeof window.applyDataI18n === 'function') {
                        window.applyDataI18n(root);
                    }
                },

                // ---- Bootstrap modal ----
                showFormModal: function () {
                    var el = document.getElementById(MODAL_ID);
                    if (!el || typeof bootstrap === 'undefined') return;
                    bootstrap.Modal.getOrCreateInstance(el).show();
                },
                hideFormModal: function () {
                    var el = document.getElementById(MODAL_ID);
                    if (!el || typeof bootstrap === 'undefined') return;
                    var instance = bootstrap.Modal.getInstance(el);
                    if (instance) instance.hide();
                }
            }
        });
    }

    function getVm() {
        if (vm) return vm;
        var root = document.getElementById(ROOT_ID);
        if (!root || typeof Vue === 'undefined' || !window.ProjectFormMixin || typeof axios === 'undefined') {
            return null;
        }
        vm = buildApp().mount(root);
        window.__projectFormModalVm = vm;
        return vm;
    }

    /**
     * Open the shared project form.
     * @param {{projectId: (number|string), onSaved?: function, onClosed?: function}} options
     * @returns {Promise<boolean>} false when the form could not be opened
     */
    window.openProjectFormModal = function (options) {
        var instance = getVm();
        if (!instance) {
            notifyError('案件フォームを開けません。ページを再読み込みしてください。');
            return Promise.resolve(false);
        }
        if (!options || (!options.projectId && options.mode !== 'create')) {
            return Promise.resolve(false);
        }
        return instance.openForm(options);
    };
})();
