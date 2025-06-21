<?php

class Notification extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'notification';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'target_type' => array('notnull'), // user, group, global
            'target_value' => array(),         // user_id, group_id, hoặc null
            'event' => array('notnull'),   // Tên event (project_update, task_update...)
            'title' => array(),            // Tiêu đề thông báo
            'message' => array(),          // Nội dung thông báo
            'data' => array(),             // Dữ liệu phụ (json)
            'project_id' => array(),           // id dự án liên quan (nếu có)
            'task_id' => array(),              // id task liên quan (nếu có)
            'request_id' => array(),           // id request liên quan (nếu có)
            'is_read' => array(),          // Đã đọc hay chưa (0/1)
            'created_at' => array('except' => array('search')), // Thời gian tạo
        );
        $this->connect();
    }

    // Lấy danh sách thông báo cho user (bao gồm: user, group, global)
    function getByUser($user_id, $user_groups = [], $limit = 20) {
        $user_id = $this->quote($user_id);
        $group_ids = array_map(function($g) { return "'".$this->quote($g)."'"; }, $user_groups);
        $group_cond = count($group_ids) > 0 ? ("OR (target_type = 'group' AND target_value IN (".implode(",", $group_ids)."))") : '';
        $query = sprintf(
            "SELECT * FROM %s WHERE (target_type = 'user' AND target_value = '%s') %s OR target_type = 'global' ORDER BY created_at DESC LIMIT %d",
            $this->table,
            $user_id,
            $group_cond,
            intval($limit)
        );
        return $this->fetchAll($query);
    }

    // Đánh dấu đã đọc
    function markAsRead($notification_id, $user_id) {
        $query = sprintf(
            "UPDATE %s SET is_read = 1 WHERE id = %d AND ((target_type = 'user' AND target_value = '%s') OR target_type = 'global')",
            $this->table,
            intval($notification_id),
            $this->quote($user_id)
        );
        return $this->query($query);
    }

    // Thêm thông báo mới
    function addNotification($target_type, $target_value, $event, $title = '', $message = '', $data = null, $project_id = null, $task_id = null, $request_id = null) {
        $query = sprintf(
            "INSERT INTO %s (target_type, target_value, event, title, message, data, project_id, task_id, request_id, is_read, created_at) VALUES ('%s', %s, '%s', '%s', '%s', '%s', %s, %s, %s, 0, NOW())",
            $this->table,
            $this->quote($target_type),
            $target_value === null ? 'NULL' : "'".$this->quote($target_value)."'",
            $this->quote($event),
            $this->quote($title),
            $this->quote($message),
            $this->quote(is_array($data) ? json_encode($data) : $data),
            $project_id === null ? 'NULL' : intval($project_id),
            $task_id === null ? 'NULL' : intval($task_id),
            $request_id === null ? 'NULL' : intval($request_id)
        );
        return $this->query($query);
    }
} 