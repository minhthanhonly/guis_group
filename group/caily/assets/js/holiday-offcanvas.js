(function () {
    'use strict';

    var CAILY_API = 'https://group.caily.com.vn/api/index.php?type=get_upcoming_holidays&debug=1&days=60&limit=40';
    var GUIS_API = '/api/index.php?model=timecard&method=get_holiday_upcoming&days=60&limit=40';
    var BANNER_DAYS_WINDOW = 14;
    var BANNER_COLLAPSE_KEY = 'upcomingHolidayBannerCollapsed';

    // i18n helper — fallback to JP if i18next not ready
    var I18N = {
        '祝日一覧':         { vi: 'Danh sách ngày nghỉ' },
        '今後の祝日':       { vi: 'Ngày nghỉ sắp tới' },
        '近日の祝日':       { vi: 'Ngày nghỉ sắp tới' },
        '14日以内':         { vi: 'Trong 14 ngày tới' },
        '読み込み中...':    { vi: 'Đang tải...' },
        '祝日はありません': { vi: 'Không có ngày nghỉ' },
        '今週':             { vi: 'Tuần này' },
        '今日':             { vi: 'Hôm nay' },
        '明日':             { vi: 'Ngày mai' },
        '展開':             { vi: 'Mở rộng' },
        '縮小':             { vi: 'Thu nhỏ' },
        'なし':             { vi: 'Không có' }
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

    function pad2(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function toYmd(dateObj) {
        return dateObj.getFullYear() + '-' + pad2(dateObj.getMonth() + 1) + '-' + pad2(dateObj.getDate());
    }

    function parseYmdLocal(dateStr) {
        if (!dateStr) return null;
        var m = String(dateStr).match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!m) return null;
        return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    }

    function isWeekend(dateObj) {
        var day = dateObj.getDay();
        return day === 0 || day === 6;
    }

    function startOfToday() {
        var d = new Date();
        d.setHours(0, 0, 0, 0);
        return d;
    }

    /** Last day of a window of `count` calendar days starting today (today counts as day 1). */
    function getWindowEnd(count) {
        var d = startOfToday();
        d.setDate(d.getDate() + count - 1);
        return d;
    }

    function weekdayLabel(dateObj) {
        var labels = ['日', '月', '火', '水', '木', '金', '土'];
        return labels[dateObj.getDay()] || '';
    }

    function formatHolidayDate(dateStr) {
        var d = parseYmdLocal(dateStr);
        if (!d) return dateStr || '';
        return (d.getMonth() + 1) + '/' + d.getDate() + '(' + weekdayLabel(d) + ')';
    }

    function holidayName(h) {
        return (h && (h.name_ja || h.name)) ? String(h.name_ja || h.name) : '';
    }

    function filterHolidaysInWindow(list, days) {
        var today = startOfToday();
        var end = getWindowEnd(days || BANNER_DAYS_WINDOW);
        var todayYmd = toYmd(today);
        var endYmd = toYmd(end);
        var out = [];
        (list || []).forEach(function (h) {
            var dateStr = h && h.date ? String(h.date).slice(0, 10) : '';
            if (!dateStr) return;
            var d = parseYmdLocal(dateStr);
            if (!d || isWeekend(d)) return;
            if (dateStr >= todayYmd && dateStr <= endYmd) {
                out.push(h);
            }
        });
        out.sort(function (a, b) {
            return String(a.date).localeCompare(String(b.date));
        });
        return out;
    }

    function isBannerCollapsed() {
        try {
            return localStorage.getItem(BANNER_COLLAPSE_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function setBannerCollapsed(collapsed) {
        try {
            localStorage.setItem(BANNER_COLLAPSE_KEY, collapsed ? '1' : '0');
        } catch (e) {}
    }

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Only companies that have a holiday in the window get a line.
    function companyLineHtml(company, list) {
        if (!list || !list.length) return '';
        var badgeClass = company === 'CAILY' ? 'bg-success' : 'bg-dark';
        var items = list.map(function (h) {
            return '<div class="uhb-item">' +
                '<span class="uhb-date">' + escapeHtml(formatHolidayDate(h.date)) + '</span>' +
                '<span class="uhb-name" title="' + escapeHtml(holidayName(h)) + '">' + escapeHtml(holidayName(h)) + '</span>' +
                '</div>';
        }).join('');
        return '<div class="uhb-company">' +
            '<span class="badge ' + badgeClass + '">' + company + '</span>' +
            '<div class="uhb-items">' + items + '</div>' +
            '</div>';
    }

    function syncToggleUi(banner, btn, collapsed) {
        if (!banner || !btn) return;
        banner.classList.toggle('is-collapsed', collapsed);
        var icon = btn.querySelector('i');
        if (icon) {
            icon.className = 'fa ' + (collapsed ? 'fa-calendar' : 'fa-chevron-right');
        }
        btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        btn.setAttribute('title', collapsed ? t('展開') : t('縮小'));
        var headerIcon = banner.querySelector('.uhb-header > .fa-calendar');
        if (headerIcon) {
            headerIcon.style.display = collapsed ? 'none' : '';
        }
    }

    function renderBanner(guisList, cailyList) {
        var slot = document.getElementById('upcoming-holiday-banner-slot');
        if (!slot) return;

        if ((!guisList || !guisList.length) && (!cailyList || !cailyList.length)) {
            slot.innerHTML = '';
            return;
        }

        var collapsed = isBannerCollapsed();
        var html =
            '<div id="upcoming-holiday-banner"' + (collapsed ? ' class="is-collapsed"' : '') + ' role="status">' +
            '  <div class="uhb-header">' +
            '    <i class="fa fa-calendar text-danger" aria-hidden="true"' + (collapsed ? ' style="display:none"' : '') + '></i>' +
            '    <span class="uhb-title">' +
            escapeHtml(t('近日の祝日')) +
            ' <span class="uhb-window">(' + escapeHtml(t('14日以内')) + ')</span>' +
            '</span>' +
            '    <button type="button" class="uhb-toggle ms-auto" id="upcomingHolidayBannerToggle"' +
            '      title="' + escapeHtml(collapsed ? t('展開') : t('縮小')) + '"' +
            '      aria-expanded="' + (collapsed ? 'false' : 'true') + '">' +
            '      <i class="fa ' + (collapsed ? 'fa-calendar' : 'fa-chevron-right') + '" aria-hidden="true"></i>' +
            '    </button>' +
            '  </div>' +
            '  <div class="uhb-body">' +
            companyLineHtml('GUIS', guisList) +
            companyLineHtml('CAILY', cailyList) +
            '  </div>' +
            '</div>';

        slot.innerHTML = html;

        var btn = document.getElementById('upcomingHolidayBannerToggle');
        var banner = document.getElementById('upcoming-holiday-banner');
        if (btn && banner) {
            btn.addEventListener('click', function () {
                var nextCollapsed = !banner.classList.contains('is-collapsed');
                setBannerCollapsed(nextCollapsed);
                syncToggleUi(banner, btn, nextCollapsed);
            });
        }
    }

    function loadBannerHolidays() {
        if (!document.getElementById('upcoming-holiday-banner-slot')) return;

        var guisList = [];
        var cailyList = [];
        var guisDone = false;
        var cailyDone = false;

        function finish() {
            if (!guisDone || !cailyDone) return;
            renderBanner(
                filterHolidaysInWindow(guisList, BANNER_DAYS_WINDOW),
                filterHolidaysInWindow(cailyList, BANNER_DAYS_WINDOW)
            );
        }

        fetch(GUIS_API)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                guisList = (data && data.list) ? data.list : [];
                guisDone = true;
                finish();
            })
            .catch(function () {
                guisDone = true;
                finish();
            });

        fetch(CAILY_API)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                cailyList = (data && data.list) ? data.list : [];
                cailyDone = true;
                finish();
            })
            .catch(function () {
                cailyDone = true;
                finish();
            });
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
            var node = wrapper.firstChild;
            if (node.nodeType === 1 && node.id === 'offcanvasHolidays') {
                document.body.appendChild(node);
            } else {
                target.appendChild(node);
            }
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
            loadBannerHolidays();
        });
    } else {
        injectHTML();
        initOffcanvas();
        loadBannerHolidays();
    }
})();
