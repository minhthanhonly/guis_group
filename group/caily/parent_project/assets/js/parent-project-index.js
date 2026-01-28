const { createApp } = Vue;

createApp({
    data() {
        return {
            parentProjects: [],
            searchKeyword: '',
            statusFilter: 'all',
            favoritesOnly: false,
            currentPage: 1,
            pageSize: 50,
            totalRecords: 0,
            loading: false,
            isProjectManager: typeof IS_PROJECT_MANAGER !== 'undefined' ? IS_PROJECT_MANAGER : false,
            sortColumn: 'created_at',
            sortDirection: 'DESC', // 'ASC' or 'DESC'
            selectedParentProject: null,
            childProjectsForModal: [],
            loadingChildProjects: false,
            // Column visibility
            availableColumns: [
                { key: 'project_number', label: '管理番号', visible: true },
                { key: 'project_name', label: 'お施主様名', visible: true },
                { key: 'construction_number', label: '工事番号', visible: true },
                { key: 'company_name', label: '会社名', visible: true },
                { key: 'scale', label: '規模', visible: false },
                { key: 'type1', label: '種類1', visible: false },
                { key: 'type2', label: '種類2', visible: false },
                { key: 'child_project_count', label: '件数', visible: true },
                { key: 'created_by_name', label: '作成者', visible: true },
                { key: 'notes', label: 'メモ', visible: true },
                { key: 'request_date', label: '依頼日', visible: false },
                { key: 'created_at', label: '作成日', visible: false },
            ],
            // Notes for parent projects (メモ)
            notes: [],
            showNoteModal: false,
            isNoteEditMode: false,
            editingNote: {
                id: null,
                title: '',
                content: '',
                is_important: false,
                user_id: null
            },
            currentNoteParentProjectId: null,
            noteContextMenuVisible: false,
            noteContextMenuX: 0,
            noteContextMenuY: 0,
            contextMenuParentProjectId: null,
            columnVisibilityStorageKey: 'parent_project_column_visibility',
            statuses: [
                { value: 'draft', label: '下書き', color: 'secondary' },
                { value: 'under_contract', label: '契約中', color: 'info' },
                { value: 'in_progress', label: '進行中', color: 'primary' },
                { value: 'completed', label: '完了', color: 'success' },
                { value: 'cancelled', label: 'キャンセル', color: 'danger' }
            ]
        }
    },
    computed: {
        filteredParentProjects() {
            return this.parentProjects;
        },
        totalPages() {
            return Math.ceil(this.totalRecords / this.pageSize);
        },
        startRecord() {
            return (this.currentPage - 1) * this.pageSize + 1;
        },
        endRecord() {
            return Math.min(this.currentPage * this.pageSize, this.totalRecords);
        },
        visiblePages() {
            const pages = [];
            const maxVisible = 5;
            let start = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
            let end = Math.min(this.totalPages, start + maxVisible - 1);
            
            if (end - start + 1 < maxVisible) {
                start = Math.max(1, end - maxVisible + 1);
            }
            
            for (let i = start; i <= end; i++) {
                pages.push(i);
            }
            
            return pages;
        }
    },
    methods: {
        async loadParentProjects() {
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    draw: 1,
                    start: (this.currentPage - 1) * this.pageSize,
                    length: this.pageSize,
                    search: this.searchKeyword,
                    status: this.statusFilter === 'all' ? '' : this.statusFilter,
                    favorites_only: this.favoritesOnly ? '1' : '0',
                    order_column: this.sortColumn,
                    order_dir: this.sortDirection
                });
                
                const response = await axios.get(`/api/index.php?model=parentproject&method=list&${params.toString()}`);
                if (response.data && response.data.data) {
                    this.parentProjects = response.data.data;
                    this.totalRecords = response.data.recordsTotal || 0;
                }
            } catch (error) {
                console.error('Error loading parent projects:', error);
                showMessage('親プロジェクトの読み込みに失敗しました。', true);
            } finally {
                this.loading = false;
                // Re-translate i18n elements after Vue updates DOM
                this.$nextTick(() => {
                    this.translateI18n();
                });
            }
        },
        // Column visibility helpers
        isColumnVisible(key) {
            const col = this.availableColumns.find(c => c.key === key);
            // Nếu không tìm thấy, mặc định hiển thị
            return !col || col.visible;
        },
        loadColumnVisibilityFromStorage() {
            try {
                const raw = localStorage.getItem(this.columnVisibilityStorageKey);
                if (!raw) return;
                const stored = JSON.parse(raw);
                this.availableColumns = this.availableColumns.map(col => {
                    if (Object.prototype.hasOwnProperty.call(stored, col.key)) {
                        return { ...col, visible: !!stored[col.key] };
                    }
                    return col;
                });
            } catch (e) {
                console.error('Error loading column visibility:', e);
            }
        },
        saveColumnVisibilityToStorage() {
            try {
                const map = {};
                this.availableColumns.forEach(col => {
                    map[col.key] = !!col.visible;
                });
                localStorage.setItem(this.columnVisibilityStorageKey, JSON.stringify(map));
            } catch (e) {
                console.error('Error saving column visibility:', e);
            }
        },
        onColumnVisibilityChange() {
            this.saveColumnVisibilityToStorage();
        },
        // ----- Notes (メモ) helpers & actions -----
        parseNotesDisplay(notesDisplay) {
            if (!notesDisplay || typeof notesDisplay !== 'string') return [];
            return notesDisplay
                .split(' | ')
                .map(raw => raw.trim())
                .filter(raw => raw !== '')
                .map(raw => {
                    const idx = raw.indexOf('::');
                    if (idx === -1) return { id: null, content: raw };
                    const id = raw.substring(0, idx);
                    const content = raw.substring(idx + 2);
                    return { id, content };
                });
        },
        onNotesContextMenu(event, project) {
            this.contextMenuParentProjectId = project.id;
            this.noteContextMenuX = event.pageX;
            this.noteContextMenuY = event.pageY;
            this.noteContextMenuVisible = true;
        },
        addNoteFromContextMenu() {
            if (!this.contextMenuParentProjectId) return;
            this.openNoteModalFromList(this.contextMenuParentProjectId, null);
            this.noteContextMenuVisible = false;
        },
        async loadNotesForParentProject(parentProjectId) {
            try {
                const response = await axios.get(`/api/index.php?model=parentproject&method=getNotes&parent_project_id=${parentProjectId}`);
                if (response.data && response.data.status === 'success') {
                    this.notes = response.data.data || [];
                } else {
                    this.notes = [];
                }
            } catch (error) {
                console.error('Error loading parent project notes:', error);
                this.notes = [];
            }
        },
        openNoteModalFromList(parentProjectId, noteContent = null) {
            this.currentNoteParentProjectId = parentProjectId;
            this.showNoteModal = true;
            this.isNoteEditMode = true;
            this.editingNote = {
                id: null,
                title: '',
                content: '',
                is_important: false,
                user_id: null
            };
            this.loadNotesForParentProject(parentProjectId).then(() => {
                if (noteContent) {
                    const trimmed = noteContent.trim();
                    const match = this.notes.find(n => (n.content || '').trim() === trimmed);
                    if (match) {
                        this.editingNote = {
                            id: match.id,
                            title: match.title,
                            content: match.content,
                            is_important: match.is_important == 1,
                            user_id: match.user_id
                        };
                    } else {
                        this.editingNote.content = noteContent;
                    }
                }
            });
        },
        openNoteEdit(project, note) {
            this.currentNoteParentProjectId = project.id;
            this.showNoteModal = true;
            this.isNoteEditMode = true;
            this.editingNote = {
                id: note.id,
                title: '',
                content: note.content,
                is_important: false,
                user_id: note.user_id
            };
        },
        closeNoteModal() {
            this.showNoteModal = false;
            this.isNoteEditMode = false;
            this.editingNote = {
                id: null,
                title: '',
                content: '',
                is_important: false,
                user_id: null
            };
        },
        async saveNote() {
            const rawContent = (this.editingNote.content || '').trim();
            if (!rawContent) {
                showMessage('内容を入力してください', true);
                return;
            }
            let title = (this.editingNote.title || '').trim();
            if (!title) {
                title = rawContent.split(/\r?\n/)[0].slice(0, 50) || 'メモ';
            }
            try {
                const formData = new FormData();
                formData.append('parent_project_id', this.currentNoteParentProjectId);
                formData.append('title', title);
                formData.append('content', rawContent);
                formData.append('is_important', this.editingNote.is_important ? 1 : 0);
                let response;
                if (this.editingNote.id) {
                    formData.append('id', this.editingNote.id);
                    response = await axios.post('/api/index.php?model=parentproject&method=updateNote', formData);
                } else {
                    response = await axios.post('/api/index.php?model=parentproject&method=addNote', formData);
                }
                if (response.data && response.data.status === 'success') {
                    showMessage('メモが保存されました');
                    this.closeNoteModal();
                    this.loadParentProjects();
                } else {
                    showMessage(response.data?.error || 'メモの保存に失敗しました', true);
                }
            } catch (error) {
                console.error('Error saving parent project note:', error);
                showMessage('メモの保存に失敗しました', true);
            }
        },
        async deleteNoteFromList(project, noteId) {
            if (!confirm('このメモを削除しますか？')) return;
            try {
                const formData = new FormData();
                formData.append('id', noteId);
                const response = await axios.post('/api/index.php?model=parentproject&method=deleteNote', formData);
                if (response.data && response.data.status === 'success') {
                    showMessage('メモが削除されました');
                    this.loadParentProjects();
                } else {
                    showMessage(response.data?.error || 'メモの削除に失敗しました', true);
                }
            } catch (error) {
                console.error('Error deleting parent project note:', error);
                showMessage('メモの削除に失敗しました', true);
            }
        },
        translateI18n() {
            // Call localize function if it exists (from main.js)
            if (typeof localize === 'function') {
                localize();
            } else if (typeof i18next !== 'undefined' && i18next.isInitialized) {
                // Fallback: manually translate all data-i18n elements
                const i18nList = document.querySelectorAll('[data-i18n]');
                i18nList.forEach(function (item) {
                    item.innerHTML = i18next.t(item.dataset.i18n);
                });
            }
        },
        translatePlaceholder(key) {
            // Translate placeholder text using i18next
            if (typeof i18next !== 'undefined' && i18next.isInitialized) {
                return i18next.t(key) || key;
            }
            return key;
        },
        sortBy(column) {
            // Map frontend column names to database column names
            const columnMap = {
                'project_number': 'project_number',
                'project_name': 'project_name',
                'construction_number': 'construction_number',
                'company_name': 'company_name',
                'scale': 'scale',
                'type1': 'type1',
                'type2': 'type2',
                'request_date': 'request_date',
                'child_project_count': 'child_project_count',
                'created_by_name': 'created_by_name',
                'created_at': 'created_at'
            };
            
            const dbColumn = columnMap[column] || column;
            
            if (this.sortColumn === dbColumn) {
                // Toggle direction if same column
                this.sortDirection = this.sortDirection === 'ASC' ? 'DESC' : 'ASC';
            } else {
                // New column, default to ascending
                this.sortColumn = dbColumn;
                this.sortDirection = 'ASC';
            }
            // Reset to first page when sorting
            this.currentPage = 1;
            // Reload data with new sort
            this.loadParentProjects();
        },
        getSortIcon(column) {
            // Map frontend column names to database column names
            const columnMap = {
                'project_number': 'project_number',
                'project_name': 'project_name',
                'construction_number': 'construction_number',
                'company_name': 'company_name',
                'scale': 'scale',
                'type1': 'type1',
                'type2': 'type2',
                'request_date': 'request_date',
                'child_project_count': 'child_project_count',
                'created_by_name': 'created_by_name',
                'created_at': 'created_at'
            };
            
            const dbColumn = columnMap[column] || column;
            
            if (this.sortColumn !== dbColumn) {
                return 'fa-sort text-muted';
            }
            return this.sortDirection === 'ASC' ? 'fa-sort-up' : 'fa-sort-down';
        },
        onSearch() {
            this.currentPage = 1;
            this.loadParentProjects();
        },
        clearSearch() {
            this.searchKeyword = '';
            this.onSearch();
        },
        onStatusFilterChange() {
            this.currentPage = 1;
            this.loadParentProjects();
        },
        onFavoritesFilterChange() {
            this.currentPage = 1;
            this.loadParentProjects();
        },
        async openChildProjectsWindow(project) {
            this.selectedParentProject = project;
            this.childProjectsForModal = [];
            this.loadingChildProjects = true;
            try {
                const response = await axios.get(`/api/index.php?model=parentproject&method=getChildProjects&parent_project_id=${project.id}`);
                if (response.data) {
                    this.childProjectsForModal = response.data;
                }
            } catch (error) {
                console.error('Error loading child projects for parent project:', project.id, error);
                showMessage('子プロジェクトの読み込みに失敗しました。', true);
            } finally {
                this.loadingChildProjects = false;
                const modalEl = document.getElementById('childProjectsModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.show();
                }
            }
        },
        async toggleFavorite(project) {
            try {
                const formData = new FormData();
                formData.append('parent_project_id', project.id);
                
                const response = await axios.post('/api/index.php?model=parentproject&method=toggleFavorite', formData);
                
                if (response.data && response.data.status === 'success') {
                    // Update the project's favorite status (convert boolean to number for consistency)
                    project.is_favorite = response.data.is_favorite ? 1 : 0;
                } else {
                    showMessage(response.data?.message || '操作に失敗しました。', true);
                }
            } catch (error) {
                console.error('Error toggling favorite:', error);
                showMessage('操作に失敗しました。', true);
            }
        },
        async clearAllFavorites() {
            try {
                const result = await Swal.fire({
                    title: '確認',
                    text: 'すべてのお気に入りを削除しますか？',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: '削除',
                    cancelButtonText: 'キャンセル'
                });
                
                if (result.isConfirmed) {
                    const response = await axios.post('/api/index.php?model=parentproject&method=clearAllFavorites');
                    
                    if (response.data && response.data.status === 'success') {
                        // Uncheck the favorites filter
                        this.favoritesOnly = false;
                        // Reload the list to refresh favorite status
                        this.loadParentProjects();
                    } else {
                        showMessage(response.data?.message || '削除に失敗しました。', true);
                    }
                }
            } catch (error) {
                console.error('Error clearing all favorites:', error);
                showMessage('削除に失敗しました。', true);
            }
        },
        changePage(page) {
            if (page >= 1 && page <= this.totalPages) {
                this.currentPage = page;
                this.loadParentProjects();
            }
        },
        getStatusLabel(status) {
            const s = this.statuses.find(s => s.value === status);
            return s ? s.label : status;
        },
        getStatusBadgeClass(status) {
            const s = this.statuses.find(s => s.value === status);
            return `bg-${s?.color || 'secondary'}`;
        },
        getOrderTypeBadgeClass(orderType) {
            const type = (orderType || '').trim().toLowerCase();
            switch (type) {
                case '修正':
                    return 'bg-warning';
                case '新規':
                    return 'bg-primary';
                default:
                    return 'bg-info';
            }
        },
        getManagerName(managerString) {
            if (!managerString) return '';
            const parts = managerString.split(':');
            return parts[1] || parts[0] || '';
        },
        getManagerImage(managerString) {
            if (!managerString) return '';
            const parts = managerString.split(':');
            return parts[2] || '';
        },
        getManagerInitials(managerString) {
            if (!managerString) return '?';
            const parts = managerString.split(':');
            const name = parts[1] || parts[0] || '';
            return this.getInitials(name);
        },
        getRemainingManagers(managerIdString) {
            if (!managerIdString) return '';
            const managers = managerIdString.split('|').filter(m => m.trim() !== '');
            if (managers.length <= 1) return '';
            const remaining = managers.slice(1).map(manager => {
                const parts = manager.split(':');
                return parts[1] || parts[0] || '';
            }).filter(name => name).join(', ');
            return remaining;
        },
        getInitials(name) {
            if (!name) return '?';
            if (typeof getAvatarName === 'function') {
                return getAvatarName(name);
            }
            try {
                const hasJapanese = /[\u3040-\u309f\u30a0-\u30ff\u4e00-\u9faf]/.test(name);
                if (hasJapanese) {
                    return name.substring(0, 2);
                } else {
                    const words = name.trim().split(' ');
                    const lastWord = words[words.length - 1];
                    return lastWord;
                }
            } catch (error) {
                return name.charAt(0).toUpperCase();
            }
        },
        formatDate(date) {
            if (!date) return '-';
            return moment(date).format('Y年M月D日 HH:mm');
        },
        formatDate2(date) {
            if (!date) return '-';
            return moment(date).format('Y年M月D日');
        },
        formatDateTime(date) {
            if (!date) return '-';
            return moment(date).format('YYYY/MM/DD HH:mm');
        },
        formatPrice(amount) {
            const n = Number(amount) || 0;
            return '¥' + n.toLocaleString('ja-JP');
        },
        async toggleChildFavorite(child) {
            try {
                const formData = new FormData();
                formData.append('project_id', child.id);
                
                const response = await axios.post('/api/index.php?model=project&method=toggleFavorite', formData);
                
                if (response.data && response.data.status === 'success') {
                    child.is_favorite = response.data.is_favorite ? 1 : 0;
                } else {
                    showMessage(response.data?.message || '操作に失敗しました。', true);
                }
            } catch (error) {
                console.error('Error toggling child project favorite:', error);
                showMessage('操作に失敗しました。', true);
            }
        },
        async deleteParentProject(id) {
            try {
                // Tìm dự án cha để kiểm tra số lượng dự án con
                const project = this.parentProjects.find(p => p.id == id);
                if (project && project.child_project_count > 0) {
                    Swal.fire({
                        title: '削除できません',
                        text: 'この親プロジェクトには子プロジェクトが存在するため削除できません。先に子プロジェクトを削除してください。',
                        icon: 'warning',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                const result = await Swal.fire({
                    title: '確認',
                    text: 'この親プロジェクトを削除しますか？',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: '削除',
                    cancelButtonText: 'キャンセル'
                });
                
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('id', id);
                    
                    const response = await axios.post('/api/index.php?model=parentproject&method=delete', formData);
                    
                    if (response.data && response.data.status === 'success') {
                        Swal.fire({
                            title: '成功',
                            text: '親プロジェクトを削除しました。',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                        this.loadParentProjects();
                    } else {
                        showMessage(response.data?.error || '削除に失敗しました。', true);
                    }
                }
            } catch (error) {
                console.error('Error deleting parent project:', error);
                showMessage('削除に失敗しました。', true);
            }
        }
    },
    mounted() {
        // Khôi phục trạng thái ẩn/hiện cột
        this.loadColumnVisibilityFromStorage();
        this.loadParentProjects();
        document.addEventListener('click', () => {
            this.noteContextMenuVisible = false;
        });
    },
    updated() {
        // Re-translate i18n elements after any DOM update
        this.$nextTick(() => {
            this.translateI18n();
        });
    }
}).mount('#app'); 