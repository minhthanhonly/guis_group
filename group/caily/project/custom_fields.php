<?php
require_once('../application/loader.php');
$view->heading('カスタムフィールド管理');
?>
<div id="app" class="container mt-4" v-cloak>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>カスタムフィールド管理</h2>
        <button class="btn btn-primary" @click="openModalForNew"><i class="fa fa-plus"></i> 新規カスタムフィールドセット</button>
    </div>
    <div class="card mb-4">
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>セット名</th>
                        <th>部署</th>
                        <th>項目数</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(set, setIdx) in customFieldSets" :key="set.id">
                        <td>{{ set.name }}</td>
                        <td>{{ getDepartmentName(set.department_id) }}</td>
                        <td>{{ set.fields.length }}</td>
                        <td>
                            <button class="btn btn-outline-primary btn-sm me-1" @click="openModalForEdit(set, setIdx)"><i class="fa fa-edit"></i> 編集</button>
                            <button class="btn btn-outline-danger btn-sm" @click="removeFieldSet(set)"><i class="fa fa-trash"></i> 削除</button>
                        </td>
                    </tr>
                    <tr v-if="!customFieldSets.length">
                        <td colspan="4" class="text-center text-muted">データがありません</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Modal for Add/Edit Custom Field Set -->
    <div class="modal fade" tabindex="-1" :class="{show: showModal}" style="display: block;" v-if="showModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ editingSetIdx === null ? '新規カスタムフィールドセット' : 'カスタムフィールドセット編集' }}</h5>
                    <button type="button" class="btn-close" @click="closeModal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">セット名</label>
                        <input class="form-control" v-model="modalSet.name" placeholder="セット名">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">部署</label>
                        <select v-model="modalSet.department_id" class="form-select">
                            <option v-for="dept in departments" :value="dept.id">{{ dept.name }}</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">カスタム項目</label>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th style="width:32px;"></th>
                                    <th>ラベル</th>
                                    <th>タイプ</th>
                                    <th>選択肢 (カンマ区切り)</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(field, idx) in modalSet.fields" :key="idx"
                                    @dragover.prevent
                                    @drop="onDrop(idx)"
                                    :class="{ 'table-active': dragIndex === idx }"
                                >
                                    <td style="width:32px;cursor:move;">
                                        <i class="fa fa-bars text-secondary"
                                           draggable="true"
                                           @dragstart="onDragStart(idx)"
                                           @dragend="onDragEnd"
                                        ></i>
                                    </td>
                                    <td><input class="form-control" v-model="field.label" placeholder="ラベル"></td>
                                    <td>
                                        <select class="form-select" v-model="field.type">
                                            <option value="text">テキスト</option>
                                            <option value="textarea">テキストエリア</option>
                                            <option value="select">セレクト</option>
                                            <option value="radio">ラジオ</option>
                                            <option value="checkbox">チェックボックス</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input class="form-control" v-model="field.options" :disabled="!['select','radio','checkbox'].includes(field.type)" :placeholder="field.type === 'select' || field.type === 'radio' || field.type === 'checkbox' ? 'A,B,C' : ''">
                                    </td>
                                    <td>
                                        <button class="btn btn-outline-danger btn-sm" @click="removeFieldFromModal(idx)"><i class="fa fa-times"></i></button>
                                    </td>
                                </tr>
                                <tr v-if="!modalSet.fields.length">
                                    <td colspan="5" class="text-center text-muted">項目がありません</td>
                                </tr>
                            </tbody>
                        </table>
                        <button class="btn btn-outline-secondary btn-sm" @click="addFieldToModal"><i class="fa fa-plus"></i> 項目追加</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="closeModal">キャンセル</button>
                    <button class="btn btn-primary" @click="saveModalSet">保存</button>
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
const app = Vue.createApp({
    data() {
        return {
            departments: [],
            customFieldSets: [],
            showModal: false,
            editingSetIdx: null,
            modalSet: { name: '', department_id: '', fields: [] },
            dragIndex: null,
            dragging: false
        }
    },
    methods: {
        getDepartmentName(id) {
            const dept = this.departments.find(d => d.id == id);
            return dept ? dept.name : '';
        },
        openModalForNew() {
            this.editingSetIdx = null;
            this.modalSet = { name: '', department_id: this.departments.length ? this.departments[0].id : '', fields: [] };
            this.showModal = true;
        },
        openModalForEdit(set, idx) {
            console.log(set);
            this.editingSetIdx = idx;
            // Deep copy
            this.modalSet = JSON.parse(JSON.stringify(set));
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
        },
        addFieldToModal() {
            this.modalSet.fields.push({ label: '', type: 'text', options: '' });
        },
        removeFieldFromModal(idx) {
            this.modalSet.fields.splice(idx, 1);
        },
        saveModalSet() {
            // Validate
            if (!this.modalSet.name.trim()) {
                showMessage('セット名を入力してください', true);
                return;
            }
            if (!this.modalSet.department_id) {
                showMessage('部署を選択してください', true);
                return;
            }
            const labels = new Set();
            for (const [idx, field] of this.modalSet.fields.entries()) {
                if (!field.label.trim()) {
                    alert(`項目${idx + 1}：ラベルを入力してください`);
                    return;
                }
                if (labels.has(field.label.trim())) {
                    alert(`項目${idx + 1}：ラベルが重複しています`);
                    return;
                }
                labels.add(field.label.trim());
                if (['select', 'radio', 'checkbox'].includes(field.type) && !field.options.trim()) {
                    alert(`項目${idx + 1}：選択肢を入力してください`);
                    return;
                }
            }
            const method = this.editingSetIdx === null ? 'addCustomFields' : 'saveCustomFields';
            // Save only this set to the database
            axios.post('/api/index.php?model=department&method=' + method, this.modalSet)
                .then(() => {
                    this.showModal = false;
                    this.loadCustomFieldSets();
                    showMessage('保存しました');
                });
        },
        removeFieldSet(set) {
            if (confirm('本当に削除しますか？')) {
                axios.post('/api/index.php?model=department&method=removeCustomFields', { id: set.id })
                    .then(() => {
                        this.loadCustomFieldSets();
                        showMessage('削除しました');
                    });
            }
        },
        async loadDepartments() {
            const res = await axios.get('/api/index.php?model=department&method=list');
            this.departments = res.data;
        },
        async loadCustomFieldSets() {
            const res = await axios.get('/api/index.php?model=department&method=getCustomFields');
            this.customFieldSets = res.data || [];
        },
        onDragStart(idx) {
            this.dragIndex = idx;
            this.dragging = true;
        },
        onDragEnd() {
            this.dragIndex = null;
            this.dragging = false;
        },
        onDrop(idx) {
            if (!this.dragging || this.dragIndex === null || this.dragIndex === idx) return;
            const moved = this.modalSet.fields.splice(this.dragIndex, 1)[0];
            this.modalSet.fields.splice(idx, 0, moved);
            this.dragIndex = null;
            this.dragging = false;
        }
    },
    async mounted() {
        await this.loadDepartments();
        await this.loadCustomFieldSets();
    }
});
app.mount('#app');
</script> 


