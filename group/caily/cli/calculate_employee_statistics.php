#!/usr/bin/env php
<?php
/**
 * CLI batch job for employee statistics (long-running; not for web timeout).
 *
 * Usage:
 *   php cli/calculate_employee_statistics.php [period_type] [months]
 * Examples:
 *   php cli/calculate_employee_statistics.php month 12
 *   php cli/calculate_employee_statistics.php year 3
 */
$root = dirname(__DIR__);
chdir($root);

require_once $root . '/application/config.php';
require_once DIR_LIBRARY . 'connectionmysql.php';
require_once DIR_LIBRARY . 'helper.php';
require_once DIR_MODEL . 'model.php';
require_once DIR_MODEL . 'applicationmodel.php';
require_once DIR_MODEL . 'employeestatistics.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['userid'] = 'cli';
$_SESSION['id'] = 1;
$_SESSION['authority'] = 'administrator';
$_SESSION['group'] = defined('ADMIN_GROUP') ? ADMIN_GROUP : 4;

$periodType = isset($argv[1]) ? trim((string)$argv[1]) : 'month';
$months = isset($argv[2]) ? intval($argv[2]) : 12;
if (!in_array($periodType, ['month', 'year'], true)) {
    fwrite(STDERR, "Invalid period_type. Use month or year.\n");
    exit(1);
}

$_GET['period_type'] = $periodType;
$_GET['months'] = $months;

try {
    $model = new Employeestatistics();
    $result = $model->calculateStatistics();
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(isset($result['status']) && $result['status'] === 'success' ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
