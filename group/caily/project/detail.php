<?php
require_once('../application/loader.php');
$view->heading('案件詳細');

// Default note type by branch: CAILY branch -> CAILYメモ(1), otherwise GUISメモ(2)
$noteDefaultType = 2;
$isCailyBranchUser = false;
try {
    require_once('../application/model/branch.php');
    $branchModel = new Branch();
    $branch = $branchModel->get_user_branch_name();
    if ($branch && isset($branch['name']) && $branch['name'] === 'CAILY') {
        $noteDefaultType = 1;
        $isCailyBranchUser = true;
    }
} catch (Exception $e) {
    // fallback giữ nguyên GUISメモ
}
echo '<script>window.NOTE_DEFAULT_TYPE = ' . (int)$noteDefaultType . ';</script>';

// Get project ID from URL
$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$project_id) {
    header('Location: index.php');
    exit;
}
if($_SESSION['show_project'] == 0){
    echo '<div class="container-fluid mt-4"><div class="alert alert-danger">権限がありません。</div></div>';
    exit;
}
?>
<div id="app" class="container-fluid mt-4" v-cloak>
    <div v-if="true">
        <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold" href="#"><span class="badge badge-sm bg-primary">#{{ project?.id }}</span></a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#projectNavbar" aria-controls="projectNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="projectNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                    <a class="nav-link active text-primary" aria-current="page" href="detail.php?id=<?php echo $project_id; ?>"><span data-i18n="概要">概要</span></a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link" href="task.php?project_id=<?php echo $project_id; ?>"><span data-i18n="タスク">タスク</span><span class="badge badge-sm ms-1 rounded-pill">{{ project?.task_count }}</span></a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link" href="gantt.php?project_id=<?php echo $project_id; ?>"><span data-i18n="ガントチャート">ガントチャート</span></a>
                    </li>
                    <!--<li class="nav-item" v-if="canViewDrawings">
                    <a class="nav-link" href="drawings.php?project_id=<?php echo $project_id; ?>"><span data-i18n="図面">図面</span><span class="badge badge-sm bg-info ms-1 rounded-pill">{{ project?.drawing_count }}</span></a>
                    </li>-->
                    <li class="nav-item">
                    <a class="nav-link" href="attachment.php?project_id=<?php echo $project_id; ?>"><span data-i18n="添付ファイル">添付ファイル</span></a>
                    </li>
                </ul>
                </div>
            </div>
        </nav>
        <?php $statusBannerVar = 'project'; require __DIR__ . '/partials/project-status-banner.php'; ?>
        <div class="row">
            <!-- Back button -->
            <div class="col-12 mb-3">
                <a href="index.php" class="btn btn-outline-primary me-2">
                    <i class="fa fa-arrow-left me-2"></i><span data-i18n="案件一覧へ戻る">案件一覧へ戻る</span>
                </a>
                <a v-if="project && project.parent_project_id" :href="'../parent_project/detail.php?id=' + project.parent_project_id" class="btn btn-outline-primary">
                    <i class="fa fa-external-link me-2"></i>
                    <span data-i18n="建物詳細">建物詳細</span>
                </a>
                <span v-if="project && (project.parent_project_name || project.parent_construction_number)"
                      class="ms-3 d-inline-flex flex-wrap align-items-center gap-3 border rounded px-3 py-2 align-middle">
                    <span v-if="project.parent_project_name" class="d-inline-flex align-items-center gap-1">
                        <span class="badge bg-label-primary" data-i18n="お施主様名">お施主様名</span>
                        <span class="fw-semibold">{{ project.parent_project_name }}</span>
                    </span>
                    <span v-if="project.parent_construction_number" class="d-inline-flex align-items-center gap-1">
                        <span class="badge bg-label-info" data-i18n="工事番号">工事番号</span>
                        <span class="fw-semibold">{{ project.parent_construction_number }}</span>
                    </span>
                </span>
            </div>

            <!-- Left Column - Project Details -->
            <div class="col-xl-8">
                <div class="card" :class="{ 'edit-mode': isEditMode }">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="card-title">
                                <i class="fa fa-star me-2" 
                                   :class="project && project.is_favorite == 1 ? 'text-warning' : 'text-muted'"
                                   style="cursor: pointer; font-size: 1.2em;"
                                   @click="toggleFavorite"
                                   :title="project && project.is_favorite == 1 ? 'お気に入りから削除' : 'お気に入りに追加'"></i>
                                <span data-i18n="案件詳細">案件詳細</span>
                                <span v-if="project && project.department_name" class="badge border border-info bg-transparent text-info ms-2">{{ project.department_name }}</span>
                            </h5>
                            <div>
                                <button v-if="!isEditMode && project" class="btn btn-outline-info btn-sm me-2" @click="copyProjectInfoToClipboard" title="案件情報をコピー">
                                    <i class="fa fa-copy me-1"></i><span data-i18n="案件情報をコピー">案件情報をコピー</span>
                                </button>
                                <!-- Join Project Button -->
                            <button v-if="!isEditMode && canJoinProject" class="btn btn-primary btn-sm me-2" @click="joinProject" title="案件に参加">
                                    <i class="fa fa-user-plus me-1"></i><span data-i18n="案件に参加">案件に参加</span>
                                </button>
                                
                                <!-- Confirm Project Button for Kadai Projects -->
                                <button v-if="project && project.is_kadai == 1 && project.status !== 'cancelled' && !isEditMode && canEditProject" class="btn btn-success btn-sm me-2" @click="confirmKadaiProject" title="案件を承認">
                                    <i class="fa fa-check me-1"></i>案件を承認
                                </button>
                                
                                <!-- <button v-if="!isEditMode && canAddProject" class="btn btn-outline-info btn-sm me-2" @click="copyProject" title="案件をコピー">
                                    <i class="fa fa-copy"></i> <span data-i18n="案件をコピー">案件をコピー</span>
                                </button> -->
                                <button v-if="!isEditMode && canEditProject && !(project && project.is_kadai == 1)" class="btn btn-outline-warning btn-sm me-2" @click="toggleEditMode" title="編集">
                                    <i class="fa fa-pencil-alt me-1"></i> <span data-i18n="編集">編集</span>
                                </button>
                                <button v-if="isEditMode" class="btn btn-success btn-sm me-2" @click="saveProject" :disabled="savingProject" title="保存">
                                    <span v-if="savingProject" class="spinner-border spinner-border-sm me-1"></span>
                                    <i v-else class="fa fa-save me-1"></i> <span v-if="savingProject" data-i18n="保存中">保存中...</span><span v-else data-i18n="保存">保存</span>
                                </button>
                                <button v-if="isEditMode" class="btn btn-secondary btn-sm me-2" @click="cancelEdit" title="キャンセル">
                                    <i class="fa fa-times me-1"></i> <span data-i18n="キャンセル">キャンセル</span>
                                </button>  
                                <button v-if="!isEditMode && canDeleteProject && !(project && project.is_kadai == 1)" class="btn btn-outline-danger btn-sm" @click="deleteProject" title="削除">
                                    <i class="fa fa-trash me-1"></i> <span data-i18n="削除">削除</span>
                                </button>
                            </div>
                        </div>


                        
                        <div class="row g-3" v-if="project">

                            
                            <!-- Parent Project Information Accordion (for child projects) -->
                            <div v-if="project.parent_project_id" class="col-12 mb-4">
                                <div class="accordion" id="parentProjectAccordion">
                                    <div class="accordion-item border-primary">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#parentProjectCollapse" aria-expanded="false" aria-controls="parentProjectCollapse">
                                                <span data-i18n="建物情報">建物情報</span>
                                            </button>
                                        </h2>
                                        <div id="parentProjectCollapse" class="accordion-collapse collapse" data-bs-parent="#parentProjectAccordion">
                                            <div class="accordion-body">
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
                                                        <label class="form-label"><span data-i18n="担当様">担当様</span></label>
                                                        <input type="text" class="form-control" :value="project.contact_name || '-'" readonly>
                                                    </div>
                                                    <div class="col-md-4 mb-3">
                                                        <label class="form-label"><span data-i18n="お施主様名">お施主様名</span></label>
                                                        <input type="text" class="form-control" :value="project.building_name || '-'" readonly>
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
                                                        <label class="form-label"><span data-i18n="GUIS 受付者">GUIS 受付者</span></label>
                                                        <input type="text" class="form-control" :value="project.guis_receiver_display_name || project.guis_receiver || '-'" readonly>
                                                    </div>
                                                    <!--<div class="col-md-4 mb-3">
                                                        <label class="form-label"><span data-i18n="工事支店">工事支店</span></label>
                                                        <div style="min-height:38px;">
                                                            <span v-if="project.building_branch && project.building_branch.split(',').length > 0">
                                                                <span v-for="item in project.building_branch.split(',')" :key="item.trim()" class="badge bg-primary me-1">{{ item.trim() }}</span>
                                                            </span>
                                                            <span v-else>-</span>
                                                        </div>
                                                    </div>-->
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
                                                        <label class="form-label"><span data-i18n="依頼">依頼</span></label>
                                                        <div class="form-control-plaintext">
                                                            <div class="d-flex flex-wrap gap-1 align-items-center" v-if="parentRequestTypes.length">
                                                                <span v-for="item in parentRequestTypes" :key="item"
                                                                      class="badge me-1 mb-1"
                                                                      :class="isParentRequestFulfilled(item) ? getRequestBadgeClass(item) : 'bg-warning text-dark'"
                                                                      :title="isParentRequestFulfilled(item) ? '' : '未作成'">
                                                                    <i v-if="!isParentRequestFulfilled(item)" class="fa fa-exclamation-triangle me-1"></i>
                                                                    <i v-else class="fa fa-check me-1"></i>
                                                                    {{ item }}
                                                                </span>
                                                            </div>
                                                            <span v-else class="text-muted">-</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
                        
                            <div class="col-md-4  mt-4">
                                <label class="form-label"><span data-i18n="ID">ID</span></label>
                                <input type="text" class="form-control" :value="project?.id || '-'" readonly>
                            </div>
                            <input v-if="isEditMode && project" type="hidden" v-model.number="project.version">
                            <div class="col-md-4">
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
                                                {{ getStatusLabel(project.status) }}
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
                                </div>
                            </div>
                            <div class="col-12">
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
                            <div class="col-md-4">
                                <label class="form-label"><span data-i18n="開始日">開始日</span></label>
                                <div v-if="isEditMode" class="input-group">
                                    <input type="text" class="form-control" v-model="project.start_date" id="start_date_picker" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off">
                                    <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                                </div>
                                <input v-else type="text" class="form-control" :value="formatDateTime(project.start_date)" :data-time="project.start_date || ''" :data-todo-title="(project ? ('#' + project.id + ' ' + (project.name || '')) : '') + ''" :data-todo-link="project ? ('/project/detail.php?id=' + project.id) : ''" data-bs-toggle="tooltip" :data-bs-title="getVietnamTimeTooltip(project.start_date)" readonly>
                            </div>
                            <div class="col-md-4" v-if="canViewEndDate">
                                <label class="form-label">
                                    <span data-i18n="期限日(実納期)">期限日(実納期)</span>
                                    <span v-if="getTimeRemaining()" :class="'badge ms-2 ' + getTimeRemaining().class" 
                                          :title="getTimeRemaining().isOverdue ? '期限を超過しています' : '残り時間'">
                                        {{ getTimeRemaining().text }}
                                    </span>
                                </label>
                                <template v-if="isEditMode && !isCailyBranchUser">
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
                            <div class="col-md-4">
                                <label class="form-label"><span data-i18n="担当">担当</span></label>
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
                            </div>
                            <div class="col-4">
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
                            
                            <div class="col-md-4">
                                <label class="form-label"><span data-i18n="CAILY納期">CAILY納期</span>
                                    <span v-if="isEditMode && project.end_date && project.tantou === 'CAILY'" class="text-danger">*</span>
                                    <span v-if="getTimeRemainingForDate(project.caily_nouki) && project.caily_nouki_status !== '納品済み'" :class="'badge ms-2 ' + getTimeRemainingForDate(project.caily_nouki).class"
                                          :title="getTimeRemainingForDate(project.caily_nouki).isOverdue ? '期限を超過しています' : '残り時間'">
                                        {{ getTimeRemainingForDate(project.caily_nouki).text }}
                                    </span>
                                    <span v-if="getNoukiScheduleJudgment('caily')"
                                          :class="'badge ms-1 ' + getNoukiScheduleJudgment('caily').className">
                                        {{ translateLabel(getNoukiScheduleJudgment('caily').textKey) }}
                                    </span>
                                </label>
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
                            <div class="col-md-4" v-if="canViewEndDate">
                                <label class="form-label"><span data-i18n="GUIS納期">GUIS納期</span>
                                    <span v-if="isEditMode && !isCailyBranchUser && project.end_date && project.tantou === 'GUIS'" class="text-danger">*</span>
                                    <span v-if="getTimeRemainingForDate(project.guis_nouki) && project.guis_nouki_status !== '納品済み'" :class="'badge ms-2 ' + getTimeRemainingForDate(project.guis_nouki).class"
                                          :title="getTimeRemainingForDate(project.guis_nouki).isOverdue ? '期限を超過しています' : '残り時間'">
                                        {{ getTimeRemainingForDate(project.guis_nouki).text }}
                                    </span>
                                    <span v-if="getNoukiScheduleJudgment('guis')"
                                          :class="'badge ms-1 ' + getNoukiScheduleJudgment('guis').className">
                                        {{ translateLabel(getNoukiScheduleJudgment('guis').textKey) }}
                                    </span>
                                </label>
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
                            
                            <div class="col-md-4">
                                <label class="form-label"><span data-i18n="実終了日">実終了日</span></label>
                                <div v-if="isEditMode" class="input-group">
                                    <input type="text" class="form-control" v-model="project.actual_end_date" id="actual_end_date_picker" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off">
                                    <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                                </div>
                                <input v-else type="text" class="form-control" :value="formatDateTime(project.actual_end_date)" readonly>
                            </div>
                            <hr class="mt-4 border-primary">
                            <div class="col-12 mt-3">
                                <template v-if="isEditMode">
                                    <div v-if="customFields && customFields.length > 0" class="mt-3">
                                        <div class="row">
                                            <template v-for="(field, idx) in customFields" :key="idx">
                                                <div v-if="field.type === 'textarea'" class="col-12 mb-3">
                                                    <label class="form-label">{{ translateLabel(field.label) }}</label>
                                                    <textarea class="form-control" v-model="field.value"></textarea>
                                                </div>
                                                <div v-else :class="['mb-3', field.one_row ? 'col-12' : 'col-md-6']">
                                                    <div class="form-label">{{ translateLabel(field.label) }}</div>
                                                    <template v-if="field.type === 'radio'">
                                                        <div class="form-check form-check-inline" v-for="opt in field.options.split(',')" :key="opt.trim()">
                                                            <input class="form-check-input" type="radio" :name="'custom_radio_' + idx" :value="opt.trim()" v-model="field.value">
                                                            <label class="form-check-label">{{ opt.trim() }}</label>
                                                        </div>
                                                    </template>
                                                    <template v-else-if="field.type === 'select'">
                                                        <select class="form-select" v-model="field.value">
                                                            <option value="" data-i18n="選択してください">選択してください</option>
                                                            <option v-for="opt in field.options.split(',')" :key="opt.trim()" :value="opt.trim()">{{ opt.trim() }}</option>
                                                        </select>
                                                    </template>
                                                    <template v-else-if="field.type === 'checkbox'">
                                                        <div class="form-check form-check-inline" v-for="opt in field.options.split(',')" :key="opt.trim()">
                                                            <input class="form-check-input" type="checkbox" :name="'custom_checkbox_' + idx" :value="opt.trim()" v-model="field.valueArr">
                                                            <label class="form-check-label">{{ opt.trim() }}</label>
                                                        </div>
                                                    </template>
                                                    <template v-else-if="field.type === 'datetime'">
                                                        <div class="input-group">
                                                            <input type="text" class="form-control custom-field-datetime" 
                                                                   :id="'custom_datetime_' + idx" 
                                                                   v-model="field.value" 
                                                                   :placeholder="getProjectDateTimePlaceholder()" 
                                                                   autocomplete="off">
                                                            <span class="input-group-text"><i class="fa fa-calendar"></i></span>
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
                                                <div v-else :class="['mb-3', field.one_row ? 'col-12' : 'col-md-6']">
                                                    <label class="form-label">{{ translateLabel(field.label) }}</label>
                                                    <template v-if="canEditProject && (field.type === 'select' || field.type === 'radio' || field.type === 'checkbox')">
                                                        <select v-if="field.type === 'select'" class="form-select form-select-sm" :value="field.value" @change="updateCustomFieldValue(field.label, $event.target.value)">
                                                            <option value="">選択してください</option>
                                                            <option v-for="opt in (field.options || '').split(',').map(o=>o.trim()).filter(Boolean)" :key="opt" :value="opt">{{ translateLabel(opt) }}</option>
                                                        </select>
                                                        <template v-else-if="field.type === 'radio'">
                                                            <div class="d-flex flex-wrap gap-2">
                                                                <div v-for="opt in (field.options || '').split(',').map(o=>o.trim()).filter(Boolean)" :key="opt" class="form-check">
                                                                    <input class="form-check-input" type="radio" :name="'cf_'+field.label+'_'+idx" :value="opt" :checked="field.value === opt" @change="updateCustomFieldValue(field.label, opt)">
                                                                    <label class="form-check-label">{{ translateLabel(opt) }}</label>
                                                                </div>
                                                            </div>
                                                        </template>
                                                        <template v-else-if="field.type === 'checkbox'">
                                                            <div class="d-flex flex-wrap gap-2">
                                                                <div v-for="opt in (field.options || '').split(',').map(o=>o.trim()).filter(Boolean)" :key="opt" class="form-check">
                                                                    <input class="form-check-input" type="checkbox" :value="opt" :checked="isCustomFieldCheckboxChecked(field.label, opt)" @change="onCustomFieldCheckboxChange(field, opt, $event.target.checked)">
                                                                    <label class="form-check-label">{{ translateLabel(opt) }}</label>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </template>
                                                    <template v-else>
                                                        <template v-if="field.type === 'checkbox' || field.type === 'radio' || field.type === 'select'">
                                                            <div>
                                                                <span v-if="field.value">
                                                                    <span v-for="val in field.value.split(',')" :key="val.trim()" class="badge bg-primary me-1">{{ translateLabel(val.trim()) }}</span>
                                                                </span>
                                                                <span v-else>-</span>
                                                            </div>
                                                        </template>
                                                        <template v-else-if="field.type === 'datetime'">
                                                            <div class="form-control" :data-time="field.value || ''" :data-todo-title="(project ? ('#' + project.id + ' ' + (project.name || '')) : '') + field.label" :data-todo-link="project ? ('/project/detail.php?id=' + project.id) : ''">{{ formatDateTime(field.value) }}</div>
                                                        </template>
                                                        <template v-else>
                                                            <div class="form-control">{{ field.value || '-' }}</div>
                                                        </template>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </template>
                            </div>
                            <hr class="mt-4 border-primary">
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
                            

                        </div>
                        <div v-else class="text-center py-5">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Comments Section (D6: mount after idle / near-viewport) -->
                <div class="card mt-4" v-if="canCommentProject" ref="commentsSection">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fa fa-comment me-2"></i><span data-i18n="コメント">コメント</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <comment-component
                             v-if="commentsMountReady"
                             :entity-type="'project'"
                             :entity-id="projectId"
                             :current-user="currentUser"
                             :enable-threads="true"
                             @comment-added="onCommentAdded"
                             @message="onCommentMessage"
                             @error="onCommentError">
                        </comment-component>
                        <div v-else class="text-center text-muted py-3">
                            <i class="fa fa-spinner fa-spin me-1"></i>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column - Stats & Comments -->
            <div class="col-xl-4">
                <!-- Project Status Block -->
                <div class="card mb-4 project-status-block" v-if="canViewBusinessDocuments">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="card-title mb-0"><span data-i18n="決済情報">決済情報</span></h5>
                            <span v-if="businessDocumentSaveStatus === 'loading'" class="text-muted" title="保存中">
                                <i class="fa fa-spinner fa-spin"></i>
                            </span>
                            <span v-else-if="businessDocumentSaveStatus === 'saved'" class="text-success" title="保存済み">
                                <i class="fa fa-check-circle"></i>
                            </span>
                        </div>
                        <button type="button" class="btn btn-outline-info btn-sm" @click="openBusinessDocumentLogModal">
                            <i class="fa fa-history me-1"></i><span data-i18n="履歴">履歴</span>
                        </button>
                    </div>
                    <div class="card-body" v-if="project">
                        <input type="hidden" v-model.number="project.payment_version">
                        <h6 class="text-muted mb-3"><span data-i18n="見積">見積</span></h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label mb-0">見積日 <span v-if="canEditBusinessDocuments" class="text-danger">*</span></label>
                                    <button v-if="canEditBusinessDocuments && !hasBusinessDocumentDate('estimate_date')" type="button" class="btn btn-outline-primary btn-sm py-0 px-2"
                                            @click="setBusinessDocumentDateToday('estimate_date')">今日</button>
                                </div>
                                <input v-if="canEditBusinessDocuments" type="text" class="form-control" v-model="project.estimate_date" id="estimate_date_picker" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off">
                                <input v-else type="text" class="form-control" :value="formatBusinessDocumentDateTime('estimate_date')" :data-time="getBusinessDocumentDateForTooltip('estimate_date')" data-bs-toggle="tooltip" :data-bs-title="getVietnamTimeTooltip(getBusinessDocumentDateForTooltip('estimate_date'))" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">見積金額(税抜き) <span v-if="canEditBusinessDocuments" class="text-danger">*</span></label>
                                <input type="number" autocomplete="off" class="form-control" v-model.number="project.amount" :readonly="!canEditBusinessDocuments" @input="scheduleBusinessDocumentUpdate" min="0" step="1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted">消費税（10%）</label>
                                <input type="text" class="form-control bg-light" readonly tabindex="-1" :value="formatBusinessDocumentTaxAmount(project.amount)">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted">税込合計</label>
                                <input type="text" class="form-control bg-light fw-semibold" readonly tabindex="-1" :value="formatBusinessDocumentTotalWithTax(project.amount)">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">見積番号 <span v-if="canEditBusinessDocuments" class="text-danger">*</span></label>
                                <input type="text" autocomplete="off" class="form-control" v-model="project.estimate_number" :readonly="!canEditBusinessDocuments" @change="scheduleBusinessDocumentUpdate">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">見積状況</label>
                                <div v-if="canEditBusinessDocuments">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light"
                                                :class="getEstimateStatusButtonClass(project.estimate_status)"
                                                id="estimateStatusDropdown"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                            {{ getEstimateStatusLabel(project.estimate_status) }}
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li v-for="status in businessEstimateStatuses" :key="status.value">
                                                <a class="dropdown-item waves-effect" href="javascript:void(0);"
                                                   :class="{ disabled: status.value === '発行済' && !isEstimateDocumentFieldsComplete() }"
                                                   @click="selectEstimateStatus(status.value)">
                                                    {{ status.label }}
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    <div v-if="!isEstimateDocumentFieldsComplete()" class="form-text text-muted">
                                        発行済にするには見積日・見積金額・見積番号が必要です
                                    </div>
                                </div>
                                <div v-else>
                                    <span class="badge" :class="getEstimateStatusBadgeClass(project.estimate_status)">
                                        {{ getEstimateStatusLabel(project.estimate_status) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">

                        <h6 class="text-muted mb-3"><span data-i18n="請求">請求</span></h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label mb-0">請求日 <span v-if="canEditBusinessDocuments" class="text-danger">*</span></label>
                                    <button v-if="canEditBusinessDocuments && !hasBusinessDocumentDate('invoice_date')" type="button" class="btn btn-outline-primary btn-sm py-0 px-2"
                                            @click="setBusinessDocumentDateToday('invoice_date')">今日</button>
                                </div>
                                <input v-if="canEditBusinessDocuments" type="text" class="form-control" v-model="project.invoice_date" id="invoice_date_picker" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off">
                                <input v-else type="text" class="form-control" :value="formatBusinessDocumentDateTime('invoice_date')" :data-time="getBusinessDocumentDateForTooltip('invoice_date')" data-bs-toggle="tooltip" :data-bs-title="getVietnamTimeTooltip(getBusinessDocumentDateForTooltip('invoice_date'))" readonly>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label mb-0">請求金額(税抜き) <span v-if="canEditBusinessDocuments" class="text-danger">*</span></label>
                                    <button v-if="canEditBusinessDocuments && !hasBusinessDocumentAmount(project.invoice_amount)" type="button" class="btn btn-outline-primary btn-sm py-0 px-2"
                                            @click="copyEstimateAmountToInvoice">見積と同額</button>
                                </div>
                                <input type="number" autocomplete="off" class="form-control" v-model.number="project.invoice_amount" :readonly="!canEditBusinessDocuments" @input="scheduleBusinessDocumentUpdate" min="0" step="1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted">消費税（10%）</label>
                                <input type="text" class="form-control bg-light" readonly tabindex="-1" :value="formatBusinessDocumentTaxAmount(project.invoice_amount)">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted">税込合計</label>
                                <input type="text" class="form-control bg-light fw-semibold" readonly tabindex="-1" :value="formatBusinessDocumentTotalWithTax(project.invoice_amount)">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">請求番号 <span v-if="canEditBusinessDocuments" class="text-danger">*</span></label>
                                <input type="text" autocomplete="off" class="form-control" v-model="project.invoice_number" :readonly="!canEditBusinessDocuments" @change="scheduleBusinessDocumentUpdate">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">請求状況</label>
                                <div v-if="canEditBusinessDocuments">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light"
                                                :class="getInvoiceStatusButtonClass(project.invoice_status)"
                                                id="invoiceStatusDropdown"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                            {{ getInvoiceStatusLabel(project.invoice_status) }}
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li v-for="status in businessInvoiceStatuses" :key="status.value">
                                                <a class="dropdown-item waves-effect" href="javascript:void(0);"
                                                   :class="{ disabled: status.value === '発行済' && !isInvoiceDocumentFieldsComplete() }"
                                                   @click="selectInvoiceStatus(status.value)">
                                                    {{ status.label }}
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    <div v-if="!isInvoiceDocumentFieldsComplete()" class="form-text text-muted">
                                        発行済にするには請求日・請求金額・請求番号が必要です
                                    </div>
                                </div>
                                <div v-else>
                                    <span class="badge" :class="getInvoiceStatusBadgeClass(project.invoice_status)">
                                        {{ getInvoiceStatusLabel(project.invoice_status) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="mb-0">
                            <label class="form-label" data-i18n="決済備考">決済備考</label>
                            <textarea class="form-control" rows="3" v-model="project.payment_note" :readonly="!canEditBusinessDocuments" @change="scheduleBusinessDocumentUpdate"></textarea>
                        </div>

                        <div v-if="businessDocumentError" class="alert alert-danger mt-3 mb-0">{{ businessDocumentError }}</div>
                    </div>
                    <div class="card-body text-center py-4" v-else>
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>

                <!-- Other departments' sibling project deadlines -->
                <div class="card mb-4" v-if="otherDepartmentSiblingProjects.length">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fa fa-calendar-alt me-1"></i>
                            <span data-i18n="他部署の納期">他部署の納期</span>
                        </h5>
                    </div>
                    <div class="card-body other-dept-nouki-body">
                        <div v-for="sibling in otherDepartmentSiblingProjects" :key="sibling.id"
                             class="other-dept-nouki-item">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div class="min-w-0">
                                    <span class="badge border border-info bg-transparent text-info me-1">
                                        {{ sibling.department_name || '-' }}
                                    </span>
                                    <a :href="'detail.php?id=' + sibling.id" class="text-decoration-none fw-semibold">
                                        <span class="badge bg-primary me-1">#{{ sibling.id }}</span>
                                    </a>
                                    <span v-if="sibling.project_order_type"
                                          class="d-inline-flex flex-wrap align-items-center gap-1 ms-1">
                                        <span v-for="item in sibling.project_order_type.split(',')"
                                              :key="'ot-' + sibling.id + '-' + item.trim()"
                                              class="badge me-0"
                                              :class="getOrderTypeBadgeClass(item.trim())"
                                              v-show="item.trim()">{{ item.trim() }}</span>
                                    </span>
                                </div>
                                <span class="badge flex-shrink-0" :class="getStatusBadgeClass(sibling.status)">
                                    {{ getStatusLabel(sibling.status) }}
                                </span>
                            </div>
                            <div class="small">
                                <div class="d-flex flex-wrap align-items-center gap-1 mb-1">
                                    <span class="text-muted" data-i18n="CAILY納期">CAILY納期</span>
                                    <span v-if="sibling.caily_nouki"
                                          :data-time="sibling.caily_nouki"
                                          data-bs-toggle="tooltip"
                                          :data-bs-title="getVietnamTimeTooltip(sibling.caily_nouki)">
                                        {{ formatDateTime(sibling.caily_nouki) }}
                                    </span>
                                    <span v-else class="text-muted">-</span>
                                    <span v-if="isNoukiDelivered(sibling.caily_nouki_status)"
                                          class="badge bg-success"
                                          data-i18n="納品済み">納品済み</span>
                                    <span v-else-if="getSiblingDeadlineRemaining(sibling, sibling.caily_nouki)"
                                          class="badge"
                                          :class="getSiblingDeadlineRemaining(sibling, sibling.caily_nouki).class">
                                        {{ getSiblingDeadlineRemaining(sibling, sibling.caily_nouki).text }}
                                    </span>
                                </div>
                                <template v-if="canViewEndDate">
                                    <div class="d-flex flex-wrap align-items-center gap-1 mb-1">
                                        <span class="text-muted" data-i18n="GUIS納期">GUIS納期</span>
                                        <span v-if="sibling.guis_nouki"
                                              :data-time="sibling.guis_nouki"
                                              data-bs-toggle="tooltip"
                                              :data-bs-title="getVietnamTimeTooltip(sibling.guis_nouki)">
                                            {{ formatDateTime(sibling.guis_nouki) }}
                                        </span>
                                        <span v-else class="text-muted">-</span>
                                        <span v-if="isNoukiDelivered(sibling.guis_nouki_status)"
                                              class="badge bg-success"
                                              data-i18n="納品済み">納品済み</span>
                                        <span v-else-if="getSiblingDeadlineRemaining(sibling, sibling.guis_nouki)"
                                              class="badge"
                                              :class="getSiblingDeadlineRemaining(sibling, sibling.guis_nouki).class">
                                            {{ getSiblingDeadlineRemaining(sibling, sibling.guis_nouki).text }}
                                        </span>
                                    </div>
                                    <div class="d-flex flex-wrap align-items-center gap-1">
                                        <span class="text-muted" data-i18n="期限日(実納期)">期限日(実納期)</span>
                                        <span v-if="sibling.end_date"
                                              :data-time="sibling.end_date"
                                              data-bs-toggle="tooltip"
                                              :data-bs-title="getVietnamTimeTooltip(sibling.end_date)">
                                            {{ formatDateTime(sibling.end_date) }}
                                        </span>
                                        <span v-else class="text-muted">-</span>
                                        <span v-if="getSiblingDeadlineRemaining(sibling, sibling.end_date)"
                                              class="badge"
                                              :class="getSiblingDeadlineRemaining(sibling, sibling.end_date).class">
                                            {{ getSiblingDeadlineRemaining(sibling, sibling.end_date).text }}
                                        </span>
                                    </div>
                                </template>
                            </div>
                            <div v-if="isEnergyDepartmentProject && isEnergyDrawingShareSourceDept(sibling.department_name)"
                                 class="d-flex flex-wrap align-items-center gap-2 small mt-2 pt-2 border-top">
                                <span class="text-muted" data-i18n="共有状況">共有状況</span>
                                <span v-if="sibling.energy_drawing_share_status"
                                      class="badge"
                                      :class="getEnergyDrawingShareBadgeClass(sibling)">
                                    {{ getEnergyDrawingShareLabel(sibling) }}
                                </span>
                                <span v-else class="badge bg-secondary" data-i18n="未回答">未回答</span>
                                <span v-if="sibling.energy_drawing_share_at" class="text-muted">
                                    {{ formatDateTime(sibling.energy_drawing_share_at) }}
                                    <template v-if="sibling.energy_drawing_share_by">
                                        — {{ sibling.energy_drawing_share_by }}
                                    </template>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 省エネ図面共有: editable for 意匠/設備 (not for 省エネ itself) -->
                <div class="card mb-4" v-if="showEnergyDrawingShareEditBox">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fa fa-share-alt me-1"></i>
                            <span data-i18n="省エネへの図面共有">省エネへの図面共有</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label" data-i18n="共有状況">共有状況</label>
                                <select class="form-select"
                                        v-model="project.energy_drawing_share_status"
                                        :disabled="!canEditProject || savingEnergyDrawingShare"
                                        @change="onEnergyDrawingShareStatusChange">
                                    <option value="" data-i18n="未回答">未回答</option>
                                    <option value="shared" data-i18n="共有する">共有する</option>
                                    <option value="not_shared" data-i18n="共有しない">共有しない</option>
                                </select>
                            </div>
                            <div class="col-md-4" v-if="project.energy_drawing_share_status === 'not_shared'">
                                <label class="form-label" data-i18n="共有しない理由">共有しない理由</label>
                                <select class="form-select"
                                        v-model="project.energy_drawing_share_reason"
                                        :disabled="!canEditProject || savingEnergyDrawingShare"
                                        @change="onEnergyDrawingShareReasonChange">
                                    <option v-for="opt in energyDrawingShareReasonOptions"
                                            :key="opt.value"
                                            :value="opt.value">{{ getEnergyDrawingShareReasonLabel(opt.value) }}</option>
                                </select>
                            </div>
                            <div class="col-md-4" v-if="canEditProject">
                                <button type="button"
                                        class="btn btn-primary"
                                        :disabled="savingEnergyDrawingShare || !project.energy_drawing_share_status"
                                        @click="saveEnergyDrawingShare">
                                    <span v-if="savingEnergyDrawingShare" class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    <span data-i18n="保存">保存</span>
                                </button>
                            </div>
                        </div>
                        <div class="mt-3" v-if="project.energy_drawing_share_status === 'not_shared' && project.energy_drawing_share_reason === 'other'">
                            <label class="form-label" data-i18n="理由を入力してください">理由を入力してください</label>
                            <textarea class="form-control" rows="3"
                                      v-model="project.energy_drawing_share_note"
                                      :readonly="!canEditProject"
                                      :disabled="savingEnergyDrawingShare"></textarea>
                        </div>
                        <div class="mt-2 small text-muted" v-if="project.energy_drawing_share_at">
                            {{ formatDateTime(project.energy_drawing_share_at) }}
                            <template v-if="project.energy_drawing_share_by">
                                — {{ project.energy_drawing_share_by }}
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Quick Notes Section -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><span data-i18n="メモ">メモ</span></h5>
                        <button v-if="canAddNote" class="btn btn-primary btn-sm" @click="openNoteModal()" title="メモを追加">
                            <i class="fa fa-plus me-1"></i> <span data-i18n="メモを追加">メモを追加</span>
                        </button>
                    </div>
                    <div class="card-body">
                        <div v-if="notes.length > 0" class="list-group">
                            <div v-for="note in notes" :key="note.id" class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1" style="cursor: pointer;" @click="openNoteModal(note)">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <small class="text-secondary">
                                            {{ formatShortDateTime(note.created_at) }} - {{ note.realname || 'Unknown' }}
                                        </small>
                                        <div class="ms-2">
                                            <i v-if="note.is_important == 1" class="fa fa-exclamation-circle text-danger me-2"></i>
                                            <span v-if="note.needs_confirmation == 1" class="badge bg-primary me-1">CAILYメモ</span>
                                            <span v-else-if="note.needs_confirmation == 2" class="badge bg-dark me-1">GUISメモ</span>
                                            <span v-if="note.display_column" class="badge bg-secondary">関連項目: {{ getNoteDisplayColumnLabel(note.display_column) }}</span>
                                        </div>
                                    </div>
                                    <div v-if="note.content" class="text-muted small ql-editor" style="word-break: break-word;" v-html="decodeNoteHtml(note.content)"></div>
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
                            <p><span data-i18n="メモがありません">メモがありません</span></p>
                            <button v-if="canAddNote" class="btn btn-outline-primary btn-sm" @click="openNoteModal()">
                                <span data-i18n="最初のメモを追加">最初のメモを追加</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Lịch sử hành động (履歴) -->
                <div class="card mb-4">
                    <div class="card-header d-flex align-items-center">
                        <h5 class="card-title mb-0"><span data-i18n="履歴">履歴</span></h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group" style="max-height: 400px; overflow-y: auto;">
                            <li v-for="log in sortedGeneralLogs" :key="log.id" class="list-group-item">
                                <div class="d-flex">
                                    <div class="d-flex flex-row align-items-start justify-content-start me-3" style="min-width:130px;">
                                        <div class="d-flex flex-column align-items-center justify-content-start" style="width:40px;">
                                            <div class="avatar">
                                                <span v-if="showAvatarInitials(log)" class="avatar-initial rounded-circle bg-label-primary">
                                                    {{ getInitials(log.username || log.realname || '?', log.userid || log.user_id || '', log.user_ruby || '') }}
                                                </span>
                                                <img v-if="!log.avatarError && getAvatarSrc(log)"
                                                    :src="getAvatarSrc(log)"
                                                    alt="avatar"
                                                    class="rounded-circle"
                                                    :class="{ 'd-none': !log.avatarLoaded }"
                                                    @load="handleAvatarLoad(log)"
                                                    @error="handleAvatarError(log)">
                                            </div>
                                        </div>
                                        <div class="d-flex flex-column align-items-start justify-content-center ms-2">
                                            <span class="fw-bold small">{{ getBdLogDisplayName(log) }}</span>
                                            <span class="text-muted small">{{ formatShortDateTime(log.time) }}</span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 d-flex align-items-center">
                                        <span>
                                            <i :class="historyIcon(log.action) + ' me-2'"></i>
                                            <span class="me-2">{{ getLogNote(log) }}</span>
                                            <br>
                                            <span v-if="log.value1" :class="getLogBadgeClass(log, 'value1')" class="mx-1">{{ getLogBadgeLabel(log, 'value1') }}</span>
                                            <span v-if="log.value1 && log.value2" class="mx-1">→</span>
                                            <span v-if="log.value2" :class="getLogBadgeClass(log, 'value2')" class="mx-1">{{ getLogBadgeLabel(log, 'value2') }}</span>
                                        </span>
                                    </div>
                                </div>
                            </li>
                            <li v-if="!sortedGeneralLogs || sortedGeneralLogs.length === 0" class="list-group-item text-muted">履歴はありません。</li>
                        </ul>
                    </div>
                </div>

                <!-- Statistics Cards -->
               <div class="row g-3 mb-4">
                    <div class="col-4">
                        <div class="card bg-primary text-white text-center">
                            <div class="card-body">
                                <div class="mb-1">
                                    <i class="fa fa-list-alt fs-3"></i>
                                </div>
                                <h2 class="mb-1 text-white">{{ stats.totalTasks }}</h2>
                                <small><span data-i18n="タスク総数">タスク総数</span></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card bg-info text-white text-center">
                            <div class="card-body">
                                <div class="mb-1">
                                    <i class="fa fa-clock fs-3"></i>
                                </div>
                                <h2 class="mb-1 text-white fs-3">{{ formatTotalWorkload(stats.totalWorkload) }}</h2>
                                <small><span data-i18n="工数合計">工数合計</span></small>
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
                    <div class="col-4">
                        <div class="card bg-warning text-white text-center">
                            <div class="card-body">
                                <div class="mb-1">
                                    <i class="fa fa-calendar fs-3"></i>
                                </div>
                                <h2 class="mb-1 text-white">{{ stats.totalDays }}</h2>
                                <small><span data-i18n="日数">日数</span></small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4" v-if="workloadByKind.length">
                    <div class="card-header pb-0">
                        <h5 class="card-title mb-0"><span data-i18n="種別別工数">種別別工数</span></h5>
                    </div>
                    <div class="card-body py-2 px-3">
                        <div v-for="(item, idx) in workloadByKind" :key="item.kind"
                             class="d-flex justify-content-between align-items-center py-2"
                             :class="{ 'border-bottom': idx < workloadByKind.length - 1 }">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge" :class="getTaskKindBadgeClass(item.kind)">{{ getTaskKindLabel(item.kind) }}</span>
                            </div>
                            <span class="fw-semibold text-nowrap">{{ formatTotalWorkload(item.hours) }}</span>
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
                                <div @click="toggleMemberSelect(user.userid)" :class="{'border border-primary': memberSelected.includes(user.userid)}" class="avatar avatar-md" style="display:inline-block;padding:2px;">
                                    <span v-if="showAvatarInitials(user)" class="avatar-initial rounded-circle bg-label-primary">{{ getInitials(user) }}</span>
                                    <img v-if="!user.avatarError && getAvatarSrc(user)"
                                        class="rounded-circle"
                                        :class="{ 'd-none': !user.avatarLoaded }"
                                        :src="getAvatarSrc(user)"
                                        :alt="user.user_name"
                                        @load="handleAvatarLoad(user)"
                                        @error="handleAvatarError(user)">
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

        <!-- Modal Business Document History -->
        <div class="modal fade" tabindex="-1" :class="{show: showBusinessDocumentLogModal}" style="display: block;" v-if="showBusinessDocumentLogModal">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">決済情報 履歴</h5>
                        <button type="button" class="btn-close" @click="closeBusinessDocumentLogModal"></button>
                    </div>
                    <div class="modal-body p-0">
                        <ul class="list-group list-group-flush">
                            <li v-for="log in sortedBusinessDocumentLogs" :key="log.id" class="list-group-item">
                                <div class="d-flex">
                                    <div class="d-flex flex-row align-items-start justify-content-start me-3" style="min-width:130px;">
                                        <div class="d-flex flex-column align-items-center justify-content-start" style="width:40px;">
                                            <div class="avatar">
                                                <span v-if="showAvatarInitials(log)" class="avatar-initial rounded-circle bg-label-primary">
                                                    {{ getInitials(log.username || log.realname || '?', log.userid || log.user_id || '', log.user_ruby || '') }}
                                                </span>
                                                <img v-if="!log.avatarError && getAvatarSrc(log)"
                                                    :src="getAvatarSrc(log)"
                                                    alt="avatar"
                                                    class="rounded-circle"
                                                    :class="{ 'd-none': !log.avatarLoaded }"
                                                    @load="handleAvatarLoad(log)"
                                                    @error="handleAvatarError(log)">
                                            </div>
                                        </div>
                                        <div class="d-flex flex-column align-items-start justify-content-center ms-2">
                                            <span class="fw-bold small">{{ getBdLogDisplayName(log) }}</span>
                                            <span class="text-muted small">{{ formatShortDateTime(log.time) }}</span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 d-flex align-items-center">
                                        <span>
                                            <i :class="historyIcon(log.action) + ' me-2'"></i>
                                            <span class="me-2">{{ getLogNote(log) }}</span>
                                            <br>
                                            <span v-if="hasLogValue(log.value1)" :class="getLogBadgeClass(log, 'value1')" class="mx-1">{{ getBusinessDocumentLogValue(log, 'value1') }}</span>
                                            <span v-if="hasLogValue(log.value1) && hasLogValue(log.value2)" class="mx-1">→</span>
                                            <span v-if="hasLogValue(log.value2)" :class="getLogBadgeClass(log, 'value2')" class="mx-1">{{ getBusinessDocumentLogValue(log, 'value2') }}</span>
                                        </span>
                                    </div>
                                </div>
                            </li>
                            <li v-if="!sortedBusinessDocumentLogs || sortedBusinessDocumentLogs.length === 0" class="list-group-item text-muted">決済情報の履歴はありません。</li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" @click="closeBusinessDocumentLogModal">閉じる</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show" v-if="showBusinessDocumentLogModal"></div>

        <!-- Modal Note -->
        <div class="modal fade" tabindex="-1" :class="{show: showNoteModal}" style="display: block;" v-if="showNoteModal">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"></h5>
                        <button type="button" class="btn-close" @click="closeNoteModal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- View mode -->
                        <div v-if="editingNote.id && !isNoteEditMode">
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="内容">内容</span></label>
                                <div class="form-control ql-editor" style="min-height:100px;max-height:300px;overflow-y:auto;" v-html="editingNote.content || '-'"></div>
                            </div>
                            <div class="mb-3" v-if="editingNote.is_important">
                                <label class="form-label"><span data-i18n="重要メモ">重要メモ</span></label>
                                <div>
                                    <i class="fa fa-exclamation-circle text-danger"></i>
                                </div>
                            </div>
                            <div class="mb-3" v-if="editingNote.needs_confirmation">
                                <label class="form-label"><span>区分</span></label>
                                <div>
                                    <span class="badge bg-primary" v-if="String(editingNote.needs_confirmation) === '1'">CAILYメモ</span>
                                    <span class="badge bg-dark" v-else-if="String(editingNote.needs_confirmation) === '2'">GUISメモ</span>
                                    <span class="badge bg-primary" v-else>確認必要</span>
                                </div>
                            </div>
                            <div class="mb-3" v-if="editingNote.display_column">
                                <label class="form-label"><span>表示列</span></label>
                                <div>{{ getNoteDisplayColumnLabel(editingNote.display_column) }}</div>
                            </div>
                        </div>
                        <!-- Edit mode -->
                        <form v-else @submit.prevent="saveNote">
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="内容">内容</span></label>
                                <div class="custom_editor">
                                    <div class="custom_editor_content" id="quill_note_content_detail"></div>
                                    <textarea class="custom_editor_textarea d-none" v-model="editingNote.content" id="quill_note_content_detail_textarea"></textarea>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" v-model="editingNote.is_important" id="isImportant">
                                    <label class="form-check-label" for="isImportant">
                                        <i class="fa fa-exclamation-circle text-danger me-2"></i> <span data-i18n="重要メモ">重要メモ</span>
                                    </label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label d-block"><span>区分</span></label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" :value="1" v-model="editingNote.needs_confirmation" id="needsConfirmationCaily">
                                    <label class="form-check-label" for="needsConfirmationCaily">
                                        CAILYメモ
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" :value="2" v-model="editingNote.needs_confirmation" id="needsConfirmationGuis">
                                    <label class="form-check-label" for="needsConfirmationGuis">
                                        GUISメモ
                                    </label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><span>表示列</span></label>
                                <select class="form-select" v-model="editingNote.display_column">
                                    <option value="">— 選択 —</option>
                                    <option v-for="opt in noteDisplayColumnOptions" :key="opt.value" :value="opt.value">{{ opt.text }}</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <template v-if="editingNote.id && !isNoteEditMode">
                            <button class="btn btn-primary" @click="isNoteEditMode = true; $nextTick(() => initQuillNoteEditor())" v-if="canEditNote(editingNote)"><i class="fa fa-pencil-alt me-2"></i> <span data-i18n="編集">編集</span></button>
                            <button class="btn btn-secondary" @click="closeNoteModal"><span data-i18n="閉じる">閉じる</span></button>
                        </template>
                        <template v-else>
                            <button class="btn btn-secondary" @click="isNoteEditMode = false" v-if="editingNote.id"><i class="fa fa-times me-2"></i> <span data-i18n="キャンセル">キャンセル</span></button>
                            <button class="btn btn-secondary" @click="closeNoteModal" v-else><span data-i18n="キャンセル">キャンセル</span></button>
                            <button class="btn btn-primary" @click="saveNote" :disabled="!((quillNoteContent && quillNoteContent.trim()) || (editingNote.content && editingNote.content.trim()))">
                                <i class="fa fa-save me-2"></i> <span data-i18n="保存">保存</span>
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

.other-dept-nouki-body {
    display: flex;
    flex-direction: column;
    background: #f8f9fa;
}

.other-dept-nouki-item {
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    background: #fff;
    padding: 0.85rem 1rem;
    margin-top: 0.75rem;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}

.other-dept-nouki-item:hover {
    border-color: #adb5bd;
}

/* Note content styling (now show full content in list) */
.list-group-item .ql-editor {
    padding: 0;
    font-size: 0.875rem;
    line-height: 1.4;
}

.list-group-item .ql-editor p {
    margin: 0 0 4px 0;
}

.list-group-item .ql-editor p:last-child {
    margin-bottom: 0;
}

.list-group-item .ql-editor ul,
.list-group-item .ql-editor ol {
    margin: 4px 0;
    padding-left: 20px;
}

.list-group-item .ql-editor strong {
    font-weight: 600;
}

.list-group-item .ql-editor em {
    font-style: italic;
}

.list-group-item .ql-editor u {
    text-decoration: underline;
}

.list-group-item .ql-editor a {
    color: #0d6efd;
    text-decoration: underline;
}

.list-group-item .ql-editor blockquote {
    border-left: 3px solid #ddd;
    padding-left: 10px;
    margin: 4px 0;
    color: #666;
    font-style: italic;
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

/* Kadai project confirmation button styling */
.btn-success[title="プロジェクトを承認"] {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
    box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
    transition: all 0.3s ease;
}

.btn-success[title="プロジェクトを承認"]:hover {
    background: linear-gradient(135deg, #218838 0%, #1ba085 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(40, 167, 69, 0.4);
}

/* Kadai status badge styling */
.badge.bg-warning {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%) !important;
    border: 1px solid rgba(255, 193, 7, 0.3);
    box-shadow: 0 2px 4px rgba(255, 193, 7, 0.2);
}

</style>

<!-- Define PROJECT_ID and chat page context (for AI: default project_id)
     D5: Vue from header only. D6: Quill/mention/comment lazy via project-detail.js. D7: no task-manager.css. -->
<script>
const PROJECT_ID = <?php echo $project_id; ?>;
window.IS_CAILY_BRANCH_USER = <?php echo $isCailyBranchUser ? 'true' : 'false'; ?>;
window.__chatPageContext = { project_id: PROJECT_ID };
window.__PROJECT_CACHE_VERSION = <?= json_encode(PROJECT_CACHE_VERSION) ?>;
window.__CACHE_VERSION = <?= json_encode(CACHE_VERSION) ?>;
</script>
<script src="<?=ROOT?>assets/js/sw-manager.js?v=<?=CACHE_VERSION?>"></script>
<script src="assets/js/project-clipboard.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="assets/js/yotei-field.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="assets/js/energy-drawing-share.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="assets/js/project-detail.js?v=<?=PROJECT_CACHE_VERSION?>"></script>

