<?php
/**
 * Public API: add customer without login.
 * GET or POST: name (required), branch_name (optional), company_name (optional; if empty, default 大東建託株式会社), category_id (optional; if empty, default 2).
 * - If a customer already exists with same company_name, name containing $name and same branch_name, returns that customer id.
 * - Otherwise inserts a new customer with given company_name (or default) and returns the new id.
 */
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/loader.php';
$controller->requiring();

require_once dirname(__DIR__) . '/application/model/customer.php';

$params = array(
    'name'         => isset($_REQUEST['name']) ? $_REQUEST['name'] : '',
    'branch_name'  => isset($_REQUEST['branch_name']) ? $_REQUEST['branch_name'] : '',
    'company_name' => isset($_REQUEST['company_name']) ? $_REQUEST['company_name'] : '',
    'category_id'  => isset($_REQUEST['category_id']) ? $_REQUEST['category_id'] : '',
    'key' => isset($_REQUEST['key']) ? $_REQUEST['key'] : '',
);
if($params['key'] != 'caily@123'){
    $hash['status'] = 'error';
    $hash['message_code'] = 'invalid key';
    echo json_encode($hash);
    exit;
}
$customer = new Customer();
$customer->connect();
$hash = $customer->add_customer_public($params);
$customer->close();

echo json_encode($hash);
