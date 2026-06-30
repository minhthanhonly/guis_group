<?php
require_once('../application/loader.php');
$view->heading('ファイル詳細');

// Get file ID from URL
$file_id = isset($_GET['file_id']) ? intval($_GET['file_id']) : 0;
if (!$file_id) {
    header('Location: attachment.php');
    exit;
}
if($_SESSION['show_project'] == 0){
    echo '<div class="container-fluid mt-4"><div class="alert alert-danger">権限がありません。</div></div>';
    exit;
}
?>
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
                    <a class="nav-link" :href="`detail.php?id=${project?.id}`"><span data-i18n="概要">概要</span></a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link" :href="`task.php?project_id=${project?.id}`"><span data-i18n="タスク">タスク</span><span class="badge badge-sm ms-1 rounded-pill">{{ project?.task_count }}</span></a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link" :href="`gantt.php?project_id=${project?.id}`"><span data-i18n="ガントチャート">ガントチャート</span></a>
                    </li>
                    <li class="nav-item" v-if="canViewBusinessDocuments">
                    <a class="nav-link" :href="`drawings.php?project_id=${project?.id}`"><span data-i18n="図面">図面</span><span class="badge badge-sm bg-info ms-1 rounded-pill">{{ project?.drawing_count }}</span></a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link active text-primary" aria-current="page" :href="`attachment.php?project_id=${project?.id}`"><span data-i18n="添付ファイル">添付ファイル</span></a>
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
                            <strong><span data-i18n="プロジェクト">プロジェクト</span>:</strong>
                            <p class="mb-0">{{ fileInfo.data.project_name }}</p>
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

<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="<?=ROOT?>assets/js/axios.min.js"></script>
<script>
const { createApp } = Vue;

createApp({
    data() {
        return {
            fileId: <?php echo $file_id; ?>,
            fileInfo: null,
            project: null,
            permission: {},
            loading: true,
            errorMessage: null
        }
    },
    
    computed: {
        canViewBusinessDocuments() {
            if (typeof USER_ROLE !== 'undefined' && USER_ROLE === 'administrator') {
                return true;
            }
            if (!this.permission) return false;
            if (this.permission.can_manage_project) return true;
            const rule = this.permission.rule;
            if (!rule) return false;
            return rule.project_director_stat == 1
                || rule.project_director_view == 1
                || rule.project_director_edit == 1
                || rule.project_director == 1;
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
                const response = await axios.get(`/api/index.php?model=project&method=getAttachmentInfo&file_id=${this.fileId}`);
                if (response.data && response.data.success) {
                    this.fileInfo = response.data;
                    // Load project info if we have project_id
                    if (this.fileInfo.data && this.fileInfo.data.project_id) {
                        await this.loadProject(this.fileInfo.data.project_id);
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
        
        async loadProject(projectId) {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getById&id=${projectId}`);
                if (response.data && response.data.success) {
                    this.project = response.data.data;
                }
                await this.loadPermission(projectId);
            } catch (error) {
                console.error('Error loading project:', error);
            }
        },

        async loadPermission(projectId) {
            try {
                const response = await axios.get('/api/index.php?model=task&method=getPermission&project_id=' + projectId);
                this.permission = response.data || {};
            } catch (error) {
                console.error('Error loading permission:', error);
            }
        },
        
        getBackUrl() {
            if (!this.fileInfo || !this.fileInfo.data) {
                return 'attachment.php';
            }
            const projectId = this.fileInfo.data.project_id;
            const folderId = this.fileInfo.data.folder_id;
            
            if (folderId) {
                return `attachment.php?project_id=${projectId}&folder_id=${folderId}`;
            } else {
                return `attachment.php?project_id=${projectId}`;
            }
        },
        
        getSecureViewUrl() {
            return `${window.location.origin}/api/index.php?model=project&method=viewAttachment&file_id=${this.fileId}`;
        },
        
        getSecureDownloadUrl() {
            return `${window.location.origin}/api/index.php?model=project&method=downloadAttachment&file_id=${this.fileId}`;
        },
        
        copyFileUrl() {
            const url = this.getSecureViewUrl();
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

