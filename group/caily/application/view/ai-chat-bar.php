<?php
/**
 * AI assistant bar, fixed at the bottom of every signed-in page.
 * Behaviour: assets/js/ai-chat-bar.js. Styles: assets/css/ai-chat-bar.css.
 * Icons are inline SVG so they do not depend on which icon fonts a page loads.
 * The textarea has no data-i18n on purpose: the page localiser writes into the element body,
 * which for a textarea becomes its value. Its placeholder is set by the script.
 */
if (empty($_SESSION['userid']) || empty($_SESSION['show_project'])) {
    return;
}
$aiIconSparkles = '<svg class="ai-chat-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 18a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm0 -12a2 2 0 0 1 2 2a2 2 0 0 1 2 -2a2 2 0 0 1 -2 -2a2 2 0 0 1 -2 2zm-7 12a6 6 0 0 1 6 -6a6 6 0 0 1 -6 -6a6 6 0 0 1 -6 6a6 6 0 0 1 6 6z"/></svg>';
// Is there a conversation to show again? (The display log of AI::logShown, which get_chat_history
// returns.) The script uses this to reopen the conversation when the input is clicked on a page
// that has just loaded.
$aiHasHistory = !empty($_SESSION['ai_chat_log']) && is_array($_SESSION['ai_chat_log']);
?>
<div id="ai-chat-bar" class="ai-chat-bar is-hidden" data-has-history="<?= $aiHasHistory ? '1' : '0' ?>">
  <div id="ai-chat-panel" class="ai-chat-panel" aria-hidden="true">
    <div class="ai-chat-head">
      <span class="ai-chat-title"><?= $aiIconSparkles ?><span data-i18n="AIアシスタント">AIアシスタント</span></span>
      <!-- Starts over: the conversation so far is dropped on the server and is no longer sent to the AI. -->
      <button type="button" id="ai-chat-new" class="btn btn-sm btn-outline-primary ai-chat-new" title="これまでの会話を消して、最初から始めます">
        <svg class="ai-chat-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14m-7 -7h14"/></svg>
        <span data-i18n="新しい会話">新しい会話</span>
      </button>
      <button type="button" id="ai-chat-close" class="btn btn-sm btn-icon btn-text-secondary" aria-label="閉じる">
        <svg class="ai-chat-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6l6 -6"/></svg>
      </button>
    </div>
    <div id="ai-chat-messages" class="ai-chat-messages" role="log" aria-live="polite"></div>
  </div>
  <div id="ai-chat-peek" class="ai-chat-peek" hidden>
    <div id="ai-chat-peek-text" class="ai-chat-peek-text"></div>
    <button type="button" id="ai-chat-peek-close" class="btn btn-sm btn-icon btn-text-secondary" aria-label="閉じる">
      <svg class="ai-chat-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6l-12 12m0 -12l12 12"/></svg>
    </button>
  </div>
  <!-- What the assistant can do: example questions, filled in by the script in the user's language. -->
  <div id="ai-chat-helpbox" class="ai-chat-helpbox" hidden></div>
  <form id="ai-chat-form" class="ai-chat-inputrow" autocomplete="off">
    <span class="ai-chat-icon"><?= $aiIconSparkles ?></span>
    <textarea id="ai-chat-input" class="ai-chat-input" rows="1" maxlength="2000" placeholder="AIに質問…" aria-label="AIに質問"></textarea>
    <!-- Default department for questions about projects and tasks; filled and remembered by the script. -->
    <select id="ai-chat-dept" class="ai-chat-dept" title="既定の部署" aria-label="既定の部署">
      <option value="">すべての部署</option>
    </select>
    <button type="button" id="ai-chat-help" class="btn btn-icon btn-text-secondary ai-chat-help" aria-label="AIにできること" aria-expanded="false" aria-controls="ai-chat-helpbox">
      <svg class="ai-chat-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"/><path d="M12 17l0 .01"/><path d="M12 13.5a1.5 1.5 0 0 1 1 -1.5a2.6 2.6 0 1 0 -3 -4"/></svg>
    </button>
    <button type="button" id="ai-chat-hide" class="btn btn-icon btn-text-secondary ai-chat-help" title="右に隠す" aria-label="右に隠す">
      <svg class="ai-chat-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 7l5 5l-5 5m6 -10l5 5l-5 5"/></svg>
    </button>
    <button type="submit" id="ai-chat-send" class="btn btn-primary btn-icon ai-chat-send" aria-label="送信" disabled>
      <svg class="ai-chat-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6v6a3 3 0 0 1 -3 3h-10l4 -4m0 8l-4 -4"/></svg>
    </button>
  </form>
  <!-- Shown instead of the bar while it is hidden; the script places it beside the shortcut buttons. -->
  <button type="button" id="ai-chat-show" class="btn btn-primary rounded-circle ai-chat-show" title="AIアシスタントを表示" aria-label="AIアシスタントを表示"><?= $aiIconSparkles ?></button>
</div>
<script>
  // The bar starts as the round button (is-hidden above). It is opened here, before the script loads,
  // only for someone who opened it before, so it does not flash on every page.
  try { if (localStorage.getItem('aiChatHidden') === '0') { document.getElementById('ai-chat-bar').classList.remove('is-hidden'); } } catch (e) {}
</script>
