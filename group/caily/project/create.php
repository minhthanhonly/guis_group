<?php
require_once('../application/loader.php');
$view->heading('プロジェクト作成');

// Get department ID from URL parameter
$department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
?>
<div id="app" class="container-fluid mt-4" v-cloak>

    <div class="row">
        <!-- Back button -->
        <div class="col-12 mb-3">
            <a href="index.php" class="btn btn-outline-primary">
                <i class="fa fa-arrow-left me-1"></i> <span data-i18n="戻る">戻る</span>
            </a>
        </div>

        <!-- Left Column - Project Details -->
        <div class="col-xl-8">
            <div class="card edit-mode">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title"><span data-i18n="プロジェクト作成">プロジェクト作成</span></h5>
                        <div>
                            <button class="btn btn-success btn-sm me-2" @click="saveProject">
                                <i class="fa fa-save"></i>
                            </button>
                            <a href="index.php" class="btn btn-secondary btn-sm">
                                <i class="fa fa-times"></i>
                            </a>
                        </div>
                    </div>

                    <div class="row g-3" v-if="project">
                        <div class="col-md-4">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="顧客カテゴリー">顧客カテゴリー</span> <span class="text-danger">*</span></label>
                                <select id="category_id" class="form-select select2" v-model="newProject.category_id" name="category_id" required @change="onCategoryChange">
                                    <option value="" data-i18n="選択してください">選択してください</option>
                                    <option v-for="category in categories" :key="category.id" :value="category.id">
                                        {{ category.name }}
                                    </option>
                                </select>
                                <div v-if="validationErrors.category_id" class="invalid-feedback d-block">
                                    {{ validationErrors.category_id }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="会社名">会社名</span> <span class="text-danger">*</span></label>
                                <select id="company_name" class="form-select select2" v-model="newProject.company_name" name="company_name" required @change="onCompanyChange">
                                    <option value="" data-i18n="選択してください">選択してください</option>
                                    <option v-for="company in companies" :key="company.company_name" :value="company.company_name">
                                        {{ company.company_name }}
                                    </option>
                                </select>
                                <div v-if="validationErrors.company_name" class="invalid-feedback d-block">
                                    {{ validationErrors.company_name }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label"><span data-i18n="担当者名">担当者名</span> <span class="text-danger">*</span></label>
                                <div class="">
                                    <select id="customer_id" class="form-select select2 flex-shrink-1" v-model="newProject.customer_id" name="customer_id" required>
                                        <option value="" data-i18n="選択してください">選択してください</option>
                                        <option v-for="contact in contacts" :key="contact.id" :value="contact.id">
                                            {{ contact.name }}
                                        </option>
                                    </select>
                                    <div v-if="validationErrors.customer_id" class="invalid-feedback d-block">
                                        {{ validationErrors.customer_id }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><span data-i18n="プロジェクト番号">プロジェクト番号</span> <span class="text-danger">*</span> <button class="btn btn-sm btn-outline-primary py-0 small" @click="generateProjectNumber">生成</button></label>
                            <input type="text" class="form-control" v-model="project.project_number" required>
                            <div v-if="validationErrors.project_number" class="invalid-feedback d-block">
                                {{ validationErrors.project_number }}
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><span data-i18n="プロジェクト名">プロジェクト名</span> <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" v-model="project.name" required>
                            <div v-if="validationErrors.name" class="invalid-feedback d-block">
                                {{ validationErrors.name }}
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label"><span data-i18n="部署">部署</span></label>
                            <select class="form-select" v-model="project.department_id" @change="onDepartmentChange" :disabled="presetDepartmentId">
                                <option value="" data-i18n="選択してください">選択してください</option>
                                <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                                    {{ dept.name }}
                                </option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label"><span data-i18n="工事番号">工事番号</span></label>
                            <input type="text" class="form-control" v-model="project.building_number">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><span data-i18n="建物規模">建物規模</span></label>
                            <input type="text" class="form-control" v-model="project.building_size">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><span data-i18n="建物種類">建物種類</span></label>
                            <input type="text" class="form-control" v-model="project.building_type">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><span data-i18n="工事支店">工事支店</span></label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="text" class="form-control tagify" v-model="project.building_branch" id="building_branch" name="building_branch">
                                <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearTagifyTags('building_branch')" title="すべて削除"><i class="fa fa-times"></i></button>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label"><span data-i18n="受注形態">受注形態</span></label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="text" class="form-control tagify" v-model="project.project_order_type" id="project_order_type" name="project_order_type" required>
                                <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearTagifyTags('project_order_type')" title="すべて削除"><i class="fa fa-times"></i></button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><span data-i18n="優先度">優先度</span></label>
                            <div>
                                <div class="btn-group">
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
                            </div>
                        </div>
                      
                        <div class="col-4">
                            <label class="form-label"><span data-i18n="チーム">チーム</span></label>
                            <div class="d-flex align-items-center gap-2">
                                <input id="team_tags" class="form-control" />
                                <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearTagifyTags('team')" title="すべて削除"><i class="fa fa-times"></i></button>
                            </div>
                        </div>
                        <div class="col-4">
                            <label class="form-label"><span data-i18n="管理">管理</span></label>
                            <div class="d-flex align-items-center gap-2">
                                <input class="form-control" type="text" id="manager_tags" name="manager_tags">
                                <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearTagifyTags('manager')" title="すべて削除"><i class="fa fa-times"></i></button>
                            </div>
                        </div>
                        <div class="col-4">
                            <label class="form-label"><span data-i18n="メンバー">メンバー</span></label>
                            <div class="d-flex align-items-center gap-2">
                                <input class="form-control" type="text" id="members_tags" name="members_tags">
                                <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearTagifyTags('members')" title="すべて削除"><i class="fa fa-times"></i></button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><span data-i18n="開始日">開始日</span></label>
                            <div class="input-group">
                                <input type="text" class="form-control" v-model="project.start_date" id="start_date_picker" placeholder="YYYY/MM/DD HH:mm" autocomplete="off">
                                <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><span data-i18n="終了日">終了日</span></label>
                            <div class="input-group">
                                <input type="text" class="form-control" v-model="project.end_date" id="end_date_picker" placeholder="YYYY/MM/DD HH:mm" autocomplete="off">
                                <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label"><span data-i18n="ステータス">ステータス</span></label>
                            <div>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light" 
                                            :class="getStatusButtonClass(project.status)"
                                            id="statusDropdown"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                        {{ getStatusLabel(project.status) }}
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li v-for="status in statuses" :key="status.value">
                                            <a class="dropdown-item waves-effect" href="javascript:void(0);" 
                                            @click="selectStatus(status.value)">
                                                <span :data-i18n="status.label">{{ status.label }}</span>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <label class="form-label"><span data-i18n="進捗率">進捗率</span></label>
                            <div class="position-relative">
                                <input
                                    type="range"
                                    min="0"
                                    max="100"
                                    v-model="project.progress"
                                    class="form-range custom-progress-range"
                                >
                                <div class="progress-value-label text-center">
                                    {{ project.progress }}%
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label"><span data-i18n="タグ">タグ</span></label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="text" class="form-control tagify" v-model="project.tags" id="project_tags" name="project_tags" @input="updateTags">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><span data-i18n="説明">説明</span></label>
                            <div class="custom_editor">
                                <div class="custom_editor_content" id="quill_description"></div>
                                <textarea class="custom_editor_textarea d-none" v-model="project.description" id="quill_description_textarea"></textarea>
                            </div>
                        </div>
                        <div class="col-12 mt-3">
                            <label class="form-label"><span data-i18n="カスタム項目セット">カスタム項目セット</span></label>
                            <select class="form-select" v-model="project.department_custom_fields_set_id">
                                <option value="" data-i18n="選択してください">選択してください</option>
                                <option v-for="set in departmentCustomFieldSets" :value="set.id">{{ set.name }}</option>
                            </select>
                            <div v-if="selectedCustomFieldSet" class="mt-3">
                                <div class="row">
                                    <template v-for="(field, idx) in selectedCustomFieldSet.fields" :key="idx">
                                        <div v-if="field.type === 'textarea'" class="col-12 mb-3">
                                            <label class="form-label">{{ field.label }}</label>
                                            <textarea class="form-control" v-if="customFields[idx]" v-model="customFields[idx].value"></textarea>
                                        </div>
                                        <div v-else class="col-md-4 mb-3">
                                            <label class="form-label">{{ field.label }}</label>
                                            <template v-if="field.type === 'radio'">
                                                <div v-if="customFields[idx]">
                                                    <div class="form-check form-check-inline" v-for="opt in field.options.split(',')" :key="opt.trim()">
                                                        <input class="form-check-input" type="radio" :name="'custom_radio_' + idx" :value="opt.trim()" v-model="customFields[idx].value">
                                                        <label class="form-check-label">{{ opt.trim() }}</label>
                                                    </div>
                                                </div>
                                            </template>
                                            <template v-else-if="field.type === 'select'">
                                                <select class="form-select" v-if="customFields[idx]" v-model="customFields[idx].value">
                                                    <option value="">選択してください</option>
                                                    <option v-for="opt in field.options.split(',')" :key="opt.trim()" :value="opt.trim()">{{ opt.trim() }}</option>
                                                </select>
                                            </template>
                                            <template v-else-if="field.type === 'checkbox'">
                                                <div v-if="customFields[idx]">
                                                    <div class="form-check form-check-inline" v-for="opt in field.options.split(',')" :key="opt.trim()">
                                                        <input class="form-check-input" type="checkbox" :name="'custom_checkbox_' + idx" :value="opt.trim()" v-model="customFields[idx].valueArr">
                                                        <label class="form-check-label">{{ opt.trim() }}</label>
                                                    </div>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <input class="form-control" v-if="customFields[idx]" v-model="customFields[idx].value" type="text">
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-5">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Info -->
        <div class="col-xl-4">
            <!-- Info Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><span data-i18n="作成情報">作成情報</span></h5>
                </div>
                <div class="card-body">
                    <div class="text-center py-3">
                        <i class="fa fa-plus-circle fs-1 text-primary"></i>
                        <p class="text-muted mt-2" data-i18n="新しいプロジェクトを作成します">新しいプロジェクトを作成します</p>
                        <p class="small text-muted" data-i18n="必須項目（*）を入力してから保存してください">必須項目（*）を入力してから保存してください</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$view->footing();
?>

<style>
.comment-item {
    padding: 1rem;
    border-radius: 0.375rem;
    background-color: #f8f9fa;
}
.comment-header {
    margin-bottom: 0.5rem;
}
.comment-content {
    white-space: pre-wrap;
    word-break: break-word;
}

/* Edit mode styles */
.edit-mode .select2-selection,
.edit-mode .form-select:not([readonly]) ,
.edit-mode .form-control:not([readonly]) {
    border-color: var(--bs-primary);
}

.content-wrapper{
    overflow-x: hidden;
}

.tags-look-building-branch .tagify__dropdown__item:last-child {
    border-bottom: none;
}

/* Project tags styling */
.project-tags .badge {
    font-size: 0.75rem;
    padding: 0.375rem 0.5rem;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
}

.project-tags .badge:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Tagify styling for project tags */
#project_tags .tagify__tag {
    background: #17a2b8;
    color: white;
    border-radius: 0.375rem;
    margin: 2px;
}

#project_tags .tagify__tag:hover {
    background: #138496;
}

#project_tags .tagify__tag__removeBtn {
    color: white;
}

#project_tags .tagify__tag__removeBtn:hover {
    background: rgba(255,255,255,0.2);
}

</style>

<!-- Define PRESET_DEPARTMENT_ID before loading Vue and project-create.js -->
<script>
const PRESET_DEPARTMENT_ID = <?php echo $department_id; ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/quill/typography.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/quill/editor.css" />
<script src="<?=ROOT?>assets/vendor/libs/quill/quill.js"></script>
<script src="assets/js/project-create.js?v=<?=CACHE_VERSION?>"></script>