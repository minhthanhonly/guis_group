(function () {
    'use strict';

    var CAILY_API = 'https://group.caily.com.vn/api/index.php?type=get_upcoming_holidays&debug=1&days=90&limit=10';
    var GUIS_API = '/api/index.php?model=timecard&method=get_holiday_upcoming&days=90&limit=10';

    // i18n helper — fallback to JP if i18next not ready
    var I18N = {
        '祝日一覧':         { vi: 'Danh sách ngày nghỉ' },
        '今後の祝日':       { vi: 'Ngày nghỉ sắp tới' },
        '読み込み中...':    { vi: 'Đang tải...' },
        '祝日はありません': { vi: 'Không có ngày nghỉ' },
        '今週':             { vi: 'Tuần này' },
        '今日':             { vi: 'Hôm nay' },
        '明日':             { vi: 'Ngày mai' },
    };

    function t(key) {
        if (typeof i18next !== 'undefined' && i18next.isInitialized) {
            var tr = i18next.t(key);
            if (tr && tr !== key) return tr;
        }
        var lang = (typeof i18next !== 'undefined' && i18next.language) ? i18next.language : 'ja';
        if (lang.indexOf('vi') === 0 && I18N[key] && I18N[key].vi) return I18N[key].vi;
        return key;
    }

    function injectHTML() {
        var html = [
            '<span class="fab-shortcut-label">F4</span>',
            '<button type="button" id="holidayOffcanvasBtn"',
            ' class="btn btn-danger rounded-circle shadow-lg"',
            ' data-bs-toggle="offcanvas" data-bs-target="#offcanvasHolidays"',
            ' aria-controls="offcanvasHolidays" title="祝日一覧 / Ngày nghỉ sắp tới">',
            '  <i class="fa fa-calendar" style="font-size:0.95rem;pointer-events:none;"></i>',
            '</button>',

            '<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHolidays"',
            ' aria-labelledby="offcanvasHolidaysLabel"',
            ' style="width:360px;z-index:99999;"',
            ' data-bs-backdrop="false" data-bs-scroll="true">',
            '  <div class="offcanvas-header border-bottom">',
            '    <h5 class="offcanvas-title" id="offcanvasHolidaysLabel">',
            '      <i class="fa fa-calendar me-2 text-danger"></i>',
            '      <span data-i18n-ho="祝日一覧">祝日一覧</span>',
            '    </h5>',
            '    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="閉じる"></button>',
            '  </div>',
            '  <div class="offcanvas-body p-0">',
            '    <div class="p-3 text-center text-muted" id="holidayLoadingMsg">',
            '      <span class="spinner-border spinner-border-sm me-2"></span>',
            '      <span data-i18n-ho="読み込み中...">読み込み中...</span>',
            '    </div>',
            '    <div id="holidayGuisSection" class="d-none">',
            '      <div class="px-3 py-2 bg-light border-bottom d-flex align-items-center gap-2">',
            '        <span class="badge bg-dark">GUIS</span>',
            '        <span class="fw-semibold small" data-i18n-ho="今後の祝日">今後の祝日</span>',
            '      </div>',
            '      <ul class="list-group list-group-flush" id="holidayGuisList"></ul>',
            '    </div>',
            '    <div id="holidayCailySection" class="d-none">',
            '      <div class="px-3 py-2 bg-light border-bottom d-flex align-items-center gap-2">',
            '        <span class="badge bg-success">CAILY</span>',
            '        <span class="fw-semibold small" data-i18n-ho="今後の祝日">今後の祝日</span>',
            '      </div>',
            '      <ul class="list-group list-group-flush" id="holidayCailyList"></ul>',
            '    </div>',
            '    <div id="holidayEmptyMsg" class="d-none p-3 text-center text-muted" data-i18n-ho="祝日はありません">祝日はありません</div>',
            '  </div>',
            '</div>'
        ].join('');

        // Inject button vào slot trong fab-bar, offcanvas vào body
        var slot = document.getElementById('holiday-fab-slot');
        var target = slot || document.body;
        var wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        while (wrapper.firstChild) {
            target.appendChild(wrapper.firstChild);
        }
    }

    function applyI18n() {
        document.querySelectorAll('[data-i18n-ho]').forEach(function (el) {
            el.textContent = t(el.getAttribute('data-i18n-ho'));
        });
    }

    // Kiểm tra ngày có phải hôm nay không
    function isToday(dateStr) {
        if (!dateStr) return false;
        var today = new Date();
        var d = new Date(dateStr);
        return d.getFullYear() === today.getFullYear() &&
               d.getMonth() === today.getMonth() &&
               d.getDate() === today.getDate();
    }

    function isTomorrow(dateStr) {
        if (!dateStr) return false;
        var tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        var d = new Date(dateStr);
        return d.getFullYear() === tomorrow.getFullYear() &&
               d.getMonth() === tomorrow.getMonth() &&
               d.getDate() === tomorrow.getDate();
    }

    // Kiểm tra ngày có nằm trong tuần hiện tại (Thứ 2 → CN)
    function isThisWeek(dateStr) {
        if (!dateStr) return false;
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var day = today.getDay(); // 0=CN
        var monday = new Date(today);
        monday.setDate(today.getDate() - (day === 0 ? 6 : day - 1));
        var sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);
        var d = new Date(dateStr);
        return d >= monday && d <= sunday;
    }

    function renderList(ulId, sectionId, list) {
        var ul = document.getElementById(ulId);
        var section = document.getElementById(sectionId);
        if (!ul || !section || !list || list.length === 0) return;
        list.forEach(function (h) {
            var name = h.name_ja || h.name || '';
            var dateStr = h.date || '';
            var badges = '';
            if (isToday(dateStr)) {
                badges += '<span class="badge bg-danger ms-1" style="font-size:0.7em">' + t('今日') + '</span>';
            } else if (isTomorrow(dateStr)) {
                badges += '<span class="badge bg-primary ms-1" style="font-size:0.7em">' + t('明日') + '</span>';
            } else if (isThisWeek(dateStr)) {
                badges += '<span class="badge bg-warning text-dark ms-1" style="font-size:0.7em">' + t('今週') + '</span>';
            }
            var li = document.createElement('li');
            li.className = 'list-group-item d-flex align-items-center py-2 px-3';
            li.innerHTML =
                '<span class="text-muted small me-3" style="min-width:60px">' + dateStr + '</span>' +
                '<span class="me-2 text-nowrap" style="min-width:60px">' + badges + '</span>' +
                '<span class="flex-grow-1 small">' + name + '</span>';
            ul.appendChild(li);
        });
        section.classList.remove('d-none');
    }

    function initOffcanvas() {
        var el = document.getElementById('offcanvasHolidays');
        if (!el) return;

        var loaded = false;

        el.addEventListener('show.bs.offcanvas', function () {
            applyI18n();
            if (loaded) return;
            loaded = true;

            var guisDone = false, cailyDone = false;
            function checkDone() {
                if (!guisDone || !cailyDone) return;
                var loadingEl = document.getElementById('holidayLoadingMsg');
                if (loadingEl) loadingEl.classList.add('d-none');
                var allEmpty =
                    document.getElementById('holidayGuisSection').classList.contains('d-none') &&
                    document.getElementById('holidayCailySection').classList.contains('d-none');
                if (allEmpty) {
                    var emptyEl = document.getElementById('holidayEmptyMsg');
                    if (emptyEl) emptyEl.classList.remove('d-none');
                }
            }

            fetch(GUIS_API)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    renderList('holidayGuisList', 'holidayGuisSection', (data && data.list) ? data.list : []);
                    guisDone = true; checkDone();
                })
                .catch(function () { guisDone = true; checkDone(); });

            fetch(CAILY_API)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    renderList('holidayCailyList', 'holidayCailySection', (data && data.list) ? data.list : []);
                    cailyDone = true; checkDone();
                })
                .catch(function () { cailyDone = true; checkDone(); });
        });

        // ESC to close
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                var instance = typeof bootstrap !== 'undefined' && bootstrap.Offcanvas
                    ? bootstrap.Offcanvas.getInstance(el)
                    : null;
                if (instance) instance.hide();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            injectHTML();
            initOffcanvas();
        });
    } else {
        injectHTML();
        initOffcanvas();
    }
})();
