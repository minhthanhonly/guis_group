<?php
require_once('../application/loader.php');
$view->heading('チーム設定');
?>

<div id="app" v-cloak>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>チーム一覧</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#teamModal">
                        <i class="bi bi-plus"></i> 新規チーム
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>チーム名</th>
                                <th>部署</th>
                                <th>メンバー</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="team in teams" :key="team.id">
                                <td>{{ team.name }}</td>
                                <td>{{ team.department_name }}</td>
                                <td>{{ team.members }}</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#teamModal" @click="editTeam(team)">
                                            <i class="icon-base ti tabler-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" @click="deleteTeam(team)">
                                            <i class="icon-base ti tabler-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- New/Edit Team Modal -->
    <div class="modal fade" id="teamModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ editingTeam ? 'チーム編集' : '新規チーム' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="saveTeam">
                        <div class="mb-3">
                            <label class="form-label">チーム名</label>
                            <input type="text" class="form-control" v-model="newTeam.name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">部署</label>
                            <select class="form-select" v-model="newTeam.department_id" @change="loadDepartmentUsers" required>
                                <option value="">選択してください</option>
                                <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                                    {{ dept.name }}
                                </option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">メンバー</label>
                            <div class="mb-2">
                                <select class="form-select" v-model="selectedUser" @change="addMember" style="max-width: 300px;">
                                    <option value="">メンバーを選択</option>
                                    <option v-for="user in departmentUsers" :key="user.id" :value="user">
                                        {{ user.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">説明</label>
                            <textarea class="form-control" v-model="newTeam.description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>名前</th>
                                            <th>チーム担当</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(member, index) in selectedMembers" :key="index">
                                            <td>{{ member.name }}</td>
                                            <td>
                                                <div class="form-check form-check-inline">
                                                    <label class="form-check-label"><input class="form-check-input" type="checkbox" v-model="member.leader">
                                                    チーム担当</label>
                                                </div>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-danger" @click="removeMember(index)">
                                                    <i class="icon-base ti tabler-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-primary" @click="saveTeam">保存</button>
                </div>
            </div>
        </div>
    </div>

  
</div>

<?php
$view->footing();
?>

<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script>
    const { createApp } = Vue;
    
    createApp({
        data() {
            return {
                teams: [],
                departments: [],
                departmentUsers: [],
                selectedTeamMembers: [],
                editingTeam: null,
                selectedUser: '',
                selectedMembers: [],
                newTeam: {
                    name: '',
                    department_id: '',
                    description: '',
                    members: [],
                    leader: {},
                    // project_delete: {},
                    // project_comment: {},
                    // task_view: {},
                    // task_add: {},
                    // task_edit: {},
                    // task_delete: {}
                }
            }
        },
        methods: {
            async loadTeams() {
                try {
                    const response = await axios.get('/api/index.php?model=team&method=list');
                    this.teams = response.data;
                } catch (error) {
                    console.error('Error loading teams:', error);
                    showMessage('チームの読み込みに失敗しました。', true);
                }
            },
            async loadDepartments() {
                try {
                    const response = await axios.get('/api/index.php?model=department&method=list_department');
                    this.departments = response.data;
                } catch (error) {
                    console.error('Error loading departments:', error);
                    showMessage('部署の読み込みに失敗しました。', true);
                }
            },
            async loadDepartmentUsers() {
                if (!this.newTeam.department_id) {
                    this.departmentUsers = [];
                    this.selectedMembers = [];
                    return;
                }
                try {
                    const response = await axios.get('/api/index.php?model=team&method=getDepartmentUsers&department_id=' + this.newTeam.department_id);
                    this.departmentUsers = response.data;
                } catch (error) {
                    console.error('Error loading department users:', error);
                    showMessage('部署メンバーの読み込みに失敗しました。', true);
                }
            },
            async editTeam(team) {
                try {
                    // Load team details including members
                    const response = await axios.get('/api/index.php?model=team&method=get&id=' + team.id);
                    const teamData = response.data;
                    
                    this.editingTeam = team;
                    this.newTeam = {
                        name: teamData.name,
                        department_id: teamData.department_id,
                        description: teamData.description,
                        members: [],
                        leader: {}
                    };

                    // Load department users first
                    await this.loadDepartmentUsers();
                    
                    // Then set selected members with their permissions
                    if (teamData.members && teamData.members.length > 0) {
                        this.selectedMembers = teamData.members.map(member => ({
                            id: member.user_id,
                            name: member.user_name,
                            leader: member.leader === '1'
                        }));
                    }
                } catch (error) {
                    console.error('Error loading team details:', error);
                    showMessage('チーム情報の読み込みに失敗しました。', true);
                }
            },
            async viewMembers(team) {
                try {
                    const response = await axios.get('/api/index.php?model=team&method=get&id=' + team.id);
                    this.selectedTeamMembers = response.data.members || [];
                } catch (error) {
                    console.error('Error loading team members:', error);
                    showMessage('チームメンバーの読み込みに失敗しました。', true);
                }
            },
            async deleteTeam(team) {
                if (!confirm('このチームを削除してもよろしいですか？')) {
                    return;
                }

                try {
                    await axios.post('/api/index.php?model=team&method=delete&id=' + team.id);
                    this.loadTeams();
                    showMessage('チームを削除しました。');
                } catch (error) {
                    console.error('Error deleting team:', error);
                    showMessage('チームの削除に失敗しました。', true);
                }
            },
            addMember() {
                if (!this.selectedUser) return;
                
                // Check if member already exists
                const exists = this.selectedMembers.some(m => m.id === this.selectedUser.id);
                if (exists) {
                    showMessage('このメンバーは既に追加されています。', true);
                    return;
                }

                // Add new member with default permissions
                this.selectedMembers.push({
                    id: this.selectedUser.id,
                    name: this.selectedUser.name,
                    leader: false
                });

                // Reset selection
                this.selectedUser = '';
            },
            removeMember(index) {
                this.selectedMembers.splice(index, 1);
            },
            async saveTeam() {
                try {
                    // Prepare data for saving
                    const teamData = {
                        name: this.newTeam.name,
                        department_id: this.newTeam.department_id,
                        description: this.newTeam.description,
                        members: this.selectedMembers.map(m => m.id),
                        leader: {},
                    };

                    // Add permissions for each member
                    this.selectedMembers.forEach(member => {
                        teamData.leader[member.id] = member.leader;
                    });

                    if (this.editingTeam) {
                        await axios.post('/api/index.php?model=team&method=edit&id=' + this.editingTeam.id, teamData,
                        {
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            }
                        }
                        );
                    } else {
                        await axios.post('/api/index.php?model=team&method=add', teamData,
                        {
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            }
                        }
                        );
                    }
                    $('#teamModal').modal('hide');
                    this.resetTeamData();
                    showMessage('チームを保存しました。');
                    this.loadTeams();
                } catch (error) {
                    console.error('Error saving team:', error);
                    showMessage('チームの保存に失敗しました。', true);
                }
            },
            resetTeamData() {
                this.editingTeam = null;
                this.selectedUser = '';
                this.selectedMembers = [];
                this.newTeam = {
                    name: '',
                    department_id: '',
                    description: '',
                    members: [],
                    leader: {}
                };
                this.departmentUsers = [];
            }
        },
        mounted() {
            this.loadTeams();
            this.loadDepartments();
            
            // Add event listener for modal hide
            const teamModal = document.getElementById('teamModal');
            teamModal.addEventListener('hide.bs.modal', () => {
                this.resetTeamData();
            });
        }
    }).mount('#app');
</script> 