<?php
/**
 * 決済情報 modal — 案件一覧 (project/index.php) and 建物詳細 (parent_project/detail.php).
 * Same block layout as the 決済情報 card on 案件詳細.
 *
 * Host: businessDocumentProject, the Bd* methods (BusinessDocumentModalMixin / parent-project-detail.js)
 * and BusinessDocumentUiMixin (bdm* adapters, ¥ amount input).
 * Styles: project/assets/css/project-business-doc.css
 */
?>
    <!-- Business Document (決済情報) Modal -->
    <div class="modal fade bd-edit-modal" id="businessDocumentModal" tabindex="-1" aria-labelledby="businessDocumentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content bd-scope" v-if="businessDocumentProject">
                <div class="modal-header">
                    <div class="bd-modal-heading">
                        <h5 class="modal-title mb-0 d-flex align-items-center gap-2" id="businessDocumentModalLabel">
                            <span data-i18n="決済情報">決済情報</span>
                            <span v-if="businessDocumentSaveStatus === 'loading'" class="text-muted fs-6" title="保存中">
                                <i class="fa fa-spinner fa-spin"></i>
                            </span>
                            <span v-else-if="businessDocumentSaveStatus === 'saved'" class="text-success fs-6" title="保存済み">
                                <i class="fa fa-check-circle"></i>
                            </span>
                        </h5>
                        <div class="bd-modal-project">#{{ businessDocumentProjectId }} {{ businessDocumentProject.name }}</div>
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <button type="button" class="btn btn-outline-info btn-sm" @click="openBusinessDocumentLogModal">
                            <i class="fa fa-history me-1"></i><span data-i18n="履歴">履歴</span>
                        </button>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" @click="closeBusinessDocumentModal"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <input type="hidden" v-model.number="businessDocumentProject.payment_version">

                    <div v-for="section in bdmSections" :key="'bdm-' + section.kind" class="bd-section">
                        <div v-if="isBdEnergyDepartment" class="bd-section-title">
                            <i :class="section.icon" class="me-1"></i><span :data-i18n="section.heading">{{ section.heading }}</span>
                        </div>
                        <div v-for="row in section.rows" :key="'bdm-' + section.kind + '-' + row.fee" class="bd-block" :class="'bd-block-' + row.kind">
                            <div class="bd-block-head">
                                <div class="bd-block-title">
                                    <i v-if="row.standard" :class="section.icon" class="me-1"></i>
                                    <span :data-i18n="bdmRowTitle(section, row)">{{ bdmRowTitle(section, row) }}</span>
                                    <span v-if="bdmRowShowIssuedHint(row)" class="bd-hint-icon" tabindex="0" :data-tip="'発行済にするには' + row.dateLabel + '・' + row.amountLabel + 'が必要です'" :aria-label="'発行済にするには' + row.dateLabel + '・' + row.amountLabel + 'が必要です'"><i class="fa fa-info-circle"></i></span>
                                </div>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm dropdown-toggle waves-effect waves-light bd-status-btn"
                                            :class="bdmRowStatusButtonClass(row)"
                                            :id="row.dropdownId" data-bs-toggle="dropdown" aria-expanded="false">
                                        {{ bdmRowStatusLabel(row) }}
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li v-for="status in bdmRowStatusOptions(row)" :key="status.value">
                                            <a class="dropdown-item waves-effect" href="javascript:void(0);"
                                               :class="{ disabled: status.value === '発行済' && !bdmRowIsComplete(row), active: bdmRowStatusLabel(row) === translateLabel(status.label) }"
                                               @click="bdmRowSelectStatus(row, status.value)">{{ status.label }}</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="bd-fields">
                                <div class="bd-field">
                                    <label class="bd-label" :for="bdModalPickerId(row.dateKey)">{{ row.dateLabel }}<span class="text-danger ms-1">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" v-model="businessDocumentProject[row.dateKey]"
                                               :id="bdModalPickerId(row.dateKey)" :placeholder="getProjectDateTimePlaceholder()" autocomplete="off">
                                        <button v-if="!hasBdDate(row.dateKey)" type="button" class="btn btn-outline-primary" title="今日の日付を入力"
                                                @click="setBdDateToday(row.dateKey)">今日</button>
                                    </div>
                                </div>
                                <div class="bd-field">
                                    <label class="bd-label">{{ row.amountLabel }}<span class="text-danger ms-1">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">¥</span>
                                        <input type="text" inputmode="numeric" autocomplete="off" class="form-control text-end bd-amount-input"
                                               :value="bdAmountDisplay(businessDocumentProject, row.amountKey)" placeholder="未入力" data-i18n="未入力"
                                               @focus="onBdAmountFocus(row.amountKey, $event)" @blur="onBdAmountBlur"
                                               @input="onBdAmountInput(businessDocumentProject, row.amountKey, $event, scheduleBdUpdate)">
                                        <button v-if="bdmRowCanCopy(row)" type="button" class="btn btn-outline-primary" title="見積金額をコピー"
                                                @click="bdmRowCopy(row)">見積と同額</button>
                                    </div>
                                    <div v-if="bdmRowTaxIncluded(row)" class="bd-sub text-end">税込 {{ bdmRowTaxIncluded(row) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-0 bd-note">
                        <label class="bd-label" data-i18n="決済備考">決済備考</label>
                        <textarea class="form-control form-control-sm" rows="3" placeholder="備考を入力" v-model="businessDocumentProject.payment_note" @change="scheduleBdUpdate"></textarea>
                    </div>
                    <div v-if="businessDocumentError" class="alert alert-danger mt-3 mb-0">{{ businessDocumentError }}</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" @click="closeBusinessDocumentModal">閉じる</button>
                </div>
            </div>
        </div>
    </div>
