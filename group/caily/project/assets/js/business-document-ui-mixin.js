/**
 * 決済情報 UI shared by 案件詳細, 案件一覧 and 建物詳細 (parent_project/detail.php).
 *
 * - Amount inputs: ¥ + 3-digit commas when not focused (bdAmountDisplay / onBdAmount*)
 * - 決済情報 modal (partials/business-document-modal.php): bdm* row adapters over the host's
 *   Bd* methods (BusinessDocumentModalMixin on 案件一覧, parent-project-detail.js on 建物詳細)
 * - 履歴 modal (partials/business-document-log-modal.php): grouped by day, filter by type
 *
 * Host requirements are the existing names on each page: sortedBusinessDocumentLogs,
 * getBusinessDocumentLogValue, getLogNote, getInitials, formatShortDateTime and, for the
 * edit modal, businessDocumentProject + the Bd* methods.
 */
(function () {
    'use strict';

    var STANDARD_MODAL_ROWS = {
        estimate: {
            kind: 'estimate', fee: 'standard', standard: true, title: '見積',
            amountKey: 'amount', dateKey: 'estimate_date', statusKey: 'estimate_status',
            dateLabel: '見積日', amountLabel: '見積金額(税抜き)', dropdownId: 'bdEstimateStatusDropdown'
        },
        invoice: {
            kind: 'invoice', fee: 'standard', standard: true, title: '請求',
            amountKey: 'invoice_amount', dateKey: 'invoice_date', statusKey: 'invoice_status',
            dateLabel: '請求日', amountLabel: '請求金額(税抜き)', dropdownId: 'bdInvoiceStatusDropdown',
            copyFromAmountKey: 'amount'
        }
    };

    var LOG_CATEGORIES = [
        { value: '', label: 'すべて' },
        { value: 'estimate', label: '見積' },
        { value: 'invoice', label: '請求' },
        { value: 'payment', label: '入金' },
        { value: 'note', label: '備考' }
    ];

    var WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    function isEmpty(v) {
        return v === null || v === undefined || String(v).trim() === '';
    }

    window.BusinessDocumentUiMixin = {
        data: function () {
            return {
                bdAmountFocusKey: '',
                bdLogFilter: ''
            };
        },
        computed: {
            /** 決済情報 modal: [{ kind, heading, icon, rows }] */
            bdmSections: function () {
                var energy = !!this.isBdEnergyDepartment;
                return [
                    {
                        kind: 'estimate', heading: '見積', icon: 'fa fa-file-invoice',
                        rows: energy ? this.energyBdEstimateFeeRows : [STANDARD_MODAL_ROWS.estimate]
                    },
                    {
                        kind: 'invoice', heading: '請求', icon: 'fa fa-file-alt',
                        rows: energy ? this.energyBdInvoiceFeeRows : [STANDARD_MODAL_ROWS.invoice]
                    }
                ];
            },
            bdLogFilterOptions: function () {
                var logs = this.sortedBusinessDocumentLogs || [];
                var self = this;
                return LOG_CATEGORIES.map(function (c) {
                    var count = c.value
                        ? logs.filter(function (l) { return self.getBdLogCategory(l) === c.value; }).length
                        : logs.length;
                    return { value: c.value, label: c.label, count: count };
                });
            },
            /** [{ date, label, logs }] newest day first */
            businessDocumentLogGroups: function () {
                var self = this;
                var logs = (this.sortedBusinessDocumentLogs || []).filter(function (l) {
                    return !self.bdLogFilter || self.getBdLogCategory(l) === self.bdLogFilter;
                });
                var groups = [];
                var byDate = {};
                logs.forEach(function (log) {
                    var key = String(log.time || '').slice(0, 10) || '—';
                    if (!byDate[key]) {
                        byDate[key] = { date: key, label: self.formatBdLogDay(key), logs: [] };
                        groups.push(byDate[key]);
                    }
                    byDate[key].logs.push(log);
                });
                return groups;
            }
        },
        methods: {
            // ---------- amount input ----------
            bdAmountDisplay: function (obj, key) {
                var v = obj ? obj[key] : null;
                if (isEmpty(v)) return '';
                if (this.bdAmountFocusKey === key) return String(v);
                var n = Number(v);
                return Number.isFinite(n) ? n.toLocaleString('ja-JP') : String(v);
            },
            onBdAmountFocus: function (key, e) {
                this.bdAmountFocusKey = key;
                var el = e && e.target;
                this.$nextTick(function () {
                    if (el && typeof el.select === 'function') el.select();
                });
            },
            onBdAmountBlur: function () {
                this.bdAmountFocusKey = '';
            },
            /** onChange: the host's save scheduler (scheduleBusinessDocumentUpdate / scheduleBdUpdate) */
            onBdAmountInput: function (obj, key, e, onChange) {
                if (!obj) return;
                var digits = String(e.target.value || '').replace(/[^\d]/g, '');
                if (e.target.value !== digits) e.target.value = digits;
                obj[key] = digits === '' ? '' : Number(digits);
                if (typeof onChange === 'function') onChange();
            },

            // ---------- 決済情報 modal row adapters (host: Bd* methods) ----------
            bdmRowTitle: function (section, row) {
                return row.standard ? section.heading : row.title;
            },
            bdmRowStatusOptions: function (row) {
                if (row.standard) {
                    return row.kind === 'invoice' ? this.businessInvoiceStatuses : this.businessEstimateStatuses;
                }
                return this.getBdEnergyFeeStatusOptions(row.kind);
            },
            bdmRowStatusLabel: function (row) {
                var v = this.businessDocumentProject ? this.businessDocumentProject[row.statusKey] : '';
                if (row.standard) {
                    return row.kind === 'invoice' ? this.getBdInvoiceStatusLabel(v) : this.getBdEstimateStatusLabel(v);
                }
                return this.getBdEnergyFeeStatusLabel(row.kind, v);
            },
            bdmRowStatusButtonClass: function (row) {
                var v = this.businessDocumentProject ? this.businessDocumentProject[row.statusKey] : '';
                if (row.standard) {
                    return row.kind === 'invoice' ? this.getBdInvoiceStatusButtonClass(v) : this.getBdEstimateStatusButtonClass(v);
                }
                return this.getBdEnergyFeeStatusButtonClass(row.kind, v);
            },
            bdmRowIsComplete: function (row) {
                if (row.standard) {
                    return row.kind === 'invoice'
                        ? this.isBdInvoiceDocumentFieldsComplete()
                        : this.isBdEstimateDocumentFieldsComplete();
                }
                return this.isBdEnergyFeeRowComplete(row);
            },
            bdmRowShowIssuedHint: function (row) {
                if (row.standard) {
                    return row.kind === 'invoice'
                        ? this.shouldShowBdInvoiceIssuedHint()
                        : this.shouldShowBdEstimateIssuedHint();
                }
                return this.shouldShowBdEnergyFeeIssuedHint(row);
            },
            bdmRowSelectStatus: function (row, value) {
                if (row.standard) {
                    if (row.kind === 'invoice') this.selectBdInvoiceStatus(value);
                    else this.selectBdEstimateStatus(value);
                    return;
                }
                this.selectBdEnergyFeeStatus(row, value);
            },
            bdmRowCanCopy: function (row) {
                if (row.kind !== 'invoice') return false;
                return row.standard ? this.canCopyBdEstimateAmountToInvoice() : this.canCopyBdEnergyFeeAmountToInvoice(row);
            },
            bdmRowCopy: function (row) {
                if (row.standard) this.copyBdEstimateAmountToInvoice();
                else this.copyBdEnergyFeeAmountToInvoice(row);
            },
            bdmRowTaxIncluded: function (row) {
                var v = this.businessDocumentProject ? this.businessDocumentProject[row.amountKey] : null;
                if (!this.hasBdAmount(v)) return '';
                var s = String(this.formatBusinessDocumentTotalWithTax(v));
                return s.charAt(0) === '¥' ? s : '¥' + s;
            },

            // ---------- 履歴 modal ----------
            getBdLogCategory: function (log) {
                var a = String((log && log.action) || '');
                if (a === 'payment_note_updated') return 'note';
                if (a === 'amount_updated' || a.indexOf('estimate_') === 0) return 'estimate';
                if (a.indexOf('invoice_') === 0) return 'invoice';
                if (a.indexOf('payment_') === 0 || a.indexOf('receipt_') === 0) return 'payment';
                var note = String((log && log.note) || '');
                if (/見積/.test(note)) return 'estimate';
                if (/請求/.test(note)) return 'invoice';
                if (/入金|領収書/.test(note)) return 'payment';
                return 'note';
            },
            getBdLogCategoryLabel: function (log) {
                var c = this.getBdLogCategory(log);
                for (var i = 0; i < LOG_CATEGORIES.length; i++) {
                    if (LOG_CATEGORIES[i].value === c) return LOG_CATEGORIES[i].label;
                }
                return '';
            },
            formatBdLogDay: function (ymd) {
                var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(ymd || ''));
                if (!m) return ymd;
                var d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
                return m[1] + '/' + m[2] + '/' + m[3] + '（' + WEEKDAYS[d.getDay()] + '）';
            },
            formatBdLogTime: function (log) {
                var m = /\d{2}:\d{2}/.exec(String((log && log.time) || '').slice(10));
                return m ? m[0] : '';
            },
            bdLogHasValue: function (v) {
                return !isEmpty(v);
            },
            bdLogUserName: function (log) {
                if (typeof this.getBdLogDisplayName === 'function') return this.getBdLogDisplayName(log);
                return (log && (log.username || log.realname || log.user)) || '';
            },
            bdLogInitials: function (log) {
                return this.getInitials(log.username || log.realname || '?', log.userid || log.user_id || '', log.user_ruby || '');
            },
            bdLogIcon: function (log) {
                var a = log && log.action;
                if (typeof this.bdHistoryIcon === 'function') return this.bdHistoryIcon(a);
                if (typeof this.historyIcon === 'function') return this.historyIcon(a);
                return 'fa fa-history';
            },
            /** 'money' | 'date' | 'status' | 'text' */
            getBdLogValueKind: function (log) {
                var a = String((log && log.action) || '');
                if (/amount_updated$/.test(a)) return 'money';
                if (/_date_updated$/.test(a)) return 'date';
                if (/_status_updated$/.test(a)) return 'status';
                return 'text';
            },
            formatBdLogValue: function (log, field) {
                var v = log ? log[field] : null;
                if (isEmpty(v)) return '—';
                var kind = this.getBdLogValueKind(log);
                if (kind === 'money') {
                    var n = Number(v);
                    return Number.isFinite(n) ? '¥' + n.toLocaleString('ja-JP') : String(v);
                }
                if (kind === 'date') {
                    return this.formatShortDateTime(v) || String(v);
                }
                return this.getBusinessDocumentLogValue(log, field);
            },
            getBdLogStatusBadgeClass: function (log, field) {
                var a = String((log && log.action) || '');
                var v = log ? log[field] : '';
                var fn = null;
                if (a.indexOf('invoice') === 0) {
                    fn = this.getBusinessInvoiceStatusBadgeClass || this.getInvoiceStatusBadgeClass;
                } else if (a.indexOf('estimate') === 0) {
                    fn = this.getBusinessEstimateStatusBadgeClass || this.getEstimateStatusBadgeClass;
                } else if (a.indexOf('payment') === 0) {
                    fn = this.getBusinessPaymentStatusBadgeClass || this.getPaymentStatusBadgeClass;
                }
                if (typeof fn === 'function') return fn.call(this, v);
                return field === 'value1' ? 'bg-label-secondary' : 'bg-label-primary';
            }
        }
    };
})();
