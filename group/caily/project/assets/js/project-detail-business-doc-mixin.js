/**
 * 案件詳細 — 決済情報 card (detail.php). Amount input and 履歴 helpers: business-document-ui-mixin.js.
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

    window.ProjectDetailBusinessDocMixin = {
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
            }
        }
    };
})();
