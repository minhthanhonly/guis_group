<?php
require_once('../application/loader.php');
$view->heading('図面管理');

// Get project ID from URL
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
if (!$project_id) {
    header('Location: index.php');
    exit;
}
if($_SESSION['show_project'] == 0){
    echo '<div class="container-fluid mt-4"><div class="alert alert-danger">権限がありません。</div></div>';
    exit;
}
?>
<script>window.__chatPageContext = { project_id: <?php echo (int)$project_id; ?> };</script>
<div id="app" class="container-fluid mt-4" v-cloak>
    <div>
        <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold" href="#"><span class="badge badge-sm bg-primary">#{{ project?.id }}</span></a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#projectNavbar" aria-controls="projectNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="projectNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                    <a class="nav-link" href="detail.php?id=<?php echo $project_id; ?>"><span data-i18n="概要">概要</span></a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link" href="task.php?project_id=<?php echo $project_id; ?>"><span data-i18n="タスク">タスク</span><span class="badge badge-sm ms-1 rounded-pill">{{ project?.task_count }}</span></a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link" href="gantt.php?project_id=<?php echo $project_id; ?>"><span data-i18n="ガントチャート">ガントチャート</span></a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link active text-primary" aria-current="page" href="drawings.php?project_id=<?php echo $project_id; ?>"><span data-i18n="図面">図面</span><span class="badge badge-sm bg-info ms-1 rounded-pill">{{ totalStat?.value ?? 0 }}</span></a>
                    </li>
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
                <a href="detail.php?id=<?php echo $project_id; ?>" class="btn btn-outline-primary">
                    <i class="fa fa-arrow-left me-2"></i><span data-i18n="案件概要へ戻る">案件概要へ戻る</span>
                </a>
            </div>

            <!-- Main Content -->
            <div class="col-12" v-if="canViewProject">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <h5 class="card-title mb-0 me-3">
                                <i class="fa fa-file-alt me-2"></i><span data-i18n="図面ファイル管理">図面ファイル管理</span>
                            </h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Price Warning Alert -->
                        <div v-if="isPriceExceedingAmount" class="alert alert-warning alert-dismissible fade show" role="alert">
                            <i class="fa fa-exclamation-triangle me-2"></i>
                            <strong><span data-i18n="警告">警告</span>:</strong> <span data-i18n="図面の合計金額">図面の合計金額</span>（¥{{ formatNumber(totalDrawingsPrice) }}）が<span data-i18n="プロジェクトの金額">プロジェクトの金額</span>（¥{{ formatNumber(projectAmount) }}）を超えています。
                            <br>
                            <small class="text-muted"><span data-i18n="超過額">超過額</span>: ¥{{ formatNumber(priceDifference) }}</small>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        
                        <!-- Status filter statistics & totals -->
                        <div class="row mb-4">
                            <div class="col-auto" v-for="stat in statusStats" :key="stat.label">
                                <div class="card border text-center"
                                     :class="{'border-primary': statusFilter === stat.status}"
                                     style="min-width: 140px; cursor: pointer;"
                                     @click="filterByStatus(stat.status)">
                                    <div class="card-body py-3 px-2">
                                        <div :class="'fs-5 mb-1 ' + stat.color" style="font-size: 1.25rem;">
                                            <i :class="stat.icon"></i> {{ stat.value }}
                                        </div>
                                        <div class="fw-bold small">{{ $t(stat.label) }}</div>
                                    </div>
                                </div>
                            </div>
                            <!-- Total Price Card -->
                            <div class="col-auto" v-if="projectAmount > 0">
                                <div class="card border text-center"
                                     :class="{'border-danger': isPriceExceedingAmount, 'border-success': !isPriceExceedingAmount}"
                                     style="min-width: 180px;">
                                    <div class="card-body py-3 px-2">
                                        <div :class="'fs-5 mb-1 ' + (isPriceExceedingAmount ? 'text-danger' : 'text-success')" style="font-size: 1.25rem;">
                                            <i class="fa fa-yen-sign"></i> {{ formatNumber(totalDrawingsPrice) }}
                                        </div>
                                        <div class="fw-bold small"><span data-i18n="図面合計金額">図面合計金額</span></div>
                                        <div class="small text-muted mt-1">
                                            <span data-i18n="プロジェクト金額">プロジェクト金額</span>: ¥{{ formatNumber(projectAmount) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Total drawings badge on the right -->
                            <div class="col-auto ms-auto d-flex align-items-center" v-if="totalStat">
                                <span class="badge bg-primary rounded-pill px-3 py-2 text-white"
                                      style="cursor: pointer; font-size: 1.05rem;"
                                      @click="filterByStatus('')">
                                    {{ $t(totalStat.label) }}:
                                    <span class="fw-bold text-warning ms-1">{{ totalStat.value }}</span>
                                </span>
                            </div>
                        </div>

                        <!-- Filters and Search -->
                        <div class="row mb-2">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-search"></i></span>
                                    <input type="text" class="form-control" v-model="searchQuery" placeholder="ファイル名で検索...">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" v-model="statusFilter">
                                    <option value="" data-i18n="すべてのステータス">すべてのステータス</option>
                                    <option v-for="status in drawingStatuses" :key="status.value" :value="status.value">{{ $t(status.label) }}</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn w-100"
                                        :class="hasActiveFilter ? 'btn-outline-danger' : 'btn-outline-secondary'"
                                        @click="clearFilters">
                                    <i class="fa fa-times me-1"></i><span data-i18n="クリア">クリア</span>
                                </button>
                            </div>
                        </div>

                        <!-- Unassigned drawings warning -->
                        <div v-if="canManageDrawingAssignee() && hasUnassignedDrawings" class="alert alert-warning py-2 mb-4">
                            <i class="fa fa-exclamation-triangle me-2"></i>
                            <span data-i18n="未割り当ての図面があります">未割り当ての図面があります</span>
                        </div>
                        
                        <!-- Selection Mode Indicator -->
                        <div class="selection-mode-hint" role="alert" :class="{'show': isCtrlMode || isShiftMode, 'ctrl-mode': isCtrlMode, 'shift-mode': isShiftMode}">
                            <i class="fa fa-info-circle me-2"></i>
                            <strong><span data-i18n="選択モード">選択モード</span>:</strong> 
                            <span class="ctrl-mode"><span data-i18n="Ctrlキーを押しながら行をクリックして複数のファイルを選択できます">Ctrlキーを押しながら行をクリックして複数のファイルを選択できます</span></span>
                            <span class="shift-mode"><span data-i18n="Shiftキーを押しながら行をクリックして範囲選択できます">Shiftキーを押しながら行をクリックして範囲選択できます</span></span>
                        </div>
                        
                        <!-- Selection Count Indicator -->
                        <div v-if="selectedDrawings.length > 0" class="selection-count">
                            <i class="fa fa-check-circle me-1"></i>
                            {{ selectedDrawings.length }}<span data-i18n="個選択中">個選択中</span>
                        </div>

                        <!-- Loading State -->
                        <div v-if="loading" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden"><span data-i18n="読み込み中">読み込み中</span>...</span>
                            </div>
                            <p class="mt-2 text-muted"><span data-i18n="ファイルを読み込み中">ファイルを読み込み中</span>...</p>
                        </div>

                        <!-- Drawings Table -->
                        <div v-else-if="filteredDrawings.length > 0" class="table-responsive" id="drawingsTable">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" class="form-check-input" @change="toggleSelectAll" :checked="isAllSelected">
                                        </th>
                                        <th width="60" class="text-center">
                                            <span data-i18n="番号">番号</span>
                                        </th>
                                        <th @click="sortBy('name')" style="cursor: pointer;">
                                            <div class="d-flex align-items-center">
                                                <span><span data-i18n="タスク">タスク</span></span>
                                                <i class="fa ms-1" :class="getSortIcon('name')"></i>
                                            </div>
                                        </th>
                                        <th @click="sortBy('drawing_count')" style="cursor: pointer; min-width: 72px;" class="text-center">
                                            <div class="d-flex align-items-center justify-content-center">
                                                <span><span data-i18n="図面数">図面数</span></span>
                                                <i class="fa ms-1" :class="getSortIcon('drawing_count')"></i>
                                            </div>
                                        </th>
                                        <th @click="sortBy('created_by')" style="cursor: pointer;">
                                            <div class="d-flex align-items-center">
                                                <span><span data-i18n="担当者">担当者</span></span>
                                                <i class="fa ms-1" :class="getSortIcon('created_by')"></i>
                                            </div>
                                        </th>
                                        <th @click="sortBy('status')" style="cursor: pointer;">
                                            <div class="d-flex align-items-center">
                                                <span><span data-i18n="ステータス">ステータス</span></span>
                                                <i class="fa ms-1" :class="getSortIcon('status')"></i>
                                            </div>
                                        </th>
                                        <!-- Price column -->
                                        <th style="min-width: 140px;">
                                            <div class="d-flex align-items-center">
                                                <span><span data-i18n="単価">単価</span></span>
                                            </div>
                                        </th>
                                        <th style="min-width: 120px;"><span data-i18n="最終更新者">最終更新者</span></th>
                                        <th width="150"><span data-i18n="操作">操作</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(drawing, index) in filteredDrawings" :key="drawing.id" 
                                        :class="{ 
                                            'table-primary': selectedDrawings.includes(drawing.id),
                                            'ctrl-mode': isCtrlMode,
                                            'shift-mode': isShiftMode
                                        }"
                                        :data-index="index"
                                        @click="handleRowClick($event, drawing.id, index)"
                                        @mousedown="startDragSelection($event, index)"
                                        @mouseover="hoveredRow = index"
                                        @mouseleave="hoveredRow = null"
                                        style="cursor: pointer;">
                                        <td @click.stop @mousedown.stop>
                                            <input type="checkbox" class="form-check-input" :value="drawing.id" v-model="selectedDrawings" @change="handleCheckboxClick(drawing.id)">
                                        </td>
                                        <td class="text-center align-middle">
                                            {{ index + 1 }}
                                        </td>
                                        <td style="position: relative;">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-grow-1">
                                                    <span>{{ drawing.name }}</span>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-secondary position-absolute" 
                                                    @click.stop="copyToClipboard(drawing.name)" 
                                                    title="ファイル名をコピー"
                                                    v-show="hoveredRow === index"
                                                    style="right: 8px; top: 50%; transform: translateY(-50%);">
                                                <i class="fa fa-copy"></i>
                                            </button>
                                        </td>
                                        <td class="text-center align-middle">
                                            <span v-if="!isDefaultTaskDrawing(drawing)" class="badge bg-label-info">{{ getDrawingQuantity(drawing) }}</span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar me-2" v-for="(userid, user_index) in (drawing.created_by || '').split(',')" :key="userid && userid.trim()" data-bs-toggle="tooltip" :title="getUserCreatedByFullNameText(drawing, user_index)">
                                                    <img :src="getUserAvatar(userid.trim())" class="avatar-img rounded-circle" v-if="getUserAvatar(userid.trim()) && userid.trim()">
                                                    <span class="avatar-initial rounded-circle bg-label-primary" v-else-if="userid.trim()!= ''">{{ getUserAvatarCreatedByText(drawing, user_index) }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge" :class="getStatusBadgeClass(drawing.status)">{{ getStatusLabel(drawing.status) }}</span>
                                        </td>
                                        <!-- Price column -->
                                        <td>
                                            <span v-if="isDefaultTaskDrawing(drawing) && drawing.status !== 'completed'" class="text-muted">¥0</span>
                                            <div v-else-if="canEditDrawingPrice()" class="input-group input-group-sm" style="max-width: 140px;">
                                                <span class="input-group-text">¥</span>
                                                <input type="number"
                                                       class="form-control text-end"
                                                       v-model.number="drawing.price"
                                                       min="0"
                                                       step="1"
                                                       @click.stop
                                                       @change="updatePrice(drawing)">
                                            </div>
                                            <span v-else-if="getEffectiveDrawingPrice(drawing) > 0" class="text-nowrap">¥{{ formatNumber(getEffectiveDrawingPrice(drawing)) }}</span>
                                            <span v-else class="text-muted">—</span>
                                        </td>
                                        <td>
                                            <span class="text-muted small">{{ formatLastEditor(drawing) }}</span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button v-if="canManageDrawingAssignee() && !isUserAssigned(drawing)" class="btn btn-outline-success" @click="assignDrawing(drawing.id)" title="割り当てる">
                                                    <i class="fa fa-user-plus"></i>
                                                </button>
                                                <button v-else-if="canManageDrawingAssignee() && isUserAssigned(drawing)" class="btn btn-outline-warning" @click="unassignDrawing(drawing.id)" title="割り当て解除">
                                                    <i class="fa fa-user-minus"></i>
                                                </button>
                                                <button class="btn btn-outline-primary" @click="downloadDrawing(drawing)" title="ダウンロード" v-if="drawing.file_path">
                                                    <i class="fa fa-download"></i>
                                                </button>
                                                <button class="btn btn-outline-secondary" @click="openEditModal(drawing)" title="編集">
                                                    <i class="fa fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" @click="deleteDrawing(drawing.id)" title="削除">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="text-center py-5">
                            <i class="fa fa-file-alt fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted"><span data-i18n="図面がありません">図面がありません</span></h5>
                        </div>


                    </div>
                </div>
            </div>
            <div class="col-12" v-else>
                <div class="text-center py-5">
                    <div class="text-muted">
                        <i class="fa fa-lock fa-3x mb-2"></i>
                        <p>権限がありません</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Drawing Modal -->
        <div class="modal fade" id="drawingModal" tabindex="-1" aria-labelledby="drawingModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="drawingModalLabel">{{ editingDrawing.id ? $t('タスク編集') : $t('タスク追加') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form @submit.prevent="saveDrawing">
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="タスク">タスク</span> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" v-model="editingDrawing.name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="図面数">図面数</span></label>
                                <input type="number" class="form-control" min="1" step="1" v-model.number="editingDrawing.drawing_count" :readonly="!!editingDrawing.task_id" :disabled="!!editingDrawing.task_id">
                                <small v-if="editingDrawing.task_id" class="text-muted"><span data-i18n="タスクの図面数と連動しています">タスクの図面数と連動しています</span></small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><span data-i18n="ステータス">ステータス</span></label>
                                <select class="form-select" v-model="editingDrawing.status">
                                    <option v-for="status in drawingStatuses" :key="status.value" :value="status.value">{{ $t(status.label) }}</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><span data-i18n="キャンセル">キャンセル</span></button>
                        <button type="button" class="btn btn-primary" @click="saveDrawing">
                            <i class="fa fa-save me-1"></i> <span data-i18n="保存">保存</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Status Change Modal -->
        <div class="modal fade" id="bulkStatusModal" tabindex="-1" aria-labelledby="bulkStatusModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bulkStatusModalLabel">ステータス一括変更</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>選択された {{ selectedDrawings.length }} 個のファイルのステータスを変更します。</p>
                                                <div class="mb-3">
                            <label class="form-label">新しいステータス</label>
                            <select class="form-select" v-model="bulkStatus">
                                <option v-for="status in drawingStatuses" :key="status.value" :value="status.value">{{ $t(status.label) }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="button" class="btn btn-primary" @click="confirmBulkStatusChange">
                            <i class="fa fa-check"></i> 変更
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assignee Selection Modal -->
        <div class="modal fade" tabindex="-1" :class="{show: assigneeModal.show}" style="display: block;" v-if="assigneeModal.show">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ assigneeModal.isBulk ? '担当者を一括割り当て' : '担当者を選択' }}</h5>
                        <button type="button" class="btn-close" @click="closeAssigneeModal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex flex-wrap">
                            <div v-for="member in projectMembers" :key="member.userid || member.user_id" class="m-2 text-center" style="cursor:pointer;">
                                <div @click="toggleAssignee(member.userid || member.user_id)" :class="{'border border-primary': assigneeModal.selected.includes(member.userid || member.user_id)}" style="display:inline-block;border-radius:50%;padding:2px;">
                                    <img v-if="!member.avatarError && getAvatarSrc(member)" class="rounded-circle" :src="getAvatarSrc(member)" :alt="member.user_name" width="40" height="40" @error="handleAvatarError(member)">
                                    <span v-else class="avatar-initial rounded-circle bg-label-primary" style="width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;">{{ getInitials(member.user_name) }}</span>
                                </div>
                                <div style="font-size:12px;max-width:60px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ member.user_name }}</div>
                                <input type="radio" class="form-check-input mt-1" name="assignee_radio" :checked="assigneeModal.selected.includes(member.userid || member.user_id)" @change="toggleAssignee(member.userid || member.user_id)">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" @click="closeAssigneeModal">キャンセル</button>
                        <button class="btn btn-primary" @click="confirmAssigneeModal">OK</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    

    
    <!-- Fixed Bulk Actions Bar -->
    <div v-if="selectedDrawings.length > 0" class="bulk-actions-bar">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <span class="fw-medium text-white">
                        {{ selectedDrawings.length }}{{ $t('個のファイルが選択されています') }}
                    </span>
                    <button class="btn btn-light btn-sm" @click="clearSelection">
                        <i class="fa fa-times me-1"></i>{{ $t('選択解除') }}
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-info" @click="bulkCopyNames">
                        <i class="fa fa-copy me-1"></i>{{ $t('名前をコピー') }}
                    </button>
                    <button v-if="canManageDrawingAssignee()" class="btn btn-success" @click="bulkAssign">
                        <i class="fa fa-user-plus me-1"></i>{{ $t('一括割り当て') }}
                    </button>
                    <button v-if="canManageDrawingAssignee()" class="btn btn-warning" @click="bulkUnassign">
                        <i class="fa fa-user-minus me-1"></i>{{ $t('一括解除') }}
                    </button>
                    <button class="btn btn-danger" @click="bulkDelete">
                        <i class="fa fa-trash me-1"></i>{{ $t('一括削除') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$view->footing();
?>

<style>
.drop-zone {
    border: 2px dashed #dee2e6;
    border-radius: 0.5rem;
    padding: 3rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background-color: #f8f9fa;
}

.drop-zone:hover {
    border-color: var(--bs-primary);
    background-color: rgba(var(--bs-primary-rgb), 0.05);
}

.drop-zone.drag-over {
    border-color: var(--bs-primary);
    background-color: rgba(var(--bs-primary-rgb), 0.1);
    transform: scale(1.02);
}

.drop-zone-content {
    pointer-events: none;
}

.drop-zone-content button {
    pointer-events: auto;
}

.table-hover tbody tr:hover {
    background-color: rgba(var(--bs-primary-rgb), 0.05);
}

.table-primary {
    background-color: rgba(var(--bs-primary-rgb), 0.1) !important;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.avatar-initial {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 500;
    text-transform: uppercase;
}

/* Sortable table headers */
.table th[style*="cursor: pointer"]:hover {
    background-color: rgba(var(--bs-primary-rgb), 0.1);
    transition: background-color 0.2s ease;
}

.table th[style*="cursor: pointer"] .fa {
    color: #6c757d;
    transition: color 0.2s ease;
}

.table th[style*="cursor: pointer"]:hover .fa {
    color: var(--bs-primary);
}

.table th[style*="cursor: pointer"] .fa-sort-up,
.table th[style*="cursor: pointer"] .fa-sort-down {
    color: var(--bs-primary);
}

/* Fixed Bulk Actions Bar */
.bulk-actions-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 0;
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
    z-index: 9999;
    animation: slideUp 0.3s ease-out;
}

.bulk-actions-bar .btn {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    border: none;
    font-weight: 500;
}

.bulk-actions-bar .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    transition: all 0.2s ease;
}

@keyframes slideUp {
    from {
        transform: translateY(100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Add bottom padding to main content when bulk actions bar is visible */
.bulk-actions-bar + .container-fluid {
    padding-bottom: 80px;
}

/* Drag selection styles */
.drag-selecting {
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}

.drag-selecting .table tbody tr {
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}

/* Prevent text selection during drag */
.table tbody tr {
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}

/* Allow text selection in specific cells */
.table tbody tr td:nth-child(2) {
    user-select: text;
    -webkit-user-select: text;
    -moz-user-select: text;
    -ms-user-select: text;
}

/* Enhanced selection styles */
.table tbody tr {
    transition: background-color 0.15s ease;
}

.table tbody tr:hover {
    background-color: rgba(var(--bs-primary-rgb), 0.08) !important;
}

.table tbody tr.table-primary {
    background-color: rgba(var(--bs-primary-rgb), 0.15) !important;
    border-left: 3px solid var(--bs-primary);
}

.table tbody tr.table-primary:hover {
    background-color: rgba(var(--bs-primary-rgb), 0.2) !important;
}

/* Prevent selection on interactive elements */
.table tbody tr button,
.table tbody tr input,
.table tbody tr .btn-group,
.table tbody tr .dropdown {
    pointer-events: auto;
}

/* Ensure checkbox works properly */
.table tbody tr td:first-child {
    pointer-events: auto;
    position: relative;
}

.table tbody tr td:first-child .form-check-input {
    pointer-events: auto;
    z-index: 10;
    position: relative;
    cursor: pointer;
}

/* Visual feedback for Ctrl key state */
.table tbody tr.ctrl-mode {
    cursor: crosshair;
}

/* Visual feedback for Shift key state */
.table tbody tr.shift-mode {
    cursor: crosshair;
}

/* Enhanced table styles */
#drawingsTable {
    position: relative;
    overflow-x: auto;
    overflow-y: visible;
    min-height: 400px;
}

.table tbody tr {
    position: relative;
}


/* Selection count indicator */
.selection-count {
    position: fixed;
    top: 20px;
    right: 20px;
    background: var(--bs-primary);
    color: white;
    padding: 8px 12px;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
    z-index: 1000;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    animation: fadeInUp 0.3s ease-out;
}

/* Selection mode hint */
.selection-mode-hint {
    position: fixed;
    top: 160px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--bs-info);
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    z-index: 1001;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    max-width: 600px;
    text-align: center;
    visibility: hidden;
    opacity: 0;
    transition: visibility 0s, opacity 0.3s ease-out;
}

.selection-mode-hint.show {
    visibility: visible;
    animation: fadeInUp 0.3s ease-out forwards;
}

.selection-mode-hint .ctrl-mode {
    display: inline-block;
}

.selection-mode-hint .shift-mode {
    display: none;
}

.selection-mode-hint.shift-mode .shift-mode{
    display: inline-block;
}

.selection-mode-hint.shift-mode .ctrl-mode {
    display: none;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateX(-50%) translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
}
</style>

<!-- Define PROJECT_ID before loading Vue and drawings.js -->
<script>
const PROJECT_ID = <?php echo $project_id; ?>;
const CURRENT_USER_ID = '<?php echo $_SESSION['userid']; ?>';
</script>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="assets/js/drawings.js?v=<?=CACHE_VERSION?>"></script> 