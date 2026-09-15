/**
 * Protected PDF/image viewer with watermark + view tools (no print/copy/download).
 * Expects PDF.js globals when rendering PDF: pdfjsLib
 */
(function (window) {
    'use strict';

    var ZOOM_MIN = 0.5;
    var ZOOM_MAX = 3;
    var ZOOM_STEP = 0.25;
    var DEFAULT_SCALE = 1.25;

    function pad2(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function formatStamp(date) {
        var d = date instanceof Date ? date : new Date();
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate())
            + ' ' + pad2(d.getHours()) + ':' + pad2(d.getMinutes());
    }

    function buildWatermarkText(opts) {
        opts = opts || {};
        var name = String(opts.realname || opts.userid || 'user').trim();
        var userid = String(opts.userid || '').trim();
        var when = opts.viewedAt || formatStamp(new Date());
        var parts = [name];
        if (userid && userid !== name) parts.push(userid);
        parts.push(when);
        return parts.join(' / ');
    }

    function createWatermarkLayer(container, text) {
        var existing = container.querySelector(':scope > .caily-protect-wm');
        if (existing) existing.remove();

        var layer = document.createElement('div');
        layer.className = 'caily-protect-wm';
        layer.setAttribute('aria-hidden', 'true');
        container.appendChild(layer);

        function layout() {
            var w = container.clientWidth || container.offsetWidth || 0;
            var h = container.clientHeight || container.offsetHeight || 0;
            if (w < 20 || h < 20) {
                requestAnimationFrame(layout);
                return;
            }

            // ~3 across, rows from aspect — even spacing, not crowded
            var cols = 3;
            var rows = Math.max(3, Math.min(5, Math.round((h / w) * cols) + 1));
            layer.innerHTML = '';
            layer.style.display = 'grid';
            layer.style.gridTemplateColumns = 'repeat(' + cols + ', 1fr)';
            layer.style.gridTemplateRows = 'repeat(' + rows + ', 1fr)';
            layer.style.alignItems = 'center';
            layer.style.justifyItems = 'center';
            layer.style.backgroundImage = '';
            layer.style.backgroundSize = '';
            layer.style.backgroundPosition = '';

            var total = cols * rows;
            for (var i = 0; i < total; i++) {
                var span = document.createElement('span');
                span.className = 'caily-protect-wm-line';
                span.textContent = text;
                layer.appendChild(span);
            }
        }

        layout();
        return layer;
    }

    function bindHardening(container) {
        container.addEventListener('contextmenu', function (e) {
            e.preventDefault();
        });
        container.addEventListener('dragstart', function (e) {
            e.preventDefault();
        });
        container.addEventListener('copy', function (e) {
            e.preventDefault();
        });
        container.addEventListener('cut', function (e) {
            e.preventDefault();
        });
        container.addEventListener('keydown', function (e) {
            var key = (e.key || '').toLowerCase();
            if ((e.ctrlKey || e.metaKey) && (key === 'c' || key === 'x' || key === 'p' || key === 's' || key === 'a')) {
                e.preventDefault();
                e.stopPropagation();
            }
            if (key === 'printscreen') {
                e.preventDefault();
            }
        });
    }

    function createToolbar(isProtected) {
        var bar = document.createElement('div');
        bar.className = 'caily-protect-toolbar';
        bar.innerHTML = ''
            + '<div class="caily-protect-tb-group">'
            +   '<button type="button" class="caily-protect-btn" data-act="prev" title="前のページ">◀</button>'
            +   '<label class="caily-protect-pagebox">'
            +     '<input type="number" class="caily-protect-pageinput" data-act="page" min="1" value="1" />'
            +     '<span class="caily-protect-pageof">/ <span data-role="page-count">1</span></span>'
            +   '</label>'
            +   '<button type="button" class="caily-protect-btn" data-act="next" title="次のページ">▶</button>'
            + '</div>'
            + '<div class="caily-protect-tb-group">'
            +   '<button type="button" class="caily-protect-btn" data-act="zoom-out" title="縮小">−</button>'
            +   '<select class="caily-protect-zoomselect" data-act="zoom-select" title="ズーム">'
            +     '<option value="0.5">50%</option>'
            +     '<option value="0.75">75%</option>'
            +     '<option value="1">100%</option>'
            +     '<option value="1.25" selected>125%</option>'
            +     '<option value="1.5">150%</option>'
            +     '<option value="2">200%</option>'
            +     '<option value="fit-width">幅に合わせる</option>'
            +     '<option value="fit-page">ページに合わせる</option>'
            +   '</select>'
            +   '<button type="button" class="caily-protect-btn" data-act="zoom-in" title="拡大">＋</button>'
            + '</div>'
            + '<div class="caily-protect-tb-group">'
            +   '<button type="button" class="caily-protect-btn" data-act="fit-width" title="幅に合わせる">⇔</button>'
            +   '<button type="button" class="caily-protect-btn" data-act="fit-page" title="ページに合わせる">▣</button>'
            +   '<button type="button" class="caily-protect-btn" data-act="rotate" title="右に回転">↻</button>'
            + '</div>'
            + (isProtected
                ? '<div class="caily-protect-tb-note">閲覧のみ（印刷・コピー・ダウンロード不可）</div>'
                : '');
        return bar;
    }

    function clampZoom(scale) {
        if (scale < ZOOM_MIN) return ZOOM_MIN;
        if (scale > ZOOM_MAX) return ZOOM_MAX;
        return Math.round(scale * 100) / 100;
    }

    /**
     * @param {object} options
     * @param {HTMLElement} options.container
     * @param {string} options.url - authenticated stream URL (inline)
     * @param {boolean} options.isPdf
     * @param {boolean} options.isImage
     * @param {string} options.realname
     * @param {string} options.userid
     * @param {string} [options.alt]
     * @param {boolean} [options.isProtected] - watermark + copy/print lock only when true
     */
    function mountProtectedViewer(options) {
        var container = options.container;
        if (!container) return Promise.resolve();
        container.innerHTML = '';
        container.classList.add('caily-protect-viewer');

        var isProtected = !!options.isProtected;
        if (isProtected) {
            bindHardening(container);
        }

        var wmText = isProtected ? buildWatermarkText(options) : '';
        var stage = document.createElement('div');
        stage.className = 'caily-protect-stage';
        container.appendChild(stage);

        if (options.isImage) {
            var img = document.createElement('img');
            img.className = 'caily-protect-img';
            img.alt = options.alt || '';
            img.draggable = false;
            img.src = options.url;
            stage.appendChild(img);
            if (isProtected) createWatermarkLayer(stage, wmText);
            return Promise.resolve();
        }

        if (!options.isPdf) {
            stage.innerHTML = '<div class="text-muted p-4 text-center">プレビューは利用できません</div>';
            if (isProtected) createWatermarkLayer(stage, wmText);
            return Promise.resolve();
        }

        if (typeof pdfjsLib === 'undefined') {
            var iframe = document.createElement('iframe');
            iframe.className = 'caily-protect-iframe';
            iframe.src = options.url;
            stage.appendChild(iframe);
            if (isProtected) createWatermarkLayer(stage, wmText);
            return Promise.resolve();
        }

        var toolbar = createToolbar(isProtected);
        container.insertBefore(toolbar, stage);

        var pagesWrap = document.createElement('div');
        pagesWrap.className = 'caily-protect-pages';
        stage.insertBefore(pagesWrap, stage.firstChild);

        var pdfjsVer = options.pdfjsVersion || '3.11.174';
        var pdfjsBase = options.pdfjsBase
            || ('https://cdn.jsdelivr.net/npm/pdfjs-dist@' + pdfjsVer + '/');
        pdfjsLib.GlobalWorkerOptions.workerSrc = options.workerSrc
            || (pdfjsBase + 'build/pdf.worker.min.js');

        var state = {
            pdf: null,
            scale: DEFAULT_SCALE,
            rotation: 0,
            currentPage: 1,
            rendering: false,
            fitMode: null // 'width' | 'page' | null
        };

        var pageInput = toolbar.querySelector('[data-act="page"]');
        var pageCountEl = toolbar.querySelector('[data-role="page-count"]');
        var zoomSelect = toolbar.querySelector('[data-act="zoom-select"]');

        function syncZoomSelect() {
            if (!zoomSelect) return;
            if (state.fitMode === 'width') {
                zoomSelect.value = 'fit-width';
                return;
            }
            if (state.fitMode === 'page') {
                zoomSelect.value = 'fit-page';
                return;
            }
            var s = String(state.scale);
            var found = false;
            for (var i = 0; i < zoomSelect.options.length; i++) {
                if (zoomSelect.options[i].value === s) {
                    zoomSelect.selectedIndex = i;
                    found = true;
                    break;
                }
            }
            if (!found) {
                // show custom % as temporary label via title
                zoomSelect.title = Math.round(state.scale * 100) + '%';
            }
        }

        function syncPageUi() {
            if (pageInput) pageInput.value = String(state.currentPage);
            if (pageCountEl && state.pdf) pageCountEl.textContent = String(state.pdf.numPages);
        }

        function computeFitScale(mode) {
            if (!state.pdf) return DEFAULT_SCALE;
            return state.pdf.getPage(state.currentPage || 1).then(function (page) {
                var base = page.getViewport({ scale: 1, rotation: state.rotation });
                var availW = Math.max(120, stage.clientWidth - 32);
                var availH = Math.max(120, stage.clientHeight - 24);
                if (mode === 'page') {
                    return clampZoom(Math.min(availW / base.width, availH / base.height));
                }
                return clampZoom(availW / base.width);
            });
        }

        function renderAll() {
            if (!state.pdf || state.rendering) return Promise.resolve();
            state.rendering = true;
            pagesWrap.innerHTML = '';

            var chain = Promise.resolve();
            for (var pageNum = 1; pageNum <= state.pdf.numPages; pageNum++) {
                (function (n) {
                    chain = chain.then(function () {
                        return state.pdf.getPage(n).then(function (page) {
                            var viewport = page.getViewport({
                                scale: state.scale,
                                rotation: state.rotation
                            });
                            var wrap = document.createElement('div');
                            wrap.className = 'caily-protect-pagewrap';
                            wrap.setAttribute('data-page', String(n));
                            var canvas = document.createElement('canvas');
                            canvas.className = 'caily-protect-page';
                            canvas.width = viewport.width;
                            canvas.height = viewport.height;
                            wrap.appendChild(canvas);
                            if (isProtected) createWatermarkLayer(wrap, wmText);
                            pagesWrap.appendChild(wrap);
                            return page.render({
                                canvasContext: canvas.getContext('2d'),
                                viewport: viewport
                            }).promise;
                        });
                    });
                })(pageNum);
            }
            return chain.then(function () {
                state.rendering = false;
                syncPageUi();
                syncZoomSelect();
                scrollToPage(state.currentPage, true);
            }).catch(function (err) {
                state.rendering = false;
                console.error('PDF render failed', err);
                pagesWrap.innerHTML = '<div class="alert alert-warning m-2">PDFの表示に失敗しました。</div>';
            });
        }

        function scrollToPage(num, instant) {
            var el = pagesWrap.querySelector('[data-page="' + num + '"]');
            if (!el) return;
            el.scrollIntoView({
                behavior: instant ? 'auto' : 'smooth',
                block: 'start'
            });
        }

        function goPage(num) {
            if (!state.pdf) return;
            num = parseInt(num, 10);
            if (isNaN(num) || num < 1) num = 1;
            if (num > state.pdf.numPages) num = state.pdf.numPages;
            state.currentPage = num;
            syncPageUi();
            scrollToPage(num, false);
        }

        function setScale(scale, fitMode) {
            state.fitMode = fitMode || null;
            state.scale = clampZoom(scale);
            return renderAll();
        }

        function applyFit(mode) {
            return computeFitScale(mode).then(function (scale) {
                return setScale(scale, mode);
            });
        }

        toolbar.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-act]');
            if (!btn || btn.tagName === 'SELECT' || btn.tagName === 'INPUT') return;
            var act = btn.getAttribute('data-act');
            if (act === 'prev') goPage(state.currentPage - 1);
            else if (act === 'next') goPage(state.currentPage + 1);
            else if (act === 'zoom-in') setScale(state.scale + ZOOM_STEP, null);
            else if (act === 'zoom-out') setScale(state.scale - ZOOM_STEP, null);
            else if (act === 'fit-width') applyFit('width');
            else if (act === 'fit-page') applyFit('page');
            else if (act === 'rotate') {
                state.rotation = (state.rotation + 90) % 360;
                if (state.fitMode) applyFit(state.fitMode);
                else renderAll();
            }
        });

        if (zoomSelect) {
            zoomSelect.addEventListener('change', function () {
                var v = zoomSelect.value;
                if (v === 'fit-width') applyFit('width');
                else if (v === 'fit-page') applyFit('page');
                else setScale(parseFloat(v), null);
            });
        }

        if (pageInput) {
            pageInput.addEventListener('change', function () {
                goPage(pageInput.value);
            });
            pageInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    goPage(pageInput.value);
                }
            });
        }

        // Ctrl + wheel zoom
        stage.addEventListener('wheel', function (e) {
            if (!(e.ctrlKey || e.metaKey)) return;
            e.preventDefault();
            if (e.deltaY < 0) setScale(state.scale + ZOOM_STEP, null);
            else setScale(state.scale - ZOOM_STEP, null);
        }, { passive: false });

        // Track visible page while scrolling
        stage.addEventListener('scroll', function () {
            if (!state.pdf) return;
            var wraps = pagesWrap.querySelectorAll('[data-page]');
            var stageTop = stage.getBoundingClientRect().top;
            var best = state.currentPage;
            var bestDist = Infinity;
            for (var i = 0; i < wraps.length; i++) {
                var rect = wraps[i].getBoundingClientRect();
                var dist = Math.abs(rect.top - stageTop - 8);
                if (dist < bestDist) {
                    bestDist = dist;
                    best = parseInt(wraps[i].getAttribute('data-page'), 10);
                }
            }
            if (best !== state.currentPage) {
                state.currentPage = best;
                syncPageUi();
            }
        });

        return pdfjsLib.getDocument({
            url: options.url,
            withCredentials: true,
            cMapUrl: options.cMapUrl || (pdfjsBase + 'cmaps/'),
            cMapPacked: true,
            standardFontDataUrl: options.standardFontDataUrl || (pdfjsBase + 'standard_fonts/')
        }).promise.then(function (pdf) {
            state.pdf = pdf;
            syncPageUi();
            return renderAll();
        }).catch(function (err) {
            console.error('PDF load failed', err);
            pagesWrap.innerHTML = '<div class="alert alert-warning m-2">PDFの表示に失敗しました。保護ビューアをご利用ください。</div>';
        });
    }

    function injectStylesOnce() {
        if (document.getElementById('caily-protect-viewer-css')) return;
        var style = document.createElement('style');
        style.id = 'caily-protect-viewer-css';
        style.textContent = ''
            + '.caily-protect-viewer{position:relative;user-select:none;-webkit-user-select:none;}'
            + '.caily-protect-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px;'
            + 'padding:8px 10px;margin-bottom:8px;background:#2b2b2b;color:#f0f0f0;border-radius:.35rem;}'
            + '.caily-protect-tb-group{display:flex;align-items:center;gap:4px;}'
            + '.caily-protect-btn{appearance:none;border:1px solid #555;background:#3a3a3a;color:#fff;'
            + 'border-radius:4px;min-width:32px;height:30px;padding:0 8px;cursor:pointer;line-height:1;}'
            + '.caily-protect-btn:hover{background:#4a4a4a;}'
            + '.caily-protect-pagebox{display:flex;align-items:center;gap:4px;margin:0 2px;}'
            + '.caily-protect-pageinput{width:52px;height:28px;border:1px solid #555;border-radius:4px;'
            + 'background:#1f1f1f;color:#fff;text-align:center;}'
            + '.caily-protect-pageof{font-size:13px;opacity:.9;}'
            + '.caily-protect-zoomselect{height:30px;border:1px solid #555;border-radius:4px;'
            + 'background:#1f1f1f;color:#fff;padding:0 6px;max-width:140px;}'
            + '.caily-protect-tb-note{margin-left:auto;font-size:12px;opacity:.75;}'
            + '.caily-protect-stage{position:relative;overflow:auto;max-height:75vh;background:#525659;border-radius:.35rem;}'
            + '.caily-protect-pages{display:flex;flex-direction:column;align-items:center;gap:12px;padding:12px;}'
            + '.caily-protect-pagewrap{position:relative;box-shadow:0 1px 4px rgba(0,0,0,.35);background:#fff;}'
            + '.caily-protect-page{display:block;max-width:none;height:auto;background:#fff;}'
            + '.caily-protect-img{display:block;max-width:100%;height:auto;margin:0 auto;}'
            + '.caily-protect-iframe{width:100%;height:70vh;border:0;background:#fff;}'
            + '.caily-protect-wm{pointer-events:none;position:absolute;inset:0;z-index:5;overflow:hidden;}'
            + '.caily-protect-wm-line{display:block;transform:rotate(-28deg);font-size:28px;font-weight:700;'
            + 'color:rgba(0,0,0,.055);white-space:nowrap;line-height:1.2;'
            + 'font-family:Segoe UI,Meiryo,Yu Gothic,sans-serif;}'
            + '@media print{.caily-protect-viewer{display:none!important;}}'
            + '@media (max-width:720px){.caily-protect-tb-note{width:100%;margin-left:0;}}';
        document.head.appendChild(style);
    }

    injectStylesOnce();

    window.CailyProtectedViewer = {
        mount: mountProtectedViewer,
        buildWatermarkText: buildWatermarkText,
        formatStamp: formatStamp
    };
})(window);
