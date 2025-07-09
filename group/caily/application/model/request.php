<?php

class Request extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'requests';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'user_id' => array(),
            'type' => array(),
            'data' => array(),
            'status' => array(),
            'approver_id' => array(),
            'approved_at' => array(),
            'history' => array(),
            'comments' => array(),
            'created_at' => array('except' => array('search')),
            'updated_at' => array('except' => array('search'))
        );
        $this->connect();
    }

    // Validate dữ liệu đầu vào cho từng loại đơn
    function validate_request($type, $data) {
        $errors = array();
        if ($type == 'leave') {
            if (empty($data['start_datetime'])) $errors[] = '開始日時を入力してください。';
            if (empty($data['end_datetime'])) $errors[] = '終了日時を入力してください。';
            if (!empty($data['start_datetime']) && !empty($data['end_datetime'])) {
                if (strtotime($data['start_datetime']) >= strtotime($data['end_datetime'])) {
                    $errors[] = '終了日時は開始日時より後にしてください。';
                }
            }
            if (empty($data['days']) || !is_numeric($data['days']) || floatval($data['days']) <= 0) $errors[] = '日間は0より大きい値を入力してください。';
            if (empty($data['leave_type'])) $errors[] = '休暇種別を選択してください。';
            if ($data['leave_type'] === 'paid' && empty($data['paid_type'])) $errors[] = '有給休暇の種類を選択してください。';
            if ($data['leave_type'] === 'unpaid' && empty($data['unpaid_type'])) $errors[] = '無給休暇の種類を選択してください。';
        }
        // Có thể bổ sung validate cho các loại khác ở đây
        return $errors;
    }

    // Thêm mới đơn
    function add() {
        if (empty($_SESSION['userid'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ユーザー情報がありません。']);
            exit;
        }
        if (empty($_POST['type'])) {
            http_response_code(400);
            echo json_encode(['error' => 'typeが必要です。']);
            exit;
        }
        if (!isset($_POST['data'])) {
            http_response_code(400);
            echo json_encode(['error' => 'dataが必要です。']);
            exit;
        }
        $type = $_POST['type'];
        $data = is_array($_POST['data']) ? $_POST['data'] : json_decode($_POST['data'], true);
        if (!is_array($data)) $data = [];
        $status = isset($_POST['status']) ? $_POST['status'] : 'pending';
        // Chỉ validate khi gửi đăng ký (pending), không validate khi lưu nháp (draft)
        if ($status === 'pending') {
            $errors = $this->validate_request($type, $data);
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['error' => $errors]);
                exit;
            }
        }
        $row = array(
            'user_id' => $_SESSION['userid'],
            'type' => $type,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'status' => $status,
            'history' => json_encode([
                [
                    'action' => 'created',
                    'user' => $_SESSION['userid'],
                    'time' => date('Y-m-d H:i:s'),
                    'note' => ($status === 'draft' ? '下書き保存' : 'Khởi tạo')
                ]
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
        $result = $this->query_insert($row);
        
        // Send Pusher notification if request was created successfully
        if ($result && $status === 'pending') {
            $this->sendRequestCreatedNotification($result, $type, $_SESSION['userid']);
        }
        
        return $result;
    }

    // Lấy danh sách đơn (có thể lọc theo type, user, status)
    function list() {
        $where = [];
        if (!empty($_GET['type'])) {
            $where[] = "type = '" . $this->quote($_GET['type']) . "'";
        }
        if (!empty($_GET['user_id'])) {
            $where[] = "user_id = '" . $this->quote($_GET['user_id']) . "'";
        }
        if (!empty($_GET['status'])) {
            $where[] = "status = '" . $this->quote($_GET['status']) . "'";
        }
        $whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
        $query = "SELECT * FROM {$this->table} $whereSql ORDER BY created_at DESC";
        $rows = $this->fetchAll($query);
        // Parse JSON fields
        foreach ($rows as &$row) {
            $row['data'] = json_decode($row['data'], true);
            $row['history'] = json_decode($row['history'], true);
            $row['comments'] = json_decode($row['comments'], true);
        }
        return $rows;
    }

    // Thêm comment
    function add_comment() {
        if (empty($_SESSION['userid'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ユーザー情報がありません。']);
            exit;
        }
        if (empty($_POST['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'idが必要です。']);
            exit;
        }
        if (!isset($_POST['message']) || trim($_POST['message']) === '') {
            http_response_code(400);
            echo json_encode(['error' => 'コメント内容が必要です。']);
            exit;
        }
        $id = intval($_POST['id']);
        $comment = [
            'user_id' => $_SESSION['userid'],
            'message' => $_POST['message'],
            'date' => date('Y-m-d H:i:s')
        ];
        $row = $this->fetchOne("SELECT comments, type, user_id FROM {$this->table} WHERE id = $id");
        $comments = $row && $row['comments'] ? json_decode($row['comments'], true) : [];
        $comments[] = $comment;
        $result = $this->query_update(['comments' => json_encode($comments, JSON_UNESCAPED_UNICODE)], ['id' => $id]);
        
        // Send Pusher notification for comment added
        if ($result && $row) {
            $this->sendRequestCommentNotification($id, $row['type'], $row['user_id'], $_SESSION['userid']);
        }
        
        return $result;
    }

    // Cập nhật trạng thái và lịch sử
    function update_status() {
        if (empty($_SESSION['userid'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ユーザー情報がありません。']);
            exit;
        }
        if (empty($_POST['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'idが必要です。']);
            exit;
        }
        $id = intval($_POST['id']);
        $status = $_POST['status'];
        $note = isset($_POST['note']) ? $_POST['note'] : '';
        $user = $_SESSION['userid'];
        
        // Get current request info for notifications
        $currentRequest = $this->fetchOne("SELECT type, user_id FROM {$this->table} WHERE id = $id");
        
        $row = $this->fetchOne("SELECT history FROM {$this->table} WHERE id = $id");
        $history = $row && $row['history'] ? json_decode($row['history'], true) : [];
        $history[] = [
            'action' => $status,
            'user' => $user,
            'time' => date('Y-m-d H:i:s'),
            'note' => $note
        ];
        $update = [
            'status' => $status,
            'history' => json_encode($history, JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        if ($status === 'approved') {
            $update['approver_id'] = $user;
            $update['approved_at'] = date('Y-m-d H:i:s');
        }
        $result = $this->query_update($update, ['id' => $id]);
        
        // Send Pusher notification for status change
        if ($result && $currentRequest && in_array($status, ['approved', 'rejected'])) {
            $this->sendRequestStatusNotification($id, $currentRequest['type'], $status, $currentRequest['user_id'], $user, $status);
        }
        
        return $result;
    }

    // Lấy chi tiết đơn
    function get() {
        $id = intval($_GET['id']);
        $row = $this->fetchOne("SELECT * FROM {$this->table} WHERE id = $id");
        if ($row) {
            $row['data'] = json_decode($row['data'], true);
            $row['history'] = json_decode($row['history'], true);
            $row['comments'] = json_decode($row['comments'], true);
            // Lấy danh sách user_id xuất hiện trong history và comments
            $user_ids = array();
            if (is_array($row['history'])) {
                foreach ($row['history'] as $h) if (!empty($h['user'])) $user_ids[] = $h['user'];
            }
            if (is_array($row['comments'])) {
                foreach ($row['comments'] as $c) if (!empty($c['user_id'])) $user_ids[] = $c['user_id'];
            }
            // Thêm user_id của người đăng ký
            if (!empty($row['user_id'])) $user_ids[] = $row['user_id'];
            $user_ids = array_unique($user_ids);
            if (count($user_ids)) {
                $in = "'" . implode("','", array_map([$this, 'quote'], $user_ids)) . "'";
                $users = $this->fetchAll("SELECT userid, realname, user_image FROM ".DB_PREFIX."user WHERE userid IN ($in)");
                $user_map = array();
                foreach ($users as $u) {
                    $user_map[$u['userid']] = array('realname' => $u['realname'], 'user_image' => $u['user_image']);
                }
                // Gán realname, user_image vào history
                if (is_array($row['history'])) {
                    foreach ($row['history'] as &$h) {
                        if (!empty($user_map[$h['user']])) {
                            $h['realname'] = $user_map[$h['user']]['realname'];
                            $h['user_image'] = $user_map[$h['user']]['user_image'];
                        }
                    }
                }
                // Gán realname, user_image vào comments
                if (is_array($row['comments'])) {
                    foreach ($row['comments'] as &$c) {
                        if (!empty($user_map[$c['user_id']])) {
                            $c['realname'] = $user_map[$c['user_id']]['realname'];
                            $c['user_image'] = $user_map[$c['user_id']]['user_image'];
                        }
                    }
                }
                // Gán realname, user_image cho người đăng ký
                if (!empty($row['user_id']) && !empty($user_map[$row['user_id']])) {
                    $row['realname'] = $user_map[$row['user_id']]['realname'];
                    $row['user_image'] = $user_map[$row['user_id']]['user_image'];
                }
            }
        }
        return $row;
    }

    // Sửa đơn
    function edit() {
        if (empty($_SESSION['userid'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ユーザー情報がありません。']);
            exit;
        }
        if (empty($_POST['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'idが必要です。']);
            exit;
        }
        $id = intval($_POST['id']);
        $row = $this->fetchOne("SELECT * FROM {$this->table} WHERE id = $id");
        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => '申請が見つかりません。']);
            exit;
        }
        // Chỉ cho phép người đăng ký hoặc administrator sửa
        if ($_SESSION['userid'] !== $row['user_id'] && $_SESSION['authority'] !== 'administrator') {
            http_response_code(403);
            echo json_encode(['error' => '編集権限がありません。']);
            exit;
        }
        if (!isset($_POST['data'])) {
            http_response_code(400);
            echo json_encode(['error' => 'dataが必要です。']);
            exit;
        }
        $data = is_array($_POST['data']) ? $_POST['data'] : json_decode($_POST['data'], true);
        if (!is_array($data)) $data = [];
        // Validate nếu trạng thái là pending
        $status = $row['status'];
        if ($status === 'pending') {
            $errors = $this->validate_request($row['type'], $data);
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['error' => $errors]);
                exit;
            }
        }
        // Cập nhật history
        $history = $row['history'] ? json_decode($row['history'], true) : [];
        $history[] = [
            'action' => 'edited',
            'user' => $_SESSION['userid'],
            'time' => date('Y-m-d H:i:s'),
            'note' => '内容を編集'
        ];
        $update = [
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'history' => json_encode($history, JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $result = $this->query_update($update, ['id' => $id]);
        
        // Send Pusher notification for request update
        if ($result) {
            $this->sendRequestUpdatedNotification($id, $row['type'], $row['status'], $row['user_id']);
        }
        
        return $result;
    }

    // Firebase notification methods (NEW: dùng NotificationService)
    private function sendRequestCreatedNotification($requestId, $requestType, $userId) {
        try {
            require_once(DIR_MODEL . 'NotificationService.php');
            $notiService = new NotificationService();
            // Lấy danh sách admin
            $admins = $this->fetchAll("SELECT userid FROM ".DB_PREFIX."user WHERE authority = 'administrator' AND (is_suspend IS NULL OR is_suspend = 0)");
            $admin_ids = array_map(function($a){return $a['userid'];}, $admins);
            if (empty($admin_ids)) return;
            $payload = [
                'event' => 'form_request_update',
                'title' => '申請作成',
                'message' => '新しい申請が作成されました',
                'data' => [
                    'request_id' => $requestId,
                    'request_type' => $requestType,
                    'user_id' => $userId,
                    'action' => 'created',
                    'url' => "/form/detail.php?id=$requestId"
                ],
                'request_id' => $requestId,
                'user_ids' => $admin_ids
            ];
            $notiService->create($payload);
        } catch (Exception $e) {
            error_log('Failed to send request created notification: ' . $e->getMessage());
        }
    }

    private function sendRequestUpdatedNotification($requestId, $requestType, $status, $userId) {
        try {
            require_once(DIR_MODEL . 'NotificationService.php');
            $notiService = new NotificationService();
            $admins = $this->fetchAll("SELECT userid FROM ".DB_PREFIX."user WHERE authority = 'administrator' AND (is_suspend IS NULL OR is_suspend = 0)");
            $admin_ids = array_map(function($a){return $a['userid'];}, $admins);
            if (empty($admin_ids)) return;
            $payload = [
                'event' => 'form_request_update',
                'title' => '申請更新',
                'message' => '申請が更新されました',
                'data' => [
                    'request_id' => $requestId,
                    'request_type' => $requestType,
                    'status' => $status,
                    'user_id' => $userId,
                    'action' => 'updated',
                    'url' => "/form/detail.php?id=$requestId"
                ],
                'request_id' => $requestId,
                'user_ids' => $admin_ids
            ];
            $notiService->create($payload);
        } catch (Exception $e) {
            error_log('Failed to send request updated notification: ' . $e->getMessage());
        }
    }

    private function sendRequestStatusNotification($requestId, $requestType, $status, $userId, $actionUser, $action) {
        try {
            require_once(DIR_MODEL . 'NotificationService.php');
            $notiService = new NotificationService();
            // Gửi cho chủ đơn
            $payload_user = [
                'event' => 'form_request_update',
                'title' => '申請ステータス',
                'message' => '申請のステータスが変更されました',
                'data' => [
                    'request_id' => $requestId,
                    'request_type' => $requestType,
                    'status' => $status,
                    'action_user' => $actionUser,
                    'action' => $action,
                    'url' => "/form/detail.php?id=$requestId"
                ],
                'request_id' => $requestId,
                'user_ids' => [$userId]
            ];
            $notiService->create($payload_user);
            // Gửi cho admin
            $admins = $this->fetchAll("SELECT userid FROM ".DB_PREFIX."user WHERE authority = 'administrator' AND (is_suspend IS NULL OR is_suspend = 0)");
            $admin_ids = array_map(function($a){return $a['userid'];}, $admins);
            if (!empty($admin_ids)) {
                $payload_admin = $payload_user;
                $payload_admin['user_ids'] = $admin_ids;
                $notiService->create($payload_admin);
            }
        } catch (Exception $e) {
            error_log('Failed to send request status notification: ' . $e->getMessage());
        }
    }

    private function sendRequestCommentNotification($requestId, $requestType, $userId, $commentUserId) {
        try {
            require_once(DIR_MODEL . 'NotificationService.php');
            $notiService = new NotificationService();
            // Gửi cho chủ đơn nếu người comment khác chủ đơn
            if ($userId !== $commentUserId) {
                $payload_user = [
                    'event' => 'form_comment',
                    'title' => '新しいコメント',
                    'message' => '申請に新しいコメントが追加されました',
                    'data' => [
                        'request_id' => $requestId,
                        'request_type' => $requestType,
                        'comment_user_id' => $commentUserId,
                        'url' => "/form/detail.php?id=$requestId"
                    ],
                    'request_id' => $requestId,
                    'user_ids' => [$userId]
                ];
                $notiService->create($payload_user);
            }
            // Gửi cho admin
            $admins = $this->fetchAll("SELECT userid FROM ".DB_PREFIX."user WHERE authority = 'administrator' AND (is_suspend IS NULL OR is_suspend = 0)");
            $admin_ids = array_map(function($a){return $a['userid'];}, $admins);
            if (!empty($admin_ids)) {
                $payload_admin = [
                    'event' => 'form_comment',
                    'title' => '新しいコメント',
                    'message' => '申請に新しいコメントが追加されました',
                    'data' => [
                        'request_id' => $requestId,
                        'request_type' => $requestType,
                        'user_id' => $userId,
                        'comment_user_id' => $commentUserId,
                        'url' => "/form/detail.php?id=$requestId"
                    ],
                    'request_id' => $requestId,
                    'user_ids' => $admin_ids
                ];
                $notiService->create($payload_admin);
            }
        } catch (Exception $e) {
            error_log('Failed to send request comment notification: ' . $e->getMessage());
        }
    }

} 