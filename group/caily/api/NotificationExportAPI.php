<?php
require_once __DIR__ . '/loader.php';

// Load framework classes/config without calling auth gate.
$controller->requiring();
require_once dirname(__DIR__) . '/application/model/notification.php';

class NotificationExportAPI {
    private $notificationModel;

    public function __construct() {
        $this->notificationModel = new Notification();
    }

    public function handleRequest() {
        $method = $_GET['method'] ?? 'export_notifications';
        switch ($method) {
            case 'export_notifications':
                return $this->exportNotifications();
            case 'mark_read':
                return $this->markRead();
            case 'mark_read_multi':
                return $this->markReadMulti();
            case 'mark_unread':
                return $this->markUnread();
            default:
                return ['error' => 'Invalid method'];
        }
    }

    /**
     * Public export endpoint (no login required).
     * Query: user_id (required), limit, offset, category, download=1.
     */
    private function exportNotifications() {
        $user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? '';
        if (empty($user_id)) {
            http_response_code(400);
            return ['error' => 'Missing user_id'];
        }

        if (isset($_GET['download']) && $_GET['download'] == '1') {
            header('Content-Disposition: attachment; filename="notifications-export.json"');
        }

        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 500;
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 5000) {
            $limit = 5000;
        }

        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        if ($offset < 0) {
            $offset = 0;
        }

        $category = isset($_GET['category']) ? strtolower(trim($_GET['category'])) : 'all';
        if (!in_array($category, ['all', 'project', 'soumu'], true)) {
            $category = 'all';
        }

        $catSql = '';
        if ($category === 'soumu') {
            $catSql = " AND (COALESCE(n.event,'') LIKE 'form%' OR COALESCE(n.event,'') LIKE 'other%') ";
        } elseif ($category === 'project') {
            $catSql = " AND NOT (COALESCE(n.event,'') LIKE 'form%' OR COALESCE(n.event,'') LIKE 'other%') ";
        }

        $uidEsc = $this->notificationModel->quote($user_id);

        $countSql = "SELECT COUNT(*) AS cnt
                FROM notification_user nu
                INNER JOIN notification n ON nu.notification_id = n.id
                WHERE nu.user_id = '" . $uidEsc . "' " . $catSql;
        $countRow = $this->notificationModel->fetchOne($countSql);
        $total = isset($countRow['cnt']) ? (int) $countRow['cnt'] : 0;

        $sql = "SELECT n.*, nu.is_read, nu.read_at
                FROM notification_user nu
                INNER JOIN notification n ON nu.notification_id = n.id
                WHERE nu.user_id = '" . $uidEsc . "' " . $catSql . "
                ORDER BY n.created_at DESC
                LIMIT $limit OFFSET $offset";

        $list = $this->notificationModel->fetchAll($sql);
        $out = [];
        foreach ($list as $row) {
            $ev = isset($row['event']) ? (string) $row['event'] : '';
            $tab = (strpos($ev, 'form') === 0 || strpos($ev, 'other') === 0) ? 'soumu' : 'project';
            $dataRaw = $row['data'] ?? null;
            $dataParsed = null;
            if ($dataRaw !== null && $dataRaw !== '') {
                $decoded = json_decode($dataRaw, true);
                $dataParsed = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $dataRaw;
            }

            $row['category'] = $tab;
            $row['data_parsed'] = $dataParsed;
            $out[] = $row;
        }

        return [
            'success' => true,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'category' => $category,
            'notifications' => $out,
        ];
    }

    private function markRead() {
        $user_id = $_POST['user_id'] ?? $_GET['user_id'] ?? '';
        $notification_id = $_POST['notification_id'] ?? $_GET['notification_id'] ?? 0;
        if (empty($user_id) || empty($notification_id)) {
            http_response_code(400);
            return ['error' => 'Missing user_id or notification_id'];
        }

        $sql = sprintf(
            "UPDATE notification_user SET is_read = 1, read_at = NOW() WHERE notification_id = %d AND user_id = '%s'",
            intval($notification_id),
            $this->notificationModel->quote($user_id)
        );
        $this->notificationModel->query($sql);
        return ['success' => true];
    }

    private function markReadMulti() {
        $user_id = $_POST['user_id'] ?? $_GET['user_id'] ?? '';
        $notification_ids = $_POST['notification_ids'] ?? $_GET['notification_ids'] ?? [];
        if (is_string($notification_ids)) {
            $notification_ids = explode(',', $notification_ids);
        }
        if (empty($user_id) || empty($notification_ids) || !is_array($notification_ids)) {
            http_response_code(400);
            return ['error' => 'Missing user_id or notification_ids'];
        }

        $ids = array_map('intval', $notification_ids);
        $ids = array_values(array_filter($ids, function ($id) { return $id > 0; }));
        if (empty($ids)) {
            http_response_code(400);
            return ['error' => 'Invalid notification_ids'];
        }
        $ids_str = implode(',', $ids);
        $sql = sprintf(
            "UPDATE notification_user SET is_read = 1, read_at = NOW() WHERE user_id = '%s' AND notification_id IN (%s)",
            $this->notificationModel->quote($user_id),
            $ids_str
        );
        $this->notificationModel->query($sql);
        return ['success' => true];
    }

    private function markUnread() {
        $user_id = $_POST['user_id'] ?? $_GET['user_id'] ?? '';
        $notification_id = $_POST['notification_id'] ?? $_GET['notification_id'] ?? 0;
        if (empty($user_id) || empty($notification_id)) {
            http_response_code(400);
            return ['error' => 'Missing user_id or notification_id'];
        }

        $sql = sprintf(
            "UPDATE notification_user SET is_read = 0, read_at = NULL WHERE notification_id = %d AND user_id = '%s'",
            intval($notification_id),
            $this->notificationModel->quote($user_id)
        );
        $this->notificationModel->query($sql);
        return ['success' => true];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $api = new NotificationExportAPI();
    $result = $api->handleRequest();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}
?>
