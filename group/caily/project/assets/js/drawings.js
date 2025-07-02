const { createApp } = Vue;

createApp({
    data() {
        return {
            project: null,
            drawings: [],
            selectedDrawings: [],
            searchQuery: '',
            statusFilter: '',
            
            // Form data
            editingDrawing: {
                id: null,
                name: '',
                status: 'draft',
                file_path: ''
            },
            
            // Bulk operations
            bulkStatus: 'draft',
            
            // Import data
            importFiles: [],
            clipboardText: '',
            
            // Loading states
            loading: false
        }
    },
    
    computed: {
        canViewProject() {
            return true; // Implement permission check
        },
        
        filteredDrawings() {
            let filtered = this.drawings;
            
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter(drawing => 
                    drawing.name.toLowerCase().includes(query)
                );
            }
            
            if (this.statusFilter) {
                filtered = filtered.filter(drawing => drawing.status === this.statusFilter);
            }
            
            return filtered;
        },
        
        isAllSelected() {
            return this.filteredDrawings.length > 0 && this.selectedDrawings.length === this.filteredDrawings.length;
        }
    },
    
    mounted() {
        this.loadProject();
        this.loadDrawings();
    },
    
    methods: {
        // Project loading
        async loadProject() {
            try {
                const response = await axios.get(`/api/index.php?model=project&method=getById&id=${PROJECT_ID}`);
                if (response.data && response.data.id) {
                    this.project = response.data;
                }
            } catch (error) {
                console.error('Error loading project:', error);
                this.showError('プロジェクトの読み込みに失敗しました');
            }
        },
        
        // Drawings loading
        async loadDrawings() {
            this.loading = true;
            try {
                const response = await axios.get(`/api/index.php?model=drawing&method=list&project_id=${PROJECT_ID}`);
                if (Array.isArray(response.data)) {
                    this.drawings = response.data;
                } else {
                    this.showError('ファイルの読み込みに失敗しました');
                }
            } catch (error) {
                console.error('Error loading drawings:', error);
                this.showError('ファイルの読み込みに失敗しました');
            } finally {
                this.loading = false;
            }
        },
        
        // Drag and Drop handlers for modal
        onDrop(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const files = Array.from(e.dataTransfer.files);
            this.processDroppedFiles(files);
        },
        
        onFileSelect(event) {
            const files = Array.from(event.target.files);
            this.processDroppedFiles(files);
        },
        
        processDroppedFiles(files) {
            files.forEach(file => {
                const fileInfo = {
                    name: file.name,
                    file_path: '' // Only save filename, not full path
                };
                this.importFiles.push(fileInfo);
            });
        },
        
        // Modal management
        openAddModal() {
            this.resetForm();
            const modal = new bootstrap.Modal(document.getElementById('drawingModal'));
            modal.show();
        },
        
        closeAddModal() {
            this.showAddModal = false;
            this.resetForm();
        },
        
        openEditModal(drawing) {
            this.editingDrawing = { ...drawing };
            const modal = new bootstrap.Modal(document.getElementById('drawingModal'));
            modal.show();
        },
        
        closeEditModal() {
            this.showEditModal = false;
            this.resetForm();
        },
        
        parseClipboardText() {
            if (!this.clipboardText.trim()) {
                this.showError('クリップボードのテキストを入力してください');
                return;
            }
            
            const lines = this.clipboardText.split('\n').filter(line => line.trim());
            this.importFiles = [];
            
            lines.forEach(line => {
                // Remove quotes and trim
                const cleanLine = line.replace(/^["']|["']$/g, '').trim();
                if (cleanLine) {
                    const fileName = this.extractFileName(cleanLine);
                    const fileInfo = {
                        name: fileName,
                        file_path: '' // Only save filename, not full path
                    };
                    this.importFiles.push(fileInfo);
                }
            });
            
            if (this.importFiles.length === 0) {
                this.showError('有効なファイルパスが見つかりませんでした');
            }
        },
        
        extractFileName(filePath) {
            // Extract filename from full path
            const parts = filePath.split(/[\\\/]/);
            return parts[parts.length - 1] || filePath;
        },
        
        removeImportFile(index) {
            this.importFiles.splice(index, 1);
        },
        
        async importFiles() {
            if (this.importFiles.length === 0) {
                this.showError('インポートするファイルがありません');
                return;
            }
            
            try {
                let successCount = 0;
                let errorCount = 0;
                
                for (const fileInfo of this.importFiles) {
                    try {
                        const response = await axios.post('/api/index.php', {
                            model: 'drawing',
                            method: 'add',
                            project_id: PROJECT_ID,
                            name: fileInfo.name,
                            status: 'draft' // Default status for imported files
                        });
                        
                        if (response.data && response.data.status === 'success') {
                            successCount++;
                        } else {
                            errorCount++;
                        }
                    } catch (error) {
                        console.error('Error importing file:', fileInfo.name, error);
                        errorCount++;
                    }
                }
                
                if (successCount > 0) {
                    this.showSuccess(`${successCount}個のファイルをインポートしました`);
                    // Close modal using Bootstrap
                    const modal = bootstrap.Modal.getInstance(document.getElementById('importModal'));
                    if (modal) {
                        modal.hide();
                    }
                    this.importFiles = [];
                    this.clipboardText = '';
                    this.loadDrawings();
                }
                
                if (errorCount > 0) {
                    this.showError(`${errorCount}個のファイルのインポートに失敗しました`);
                }
                
            } catch (error) {
                console.error('Error during import:', error);
                this.showError('インポートに失敗しました');
            }
        },
        
        async saveDrawing() {
            if (!this.editingDrawing.name) {
                this.showError('ファイル名を入力してください');
                return;
            }
            
            try {
                let response;
                if (this.editingDrawing.id) {
                    // Update existing drawing
                    response = await axios.post('/api/index.php', {
                        model: 'drawing',
                        method: 'edit',
                        id: this.editingDrawing.id,
                        name: this.editingDrawing.name,
                        status: this.editingDrawing.status
                    });
                } else {
                    // Create new drawing
                    response = await axios.post('/api/index.php', {
                        model: 'drawing',
                        method: 'add',
                        project_id: PROJECT_ID,
                        name: this.editingDrawing.name,
                        status: this.editingDrawing.status
                    });
                }
                
                if (response.data && response.data.status === 'success') {
                    this.showSuccess(this.editingDrawing.id ? 'ファイルを更新しました' : 'ファイルを追加しました');
                    // Close modal using Bootstrap
                    const modal = bootstrap.Modal.getInstance(document.getElementById('drawingModal'));
                    if (modal) {
                        modal.hide();
                    }
                    this.loadDrawings();
                } else {
                    this.showError(response.data?.message || '保存に失敗しました');
                }
            } catch (error) {
                console.error('Error saving drawing:', error);
                this.showError('保存に失敗しました');
            }
        },
        
        async deleteDrawing(id) {
            if (!confirm('このファイルを削除しますか？')) {
                return;
            }
            
            try {
                const response = await axios.post('/api/index.php', {
                    model: 'drawing',
                    method: 'delete',
                    id: id
                });
                
                if (response.data && response.data.status === 'success') {
                    this.showSuccess('ファイルを削除しました');
                    this.loadDrawings();
                } else {
                    this.showError(response.data?.message || '削除に失敗しました');
                }
            } catch (error) {
                console.error('Error deleting drawing:', error);
                this.showError('削除に失敗しました');
            }
        },
        
        async updateStatus(id, status) {
            try {
                const response = await axios.post('/api/index.php', {
                    model: 'drawing',
                    method: 'updateStatus',
                    id: id,
                    status: status
                });
                
                if (response.data && response.data.status === 'success') {
                    this.showSuccess('ステータスを更新しました');
                    this.loadDrawings();
                } else {
                    this.showError(response.data?.message || 'ステータスの更新に失敗しました');
                }
            } catch (error) {
                console.error('Error updating status:', error);
                this.showError('ステータスの更新に失敗しました');
            }
        },
        
        toggleSelectAll() {
            if (this.isAllSelected) {
                this.selectedDrawings = [];
            } else {
                this.selectedDrawings = this.filteredDrawings.map(d => d.id);
            }
        },
        
        toggleSelectDrawing(id) {
            const index = this.selectedDrawings.indexOf(id);
            if (index > -1) {
                this.selectedDrawings.splice(index, 1);
            } else {
                this.selectedDrawings.push(id);
            }
        },
        
        bulkChangeStatus() {
            if (this.selectedDrawings.length === 0) {
                this.showError('ファイルを選択してください');
                return;
            }
            const modal = new bootstrap.Modal(document.getElementById('bulkStatusModal'));
            modal.show();
        },
        
        async confirmBulkStatusChange() {
            try {
                const response = await axios.post('/api/index.php', {
                    model: 'drawing',
                    method: 'bulkUpdateStatus',
                    ids: JSON.stringify(this.selectedDrawings),
                    status: this.bulkStatus
                });
                
                if (response.data && response.data.status === 'success') {
                    this.showSuccess('ステータスを一括更新しました');
                    // Close modal using Bootstrap
                    const modal = bootstrap.Modal.getInstance(document.getElementById('bulkStatusModal'));
                    if (modal) {
                        modal.hide();
                    }
                    this.selectedDrawings = [];
                    this.loadDrawings();
                } else {
                    this.showError(response.data?.message || '一括更新に失敗しました');
                }
            } catch (error) {
                console.error('Error bulk updating status:', error);
                this.showError('一括更新に失敗しました');
            }
        },
        
        async bulkDelete() {
            if (this.selectedDrawings.length === 0) {
                this.showError('ファイルを選択してください');
                return;
            }
            
            if (!confirm(`${this.selectedDrawings.length}個のファイルを削除しますか？`)) {
                return;
            }
            
            try {
                const response = await axios.post('/api/index.php', {
                    model: 'drawing',
                    method: 'bulkDelete',
                    ids: JSON.stringify(this.selectedDrawings)
                });
                
                if (response.data && response.data.status === 'success') {
                    this.showSuccess('ファイルを一括削除しました');
                    this.selectedDrawings = [];
                    this.loadDrawings();
                } else {
                    this.showError(response.data?.message || '一括削除に失敗しました');
                }
            } catch (error) {
                console.error('Error bulk deleting:', error);
                this.showError('一括削除に失敗しました');
            }
        },
        
        clearFilters() {
            this.searchQuery = '';
            this.statusFilter = '';
        },
        
        formatDateTime(dateString) {
            return new Date(dateString).toLocaleString('ja-JP');
        },
        
        downloadDrawing(drawing) {
            if (drawing.file_path) {
                const link = document.createElement('a');
                link.href = this.getDrawingDownloadUrl(drawing);
                link.download = drawing.name;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        },
        
        getDrawingDownloadUrl(drawing) {
            if (drawing.file_path) {
                return `/storage/download.php?file=${encodeURIComponent(drawing.file_path)}`;
            }
            return '#';
        },
        
        getAvatarSrc(user) {
            if (user && user.avatar) {
                return `/assets/upload/avatar/${user.avatar}`;
            }
            return '';
        },
        
        handleAvatarError(user) {
            // Fallback to initials
        },
        
        getInitials(name) {
            if (!name) return '';
            return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
        },
        
        showSuccess(message) {
            // Implement success notification
            alert(message);
        },
        
        showError(message) {
            // Implement error notification
            alert(message);
        },
        
        resetForm() {
            this.editingDrawing = {
                id: null,
                name: '',
                status: 'draft',
                file_path: ''
            };
        }
    }
}).mount('#app'); 