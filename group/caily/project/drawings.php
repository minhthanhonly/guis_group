<?php
require_once('../application/loader.php');
$view->heading('図面管理');

// Get project ID from URL
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
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
                    <a class="nav-link" href="detail.php?id=<?php echo $project_id; ?>">概要</a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link" href="task.php?project_id=<?php echo $project_id; ?>">タスク</a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link" href="gantt.php?project_id=<?php echo $project_id; ?>">ガントチャート</a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="drawings.php?project_id=<?php echo $project_id; ?>">図面</a>
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
                <a href="detail.php?id=<?php echo $project_id; ?>" class="btn btn-outline-primary">
                    <i class="fa fa-arrow-left me-2"></i><span data-i18n="戻る">戻る</span>
                </a>
            </div>

            <!-- Main Content -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fa fa-file-alt me-2"></i>図面ファイル管理
                        </h5>
                        <div class="d-flex gap-2">
                            <div class="col-md-6">
                                <div class="d-flex gap-2">
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importModal">
                                        <i class="fa fa-upload me-1"></i>インポート
                                    </button>
                                    <button class="btn btn-success" @click="showAddModal = true">
                                        <i class="fa fa-plus me-1"></i>追加
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filters and Search -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-search"></i></span>
                                    <input type="text" class="form-control" v-model="searchQuery" placeholder="ファイル名で検索...">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" v-model="statusFilter">
                                    <option value="">すべてのステータス</option>
                                    <option value="draft">下書き</option>
                                    <option value="review">レビュー中</option>
                                    <option value="approved">承認済み</option>
                                    <option value="rejected">却下</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-outline-secondary w-100" @click="clearFilters">
                                    <i class="fa fa-times me-1"></i>クリア
                                </button>
                            </div>
                        </div>

                        <!-- Loading State -->
                        <div v-if="loading" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">読み込み中...</span>
                            </div>
                            <p class="mt-2 text-muted">ファイルを読み込み中...</p>
                        </div>

                        <!-- Drawings Table -->
                        <div v-else-if="filteredDrawings.length > 0" class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" class="form-check-input" @change="toggleSelectAll" :checked="isAllSelected">
                                        </th>
                                        <th>ファイル名</th>
                                        <th>ステータス</th>
                                        <th>作成日</th>
                                        <th>作成者</th>
                                        <th width="120">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="drawing in filteredDrawings" :key="drawing.id" :class="{ 'table-primary': selectedDrawings.includes(drawing.id) }">
                                        <td>
                                            <input type="checkbox" class="form-check-input" :value="drawing.id" v-model="selectedDrawings">
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fa fa-file-pdf text-danger me-2" v-if="drawing.name && drawing.name.toLowerCase().endsWith('.pdf')"></i>
                                                <i class="fa fa-file-image text-success me-2" v-else-if="drawing.name && ['jpg','jpeg','png','gif','bmp','tiff'].some(ext => drawing.name.toLowerCase().endsWith('.' + ext))"></i>
                                                <i class="fa fa-file me-2" v-else></i>
                                                <div>
                                                    <div class="fw-medium">{{ drawing.name }}</div>
                                                    <small class="text-muted" v-if="drawing.name">{{ drawing.name.split('.').pop().toUpperCase() }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" v-model="drawing.status" @change="updateStatus(drawing.id, drawing.status)" style="width: auto;">
                                                <option value="draft">下書き</option>
                                                <option value="review">レビュー中</option>
                                                <option value="approved">承認済み</option>
                                                <option value="rejected">却下</option>
                                            </select>
                                        </td>
                                        <td>{{ formatDateTime(drawing.created_at) }}</td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar me-2" v-if="drawing.created_by_name">
                                                    <span class="avatar-initial rounded-circle bg-label-primary" style="width:24px;height:24px;font-size:10px;">{{ getInitials(drawing.created_by_name) }}</span>
                                                </div>
                                                <span class="small">{{ drawing.created_by_name || 'Unknown' }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" @click="downloadDrawing(drawing)" title="ダウンロード" v-if="drawing.file_path">
                                                    <i class="fa fa-download"></i>
                                                </button>
                                                <button class="btn btn-outline-secondary" @click="openDrawingModal(drawing)" title="編集">
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
                            <h5 class="text-muted">ファイルがありません</h5>
                            <p class="text-muted">最初のファイルを追加してください</p>
                            <div class="d-flex gap-2 justify-content-center">
                                <button class="btn btn-outline-info" @click="openImportModal">
                                    <i class="fa fa-upload me-1"></i>インポート
                                </button>
                                <button class="btn btn-primary" @click="openDrawingModal()">
                                    <i class="fa fa-plus me-1"></i>ファイル追加
                                </button>
                            </div>
                        </div>

                        <!-- Bulk Actions -->
                        <div v-if="selectedDrawings.length > 0" class="mt-4 p-3 bg-light rounded">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-medium">{{ selectedDrawings.length }}個のファイルが選択されています</span>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-warning btn-sm" @click="bulkChangeStatus">
                                        <i class="fa fa-edit me-1"></i>ステータス変更
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm" @click="bulkDelete">
                                        <i class="fa fa-trash me-1"></i>一括削除
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Drawing Modal -->
        <div class="modal fade" id="drawingModal" tabindex="-1" aria-labelledby="drawingModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="drawingModalLabel">{{ editingDrawing.id ? 'ファイル編集' : 'ファイル追加' }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form @submit.prevent="saveDrawing">
                            <div class="mb-3">
                                <label class="form-label">ファイル名 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" v-model="editingDrawing.name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">ステータス</label>
                                <select class="form-select" v-model="editingDrawing.status">
                                    <option value="draft">下書き</option>
                                    <option value="review">レビュー中</option>
                                    <option value="approved">承認済み</option>
                                    <option value="rejected">却下</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="button" class="btn btn-primary" @click="saveDrawing">
                            <i class="fa fa-save"></i> 保存
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Import Modal -->
        <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="importModalLabel">ファイルインポート</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Drag & Drop Area -->
                        <div class="mb-4">
                            <div class="border-2 border-dashed border-primary rounded p-4 text-center" 
                                 @drop="onDrop" 
                                 @dragover.prevent 
                                 @dragenter.prevent
                                 style="border-style: dashed; min-height: 120px; display: flex; align-items: center; justify-content: center;">
                                <div>
                                    <i class="fa fa-cloud-upload fa-3x text-primary mb-2"></i>
                                    <p class="mb-1">ファイルをここにドラッグ＆ドロップ</p>
                                    <p class="text-muted small">または</p>
                                    <button class="btn btn-outline-primary btn-sm" @click="$refs.fileInput.click()">
                                        ファイルを選択
                                    </button>
                                    <input type="file" ref="fileInput" multiple style="display: none;" @change="onFileSelect">
                                </div>
                            </div>
                        </div>

                        <!-- Clipboard Import -->
                        <div class="mb-4">
                            <h6>クリップボードからインポート</h6>
                            <div class="input-group">
                                <textarea class="form-control" v-model="clipboardText" 
                                          placeholder="ファイルパスを1行ずつ入力してください&#10;例:&#10;C:\Documents\file1.pdf&#10;D:\Projects\drawing2.dwg" 
                                          rows="4"></textarea>
                                <button class="btn btn-outline-secondary" @click="parseClipboardText">
                                    <i class="fa fa-paste"></i> 解析
                                </button>
                            </div>
                        </div>

                        <!-- Import Files List -->
                        <div v-if="importFiles.length > 0">
                            <h6>インポートするファイル ({{ importFiles.length }}件)</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>ファイル名</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(file, index) in importFiles" :key="index">
                                            <td>{{ file.name }}</td>
                                            <td>
                                                <button class="btn btn-outline-danger btn-sm" @click="removeImportFile(index)">
                                                    <i class="fa fa-times"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="button" class="btn btn-primary" @click="importFiles" :disabled="importFiles.length === 0">
                            <i class="fa fa-upload"></i> インポート ({{ importFiles.length }})
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
                                <option value="draft">下書き</option>
                                <option value="review">レビュー中</option>
                                <option value="approved">承認済み</option>
                                <option value="rejected">却下</option>
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
</style>

<!-- Define PROJECT_ID before loading Vue and drawings.js -->
<script>
const PROJECT_ID = <?php echo $project_id; ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="assets/js/drawings.js"></script> 