<?php
/** The bar tells the script whether the server still holds a conversation, so a click can reopen it on any page. */
require __DIR__ . '/bootstrap.php';

ai_test_login(ADMIN_USERID);
$render = function () {
    ob_start();
    require DIR_VIEW . 'ai-chat-bar.php';
    return ob_get_clean();
};
$talk = [['role' => 'user', 'text' => 'q'], ['role' => 'assistant', 'text' => 'a']];

unset($_SESSION['ai_chat'], $_SESSION['ai_chat_at'], $_SESSION['ai_chat_log']);
check('hide and show buttons are in the bar', strpos($render(), 'id="ai-chat-hide"') !== false && strpos($render(), 'id="ai-chat-show"') !== false);
check('the hidden choice is applied before the script loads', strpos($render(), "localStorage.getItem('aiChatHidden')") !== false);
check('new conversation button says what it does', preg_match('/id="ai-chat-new"[^>]*title="[^"]+"[^>]*>\s*<svg/', $render()) === 1);
check('no conversation: flag is 0', strpos($render(), 'data-has-history="0"') !== false);

$_SESSION['ai_chat'] = $talk;
$_SESSION['ai_chat_log'] = $talk;
$_SESSION['ai_chat_at'] = time();
check('conversation in the session: flag is 1', strpos($render(), 'data-has-history="1"') !== false);

$_SESSION['ai_chat_at'] = time() - AI::HISTORY_IDLE_SECONDS + 5;
check('still within the idle limit: flag is 1', strpos($render(), 'data-has-history="1"') !== false && count((new AI())->chatHistory) === 2);

$_SESSION['ai_chat_at'] = time() - AI::HISTORY_IDLE_SECONDS - 5;
check('past the idle limit the AI forgets, but the box can still show the conversation', strpos($render(), 'data-has-history="1"') !== false && count((new AI())->chatHistory) === 0);

$_SESSION['ai_chat'] = [];
$_SESSION['ai_chat_log'] = [];
$_SESSION['ai_chat_at'] = time();
$html = $render();
check('the bar has a help button and a help box', strpos($html, 'id="ai-chat-help"') !== false && strpos($html, 'id="ai-chat-helpbox"') !== false && preg_match('/id="ai-chat-helpbox"[^>]*hidden/', $html) === 1);
check('empty conversation: flag is 0', strpos($render(), 'data-has-history="0"') !== false);
finish();
