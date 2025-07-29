const { createApp } = Vue;

createApp({
    data() {
        return {
            parentProjects: [],
            searchKeyword: '',
            statusFilter: 'all',
            currentPage: 1,
            pageSize: 20,
            totalRecords: 0,
            loading: false,
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
            let filtered = this.parentProjects;
            
            // Apply search filter
            if (this.searchKeyword) {
                const keyword = this.searchKeyword.toLowerCase();
                filtered = filtered.filter(project => 
                    project.company_name.toLowerCase().includes(keyword) ||
                    project.project_name.toLowerCase().includes(keyword) ||
                    (project.construction_number && project.construction_number.toLowerCase().includes(keyword))
                );
            }
            
            // Apply status filter
            if (this.statusFilter !== 'all') {
                filtered = filtered.filter(project => project.status === this.statusFilter);
            }
            
            return filtered;
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
                    status: this.statusFilter === 'all' ? '' : this.statusFilter
                });
                
                const response = await axios.get(`/api/index.php?model=parentproject&method=list&${params.toString()}`);
                if (response.data && response.data.data) {
                    this.parentProjects = response.data.data;
                    this.totalRecords = response.data.recordsTotal;
                }
            } catch (error) {
                console.error('Error loading parent projects:', error);
                showMessage('親プロジェクトの読み込みに失敗しました。', true);
            } finally {
                this.loading = false;
            }
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
        formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('ja-JP');
        },
        async deleteParentProject(id) {
            try {
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