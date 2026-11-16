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

    /** Date input of one line: flatpickr (Y/m/d) when the page has it, native date otherwise. */
    var PaymentItemDateInput = {
        props: { modelValue: { type: String, default: '' }, disabled: Boolean, placeholder: { type: String, default: '' } },
        emits: ['update:modelValue'],
        template: '<input type="text" class="form-control form-control-sm pi-date" ref="el" :disabled="disabled"'
            + ' :placeholder="placeholder" autocomplete="off" :value="display" @change="onNativeChange">',
        computed: {
            display: function () {
                return this.modelValue ? String(this.modelValue).replace(/-/g, '/') : '';
            }
        },
        mounted: function () {
            var self = this;
            if (typeof window.flatpickr !== 'function') {
                this.$refs.el.type = 'date';
                this.$refs.el.value = this.modelValue || '';
                return;
            }
            var opts = {
                dateFormat: 'Y/m/d',
                allowInput: true,
                disableMobile: true,
                onChange: function (dates, str) {
                    self.$emit('update:modelValue', str ? str.replace(/\//g, '-') : '');
                }
            };
            if (window.flatpickr.l10ns && window.flatpickr.l10ns.ja) opts.locale = window.flatpickr.l10ns.ja;
            this._fp = window.flatpickr(this.$refs.el, opts);
            if (this.modelValue) this._fp.setDate(this.display, false, 'Y/m/d');
        },
        watch: {
            modelValue: function (v) {
                if (this._fp) {
                    var cur = this._fp.input.value;
                    var next = v ? String(v).replace(/-/g, '/') : '';
                    if (cur !== next) {
                        if (next) this._fp.setDate(next, false, 'Y/m/d');
                        else this._fp.clear(false);
                    }
                } else if (this.$refs.el && this.$refs.el.type === 'date') {
                    this.$refs.el.value = v || '';
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
            onNativeChange: function (e) {
                if (this._fp) {
                    // typed value (allowInput): flatpickr parses it on blur and fires onChange
                    var v = String(e.target.value || '').trim();
                    if (v === '') this.$emit('update:modelValue', '');
                    return;
                }
                this.$emit('update:modelValue', e.target.value || '');
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
            + '  <div v-if="pendingCount" class="pi-pending-summary" role="status">'
            + '    <i class="fa fa-exclamation-circle me-1"></i>{{ t("状態が未更新の明細") }}: {{ pendingCount }}{{ t("件") }}</div>'
            + '  <div v-if="!items.length" class="pi-empty text-muted">{{ t("明細なし") }}</div>'
            + '  <div v-for="(item, idx) in items" :key="keyOf(item)" class="pi-line" :class="{ \'pi-line-pending\': isPending(item) }">'
            + '    <div class="pi-line-row">'
            + '      <input v-if="editable" type="text" class="form-control form-control-sm pi-type" :list="listId" maxlength="100"'
            + '             v-model="item.item_type" :placeholder="t(\'種別\')" @change="emitChange">'
            + '      <span v-else class="pi-type-badge" :class="{ \'pi-type-none\': !item.item_type }">{{ item.item_type || t("種別なし") }}</span>'
            + '      <select v-if="editable" class="form-select form-select-sm pi-status"'
            + '              :value="normStatus(item.status)" @change="selectStatus(item, $event)">'
            + '        <option v-for="s in statusOptions" :key="s.value" :value="s.value"'
            + '                :disabled="s.value === \'発行済\' && !isComplete(item)">{{ t(s.label) }}</option>'
            + '      </select>'
            + '      <span v-else class="pi-status-text">{{ t(normStatus(item.status)) }}</span>'
            + '      <button v-if="editable" type="button" class="btn btn-sm btn-icon btn-text-secondary pi-remove" :title="t(\'削除\')" @click="removeItem(item)">'
            + '        <i class="fa fa-times"></i></button>'
            + '    </div>'
            + '    <div class="pi-line-row">'
            + '      <div v-if="editable" class="input-group input-group-sm pi-date-group">'
            + '        <payment-item-date-input :model-value="datePart(item)" :placeholder="t(dateLabel)" @update:model-value="setDate(item, $event)"></payment-item-date-input>'
            + '        <button v-if="!item.item_date" type="button" class="btn btn-outline-primary" :title="t(\'今日の日付を入力\')" @click="setToday(item)">{{ t("今日") }}</button>'
            + '      </div>'
            + '      <div v-else class="pi-value">{{ item.item_date ? datePart(item).replace(/-/g, "/") : "—" }}</div>'
            + '      <div v-if="editable" class="input-group input-group-sm pi-amount-group">'
            + '        <span class="input-group-text">¥</span>'
            + '        <input type="text" inputmode="decimal" autocomplete="off" class="form-control text-end"'
            + '               :value="amountDisplay(item)" :placeholder="t(\'未入力\')" :disabled="isWaived(item.status)"'
            + '               @focus="onAmountFocus(item, $event)" @blur="onAmountBlur(item)" @input="onAmountInput(item, $event)">'
            + '      </div>'
            + '      <div v-else class="pi-value text-end">{{ isEmptyAmount(item) ? "—" : yen(item.amount) }}</div>'
            + '    </div>'
            + '    <div v-if="editable && normStatus(item.status) !== \'発行済\' && !isWaived(item.status) && !isComplete(item)" class="pi-hint">'
            + '      {{ t("発行済にするには{fields}が必要です").replace("{fields}", t(dateLabel) + "・" + t("金額")) }}</div>'
            + '    <div v-if="isPending(item)" class="pi-pending">'
            + '      <span><i class="fa fa-exclamation-circle me-1"></i>{{ t("状態が「{status}」のままです").replace("{status}", t(normStatus(item.status))) }}</span>'
            + '      <button v-if="editable" type="button" class="btn btn-link btn-sm pi-issue-btn" @click="markIssued(item)">{{ t("発行済にする") }}</button>'
            + '    </div>'
            + '  </div>'
            + '  <div class="pi-footer">'
            + '    <div class="pi-actions" v-if="editable">'
            + '      <button type="button" class="btn btn-sm btn-link" @click="addItem()"><i class="fa fa-plus me-1"></i>{{ t("明細を追加") }}</button>'
            + '      <button v-if="kind === \'invoice\' && canCopyFromEstimate" type="button" class="btn btn-sm btn-link text-secondary" :title="t(\'見積の明細を請求にコピー\')" @click="copyFromEstimate">{{ t("見積からコピー") }}</button>'
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
            },
            pendingCount: function () {
                return this.items.filter(isPendingLine).length;
            }
        },
        watch: {
            // lines loaded from the API (a new array on every load): decode their 種別
            'project.payment_items': { handler: decodeItems, immediate: true }
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
            markIssued: function (item) {
                if (!isLineComplete(item)) return;
                item.status = '発行済';
                this.emitChange();
            },
            /**
             * The line just got its date and amount (it had not both before) but is still
             * 未発行 / 見積作成中 / 請求準備: ask once whether it is 発行済 now.
             * Lines that were already complete are only flagged (isPending), never asked.
             */
            askIssuedIfJustCompleted: function (item, wasComplete) {
                if (!this.editable || wasComplete || !isPendingLine(item)) return;
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
            selectStatus: function (item, e) {
                var value = e.target.value;
                if (value === '発行済' && !isLineComplete(item)) {
                    e.target.value = normalizeStatus(item.status);
                    return;
                }
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
        decodeText: decodeText
    };
    window.PaymentItemsEditor = PaymentItemsEditor;

    // Available in every host that uses the shared 決済情報 UI mixin
    if (window.BusinessDocumentUiMixin) {
        window.BusinessDocumentUiMixin.components = Object.assign({}, window.BusinessDocumentUiMixin.components, {
            'payment-items-editor': PaymentItemsEditor
        });
    }
})(window);
