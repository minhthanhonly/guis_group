const { createApp } = Vue;

createApp({
    data() {
        return {
            projectId: typeof PROJECT_ID !== 'undefined' ? PROJECT_ID : this.getProjectIdFromUrl(),
            project: null,
            folders: [],
            files: [],
            breadcrumbs: [],
            currentFolderId: null,
            loading: false,
            uploading: false,
            uploadProgress: [],
            
            // Modal states
            showCreateFolderModalFlag: false,
            showUploadModalFlag: false,
            showEditFolderModalFlag: false,
            
            // Form data
            newFolderName: '',
            selectedFiles: [],
            editingFolder: {
                id: null,
                name: ''
            },
            
            // Selection for bulk operations
            selectedFileIds: [],
            selectAllFiles: false,
            
            // User permissions
            userPermissions: null,
            managers: [],
            members: []
        }
    },
    
    computed: {
        canViewProject() {
            if (USER_ROLE == 'administrator') return true;
            if (!this.project) return false;
            if (this.project.created_by && this.project.created_by == USER_ID) return true;
            if (this.managers && this.managers.some(m => String(m.user_id) === String(USER_AUTH_ID))) return true;
            if (this.members && this.members.some(m => String(m.user_id) === String(USER_AUTH_ID))) return true;
            if (this.hasPermission('project_manager')) return true;
            if (this.hasPermission('project_director')) return true;
            return false;
        },
        
        canManageProject() {
            if (USER_ROLE == 'administrator') return true;
            if (this.managers && this.managers.some(m => String(m.user_id) === String(USER_AUTH_ID))) return true;
            return this.hasPermission('project_manager');
        }
    },
    
    methods: {
        getProjectIdFromUrl() {
            const urlParams = new URLSearchParams(window.location.search);
            const id = urlParams.get('project_id');
            if (id) return parseInt(id);
            
            console.error('Could not determine project ID from URL');
            return null;
        },
        
        getFolderIdFromUrl() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('folder_id') ? parseInt(urlParams.get('folder_id')) : null;
        },
        
        async getUserPermissions(departmentId) {
            try {
                const response = await axios.get(`/api/index.php?model=department&method=get_user_permission_by_department&department_id=${departmentId}`);
                this.userPermissions = response.data;
                return this.userPermissions;
            } catch (error) {
                console.error('Error loading user permissions:', error);
                this.userPermissions = null;
                return null;
            }
        },
        
        hasPermission(permission) {
            if (USER_ROLE == 'administrator') return true;
            if (!this.userPermissions) return false;
            return this.userPermissions[permission] == 1;
        },
        
        async loadProject() {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getById&id=${this.projectId}`);
                this.project = response.data;
                await this.getUserPermissions(this.project.department_id);
                await this.loadMembers();
            } catch (error) {
                console.error('Error loading project:', error);
                alert('プロジェクトの読み込みに失敗しました。');
            }
        },
        
        async loadMembers() {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getMembers&project_id=${this.projectId}`);
                const members = response.data || [];
                this.managers = members.filter(m => m && m.role === 'manager');
                const managerIds = this.managers.map(m => m.user_id);
                this.members = members.filter(m => m && m.role === 'member' && !managerIds.includes(m.user_id));
            } catch (error) {
                console.error('Error loading members:', error);
            }
        },
        
        async loadFoldersAndFiles() {
            this.loading = true;
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getAttachments&project_id=${this.projectId}&folder_id=${this.currentFolderId || ''}`);
                if (response.data && response.data.status === 'success') {
                    this.folders = response.data.folders || [];
                    this.files = response.data.files || [];
                    this.breadcrumbs = response.data.breadcrumbs || [];
                } else {
                    this.folders = [];
                    this.files = [];
                    this.breadcrumbs = [];
                }
                
                // Clear selection when loading new folder
                this.clearSelection();
            } catch (error) {
                console.error('Error loading attachments:', error);
                this.folders = [];
                this.files = [];
                this.breadcrumbs = [];
                this.clearSelection();
            } finally {
                this.loading = false;
            }
        },
        
        async navigateToFolder(folderId) {
            this.currentFolderId = folderId;
            
            // Update URL without page reload
            const url = new URL(window.location);
            if (folderId) {
                url.searchParams.set('folder_id', folderId);
            } else {
                url.searchParams.delete('folder_id');
            }
            window.history.pushState({}, '', url);
            
            await this.loadFoldersAndFiles();
        },
        
        // Modal methods
        showCreateFolderModal() {
            this.newFolderName = '';
            this.showCreateFolderModalFlag = true;
        },
        
        closeCreateFolderModal() {
            this.showCreateFolderModalFlag = false;
            this.newFolderName = '';
        },
        
        showUploadModal() {
            this.selectedFiles = [];
            this.showUploadModalFlag = true;
        },
        
        closeUploadModal() {
            this.showUploadModalFlag = false;
            this.selectedFiles = [];
            if (this.$refs.fileInput) {
                this.$refs.fileInput.value = '';
            }
        },
        
        editFolder(folder) {
            this.editingFolder = {
                id: folder.id,
                name: folder.name
            };
            this.showEditFolderModalFlag = true;
        },
        
        closeEditFolderModal() {
            this.showEditFolderModalFlag = false;
            this.editingFolder = {
                id: null,
                name: ''
            };
        },
        
        // Folder operations
        async createFolder() {
            if (!this.newFolderName.trim()) return;
            
            try {
                const formData = new FormData();
                formData.append('project_id', this.projectId);
                formData.append('parent_folder_id', this.currentFolderId || '');
                formData.append('name', this.newFolderName.trim());
                
                const response = await axios.post('/api/index.php?model=project&method=createFolder', formData);
                
                if (response.data && response.data.status === 'success') {
                    this.showNotification('フォルダが作成されました', 'success');
                    this.closeCreateFolderModal();
                    await this.loadFoldersAndFiles();
                } else {
                    this.showNotification('フォルダの作成に失敗しました: ' + (response.data.message || ''), 'error');
                }
            } catch (error) {
                console.error('Error creating folder:', error);
                this.showNotification('フォルダの作成に失敗しました', 'error');
            }
        },
        
        async updateFolder() {
            if (!this.editingFolder.name.trim()) return;
            
            try {
                const formData = new FormData();
                formData.append('id', this.editingFolder.id);
                formData.append('name', this.editingFolder.name.trim());
                
                const response = await axios.post('/api/index.php?model=project&method=updateFolder', formData);
                
                if (response.data && response.data.status === 'success') {
                    this.showNotification('フォルダ名が更新されました', 'success');
                    this.closeEditFolderModal();
                    await this.loadFoldersAndFiles();
                } else {
                    this.showNotification('フォルダ名の更新に失敗しました: ' + (response.data.message || ''), 'error');
                }
            } catch (error) {
                console.error('Error updating folder:', error);
                this.showNotification('フォルダ名の更新に失敗しました', 'error');
            }
        },
        
        async deleteFolder(folder) {
            if (!confirm(`フォルダ「${folder.name}」を削除しますか？\n※フォルダ内のすべてのファイルとサブフォルダも削除されます。`)) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('id', folder.id);
                
                const response = await axios.post('/api/index.php?model=project&method=deleteFolder', formData);
                
                if (response.data && response.data.status === 'success') {
                    this.showNotification('フォルダが削除されました', 'success');
                    await this.loadFoldersAndFiles();
                } else {
                    this.showNotification('フォルダの削除に失敗しました: ' + (response.data.message || ''), 'error');
                }
            } catch (error) {
                console.error('Error deleting folder:', error);
                this.showNotification('フォルダの削除に失敗しました', 'error');
            }
        },
        
        // File operations
        handleFileSelect(event) {
            const files = Array.from(event.target.files);
            this.selectedFiles = files.filter(file => {
                if (file.size > 100 * 1024 * 1024) { // 100MB limit
                    this.showNotification(`ファイル「${file.name}」は100MBを超えています`, 'error');
                    return false;
                }
                return true;
            }).map(file => ({
                file: file,
                name: file.name,
                size: file.size,
                progress: 0,
                status: 'pending'
            }));
        },
        
        removeSelectedFile(index) {
            this.selectedFiles.splice(index, 1);
        },
        
        // Drag and drop methods
        handleDragOver(event) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'copy';
            event.currentTarget.classList.add('drag-over');
        },
        
        handleDragLeave(event) {
            event.preventDefault();
            event.currentTarget.classList.remove('drag-over');
        },
        
        handleDrop(event) {
            event.preventDefault();
            event.currentTarget.classList.remove('drag-over');
            
            if (!this.canManageProject) {
                this.showNotification('ファイルをアップロードする権限がありません', 'error');
                return;
            }
            
            const files = Array.from(event.dataTransfer.files);
            if (files.length === 0) return;
            
            // Filter files by size and add to selected files
            const validFiles = files.filter(file => {
                if (file.size > 100 * 1024 * 1024) { // 100MB limit
                    this.showNotification(`ファイル「${file.name}」は100MBを超えています`, 'error');
                    return false;
                }
                return true;
            }).map(file => ({
                file: file,
                name: file.name,
                size: file.size,
                progress: 0,
                status: 'pending'
            }));
            
            if (validFiles.length === 0) return;
            
            // Add to selected files list instead of uploading directly
            this.selectedFiles = [...this.selectedFiles, ...validFiles];
            
            // Open upload modal if not already open
            if (!this.showUploadModalFlag) {
                this.showUploadModal();
            }
        },
        

        
        async uploadFiles() {
            if (this.selectedFiles.length === 0) return;
            
            this.uploading = true;
            this.uploadProgress = [];
            
            try {
                const uploadUrl = '/api/index.php?model=project&method=uploadAttachment';
                
                for (const fileObj of this.selectedFiles) {
                    // Add to progress tracking
                    this.uploadProgress.push({
                        fileName: fileObj.name,
                        progress: 0
                    });
                    
                    try {
                        const additionalData = {
                            project_id: this.projectId,
                            folder_id: this.currentFolderId || ''
                        };
                        
                        let response;
                        if (window.swManager && window.swManager.swRegistration && window.swManager.swRegistration.active) {
                            console.log('Using Service Worker for upload:', fileObj.name);
                            // Use Service Worker
                            response = await window.swManager.uploadFile(fileObj.file, uploadUrl, additionalData);
                        } else {
                            console.log('Using fallback upload for:', fileObj.name);
                            // Fallback upload
                            response = await this.fallbackUpload(fileObj.file, uploadUrl, additionalData);
                        }
                        
                        if (response.success) {
                            this.updateUploadProgress(fileObj.name, 100);
                            console.log('File uploaded successfully:', fileObj.name);
                        } else {
                            this.showNotification(`ファイル「${fileObj.name}」のアップロードに失敗しました: ${response.error || 'Unknown error'}`, 'error');
                        }
                    } catch (error) {
                        console.error('Error uploading file:', fileObj.name, error);
                        this.showNotification(`ファイル「${fileObj.name}」のアップロードに失敗しました`, 'error');
                    }
                }
                
                // Clear progress after a delay
                setTimeout(() => {
                    this.uploadProgress = [];
                }, 2000);
                
                this.closeUploadModal();
                await this.loadFoldersAndFiles();
                this.showNotification('ファイルのアップロードが完了しました', 'success');
                
            } catch (error) {
                console.error('Error in upload process:', error);
                this.showNotification('アップロード処理でエラーが発生しました', 'error');
            } finally {
                this.uploading = false;
            }
        },
        
        async fallbackUpload(file, uploadUrl, additionalData) {
            console.log('Starting fallback upload for:', file.name, 'to:', uploadUrl);
            
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('image', file);
                
                // Add additional data
                Object.keys(additionalData).forEach(key => {
                    formData.append(key, additionalData[key]);
                    console.log('Adding form data:', key, '=', additionalData[key]);
                });
                
                const xhr = new XMLHttpRequest();
                
                xhr.upload.addEventListener('progress', (event) => {
                    if (event.lengthComputable) {
                        const progress = Math.round((event.loaded / event.total) * 100);
                        console.log('Upload progress for', file.name, ':', progress + '%');
                        this.updateUploadProgress(file.name, progress);
                    }
                });
                
                xhr.addEventListener('load', () => {
                    console.log('Upload completed for', file.name, 'Status:', xhr.status);
                    console.log('Response:', xhr.responseText);
                    
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            console.log('Parsed response:', response);
                            resolve(response);
                        } catch (e) {
                            console.error('Failed to parse response:', e, xhr.responseText);
                            resolve({ success: false, error: 'Invalid response format' });
                        }
                    } else {
                        console.error('HTTP Error:', xhr.status, xhr.statusText);
                        resolve({ success: false, error: `HTTP ${xhr.status}: ${xhr.statusText}` });
                    }
                });
                
                xhr.addEventListener('error', (event) => {
                    console.error('Network error during upload:', event);
                    reject(new Error('Network error'));
                });
                
                xhr.addEventListener('timeout', () => {
                    console.error('Upload timeout');
                    reject(new Error('Upload timeout'));
                });
                
                xhr.open('POST', uploadUrl);
                xhr.timeout = 300000; // 5 minutes timeout
                xhr.send(formData);
            });
        },
        
        updateUploadProgress(fileName, progress) {
            const upload = this.uploadProgress.find(u => u.fileName === fileName);
            if (upload) {
                upload.progress = progress;
            }
        },
        
        async deleteFile(file) {
            if (!confirm(`ファイル「${file.file_name}」を削除しますか？`)) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('id', file.id);
                
                const response = await axios.post('/api/index.php?model=project&method=deleteAttachment', formData);
                
                if (response.data && response.data.status === 'success') {
                    this.showNotification('ファイルが削除されました', 'success');
                    await this.loadFoldersAndFiles();
                } else {
                    this.showNotification('ファイルの削除に失敗しました: ' + (response.data.message || ''), 'error');
                }
            } catch (error) {
                console.error('Error deleting file:', error);
                this.showNotification('ファイルの削除に失敗しました', 'error');
            }
        },
        
        // Bulk selection methods
        toggleFileSelection(fileId) {
            const index = this.selectedFileIds.indexOf(fileId);
            if (index > -1) {
                this.selectedFileIds.splice(index, 1);
            } else {
                this.selectedFileIds.push(fileId);
            }
            this.updateSelectAllState();
        },
        
        toggleSelectAll() {
            if (this.selectAllFiles) {
                this.selectedFileIds = this.files.map(file => file.id);
            } else {
                this.selectedFileIds = [];
            }
        },
        
        updateSelectAllState() {
            if (this.files.length === 0) {
                this.selectAllFiles = false;
                return;
            }
            this.selectAllFiles = this.selectedFileIds.length === this.files.length;
        },
        
        isFileSelected(fileId) {
            return this.selectedFileIds.includes(fileId);
        },
        
        async deleteSelectedFiles() {
            if (this.selectedFileIds.length === 0) {
                this.showNotification('削除するファイルを選択してください', 'warning');
                return;
            }
            
            if (!confirm(`選択された${this.selectedFileIds.length}個のファイルを削除しますか？`)) {
                return;
            }
            
            try {
                let successCount = 0;
                let errorCount = 0;
                
                for (const fileId of this.selectedFileIds) {
                    try {
                        const formData = new FormData();
                        formData.append('id', fileId);
                        
                        const response = await axios.post('/api/index.php?model=project&method=deleteAttachment', formData);
                        
                        if (response.data && response.data.status === 'success') {
                            successCount++;
                        } else {
                            errorCount++;
                        }
                    } catch (error) {
                        console.error('Error deleting file:', fileId, error);
                        errorCount++;
                    }
                }
                
                // Clear selection
                this.selectedFileIds = [];
                this.selectAllFiles = false;
                
                // Reload files
                await this.loadFoldersAndFiles();
                
                // Show result notification
                if (errorCount === 0) {
                    this.showNotification(`${successCount}個のファイルが削除されました`, 'success');
                } else if (successCount === 0) {
                    this.showNotification('ファイルの削除に失敗しました', 'error');
                } else {
                    this.showNotification(`${successCount}個のファイルが削除されました（${errorCount}個は失敗）`, 'warning');
                }
            } catch (error) {
                console.error('Error in bulk delete:', error);
                this.showNotification('ファイルの削除処理でエラーが発生しました', 'error');
            }
        },
        
        async copySelectedFileUrls() {
            if (this.selectedFileIds.length === 0) {
                this.showNotification('コピーするファイルを選択してください', 'warning');
                return;
            }
            
            try {
                // Get selected files
                const selectedFiles = this.files.filter(file => this.selectedFileIds.includes(file.id));
                
                // Create URLs for selected files
                const urls = selectedFiles.map(file => {
                    return this.getSecureDownloadUrl(file);
                });
                
                // Join URLs with newlines
                const urlText = urls.join('\n');
                
                // Copy to clipboard
                this.copyToClipboard(urlText);
                
                this.showNotification(`${selectedFiles.length}個のファイルURLをコピーしました`, 'success');
            } catch (error) {
                console.error('Error copying URLs:', error);
                this.showNotification('URLのコピーに失敗しました', 'error');
            }
        },
        
        clearSelection() {
            this.selectedFileIds = [];
            this.selectAllFiles = false;
        },
        
        // Utility methods
        formatDateTime(datetime) {
            if (!datetime) return '-';
            return moment(datetime).format('YYYY/MM/DD HH:mm');
        },
        
        formatFileSize(bytes) {
            if (!bytes) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        
        getFileIcon(fileName) {
            const ext = fileName.split('.').pop().toLowerCase();
            const iconMap = {
                // Documents
                'pdf': 'fa fa-file-pdf text-danger',
                'doc': 'fa fa-file-word text-primary',
                'docx': 'fa fa-file-word text-primary',
                'xls': 'fa fa-file-excel text-success',
                'xlsx': 'fa fa-file-excel text-success',
                'ppt': 'fa fa-file-powerpoint text-warning',
                'pptx': 'fa fa-file-powerpoint text-warning',
                'txt': 'fa fa-file-alt text-secondary',
                
                // Images
                'jpg': 'fa fa-file-image text-info',
                'jpeg': 'fa fa-file-image text-info',
                'png': 'fa fa-file-image text-info',
                'gif': 'fa fa-file-image text-info',
                'svg': 'fa fa-file-image text-info',
                'bmp': 'fa fa-file-image text-info',
                
                // Archives
                'zip': 'fa fa-file-archive text-dark',
                'rar': 'fa fa-file-archive text-dark',
                '7z': 'fa fa-file-archive text-dark',
                'tar': 'fa fa-file-archive text-dark',
                'gz': 'fa fa-file-archive text-dark',
                
                // Videos
                'mp4': 'fa fa-file-video text-purple',
                'avi': 'fa fa-file-video text-purple',
                'mov': 'fa fa-file-video text-purple',
                'wmv': 'fa fa-file-video text-purple',
                
                // Audio
                'mp3': 'fa fa-file-audio text-success',
                'wav': 'fa fa-file-audio text-success',
                'flac': 'fa fa-file-audio text-success',
                
                // Code
                'html': 'fa fa-file-code text-warning',
                'css': 'fa fa-file-code text-warning',
                'js': 'fa fa-file-code text-warning',
                'php': 'fa fa-file-code text-warning',
                'py': 'fa fa-file-code text-warning',
                'java': 'fa fa-file-code text-warning',
                'cpp': 'fa fa-file-code text-warning',
                'c': 'fa fa-file-code text-warning'
            };
            
            return iconMap[ext] || 'fa fa-file text-secondary';
        },
        
        // URL copying methods
        copyFolderUrl(folder) {
            const url = `${window.location.origin}${window.location.pathname}?project_id=${this.projectId}&folder_id=${folder.id}`;
            this.copyToClipboard(url);
            this.showNotification('フォルダURLをコピーしました', 'success');
        },
        
        copyFileUrl(file) {
            const url = this.getSecureDownloadUrl(file);
            this.copyToClipboard(url);
            this.showNotification('ファイルURLをコピーしました', 'success');
        },
        
        getSecureViewUrl(file) {
            return `${window.location.origin}/api/index.php?model=project&method=viewAttachment&file_id=${file.id}`;
        },
        
        getSecureDownloadUrl(file) {
            return `${window.location.origin}/api/index.php?model=project&method=downloadAttachment&file_id=${file.id}`;
        },
        
        copyToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text);
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                document.execCommand('copy');
                textArea.remove();
            }
        },
        
        showNotification(message, type = 'info') {
            if (typeof showMessage === 'function') {
                showMessage(message, type === 'error');
            } else {
                alert(message);
            }
        }
    },
    
    async mounted() {
        await this.loadProject();
        
        // Check if folder_id is provided in URL
        const folderIdFromUrl = this.getFolderIdFromUrl();
        if (folderIdFromUrl) {
            this.currentFolderId = folderIdFromUrl;
        }
        
        await this.loadFoldersAndFiles();
        
        // Initialize Service Worker Manager
        if (window.swManager) {
            // Service Worker is already initialized
            console.log('Service Worker Manager already available');
        } else {
            // Initialize Service Worker Manager
            window.swManager = new ServiceWorkerManager();
            await window.swManager.init();
        }
        
        // Add upload event listeners
        window.addEventListener('uploadProgress', (event) => {
            const { progress, fileName } = event.detail;
            this.updateUploadProgress(fileName, progress);
        });
        
        window.addEventListener('uploadSuccess', (event) => {
            const { data, fileName } = event.detail;
            console.log('Upload success:', fileName, data);
        });
        
        window.addEventListener('uploadError', (event) => {
            const { error, fileName } = event.detail;
            console.error('Upload error:', fileName, error);
            this.showNotification(`ファイル「${fileName}」のアップロードに失敗しました`, 'error');
        });
    }
}).mount('#app'); 