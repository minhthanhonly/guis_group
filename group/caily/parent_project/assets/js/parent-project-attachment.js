const { createApp } = Vue;

createApp({
    data() {
        return {
            isProjectManager: typeof IS_PROJECT_MANAGER !== 'undefined' ? IS_PROJECT_MANAGER : false,
            parentProject: null,
            loading: true,
            files: [],
            folders: [],
            breadcrumbs: [],
            currentFolderId: null,
            selectedFileIds: [],
            selectAllFiles: false,
            uploadProgress: [],
            uploading: false,
            selectedFiles: [],
            newFolderName: '',
            editingFolder: { name: '' },
            showCreateFolderModalFlag: false,
            showUploadModalFlag: false,
            showEditFolderModalFlag: false,
            showAttachmentInfoModalFlag: false,
            attachmentInfo: {
                type: null,
                data: null
            },
            loadingAttachmentInfo: false,
            sortField: 'name',
            sortDirection: 'asc'
        };
    },
    computed: {
        canViewProject() {
            return this.isProjectManager;
        },
        sortedFolders() {
            return [...this.folders].sort((a, b) => {
                const aVal = a[this.sortField] || '';
                const bVal = b[this.sortField] || '';
                const comparison = aVal.toString().localeCompare(bVal.toString(), 'ja');
                return this.sortDirection === 'asc' ? comparison : -comparison;
            });
        },
        sortedFiles() {
            return [...this.files].sort((a, b) => {
                let aVal, bVal;
                
                if (this.sortField === 'file_size') {
                    aVal = parseInt(a[this.sortField]) || 0;
                    bVal = parseInt(b[this.sortField]) || 0;
                    return this.sortDirection === 'asc' ? aVal - bVal : bVal - aVal;
                } else if (this.sortField === 'uploaded_at') {
                    aVal = new Date(a[this.sortField]);
                    bVal = new Date(b[this.sortField]);
                    return this.sortDirection === 'asc' ? aVal - bVal : bVal - aVal;
                } else {
                    aVal = a[this.sortField] || '';
                    bVal = b[this.sortField] || '';
                    const comparison = aVal.toString().localeCompare(bVal.toString(), 'ja');
                    return this.sortDirection === 'asc' ? comparison : -comparison;
                }
            });
        }
    },
    async mounted() {
        await this.loadParentProject();
        
        // Check if folder_id is provided in URL
        const folderIdFromUrl = this.getFolderIdFromUrl();
        if (folderIdFromUrl) {
            this.currentFolderId = folderIdFromUrl;
        }
        
        await this.loadFiles();
    },
    methods: {
        async loadParentProject() {
            try {
                const response = await axios.get(`/api/index.php?model=parentproject&method=getById&id=${PARENT_PROJECT_ID}`);
                if (response.data) {
                    this.parentProject = response.data;
                }
            } catch (error) {
                console.error('Error loading parent project:', error);
                this.canViewProject = false;
            }
        },

        async loadFiles() {
            this.loading = true;
            try {
                const response = await axios.get(`/api/index.php?model=parentproject&method=getByParentProject&parent_project_id=${PARENT_PROJECT_ID}&folder_id=${this.currentFolderId || ''}`);
                
                if (response.data && response.data.success) {
                    this.files = response.data.files || [];
                    this.folders = response.data.folders || [];
                    this.breadcrumbs = response.data.breadcrumbs || [];
                } else {
                    this.files = [];
                    this.folders = [];
                    this.breadcrumbs = [];
                }
            } catch (error) {
                console.error('Error loading files:', error);
                this.files = [];
                this.folders = [];
                this.breadcrumbs = [];
            } finally {
                this.loading = false;
            }
        },

        async navigateToFolder(folderId) {
            this.currentFolderId = folderId;
            this.selectedFileIds = [];
            this.selectAllFiles = false;
            
            // Update URL without page reload
            const url = new URL(window.location);
            if (folderId) {
                url.searchParams.set('folder_id', folderId);
            } else {
                url.searchParams.delete('folder_id');
            }
            window.history.pushState({}, '', url);
            
            await this.loadFiles();
        },

        sortBy(field) {
            if (this.sortField === field) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortField = field;
                this.sortDirection = 'asc';
            }
        },

        getSortIcon(field) {
            if (this.sortField !== field) {
                return 'fa-sort';
            }
            return this.sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
        },

        isFileSelected(fileId) {
            return this.selectedFileIds.includes(fileId);
        },

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
            this.selectAllFiles = this.files.length > 0 && this.selectedFileIds.length === this.files.length;
        },

        clearSelection() {
            this.selectedFileIds = [];
            this.selectAllFiles = false;
        },

        getFolderIdFromUrl() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('folder_id') ? parseInt(urlParams.get('folder_id')) : null;
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
            this.uploadProgress = [];
        },

        showEditFolderModal() {
            this.showEditFolderModalFlag = true;
        },

        closeEditFolderModal() {
            this.showEditFolderModalFlag = false;
            this.editingFolder = { name: '' };
        },

        // File operations
        async createFolder() {
            if (!this.newFolderName.trim()) return;

            try {
                const formData = new FormData();
                formData.append('name', this.newFolderName.trim());
                formData.append('parent_project_id', PARENT_PROJECT_ID);
                if (this.currentFolderId) {
                    formData.append('parent_folder_id', this.currentFolderId);
                }

                const response = await axios.post('/api/index.php?model=parentproject&method=createFolder', formData);
                
                if (response.data && response.data.success) {
                    this.closeCreateFolderModal();
                    await this.loadFiles();
                    this.showMessage('フォルダが作成されました。', 'success');
                } else {
                    showParentProjectError(response.data?.message || 'フォルダの作成に失敗しました。', response && response.data);
                }
            } catch (error) {
                console.error('Error creating folder:', error);
                showParentProjectError('フォルダの作成中にエラーが発生しました。', error);
            }
        },

        async deleteFolder(folder) {
            if (!confirm(`フォルダ「${folder.name}」を削除してもよろしいですか？\n\nフォルダ内のファイルもすべて削除されます。`)) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('folder_id', folder.id);
                const response = await axios.post('/api/index.php?model=parentproject&method=deleteFolder', formData);

                if (response.data && response.data.success) {
                    await this.loadFiles();
                    this.showMessage('フォルダが削除されました。', 'success');
                } else {
                    showParentProjectError(response.data?.message || 'フォルダの削除に失敗しました。', response && response.data);
                }
            } catch (error) {
                console.error('Error deleting folder:', error);
                showParentProjectError('フォルダの削除中にエラーが発生しました。', error);
            }
        },

        async deleteFile(file) {
            if (!confirm(`ファイル「${file.original_name}」を削除してもよろしいですか？`)) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('file_id', file.id);
                const response = await axios.post('/api/index.php?model=parentproject&method=deleteFile', formData);

                if (response.data && response.data.success) {
                    await this.loadFiles();
                    this.showMessage('ファイルが削除されました。', 'success');
                } else {
                    showParentProjectError(response.data?.message || 'ファイルの削除に失敗しました。', response && response.data);
                }
            } catch (error) {
                console.error('Error deleting file:', error);
                showParentProjectError('ファイルの削除中にエラーが発生しました。', error);
            }
        },

        async deleteSelectedFiles() {
            if (this.selectedFileIds.length === 0) return;
            
            if (!confirm(`選択された${this.selectedFileIds.length}個のファイルを削除してもよろしいですか？`)) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('file_ids', this.selectedFileIds);
                const response = await axios.post('/api/index.php?model=parentproject&method=deleteFiles', formData);

                if (response.data && response.data.success) {
                    this.clearSelection();
                    await this.loadFiles();
                    this.showMessage('選択されたファイルが削除されました。', 'success');
                } else {
                    showParentProjectError(response.data?.message || 'ファイルの削除に失敗しました。', response && response.data);
                }
            } catch (error) {
                console.error('Error deleting files:', error);
                showParentProjectError('ファイルの削除中にエラーが発生しました。', error);
            }
        },

        // File upload methods
        // All file types are allowed - no file type restrictions
        handleFileSelect(event) {
            const files = Array.from(event.target.files);
            this.selectedFiles = [...this.selectedFiles, ...files];
        },

        handleDragOver(event) {
            event.preventDefault();
            event.currentTarget.classList.add('drag-over');
        },

        handleDragLeave(event) {
            event.preventDefault();
            event.currentTarget.classList.remove('drag-over');
        },

        handleDrop(event) {
            event.preventDefault();
            event.currentTarget.classList.remove('drag-over');
            
            const files = Array.from(event.dataTransfer.files);
            this.selectedFiles = [...this.selectedFiles, ...files];
        },

        removeSelectedFile(index) {
            this.selectedFiles.splice(index, 1);
        },

        async uploadFiles() {
            if (this.selectedFiles.length === 0) return;

            this.uploading = true;
            this.uploadProgress = [];

            try {
                for (let i = 0; i < this.selectedFiles.length; i++) {
                    const file = this.selectedFiles[i];
                    await this.uploadSingleFile(file, i);
                }

                this.closeUploadModal();
                await this.loadFiles();
                this.showMessage('ファイルのアップロードが完了しました。', 'success');
            } catch (error) {
                console.error('Error uploading files:', error);
                console.error('Error details:', error.message);
                showParentProjectError('ファイルのアップロード中にエラーが発生しました。', error);
            } finally {
                this.uploading = false;
                this.uploadProgress = [];
            }
        },

        async uploadSingleFile(file, index) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('image', file);
                formData.append('parent_project_id', PARENT_PROJECT_ID);
                if (this.currentFolderId) {
                    formData.append('folder_id', this.currentFolderId);
                }

                // Add progress tracking
                this.uploadProgress.push({
                    fileName: file.name,
                    progress: 0
                });

                axios.post('/api/index.php?model=parentproject&method=uploadAttachment', formData, {
                    onUploadProgress: (progressEvent) => {
                        const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                        this.uploadProgress[index].progress = percentCompleted;
                    }
                })
                .then(response => {
                    console.log('Upload response:', response);
                    if (response.data && response.data.success) {
                        resolve(response);
                    } else {
                        console.error('Upload failed response:', response.data);
                        reject(new Error(response.data?.message || 'Upload failed'));
                    }
                })
                .catch(error => {
                    console.error('Upload axios error:', error);
                    console.error('Error response:', error.response);
                    reject(error);
                });
            });
        },

        // Utility methods
        getFileIcon(fileName) {
            if (!fileName) return 'fa fa-file text-muted';
            
            const extension = fileName.split('.').pop().toLowerCase();
            
            const iconMap = {
                // Images
                'jpg': 'fa fa-file-image text-primary',
                'jpeg': 'fa fa-file-image text-primary',
                'png': 'fa fa-file-image text-primary',
                'gif': 'fa fa-file-image text-primary',
                'svg': 'fa fa-file-image text-primary',
                'webp': 'fa fa-file-image text-primary',
                
                // Documents
                'pdf': 'fa fa-file-pdf text-danger',
                'doc': 'fa fa-file-word text-primary',
                'docx': 'fa fa-file-word text-primary',
                'xls': 'fa fa-file-excel text-success',
                'xlsx': 'fa fa-file-excel text-success',
                'ppt': 'fa fa-file-powerpoint text-warning',
                'pptx': 'fa fa-file-powerpoint text-warning',
                'txt': 'fa fa-file-alt text-muted',
                
                // Archives
                'zip': 'fa fa-file-archive text-warning',
                'rar': 'fa fa-file-archive text-warning',
                '7z': 'fa fa-file-archive text-warning',
                'tar': 'fa fa-file-archive text-warning',
                'gz': 'fa fa-file-archive text-warning',
                
                // Videos
                'mp4': 'fa fa-file-video text-info',
                'avi': 'fa fa-file-video text-info',
                'mov': 'fa fa-file-video text-info',
                'wmv': 'fa fa-file-video text-info',
                'flv': 'fa fa-file-video text-info',
                
                // Audio
                'mp3': 'fa fa-file-audio text-success',
                'wav': 'fa fa-file-audio text-success',
                'flac': 'fa fa-file-audio text-success',
                'aac': 'fa fa-file-audio text-success',
                
                // Code
                'html': 'fa fa-file-code text-warning',
                'css': 'fa fa-file-code text-info',
                'js': 'fa fa-file-code text-warning',
                'php': 'fa fa-file-code text-primary',
                'py': 'fa fa-file-code text-success',
                'java': 'fa fa-file-code text-danger',
                'cpp': 'fa fa-file-code text-primary',
                'c': 'fa fa-file-code text-primary'
            };
            
            return iconMap[extension] || 'fa fa-file text-muted';
        },

        formatFileSize(bytes) {
            if (!bytes) return '0 B';
            
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        },

        formatDateTime(dateString) {
            if (!dateString) return '';
            
            const date = new Date(dateString);
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            
            return `${year}/${month}/${day} ${hours}:${minutes}`;
        },

        getSecureViewUrl(file) {
            // Use absolute path so copied URL is correct
            return `/parent_project/file-view.php?file_id=${file.id}`;
        },

        getSecureDownloadUrl(file) {
            return `/api/index.php?model=parentproject&method=downloadFile&file_id=${file.id}&token=${this.generateSecureToken(file)}`;
        },

        generateSecureToken(file) {
            // Simple token generation - in production, use a more secure method
            return btoa(`${file.id}-${PARENT_PROJECT_ID}-${Date.now()}`).replace(/[^a-zA-Z0-9]/g, '');
        },

        // URL copy methods
        copyFileUrl(file) {
            const url = window.location.origin + this.getSecureViewUrl(file);
            this.copyToClipboard(url);
            this.showMessage('ファイルURLをコピーしました。', 'success');
        },

        copyFolderUrl(folder) {
            const url = `${window.location.origin}${window.location.pathname}?id=${PARENT_PROJECT_ID}&folder=${folder.id}`;
            this.copyToClipboard(url);
            this.showMessage('フォルダURLをコピーしました。', 'success');
        },

        copySelectedFileUrls() {
            const urls = this.selectedFileIds.map(fileId => {
                const file = this.files.find(f => f.id === fileId);
                return window.location.origin + this.getSecureViewUrl(file);
            });
            
            this.copyToClipboard(urls.join('\n'));
            this.showMessage(`${urls.length}個のファイルURLをコピーしました。`, 'success');
        },

        copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
            }
        },

        editFolder(folder) {
            this.editingFolder = { ...folder };
            this.showEditFolderModal();
        },

        async updateFolder() {
            if (!this.editingFolder.name.trim()) return;

            try {
                const formData = new FormData();
                formData.append('folder_id', this.editingFolder.id);
                formData.append('name', this.editingFolder.name.trim());
                const response = await axios.post('/api/index.php?model=parentproject&method=updateFolder', formData);

                if (response.data && response.data.success) {
                    this.closeEditFolderModal();
                    await this.loadFiles();
                    this.showMessage('フォルダ名が更新されました。', 'success');
                } else {
                    showParentProjectError(response.data?.message || 'フォルダ名の更新に失敗しました。', response && response.data);
                }
            } catch (error) {
                console.error('Error updating folder:', error);
                showParentProjectError('フォルダ名の更新中にエラーが発生しました。', error);
            }
        },

        showMessage(message, type = 'info') {
            // Simple message display - you can enhance this with a toast library
            if (type === 'success') {
                showMessage(message);
            } else if (type === 'error') {
                showMessage(message, true);
            } else {
               alert(message);
            }
        },
        
        async showAttachmentInfo(id, type) {
            this.loadingAttachmentInfo = true;
            this.showAttachmentInfoModalFlag = true;
            
            try {
                const params = type === 'file' ? `file_id=${id}` : `folder_id=${id}`;
                const response = await axios.get(`/api/index.php?model=parentproject&method=getAttachmentInfo&${params}`);
                
                if (response.data && response.data.success) {
                    this.attachmentInfo = {
                        type: response.data.type,
                        data: response.data.data
                    };
                } else {
                    this.showMessage(response.data?.message || '情報の取得に失敗しました', 'error');
                    this.closeAttachmentInfoModal();
                }
            } catch (error) {
                console.error('Error loading attachment info:', error);
                this.showMessage('情報の取得に失敗しました', 'error');
                this.closeAttachmentInfoModal();
            } finally {
                this.loadingAttachmentInfo = false;
            }
        },
        
        closeAttachmentInfoModal() {
            this.showAttachmentInfoModalFlag = false;
            this.attachmentInfo = {
                type: null,
                data: null
            };
        },
        
        downloadFolderZip(folderId) {
            const url = `/api/index.php?model=parentproject&method=downloadFolderZip&folder_id=${folderId}&parent_project_id=${PARENT_PROJECT_ID}`;
            window.open(url, '_blank');
        },
        
        downloadCurrentFolderZip() {
            // Download current folder (or root if no folder selected)
            if (this.currentFolderId) {
                const url = `/api/index.php?model=parentproject&method=downloadFolderZip&folder_id=${this.currentFolderId}&parent_project_id=${PARENT_PROJECT_ID}`;
                window.open(url, '_blank');
            } else {
                // Download root (all files in parent project)
                const url = `/api/index.php?model=parentproject&method=downloadFolderZip&parent_project_id=${PARENT_PROJECT_ID}`;
                window.open(url, '_blank');
            }
        }
    }
}).mount('#app');
