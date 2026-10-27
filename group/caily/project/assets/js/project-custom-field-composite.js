/**
 * 設備図 / 意匠図 / 配置図: セットの選択肢をそのまま使い、値選択時に【メモ】を表示
 * 設備図 / 意匠図: 追加で【受領済み】チェック（【メモ】の直前）
 * (案件詳細・一覧クイック編集・建物詳細の子案件モーダルで共用)
 */
(function(global) {
    'use strict';

    var BASE_LABELS = { '設備図': true, '意匠図': true, '配置図': true };
    /** 意匠図 uses the same type/options as 設備図 when both exist in the set */
    var OPTIONS_SOURCE_LABEL = '設備図';
    var OPTIONS_FOLLOW_LABELS = { '意匠図': true };
    var DEFAULT_SETSUBI_OPTIONS = 'GUIS,他社';
    /** Only these bases get 受領済み checkbox */
    var RECEIVED_BASE_LABELS = { '設備図': true, '意匠図': true };
    var RECEIVED_VALUE = '1';

    function isBaseLabel(label) {
        return !!BASE_LABELS[String(label || '').trim()];
    }

    function supportsReceived(label) {
        return !!RECEIVED_BASE_LABELS[String(label || '').trim()];
    }

    /**
     * Make 意匠図 options/type match 設備図 (fallback GUIS,他社 if 設備図 missing).
     */
    function alignOptionsWithSetsubi(fields) {
        var list = Array.isArray(fields) ? fields.slice() : [];
        var source = null;
        for (var i = 0; i < list.length; i++) {
            if (list[i] && String(list[i].label || '').trim() === OPTIONS_SOURCE_LABEL) {
                source = list[i];
                break;
            }
        }
        var srcOptions = source
            ? (Array.isArray(source.options) ? source.options.join(',') : String(source.options != null ? source.options : ''))
            : DEFAULT_SETSUBI_OPTIONS;
        var srcType = source ? (source.type || 'radio') : 'radio';
        for (var j = 0; j < list.length; j++) {
            var f = list[j];
            if (!f || !f.label) continue;
            var label = String(f.label).trim();
            if (!OPTIONS_FOLLOW_LABELS[label]) continue;
            list[j] = Object.assign({}, f, {
                options: srcOptions,
                type: srcType
            });
        }
        return list;
    }

    function isCompanionLabel(label) {
        // 【メモ】/【受領済み】= companions; 【〇】= legacy
        return /【(?:メモ|受領済み|〇|○)】$/.test(String(label || '').trim());
    }

    function memoLabel(base) {
        return String(base || '').trim() + '【メモ】';
    }

    function receivedLabel(base) {
        return String(base || '').trim() + '【受領済み】';
    }

    function isReceivedChecked(value) {
        var s = String(value == null ? '' : value).trim();
        return s === RECEIVED_VALUE || s === '受領済み' || s === '1' || s === 'true' || s === 'on';
    }

    function normalizeReceivedValue(value) {
        return isReceivedChecked(value) ? RECEIVED_VALUE : '';
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
        return alignOptionsWithSetsubi(out);
    }

    function isCheckboxType(type) {
        return type === 'checkbox' || type === 'checkbox_select_all';
    }

    /**
     * Build inner HTML for a composite field (set options + optional 受領済み + conditional memo).
     * Caller should set data-custom-options on the row when using collectFromRow sanitize.
     */
    function buildCompositeInnerHtml(opts) {
        var label = opts.label || '';
        var type = opts.type || 'radio';
        var optionList = parseOptions(opts.options);
        var val = sanitizeMainValue(opts.val, opts.options, type);
        var selectedArr = val ? val.split(',').map(function(s) { return s.trim(); }).filter(Boolean) : [];
        var showExtras = selectedArr.length > 0;
        var memoVal = showExtras ? (opts.memoVal || '') : '';
        var receivedChecked = showExtras && supportsReceived(label) && isReceivedChecked(opts.receivedVal);
        var radioName = opts.radioName || ('composite_radio_' + label);
        var radioClass = opts.radioClass || 'customCompositeRadio';
        var checkboxClass = opts.checkboxClass || 'customCompositeCheckbox';
        var memoInputClass = opts.memoInputClass || 'customCompositeMemoInput';
        var receivedClass = opts.receivedClass || 'customCompositeReceived';
        var safeLabel = opts.safeLabel != null ? opts.safeLabel : escapeAttr(label);
        var html = '<label class="form-label">' + escapeHtml(label) + '</label>';
        // Row 1: options (radio/checkbox) + 受領済み
        html += '<div class="d-flex flex-wrap align-items-center gap-3 custom-composite-options-row">';

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

        if (supportsReceived(label)) {
            html += '<div class="form-check form-check-inline mb-0 custom-composite-received-wrap'
                + (showExtras ? '' : ' d-none') + '">'
                + '<input class="form-check-input ' + receivedClass + '" type="checkbox"'
                + ' data-custom-label="' + escapeAttr(receivedLabel(label)) + '"'
                + ' data-composite-base="' + safeLabel + '"'
                + ' value="' + RECEIVED_VALUE + '"'
                + (receivedChecked ? ' checked' : '') + '>'
                + '<label class="form-check-label text-nowrap">受領済み</label></div>';
        }
        html += '</div>';

        // Row 2: 【メモ】 only — Use d-none (not inline display:none); .d-flex is !important
        html += '<div class="d-flex align-items-center gap-2 mt-2 custom-composite-memo-wrap'
            + (showExtras ? '' : ' d-none') + '" style="min-width:8rem;">'
            + '<span class="text-nowrap small text-muted">【メモ】</span>'
            + '<input type="text" class="form-control form-control-sm ' + memoInputClass + '"'
            + ' data-custom-label="' + escapeAttr(memoLabel(label)) + '"'
            + ' data-composite-base="' + safeLabel + '"'
            + ' value="' + escapeAttr(memoVal) + '" placeholder="メモ" style="min-width:6rem;">'
            + '</div>';
        return html;
    }

    /** Show/hide 受領済み + 【メモ】 for a composite row based on checked options */
    function syncMemoVisibility(row, radioClass, checkboxClass) {
        if (!row) return;
        var rCls = radioClass || 'customCompositeRadio';
        var cCls = checkboxClass || 'customCompositeCheckbox';
        var memoWrap = row.querySelector('.custom-composite-memo-wrap');
        var receivedWrap = row.querySelector('.custom-composite-received-wrap');
        var anyChecked = row.querySelectorAll(
            '.' + rCls + ':checked, .' + cCls + ':checked'
        ).length > 0;
        if (anyChecked) {
            if (memoWrap) {
                memoWrap.classList.remove('d-none');
                memoWrap.style.removeProperty('display');
            }
            if (receivedWrap) {
                receivedWrap.classList.remove('d-none');
                receivedWrap.style.removeProperty('display');
            }
        } else {
            if (memoWrap) {
                memoWrap.classList.add('d-none');
                memoWrap.style.removeProperty('display');
                var memoInput = memoWrap.querySelector('input[type="text"]');
                if (memoInput) memoInput.value = '';
            }
            if (receivedWrap) {
                receivedWrap.classList.add('d-none');
                receivedWrap.style.removeProperty('display');
                var receivedInput = receivedWrap.querySelector('input[type="checkbox"]');
                if (receivedInput) receivedInput.checked = false;
            }
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
     * Collect main value + optional 受領済み + memo from a composite row.
     */
    function collectFromRow(row, radioClass, memoInputClass, checkboxClass, receivedClass) {
        if (!row) return [];
        var label = (row.getAttribute('data-custom-label') || '').replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>');
        if (!label || !isBaseLabel(label)) return [];
        var type = row.getAttribute('data-custom-type') || 'radio';
        var rCls = radioClass || 'customCompositeRadio';
        var cCls = checkboxClass || 'customCompositeCheckbox';
        var mCls = memoInputClass || 'customCompositeMemoInput';
        var recvCls = receivedClass || 'customCompositeReceived';
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
        var receivedInput = row.querySelector('.' + recvCls);
        var optionsAttr = row.getAttribute('data-custom-options');
        mainVal = sanitizeMainValue(mainVal, optionsAttr != null ? optionsAttr : '', type);
        var selected = isValueSelected(mainVal);
        var memoVal = (selected && memoInput) ? String(memoInput.value || '').trim() : '';
        var out = [
            { label: label, value: mainVal },
            { label: memoLabel(label), value: memoVal }
        ];
        if (supportsReceived(label)) {
            var receivedVal = (selected && receivedInput && receivedInput.checked) ? RECEIVED_VALUE : '';
            out.splice(1, 0, { label: receivedLabel(label), value: receivedVal });
        }
        return out;
    }

    global.ProjectCustomFieldComposite = {
        BASE_LABELS: BASE_LABELS,
        RECEIVED_BASE_LABELS: RECEIVED_BASE_LABELS,
        RECEIVED_VALUE: RECEIVED_VALUE,
        isBaseLabel: isBaseLabel,
        supportsReceived: supportsReceived,
        isCompanionLabel: isCompanionLabel,
        memoLabel: memoLabel,
        receivedLabel: receivedLabel,
        isReceivedChecked: isReceivedChecked,
        normalizeReceivedValue: normalizeReceivedValue,
        parseOptions: parseOptions,
        isValueSelected: isValueSelected,
        isCheckboxType: isCheckboxType,
        sanitizeMainValue: sanitizeMainValue,
        /** @deprecated use isValueSelected */
        isRadioSelected: isValueSelected,
        normalizeMergedFields: normalizeMergedFields,
        alignOptionsWithSetsubi: alignOptionsWithSetsubi,
        buildCompositeInnerHtml: buildCompositeInnerHtml,
        syncMemoVisibility: syncMemoVisibility,
        bindEvents: bindEvents,
        collectFromRow: collectFromRow,
        escapeAttr: escapeAttr,
        escapeHtml: escapeHtml
    };
})(typeof window !== 'undefined' ? window : this);
