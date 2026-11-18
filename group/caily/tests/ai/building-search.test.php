<?php
/** Building lookups: keyword search, request filter, sorting, child projects, customers, statistics. Read only. */
require __DIR__ . '/bootstrap.php';

$p = DB_PREFIX;
ai_test_login(ADMIN_USERID);
$t = new AiTools(new AI());
$names = array_column($t->definitions(), 'name');
check('the two building tools are read tools', $t->isReadTool('get_building_projects') && $t->isReadTool('get_building_statistics'));

$LIST_KEYS = ['id', 'project_number', 'project_name', 'construction_number', 'company_name', 'requests', 'child_projects', 'nearest_deadline'];
$sample = ai_test_rows("SELECT id, construction_number, project_number FROM {$p}parent_projects WHERE status != 'deleted' AND construction_number <> '' ORDER BY id DESC LIMIT 1")[0];
$sameNumber = (int) ai_test_rows("SELECT COUNT(*) AS c FROM {$p}parent_projects WHERE status != 'deleted' AND construction_number LIKE '%" . $sample['construction_number'] . "%'")[0]['c'];

// ---- Keyword search ----
$getBefore = $_GET;
$r = $t->runRead('search_buildings', ['keyword' => $sample['construction_number']]);
check('found by construction number', isset($r['buildings']) && in_array((int) $sample['id'], array_map('intval', array_column($r['buildings'], 'id')), true));
check('total is reported', isset($r['total']) && $r['total'] >= 1 && $r['total'] <= max(1, $sameNumber + 5));
check('list rows are compact', array_keys($r['buildings'][0]) === $LIST_KEYS);
check('request globals are left as they were', $_GET === $getBefore);
$r = $t->runRead('search_buildings', ['keyword' => "x' OR '1'='1"]);
check('a keyword with quotes is just text', isset($r['buildings']) && !isset($r['error']) && $r['total'] === 0);
check('status cannot be passed to the list', isset($t->runRead('search_buildings', ['status' => "x' OR 1=1 -- "])['error']));

// ---- Filter by request kind ----
$design = (int) ai_test_rows("SELECT COUNT(*) AS c FROM {$p}parent_projects WHERE status != 'deleted' AND FIND_IN_SET('意匠', REPLACE(requests, ' ', ''))")[0]['c'];
$r = $t->runRead('search_buildings', ['request' => '意匠', 'limit' => 20]);
check('request filter counts like the database', $r['total'] === $design && count($r['buildings']) <= 20 && $r['more'] === ($design > count($r['buildings'])));
check('unknown request kind refused', isset($t->runRead('search_buildings', ['request' => '構造'])['error']));

// ---- Sorting ----
$r = $t->runRead('search_buildings', ['sort' => 'most_projects', 'limit' => 3]);
check('most projects first', count($r['buildings']) === 3 && (int) $r['buildings'][0]['child_projects'] >= (int) $r['buildings'][1]['child_projects'] && (int) $r['buildings'][0]['child_projects'] > 1);
$r = $t->runRead('search_buildings', ['sort' => 'nearest_deadline', 'limit' => 3]);
$dates = array_values(array_filter(array_column($r['buildings'], 'nearest_deadline')));
$sorted = $dates;
sort($sorted);
check('nearest deadline first', count($dates) >= 1 && $dates === $sorted);
check('unknown sort refused', isset($t->runRead('search_buildings', ['sort' => 'random'])['error']));
$r = $t->runRead('search_buildings', ['limit' => 3]);
check('no filter: most recent buildings', count($r['buildings']) === 3 && array_keys($r['buildings'][0]) === $LIST_KEYS && $r['total'] > 3);

// ---- One building: customers and note entries ----
$withCustomer = ai_test_rows("SELECT ppc.parent_project_id AS id, COUNT(*) AS c FROM {$p}parent_project_customers ppc JOIN {$p}parent_projects pp ON pp.id = ppc.parent_project_id WHERE pp.status != 'deleted' GROUP BY ppc.parent_project_id ORDER BY c DESC, ppc.parent_project_id DESC LIMIT 1")[0];
$b = $t->runRead('search_buildings', ['id' => (int) $withCustomer['id']])['buildings'][0];
check('all customers of the building are listed', isset($b['customers']) && count($b['customers']) === (int) $withCustomer['c'] && array_keys($b['customers'][0]) === ['company_name', 'branch', 'contact_name', 'primary']);
check('one customer is marked primary', count(array_filter(array_column($b['customers'], 'primary'))) === 1);
$withNote = ai_test_rows("SELECT n.parent_project_id AS id FROM {$p}parent_project_notes n JOIN {$p}parent_projects pp ON pp.id = n.parent_project_id WHERE pp.status != 'deleted' ORDER BY n.id DESC LIMIT 1");
if (count($withNote) === 1) {
    $b = $t->runRead('search_buildings', ['id' => (int) $withNote[0]['id']])['buildings'][0];
    check('note entries of the building are included', isset($b['note_entries']) && count($b['note_entries']) >= 1 && array_keys($b['note_entries'][0]) === ['content', 'important', 'updated_at'] && mb_strlen($b['note_entries'][0]['content']) <= 200);
}

// ---- Child projects ----
$big = ai_test_rows("SELECT parent_project_id AS id, COUNT(*) AS c FROM {$p}projects WHERE parent_project_id > 0 GROUP BY parent_project_id ORDER BY c DESC LIMIT 1")[0];
$bigNumber = ai_test_rows("SELECT project_number FROM {$p}parent_projects WHERE id = " . (int) $big['id'])[0]['project_number'];
$r = $t->runRead('get_building_projects', ['building_id' => (int) $big['id']]);
check('child projects of a building', $r['total'] === (int) $big['c'] && count($r['projects']) === min(20, (int) $big['c']) && array_keys($r['projects'][0]) === ['id', 'name', 'status', 'department', 'start_date', 'end_date', 'progress']);
check('status counts of the child projects', array_sum($r['by_status']) === (int) $big['c']);
$r2 = $t->runRead('get_building_projects', ['project_number' => $bigNumber]);
check('child projects by building number', $r2['total'] === (int) $big['c'] && $r2['building_id'] === (int) $big['id']);
check('a building is required', isset($t->runRead('get_building_projects', [])['error']));
check('unknown building: error', isset($t->runRead('get_building_projects', ['building_id' => 2000000000])['error']));

// ---- Statistics ----
$s = $t->runRead('get_building_statistics', []);
$total = (int) ai_test_rows("SELECT COUNT(*) AS c FROM {$p}parent_projects WHERE status != 'deleted'")[0]['c'];
$orphans = (int) ai_test_rows("SELECT COUNT(*) AS c FROM {$p}parent_projects pp WHERE pp.status != 'deleted' AND NOT EXISTS (SELECT 1 FROM {$p}projects pr WHERE pr.parent_project_id = pp.id)")[0]['c'];
check('statistics: total and buildings without a project', $s['total'] === $total && $s['without_projects'] === $orphans);
check('statistics: by request kind counts each kind once per building', isset($s['by_request']['省エネ']) && $s['by_request']['意匠'] === $design);
check('statistics: house types, customers and months are short lists', count($s['by_house_type']) <= 10 && count($s['top_customers']) <= 10 && count($s['created_by_month']) <= 6 && array_keys($s['top_customers'][0]) === ['company_name', 'count']);

// ---- Access ----
$_SESSION['show_project'] = 0;
$none = new AiTools(new AI());
check('nothing without project access', isset($none->runRead('search_buildings', ['keyword' => 'a'])['error']) && isset($none->runRead('get_building_statistics', [])['error']));
$_SESSION['show_project'] = 1;
check('prompt says how to look at the progress of a building', strpos((new AI())->buildSystemBlocks([])[0]['text'], 'get_building_projects') !== false);
finish();
