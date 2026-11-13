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
            <div class="col-12 mb-3 d-flex flex-wrap align-items-center gap-2">
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="fa fa-arrow-left me-2"></i><span data-i18n="案件一覧へ戻る">案件一覧へ戻る</span>
                </a>
                <?php
                // 建物一覧 › 建物 › 案件: replaces the 建物詳細 button and the お施主様名 / 工事番号 box
                $breadcrumbVar = 'project';
                $breadcrumbLeaf = '';
                require __DIR__ . '/partials/project-breadcrumb.php';
                ?>
            </div>

            <!-- Left Column - Project Details -->
            <div class="col-xl-8">
                <!-- The page itself is read-only: 編集 opens the shared form dialog
                     (partials/project-form-modal.php), which renders the same field markup. -->
                <div class="card project-detail-card" :class="{ 'edit-mode': isEditMode }">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4 project-detail-card-header">
                            <h5 class="card-title">
                                <i class="fa fa-star me-2" 
                                   :class="project && project.is_favorite == 1 ? 'text-warning' : 'text-muted'"
                                   style="cursor: pointer; font-size: 1.2em;"
                                   @click="toggleFavorite"
                                   :title="project && project.is_favorite == 1 ? 'お気に入りから削除' : 'お気に入りに追加'"></i>
                                <span v-show="!isEditMode" data-i18n="案件詳細">案件詳細</span>
                                <span v-show="isEditMode" data-i18n="案件を編集">案件を編集</span>
                                <span v-if="project && project.department_name" class="badge border border-info bg-transparent text-info ms-2">{{ project.department_name }}</span>
                                <span v-if="isPeriodUndecided" class="badge bg-label-warning ms-2">{{ translateLabel('期間未定') }}</span>
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
                                <button v-if="!isEditMode && canEditProject && !(project && project.is_kadai == 1)" class="btn btn-outline-warning btn-sm me-2" @click="openEditForm" title="編集">
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


                        
                        <?php require __DIR__ . '/partials/project-form-grid.php'; ?>
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

                        <template v-if="isEnergyDepartmentProject">
                            <h6 class="text-muted mb-3"><span data-i18n="見積">見積</span></h6>
                            <div v-for="row in energyEstimateFeeRows" :key="'est-' + row.fee" class="border rounded p-3 mb-3">
                                <div class="fw-semibold mb-2"><span :data-i18n="row.title">{{ row.title }}</span></div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label mb-0">{{ row.dateLabel }} <span v-if="canEditBusinessDocuments" class="text-danger">*</span></label>
                                            <button v-if="canEditBusinessDocuments && !hasBusinessDocumentDate(row.dateKey)" type="button" class="btn btn-outline-primary btn-sm py-0 px-2"
                                                    @click="setBusinessDocumentDateToday(row.dateKey)">今日</button>
                                        </div>
                                        <input v-if="canEditBusinessDocuments" type="text" class="form-control" v-model="project[row.dateKey]" :id="businessDocumentPickerId(row.dateKey)" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off">
                                        <input v-else type="text" class="form-control" :value="formatBusinessDocumentDateTime(row.dateKey)" :data-time="getBusinessDocumentDateForTooltip(row.dateKey)" data-bs-toggle="tooltip" :data-bs-title="getVietnamTimeTooltip(getBusinessDocumentDateForTooltip(row.dateKey))" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">{{ row.amountLabel }} <span v-if="canEditBusinessDocuments" class="text-danger">*</span></label>
                                        <input type="number" autocomplete="off" class="form-control" v-model.number="project[row.amountKey]" :readonly="!canEditBusinessDocuments" placeholder="未入力" data-i18n="未入力" @input="scheduleBusinessDocumentUpdate" min="0" step="1">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">状況</label>
                                        <div v-if="canEditBusinessDocuments">
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light"
                                                        :class="getEnergyFeeStatusButtonClass(row.kind, project[row.statusKey])"
                                                        :id="row.dropdownId"
                                                        data-bs-toggle="dropdown" aria-expanded="false">
                                                    {{ getEnergyFeeStatusLabel(row.kind, project[row.statusKey]) }}
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li v-for="status in getEnergyFeeStatusOptions(row.kind)" :key="status.value">
                                                        <a class="dropdown-item waves-effect" href="javascript:void(0);"
                                                           :class="{ disabled: status.value === '発行済' && !isEnergyFeeRowComplete(row) }"
                                                           @click="selectEnergyFeeStatus(row, status.value)">
                                                            {{ status.label }}
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                            <div v-if="shouldShowEnergyFeeIssuedHint(row)" class="form-text text-muted">
                                                発行済にするには{{ row.dateLabel }}・{{ row.amountLabel }}が必要です
                                            </div>
                                        </div>
                                        <div v-else>
                                            <span class="badge" :class="getEnergyFeeStatusBadgeClass(row.kind, project[row.statusKey])">
                                                {{ getEnergyFeeStatusLabel(row.kind, project[row.statusKey]) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-3">

                            <h6 class="text-muted mb-3"><span data-i18n="請求">請求</span></h6>
                            <div v-for="row in energyInvoiceFeeRows" :key="'inv-' + row.fee" class="border rounded p-3 mb-3">
                                <div class="fw-semibold mb-2"><span :data-i18n="row.title">{{ row.title }}</span></div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label mb-0">{{ row.dateLabel }} <span v-if="canEditBusinessDocuments" class="text-danger">*</span></label>
                                            <button v-if="canEditBusinessDocuments && !hasBusinessDocumentDate(row.dateKey)" type="button" class="btn btn-outline-primary btn-sm py-0 px-2"
                                                    @click="setBusinessDocumentDateToday(row.dateKey)">今日</button>
                                        </div>
                                        <input v-if="canEditBusinessDocuments" type="text" class="form-control" v-model="project[row.dateKey]" :id="businessDocumentPickerId(row.dateKey)" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off">
                                        <input v-else type="text" class="form-control" :value="formatBusinessDocumentDateTime(row.dateKey)" :data-time="getBusinessDocumentDateForTooltip(row.dateKey)" data-bs-toggle="tooltip" :data-bs-title="getVietnamTimeTooltip(getBusinessDocumentDateForTooltip(row.dateKey))" readonly>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex justify-content-between align-items-center mb-1 flex-nowrap gap-1">
                                            <label class="form-label mb-0 text-nowrap">{{ row.amountLabel }} <span v-if="canEditBusinessDocuments" class="text-danger">*</span></label>
                                            <button v-if="canEditBusinessDocuments && canCopyEnergyFeeAmountToInvoice(row)" type="button" class="btn btn-outline-primary btn-sm py-0 px-2 text-nowrap flex-shrink-0"
                                                    @click="copyEnergyFeeAmountToInvoice(row)">見積と同額</button>
                                        </div>
                                        <input type="number" autocomplete="off" class="form-control" v-model.number="project[row.amountKey]" :readonly="!canEditBusinessDocuments" placeholder="未入力" data-i18n="未入力" @input="scheduleBusinessDocumentUpdate" min="0" step="1">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">状況</label>
                                        <div v-if="canEditBusinessDocuments">
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light"
                                                        :class="getEnergyFeeStatusButtonClass(row.kind, project[row.statusKey])"
                                                        :id="row.dropdownId"
                                                        data-bs-toggle="dropdown" aria-expanded="false">
                                                    {{ getEnergyFeeStatusLabel(row.kind, project[row.statusKey]) }}
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li v-for="status in getEnergyFeeStatusOptions(row.kind)" :key="status.value">
                                                        <a class="dropdown-item waves-effect" href="javascript:void(0);"
                                                           :class="{ disabled: status.value === '発行済' && !isEnergyFeeRowComplete(row) }"
                                                           @click="selectEnergyFeeStatus(row, status.value)">
                                                            {{ status.label }}
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                            <div v-if="shouldShowEnergyFeeIssuedHint(row)" class="form-text text-muted">
                                                発行済にするには{{ row.dateLabel }}・{{ row.amountLabel }}が必要です
                                            </div>
                                        </div>
                                        <div v-else>
                                            <span class="badge" :class="getEnergyFeeStatusBadgeClass(row.kind, project[row.statusKey])">
                                                {{ getEnergyFeeStatusLabel(row.kind, project[row.statusKey]) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template v-else>
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
                                    <input type="number" autocomplete="off" class="form-control" v-model.number="project.amount" :readonly="!canEditBusinessDocuments" placeholder="未入力" data-i18n="未入力" @input="scheduleBusinessDocumentUpdate" min="0" step="1">
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
                                        <div v-if="shouldShowEstimateIssuedHint()" class="form-text text-muted">
                                            発行済にするには見積日・見積金額が必要です
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
                                    <div class="d-flex justify-content-between align-items-center mb-1 flex-nowrap gap-1">
                                        <label class="form-label mb-0 text-nowrap">請求金額(税抜き) <span v-if="canEditBusinessDocuments" class="text-danger">*</span></label>
                                        <button v-if="canEditBusinessDocuments && canCopyEstimateAmountToInvoice()" type="button" class="btn btn-outline-primary btn-sm py-0 px-2 text-nowrap flex-shrink-0"
                                                @click="copyEstimateAmountToInvoice">見積と同額</button>
                                    </div>
                                    <input type="number" autocomplete="off" class="form-control" v-model.number="project.invoice_amount" :readonly="!canEditBusinessDocuments" placeholder="未入力" data-i18n="未入力" @input="scheduleBusinessDocumentUpdate" min="0" step="1">
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
                                        <div v-if="shouldShowInvoiceIssuedHint()" class="form-text text-muted">
                                            発行済にするには請求日・請求金額が必要です
                                        </div>
                                    </div>
                                    <div v-else>
                                        <span class="badge" :class="getInvoiceStatusBadgeClass(project.invoice_status)">
                                            {{ getInvoiceStatusLabel(project.invoice_status) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </template>

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
                                <!-- 省エネ departments do not use CAILY納期 / GUIS納期 -->
                                <div v-if="!isEnergyDepartmentRow(sibling)" class="d-flex flex-wrap align-items-center gap-1 mb-1">
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
                                    <span v-else-if="getSiblingDeadlineRemaining(sibling, sibling.caily_nouki, isNoukiDelivered(sibling.caily_nouki_status))"
                                          class="badge"
                                          :class="getSiblingDeadlineRemaining(sibling, sibling.caily_nouki, isNoukiDelivered(sibling.caily_nouki_status)).class">
                                        {{ getSiblingDeadlineRemaining(sibling, sibling.caily_nouki, isNoukiDelivered(sibling.caily_nouki_status)).text }}
                                    </span>
                                </div>
                                <template v-if="canViewEndDate">
                                    <div v-if="!isEnergyDepartmentRow(sibling)" class="d-flex flex-wrap align-items-center gap-1 mb-1">
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
                                        <span v-else-if="getSiblingDeadlineRemaining(sibling, sibling.guis_nouki, isNoukiDelivered(sibling.guis_nouki_status))"
                                              class="badge"
                                              :class="getSiblingDeadlineRemaining(sibling, sibling.guis_nouki, isNoukiDelivered(sibling.guis_nouki_status)).class">
                                            {{ getSiblingDeadlineRemaining(sibling, sibling.guis_nouki, isNoukiDelivered(sibling.guis_nouki_status)).text }}
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
                                        <span v-if="getSiblingDeadlineRemaining(sibling, sibling.end_date, isRowNoukiDelivered(sibling))"
                                              class="badge"
                                              :class="getSiblingDeadlineRemaining(sibling, sibling.end_date, isRowNoukiDelivered(sibling)).class">
                                            {{ getSiblingDeadlineRemaining(sibling, sibling.end_date, isRowNoukiDelivered(sibling)).text }}
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
                        <div v-if="notes.length > 0" class="row g-2 mb-3 align-items-end note-list-filters">
                            <div class="col-sm-6 col-md-4">
                                <label class="form-label small mb-1">ラベル</label>
                                <select class="form-select form-select-sm" v-model="noteFilterLabel">
                                    <option value="">すべて</option>
                                    <option value="__none__">ラベルなし</option>
                                    <option v-for="lbl in noteFilterLabelOptions" :key="lbl" :value="lbl">{{ lbl }}</option>
                                </select>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <label class="form-label small mb-1">区分</label>
                                <select class="form-select form-select-sm" v-model="noteFilterType">
                                    <option value="">すべて</option>
                                    <option value="1">{{ cailyNoteLabel }}</option>
                                    <option value="2">GUISメモ</option>
                                    <option value="0">未分類</option>
                                </select>
                            </div>
                            <div class="col-sm-12 col-md-4 d-flex align-items-center gap-2">
                                <span class="small text-muted">{{ filteredNotes.length }} / {{ notes.length }}</span>
                                <button v-if="noteFilterLabel || noteFilterType !== ''"
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        @click="clearNoteFilters">
                                    クリア
                                </button>
                            </div>
                        </div>
                        <div v-if="notes.length > 0 && filteredNotes.length > 0"
                             ref="detailNotesList"
                             class="list-group detail-notes-list"
                             :class="{ 'note-filters-active': !!(noteFilterLabel || noteFilterType !== '') }">
                            <div v-for="note in filteredNotes"
                                 :key="note.id"
                                 class="list-group-item detail-note-item"
                                 :class="{
                                     'important-note': note.is_important == 1,
                                     'pinned-self-note': note.is_pinned_self == 1
                                 }"
                                 :data-note-id="note.id">
                                <span class="note-drag-handle"
                                      title="並べ替え"
                                      aria-label="並べ替え"
                                      @click.stop
                                      @mousedown.stop>
                                    <i class="fa fa-grip-vertical"></i>
                                </span>
                                <div class="note-main" style="cursor: pointer;" @click="openNoteModal(note)">
                                    <div class="d-flex align-items-center mb-1 flex-wrap gap-1">
                                        <i v-if="note.is_important == 1" class="fa fa-thumb-tack text-danger" title="ピン（全員）"></i>
                                        <i v-if="note.is_pinned_self == 1" class="fa fa-bookmark text-primary" title="ピン（自分）"></i>
                                        <small class="text-secondary">
                                            {{ formatShortDateTime(note.created_at) }} - {{ note.realname || 'Unknown' }}
                                        </small>
                                    </div>
                                    <div v-if="note.label || note.needs_confirmation == 1 || note.needs_confirmation == 2 || note.display_column"
                                         class="d-flex flex-wrap align-items-center gap-1 mb-2">
                                        <span v-if="note.label" class="note-label-chip" :style="getNoteLabelStyle(note.label)"><i class="fa fa-tag note-label-chip-icon" aria-hidden="true"></i><span class="note-label-chip-text">{{ note.label }}</span></span>
                                        <span v-if="note.needs_confirmation == 1" class="badge bg-primary">{{ cailyNoteLabel }}</span>
                                        <span v-else-if="note.needs_confirmation == 2" class="badge bg-dark">GUISメモ</span>
                                        <span v-if="note.display_column" class="badge bg-secondary">関連項目: {{ getNoteDisplayColumnLabel(note.display_column) }}</span>
                                    </div>
                                    <div v-if="note.content" class="text-muted small ql-editor detail-note-content" style="word-break: break-word;" v-html="decodeNoteHtml(note.content)"></div>
                                </div>
                                <div class="detail-note-trailing" @click.stop>
                                    <span class="note-actions">
                                        <span class="note-pin-everyone-icon"
                                              :class="{ 'is-active': note.is_important == 1 }"
                                              :title="note.is_important == 1 ? 'ピン（全員）解除' : 'ピン（全員）'"
                                              @click="toggleNotePin(note, 'everyone')">
                                            <i class="fa fa-thumb-tack"></i>
                                        </span>
                                        <span class="note-pin-self-icon"
                                              :class="{ 'is-active': note.is_pinned_self == 1 }"
                                              :title="note.is_pinned_self == 1 ? 'ピン（自分）解除' : 'ピン（自分）'"
                                              @click="toggleNotePin(note, 'self')">
                                            <i class="fa fa-bookmark"></i>
                                        </span>
                                    </span>
                                    <button class="btn btn-outline-danger btn-sm detail-note-delete"
                                            @click="deleteNote(note.id)"
                                            title="削除"
                                            v-if="canDeleteNote(note)">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-else-if="notes.length > 0" class="text-center text-muted py-3">
                            <i class="fa fa-filter fa-2x mb-2"></i>
                            <p class="mb-2">条件に一致するメモがありません</p>
                            <button type="button" class="btn btn-outline-secondary btn-sm" @click="clearNoteFilters">フィルターをクリア</button>
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
                            <div class="mb-3" v-if="editingNote.is_important || editingNote.is_pinned_self">
                                <label class="form-label"><span data-i18n="ピン">ピン</span></label>
                                <div class="d-flex flex-wrap gap-2">
                                    <span v-if="editingNote.is_important" class="badge bg-danger"><i class="fa fa-thumb-tack me-1"></i><span data-i18n="ピン（全員）">ピン（全員）</span></span>
                                    <span v-if="editingNote.is_pinned_self" class="badge bg-primary"><i class="fa fa-bookmark me-1"></i><span data-i18n="ピン（自分）">ピン（自分）</span></span>
                                </div>
                            </div>
                            <div class="mb-3" v-if="editingNote.needs_confirmation">
                                <label class="form-label"><span>区分</span></label>
                                <div>
                                    <span class="badge bg-primary" v-if="String(editingNote.needs_confirmation) === '1'">{{ cailyNoteLabel }}</span>
                                    <span class="badge bg-dark" v-else-if="String(editingNote.needs_confirmation) === '2'">GUISメモ</span>
                                    <span class="badge bg-primary" v-else>確認必要</span>
                                </div>
                            </div>
                            <div class="mb-3" v-if="editingNote.label">
                                <label class="form-label"><span>ラベル</span></label>
                                <div>
                                    <span class="note-label-chip" :style="getNoteLabelStyle(editingNote.label)"><i class="fa fa-tag note-label-chip-icon" aria-hidden="true"></i><span class="note-label-chip-text">{{ editingNote.label }}</span></span>
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
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" v-model="editingNote.is_important" id="isImportant" @change="onNotePinEveryoneChange">
                                    <label class="form-check-label" for="isImportant">
                                        <i class="fa fa-thumb-tack text-danger me-2"></i> <span data-i18n="ピン（全員）">ピン（全員）</span>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" v-model="editingNote.is_pinned_self" id="isPinnedSelf" @change="onNotePinSelfChange">
                                    <label class="form-check-label" for="isPinnedSelf">
                                        <i class="fa fa-bookmark text-primary me-2"></i> <span data-i18n="ピン（自分）">ピン（自分）</span>
                                    </label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><span>ラベル</span></label>
                                <select class="form-select" v-model="editingNote.labelSelect" @change="onNoteLabelSelectChange">
                                    <option value="">— 選択 —</option>
                                    <option v-for="lbl in noteLabelSelectOptions" :key="lbl.name" :value="lbl.name">{{ lbl.name }}</option>
                                    <option :value="NOTE_LABEL_OTHER">その他</option>
                                </select>
                                <input v-if="editingNote.labelSelect === NOTE_LABEL_OTHER"
                                       type="text"
                                       class="form-control mt-2"
                                       v-model="editingNote.labelOther"
                                       maxlength="100"
                                       placeholder="ラベルを入力">
                            </div>
                            <div class="mb-3">
                                <label class="form-label d-block"><span>区分</span></label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" :value="1" v-model="editingNote.needs_confirmation" id="needsConfirmationCaily">
                                    <label class="form-check-label" for="needsConfirmationCaily">
                                        {{ cailyNoteLabel }}
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
// 建物情報 modal (shared standalone app, outside #app)
include dirname(__DIR__) . '/parent_project/partials/parent-project-quick-modal.php';
// 案件 edit form (shared standalone app, outside #app)
include __DIR__ . '/partials/project-form-modal.php';
$view->footing();
?>

<style>
/* Project detail card: fields grouped under section titles (same grouping as the project form modals) */
.project-detail-grid .project-form-section {
    margin-top: 1.75rem;
}
.project-detail-grid .project-form-section-title {
    margin: 0;
    padding-bottom: 0.4rem;
    font-size: 0.8125rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    color: var(--bs-primary);
    border-bottom: 1px solid var(--bs-border-color);
}
/* A read-only value is plain text. No box, no fill: a tinted box still reads as a
   disabled input, i.e. a form the user is locked out of. */
#app .card input.form-control[readonly]:not(.modal *),
#app .card textarea.form-control[readonly]:not(.modal *),
#app .card div.form-control:not(.modal *) {
    background-color: transparent !important;
    border: 0 !important;
    border-radius: 0;
    box-shadow: none !important;
    padding-left: 0;
    padding-right: 0;
    font-weight: 600;
    color: var(--bs-heading-color, inherit);
    cursor: default;
    text-overflow: ellipsis;
}
#app .card textarea.form-control[readonly]:not(.modal *) {
    resize: none;
}

/* ---- Page (read-only): one table per section, caption left, value right ----
   The markup is the same grid the edit form uses (a .pd-section per titled section, one
   field = one .col-* holding a .form-label followed by its value); only the layout
   differs, so the page and the form cannot drift apart. */
.project-detail-card:not(.edit-mode) > .card-body {
    container-type: inline-size;
}
.project-detail-card:not(.edit-mode) .project-detail-grid {
    --bs-gutter-x: 0;
    --bs-gutter-y: 0;
    --pd-caption-width: 9.5rem;
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 0 1.25rem;
    align-items: start;
    margin: 0;
}
/* Two fixed columns (.pd-col) when the card is wide enough: 基本情報 + スケジュール on the
   left, the rest on the right. The edit dialog uses the same two columns (demo.css), so a
   table is found in the same place on the page and in the form. */
@container (min-width: 760px) {
    .project-detail-card:not(.edit-mode) .project-detail-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
/* What comes before the first section (建物情報 button, customer box) spans the card */
.project-detail-card:not(.edit-mode) .project-detail-grid > [class*="col-"] {
    grid-column: 1 / -1;
    width: 100%;
    max-width: 100%;
    margin: 0 0 0.75rem !important;
    padding: 0;
}
.project-detail-card:not(.edit-mode) .pd-section {
    margin-bottom: 1rem;
    min-width: 0;
    border: 1px solid var(--bs-border-color);
    border-radius: var(--bs-border-radius);
}
.project-detail-card:not(.edit-mode) .pd-section > .project-form-section {
    width: 100%;
    margin: 0;
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid var(--bs-border-color);
    border-radius: var(--bs-border-radius) var(--bs-border-radius) 0 0;
    background: color-mix(in srgb, var(--bs-primary) 7%, transparent);
}
.project-detail-card:not(.edit-mode) .pd-section > .project-form-section .project-form-section-title {
    padding-bottom: 0;
    border-bottom: 0;
}
/* a field row */
.project-detail-card:not(.edit-mode) .pd-section > [class*="col-"]:not(.project-form-section),
.project-detail-card:not(.edit-mode) .pd-section .row > [class*="col-"] {
    flex: 0 0 100%;
    width: 100%;
    max-width: 100%;
    display: grid;
    grid-template-columns: var(--pd-caption-width) minmax(0, 1fr);
    column-gap: 0.75rem;
    align-items: start;
    margin: 0 !important;
    padding: 0.45rem 0.75rem;
    border-bottom: 1px solid color-mix(in srgb, currentColor 12%, transparent);
}
.project-detail-card:not(.edit-mode) .pd-section > [class*="col-"]:last-child,
.project-detail-card:not(.edit-mode) .pd-section > [class*="col-"]:last-child .row > [class*="col-"]:last-child {
    border-bottom: 0;
}
/* Wrappers that only hold other rows (custom fields, energy fields) are not rows themselves */
.project-detail-card:not(.edit-mode) .pd-section > [class*="col-"]:has(> .row),
.project-detail-card:not(.edit-mode) .pd-section > [class*="col-"]:has(> .mt-3 > .row),
.project-detail-card:not(.edit-mode) .pd-section > [class*="col-"]:not(.project-form-section):not(:has(> .form-label)) {
    display: block;
    padding: 0;
    border-bottom: 0;
}
.project-detail-card:not(.edit-mode) .project-detail-grid .row {
    --bs-gutter-x: 0;
    --bs-gutter-y: 0;
    margin: 0 !important;
}
/* caption column: as large as the values and bold; the values are regular weight */
#app .project-detail-card:not(.edit-mode) .project-detail-grid [class*="col-"] > .form-label {
    grid-column: 1;
    grid-row: 1;
    margin: 0;
    padding-top: 0.05rem;
    font-size: 0.9375rem;
    font-weight: 700;
    opacity: 1;
    color: var(--bs-heading-color, inherit);
}
.project-detail-card:not(.edit-mode) .project-detail-grid [class*="col-"] > :not(.form-label) {
    grid-column: 2;
    min-width: 0;
}
/* 既定タスク状況: one row per task — status, 担当, 期限 on a line, the memo under it */
.project-detail-grid .pd-default-task {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.25rem 0.75rem;
}
.project-detail-grid .pd-default-task .pd-default-task-memo {
    flex: 0 0 100%;
    white-space: pre-wrap;
}
.project-detail-card:not(.edit-mode) .project-detail-grid .form-control[readonly],
.project-detail-card:not(.edit-mode) .project-detail-grid div.form-control {
    padding-top: 0;
    padding-bottom: 0;
    min-height: 0 !important;
    height: auto;
    line-height: 1.5;
    font-weight: 400 !important;
}
@media (max-width: 767.98px) {
    .project-detail-card:not(.edit-mode) .project-detail-grid {
        --pd-caption-width: 7.5rem;
    }
}


/* Field captions: smaller and muted so they read as labels, not values */
#app .card .form-label:not(.modal *) {
    font-size: 0.75rem;
    opacity: 0.75;
    margin-bottom: 0.2rem;
}

/* Detail notes: pin + drag (aligned with project list) */
.detail-notes-list .detail-note-item {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 8px;
}
.detail-notes-list .detail-note-item.important-note {
    border-left: 3px solid #dc3545;
}
.detail-notes-list .detail-note-item.pinned-self-note {
    border-left: 3px solid #0d6efd;
}
.detail-notes-list .detail-note-item.important-note.pinned-self-note {
    border-left: 3px solid #dc3545;
    box-shadow: inset 3px 0 0 #0d6efd;
}
.detail-notes-list .note-drag-handle {
    flex: 0 0 14px;
    width: 14px;
    color: #adb5bd;
    cursor: grab;
    font-size: 0.85rem;
    line-height: 1.4;
    padding-top: 2px;
    user-select: none;
    -webkit-user-select: none;
}
.detail-notes-list .note-drag-handle:active {
    cursor: grabbing;
}
.detail-notes-list .note-drag-handle:hover {
    color: #0d6efd;
}
.detail-notes-list.note-filters-active .note-drag-handle {
    opacity: 0.35;
    cursor: not-allowed;
}
.detail-notes-list .note-main {
    flex: 1 1 auto;
    min-width: 0;
}
.detail-notes-list .detail-note-trailing {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: auto;
}
.detail-notes-list .note-actions {
    position: static;
    display: none;
    align-items: center;
    gap: 8px;
    padding: 1px 3px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.06);
}
.detail-notes-list .detail-note-item:hover .note-actions,
.detail-notes-list .detail-note-item:focus-within .note-actions {
    display: inline-flex;
}
.detail-notes-list .note-pin-everyone-icon,
.detail-notes-list .note-pin-self-icon {
    cursor: pointer;
    color: #6c757d;
}
.detail-notes-list .note-pin-everyone-icon.is-active {
    color: #dc3545;
}
.detail-notes-list .note-pin-self-icon.is-active {
    color: #0d6efd;
}
.detail-notes-list .detail-note-delete {
    flex: 0 0 auto;
}
.detail-notes-list .detail-note-item.note-sortable-ghost {
    opacity: 0.35;
    background: #dee2e6;
}
.detail-notes-list .detail-note-item.note-sortable-chosen {
    background: #e7f1ff;
}
.detail-note-item.note-sortable-fallback,
.sortable-fallback.detail-note-item {
    background: #fffdf5 !important;
    border: 1px solid #0d6efd !important;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.28) !important;
    opacity: 1 !important;
    z-index: 10050 !important;
}
.detail-note-item.note-sortable-fallback .detail-note-trailing,
.sortable-fallback.detail-note-item .detail-note-trailing {
    display: none !important;
}

/* Memo label chip — distinct from Bootstrap .badge */
.note-label-chip {
    --note-label-color: #6c757d;
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    max-width: 12rem;
    padding: 0.12rem 0.55rem 0.12rem 0.4rem;
    border: 1px dashed color-mix(in srgb, var(--note-label-color) 70%, transparent);
    border-left: 3px solid var(--note-label-color);
    border-radius: 0.15rem 999px 999px 0.15rem;
    background: color-mix(in srgb, var(--note-label-color) 12%, transparent);
    color: var(--note-label-color);
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    line-height: 1.25;
    vertical-align: middle;
    white-space: nowrap;
}
.note-label-chip-icon {
    font-size: 0.65rem;
    opacity: 0.85;
    flex-shrink: 0;
}
.note-label-chip-text {
    overflow: hidden;
    text-overflow: ellipsis;
}
[data-bs-theme="dark"] .note-label-chip {
    background: color-mix(in srgb, var(--note-label-color) 22%, transparent);
    border-color: color-mix(in srgb, var(--note-label-color) 55%, transparent);
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
<script src="assets/js/energy-fee-payment.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="assets/js/project-custom-field-composite.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="../parent_project/assets/js/parent-project-error.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="../parent_project/assets/js/parent-project-tagify-options.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="../parent_project/assets/js/parent-project-customers-mixin.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="../parent_project/assets/js/parent-project-quick-modal.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="assets/js/project-energy-fields-mixin.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="assets/js/project-form-mixin.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="assets/js/project-form-modal.js?v=<?=PROJECT_CACHE_VERSION?>"></script>
<script src="assets/js/project-detail.js?v=<?=PROJECT_CACHE_VERSION?>"></script>

