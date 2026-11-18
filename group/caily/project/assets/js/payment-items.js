/**
 * 決済明細: 見積 / 請求 as any number of lines { item_date, item_type, amount, status }.
 *
 * - project.payment_items  [{ id, kind: 'estimate'|'invoice', item_date: 'YYYY-MM-DD HH:mm:ss'|'', item_type, amount|null, status }]
 *   (the UI edits the date part only and keeps the time; a new date gets 18:00 on the server)
 * - project.payment_item_types  whitelist for the 種別 tag (types saved so far + 申請費 / 設計費)
 *   Both come from project getById.
 *
 * The lines are posted as JSON `payment_items` with the existing updateProjectStatus call
 * (EnergyFeePayment.appendEnergyFieldsToFormData adds it); the server derives the legacy
 * columns (amount, estimate_status, 申請費/設計費 …) from them and returns them as `derived`.
 *
 * <payment-items-editor> is registered through BusinessDocumentUiMixin, so it is available on
 * 案件詳細, 案件一覧 (決済情報 modal) and 建物詳細.
 *
 * 注文書 (<payment-order-form>, between 見積 and 請求): one check per 種別 申請費 / 設計費, stored as
 * project.order_form_application_at / order_form_design_at ('YYYY-MM-DD HH:mm:ss' = checked at, '' = not
 * checked). A 請求 line of that 種別 can be set to 発行済 only while it is checked (the server checks too).
 * 省エネ departments only: other departments have no 注文書 and no such restriction.
 */
(function (window) {
    'use strict';

    var STATUSES = {
        estimate: [
            { value: '未発行', label: '未発行', color: 'secondary' },
            { value: '見積作成中', label: '見積作成中', color: 'primary' },
            { value: '発行済', label: '発行済', color: 'success' },
            { value: '無償', label: '無償', color: 'info' }
        ],
        invoice: [
            { value: '未発行', label: '未発行', color: 'secondary' },
            { value: '請求準備', label: '請求準備', color: 'warning' },
            { value: '発行済', label: '発行済', color: 'success' },
            { value: '無償', label: '無償', color: 'info' }
        ]
    };
    var ENERGY_EXTRA = { value: '対象外', label: '対象外', color: 'dark' };
    var LEGACY_TYPES = ['申請費', '設計費'];
    // default blank lines per 見積/請求 when there is none (the old fixed fields)
    var DEFAULT_TYPES = ['設計費'];
    var DEFAULT_TYPES_ENERGY = ['申請費', '設計費'];
    // 注文書 per 種別 (Project::ORDER_FORM_FIELDS)
    var ORDER_FORM_TYPES = ['申請費', '設計費'];
    var ORDER_FORM_FIELDS = { '申請費': 'order_form_application_at', '設計費': 'order_form_design_at' };

    var DERIVED_DATE_KEYS = [
        'estimate_date', 'invoice_date',
        'estimate_application_date', 'estimate_design_date',
        'invoice_application_date', 'invoice_design_date'
    ];

    var keySeq = 0;

    function isEmpty(v) {
        return v === null || v === undefined || String(v).trim() === '';
    }

    /**
     * The API entity-encodes every string ("A&B" arrives as "A&amp;B"). 種別 is plain text shown
     * through text bindings and inputs, so decode it; otherwise each save would store it encoded
     * once more. Same entities as htmlspecialchars(ENT_QUOTES).
     */
    function decodeText(s) {
        s = s === null || s === undefined ? '' : String(s);
        if (s.indexOf('&') === -1) return s;
        return s.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"')
            .replace(/&#0?39;/g, "'").replace(/&amp;/g, '&');
    }

    /** Decode the 種別 of lines that came from the API (once per line object). */
    function decodeItems(list) {
        (Array.isArray(list) ? list : []).forEach(function (it) {
            if (!it || it._decoded) return;
            Object.defineProperty(it, '_decoded', { value: true, enumerable: false });
            if (typeof it.item_type === 'string' && it.item_type.indexOf('&') !== -1) {
                it.item_type = decodeText(it.item_type);
            }
        });
    }

    /** Today in the server's time zone (JST), 'YYYY-MM-DD' — what the old 今日 button used. */
    function todayServer() {
        try {
            if (window.moment && typeof window.moment.tz === 'function') {
                return window.moment.tz('Asia/Tokyo').format('YYYY-MM-DD');
            }
            return new Intl.DateTimeFormat('en-CA', {
                timeZone: 'Asia/Tokyo', year: 'numeric', month: '2-digit', day: '2-digit'
            }).format(new Date());
        } catch (e) {
            var d = new Date();
            var pad = function (n) { return (n < 10 ? '0' : '') + n; };
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        }
    }

    /** Now in the server's time zone (JST), 'YYYY-MM-DD HH:mm:00' — when a 注文書 is checked. */
    function nowServer() {
        try {
            if (window.moment && typeof window.moment.tz === 'function') {
                return window.moment.tz('Asia/Tokyo').format('YYYY-MM-DD HH:mm') + ':00';
            }
            var parts = {};
            new Intl.DateTimeFormat('en-CA', {
                timeZone: 'Asia/Tokyo', year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', hourCycle: 'h23'
            }).formatToParts(new Date()).forEach(function (p) { parts[p.type] = p.value; });
            return parts.year + '-' + parts.month + '-' + parts.day + ' ' + parts.hour + ':' + parts.minute + ':00';
        } catch (e) {
            var d = new Date();
            var pad = function (n) { return (n < 10 ? '0' : '') + n; };
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
                + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':00';
        }
    }

    function normalizeStatus(s) {
        if (s === '発行済み') return '発行済';
        return isEmpty(s) ? '未発行' : String(s);
    }

    function isWaived(s) {
        s = normalizeStatus(s);
        return s === '無償' || s === '対象外';
    }

    function isReady(s) {
        s = normalizeStatus(s);
        return s === '発行済' || isWaived(s);
    }

    function hasAmount(v) {
        return !isEmpty(v) && Number(v) > 0;
    }

    function isLineComplete(item) {
        return !!item && !isEmpty(item.item_date) && hasAmount(item.amount);
    }

    /** Date + amount entered, but the status was never moved on (未発行 / 見積作成中 / 請求準備). */
    function isPendingLine(item) {
        if (!isLineComplete(item)) return false;
        var s = normalizeStatus(item.status);
        return s !== '発行済' && !isWaived(s);
    }

    function uses(project) {
        return !!project && Array.isArray(project.payment_items);
    }

    /** 注文書 種別 a 請求 line needs before it can be 発行済, or '' (見積 lines, other 種別). */
    function orderFormTypeOf(item) {
        if (!item || item.kind !== 'invoice') return '';
        var t = decodeText(item.item_type).trim();
        return ORDER_FORM_FIELDS[t] ? t : '';
    }

    function hasOrderForm(project, type) {
        var field = ORDER_FORM_FIELDS[type];
        return !!field && !!project && !isEmpty(project[field]);
    }

    /** The line may be set to 発行済: date + amount, and (省エネ only) its 注文書 checked when it needs one. */
    function canIssueLine(project, item, energy) {
        if (!isLineComplete(item)) return false;
        var type = orderFormTypeOf(item);
        return !energy || !type || hasOrderForm(project, type);
    }

    /** 請求 lines of a 種別 that are 発行済 (a 注文書 cannot be unchecked while there is one). */
    function issuedInvoiceCount(project, type) {
        return itemsOf(project, 'invoice').filter(function (it) {
            return orderFormTypeOf(it) === type && normalizeStatus(it.status) === '発行済';
        }).length;
    }

    function itemsOf(project, kind) {
        if (!uses(project)) return [];
        return project.payment_items.filter(function (it) { return it.kind === kind; });
    }

    function ensureKey(item) {
        if (item && !item._key) {
            Object.defineProperty(item, '_key', { value: 'pi' + (++keySeq), enumerable: false });
        }
        return item;
    }

    /** Status of a group of lines (same rules as Project::summarizePaymentItems). */
    function summarizeStatus(items) {
        if (!items.length) return '未発行';
        var allWaived = true, allReady = true, inProgress = '';
        items.forEach(function (it) {
            var s = normalizeStatus(it.status);
            if (!isWaived(s)) {
                allWaived = false;
                if (s !== '発行済') allReady = false;
            }
            if (s === '見積作成中' || s === '請求準備') inProgress = s;
        });
        if (allWaived) return '無償';
        if (allReady) return '発行済';
        return inProgress || '未発行';
    }

    function sumAmount(items) {
        var sum = 0, any = false;
        items.forEach(function (it) {
            if (!isEmpty(it.amount)) {
                sum += Number(it.amount) || 0;
                any = true;
            }
        });
        return any ? sum : null;
    }

    function taxIncluded(amount) {
        var base = Number(amount);
        if (!Number.isFinite(base) || base <= 0) return 0;
        return base + Math.round(base * 0.1);
    }

    function yen(n) {
        return '¥' + Math.round(Number(n) || 0).toLocaleString('ja-JP');
    }

    /** 完了 check: every 見積 and 請求 line is 発行済 / 無償 / 対象外, and each side has a line. */
    function needsPaymentInfoBeforeComplete(project) {
        if (!uses(project)) return null;
        return !isReady(summarizeStatus(itemsOf(project, 'estimate')))
            || !isReady(summarizeStatus(itemsOf(project, 'invoice')));
    }

    function getMissingPaymentLabels(project, t) {
        if (!uses(project)) return null;
        t = typeof t === 'function' ? t : function (k) { return k; };
        var missing = [];
        if (!isReady(summarizeStatus(itemsOf(project, 'estimate')))) missing.push(t('見積状況'));
        if (!isReady(summarizeStatus(itemsOf(project, 'invoice')))) missing.push(t('請求状況'));
        return missing;
    }

    /** First problem that blocks saving, or ''. */
    function validationError(project) {
        if (!uses(project)) return '';
        for (var i = 0; i < project.payment_items.length; i++) {
            var it = project.payment_items[i];
            if (normalizeStatus(it.status) === '発行済' && !isLineComplete(it)) {
                return (it.kind === 'invoice' ? '請求' : '見積') + 'を発行済にするには日付・金額が必要です。';
            }
        }
        return '';
    }

    function serializeItems(project) {
        return JSON.stringify(project.payment_items.map(function (it) {
            return {
                kind: it.kind,
                item_date: it.item_date || '',
                item_type: String(it.item_type || '').trim(),
                amount: isEmpty(it.amount) ? '' : Number(it.amount),
                status: normalizeStatus(it.status)
            };
        }));
    }

    function appendToFormData(formData, project) {
        if (!formData || !uses(project)) return;
        formData.append('payment_items', serializeItems(project));
        // 注文書: only when the project object has the fields (never clear them by omission)
        ORDER_FORM_TYPES.forEach(function (type) {
            var field = ORDER_FORM_FIELDS[type];
            if (field in project) formData.append(field, isEmpty(project[field]) ? '' : String(project[field]));
        });
    }

    /**
     * After a successful save: copy the derived legacy values onto the host's project object
     * (list rows, 完了 checks and other cards read them).
     * onDate(key, 'YYYY-MM-DD HH:mm:ss'|'') lets the host store dates in its own display format.
     */
    function applySaveResponse(project, data, onDate) {
        if (!project || !data || !data.derived) return;
        Object.keys(data.derived).forEach(function (key) {
            if (!(key in project) && !/_(amount|status|date)$/.test(key)) return;
            var v = data.derived[key];
            if (DERIVED_DATE_KEYS.indexOf(key) !== -1) {
                if (typeof onDate === 'function') onDate(key, v || '');
                else project[key] = v || '';
                return;
            }
            project[key] = v === null || v === undefined ? '' : v;
        });
        if (typeof window.PaymentItemsChanged === 'function') window.PaymentItemsChanged(project);
    }

    // ------------------------------------------------------------------ components

    /**
     * Date input of one line: flatpickr (Y/m/d) when the page has it, native date otherwise.
     * with-time (注文書): 'YYYY-MM-DD HH:mm:ss' in, 'YYYY-MM-DD HH:mm:00' out, shown as Y/m/d H:i.
     */
    var PaymentItemDateInput = {
        props: {
            modelValue: { type: String, default: '' },
            disabled: Boolean,
            placeholder: { type: String, default: '' },
            withTime: Boolean
        },
        emits: ['update:modelValue'],
        template: '<input type="text" class="form-control form-control-sm pi-date" :class="{ \'pi-datetime\': withTime }" ref="el" :disabled="disabled"'
            + ' :placeholder="placeholder" autocomplete="off" :value="display" @change="onNativeChange">',
        computed: {
            display: function () {
                return this.toDisplay(this.modelValue);
            },
            fpFormat: function () {
                return this.withTime ? 'Y/m/d H:i' : 'Y/m/d';
            }
        },
        mounted: function () {
            var self = this;
            if (typeof window.flatpickr !== 'function') {
                this.$refs.el.type = this.withTime ? 'datetime-local' : 'date';
                this.$refs.el.value = this.toNative(this.modelValue);
                return;
            }
            var opts = {
                dateFormat: this.fpFormat,
                enableTime: this.withTime,
                time_24hr: true,
                allowInput: true,
                disableMobile: true,
                onChange: function (dates, str) {
                    self.$emit('update:modelValue', self.fromDisplay(str));
                }
            };
            if (window.flatpickr.l10ns && window.flatpickr.l10ns.ja) opts.locale = window.flatpickr.l10ns.ja;
            this._fp = window.flatpickr(this.$refs.el, opts);
            if (this.modelValue) this._fp.setDate(this.display, false, this.fpFormat);
        },
        watch: {
            modelValue: function (v) {
                if (this._fp) {
                    var cur = this._fp.input.value;
                    var next = this.toDisplay(v);
                    if (cur !== next) {
                        if (next) this._fp.setDate(next, false, this.fpFormat);
                        else this._fp.clear(false);
                    }
                } else if (this.$refs.el && this.$refs.el.type !== 'text') {
                    this.$refs.el.value = this.toNative(v);
                }
            }
        },
        beforeUnmount: function () {
            if (this._fp) {
                this._fp.destroy();
                this._fp = null;
            }
        },
        methods: {
            /** 'YYYY-MM-DD[ HH:mm[:ss]]' → 'YYYY/MM/DD[ HH:mm]' */
            toDisplay: function (v) {
                if (!v) return '';
                var s = String(v);
                return (this.withTime ? s.slice(0, 16) : s.slice(0, 10)).replace(/-/g, '/');
            },
            /** flatpickr / native value → 'YYYY-MM-DD' or 'YYYY-MM-DD HH:mm:00' */
            fromDisplay: function (str) {
                if (!str) return '';
                var s = String(str).replace(/\//g, '-').replace('T', ' ');
                return this.withTime ? s.slice(0, 16) + ':00' : s.slice(0, 10);
            },
            toNative: function (v) {
                if (!v) return '';
                return this.withTime ? String(v).slice(0, 16).replace(' ', 'T') : String(v).slice(0, 10);
            },
            onNativeChange: function (e) {
                if (this._fp) {
                    // typed value (allowInput): flatpickr parses it on blur and fires onChange
                    var v = String(e.target.value || '').trim();
                    if (v === '') this.$emit('update:modelValue', '');
                    return;
                }
                this.$emit('update:modelValue', this.fromDisplay(e.target.value || ''));
            }
        }
    };

    var PaymentItemsEditor = {
        name: 'PaymentItemsEditor',
        components: { 'payment-item-date-input': PaymentItemDateInput },
        props: {
            project: { type: Object, required: true },
            kind: { type: String, required: true },
            editable: { type: Boolean, default: true },
            energy: { type: Boolean, default: false },
            translate: { type: Function, default: null }
        },
        emits: ['change'],
        data: function () {
            return { focusKey: '', listId: 'pi-types-' + (++keySeq) };
        },
        template: ''
            + '<div class="pi-editor" :class="\'pi-editor-\' + kind">'
            + '  <datalist :id="listId"><option v-for="t in typeOptions" :key="t" :value="t"></option></datalist>'
            + '  <div v-if="!items.length" class="pi-empty text-muted">{{ t("明細なし") }}</div>'
            + '  <div v-for="(item, idx) in items" :key="keyOf(item)" class="pi-line" :class="{ \'pi-line-pending\': isPending(item) }">'
            // input order: 日付 → 種別 (row 1), 金額 → 状態 (row 2, status last)
            + '    <div class="pi-line-row">'
            + '      <div v-if="editable" class="input-group input-group-sm pi-date-group">'
            + '        <payment-item-date-input :model-value="datePart(item)" :placeholder="t(dateLabel)" @update:model-value="setDate(item, $event)"></payment-item-date-input>'
            + '        <button v-if="!item.item_date" type="button" class="btn btn-outline-primary" :title="t(\'今日の日付を入力\')" @click="setToday(item)">{{ t("今日") }}</button>'
            + '      </div>'
            + '      <div v-else class="pi-value">{{ item.item_date ? datePart(item).replace(/-/g, "/") : "—" }}</div>'
            + '      <input v-if="editable" type="text" class="form-control form-control-sm pi-type" :list="listId" maxlength="100"'
            + '             v-model="item.item_type" :placeholder="t(\'種別\')" @change="emitChange">'
            + '      <span v-else class="pi-type-badge" :class="{ \'pi-type-none\': !item.item_type }">{{ item.item_type || t("種別なし") }}</span>'
            + '      <button v-if="editable" type="button" class="btn btn-sm btn-icon btn-text-secondary pi-remove" :title="t(\'削除\')" @click="removeItem(item)">'
            + '        <i class="fa fa-times"></i></button>'
            + '    </div>'
            + '    <div class="pi-line-row">'
            + '      <div v-if="editable" class="input-group input-group-sm pi-amount-group">'
            + '        <span class="input-group-text">¥</span>'
            + '        <input type="text" inputmode="decimal" autocomplete="off" class="form-control text-end"'
            + '               :value="amountDisplay(item)" :placeholder="t(\'未入力\')" :disabled="isWaived(item.status)"'
            + '               @focus="onAmountFocus(item, $event)" @blur="onAmountBlur(item)" @input="onAmountInput(item, $event)">'
            + '      </div>'
            + '      <div v-else class="pi-value text-end">{{ isEmptyAmount(item) ? "—" : yen(item.amount) }}</div>'
            + '      <div v-if="editable" class="dropdown pi-status">'
            + '        <button type="button" class="btn btn-sm dropdown-toggle pi-status-toggle" :class="\'btn-\' + statusColor(item.status)"'
            + '                data-bs-toggle="dropdown" aria-expanded="false">{{ t(normStatus(item.status)) }}</button>'
            + '        <ul class="dropdown-menu dropdown-menu-end">'
            + '          <li v-for="s in statusOptions" :key="s.value">'
            + '            <a href="javascript:void(0);" class="dropdown-item pi-status-option"'
            + '               :class="{ active: s.value === normStatus(item.status), disabled: s.value === \'発行済\' && !canIssue(item) }"'
            + '               :title="s.value === \'発行済\' && !canIssue(item) ? issueBlockReason(item) : null"'
            + '               @click="selectStatus(item, s.value)"><span class="pi-status-dot" :class="\'bg-\' + s.color"></span>{{ t(s.label) }}</a></li>'
            + '        </ul>'
            + '      </div>'
            + '      <span v-else class="badge pi-status-text" :class="\'bg-\' + statusColor(item.status)">{{ t(normStatus(item.status)) }}</span>'
            + '    </div>'
            // one note per line at most: 注文書 missing (省エネ) > status not moved on.
            // The 日付・金額 requirement is the tooltip of the disabled 発行済 option.
            + '    <div v-if="editable && needsOrderForm(item) && normStatus(item.status) !== \'発行済\' && !isWaived(item.status)" class="pi-note pi-note-order">'
            + '      <i class="fa fa-file-signature" aria-hidden="true"></i>'
            + '      <span>{{ t("発行済にするには注文書（{type}）にチェックしてください").replace("{type}", t(orderFormType(item))) }}</span></div>'
            + '    <div v-else-if="isPending(item)" class="pi-note pi-note-pending">'
            + '      <i class="fa fa-exclamation-circle" aria-hidden="true"></i>'
            + '      <span>{{ t("状態が「{status}」のままです").replace("{status}", t(normStatus(item.status))) }}</span>'
            + '      <button v-if="editable && canIssue(item)" type="button" class="btn btn-sm btn-outline-success pi-issue-btn" @click="markIssued(item)">{{ t("発行済にする") }}</button>'
            + '    </div>'
            + '  </div>'
            + '  <div class="pi-footer">'
            + '    <div class="pi-actions" v-if="editable">'
            + '      <button type="button" class="btn btn-sm btn-outline-primary" @click="addItem()"><i class="fa fa-plus me-1"></i>{{ t("明細を追加") }}</button>'
            + '      <button v-if="kind === \'invoice\' && canCopyFromEstimate" type="button" class="btn btn-sm btn-outline-secondary" :title="t(\'見積の明細を請求にコピー\')" @click="copyFromEstimate"><i class="fa fa-copy me-1"></i>{{ t("見積からコピー") }}</button>'
            + '    </div>'
            + '    <div class="pi-total" v-if="items.length">'
            + '      <span class="pi-total-label">{{ t("合計(税抜き)") }}</span> <strong>{{ yen(total || 0) }}</strong>'
            + '      <span v-if="total > 0" class="pi-total-tax text-muted">（{{ t("税込") }} {{ yen(totalTax) }}）</span>'
            + '    </div>'
            + '  </div>'
            + '</div>',
        computed: {
            items: function () {
                return itemsOf(this.project, this.kind).map(ensureKey);
            },
            statusOptions: function () {
                var list = STATUSES[this.kind] || STATUSES.estimate;
                return this.energy ? list.concat([ENERGY_EXTRA]) : list;
            },
            /** status value → Bootstrap colour (both kinds + 対象外), for lines whose status is not offered here */
            statusColors: function () {
                var map = {};
                STATUSES.estimate.concat(STATUSES.invoice, [ENERGY_EXTRA]).forEach(function (s) { map[s.value] = s.color; });
                return map;
            },
            typeOptions: function () {
                var seen = {};
                var out = [];
                var add = function (t) {
                    t = decodeText(t).trim();
                    if (t && !seen[t]) { seen[t] = true; out.push(t); }
                };
                (this.project.payment_item_types || []).forEach(add);
                LEGACY_TYPES.forEach(add);
                (this.project.payment_items || []).forEach(function (it) { add(it.item_type); });
                return out;
            },
            dateLabel: function () {
                return this.kind === 'invoice' ? '請求日' : '見積日';
            },
            total: function () {
                return sumAmount(this.items);
            },
            totalTax: function () {
                return taxIncluded(this.total);
            },
            canCopyFromEstimate: function () {
                return itemsOf(this.project, 'estimate').length > 0;
            }
        },
        watch: {
            // lines loaded from the API (a new array on every load): decode their 種別,
            // and give an empty 見積/請求 its default blank lines
            'project.payment_items': {
                handler: function (list) {
                    decodeItems(list);
                    this.fillDefaultItems();
                },
                immediate: true
            },
            editable: function () { this.fillDefaultItems(); }
        },
        created: function () {
            // _key → typing the amount turned the line complete; asked on blur (not reactive)
            this._completedByTyping = {};
        },
        methods: {
            t: function (k) {
                return this.translate ? this.translate(k) : k;
            },
            keyOf: function (item) {
                return ensureKey(item)._key;
            },
            yen: yen,
            normStatus: normalizeStatus,
            isWaived: isWaived,
            isComplete: isLineComplete,
            isEmptyAmount: function (item) {
                return isEmpty(item.amount);
            },
            emitChange: function () {
                this.$emit('change');
            },
            amountDisplay: function (item) {
                if (isEmpty(item.amount)) return '';
                if (this.focusKey === item._key) return String(item.amount);
                var n = Number(item.amount);
                return Number.isFinite(n) ? n.toLocaleString('ja-JP') : String(item.amount);
            },
            isPending: isPendingLine,
            statusColor: function (status) {
                return this.statusColors[normalizeStatus(status)] || 'secondary';
            },
            canIssue: function (item) {
                return canIssueLine(this.project, item, this.energy);
            },
            orderFormType: orderFormTypeOf,
            /** Date + amount entered, but the line's 注文書 is not checked yet. */
            /** Why 発行済 is disabled for this line (tooltip of the option). */
            issueBlockReason: function (item) {
                if (!isLineComplete(item)) {
                    return this.t('発行済にするには{fields}が必要です').replace('{fields}', this.t(this.dateLabel) + '・' + this.t('金額'));
                }
                return this.t('発行済にするには注文書（{type}）にチェックしてください').replace('{type}', this.t(orderFormTypeOf(item)));
            },
            needsOrderForm: function (item) {
                var type = orderFormTypeOf(item);
                return this.energy && !!type && isLineComplete(item) && !hasOrderForm(this.project, type);
            },
            markIssued: function (item) {
                if (!canIssueLine(this.project, item, this.energy)) return;
                item.status = '発行済';
                this.emitChange();
            },
            /**
             * The line just got its date and amount (it had not both before) but is still
             * 未発行 / 見積作成中 / 請求準備: ask once whether it is 発行済 now.
             * Lines that were already complete are only flagged (isPending), never asked.
             */
            askIssuedIfJustCompleted: function (item, wasComplete) {
                if (!this.editable || wasComplete || !isPendingLine(item) || !canIssueLine(this.project, item, this.energy)) return;
                var self = this;
                var title = this.t('発行済にしますか？');
                var text = this.t('{label}の日付・金額が入力されました。').replace('{label}',
                    (this.kind === 'invoice' ? this.t('請求') : this.t('見積')) + (item.item_type ? '［' + item.item_type + '］' : ''));
                var apply = function (yes) {
                    if (yes && isPendingLine(item)) self.markIssued(item);
                };
                if (typeof window.Swal !== 'undefined' && window.Swal.fire) {
                    window.Swal.fire({
                        icon: 'question',
                        title: title,
                        text: text,
                        showCancelButton: true,
                        confirmButtonText: this.t('発行済にする'),
                        cancelButtonText: this.t('あとで'),
                        customClass: { confirmButton: 'btn btn-success me-2', cancelButton: 'btn btn-label-secondary' },
                        buttonsStyling: false
                    }).then(function (r) { apply(!!(r && r.isConfirmed)); });
                } else {
                    apply(window.confirm(title + '\n' + text));
                }
            },
            /** Ask when leaving the amount, if typing it is what completed the line. */
            onAmountBlur: function (item) {
                this.focusKey = '';
                var completedByTyping = !!this._completedByTyping[item._key];
                delete this._completedByTyping[item._key];
                this.askIssuedIfJustCompleted(item, !completedByTyping);
            },
            onAmountFocus: function (item, e) {
                this.focusKey = item._key;
                var el = e && e.target;
                this.$nextTick(function () { if (el && el.select) el.select(); });
            },
            onAmountInput: function (item, e) {
                // digits, plus one "." with up to 2 decimals: old amounts written from 見積書 are
                // tax-included decimals (e.g. 12345.6), which must not turn into 123456 when edited
                var raw = String(e.target.value || '').replace(/[^\d.]/g, '');
                var dot = raw.indexOf('.');
                var digits = dot === -1 ? raw
                    : raw.slice(0, dot + 1) + raw.slice(dot + 1).replace(/\./g, '').slice(0, 2);
                if (e.target.value !== digits) e.target.value = digits;
                var wasComplete = isLineComplete(item);
                var num = digits.replace(/\.$/, '');
                item.amount = num === '' || num === '.' ? null : Number(num);
                if (!wasComplete && isLineComplete(item)) this._completedByTyping[item._key] = true;
                else if (!isLineComplete(item)) delete this._completedByTyping[item._key];
                if (normalizeStatus(item.status) === '発行済' && !isLineComplete(item)) item.status = '未発行';
                this.emitChange();
            },
            selectStatus: function (item, value) {
                if (value === normalizeStatus(item.status)) return;
                if (value === '発行済' && !canIssueLine(this.project, item, this.energy)) return;
                item.status = value;
                if (isWaived(value)) item.amount = 0;
                this.emitChange();
            },
            datePart: function (item) {
                return item && item.item_date ? String(item.item_date).slice(0, 10) : '';
            },
            /** Date picked in the UI; the stored time (e.g. 18:57 from the old 見積日) is kept. */
            setDate: function (item, ymd) {
                var current = String(item.item_date || '');
                var was = isLineComplete(item);
                if (!ymd) {
                    if (current === '') return;
                    item.item_date = '';
                    // like clearing the amount: a 発行済 line needs a date, or every save fails
                    if (normalizeStatus(item.status) === '発行済') item.status = '未発行';
                } else {
                    if (ymd === current.slice(0, 10)) return;
                    item.item_date = ymd + current.slice(10);
                }
                this.emitChange();
                this.askIssuedIfJustCompleted(item, was);
            },
            setToday: function (item) {
                var was = isLineComplete(item);
                item.item_date = todayServer();
                this.emitChange();
                this.askIssuedIfJustCompleted(item, was);
            },
            /**
             * 種別 for a line added with 明細を追加. The stats match 見積 and 請求 by 種別, so a blank
             * tag next to the migrated 設計費 lines would split them:
             * 請求 → the first 見積 種別 with no 請求 line yet, else the last 請求 / 見積 種別;
             * 見積 → the last 見積 種別.
             */
            defaultType: function () {
                var typesOf = function (list) {
                    return list.map(function (it) { return String(it.item_type || '').trim(); })
                        .filter(function (t) { return t !== ''; });
                };
                var est = typesOf(itemsOf(this.project, 'estimate'));
                if (this.kind !== 'invoice') return est.length ? est[est.length - 1] : '';
                var inv = typesOf(itemsOf(this.project, 'invoice'));
                for (var i = 0; i < est.length; i++) {
                    if (inv.indexOf(est[i]) === -1) return est[i];
                }
                if (inv.length) return inv[inv.length - 1];
                return est.length ? est[est.length - 1] : '';
            },
            newItem: function (src) {
                return ensureKey({
                    id: null,
                    kind: this.kind,
                    item_date: '',
                    item_type: src ? (src.item_type || '') : this.defaultType(),
                    amount: src && !isEmpty(src.amount) ? Number(src.amount) : null,
                    status: src && isWaived(src.status) ? normalizeStatus(src.status) : '未発行'
                });
            },
            /**
             * No line of this kind yet: show the default blank lines, like the old fixed fields
             * (省エネ: 申請費 + 設計費, other departments: 設計費). Blank lines are not stored
             * (the server skips them), so an untouched project comes back with them again.
             */
            fillDefaultItems: function () {
                if (!this.editable || !uses(this.project) || itemsOf(this.project, this.kind).length) return;
                var self = this;
                var types = this.energy ? DEFAULT_TYPES_ENERGY : DEFAULT_TYPES;
                this.insertItems(types.map(function (t) {
                    var it = self.newItem(null);
                    it.item_type = t;
                    return it;
                }));
            },
            /** Insert after the last line of this kind, keeping 見積 lines before 請求 lines. */
            insertItems: function (newItems) {
                var list = this.project.payment_items;
                var at = -1;
                for (var i = 0; i < list.length; i++) {
                    if (list[i].kind === this.kind) at = i;
                }
                if (at === -1) at = this.kind === 'estimate' ? -1 : list.length - 1;
                Array.prototype.splice.apply(list, [at + 1, 0].concat(newItems));
            },
            addItem: function () {
                if (!Array.isArray(this.project.payment_items)) this.project.payment_items = [];
                this.insertItems([this.newItem(null)]);
                this.emitChange();
                var self = this;
                this.$nextTick(function () {
                    var inputs = self.$el.querySelectorAll('.pi-type');
                    if (inputs.length) {
                        var input = inputs[inputs.length - 1];
                        input.focus();
                        if (input.select) input.select(); // prefilled 種別: typing replaces it
                    }
                });
            },
            removeItem: function (item) {
                var hasData = !isEmpty(item.item_date) || !isEmpty(item.amount) || !isEmpty(item.item_type);
                if (hasData && !window.confirm(this.t('この明細を削除しますか？'))) return;
                var list = this.project.payment_items;
                var idx = list.indexOf(item);
                if (idx === -1) {
                    // reactive proxy vs raw object
                    idx = list.findIndex(function (x) { return x._key === item._key; });
                }
                if (idx !== -1) list.splice(idx, 1);
                this.emitChange();
            },
            copyFromEstimate: function () {
                var self = this;
                var src = itemsOf(this.project, 'estimate');
                if (!src.length) return;
                if (this.items.length && !window.confirm(this.t('見積の明細を請求に追加しますか？'))) return;
                this.insertItems(src.map(function (it) { return self.newItem(it); }));
                this.emitChange();
            }
        }
    };

    /**
     * 注文書: a check per 種別 (申請費 / 設計費). Checking fills in now (JST); the date/time stays editable.
     * Unchecking is refused while a 請求 line of that 種別 is 発行済. Clearing the time unchecks.
     */
    var PaymentOrderForm = {
        name: 'PaymentOrderForm',
        components: { 'payment-item-date-input': PaymentItemDateInput },
        props: {
            project: { type: Object, required: true },
            editable: { type: Boolean, default: true },
            translate: { type: Function, default: null }
        },
        emits: ['change'],
        data: function () {
            return { uid: 'pof-' + (++keySeq), redraw: 0 };
        },
        template: ''
            + '<div class="pi-order-form">'
            + '  <div v-for="type in types" :key="type" class="pi-order-row" :class="{ \'pi-order-checked\': isChecked(type) }">'
            + '    <div class="form-check pi-order-check">'
            + '      <input class="form-check-input" type="checkbox" :id="uid + \'-\' + type" :checked="isChecked(type)"'
            + '             :disabled="!editable" @change="onToggle(type, $event)">'
            + '      <label class="form-check-label" :for="uid + \'-\' + type">{{ t(type) }}</label>'
            + '    </div>'
            + '    <template v-if="isChecked(type)">'
            + '      <payment-item-date-input v-if="editable" :key="type + redraw" with-time :model-value="valueOf(type)"'
            + '                               :placeholder="t(\'日時\')" @update:model-value="setValue(type, $event)"></payment-item-date-input>'
            + '      <span v-else class="pi-value">{{ displayOf(type) }}</span>'
            + '    </template>'
            + '    <span v-else class="pi-order-none text-muted">{{ t("未受領") }}</span>'
            + '  </div>'
            + '</div>',
        computed: {
            types: function () {
                return ORDER_FORM_TYPES;
            }
        },
        methods: {
            t: function (k) {
                return this.translate ? this.translate(k) : k;
            },
            isChecked: function (type) {
                return hasOrderForm(this.project, type);
            },
            valueOf: function (type) {
                var v = this.project[ORDER_FORM_FIELDS[type]];
                return isEmpty(v) ? '' : String(v);
            },
            displayOf: function (type) {
                return this.valueOf(type).slice(0, 16).replace(/-/g, '/');
            },
            /** Refuse unchecking while a 請求 of this 種別 is 発行済; true when it was refused. */
            refuseUncheck: function (type) {
                if (!issuedInvoiceCount(this.project, type)) return false;
                var msg = this.t('発行済の請求（{type}）があるため、注文書（{type}）のチェックを外せません。').replace(/\{type\}/g, this.t(type));
                if (typeof window.Swal !== 'undefined' && window.Swal.fire) {
                    window.Swal.fire({
                        icon: 'warning', text: msg,
                        customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false
                    });
                } else {
                    window.alert(msg);
                }
                return true;
            },
            onToggle: function (type, e) {
                var field = ORDER_FORM_FIELDS[type];
                if (e.target.checked) {
                    this.project[field] = nowServer();
                } else {
                    if (this.refuseUncheck(type)) {
                        e.target.checked = true;
                        return;
                    }
                    this.project[field] = '';
                }
                this.$emit('change');
            },
            setValue: function (type, v) {
                var field = ORDER_FORM_FIELDS[type];
                if (!v) {
                    // cleared time = unchecked; when that is refused, show the stored time again
                    if (this.refuseUncheck(type)) {
                        this.redraw++;
                        return;
                    }
                } else if (v === this.valueOf(type) || v.slice(0, 16) === this.valueOf(type).slice(0, 16)) {
                    return;
                }
                this.project[field] = v || '';
                this.$emit('change');
            }
        }
    };

    window.PaymentItems = {
        STATUSES: STATUSES,
        uses: uses,
        itemsOf: itemsOf,
        summarizeStatus: summarizeStatus,
        sumAmount: sumAmount,
        isReady: isReady,
        needsPaymentInfoBeforeComplete: needsPaymentInfoBeforeComplete,
        getMissingPaymentLabels: getMissingPaymentLabels,
        validationError: validationError,
        appendToFormData: appendToFormData,
        applySaveResponse: applySaveResponse,
        decodeText: decodeText,
        ORDER_FORM_FIELDS: ORDER_FORM_FIELDS
    };
    window.PaymentItemsEditor = PaymentItemsEditor;
    window.PaymentOrderForm = PaymentOrderForm;

    // Available in every host that uses the shared 決済情報 UI mixin
    if (window.BusinessDocumentUiMixin) {
        window.BusinessDocumentUiMixin.components = Object.assign({}, window.BusinessDocumentUiMixin.components, {
            'payment-items-editor': PaymentItemsEditor,
            'payment-order-form': PaymentOrderForm
        });
    }
})(window);
