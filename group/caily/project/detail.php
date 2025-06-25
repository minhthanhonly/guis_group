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
    <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="#">プロジェクト詳細</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#projectNavbar" aria-controls="projectNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="projectNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                <a class="nav-link active" aria-current="page" href="detail.php?id=<?php echo $project_id; ?>">概要</a>
                </li>
                <li class="nav-item">
                <a class="nav-link" href="task.php?project_id=<?php echo $project_id; ?>">タスク</a>
                </li>
                <li class="nav-item">
                <a class="nav-link" href="gantt.php?project_id=<?php echo $project_id; ?>">ガントチャート</a>
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
            <a href="index.php" class="btn btn-outline-primary">
                <i class="fa fa-arrow-left"></i> 戻る
            </a>
        </div>

        <!-- Left Column - Project Details -->
        <div class="col-xl-8">
            <div class="card" :class="{ 'edit-mode': isEditMode }">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title">基本情報</h5>
                        <div>
                            <button v-if="!isEditMode" class="btn btn-outline-primary btn-sm me-2" @click="toggleEditMode">
                                <i class="fa fa-pencil-alt"></i>
                            </button>
                            <div v-else class="d-flex gap-2">
                                <button class="btn btn-success btn-sm" @click="saveProject">
                                    <i class="fa fa-save"></i>
                                </button>
                                <button class="btn btn-secondary btn-sm" @click="cancelEdit">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                            <button v-if="!isEditMode" class="btn btn-outline-danger btn-sm" @click="deleteProject">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>


                    
                    <div class="row g-3" v-if="project">
                        <div class="col-md-4">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label">会社名 <span class="text-danger">*</span></label>
                                <template v-if="isEditMode">
                                    <select id="category_id" class="form-select select2" v-model="newProject.category_id" name="category_id" required @change="onCategoryChange">
                                        <option value="">選択してください</option>
                                        <option v-for="category in categories" :key="category.id" :value="category.id">
                                            {{ category.name }}
                                        </option>
                                    </select>
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control" :value="getCategoryName(newProject.category_id)" readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label">支店名 <span class="text-danger">*</span></label>
                                <template v-if="isEditMode">
                                    <select id="company_name" class="form-select select2" v-model="newProject.company_name" name="company_name" required @change="onCompanyChange">
                                        <option value="">選択してください</option>
                                        <option v-for="company in companies" :key="company.company_name" :value="company.company_name">
                                            {{ company.company_name }}
                                        </option>
                                    </select>
                                </template>
                                <template v-else>
                                    <input type="text" class="form-control" :value="newProject.company_name" readonly>
                                </template>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3 form-control-validation">
                                <label class="form-label">担当者名 <span class="text-danger">*</span></label>
                                <div class="d-flex gap-2 justify-content-between">
                                    <template v-if="isEditMode">
                                        <select id="customer_id" class="form-select select2 flex-shrink-1" v-model="newProject.customer_id" name="customer_id" required>
                                            <option value="">選択してください</option>
                                            <option v-for="contact in contacts" :key="contact.id" :value="contact.id">
                                                {{ contact.name }}
                                            </option>
                                        </select>
                                    </template>
                                    <template v-else>
                                        <input type="text" class="form-control" :value="getContactName(newProject.customer_id)" readonly>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">プロジェクト番号</label>
                            <input type="text" class="form-control" v-if="isEditMode" v-model="project.project_number">
                            <input type="text" class="form-control" v-else :value="project.project_number || '-'" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">プロジェクト名</label>
                            <input type="text" class="form-control" :readonly="!isEditMode" v-model="project.name">
                        </div>
                        
                        
                        <div class="col-md-4">
                            <label class="form-label">部署</label>
                            <input type="text" class="form-control" :value="department?.name || '-'" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">工事支店</label>
                            <input type="text" class="form-control" :readonly="!isEditMode" v-model="project.building_branch">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">建物規模</label>
                            <input type="text" class="form-control" :readonly="!isEditMode" v-model="project.building_size">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">建物種類</label>
                            <input type="text" class="form-control" :readonly="!isEditMode" v-model="project.building_type">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">工事番号</label>
                            <input type="text" class="form-control" :readonly="!isEditMode" v-model="project.building_number">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">優先度</label>
                            <div>
                                <div class="btn-group" v-if="isManager">
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
                        <div class="col-md-4">
                            <label class="form-label">ステータス</label>
                            <div>
                                <div class="btn-group" v-if="isManager">
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
                            <label class="form-label">受注形態</label>
                            <template v-if="isEditMode">
                                <input type="text" class="form-control tagify" v-model="project.project_order_type" id="project_order_type" name="project_order_type" required>
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
                            <label class="form-label">チーム</label>
                            <div class="d-flex align-items-center gap-2">
                                <input v-if="isEditMode" id="team_tags" class="form-control" />
                                <button v-if="isEditMode" class="btn btn-outline-secondary btn-sm" type="button" @click="clearTagifyTags('team')" title="すべて削除"><i class="fa fa-times"></i></button>
                                <div v-else>
                                    <span v-if="project.team_list && project.team_list.length > 0">
                                        <span v-for="team in project.team_list" :key="team.id" class="badge bg-info me-1">{{ team.name }}</span>
                                    </span>
                                    <span v-else class="text-muted">-</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <label class="form-label">管理</label>
                            <div v-if="isEditMode" class="d-flex align-items-center gap-2">
                                <input class="form-control" type="text" id="manager_tags" name="manager_tags">
                                <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearTagifyTags('manager')" title="すべて削除"><i class="fa fa-times"></i></button>
                            </div>
                            <div class="d-flex align-items-center" v-else-if="managers && managers.length > 0">
                                <div v-for="member in managers" :key="member.userid"
                                    class="avatar me-2"
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
                                メンバーがいません
                            </div>
                        </div>
                        <div class="col-4">
                            <label class="form-label">メンバー</label>
                            <div v-if="isEditMode" class="d-flex align-items-center gap-2">
                                <input class="form-control" type="text" id="members_tags" name="members_tags">
                                <button class="btn btn-outline-secondary btn-sm" type="button" @click="clearTagifyTags('members')" title="すべて削除"><i class="fa fa-times"></i></button>
                            </div>
                            <div class="d-flex align-items-center" v-else-if="members.length > 0">
                                <div v-for="member in members" :key="member.userid"
                                    class="avatar me-2"
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
                                メンバーがいません
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">開始日</label>
                            <div v-if="isEditMode" class="input-group">
                                <input type="text" class="form-control" v-model="project.start_date" id="start_date_picker" placeholder="YYYY/MM/DD HH:mm">
                                <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                            </div>
                            <input v-else type="text" class="form-control" :value="formatDateTime(project.start_date)" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">終了日</label>
                            <div v-if="isEditMode" class="input-group">
                                <input type="text" class="form-control" v-model="project.end_date" id="end_date_picker" placeholder="YYYY/MM/DD HH:mm">
                                <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                            </div>
                            <input v-else type="text" class="form-control" :value="formatDateTime(project.end_date)" readonly>
                        </div>
                        <!-- <div class="col-md-4">
                            <label class="form-label">実終了日</label>
                            <div v-if="isEditMode" class="input-group">
                                <input type="text" class="form-control" v-model="project.actual_end_date" id="actual_end_date_picker" placeholder="YYYY/MM/DD HH:mm">
                                <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                            </div>
                            <input v-else type="text" class="form-control" :value="formatDateTime(project.actual_end_date)" readonly>
                        </div> -->
                        <div class="col-4">
                            <label class="form-label">進捗率</label>
                            <div class="position-relative">
                                <input
                                    v-if="isManager"
                                    type="range"
                                    min="0"
                                    max="100"
                                    v-model="project.progress"
                                    @change="updateProgress"
                                    class="form-range custom-progress-range"
                                >
                                <input
                                    v-else
                                    type="range"
                                    min="0"
                                    max="100"
                                    :value="project.progress"
                                    class="form-range custom-progress-range"
                                    disabled
                                >
                                <div class="progress-value-label text-center">
                                    {{ project.progress }}%
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">説明</label>
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
                                <label class="form-label">カスタム項目セット</label>
                                <select class="form-select" v-model="project.department_custom_fields_set_id">
                                    <option value="">選択してください</option>
                                    <option v-for="set in departmentCustomFieldSets" :value="set.id">{{ set.name }}</option>
                                </select>
                                <div v-if="selectedCustomFieldSet" class="mt-3">
                                    <div v-for="(field, idx) in selectedCustomFieldSet.fields" :key="idx" class="mb-2">
                                        <label class="form-label">{{ field.label }}</label>
                                        <template v-if="field.type === 'radio'">
                                            <div v-if="customFields[idx]">
                                                <label v-for="opt in field.options.split(',')" :key="opt.trim()" class="me-3">
                                                    <input type="radio" :name="'custom_radio_' + idx" :value="opt.trim()" v-model="customFields[idx].value"> {{ opt.trim() }}
                                                </label>
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
                                                <label v-for="opt in field.options.split(',')" :key="opt.trim()" class="me-3">
                                                    <input type="checkbox" :name="'custom_checkbox_' + idx" :value="opt.trim()" v-model="customFields[idx].valueArr"> {{ opt.trim() }}
                                                </label>
                                            </div>
                                        </template>
                                        <template v-else-if="field.type === 'textarea'">
                                            <textarea class="form-control" v-if="customFields[idx]" v-model="customFields[idx].value"></textarea>
                                        </template>
                                        <template v-else>
                                            <input class="form-control" v-if="customFields[idx]" v-model="customFields[idx].value" type="text">
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <template v-else>
                                <template v-if="getCustomFieldsForView().length">
                                    <ul class="mb-0 ps-3">
                                        <li v-for="(field, idx) in getCustomFieldsForView()" :key="idx">
                                            <strong>{{ field.label }}:</strong>
                                            <template v-if="field.type === 'checkbox'">
                                                <span v-if="field.value">
                                                    <span v-for="val in field.value.split(',')" :key="val.trim()" class="badge bg-primary me-1">{{ val.trim() }}</span>
                                                </span>
                                                <span v-else>-</span>
                                            </template>
                                            <template v-else-if="field.type === 'radio' || field.type === 'select' || field.type === 'text' || field.type === 'textarea'">
                                                {{ field.value || '-' }}
                                            </template>
                                            <template v-else>
                                                {{ field.value || '-' }}
                                            </template>
                                        </li>
                                    </ul>
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

        </div>

        <!-- Right Column - Stats & Comments -->
        <div class="col-xl-4">
            <!-- Statistics Cards -->
            <!-- <div class="row g-3 mb-4">
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
                <div class="col-6">
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
                </div>
                <div class="col-6">
                    <div class="card bg-warning text-white text-center">
                        <div class="card-body">
                            <div class="mb-1">
                                <i class="fa fa-calendar fs-3"></i>
                            </div>
                            <h2 class="mb-1">{{ stats.totalDays }}</h2>
                            <small>日数</small>
                        </div>
                    </div>
                </div>
            </div> -->

            <!-- Comments Section -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">コメント</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="text-center py-5" v-if="comments.length === 0">
                            <i class="fa fa-comment-dots fs-1 text-muted"></i>
                            <p class="text-muted">コメントはまだありません</p>
                        </div>
                        <!-- Comments List -->
                        <div v-else class="comment-list mb-4" style="max-height: 400px; overflow-y: auto;">
                            <div v-for="comment in comments" :key="comment.id" class="comment-item mb-3">
                                <div class="d-flex gap-3">
                                    <div class="flex-shrink-0">
                                        <div class="avatar avatar-sm">
                                            <span class="avatar-initial rounded-circle bg-primary">
                                                {{ comment.user_name ? comment.user_name.charAt(0) : '?' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="comment-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0">{{ comment.user_name }}</h6>
                                            <small class="text-muted">{{ formatDateTime(comment.created_at) }}</small>
                                        </div>
                                        <div class="comment-content mt-1">
                                            {{ comment.content }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Comment Input -->
                    <div class="d-flex gap-3">
                        <div class="flex-grow-1">
                            <div class="input-group">
                                <input type="text" class="form-control" 
                                       v-model="newComment" 
                                       @keyup.enter="addComment"
                                       placeholder="コメントを入力...">
                                <button class="btn btn-primary" 
                                        @click="addComment"
                                        :disabled="!newComment.trim()">
                                    <i class="fa fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
</style>

<!-- Define PROJECT_ID before loading Vue and project-detail.js -->
<script>
const PROJECT_ID = <?php echo $project_id; ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/quill/typography.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/highlight/highlight.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/quill/editor.css" />
<link rel="stylesheet" href="<?=ROOT?>assets/vendor/libs/quill/katex.css" />
<script src="<?=ROOT?>assets/vendor/libs/highlight/highlight.js"></script>
<script src="<?=ROOT?>assets/vendor/libs/quill/katex.js"></script>
<script src="<?=ROOT?>assets/vendor/libs/quill/quill.js"></script>
<script src="assets/js/project-detail.js"></script>