/**
 * 省エネ 決済情報: 申請費 / 設計費 split helpers (見積 + 請求).
 */
(function (window) {
    'use strict';

    var AMOUNT_FIELDS = [
        'estimate_application_amount',
        'estimate_design_amount',
        'invoice_application_amount',
        'invoice_design_amount'
    ];

    var DATE_FIELDS = [
        'estimate_application_date',
        'estimate_design_date',
        'invoice_application_date',
        'invoice_design_date'
    ];

    var STATUS_FIELDS = [
        'estimate_application_status',
        'estimate_design_status',
        'invoice_application_status',
        'invoice_design_status'
    ];

    var ALL_FIELDS = AMOUNT_FIELDS.concat(DATE_FIELDS, STATUS_FIELDS);

    var FEE_ROWS = [
        {
            kind: 'estimate',
            fee: 'application',
            title: '申請費',
            amountKey: 'estimate_application_amount',
            dateKey: 'estimate_application_date',
            statusKey: 'estimate_application_status',
            dateLabel: '見積日',
            amountLabel: '見積金額(税抜き)',
            dropdownId: 'estimateApplicationStatusDropdown'
        },
        {
            kind: 'estimate',
            fee: 'design',
            title: '設計費',
            amountKey: 'estimate_design_amount',
            dateKey: 'estimate_design_date',
            statusKey: 'estimate_design_status',
            dateLabel: '見積日',
            amountLabel: '見積金額(税抜き)',
            dropdownId: 'estimateDesignStatusDropdown'
        },
        {
            kind: 'invoice',
            fee: 'application',
            title: '申請費',
            amountKey: 'invoice_application_amount',
            dateKey: 'invoice_application_date',
            statusKey: 'invoice_application_status',
            dateLabel: '請求日',
            amountLabel: '請求金額(税抜き)',
            dropdownId: 'invoiceApplicationStatusDropdown',
            copyFromAmountKey: 'estimate_application_amount'
        },
        {
            kind: 'invoice',
            fee: 'design',
            title: '設計費',
            amountKey: 'invoice_design_amount',
            dateKey: 'invoice_design_date',
            statusKey: 'invoice_design_status',
            dateLabel: '請求日',
            amountLabel: '請求金額(税抜き)',
            dropdownId: 'invoiceDesignStatusDropdown',
            copyFromAmountKey: 'estimate_design_amount'
        }
    ];

    function isEnergyDepartmentName(name) {
        if (window.EnergyDrawingShare && typeof window.EnergyDrawingShare.isEnergyDepartmentName === 'function') {
            return window.EnergyDrawingShare.isEnergyDepartmentName(name);
        }
        return String(name || '').trim().indexOf('省エネ') !== -1;
    }

    function isEnergyProject(project) {
        if (!project) return false;
        return isEnergyDepartmentName(project.department_name);
    }

    function normalizeStatusValue(status) {
        if (status === '発行済み') return '発行済';
        return status || '未発行';
    }

    /** 発行済 / 無償 / 対象外 (省エネ only; 対象外 acts like 無償). */
    function isReadyForCompletion(status) {
        var n = normalizeStatusValue(status);
        return n === '発行済' || n === '無償' || n === '対象外';
    }

    function isWaivedStatus(status) {
        var n = normalizeStatusValue(status);
        return n === '無償' || n === '対象外';
    }

    var ENERGY_EXTRA_STATUSES = [
        { value: '対象外', label: '対象外', color: 'dark' }
    ];

    function hasAmount(amount) {
        return amount != null && amount !== '' && Number(amount) > 0;
    }

    /**
     * Once: copy legacy amount/date/status into application fee when empty.
     */
    function hydrateApplicationFromLegacy(p) {
        if (!p || !isEnergyProject(p)) return;
        var appAmountEmpty = p.estimate_application_amount == null || p.estimate_application_amount === '';
        var appDateEmpty = !String(p.estimate_application_date || '').trim();
        var appStatusEmpty = !String(p.estimate_application_status || '').trim();
        if (appAmountEmpty && appDateEmpty && appStatusEmpty) {
            if (p.amount != null && p.amount !== '') p.estimate_application_amount = p.amount;
            if (p.estimate_date) p.estimate_application_date = p.estimate_date;
            if (p.estimate_status) p.estimate_application_status = p.estimate_status;
        }
        var invAmountEmpty = p.invoice_application_amount == null || p.invoice_application_amount === '';
        var invDateEmpty = !String(p.invoice_application_date || '').trim();
        var invStatusEmpty = !String(p.invoice_application_status || '').trim();
        if (invAmountEmpty && invDateEmpty && invStatusEmpty) {
            if (p.invoice_amount != null && p.invoice_amount !== '') p.invoice_application_amount = p.invoice_amount;
            if (p.invoice_date) p.invoice_application_date = p.invoice_date;
            if (p.invoice_status) p.invoice_application_status = p.invoice_status;
        }
        STATUS_FIELDS.forEach(function (key) {
            p[key] = normalizeStatusValue(p[key]);
        });
    }

    function needsPaymentInfoBeforeComplete(project) {
        if (!project) return true;
        if (isEnergyProject(project)) {
            return !isReadyForCompletion(project.estimate_application_status)
                || !isReadyForCompletion(project.estimate_design_status)
                || !isReadyForCompletion(project.invoice_application_status)
                || !isReadyForCompletion(project.invoice_design_status);
        }
        return !isReadyForCompletion(project.estimate_status)
            || !isReadyForCompletion(project.invoice_status);
    }

    function getMissingPaymentLabels(project, translateFn) {
        var t = typeof translateFn === 'function' ? translateFn : function (k) { return k; };
        var missing = [];
        if (!project) return missing;
        if (isEnergyProject(project)) {
            if (!isReadyForCompletion(project.estimate_application_status)) missing.push(t('見積申請費'));
            if (!isReadyForCompletion(project.estimate_design_status)) missing.push(t('見積設計費'));
            if (!isReadyForCompletion(project.invoice_application_status)) missing.push(t('請求申請費'));
            if (!isReadyForCompletion(project.invoice_design_status)) missing.push(t('請求設計費'));
        } else {
            if (!isReadyForCompletion(project.estimate_status)) missing.push(t('見積状況'));
            if (!isReadyForCompletion(project.invoice_status)) missing.push(t('請求状況'));
        }
        return missing;
    }

    function isFeeRowComplete(project, row, hasDateFn) {
        if (!project || !row) return false;
        var hasDate = typeof hasDateFn === 'function'
            ? hasDateFn(row.dateKey)
            : !!String(project[row.dateKey] || '').trim();
        return hasDate && hasAmount(project[row.amountKey]);
    }

    function appendEnergyFieldsToFormData(formData, project, getAmountFn, getDateFn) {
        if (!formData || !project) return;
        AMOUNT_FIELDS.forEach(function (key) {
            var v = typeof getAmountFn === 'function' ? getAmountFn(project[key]) : (project[key] != null ? project[key] : '');
            formData.append(key, v === undefined || v === null ? '' : String(v));
        });
        DATE_FIELDS.forEach(function (key) {
            var v = typeof getDateFn === 'function' ? getDateFn(key) : (project[key] || '');
            formData.append(key, v || '');
        });
        STATUS_FIELDS.forEach(function (key) {
            formData.append(key, normalizeStatusValue(project[key]));
        });
    }

    function pickerIdForDateKey(dateKey, prefix) {
        prefix = prefix || '';
        return prefix + dateKey + '_picker';
    }

    window.EnergyFeePayment = {
        AMOUNT_FIELDS: AMOUNT_FIELDS,
        DATE_FIELDS: DATE_FIELDS,
        STATUS_FIELDS: STATUS_FIELDS,
        ALL_FIELDS: ALL_FIELDS,
        FEE_ROWS: FEE_ROWS,
        ENERGY_EXTRA_STATUSES: ENERGY_EXTRA_STATUSES,
        isEnergyDepartmentName: isEnergyDepartmentName,
        isEnergyProject: isEnergyProject,
        normalizeStatusValue: normalizeStatusValue,
        isReadyForCompletion: isReadyForCompletion,
        isWaivedStatus: isWaivedStatus,
        hasAmount: hasAmount,
        hydrateApplicationFromLegacy: hydrateApplicationFromLegacy,
        needsPaymentInfoBeforeComplete: needsPaymentInfoBeforeComplete,
        getMissingPaymentLabels: getMissingPaymentLabels,
        isFeeRowComplete: isFeeRowComplete,
        appendEnergyFieldsToFormData: appendEnergyFieldsToFormData,
        pickerIdForDateKey: pickerIdForDateKey
    };
})(window);
