/**
 * Shared Tagify whitelist helpers for parent project fields.
 * Merges defaults + localStorage custom values + distinct values already saved in DB.
 */
(function(global) {
    var FIELD_CONFIG = {
        application_review_agency: {
            defaults: [
                '日本ERI',
                'ERIソリューション',
                'ビューローベリタスジャパン',
                '日本住宅保証検査機構（JIO）',
                'ハウスプラス住宅保証',
                '東京建築検査機構（TBTC）',
                'ユーディーアイ確認検査',
                '日本確認検査センター',
                'JCI'
            ],
            storageKey: 'parent_project_application_review_agency_custom'
        },
        type2: {
            defaults: ['長屋', '共同', '戸建', '非住宅', '集合'],
            storageKey: 'parent_project_type2_custom'
        },
        scale: {
            defaults: [
                'W2F3J', 'W2F4J', 'W2F5J', 'W2F6J',
                'W3F3J', 'W3F4J', 'W3F5J', 'W3F6J',
                'RC3F3J', 'RC3F4J', 'RC3F5J', 'RC3F6J', 'RC3F7J', 'RC3F8J'
            ],
            storageKey: 'parent_project_scale_custom'
        },
        city: {
            defaults: [],
            storageKey: 'parent_project_city_custom'
        },
        structural_office: {
            defaults: [],
            storageKey: 'parent_project_structural_office_custom'
        }
    };

    var cachedDbOptions = null;
    var cachePromise = null;

    function uniqKeepOrder(arr) {
        var seen = {};
        var out = [];
        (arr || []).forEach(function(v) {
            var s = String(v == null ? '' : v).trim();
            if (!s || seen[s]) return;
            seen[s] = true;
            out.push(s);
        });
        return out;
    }

    function getConfig(field) {
        return FIELD_CONFIG[field] || null;
    }

    function loadLocalCustom(field) {
        var cfg = getConfig(field);
        if (!cfg) return [];
        try {
            var raw = localStorage.getItem(cfg.storageKey);
            if (!raw) return [];
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed.map(String) : [];
        } catch (e) {
            return [];
        }
    }

    function saveLocalCustom(field, value) {
        var cfg = getConfig(field);
        if (!cfg) return;
        var v = String(value == null ? '' : value).trim();
        if (!v) return;
        if (cfg.defaults.indexOf(v) !== -1) return;
        var list = uniqKeepOrder(loadLocalCustom(field).concat([v]));
        try {
            localStorage.setItem(cfg.storageKey, JSON.stringify(list));
        } catch (e) { /* ignore quota */ }
    }

    function buildWhitelist(field, extra) {
        var cfg = getConfig(field);
        if (!cfg) return uniqKeepOrder(extra || []);
        return uniqKeepOrder(cfg.defaults.concat(loadLocalCustom(field)).concat(extra || []));
    }

    async function fetchAllSavedOptions() {
        if (cachedDbOptions) return cachedDbOptions;
        if (cachePromise) return cachePromise;
        cachePromise = axios.get('/api/index.php', {
            params: {
                model: 'parentproject',
                method: 'listTagifyFieldOptions'
            }
        }).then(function(response) {
            var data = response && response.data ? response.data : {};
            if (data.status === 'error') {
                cachedDbOptions = {};
            } else if (data.options && typeof data.options === 'object') {
                cachedDbOptions = data.options;
            } else {
                cachedDbOptions = {};
            }
            return cachedDbOptions;
        }).catch(function() {
            cachedDbOptions = {};
            return cachedDbOptions;
        }).finally(function() {
            cachePromise = null;
        });
        return cachePromise;
    }

    async function loadMergedWhitelist(field) {
        var all = await fetchAllSavedOptions();
        var saved = (all && Array.isArray(all[field])) ? all[field] : [];
        return buildWhitelist(field, saved);
    }

    function bindSaveOnAdd(tagify, field, onUpdate) {
        if (!tagify) return;
        tagify.on('add', function(e) {
            if (typeof onUpdate === 'function') onUpdate();
            var value = e && e.detail && e.detail.data ? e.detail.data.value : '';
            if (!value) return;
            saveLocalCustom(field, value);
            var merged = buildWhitelist(field, tagify.settings.whitelist || []);
            tagify.settings.whitelist = merged.slice();
            tagify.whitelist = merged.slice();
        });
        if (typeof onUpdate === 'function') {
            tagify.on('remove', onUpdate);
        }
    }

    global.ParentProjectTagifyOptions = {
        FIELD_CONFIG: FIELD_CONFIG,
        getDefaults: function(field) {
            var cfg = getConfig(field);
            return cfg ? cfg.defaults.slice() : [];
        },
        buildWhitelist: buildWhitelist,
        saveLocalCustom: saveLocalCustom,
        fetchAllSavedOptions: fetchAllSavedOptions,
        loadMergedWhitelist: loadMergedWhitelist,
        bindSaveOnAdd: bindSaveOnAdd
    };

    // Backward-compatible alias used by 申請提出先の審査機関.
    global.ParentProjectReviewAgency = {
        DEFAULT_WHITELIST: FIELD_CONFIG.application_review_agency.defaults.slice(),
        buildWhitelist: function(extra) {
            return buildWhitelist('application_review_agency', extra);
        },
        saveLocalCustom: function(value) {
            return saveLocalCustom('application_review_agency', value);
        },
        loadMergedWhitelist: function() {
            return loadMergedWhitelist('application_review_agency');
        }
    };
})(window);
