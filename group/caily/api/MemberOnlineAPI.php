<?php
require_once __DIR__ . '/loader.php';

// Load framework classes/config without calling auth gate.
$controller->requiring();
require_once __DIR__ . '/guis_plus_auth.php';

class MemberOnlineAPI {
    public function handleRequest() {
        $authError = GuisPlusApiAuth::assertSecretOrFail();
        if ($authError) {
            return $authError;
        }

        $method = $_GET['method'] ?? 'list';
        switch ($method) {
            case 'list':
                return $this->listMembers();
            default:
                http_response_code(400);
                return ['error' => 'Invalid method'];
        }
    }

    /**
     * Public endpoint for desktop app.
     * Query: user_id (required)
     * Online status is determined client-side via Firebase.
     */
    private function listMembers() {
        $user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? '';
        if (empty($user_id)) {
            http_response_code(400);
            return ['error' => 'Missing user_id'];
        }

        $appModel = new ApplicationModel();
        $appModel->connect();

        $uidEsc = $appModel->quote($user_id);
        $requester = $appModel->fetchOne(
            "SELECT userid FROM " . DB_PREFIX . "user WHERE userid = '" . $uidEsc . "' AND (`is_suspend` = '' OR `is_suspend` IS NULL OR is_suspend = '0') LIMIT 1"
        );
        if (!$requester) {
            http_response_code(401);
            return ['error' => 'Invalid user_id'];
        }

        $members = $appModel->getUserList();
        $root = defined('ROOT') ? ROOT : '/';

        $out = [];
        foreach ($members as $member) {
            $memberId = (string) ($member['userid'] ?? '');
            if ($memberId === '') {
                continue;
            }

            $avatarPath = !empty($member['user_image'])
                ? $root . 'assets/upload/avatar/' . $member['user_image']
                : $root . 'assets/img/avatars/1.png';

            $out[] = [
                'userid' => $memberId,
                'realname' => (string) ($member['realname'] ?? ''),
                'user_groupname' => (string) ($member['user_groupname'] ?? ''),
                'avatar_url' => $avatarPath,
            ];
        }

        usort($out, function ($a, $b) {
            return strcasecmp($a['realname'], $b['realname']);
        });

        return [
            'success' => true,
            'members' => $out,
            'total' => count($out),
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $api = new MemberOnlineAPI();
    $result = $api->handleRequest();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}
?>
