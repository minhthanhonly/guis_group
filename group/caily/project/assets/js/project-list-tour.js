/**
 * Shepherd Tour for Project List (案件一覧)
 * - JP / VI copy based on i18next language
 * - Auto-start once (localStorage)
 * - Reopen via #projectListTourBtn
 */
(function () {
    'use strict';

    // Bump version when steps change so existing users see the updated tour once.
    var STORAGE_KEY = 'caily_project_list_tour_seen_v3';
    var activeTour = null;
    var suppressSeenMark = false;

    var COPY = {
        ja: {
            btnTitle: 'UIガイドを開始',
            back: '戻る',
            next: '次へ',
            done: '完了',
            skip: 'スキップ',
            deptTitle: '部署ナビゲーション',
            deptText: 'ここから部署を切り替えて案件一覧を表示します。選択中の部署は青色でハイライトされます。',
            filterTitle: '高度なフィルター',
            filterText: '優先度・進捗・チーム・キーワードなど、条件を組み合わせて案件を絞り込めます。不要なときはこのパネルを折りたたみできます。',
            resetTitle: 'フィルターをリセット',
            resetText: '「リセット」を押すと、現在の絞り込み条件をまとめてクリアできます。',
            keepOnResetTitle: 'リセット時に保持するフィルター',
            keepOnResetText: '「フィルター設定」で、リセット後も残したい項目を選べます。チーム・会社・案件状況などに対応しています（並べ替えは常に保持されます）。',
            columnsTitle: '列の表示',
            columnsText: 'テーブルに表示する列をオン／オフできます。よく使う列だけを残して見やすくしましょう。Excel出力にもこの表示列が反映されます。',
            customizeTitle: '列設定（テーブルのカスタマイズ）',
            customizeText: '列の表示・幅・順序を初期値に戻したり、設定のエクスポート／インポートができます。',
            colResizeTitle: '列幅の調整',
            colResizeText: '列見出しの右端をドラッグすると、列の横幅を自由に変更できます。',
            colReorderTitle: '列の並び替え',
            colReorderText: '列見出し（タイトル）をドラッグ＆ドロップすると、列の表示位置を入れ替えられます。',
            spaceScrollTitle: '表の横スクロール',
            spaceScrollText: 'Spaceキーを押しながら表の上でマウスをドラッグすると、表を左右にスクロールできます。',
            excelTitle: 'Excel出力（レポート作成）',
            excelText: 'フィルターで案件を絞り、「列の表示」で必要な列だけ残してから「Excel出力」を押すと、そのままレポート用のExcelを作れます。',
            excelTip: 'ヒント: 列設定のインポート／エクスポートを使うと、よく使う表レイアウトを素早く切り替えられます。'
        },
        vi: {
            btnTitle: 'Bắt đầu hướng dẫn UI',
            back: 'Quay lại',
            next: 'Tiếp',
            done: 'Xong',
            skip: 'Bỏ qua',
            deptTitle: 'Thanh chọn phòng ban',
            deptText: 'Tại đây bạn chuyển phòng ban để xem danh sách dự án. Phòng ban đang chọn được tô màu xanh.',
            filterTitle: 'Bộ lọc nâng cao',
            filterText: 'Lọc dự án theo độ ưu tiên, tiến độ, team, từ khóa… Có thể thu gọn panel khi không cần.',
            resetTitle: 'Nút Reset bộ lọc',
            resetText: 'Nhấn «Reset» để xóa toàn bộ điều kiện lọc hiện tại trong một lần.',
            keepOnResetTitle: 'Giữ filter khi Reset',
            keepOnResetText: 'Mở「フィルター設定」để chọn filter nào được giữ sau khi Reset — team, công ty, trạng thái, v.v. (sắp xếp luôn được giữ).',
            columnsTitle: 'Hiển thị cột',
            columnsText: 'Bật/tắt các cột trên bảng. Chỉ giữ cột cần dùng cho dễ theo dõi. Các cột đang hiển thị cũng được dùng khi xuất Excel.',
            customizeTitle: 'Tùy chỉnh bảng (cài đặt cột)',
            customizeText: 'Đặt lại hiển thị / độ rộng / thứ tự cột về mặc định, hoặc xuất / nhập cấu hình cột.',
            colResizeTitle: 'Chỉnh chiều rộng cột',
            colResizeText: 'Kéo cạnh phải của tiêu đề cột để thay đổi chiều ngang cột theo ý muốn.',
            colReorderTitle: 'Đổi vị trí cột',
            colReorderText: 'Kéo thả tiêu đề bảng để thay đổi vị trí / thứ tự cột.',
            spaceScrollTitle: 'Cuộn ngang bảng',
            spaceScrollText: 'Giữ phím Space + kéo chuột trên bảng để scroll ngang nhanh.',
            excelTitle: 'Xuất Excel (báo cáo)',
            excelText: 'Hãy lọc dự án bằng bộ lọc, chỉnh «Hiển thị cột» chỉ còn các cột cần báo cáo, rồi nhấn «Xuất Excel» để tạo file báo cáo.',
            excelTip: 'Tips: Dùng kết hợp Import / Export cấu hình cột để điều chỉnh bảng nhanh theo từng loại báo cáo.'
        }
    };

    function isVietnamese() {
        return typeof i18next !== 'undefined'
            && i18next.isInitialized
            && String(i18next.language || '').indexOf('vi') === 0;
    }

    function t(key) {
        var pack = isVietnamese() ? COPY.vi : COPY.ja;
        return pack[key] || COPY.ja[key] || key;
    }

    function markTourSeen() {
        if (suppressSeenMark) return;
        try {
            localStorage.setItem(STORAGE_KEY, '1');
        } catch (e) { /* ignore */ }
    }

    function hasSeenTour() {
        try {
            return localStorage.getItem(STORAGE_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function updateTourButtonTitle() {
        var btn = document.getElementById('projectListTourBtn');
        if (!btn) return;
        var title = t('btnTitle');
        btn.setAttribute('title', title);
        btn.setAttribute('aria-label', title);
    }

    function ensureFilterPanelOpen() {
        var box = document.getElementById('projectFilterBox');
        if (!box) return;
        if (box.classList.contains('show')) return;
        if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
            var instance = bootstrap.Collapse.getOrCreateInstance(box, { toggle: false });
            instance.show();
        } else {
            box.classList.add('show');
        }
    }

    function elementVisible(el) {
        if (!el) return false;
        var style = window.getComputedStyle(el);
        if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
            return false;
        }
        var rect = el.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
    }

    function resolveAttachElement(selectors) {
        var list = Array.isArray(selectors) ? selectors : [selectors];
        for (var i = 0; i < list.length; i++) {
            var el = typeof list[i] === 'string' ? document.querySelector(list[i]) : list[i];
            if (el && elementVisible(el)) return el;
        }
        for (var j = 0; j < list.length; j++) {
            var fallback = typeof list[j] === 'string' ? document.querySelector(list[j]) : list[j];
            if (fallback) return fallback;
        }
        return null;
    }

    function waitForReady(timeoutMs) {
        var deadline = Date.now() + (timeoutMs || 8000);
        return new Promise(function (resolve) {
            function check() {
                var deptReady = !!document.querySelector('#projectDepartmentNavList .nav-item:not(.d-none)');
                var filterReady = !!document.getElementById('projectFilterTourTarget');
                var columnsReady = !!document.getElementById('columnVisibilityDropdown')
                    || !!document.getElementById('projectListColumnToolsRow');
                var customizeReady = !!document.getElementById('projectListColumnResetDropdown')
                    || !!document.getElementById('projectListColumnResetMount');
                var appReady = !!(window.app && window.app.selectedDepartment && window.app.selectedDepartment.id);

                if ((deptReady && filterReady && (columnsReady || customizeReady) && appReady) || Date.now() >= deadline) {
                    resolve();
                    return;
                }
                setTimeout(check, 250);
            }
            check();
        });
    }

    function buildButtons(tour, opts) {
        var buttons = [];
        if (opts.showBack) {
            buttons.push({
                text: t('back'),
                classes: 'btn btn-sm btn-label-secondary',
                action: tour.back
            });
        }
        if (opts.showSkip) {
            buttons.push({
                text: t('skip'),
                classes: 'btn btn-sm btn-label-secondary',
                action: tour.cancel
            });
        }
        buttons.push({
            text: opts.isLast ? t('done') : t('next'),
            classes: 'btn btn-sm btn-primary',
            action: opts.isLast ? tour.complete : tour.next
        });
        return buttons;
    }

    function stepHtml(text, tip) {
        var html = '<p class="mb-0">' + text + '</p>';
        if (tip) {
            html += '<p class="mb-0 mt-2 small text-muted"><i class="fa fa-lightbulb me-1 text-warning"></i>' + tip + '</p>';
        }
        return html;
    }

    function createTour() {
        if (typeof Shepherd === 'undefined') {
            console.warn('Shepherd.js is not loaded');
            return null;
        }

        ensureFilterPanelOpen();

        var tour = new Shepherd.Tour({
            useModalOverlay: true,
            defaultStepOptions: {
                cancelIcon: { enabled: true },
                canClickTarget: false,
                scrollTo: { behavior: 'smooth', block: 'center' },
                classes: 'shadow-sm',
                modalOverlayOpeningPadding: 8,
                modalOverlayOpeningRadius: 8
            }
        });

        var steps = [
            {
                id: 'department-nav',
                title: t('deptTitle'),
                text: t('deptText'),
                attachSelectors: ['#projectDepartmentNavList', '#projectDepartmentNav'],
                attachOn: 'bottom',
                showSkip: true,
                showBack: false,
                needsFilterOpen: false
            },
            {
                id: 'filter',
                title: t('filterTitle'),
                text: t('filterText'),
                attachSelectors: ['#projectFilterTourTarget', '#projectFilterBox', '#projectFilterToggleBtn'],
                attachOn: 'bottom',
                showSkip: true,
                showBack: true,
                needsFilterOpen: true
            },
            {
                id: 'filter-reset',
                title: t('resetTitle'),
                text: t('resetText'),
                attachSelectors: ['#filterReset'],
                attachOn: 'top',
                showSkip: true,
                showBack: true,
                needsFilterOpen: true
            },
            {
                id: 'filter-keep-on-reset',
                title: t('keepOnResetTitle'),
                text: t('keepOnResetText'),
                attachSelectors: ['#projectFilterResetPrefsBtn', '#offcanvasProjectFilterResetPrefs', '#projectFilterKeepOnResetTourTarget'],
                attachOn: 'top',
                showSkip: true,
                showBack: true,
                needsFilterOpen: true
            },
            {
                id: 'column-visibility',
                title: t('columnsTitle'),
                text: t('columnsText'),
                attachSelectors: [
                    '#projectColumnVisibilityTourTarget',
                    '#columnVisibilityDropdown',
                    '#projectListColumnToolsRow'
                ],
                attachOn: 'left',
                showSkip: true,
                showBack: true,
                needsFilterOpen: false
            },
            {
                id: 'column-customize',
                title: t('customizeTitle'),
                text: t('customizeText'),
                attachSelectors: [
                    '#projectListColumnResetDropdown',
                    '#projectListColumnResetMount',
                    '#projectListColumnToolsRow'
                ],
                attachOn: 'left',
                showSkip: true,
                showBack: true,
                needsFilterOpen: false
            },
            {
                id: 'column-resize',
                title: t('colResizeTitle'),
                text: t('colResizeText'),
                attachSelectors: [
                    '#projectTableTourHintResize',
                    '#projectTableScrollHint',
                    '#projectTable_wrapper .dt-scroll-head thead',
                    '#projectTable thead',
                    '#projectTableCard'
                ],
                attachOn: 'bottom',
                showSkip: true,
                showBack: true,
                needsFilterOpen: false
            },
            {
                id: 'column-reorder',
                title: t('colReorderTitle'),
                text: t('colReorderText'),
                attachSelectors: [
                    '#projectTableTourHintReorder',
                    '#projectTableScrollHint',
                    '#projectTable_wrapper .dt-scroll-head thead',
                    '#projectTable thead',
                    '#projectTableCard'
                ],
                attachOn: 'bottom',
                showSkip: true,
                showBack: true,
                needsFilterOpen: false
            },
            {
                id: 'space-scroll',
                title: t('spaceScrollTitle'),
                text: t('spaceScrollText'),
                attachSelectors: [
                    '#projectTableTourHintSpaceScroll',
                    '#projectTableScrollHint',
                    '#projectTable_wrapper',
                    '#projectTableCard'
                ],
                attachOn: 'bottom',
                showSkip: true,
                showBack: true,
                needsFilterOpen: false
            },
            {
                id: 'excel-export',
                title: t('excelTitle'),
                text: t('excelText'),
                tip: t('excelTip'),
                attachSelectors: ['#projectExportExcelBtn'],
                attachOn: 'top',
                showSkip: false,
                showBack: true,
                isLast: true,
                needsFilterOpen: true
            }
        ];

        steps.forEach(function (stepDef, index) {
            var isLast = !!stepDef.isLast || index === steps.length - 1;
            tour.addStep({
                id: stepDef.id,
                title: stepDef.title,
                text: stepHtml(stepDef.text, stepDef.tip),
                attachTo: {
                    element: function () {
                        return resolveAttachElement(stepDef.attachSelectors);
                    },
                    on: stepDef.attachOn || 'bottom'
                },
                when: {
                    show: function () {
                        if (stepDef.needsFilterOpen) {
                            ensureFilterPanelOpen();
                        }
                    }
                },
                buttons: buildButtons(tour, {
                    showBack: !!stepDef.showBack,
                    showSkip: !!stepDef.showSkip,
                    isLast: isLast
                })
            });
        });

        tour.on('complete', markTourSeen);
        tour.on('cancel', markTourSeen);

        return tour;
    }

    function startTour(options) {
        options = options || {};
        if (activeTour && typeof activeTour.isActive === 'function' && activeTour.isActive()) {
            return;
        }
        if (activeTour && typeof activeTour.cancel === 'function') {
            suppressSeenMark = true;
            try { activeTour.cancel(); } catch (e) { /* ignore */ }
            suppressSeenMark = false;
            activeTour = null;
        }

        waitForReady(options.waitMs || 8000).then(function () {
            updateTourButtonTitle();
            activeTour = createTour();
            if (!activeTour) return;
            activeTour.start();
        });
    }

    function bindTourButton() {
        var btn = document.getElementById('projectListTourBtn');
        if (!btn || btn.__tourBound) return;
        btn.__tourBound = true;
        btn.addEventListener('click', function () {
            startTour({ waitMs: 3000 });
        });
        updateTourButtonTitle();
    }

    function maybeAutoStart() {
        if (hasSeenTour()) return;
        // Wait for departments + table tools after Vue mount
        setTimeout(function () {
            if (hasSeenTour()) return;
            startTour({ waitMs: 10000 });
        }, 1500);
    }

    function init() {
        bindTourButton();
        maybeAutoStart();
        if (typeof i18next !== 'undefined' && i18next.on) {
            i18next.on('languageChanged', updateTourButtonTitle);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.ProjectListTour = {
        start: startTour,
        resetSeen: function () {
            try { localStorage.removeItem(STORAGE_KEY); } catch (e) { /* ignore */ }
        }
    };
})();
