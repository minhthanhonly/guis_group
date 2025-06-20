<?php
require_once('../application/loader.php');
$view->heading('休暇届');
?>

<div id="app" v-cloak>
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card" id="option-block">
            <div class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row">
                <div class="col-md-6">
                    <h4 class="card-title mb-0"><span>休暇届</span></h4>
                </div>
                <div class="col-md-6 text-end">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#leaveModal">
                        <i class="bi bi-plus"></i> 新規申請
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div v-if="loading" class="text-center py-4">
                    <span class="spinner-border"></span>
                </div>
                <div v-else>
                    <div v-if="leaves.length === 0" class="text-center text-muted py-4">
                        まだ申請がありません。
                    </div>
                    <div v-else class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>期間</th>
                                    <th>日間</th>
                                    <th>休暇種別</th>
                                    <th>詳細</th>
                                    <th>事由</th>
                                    <th>注記</th>
                                    <th>申請日</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="leave in leaves" :key="leave.id">
                                    <td>{{ leave.start_datetime }} ~ {{ leave.end_datetime }}</td>
                                    <td>{{ leave.days }}</td>
                                    <td>{{ leave.leave_type === 'paid' ? '有給休暇' : '無給休暇' }}</td>
                                    <td>
                                        <span v-if="leave.leave_type === 'paid'">
                                            {{ leave.paid_type === 'full' ? '全休' : (leave.paid_type === 'am' ? '午前休' : (leave.paid_type === 'pm' ? '午後休' : '')) }}
                                        </span>
                                        <span v-if="leave.leave_type === 'unpaid'">
                                            {{ leave.unpaid_type === 'congratulatory' ? '慶弔休暇' : (leave.unpaid_type === 'menstrual' ? '生理休暇' : (leave.unpaid_type === 'child_nursing' ? '子の看護休暇' : '')) }}
                                        </span>
                                    </td>
                                    <td>{{ leave.reason }}</td>
                                    <td>{{ leave.note }}</td>
                                    <td>{{ leave.created_at }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leave Request Modal -->
        <div class="modal fade" id="leaveModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">休暇申請</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form @submit.prevent="submitLeave">
                            <div class="mb-3 row">
                                <label class="col-sm-3 col-form-label">期間</label>
                                <div class="col-sm-4">
                                    <input type="datetime-local" class="form-control" v-model="leave.start_datetime" required>
                                    <div class="text-danger small" v-if="errors.start_datetime">{{ errors.start_datetime }}</div>
                                </div>
                                <div class="col-sm-1 text-center">~</div>
                                <div class="col-sm-4">
                                    <input type="datetime-local" class="form-control" v-model="leave.end_datetime" required>
                                    <div class="text-danger small" v-if="errors.end_datetime">{{ errors.end_datetime }}</div>
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label class="col-sm-3 col-form-label">日間</label>
                                <div class="col-sm-4">
                                    <input type="number" step="0.5" min="0" class="form-control" v-model="leave.days" required>
                                    <div class="text-danger small" v-if="errors.days">{{ errors.days }}</div>
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label class="col-sm-3 col-form-label">休暇種別</label>
                                <div class="col-sm-9">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" id="paid" value="paid" v-model="leave.leave_type">
                                        <label class="form-check-label" for="paid">有給休暇</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" id="unpaid" value="unpaid" v-model="leave.leave_type">
                                        <label class="form-check-label" for="unpaid">無給休暇</label>
                                    </div>
                                    <div class="text-danger small" v-if="errors.leave_type">{{ errors.leave_type }}</div>
                                </div>
                            </div>
                            <div class="mb-3 row" v-if="leave.leave_type === 'paid'">
                                <label class="col-sm-3 col-form-label">有給休暇</label>
                                <div class="col-sm-9">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" id="full" value="full" v-model="leave.paid_type">
                                        <label class="form-check-label" for="full">全休</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" id="am" value="am" v-model="leave.paid_type">
                                        <label class="form-check-label" for="am">午前休</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" id="pm" value="pm" v-model="leave.paid_type">
                                        <label class="form-check-label" for="pm">午後休</label>
                                    </div>
                                    <div class="text-danger small" v-if="errors.paid_type">{{ errors.paid_type }}</div>
                                </div>
                            </div>
                            <div class="mb-3 row" v-if="leave.leave_type === 'unpaid'">
                                <label class="col-sm-3 col-form-label">無給休暇</label>
                                <div class="col-sm-9">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" id="congratulatory" value="congratulatory" v-model="leave.unpaid_type">
                                        <label class="form-check-label" for="congratulatory">慶弔休暇</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" id="menstrual" value="menstrual" v-model="leave.unpaid_type">
                                        <label class="form-check-label" for="menstrual">生理休暇</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" id="child_nursing" value="child_nursing" v-model="leave.unpaid_type">
                                        <label class="form-check-label" for="child_nursing">子の看護休暇</label>
                                    </div>
                                    <div class="text-danger small" v-if="errors.unpaid_type">{{ errors.unpaid_type }}</div>
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label class="col-sm-3 col-form-label">事由</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" v-model="leave.reason" maxlength="255">
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label class="col-sm-3 col-form-label">注記</label>
                                <div class="col-sm-9">
                                    <textarea class="form-control" v-model="leave.note" rows="2"></textarea>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="button" class="btn btn-primary" @click="submitLeave">申請</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$view->footing();
?>
<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>
<script src="/assets/js/axios.min.js"></script>
<script>
const { createApp } = Vue;
createApp({
    data() {
        return {
            leave: {
                start_datetime: '',
                end_datetime: '',
                days: '',
                leave_type: '',
                paid_type: '',
                unpaid_type: '',
                reason: '',
                note: ''
            },
            leaves: [],
            loading: false,
            errors: {}
        }
    },
    methods: {
        validateForm() {
            this.errors = {};
            let valid = true;
            if (!this.leave.start_datetime) {
                this.errors.start_datetime = '開始日時を入力してください。';
                valid = false;
            }
            if (!this.leave.end_datetime) {
                this.errors.end_datetime = '終了日時を入力してください。';
                valid = false;
            }
            if (this.leave.start_datetime && this.leave.end_datetime) {
                const start = new Date(this.leave.start_datetime);
                const end = new Date(this.leave.end_datetime);
                if (start >= end) {
                    this.errors.end_datetime = '終了日時は開始日時より後にしてください。';
                    valid = false;
                }
            }
            if (!this.leave.days || isNaN(this.leave.days) || Number(this.leave.days) <= 0) {
                this.errors.days = '日間は0より大きい値を入力してください。';
                valid = false;
            }
            if (!this.leave.leave_type) {
                this.errors.leave_type = '休暇種別を選択してください。';
                valid = false;
            }
            if (this.leave.leave_type === 'paid' && !this.leave.paid_type) {
                this.errors.paid_type = '有給休暇の種類を選択してください。';
                valid = false;
            }
            if (this.leave.leave_type === 'unpaid' && !this.leave.unpaid_type) {
                this.errors.unpaid_type = '無給休暇の種類を選択してください。';
                valid = false;
            }
            return valid;
        },
        async fetchLeaves() {
            this.loading = true;
            try {
                const res = await axios.get('/api/index.php?model=leave&method=list');
                this.leaves = Array.isArray(res.data) ? res.data : [];
            } catch (e) {
                this.leaves = [];
            }
            this.loading = false;
        },
        async submitLeave() {
            if (!this.validateForm()) {
                return;
            }
            try {
                const res = await axios.post('/api/index.php?model=leave&method=add', this.leave, {
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                });
                if(res != ''){
                    showMessage('申請が完了しました。');
                    this.resetForm();
                    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('leaveModal'));
                    modal.hide();
                    this.fetchLeaves();
                    return;
                }
                alert('申請に失敗しました。', true);
            } catch (error) {
                alert('申請に失敗しました。', true);
            }
        },
        resetForm() {
            this.leave = {
                start_datetime: '',
                end_datetime: '',
                days: '',
                leave_type: '',
                paid_type: '',
                unpaid_type: '',
                reason: '',
                note: ''
            };
            this.errors = {};
        }
    },
    mounted() {
        this.fetchLeaves();
    }
}).mount('#app');
</script> 