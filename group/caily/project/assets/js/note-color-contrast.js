/**
 * メモ: keep coloured text readable on the note's background.
 *
 * Note HTML carries inline colours picked for one background (Quill colours, imported
 * #FFFF00 / #FFFFFF text written for dark sheet cells, white highlights …). Rendered on a
 * dark theme or another note colour they can become unreadable. Here every inline-coloured
 * element of a rendered note gets only its lightness moved until it contrasts enough with
 * what is actually behind it (hue kept: red stays red). The stored note is never changed.
 *
 * Rendered note bodies (never the Quill editor):
 *   .note-card-content     メモボード card
 *   .note-view-content     メモ modal, view mode
 *   .note-text             案件一覧 memo cells
 *   .note-hover-full-body  案件一覧 hover popup (copies the cell HTML, re-checked on its own background)
 * Runs on insert / change (MutationObserver) and again when the theme switches.
 */
(function (global) {
    'use strict';

    var TARGETS = '.note-card-content, .note-view-content, .note-text, .note-hover-full-body';
    // dark: normal-text AA; light: only fix what is really unreadable, keep the look people know
    var MIN_DARK = 4.5;
    var MIN_LIGHT = 3;

    function parse(c) {
        var m = /rgba?\(([^)]+)\)/.exec(c || '');
        if (!m) return null;
        var p = m[1].split(/[\s,\/]+/).filter(Boolean).map(parseFloat);
        return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 };
    }
    function blend(top, under) {
        var a = top.a;
        return { r: top.r * a + under.r * (1 - a), g: top.g * a + under.g * (1 - a), b: top.b * a + under.b * (1 - a), a: 1 };
    }
    function lum(c) {
        var f = function (v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
        return 0.2126 * f(c.r) + 0.7152 * f(c.g) + 0.0722 * f(c.b);
    }
    function contrast(a, b) {
        var x = lum(a), y = lum(b);
        return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
    }
    function toHsl(c) {
        var r = c.r / 255, g = c.g / 255, b = c.b / 255;
        var max = Math.max(r, g, b), min = Math.min(r, g, b), l = (max + min) / 2, h = 0, s = 0;
        if (max !== min) {
            var d = max - min;
            s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
            h = max === r ? (g - b) / d + (g < b ? 6 : 0) : max === g ? (b - r) / d + 2 : (r - g) / d + 4;
            h /= 6;
        }
        return { h: h, s: s, l: l };
    }
    function fromHsl(h, s, l) {
        var hue = function (p, q, t) {
            if (t < 0) t += 1;
            if (t > 1) t -= 1;
            if (t < 1 / 6) return p + (q - p) * 6 * t;
            if (t < 1 / 2) return q;
            if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
            return p;
        };
        var r, g, b;
        if (s === 0) {
            r = g = b = l;
        } else {
            var q = l < 0.5 ? l * (1 + s) : l + s - l * s, p = 2 * l - q;
            r = hue(p, q, h + 1 / 3); g = hue(p, q, h); b = hue(p, q, h - 1 / 3);
        }
        return { r: Math.round(r * 255), g: Math.round(g * 255), b: Math.round(b * 255), a: 1 };
    }

    /** Opaque colour behind el: its own and its ancestors' backgrounds, over the page */
    function backdrop(el) {
        var layers = [];
        for (var e = el; e && e.nodeType === 1; e = e.parentElement) {
            var c = parse(getComputedStyle(e).backgroundColor);
            if (c && c.a > 0) {
                layers.push(c);
                if (c.a >= 1) break;
            }
        }
        var acc = { r: 255, g: 255, b: 255, a: 1 };
        if (!layers.length || layers[layers.length - 1].a < 1) {
            var page = parse(getComputedStyle(document.body).backgroundColor);
            if (page && page.a >= 1) acc = page;
        }
        for (var i = layers.length - 1; i >= 0; i--) acc = blend(layers[i], acc);
        return acc;
    }

    /** fg with its lightness moved until it reaches min against bg (toward the side that works) */
    function readable(fg, bg, min) {
        var hsl = toHsl(fg);
        var first = lum(bg) < 0.4 ? 1 : -1;
        var dirs = [first, -first];
        for (var k = 0; k < dirs.length; k++) {
            for (var l = hsl.l; l >= 0 && l <= 1; l += dirs[k] * 0.02) {
                var c = fromHsl(hsl.h, hsl.s, l);
                if (contrast(c, bg) >= min) return c;
            }
        }
        return lum(bg) < 0.4 ? { r: 255, g: 255, b: 255, a: 1 } : { r: 0, g: 0, b: 0, a: 1 };
    }

    function isDark() {
        return document.documentElement.getAttribute('data-bs-theme') === 'dark';
    }

    function fixRoot(root) {
        if (!root.isConnected || root.isContentEditable) return;
        var min = isDark() ? MIN_DARK : MIN_LIGHT;
        var els = root.querySelectorAll('[style]');
        for (var i = 0; i < els.length; i++) {
            var el = els[i];
            var ds = el.dataset;
            if (!('ncFg' in ds)) {
                if (!el.style.color && !el.style.backgroundColor) continue;
                ds.ncFg = el.style.color || '';
            }
            // the colour as written (or, without one, what it inherits) on what is really behind it
            var bg = backdrop(el);
            var own = parse(ds.ncFg);
            var fg = own || parse(getComputedStyle(el.parentElement || el).color);
            if (!fg) continue;
            var want = ds.ncFg;
            if (contrast(blend(fg, bg), bg) < min) {
                var c = readable(blend(fg, bg), bg, min);
                want = 'rgb(' + c.r + ', ' + c.g + ', ' + c.b + ')';
            }
            if (el.style.color !== want) el.style.color = want; // write only on change (no needless re-style)
        }
    }

    var pending = [];
    var scheduled = false;
    function queue(root) {
        if (pending.indexOf(root) === -1) pending.push(root);
        if (!scheduled) {
            scheduled = true;
            // whichever comes first: rAF does not run in a background tab
            if (global.requestAnimationFrame) global.requestAnimationFrame(flush);
            setTimeout(flush, 100);
        }
    }
    function flush() {
        if (!scheduled) return;
        scheduled = false;
        var list = pending;
        pending = [];
        for (var i = 0; i < list.length; i++) fixRoot(list[i]);
    }
    function queueAll() {
        var all = document.querySelectorAll(TARGETS);
        for (var i = 0; i < all.length; i++) queue(all[i]);
    }
    function scan(node) {
        if (node.nodeType !== 1) return;
        if (node.matches(TARGETS)) queue(node);
        var inner = node.querySelectorAll(TARGETS);
        for (var i = 0; i < inner.length; i++) queue(inner[i]);
    }

    function start() {
        new MutationObserver(function (records) {
            for (var i = 0; i < records.length; i++) {
                var t = records[i].target;
                var host = t.nodeType === 1 ? t.closest(TARGETS) : null;
                if (host) {
                    queue(host); // v-html / innerHTML of a note body changed
                    continue;
                }
                var added = records[i].addedNodes;
                for (var j = 0; j < added.length; j++) scan(added[j]);
            }
        }).observe(document.body, { childList: true, subtree: true });
        new MutationObserver(queueAll).observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-bs-theme']
        });
        queueAll();
    }

    global.NoteColorContrast = { fix: fixRoot, fixAll: queueAll, contrast: contrast };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})(window);
