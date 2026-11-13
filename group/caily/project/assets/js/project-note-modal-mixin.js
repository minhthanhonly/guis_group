/**
 * メモ modal shared by 案件詳細 (project-detail.js) and 案件一覧 (project-list.js).
 * Markup: project/partials/project-note-modal.php. Styles: project/assets/css/project-notes.css.
 *
 * The host component provides the page-specific parts the partial calls:
 *   data:     showNoteModal, isNoteEditMode, editingNote, savingNote, NOTE_LABEL_OTHER
 *   computed: canSaveNote, canDeleteCurrentNote, cailyNoteLabel, noteLabelSelectOptions, noteDisplayColumnOptions
 *   methods:  saveNote, closeNoteModal, deleteCurrentNote, canEditNote(note), initQuillNoteEditor,
 *             onNoteLabelSelectChange, onNotePinEveryoneChange, onNotePinSelfChange,
 *             getNoteLabelStyle, getNoteDisplayColumnLabel
 * editingNote also carries bg_color, visibility, created_at and realname.
 */
(function (global) {
    'use strict';

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
                ]
            };
        },
        computed: {
            noteModalTitle() {
                if (!this.editingNote || !this.editingNote.id) return 'メモを追加';
                return this.isNoteEditMode ? 'メモを編集' : 'メモ';
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
            // 自分のみ: nobody else sees the note, so ピン（全員） has no meaning
            'editingNote.visibility'(value) {
                if (value === 'self' && this.editingNote) {
                    this.editingNote.is_important = false;
                }
            }
        },
        methods: {
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
