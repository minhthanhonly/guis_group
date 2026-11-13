/**
 * 案件詳細 — 決済情報 card + 決済情報 履歴 modal (detail.php).
 *
 * The card renders 見積 / 請求 as sections of rows. A standard project has one row per
 * section (estimate_date / amount / estimate_status …); a 省エネ project has one row per fee
 * (EnergyFeePayment.FEE_ROWS). The bdRow* adapters route each row to the existing
 * standard or energy-fee methods of project-detail.js, so validation and saving are unchanged.
 */
(function () {
    'use strict';

    var STANDARD_ROWS = {
        estimate: {
            kind: 'estimate',
            fee: 'standard',
            standard: true,
            title: '見積',
            amountKey: 'amount',
            dateKey: 'estimate_date',
            statusKey: 'estimate_status',
            dateLabel: '見積日',
            amountLabel: '見積金額(税抜き)',
            dropdownId: 'estimateStatusDropdown'
        },
        invoice: {
            kind: 'invoice',
            fee: 'standard',
            standard: true,
            title: '請求',
            amountKey: 'invoice_amount',
            dateKey: 'invoice_date',
            statusKey: 'invoice_status',
            dateLabel: '請求日',
            amountLabel: '請求金額(税抜き)',
            dropdownId: 'invoiceStatusDropdown',
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

    window.ProjectDetailBusinessDocMixin = {
        data: function () {
            return {
                bdAmountFocusKey: '',
                bdLogFilter: ''
            };
        },
        computed: {
            /** [{ kind, heading, icon, rows }] — 見積 then 請求 */
            businessDocumentSections: function () {
                var energy = !!this.isEnergyDepartmentProject;
                return [
                    {
                        kind: 'estimate',
                        heading: '見積',
                        icon: 'fa fa-file-invoice',
                        rows: energy ? this.energyEstimateFeeRows : [STANDARD_ROWS.estimate]
                    },
                    {
                        kind: 'invoice',
                        heading: '請求',
                        icon: 'fa fa-file-alt',
                        rows: energy ? this.energyInvoiceFeeRows : [STANDARD_ROWS.invoice]
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
            // ---------- card: row adapters ----------
            bdRowTitle: function (section, row) {
                return row.standard ? section.heading : row.title;
            },
            bdRowStatusOptions: function (row) {
                if (row.standard) {
                    return row.kind === 'invoice' ? this.businessInvoiceStatuses : this.businessEstimateStatuses;
                }
                return this.getEnergyFeeStatusOptions(row.kind);
            },
            bdRowIsComplete: function (row) {
                if (row.standard) {
                    return row.kind === 'invoice'
                        ? this.isInvoiceDocumentFieldsComplete()
                        : this.isEstimateDocumentFieldsComplete();
                }
                return this.isEnergyFeeRowComplete(row);
            },
            bdRowShowIssuedHint: function (row) {
                if (row.standard) {
                    return row.kind === 'invoice'
                        ? this.shouldShowInvoiceIssuedHint()
                        : this.shouldShowEstimateIssuedHint();
                }
                return this.shouldShowEnergyFeeIssuedHint(row);
            },
            bdRowSelectStatus: function (row, value) {
                if (row.standard) {
                    if (row.kind === 'invoice') this.selectInvoiceStatus(value);
                    else this.selectEstimateStatus(value);
                    return;
                }
                this.selectEnergyFeeStatus(row, value);
            },
            bdRowCanCopy: function (row) {
                if (row.kind !== 'invoice') return false;
                return row.standard ? this.canCopyEstimateAmountToInvoice() : this.canCopyEnergyFeeAmountToInvoice(row);
            },
            bdRowCopy: function (row) {
                if (row.standard) this.copyEstimateAmountToInvoice();
                else this.copyEnergyFeeAmountToInvoice(row);
            },
            bdRowTaxIncluded: function (row) {
                var v = this.project ? this.project[row.amountKey] : null;
                if (!this.hasBusinessDocumentAmount(v)) return '';
                return this.formatBusinessDocumentTotalWithTax(v);
            },

            // ---------- card: amount input (¥ + 3-digit commas when not focused) ----------
            bdAmountDisplay: function (key) {
                var v = this.project ? this.project[key] : null;
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
            onBdAmountInput: function (key, e) {
                if (!this.project) return;
                var digits = String(e.target.value || '').replace(/[^\d]/g, '');
                if (e.target.value !== digits) e.target.value = digits;
                this.project[key] = digits === '' ? '' : Number(digits);
                this.scheduleBusinessDocumentUpdate();
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
            /** 'money' | 'date' | 'status' | 'text' — how a value of this log is shown */
            getBdLogValueKind: function (log) {
                var a = String((log && log.action) || '');
                if (/amount_updated$/.test(a)) return 'money';
                if (/_date_updated$/.test(a)) return 'date';
                if (/_status_updated$/.test(a)) return 'status';
                return 'text';
            },
            formatBdLogValue: function (log, field) {
                var v = log ? log[field] : null;
                if (!this.hasLogValue(v)) return '—';
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
                if (a.indexOf('invoice') === 0) return this.getInvoiceStatusBadgeClass(v);
                if (a.indexOf('estimate') === 0) return this.getEstimateStatusBadgeClass(v);
                return field === 'value1' ? 'bg-label-secondary' : 'bg-label-primary';
            }
        }
    };
})();
