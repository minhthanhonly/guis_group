<?php
require_once('../application/loader.php');
$view->heading('プロジェクト添付ファイル');

// Get project ID from URL
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
if (!$project_id) {
    header('Location: index.php');
    exit;
}
?>
<div id="app" class="container-fluid mt-4" v-cloak>
    <!-- Service Worker Status Indicator -->
    <div id="sw-status" class="sw-status">
        <i class="fas fa-circle"></i>
        <span id="sw-status-text">Service Worker</span>
    </div>
    
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
                    <a class="nav-link" href="task.php?project_id=<?php echo $project_id; ?>">タスク<span class="badge badge-sm ms-1 rounded-pill">{{ project?.task_count }}</span></a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link" href="gantt.php?project_id=<?php echo $project_id; ?>">ガントチャート</a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link" href="drawings.php?project_id=<?php echo $project_id; ?>">図面<span class="badge badge-sm bg-info ms-1 rounded-pill">{{ project?.drawing_count }}</span></a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="attachment.php?project_id=<?php echo $project_id; ?>">添付ファイル</a>
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
                            <i class="fa fa-folder me-2"></i>
                            添付ファイル管理
                        </h5>
                        <div>
                            <button class="btn btn-outline-primary btn-sm me-2" @click="showCreateFolderModal" v-if="canViewProject">
                                <i class="fa fa-folder-plus me-1"></i>フォルダ作成
                            </button>
                            <button class="btn btn-primary btn-sm" @click="showUploadModal" v-if="canViewProject">
                                <i class="fa fa-upload me-1"></i>ファイルアップロード
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        
                        <!-- Breadcrumb -->
                        <nav aria-label="breadcrumb" class="mb-3">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="#" @click="navigateToFolder(null)" class="text-decoration-none">
                                        <i class="fa fa-home me-1"></i>ルート
                                    </a>
                                </li>
                                <li v-for="folder in breadcrumbs" :key="folder.id" class="breadcrumb-item">
                                    <a href="#" @click="navigateToFolder(folder.id)" class="text-decoration-none">
                                        {{ folder.name }}
                                    </a>
                                </li>
                            </ol>
                        </nav>

                        <!-- Upload Progress -->
                        <div v-if="uploadProgress.length > 0" class="mb-3">
                            <div v-for="upload in uploadProgress" :key="upload.fileName" class="mb-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="text-muted">{{ upload.fileName }}</small>
                                    <small class="text-muted">{{ upload.progress }}%</small>
                                </div>
                                <div class="progress" style="height: 4px;">
                                    <div class="progress-bar" :style="{ width: upload.progress + '%' }"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Select All Checkbox -->
                        <div v-if="files.length > 0 && canViewProject" class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" 
                                       v-model="selectAllFiles" 
                                       @change="toggleSelectAll"
                                       id="selectAllFiles">
                                <label class="form-check-label" for="selectAllFiles">
                                    すべて選択
                                </label>
                            </div>
                        </div>

                        <!-- File List -->
                        <div v-if="loading" class="text-center py-5">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>

                        <div v-else>
                            <!-- Empty State -->
                            <div v-if="folders.length === 0 && files.length === 0" class="text-center py-5">
                                <i class="fa fa-folder-open fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">フォルダまたはファイルがありません</h5>
                                <p class="text-muted">新しいフォルダを作成するか、ファイルをアップロードしてください。</p>
                            </div>

                            <!-- Folders and Files -->
                            <div v-else class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 40px;" v-if="canViewProject"></th>
                                            <th style="width: 40px;"></th>
                                            <th @click="sortBy('name')" style="cursor: pointer;">
                                                <div class="d-flex align-items-center">
                                                    <span>名前</span>
                                                    <i class="fa ms-1" :class="getSortIcon('name')"></i>
                                                </div>
                                            </th>
                                            <th @click="sortBy('file_type')" style="cursor: pointer; width: 150px;">
                                                <div class="d-flex align-items-center">
                                                    <span>ファイルタイプ</span>
                                                    <i class="fa ms-1" :class="getSortIcon('file_type')"></i>
                                                </div>
                                            </th>
                                            <th @click="sortBy('file_size')" style="cursor: pointer; width: 120px;">
                                                <div class="d-flex align-items-center">
                                                    <span>サイズ</span>
                                                    <i class="fa ms-1" :class="getSortIcon('file_size')"></i>
                                                </div>
                                            </th>
                                            <th @click="sortBy('uploaded_at')" style="cursor: pointer; width: 150px;">
                                                <div class="d-flex align-items-center">
                                                    <span>更新日時</span>
                                                    <i class="fa ms-1" :class="getSortIcon('uploaded_at')"></i>
                                                </div>
                                            </th>
                                            <th @click="sortBy('uploaded_by_name')" style="cursor: pointer; width: 120px;">
                                                <div class="d-flex align-items-center">
                                                    <span>作成者</span>
                                                    <i class="fa ms-1" :class="getSortIcon('uploaded_by_name')"></i>
                                                </div>
                                            </th>
                                            <th style="width: 100px;" v-if="canViewProject">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Folders -->
                                        <tr v-for="folder in sortedFolders" :key="'folder-' + folder.id">
                                            <td v-if="canViewProject">
                                                <!-- Empty cell for folders -->
                                            </td>
                                            <td>
                                                <i class="fa fa-folder text-warning fa-lg"></i>
                                            </td>
                                            <td>
                                                <strong>
                                                    <a href="#" @click.prevent="navigateToFolder(folder.id)" class="text-decoration-none text-primary" style="cursor: pointer;">
                                                        {{ folder.name }}
                                                    </a>
                                                </strong>
                                                <small class="text-muted d-block">{{ folder.file_count }} ファイル</small>
                                            </td>
                                            <td>
                                                <span class="badge bg-label-secondary">フォルダ</span>
                                            </td>
                                            <td>-</td>
                                            <td>{{ formatDateTime(folder.updated_at) }}</td>
                                            <td>{{ folder.created_by_name }}</td>
                                            <td v-if="canViewProject">
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        <i class="fa fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="#" @click="copyFolderUrl(folder)">
                                                            <i class="fa fa-link me-2"></i>URLをコピー
                                                        </a></li>
                                                        <li><a class="dropdown-item" href="#" @click="editFolder(folder)">
                                                            <i class="fa fa-edit me-2"></i>名前変更
                                                        </a></li>
                                                        <li><a class="dropdown-item text-danger" href="#" @click="deleteFolder(folder)">
                                                            <i class="fa fa-trash me-2"></i>削除
                                                        </a></li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Files -->
                                        <tr v-for="file in sortedFiles" :key="'file-' + file.id" 
                                            :class="{ 'table-primary': isFileSelected(file.id) }">
                                            <td v-if="canViewProject">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           :checked="isFileSelected(file.id)"
                                                           @change="toggleFileSelection(file.id)"
                                                           :id="'file-' + file.id">
                                                </div>
                                            </td>
                                            <td>
                                                <i :class="getFileIcon(file.file_name)" class="fa-lg"></i>
                                            </td>
                                            <td>
                                                <a :href="getSecureViewUrl(file)" target="_blank" class="text-decoration-none">
                                                    {{ file.original_name }}
                                                </a>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="badge bg-label-primary" v-if="file.original_name && file.original_name.includes('.')">{{ file.original_name.split('.').pop().toUpperCase() }}</span>
                                                    <span v-else class="text-muted">-</span>
                                                </div>
                                            </td>
                                            <td>{{ formatFileSize(file.file_size) }}</td>
                                            <td>{{ formatDateTime(file.uploaded_at) }}</td>
                                            <td>{{ file.uploaded_by_name }}</td>
                                            <td v-if="canViewProject">
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        <i class="fa fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="#" @click="copyFileUrl(file)">
                                                            <i class="fa fa-link me-2"></i>URLをコピー
                                                        </a></li>
                                                        <li><a class="dropdown-item" :href="getSecureViewUrl(file)" target="_blank">
                                                            <i class="fa fa-eye me-2"></i>表示
                                                        </a></li>
                                                        <li><a class="dropdown-item" :href="getSecureDownloadUrl(file)" download>
                                                            <i class="fa fa-download me-2"></i>ダウンロード
                                                        </a></li>
                                                        <li><a class="dropdown-item text-danger" href="#" @click="deleteFile(file)">
                                                            <i class="fa fa-trash me-2"></i>削除
                                                        </a></li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Folder Modal -->
        <div class="modal fade" tabindex="-1" :class="{show: showCreateFolderModalFlag}" style="display: block;" v-if="showCreateFolderModalFlag">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">フォルダ作成</h5>
                        <button type="button" class="btn-close" @click="closeCreateFolderModal"></button>
                    </div>
                    <div class="modal-body">
                        <form @submit.prevent="createFolder">
                            <div class="mb-3">
                                <label class="form-label">フォルダ名 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" v-model="newFolderName" required maxlength="255" placeholder="フォルダ名を入力してください">
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" @click="closeCreateFolderModal">キャンセル</button>
                        <button class="btn btn-primary" @click="createFolder" :disabled="!newFolderName.trim()">
                            <i class="fa fa-folder-plus me-2"></i>作成
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Modal -->
        <div class="modal fade" tabindex="-1" :class="{show: showUploadModalFlag}" style="display: block;" v-if="showUploadModalFlag">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">ファイルアップロード</h5>
                        <button type="button" class="btn-close" @click="closeUploadModal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Drag and Drop Zone -->
                        <div class="upload-drop-zone mb-3" 
                             @dragover="handleDragOver" 
                             @dragleave="handleDragLeave" 
                             @drop="handleDrop"
                             @click="$refs.fileInput.click()">
                            <div class="drop-zone-content text-center py-5">
                                <i class="fa fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">ファイルをここにドラッグ&ドロップ</h5>
                                <p class="text-muted mb-3">または クリックしてファイルを選択</p>
                                <button type="button" class="btn btn-outline-primary">
                                    <i class="fa fa-folder-open me-2"></i>ファイルを選択
                                </button>
                                <div class="form-text mt-2">複数のファイルを選択できます。最大ファイルサイズ: 100MB</div>
                            </div>
                        </div>
                        
                        <!-- Hidden File Input -->
                        <input type="file" class="d-none" ref="fileInput" multiple @change="handleFileSelect">
                        
                                <!-- Selected Files List -->
        <div v-if="selectedFiles.length > 0" class="mb-3">
            <h6>選択されたファイル:</h6>
            <div class="list-group selected-files-list">
                <div v-for="(file, index) in selectedFiles" :key="index" class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <i :class="getFileIcon(file.name)" class="me-2"></i>
                        {{ file.name }}
                        <small class="text-muted">({{ formatFileSize(file.size) }})</small>
                    </div>
                    <button class="btn btn-sm btn-outline-danger" @click="removeSelectedFile(index)">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" @click="closeUploadModal">キャンセル</button>
                        <button class="btn btn-primary" @click="uploadFiles" :disabled="selectedFiles.length === 0 || uploading">
                            <i class="fa fa-upload me-2"></i>
                            <span v-if="uploading">アップロード中...</span>
                            <span v-else>アップロード</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Folder Modal -->
        <div class="modal fade" tabindex="-1" :class="{show: showEditFolderModalFlag}" style="display: block;" v-if="showEditFolderModalFlag">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">フォルダ名変更</h5>
                        <button type="button" class="btn-close" @click="closeEditFolderModal"></button>
                    </div>
                    <div class="modal-body">
                        <form @submit.prevent="updateFolder">
                            <div class="mb-3">
                                <label class="form-label">フォルダ名 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" v-model="editingFolder.name" required maxlength="255">
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" @click="closeEditFolderModal">キャンセル</button>
                        <button class="btn btn-primary" @click="updateFolder" :disabled="!editingFolder.name.trim()">
                            <i class="fa fa-save me-2"></i>保存
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Fixed Bulk Actions Bar -->
    <div v-if="selectedFileIds.length > 0" class="bulk-actions-bar">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <span class="fw-medium text-white">{{ selectedFileIds.length }}個のファイルが選択されています</span>
                    <button class="btn btn-light btn-sm" @click="clearSelection">
                        <i class="fa fa-times me-1"></i>選択解除
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-info" @click="copySelectedFileUrls">
                        <i class="fa fa-copy me-1"></i>URLをコピー
                    </button>
                    <button class="btn btn-danger" @click="deleteSelectedFiles">
                        <i class="fa fa-trash me-1"></i>一括削除
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
    z-index: 9998;
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

/* Table row selection styles */
.table-primary {
    background-color: rgba(var(--bs-primary-rgb), 0.2) !important;
}

.table tbody tr:hover {
    background-color: rgba(var(--bs-primary-rgb), 0.1) !important;
}
</style>

<style>
.sw-status {
    position: fixed;
    top: 10px;
    right: 10px;
    z-index: 1000;
    background: rgba(255, 255, 255, 0.9);
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 12px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.sw-status.online i {
    color: #28a745;
}

.sw-status.offline i {
    color: #dc3545;
}

.sw-status.installing i {
    color: #ffc107;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
}

.breadcrumb-item a {
    color: #0d6efd;
}

.breadcrumb-item a:hover {
    color: #0a58ca;
}

.file-icon {
    width: 20px;
    text-align: center;
}

/* Upload Modal Drag and Drop Styles */
.upload-drop-zone {
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
}

/* Selected Files List Height Limit */
.selected-files-list {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 6px;
}

.selected-files-list::-webkit-scrollbar {
    width: 8px;
}

.selected-files-list::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.selected-files-list::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.selected-files-list::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
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

.upload-drop-zone:hover {
    border-color: #007bff;
}

.upload-drop-zone.drag-over {
    border-color: #007bff;
    background-color: #e3f2fd;
    transform: scale(1.02);
}

.upload-drop-zone.drag-over .drop-zone-content {
    opacity: 0.8;
}

.upload-drop-zone.drag-over::before {
    content: "ファイルをここにドロップしてください";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background-color: rgba(0, 123, 255, 0.9);
    color: white;
    padding: 15px 30px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 500;
    z-index: 10;
    pointer-events: none;
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
}

.drop-zone-content {
    transition: all 0.3s ease;
}

.upload-drop-zone .btn {
    pointer-events: none;
}

.upload-drop-zone:hover .btn {
    background-color: #007bff;
    color: white;
    border-color: #007bff;
}

/* Selected Files List Styling */
.list-group-item {
    border-left: 4px solid transparent;
    transition: all 0.2s ease;
}

.list-group-item:hover {
    border-left-color: #007bff;
    background-color: #f8f9fa;
}

/* Upload Modal Styling */
.modal-lg .modal-body {
    padding: 2rem;
}

.upload-drop-zone .fa-cloud-upload-alt {
    transition: all 0.3s ease;
}

.upload-drop-zone:hover .fa-cloud-upload-alt {
    color: #007bff !important;
    transform: scale(1.1);
}

/* Bulk Actions Styling */
.bulk-actions-toolbar {
    border: 1px solid #e9ecef;
    transition: all 0.2s ease;
}

.bulk-actions-toolbar:hover {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.table-primary {
    background-color: rgba(13, 110, 253, 0.1) !important;
}

.form-check-input:checked {
    background-color: #007bff;
    border-color: #007bff;
}

/* Smooth transitions for selected rows */
.table tbody tr {
    transition: background-color 0.2s ease;
}

/* Checkbox styling */
.form-check-input {
    cursor: pointer;
}

.form-check-label {
    cursor: pointer;
    user-select: none;
}
</style>

<!-- Define PROJECT_ID before loading Vue and attachment.js -->
<script>
const PROJECT_ID = <?php echo $project_id; ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="<?=ROOT?>assets/js/sw-manager.js?v=<?=CACHE_VERSION?>"></script>
<script src="assets/js/attachment.js?v=<?=CACHE_VERSION?>"></script> 