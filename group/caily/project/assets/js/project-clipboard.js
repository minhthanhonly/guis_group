(function(global) {
    'use strict';

    var PROJECT_CLIPBOARD_SEPARATOR = '_'.repeat(66);

    var branchRomajiMap = {
        '和歌山': 'WAKAYAMA',
        '青梅': 'AOMEI',
        '足立': 'ADACHI',
        '厚木': 'ASTUGI',
        '尼崎': 'AMAGASAKI',
        '安城': 'ANZOU',
        '池田': 'IKEDA',
        '板橋': 'ITABASHI',
        '市川': 'ICHIKAWA',
        '一宮': 'ICHINOMIYA',
        '宇都宮': 'USTUNOMIYA',
        '江戸川': 'EDOGAWA',
        '大阪': 'OSAKA',
        '大阪りんくう': 'OSAKARINKU',
        '岡崎': 'OKAZAKI',
        '小田原': 'ODAWARA',
        '柏': 'KASHIWA',
        '春日井': 'KASUGAI',
        '春日部': 'KASUKABE',
        '鎌倉': 'KAMAKURA',
        '刈谷': 'KARIYA',
        '川口': 'KAWAGUCHI',
        '川崎': 'KAWASAKI',
        '川崎西': 'KAWASAKINISHI',
        '川崎東': 'KAWASAKIHIGASHI',
        '岐阜東': 'GIFUHIGASHI',
        '京都': 'KYOTO',
        '京都西': 'KYOTONISHI',
        '京都東': 'KYOTOHIGASHI',
        '京都山科': 'KYOTOYAMASHINA',
        '桑名': 'KUWANA',
        '江東': 'KOUTOU',
        '神戸': 'KOBE',
        '国分寺': 'KOKUBUNJI',
        '埼玉南': 'SAITAMAMINAMI',
        '堺': 'SAKAI',
        '相模原': 'SAGAMIHARA',
        '三宮': 'SANNOMIYA',
        '滋賀': 'SHIGA',
        '静岡': 'SHIZUOKA',
        '静岡東': 'SHIZUOKAHIGASI',
        '品川': 'SHINAGAWA',
        '杉並': 'SUGINAMI',
        '墨田': 'SUMIDA',
        '世田谷': 'SETAGAYA',
        '仙台南': 'SENDAIMINAMI',
        '高崎': 'TAKASAKI',
        '多治見': 'TAJIMI',
        '立川': 'TACHIKAWA',
        '多摩': 'TAMA',
        '千葉': 'CHIBA',
        '千葉北': 'CHIBAKITA',
        '千葉南': 'CHIBAMINAMI',
        '東京大田': 'TOKYOOOTA',
        '東京北': 'TOKYOKITA',
        '所沢': 'TOKOROZAWA',
        '富山': 'TOYAMA',
        '豊川': 'TOYOKAWA',
        '豊田': 'TOYOTA',
        '豊橋': 'TOYOHASHI',
        '長崎': 'NAGASAKI',
        '名古屋北': 'NAGOYAKITA',
        '名古屋港': 'NAGOYAMINATO',
        '名古屋天白': 'NAGOYATENPAKU',
        '名古屋西': 'NAGOYANISHI',
        '名古屋東': 'NAGOYAHIGASHI',
        '名古屋南': 'NAGOYAMINAMI',
        '奈良南': 'NARAMINAMI',
        '成田': 'NARITA',
        '新潟': 'NIIGATA',
        '新潟西': 'NIIGATANISHI',
        '練馬': 'NERYMA',
        '練馬西': 'NERIMANISHI',
        '八王子': 'HACHIOJI',
        '八戸': 'HACHINOHE',
        '浜松': 'HAMAMASTU',
        '東大阪': 'HIGASHIOSAKA',
        '東京太田': 'HIGASITOKYOOTA',
        '姫路': 'HIMEJI',
        '枚方': 'HIRAKATA',
        '枚方南': 'HIRAKATAMINAMI',
        '平塚': 'HIRATUKA',
        '福島': 'FUKUSHIMA',
        '福島南': 'FUKUSHIMAMINAMI',
        '富士': 'FUJI',
        '藤沢': 'FUJISAWA',
        '船橋': 'FUNAHASHI',
        '町田': 'MACHIDA',
        '松江': 'MATSUE',
        '松戸': 'MATSUDO',
        '松山': 'MASTUYAMA',
        '三鷹': 'MITAKA',
        '水戸': 'MITO',
        '南大阪': 'MINAMIOSAKA',
        '目黒': 'MEKURO',
        '守谷': 'MORIYA',
        '大和': 'YAMATO',
        '横浜': 'YOKOHAMA',
        '横浜東': 'YOKOHAMAHIGASI',
        '横浜南': 'YOKOHAMAMINAMI',
        '四日市': 'YOKAICHI',
        '流通開発神戸': 'RYUTUKAIHATUKOBE',
        '流通開発静岡': 'RYUTUKAIHATUSHIZUOKA',
        '徳山': 'TOKUYAMA',
        '仙台': 'SENDAI',
        '仙台西': 'SENDAINISHI',
        '仙台東': 'SENDAIHIGASHI',
        '仙台北': 'SENDAIKITA',
        '流通開発大阪': 'RYUTUKAIHATU OSAKA',
        '流通開発東京': 'RYUTUKAIHATU TOKYO',
        '流通開発札幌': 'RYUTUKAIHATU SAPPORO',
        '流通開発仙台': 'RYUTUKAIHATU SENDAI',
    };

    function getBranchRomaji(branchName) {
        return branchRomajiMap[String(branchName || '').trim()] || '';
    }

    function formatBranchNameForClipboard(branchName) {
        var raw = String(branchName || '').trim();
        if (!raw) return '';
        if (/\([A-Za-z0-9\-\s]+\)$/.test(raw)) return raw;
        var romaji = getBranchRomaji(raw);
        return romaji ? (raw + ' ' + romaji) : raw;
    }

    function formatProjectClipboardDateTime(v) {
        if (!v) return '';
        if (typeof moment !== 'undefined') {
            var m = moment(v);
            if (m.isValid()) return m.format('YYYY/MM/DD HH:mm');
        }
        return String(v).trim();
    }

    // API values are entity-encoded; the clipboard gets plain text ("A & B", not "A &amp; B").
    function decodeEntitiesForClipboard(value) {
        var text = String(value == null ? '' : value);
        if (text.indexOf('&') === -1) return text;
        var area = document.createElement('textarea');
        area.innerHTML = text;
        return area.value;
    }

    function normalizeProjectForClipboard(data) {
        if (!data) return {};
        var plain = decodeEntitiesForClipboard;
        return {
            id: data.id,
            parent_construction_number: plain(data.parent_construction_number || data.building_number || ''),
            building_branch_name: plain(data.parent_project_branch_name || ''),
            project_branch_name: plain(data.project_branch_name || data.child_customer_branch || data.branch_name || ''),
            name: plain(data.name || data.building_name || ''),
            parent_scale: plain(data.parent_scale || data.building_size || ''),
            project_order_type: plain(data.project_order_type || ''),
            teams: data.teams || '',
            caily_nouki: data.caily_nouki || '',
            department_name: plain(data.department_name || ''),
            team_list: data.team_list || []
        };
    }

    function buildBranchClipboardLines(row) {
        var buildingBranch = String(row.building_branch_name || '').trim();
        var projectBranch = String(row.project_branch_name || '').trim();

        if (!buildingBranch && !projectBranch) {
            return ['支店名: '];
        }
        if (!buildingBranch) {
            return ['支店名: ' + formatBranchNameForClipboard(projectBranch)];
        }
        if (!projectBranch || buildingBranch === projectBranch) {
            return ['支店名: ' + formatBranchNameForClipboard(buildingBranch)];
        }
        return [
            '建物支店名: ' + formatBranchNameForClipboard(buildingBranch),
            '案件支店名: ' + formatBranchNameForClipboard(projectBranch)
        ];
    }

    function formatTeamsForClipboard(data, teamIdToName) {
        if (Array.isArray(data.team_list) && data.team_list.length) {
            return data.team_list.map(function(t) { return t.name || ''; }).filter(Boolean).join(', ');
        }
        var teamsData = data.teams;
        if (!teamsData || String(teamsData).trim() === '') return '';
        var ids = String(teamsData).split(',').map(function(s) { return s.trim(); }).filter(Boolean);
        var map = teamIdToName || {};
        return ids.map(function(id) { return map[id] || id; }).join(', ');
    }

    /** 省エネ projects copy a shorter block with the 計算書作成 task 期限 instead of CAILY納期 */
    var ENERGY_TASK_TITLE = '計算書作成';

    function isEnergyClipboardProject(row) {
        if (global.EnergyFeePayment && global.EnergyFeePayment.isEnergyProject) {
            return !!global.EnergyFeePayment.isEnergyProject(row);
        }
        return String(row.department_name || '').indexOf('省エネ') !== -1;
    }

    function projectDetailUrl(id) {
        var url = new URL('detail.php', global.location.href);
        url.searchParams.set('id', id);
        return url.href;
    }

    function energyTaskUrl(projectId, taskId) {
        var url = new URL('task.php', global.location.href);
        url.searchParams.set('project_id', projectId);
        url.searchParams.set('task_id', taskId);
        return url.href;
    }

    /**
     * Lines of the 省エネ block. Each line is {text} or, for the task line, {text, link, linkText}
     * so the rich copy can make 計算書作成 a link to the task.
     */
    function buildEnergyClipboardLines(row, energyTask) {
        var lines = [
            { text: PROJECT_CLIPBOARD_SEPARATOR },
            { text: 'ID: ' + row.id }
        ];
        buildBranchClipboardLines(row).forEach(function (t) { lines.push({ text: t }); });
        lines.push(
            { text: 'お施主様名: ' + (row.name || '') },
            { text: '規模: ' + (row.parent_scale || '') }
        );
        var due = energyTask ? formatProjectClipboardDateTime(energyTask.due_date) : '';
        lines.push({
            text: ENERGY_TASK_TITLE + '納期: ' + due,
            link: energyTask && energyTask.id ? energyTaskUrl(row.id, energyTask.id) : '',
            linkText: ENERGY_TASK_TITLE,
            rest: '納期: ' + due
        });
        lines.push(
            { text: 'URL: ' + projectDetailUrl(row.id) },
            { text: PROJECT_CLIPBOARD_SEPARATOR }
        );
        return lines;
    }

    function buildProjectClipboardText(data, teamIdToName, energyTask) {
        var row = normalizeProjectForClipboard(data);
        if (!row.id) return '';
        if (isEnergyClipboardProject(row)) {
            return buildEnergyClipboardLines(row, energyTask).map(function (l) { return l.text; }).join('\n') + '\n';
        }
        var lines = [
            PROJECT_CLIPBOARD_SEPARATOR,
            'ID: ' + row.id,
            '工事番号: ' + (row.parent_construction_number || '')
        ];
        lines.push.apply(lines, buildBranchClipboardLines(row));
        lines.push(
            'お施主様名: ' + (row.name || ''),
            '規模: ' + (row.parent_scale || ''),
            '受注形態: ' + (row.project_order_type || ''),
            'チーム: ' + formatTeamsForClipboard(row, teamIdToName),
            'CAILY納期: ' + formatProjectClipboardDateTime(row.caily_nouki),
            'URL: ' + projectDetailUrl(row.id),
            PROJECT_CLIPBOARD_SEPARATOR
        );
        return lines.join('\n') + '\n';
    }

    async function copyTextToClipboard(text) {
        if (global.navigator && global.navigator.clipboard && global.navigator.clipboard.writeText) {
            await global.navigator.clipboard.writeText(text);
            return true;
        }
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            return document.execCommand('copy');
        } finally {
            document.body.removeChild(ta);
        }
    }

    function escapeHtml(t) {
        return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /** The 計算書作成 task of the project ({id, due_date}) or null */
    async function fetchEnergyTask(projectId) {
        if (!global.axios || !projectId) return null;
        try {
            var res = await global.axios.get('/api/index.php?model=task&method=list&include_subtasks=1&project_id=' + encodeURIComponent(projectId));
            var list = Array.isArray(res.data) ? res.data : (res.data && Array.isArray(res.data.data) ? res.data.data : []);
            for (var i = 0; i < list.length; i++) {
                if (String(list[i].title || '').trim() === ENERGY_TASK_TITLE) {
                    return { id: list[i].id, due_date: list[i].due_date || '' };
                }
            }
        } catch (e) { /* copy without the 期限 */ }
        return null;
    }

    /**
     * 案件情報をコピー: builds and copies the block. 省エネ projects look up the 計算書作成 task and
     * also put an HTML version on the clipboard where 計算書作成 links to the task.
     */
    async function copyProjectInfo(data, teamIdToName) {
        var row = normalizeProjectForClipboard(data);
        if (!row.id) return false;
        if (!isEnergyClipboardProject(row)) {
            return copyTextToClipboard(buildProjectClipboardText(data, teamIdToName));
        }
        var task = await fetchEnergyTask(row.id);
        var lines = buildEnergyClipboardLines(row, task);
        var text = lines.map(function (l) { return l.text; }).join('\n') + '\n';
        var html = lines.map(function (l) {
            if (l.link) {
                return '<a href="' + escapeHtml(l.link) + '">' + escapeHtml(l.linkText) + '</a>' + escapeHtml(l.rest);
            }
            return escapeHtml(l.text);
        }).join('<br>');
        if (global.ClipboardItem && global.navigator && global.navigator.clipboard && global.navigator.clipboard.write) {
            try {
                await global.navigator.clipboard.write([new global.ClipboardItem({
                    'text/plain': new Blob([text], { type: 'text/plain' }),
                    'text/html': new Blob([html], { type: 'text/html' })
                })]);
                return true;
            } catch (e) { /* fall back to plain text */ }
        }
        return copyTextToClipboard(text);
    }

    global.ProjectClipboard = {
        copyProject: copyProjectInfo,
        getBranchRomaji: getBranchRomaji,
        formatBranchNameForClipboard: formatBranchNameForClipboard,
        buildText: buildProjectClipboardText,
        copy: copyTextToClipboard
    };
})(typeof window !== 'undefined' ? window : this);
