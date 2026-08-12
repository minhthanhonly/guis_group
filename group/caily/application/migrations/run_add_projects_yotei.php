<?php
/**
 * One-shot: add projects.yotei column if missing.
 * CLI: php application/migrations/run_add_projects_yotei.php
 */
chdir(__DIR__ . '/../../api');
require __DIR__ . '/../../api/loader.php';
$controller->requiring();

$model = new ApplicationModel();
$model->connect();
$table = DB_PREFIX . 'projects';
$col = $model->fetchOne("SHOW COLUMNS FROM {$table} LIKE 'yotei'");
if ($col) {
    echo "Column yotei already exists on {$table}\n";
    exit(0);
}
$sql = "ALTER TABLE {$table} ADD COLUMN yotei TEXT NULL COMMENT '予定工程 JSON'";
$ok = $model->query($sql);
echo $ok ? "OK: added yotei to {$table}\n" : "FAIL: could not add column\n";
