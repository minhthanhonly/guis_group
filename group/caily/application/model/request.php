<?php

class Request extends ApplicationModel {
    function __construct() {
        $this->table = DB_PREFIX . 'requests';
        $this->schema = array(
            'id' => array('except' => array('search')),
            'user_id' => array(),
            'type' => array(),
            'data' => array(),
            'start_date' => array(),
            'end_date' => array(),
            'status' => array(),
            'approver_id' => array(),
            'approver_user_id' => array(),
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
            if ($data['leave_type'] === '有給休暇' && empty($data['paid_type'])) {
                $errors[] = '有給休暇の種類を選択してください。';
            }
            if ($data['leave_type'] === '無給休暇' && empty($data['unpaid_type'])) {
                $errors[] = '無給休暇の種類を選択してください。';
            }
        } elseif ($type == 'outing') {
            if (empty($data['date'])) $errors[] = '日付を入力してください。';
            if (empty($data['start_time'])) $errors[] = '開始時刻を入力してください。';
            if (empty($data['end_time'])) $errors[] = '終了時刻を入力してください。';
            if (empty($data['destination'])) $errors[] = '行先を入力してください。';
            if (empty($data['reason'])) $errors[] = '事由を入力してください。';
        } elseif ($type == 'trip') {
            if (empty($data['start_datetime'])) $errors[] = '開始日時を入力してください。';
            if (empty($data['end_datetime'])) $errors[] = '終了日時を入力してください。';
            if (!empty($data['start_datetime']) && !empty($data['end_datetime']) && strtotime($data['start_datetime']) >= strtotime($data['end_datetime'])) {
                $errors[] = '終了日時は開始日時より後にしてください。';
            }
            if (empty($data['days']) || !is_numeric($data['days']) || floatval($data['days']) <= 0) $errors[] = '日間は0より大きい値を入力してください。';
            if (empty(trim($data['destination'] ?? ''))) $errors[] = '行先を入力してください。';
            if (empty(trim($data['reason'] ?? ''))) $errors[] = '事由を入力してください。';
        } elseif ($type == 'holiday_work') {
            if (empty($data['date'])) $errors[] = '日付を入力してください。';
            if (empty($data['start_time'])) $errors[] = '開始時刻を入力してください。';
            if (empty($data['end_time'])) $errors[] = '終了時刻を入力してください。';
            if (!empty($data['start_time']) && !empty($data['end_time']) && $data['start_time'] >= $data['end_time']) {
                $errors[] = '終了時刻は開始時刻より後にしてください。';
            }
            if (empty(trim($data['reason'] ?? ''))) $errors[] = '事由を入力してください。';
        } elseif ($type == 'overtime') {
            if (empty($data['date'])) $errors[] = '日付を入力してください。';
            if (empty($data['start_time'])) $errors[] = '開始時刻を入力してください。';
            if (empty($data['end_time'])) $errors[] = '終了時刻を入力してください。';
            if (!empty($data['start_time']) && !empty($data['end_time']) && $data['start_time'] >= $data['end_time']) {
                $errors[] = '終了時刻は開始時刻より後にしてください。';
            }
            $purpose = $data['purpose'] ?? null;
            $allowed = ['遅刻', '早退', '時間外勤務'];
            if (is_array($purpose)) {
                $purpose = isset($purpose[0]) ? $purpose[0] : '';
            }
            if (empty(trim((string)$purpose)) || !in_array(trim($purpose), $allowed, true)) {
                $errors[] = '用途を選択してください。';
            }
        } elseif ($type == 'attendance_correction') {
            if (empty($data['date'])) $errors[] = '日付を入力してください。';
            if (empty($data['time'])) $errors[] = '時間を入力してください。';
            $ctype = trim($data['correction_type'] ?? '');
            if ($ctype === '' || !in_array($ctype, ['出社', '退社'], true)) $errors[] = '区分を選択してください。';
            if (empty(trim($data['reason'] ?? ''))) $errors[] = '事由を入力してください。';
        } elseif ($type == 'travel_expense') {
            if (empty(trim($data['attachment'] ?? ''))) $errors[] = '交通費精算書のファイルをアップロードしてください。';
        } elseif ($type == 'expense') {
            if (empty(trim($data['attachment'] ?? ''))) $errors[] = '経費精算書のファイルをアップロードしてください。';
        } elseif ($type == 'trip_expense') {
            if (empty(trim($data['attachment'] ?? ''))) $errors[] = '出張旅費精算書のファイルをアップロードしてください。';
        } elseif ($type == 'commuting_allowance') {
            if (empty(trim($data['attachment'] ?? ''))) $errors[] = '通勤手当申請書のファイルをアップロードしてください。';
        }
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
            // 承認者は必須
            if (empty($_POST['approver_user_id'])) {
                http_response_code(400);
                echo json_encode(['error' => '承認者を選択してください。']);
                exit;
            }
        }
        $start_date = null;
        $end_date = null;
        if (!empty($_POST['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}/', $_POST['start_date'])) {
            $start_date = substr($_POST['start_date'], 0, 10);
        }
        if (!empty($_POST['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}/', $_POST['end_date'])) {
            $end_date = substr($_POST['end_date'], 0, 10);
        }
        if ($start_date === null && $type === 'leave' && !empty($data['start_datetime'])) {
            $start_date = substr($data['start_datetime'], 0, 10);
        }
        if ($end_date === null && $type === 'leave' && !empty($data['end_datetime'])) {
            $end_date = substr($data['end_datetime'], 0, 10);
        }
        // 外出申請書: start_date / end_date を日付から設定
        if ($start_date === null && $type === 'outing' && !empty($data['date'])) {
            $start_date = preg_match('/^\d{4}-\d{2}-\d{2}/', $data['date']) ? substr($data['date'], 0, 10) : $data['date'];
        }
        if ($end_date === null && $type === 'outing' && !empty($data['date'])) {
            $end_date = preg_match('/^\d{4}-\d{2}-\d{2}/', $data['date']) ? substr($data['date'], 0, 10) : $data['date'];
        }
        if ($start_date === null && $type === 'trip' && !empty($data['start_datetime'])) {
            $start_date = substr($data['start_datetime'], 0, 10);
        }
        if ($end_date === null && $type === 'trip' && !empty($data['end_datetime'])) {
            $end_date = substr($data['end_datetime'], 0, 10);
        }
        if ($start_date === null && $type === 'holiday_work' && !empty($data['date'])) {
            $start_date = preg_match('/^\d{4}-\d{2}-\d{2}/', $data['date']) ? substr($data['date'], 0, 10) : $data['date'];
        }
        if ($end_date === null && $type === 'holiday_work' && !empty($data['date'])) {
            $end_date = preg_match('/^\d{4}-\d{2}-\d{2}/', $data['date']) ? substr($data['date'], 0, 10) : $data['date'];
        }
        if ($start_date === null && $type === 'overtime' && !empty($data['date'])) {
            $start_date = preg_match('/^\d{4}-\d{2}-\d{2}/', $data['date']) ? substr($data['date'], 0, 10) : $data['date'];
        }
        if ($end_date === null && $type === 'overtime' && !empty($data['date'])) {
            $end_date = preg_match('/^\d{4}-\d{2}-\d{2}/', $data['date']) ? substr($data['date'], 0, 10) : $data['date'];
        }
        if ($start_date === null && $type === 'attendance_correction' && !empty($data['date'])) {
            $start_date = preg_match('/^\d{4}-\d{2}-\d{2}/', $data['date']) ? substr($data['date'], 0, 10) : $data['date'];
        }
        if ($end_date === null && $type === 'attendance_correction' && !empty($data['date'])) {
            $end_date = preg_match('/^\d{4}-\d{2}-\d{2}/', $data['date']) ? substr($data['date'], 0, 10) : $data['date'];
        }
        $addToCalendar = 0;
        if (in_array($type, ['leave', 'outing', 'trip', 'holiday_work'], true)) {
            $addToCalendar = !empty($data['add_to_calendar']) ? 1 : 0;
        }
        $row = array(
            'user_id' => $_SESSION['userid'],
            'type' => $type,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'status' => $status,
            'approver_user_id' => !empty($_POST['approver_user_id']) ? $_POST['approver_user_id'] : null,
            'add_to_calendar' => $addToCalendar,
            'history' => json_encode([
                [
                    'action' => 'created',
                    'user' => $_SESSION['userid'],
                    'time' => date('Y-m-d H:i:s'),
                    'note' => ''
                ]   
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
        if ($start_date !== null) {
            $row['start_date'] = $start_date;
        }
        if ($end_date !== null) {
            $row['end_date'] = $end_date;
        }
        $result = $this->query_insert($row);
        
        // Send Pusher notification if request was created successfully
        if ($result && $status === 'pending') {
            $approverUserId = !empty($_POST['approver_user_id']) ? $_POST['approver_user_id'] : null;
            $this->sendRequestCreatedNotification($result, $type, $_SESSION['userid'], $approverUserId);
        }
        
        return $result;
    }

    // Lấy danh sách đơn (có thể lọc theo type, user, status), hỗ trợ phân trang & sắp xếp
    // Mặc định chỉ hiển thị đơn của user đang đăng nhập. Chỉ administrator hoặc user có quyền duyệt (can_approve_request) mới xem tất cả.
    function list() {
        $where = [];
        if (!empty($_GET['type'])) {
            $where[] = "type = '" . $this->quote($_GET['type']) . "'";
        }
        $isAdmin = !empty($_SESSION['authority']) && $_SESSION['authority'] === 'administrator';
        $isApprover = false;
        if (!empty($_SESSION['userid'])) {
            $u = $this->fetchOne("SELECT can_approve_request FROM " . DB_PREFIX . "user WHERE userid = '" . $this->quote($_SESSION['userid']) . "'");
            $isApprover = !empty($u['can_approve_request']);
        }
        if (!$isAdmin && !$isApprover) {
            $where[] = "user_id = '" . $this->quote($_SESSION['userid'] ?? '') . "'";
        } elseif (!empty($_GET['user_id'])) {
            $where[] = "user_id = '" . $this->quote($_GET['user_id']) . "'";
        }
        if (!empty($_GET['status'])) {
            $where[] = "status = '" . $this->quote($_GET['status']) . "'";
        }
        // Keyword search (tìm trong JSON data – ví dụ reason, note, và realname người đăng ký)
        if (!empty($_GET['keyword'])) {
            $rawKw = trim($_GET['keyword']);
            if ($rawKw !== '') {
                $kw = '%' . $rawKw . '%';
                $like = $this->quote($kw);
                $cond = "(data LIKE '" . $like . "'";
                // Tìm user_id theo realname để cho phép search theo tên người đăng ký
                $userLike = $this->quote($kw);
                $users = $this->fetchAll("SELECT userid FROM " . DB_PREFIX . "user WHERE realname LIKE '" . $userLike . "'");
                if ($users && count($users)) {
                    $ids = array();
                    foreach ($users as $u) {
                        if (!empty($u['userid'])) {
                            $ids[] = "'" . $this->quote($u['userid']) . "'";
                        }
                    }
                    if (count($ids)) {
                        $cond .= " OR user_id IN (" . implode(',', $ids) . ")";
                    }
                }
                // 検索キーワードで user_id も部分一致検索
                $cond .= " OR user_id LIKE '" . $userLike . "'";
                $cond .= ")";
                $where[] = $cond;
            }
        }
        // 期間フィルタ: 21日～翌20日。start_date がある場合は start_date、ない場合は created_at の日付で判定
        if (!empty($_GET['from_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from_date'])
            && !empty($_GET['to_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to_date'])) {
            $from_date = $_GET['from_date'];
            $to_date = $_GET['to_date'];
            $where[] = "((start_date IS NOT NULL AND start_date >= '" . $this->quote($from_date) . "' AND start_date <= '" . $this->quote($to_date) . "')"
                . " OR (end_date IS NOT NULL AND end_date >= '" . $this->quote($from_date) . "' AND end_date <= '" . $this->quote($to_date) . "')"
                . " OR (start_date IS NULL AND end_date IS NULL AND DATE(created_at) >= '" . $this->quote($from_date) . "' AND DATE(created_at) <= '" . $this->quote($to_date) . "'))";
        }
        $whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Sắp xếp
        $allowedSort = ['id', 'created_at', 'status', 'start_date', 'end_date'];
        $sort_by = isset($_GET['sort_by']) && in_array($_GET['sort_by'], $allowedSort, true) ? $_GET['sort_by'] : 'created_at';
        $sort_dir = (isset($_GET['sort_dir']) && strtolower($_GET['sort_dir']) === 'asc') ? 'ASC' : 'DESC';
        $orderSql = "ORDER BY {$sort_by} {$sort_dir}";

        // Phân trang
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        if ($page < 1) $page = 1;
        $perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 50;
        if ($perPage < 1) $perPage = 50;
        if ($perPage > 200) $perPage = 200;
        $offset = ($page - 1) * $perPage;

        $countRow = $this->fetchOne("SELECT COUNT(*) AS cnt FROM {$this->table} $whereSql");
        $total = $countRow ? intval($countRow['cnt']) : 0;
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 0;

        $query = "SELECT * FROM {$this->table} $whereSql $orderSql LIMIT {$perPage} OFFSET {$offset}";
        $rows = $this->fetchAll($query);

        // Parse JSON fields and collect user ids for name lookup
        $user_ids = array();
        foreach ($rows as &$row) {
            $row['data'] = json_decode($row['data'], true);
            $row['history'] = json_decode($row['history'], true);
            $row['comments'] = json_decode($row['comments'], true);
            $row['comment_count'] = is_array($row['comments']) ? count($row['comments']) : 0;
            if (!empty($row['user_id'])) $user_ids[$row['user_id']] = true;
            if (!empty($row['approver_id'])) $user_ids[$row['approver_id']] = true;
            if (!empty($row['approver_user_id'])) $user_ids[$row['approver_user_id']] = true;
        }
        unset($row);

        $user_map = array();
        if (count($user_ids)) {
            $in = "'" . implode("','", array_map([$this, 'quote'], array_keys($user_ids))) . "'";
            $users = $this->fetchAll("SELECT userid, realname FROM " . DB_PREFIX . "user WHERE userid IN ($in)");
            foreach ($users as $u) {
                $user_map[$u['userid']] = $u['realname'];
            }
        }
        foreach ($rows as &$row) {
            $row['user_realname'] = isset($user_map[$row['user_id']]) ? $user_map[$row['user_id']] : ($row['user_id'] ?? '');
            $row['approver_realname'] = !empty($row['approver_id']) && isset($user_map[$row['approver_id']]) ? $user_map[$row['approver_id']] : '';
            $row['approver_user_realname'] = !empty($row['approver_user_id']) && isset($user_map[$row['approver_user_id']]) ? $user_map[$row['approver_user_id']] : ($row['approver_user_id'] ?? '');
        }
        unset($row);

        return [
            'data' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages
            ]
        ];
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
        
        // Get current request info for permissions & notifications (incl. add_to_calendar, schedule_id for calendar sync)
        $currentRequest = $this->fetchOne("SELECT type, status, user_id, approver_user_id, add_to_calendar, schedule_id, data FROM {$this->table} WHERE id = $id");
        if (!$currentRequest) {
            http_response_code(404);
            echo json_encode(['error' => '申請が見つかりません。']);
            exit;
        }

        if($currentRequest['status']  === $status) {
            http_response_code(400);
            echo json_encode(['error' => '状態が変更されていません。']);
            exit;
        }

        // Only administrator or designated approver can update status
        $isAdmin = !empty($_SESSION['authority']) && $_SESSION['authority'] === 'administrator';
        $isDesignatedApprover = !empty($currentRequest['approver_user_id']) && $currentRequest['approver_user_id'] === $_SESSION['userid'];
        if (!$isAdmin && !$isDesignatedApprover) {
            http_response_code(403);
            echo json_encode(['error' => '状態を変更する権限がありません。']);
            exit;
        }
        
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
            // 承認時: add_to_calendar ならカレンダーに追加し schedule_id を保存
            if (!empty($currentRequest['add_to_calendar']) && in_array($currentRequest['type'], ['leave', 'outing', 'trip', 'holiday_work'], true)) {
                $scheduleId = $this->createScheduleFromRequest($id);
                if ($scheduleId) {
                    $update['schedule_id'] = $scheduleId;
                }
            }
        }
        if (($status === 'rejected' || $status === 'pending' || $status === 'draft') && !empty($currentRequest['schedule_id'])) {
            $this->deleteScheduleForRequest((int) $currentRequest['schedule_id']);
        }
        $result = $this->query_update($update, ['id' => $id]);
        if ($result && $status === 'rejected' && !empty($currentRequest['schedule_id'])) {
            $this->query("UPDATE {$this->table} SET schedule_id = NULL WHERE id = " . intval($id));
        }
        
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
            // Thêm user chỉ định duyệt (approver_user_id) nếu có
            if (!empty($row['approver_user_id'])) $user_ids[] = $row['approver_user_id'];
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
                // Gán realname cho người chỉ định duyệt (approver_user_id)
                if (!empty($row['approver_user_id']) && !empty($user_map[$row['approver_user_id']])) {
                    $row['approver_user_realname'] = $user_map[$row['approver_user_id']]['realname'];
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
            // 承認者は必須（現在の値または送信された値）
            $newApprover = array_key_exists('approver_user_id', $_POST)
                ? (isset($_POST['approver_user_id']) ? $_POST['approver_user_id'] : '')
                : (isset($row['approver_user_id']) ? $row['approver_user_id'] : '');
            if (empty($newApprover)) {
                http_response_code(400);
                echo json_encode(['error' => '承認者を選択してください。']);
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
        $start_date = null;
        $end_date = null;
        if (!empty($_POST['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}/', $_POST['start_date'])) {
            $start_date = substr($_POST['start_date'], 0, 10);
        }
        if (!empty($_POST['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}/', $_POST['end_date'])) {
            $end_date = substr($_POST['end_date'], 0, 10);
        }
        if ($start_date === null && $row['type'] === 'leave' && !empty($data['start_datetime'])) {
            $start_date = substr($data['start_datetime'], 0, 10);
        }
        if ($end_date === null && $row['type'] === 'leave' && !empty($data['end_datetime'])) {
            $end_date = substr($data['end_datetime'], 0, 10);
        }
        // 外出申請書: start_date / end_date を日付から設定
        if ($start_date === null && $row['type'] === 'outing' && !empty($data['date'])) {
            $start_date = preg_match('/^\d{4}-\d{2}-\d{2}/', $data['date']) ? substr($data['date'], 0, 10) : $data['date'];
        }
        if ($end_date === null && $row['type'] === 'outing' && !empty($data['date'])) {
            $end_date = preg_match('/^\d{4}-\d{2}-\d{2}/', $data['date']) ? substr($data['date'], 0, 10) : $data['date'];
        }
        if ($start_date === null && $row['type'] === 'trip' && !empty($data['start_datetime'])) {
            $start_date = substr($data['start_datetime'], 0, 10);
        }
        if ($end_date === null && $row['type'] === 'trip' && !empty($data['end_datetime'])) {
            $end_date = substr($data['end_datetime'], 0, 10);
        }
        if ($start_date === null && $row['type'] === 'holiday_work' && !empty($data['date'])) {
            $start_date = preg_match('/^\d{4}-\d{2}-\d{2}/', $data['date']) ? substr($data['date'], 0, 10) : $data['date'];
        }
        if ($end_date === null && $row['type'] === 'holiday_work' && !empty($data['date'])) {
            $end_date = preg_match('/^\d{4}-\d{2}-\d{2}/', $data['date']) ? substr($data['date'], 0, 10) : $data['date'];
        }
        if ($start_date === null && $row['type'] === 'overtime' && !empty($data['date'])) {
            $start_date = preg_match('/^\d{4}-\d{2}-\d{2}/', $data['date']) ? substr($data['date'], 0, 10) : $data['date'];
        }
        if ($end_date === null && $row['type'] === 'overtime' && !empty($data['date'])) {
            $end_date = preg_match('/^\d{4}-\d{2}-\d{2}/', $data['date']) ? substr($data['date'], 0, 10) : $data['date'];
        }
        if ($start_date === null && $row['type'] === 'attendance_correction' && !empty($data['date'])) {
            $start_date = preg_match('/^\d{4}-\d{2}-\d{2}/', $data['date']) ? substr($data['date'], 0, 10) : $data['date'];
        }
        if ($end_date === null && $row['type'] === 'attendance_correction' && !empty($data['date'])) {
            $end_date = preg_match('/^\d{4}-\d{2}-\d{2}/', $data['date']) ? substr($data['date'], 0, 10) : $data['date'];
        }
        $update = [
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'history' => json_encode($history, JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        if (in_array($row['type'], ['leave', 'outing', 'trip', 'holiday_work'], true)) {
            $update['add_to_calendar'] = !empty($data['add_to_calendar']) ? 1 : 0;
        }
        // Cập nhật người chỉ định duyệt nếu có (cho phép clear về null)
        if (array_key_exists('approver_user_id', $_POST)) {
            $update['approver_user_id'] = $_POST['approver_user_id'] !== '' ? $_POST['approver_user_id'] : null;
        }
        if ($start_date !== null) $update['start_date'] = $start_date;
        if ($end_date !== null) $update['end_date'] = $end_date;
        $result = $this->query_update($update, ['id' => $id]);
        
        // 承認済みでカレンダー連携: スケジュールを更新するか削除
        if ($result && $row['status'] === 'approved' && in_array($row['type'], ['leave', 'outing', 'trip', 'holiday_work'], true)) {
            $scheduleId = isset($row['schedule_id']) ? (int) $row['schedule_id'] : 0;
            if ($scheduleId > 0) {
                if (!empty($data['add_to_calendar'])) {
                    $this->updateScheduleFromRequest($scheduleId, $id);
                } else {
                    $this->deleteScheduleForRequest($scheduleId);
                    $this->query("UPDATE {$this->table} SET schedule_id = NULL WHERE id = " . intval($id));
                }
            }
        }
        
        // Send Pusher notification for request update
        if ($result) {
            $this->sendRequestUpdatedNotification($id, $row['type'], $row['status'], $row['user_id']);
        }
        
        return $result;
    }

    /**
     * Build schedule row (title, schedule_date, schedule_time, schedule_date_end, schedule_endtime, schedule_allday, schedule_comment) from request.
     * @param array $requestRow ['type' => ..., 'data' => json string, 'user_id' => ...]
     * @return array|null assoc for INSERT/UPDATE or null if unsupported type
     */
    private function buildScheduleRowFromRequest($requestRow) {
        $type = $requestRow['type'] ?? '';
        $data = is_array($requestRow['data']) ? $requestRow['data'] : json_decode($requestRow['data'], true);
        if (!is_array($data)) $data = [];
        $owner = $requestRow['user_id'] ?? $_SESSION['userid'];
        $realname = '';
        if (!empty($owner)) {
            $u = $this->fetchOne("SELECT realname FROM " . DB_PREFIX . "user WHERE userid = '" . $this->quote($owner) . "'");
            $realname = isset($u['realname']) ? $u['realname'] : '';
        }
        $title = '';
        $schedule_date = '';
        $schedule_time = '';
        $schedule_date_end = '';
        $schedule_endtime = '';
        $schedule_allday = 0;
        $comment = '';
        if ($type === 'leave') {
            $title = $realname;
            $start = $data['start_datetime'] ?? '';
            $end = $data['end_datetime'] ?? '';
            if (preg_match('/^(\d{4}-\d{2}-\d{2})[T\s](\d{2}:\d{2})/', $start, $m)) {
                $schedule_date = $m[1];
                $schedule_time = $m[2] . ':00';
                if (strlen($schedule_time) === 7) $schedule_time = $m[2] . ':00';
            }
            if (preg_match('/^(\d{4}-\d{2}-\d{2})[T\s](\d{2}:\d{2})/', $end, $m)) {
                $schedule_date_end = $m[1];
                $schedule_endtime = $m[2] . ':00';
                if (strlen($schedule_endtime) === 7) $schedule_endtime = $m[2] . ':00';
            }
            if (!$schedule_time) $schedule_time = '00:00:00';
            if (!$schedule_endtime) $schedule_endtime = '23:59:00';
            $paidType = isset($data['paid_type']) ? trim($data['paid_type']) : '';
            if ($paidType === '全休') {
                $schedule_date_end = $requestRow['end_date'] ?? '';
                $schedule_date_end = date('Y-m-d', strtotime($schedule_date_end . ' +1 day'));
                $schedule_allday = 1;
                $title .= '　休み';
                $schedule_time = '00:00';
                $schedule_endtime = '00:00';
            } else {
                if ($paidType !== '') $title .= '　' . $paidType;
            }
            
        } elseif ($type === 'outing') {
            $title = $realname . '　外出';
            $schedule_date = preg_match('/^\d{4}-\d{2}-\d{2}/', $data['date'] ?? '') ? substr($data['date'], 0, 10) : '';
            $schedule_date_end = $schedule_date;
            $schedule_time = !empty($data['start_time']) ? $data['start_time'] . ':00' : '09:00:00';
            if (strlen($schedule_time) === 7) $schedule_time = $data['start_time'] . ':00';
            $schedule_endtime = !empty($data['end_time']) ? $data['end_time'] . ':00' : '18:00:00';
            if (strlen($schedule_endtime) === 7) $schedule_endtime = $data['end_time'] . ':00';
            $comment = $data['destination'] ?? '';
        } elseif ($type === 'trip') {
            $title = $realname . '　出張';
            $schedule_date = $requestRow['start_date'] ?? '';
            $schedule_date_end = $requestRow['end_date'] ?? '';
            //+1 day
            $schedule_date_end = date('Y-m-d', strtotime($schedule_date_end . ' +1 day'));
            $schedule_time = '00:00';
            $schedule_endtime = '00:00';
            $schedule_allday = 1;
        } elseif ($type === 'holiday_work') {
            $title = $realname . '　休日勤務';
            $schedule_date = preg_match('/^\d{4}-\d{2}-\d{2}/', $data['date'] ?? '') ? substr($data['date'], 0, 10) : '';
            $schedule_date_end = $schedule_date;
            $schedule_time = !empty($data['start_time']) ? $data['start_time'] . ':00' : '09:00:00';
            if (strlen($schedule_time) === 7) $schedule_time = $data['start_time'] . ':00';
            $schedule_endtime = !empty($data['end_time']) ? $data['end_time'] . ':00' : '18:00:00';
            if (strlen($schedule_endtime) === 7) $schedule_endtime = $data['end_time'] . ':00';
        } else {
            return null;
        }
        if (!$schedule_date) return null;
        if (!$schedule_date_end) $schedule_date_end = $schedule_date;
        
        return [
            'schedule_title' => $title,
            'schedule_date' => $schedule_date,
            'schedule_time' => $schedule_time,
            'schedule_date_end' => $schedule_date_end,
            'schedule_endtime' => $schedule_endtime,
            'schedule_allday' => $schedule_allday,
            'schedule_comment' => $comment,
            'public_level' => 0,
            'schedule_category' => '勤怠',
            'owner' => $owner
        ];
    }

    /** Create schedule from approved request; returns schedule id or 0 */
    private function createScheduleFromRequest($requestId) {
        $row = $this->fetchOne("SELECT id, type, data, user_id, start_date, end_date FROM {$this->table} WHERE id = " . intval($requestId));
        if (!$row) return 0;
        $r = $this->buildScheduleRowFromRequest($row);
        if (!$r) return 0;
        $created = date('Y-m-d H:i:s');
        $t = DB_PREFIX . 'schedule';
        $sql = "INSERT INTO {$t} (schedule_title, schedule_date, schedule_time, schedule_date_end, schedule_endtime, schedule_allday, schedule_comment, public_level, created, schedule_category, owner) VALUES ("
            . "'" . $this->quote($r['schedule_title']) . "', "
            . "'" . $this->quote($r['schedule_date']) . "', "
            . "'" . $this->quote($r['schedule_time']) . "', "
            . "'" . $this->quote($r['schedule_date_end']) . "', "
            . "'" . $this->quote($r['schedule_endtime']) . "', "
            . intval($r['schedule_allday']) . ", "
            . "'" . $this->quote($r['schedule_comment']) . "', "
            . intval($r['public_level']) . ", "
            . "'" . $this->quote($created) . "', "
            . "'" . $this->quote($r['schedule_category']) . "', "
            . "'" . $this->quote($r['owner']) . "')";
        $this->query($sql);
        return $this->insertid() ?: 0;
    }

    /** Update existing schedule from request data */
    private function updateScheduleFromRequest($scheduleId, $requestId) {
        $row = $this->fetchOne("SELECT id, type, data, user_id, start_date, end_date FROM {$this->table} WHERE id = " . intval($requestId));
        if (!$row) return;
        $r = $this->buildScheduleRowFromRequest($row);
        if (!$r) return;
        $updated = date('Y-m-d H:i:s');
        $editor = $_SESSION['userid'];
        $t = DB_PREFIX . 'schedule';
        $sql = "UPDATE {$t} SET schedule_title = '" . $this->quote($r['schedule_title']) . "', schedule_date = '" . $this->quote($r['schedule_date']) . "', schedule_time = '" . $this->quote($r['schedule_time']) . "', schedule_date_end = '" . $this->quote($r['schedule_date_end']) . "', schedule_endtime = '" . $this->quote($r['schedule_endtime']) . "', schedule_allday = " . intval($r['schedule_allday']) . ", schedule_comment = '" . $this->quote($r['schedule_comment']) . "', public_level = " . intval($r['public_level']) . ", editor = '" . $this->quote($editor) . "', updated = '" . $this->quote($updated) . "', schedule_category = '" . $this->quote($r['schedule_category']) . "' WHERE id = " . intval($scheduleId);
        $this->query($sql);
    }

    /** Delete schedule by id */
    private function deleteScheduleForRequest($scheduleId) {
        $t = DB_PREFIX . 'schedule';
        $this->query("DELETE FROM {$t} WHERE id = " . intval($scheduleId));
    }

    // Firebase notification methods (NEW: dùng NotificationService)
    private function sendRequestCreatedNotification($requestId, $requestType, $userId, $approverUserId = null) {
        try {
            require_once(DIR_MODEL . 'NotificationService.php');
            $notiService = new NotificationService();
            $targetUserIds = [];
            if (!empty($approverUserId)) {
                // Chỉ gửi cho người được chỉ định duyệt nếu có
                $targetUserIds = [$approverUserId];
            } else {
                // Nếu không chỉ định, fallback gửi cho admin như hiện tại
                $admins = $this->fetchAll("SELECT userid FROM ".DB_PREFIX."user WHERE authority = 'administrator' AND (is_suspend IS NULL OR is_suspend = 0)");
                $targetUserIds = array_map(function($a){return $a['userid'];}, $admins);
            }
            // Loại trừ user hiện tại khỏi danh sách notify
            if (!empty($_SESSION['userid'])) {
                $currentUserId = $_SESSION['userid'];
                $targetUserIds = array_values(array_filter($targetUserIds, function($id) use ($currentUserId) {
                    return $id !== $currentUserId;
                }));
            }
            if (empty($targetUserIds)) return;
            // 日本語ラベルに変換（例: leave -> 休暇届）
            $typeLabel = $this->getRequestTypeLabel($requestType);
            $payload = [
                'event' => 'form_request_update',
                'title' => $typeLabel . 'が作成されました',
                'message' => $typeLabel . 'の申請が作成されました',
                'data' => [
                    'request_id' => $requestId,
                    'request_type' => $requestType,
                    'user_id' => $userId,
                    'action' => 'created',
                    'url' => "/form/detail.php?id=$requestId"
                ],
                'request_id' => $requestId,
                'user_ids' => $targetUserIds
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
            // Ưu tiên gửi cho người được chỉ định duyệt, fallback về admin nếu không có
            $targetUserIds = [];
            $req = $this->fetchOne("SELECT approver_user_id FROM {$this->table} WHERE id = " . intval($requestId));
            if ($req && !empty($req['approver_user_id'])) {
                $targetUserIds = [$req['approver_user_id']];
            } else {
                $admins = $this->fetchAll("SELECT userid FROM ".DB_PREFIX."user WHERE authority = 'administrator' AND (is_suspend IS NULL OR is_suspend = 0)");
                $targetUserIds = array_map(function($a){return $a['userid'];}, $admins);
            }
            // Loại trừ user hiện tại khỏi danh sách notify
            if (!empty($_SESSION['userid'])) {
                $currentUserId = $_SESSION['userid'];
                $targetUserIds = array_values(array_filter($targetUserIds, function($id) use ($currentUserId) {
                    return $id !== $currentUserId;
                }));
            }
            if (empty($targetUserIds)) return;
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
                'user_ids' => $targetUserIds
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
            // 日本語ラベル（例: leave -> 休暇届）
            $typeLabel = $this->getRequestTypeLabel($requestType);
            // ステータスごとのメッセージ文言
            switch ($status) {
                case 'approved':
                    $statusMessageText = $typeLabel . 'が承認されました';
                    break;
                case 'rejected':
                    $statusMessageText = $typeLabel . 'が却下されました';
                    break;
                default:
                    $statusMessageText = $typeLabel . 'のステータスが変更されました';
                    break;
            }
            // Gửi cho chủ đơn
            if (empty($_SESSION['userid']) || $_SESSION['userid'] !== $userId) {
                $payload_user = [
                    'event' => 'form_request_update',
                    'title' => $typeLabel . 'ステータス',
                    'message' => $statusMessageText,
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
            }
            // Gửi cho người chỉ định duyệt (hoặc admin nếu không có)
            $targetUserIds = [];
            $req = $this->fetchOne("SELECT approver_user_id FROM {$this->table} WHERE id = " . intval($requestId));
            if ($req && !empty($req['approver_user_id'])) {
                $targetUserIds = [$req['approver_user_id']];
            } else {
                $admins = $this->fetchAll("SELECT userid FROM ".DB_PREFIX."user WHERE authority = 'administrator' AND (is_suspend IS NULL OR is_suspend = 0)");
                $targetUserIds = array_map(function($a){return $a['userid'];}, $admins);
            }
            if (!empty($targetUserIds)) {
                // Loại trừ user hiện tại khỏi danh sách notify
                if (!empty($_SESSION['userid'])) {
                    $currentUserId = $_SESSION['userid'];
                    $targetUserIds = array_values(array_filter($targetUserIds, function($id) use ($currentUserId) {
                        return $id !== $currentUserId;
                    }));
                }
                if (empty($targetUserIds)) return;
                $payload_admin = isset($payload_user) ? $payload_user : [
                    'event' => 'form_request_update',
                    'title' => $typeLabel . 'ステータス',
                    'message' => $statusMessageText,
                    'data' => [
                        'request_id' => $requestId,
                        'request_type' => $requestType,
                        'status' => $status,
                        'action_user' => $actionUser,
                        'action' => $action,
                        'url' => "/form/detail.php?id=$requestId"
                    ],
                    'request_id' => $requestId,
                ];
                $payload_admin['user_ids'] = $targetUserIds;
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
            // 日本語ラベル（例: leave -> 休暇届）
            $typeLabel = $this->getRequestTypeLabel($requestType);
            // Gửi cho chủ đơn nếu người comment khác chủ đơn
            if ($userId !== $commentUserId) {
                $payload_user = [
                    'event' => 'form_comment',
                    'title' => '新しいコメント（' . $typeLabel . '）',
                    'message' => $typeLabel . 'に新しいコメントが追加されました',
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
            // Gửi cho người chỉ định duyệt (hoặc admin nếu không có)
            $targetUserIds = [];
            $req = $this->fetchOne("SELECT approver_user_id FROM {$this->table} WHERE id = " . intval($requestId));
            if ($req && !empty($req['approver_user_id'])) {
                $targetUserIds = [$req['approver_user_id']];
            } else {
                $admins = $this->fetchAll("SELECT userid FROM ".DB_PREFIX."user WHERE authority = 'administrator' AND (is_suspend IS NULL OR is_suspend = 0)");
                $targetUserIds = array_map(function($a){return $a['userid'];}, $admins);
            }
            if (!empty($targetUserIds)) {
                // Loại trừ user hiện tại khỏi danh sách notify
                if (!empty($_SESSION['userid'])) {
                    $currentUserId = $_SESSION['userid'];
                    $targetUserIds = array_values(array_filter($targetUserIds, function($id) use ($currentUserId) {
                        return $id !== $currentUserId;
                    }));
                }
                if (empty($targetUserIds)) return;
                $payload_admin = [
                    'event' => 'form_comment',
                    'title' => '新しいコメント（' . $typeLabel . '）',
                    'message' => $typeLabel . 'に新しいコメントが追加されました',
                    'data' => [
                        'request_id' => $requestId,
                        'request_type' => $requestType,
                        'user_id' => $userId,
                        'comment_user_id' => $commentUserId,
                        'url' => "/form/detail.php?id=$requestId"
                    ],
                    'request_id' => $requestId,
                    'user_ids' => $targetUserIds
                ];
                $notiService->create($payload_admin);
            }
        } catch (Exception $e) {
            error_log('Failed to send request comment notification: ' . $e->getMessage());
        }
    }

    // Upload file for form (e.g. 交通費精算書) to application/upload/form/ with unique name
    function uploadFormFile($params = null) {
        if (empty($_SESSION['userid'])) {
            return ['success' => false, 'error' => 'ユーザー情報がありません。'];
        }
        $fieldName = 'file';
        if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'ファイルが選択されていないか、アップロードエラーです。'];
        }
        $file = $_FILES[$fieldName];
        $originalName = $file['name'];
        $tmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        if ($fileSize > 20 * 1024 * 1024) {
            return ['success' => false, 'error' => 'ファイルサイズは20MB以下にしてください。'];
        }
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($originalName, '.'.$ext));
        $uniqueName = date('YmdHis') . '_' . uniqid() . '_' . ($safeName ?: 'file') . ($ext ? '.' . $ext : '');
        $uploadDir = DIR_UPLOAD . 'form/';
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0755, true)) {
                return ['success' => false, 'error' => 'アップロードフォルダを作成できませんでした。'];
            }
        }
        $filePath = $uploadDir . $uniqueName;
        if (!move_uploaded_file($tmpName, $filePath)) {
            return ['success' => false, 'error' => 'ファイルの保存に失敗しました。'];
        }
        return [
            'success' => true,
            'filename' => $uniqueName,
            'original_name' => $originalName,
            'path' => 'form/' . $uniqueName
        ];
    }

    // Helper: map request type -> Japanese label
    private function getRequestTypeLabel($type) {
        switch ($type) {
            case 'leave':
                return '休暇届';
            case 'outing':
                return '外出申請書';
            case 'trip':
                return '出張申請書';
            case 'holiday_work':
                return '休日勤務申請書';
            case 'overtime':
                return '遅刻・早退・時間外勤務';
            case 'attendance_correction':
                return '勤怠打刻修正';
            case 'travel_expense':
                return '交通費精算書';
            case 'expense':
                return '経費精算書';
            case 'trip_expense':
                return '出張旅費精算書';
            case 'commuting_allowance':
                return '通勤手当申請書';
            default:
                return '申請';
        }
    }

} 