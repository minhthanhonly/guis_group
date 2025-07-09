<?php
require_once(DIR_MODEL . 'notification.php');
require_once(DIR_LIBRARY . 'firebase_helper.php');

class NotificationService {
    private $notificationModel;
    private $firebase;

    public function __construct() {
        $this->notificationModel = new Notification();
        $this->firebase = new FirebaseHelper();
    }

    /**
     * Tạo notification, mapping user nhận, gửi event lên Firebase
     * @param array $params (event, title, message, data, project_id, task_id, request_id, user_ids)
     * @return array
     */
    public function create($params) {
        $event = $params['event'] ?? '';
        $title = $params['title'] ?? '';
        $message = $params['message'] ?? '';
        $data = $params['data'] ?? null;
        $project_id = $params['project_id'] ?? null;
        $task_id = $params['task_id'] ?? null;
        $request_id = $params['request_id'] ?? null;
        $user_ids = $params['user_ids'] ?? [];
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
        // 3. Chỉ cập nhật meta cho user trên Firebase (không gửi nội dung)
        foreach ($user_ids as $uid) {
            $this->firebase->updateUserNotificationMeta($uid, [
                'last_notification_time' => time(),
                'last_notification_id' => $notification_id
            ]);
        }
        return ['success' => true, 'notification_id' => $notification_id];
    }

    function sendProjectCommentNotification($project_id, $comment_id) {
        $this->firebase->sendNotification('project_' . $project_id, 'new-comment', [
            'comment_id' => $comment_id,
            'project_id' => $project_id
        ]);
        return ['success' => true];
    }

    function sendTaskCommentNotification($task_id, $comment_id) {
        $this->firebase->sendNotification('task_' . $task_id, 'new-comment', [
            'comment_id' => $comment_id,
            'task_id' => $task_id
        ]);
        return ['success' => true];
    }
} 