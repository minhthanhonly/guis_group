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

    function normalizeProjectForClipboard(data) {
        if (!data) return {};
        return {
            id: data.id,
            parent_construction_number: data.parent_construction_number || data.building_number || '',
            parent_branch_name: data.parent_branch_name || data.branch_name || '',
            name: data.name || data.building_name || '',
            parent_scale: data.parent_scale || data.building_size || '',
            project_order_type: data.project_order_type || '',
            teams: data.teams || '',
            caily_nouki: data.caily_nouki || '',
            team_list: data.team_list || []
        };
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

    function buildProjectClipboardText(data, teamIdToName) {
        var row = normalizeProjectForClipboard(data);
        if (!row.id) return '';
        var detailUrl = new URL('detail.php', global.location.href);
        detailUrl.searchParams.set('id', row.id);
        return [
            PROJECT_CLIPBOARD_SEPARATOR,
            '工事番号: ' + (row.parent_construction_number || ''),
            '支店名: ' + formatBranchNameForClipboard(row.parent_branch_name),
            'お施主様名: ' + (row.name || ''),
            '規模: ' + (row.parent_scale || ''),
            '受注形態: ' + (row.project_order_type || ''),
            'チーム: ' + formatTeamsForClipboard(row, teamIdToName),
            'CAILY納期: ' + formatProjectClipboardDateTime(row.caily_nouki),
            'URL: ' + detailUrl.href,
            PROJECT_CLIPBOARD_SEPARATOR
        ].join('\n');
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

    global.ProjectClipboard = {
        getBranchRomaji: getBranchRomaji,
        formatBranchNameForClipboard: formatBranchNameForClipboard,
        buildText: buildProjectClipboardText,
        copy: copyTextToClipboard
    };
})(typeof window !== 'undefined' ? window : this);
