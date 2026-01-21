<?php
require_once('../application/loader.php');
$view->heading('ファイル詳細');

// Get file ID from URL
$file_id = isset($_GET['file_id']) ? intval($_GET['file_id']) : 0;
if (!$file_id) {
    header('Location: attachment.php');
    exit;
}
?>
<div id="app" class="container-fluid mt-4" v-cloak>
    <div v-if="canViewProject">
        <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold" href="#" v-if="parentProject">
                    <span class="badge badge-sm bg-label-info">#{{ parentProject.project_number || 'N/A' }}</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#parentProjectNavbar" aria-controls="parentProjectNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="parentProjectNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                    <a class="nav-link" :href="`detail.php?id=${parentProject?.id}`">建物詳細</a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link active" aria-current="page" :href="`attachment.php?id=${parentProject?.id}`">添付ファイル</a>
                    </li>
                </ul>
                </div>
            </div>
        </nav>
        <div class="row">
            <!-- Back button -->
            <div class="col-12 mb-3">
                <a :href="getBackUrl()" class="btn btn-outline-primary">
                    <i class="fa fa-arrow-left me-2"></i><span data-i18n="添付ファイル一覧へ戻る">添付ファイル一覧へ戻る</span>
                </a>
            </div>
        
        <div class="col-12" v-if="loading">
            <div class="card">
                <div class="card-body text-center py-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12" v-else-if="fileInfo && fileInfo.success">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fa fa-file me-2"></i>
                        <span data-i18n="ファイル詳細">ファイル詳細</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong><span data-i18n="名前">名前</span>:</strong>
                            <p class="mb-0">{{ fileInfo.data.original_name }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong><span data-i18n="建物プロジェクト">建物プロジェクト</span>:</strong>
                            <p class="mb-0">{{ fileInfo.data.parent_project_name }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong><span data-i18n="ファイルサイズ">ファイルサイズ</span>:</strong>
                            <p class="mb-0">{{ formatFileSize(fileInfo.data.file_size) }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong><span data-i18n="ファイルタイプ">ファイルタイプ</span>:</strong>
                            <p class="mb-0">
                                <span class="badge bg-label-primary" v-if="fileInfo.data.original_name && fileInfo.data.original_name.includes('.')">
                                    {{ fileInfo.data.original_name.split('.').pop().toUpperCase() }}
                                </span>
                                <span v-else class="text-muted">-</span>
                            </p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong><span data-i18n="MIMEタイプ">MIMEタイプ</span>:</strong>
                            <p class="mb-0">{{ fileInfo.data.mime_type || '-' }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong><span data-i18n="アップロード日時">アップロード日時</span>:</strong>
                            <p class="mb-0">{{ formatDateTime(fileInfo.data.uploaded_at) }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong><span data-i18n="アップロード者">アップロード者</span>:</strong>
                            <p class="mb-0">{{ fileInfo.data.uploaded_by_name }}</p>
                        </div>
                    </div>
                    
                    <!-- File Preview (if image or PDF) -->
                    <div v-if="isPreviewable" class="mt-4">
                        <h6><span data-i18n="プレビュー">プレビュー</span>:</h6>
                        <div class="file-preview border rounded p-3 bg-light">
                            <img v-if="isImage" :src="getSecureViewUrl()" class="img-fluid" :alt="fileInfo.data.original_name">
                            <iframe v-else-if="isPdf" :src="getSecureViewUrl()" class="w-100" style="height: 600px; border: none;"></iframe>
                            <div v-else class="text-center py-5">
                                <i class="fa fa-file fa-3x text-muted mb-3"></i>
                                <p class="text-muted"><span data-i18n="プレビューは利用できません">プレビューは利用できません</span></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex gap-2">
                        <a :href="getSecureDownloadUrl()" download class="btn btn-success">
                            <i class="fa fa-download me-2"></i><span data-i18n="ダウンロード">ダウンロード</span>
                        </a>
                        <button class="btn btn-outline-secondary" @click="copyFileUrl">
                            <i class="fa fa-link me-2"></i><span data-i18n="URLをコピー">URLをコピー</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12" v-else>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fa fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                    <h5 class="text-muted">{{ errorMessage || 'ファイルが見つかりません' }}</h5>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$view->footing();
?>
<script>
const IS_PROJECT_MANAGER = <?php echo isset($_SESSION['isProjectManager']) && $_SESSION['isProjectManager'] ? 'true' : 'false'; ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="<?=ROOT?>assets/js/axios.min.js"></script>
<script>
const { createApp } = Vue;

createApp({
    data() {
        // Convert IS_PROJECT_MANAGER to boolean
        const isManager = typeof IS_PROJECT_MANAGER !== 'undefined' 
            ? (IS_PROJECT_MANAGER === true || IS_PROJECT_MANAGER === 'true' || IS_PROJECT_MANAGER === 1)
            : false;
        
        
        return {
            fileId: <?php echo $file_id; ?>,
            fileInfo: null,
            parentProject: null,
            isProjectManager: isManager,
            loading: true,
            errorMessage: null
        }
    },
    
    computed: {
        canViewProject() {
            // Ensure we always return a boolean
            const result = !!(this.isProjectManager);
            return result;
        },
        isImage() {
            if (!this.fileInfo || !this.fileInfo.data) return false;
            const ext = this.fileInfo.data.original_name.split('.').pop().toLowerCase();
            return ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'].includes(ext);
        },
        
        isPdf() {
            if (!this.fileInfo || !this.fileInfo.data) return false;
            const ext = this.fileInfo.data.original_name.split('.').pop().toLowerCase();
            return ext === 'pdf';
        },
        
        isPreviewable() {
            return this.isImage || this.isPdf;
        }
    },
    
    methods: {
        async loadFileInfo() {
            this.loading = true;
            try {
                const response = await axios.get(`/api/index.php?model=parentproject&method=getAttachmentInfo&file_id=${this.fileId}`);
                if (response.data && response.data.success) {
                    this.fileInfo = response.data;
                    // Load parent project info if we have parent_project_id
                    if (this.fileInfo.data && this.fileInfo.data.parent_project_id) {
                        await this.loadParentProject(this.fileInfo.data.parent_project_id);
                    }
                } else {
                    this.errorMessage = response.data?.message || 'ファイル情報の取得に失敗しました';
                }
            } catch (error) {
                console.error('Error loading file info:', error);
                this.errorMessage = 'ファイル情報の取得に失敗しました';
            } finally {
                this.loading = false;
            }
        },
        
        async loadParentProject(parentProjectId) {
            try {
                const response = await axios.get(`/api/index.php?model=parentproject&method=getById&id=${parentProjectId}`);
                if (response.data) {
                    this.parentProject = response.data;
                }
            } catch (error) {
                console.error('Error loading parent project:', error);
            }
        },
        
        getBackUrl() {
            if (!this.fileInfo || !this.fileInfo.data) {
                return 'attachment.php';
            }
            const parentProjectId = this.fileInfo.data.parent_project_id;
            const folderId = this.fileInfo.data.folder_id;
            
            if (folderId) {
                return `attachment.php?id=${parentProjectId}&folder_id=${folderId}`;
            } else {
                return `attachment.php?id=${parentProjectId}`;
            }
        },
        
        // URL for inline preview (image/PDF iframe)
        getSecureViewUrl() {
            return `${window.location.origin}/api/index.php?model=parentproject&method=viewFile&file_id=${this.fileId}&inline=1`;
        },
        
        // URL for direct download
        getSecureDownloadUrl() {
            return `${window.location.origin}/api/index.php?model=parentproject&method=downloadFile&file_id=${this.fileId}`;
        },
        
        // URL for detail page (used when copying URL, same behavior as attachment list)
        getDetailPageUrl() {
            return `${window.location.origin}/parent_project/file-view.php?file_id=${this.fileId}`;
        },
        
        copyFileUrl() {
            const url = this.getDetailPageUrl();
            this.copyToClipboard(url);
            if (typeof showMessage === 'function') {
                showMessage('ファイルURLをコピーしました', false);
            } else {
                alert('ファイルURLをコピーしました');
            }
        },
        
        copyToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text);
            } else {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
            }
        },
        
        formatFileSize(bytes) {
            if (!bytes) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        },
        
        formatDateTime(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleString('ja-JP', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    },
    
    async mounted() {
        console.log('Mounted - isProjectManager:', this.isProjectManager, typeof this.isProjectManager);
        console.log('Mounted - canViewProject:', this.canViewProject);
        await this.loadFileInfo();
    }
}).mount('#app');
</script>

<style>
.file-preview {
    max-height: 800px;
    overflow: auto;
}

.file-preview img {
    max-width: 100%;
    height: auto;
}
</style>

