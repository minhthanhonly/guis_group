<?php
/**
 * Bootstrap for AI CLI tests. Usage: php tests/ai/<name>.test.php
 * Loads the app the same way api/index.php does, without output compression or a browser session.
 */
define('CAILY_OUTPUT_COMPRESSION_STARTED', 1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '1');
$GLOBALS['__ai_test_root'] = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $GLOBALS['__ai_test_root'];
$_SERVER['SCRIPT_NAME'] = '/ai/index.php';
$_SERVER['REQUEST_URI'] = '/ai/index.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SESSION = [];
chdir($GLOBALS['__ai_test_root'] . '/api');
require 'loader.php';
$controller->requiring();
require_once DIR_LIBRARY . 'claudeclient.php';
require_once __DIR__ . '/fakeclaudeclient.php';

$GLOBALS['__ai_test_fail'] = 0;
$GLOBALS['__ai_test_count'] = 0;

function check($name, $ok) {
    $GLOBALS['__ai_test_count']++;
    if (!$ok) {
        $GLOBALS['__ai_test_fail']++;
    }
    echo ($ok ? 'PASS' : 'FAIL') . '  ' . $name . "\n";
}

function finish() {
    echo "\n" . ($GLOBALS['__ai_test_count'] - $GLOBALS['__ai_test_fail']) . '/' . $GLOBALS['__ai_test_count'] . " passed\n";
    exit($GLOBALS['__ai_test_fail'] > 0 ? 1 : 0);
}

/** Runs a read-only query on the app database and returns all rows. */
function ai_test_rows($sql) {
    $m = new ApplicationModel();
    $m->connect();
    return $m->fetchAll($sql);
}

/** Fills $_SESSION the way Authority does at login, for the given userid. */
function ai_test_login($userid) {
    $m = new ApplicationModel();
    $m->connect();
    $data = $m->fetchOne("SELECT * FROM " . DB_PREFIX . "user WHERE userid = '" . $m->quote($userid) . "'");
    if (!$data) {
        fwrite(STDERR, "ai_test_login: user not found: $userid\n");
        exit(2);
    }
    $_SESSION = [
        'logintime' => time(),
        'accesstime' => time(),
        'userid' => $data['userid'],
        'id' => $data['id'],
        'lastname' => $data['lastname'],
        'firstname' => $data['firstname'],
        'realname' => $data['realname'],
        'group' => $data['user_group'],
        'authority' => $data['authority'],
        'user_image' => $data['user_image'],
        'user_groupname' => isset($data['user_groupname']) ? $data['user_groupname'] : '',
        'show_project' => $data['show_project'],
    ];
}

/** Test accounts picked from the local database: one administrator, one member with no project_manager flag. */
(function () {
    $p = DB_PREFIX;
    $admin = ai_test_rows("SELECT userid FROM {$p}user WHERE authority = 'administrator' AND show_project = 1 AND (is_suspend = 0 OR is_suspend IS NULL) ORDER BY id LIMIT 1");
    $normal = ai_test_rows("SELECT u.userid FROM {$p}user u WHERE u.authority <> 'administrator' AND u.show_project = 1 AND (u.is_suspend = 0 OR u.is_suspend IS NULL) AND NOT EXISTS (SELECT 1 FROM {$p}user_department ud WHERE ud.userid = u.userid AND (ud.project_manager = 1 OR ud.project_director = 1 OR ud.project_director_stat = 1 OR ud.project_director_view = 1 OR ud.project_director_edit = 1)) ORDER BY u.id LIMIT 1");
    if (empty($admin) || empty($normal)) {
        fwrite(STDERR, "bootstrap: need one administrator and one plain member in the local DB\n");
        exit(2);
    }
    define('ADMIN_USERID', $admin[0]['userid']);
    define('NORMAL_USERID', $normal[0]['userid']);
})();
require_once DIR_MODEL . 'ai.php';
if (file_exists(DIR_MODEL . 'aitools.php')) {
    require_once DIR_MODEL . 'aitools.php';
}
