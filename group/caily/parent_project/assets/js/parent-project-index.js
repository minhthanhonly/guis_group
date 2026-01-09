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
            }
        },
        sortBy(column) {
            // Map frontend column names to database column names
            const columnMap = {
                'project_number': 'project_number',
                'project_name': 'project_name',
                'construction_number': 'construction_number',
                'company_name': 'company_name',
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
        formatDate(date) {
            if (!date) return '-';
            return moment(date).format('Y年M月D日 HH:mm');
        },
        formatDate2(date) {
            if (!date) return '-';
            return moment(date).format('Y年M月D日');
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
        this.loadParentProjects();
    }
}).mount('#app'); 