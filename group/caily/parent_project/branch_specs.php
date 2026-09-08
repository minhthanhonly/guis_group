<?php
require_once('../application/loader.php');
$view->heading('支店・地域仕様管理');
if (!isset($_SESSION['authority']) || $_SESSION['authority'] !== 'administrator') {
    echo '<div class="container-fluid mt-4"><div class="alert alert-danger">権限がありません。</div></div>';
    exit;
}
?>
<style>
.branch-spec-excel {
    border-collapse: collapse;
    width: 100%;
    background: #fff;
    font-size: 13px;
}
.branch-spec-excel th,
.branch-spec-excel td {
    border: 1px solid #b0b0b0;
    padding: 6px 8px;
    vertical-align: top;
}
.branch-spec-excel thead th {
    background: #e8f0fe;
    position: sticky;
    top: 0;
    z-index: 2;
    white-space: nowrap;
    font-weight: 600;
}
.branch-spec-excel .col-branch { min-width: 140px; max-width: 180px; background: #f8f9fa; }
.branch-spec-excel .col-cond { min-width: 140px; max-width: 200px; }
.branch-spec-excel .col-content { min-width: 320px; white-space: pre-wrap; }
.branch-spec-excel .col-mark { width: 64px; text-align: center; white-space: nowrap; }
.branch-spec-excel .col-actions { width: 100px; white-space: nowrap; }
.branch-spec-excel tr.inactive-row { opacity: 0.45; background: #f5f5f5; }
.branch-spec-excel .mark-yes { color: #0d6efd; font-weight: 700; }
.branch-spec-excel .mark-no { color: #999; }
.branch-spec-sheet-tabs .nav-link { cursor: pointer; }
.branch-spec-table-wrap { max-height: calc(100vh - 220px); overflow: auto; border: 1px solid #b0b0b0; }
</style>
<div id="app" class="container-fluid mt-4" v-cloak>
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h2 class="mb-0" data-i18n="支店・地域仕様管理">支店・地域仕様管理</h2>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <input type="search" class="form-control form-control-sm" style="width:220px"
                v-model="searchText" placeholder="検索（支店・内容）" data-i18n-placeholder="検索（支店・内容）">
            <button class="btn btn-outline-secondary btn-sm" @click="importSeed(false)" :disabled="busy">シード取込</button>
            <button class="btn btn-outline-danger btn-sm" @click="importSeed(true)" :disabled="busy">シード再取込</button>
            <button class="btn btn-primary btn-sm" @click="openNew" :disabled="busy">
                <i class="fa fa-plus"></i> 新規
            </button>
        </div>
    </div>

    <ul class="nav nav-tabs branch-spec-sheet-tabs mb-2">
        <li class="nav-item" v-for="tab in sheetTabs" :key="tab.key">
            <a class="nav-link" :class="{ active: filterDept === tab.key }" @click.prevent="filterDept = tab.key">
                {{ tab.label }}
                <span class="badge bg-secondary ms-1">{{ countByDept(tab.key) }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" :class="{ active: filterDept === '' }" @click.prevent="filterDept = ''">
                すべて
                <span class="badge bg-secondary ms-1">{{ rules.length }}</span>
            </a>
        </li>
    </ul>

    <div class="branch-spec-table-wrap">
        <table class="branch-spec-excel">
            <thead>
                <tr>
                    <th class="col-branch">支店名</th>
                    <th class="col-cond">条件 / 機能</th>
                    <th class="col-content">仕様内容</th>
                    <th class="col-mark" v-if="showEquipCols">電気設備図</th>
                    <th class="col-mark" v-if="showEquipCols">衛生設備図</th>
                    <th class="col-mark">木造</th>
                    <th class="col-mark">S・RC</th>
                    <th class="col-mark" v-if="showEquipCols">意匠</th>
                    <th class="col-mark">有効</th>
                    <th class="col-actions">操作</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="r in filteredRules" :key="r.id" :class="{ 'inactive-row': r.is_active != 1 }">
                    <td class="col-branch">{{ displayBranch(r) }}</td>
                    <td class="col-cond small">{{ displayCondition(r) }}</td>
                    <td class="col-content">
                        <div>{{ r.content_ja || r.title_ja || '-' }}</div>
                        <div v-if="r.content_vi" class="text-muted small mt-1">{{ r.content_vi }}</div>
                    </td>
                    <td class="col-mark" v-if="showEquipCols">
                        <span :class="markClass(r.apply_electrical)">{{ markText(r.apply_electrical) }}</span>
                    </td>
                    <td class="col-mark" v-if="showEquipCols">
                        <span :class="markClass(r.apply_sanitary)">{{ markText(r.apply_sanitary) }}</span>
                    </td>
                    <td class="col-mark">
                        <span :class="markClass(r.apply_wood)">{{ markText(r.apply_wood) }}</span>
                    </td>
                    <td class="col-mark">
                        <span :class="markClass(r.apply_steel_rc)">{{ markText(r.apply_steel_rc) }}</span>
                    </td>
                    <td class="col-mark" v-if="showEquipCols">
                        <span :class="markClass(r.apply_architectural)">{{ markText(r.apply_architectural) }}</span>
                    </td>
                    <td class="col-mark">
                        <div class="form-check form-switch d-inline-flex justify-content-center m-0">
                            <input class="form-check-input" type="checkbox"
                                :checked="r.is_active == 1"
                                @change="toggleActive(r, $event)">
                        </div>
                    </td>
                    <td class="col-actions">
                        <button class="btn btn-outline-primary btn-sm me-1" @click="openEdit(r)" title="編集"><i class="fa fa-edit"></i></button>
                        <button class="btn btn-outline-danger btn-sm" @click="removeRule(r)" title="削除"><i class="fa fa-trash"></i></button>
                    </td>
                </tr>
                <tr v-if="!filteredRules.length">
                    <td :colspan="showEquipCols ? 10 : 7" class="text-center text-muted py-4">データがありません</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="branchSpecModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ editing.id ? '仕様編集' : '仕様新規' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">部門</label>
                            <select class="form-select" v-model="editing.department_key">
                                <option v-for="(label, key) in deptLabels" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">支店名</label>
                            <input class="form-control" v-model="editing.match_branch" placeholder="例: 一宮 / 共有は空欄">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">条件 / 機能</label>
                            <select class="form-select" v-model="editing.match_feature">
                                <option value="">（なし）</option>
                                <option v-for="(label, key) in featureLabels" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">都道府県</label>
                            <input class="form-control" v-model="editing.match_prefecture" placeholder="例: 大阪府">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">市区町村</label>
                            <input class="form-control" v-model="editing.match_city" placeholder="例: 高槻市">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">priority</label>
                            <input type="number" class="form-control" v-model.number="editing.priority">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">種類1 (TAC等)</label>
                            <input class="form-control" v-model="editing.match_type1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">種類2</label>
                            <input class="form-control" v-model="editing.match_type2">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">規模パターン</label>
                            <input class="form-control" v-model="editing.match_scale_pattern" placeholder="例: 3F|3階">
                        </div>
                        <div class="col-12">
                            <label class="form-label">タイトル</label>
                            <input class="form-control" v-model="editing.title_ja">
                        </div>
                        <div class="col-12">
                            <label class="form-label">仕様内容（日本語）</label>
                            <textarea class="form-control" rows="4" v-model="editing.content_ja"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">仕様内容（ベトナム語）</label>
                            <textarea class="form-control" rows="3" v-model="editing.content_vi"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-1">適用マーク（Excel列）</label>
                            <div class="d-flex flex-wrap gap-3">
                                <label class="form-check"><input type="checkbox" class="form-check-input" v-model="editing.apply_electrical" :true-value="1" :false-value="0"> 電気設備図</label>
                                <label class="form-check"><input type="checkbox" class="form-check-input" v-model="editing.apply_sanitary" :true-value="1" :false-value="0"> 衛生設備図</label>
                                <label class="form-check"><input type="checkbox" class="form-check-input" v-model="editing.apply_wood" :true-value="1" :false-value="0"> 木造</label>
                                <label class="form-check"><input type="checkbox" class="form-check-input" v-model="editing.apply_steel_rc" :true-value="1" :false-value="0"> S・RC</label>
                                <label class="form-check"><input type="checkbox" class="form-check-input" v-model="editing.apply_architectural" :true-value="1" :false-value="0"> 意匠</label>
                                <label class="form-check"><input type="checkbox" class="form-check-input" v-model="editing.requires_manual_confirm" :true-value="1" :false-value="0"> 要確認</label>
                                <label class="form-check"><input type="checkbox" class="form-check-input" v-model="editing.is_active" :true-value="1" :false-value="0"> 有効</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-primary" @click="saveRule" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>保存
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $view->footing(); ?>
<script>
(function () {
    function emptyRule(dept) {
        return {
            id: 0,
            department_key: dept || 'isho',
            company_key: '*',
            priority: 0,
            match_branch: '',
            match_prefecture: '',
            match_city: '',
            match_structure: 'any',
            match_type1: '',
            match_type2: '',
            match_scale_pattern: '',
            match_feature: '',
            match_note: '',
            title_ja: '',
            title_vi: '',
            content_ja: '',
            content_vi: '',
            apply_electrical: 0,
            apply_sanitary: 0,
            apply_architectural: dept === 'isho' ? 1 : 0,
            apply_wood: 1,
            apply_steel_rc: 1,
            requires_manual_confirm: 0,
            source_sheet: dept || 'isho',
            is_active: 1
        };
    }

    const { createApp } = Vue;
    createApp({
        data() {
            return {
                rules: [],
                filterDept: 'isho',
                searchText: '',
                deptLabels: { isho: '意匠設計', setsubi: '設備設計' },
                featureLabels: {
                    mb_water_heater: 'MB内に給湯器設置',
                    fire_water_tank: '消火用補給水槽',
                    steel_stairs: '鉄骨階段',
                    gh_l_type: 'GH・L型'
                },
                editing: emptyRule('isho'),
                formError: '',
                saving: false,
                busy: false,
                modal: null
            };
        },
        computed: {
            sheetTabs() {
                return Object.keys(this.deptLabels).map(k => ({ key: k, label: this.deptLabels[k] }));
            },
            showEquipCols() {
                return this.filterDept === '' || this.filterDept === 'setsubi';
            },
            filteredRules() {
                const q = (this.searchText || '').trim().toLowerCase();
                return (this.rules || []).filter(r => {
                    if (this.filterDept && r.department_key !== this.filterDept) return false;
                    if (!q) return true;
                    const blob = [
                        r.match_branch, r.match_note, r.title_ja, r.content_ja, r.content_vi,
                        r.match_prefecture, r.match_city, r.match_feature
                    ].join(' ').toLowerCase();
                    return blob.indexOf(q) !== -1;
                });
            }
        },
        methods: {
            countByDept(key) {
                return (this.rules || []).filter(r => r.department_key === key).length;
            },
            markText(v) {
                return (v == 1 || v === '1') ? '○' : 'ー';
            },
            markClass(v) {
                return (v == 1 || v === '1') ? 'mark-yes' : 'mark-no';
            },
            displayBranch(r) {
                if (r.match_branch) return r.match_branch;
                if (r.match_note) {
                    const first = String(r.match_note).split('/')[0].trim();
                    if (first) return first;
                }
                return '共有';
            },
            displayCondition(r) {
                const parts = [];
                if (r.match_feature && this.featureLabels[r.match_feature]) {
                    parts.push(this.featureLabels[r.match_feature]);
                } else if (r.match_feature) {
                    parts.push(r.match_feature);
                }
                if (r.match_prefecture) parts.push(r.match_prefecture);
                if (r.match_city) parts.push(r.match_city);
                if (r.match_type1) parts.push(r.match_type1);
                if (r.match_type2) parts.push(r.match_type2);
                if (r.match_scale_pattern) parts.push(r.match_scale_pattern);
                if (!parts.length && r.title_ja && r.title_ja !== this.displayBranch(r)) {
                    parts.push(r.title_ja);
                }
                return parts.join(' / ') || '-';
            },
            async loadRules() {
                this.busy = true;
                try {
                    const q = new URLSearchParams({ model: 'branchspec', method: 'listRules', all: '1' });
                    const res = await axios.get('/api/index.php?' + q.toString());
                    if (res.data && res.data.error && !res.data.data) {
                        throw new Error(typeof res.data.error === 'string' ? res.data.error : JSON.stringify(res.data.error));
                    }
                    this.rules = (res.data && res.data.data) ? res.data.data : [];
                    const meta = await axios.get('/api/index.php?model=branchspec&method=getDepartmentMeta');
                    if (meta.data && meta.data.data) this.deptLabels = meta.data.data;
                    if (meta.data && meta.data.features) this.featureLabels = meta.data.features;
                } catch (e) {
                    console.error(e);
                    const msg = (e.response && e.response.data && (e.response.data.error || e.response.data.message))
                        || e.message
                        || '読み込みに失敗しました';
                    alert(typeof msg === 'string' ? msg : '読み込みに失敗しました');
                } finally {
                    this.busy = false;
                }
            },
            getModal() {
                if (!this.modal) {
                    const el = document.getElementById('branchSpecModal');
                    this.modal = bootstrap.Modal.getOrCreateInstance(el);
                }
                return this.modal;
            },
            openNew() {
                this.formError = '';
                this.editing = emptyRule(this.filterDept || 'isho');
                this.getModal().show();
            },
            openEdit(r) {
                this.formError = '';
                const row = Object.assign(emptyRule(r.department_key), r);
                ['apply_electrical', 'apply_sanitary', 'apply_architectural', 'apply_wood', 'apply_steel_rc', 'requires_manual_confirm', 'is_active'].forEach(k => {
                    row[k] = parseInt(r[k], 10) ? 1 : 0;
                });
                if (!row.apply_wood && !row.apply_steel_rc) {
                    if (row.match_structure === 'wood') { row.apply_wood = 1; row.apply_steel_rc = 0; }
                    else if (row.match_structure === 'steel_rc') { row.apply_wood = 0; row.apply_steel_rc = 1; }
                    else { row.apply_wood = 1; row.apply_steel_rc = 1; }
                }
                this.editing = row;
                this.getModal().show();
            },
            closeModal() {
                this.formError = '';
                this.getModal().hide();
            },
            async saveRule() {
                this.formError = '';
                if (!this.editing.content_ja && !this.editing.title_ja) {
                    this.formError = '仕様内容またはタイトルを入力してください';
                    return;
                }
                this.saving = true;
                try {
                    const fd = new FormData();
                    const payload = Object.assign({}, this.editing);
                    ['apply_electrical', 'apply_sanitary', 'apply_architectural', 'apply_wood', 'apply_steel_rc', 'requires_manual_confirm', 'is_active'].forEach(k => {
                        payload[k] = payload[k] ? 1 : 0;
                    });
                    Object.keys(payload).forEach(k => {
                        const v = payload[k];
                        fd.append(k, v == null ? '' : v);
                    });
                    const method = payload.id ? 'update' : 'create';
                    const res = await axios.post('/api/index.php?model=branchspec&method=' + method, fd);
                    if (res.data && res.data.status === 'success') {
                        this.closeModal();
                        await this.loadRules();
                    } else {
                        this.formError = (res.data && (res.data.error || res.data.message)) || '保存に失敗しました';
                        if (typeof res.data.error === 'object') {
                            this.formError = JSON.stringify(res.data.error);
                        }
                    }
                } catch (e) {
                    console.error(e);
                    this.formError = (e.response && e.response.data && e.response.data.error) || e.message || '保存に失敗しました';
                } finally {
                    this.saving = false;
                }
            },
            async toggleActive(r, ev) {
                const fd = new FormData();
                fd.append('id', r.id);
                fd.append('is_active', ev.target.checked ? 1 : 0);
                try {
                    await axios.post('/api/index.php?model=branchspec&method=setActive', fd);
                    r.is_active = ev.target.checked ? 1 : 0;
                } catch (e) {
                    ev.target.checked = !ev.target.checked;
                    alert('更新に失敗しました');
                }
            },
            async removeRule(r) {
                if (!confirm('削除しますか？\n' + (r.title_ja || r.match_branch || ('#' + r.id)))) return;
                const fd = new FormData();
                fd.append('id', r.id);
                await axios.post('/api/index.php?model=branchspec&method=delete', fd);
                await this.loadRules();
            },
            async importSeed(force) {
                if (force && !confirm('既存データを削除して再取込します。よろしいですか？')) return;
                this.busy = true;
                try {
                    const fd = new FormData();
                    if (force) fd.append('force', '1');
                    const res = await axios.post('/api/index.php?model=branchspec&method=importSeed', fd);
                    alert('seeded: ' + ((res.data && res.data.seeded) != null ? res.data.seeded : '?'));
                    await this.loadRules();
                } catch (e) {
                    alert('取込に失敗しました');
                } finally {
                    this.busy = false;
                }
            }
        },
        async mounted() {
            const modalEl = document.getElementById('branchSpecModal');
            if (modalEl) {
                modalEl.addEventListener('hidden.bs.modal', () => {
                    this.formError = '';
                });
            }
            await this.loadRules();
        }
    }).mount('#app');
})();
</script>
