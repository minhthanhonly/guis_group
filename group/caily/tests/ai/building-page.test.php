<?php
/** show_buildings: the building list page opens with the filters filled in; the chat only gets the count. */
require __DIR__ . '/bootstrap.php';

$p = DB_PREFIX;
ai_test_login(ADMIN_USERID);
$t = new AiTools(new AI());
$names = array_column($t->definitions(), 'name');
check('show_buildings is a page tool', in_array('show_buildings', AiTools::PAGE_TOOLS, true) && $names === array_merge(AiTools::READ_TOOLS, AiTools::PAGE_TOOLS, AiTools::TERMINAL_TOOLS));

$total = (int) ai_test_rows("SELECT COUNT(*) AS c FROM {$p}parent_projects WHERE status != 'deleted'")[0]['c'];
$design = (int) ai_test_rows("SELECT COUNT(*) AS c FROM {$p}parent_projects WHERE status != 'deleted' AND FIND_IN_SET('意匠', REPLACE(requests, ' ', ''))")[0]['c'];
$SAFE = '/^\/parent_project\/index\.php\?[A-Za-z0-9_=&%.,\-]*$/';

$r = $t->runPage('show_buildings', []);
parse_str((string) parse_url((string) $r['url'], PHP_URL_QUERY), $q);
check('no filter: every building, newest first', $r['data']['shown_on_page'] === true && $r['data']['count'] === $total && $q['order_column'] === 'created_at' && $q['order_dir'] === 'DESC');
check('the address is marked as opened by the assistant and carries every filter', strpos($r['url'], '/parent_project/index.php?ai=1&') === 0 && $q['search'] === '' && $q['request_filter'] === '');

$r = $t->runPage('show_buildings', ['request' => '意匠']);
parse_str((string) parse_url((string) $r['url'], PHP_URL_QUERY), $q);
check('request filter: count matches the database', $r['data']['count'] === $design && $q['request_filter'] === '意匠');

$r = $t->runPage('show_buildings', ['keyword' => '大東 建託', 'request' => '意匠', 'sort' => 'nearest_deadline']);
parse_str((string) parse_url((string) $r['url'], PHP_URL_QUERY), $q);
$same = $t->runRead('search_buildings', ['keyword' => '大東 建託', 'request' => '意匠', 'sort' => 'nearest_deadline', 'limit' => 1]);
check('keyword, request and sort are passed on', $q['search'] === '大東 建託' && $q['request_filter'] === '意匠' && $q['order_column'] === 'nearest_nouki' && $q['order_dir'] === 'ASC');
check('count is the one the search tool reports', $r['data']['count'] === $same['total']);
check('address uses only safe characters', preg_match($SAFE, $r['url']) === 1);

// Attributes of the building (建物情報): the page has one search box, so they go into it as words.
$r = $t->runPage('show_buildings', []);
parse_str((string) parse_url((string) $r['url'], PHP_URL_QUERY), $q);
check('the address carries only the filters the page has', array_keys($q) === ['ai', 'search', 'request_filter', 'order_column', 'order_dir']);
$r = $t->runPage('show_buildings', ['keyword' => '大東建託', 'scale' => 'W2F', 'house_type' => '長屋', 'city' => '名古屋市']);
parse_str((string) parse_url((string) $r['url'], PHP_URL_QUERY), $q);
check('attributes are added to the search box as words', $q['search'] === '大東建託 W2F 名古屋市 長屋' && preg_match($SAFE, $r['url']) === 1);
$r = $t->runPage('show_buildings', ['scale' => 'W2F', 'house_type' => '長屋']);
parse_str((string) parse_url((string) $r['url'], PHP_URL_QUERY), $q);
$same = $t->runRead('search_buildings', ['keyword' => $q['search'], 'limit' => 1]);
check('the count is what the page will list for that search', $q['search'] === 'W2F 長屋' && $r['data']['count'] === $same['total'] && $same['total'] > 0);

// The page itself: no separate attribute inputs, the search box says what it covers.
$html = file_get_contents(dirname(DIR_MODEL, 2) . '/parent_project/index.php');
$js = file_get_contents(dirname(DIR_MODEL, 2) . '/parent_project/assets/js/parent-project-index.js');
check('the page has one search box and no attribute inputs', strpos($html, 'attributeFilter') === false && strpos($js, 'attributeFilter') === false);
check('the search box says it covers 建物情報', strpos($html, 'searchBoxHint') !== false && strpos($js, '建物規模') !== false);

check('a request kind the page cannot filter on is refused', isset($t->runPage('show_buildings', ['request' => '3D'])['data']['error']));
check('status cannot be passed', isset($t->runPage('show_buildings', ['status' => "x' OR 1=1 -- "])['data']['error']) && $t->runPage('show_buildings', ['status' => 'draft'])['url'] === null);
check('unknown sort refused', isset($t->runPage('show_buildings', ['sort' => 'random'])['data']['error']));

$_SESSION['show_project'] = 0;
$r = (new AiTools(new AI()))->runPage('show_buildings', []);
check('nothing without project access', isset($r['data']['error']) && $r['url'] === null);
$_SESSION['show_project'] = 1;

$usageStart = (int) ai_test_rows("SELECT COALESCE(MAX(id), 0) AS m FROM {$p}ai_usage")[0]['m'];
$db = new ApplicationModel();
$db->connect();
register_shutdown_function(function () use ($db, $p, $usageStart) {
    $db->query("DELETE FROM {$p}ai_usage WHERE id > {$usageStart} AND userid = '" . $db->quote(ADMIN_USERID) . "'");
});
$ai = new AI();
$f = fake([uses(['show_buildings' => ['request' => '意匠']]), text("意匠の建物は{$design}件です。一覧に表示しました。")]);
$r = $ai->runConversation('意匠の建物を見せて', [], $f);
$last = end($f->calls[1]['messages']);
check('claude receives the count only', strpos($last['content'][0]['content'], '"count":' . $design) !== false && strpos($last['content'][0]['content'], 'project_name') === false);
check('browser is sent to the filtered building list', is_array($r['navigate']) && strpos($r['navigate']['url'], '/parent_project/index.php?ai=1&') === 0);
check('prompt tells claude when to show buildings on the page', strpos((new AI())->buildSystemBlocks([])[0]['text'], 'show_buildings') !== false);
$_SESSION['ai_chat'] = [];
finish();
