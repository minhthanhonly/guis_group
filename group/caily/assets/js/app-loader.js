/**
 * Lightweight script/style loader — dedupe requests, preserve execution order.
 */
(function (window) {
    'use strict';

    var scriptCache = Object.create(null);
    var styleCache = Object.create(null);

    function resolveUrl(src) {
        if (!src || /^https?:\/\//i.test(src) || src.indexOf('//') === 0) {
            return src;
        }
        var root = window.ROOT || '';
        if (root && src.charAt(0) !== '/') {
            return root + src;
        }
        return src;
    }

    function loadScript(src) {
        var url = resolveUrl(src);
        if (!url) {
            return Promise.resolve();
        }
        if (scriptCache[url]) {
            return scriptCache[url];
        }
        scriptCache[url] = new Promise(function (resolve, reject) {
            var existing = document.querySelector('script[src="' + url.replace(/"/g, '\\"') + '"]');
            if (existing) {
                if (existing.getAttribute('data-loaded') === '1') {
                    resolve();
                    return;
                }
                existing.addEventListener('load', function () { resolve(); }, { once: true });
                existing.addEventListener('error', function () { reject(new Error('Failed to load ' + url)); }, { once: true });
                return;
            }
            var el = document.createElement('script');
            el.src = url;
            el.async = false;
            el.onload = function () {
                el.setAttribute('data-loaded', '1');
                resolve();
            };
            el.onerror = function () {
                delete scriptCache[url];
                reject(new Error('Failed to load ' + url));
            };
            document.head.appendChild(el);
        });
        return scriptCache[url];
    }

    function loadScripts(srcList) {
        var chain = Promise.resolve();
        (srcList || []).forEach(function (src) {
            chain = chain.then(function () {
                return loadScript(src);
            });
        });
        return chain;
    }

    function loadStyle(href) {
        var url = resolveUrl(href);
        if (!url) {
            return Promise.resolve();
        }
        if (styleCache[url]) {
            return styleCache[url];
        }
        styleCache[url] = new Promise(function (resolve, reject) {
            var existing = document.querySelector('link[rel="stylesheet"][href="' + url.replace(/"/g, '\\"') + '"]');
            if (existing) {
                resolve();
                return;
            }
            var el = document.createElement('link');
            el.rel = 'stylesheet';
            el.href = url;
            el.onload = function () { resolve(); };
            el.onerror = function () {
                delete styleCache[url];
                reject(new Error('Failed to load ' + url));
            };
            document.head.appendChild(el);
        });
        return styleCache[url];
    }

    function whenDomReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    window.AppLoader = {
        loadScript: loadScript,
        loadScripts: loadScripts,
        loadStyle: loadStyle,
        whenDomReady: whenDomReady,
        isDefined: function (name) {
            return typeof window[name] !== 'undefined';
        }
    };
})(window);
