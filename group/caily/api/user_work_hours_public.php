<?php
/**
 * Public API: get working hours + day's forms for a user without login.
 * GET or POST:
 *   key (required), userid (required),
 *   date (optional, YYYY-MM-DD; default today Asia/Tokyo).
 *
 * Work hours: groupware_user.member_type → groupware_config.config_type
 * Forms: groupware_requests overlapping the date for types
 *   leave, outing, trip, holiday_work, overtime
 *   (status: pending, approved, completed — excludes draft/rejected)
 */
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/loader.php';
$controller->requiring();

$params = array(
    'userid' => isset($_REQUEST['userid']) ? $_REQUEST['userid'] : '',
    'date'   => isset($_REQUEST['date']) ? $_REQUEST['date'] : '',
    'key'    => isset($_REQUEST['key']) ? $_REQUEST['key'] : '',
);

if ($params['key'] != 'caily@123') {
    echo json_encode(array('status' => 'error', 'message_code' => 'invalid key', 'data' => null));
    exit;
}

$date = trim((string)$params['date']);
if ($date === '') {
    $date = date('Y-m-d');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date === '0000-00-00') {
    echo json_encode(array('status' => 'error', 'message_code' => 'invalid date', 'data' => null));
    exit;
}

require_once dirname(__DIR__) . '/application/model/config.php';
require_once dirname(__DIR__) . '/application/model/request.php';

$config = new Config();
$config->connect();
$hash = $config->get_work_hours_public($params);
$config->close();

if (!empty($hash['status']) && $hash['status'] === 'success' && is_array($hash['data'])) {
    $request = new Request();
    $formsBundle = $request->get_forms_for_date_public($params['userid'], $date);
    $hash['data']['date'] = $formsBundle['date'];
    $hash['data']['forms'] = $formsBundle['forms'];
    $hash['data']['forms_by_type'] = $formsBundle['forms_by_type'];
    // Request constructor already connects; close if available
    if (method_exists($request, 'close')) {
        $request->close();
    }
}

echo json_encode($hash, JSON_UNESCAPED_UNICODE);
