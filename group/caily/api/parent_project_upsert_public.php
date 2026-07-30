<?php
/**
 * Public API: insert or update parent_project without login.
 * GET or POST: customer_id (required), construction_number (required), request_date, project_name, scale, type1, request_type, status (default completed), department_id (default 5).
 * Loads customer to get company_name, branch_name, contact_name. If construction_number + customer_id already exists then update, else insert.
 */
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/loader.php';
$controller->requiring();

require_once dirname(__DIR__) . '/application/model/parentproject.php';

$params = array(
    'customer_id'         => isset($_REQUEST['customer_id']) ? $_REQUEST['customer_id'] : '',
    'request_date'        => isset($_REQUEST['request_date']) ? $_REQUEST['request_date'] : '',
    'construction_number' => isset($_REQUEST['construction_number']) ? $_REQUEST['construction_number'] : '',
    'project_name'        => isset($_REQUEST['project_name']) ? $_REQUEST['project_name'] : '',
    'scale'               => isset($_REQUEST['scale']) ? $_REQUEST['scale'] : '',
    'type1'               => isset($_REQUEST['type1']) ? $_REQUEST['type1'] : '',
    'request_type'        => isset($_REQUEST['request_type']) ? $_REQUEST['request_type'] : '',
    'requests'            => isset($_REQUEST['requests']) ? $_REQUEST['requests'] : '',
    //'status'              => isset($_REQUEST['status']) ? $_REQUEST['status'] : '',
    'department_id'       => isset($_REQUEST['department_id']) ? $_REQUEST['department_id'] : '',
    'key' => isset($_REQUEST['key']) ? $_REQUEST['key'] : '',
);
if($params['key'] != 'caily@123'){
    $hash['status'] = 'error';
    $hash['message_code'] = 'invalid key';
    echo json_encode($hash);
    exit;
}
$parentProject = new ParentProject();
$parentProject->connect();
$hash = $parentProject->upsert_parent_project_public($params);
$parentProject->close();

echo json_encode($hash);
