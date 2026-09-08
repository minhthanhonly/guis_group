<?php
require_once('../application/loader.php');
$view->heading('オンライン状況');
?>

<div id="memberOnlineApp" class="container-xxl flex-grow-1 container-p-y" v-cloak>
    <div class="card">
        <div class="card-header bg-label-secondary d-flex justify-content-sm-between align-items-sm-center flex-column flex-sm-row gap-2">
            <div>
                <h4 class="card-title mb-0"><span data-i18n="オンライン状況">オンライン状況</span></h4>
                <div class="small text-muted mt-1">
                    <span data-i18n="更新">更新</span>: {{ updatedAt || '-' }}
                    <span class="ms-2">Online {{ onlineCount }} / {{ filteredMembers.length }}</span>
                    <span class="ms-2" data-i18n="申請">申請</span>: {{ formsDate || '-' }}
                    <span v-if="isAdministrator && unlockRequestCount > 0" class="ms-2 badge bg-warning text-dark">
                        <span data-i18n="解除申請">解除申請</span>: {{ unlockRequestCount }}
                    </span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa fa-users me-1"></i><span data-i18n="ユーザー一覧">ユーザー一覧</span>
                </a>
                <button type="button" class="btn btn-outline-primary btn-sm" @click="refreshAll" :disabled="loading">
                    <i class="fa fa-sync-alt me-1" :class="{ 'fa-spin': loading }"></i>
                    <span data-i18n="更新">更新</span>
                </button>
            </div>
        </div>

        <div class="card-body">
            <div class="row g-2 pt-3 mb-3 align-items-center">
                <div class="col-md-4">
                    <input type="text" class="form-control" v-model="searchKeyword"
                           :placeholder="searchPlaceholder">
                </div>
                <div class="col-md-8">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <div class="btn-group flex-wrap" role="group" aria-label="company filter">
                            <button type="button" class="btn btn-sm"
                                    :class="companyFilter === 'GUIS' ? 'btn-primary' : 'btn-outline-primary'"
                                    @click="companyFilter = 'GUIS'">GUIS</button>
                            <button type="button" class="btn btn-sm"
                                    :class="companyFilter === 'CAILY' ? 'btn-primary' : 'btn-outline-primary'"
                                    @click="companyFilter = 'CAILY'">CAILY</button>
                            <button type="button" class="btn btn-sm"
                                    :class="companyFilter === 'all' ? 'btn-primary' : 'btn-outline-primary'"
                                    @click="companyFilter = 'all'" data-i18n="すべて">すべて</button>
                        </div>
                        <div class="btn-group flex-wrap" role="group" aria-label="online filter">
                            <button type="button" class="btn btn-sm"
                                    :class="statusFilter === 'all' ? 'btn-primary' : 'btn-outline-primary'"
                                    @click="statusFilter = 'all'" data-i18n="すべて">すべて</button>
                            <button type="button" class="btn btn-sm"
                                    :class="statusFilter === 'online' ? 'btn-success' : 'btn-outline-success'"
                                    @click="statusFilter = 'online'" data-i18n="オンライン">オンライン</button>
                            <button type="button" class="btn btn-sm"
                                    :class="statusFilter === 'offline' ? 'btn-secondary' : 'btn-outline-secondary'"
                                    @click="statusFilter = 'offline'" data-i18n="オフライン">オフライン</button>
                            <button type="button" class="btn btn-sm"
                                    :class="statusFilter === 'app' ? 'btn-info' : 'btn-outline-info'"
                                    @click="statusFilter = 'app'" data-i18n="アプリでオンライン">アプリでオンライン</button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="loading && members.length === 0" class="text-center py-5">
                <div class="spinner-border" role="status"></div>
            </div>

            <div v-else class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 64px;"></th>
                            <th data-i18n="ユーザー">ユーザー</th>
                            <th data-i18n="グループ">グループ</th>
                            <th data-i18n="勤務種別">勤務種別</th>
                            <th data-i18n="勤務時間">勤務時間</th>
                            <th data-i18n="ステータス">ステータス</th>
                            <th data-i18n="接続">接続</th>
                            <th data-i18n="申請">申請</th>
                            <th v-if="isAdministrator" style="width: 110px;" data-i18n="残業警告">残業警告</th>
                            <th style="width: 200px;" data-i18n="操作">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in filteredMembers" :key="m.userid"
                            :class="{ 'table-warning': isAdministrator && hasUnlockRequest(m.userid) }">
                            <td>
                                <div class="avatar"
                                     :class="avatarClass(m.userid)"
                                     :data-userid="m.userid"
                                     :title="displayName(m)">
                                    <span class="avatar-initial rounded-circle bg-label-primary">{{ avatarInitials(m) }}</span>
                                    <img v-if="hasValidAvatar(m)"
                                         :src="avatarUrl(m)"
                                         alt=""
                                         class="rounded-circle"
                                         style="display:none;"
                                         @load="onAvatarLoad"
                                         @error="onAvatarError" />
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ displayName(m) }}</div>
                                <div class="small text-muted">{{ m.userid }}</div>
                                <div v-if="isAdministrator && hasUnlockRequest(m.userid)" class="mt-1">
                                    <span class="badge bg-warning text-dark">
                                        <i class="fa fa-unlock-alt me-1"></i>
                                        <span data-i18n="解除申請あり">解除申請あり</span>
                                    </span>
                                    <div class="small text-muted mt-1" v-if="unlockRequestAt(m.userid)">
                                        {{ unlockRequestAt(m.userid) }}
                                    </div>
                                </div>
                            </td>
                            <td>{{ m.group_name || '-' }}</td>
                            <td>
                                <span v-if="(m.member_type_name || m.member_type)" class="badge bg-label-secondary">{{ m.member_type_name || m.member_type }}</span>
                                <span v-else class="text-muted">-</span>
                            </td>
                            <td>
                                <div v-if="m.work_start && m.work_end">{{ m.work_start }}〜{{ m.work_end }}</div>
                                <div v-if="m.lunch_label" class="small text-muted">{{ m.lunch_label }}</div>
                                <div v-else-if="m.lunch_start === '00:00' && m.lunch_end === '00:00'" class="small text-muted">休憩無し</div>
                                <div v-else-if="m.lunch_start && m.lunch_end" class="small text-muted">昼 {{ m.lunch_start }}〜{{ m.lunch_end }}</div>
                                <span v-if="!(m.work_start && m.work_end) && !m.lunch_label && !(m.lunch_start && m.lunch_end)" class="text-muted">-</span>
                            </td>
                            <td>
                                <span v-if="isOnline(m.userid)" class="badge bg-success" data-i18n="オンライン">オンライン</span>
                                <span v-else class="badge bg-secondary" data-i18n="オフライン">オフライン</span>
                            </td>
                            <td>
                                <span v-if="isWeb(m.userid)" class="badge bg-label-primary me-1">Web</span>
                                <span v-if="isApp(m.userid)" class="badge bg-label-info me-1">App</span>
                                <span v-if="!isOnline(m.userid)" class="text-muted">-</span>
                            </td>
                            <td>
                                <template v-if="formsOf(m.userid).length">
                                    <div v-for="f in formsOf(m.userid)" :key="f.id" class="mb-1">
                                        <a :href="formDetailUrl(f.id)"
                                           class="badge me-1 text-decoration-none"
                                           :class="formBadgeClass(f)"
                                           :title="formTooltip(f)"
                                           target="_blank" rel="noopener">
                                            {{ f.short_label || f.type_label || f.type }}
                                            <span class="opacity-75">({{ f.status_label || f.status }})</span>
                                        </a>
                                        <div v-if="f.time_label || f.start_time || f.end_time || (f.data && (f.data.start_time || f.data.end_time))"
                                             class="small text-muted ms-1">
                                            <template v-if="f.time_label">{{ f.time_label }}</template>
                                            <template v-else-if="f.start_time && f.end_time">{{ f.start_time }}〜{{ f.end_time }}</template>
                                            <template v-else-if="f.start_time">{{ f.start_time }}</template>
                                            <template v-else-if="f.end_time">{{ f.end_time }}</template>
                                            <template v-else-if="f.data && f.data.start_time && f.data.end_time">{{ f.data.start_time }}〜{{ f.data.end_time }}</template>
                                        </div>
                                    </div>
                                </template>
                                <span v-else class="text-muted">-</span>
                            </td>
                            <td v-if="isAdministrator">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           role="switch"
                                           :id="'whw-' + m.userid"
                                           v-model="m.work_hours_warning"
                                           :true-value="1"
                                           :false-value="0"
                                           :disabled="warningSavingUserId === m.userid"
                                           @change="toggleWorkHoursWarning(m)"
                                           :title="warningToggleTitle">
                                    <label class="form-check-label small" :for="'whw-' + m.userid">
                                        <span v-if="isWorkHoursWarningEnabled(m)">ON</span>
                                        <span v-else>OFF</span>
                                    </label>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-1 align-items-start">
                                    <template v-if="isAdministrator">
                                        <button v-if="hasUnlockRequest(m.userid)"
                                                type="button"
                                                class="btn btn-sm btn-success"
                                                :disabled="unlockingUserId === m.userid"
                                                @click="approveUnlockRequest(m)"
                                                :title="unlockButtonTitle">
                                            <i class="fa fa-unlock me-1"></i>
                                            <span data-i18n="解除承認">解除承認</span>
                                        </button>
                                        <button v-if="isApp(m.userid)"
                                                type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                :disabled="lockingUserId === m.userid"
                                                @click="lockUserPc(m)"
                                                :title="lockButtonTitle">
                                            <i class="fa fa-lock me-1"></i>
                                            <span data-i18n="ロック">ロック</span>
                                        </button>
                                        <span v-if="!isApp(m.userid) && !hasUnlockRequest(m.userid)" class="text-muted">-</span>
                                    </template>
                                    <span v-else class="text-muted">-</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredMembers.length === 0">
                            <td :colspan="isAdministrator ? 10 : 9" class="text-center text-muted py-4" data-i18n="対象がいません">対象がいません</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
/* App presence badge — same as app-chat / notification presence */
#memberOnlineApp .avatar.avatar-online-app::before {
    content: 'A';
    font-family: system-ui, -apple-system, sans-serif;
    position: absolute;
    top: -2px;
    left: -2px;
    z-index: 2;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #3b82f6;
    color: #fff;
    font-size: 7px;
    font-weight: 700;
    line-height: 14px;
    text-align: center;
    border: 1.5px solid var(--bs-body-bg, #fff);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}
</style>

<script src="https://cdn.jsdelivr.net/npm/vue@3.2.31"></script>

<?php
$view->footing();
?>
