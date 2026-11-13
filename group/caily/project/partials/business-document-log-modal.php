<?php
/**
 * 決済情報 履歴 modal — 案件詳細, 案件一覧 and 建物詳細.
 * Grouped by day, filter すべて / 見積 / 請求 / 入金 / 備考, old value → new value.
 *
 * Host: showBusinessDocumentLogModal, sortedBusinessDocumentLogs, closeBusinessDocumentLogModal,
 * getBusinessDocumentLogValue, getLogNote, getInitials, formatShortDateTime
 * and BusinessDocumentUiMixin (grouping / filter / formatting).
 * Styles: project/assets/css/project-business-doc.css
 */
?>
    <!-- Business Document History Modal -->
    <div class="modal fade bd-log-modal" tabindex="-1" :class="{show: showBusinessDocumentLogModal}" style="display: block;" v-if="showBusinessDocumentLogModal" @keydown.esc="closeBusinessDocumentLogModal">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header flex-wrap gap-2">
                    <h5 class="modal-title"><i class="fa fa-history me-2"></i>決済情報 履歴</h5>
                    <button type="button" class="btn-close" @click="closeBusinessDocumentLogModal"></button>
                    <div class="w-100 bd-log-filters" role="group" aria-label="種類で絞り込み">
                        <button v-for="opt in bdLogFilterOptions" :key="'bdf-' + opt.value" type="button"
                                class="btn btn-sm" :class="bdLogFilter === opt.value ? 'btn-primary' : 'btn-outline-secondary'"
                                :disabled="opt.value !== '' && opt.count === 0"
                                @click="bdLogFilter = opt.value">
                            {{ opt.label }} <span class="bd-log-filter-count">{{ opt.count }}</span>
                        </button>
                    </div>
                </div>
                <div class="modal-body">
                    <div v-for="group in businessDocumentLogGroups" :key="'bdg-' + group.date" class="bd-log-day">
                        <div class="bd-log-day-label">{{ group.label }}</div>
                        <ul class="bd-log-list">
                            <li v-for="log in group.logs" :key="log.id" class="bd-log-item" :class="'bd-log-cat-' + getBdLogCategory(log)">
                                <div class="bd-log-time">{{ formatBdLogTime(log) }}</div>
                                <div class="bd-log-avatar avatar avatar-sm">
                                    <span class="avatar-initial rounded-circle bg-label-primary">{{ bdLogInitials(log) }}</span>
                                    <img v-if="log.user_image" :src="'/assets/upload/avatar/' + log.user_image" alt="avatar" class="rounded-circle"
                                         style="display:none;"
                                         @load="$event.target.style.display='block'; if ($event.target.previousElementSibling) $event.target.previousElementSibling.style.display='none';"
                                         @error="$event.target.remove()">
                                </div>
                                <div class="bd-log-body">
                                    <div class="bd-log-head">
                                        <span class="bd-log-cat">{{ getBdLogCategoryLabel(log) }}</span>
                                        <i :class="bdLogIcon(log)"></i>
                                        <span class="bd-log-note">{{ getLogNote(log) }}</span>
                                        <span class="bd-log-user">{{ bdLogUserName(log) }}</span>
                                    </div>
                                    <div v-if="bdLogHasValue(log.value1) || bdLogHasValue(log.value2)" class="bd-log-change">
                                        <template v-if="getBdLogValueKind(log) === 'status'">
                                            <span v-if="bdLogHasValue(log.value1)" class="badge bd-log-old-badge" :class="getBdLogStatusBadgeClass(log, 'value1')">{{ formatBdLogValue(log, 'value1') }}</span>
                                            <i v-if="bdLogHasValue(log.value1)" class="fa fa-arrow-right bd-log-arrow"></i>
                                            <span class="badge" :class="getBdLogStatusBadgeClass(log, 'value2')">{{ formatBdLogValue(log, 'value2') }}</span>
                                        </template>
                                        <template v-else>
                                            <span v-if="bdLogHasValue(log.value1)" class="bd-log-old">{{ formatBdLogValue(log, 'value1') }}</span>
                                            <i v-if="bdLogHasValue(log.value1)" class="fa fa-arrow-right bd-log-arrow"></i>
                                            <span class="bd-log-new">{{ formatBdLogValue(log, 'value2') }}</span>
                                        </template>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <div v-if="businessDocumentLogGroups.length === 0" class="text-muted text-center py-4">
                        <i class="fa fa-inbox d-block mb-2 fs-3 opacity-50"></i>
                        {{ bdLogFilter ? 'この種類の履歴はありません。' : '決済情報の履歴はありません。' }}
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="closeBusinessDocumentLogModal">閉じる</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show" v-if="showBusinessDocumentLogModal"></div>
