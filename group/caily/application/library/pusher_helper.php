<?php

class PusherHelper {
    private static $pusher = null;
    
    private static function getConfig() {
        // Load environment config if not already loaded
        if (!class_exists('EnvConfig')) {
            require_once(DIR_ROOT . '/env_config.php');
        }
        return EnvConfig::getPusherConfig();
    }

    private static function getPusher() {
        if (self::$pusher === null) {
            $config = self::getConfig();
            require_once(DIR_ROOT . '/libs/pusher-php-server/src/Pusher.php');
            self::$pusher = new Pusher\Pusher(
                $config['key'],
                $config['secret'],
                $config['app_id'],
                [
                    'cluster' => $config['cluster'],
                    'useTLS' => $config['useTLS']
                ]
            );
        }
        return self::$pusher;
    }

    /**
     * Send a notification to all users
     */
    public static function sendNotification($event, $data) {
        try {
            $pusher = self::getPusher();
            $pusher->trigger('notifications', $event, $data);
            return true;
        } catch (Exception $e) {
            error_log('Pusher notification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to specific user
     */
    public static function sendToUser($userId, $event, $data) {
        try {
            $pusher = self::getPusher();
            $pusher->trigger('private-user-' . $userId, $event, $data);
            return true;
        } catch (Exception $e) {
            error_log('Pusher user notification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to administrators
     */
    public static function sendToAdmins($event, $data) {
        try {
            $pusher = self::getPusher();
            $pusher->trigger('private-admin', $event, $data);
            return true;
        } catch (Exception $e) {
            error_log('Pusher admin notification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send request created notification
     */
    public static function requestCreated($requestId, $requestType, $userId) {
        $data = [
            'request_id' => $requestId,
            'request_type' => $requestType,
            'user_id' => $userId,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        return self::sendNotification('request_created', $data);
    }

    /**
     * Send request updated notification
     */
    public static function requestUpdated($requestId, $requestType, $status, $userId) {
        $data = [
            'request_id' => $requestId,
            'request_type' => $requestType,
            'status' => $status,
            'user_id' => $userId,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        return self::sendNotification('request_updated', $data);
    }

    /**
     * Send request status changed notification
     */
    public static function requestStatusChanged($requestId, $requestType, $status, $userId, $actionUser, $action) {
        $data = [
            'request_id' => $requestId,
            'request_type' => $requestType,
            'status' => $status,
            'user_id' => $userId,
            'action_user' => $actionUser,
            'action' => $action,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        return self::sendNotification('request_status_changed', $data);
    }

    /**
     * Send request comment added notification
     */
    public static function requestCommentAdded($requestId, $requestType, $userId, $commentUserId) {
        $data = [
            'request_id' => $requestId,
            'request_type' => $requestType,
            'user_id' => $userId,
            'comment_user_id' => $commentUserId,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        return self::sendNotification('request_comment_added', $data);
    }

    /**
     * Send project updated notification
     */
    public static function projectUpdated($projectId, $projectName, $userId) {
        $data = [
            'project_id' => $projectId,
            'project_name' => $projectName,
            'user_id' => $userId,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        return self::sendNotification('project_updated', $data);
    }

    /**
     * Send task updated notification
     */
    public static function taskUpdated($taskId, $taskName, $projectId, $userId) {
        $data = [
            'task_id' => $taskId,
            'task_name' => $taskName,
            'project_id' => $projectId,
            'user_id' => $userId,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        return self::sendNotification('task_updated', $data);
    }

    /**
     * Send comment added notification
     */
    public static function commentAdded($taskId, $taskName, $userId, $commentUserId) {
        $data = [
            'task_id' => $taskId,
            'task_name' => $taskName,
            'user_id' => $userId,
            'comment_user_id' => $commentUserId,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        return self::sendNotification('comment_added', $data);
    }

    /**
     * Send time entry updated notification
     */
    public static function timeEntryUpdated($taskId, $taskName, $userId) {
        $data = [
            'task_id' => $taskId,
            'task_name' => $taskName,
            'user_id' => $userId,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        return self::sendNotification('time_entry_updated', $data);
    }

    /**
     * Send test notification
     */
    public static function testNotification($message = 'Test notification') {
        $data = [
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        return self::sendNotification('test_event', $data);
    }
} 