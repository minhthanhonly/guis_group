<?php
require_once('../application/loader.php');
$view->heading('既定タスク管理');
if ($_SESSION['show_project'] == 0) {
    echo '<div class="container-fluid mt-4"><div class="alert alert-danger">権限がありません。</div></div>';
    exit;
}
?>
<div id="app" class="container mt-4" v-cloak>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><span data-i18n="既定タスク管理">既定タスク管理</span></h2>
            <div class="text-muted small">
                <span data-i18n="部署ごとに、案件作成時に自動追加するタスク名と比率（%）を設定します。">部署ごとに、案件作成時に自動追加するタスク名と比率（%）を設定します。</span>
            </div>
        </div>
        <button class="btn btn-primary" @click="openModalForNew">
            <i class="fa fa-plus"></i> <span data-i18n="部署の設定">部署の設定</span>
        </button>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th data-i18n="部署">部署</th>
                        <th data-i18n="タスク数">タスク数</th>
                        <th data-i18n="設定">設定</th>
                        <th data-i18n="操作">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.department_id">
                        <td>{{ row.department_name }}</td>
                        <td>{{ row.task_count }}</td>
                        <td>
                            <span v-if="row.is_builtin" class="badge bg-secondary" data-i18n="標準（未保存）">標準（未保存）</span>
                            <span v-else class="badge bg-success" data-i18n="カスタム">カスタム</span>
                            <div class="small text-muted mt-1">
                                <span v-for="(t, i) in row.tasks" :key="row.department_id + '_' + i">
                                    {{ t.title }} ({{ formatPct(t.price_pct) }}%)<span v-if="i < row.tasks.length - 1">、</span>
                                </span>
                            </div>
                        </td>
                        <td class="text-nowrap">
                            <button class="btn btn-outline-primary btn-sm me-1" @click="openModalForEdit(row)">
                                <i class="fa fa-edit"></i> <span data-i18n="編集">編集</span>
                            </button>
                            <button class="btn btn-outline-danger btn-sm" :disabled="row.is_builtin" @click="resetRow(row)">
                                <i class="fa fa-undo"></i> <span data-i18n="標準に戻す">標準に戻す</span>
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="4" class="text-center text-muted" data-i18n="データがありません">データがありません</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" tabindex="-1" :class="{show: showModal}" style="display: block;" v-if="showModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span data-i18n="既定タスク設定">既定タスク設定</span>
                        <span v-if="modalRow.department_name"> — {{ modalRow.department_name }}</span>
                    </h5>
                    <button type="button" class="btn-close" @click="closeModal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3" v-if="!modalRow.department_id">
                        <label class="form-label" data-i18n="部署">部署</label>
                        <select v-model="modalRow.department_id" class="form-select" @change="onDepartmentPicked">
                            <option value="">—</option>
                            <option v-for="dept in availableDepartments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                        </select>
                    </div>
                    <div class="mb-2 d-flex justify-content-between align-items-center">
                        <label class="form-label mb-0" data-i18n="タスク一覧">タスク一覧</label>
                        <span class="small" :class="percentSum > 100 ? 'text-danger' : 'text-muted'">
                            <span data-i18n="比率合計">比率合計</span>: {{ formatPct(percentSum) }}%
                        </span>
                    </div>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th style="width:32px;"></th>
                                <th data-i18n="タスク名">タスク名</th>
                                <th style="width:110px;" data-i18n="比率(%)">比率(%)</th>
                                <th style="width:120px;" data-i18n="種別">種別</th>
                                <th style="width:70px;" data-i18n="操作">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(task, idx) in modalRow.tasks" :key="idx"
                                @dragover.prevent
                                @drop="onDrop(idx)"
                                :class="{ 'table-active': dragIndex === idx }">
                                <td style="width:32px;cursor:move;">
                                    <i class="fa fa-bars text-secondary"
                                       draggable="true"
                                       @dragstart="onDragStart(idx)"
                                       @dragend="onDragEnd"></i>
                                </td>
                                <td>
                                    <input class="form-control" v-model="task.title" placeholder="タスク名">
                                    <div v-if="isContactTaskTitle(task.title)" class="form-text text-muted">
                                        <span data-i18n="このタスクのみ GUIS受付者へ自動割当・進捗100%">このタスクのみ GUIS受付者へ自動割当・進捗100%</span>
                                    </div>
                                </td>
                                <td>
                                    <input type="number" class="form-control" v-model.number="task.price_pct"
                                           min="0" max="100" step="0.1">
                                </td>
                                <td>
                                    <input class="form-control" v-model="task.task_kind" placeholder="例: 連絡">
                                </td>
                                <td>
                                    <button class="btn btn-outline-danger btn-sm" @click="removeTask(idx)">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!modalRow.tasks.length">
                                <td colspan="5" class="text-center text-muted" data-i18n="タスクがありません">タスクがありません</td>
                            </tr>
                        </tbody>
                    </table>
                    <button class="btn btn-outline-secondary btn-sm" @click="addTask">
                        <i class="fa fa-plus"></i> <span data-i18n="タスク追加">タスク追加</span>
                    </button>
                    <div class="form-text mt-2">
                        <span data-i18n="「お客様との連絡・調整・納品対応」のみ担当者（GUIS受付者）と進捗100%が自動設定されます。他タスクは担当者・進捗なしです。">「お客様との連絡・調整・納品対応」のみ担当者（GUIS受付者）と進捗100%が自動設定されます。他タスクは担当者・進捗なしです。</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="closeModal" data-i18n="キャンセル">キャンセル</button>
                    <button class="btn btn-primary" @click="saveModal" data-i18n="保存">保存</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show" v-if="showModal"></div>
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
            rows: [],
            showModal: false,
            modalRow: { department_id: '', department_name: '', tasks: [] },
            dragIndex: null,
            dragging: false,
            builtinTasks: []
        };
    },
    computed: {
        percentSum() {
            return (this.modalRow.tasks || []).reduce((sum, t) => sum + (Number(t.price_pct) || 0), 0);
        },
        availableDepartments() {
            const configured = new Set(
                (this.rows || []).filter(r => !r.is_builtin).map(r => String(r.department_id))
            );
            return (this.departments || []).filter(d => !configured.has(String(d.id)));
        }
    },
    methods: {
        contactTaskTitle() {
            return 'お客様との連絡・調整・納品対応';
        },
        isContactTaskTitle(title) {
            return String(title || '').trim() === this.contactTaskTitle();
        },
        formatPct(v) {
            const n = Number(v);
            if (!Number.isFinite(n)) return '0';
            const rounded = Math.round(n * 10) / 10;
            return Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(1);
        },
        blankTask() {
            return {
                title: '',
                price_pct: 0,
                task_kind: '',
                assign_guis_receiver: 0,
                status: 'todo',
                progress: 0,
                drawing_count: 1
            };
        },
        cloneTasks(tasks) {
            return JSON.parse(JSON.stringify(Array.isArray(tasks) ? tasks : []));
        },
        openModalForNew() {
            const first = this.availableDepartments[0];
            this.modalRow = {
                department_id: first ? first.id : '',
                department_name: first ? first.name : '',
                tasks: this.cloneTasks(this.builtinTasks.length ? this.builtinTasks : [
                    { title: 'お客様との連絡・調整・納品対応', price_pct: 15, task_kind: '連絡', assign_guis_receiver: 1, status: 'completed', progress: 100, drawing_count: 1 },
                    { title: '全図面のチェック・確認作業', price_pct: 20, task_kind: 'チェック', assign_guis_receiver: 0, status: 'todo', progress: 0, drawing_count: 1 }
                ])
            };
            this.showModal = true;
        },
        openModalForEdit(row) {
            this.modalRow = {
                department_id: row.department_id,
                department_name: row.department_name,
                tasks: this.cloneTasks(row.tasks)
            };
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
        },
        onDepartmentPicked() {
            const dept = (this.departments || []).find(d => String(d.id) === String(this.modalRow.department_id));
            this.modalRow.department_name = dept ? dept.name : '';
            const existing = (this.rows || []).find(r => String(r.department_id) === String(this.modalRow.department_id));
            if (existing) {
                this.modalRow.tasks = this.cloneTasks(existing.tasks);
            }
        },
        addTask() {
            if (!Array.isArray(this.modalRow.tasks)) this.modalRow.tasks = [];
            this.modalRow.tasks.push(this.blankTask());
        },
        removeTask(idx) {
            this.modalRow.tasks.splice(idx, 1);
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
            const moved = this.modalRow.tasks.splice(this.dragIndex, 1)[0];
            this.modalRow.tasks.splice(idx, 0, moved);
            this.dragIndex = null;
            this.dragging = false;
        },
        saveModal() {
            if (!this.modalRow.department_id) {
                showMessage('部署を選択してください', true);
                return;
            }
            if (!this.modalRow.tasks.length) {
                showMessage('タスクを1件以上設定してください', true);
                return;
            }
            const titles = new Set();
            for (let i = 0; i < this.modalRow.tasks.length; i++) {
                const t = this.modalRow.tasks[i];
                const title = String(t.title || '').trim();
                if (!title) {
                    showMessage('タスク' + (i + 1) + '：タスク名を入力してください', true);
                    return;
                }
                if (titles.has(title)) {
                    showMessage('タスク名が重複しています: ' + title, true);
                    return;
                }
                titles.add(title);
                const pct = Number(t.price_pct);
                if (!Number.isFinite(pct) || pct < 0 || pct > 100) {
                    showMessage('タスク' + (i + 1) + '：比率は0〜100で入力してください', true);
                    return;
                }
            }
            if (this.percentSum > 100) {
                showMessage('比率の合計は100%以下にしてください', true);
                return;
            }
            const contactTitle = this.contactTaskTitle();
            const payload = {
                department_id: this.modalRow.department_id,
                tasks: this.modalRow.tasks.map(t => {
                    const title = String(t.title || '').trim();
                    const isContact = title === contactTitle;
                    return {
                        title: title,
                        price_pct: Number(t.price_pct) || 0,
                        task_kind: String(t.task_kind || '').trim(),
                        assign_guis_receiver: isContact ? 1 : 0,
                        status: isContact ? 'completed' : 'todo',
                        progress: isContact ? 100 : 0,
                        drawing_count: t.drawing_count != null ? Number(t.drawing_count) : 1
                    };
                })
            };
            axios.post('/api/index.php?model=department&method=saveDefaultTasks', payload)
                .then((res) => {
                    const data = res && res.data ? res.data : {};
                    if (data.success === false || data.error) {
                        showMessage(data.message || data.error || '保存に失敗しました', true);
                        return;
                    }
                    this.showModal = false;
                    this.loadRows();
                    showMessage('保存しました');
                })
                .catch((err) => {
                    const msg = (err && err.response && err.response.data && (err.response.data.message || err.response.data.error))
                        || (err && err.message)
                        || '保存に失敗しました';
                    showMessage(msg, true);
                });
        },
        resetRow(row) {
            if (!confirm('標準の既定タスクに戻しますか？')) return;
            axios.post('/api/index.php?model=department&method=removeDefaultTasks', {
                department_id: row.department_id,
                id: row.id
            }).then((res) => {
                const data = res && res.data ? res.data : {};
                if (data.success === false || data.error) {
                    showMessage(data.message || data.error || '削除に失敗しました', true);
                    return;
                }
                this.loadRows();
                showMessage('標準に戻しました');
            }).catch((err) => {
                const msg = (err && err.response && err.response.data && (err.response.data.message || err.response.data.error))
                    || (err && err.message)
                    || '削除に失敗しました';
                showMessage(msg, true);
            });
        },
        async loadDepartments() {
            try {
                const res = await axios.get('/api/index.php?model=department&method=list');
                this.departments = Array.isArray(res.data) ? res.data : [];
            } catch (e) {
                this.departments = [];
            }
        },
        async loadRows() {
            try {
                const res = await axios.get('/api/index.php?model=department&method=getDefaultTasks');
                this.rows = Array.isArray(res.data) ? res.data : [];
                const builtinRow = this.rows.find(r => r.is_builtin);
                if (builtinRow && Array.isArray(builtinRow.tasks)) {
                    this.builtinTasks = this.cloneTasks(builtinRow.tasks);
                }
            } catch (e) {
                this.rows = [];
            }
        }
    },
    async mounted() {
        await this.loadDepartments();
        await this.loadRows();
    }
});
app.mount('#app');
</script>
