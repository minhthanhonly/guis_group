<?php
/**
 * The project fields: one markup for the read-only page and for the edit / create form.
 * Vue template fragment, rendered inside an app that uses window.ProjectFormMixin
 * (project-form-mixin.js): the project detail page (isEditMode stays false there) and the
 * shared project form dialog (partials/project-form-modal.php, isEditMode true).
 * Layout: assets/css/demo.css (form) and the style block of project/detail.php (page).
 */
?>
                        <div class="row g-3 project-detail-grid project-form-grid" v-if="project">
                            <!-- 建物情報: opens the shared building modal (read-only; 編集 inside it when permitted) -->
                            <div v-if="project.parent_project_id" class="col-12 mb-2">
                                <button type="button" class="btn btn-outline-primary btn-sm" @click="openParentProjectInfoModal" title="建物情報">
                                    <i class="fa fa-building me-1"></i> <span data-i18n="建物情報">建物情報</span>
                                </button>
                            </div>
                            <div v-if="project.parent_project_id && (project.show_child_customer_info || project.show_child_guis_receiver)" class="col-12 mt-3">
                                <div class="border rounded p-3 border-primary">
                                    <div v-if="project.show_child_customer_info" :class="{ 'mb-3': project.show_child_guis_receiver }">
                                        <div class="fw-semibold small text-muted mb-2"><span data-i18n="顧客情報">顧客情報</span></div>
                                        <div class="small">{{ project.child_customer_company || '-' }}</div>
                                        <div v-if="project.child_customer_branch" class="small text-muted"><span data-i18n="支店名">支店名</span>: {{ project.child_customer_branch }}</div>
                                        <div v-if="project.child_customer_contact" class="small text-muted"><span data-i18n="担当様">担当様</span>: {{ project.child_customer_contact }}</div>
                                    </div>
                                    <div v-if="project.show_child_guis_receiver">
                                        <label class="form-label mb-1"><span data-i18n="GUIS 受付者">GUIS 受付者</span></label>
                                        <input type="text" class="form-control"
                                            :value="project.child_guis_receiver_display_name || project.child_guis_receiver_userid || '-'"
                                            readonly>
                                    </div>
                                </div>
                            </div>
                            <!-- Each titled section is its own block: one table per section on the read-only page -->
                            <!-- Two fixed columns of section tables: the same sections sit in the same column on the page and in the form -->
                            <div class="pd-col">
                            <div class="pd-section">
                            <div class="col-12 project-form-section">
                                <h6 class="project-form-section-title"><span data-i18n="基本情報">基本情報</span> <span v-if="!isEditMode && inlineSaveStatus.basic === 'loading'" class="text-muted ms-1" title="保存中"><i class="fa fa-spinner fa-spin"></i></span><span v-else-if="!isEditMode && inlineSaveStatus.basic === 'saved'" class="text-success ms-1" title="保存済み"><i class="fa fa-check-circle"></i></span></h6>
                            </div>
                            <!-- 部署: chosen in the form when creating (it decides the fields below); shown read-only when editing -->
                            <div class="col-md-12" v-if="isEditMode">
                                <label class="form-label"><span data-i18n="部署">部署</span> <span v-if="formCreateMode" class="text-danger">*</span></label>
                                <template v-if="formCreateMode">
                                    <select class="form-select" id="pf_department_id" v-model="project.department_id" @change="onFormDepartmentChange">
                                        <option value="" data-i18n="選択してください">選択してください</option>
                                        <option v-for="dept in formDepartments" :key="'pf_dept_' + dept.id" :value="dept.id">{{ dept.name }}</option>
                                    </select>
                                    <div v-if="validationErrors.department_id" class="invalid-feedback d-block">{{ validationErrors.department_id }}</div>
                                </template>
                                <input v-else type="text" class="form-control" :value="project.department_name || '-'" readonly>
                            </div>
                            <!-- GUIS受付者: child projects, only when the host page asks for it (see loadGuisReceiverForForm in project-form-mixin.js) -->
                            <div class="col-md-12" v-if="isEditMode && guisReceiverForm && guisReceiverForm.enabled">
                                <label class="form-label"><span data-i18n="GUIS受付者">GUIS受付者</span> <span v-if="!guisReceiverForm.useParent" class="text-danger">*</span></label>
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" id="pf_use_parent_guis_receiver" v-model="guisReceiverForm.useParent" @change="onUseParentGuisReceiverChange">
                                    <label class="form-check-label" for="pf_use_parent_guis_receiver"><span data-i18n="GUIS受付者は建物と同じ">GUIS受付者は建物と同じ</span></label>
                                </div>
                                <div v-if="guisReceiverForm.useParent" class="small text-muted">{{ guisReceiverForm.parentName || "-" }}</div>
                                <div v-show="!guisReceiverForm.useParent">
                                    <select id="pf_guis_receiver" class="form-select"></select>
                                </div>
                                <div v-if="validationErrors.guis_receiver" class="invalid-feedback d-block">{{ validationErrors.guis_receiver }}</div>
                            </div>
                            <div class="col-md-4" v-show="!formCreateMode">
                                <label class="form-label"><span data-i18n="ID">ID</span></label>
                                <input type="text" class="form-control" :value="project?.id || '-'" readonly>
                            </div>
                            <input v-if="isEditMode && project" type="hidden" v-model.number="project.version">
                            <div class="col-md-8 pf-full-only">
                                <label class="form-label"><span data-i18n="案件名">案件名</span>  <span class="text-danger">*</span></label>
                                <template v-if="isEditMode">
                                    <input type="text" class="form-control" v-model="project.name">
                                    <div v-if="validationErrors.name" class="invalid-feedback d-block">
                                        {{ validationErrors.name }}
                                    </div>
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control" :value="project.name || '-'" readonly>
                                </template>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><span data-i18n="ステータス">ステータス</span></label>
                                <div>
                                    <!-- For Kadai projects, show waiting status -->
                                    <div v-if="project.is_kadai == 1 && project.status !== 'cancelled'">
                                        <span class="badge bg-warning">承認待ち</span>
                                    </div>
                                    <!-- For normal projects, show actual status -->
                                    <div v-else>
                                        <div class="btn-group" v-if="canEditProject">
                                            <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light" 
                                                    :class="getStatusButtonClass(project.status)"
                                                    id="statusDropdown"
                                                    data-bs-toggle="dropdown" aria-expanded="false">
                                                {{ getStatusLabel(project.status) || '選択してください' }}
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li v-for="status in editableStatuses" :key="status.value">
                                                    <a class="dropdown-item waves-effect" href="javascript:void(0);" 
                                                    @click="selectStatus(status.value)">
                                                        {{ translateLabel(status.label) }}
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                        <div v-else>
                                            <span class="badge" :class="getStatusBadgeClass(project.status)">{{ getStatusLabel(project.status) }}</span>
                                        </div>
                                    </div>
                                    <div v-if="isEditMode && validationErrors.status" class="invalid-feedback d-block">{{ validationErrors.status }}</div>
                                </div>
                            </div>
                            <div class="col-md-4 pf-full-only" v-show="showField('order_type')">
                                <label class="form-label"><span data-i18n="受注形態">受注形態</span> <span v-if="isEditMode" class="text-danger">*</span></label>
                                <template v-if="isEditMode">
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control tagify" v-model="project.project_order_type" id="project_order_type" name="project_order_type" required>
                                        <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearTagifyTags('project_order_type')" title="すべて削除"><i class="fa fa-times"></i></button>
                                    </div>
                                    <div v-if="validationErrors.project_order_type" class="invalid-feedback d-block">{{ validationErrors.project_order_type }}</div>
                                </template>
                                <template v-else>
                                    <div style="min-height:38px;">
                                        <span v-if="project.project_order_type && project.project_order_type.split(',').length > 0">
                                            <span v-for="item in project.project_order_type.split(',')" :key="item.trim()" class="badge me-1" :class="getOrderTypeBadgeClass(item)">{{ item.trim() }}</span>
                                        </span>
                                        <span v-else>-</span>
                                    </div>
                                </template>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><span data-i18n="優先度">優先度</span></label>
                                <div>
                                    <div class="btn-group" v-if="canEditProject">
                                        <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light" 
                                                :class="getPriorityButtonClass(project.priority)"
                                                id="priorityDropdown"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                            {{ getPriorityLabel(project.priority) }}
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li v-for="priority in priorities" :key="priority.value">
                                                <a class="dropdown-item waves-effect" href="javascript:void(0);" 
                                                @click="selectPriority(priority.value)">
                                                    {{ priority.label }}
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    <div v-else>
                                        <span class="badge" :class="getPriorityBadgeClass(project.priority)">{{ getPriorityLabel(project.priority) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 pf-full-only" v-show="(!isEnergyDepartmentProject) && showField('tantou')">
                                <label class="form-label"><span data-i18n="担当">担当</span> <span v-if="isEditMode && !isCailyBranchUser" class="text-danger">*</span></label>
                                <div v-if="isEditMode && !isCailyBranchUser" class="d-flex gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" v-model="project.tantou" value="CAILY" id="tantou_caily">
                                        <label class="form-check-label" for="tantou_caily">CAILY</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" v-model="project.tantou" value="GUIS" id="tantou_guis">
                                        <label class="form-check-label" for="tantou_guis">GUIS</label>
                                    </div>
                                </div>
                                <div v-else class="fw-semibold">{{ project.tantou || '—' }}</div>
                                <div v-if="isEditMode && validationErrors.tantou" class="invalid-feedback d-block">{{ validationErrors.tantou }}</div>
                            </div>
                            <!-- 進捗率 is not used by 省エネ departments (progress is followed by tasks) -->
                            <div class="col-md-4" v-show="!isEnergyDepartmentProject">
                                <label class="form-label"><span data-i18n="進捗率">進捗率</span></label>
                                <div class="position-relative">
                                    <input
                                        type="range"
                                        min="0"
                                        max="100"
                                        step="5"
                                        v-model="project.progress"
                                        @change="updateProgress"
                                        class="form-range"
                                        :class="{'prevent-click': !canUpdateProgress}"
                                    >
                                    <div class="progress-value-label text-center">
                                        {{ project.progress }}%
                                    </div>
                                </div>
                            </div>
                            <!-- <div class="col-md-4">
                                <label class="form-label">予定時間</label>
                                <input type="text" class="form-control" :value="project.estimated_hours + 'h'" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">実績時間</label>
                                <input type="text" class="form-control" :value="project.actual_hours + 'h'" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">金額</label>
                                <input type="text" class="form-control" :value="formatCurrency(project.amount)" readonly>
                            </div> -->
                            </div>
                            <div class="pd-section" v-show="showSection('schedule')">
                            <div class="col-12 project-form-section" v-show="showSection('schedule')">
                                <h6 class="project-form-section-title"><span data-i18n="スケジュール">スケジュール</span> <span v-if="!isEditMode && inlineSaveStatus.schedule === 'loading'" class="text-muted ms-1" title="保存中"><i class="fa fa-spinner fa-spin"></i></span><span v-else-if="!isEditMode && inlineSaveStatus.schedule === 'saved'" class="text-success ms-1" title="保存済み"><i class="fa fa-check-circle"></i></span></h6>
                            </div>
                            <div class="col-12" v-show="showField('yotei')">
                                <label class="form-label"><span data-i18n="予定工程">予定工程</span></label>
                                <div v-if="isEditMode" class="d-flex flex-wrap align-items-center gap-2">
                                    <div>
                                        <div class="d-flex gap-1">
                                            <input type="text" class="form-control yotei-month-input" style="min-width: 9rem;" id="yotei_from_month_picker" :value="yoteiDraft.from_month" autocomplete="off" placeholder="YYYY-MM">
                                            <select class="form-select" style="width: 6.5rem;" v-model="yoteiDraft.from_part">
                                                <option v-for="opt in yoteiPartOptions" :key="'from-' + opt.value" :value="opt.value">{{ opt.label }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="text-muted">～</div>
                                    <div>
                                        <div class="d-flex gap-1">
                                            <input type="text" class="form-control yotei-month-input" style="min-width: 9rem;" id="yotei_to_month_picker" :value="yoteiDraft.to_month" autocomplete="off" placeholder="YYYY-MM">
                                            <select class="form-select" style="width: 6.5rem;" v-model="yoteiDraft.to_part" :disabled="!yoteiDraft.to_month">
                                                <option v-for="opt in yoteiPartOptions" :key="'to-' + opt.value" :value="opt.value">{{ opt.label }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" @click="clearYoteiDraft" data-i18n="クリア">クリア</button>
                                    <div v-if="yoteiPreview" class="ms-2 small text-body-secondary">{{ yoteiPreview }}</div>
                                    <div v-if="validationErrors.yotei" class="invalid-feedback d-block w-100">{{ validationErrors.yotei }}</div>
                                </div>
                                <input v-else type="text" class="form-control" :value="yoteiDisplayText" readonly>
                            </div>
                            <div class="col-md-4 pf-full-only" v-show="showField('start')">
                                <label class="form-label"><span data-i18n="開始日">開始日</span></label>
                                <div v-if="isEditMode" class="input-group">
                                    <input type="text" class="form-control" v-model="project.start_date" id="start_date_picker" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off">
                                    <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                                </div>
                                <input v-else type="text" class="form-control" :value="formatDateTime(project.start_date)" :data-time="project.start_date || ''" :data-todo-title="(project ? ('#' + project.id + ' ' + (project.name || '')) : '') + ''" :data-todo-link="project ? ('/project/detail.php?id=' + project.id) : ''" data-bs-toggle="tooltip" :data-bs-title="getVietnamTimeTooltip(project.start_date)" readonly>
                            </div>
                            <div class="col-md-4" v-if="canViewEndDate" v-show="showField('end')">
                                <label class="form-label">
                                    <span data-i18n="期限日(実納期)">期限日(実納期)</span>
                                </label>
                                <div class="pd-deadline-badges">
                                    <span v-if="getTimeRemaining()" :class="'badge ' + getTimeRemaining().class"  :title="getTimeRemaining().isOverdue ? '期限を超過しています' : '残り時間'"> {{ getTimeRemaining().text }} </span>
                                </div>
                                <template v-if="isEditMode">
                                    <div class="input-group">
                                        <input type="text" class="form-control" v-model="project.end_date" id="end_date_picker" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off"
                                            :class="{ 'is-invalid': validationErrors.end_date }">
                                        <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                                    </div>
                                    <div v-if="validationErrors.end_date" class="invalid-feedback d-block">
                                        {{ validationErrors.end_date }}
                                    </div>
                                </template>
                                <input v-else type="text" class="form-control" :value="formatDateTime(project.end_date)" :data-time="project.end_date || ''" :data-todo-title="(project ? ('#' + project.id + ' ' + (project.name || '')) : '') + ' 期限日'" :data-todo-link="project ? ('/project/detail.php?id=' + project.id) : ''" data-bs-toggle="tooltip" :data-bs-title="getVietnamTimeTooltip(project.end_date)" readonly>
                            </div>
                            <!-- 実終了日 only makes sense once the project is 完了 (v-show keeps the date picker input alive in edit mode) -->
                            <div class="col-md-4" v-show="(project.status === 'completed') && showField('actual_end')">
                                <label class="form-label"><span data-i18n="実終了日">実終了日</span></label>
                                <div v-if="isEditMode" class="input-group">
                                    <input type="text" class="form-control" v-model="project.actual_end_date" id="actual_end_date_picker" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off">
                                    <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                                </div>
                                <input v-else type="text" class="form-control" :value="formatDateTime(project.actual_end_date)" readonly>
                            </div>
                            <div class="col-md-4" v-show="showField('caily')">
                                <label class="form-label"><span data-i18n="CAILY納期">CAILY納期</span>
                                    <span v-if="isEditMode && project.end_date && project.tantou === 'CAILY' && !isEnergyDepartmentProject" class="text-danger">*</span>
                                </label>
                                <div class="pd-deadline-badges">
                                    <span v-if="cailyNoukiTimeRemaining()" :class="'badge ' + cailyNoukiTimeRemaining().class" :title="cailyNoukiTimeRemaining().isOverdue ? '期限を超過しています' : '残り時間'"> {{ cailyNoukiTimeRemaining().text }} </span>
                                    <span v-if="getNoukiJudgmentBadge('caily')" :class="'badge ' + getNoukiJudgmentBadge('caily').className"> {{ translateLabel(getNoukiJudgmentBadge('caily').textKey) }} </span>
                                </div>
                                <div v-if="isEditMode">
                                    <div class="input-group mb-1">
                                        <input type="text" class="form-control" v-model="project.caily_nouki" id="caily_nouki_picker" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off"
                                            :class="{ 'is-invalid': validationErrors.caily_nouki }">
                                        <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                                    </div>
                                    <div v-if="validationErrors.caily_nouki" class="invalid-feedback d-block">
                                        {{ validationErrors.caily_nouki }}
                                    </div>
                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" id="caily_nouki_status" v-model="project.caily_nouki_status" true-value="納品済み" false-value="">
                                        <label class="form-check-label" for="caily_nouki_status"><span data-i18n="納品済み">納品済み</span></label>
                                    </div>
                                </div>
                                <div v-else class="d-flex flex-column">
                                    <input type="text" class="form-control" :value="formatDateTime(project.caily_nouki)" :data-time="project.caily_nouki || ''" :data-todo-title="(project ? ('#' + project.id + ' ' + (project.name || '')) : '') + ' CAILY納期'" :data-todo-link="project ? ('/project/detail.php?id=' + project.id) : ''" data-bs-toggle="tooltip" :data-bs-title="getVietnamTimeTooltip(project.caily_nouki)" readonly>
                                    <div class="form-check mt-1" v-if="canEditProject">
                                        <input class="form-check-input" type="checkbox" id="caily_nouki_status_view" v-model="project.caily_nouki_status" true-value="納品済み" false-value="" @change="quickUpdateNoukiStatus('caily')">
                                        <label class="form-check-label" for="caily_nouki_status_view"><span data-i18n="納品済み">納品済み</span></label>
                                    </div>
                                    <div class="mt-1" v-else>
                                        <span class="badge bg-success" v-if="project.caily_nouki_status">{{ project.caily_nouki_status || '-' }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4" v-if="canViewEndDate" v-show="showField('guis')">
                                <label class="form-label"><span data-i18n="GUIS納期">GUIS納期</span>
                                    <span v-if="isEditMode && !isCailyBranchUser && project.end_date && project.tantou === 'GUIS' && !isEnergyDepartmentProject" class="text-danger">*</span>
                                </label>
                                <div class="pd-deadline-badges">
                                    <span v-if="guisNoukiTimeRemaining()" :class="'badge ' + guisNoukiTimeRemaining().class" :title="guisNoukiTimeRemaining().isOverdue ? '期限を超過しています' : '残り時間'"> {{ guisNoukiTimeRemaining().text }} </span>
                                    <span v-if="getNoukiJudgmentBadge('guis')" :class="'badge ' + getNoukiJudgmentBadge('guis').className"> {{ translateLabel(getNoukiJudgmentBadge('guis').textKey) }} </span>
                                </div>
                                <div v-if="isEditMode && !isCailyBranchUser">
                                    <div class="input-group mb-1">
                                        <input type="text" class="form-control" v-model="project.guis_nouki" id="guis_nouki_picker" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off"
                                            :class="{ 'is-invalid': validationErrors.guis_nouki }">
                                        <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                                    </div>
                                    <div v-if="validationErrors.guis_nouki" class="invalid-feedback d-block">
                                        {{ validationErrors.guis_nouki }}
                                    </div>
                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" id="guis_nouki_status" v-model="project.guis_nouki_status" true-value="納品済み" false-value="">
                                        <label class="form-check-label" for="guis_nouki_status"><span data-i18n="納品済み">納品済み</span></label>
                                    </div>
                                </div>
                                <div v-else class="d-flex flex-column">
                                    <input type="text" class="form-control" :value="formatDateTime(project.guis_nouki)" :data-time="project.guis_nouki || ''" :data-todo-title="(project ? ('#' + project.id + ' ' + (project.name || '')) : '') + ' GUIS納期'" :data-todo-link="project ? ('/project/detail.php?id=' + project.id) : ''" data-bs-toggle="tooltip" :data-bs-title="getVietnamTimeTooltip(project.guis_nouki)" readonly>
                                    <div class="form-check mt-1" v-if="canEditProject && !isCailyBranchUser">
                                        <input class="form-check-input" type="checkbox" id="guis_nouki_status_view" v-model="project.guis_nouki_status" true-value="納品済み" false-value="" @change="quickUpdateNoukiStatus('guis')">
                                        <label class="form-check-label" for="guis_nouki_status_view"><span data-i18n="納品済み">納品済み</span></label>
                                    </div>
                                    <div class="mt-1" v-else>
                                        <span class="badge bg-secondary">{{ project.guis_nouki_status || '未納品' }}</span>
                                    </div>
                                </div>
                            </div>
                            </div>
                            <!-- 既定タスク状況: default tasks marked show_on_project that exist in this project; no table when
                                 none does. Read-only page only (the form dialog
                                 never renders this: it is always in its edit state). -->
                            <div class="pd-section" v-if="!isEditMode && namedDefaultTaskRows && namedDefaultTaskRows.some(r => r.task && r.task.id)">
                            <div class="col-12 project-form-section">
                                <h6 class="project-form-section-title"><span data-i18n="既定タスク状況">既定タスク状況</span></h6>
                            </div>
                            <div class="col-12" v-for="row in namedDefaultTaskRows.filter(r => r.task && r.task.id)" :key="'ndt_' + row.key">
                                <label class="form-label">
                                    <a v-if="row.task && row.task.id" class="text-reset text-decoration-none" :href="'task.php?project_id=' + project.id + '&task_id=' + row.task.id" title="タスクへ">{{ translateLabel(row.title) }} <i class="fa fa-arrow-up-right-from-square small text-muted"></i></a>
                                    <span v-else>{{ translateLabel(row.title) }}</span>
                                </label>
                                <div class="pd-default-task">
                                    <span class="badge" :class="getNamedDefaultTaskStatusBadgeClass(row.task && row.task.status)">{{ getNamedDefaultTaskStatusLabel(row.task && row.task.status) }}</span>
                                    <span><span class="text-muted small me-1" data-i18n="担当">担当</span>{{ (row.task && row.task.assignee_name) ? row.task.assignee_name : '-' }}</span>
                                    <span><span class="text-muted small me-1" data-i18n="期限">期限</span>{{ (row.task && row.task.due_date) ? formatDateTime(row.task.due_date) : '-' }}</span>
                                    <div v-if="row.task && hasFieldValue(richTextToPlain(row.task.note))" class="pd-default-task-memo small text-break" v-html="sanitizeRichText(row.task.note)"></div>
                                </div>
                            </div>
                            </div>
                            </div>
                            <div class="pd-col">
                            <div class="pd-section" v-show="showSection('team')">
                            <div class="col-12 project-form-section" v-show="showSection('team')">
                                <h6 class="project-form-section-title"><span data-i18n="チーム・メンバー">チーム・メンバー</span></h6>
                            </div>
                            <!-- チーム / 担当 are not used by 省エネ departments -->
                            <div class="col-md-4" v-show="(!isEnergyDepartmentProject) && showField('team')">
                                <label class="form-label"><span data-i18n="チーム">チーム</span></label>
                                <div class="d-flex align-items-center gap-2">
                                    <input v-if="isEditMode" id="team_tags" class="form-control" />
                                    <button v-if="isEditMode" class="btn btn-outline-secondary btn-sm" type="button" @click="clearTagifyTags('team')" title="すべて削除"><i class="fa fa-times"></i></button>
                                    <div v-else>
                                        <span v-if="project.team_list && project.team_list.length > 0" class="d-flex flex-wrap gap-1">
                                            <span v-for="team in project.team_list" :key="team.id" class="badge bg-info">{{ team.name }}</span>
                                        </span>
                                        <span v-else class="text-muted">-</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4" v-show="showField('managers')">
                                <!-- 省エネ departments call 管理 "担当者" and do not use メンバー -->
                                <label class="form-label"><span v-show="!isEnergyDepartmentProject" data-i18n="管理">管理</span><span v-show="isEnergyDepartmentProject" data-i18n="担当者">担当者</span></label>
                                <div v-if="isEditMode" class="d-flex align-items-center gap-2">
                                    <input class="form-control" type="text" id="manager_tags" name="manager_tags">
                                    <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearTagifyTags('manager')" title="すべて削除"><i class="fa fa-times"></i></button>
                                </div>
                                <div class="d-flex align-items-center flex-wrap gap-2" v-else-if="managers && managers.length > 0">
                                    <div v-for="member in managers" :key="member.userid"
                                        class="avatar"
                                        :data-userid="member.userid"
                                        data-bs-toggle="tooltip"
                                        data-popup="tooltip-custom"
                                        data-bs-placement="top"
                                        :aria-label="member.user_name"
                                        :data-bs-original-title="member.user_name">
                                        <span v-if="showAvatarInitials(member)" class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(member) }}</span>
                                        <img v-if="!member.avatarError && getAvatarSrc(member)"
                                            class="rounded-circle"
                                            :class="{ 'd-none': !member.avatarLoaded }"
                                            :src="getAvatarSrc(member)"
                                            :alt="member.user_name"
                                            @load="handleAvatarLoad(member)"
                                            @error="handleAvatarError(member)">
                                    </div>
                                </div>
                                <div v-else class="text-muted">
                                    <span data-i18n="メンバーがいません">メンバーがいません</span>
                                </div>
                            </div>
                            <div class="col-md-4" v-show="(!isEnergyDepartmentProject) && showField('members')">
                                <label class="form-label"><span data-i18n="メンバー">メンバー</span></label>
                                <div v-if="isEditMode" class="d-flex align-items-center gap-2">
                                    <input class="form-control" type="text" id="members_tags" name="members_tags">
                                    <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearTagifyTags('members')" title="すべて削除"><i class="fa fa-times"></i></button>
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center" v-else-if="members.length > 0">
                                    <div v-for="member in members" :key="member.userid"
                                        class="avatar"
                                        data-bs-toggle="tooltip"
                                        :data-userid="member.userid"
                                        data-popup="tooltip-custom"
                                        data-bs-placement="top"
                                        :aria-label="member.user_name"
                                        :data-bs-original-title="member.user_name">
                                        <span v-if="showAvatarInitials(member)" class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(member) }}</span>
                                        <img v-if="!member.avatarError && getAvatarSrc(member)"
                                            class="rounded-circle"
                                            :class="{ 'd-none': !member.avatarLoaded }"
                                            :src="getAvatarSrc(member)"
                                            :alt="member.user_name"
                                            @load="handleAvatarLoad(member)"
                                            @error="handleAvatarError(member)">
                                    </div>
                                </div>
                                <div v-else class="text-muted">
                                    <span data-i18n="メンバーがいません">メンバーがいません</span>
                                </div>
                            </div>
                            </div>
                            <div class="pd-section" v-show="(isEditMode ? (customFields && customFields.length > 0) : getCustomFieldsForView().length > 0) || (isEnergyDepartmentProject && showSection('energy'))">
                            <div class="col-12 project-form-section" v-if="(isEditMode ? (customFields && customFields.length > 0) : getCustomFieldsForView().length > 0) || (isEnergyDepartmentProject && showSection('energy'))">
                                <h6 class="project-form-section-title"><span data-i18n="部署別項目">部署別項目</span> <span v-if="!isEditMode && inlineSaveStatus.custom === 'loading'" class="text-muted ms-1" title="保存中"><i class="fa fa-spinner fa-spin"></i></span><span v-else-if="!isEditMode && inlineSaveStatus.custom === 'saved'" class="text-success ms-1" title="保存済み"><i class="fa fa-check-circle"></i></span></h6>
                            </div>
                            <?php
                            // One group of custom fields (see getCustomFieldGroup in project-form-mixin.js): its own
                            // wrapper row, so that groups and the 省エネ fields can be interleaved.
                            $pfRenderCustomFields = function ($group) {
                                $edit = "getCustomFieldGroup(customFields, '" . $group . "')";
                                $view = "getCustomFieldGroup(getCustomFieldsForView(), '" . $group . "')";
                                ?>
                            <div class="col-12" v-show="(isEditMode ? <?= $edit ?> : <?= $view ?>).length > 0">
                                <div class="row" v-if="isEditMode">
                                    <?php $pfCustomFieldList = $edit; require __DIR__ . '/project-custom-fields-edit.php'; ?>
                                </div>
                                <div class="row" v-else>
                                    <?php $pfCustomFieldList = $view; require __DIR__ . '/project-custom-fields-view.php'; ?>
                                </div>
                            </div>
                                <?php
                            };
                            ?>
                            <!-- every department but 省エネ: the custom fields in the order of their sets -->
                            <?php $pfRenderCustomFields('all'); ?>
                            <!-- 省エネ部署の案件: fixed order — 省エネ・申請関連, 申請提出先の審査機関, BELSのZEH等級, 面積確認,
                                 太陽光 年間日射地域区分, 省エネ向け資料, PJ No(旧), then any other custom field.
                                 (edit state of the 省エネ fields: energyEdit, see assets/js/project-energy-fields-mixin.js) -->
                            <template v-if="isEnergyDepartmentProject">
                                <?php $pfRenderCustomFields('energy_top'); ?>
                                <div class="col-md-6" v-show="showField('energy_agency')">
                                    <label class="form-label"><span data-i18n="申請提出先の審査機関">申請提出先の審査機関</span></label>
                                    <div v-if="isEditMode" class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control tagify" id="project_energy_review_agency_tags" name="project_energy_review_agency_tags">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearEnergyAgencyTags" title="すべて削除"><i class="fa fa-times"></i></button>
                                    </div>
                                    <div v-else class="d-flex flex-wrap gap-1 align-items-center" style="min-height:38px;">
                                        <span v-for="item in energyReviewAgencyList" :key="'ag_' + item"
                                              class="badge badge-outline-primary me-1">{{ item }}</span>
                                        <span v-if="energyReviewAgencyList.length === 0" class="text-muted">-</span>
                                    </div>
                                </div>
                                <div class="col-md-6" v-show="showField('energy_grade')">
                                    <label class="form-label"><span data-i18n="BELSのZEH等級">BELSのZEH等級</span></label>
                                    <select v-if="isEditMode" class="form-select" v-model="energyEdit.grade">
                                        <option v-for="opt in energyEditGradeOptions" :key="'zeh_edit_' + opt.value" :value="opt.value">
                                            {{ opt.label }}
                                        </option>
                                    </select>
                                    <div v-else style="min-height:38px;">{{ belsZehGradeLabel || '-' }}</div>
                                </div>
                                <?php $pfRenderCustomFields('energy_middle'); ?>
                                <div class="col-12" v-show="showField('energy_materials')">
                                    <label class="form-label"><span data-i18n="省エネ向け資料">省エネ向け資料</span></label>
                                    <template v-if="isEditMode">
                                        <!-- 意匠図 / 設備図 / 配置図: the composite custom fields of the department, in place of plain checkboxes -->
                                        <div class="pf-energy-composite" v-for="(field, idx) in getEnergyMaterialCompositeFields(customFields)" :key="'emc_edit_' + field.label">
                                            <div class="pf-energy-composite-title">{{ translateLabel(field.label) }}</div>
                                            <?php require __DIR__ . '/project-composite-field-edit.php'; ?>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4" v-for="opt in getEnergyMaterialPlainOptions()" :key="'em_edit_' + opt">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox"
                                                        :id="'project_energy_materials_' + opt"
                                                        v-model="energyEdit.flags[opt]">
                                                    <label class="form-check-label" :for="'project_energy_materials_' + opt">{{ opt }}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-2" v-if="energyEdit.flags['その他']">
                                            <input type="text" class="form-control" v-model="energyEdit.memo"
                                                   placeholder="その他メモ" maxlength="500">
                                        </div>
                                    </template>
                                    <div v-else>
                                        <div class="pf-energy-composite" v-for="(field, idx) in getEnergyMaterialCompositeFields(getAllCustomFieldsForView())" :key="'emc_view_' + field.label">
                                            <div class="pf-energy-composite-title">{{ translateLabel(field.label) }}</div>
                                            <?php require __DIR__ . '/project-composite-field-view.php'; ?>
                                        </div>
                                        <!-- every other choice, ticked or not; saved on change for users who may edit -->
                                        <div class="pf-energy-plain-list">
                                            <div class="form-check mb-0" v-for="opt in getEnergyMaterialPlainOptions()" :key="'em_' + opt">
                                                <input v-if="canEditProject" class="form-check-input" type="checkbox"
                                                       :id="'pd_energy_material_' + opt" :disabled="savingEnergyMaterials"
                                                       :checked="selectedEnergyMaterialLabels.indexOf(opt) !== -1"
                                                       @change="onEnergyMaterialToggle(opt, $event)">
                                                <input v-else class="form-check-input pf-readonly-check" type="checkbox" tabindex="-1"
                                                       :checked="selectedEnergyMaterialLabels.indexOf(opt) !== -1" @click.prevent>
                                                <label class="form-check-label" :for="canEditProject ? ('pd_energy_material_' + opt) : null">{{ opt }}<span v-if="!canEditProject && opt === 'その他' && project.energy_materials_memo && selectedEnergyMaterialLabels.indexOf('その他') !== -1"
                                                      class="small text-muted ms-1">({{ project.energy_materials_memo }})</span></label>
                                            </div>
                                        </div>
                                        <!-- その他メモ: typed on the page while その他 is ticked -->
                                        <div class="mt-2" v-if="canEditProject && selectedEnergyMaterialLabels.indexOf('その他') !== -1">
                                            <input type="text" class="form-control form-control-sm" placeholder="その他メモ" maxlength="500"
                                                   :value="project.energy_materials_memo || ''" :disabled="savingEnergyMaterials"
                                                   @change="onEnergyMaterialMemoChange($event)">
                                        </div>
                                    </div>
                                </div>
                                <?php $pfRenderCustomFields('energy_rest'); ?>
                            </template>
                            </div>
                            <div class="pd-section" v-show="showSection('other')">
                            <div class="col-12 project-form-section" v-show="showSection('other')">
                                <h6 class="project-form-section-title"><span data-i18n="その他">その他</span></h6>
                            </div>
                            <div class="col-md-12" v-show="showField('tags') && !formCreateMode">
                                <label class="form-label"><span data-i18n="タグ">タグ</span><i class="fa fa-question-circle text-muted ms-2" 
                                data-bs-toggle="tooltip" 
                                data-bs-placement="top" 
                                title="タグは案件の探す時に使用します。"></i></label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="text" class="form-control tagify" v-model="project.tags" id="project_tags" name="project_tags" @input="updateTags">
                                </div>
                            </div>
                            <div class="col-12 pf-full-only" v-show="showField('description')">
                                <label class="form-label"><span data-i18n="説明">説明</span></label>
                                <template v-if="isEditMode">
                                    <div class="custom_editor">
                                        <div class="custom_editor_content" id="quill_description"></div>
                                        <textarea class="custom_editor_textarea d-none" v-model="project.description" id="quill_description_textarea"></textarea>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="form-control ql-editor" style="min-height:100px;" v-html="decodeHtmlEntities(project.description)"></div>
                                </template>
                            </div>
                            </div>
                            </div>
                        </div>
