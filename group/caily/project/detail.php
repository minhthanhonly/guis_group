<?php
require_once('../application/loader.php');
$view->heading('プロジェクト詳細');

// Get project ID from URL
$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$project_id) {
    header('Location: index.php');
    exit;
}
?>
<div id="app" class="container-fluid mt-4" v-cloak>
    <div v-if="canViewProject">
        <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold" href="#"><span class="badge badge-sm bg-label-info">#P{{ project?.project_number }}</span></a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#projectNavbar" aria-controls="projectNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="projectNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="detail.php?id=<?php echo $project_id; ?>">概要</a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link" href="task.php?project_id=<?php echo $project_id; ?>">タスク<span class="badge badge-sm ms-1 rounded-pill">{{ project?.task_count }}</span></a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link" href="gantt.php?project_id=<?php echo $project_id; ?>">ガントチャート</a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link" href="drawings.php?project_id=<?php echo $project_id; ?>">図面<span class="badge badge-sm bg-info ms-1 rounded-pill">{{ project?.drawing_count }}</span></a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link" href="attachment.php?project_id=<?php echo $project_id; ?>">添付ファイル</a>
                    </li>
                </ul>
                </div>
            </div>
        </nav>
        <div class="row">
            <!-- Back button -->
            <div class="col-12 mb-3">
                <a href="index.php" class="btn btn-outline-primary me-2">
                    <i class="fa fa-arrow-left me-2"></i><span data-i18n="戻る">戻る</span>
                </a>
                <a :href="'../parent_project/detail.php?id=' + project.parent_project_id" class="btn btn-outline-primary" title="親プロジェクト詳細">
                    <i class="fa fa-external-link me-2"></i>
                    <span data-i18n="詳細">詳細</span>
                </a>
            </div>

            <!-- Left Column - Project Details -->
            <div class="col-xl-8">
                <div class="card" :class="{ 'edit-mode': isEditMode }">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="card-title"><span data-i18n="基本情報">基本情報</span></h5>
                            <div>
                                <!-- <button v-if="!isEditMode && canAddProject" class="btn btn-outline-info btn-sm me-2" @click="copyProject" title="プロジェクトをコピー">
                                    <i class="fa fa-copy"></i>
                                </button> -->
                                <button v-if="!isEditMode && canEditProject" class="btn btn-outline-warning btn-sm me-2" @click="toggleEditMode" title="編集">
                                    <i class="fa fa-pencil-alt"></i>
                                </button>
                                <button v-if="isEditMode" class="btn btn-success btn-sm me-2" @click="saveProject" title="保存">
                                    <i class="fa fa-save"></i>
                                </button>
                                <button v-if="isEditMode" class="btn btn-secondary btn-sm me-2" @click="cancelEdit" title="キャンセル">
                                    <i class="fa fa-times"></i>
                                </button>  
                                <button v-if="!isEditMode && canDeleteProject" class="btn btn-outline-danger btn-sm" @click="deleteProject" title="削除">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </div>
                        </div>


                        
                        <div class="row g-3" v-if="project">

                            
                            <!-- Parent Project Information Accordion (for child projects) -->
                            <div v-if="project.parent_project_id" class="col-12 mb-4">
                                <div id="parentProjectCollapse">
                                    <div class="border p-3">
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label"><span data-i18n="会社名">会社名</span></label>
                                                <input type="text" class="form-control" :value="project.company_name || '-'" readonly>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label"><span data-i18n="支店名">支店名</span></label>
                                                <input type="text" class="form-control" :value="project.branch_name || '-'" readonly>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label"><span data-i18n="担当者名">担当者名</span></label>
                                                <input type="text" class="form-control" :value="project.contact_name || '-'" readonly>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label"><span data-i18n="工事番号">工事番号</span></label>
                                                <input type="text" class="form-control" :value="project.building_number || '-'" readonly>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label"><span data-i18n="建物規模">建物規模</span></label>
                                                <input type="text" class="form-control" :value="project.building_size || '-'" readonly>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label"><span data-i18n="GUIS　受付者">GUIS　受付者</span></label>
                                                <input type="text" class="form-control" :value="project.guis_receiver_display_name || project.guis_receiver || '-'" readonly>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label"><span data-i18n="工事支店">工事支店</span></label>
                                                <div style="min-height:38px;">
                                                    <span v-if="project.building_branch && project.building_branch.split(',').length > 0">
                                                        <span v-for="item in project.building_branch.split(',')" :key="item.trim()" class="badge bg-primary me-1">{{ item.trim() }}</span>
                                                    </span>
                                                    <span v-else>-</span>
                                                </div>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label"><span data-i18n="種類1">種類1</span></label>
                                                <div style="min-height:38px;">
                                                    <span v-if="project.type1 && project.type1.split(',').length > 0">
                                                        <span v-for="item in project.type1.split(',')" :key="item.trim()" class="badge bg-info me-1">{{ item.trim() }}</span>
                                                    </span>
                                                    <span v-else>-</span>
                                                </div>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label"><span data-i18n="種類2">種類2</span></label>
                                                <div style="min-height:38px;">
                                                    <span v-if="project.type2 && project.type2.split(',').length > 0">
                                                        <span v-for="item in project.type2.split(',')" :key="item.trim()" class="badge bg-info me-1">{{ item.trim() }}</span>
                                                    </span>
                                                    <span v-else>-</span>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label"><span data-i18n="構造事務所">構造事務所</span></label>
                                                <input type="text" class="form-control" :value="project.structural_office || '-'" readonly>
                                            </div>
                                            <div class="col-12 mb-3">
                                                <label class="form-label"><span data-i18n="資料">資料</span></label>
                                                <div class="form-control-plaintext">
                                                    <div class="row">
                                                        <div class="col-md-4" v-if="project.materials && project.materials.includes('配置図')">
                                                            <i class="fa fa-check text-success me-2"></i>配置図
                                                        </div>
                                                        <div class="col-md-4" v-if="project.materials && project.materials.includes('家賃審査書')">
                                                            <i class="fa fa-check text-success me-2"></i>家賃審査書
                                                        </div>
                                                        <div class="col-md-4" v-if="project.materials && project.materials.includes('契約図')">
                                                            <i class="fa fa-check text-success me-2"></i>契約図
                                                        </div>
                                                        <div class="col-md-4" v-if="project.materials && project.materials.includes('TAC図')">
                                                            <i class="fa fa-check text-success me-2"></i>TAC図
                                                        </div>
                                                        <div class="col-md-4" v-if="project.materials && project.materials.includes('その他')">
                                                            <i class="fa fa-check text-success me-2"></i>その他
                                                        </div>
                                                        <div v-if="!project.materials || project.materials.length === 0" class="col-12">
                                                            <span class="text-muted">-</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12 mb-3">
                                                <label class="form-label"><span data-i18n="備考">備考</span></label>
                                                <div class="form-control-plaintext" style="white-space: pre-wrap;">{{ project.notes || '-' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        
                            <div class="col-md-4  mt-4">
                                <label class="form-label"><span data-i18n="プロジェクト番号">プロジェクト番号</span>  <span class="text-danger">*</span></label>
                                <template v-if="isEditMode">
                                    <input type="text" class="form-control" v-model="project.project_number">
                                    <div v-if="validationErrors.project_number" class="invalid-feedback d-block">
                                        {{ validationErrors.project_number }}
                                    </div>
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control" :value="project.project_number || '-'" readonly>
                                </template>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><span data-i18n="プロジェクト名">プロジェクト名</span>  <span class="text-danger">*</span></label>
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
                                <!-- <label class="form-label"><span data-i18n="部署">部署</span></label>
                                <input type="text" class="form-control" :value="department?.name || '-'" readonly> -->
                            </div>
                            

                            
                            
                            <div class="col-md-4">
                                <label class="form-label"><span data-i18n="受注形態">受注形態</span></label>
                                <template v-if="isEditMode">
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control tagify" v-model="project.project_order_type" id="project_order_type" name="project_order_type" required>
                                        <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearTagifyTags('project_order_type')" title="すべて削除"><i class="fa fa-times"></i></button>
                                    </div>
                                </template>
                                <template v-else>
                                    <div style="min-height:38px;">
                                        <span v-if="project.project_order_type && project.project_order_type.split(',').length > 0">
                                            <span v-for="item in project.project_order_type.split(',')" :key="item.trim()" class="badge bg-primary me-1">{{ item.trim() }}</span>
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
                            <div class="col-4">
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
                            <div class="col-4">
                                <label class="form-label"><span data-i18n="管理">管理</span></label>
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
                                        <img v-if="!member.avatarError" class="rounded-circle" :src="getAvatarSrc(member)" :alt="member.user_name" @error="handleAvatarError(member)">
                                        <span v-else class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(member.user_name) }}</span>
                                    </div>
                                </div>
                                <div v-else class="text-muted">
                                    <span data-i18n="メンバーがいません">メンバーがいません</span>
                                </div>
                            </div>
                            <div class="col-4">
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
                                        <img v-if="!member.avatarError" class="rounded-circle" :src="getAvatarSrc(member)" :alt="member.user_name" @error="handleAvatarError(member)">
                                        <span v-else class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(member.user_name) }}</span>
                                    </div>
                                </div>
                                <div v-else class="text-muted">
                                    <span data-i18n="メンバーがいません">メンバーがいません</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><span data-i18n="ステータス">ステータス</span></label>
                                <div>
                                    <div class="btn-group" v-if="canEditProject">
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
                                                    {{ status.label }}
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    <div v-else>
                                        <span class="badge" :class="getStatusBadgeClass(project.status)">{{ getStatusLabel(project.status) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><span data-i18n="開始日">開始日</span></label>
                                <div v-if="isEditMode" class="input-group">
                                    <input type="text" class="form-control" v-model="project.start_date" id="start_date_picker" placeholder="YYYY/MM/DD HH:mm" autocomplete="off">
                                    <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                                </div>
                                <input v-else type="text" class="form-control" :value="formatDateTime(project.start_date)" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">
                                    <span data-i18n="期限日">期限日</span>
                                    <span v-if="getTimeRemaining()" :class="'badge ms-2 ' + getTimeRemaining().class" 
                                          :title="getTimeRemaining().isOverdue ? '期限を超過しています' : '残り時間'">
                                        {{ getTimeRemaining().text }}
                                    </span>
                                </label>
                                <div v-if="isEditMode" class="input-group">
                                    <input type="text" class="form-control" v-model="project.end_date" id="end_date_picker" placeholder="YYYY/MM/DD HH:mm" autocomplete="off">
                                    <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                                </div>
                                <input v-else type="text" class="form-control" :value="formatDateTime(project.end_date)" readonly>
                            </div>
                            
                            
                            <div class="col-4">
                                <label class="form-label"><span data-i18n="進捗率">進捗率</span></label>
                                <div class="position-relative">
                                    <input
                                        type="range"
                                        min="0"
                                        max="100"
                                        v-model="project.progress"
                                        @change="updateProgress"
                                        class="form-range"
                                        :class="{'prevent-click': !canEditProject}"
                                    >
                                    <div class="progress-value-label text-center">
                                        {{ project.progress }}%
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4" v-if="project.actual_end_date">
                                <label class="form-label"><span data-i18n="実終了日">実終了日</span></label>
                                <input type="text" class="form-control" :value="formatDateTime(project.actual_end_date)" readonly>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label"><span data-i18n="タグ">タグ</span><i class="fa fa-question-circle text-muted ms-2" 
                                data-bs-toggle="tooltip" 
                                data-bs-placement="top" 
                                title="タグは案件の探す時に使用します。"></i></label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="text" class="form-control tagify" v-model="project.tags" id="project_tags" name="project_tags" @input="updateTags">
                                </div>
                            </div>
                            <div class="col-12">
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
                            

                            <div class="col-12 mt-3">
                                <template v-if="isEditMode">
                                    <label class="form-label"><span data-i18n="カスタム項目セット">カスタム項目セット</span></label>
                                    <select class="form-select" v-model="project.department_custom_fields_set_id">
                                        <option value="">選択してください</option>
                                        <option v-for="set in departmentCustomFieldSets" :value="set.id">{{ set.name }}</option>
                                    </select>
                                    <div v-if="customFields && customFields.length > 0" class="mt-3">
                                        <div class="row">
                                            <template v-for="(field, idx) in customFields" :key="idx">
                                                <div v-if="field.type === 'textarea'" class="col-12 mb-3">
                                                    <label class="form-label">{{ translateLabel(field.label) }}</label>
                                                    <textarea class="form-control" v-model="field.value"></textarea>
                                                </div>
                                                <div v-else class="col-md-4 mb-3">
                                                    <div class="form-label">{{ translateLabel(field.label) }}</div>
                                                    <template v-if="field.type === 'radio'">
                                                        <div class="form-check form-check-inline" v-for="opt in field.options.split(',')" :key="opt.trim()">
                                                            <input class="form-check-input" type="radio" :name="'custom_radio_' + idx" :value="opt.trim()" v-model="field.value">
                                                            <label class="form-check-label">{{ opt.trim() }}</label>
                                                        </div>
                                                    </template>
                                                    <template v-else-if="field.type === 'select'">
                                                        <select class="form-select" v-model="field.value">
                                                            <option value="">選択してください</option>
                                                            <option v-for="opt in field.options.split(',')" :key="opt.trim()" :value="opt.trim()">{{ opt.trim() }}</option>
                                                        </select>
                                                    </template>
                                                    <template v-else-if="field.type === 'checkbox'">
                                                        <div class="form-check form-check-inline" v-for="opt in field.options.split(',')" :key="opt.trim()">
                                                            <input class="form-check-input" type="checkbox" :name="'custom_checkbox_' + idx" :value="opt.trim()" v-model="field.valueArr">
                                                            <label class="form-check-label">{{ opt.trim() }}</label>
                                                        </div>
                                                    </template>
                                                    <template v-else>
                                                        <input class="form-control" v-model="field.value" type="text">
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                                <template v-else>
                                    <template v-if="getCustomFieldsForView().length">
                                        <div class="row">
                                            <template v-for="(field, idx) in getCustomFieldsForView()" :key="idx">
                                                <div v-if="field.type === 'textarea'" class="col-12 mb-3">
                                                    <label class="form-label">{{ translateLabel(field.label) }}</label>
                                                    <div class="form-control" style="min-height:80px;white-space:pre-line;">{{ field.value || '-' }}</div>
                                                </div>
                                                <div v-else class="col-md-4 mb-3">
                                                    <label class="form-label">{{ translateLabel(field.label) }}</label>
                                                    <template v-if="field.type === 'checkbox' || field.type === 'radio' || field.type === 'select'">
                                                        <div>
                                                            <span v-if="field.value">
                                                                <span v-for="val in field.value.split(',')" :key="val.trim()" class="badge bg-primary me-1">{{ val.trim() }}</span>
                                                            </span>
                                                            <span v-else>-</span>
                                                        </div>
                                                    </template>
                                                    <template v-else>
                                                        <div class="form-control">{{ field.value || '-' }}</div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="text-muted">-</div>
                                    </template>
                                </template>
                            </div>
                        </div>
                        <div v-else class="text-center py-5">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Comments Section -->
                <div class="card mt-4" v-if="canCommentProject">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fa fa-comment me-2"></i>コメント
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Comment Component -->
                        <comment-component
                             :entity-type="'project'"
                             :entity-id="projectId"
                             :current-user="currentUser"
                             @comment-added="onCommentAdded"
                             @message="onCommentMessage"
                             @error="onCommentError">
                        </comment-component>
                    </div>
                </div>

            </div>

            <!-- Right Column - Stats & Comments -->
            <div class="col-xl-4">
                <!-- Project Status Block -->
                <div class="card mb-4 project-status-block">
                    <div class="card-header d-flex justify-content-start align-items-center">
                        <h5 class="card-title mb-0">業務書類</h5>
                        <i class="fa fa-question-circle text-muted ms-2" 
                        data-bs-toggle="tooltip" 
                        data-bs-placement="top" 
                        title="システム見積書作成機能は開発中です"></i>
                    </div>
                    <div class="card-body" v-if="project">
                        <div class="row g-3">
                            <div class="col-6 col-xl-3">
                                <label class="form-label">見積書</label>
                                <div>
                                    <div class="btn-group w-100" v-if="canDocumentProject">
                                        <button type="button" class="btn dropdown-toggle waves-effect waves-light fs-small px-1" 
                                                :class="getEstimateStatusButtonClass(project.estimate_status)"
                                                id="estimateStatusDropdown"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                            {{ getEstimateStatusLabel(project.estimate_status) }}
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li v-for="status in estimateStatuses" :key="status.value">
                                                <a class="dropdown-item waves-effect" href="javascript:void(0);" 
                                                @click="selectEstimateStatus(status.value)">
                                                    {{ status.label }}
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    <div v-else>
                                        <span class="badge" :class="getEstimateStatusBadgeClass(project.estimate_status)">{{ getEstimateStatusLabel(project.estimate_status) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-xl-3">
                                <label class="form-label">請求書</label>
                                <div>
                                    <div class="btn-group w-100" v-if="canDocumentProject">
                                        <button type="button" class="btn dropdown-toggle waves-effect waves-light fs-small px-1" 
                                                :class="getInvoiceStatusButtonClass(project.invoice_status)"
                                                id="invoiceStatusDropdown"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                            {{ getInvoiceStatusLabel(project.invoice_status) }}
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li v-for="status in invoiceStatuses" :key="status.value">
                                                <a class="dropdown-item waves-effect" href="javascript:void(0);" 
                                                @click="selectInvoiceStatus(status.value)">
                                                    {{ status.label }}
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    <div v-else>
                                        <span class="badge" :class="getInvoiceStatusBadgeClass(project.invoice_status)">{{ getInvoiceStatusLabel(project.invoice_status) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-xl-6">
                                <label class="form-label">総額</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" v-model="project.amount" placeholder="0" step="100" @input="updateAmount" v-if="canDocumentProject">
                                    <input type="text" class="form-control bg-light" :value="formatCurrency(project.amount || 0)" readonly v-else>
                                    <span class="input-group-text">円</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body text-center py-4" v-else>
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Notes Section -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">メモ</h5>
                        <button class="btn btn-primary btn-sm" @click="openNoteModal()" title="メモを追加">
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>
                    <div class="card-body">
                        <div v-if="notes.length > 0" class="list-group">
                            <div v-for="note in notes" :key="note.id" class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1" style="cursor: pointer;" @click="openNoteModal(note)">
                                    <div class="d-flex align-items-center mb-1">
                                        <i v-if="note.is_important == 1" class="fa fa-star text-warning me-2"></i>
                                        <span class="fw-medium text-primary">{{ note.title }}</span>
                                    </div>
                                    <div v-if="note.content" class="text-muted small note-content">
                                        {{ getNotePreview(note.content) }}
                                    </div>
                                    <small class="text-secondary">{{ formatShortDateTime(note.created_at) }} - {{ note.realname || 'Unknown' }}</small>
                                </div>
                                <button class="btn btn-outline-danger btn-sm ms-2" 
                                        @click="deleteNote(note.id)" 
                                        title="削除"
                                        v-if="canDeleteNote(note)">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div v-else class="text-center text-muted py-3">
                            <i class="fa fa-sticky-note fa-2x mb-2"></i>
                            <p>メモがありません</p>
                            <button class="btn btn-outline-primary btn-sm" @click="openNoteModal()">
                                最初のメモを追加
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Lịch sử hành động (履歴) -->
                <div class="card mb-4">
                    <div class="card-header d-flex align-items-center">
                        <h5 class="card-title mb-0">履歴</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group" style="max-height: 400px; overflow-y: auto;">
                            <li v-for="log in sortedLogs" :key="log.id" class="list-group-item">
                                <div class="d-flex">
                                    <div class="d-flex flex-row align-items-start justify-content-start me-3" style="min-width:130px;">
                                        <div class="d-flex flex-column align-items-center justify-content-start" style="width:40px;">
                                            <span v-if="log.user_image">
                                                <img :src="'/assets/upload/avatar/' + log.user_image" alt="avatar" class="rounded-circle" width="32" height="32">
                                            </span>
                                            <div class="avatar avatar-sm" v-else>
                                                <span class="avatar-initial rounded-circle bg-label-primary">
                                                    {{ getInitials(log.username ? log.username : (log.realname ? log.realname : '?')) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-column align-items-start justify-content-center ms-2">
                                            <span class="fw-bold small">{{ log.username || log.realname || log.user }}</span>
                                            <span class="text-muted small">{{ formatShortDateTime(log.time) }}</span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 d-flex align-items-center">
                                        <span>
                                            <i :class="historyIcon(log.action) + ' me-2'"></i>
                                            <span class="me-2">{{ log.note }}</span>
                                            <br>
                                            <span v-if="log.value1" :class="getLogBadgeClass(log, 'value1')" class="mx-1">{{ getLogBadgeLabel(log, 'value1') }}</span>
                                            <span v-if="log.value1 && log.value2" class="mx-1">→</span>
                                            <span v-if="log.value2" :class="getLogBadgeClass(log, 'value2')" class="mx-1">{{ getLogBadgeLabel(log, 'value2') }}</span>
                                        </span>
                                    </div>
                                </div>
                            </li>
                            <li v-if="!logs || logs.length === 0" class="list-group-item text-muted">履歴はありません。</li>
                        </ul>
                    </div>
                </div>

                <!-- Statistics Cards -->
               <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="card bg-primary text-white text-center">
                            <div class="card-body">
                                <div class="mb-1">
                                    <i class="fa fa-list-alt fs-3"></i>
                                </div>
                                <h2 class="mb-1">{{ stats.totalTasks }}</h2>
                                <small>タスク総数</small>
                            </div>
                        </div>
                    </div>
                    <!-- <div class="col-6">
                        <div class="card bg-success text-white text-center">
                            <div class="card-body">
                                <div class="mb-1">
                                    <i class="fa fa-check-circle fs-3"></i>
                                </div>
                                <h2 class="mb-1">{{ stats.completedTasks }}</h2>
                                <small>完了タスク</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card bg-info text-white text-center">
                            <div class="card-body">
                                <div class="mb-1">
                                    <i class="fa fa-clock fs-3"></i>
                                </div>
                                <h2 class="mb-1">{{ stats.timeTracked }}h</h2>
                                <small>記録時間</small>
                            </div>
                        </div>
                    </div>-->
                    <div class="col-6">
                        <div class="card bg-info text-white text-center">
                            <div class="card-body">
                                <div class="mb-1">
                                    <i class="fa fa-calendar fs-3"></i>
                                </div>
                                <h2 class="mb-1">{{ stats.totalDays }}</h2>
                                <small>日数</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Nút mở modal comment -->
                
            </div>
        </div>

        <!-- Modal chọn member/manager -->
        <div class="modal fade" tabindex="-1" :class="{show: showMemberModal}"  style="display: block;"  v-if="showMemberModal">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ memberSelectType === 'manager' ? '管理者を選択' : 'メンバーを選択' }}</h5>
                        <button type="button" class="btn-close" @click="showMemberModal = false"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex flex-wrap">
                            <div v-for="user in allUsers" :key="user.userid" class="m-2 text-center" style="cursor:pointer;">
                                <div @click="toggleMemberSelect(user.userid)" :class="{'border border-primary': memberSelected.includes(user.userid)}" style="display:inline-block;border-radius:50%;padding:2px;">
                                    <img v-if="!user.avatarError && getAvatarSrc(user)" class="rounded-circle" :src="getAvatarSrc(user)" :alt="user.user_name" width="40" height="40" @error="handleAvatarError(user)">
                                    <span v-else class="avatar-initial rounded-circle bg-label-primary" style="width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;">{{ getInitials(user.user_name) }}</span>
                                </div>
                                <div style="font-size:12px;max-width:60px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ user.user_name }}</div>
                                <input type="checkbox" class="form-check-input mt-1" :checked="memberSelected.includes(user.userid)" @change="toggleMemberSelect(user.userid)">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" @click="showMemberModal = false">キャンセル</button>
                        <button class="btn btn-primary" @click="confirmMemberSelect">OK</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Note -->
        <div class="modal fade" tabindex="-1" :class="{show: showNoteModal}" style="display: block;" v-if="showNoteModal">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <template v-if="editingNote.id">
                                メモ詳細
                            </template>
                            <template v-else>
                                新しいメモ
                            </template>
                        </h5>
                        <button type="button" class="btn-close" @click="closeNoteModal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- View mode -->
                        <div v-if="editingNote.id && !isNoteEditMode">
                            <div class="mb-3">
                                <label class="form-label">タイトル</label>
                                <div class="form-control bg-light">{{ editingNote.title }}</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">内容</label>
                                <div class="form-control bg-light" style="min-height:100px;white-space:pre-line;">{{ editingNote.content || '-' }}</div>
                            </div>
                            <div class="mb-3" v-if="editingNote.is_important">
                                <label class="form-label">重要メモ</label>
                                <div>
                                    <i class="fa fa-star text-warning"></i>
                                </div>
                            </div>
                           
                        </div>
                        <!-- Edit mode -->
                        <form v-else @submit.prevent="saveNote">
                            <div class="mb-3">
                                <label class="form-label">タイトル <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" v-model="editingNote.title" required maxlength="255">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">内容</label>
                                <textarea class="form-control" v-model="editingNote.content" rows="6" placeholder="メモの詳細を入力してください..."></textarea>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" v-model="editingNote.is_important" id="isImportant">
                                    <label class="form-check-label" for="isImportant">
                                        <i class="fa fa-star text-warning me-2"></i> 重要メモ
                                    </label>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <template v-if="editingNote.id && !isNoteEditMode">
                            <button class="btn btn-primary" @click="isNoteEditMode = true" v-if="canEditNote(editingNote)"><i class="fa fa-pencil-alt me-2"></i> 編集</button>
                            <button class="btn btn-secondary" @click="closeNoteModal">閉じる</button>
                        </template>
                        <template v-else>
                            <button class="btn btn-secondary" @click="isNoteEditMode = false" v-if="editingNote.id"><i class="fa fa-times me-2"></i> キャンセル</button>
                            <button class="btn btn-secondary" @click="closeNoteModal" v-else>キャンセル</button>
                            <button class="btn btn-primary" @click="saveNote" :disabled="!editingNote.title.trim()">
                                <i class="fa fa-save me-2"></i> 保存
                            </button>
                        </template>
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


/* Note content styling */
.note-content {
    line-height: 1.4;
    max-height: 2.8em; /* 2 lines */
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    word-break: break-word;
    margin-bottom: 0.25rem;
    font-size: 0.875rem;
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

/* Time remaining badge styling */
.form-label .badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-weight: normal;
    transition: all 0.2s ease;
}

.form-label .badge:hover {
    transform: scale(1.05);
}

.form-label .badge.bg-danger {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
    }
}

</style>

<!-- Define PROJECT_ID before loading Vue and project-detail.js -->
<script>
const PROJECT_ID = <?php echo $project_id; ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/quill/typography.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/quill/editor.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/css/mention.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/css/comment-component.css" />
<link rel="stylesheet" href="assets/css/task-manager.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/tagify/tagify.css" />

<script src="<?=ROOT?>assets/vendor/libs/quill/quill.js"></script>
<script src="<?=ROOT?>assets/js/sw-manager.js"></script>
<script src="/assets/js/mention.js"></script>
<script src="/assets/js/comment-component.js"></script>
<script src="<?=ROOT?>assets/vendor/libs/tagify/tagify.js"></script>
<script src="assets/js/project-detail.js"></script>

