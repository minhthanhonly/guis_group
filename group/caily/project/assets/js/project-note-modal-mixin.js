/**
 * メモ modal shared by 案件詳細 (project-detail.js) and 案件一覧 (project-list.js).
 * Markup: project/partials/project-note-modal.php. Styles: project/assets/css/project-notes.css.
 *
 * The host component provides the page-specific parts the partial calls:
 *   data:     showNoteModal, isNoteEditMode, editingNote, savingNote, NOTE_LABEL_OTHER
 *   computed: canSaveNote, canDeleteCurrentNote, cailyNoteLabel, noteLabelSelectOptions, noteDisplayColumnOptions
 *   methods:  saveNote, closeNoteModal, deleteCurrentNote, canEditNote(note), initQuillNoteEditor,
 *             onNoteLabelSelectChange, onNotePinEveryoneChange,
 *             getNoteLabelStyle, getNoteDisplayColumnLabel
 * editingNote also carries bg_color, visibility, created_at and realname.
 *
 * 編集ロック: while an existing note is open in the editor this mixin holds its lock on the server
 * (lockNote, renewed every 30 s, released on close / leaving the page). If someone else holds it the
 * modal falls back to view mode and says who is editing.
 * Optional host method: destroyQuillNoteEditor.
 */
(function (global) {
    'use strict';

    var LOCK_RENEW_MS = 30000;
    var API = '/api/index.php?model=project&method=';

    function postNoteId(method, id) {
        var fd = new FormData();
        fd.append('id', id);
        return axios.post(API + method, fd).then(function (res) { return res.data || {}; });
    }

    global.ProjectNoteModalMixin = {
        data() {
            return {
                // 背景色 palette keys — must match ProjectNote::BG_COLORS
                noteBgColorOptions: [
                    { value: '', text: 'デフォルト' },
                    { value: 'coral', text: 'コーラル' },
                    { value: 'peach', text: 'ピーチ' },
                    { value: 'sand', text: 'サンド' },
                    { value: 'mint', text: 'ミント' },
                    { value: 'sage', text: 'セージ' },
                    { value: 'fog', text: 'フォグ' },
                    { value: 'storm', text: 'ストーム' },
                    { value: 'dusk', text: 'ダスク' },
                    { value: 'blossom', text: 'ブロッサム' },
                    { value: 'clay', text: 'クレイ' },
                    { value: 'chalk', text: 'チョーク' }
                ],
                // 公開範囲 — must match ProjectNote::VISIBILITIES
                noteVisibilityOptions: [
                    { value: 'all', text: '全員', icon: 'fa-globe' },
                    { value: 'members', text: '案件メンバー', icon: 'fa-users' },
                    { value: 'self', text: '自分のみ', icon: 'fa-lock' }
                ],
                /** 編集ロック: name of the user editing this note (we may not edit it now) */
                noteLockedBy: '',
                /** 編集ロック: our lock was taken over while we were editing (saving is refused) */
                noteLockLost: false
            };
        },
        computed: {
            noteModalTitle() {
                if (!this.editingNote || !this.editingNote.id) return 'メモを追加';
                return this.isNoteEditMode ? 'メモを編集' : 'メモ';
            },
            /** Note id whose edit lock we should hold: an existing note open in the editor */
            noteLockTarget() {
                const n = this.editingNote;
                return this.showNoteModal && this.isNoteEditMode && n && n.id ? String(n.id) : '';
            },
            /** "2026/11/13 13:42 · 林田" for an existing note, '' for a new one */
            noteModalMeta() {
                const n = this.editingNote;
                if (!n || !n.id) return '';
                const parts = [];
                if (n.created_at) {
                    const m = String(n.created_at).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
                    parts.push(m ? `${m[1]}/${m[2]}/${m[3]} ${m[4]}:${m[5]}` : String(n.created_at));
                }
                if (n.realname) parts.push(n.realname);
                return parts.join(' · ');
            }
        },
        watch: {
            noteLockTarget(id, prev) {
                if (prev && prev !== id) this.releaseNoteLock(prev);
                if (id) this.acquireNoteLock(id);
            },
            showNoteModal(open) {
                if (!open) {
                    this.noteLockedBy = '';
                    this.noteLockLost = false;
                }
            },
            // 自分のみ: nobody else sees the note, so ピン（全員） has no meaning
            'editingNote.visibility'(value) {
                if (value === 'self' && this.editingNote) {
                    this.editingNote.is_important = false;
                }
            }
        },
        mounted() {
            // leaving the page: release the lock without waiting (sendBeacon survives the unload)
            this._onNoteLockPageHide = () => {
                const id = this._noteLockId;
                if (!id || !navigator.sendBeacon) return;
                const fd = new FormData();
                fd.append('id', id);
                navigator.sendBeacon(API + 'unlockNote', fd);
            };
            global.addEventListener('pagehide', this._onNoteLockPageHide);
        },
        beforeUnmount() {
            global.removeEventListener('pagehide', this._onNoteLockPageHide);
            if (this._noteLockId) this.releaseNoteLock(this._noteLockId);
        },
        methods: {
            /** Take the edit lock of note id, then keep renewing it while the editor stays open */
            async acquireNoteLock(id) {
                let data;
                try {
                    data = await postNoteId('lockNote', id);
                } catch (e) {
                    return; // network trouble: the server still checks on save
                }
                if (this.noteLockTarget !== id) {
                    // closed / switched meanwhile: give back what we just took
                    if (data.status === 'success') postNoteId('unlockNote', id).catch(function () {});
                    return;
                }
                if (data.status === 'locked') {
                    this.onNoteLockedByOther(data.locked_by_name || '');
                    return;
                }
                if (data.status !== 'success') return;
                this._noteLockId = id;
                this.noteLockedBy = '';
                this.noteLockLost = false;
                clearInterval(this._noteLockTimer);
                this._noteLockTimer = setInterval(() => this.renewNoteLock(), LOCK_RENEW_MS);
            },
            async renewNoteLock() {
                const id = this._noteLockId;
                if (!id) return;
                try {
                    const data = await postNoteId('lockNote', id);
                    if (data.status === 'locked' && this._noteLockId === id) {
                        // our lock expired (sleep / lost connection) and someone else took it
                        clearInterval(this._noteLockTimer);
                        this._noteLockId = null;
                        this.noteLockedBy = data.locked_by_name || '';
                        this.noteLockLost = true;
                    }
                } catch (e) { /* try again on the next tick */ }
            },
            releaseNoteLock(id) {
                if (this._noteLockId === id) {
                    clearInterval(this._noteLockTimer);
                    this._noteLockTimer = null;
                    this._noteLockId = null;
                }
                postNoteId('unlockNote', id).catch(function () {});
            },
            /** Someone else edits the note: show it read-only and say who */
            onNoteLockedByOther(name) {
                this.noteLockedBy = name;
                this.noteLockLost = false;
                if (this.isNoteEditMode) {
                    this.isNoteEditMode = false;
                    if (typeof this.destroyQuillNoteEditor === 'function') this.destroyQuillNoteEditor();
                }
            },
            noteVisibilityOption(value) {
                const opts = this.noteVisibilityOptions;
                return opts.find(function (o) { return o.value === value; }) || opts[0];
            },
            noteVisibilityLabel(value) {
                return this.noteVisibilityOption(value).text;
            },
            noteVisibilityIcon(value) {
                return this.noteVisibilityOption(value).icon;
            },
            /** View mode → edit mode (read-only viewers never see the button) */
            startNoteEdit() {
                this.noteLockedBy = '';
                this.isNoteEditMode = true;
                this.$nextTick(() => this.initQuillNoteEditor());
            },
            /** Esc closes, Ctrl/⌘+Enter saves */
            onNoteModalKeydown(e) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    this.closeNoteModal();
                    return;
                }
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey) && this.isNoteEditMode) {
                    e.preventDefault();
                    if (this.canSaveNote && !this.savingNote) this.saveNote();
                }
            },
            /** Keyboard shortcuts work right away: focus the dialog when it opens */
            focusNoteModal() {
                this.$nextTick(() => {
                    const el = document.querySelector('.project-note-modal');
                    if (el && !el.contains(document.activeElement)) el.focus({ preventScroll: true });
                });
            }
        }
    };
})(window);
