<?php
require_once '../application/loader.php';
require_once '../application/library/firebase_helper.php';
require_once '../application/model/notification.php';

class NotificationAPI {
    private $firebase;
    private $notificationModel;
    
    public function __construct() {
        $this->firebase = new FirebaseHelper();
        $this->notificationModel = new Notification();
    }
    
    public function handleRequest() {
        $method = $_GET['method'] ?? '';
        switch ($method) {
            case 'test':
                return $this->testConnection();
            case 'send':
                return $this->sendNotification();
            case 'get_config':
                return $this->getConfig();
            case 'create_notification':
                return $this->createNotification();
            case 'get_notifications':
                return $this->getNotifications();
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
    
    private function testConnection() {
        $result = $this->firebase->testConnection();
        return $result;
    }
    
    private function sendNotification() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $input = $_POST;
        }
        
        $channel = $input['channel'] ?? 'global';
        $event = $input['event'] ?? 'notification';
        $data = $input['data'] ?? [];
        
        $success = $this->firebase->sendNotification($channel, $event, $data);
        
        if ($success) {
            return ['success' => true, 'message' => 'Notification sent'];
        } else {
            return ['error' => 'Failed to send notification'];
        }
    }
    
    public function createNotification($input = null) {
        if ($input === null) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }
        }
        $event = $input['event'] ?? '';
        $title = $input['title'] ?? '';
        $message = $input['message'] ?? '';
        $data = $input['data'] ?? null;
        $project_id = $input['project_id'] ?? null;
        $task_id = $input['task_id'] ?? null;
        $request_id = $input['request_id'] ?? null;
        $user_ids = $input['user_ids'] ?? [];
        if (!is_array($user_ids)) $user_ids = [$user_ids];
        if (empty($event) || empty($user_ids)) {
            return ['error' => 'Missing event or user_ids'];
        }
        // 1. Tạo notification
        $notification_id = $this->notificationModel->query(
            sprintf(
                "INSERT INTO %s (event, title, message, data, project_id, task_id, request_id, created_at) VALUES ('%s', '%s', '%s', '%s', %s, %s, %s, NOW())",
                $this->notificationModel->table,
                $this->notificationModel->quote($event),
                $this->notificationModel->quote($title),
                $this->notificationModel->quote($message),
                $this->notificationModel->quote(is_array($data) ? json_encode($data) : $data),
                $project_id === null ? 'NULL' : intval($project_id),
                $task_id === null ? 'NULL' : intval($task_id),
                $request_id === null ? 'NULL' : intval($request_id)
            )
        );
        $notification_id = $this->notificationModel->handler->insert_id;
        // 2. Mapping user nhận
        $values = [];
        foreach ($user_ids as $uid) {
            $uid = trim($uid);
            if ($uid !== '') {
                $values[] = sprintf("(%d, '%s', 0, NULL)", $notification_id, $this->notificationModel->quote($uid));
            }
        }
        if (!empty($values)) {
            $sql = "INSERT INTO notification_user (notification_id, user_id, is_read, read_at) VALUES " . implode(",", $values);
            $this->notificationModel->query($sql);
        }
        // 3. Gửi event lên Firebase cho từng user
        foreach ($user_ids as $uid) {
            $eventData = [
                'event' => $event,
                'notification_id' => $notification_id,
                'user_id' => $uid
            ];
            $this->firebase->sendToUser($uid, $event, $eventData);
        }
        return ['success' => true, 'notification_id' => $notification_id];
    }
    
    private function getNotifications() {
        $user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? '';
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        if (empty($user_id)) {
            return ['error' => 'Missing user_id'];
        }
        $sql = "SELECT n.*, nu.is_read, nu.read_at FROM notification_user nu JOIN notification n ON nu.notification_id = n.id WHERE nu.user_id = '" . $this->notificationModel->quote($user_id) . "' ORDER BY n.created_at DESC LIMIT $limit";
        $list = $this->notificationModel->fetchAll($sql);
        return ['notifications' => $list];
    }
    
    private function markRead() {
        $user_id = $_POST['user_id'] ?? '';
        $notification_id = $_POST['notification_id'] ?? 0;
        if (empty($user_id) || empty($notification_id)) {
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
        $user_id = $_POST['user_id'] ?? '';
        $notification_ids = $_POST['notification_ids'] ?? [];
        if (is_string($notification_ids)) {
            $notification_ids = explode(',', $notification_ids);
        }
        if (empty($user_id) || empty($notification_ids) || !is_array($notification_ids)) {
            return ['error' => 'Missing user_id or notification_ids'];
        }
        $ids = array_map('intval', $notification_ids);
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
        $user_id = $_POST['user_id'] ?? '';
        $notification_id = $_POST['notification_id'] ?? 0;
        if (empty($user_id) || empty($notification_id)) {
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
    
    private function getConfig() {
        $config = EnvConfig::getFirebaseConfig();
        
        // Only return public config (no sensitive data)
        return [
            'apiKey' => $config['apiKey'],
            'authDomain' => $config['authDomain'],
            'projectId' => $config['projectId'],
            'storageBucket' => $config['storageBucket'],
            'messagingSenderId' => $config['messagingSenderId'],
            'appId' => $config['appId'],
            'databaseURL' => $config['databaseURL']
        ];
    }
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $api = new NotificationAPI();
    $result = $api->handleRequest();
    
    header('Content-Type: application/json');
    echo json_encode($result);
}
?> 