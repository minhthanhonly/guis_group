const { createApp } = Vue;

createApp({
    data() {
        return {
            parentProject: null,
            childProjects: [],
            loading: true,
            statuses: [
                { value: 'draft', label: '下書き', color: 'secondary' },
                { value: 'under_contract', label: '契約中', color: 'info' },
                { value: 'in_progress', label: '進行中', color: 'primary' },
                { value: 'completed', label: '完了', color: 'success' },
                { value: 'cancelled', label: 'キャンセル', color: 'danger' }
            ],
            projectStatuses: [
                { value: 'draft', label: '下書き', color: 'secondary' },
                { value: 'open', label: 'オープン', color: 'info' },
                { value: 'confirming', label: '確認中', color: 'warning' },
                { value: 'in_progress', label: '進行中', color: 'primary' },
                { value: 'paused', label: '一時停止', color: 'warning' },
                { value: 'completed', label: '完了', color: 'success' },
                { value: 'cancelled', label: 'キャンセル', color: 'danger' }
            ]
        }
    },
    methods: {
        async loadParentProject() {
            try {
                const response = await axios.get(`/api/index.php?model=parentproject&method=getById&id=${PARENT_PROJECT_ID}`);
                if (response.data) {
                    this.parentProject = response.data;
                } else {
                    showMessage('親プロジェクトが見つかりません。', true);
                    window.location.href = 'index.php';
                }
            } catch (error) {
                console.error('Error loading parent project:', error);
                showMessage('親プロジェクトの読み込みに失敗しました。', true);
                window.location.href = 'index.php';
            }
        },
        async loadChildProjects() {
            try {
                const response = await axios.get(`/api/index.php?model=parentproject&method=getChildProjects&parent_project_id=${PARENT_PROJECT_ID}`);
                if (response.data) {
                    this.childProjects = response.data;
                }
            } catch (error) {
                console.error('Error loading child projects:', error);
                this.childProjects = [];
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
        getProjectStatusLabel(status) {
            const s = this.projectStatuses.find(s => s.value === status);
            return s ? s.label : status;
        },
        getProjectStatusBadgeClass(status) {
            const s = this.projectStatuses.find(s => s.value === status);
            return `bg-${s?.color || 'secondary'}`;
        },
        formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('ja-JP');
        },
        async deleteParentProject() {
            try {
                const result = await Swal.fire({
                    title: '確認',
                    text: 'この親プロジェクトを削除しますか？子プロジェクトがある場合は削除できません。',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: '削除',
                    cancelButtonText: 'キャンセル'
                });
                
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('id', this.parentProject.id);
                    
                    const response = await axios.post('/api/index.php?model=parentproject&method=delete', formData);
                    
                    if (response.data && response.data.status === 'success') {
                        Swal.fire({
                            title: '成功',
                            text: '親プロジェクトを削除しました。',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            window.location.href = 'index.php';
                        });
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
    async mounted() {
        await this.loadParentProject();
        await this.loadChildProjects();
        this.loading = false;
    }
}).mount('#app'); 