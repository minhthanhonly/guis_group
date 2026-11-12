/**
 * Site-wide font-size zoom.
 *
 * The theme sizes everything in rem, so scaling the root font size scales the whole UI.
 * demo.css multiplies --bs-root-font-size by --app-font-zoom; this script sets that
 * variable on <html> and remembers the choice per browser.
 *
 * Loaded synchronously in <head> so the saved zoom applies before first paint.
 * Controls:
 *   input[type=range][data-font-zoom-range]  slider, 1% steps
 *   input[type=number][data-font-zoom-number] type an exact value (applied on Enter / blur)
 *   [data-font-zoom="in|out|reset"]          buttons
 *   [data-font-zoom-label]                   shows the current value
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'caily-font-zoom';
  var MIN = 80;
  var MAX = 150;
  var STEP = 1;
  var DEFAULT = 100;

  function clamp(percent) {
    var n = Math.round(Number(percent));
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
  var committed = current;
  // The number box being typed in, if any (see updateControls)
  var typingIn = null;

  function updateControls() {
    var labels = document.querySelectorAll('[data-font-zoom-label]');
    for (var i = 0; i < labels.length; i++) labels[i].textContent = current + '%';
    var ranges = document.querySelectorAll('[data-font-zoom-range]');
    for (var r = 0; r < ranges.length; r++) {
      ranges[r].min = MIN;
      ranges[r].max = MAX;
      ranges[r].step = STEP;
      if (Number(ranges[r].value) !== current) ranges[r].value = current;
    }
    var numbers = document.querySelectorAll('[data-font-zoom-number]');
    for (var n = 0; n < numbers.length; n++) {
      numbers[n].min = MIN;
      numbers[n].max = MAX;
      numbers[n].step = STEP;
      // Do not overwrite what the user is in the middle of typing
      if (numbers[n] !== typingIn && Number(numbers[n].value) !== current) numbers[n].value = current;
    }
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

  /** Show a size without saving it: used while the slider is being dragged. */
  function preview(percent) {
    var next = clamp(percent);
    if (next === current) return current;
    current = next;
    apply();
    return current;
  }

  /** Save the size currently shown and let the page re-layout for it. */
  function commit() {
    if (current === committed) return current;
    committed = current;
    try {
      if (current === DEFAULT) localStorage.removeItem(STORAGE_KEY);
      else localStorage.setItem(STORAGE_KEY, String(current));
    } catch (e) { /* storage unavailable: zoom still applies for this page */ }
    // Tables, charts and calendars measure text. Doing this once per change, not on
    // every step of a drag, keeps the slider smooth on heavy pages.
    window.dispatchEvent(new Event('resize'));
    return current;
  }

  function set(percent) {
    preview(percent);
    return commit();
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

  function isRange(target) {
    return !!(target && target.matches && target.matches('[data-font-zoom-range]'));
  }
  // "input" fires for every step while dragging, "change" once when the thumb is released
  // (and after each keyboard step).
  function isNumberBox(target) {
    return !!(target && target.matches && target.matches('[data-font-zoom-number]'));
  }
  document.addEventListener('input', function (e) {
    if (isRange(e.target)) preview(e.target.value);
    // Typing "1" on the way to "125" must not jump to 80%: the number box applies on change
    else if (isNumberBox(e.target)) typingIn = e.target;
  });
  document.addEventListener('change', function (e) {
    if (isRange(e.target)) {
      set(e.target.value);
    } else if (isNumberBox(e.target)) {
      typingIn = null;
      // Empty or non-numeric input keeps the current size; out-of-range is clamped
      var typed = String(e.target.value).trim();
      if (typed !== '' && isFinite(Number(typed))) set(typed);
      updateControls();
    }
  });
  document.addEventListener('keydown', function (e) {
    // Enter applies the typed value without closing the menu or submitting a form
    if (e.key === 'Enter' && isNumberBox(e.target)) {
      e.preventDefault();
      e.target.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });

  // Keep other open tabs in sync.
  window.addEventListener('storage', function (e) {
    if (e.key !== STORAGE_KEY) return;
    current = read();
    committed = current;
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
