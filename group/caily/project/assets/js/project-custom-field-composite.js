/**
 * 設備図 / 意匠図 / 配置図: セットの選択肢をそのまま使い、値選択時に【メモ】を表示
 * (案件詳細・一覧クイック編集・建物詳細の子案件モーダルで共用)
 */
(function(global) {
    'use strict';

    var BASE_LABELS = { '設備図': true, '意匠図': true, '配置図': true };

    function isBaseLabel(label) {
        return !!BASE_LABELS[String(label || '').trim()];
    }

    function isCompanionLabel(label) {
        // 【メモ】= current companion; 【〇】= legacy companion (no longer used)
        return /【(?:メモ|〇|○)】$/.test(String(label || '').trim());
    }

    function memoLabel(base) {
        return String(base || '').trim() + '【メモ】';
    }

    function parseOptions(options) {
        return String(options == null ? '' : options)
            .split(',')
            .map(function(s) { return s.trim(); })
            .filter(Boolean);
    }

    /** 値が選ばれているか（ラジオ/チェックとも非空なら true） */
    function isValueSelected(value) {
        return String(value == null ? '' : value).trim() !== '';
    }

    /**
     * Drop saved values that are not in the current set options.
     * Prevents legacy orphans (e.g. "GUIS" when options are only "〇") from
     * surviving in valueArr and producing history like GUIS → GUIS,〇.
     */
    function sanitizeMainValue(value, options, type) {
        var opts = parseOptions(options);
        if (!opts.length) {
            return String(value == null ? '' : value).trim();
        }
        var allowed = {};
        opts.forEach(function(o) { allowed[o] = true; });
        var parts = String(value == null ? '' : value)
            .split(',')
            .map(function(s) { return s.trim(); })
            .filter(Boolean);
        if (isCheckboxType(type)) {
            return parts.filter(function(v) { return !!allowed[v]; }).join(',');
        }
        // radio / select: keep exact match, else first allowed token from a comma list
        var raw = String(value == null ? '' : value).trim();
        if (raw && allowed[raw]) return raw;
        for (var i = 0; i < parts.length; i++) {
            if (allowed[parts[i]]) return parts[i];
        }
        return '';
    }

    function escapeAttr(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    /** Filter memo companions; keep original type/options for composite bases */
    function normalizeMergedFields(mergedFields) {
        var out = [];
        (mergedFields || []).forEach(function(f) {
            if (!f || !f.label) return;
            var label = String(f.label).trim();
            if (!label || isCompanionLabel(label)) return;
            if (isBaseLabel(label)) {
                out.push({
                    label: label,
                    type: f.type || 'text',
                    options: f.options != null ? f.options : '',
                    one_row: f.one_row,
                    isComposite: true
                });
                return;
            }
            out.push(f);
        });
        return out;
    }

    function isCheckboxType(type) {
        return type === 'checkbox' || type === 'checkbox_select_all';
    }

    /**
     * Build inner HTML for a composite field (set options + conditional memo).
     * Caller should set data-custom-options on the row when using collectFromRow sanitize.
     */
    function buildCompositeInnerHtml(opts) {
        var label = opts.label || '';
        var type = opts.type || 'radio';
        var optionList = parseOptions(opts.options);
        var val = sanitizeMainValue(opts.val, opts.options, type);
        var selectedArr = val ? val.split(',').map(function(s) { return s.trim(); }).filter(Boolean) : [];
        var memoVal = isValueSelected(val) ? (opts.memoVal || '') : '';
        var radioName = opts.radioName || ('composite_radio_' + label);
        var radioClass = opts.radioClass || 'customCompositeRadio';
        var checkboxClass = opts.checkboxClass || 'customCompositeCheckbox';
        var memoInputClass = opts.memoInputClass || 'customCompositeMemoInput';
        var safeLabel = opts.safeLabel != null ? opts.safeLabel : escapeAttr(label);
        var showMemo = selectedArr.length > 0;
        var html = '<label class="form-label">' + escapeHtml(label) + '</label>';
        html += '<div class="d-flex flex-wrap align-items-center gap-3">';

        if (isCheckboxType(type)) {
            optionList.forEach(function(opt) {
                var checked = selectedArr.indexOf(opt) !== -1;
                html += '<div class="form-check form-check-inline mb-0">'
                    + '<input class="form-check-input ' + checkboxClass + '" type="checkbox"'
                    + ' data-custom-label="' + safeLabel + '" value="' + escapeAttr(opt) + '"'
                    + (checked ? ' checked' : '') + '>'
                    + '<label class="form-check-label">' + escapeHtml(opt) + '</label></div>';
            });
        } else {
            // radio / select 扱い: 選択肢をラジオで表示（セットの options をそのまま）
            optionList.forEach(function(opt) {
                html += '<div class="form-check form-check-inline mb-0">'
                    + '<input class="form-check-input ' + radioClass + '" type="radio" name="' + escapeAttr(radioName) + '"'
                    + ' data-custom-label="' + safeLabel + '" value="' + escapeAttr(opt) + '"'
                    + (selectedArr.indexOf(opt) !== -1 ? ' checked' : '') + '>'
                    + '<label class="form-check-label">' + escapeHtml(opt) + '</label></div>';
            });
        }

        // Use d-none (not inline display:none) — .d-flex is !important and would ignore style.display
        html += '<div class="d-flex align-items-center gap-1 flex-grow-1 custom-composite-memo-wrap'
            + (showMemo ? '' : ' d-none') + '" style="min-width:8rem;">'
            + '<span class="text-nowrap small text-muted">【メモ】</span>'
            + '<input type="text" class="form-control form-control-sm ' + memoInputClass + '"'
            + ' data-custom-label="' + escapeAttr(memoLabel(label)) + '"'
            + ' data-composite-base="' + safeLabel + '"'
            + ' value="' + escapeAttr(memoVal) + '" placeholder="メモ">'
            + '</div>';
        html += '</div>';
        return html;
    }

    /** Show/hide 【メモ】 for a composite row based on checked options */
    function syncMemoVisibility(row, radioClass, checkboxClass) {
        if (!row) return;
        var rCls = radioClass || 'customCompositeRadio';
        var cCls = checkboxClass || 'customCompositeCheckbox';
        var memoWrap = row.querySelector('.custom-composite-memo-wrap');
        if (!memoWrap) return;
        var anyChecked = row.querySelectorAll(
            '.' + rCls + ':checked, .' + cCls + ':checked'
        ).length > 0;
        if (anyChecked) {
            memoWrap.classList.remove('d-none');
            memoWrap.style.removeProperty('display');
        } else {
            memoWrap.classList.add('d-none');
            memoWrap.style.removeProperty('display');
            var memoInput = memoWrap.querySelector('input');
            if (memoInput) memoInput.value = '';
        }
    }

    /**
     * Toggle memo wrap when any option in the composite row changes.
     * Always rebinds so reused containers (quick-edit / parent modal) stay correct.
     */
    function bindEvents(wrapEl, radioClass, checkboxClass) {
        if (!wrapEl) return;
        var rCls = radioClass || 'customCompositeRadio';
        var cCls = checkboxClass || 'customCompositeCheckbox';
        if (wrapEl.__compositeChangeHandler) {
            wrapEl.removeEventListener('change', wrapEl.__compositeChangeHandler);
        }
        wrapEl.__compositeChangeHandler = function(e) {
            var t = e.target;
            if (!t || !t.classList) return;
            if (!t.classList.contains(rCls) && !t.classList.contains(cCls)) return;
            var row = t.closest('[data-custom-composite="1"]');
            if (!row) return;
            syncMemoVisibility(row, rCls, cCls);
        };
        wrapEl.addEventListener('change', wrapEl.__compositeChangeHandler);
        wrapEl.__compositeBound = true;
        // Sync current rows (e.g. after rebuild with saved values)
        var rows = wrapEl.querySelectorAll('[data-custom-composite="1"]');
        for (var i = 0; i < rows.length; i++) {
            syncMemoVisibility(rows[i], rCls, cCls);
        }
    }

    /**
     * Collect main value + memo from a composite row.
     */
    function collectFromRow(row, radioClass, memoInputClass, checkboxClass) {
        if (!row) return [];
        var label = (row.getAttribute('data-custom-label') || '').replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>');
        if (!label || !isBaseLabel(label)) return [];
        var type = row.getAttribute('data-custom-type') || 'radio';
        var rCls = radioClass || 'customCompositeRadio';
        var cCls = checkboxClass || 'customCompositeCheckbox';
        var mCls = memoInputClass || 'customCompositeMemoInput';
        var mainVal = '';
        if (isCheckboxType(type)) {
            mainVal = Array.prototype.map.call(
                row.querySelectorAll('.' + cCls + ':checked'),
                function(el) { return el.value; }
            ).join(',');
        } else {
            var checked = row.querySelector('.' + rCls + ':checked');
            mainVal = checked ? checked.value : '';
        }
        var memoInput = row.querySelector('.' + mCls);
        var optionsAttr = row.getAttribute('data-custom-options');
        mainVal = sanitizeMainValue(mainVal, optionsAttr != null ? optionsAttr : '', type);
        var memoVal = (isValueSelected(mainVal) && memoInput) ? String(memoInput.value || '').trim() : '';
        return [
            { label: label, value: mainVal },
            { label: memoLabel(label), value: memoVal }
        ];
    }

    global.ProjectCustomFieldComposite = {
        BASE_LABELS: BASE_LABELS,
        isBaseLabel: isBaseLabel,
        isCompanionLabel: isCompanionLabel,
        memoLabel: memoLabel,
        parseOptions: parseOptions,
        isValueSelected: isValueSelected,
        isCheckboxType: isCheckboxType,
        sanitizeMainValue: sanitizeMainValue,
        /** @deprecated use isValueSelected */
        isRadioSelected: isValueSelected,
        normalizeMergedFields: normalizeMergedFields,
        buildCompositeInnerHtml: buildCompositeInnerHtml,
        syncMemoVisibility: syncMemoVisibility,
        bindEvents: bindEvents,
        collectFromRow: collectFromRow,
        escapeAttr: escapeAttr,
        escapeHtml: escapeHtml
    };
})(typeof window !== 'undefined' ? window : this);
