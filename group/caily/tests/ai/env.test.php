<?php
/**
 * Settings from .env must survive the whole request. Under Apache (several threads in one process)
 * putenv() values are removed again when any other request ends, so getenv() can come back empty in
 * the middle of a request. app_env() keeps a copy that belongs to the request.
 */
require __DIR__ . '/bootstrap.php';

check('config.php offers app_env()', function_exists('app_env'));
$envFile = dirname(DIR_MODEL, 2) . '/.env';
$expected = [];
foreach (is_file($envFile) ? file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [] as $line) {
    $line = trim($line);
    if ($line !== '' && strpos($line, '#') !== 0 && strpos($line, '=') !== false) {
        list($name, $value) = explode('=', $line, 2);
        $expected[trim($name)] = $value;
    }
}
check('every value of .env is kept for the request', isset($GLOBALS['APP_ENV_VALUES']) && $GLOBALS['APP_ENV_VALUES'] === $expected);

// What another thread does when its request ends: the process environment loses the value.
$GLOBALS['APP_ENV_VALUES']['AI_TEST_SETTING'] = 'kept';
putenv('AI_TEST_SETTING=kept');
putenv('AI_TEST_SETTING');
check('the process environment has lost the value', getenv('AI_TEST_SETTING') === false);
check('app_env() still returns it', app_env('AI_TEST_SETTING') === 'kept');
check('a value set in the environment wins (tests, server configuration)', (function () {
    putenv('AI_TEST_SETTING=from-environment');
    $value = app_env('AI_TEST_SETTING');
    putenv('AI_TEST_SETTING');
    return $value === 'from-environment';
})());
check('an unknown name gives the default', app_env('AI_TEST_NO_SUCH_SETTING') === '' && app_env('AI_TEST_NO_SUCH_SETTING', 'd') === 'd');

// The assistant reads its key, model, effort and limits through app_env().
$GLOBALS['APP_ENV_VALUES']['ANTHROPIC_API_KEY'] = 'request-copy-of-the-key';
$savedKey = getenv('ANTHROPIC_API_KEY');
putenv('ANTHROPIC_API_KEY');
check('the assistant stays configured when the environment loses the key', (new ClaudeClient())->isConfigured() === true);
$GLOBALS['APP_ENV_VALUES']['CLAUDE_MODEL'] = 'claude-sonnet-5-5';
putenv('CLAUDE_MODEL');
check('the model is read the same way', (new ClaudeClient('k'))->buildParams([], [], [])['model'] === 'claude-sonnet-5-5');
if ($savedKey !== false) {
    putenv('ANTHROPIC_API_KEY=' . $savedKey);
}
$GLOBALS['APP_ENV_VALUES'] = $expected;
$source = file_get_contents(DIR_MODEL . 'ai.php') . file_get_contents(DIR_LIBRARY . 'claudeclient.php');
check('the assistant no longer reads getenv() directly', strpos($source, 'getenv(') === false);
finish();
