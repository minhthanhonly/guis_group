<?php
require_once __DIR__ . '/loader.php';

$controller->requiring();
require_once dirname(__DIR__) . '/application/model/timecard.php';
require_once __DIR__ . '/guis_plus_auth.php';

class TimecardAPI {
    public function handleRequest() {
        $authError = GuisPlusApiAuth::assertSecretOrFail();
        if ($authError) {
            return $authError;
        }

        $method = $_GET['method'] ?? $_POST['method'] ?? 'status';
        switch ($method) {
            case 'status':
                return $this->getStatus();
            case 'checkin':
                return $this->checkin();
            case 'checkout':
                return $this->checkout();
            default:
                http_response_code(400);
                return ['success' => false, 'error' => 'Invalid method'];
        }
    }

    private function getUserId() {
        return trim((string) ($_GET['user_id'] ?? $_POST['user_id'] ?? ''));
    }

    private function validateUser($user_id) {
        if ($user_id === '') {
            return null;
        }

        $appModel = new ApplicationModel();
        $appModel->connect();
        $uidEsc = $appModel->quote($user_id);
        $user = $appModel->fetchOne(
            "SELECT userid, realname, lastname, firstname, user_group FROM "
            . DB_PREFIX . "user WHERE userid = '" . $uidEsc
            . "' AND (`is_suspend` = '' OR `is_suspend` IS NULL OR is_suspend = '0') LIMIT 1"
        );
        $appModel->close();
        return $user ?: null;
    }

    private function canUseTimecard($user) {
        $group = (string) ($user['user_group'] ?? '');
        return $group !== '6' && $group !== '7';
    }

    private function getTodayTimecard($user_id) {
        $appModel = new ApplicationModel();
        $appModel->connect();
        $uidEsc = $appModel->quote($user_id);
        $dateEsc = $appModel->quote(date('Y-m-d'));
        $timecard = $appModel->fetchOne(
            "SELECT id, timecard_open, timecard_close, timecard_time, timecard_timeover, timecard_timeinterval "
            . "FROM " . DB_PREFIX . "timecard WHERE timecard_date = '" . $dateEsc
            . "' AND owner = '" . $uidEsc . "' LIMIT 1"
        );
        $appModel->close();
        return $timecard ?: null;
    }

    private function formatTimecard($timecard) {
        if (!$timecard || !is_array($timecard)) {
            return null;
        }

        return [
            'id' => isset($timecard['id']) ? (int) $timecard['id'] : 0,
            'timecard_open' => (string) ($timecard['timecard_open'] ?? ''),
            'timecard_close' => (string) ($timecard['timecard_close'] ?? ''),
            'timecard_time' => (string) ($timecard['timecard_time'] ?? ''),
            'timecard_timeover' => (string) ($timecard['timecard_timeover'] ?? ''),
            'timecard_timeinterval' => (string) ($timecard['timecard_timeinterval'] ?? ''),
        ];
    }

    private function getStatus() {
        $user_id = $this->getUserId();
        if ($user_id === '') {
            http_response_code(400);
            return ['success' => false, 'error' => 'Missing user_id'];
        }

        $user = $this->validateUser($user_id);
        if (!$user) {
            http_response_code(401);
            return ['success' => false, 'error' => 'Invalid user_id'];
        }

        $canUse = $this->canUseTimecard($user);
        $timecard = $canUse ? $this->getTodayTimecard($user_id) : null;

        return [
            'success' => true,
            'can_use_timecard' => $canUse,
            'user' => [
                'userid' => (string) $user['userid'],
                'realname' => (string) ($user['realname'] ?? ''),
                'lastname' => (string) ($user['lastname'] ?? ''),
                'firstname' => (string) ($user['firstname'] ?? ''),
                'user_group' => (string) ($user['user_group'] ?? ''),
            ],
            'timecard' => $this->formatTimecard($timecard),
            'today' => date('Y-m-d'),
        ];
    }

    private function checkin() {
        $user_id = $this->getUserId();
        if ($user_id === '') {
            http_response_code(400);
            return ['success' => false, 'error' => 'Missing user_id'];
        }

        $user = $this->validateUser($user_id);
        if (!$user) {
            http_response_code(401);
            return ['success' => false, 'error' => 'Invalid user_id'];
        }
        if (!$this->canUseTimecard($user)) {
            http_response_code(403);
            return ['success' => false, 'error' => 'Timecard not available for this user'];
        }

        $_POST['owner'] = $user_id;
        $timecardModel = new Timecard();
        $timecardModel->connect();
        $result = $timecardModel->checkin();
        $timecardModel->close();

        if (!is_array($result) || ($result['status'] ?? '') !== 'success') {
            return [
                'success' => false,
                'error' => (string) ($result['message_code'] ?? 'Check-in failed'),
            ];
        }

        return [
            'success' => true,
            'message' => (string) ($result['message_code'] ?? ''),
            'timecard_id' => (int) ($result['timecard_id'] ?? 0),
            'timecard_open' => (string) ($result['timecard_open'] ?? ''),
            'timecard' => $this->formatTimecard($this->getTodayTimecard($user_id)),
        ];
    }

    private function checkout() {
        $user_id = $this->getUserId();
        $id = trim((string) ($_POST['id'] ?? $_GET['id'] ?? ''));
        $open = trim((string) ($_POST['open'] ?? $_GET['open'] ?? ''));

        if ($user_id === '') {
            http_response_code(400);
            return ['success' => false, 'error' => 'Missing user_id'];
        }
        if ($id === '' || $open === '') {
            http_response_code(400);
            return ['success' => false, 'error' => 'Missing timecard id or open time'];
        }

        $user = $this->validateUser($user_id);
        if (!$user) {
            http_response_code(401);
            return ['success' => false, 'error' => 'Invalid user_id'];
        }
        if (!$this->canUseTimecard($user)) {
            http_response_code(403);
            return ['success' => false, 'error' => 'Timecard not available for this user'];
        }

        $_POST['owner'] = $user_id;
        $_POST['id'] = $id;
        $_POST['open'] = $open;

        $timecardModel = new Timecard();
        $timecardModel->connect();
        $result = $timecardModel->checkout();
        $timecardModel->close();

        if (!is_array($result) || ($result['status'] ?? '') !== 'success') {
            return [
                'success' => false,
                'error' => (string) ($result['message_code'] ?? 'Check-out failed'),
            ];
        }

        return [
            'success' => true,
            'message' => (string) ($result['message_code'] ?? ''),
            'timecard_id' => (int) ($result['timecard_id'] ?? 0),
            'timecard_open' => (string) ($result['timecard_open'] ?? ''),
            'timecard_close' => (string) ($result['timecard_close'] ?? ''),
            'timecard_time' => (string) ($result['timecard_time'] ?? ''),
            'timecard_timeover' => (string) ($result['timecard_timeover'] ?? ''),
            'timecard_timeinterval' => (string) ($result['timecard_timeinterval'] ?? ''),
            'timecard' => $this->formatTimecard($this->getTodayTimecard($user_id)),
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $api = new TimecardAPI();
    $result = $api->handleRequest();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}
?>
