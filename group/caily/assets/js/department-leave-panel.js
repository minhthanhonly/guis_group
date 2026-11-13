/**
 * 部署の休暇予定 — /project/* pages only.
 * Lists leave in the next 14 days for members of the department being viewed:
 *  - GUIS 休暇届 (request.list_department_leaves)
 *  - CAILY休暇 (groupware get_dayoff_all_api, same source as the top page)
 * The department is read from the page's Vue root (#app): selectedDepartment, or project.department_id on detail pages.
 */
(function () {
    'use strict';

    var DAYS_WINDOW = 14;
    var POLL_MS = 1000;
    var COLLAPSE_KEY = 'departmentLeavePanelCollapsed';
    var GUIS_API = '/api/index.php?model=request&method=list_department_leaves&days=' + DAYS_WINDOW + '&department_id=';
    var CAILY_DAYOFF_API = 'https://group.caily.com.vn/api/index.php?type=get_dayoff_all_api&debug=1';
    /** CAILY groupware userid -> GUIS userid (same as dayoff-events.js). */
    var CAILY_USERID_ALIASES = { nguyen: 'duynguyen' };

    var I18N = {
        '部署の休暇予定': { vi: 'Lịch nghỉ phép của bộ phận' },
        '14日以内': { vi: 'Trong 14 ngày tới' },
        '休暇予定はありません': { vi: 'Không có ai nghỉ phép' },
        '読み込み中...': { vi: 'Đang tải...' },
        '申請中': { vi: 'Chờ duyệt' },
        '全休': { vi: 'Cả ngày' },
        '午前休': { vi: 'Nghỉ sáng' },
        '午後休': { vi: 'Nghỉ chiều' },
        '展開': { vi: 'Mở rộng' },
        '縮小': { vi: 'Thu nhỏ' }
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

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function pad2(n) { return (n < 10 ? '0' : '') + n; }
    function toYmd(d) { return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); }
    function parseYmd(s) {
        var m = String(s || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
        return m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])) : null;
    }

    function windowRange() {
        var start = new Date();
        start.setHours(0, 0, 0, 0);
        var end = new Date(start);
        end.setDate(end.getDate() + DAYS_WINDOW - 1);
        return { from: toYmd(start), to: toYmd(end) };
    }

    function formatDay(ymd) {
        var d = parseYmd(ymd);
        if (!d) return ymd || '';
        return (d.getMonth() + 1) + '/' + d.getDate() + '(' + '日月火水木金土'.charAt(d.getDay()) + ')';
    }

    function formatPeriod(start, end) {
        if (!end || end === start) return formatDay(start);
        return formatDay(start) + '～' + formatDay(end);
    }

    function isCollapsed() {
        try { return localStorage.getItem(COLLAPSE_KEY) === '1'; } catch (e) { return false; }
    }

    function setCollapsed(v) {
        try { localStorage.setItem(COLLAPSE_KEY, v ? '1' : '0'); } catch (e) {}
    }

    /** Department being viewed, from the page's Vue root. */
    function currentDepartment() {
        var el = document.getElementById('app');
        var root = el && el._vnode && el._vnode.component;
        var proxy = root && root.proxy;
        if (!proxy) return null;
        var dept = proxy.selectedDepartment;
        if (dept && dept.id) {
            return { id: String(dept.id), name: dept.name || '' };
        }
        var project = proxy.project;
        if (project && project.department_id) {
            return { id: String(project.department_id), name: project.department_name || '' };
        }
        return null;
    }

    // ---- CAILY dayoff --------------------------------------------------

    var cailyListPromise = null;

    function getCailyDayoffList() {
        if (!cailyListPromise) {
            cailyListPromise = fetch(CAILY_DAYOFF_API, { credentials: 'include' })
                .then(function (r) { return r.json(); })
                .then(function (data) { return (data && data.success && Array.isArray(data.list)) ? data.list : []; })
                .catch(function () {
                    cailyListPromise = null; // retry on the next department change
                    return [];
                });
        }
        return cailyListPromise;
    }

    function minutesOf(hhmm) {
        var p = String(hhmm || '00:00').split(':');
        return (parseInt(p[0], 10) || 0) * 60 + (parseInt(p[1], 10) || 0);
    }

    /** API times are Vietnam time (GMT+7). Same half-day rule as the schedule page. */
    function cailyPart(item) {
        if (/^true$/i.test(String(item.allday || ''))) return '全休';
        var s = minutesOf(item.time_start);
        var e = minutesOf(item.time_end);
        if (s >= 7 * 60 && e <= 12 * 60) return '午前休';
        if (s >= 12 * 60 && e <= 17 * 60 + 30) return '午後休';
        // Show other ranges in Japan time (+2h).
        function jp(m) { m += 120; return pad2(Math.floor(m / 60) % 24) + ':' + pad2(m % 60); }
        return jp(s) + '-' + jp(e);
    }

    function cailyLeavesFor(members, list, range) {
        var byUserid = {};
        members.forEach(function (m) { byUserid[m.userid] = m.realname || m.userid; });
        var out = [];
        list.forEach(function (item) {
            if (String(item.status) !== '1') return;
            var raw = String(item.userid || '').trim();
            var uid = CAILY_USERID_ALIASES[raw] || raw;
            if (!byUserid[uid]) return;
            var start = String(item.date_start || '').slice(0, 10);
            var end = String(item.date_end || item.date_start || '').slice(0, 10);
            if (!start) return;
            if (end < start) end = start;
            if (end < range.from || start > range.to) return;
            out.push({
                key: 'caily-' + item.id,
                source: 'CAILY',
                userid: uid,
                realname: byUserid[uid],
                start_date: start,
                end_date: end,
                part: cailyPart(item),
                pending: false
            });
        });
        return out;
    }

    // ---- Rendering -----------------------------------------------------

    var slot = null;
    var activeDeptId = null;
    var requestSeq = 0;

    function headerHtml(collapsed, deptName) {
        return '<div class="dlp-header">' +
            '<i class="fas fa-user-clock text-primary dlp-header-icon" aria-hidden="true"></i>' +
            '<span class="dlp-title">' + escapeHtml(t('部署の休暇予定')) +
            ' <span class="dlp-window">(' + escapeHtml(t('14日以内')) + ')</span>' +
            (deptName ? '<span class="dlp-dept" title="' + escapeHtml(deptName) + '">' + escapeHtml(deptName) + '</span>' : '') +
            '</span>' +
            '<button type="button" class="dlp-toggle ms-auto" id="departmentLeavePanelToggle"' +
            ' title="' + escapeHtml(collapsed ? t('展開') : t('縮小')) + '"' +
            ' aria-expanded="' + (collapsed ? 'false' : 'true') + '">' +
            '<i class="fas ' + (collapsed ? 'fa-user-clock' : 'fa-chevron-right') + '" aria-hidden="true"></i>' +
            '</button>' +
            '</div>';
    }

    function itemHtml(l) {
        var badgeClass = l.source === 'CAILY' ? 'bg-success' : 'bg-dark';
        var part = l.part ? '<span class="dlp-part">' + escapeHtml(t(l.part)) + '</span>' : '';
        var pending = l.pending ? '<span class="badge bg-label-warning dlp-pending">' + escapeHtml(t('申請中')) + '</span>' : '';
        return '<div class="dlp-item">' +
            '<span class="dlp-date">' + escapeHtml(formatPeriod(l.start_date, l.end_date)) + '</span>' +
            '<span class="badge ' + badgeClass + ' dlp-src">' + l.source + '</span>' +
            '<span class="dlp-name" title="' + escapeHtml(l.realname) + '">' + escapeHtml(l.realname) + '</span>' +
            part + pending +
            '</div>';
    }

    function render(deptName, leaves, loading) {
        if (!slot) return;
        var collapsed = isCollapsed();
        var body;
        if (loading) {
            body = '<div class="dlp-empty">' + escapeHtml(t('読み込み中...')) + '</div>';
        } else if (!leaves.length) {
            body = '<div class="dlp-empty">' + escapeHtml(t('休暇予定はありません')) + '</div>';
        } else {
            body = leaves.map(itemHtml).join('');
        }
        slot.innerHTML =
            '<div id="department-leave-panel" class="' + (collapsed ? 'is-collapsed' : '') + '" role="status">' +
            headerHtml(collapsed, deptName) +
            '<div class="dlp-body">' + body + '</div>' +
            '</div>';

        var btn = document.getElementById('departmentLeavePanelToggle');
        var panel = document.getElementById('department-leave-panel');
        if (btn && panel) {
            btn.addEventListener('click', function () {
                var next = !panel.classList.contains('is-collapsed');
                setCollapsed(next);
                panel.classList.toggle('is-collapsed', next);
                btn.setAttribute('aria-expanded', next ? 'false' : 'true');
                btn.setAttribute('title', next ? t('展開') : t('縮小'));
                var icon = btn.querySelector('i');
                if (icon) icon.className = 'fas ' + (next ? 'fa-user-clock' : 'fa-chevron-right');
            });
        }
    }

    function load(dept) {
        var seq = ++requestSeq;
        var range = windowRange();
        render(dept.name, [], true);

        var guisPromise = fetch(GUIS_API + encodeURIComponent(dept.id), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .catch(function () { return null; });

        guisPromise.then(function (data) {
            var members = (data && Array.isArray(data.members)) ? data.members : [];
            var guisLeaves = ((data && Array.isArray(data.leaves)) ? data.leaves : []).map(function (l) {
                return {
                    key: 'guis-' + l.id,
                    source: 'GUIS',
                    userid: l.userid,
                    realname: l.realname || l.userid,
                    start_date: l.start_date,
                    end_date: l.end_date,
                    part: l.part || '',
                    pending: l.status === 'pending'
                };
            });
            return getCailyDayoffList().then(function (cailyList) {
                return guisLeaves.concat(cailyLeavesFor(members, cailyList, range));
            });
        }).then(function (leaves) {
            if (seq !== requestSeq) return; // department changed meanwhile
            leaves.sort(function (a, b) {
                return a.start_date.localeCompare(b.start_date) ||
                    a.end_date.localeCompare(b.end_date) ||
                    String(a.realname).localeCompare(String(b.realname));
            });
            render(dept.name, leaves, false);
        });
    }

    function tick() {
        var dept = currentDepartment();
        if (!dept) {
            if (activeDeptId !== null) {
                activeDeptId = null;
                requestSeq++;
                slot.innerHTML = '';
            }
            return;
        }
        if (dept.id !== activeDeptId) {
            activeDeptId = dept.id;
            load(dept);
        }
    }

    function init() {
        slot = document.getElementById('department-leave-panel-slot');
        if (!slot) return;
        tick();
        setInterval(tick, POLL_MS);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
