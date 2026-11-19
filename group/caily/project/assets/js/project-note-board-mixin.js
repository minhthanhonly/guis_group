/**
 * メモボード shared by 案件詳細 (project-detail.js, inline card) and 案件一覧 (project-list.js,
 * opened full screen from a memo cell). Markup: project/partials/project-note-board.php.
 * Styles: project/assets/css/project-notes.css.
 *
 * Each user has their own layout (server: ProjectNote::attachUserBoard): ピン（全員） notes in a band
 * on top, reordered only among themselves; the other notes on a free 3-column grid.
 *
 * The host component provides:
 *   data:     notes (this board's notes, from getNotes)
 *   computed: canAddNote, cailyNoteLabel
 *   methods:  getNoteBoardProjectId(), reloadNoteBoard(), noteBoardNotify(message, type),
 *             openNoteModal(note|undefined), canDeleteNote(note), deleteNote(noteId),
 *             getNoteLabelStyle, getNoteDisplayColumnLabel, translateLabel
 * decodeNoteHtml and formatShortDateTime have defaults here; the host may override them.
 */
(function (global) {
    'use strict';

    /** 全文表示 switch, remembered per browser (shared by both pages) */
    var NOTE_SHOW_FULL_STORAGE_KEY = 'projectDetail.noteShowFull';
    /** free placement from this width up — 3 columns, see project-notes.css .note-board */
    var NOTE_BOARD_FREE_MEDIA = '(min-width: 992px)';
    /** 編集ロック: how often the board asks who is editing its notes */
    var NOTE_EDIT_POLL_MS = 20000;

    global.ProjectNoteBoardMixin = {
        data() {
            return {
                noteFilterLabel: '',
                noteFilterType: '',
                noteFilterVisibility: '', // '' | all | members | self
                noteFilterPin: '',        // '' | everyone | none
                noteFilterVisibilityOptions: [
                    { value: '', text: 'すべて' },
                    { value: 'all', text: '全員', icon: 'fa-globe' },
                    { value: 'members', text: '案件メンバー', icon: 'fa-users' },
                    { value: 'self', text: '自分のみ', icon: 'fa-lock' }
                ],
                noteFilterPinOptions: [
                    { value: '', text: 'すべて' },
                    { value: 'everyone', text: 'ピン（全員）', icon: 'fa-thumb-tack text-danger' },
                    { value: 'none', text: 'ピンなし' }
                ],
                // 全文表示 (default off); 全画面表示 turns it on for the time being without saving that
                noteShowFull: (function () {
                    try { return localStorage.getItem(NOTE_SHOW_FULL_STORAGE_KEY) === '1'; } catch (e) { return false; }
                })(),
                /** 全画面表示 */
                noteBoardFullscreen: false,
                /** 案件一覧: name of the project whose board is open (shown in the header) */
                noteBoardProjectName: '',
                /** 案件一覧: the board's notes are being fetched */
                noteBoardLoading: false,
                /** drag: { id, pinned, offX, offY, target: { col, row[, idx] } | null } */
                noteDrag: null,
                noteBoardWide: (function () {
                    try { return global.matchMedia(NOTE_BOARD_FREE_MEDIA).matches; } catch (e) { return true; }
                })()
            };
        },
        computed: {
            /** Unique labels present on the notes (for the filter dropdown) */
            noteFilterLabelOptions() {
                const seen = {};
                const out = [];
                (this.notes || []).forEach(function (n) {
                    const name = n && n.label != null ? String(n.label).trim() : '';
                    if (!name || seen[name]) return;
                    seen[name] = true;
                    out.push(name);
                });
                out.sort(function (a, b) { return a.localeCompare(b, 'ja'); });
                return out;
            },
            /** Any board filter set — the board falls back to reading order and drag is off */
            noteFiltersActive() {
                return !!(this.noteFilterLabel || this.noteFilterType !== ''
                    || this.noteFilterVisibility || this.noteFilterPin);
            },
            filteredNotes() {
                const labelFilter = this.noteFilterLabel;
                const visibilityFilter = this.noteFilterVisibility;
                const pinFilter = this.noteFilterPin;
                const typeFilter = this.noteFilterType === '' || this.noteFilterType === null || this.noteFilterType === undefined
                    ? ''
                    : String(this.noteFilterType);
                return (this.notes || []).filter(function (note) {
                    if (labelFilter === '__none__') {
                        if (note.label != null && String(note.label).trim() !== '') return false;
                    } else if (labelFilter) {
                        if (String(note.label || '').trim() !== labelFilter) return false;
                    }
                    if (typeFilter !== '') {
                        const t = Number(note.needs_confirmation) || 0;
                        if (String(t) !== typeFilter) return false;
                    }
                    if (visibilityFilter && (note.visibility || 'all') !== visibilityFilter) return false;
                    if (pinFilter) {
                        const everyone = note.is_important == 1;
                        if (pinFilter === 'everyone' && !everyone) return false;
                        if (pinFilter === 'none' && everyone) return false;
                    }
                    return true;
                });
            },
            /**
             * Free board: the user's own layout — ピン（全員） band on top, other notes on a 3-column grid
             * (board_col / board_row from the server, per user). Off while filtering and below 3 columns.
             */
            noteBoardFree() {
                return this.noteBoardWide && !this.noteFiltersActive;
            },
            /** ピン（全員） first in the user's own pin order, then the others in reading order (row, column) */
            boardNotes() {
                const key = function (n) {
                    if (n.is_important == 1) {
                        const r = n.pin_rank === null || n.pin_rank === undefined ? 9999 : Number(n.pin_rank);
                        return [0, r];
                    }
                    const row = Number(n.board_row) || 0;
                    const col = Number(n.board_col) || 0;
                    return [1, row > 0 && col > 0 ? row * 10 + col : Number.MAX_SAFE_INTEGER];
                };
                return this.filteredNotes
                    .map(function (n, i) { return { n: n, i: i, k: key(n) }; })
                    .sort(function (a, b) { return a.k[0] - b.k[0] || a.k[1] - b.k[1] || a.i - b.i; })
                    .map(function (x) { return x.n; });
            },
            /** ピン（全員） notes in the user's order — the band at the top of the board */
            notePinned() {
                return this.boardNotes.filter(function (n) { return n.is_important == 1; });
            },
            /** Rows taken by the pin band; the grid is drawn this many rows lower */
            notePinRows() {
                return this.noteBoardFree ? Math.ceil(this.notePinned.length / 3) : 0;
            },
            /** Last grid row in use (pinned notes are not on the grid) */
            noteBoardMaxRow() {
                return (this.notes || []).reduce(function (m, n) {
                    return n.is_important == 1 ? m : Math.max(m, Number(n.board_row) || 0);
                }, 0);
            }
        },
        watch: {
            // save the user's choice — not the temporary on of 全画面表示 (restored on exit)
            noteShowFull(on) {
                if (this.noteBoardFullscreen) return;
                try { localStorage.setItem(NOTE_SHOW_FULL_STORAGE_KEY, on ? '1' : '0'); } catch (e) { /* storage blocked */ }
            },
            // a filter turned on mid-drag: the board is no longer free
            noteBoardFree(free) {
                if (!free) this.cancelNoteDrag();
            }
        },
        mounted() {
            this.initNoteBoardMedia();
            // 編集ロック: who is editing which card (note-edit-presence.js), kept current while the page is open
            this._noteEditPoll = setInterval(() => this.pollNoteEditors(), NOTE_EDIT_POLL_MS);
            this._onNoteEditVisible = () => { if (!document.hidden) this.pollNoteEditors(); };
            document.addEventListener('visibilitychange', this._onNoteEditVisible);
        },
        beforeUnmount() {
            clearInterval(this._noteEditPoll);
            document.removeEventListener('visibilitychange', this._onNoteEditVisible);
            this.cancelNoteDrag();
            if (this.noteBoardFullscreen) this.exitNoteBoardFullscreen();
            if (this._onNoteFsChange) {
                document.removeEventListener('fullscreenchange', this._onNoteFsChange);
                document.removeEventListener('keydown', this._onNoteFsKey);
            }
            if (this._noteBoardMql) {
                if (this._noteBoardMql.removeEventListener) this._noteBoardMql.removeEventListener('change', this._onNoteBoardMedia);
                else if (this._noteBoardMql.removeListener) this._noteBoardMql.removeListener(this._onNoteBoardMedia);
            }
        },
        methods: {
            /** Refresh note.edit_locked_name (someone else has the note open in the editor) */
            async pollNoteEditors() {
                const presence = global.NoteEditPresence;
                const notes = this.notes || [];
                if (document.hidden || !presence || !notes.length || !this.getNoteBoardProjectId()) return;
                let map;
                try {
                    map = await presence.fetch(notes.map(function (n) { return n.id; }));
                } catch (e) {
                    return;
                }
                (this.notes || []).forEach(function (n) {
                    const name = map[n.id] || null;
                    if ((n.edit_locked_name || null) !== name) n.edit_locked_name = name;
                });
            },
            decodeNoteHtml(str) {
                if (!str) return '';
                // HTML from Quill is used as is; only escaped HTML entities are decoded
                if (str.indexOf('&lt;') !== -1 || str.indexOf('&gt;') !== -1 || str.indexOf('&amp;') !== -1) {
                    const txt = document.createElement('textarea');
                    txt.innerHTML = str;
                    return txt.value;
                }
                return str;
            },
            /** "2026/11/13 13:42" */
            formatShortDateTime(value) {
                if (!value) return '';
                const m = String(value).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
                return m ? `${m[1]}/${m[2]}/${m[3]} ${m[4]}:${m[5]}` : String(value);
            },
            clearNoteFilters() {
                this.noteFilterLabel = '';
                this.noteFilterType = '';
                this.noteFilterVisibility = '';
                this.noteFilterPin = '';
            },
            /** Grid placement of a card on the free board (null = auto-flow) */
            noteCardGridStyle(note) {
                if (!this.noteBoardFree) return null;
                if (note.is_important == 1) {
                    const i = this.notePinned.indexOf(note);
                    if (i < 0) return null;
                    return { gridColumn: String((i % 3) + 1), gridRow: String(Math.floor(i / 3) + 1) };
                }
                const c = Number(note.board_col) || 0;
                const r = Number(note.board_row) || 0;
                if (!c || !r) return null;
                return { gridColumn: String(c), gridRow: String(r + this.notePinRows) };
            },
            /**
             * 全画面表示: the メモ card is laid over the whole window (CSS .note-board-fullscreen) and the
             * browser goes fullscreen too when it allows it. The page itself goes fullscreen (not the card)
             * so the note modal, which lives outside the card, still shows. Esc / the browser's own exit
             * close it.
             */
            toggleNoteBoardFullscreen() {
                if (this.noteBoardFullscreen) this.exitNoteBoardFullscreen();
                else this.enterNoteBoardFullscreen();
            },
            enterNoteBoardFullscreen() {
                if (this.noteBoardFullscreen) return;
                this.noteBoardFullscreen = true;
                // fullscreen shows whole notes; the previous 全文表示 setting comes back on exit
                this._noteShowFullBeforeFs = this.noteShowFull;
                this.noteShowFull = true;
                document.body.classList.add('note-board-fullscreen-open');
                if (!this._onNoteFsChange) {
                    this._onNoteFsChange = () => {
                        if (!document.fullscreenElement && this._noteFsByUs) {
                            this._noteFsByUs = false;
                            this.exitNoteBoardFullscreen();
                        }
                    };
                    this._onNoteFsKey = (e) => {
                        // Esc without real fullscreen (not allowed / not supported); an open modal or drag takes Esc first
                        if (e.key !== 'Escape' || !this.noteBoardFullscreen || this.noteDrag) return;
                        if (document.fullscreenElement || document.querySelector('.modal.show')) return;
                        this.exitNoteBoardFullscreen();
                    };
                    document.addEventListener('fullscreenchange', this._onNoteFsChange);
                    document.addEventListener('keydown', this._onNoteFsKey);
                }
                const el = document.documentElement;
                if (el.requestFullscreen && !document.fullscreenElement) {
                    el.requestFullscreen().then(() => { this._noteFsByUs = true; }).catch(() => { /* overlay only */ });
                }
            },
            exitNoteBoardFullscreen() {
                this.cancelNoteDrag();
                this.noteBoardFullscreen = false;
                if (this._noteShowFullBeforeFs !== undefined) {
                    this.noteShowFull = this._noteShowFullBeforeFs;
                    this._noteShowFullBeforeFs = undefined;
                }
                document.body.classList.remove('note-board-fullscreen-open');
                if (this._noteFsByUs && document.fullscreenElement && document.exitFullscreen) {
                    this._noteFsByUs = false;
                    document.exitFullscreen().catch(() => {});
                }
                if (typeof this.onNoteBoardClosed === 'function') this.onNoteBoardClosed();
            },
            onNoteBoardMediaChange(e) {
                this.noteBoardWide = !!(e && e.matches);
                if (!this.noteBoardWide) this.cancelNoteDrag();
            },
            initNoteBoardMedia() {
                if (!global.matchMedia) return;
                this._noteBoardMql = global.matchMedia(NOTE_BOARD_FREE_MEDIA);
                this._onNoteBoardMedia = (e) => this.onNoteBoardMediaChange(e);
                if (this._noteBoardMql.addEventListener) this._noteBoardMql.addEventListener('change', this._onNoteBoardMedia);
                else if (this._noteBoardMql.addListener) this._noteBoardMql.addListener(this._onNoteBoardMedia);
                this.noteBoardWide = this._noteBoardMql.matches;
            },
            /**
             * Drag a note by its handle to any cell of the board: a floating copy follows the
             * pointer, the target cell is outlined, and on drop the server places the note there
             * (an occupied cell pushes its note — and the ones after it — one cell on).
             */
            /** The drop would move other notes: grid cell taken, or a different place in the pin band */
            noteDragTargetOccupied(d) {
                if (!d || !d.target) return false;
                if (d.pinned) {
                    return this.notePinned.findIndex(function (n) { return n.id == d.id; }) !== d.target.idx;
                }
                return (this.notes || []).some(function (n) {
                    return n.id != d.id && n.is_important != 1
                        && Number(n.board_col) === d.target.col && Number(n.board_row) === d.target.row;
                });
            },
            /** The board's resolved grid: viewport rect, track sizes, gaps and padding */
            noteBoardMetrics() {
                const board = this.$refs.noteBoardGrid;
                if (!board) return null;
                const r = board.getBoundingClientRect();
                const st = getComputedStyle(board);
                const tracks = function (v) { return v.split(' ').map(parseFloat).filter(function (x) { return x > 0; }); };
                return {
                    r: r,
                    cols: tracks(st.gridTemplateColumns),
                    rows: tracks(st.gridTemplateRows),
                    colGap: parseFloat(st.columnGap) || 0,
                    rowGap: parseFloat(st.rowGap) || 0,
                    padL: parseFloat(st.paddingLeft) || 0,
                    padT: parseFloat(st.paddingTop) || 0
                };
            },
            /** Viewport box of a board cell (display row: pin band rows first) */
            noteBoardCellBox(m, col, row) {
                const sum = function (tracks, gap, n) {
                    let at = 0;
                    for (let i = 0; i < n && i < tracks.length; i++) at += tracks[i] + gap;
                    return at;
                };
                const w = m.cols[col - 1] || m.cols[m.cols.length - 1] || m.r.width;
                const h = m.rows[row - 1] || m.rows[m.rows.length - 1] || 120;
                return {
                    x: m.r.left + m.padL + sum(m.cols, m.colGap, col - 1),
                    y: m.r.top + m.padT + sum(m.rows, m.rowGap, row - 1),
                    w: w,
                    h: h
                };
            },
            /**
             * Drag visuals are moved with the Web Animations API, not inline styles or classes: those make
             * the page re-style, which costs a lot on pages with heavy :has() rules (a demo.css rule, since
             * removed, made it ≈8–10 ms per pointer move on 案件一覧). Nothing re-renders while dragging.
             */
            updateNoteDragVisuals(e) {
                const d = this.noteDrag;
                if (!d) return;
                if (e && this._noteGhostAnim) {
                    this._noteGhostAnim.effect.setKeyframes([{
                        transform: 'translate3d(' + (e.clientX - d.offX - d.startLeft) + 'px, '
                            + (e.clientY - d.offY - d.startTop) + 'px, 0) rotate(1.5deg)'
                    }]);
                }
                if (!this._noteDropAnim) return;
                const m = d.target ? this.noteBoardMetrics() : null;
                if (!m) {
                    this._noteDropAnim.effect.setKeyframes([{ opacity: 0 }]);
                    return;
                }
                const row = d.pinned ? d.target.row : d.target.row + this.notePinRows;
                const box = this.noteBoardCellBox(m, d.target.col, row);
                const look = this.noteDragTargetOccupied(d) ? this._noteDropLook.busy : this._noteDropLook.free;
                this._noteDropAnim.effect.setKeyframes([{
                    opacity: 1,
                    transform: 'translate(' + box.x + 'px, ' + box.y + 'px)',
                    width: box.w + 'px',
                    height: box.h + 'px',
                    backgroundColor: look.bg,
                    boxShadow: look.shadow
                }]);
            },
            onNoteDragStart(note, e) {
                if (!this.noteBoardFree || this.noteDrag || (e.button !== undefined && e.button !== 0)) return;
                const card = e.currentTarget && e.currentTarget.closest('.note-card');
                const board = this.$refs.noteBoardGrid;
                if (!card || !board) return;
                e.preventDefault();
                const rect = card.getBoundingClientRect();
                const ghost = card.cloneNode(true);
                ghost.classList.add('note-drag-ghost');
                ghost.classList.remove('is-dragging');
                ghost.removeAttribute('data-note-id');
                ghost.style.width = rect.width + 'px';
                ghost.style.height = rect.height + 'px';
                ghost.style.left = rect.left + 'px';
                ghost.style.top = rect.top + 'px';
                // a body-level .note-board layer keeps the board styles without touching Vue's DOM
                const layer = document.createElement('div');
                layer.className = board.className + ' note-drag-layer';
                layer.appendChild(ghost);
                // target cell outline, drawn over the board (no Vue re-render while the pointer moves)
                const drop = document.createElement('div');
                drop.className = 'note-drop-cell';
                layer.appendChild(drop);
                document.body.appendChild(layer);
                this._noteDragGhost = layer;
                const look = function () {
                    const cs = getComputedStyle(drop);
                    return { bg: cs.backgroundColor, shadow: cs.boxShadow };
                };
                drop.classList.add('is-occupied');
                const busy = look();
                drop.classList.remove('is-occupied');
                this._noteDropLook = { free: look(), busy: busy };
                const anim = function (el, frame) {
                    const a = el.animate ? el.animate([frame], { duration: 1, fill: 'forwards' }) : null;
                    if (a) a.finish();
                    return a;
                };
                this._noteGhostAnim = anim(ghost, { transform: 'translate3d(0px, 0px, 0) rotate(1.5deg)' });
                this._noteDropAnim = anim(drop, { opacity: 0 });
                const pinned = note.is_important == 1;
                const pinIdx = pinned ? this.notePinned.indexOf(note) : -1;
                // raw: moving the target must not re-render the page (see updateNoteDragVisuals)
                const raw = global.Vue && global.Vue.markRaw ? global.Vue.markRaw : function (o) { return o; };
                this.noteDrag = raw({
                    id: note.id,
                    pinned: pinned,
                    offX: e.clientX - rect.left,
                    offY: e.clientY - rect.top,
                    startLeft: rect.left,
                    startTop: rect.top,
                    target: pinned
                        ? { col: (pinIdx % 3) + 1, row: Math.floor(pinIdx / 3) + 1, idx: pinIdx }
                        : { col: Number(note.board_col) || 1, row: Number(note.board_row) || 1 }
                });
                this._onNoteDragMove = (ev) => this.onNoteDragMove(ev);
                this._onNoteDragUp = (ev) => this.onNoteDragEnd(ev);
                this._onNoteDragKey = (ev) => { if (ev.key === 'Escape') this.cancelNoteDrag(); };
                this._onNoteDragCancel = () => this.cancelNoteDrag();
                global.addEventListener('pointermove', this._onNoteDragMove);
                global.addEventListener('pointerup', this._onNoteDragUp);
                global.addEventListener('pointercancel', this._onNoteDragCancel);
                global.addEventListener('keydown', this._onNoteDragKey);
                // after the board re-rendered for the drag (extra row below the last note)
                this.$nextTick(() => this.updateNoteDragVisuals(null));
            },
            onNoteDragMove(e) {
                const d = this.noteDrag;
                if (!d) return;
                const t = this.noteBoardCellAt(e.clientX, e.clientY);
                if (!t) d.target = null;
                else if (!d.target || d.target.col !== t.col || d.target.row !== t.row || d.target.idx !== t.idx) d.target = t;
                // keep scrolling while dragging near the top / bottom edge (the card scrolls in fullscreen)
                const edge = 60;
                const step = e.clientY < edge ? -14 : (e.clientY > global.innerHeight - edge ? 14 : 0);
                if (step) {
                    const body = this.noteBoardFullscreen && this.$refs.noteBoardGrid
                        ? this.$refs.noteBoardGrid.closest('.card-body') : null;
                    if (body) body.scrollTop += step;
                    else global.scrollBy(0, step);
                }
                this.updateNoteDragVisuals(e);
            },
            /**
             * Drop target under a viewport point, from the board's resolved tracks; null when off the board
             * or in the other zone: a pinned note stays in the pin band ({ col, row, idx }), any other note
             * stays on the grid below it ({ col, row } in grid rows).
             */
            noteBoardCellAt(x, y) {
                const m = this.noteBoardMetrics();
                if (!m) return null;
                const r = m.r;
                const margin = 40;
                if (x < r.left - margin || x > r.right + margin || y < r.top - margin || y > r.bottom + margin) return null;
                const cols = m.cols;
                const rows = m.rows;
                const colGap = m.colGap;
                const rowGap = m.rowGap;
                const pick = function (tracks, gap, offset) {
                    let edgeAt = 0;
                    for (let i = 0; i < tracks.length; i++) {
                        edgeAt += tracks[i] + (i < tracks.length - 1 ? gap / 2 : 0);
                        if (offset < edgeAt) return i + 1;
                        edgeAt += gap / 2;
                    }
                    return Math.max(1, tracks.length);
                };
                let col = pick(cols.length ? cols : [r.width], colGap, x - r.left - m.padL);
                let row = pick(rows.length ? rows : [r.height], rowGap, y - r.top - m.padT);
                col = Math.min(col, 3);
                const bandRows = this.notePinRows;
                if (this.noteDrag && this.noteDrag.pinned) {
                    if (row > bandRows) return null;
                    const idx = Math.min((row - 1) * 3 + (col - 1), this.notePinned.length - 1);
                    return { col: (idx % 3) + 1, row: Math.floor(idx / 3) + 1, idx: idx };
                }
                row -= bandRows;             // display row → grid row
                if (row < 1) return null;    // over the pin band
                row = Math.min(row, this.noteBoardMaxRow + 1);
                return { col: col, row: row };
            },
            stopNoteDragListeners() {
                global.removeEventListener('pointermove', this._onNoteDragMove);
                global.removeEventListener('pointerup', this._onNoteDragUp);
                global.removeEventListener('pointercancel', this._onNoteDragCancel);
                global.removeEventListener('keydown', this._onNoteDragKey);
                if (this._noteDragGhost) {
                    this._noteDragGhost.remove();
                    this._noteDragGhost = null;
                }
                this._noteGhostAnim = null;
                this._noteDropAnim = null;
            },
            cancelNoteDrag() {
                this.stopNoteDragListeners();
                this.noteDrag = null;
            },
            async onNoteDragEnd() {
                const d = this.noteDrag;
                this.stopNoteDragListeners();
                this.noteDrag = null;
                if (!d || !d.target) return;
                const note = (this.notes || []).find(function (n) { return n.id == d.id; });
                if (!note) return;
                if (d.pinned) {
                    await this.dropPinnedNote(note, d.target.idx);
                    return;
                }
                if (Number(note.board_col) === d.target.col && Number(note.board_row) === d.target.row) return;
                try {
                    const fd = new FormData();
                    fd.append('project_id', this.getNoteBoardProjectId());
                    fd.append('note_id', d.id);
                    fd.append('col', d.target.col);
                    fd.append('row', d.target.row);
                    const res = await axios.post('/api/index.php?model=project&method=moveNoteOnBoard', fd);
                    const positions = res.data && res.data.status === 'success' ? res.data.positions : null;
                    if (!positions) throw new Error((res.data && res.data.error) || 'move failed');
                    (this.notes || []).forEach(function (n) {
                        const p = positions[n.id];
                        if (p) { n.board_col = p[0]; n.board_row = p[1]; }
                    });
                } catch (err) {
                    console.error('Failed to move note', err);
                    this.noteBoardNotify('メモの移動に失敗しました', 'error');
                    await this.reloadNoteBoard();
                }
            },
            /** Pin band: move a pinned note to place idx in the user's own pin order */
            async dropPinnedNote(note, idx) {
                const order = this.notePinned.slice();
                const from = order.indexOf(note);
                if (from < 0 || from === idx) return;
                order.splice(from, 1);
                order.splice(idx, 0, note);
                order.forEach(function (n, i) { n.pin_rank = i; });
                try {
                    const fd = new FormData();
                    fd.append('project_id', this.getNoteBoardProjectId());
                    fd.append('note_ids', JSON.stringify(order.map(function (n) { return n.id; })));
                    const res = await axios.post('/api/index.php?model=project&method=reorderPinnedNotes', fd);
                    if (!res.data || res.data.status !== 'success') throw new Error((res.data && res.data.error) || 'reorder failed');
                } catch (err) {
                    console.error('Failed to reorder pinned notes', err);
                    this.noteBoardNotify('メモの移動に失敗しました', 'error');
                    await this.reloadNoteBoard();
                }
            },
            async toggleNotePin(note) {
                if (!note || !note.id) return;
                try {
                    const formData = new FormData();
                    formData.append('id', note.id);
                    const response = await axios.post('/api/index.php?model=project&method=toggleNoteImportant', formData);
                    if (response.data && response.data.status === 'success') {
                        await this.reloadNoteBoard();
                    } else if (response.data && response.data.status === 'locked') {
                        this.noteBoardNotify(response.data.error, 'error');
                        await this.reloadNoteBoard();
                    } else {
                        this.noteBoardNotify('ピンの更新に失敗しました', 'error');
                    }
                } catch (error) {
                    console.error('Error toggling note pin:', error);
                    this.noteBoardNotify('ピンの更新に失敗しました', 'error');
                }
            }
        }
    };
})(window);
