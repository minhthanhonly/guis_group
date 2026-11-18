<?php
require __DIR__ . '/bootstrap.php';

$c = new ClaudeClient('k', 'claude-haiku-5-5', 'medium');
$p = $c->buildParams([['type' => 'text', 'text' => 'S']], [], [['role' => 'user', 'content' => 'hi']]);
check('model', $p['model'] === 'claude-haiku-5-5');
check('maxTokens', $p['maxTokens'] === 8000);
check('effort', $p['outputConfig'] === ['effort' => 'medium']);
check('no sampling/thinking', !isset($p['temperature']) && !isset($p['thinking']) && !isset($p['toolChoice']));
check('empty tools omitted', !array_key_exists('tools', $p));
check('answer-only request forbids tool calls but keeps the tools', (new ClaudeClient('k'))->buildParams([], [['name' => 't']], [], true)['toolChoice'] === ['type' => 'none'] && count((new ClaudeClient('k'))->buildParams([], [['name' => 't']], [], true)['tools']) === 1);
check('tool choice is left alone otherwise', !isset((new ClaudeClient('k'))->buildParams([], [['name' => 't']], [])['toolChoice']));
check('tools kept when given', count($c->buildParams([], [['name' => 't']], [])['tools']) === 1);
check('conversation prefix cached automatically', isset($p['cacheControl']) && $p['cacheControl'] === ['type' => 'ephemeral']);
check('configured', $c->isConfigured() === true);
check('not configured', (new ClaudeClient('', 'm', 'low'))->isConfigured() === false);
try {
    (new ClaudeClient('', 'm', 'low'))->send([], [], []);
    check('throws when not configured', false);
} catch (ClaudeClientException $e) {
    check('reason not_configured', $e->reason === 'not_configured');
}
putenv('CLAUDE_MODEL');
putenv('CLAUDE_EFFORT');
$d = (new ClaudeClient('k'))->buildParams([], [], []);
check('default model', $d['model'] === 'claude-haiku-5-5');
check('default effort', $d['outputConfig'] === ['effort' => 'medium']);
putenv('CLAUDE_MODEL=claude-sonnet-5-5');
check('model from env', (new ClaudeClient('k'))->buildParams([], [], [])['model'] === 'claude-sonnet-5-5');
putenv('CLAUDE_MODEL');
finish();
