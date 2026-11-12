/**
 * Site-wide font-size zoom.
 *
 * The theme sizes everything in rem, so scaling the root font size scales the whole UI.
 * demo.css multiplies --bs-root-font-size by --app-font-zoom; this script sets that
 * variable on <html> and remembers the choice per browser.
 *
 * Loaded synchronously in <head> so the saved zoom applies before first paint.
 * Controls: any element with data-font-zoom="in|out|reset"; the current value is
 * written into elements with [data-font-zoom-label].
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'caily-font-zoom';
  var MIN = 80;
  var MAX = 150;
  var STEP = 10;
  var DEFAULT = 100;

  function clamp(percent) {
    var n = Math.round(Number(percent) / STEP) * STEP;
    if (!isFinite(n)) return DEFAULT;
    return Math.min(MAX, Math.max(MIN, n));
  }

  function read() {
    try {
      var saved = localStorage.getItem(STORAGE_KEY);
      return saved === null ? DEFAULT : clamp(saved);
    } catch (e) {
      return DEFAULT;
    }
  }

  var current = read();

  function updateControls() {
    var labels = document.querySelectorAll('[data-font-zoom-label]');
    for (var i = 0; i < labels.length; i++) labels[i].textContent = current + '%';
    var buttons = document.querySelectorAll('[data-font-zoom]');
    for (var j = 0; j < buttons.length; j++) {
      var action = buttons[j].getAttribute('data-font-zoom');
      buttons[j].disabled =
        (action === 'out' && current <= MIN) ||
        (action === 'in' && current >= MAX) ||
        (action === 'reset' && current === DEFAULT);
    }
  }

  function apply() {
    var root = document.documentElement;
    if (current === DEFAULT) root.style.removeProperty('--app-font-zoom');
    else root.style.setProperty('--app-font-zoom', String(current / 100));
    updateControls();
  }

  function set(percent) {
    var next = clamp(percent);
    if (next === current) return current;
    current = next;
    try {
      if (current === DEFAULT) localStorage.removeItem(STORAGE_KEY);
      else localStorage.setItem(STORAGE_KEY, String(current));
    } catch (e) { /* storage unavailable: zoom still applies for this page */ }
    apply();
    // Tables, charts and calendars measure text; let them re-layout at the new size.
    window.dispatchEvent(new Event('resize'));
    return current;
  }

  apply();

  document.addEventListener('DOMContentLoaded', updateControls);

  document.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest ? e.target.closest('[data-font-zoom]') : null;
    if (!btn) return;
    e.preventDefault();
    var action = btn.getAttribute('data-font-zoom');
    if (action === 'in') set(current + STEP);
    else if (action === 'out') set(current - STEP);
    else if (action === 'reset') set(DEFAULT);
  });

  // Keep other open tabs in sync.
  window.addEventListener('storage', function (e) {
    if (e.key !== STORAGE_KEY) return;
    current = read();
    apply();
  });

  window.AppFontZoom = {
    get: function () { return current; },
    set: set,
    zoomIn: function () { return set(current + STEP); },
    zoomOut: function () { return set(current - STEP); },
    reset: function () { return set(DEFAULT); }
  };
})();
