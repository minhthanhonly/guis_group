<?php
/** Finding buildings by what is recorded under 建物情報: size, city, type, house type, structural office, features. */
require __DIR__ . '/bootstrap.php';

$p = DB_PREFIX;
ai_test_login(ADMIN_USERID);
$t = new AiTools(new AI());
$count = function ($where) use ($p) {
    return (int) ai_test_rows("SELECT COUNT(*) AS c FROM {$p}parent_projects WHERE status != 'deleted' AND {$where}")[0]['c'];
};
$top = function ($column) use ($p) {
    return (string) ai_test_rows("SELECT {$column} AS v FROM {$p}parent_projects WHERE status != 'deleted' AND {$column} <> '' AND {$column} NOT LIKE '%\n%' AND {$column} NOT LIKE '%,%' GROUP BY {$column} ORDER BY COUNT(*) DESC LIMIT 1")[0]['v'];
};
$LIST_KEYS = ['id', 'project_number', 'project_name', 'construction_number', 'company_name', 'requests', 'child_projects', 'nearest_deadline'];
$ATTRIBUTE_KEYS = ['scale', 'city', 'type1', 'house_type', 'structural_office', 'features'];

// One filter at a time, each compared with the table.
$cases = ['scale' => 'scale', 'city' => 'city', 'type1' => 'type1', 'house_type' => 'type2', 'structural_office' => 'structural_office'];
foreach ($cases as $param => $column) {
    $value = $top($column);
    $r = $t->runRead('search_buildings', [$param => $value, 'limit' => 5]);
    $expected = $count("{$column} LIKE '%" . addslashes($value) . "%'");
    check("by {$param} ({$value}): counted like the table", isset($r['total']) && $r['total'] === $expected && $expected > 0 && count($r['buildings']) === min(5, $expected));
    check("by {$param}: every row carries the value", count(array_filter($r['buildings'], function ($row) use ($param, $value) { return mb_strpos((string) $row[$param], $value) === false; })) === 0);
}
$feature = ai_test_rows("SELECT features FROM {$p}parent_projects WHERE status != 'deleted' AND features <> '' ORDER BY id DESC LIMIT 1");
if (count($feature) === 1) {
    $one = explode(',', $feature[0]['features'])[0];
    $r = $t->runRead('search_buildings', ['feature' => $one]);
    check("by feature ({$one})", $r['total'] === $count("features LIKE '%" . addslashes($one) . "%'") && $r['total'] > 0);
}

// Part of a size code, and two filters together.
$r = $t->runRead('search_buildings', ['scale' => 'W2F']);
check('part of a size code matches', $r['total'] === $count("scale LIKE '%W2F%'") && $r['total'] > 0);
$house = $top('type2');
$r = $t->runRead('search_buildings', ['scale' => 'W2F', 'house_type' => $house, 'limit' => 3]);
check('two filters narrow the list together', $r['total'] === $count("scale LIKE '%W2F%' AND type2 LIKE '%" . addslashes($house) . "%'") && $r['more'] === ($r['total'] > 3));
check('rows show the attributes when searching by them', array_keys($r['buildings'][0]) === array_merge($LIST_KEYS, $ATTRIBUTE_KEYS));
$r = $t->runRead('search_buildings', ['keyword' => 'a', 'limit' => 2]);
check('a plain search keeps the short rows', array_keys($r['buildings'][0]) === $LIST_KEYS);

// Nothing matches all filters together: the answer says how many match each one, so no retry is needed.
$r = $t->runRead('search_buildings', ['city' => $top('city'), 'structural_office' => 'zz-no-such-office', 'scale' => 'W2F']);
check('no building for the combination', $r['total'] === 0 && $r['buildings'] === []);
check('each filter is counted on its own', isset($r['matches_per_filter']) && $r['matches_per_filter'] === ['scale' => $count("scale LIKE '%W2F%'"), 'city' => $count("city LIKE '%" . addslashes($top('city')) . "%'"), 'structural_office' => 0]);
check('and the answer says the fields are often empty', isset($r['note']) && strpos($r['note'], 'not filled in') !== false);
$r = $t->runRead('search_buildings', ['structural_office' => 'zz-no-such-office']);
check('a single filter with no match needs no breakdown', $r['total'] === 0 && !isset($r['matches_per_filter']));

// ---- The search box of the list covers the same attributes; several words must all be found. ----
$anyField = function ($word) {
    $w = addslashes($word);
    $fields = ['company_name', 'branch_name', 'contact_name', 'construction_number', 'project_name', 'project_number', 'scale', 'city', 'type1', 'type2', 'structural_office', 'features'];
    return '(' . implode(' OR ', array_map(function ($f) use ($w) { return "{$f} LIKE '%{$w}%'"; }, $fields)) . ')';
};
$cityName = $top('city');
$r = $t->runRead('search_buildings', ['keyword' => $cityName, 'limit' => 3]);
check("search box: a city name ({$cityName}) finds buildings by 市区町村", $r['total'] >= $count("city LIKE '%" . addslashes($cityName) . "%'") && $r['total'] === $count($anyField($cityName)));
$r = $t->runRead('search_buildings', ['keyword' => 'W2F3J']);
check('search box: a size code finds buildings by 建物規模', $r['total'] === $count($anyField('W2F3J')) && $r['total'] > 0);
$r = $t->runRead('search_buildings', ['keyword' => $cityName . ' W2F']);
check('search box: two words must both be found', $r['total'] === $count($anyField($cityName) . ' AND ' . $anyField('W2F')) && $r['total'] > 0 && $r['total'] < $count($anyField('W2F')));
$r = $t->runRead('search_buildings', ['keyword' => $cityName . '　W2F']);
check('search box: a full-width space separates words too', $r['total'] === $count($anyField($cityName) . ' AND ' . $anyField('W2F')));
foreach ([',', '、', '，', ', ', '、 '] as $separator) {
    $r = $t->runRead('search_buildings', ['keyword' => $cityName . $separator . 'W2F']);
    check('search box: "' . $separator . '" separates words', $r['total'] === $count($anyField($cityName) . ' AND ' . $anyField('W2F')) && $r['total'] > 0);
}
$r = $t->runRead('search_buildings', ['keyword' => ' , 、 ']);
check('search box: separators alone are not a search', !isset($r['error']) && $r['total'] === $count('1 = 1'));
$r = $t->runRead('search_buildings', ['keyword' => $cityName . ' zz-no-such-word']);
check('search box: one word that is nowhere gives nothing', $r['total'] === 0);
$company = (string) ai_test_rows("SELECT company_name AS v FROM {$p}parent_projects WHERE status != 'deleted' AND company_name <> '' GROUP BY company_name ORDER BY COUNT(*) DESC LIMIT 1")[0]['v'];
$r = $t->runRead('search_buildings', ['keyword' => $company]);
check('search box: a company name still finds its buildings', $r['total'] >= $count("company_name LIKE '%" . addslashes($company) . "%'"));

// The values are text, never SQL.
$r = $t->runRead('search_buildings', ['city' => "x' OR '1'='1"]);
check('a quote in a filter is just text', !isset($r['error']) && $r['total'] === 0);
$r = $t->runRead('search_buildings', ['house_type' => '%']);
check('a wildcard alone does not list everything', $r['total'] < $count('1 = 1'));

// The list page itself is unchanged when no attribute is given.
require_once DIR_MODEL . 'parentproject.php';
$pp = new ParentProject();
$pp->connect();
$saved = $_GET;
$_GET = ['draw' => 1, 'start' => 0, 'length' => 1];
$all = $pp->list($_GET);
$_GET = ['draw' => 1, 'start' => 0, 'length' => 1, 'city' => $top('city')];
$some = $pp->list($_GET);
$_GET = $saved;
check('the building list takes the filter too', (int) $all['recordsFiltered'] === $count('1 = 1') && (int) $some['recordsFiltered'] === $count("city LIKE '%" . addslashes($top('city')) . "%'"));
check('prompt explains how to search by attributes', strpos((new AI())->buildSystemBlocks([])[0]['text'], '建物規模') !== false);
finish();
