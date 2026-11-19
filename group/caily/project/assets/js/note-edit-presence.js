/**
 * メモ 編集ロック: show who is editing a note right now.
 *
 * NoteEditPresence.fetch(ids) → Promise<{ note id: realname }> (other users' live locks; API editingNotes).
 * メモボード (project-note-board-mixin.js) polls it into note.edit_locked_name.
 * 案件一覧: this file puts a chip "✎ name" into each memo cell item (.confirmation-note-item[data-note-id])
 * that someone else is editing, refreshes every POLL_MS and keeps the chips when the table redraws.
 */
(function (global) {
    'use strict';

    var API = '/api/index.php?model=project&method=editingNotes';
    var POLL_MS = 20000;

    function fetchEditing(ids) {
        var list = (ids || []).map(String).filter(Boolean);
        if (!list.length || !global.axios) return Promise.resolve({});
        var fd = new FormData();
        fd.append('note_ids', JSON.stringify(list));
        return global.axios.post(API, fd).then(function (res) {
            var d = res && res.data;
            return d && d.status === 'success' && d.editing ? d.editing : {};
        });
    }

    function tr(text) {
        return typeof global.translateText === 'function' ? global.translateText(text) : text;
    }

    /* ---------- 案件一覧 memo cells ---------- */
    var editing = {};      // note id → name, from the last poll
    var asked = {};        // note ids the last poll asked about
    var polling = false;
    var observer = null;

    function listItems(root) {
        return (root || document).querySelectorAll('#projectTable .confirmation-note-item[data-note-id]');
    }

    function decorate() {
        var items = listItems();
        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            var name = editing[item.getAttribute('data-note-id')] || '';
            var row = item.querySelector('.note-text-row');
            var chip = row ? row.querySelector('.note-editing-chip') : null;
            if (!row) continue;
            if (!name) {
                if (chip) chip.remove();
                continue;
            }
            if (chip && chip.getAttribute('data-name') === name) continue;
            if (chip) chip.remove();
            chip = document.createElement('span');
            chip.className = 'note-editing-chip';
            chip.setAttribute('data-name', name);
            chip.title = name + tr('さんが編集中');
            chip.innerHTML = '<i class="fa fa-user-edit"></i>';
            chip.appendChild(document.createTextNode(' ' + name));
            row.insertBefore(chip, row.firstChild);
        }
    }

    function poll() {
        if (polling || document.hidden) return;
        var items = listItems();
        var ids = [];
        for (var i = 0; i < items.length; i++) ids.push(items[i].getAttribute('data-note-id'));
        polling = true;
        fetchEditing(ids).then(function (map) {
            editing = map;
            asked = {};
            ids.forEach(function (id) { asked[id] = true; });
        }).catch(function () { /* keep the last answer */ }).then(function () {
            polling = false;
            decorate();
        });
    }

    var settleTimer = null;
    function onTableChange() {
        clearTimeout(settleTimer);
        settleTimer = setTimeout(function () {
            decorate(); // a redraw dropped the chips: put the known ones back at once
            var items = listItems();
            for (var i = 0; i < items.length; i++) {
                if (!asked[items[i].getAttribute('data-note-id')]) {
                    poll(); // notes we have not asked about yet (paging, filter, new note)
                    return;
                }
            }
        }, 300);
    }

    var observed = null;
    /** Watch the current #projectTable (the page may replace the element after load) */
    function watchTable() {
        var table = document.getElementById('projectTable');
        if (!table || table === observed) return;
        if (observer) observer.disconnect();
        observed = table;
        if (!observer) {
            observer = new MutationObserver(function (records) {
                for (var i = 0; i < records.length; i++) {
                    // our own chips do not count
                    var nodes = [].slice.call(records[i].addedNodes).concat([].slice.call(records[i].removedNodes));
                    for (var j = 0; j < nodes.length; j++) {
                        var n = nodes[j];
                        if (n.nodeType === 1 && !(n.classList && n.classList.contains('note-editing-chip'))) {
                            onTableChange();
                            return;
                        }
                    }
                }
            });
        }
        observer.observe(table, { childList: true, subtree: true });
    }

    function startList() {
        if (!document.getElementById('projectTable')) return; // not 案件一覧
        watchTable();
        // every DataTables draw (paging, filter, reload), whichever table element is current
        if (global.jQuery) {
            global.jQuery(document).on('draw.dt', function () {
                watchTable();
                onTableChange();
            });
        }
        poll();
        setInterval(function () {
            watchTable();
            poll();
        }, POLL_MS);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) poll();
        });
    }

    global.NoteEditPresence = { fetch: fetchEditing, POLL_MS: POLL_MS, refreshList: poll };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startList);
    } else {
        startList();
    }
})(window);
