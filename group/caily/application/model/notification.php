<?php

// Model cho bảng notification (chỉ lưu nội dung, không lưu user nhận trực tiếp)
class Notification extends ApplicationModel {
    function __construct() {
        $this->table = 'notification';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'event' => array('notnull'),   // Tên event (project_update, task_update...)
            'title' => array(),            // Tiêu đề thông báo
            'message' => array(),          // Nội dung thông báo
            'data' => array(),             // Dữ liệu phụ (json)
            'project_id' => array(),       // id dự án liên quan (nếu có)
            'task_id' => array(),          // id task liên quan (nếu có)
            'request_id' => array(),       // id request liên quan (nếu có)
            'created_at' => array('except' => array('search')),
        );
        $this->connect();
    }

    // Các hàm thao tác sẽ được cập nhật lại để sử dụng bảng notification_user cho mapping user nhận và trạng thái đã đọc
} 