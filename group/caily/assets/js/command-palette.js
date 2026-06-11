(function () {
    'use strict';

    var ROOT = (typeof window.__APP_ROOT === 'string') ? window.__APP_ROOT : '/';
    var modalEl = null;
    var modalInstance = null;
    var searchInput = null;
    var resultsEl = null;
    var emptyEl = null;
    var searchMode = 'parent';
    var results = [];
    var selectedIndex = -1;
    var searchTimer = null;
    var flags = window.__APP_SHORTCUTS || {};
    var enabled = !!window.__COMMAND_PALETTE_ENABLED;

    function t(key, fallback) {
        if (typeof i18next !== 'undefined' && i18next.isInitialized) {
            var translated = i18next.t(key);
            if (translated && translated !== key) {
                return translated;
            }
        }
        return fallback || key;
    }

    function shouldIgnoreTarget(target) {
        if (!target) {
            return false;
        }
        var tag = target.tagName ? target.tagName.toLowerCase() : '';
        if (tag === 'input' || tag === 'textarea' || tag === 'select') {
            return true;
        }
        if (target.isContentEditable) {
            return true;
        }
        if (target.closest && target.closest('.ql-editor, .ql-container, [contenteditable="true"]')) {
            return true;
        }
        return false;
    }

    function isMac() {
        return /Mac|iPhone|iPad|iPod/.test(navigator.platform || navigator.userAgent);
    }

    function isFunctionKey(key) {
        return /^F([1-9]|1[0-2])$/i.test(String(key || ''));
    }

    function formatKeys(keys) {
        var mac = isMac();
        return keys.map(function (key) {
            if (key === 'Ctrl') {
                return mac ? '⌃' : 'Ctrl';
            }
            if (key === 'Alt') {
                return mac ? '⌥' : 'Alt';
            }
            if (key === 'Shift') {
                return mac ? '⇧' : 'Shift';
            }
            return key;
        });
    }

    function navigate(url) {
        window.location.href = url;
    }

    function openTodoOffcanvas() {
        var toggle = document.getElementById('todo-toggle');
        if (toggle) {
            toggle.click();
        }
    }

    function canShowProject() {
        return !!(enabled || flags.showProject);
    }

    function canShowExtended() {
        return !!flags.showExtended;
    }

    var shortcuts = [
        {
            id: 'palette',
            labelKey: 'コマンドパレット',
            labelDefault: 'コマンドパレット',
            keys: ['F1'],
            keySets: [['Ctrl', 'K']],
            when: canShowProject,
            action: function () { openModal('search'); }
        },
        {
            id: 'project-list',
            labelKey: '案件一覧へ',
            labelDefault: '案件一覧へ',
            keys: ['F2'],
            when: canShowProject,
            action: function () { navigate(ROOT + 'project/'); }
        },
        {
            id: 'parent-list',
            labelKey: '建物一覧へ',
            labelDefault: '建物一覧へ',
            keys: ['F3'],
            when: canShowProject,
            action: function () { navigate(ROOT + 'parent_project/'); }
        },
        {
            id: 'customer',
            labelKey: '顧客情報へ',
            labelDefault: '顧客情報へ',
            keys: ['F4'],
            when: function () { return canShowProject() && canShowExtended(); },
            action: function () { navigate(ROOT + 'customer/'); }
        },
        {
            id: 'reload',
            labelKey: 'ページを再読み込み',
            labelDefault: 'ページを再読み込み',
            keys: ['F5'],
            when: function () { return true; },
            action: function () { window.location.reload(); }
        },
        {
            id: 'task-overview',
            labelKey: 'タスク一覧へ',
            labelDefault: 'タスク一覧へ',
            keys: ['F6'],
            when: canShowProject,
            action: function () { navigate(ROOT + 'project/task_overview.php'); }
        },
        {
            id: 'project-gantt',
            labelKey: '案件ガントチャートへ',
            labelDefault: '案件ガントチャートへ',
            keys: ['F7'],
            when: canShowProject,
            action: function () { navigate(ROOT + 'project/project_gantt.php'); }
        },
        {
            id: 'form',
            labelKey: '申請・承認へ',
            labelDefault: '申請・承認へ',
            keys: ['F8'],
            when: function () { return true; },
            action: function () { navigate(ROOT + 'form/index.php'); }
        },
        {
            id: 'schedule',
            labelKey: 'カレンダーへ',
            labelDefault: 'カレンダーへ',
            keys: ['F9'],
            when: function () { return true; },
            action: function () { navigate(ROOT + 'schedule/'); }
        },
        {
            id: 'timecard',
            labelKey: 'タイムカードへ',
            labelDefault: 'タイムカードへ',
            keys: ['F10'],
            when: canShowExtended,
            action: function () { navigate(ROOT + 'timecard/'); }
        },
        {
            id: 'home',
            labelKey: 'ホームへ',
            labelDefault: 'ホームへ',
            keys: ['F11'],
            when: function () { return true; },
            action: function () { navigate(ROOT); }
        },
        {
            id: 'forum',
            labelKey: 'お知らせへ',
            labelDefault: 'お知らせへ',
            keys: ['F12'],
            when: canShowExtended,
            action: function () { navigate(ROOT + 'forum/?folder=0'); }
        }
    ];

    function getModalInstance() {
        if (!modalEl || typeof bootstrap === 'undefined') {
            return null;
        }
        if (!modalInstance) {
            modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
        }
        return modalInstance;
    }

    function canShowCustomerSearch() {
        return canShowProject() && canShowExtended();
    }

    function setSearchMode(mode) {
        if (mode === 'project') {
            searchMode = 'project';
        } else if (mode === 'customer' && canShowCustomerSearch()) {
            searchMode = 'customer';
        } else {
            searchMode = 'parent';
        }
        document.querySelectorAll('[data-command-palette-mode]').forEach(function (btn) {
            var active = btn.getAttribute('data-command-palette-mode') === searchMode;
            btn.classList.toggle('active', active);
        });
        if (searchInput) {
            if (searchMode === 'project') {
                searchInput.placeholder = t('案件ID / 名称 / 会社名', '案件ID / 名称 / 会社名');
            } else if (searchMode === 'customer') {
                searchInput.placeholder = t('会社名 / 支店名 / メール', '会社名 / 支店名 / メール');
            } else {
                searchInput.placeholder = t('工事番号 / ID / 会社名 / 支店名', '工事番号 / ID / 会社名 / 支店名');
            }
        }
        runSearch(searchInput ? searchInput.value : '');
    }

    function renderResults(items) {
        results = items || [];
        selectedIndex = results.length ? 0 : -1;
        if (!resultsEl || !emptyEl) {
            return;
        }
        resultsEl.innerHTML = '';
        if (!results.length) {
            emptyEl.classList.toggle('d-none', !!(searchInput && searchInput.value.trim()));
            return;
        }
        emptyEl.classList.add('d-none');
        results.forEach(function (item, index) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'command-palette-result' + (index === selectedIndex ? ' is-active' : '');
            btn.dataset.index = String(index);

            var title = document.createElement('div');
            title.className = 'command-palette-result-title';
            if (searchMode === 'parent') {
                title.textContent = '#' + item.id + ' ' + (item.project_name || item.company_name || '');
            } else if (searchMode === 'customer') {
                title.textContent = '#' + item.id + ' ' + (item.company_name || '');
            } else {
                title.textContent = '#' + item.id + ' ' + (item.name || '');
            }

            var meta = document.createElement('div');
            meta.className = 'command-palette-result-meta';
            if (searchMode === 'parent') {
                meta.textContent = [
                    item.construction_number ? ('工事: ' + item.construction_number) : '',
                    item.company_name || '',
                    item.branch_name || ''
                ].filter(Boolean).join(' · ');
            } else if (searchMode === 'customer') {
                meta.textContent = [
                    item.branch || '',
                    item.name || '',
                    item.email || '',
                    item.category_name ? ('カテゴリー: ' + item.category_name) : ''
                ].filter(Boolean).join(' · ');
            } else {
                meta.textContent = [
                    item.company_name || '',
                    item.branch_name || '',
                    item.parent_construction_number ? ('工事: ' + item.parent_construction_number) : ''
                ].filter(Boolean).join(' · ');
            }

            btn.appendChild(title);
            btn.appendChild(meta);
            btn.addEventListener('click', function () {
                openResult(item);
            });
            resultsEl.appendChild(btn);
        });
    }

    function highlightSelection() {
        if (!resultsEl) {
            return;
        }
        resultsEl.querySelectorAll('.command-palette-result').forEach(function (el, idx) {
            el.classList.toggle('is-active', idx === selectedIndex);
        });
        var active = resultsEl.querySelector('.command-palette-result.is-active');
        if (active && typeof active.scrollIntoView === 'function') {
            active.scrollIntoView({ block: 'nearest' });
        }
    }

    function openResult(item) {
        if (!item || !item.id) {
            return;
        }
        closeModal();
        if (searchMode === 'parent') {
            navigate(ROOT + 'parent_project/detail.php?id=' + encodeURIComponent(item.id));
        } else if (searchMode === 'customer') {
            if (typeof window.openGlobalCustomerModal === 'function') {
                window.openGlobalCustomerModal(item.id);
            }
        } else {
            navigate(ROOT + 'project/detail.php?id=' + encodeURIComponent(item.id));
        }
    }

    function runSearch(query) {
        query = (query || '').trim();
        if (!query) {
            renderResults([]);
            if (emptyEl) {
                emptyEl.classList.add('d-none');
            }
            return;
        }
        var model = 'parentproject';
        if (searchMode === 'project') {
            model = 'project';
        } else if (searchMode === 'customer') {
            model = 'customer';
        }
        var url = ROOT + 'api/index.php?model=' + model + '&method=paletteSearch&q=' + encodeURIComponent(query);
        if (typeof axios !== 'undefined') {
            axios.get(url).then(function (response) {
                renderResults(Array.isArray(response.data) ? response.data : []);
            }).catch(function () {
                renderResults([]);
            });
            return;
        }
        fetch(url, { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) { renderResults(Array.isArray(data) ? data : []); })
            .catch(function () { renderResults([]); });
    }

    function queueSearch(query) {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            runSearch(query);
        }, 250);
    }

    function openModal(focusSection) {
        var instance = getModalInstance();
        if (!instance) {
            return;
        }
        instance.show();
        window.setTimeout(function () {
            if (focusSection === 'search' && searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }, 150);
    }

    window.openCommandPalette = function () {
        openModal('search');
    };

    function closeModal() {
        var instance = getModalInstance();
        if (instance) {
            instance.hide();
        }
    }

    function getShortcutKeySets(shortcut) {
        var sets = [shortcut.keys];
        if (shortcut.keySets && shortcut.keySets.length) {
            sets = sets.concat(shortcut.keySets);
        }
        return sets;
    }

    function shortcutMatches(event, shortcut) {
        var sets = getShortcutKeySets(shortcut);
        for (var i = 0; i < sets.length; i++) {
            if (matchesShortcut(event, sets[i])) {
                return true;
            }
        }
        return false;
    }

    function matchesShortcut(event, keys) {
        var needCtrl = keys.indexOf('Ctrl') >= 0;
        var needAlt = keys.indexOf('Alt') >= 0;
        var needShift = keys.indexOf('Shift') >= 0;
        var mainKey = keys[keys.length - 1];
        if (isFunctionKey(mainKey)) {
            if (event.ctrlKey || event.altKey || event.shiftKey || event.metaKey) {
                return false;
            }
            return String(event.key).toLowerCase() === String(mainKey).toLowerCase();
        }
        if (needCtrl !== (event.ctrlKey || event.metaKey)) {
            return false;
        }
        if (needAlt !== event.altKey) {
            return false;
        }
        if (needShift !== event.shiftKey) {
            return false;
        }
        return String(event.key).toLowerCase() === String(mainKey).toLowerCase();
    }

    function isRegisteredShortcutEvent(event) {
        for (var i = 0; i < shortcuts.length; i++) {
            var shortcut = shortcuts[i];
            if (shortcut.when && !shortcut.when()) {
                continue;
            }
            if (shortcutMatches(event, shortcut)) {
                return true;
            }
        }
        return false;
    }

    function runShortcutAction(shortcut) {
        if (!shortcut || typeof shortcut.action !== 'function') {
            return;
        }
        if (shortcut.id === 'palette') {
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
            return;
        }
        closeModal();
        window.setTimeout(function () {
            shortcut.action();
        }, 150);
    }

    function onGlobalKeydown(event) {
        var isAppShortcut = isRegisteredShortcutEvent(event);

        if (shouldIgnoreTarget(event.target) && !modalEl?.classList.contains('show') && !isAppShortcut) {
            return;
        }

        if (event.key === '?' && !event.ctrlKey && !event.altKey && !event.metaKey && !shouldIgnoreTarget(event.target)) {
            event.preventDefault();
            openModal('shortcuts');
            return;
        }

        for (var i = 0; i < shortcuts.length; i++) {
            var shortcut = shortcuts[i];
            if (!shortcut.when || !shortcut.when()) {
                continue;
            }
            if (shortcutMatches(event, shortcut)) {
                event.preventDefault();
                event.stopPropagation();
                shortcut.action();
                return;
            }
        }

        if (!modalEl || !modalEl.classList.contains('show')) {
            return;
        }

        if (event.key === 'Escape') {
            closeModal();
            return;
        }

        if (event.target === searchInput) {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (results.length) {
                    selectedIndex = Math.min(results.length - 1, selectedIndex + 1);
                    highlightSelection();
                }
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                if (results.length) {
                    selectedIndex = Math.max(0, selectedIndex - 1);
                    highlightSelection();
                }
            } else if (event.key === 'Enter') {
                event.preventDefault();
                if (selectedIndex >= 0 && results[selectedIndex]) {
                    openResult(results[selectedIndex]);
                }
            }
        }
    }

    function renderShortcutList() {
        var listEl = document.getElementById('command-palette-shortcuts');
        if (!listEl) {
            return;
        }
        listEl.innerHTML = '';
        shortcuts.forEach(function (shortcut) {
            if (shortcut.when && !shortcut.when()) {
                return;
            }
            var row = document.createElement('button');
            row.type = 'button';
            row.className = 'command-palette-shortcut-row';

            var label = document.createElement('span');
            label.textContent = t(shortcut.labelKey, shortcut.labelDefault);

            var keysWrap = document.createElement('span');
            keysWrap.className = 'command-palette-shortcut-keys';
            getShortcutKeySets(shortcut).forEach(function (keySet, setIndex) {
                if (setIndex > 0) {
                    var sep = document.createElement('span');
                    sep.className = 'command-palette-shortcut-sep';
                    sep.textContent = '/';
                    keysWrap.appendChild(sep);
                }
                formatKeys(keySet).forEach(function (keyText) {
                    var kbd = document.createElement('kbd');
                    kbd.className = 'command-palette-kbd';
                    kbd.textContent = keyText;
                    keysWrap.appendChild(kbd);
                });
            });

            row.appendChild(label);
            row.appendChild(keysWrap);
            row.addEventListener('click', function () {
                runShortcutAction(shortcut);
            });
            listEl.appendChild(row);
        });
    }

    function initShortcuts() {
        document.addEventListener('keydown', onGlobalKeydown, true);
        if (typeof i18next !== 'undefined' && i18next.on) {
            i18next.on('languageChanged', renderShortcutList);
        }
    }

    function initPaletteUi() {
        modalEl = document.getElementById('commandPaletteModal');
        if (!modalEl) {
            return;
        }
        searchInput = document.getElementById('command-palette-search');
        resultsEl = document.getElementById('command-palette-results');
        emptyEl = document.getElementById('command-palette-empty');
        var toggleBtn = document.getElementById('command-palette-toggle');

        renderShortcutList();

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                openModal('search');
            });
        }

        var navbarTrigger = document.getElementById('navbar-command-palette-trigger');
        if (navbarTrigger) {
            navbarTrigger.addEventListener('click', function (event) {
                event.preventDefault();
                openModal('search');
            });
        }

        document.querySelectorAll('[data-command-palette-mode]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setSearchMode(btn.getAttribute('data-command-palette-mode'));
                if (searchInput) {
                    searchInput.focus();
                }
            });
        });

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                queueSearch(searchInput.value);
            });
        }

        modalEl.addEventListener('hidden.bs.modal', function () {
            if (searchInput) {
                searchInput.value = '';
            }
            renderResults([]);
            if (emptyEl) {
                emptyEl.classList.add('d-none');
            }
        });
    }

    function init() {
        initShortcuts();
        initPaletteUi();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
