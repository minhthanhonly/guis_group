/**
 * 予定工程 (yotei) helpers — 開始/終了 month (YYYY-MM) + 上旬/中旬/下旬
 * Month UI uses flatpickr monthSelect with app i18n locale.
 */
(function (global) {
    'use strict';

    var PART_LABELS = {
        early: '上旬',
        mid: '中旬',
        late: '下旬'
    };

    function t(key) {
        if (typeof i18next !== 'undefined' && i18next.isInitialized && typeof i18next.t === 'function') {
            var v = i18next.t(key);
            if (v && v !== key) return v;
            // fallbackLng en with empty dict returns key — keep Japanese key as UI default
            return key;
        }
        return key;
    }

    function getAppLang() {
        var lng = '';
        if (typeof i18next !== 'undefined' && i18next.language) {
            lng = String(i18next.language);
        } else if (typeof document !== 'undefined' && document.documentElement) {
            lng = String(document.documentElement.lang || '');
        }
        return lng.toLowerCase();
    }

    function getFlatpickrLocale() {
        // App language codes: "en" = Japanese UI, "vi" = Vietnamese
        // (see layout-top.php language dropdown)
        var lng = getAppLang();
        if (lng.indexOf('vi') === 0) return 'vi';
        return 'ja';
    }

    function emptyModel() {
        return {
            from_month: '',
            from_part: '',
            to_month: '',
            to_part: ''
        };
    }

    function normalizePart(part) {
        var p = String(part || '').trim();
        if (p === 'early' || p === '上旬') return 'early';
        if (p === 'mid' || p === '中旬') return 'mid';
        if (p === 'late' || p === '下旬') return 'late';
        return '';
    }

    function parse(raw) {
        if (!raw) return emptyModel();
        var obj = raw;
        if (typeof raw === 'string') {
            try {
                obj = JSON.parse(raw);
            } catch (e) {
                return emptyModel();
            }
        }
        if (!obj || typeof obj !== 'object') return emptyModel();
        return {
            from_month: obj.from_month || '',
            from_part: normalizePart(obj.from_part),
            to_month: obj.to_month || '',
            to_part: normalizePart(obj.to_part),
            sort_start: obj.sort_start || null,
            sort_end: obj.sort_end || null,
            display: obj.display || ''
        };
    }

    function partLabel(part) {
        var p = normalizePart(part);
        if (!p || !PART_LABELS[p]) return '';
        return t(PART_LABELS[p]);
    }

    function formatMonthPart(ym, part) {
        var m = String(ym || '').match(/^(\d{4})-(\d{2})$/);
        if (!m) return '';
        var month = parseInt(m[2], 10);
        var pLabel = partLabel(part);
        var lng = getAppLang();
        // Display without year: e.g. 8月上旬 / Tháng 8 上旬
        if (lng.indexOf('vi') === 0) {
            return 'Tháng ' + month + (pLabel ? ' ' + pLabel : '');
        }
        return String(month) + '月' + (pLabel || '');
    }

    function buildDisplay(model) {
        if (!model) return '';
        var from = formatMonthPart(model.from_month, model.from_part);
        if (!from) return '';
        if (!model.to_month) return from;
        var to = formatMonthPart(model.to_month, model.to_part);
        return to ? (from + '～' + to) : from;
    }

    function daysInMonth(y, mo) {
        return new Date(y, mo, 0).getDate();
    }

    function partDay(part, isStart, y, mo) {
        var p = normalizePart(part);
        if (p === 'early') return 1;
        if (p === 'mid') return 11;
        if (p === 'late') return 21;
        return isStart ? 1 : daysInMonth(y, mo);
    }

    function monthPartToDate(ym, part, isStart) {
        var m = String(ym || '').match(/^(\d{4})-(\d{2})$/);
        if (!m) return null;
        var y = parseInt(m[1], 10);
        var mo = parseInt(m[2], 10);
        if (mo < 1 || mo > 12) return null;
        var day = partDay(part, isStart, y, mo);
        return y + '-' + String(mo).padStart(2, '0') + '-' + String(day).padStart(2, '0');
    }

    function isValid(model) {
        if (!model || !model.from_month) return true; // empty ok
        if (!/^\d{4}-\d{2}$/.test(model.from_month)) return false;
        if (model.to_month) {
            if (!/^\d{4}-\d{2}$/.test(model.to_month)) return false;
            var a = monthPartToDate(model.from_month, model.from_part, true);
            var b = monthPartToDate(model.to_month, model.to_part, false);
            if (a && b && a > b) return false;
        }
        return true;
    }

    /** Canonical JP display for DB (logs / payload) — no year: 8月上旬～10月上旬 */
    function buildCanonicalDisplay(model) {
        var m = String(model.from_month || '').match(/^(\d{4})-(\d{2})$/);
        if (!m) return '';
        var from = String(parseInt(m[2], 10)) + '月';
        var fp = normalizePart(model.from_part);
        if (fp && PART_LABELS[fp]) from += PART_LABELS[fp];
        if (!model.to_month) return from;
        var m2 = String(model.to_month || '').match(/^(\d{4})-(\d{2})$/);
        if (!m2) return from;
        var to = String(parseInt(m2[2], 10)) + '月';
        var tp = normalizePart(model.to_part);
        if (tp && PART_LABELS[tp]) to += PART_LABELS[tp];
        return from + '～' + to;
    }

    /** Payload for API (server re-normalizes display/sort). Null if empty. */
    function toPayload(model) {
        if (!model || !String(model.from_month || '').trim()) return null;
        var fromPart = normalizePart(model.from_part);
        var toMonth = String(model.to_month || '').trim();
        var toPart = toMonth ? normalizePart(model.to_part) : '';
        var draft = {
            from_month: String(model.from_month).trim(),
            from_part: fromPart,
            to_month: toMonth,
            to_part: toPart
        };
        return {
            from_month: draft.from_month,
            from_part: fromPart,
            to_month: toMonth || null,
            to_part: toMonth ? toPart : null,
            display: buildCanonicalDisplay(draft),
            sort_start: monthPartToDate(model.from_month, fromPart, true),
            sort_end: toMonth ? monthPartToDate(toMonth, toPart, false) : null
        };
    }

    function displayOf(raw) {
        var m = parse(raw);
        // Rebuild from structured fields so UI follows current language
        return buildDisplay(m) || '';
    }

    function getPartOptions() {
        return [
            { value: '', label: '—' },
            { value: 'early', label: t('上旬') },
            { value: 'mid', label: t('中旬') },
            { value: 'late', label: t('下旬') }
        ];
    }

    function destroyMonthPicker(el) {
        if (!el) return;
        if (el._flatpickr) {
            try { el._flatpickr.destroy(); } catch (e) { /* ignore */ }
        }
    }

    /**
     * Bind flatpickr monthSelect to an input. Values are YYYY-MM.
     * @param {HTMLElement} el
     * @param {function(): string} getValue
     * @param {function(string): void} setValue
     * @param {object} [opts]
     */
    function initMonthPicker(el, getValue, setValue, opts) {
        if (!el || typeof flatpickr === 'undefined' || typeof monthSelectPlugin === 'undefined') {
            return null;
        }
        destroyMonthPicker(el);
        var localeKey = getFlatpickrLocale();
        var locale = (flatpickr.l10ns && flatpickr.l10ns[localeKey])
            ? flatpickr.l10ns[localeKey]
            : localeKey;
        var current = (typeof getValue === 'function' ? getValue() : '') || '';
        var fp = flatpickr(el, {
            plugins: [new monthSelectPlugin({
                shorthand: true,
                dateFormat: 'Y-m',
                altFormat: 'Y-m'
            })],
            dateFormat: 'Y-m',
            locale: locale,
            allowInput: true,
            disableMobile: true,
            defaultDate: /^\d{4}-\d{2}$/.test(current) ? (current + '-01') : null,
            onChange: function (selectedDates, dateStr) {
                var ym = '';
                if (dateStr && /^\d{4}-\d{2}/.test(dateStr)) {
                    ym = dateStr.substring(0, 7);
                }
                if (typeof setValue === 'function') setValue(ym);
            }
        });
        if (opts && opts.placeholder) {
            el.setAttribute('placeholder', opts.placeholder);
        } else {
            el.setAttribute('placeholder', 'YYYY-MM');
        }
        return fp;
    }

    function setMonthPickerValue(el, ym) {
        if (!el || !el._flatpickr) {
            if (el) el.value = ym || '';
            return;
        }
        if (ym && /^\d{4}-\d{2}$/.test(ym)) {
            el._flatpickr.setDate(ym + '-01', false);
        } else {
            el._flatpickr.clear();
        }
    }

    global.YoteiField = {
        PART_LABELS: PART_LABELS,
        PART_OPTIONS: getPartOptions(),
        t: t,
        getAppLang: getAppLang,
        getFlatpickrLocale: getFlatpickrLocale,
        getPartOptions: getPartOptions,
        emptyModel: emptyModel,
        parse: parse,
        buildDisplay: buildDisplay,
        isValid: isValid,
        toPayload: toPayload,
        displayOf: displayOf,
        initMonthPicker: initMonthPicker,
        destroyMonthPicker: destroyMonthPicker,
        setMonthPickerValue: setMonthPickerValue
    };
})(typeof window !== 'undefined' ? window : this);
