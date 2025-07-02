const { createApp } = Vue;

createApp({
    data() {
        return {
            project: null,
            drawings: [],
            selectedDrawings: [],
            searchQuery: '',
            statusFilter: '',
            
            // Sorting
            sortField: 'name',
            sortDirection: 'asc',
            
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
            loading: false,
            
            // Drawing statuses for dropdown
            drawingStatuses: [
                { value: 'draft', label: '下書き', color: 'secondary' },
                { value: 'review', label: 'レビュー中', color: 'warning' },
                { value: 'revision', label: '修正中', color: 'info' },
                { value: 'revised', label: '修正済', color: 'primary' },
                { value: 'approved', label: '承認済み', color: 'success' },
                { value: 'rejected', label: '却下', color: 'danger' }
            ],
            
            // Drag selection
            isDragging: false,
            dragStartIndex: -1,
            dragEndIndex: -1,
            hoveredRow: null
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
            
            // Sort the filtered results
            filtered.sort((a, b) => {
                let aValue, bValue;
                
                // Handle special sorting for file type
                if (this.sortField === 'file_type') {
                    aValue = a.name ? a.name.split('.').pop().toUpperCase() : '';
                    bValue = b.name ? b.name.split('.').pop().toUpperCase() : '';
                } else {
                    aValue = a[this.sortField];
                    bValue = b[this.sortField];
                }
                
                // Handle null/undefined values
                if (aValue === null || aValue === undefined) aValue = '';
                if (bValue === null || bValue === undefined) bValue = '';
                
                // Convert to string for comparison
                aValue = String(aValue);
                bValue = String(bValue);
                
                // Use localeCompare for proper Japanese character sorting
                let comparison = aValue.localeCompare(bValue, 'ja');
                
                return this.sortDirection === 'asc' ? comparison : -comparison;
            });
            
            return filtered;
        },
        
        isAllSelected() {
            return this.filteredDrawings.length > 0 && this.selectedDrawings.length === this.filteredDrawings.length;
        }
    },
    
    mounted() {
        this.loadProject();
        this.loadDrawings();
        
        // Add click outside listener to close dropdowns
        document.addEventListener('click', (event) => {
            // Don't close if clicking on dropdown toggle button
            if (event.target.closest('.dropdown-toggle')) {
                return;
            }
            // Close if clicking outside dropdown
            if (!event.target.closest('.dropdown')) {
                this.closeAllDropdowns();
            }
        });
        
        // Add global mouse event listeners for drag selection
        document.addEventListener('mousemove', (event) => {
            if (this.isDragging) {
                // Find the row under the mouse
                const row = event.target.closest('tr');
                if (row && row.dataset.index !== undefined) {
                    const index = parseInt(row.dataset.index);
                    // Only update if the index is valid and different
                    if (index >= 0 && index < this.filteredDrawings.length && index !== this.dragEndIndex) {
                        this.updateDragSelection(event, index);
                    }
                }
            }
        });
        
        document.addEventListener('mouseup', () => {
            this.endDragSelection();
        });
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
            const modal = bootstrap.Modal.getInstance(document.getElementById('drawingModal'));
            if (modal) {
                modal.hide();
            }
            this.resetForm();
        },
        
        openEditModal(drawing) {
            this.editingDrawing = { ...drawing };
            const modal = new bootstrap.Modal(document.getElementById('drawingModal'));
            modal.show();
        },
        
        closeEditModal() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('drawingModal'));
            if (modal) {
                modal.hide();
            }
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
        
        async performImport() {
            if (this.importFiles.length === 0) {
                this.showError('インポートするファイルがありません');
                return;
            }
            
            try {
                let successCount = 0;
                let errorCount = 0;
                let replacedCount = 0;
                
                for (const fileInfo of this.importFiles) {
                    try {
                        const formData = new FormData();
                        formData.append('project_id', PROJECT_ID);
                        formData.append('name', fileInfo.name);
                        formData.append('status', 'draft');
                        
                        const response = await axios.post('/api/index.php?model=drawing&method=add', formData);
                        
                        if (response.data && response.data.status === 'success') {
                            successCount++;
                            // Check if this was a replacement
                            if (response.data.replaced) {
                                replacedCount++;
                            }
                        } else {
                            errorCount++;
                        }
                    } catch (error) {
                        console.error('Error importing file:', fileInfo.name, error);
                        errorCount++;
                    }
                }
                
                if (successCount > 0) {
                    let message = `${successCount}個のファイルをインポートしました`;
                    if (replacedCount > 0) {
                        message += ` (${replacedCount}個の重複ファイルを置き換えました)`;
                    }
                    this.showSuccess(message);
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
                    const formData = new FormData();
                    formData.append('id', this.editingDrawing.id);
                    formData.append('name', this.editingDrawing.name);
                    formData.append('status', this.editingDrawing.status);
                    
                    response = await axios.post('/api/index.php?model=drawing&method=edit', formData);
                } else {
                    // Create new drawing
                    const formData = new FormData();
                    formData.append('project_id', PROJECT_ID);
                    formData.append('name', this.editingDrawing.name);
                    formData.append('status', this.editingDrawing.status);
                    
                    response = await axios.post('/api/index.php?model=drawing&method=add', formData);
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
            const result = await Swal.fire({
                title: 'ファイルを削除しますか？',
                text: "この操作は元に戻せません！",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: '削除する',
                cancelButtonText: 'キャンセル'
            });

            if (!result.isConfirmed) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('id', id);
                
                const response = await axios.post('/api/index.php?model=drawing&method=delete', formData);
                
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
                // Update local data immediately for better UX
                const drawing = this.drawings.find(d => d.id === id);
                if (drawing) {
                    drawing.status = status;
                }
                
                // Close the dropdown
                this.closeAllDropdowns();
                
                const formData = new FormData();
                formData.append('id', id);
                formData.append('status', status);
                
                const response = await axios.post('/api/index.php?model=drawing&method=updateStatus', formData);
                
                if (response.data && response.data.status === 'success') {
                    // Success - local data already updated
                } else {
                    // Revert local change on error
                    if (drawing) {
                        drawing.status = response.data?.original_status || 'draft';
                    }
                    this.showError(response.data?.message || 'ステータスの更新に失敗しました');
                }
            } catch (error) {
                console.error('Error updating status:', error);
                // Revert local change on error
                const drawing = this.drawings.find(d => d.id === id);
                if (drawing) {
                    drawing.status = 'draft'; // Fallback to draft
                }
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
                const formData = new FormData();
                formData.append('ids', JSON.stringify(this.selectedDrawings));
                formData.append('status', this.bulkStatus);
                
                const response = await axios.post('/api/index.php?model=drawing&method=bulkUpdateStatus', formData);
                
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
            
            const result = await Swal.fire({
                title: `${this.selectedDrawings.length}個のファイルを削除しますか？`,
                text: "この操作は元に戻せません！",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: '削除する',
                cancelButtonText: 'キャンセル'
            });

            if (!result.isConfirmed) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('ids', JSON.stringify(this.selectedDrawings));
                
                const response = await axios.post('/api/index.php?model=drawing&method=bulkDelete', formData);
                
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

        // Bulk copy selected drawing names
        bulkCopyNames() {
            if (this.selectedDrawings.length === 0) {
                this.showError('ファイルを選択してください');
                return;
            }
            
            // Get the names of selected drawings
            const selectedNames = this.drawings
                .filter(drawing => this.selectedDrawings.includes(drawing.id))
                .map(drawing => drawing.name);
            
            // Join names with newlines
            const namesText = selectedNames.join('\n');
            
            // Copy to clipboard
            this.copyToClipboard(namesText);
        },

        // Bulk assign current user to selected drawings
        async bulkAssign() {
            if (this.selectedDrawings.length === 0) {
                this.showError('ファイルを選択してください');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('ids', JSON.stringify(this.selectedDrawings));
                
                const response = await axios.post('/api/index.php?model=drawing&method=bulkAssignUser', formData);
                
                if (response.data && response.data.status === 'success') {
                    this.showSuccess(response.data.message || '一括割り当てが完了しました');
                    this.selectedDrawings = [];
                    this.loadDrawings();
                } else {
                    this.showError(response.data?.message || '一括割り当てに失敗しました');
                }
            } catch (error) {
                console.error('Error bulk assigning:', error);
                this.showError('一括割り当てに失敗しました');
            }
        },

        // Bulk unassign current user from selected drawings
        async bulkUnassign() {
            if (this.selectedDrawings.length === 0) {
                this.showError('ファイルを選択してください');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('ids', JSON.stringify(this.selectedDrawings));
                
                const response = await axios.post('/api/index.php?model=drawing&method=bulkUnassignUser', formData);
                
                if (response.data && response.data.status === 'success') {
                    this.showSuccess(response.data.message || '一括割り当て解除が完了しました');
                    this.selectedDrawings = [];
                    this.loadDrawings();
                } else {
                    this.showError(response.data?.message || '一括割り当て解除に失敗しました');
                }
            } catch (error) {
                console.error('Error bulk unassigning:', error);
                this.showError('一括割り当て解除に失敗しました');
            }
        },

        // Clear all selections
        clearSelection() {
            this.selectedDrawings = [];
        },

        // Assign current user to drawing
        async assignDrawing(drawingId) {
            try {
                const formData = new FormData();
                formData.append('drawing_id', drawingId);
                
                const response = await axios.post('/api/index.php?model=drawing&method=assignUser', formData);
                
                if (response.data && response.data.status === 'success') {
                    this.showSuccess('割り当てが完了しました');
                    this.loadDrawings(); // Reload to get updated data
                } else {
                    this.showError(response.data?.message || '割り当てに失敗しました');
                }
            } catch (error) {
                console.error('Error assigning drawing:', error);
                this.showError('割り当てに失敗しました');
            }
        },

        // Unassign current user from drawing
        async unassignDrawing(drawingId) {
            try {
                const formData = new FormData();
                formData.append('drawing_id', drawingId);
                
                const response = await axios.post('/api/index.php?model=drawing&method=unassignUser', formData);
                
                if (response.data && response.data.status === 'success') {
                    this.showSuccess('割り当てを解除しました');
                    this.loadDrawings(); // Reload to get updated data
                } else {
                    this.showError(response.data?.message || '割り当て解除に失敗しました');
                }
            } catch (error) {
                console.error('Error unassigning drawing:', error);
                this.showError('割り当て解除に失敗しました');
            }
        },

        // Check if current user is assigned to drawing
        isUserAssigned(drawing) {
            if (!drawing.created_by) return false;
            const userIds = drawing.created_by.split(',').map(id => id.trim());
            return userIds.includes(USER_ID.toString());
        },
        
        clearFilters() {
            this.searchQuery = '';
            this.statusFilter = '';
        },
        
        sortBy(field) {
            if (this.sortField === field) {
                // Toggle direction if same field
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                // Set new field and default to ascending
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
        
        getStatusLabel(status) {
            const s = this.drawingStatuses.find(s => s.value === status);
            return s ? s.label : status;
        },
        
        getStatusButtonClass(status) {
            const s = this.drawingStatuses.find(s => s.value === status);
            return `btn-${s?.color || 'secondary'}`;
        },
        
        closeAllDropdowns() {
            // Close all open dropdowns
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
                const button = menu.previousElementSibling;
                if (button && button.classList.contains('dropdown-toggle')) {
                    button.setAttribute('aria-expanded', 'false');
                }
            });
        },
        
        // Drag selection methods
        startDragSelection(event, index) {
            this.isDragging = true;
            this.dragStartIndex = index;
            this.dragEndIndex = index;
            
            // Prevent text selection during drag
            event.preventDefault();
            
            // Add drag selection class to body
            document.body.classList.add('drag-selecting');
        },
        
        updateDragSelection(event, index) {
            if (!this.isDragging) return;
            
            this.dragEndIndex = index;
            this.updateSelectionRange();
        },
        
        endDragSelection() {
            if (!this.isDragging) return;
            
            this.isDragging = false;
            this.dragStartIndex = -1;
            this.dragEndIndex = -1;
            
            // Remove drag selection class from body
            document.body.classList.remove('drag-selecting');
        },
        
        updateSelectionRange() {
            if (this.dragStartIndex === -1 || this.dragEndIndex === -1) return;
            
            const start = Math.min(this.dragStartIndex, this.dragEndIndex);
            const end = Math.max(this.dragStartIndex, this.dragEndIndex);
            
            // Get the filtered drawings in the current view
            const visibleDrawings = this.filteredDrawings;
            
            // Validate indices
            if (start < 0 || end >= visibleDrawings.length) return;
            
            // Clear current selection and select the range
            this.selectedDrawings = [];
            for (let i = start; i <= end; i++) {
                if (visibleDrawings[i] && visibleDrawings[i].id) {
                    this.selectedDrawings.push(visibleDrawings[i].id);
                }
            }
        },
        
        formatDateTime(dateString) {
            if (!dateString) return '-';
            return moment(dateString).format('MM月DD日 HH:mm');
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
            return getAvatarName(name);
        },

        // Copy text to clipboard
        copyToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                // Use modern clipboard API
                navigator.clipboard.writeText(text).then(() => {
                    this.showSuccess('ファイル名をコピーしました');
                }).catch(err => {
                    console.error('Failed to copy: ', err);
                    this.fallbackCopyToClipboard(text);
                });
            } else {
                // Fallback for older browsers
                this.fallbackCopyToClipboard(text);
            }
        },

        // Fallback copy method for older browsers
        fallbackCopyToClipboard(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                this.showSuccess('ファイル名をコピーしました');
            } catch (err) {
                console.error('Fallback copy failed: ', err);
                this.showError('コピーに失敗しました');
            }
            
            document.body.removeChild(textArea);
        },
        
        showSuccess(message) {
            showMessage(message);
        },
        
        showError(message) {
            showMessage(message, true);
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