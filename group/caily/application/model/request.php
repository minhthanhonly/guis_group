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

    /** @return string[] */
    private function parseApproverUserIds($value) {
        if ($value === null || $value === '') {
            return array();
        }
        if (is_array($value)) {
            $ids = array();
            foreach ($value as $v) {
                $v = trim((string)$v);
                if ($v !== '') {
                    $ids[] = $v;
                }
            }
            return array_values(array_unique($ids));
        }
        $str = trim((string)$value);
        if ($str === '') {
            return array();
        }
        if ($str[0] === '[') {
            $decoded = json_decode($str, true);
            if (is_array($decoded)) {
                return $this->parseApproverUserIds($decoded);
            }
        }
        return array($str);
    }

    /** @return string[] */
    private function parseUserIdArray($value) {
        if ($value === null || $value === '') {
            return array();
        }
        if (is_array($value)) {
            $ids = array();
            foreach ($value as $v) {
                $v = trim((string)$v);
                if ($v !== '' && !in_array($v, $ids, true)) {
                    $ids[] = $v;
                }
            }
            return $ids;
        }
        $str = trim((string)$value);
        if ($str === '') {
            return array();
        }
        if ($str[0] === '[') {
            $decoded = json_decode($str, true);
            if (is_array($decoded)) {
                return $this->parseUserIdArray($decoded);
            }
        }
        return array($str);
    }

    private function encodeApproverUserIds($value) {
        $ids = $this->parseApproverUserIds($value);
        if (empty($ids)) {
            return null;
        }
        return json_encode($ids, JSON_UNESCAPED_UNICODE);
    }

    private function userIsDesignatedApprover($approverUserIdField, $userid) {
        if (empty($userid)) {
            return false;
        }
        return in_array($userid, $this->parseApproverUserIds($approverUserIdField), true);
    }

    /** SQL: đơn của user hoặc đơn user được chỉ định duyệt (approver_user_id). */
    private function sqlVisibleRequestsForUser($userid) {
        $uid = $this->quote($userid);
        $jsonLike = $this->quote('%"' . $userid . '"%');
        return "(user_id = '" . $uid . "' OR approver_user_id = '" . $uid . "'"
            . " OR (approver_user_id IS NOT NULL AND approver_user_id != '' AND approver_user_id LIKE '" . $jsonLike . "'))";
    }

    /** SQL: đơn pending mà user được chỉ định duyệt (không gồm đơn tự đăng ký chờ người khác duyệt). */
    private function sqlDesignatedApproverOnly($userid) {
        $uid = $this->quote($userid);
        $jsonLike = $this->quote('%"' . $userid . '"%');
        return "(approver_user_id = '" . $uid . "'"
            . " OR (approver_user_id IS NOT NULL AND approver_user_id != '' AND approver_user_id LIKE '" . $jsonLike . "'))";
    }

    private function currentUserCanApproveRequests() {
        if (empty($_SESSION['userid'])) {
            return false;
        }
        $u = $this->fetchOne("SELECT can_approve_request FROM " . DB_PREFIX . "user WHERE userid = '" . $this->quote($_SESSION['userid']) . "'");
        return !empty($u['can_approve_request']);
    }

    private function currentUserIsSoumu() {
        if (empty($_SESSION['userid'])) {
            return false;
        }
        if (!empty($_SESSION['is_soumu']) && (string)$_SESSION['is_soumu'] === '1') {
            return true;
        }
        $u = $this->fetchOne("SELECT is_soumu FROM " . DB_PREFIX . "user WHERE userid = '" . $this->quote($_SESSION['userid']) . "'");
        return !empty($u['is_soumu']) && (string)$u['is_soumu'] === '1';
    }

    private function approverUserIdsFromPost($postKey = 'approver_user_id') {
        if (!array_key_exists($postKey, $_POST)) {
            return null;
        }
        return $this->encodeApproverUserIds($_POST[$postKey]);
    }

    private function appendDesignatedApproversToTargetIds(array &$targetUserIds, $approverUserIdField) {
        foreach ($this->parseApproverUserIds($approverUserIdField) as $uid) {
            if ($uid !== '' && !in_array($uid, $targetUserIds, true)) {
                $targetUserIds[] = $uid;
            }
        }
    }

    /** @return string[] candidate recipients for request comments */
    private function getCommentRecipientCandidateIds($requestRow, $excludeUserId = '') {
        $ids = array();
        if (is_array($requestRow)) {
            if (!empty($requestRow['user_id'])) {
                $ids[] = (string)$requestRow['user_id'];
            }
        }
        $approverRows = $this->fetchAll("SELECT userid FROM " . DB_PREFIX . "user WHERE (can_approve_request = 1 OR authority = 'administrator') AND (is_suspend IS NULL OR is_suspend = 0)");
        if (is_array($approverRows)) {
            foreach ($approverRows as $a) {
                if (!empty($a['userid']) && !in_array($a['userid'], $ids, true)) {
                    $ids[] = $a['userid'];
                }
            }
        }
        foreach ($this->getActiveSoumuUserIds() as $sid) {
            if (!in_array($sid, $ids, true)) {
                $ids[] = $sid;
            }
        }
        $excludeUserId = trim((string)$excludeUserId);
        if ($excludeUserId !== '') {
            $ids = array_values(array_filter($ids, function($uid) use ($excludeUserId) {
                return (string)$uid !== $excludeUserId;
            }));
        }
        return array_values(array_unique($ids));
    }

    /** @return string[] active soumu user ids */
    private function getActiveSoumuUserIds() {
        $rows = $this->fetchAll("SELECT userid FROM " . DB_PREFIX . "user WHERE is_soumu = 1 AND (is_suspend IS NULL OR is_suspend = 0)");
        $ids = array();
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (!empty($r['userid']) && !in_array($r['userid'], $ids, true)) {
                    $ids[] = $r['userid'];
                }
            }
        }
        return $ids;
    }

    private function commentReadTable() {
        return DB_PREFIX . 'request_comment_reads';
    }

    private function ensureCommentReadTable() {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $t = $this->commentReadTable();
        $sql = "CREATE TABLE IF NOT EXISTS {$t} ("
            . "id INT AUTO_INCREMENT PRIMARY KEY,"
            . "request_id INT NOT NULL,"
            . "user_id VARCHAR(255) NOT NULL,"
            . "last_seen_comment_at DATETIME NULL,"
            . "created_at DATETIME NOT NULL,"
            . "updated_at DATETIME NOT NULL,"
            . "UNIQUE KEY uq_request_user (request_id, user_id),"
            . "KEY idx_user_request (user_id, request_id)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->query($sql);
        $ensured = true;
    }

    private function normalizeTimestamp($value) {
        if (empty($value)) {
            return null;
        }
        $ts = strtotime((string)$value);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $ts);
    }

    private function commentTargetsUser($comment, $userId) {
        if (empty($userId) || !is_array($comment)) {
            return false;
        }
        $targets = $this->parseUserIdArray($comment['recipient_user_ids'] ?? array());
        if (empty($targets)) {
            // Legacy comments without explicit recipients are treated as broadcast.
            return true;
        }
        return in_array((string)$userId, $targets, true);
    }

    private function latestOtherCommentAt($comments, $userId) {
        if (!is_array($comments) || empty($comments)) {
            return null;
        }
        $latestTs = null;
        foreach ($comments as $c) {
            if (!is_array($c)) continue;
            if (!empty($userId) && isset($c['user_id']) && (string)$c['user_id'] === (string)$userId) {
                continue;
            }
            if (!$this->commentTargetsUser($c, $userId)) {
                continue;
            }
            if (empty($c['date'])) continue;
            $ts = strtotime((string)$c['date']);
            if ($ts === false) continue;
            if ($latestTs === null || $ts > $latestTs) {
                $latestTs = $ts;
            }
        }
        return $latestTs ? date('Y-m-d H:i:s', $latestTs) : null;
    }

    private function hasUnreadCommentForUser($comments, $userId, $lastSeenAt) {
        $latestOtherCommentAt = $this->latestOtherCommentAt($comments, $userId);
        if (empty($latestOtherCommentAt)) {
            return false;
        }
        $latestTs = strtotime($latestOtherCommentAt);
        if ($latestTs === false) {
            return false;
        }
        $seenTs = $lastSeenAt ? strtotime((string)$lastSeenAt) : false;
        if ($seenTs === false) {
            return true;
        }
        return $latestTs > $seenTs;
    }

    private function getCommentReadMap(array $requestIds, $userId) {
        $map = array();
        if (empty($requestIds) || empty($userId)) {
            return $map;
        }
        $this->ensureCommentReadTable();
        $ids = array();
        foreach ($requestIds as $rid) {
            $ids[] = intval($rid);
        }
        $ids = array_values(array_unique(array_filter($ids, function($v) { return $v > 0; })));
        if (empty($ids)) {
            return $map;
        }
        $in = implode(',', $ids);
        $t = $this->commentReadTable();
        $uid = $this->quote((string)$userId);
        $rows = $this->fetchAll("SELECT request_id, last_seen_comment_at FROM {$t} WHERE user_id = '{$uid}' AND request_id IN ({$in})");
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $rid = isset($r['request_id']) ? intval($r['request_id']) : 0;
                if ($rid > 0) {
                    $map[$rid] = $this->normalizeTimestamp($r['last_seen_comment_at'] ?? null);
                }
            }
        }
        return $map;
    }

    private function getCommentReadMapByUsers($requestId, array $userIds) {
        $map = array();
        $requestId = intval($requestId);
        if ($requestId <= 0 || empty($userIds)) {
            return $map;
        }
        $this->ensureCommentReadTable();
        $clean = array();
        foreach ($userIds as $uid) {
            $uid = trim((string)$uid);
            if ($uid !== '' && !in_array($uid, $clean, true)) {
                $clean[] = $uid;
            }
        }
        if (empty($clean)) {
            return $map;
        }
        $in = "'" . implode("','", array_map([$this, 'quote'], $clean)) . "'";
        $t = $this->commentReadTable();
        $rows = $this->fetchAll("SELECT user_id, last_seen_comment_at FROM {$t} WHERE request_id = " . $requestId . " AND user_id IN ({$in})");
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $uid = isset($r['user_id']) ? (string)$r['user_id'] : '';
                if ($uid !== '') {
                    $map[$uid] = $this->normalizeTimestamp($r['last_seen_comment_at'] ?? null);
                }
            }
        }
        return $map;
    }

    private function markCommentsRead($requestId, $userId, $seenAt = null) {
        $requestId = intval($requestId);
        if ($requestId <= 0 || empty($userId)) {
            return;
        }
        $this->ensureCommentReadTable();
        $seenAtNorm = $this->normalizeTimestamp($seenAt ?: date('Y-m-d H:i:s'));
        $now = date('Y-m-d H:i:s');
        $t = $this->commentReadTable();
        $uid = $this->quote((string)$userId);
        $seenSql = $seenAtNorm ? "'" . $this->quote($seenAtNorm) . "'" : "NULL";
        $nowSql = "'" . $this->quote($now) . "'";
        $sql = "INSERT INTO {$t} (request_id, user_id, last_seen_comment_at, created_at, updated_at) VALUES ("
            . intval($requestId) . ", '" . $uid . "', " . $seenSql . ", " . $nowSql . ", " . $nowSql . ") "
            . "ON DUPLICATE KEY UPDATE last_seen_comment_at = VALUES(last_seen_comment_at), updated_at = VALUES(updated_at)";
        $this->query($sql);
    }

    private function markRequestNotificationsAsRead($requestId, $userId) {
        $requestId = intval($requestId);
        if ($requestId <= 0 || empty($userId)) {
            return;
        }
        $uid = $this->quote((string)$userId);
        $sql = "UPDATE notification_user nu "
            . "INNER JOIN notification n ON n.id = nu.notification_id "
            . "SET nu.is_read = 1, nu.read_at = NOW() "
            . "WHERE nu.user_id = '" . $uid . "' "
            . "AND nu.is_read = 0 "
            . "AND n.request_id = " . $requestId;
        $this->query($sql);
    }

    private function enrichApproverUserDisplay(array &$row, $user_map) {
        $ids = $this->parseApproverUserIds(isset($row['approver_user_id']) ? $row['approver_user_id'] : '');
        $row['approver_user_ids'] = $ids;
        $names = array();
        foreach ($ids as $uid) {
            if (!empty($user_map[$uid])) {
                $names[] = is_array($user_map[$uid]) ? $user_map[$uid]['realname'] : $user_map[$uid];
            } else {
                $names[] = $uid;
            }
        }
        $row['approver_user_realname'] = implode('、', $names);
    }

    // Validate dữ liệu đầu vào cho từng loại đơn
    function validate_request($type, $data) {
        $errors = array();
        if ($type == 'leave') {
            if (empty($data['start_datetime'])) $errors[] = '開始日時を入力してください。';
            if (empty($data['end_datetime'])) $errors[] = '終了日時を入力してください。';
            if (!empty($data['start_datetime']) && !empty($data['end_datetime'])) {
                if (strtotime($data['start_datetime']) > strtotime($data['end_datetime'])) {
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
            if (!empty($data['start_datetime']) && !empty($data['end_datetime']) && strtotime($data['start_datetime']) > strtotime($data['end_datetime'])) {
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
        } elseif ($type == 'purchase') {
            $lines = isset($data['lines']) && is_array($data['lines']) ? $data['lines'] : [];
            if (count($lines) === 0) {
                $errors[] = '購入品目を1件以上入力してください。';
            } else {
                foreach ($lines as $idx => $line) {
                    if (!is_array($line)) continue;
                    $row = (int) $idx + 1;
                    if (empty(trim($line['manufacturer'] ?? ''))) {
                        $errors[] = "明細{$row}行目: メーカー（販売店）を入力してください。";
                        break;
                    }
                    if (empty(trim($line['product_name'] ?? ''))) {
                        $errors[] = "明細{$row}行目: 商品名を入力してください。";
                        break;
                    }
                    $q = isset($line['quantity']) ? $line['quantity'] : '';
                    if ($q === '' || !preg_match('/^\d+$/', (string) $q) || (int) $q < 1) {
                        $errors[] = "明細{$row}行目: 数量は1以上の整数を入力してください。";
                        break;
                    }
                    $p = isset($line['unit_price']) ? $line['unit_price'] : '';
                    if ($p === '' || !is_numeric($p) || (float) $p < 0) {
                        $errors[] = "明細{$row}行目: 単価（税込み）を入力してください。";
                        break;
                    }
                    $a = isset($line['amount_with_tax']) ? $line['amount_with_tax'] : '';
                    if ($a === '' || !is_numeric($a) || (float) $a < 0) {
                        $errors[] = "明細{$row}行目: 金額（税込み）を入力してください。";
                        break;
                    }
                }
            }
            if (empty(trim($data['reason'] ?? ''))) $errors[] = '事由・用途を入力してください。';
        } elseif ($type == 'it_support') {
            $allowed = ['ハードウェア', 'ソフトウェア', 'ネットワーク', 'アカウント/権限', 'その他'];
            $cat = trim($data['category'] ?? '');
            if ($cat === '' || !in_array($cat, $allowed, true)) {
                $errors[] = '区分を選択してください。';
            }
            if (empty(trim($data['subject'] ?? ''))) $errors[] = '件名を入力してください。';
            if (empty(trim($data['description'] ?? ''))) $errors[] = '内容・詳細を入力してください。';
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
            // 承認者(指定)は必須（複数可）
            if (empty($this->parseApproverUserIds(isset($_POST['approver_user_id']) ? $_POST['approver_user_id'] : ''))) {
                http_response_code(400);
                echo json_encode(['error' => '承認者(指定)を選択してください。']);
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
            'approver_user_id' => $this->approverUserIdsFromPost('approver_user_id'),
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
        
        // Send Pusher + email notification if request was created successfully
        if ($result && $status === 'pending') {
            $approverUserId = !empty($_POST['approver_user_id']) ? $_POST['approver_user_id'] : null;
            $applicantUserId = $_SESSION['userid'];
            $this->sendRequestCreatedNotification($result, $type, $applicantUserId, $approverUserId);
        }
        
        return $result;
    }

    // Lấy danh sách đơn (có thể lọc theo type, user, status), hỗ trợ phân trang & sắp xếp
    // administrator: tất cả đơn; can_approve_request: đơn của mình + đơn được chỉ định duyệt; user thường: chỉ đơn của mình.
    function list() {
        $where = [];
        if (!empty($_GET['type'])) {
            $where[] = "type = '" . $this->quote($_GET['type']) . "'";
        }
        $isAdmin = !empty($_SESSION['authority']) && $_SESSION['authority'] === 'administrator';
        $isSoumu = $this->currentUserIsSoumu();
        $canViewAll = $isAdmin || $isSoumu;
        $isApprover = !$canViewAll && $this->currentUserCanApproveRequests();
        $currentUserid = $_SESSION['userid'] ?? '';
        $approvalQueue = !empty($_GET['approval_queue']);
        if ($approvalQueue) {
            if ($canViewAll) {
                $where[] = "status IN ('pending', 'approved')";
            } else {
                $where[] = "status = 'pending'";
            }
            if ($canViewAll) {
                if (!empty($_GET['user_id'])) {
                    $where[] = "user_id = '" . $this->quote($_GET['user_id']) . "'";
                }
            } elseif ($isApprover) {
                $where[] = $this->sqlDesignatedApproverOnly($currentUserid);
                if (!empty($_GET['user_id'])) {
                    $where[] = "user_id = '" . $this->quote($_GET['user_id']) . "'";
                }
            } else {
                $where[] = '1=0';
            }
        } elseif ($canViewAll) {
            if (!empty($_GET['user_id'])) {
                $where[] = "user_id = '" . $this->quote($_GET['user_id']) . "'";
            }
        } elseif ($isApprover) {
            $where[] = $this->sqlVisibleRequestsForUser($currentUserid);
            if (!empty($_GET['user_id'])) {
                $where[] = "user_id = '" . $this->quote($_GET['user_id']) . "'";
            }
        } else {
            $where[] = "user_id = '" . $this->quote($currentUserid) . "'";
        }
        if ($canViewAll && !empty($_GET['assigned_approver'])) {
            $where[] = $this->sqlDesignatedApproverOnly($currentUserid);
        }
        if (!empty($_GET['status'])) {
            $statusesParam = trim((string)$_GET['status']);
            $statuses = array_values(array_filter(array_map('trim', explode(',', $statusesParam))));
            if (count($statuses) === 1) {
                $where[] = "status = '" . $this->quote($statuses[0]) . "'";
            } else if (count($statuses) > 1) {
                // status を "pending,draft" のように複数指定できるようにする
                $in = "'" . implode("','", array_map([$this, 'quote'], $statuses)) . "'";
                $where[] = "status IN ($in)";
            }
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
                $users = $this->fetchAll("SELECT userid FROM " . DB_PREFIX . "user WHERE realname LIKE '" . $userLike . "' OR lastname LIKE '" . $userLike . "' OR firstname LIKE '" . $userLike . "' OR lastname_after_married LIKE '" . $userLike . "'");
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
        $allowedSort = ['id', 'created_at', 'status', 'start_date', 'end_date', 'approved_at', 'completed_at'];
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

        $filterUnreadComment = !empty($_GET['unread_comment']);
        $countRow = $this->fetchOne("SELECT COUNT(*) AS cnt FROM {$this->table} $whereSql");
        $total = $countRow ? intval($countRow['cnt']) : 0;
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 0;

        if ($filterUnreadComment) {
            $query = "SELECT * FROM {$this->table} $whereSql $orderSql";
            $rows = $this->fetchAll($query);
        } else {
            $query = "SELECT * FROM {$this->table} $whereSql $orderSql LIMIT {$perPage} OFFSET {$offset}";
            $rows = $this->fetchAll($query);
        }

        // Parse JSON fields and collect user ids for name lookup
        $user_ids = array();
        $requestIds = array();
        foreach ($rows as &$row) {
            $row['data'] = json_decode($row['data'], true);
            $row['history'] = json_decode($row['history'], true);
            $row['comments'] = json_decode($row['comments'], true);
            $row['comment_count'] = is_array($row['comments']) ? count($row['comments']) : 0;
            $requestIds[] = intval($row['id']);
            if (!empty($row['user_id'])) $user_ids[$row['user_id']] = true;
            if (!empty($row['approver_id'])) $user_ids[$row['approver_id']] = true;
            if (!empty($row['completed_userid'])) $user_ids[$row['completed_userid']] = true;
            foreach ($this->parseApproverUserIds(isset($row['approver_user_id']) ? $row['approver_user_id'] : '') as $uid) {
                if ($uid !== '') {
                    $user_ids[$uid] = true;
                }
            }
        }
        unset($row);

        $user_map = array();
        if (count($user_ids)) {
            $in = "'" . implode("','", array_map([$this, 'quote'], array_keys($user_ids))) . "'";
            $users = $this->fetchAll("SELECT userid, realname, lastname, firstname, lastname_after_married FROM " . DB_PREFIX . "user WHERE userid IN ($in)");
            foreach ($users as $u) {
                $user_map[$u['userid']] = Helper::userDisplayName($u);
            }
        }
        foreach ($rows as &$row) {
            $row['user_realname'] = isset($user_map[$row['user_id']]) ? $user_map[$row['user_id']] : ($row['user_id'] ?? '');
            $row['approver_realname'] = !empty($row['approver_id']) && isset($user_map[$row['approver_id']]) ? $user_map[$row['approver_id']] : '';
            $row['completed_realname'] = !empty($row['completed_userid']) && isset($user_map[$row['completed_userid']]) ? $user_map[$row['completed_userid']] : '';
            $this->enrichApproverUserDisplay($row, $user_map);
        }
        unset($row);

        $readMap = $this->getCommentReadMap($requestIds, $currentUserid);
        foreach ($rows as &$row) {
            $lastSeen = isset($readMap[intval($row['id'])]) ? $readMap[intval($row['id'])] : null;
            $row['unread_comment'] = $this->hasUnreadCommentForUser($row['comments'], $currentUserid, $lastSeen) ? 1 : 0;
        }
        unset($row);

        if ($filterUnreadComment) {
            $rows = array_values(array_filter($rows, function($r) {
                return !empty($r['unread_comment']);
            }));
            $total = count($rows);
            $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 0;
            if ($offset >= $total && $total > 0) {
                $page = $totalPages;
                $offset = ($page - 1) * $perPage;
            }
            $rows = array_slice($rows, $offset, $perPage);
        }

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

    // Số đơn cần xử lý hiển thị badge menu
    // admin/総務: pending + 承認済（総務対応待ち）; approver: pending được chỉ định; user thường: đơn pending của mình
    function countPendingBadge() {
        if (empty($_SESSION['userid'])) {
            return 0;
        }
        $userid = $_SESSION['userid'];
        $isAdmin = !empty($_SESSION['authority']) && $_SESSION['authority'] === 'administrator';
        $isSoumu = $this->currentUserIsSoumu();
        $canViewAll = $isAdmin || $isSoumu;
        $where = [];
        if ($canViewAll) {
            $where[] = "status IN ('pending', 'approved')";
        } else {
            $where[] = "status = 'pending'";
        }
        if ($canViewAll) {
            // tất cả đơn trong phạm vi trên
        } elseif ($this->currentUserCanApproveRequests()) {
            $where[] = $this->sqlDesignatedApproverOnly($userid);
        } else {
            $where[] = "user_id = '" . $this->quote($userid) . "'";
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $row = $this->fetchOne("SELECT COUNT(*) AS cnt FROM {$this->table} $whereSql");
        return $row ? (int)$row['cnt'] : 0;
    }

    // Số đơn có comment chưa đọc hiển thị badge menu
    function countUnreadCommentBadge() {
        if (empty($_SESSION['userid'])) {
            return 0;
        }
        $userid = $_SESSION['userid'];
        $isAdmin = !empty($_SESSION['authority']) && $_SESSION['authority'] === 'administrator';
        $isSoumu = $this->currentUserIsSoumu();
        $canViewAll = $isAdmin || $isSoumu;
        $where = array("comments IS NOT NULL", "comments != ''");
        if ($canViewAll) {
            // xem toàn bộ
        } elseif ($this->currentUserCanApproveRequests()) {
            $where[] = $this->sqlVisibleRequestsForUser($userid);
        } else {
            $where[] = "user_id = '" . $this->quote($userid) . "'";
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $rows = $this->fetchAll("SELECT id, comments FROM {$this->table} {$whereSql}");
        if (!is_array($rows) || empty($rows)) {
            return 0;
        }
        $requestIds = array();
        foreach ($rows as $r) {
            if (!empty($r['id'])) {
                $requestIds[] = intval($r['id']);
            }
        }
        $readMap = $this->getCommentReadMap($requestIds, $userid);
        $count = 0;
        foreach ($rows as $r) {
            $requestId = isset($r['id']) ? intval($r['id']) : 0;
            if ($requestId <= 0) continue;
            $comments = !empty($r['comments']) ? json_decode($r['comments'], true) : array();
            $lastSeen = isset($readMap[$requestId]) ? $readMap[$requestId] : null;
            if ($this->hasUnreadCommentForUser($comments, $userid, $lastSeen)) {
                $count++;
            }
        }
        return $count;
    }

    // Danh sách user cho filter ở màn hình申請一覧 (admin: tất cả; approver: user có đơn trong phạm vi xem)
    function list_filter_users() {
        $isAdmin = !empty($_SESSION['authority']) && $_SESSION['authority'] === 'administrator';
        $isSoumu = $this->currentUserIsSoumu();
        $canViewAll = $isAdmin || $isSoumu;
        $isApprover = !$canViewAll && $this->currentUserCanApproveRequests();
        if (!$canViewAll && !$isApprover) {
            return [];
        }

        if ($canViewAll) {
            $query = "SELECT userid, realname, lastname, firstname, lastname_after_married FROM " . DB_PREFIX . "user "
                . "WHERE (is_suspend IS NULL OR is_suspend = 0) "
                . "AND user_group IN (1,4) "
                . "ORDER BY id ASC";
            return $this->fetchAll($query);
        }

        $currentUserid = $_SESSION['userid'] ?? '';
        $visibleSql = $this->sqlVisibleRequestsForUser($currentUserid);
        $applicants = $this->fetchAll("SELECT DISTINCT user_id FROM {$this->table} WHERE {$visibleSql} AND user_id IS NOT NULL AND user_id != ''");
        $userIds = array($currentUserid);
        if ($applicants) {
            foreach ($applicants as $row) {
                if (!empty($row['user_id'])) {
                    $userIds[] = $row['user_id'];
                }
            }
        }
        $userIds = array_values(array_unique($userIds));
        if (empty($userIds)) {
            return [];
        }
        $in = "'" . implode("','", array_map([$this, 'quote'], $userIds)) . "'";
        $query = "SELECT userid, realname, lastname, firstname, lastname_after_married FROM " . DB_PREFIX . "user "
            . "WHERE userid IN ($in) "
            . "AND (is_suspend IS NULL OR is_suspend = 0) "
            . "ORDER BY id ASC";
        return $this->fetchAll($query);
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
        $row = $this->fetchOne("SELECT comments, type, user_id, approver_user_id FROM {$this->table} WHERE id = $id");
        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => '申請が見つかりません。']);
            exit;
        }
        $candidateIds = $this->getCommentRecipientCandidateIds($row, $_SESSION['userid']);
        $recipientIds = $this->parseUserIdArray($_POST['recipient_user_ids'] ?? array());
        $recipientIds = array_values(array_filter(array_unique($recipientIds), function($uid) use ($candidateIds) {
            return in_array($uid, $candidateIds, true);
        }));
        if (empty($recipientIds)) {
            http_response_code(400);
            echo json_encode(['error' => 'コメント受信者を選択してください。']);
            exit;
        }
        $comment = [
            'user_id' => $_SESSION['userid'],
            'message' => $_POST['message'],
            'date' => date('Y-m-d H:i:s'),
            'recipient_user_ids' => $recipientIds
        ];
        $comments = $row && $row['comments'] ? json_decode($row['comments'], true) : [];
        $comments[] = $comment;
        $updateData = ['comments' => json_encode($comments, JSON_UNESCAPED_UNICODE)];

        // Nếu recipient nào chưa nằm trong approver_user_id thì tự động thêm vào
        $currentApprovers = $this->parseApproverUserIds($row['approver_user_id'] ?? '');
        $newApprovers = $currentApprovers;
        $applicantUserId = $row['user_id'] ?? '';
        foreach ($recipientIds as $rid) {
            if (!in_array($rid, $newApprovers, true) && $rid !== $applicantUserId) {
                $newApprovers[] = $rid;
            }
        }
        if (count($newApprovers) !== count($currentApprovers)) {
            $updateData['approver_user_id'] = json_encode(array_values($newApprovers), JSON_UNESCAPED_UNICODE);
        }

        $result = $this->query_update($updateData, ['id' => $id]);
        
        // Send Pusher notification for comment added
        if ($result && $row) {
            $this->markCommentsRead($id, $_SESSION['userid'], $comment['date']);
            $this->sendRequestCommentNotification($id, $row['type'], $row['user_id'], $_SESSION['userid'], $recipientIds);
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
        $currentRequest = $this->fetchOne("SELECT id, type, status, user_id, approver_user_id, add_to_calendar, schedule_id, data FROM {$this->table} WHERE id = $id");
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
        $isSoumu = false;
        if (!empty($_SESSION['is_soumu']) && (string)$_SESSION['is_soumu'] === '1') {
            $isSoumu = true;
        } else {
            $currentUserId = isset($_SESSION['userid']) ? $this->quote($_SESSION['userid']) : '';
            if ($currentUserId !== '') {
                $soumuRow = $this->fetchOne("SELECT is_soumu FROM " . DB_PREFIX . "user WHERE userid = '" . $currentUserId . "' LIMIT 1");
                if (!empty($soumuRow['is_soumu']) && (string)$soumuRow['is_soumu'] === '1') {
                    $isSoumu = true;
                }
            }
        }
        $isDesignatedApprover = $this->userIsDesignatedApprover($currentRequest['approver_user_id'], $_SESSION['userid']);
        if ($currentRequest['status'] === 'completed') {
            if (!$isAdmin && !$isSoumu) {
                http_response_code(403);
                echo json_encode(['error' => '処理完了後は状態を変更する権限がありません。']);
                exit;
            }
        }

        if ($status === 'completed') {
            if (!$isAdmin && !$isSoumu) {
                http_response_code(403);
                echo json_encode(['error' => '処理完了にする権限がありません。']);
                exit;
            }
            if (!in_array($currentRequest['status'], ['approved', 'rejected'], true)) {
                http_response_code(400);
                echo json_encode(['error' => '承認済または却下の申請のみ処理完了にできます。']);
                exit;
            }
        }
        if (!$isAdmin && !$isSoumu && !$isDesignatedApprover && $status !== 'pending') {
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
        $clearCompletedInfo = false;
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
        if ($status === 'completed') {
            $update['completed_userid'] = $user;
            $update['completed_at'] = date('Y-m-d H:i:s');
        } elseif (in_array($status, ['pending', 'approved', 'rejected', 'draft'], true)) {
            $clearCompletedInfo = true;
        }
        if (($status === 'rejected' || $status === 'pending' || $status === 'draft') && !empty($currentRequest['schedule_id'])) {
            $this->deleteScheduleForRequest((int) $currentRequest['schedule_id']);
        }
        $result = $this->query_update($update, ['id' => $id]);
        if ($result && $clearCompletedInfo) {
            $this->query("UPDATE {$this->table} SET completed_userid = NULL, completed_at = NULL WHERE id = " . intval($id));
        }
        if ($result && $status === 'rejected' && !empty($currentRequest['schedule_id'])) {
            $this->query("UPDATE {$this->table} SET schedule_id = NULL WHERE id = " . intval($id));
        }
        
        // Send Pusher notification for status change
        if ($result && $currentRequest && in_array($status, ['approved', 'rejected'])) {
            $this->sendRequestStatusNotification($id, $currentRequest['type'], $status, $currentRequest['user_id'], $user, $status);
        }
        if ($result && $status === 'pending') {
            $approverUserId =  $currentRequest['approver_user_id'] ?? null;
            if($_SESSION['userid'] == $currentRequest['user_id']) {
                $this->sendRequestCreatedNotification($currentRequest['id'], $currentRequest['type'], $_SESSION['userid'], $approverUserId);
            } else{
                $this->sendRequestStatusNotification($id, $currentRequest['type'], $status, $currentRequest['user_id'], $user, $status);
            }
        }
        
        return $result;
    }

    // Lấy chi tiết đơn
    function get() {
        $id = intval($_GET['id']);
        $row = $this->fetchOne("SELECT * FROM {$this->table} WHERE id = $id");
        if ($row) {
        $isAdmin = !empty($_SESSION['authority']) && $_SESSION['authority'] === 'administrator';
        $isSoumu = $this->currentUserIsSoumu();
        if (!$isAdmin && !$isSoumu) {
                $userid = $_SESSION['userid'] ?? '';
                $isOwner = ($row['user_id'] === $userid);
                $isDesignated = $this->userIsDesignatedApprover($row['approver_user_id'], $userid);
                if (!$isOwner && !$isDesignated) {
                    return null;
                }
            }
            if (!empty($_SESSION['userid'])) {
                $this->markRequestNotificationsAsRead($id, $_SESSION['userid']);
            }
            $row['data'] = json_decode($row['data'], true);
            $row['history'] = json_decode($row['history'], true);
            $row['comments'] = json_decode($row['comments'], true);
            $latestOtherCommentAt = $this->latestOtherCommentAt($row['comments'], $_SESSION['userid'] ?? '');
            if (!empty($latestOtherCommentAt) && !empty($_SESSION['userid'])) {
                $this->markCommentsRead($id, $_SESSION['userid'], $latestOtherCommentAt);
            }
            // Lấy danh sách user_id xuất hiện trong history và comments
            $user_ids = array();
            if (is_array($row['history'])) {
                foreach ($row['history'] as $h) if (!empty($h['user'])) $user_ids[] = $h['user'];
            }
            if (is_array($row['comments'])) {
                foreach ($row['comments'] as $c) {
                    if (!empty($c['user_id'])) $user_ids[] = $c['user_id'];
                    foreach ($this->parseUserIdArray($c['recipient_user_ids'] ?? array()) as $rid) {
                        if ($rid !== '') $user_ids[] = $rid;
                    }
                }
            }
            // Thêm user_id của người đăng ký
            if (!empty($row['user_id'])) $user_ids[] = $row['user_id'];
            // Thêm user đã処理完了
            if (!empty($row['completed_userid'])) $user_ids[] = $row['completed_userid'];
            // Thêm user chỉ định duyệt (approver_user_id) nếu có
            foreach ($this->parseApproverUserIds(isset($row['approver_user_id']) ? $row['approver_user_id'] : '') as $uid) {
                if ($uid !== '') {
                    $user_ids[] = $uid;
                }
            }
            $user_ids = array_unique($user_ids);
            if (count($user_ids)) {
                $in = "'" . implode("','", array_map([$this, 'quote'], $user_ids)) . "'";
                $users = $this->fetchAll("SELECT userid, realname, lastname, firstname, lastname_after_married, user_image FROM ".DB_PREFIX."user WHERE userid IN ($in)");
                $user_map = array();
                foreach ($users as $u) {
                    $user_map[$u['userid']] = array('realname' => Helper::userDisplayName($u), 'user_image' => $u['user_image']);
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
                        $recipientNames = array();
                        foreach ($this->parseUserIdArray($c['recipient_user_ids'] ?? array()) as $rid) {
                            if (!empty($user_map[$rid]['realname'])) {
                                $recipientNames[] = $user_map[$rid]['realname'];
                            } else {
                                $recipientNames[] = $rid;
                            }
                        }
                        $c['recipient_realnames'] = array_values(array_unique($recipientNames));
                    }
                }
                // Gán realname, user_image cho người đăng ký
                if (!empty($row['user_id']) && !empty($user_map[$row['user_id']])) {
                    $row['realname'] = $user_map[$row['user_id']]['realname'];
                    $row['user_image'] = $user_map[$row['user_id']]['user_image'];
                }
                if (!empty($row['completed_userid']) && !empty($user_map[$row['completed_userid']])) {
                    $row['completed_realname'] = $user_map[$row['completed_userid']]['realname'];
                } else {
                    $row['completed_realname'] = '';
                }
                $user_map_flat = array();
                foreach ($user_map as $uid => $info) {
                    $user_map_flat[$uid] = is_array($info) ? $info['realname'] : $info;
                }
                $this->enrichApproverUserDisplay($row, $user_map_flat);

                $recipientIdsForRead = array();
                if (is_array($row['comments'])) {
                    foreach ($row['comments'] as $c) {
                        foreach ($this->parseUserIdArray($c['recipient_user_ids'] ?? array()) as $rid) {
                            if ($rid !== '' && !in_array($rid, $recipientIdsForRead, true)) {
                                $recipientIdsForRead[] = $rid;
                            }
                        }
                    }
                }
                $recipientReadMap = $this->getCommentReadMapByUsers($id, $recipientIdsForRead);
                if (is_array($row['comments'])) {
                    foreach ($row['comments'] as &$c) {
                        $recipientStates = array();
                        $commentTs = !empty($c['date']) ? strtotime((string)$c['date']) : false;
                        foreach ($this->parseUserIdArray($c['recipient_user_ids'] ?? array()) as $rid) {
                            $readAt = $recipientReadMap[$rid] ?? null;
                            $readTs = $readAt ? strtotime((string)$readAt) : false;
                            $isRead = ($commentTs !== false && $readTs !== false && $readTs >= $commentTs);
                            $recipientStates[] = array(
                                'user_id' => $rid,
                                'name' => !empty($user_map[$rid]['realname']) ? $user_map[$rid]['realname'] : $rid,
                                'is_read' => $isRead ? 1 : 0
                            );
                        }
                        $c['recipient_read_states'] = $recipientStates;
                    }
                    unset($c);
                }
            }

            $excludeUserId = $_SESSION['userid'] ?? '';
            $candidateIds = $this->getCommentRecipientCandidateIds($row, $excludeUserId);
            $row['comment_recipient_candidates'] = array();
            if (!empty($candidateIds)) {
                $in = "'" . implode("','", array_map([$this, 'quote'], $candidateIds)) . "'";
                $candidateRows = $this->fetchAll("SELECT userid, realname, lastname, firstname, lastname_after_married FROM " . DB_PREFIX . "user WHERE userid IN ($in) AND (is_suspend IS NULL OR is_suspend = 0)");
                if (is_array($candidateRows)) {
                    foreach ($candidateRows as $u) {
                        if (empty($u['userid'])) continue;
                        $row['comment_recipient_candidates'][] = array(
                            'userid' => (string)$u['userid'],
                            'realname' => Helper::userDisplayName($u)
                        );
                    }
                }
            }
            // Default selected: chủ đơn + người chỉ định duyệt (loại trừ user hiện tại)
            $defaultIds = array();
            if (!empty($row['user_id']) && (string)$row['user_id'] !== (string)$excludeUserId) {
                $defaultIds[] = (string)$row['user_id'];
            }
            foreach ($this->parseApproverUserIds($row['approver_user_id'] ?? '') as $uid) {
                if ($uid !== '' && (string)$uid !== (string)$excludeUserId && !in_array($uid, $defaultIds, true)) {
                    $defaultIds[] = $uid;
                }
            }
            $row['comment_default_recipient_ids'] = $defaultIds;
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
            $newApproverRaw = array_key_exists('approver_user_id', $_POST)
                ? (isset($_POST['approver_user_id']) ? $_POST['approver_user_id'] : '')
                : (isset($row['approver_user_id']) ? $row['approver_user_id'] : '');
            if (empty($this->parseApproverUserIds($newApproverRaw))) {
                http_response_code(400);
                echo json_encode(['error' => '承認者(指定)を選択してください。']);
                exit;
            }
        }
        // Cập nhật history (lưu chi tiết các điểm đã thay đổi)
        $history = $row['history'] ? json_decode($row['history'], true) : [];
        $diffNote = $this->formatRequestDiffForEmail($row['type'], isset($row['data']) ? $row['data'] : null, $data);
        if ($diffNote === '') {
            $diffNote = '内容を編集';
        }
        $history[] = [
            'action' => 'edited',
            'user' => $_SESSION['userid'],
            'time' => date('Y-m-d H:i:s'),
            'note' => $diffNote
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
            $update['approver_user_id'] = $this->approverUserIdsFromPost('approver_user_id');
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
        
        // Send Pusher notification + email for request update
        if ($result) {
            $this->sendRequestUpdatedNotification($id, $row['type'], $row['status'], $row['user_id']);
            $this->sendRequestUpdatedEmail($id, $row['type'], $row['status'], $row['user_id'], isset($row['data']) ? $row['data'] : null);
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
            // 休暇届は現在 yyyy-mm-dd（時刻なし）で保存されるため、
            // 旧データの datetime 形式（yyyy-mm-ddTHH:ii）も後方互換で扱う
            if (preg_match('/^(\d{4}-\d{2}-\d{2})[T\s](\d{2}:\d{2})/', $start, $m)) {
                $schedule_date = $m[1];
                $schedule_time = $m[2] . ':00';
                if (strlen($schedule_time) === 7) $schedule_time = $m[2] . ':00';
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $start)) {
                $schedule_date = substr($start, 0, 10);
            }
            if (preg_match('/^(\d{4}-\d{2}-\d{2})[T\s](\d{2}:\d{2})/', $end, $m)) {
                $schedule_date_end = $m[1];
                $schedule_endtime = $m[2] . ':00';
                if (strlen($schedule_endtime) === 7) $schedule_endtime = $m[2] . ':00';
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $end)) {
                $schedule_date_end = substr($end, 0, 10);
            }
            // フォールバック: request.start_date / end_date があれば利用
            if (!$schedule_date && !empty($requestRow['start_date'])) {
                $schedule_date = $requestRow['start_date'];
            }
            if (!$schedule_date_end && !empty($requestRow['end_date'])) {
                $schedule_date_end = $requestRow['end_date'];
            }
            if (!$schedule_time) $schedule_time = '00:00:00';
            if (!$schedule_endtime) $schedule_endtime = '23:59:00';
            $paidType = isset($data['paid_type']) ? trim($data['paid_type']) : '';
            $schedule_allday = 1;
            if ($paidType === '全休') {
                $schedule_date_end = $requestRow['end_date'] ?? '';
                $schedule_date_end = date('Y-m-d', strtotime($schedule_date_end . ' +1 day'));
                $title .= '　全休';
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
            $targetUserIds = $this->parseApproverUserIds($approverUserId);
            if (empty($targetUserIds)) {
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
            // Message = subject email (100%)
            $typeLabel = $this->getRequestTypeLabel($requestType);
            $applicantName = $this->getRealname($userId);
            $message = '[' . $applicantName . ']が[' . $typeLabel . ']の申請を作成しました';
            $payload = [
                'event' => 'form_request_created',
                'title' => $typeLabel . 'が作成されました',
                'message' => $message,
                'data' => [
                    'request_id' => $requestId,
                    'request_type' => $requestType,
                    'user_id' => $userId,
                    'action' => 'created',
                    'avatar' => $_SESSION['user_image'],
                    'url' => "/form/detail.php?id=$requestId"
                ],
                'request_id' => $requestId,
                'user_ids' => $targetUserIds
            ];
            $notiService->create($payload);
            
            $this->sendRequestCreatedEmail($requestId, $type, $applicantUserId, $approverUserId, $message, $applicantName);
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
            $designated = $req ? $this->parseApproverUserIds($req['approver_user_id']) : array();
            if (!empty($designated)) {
                $this->appendDesignatedApproversToTargetIds($targetUserIds, $req['approver_user_id']);
            } else {
                $admins = $this->fetchAll("SELECT userid FROM ".DB_PREFIX."user WHERE authority = 'administrator' AND (is_suspend IS NULL OR is_suspend = 0)");
                foreach ($admins as $a) {
                    if (!empty($a['userid']) && !in_array($a['userid'], $targetUserIds, true)) {
                        $targetUserIds[] = $a['userid'];
                    }
                }
            }
            // Nếu người sửa khác chủ đơn thì cũng gửi cho chủ đơn
            $currentUserId = !empty($_SESSION['userid']) ? $_SESSION['userid'] : '';
            if ($currentUserId !== '' && $currentUserId !== $userId && !in_array($userId, $targetUserIds, true)) {
                $targetUserIds[] = $userId;
            }
            // Loại trừ user hiện tại khỏi danh sách notify
            if ($currentUserId !== '') {
                $targetUserIds = array_values(array_filter($targetUserIds, function($id) use ($currentUserId) {
                    return $id !== $currentUserId;
                }));
            }
            if (empty($targetUserIds)) return;
            $operator = isset($_SESSION['realname']) ? $_SESSION['realname'] : '';
            $typeLabel = $this->getRequestTypeLabel($requestType);
            $applicantName = $this->getRealname($userId);
            if ($operator !== $applicantName) {
                $message = ($operator !== '' ? '[' . $operator . '] ' : '') . 'が[' . $applicantName . ']の[' . $typeLabel . ']の申請を編集しました';
            } else {
                $message = ($operator !== '' ? '[' . $operator . '] ' : '') . 'が[' . $typeLabel . ']の申請を編集しました';
            }
            $payload = [
                'event' => 'form_request_update',
                'title' => '申請更新',
                'message' => $message,
                'data' => [
                    'request_id' => $requestId,
                    'request_type' => $requestType,
                    'status' => $status,
                    'user_id' => $userId,
                    'user_name' => $_SESSION['realname'],
                    'action' => 'updated',
                    'url' => "/form/detail.php?id=$requestId",
                    'avatar' => $_SESSION['user_image']
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
            // Message = subject email (100%). statusText: 承認/却下/更新
            $typeLabel = $this->getRequestTypeLabel($requestType);
            $applicantName = $this->getRealname($userId);
            $operator = isset($_SESSION['realname']) ? $_SESSION['realname'] : '';
            $statusText = $status === 'approved' ? '承認' : ($status === 'rejected' ? '却下' : '更新');
            if ($operator !== $applicantName) {
                $statusMessageText = ($operator !== '' ? '[' . $operator . '] ' : '') . 'が[' . $applicantName . ']の[' . $typeLabel . ']の申請を' . $statusText . 'しました';
            } else {
                $statusMessageText = ($operator !== '' ? '[' . $operator . '] ' : '') . 'が[' . $typeLabel . ']の申請を' . $statusText . 'しました';
            }
            // Gửi cho chủ đơn
            $targetUserIds = [];
            if (empty($_SESSION['userid']) || $_SESSION['userid'] !== $userId) {
                $targetUserIds[] = $userId;
            }
            // Gửi thêm cho người chỉ định duyệt (hoặc admin nếu không có)
            $req = $this->fetchOne("SELECT approver_user_id FROM {$this->table} WHERE id = " . intval($requestId));
            $designated = $req ? $this->parseApproverUserIds($req['approver_user_id']) : array();
            if (!empty($designated)) {
                $this->appendDesignatedApproversToTargetIds($targetUserIds, $req['approver_user_id']);
            } else {
                $admins = $this->fetchAll("SELECT userid FROM ".DB_PREFIX."user WHERE authority = 'administrator' AND (is_suspend IS NULL OR is_suspend = 0)");
                foreach ($admins as $a) {
                    if (!empty($a['userid']) && !in_array($a['userid'], $targetUserIds, true)) {
                        $targetUserIds[] = $a['userid'];
                    }
                }
            }
            // 承認時は総務(is_soumu=1)にもデフォルトで通知
            if ($status === 'approved') {
                foreach ($this->getActiveSoumuUserIds() as $soumuId) {
                    if (!in_array($soumuId, $targetUserIds, true)) {
                        $targetUserIds[] = $soumuId;
                    }
                }
            }
            if (!empty($targetUserIds)) {
                // Loại trừ user hiện tại khỏi danh sách notify
                if (!empty($_SESSION['userid'])) {
                    $currentUserId = $_SESSION['userid'];
                    $targetUserIds = array_values(array_filter($targetUserIds, function($id) use ($currentUserId, $userId) {
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
                        'url' => "/form/detail.php?id=$requestId",
                        'avatar' => $_SESSION['user_image']
                    ],
                    'request_id' => $requestId,
                ];
                $payload_admin['user_ids'] = $targetUserIds;
                $notiService->create($payload_admin);
            }

            // Email notification to applicant about status change
            $this->sendRequestStatusEmail($requestId, $requestType, $status, $userId, $applicantName);
            // 承認時は総務(is_soumu=1)にもデフォルトでメール送信
            if ($status === 'approved') {
                $soumuUserIds = $this->getActiveSoumuUserIds();
                if (!empty($_SESSION['userid'])) {
                    $currentUserId = $_SESSION['userid'];
                    $soumuUserIds = array_values(array_filter($soumuUserIds, function($id) use ($currentUserId) {
                        return $id !== $currentUserId;
                    }));
                }
                foreach ($soumuUserIds as $sid) {
                    if ($sid === $userId) {
                        // applicant already receives status email above
                        continue;
                    }
                    $this->sendRequestStatusEmail($requestId, $requestType, $status, $sid, $applicantName);
                }
            }
        } catch (Exception $e) {
            error_log('Failed to send request status notification: ' . $e->getMessage());
        }
    }

    private function sendRequestCommentNotification($requestId, $requestType, $userId, $commentUserId, array $recipientUserIds = array()) {
        try {
            require_once(DIR_MODEL . 'NotificationService.php');
            $notiService = new NotificationService();
            // 日本語ラベル（例: leave -> 休暇届）
            $typeLabel = $this->getRequestTypeLabel($requestType);
            $targetUserIds = array_values(array_unique($this->parseUserIdArray($recipientUserIds)));
            if (!empty($targetUserIds)) {
                // Loại trừ user hiện tại khỏi danh sách notify
                if (!empty($_SESSION['userid'])) {
                    $currentUserId = $_SESSION['userid'];
                    $targetUserIds = array_values(array_filter($targetUserIds, function($id) use ($currentUserId) {
                        return $id !== $currentUserId;
                    }));
                }
                if (empty($targetUserIds)) return;
                $commentUserName = $this->getRealname($commentUserId);
                $message = '[' . $commentUserName . '] が[' . $typeLabel . ']の申請にコメントしました';
                $payload_admin = [
                    'event' => 'form_comment',
                    'title' => '新しいコメント（' . $typeLabel . '）',
                    'message' => $message,
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
                $this->sendRequestCommentEmail($requestId, $requestType, $userId, $commentUserId, $message, $targetUserIds);
            }
        } catch (Exception $e) {
            error_log('Failed to send request comment notification: ' . $e->getMessage());
        }
    }

    /**
     * Gửi email khi có comment mới trên đơn.
     * - Người nhận: chủ đơn + người chỉ định duyệt (hoặc admin nếu không có).
     * - Subject: [người thao tác] が[申請者]の[種別]にコメントしました
     * - Body: hiển thị 申請者, nội dung comment (nếu lấy được) và link chi tiết.
     */
    private function sendRequestCommentEmail($requestId, $requestType, $userId, $commentUserId, $message, array $targetUserIds = array()) {
        // Lấy thông tin đơn để biết người đăng ký và comment cuối
        $row = $this->fetchOne("SELECT user_id, comments FROM {$this->table} WHERE id = " . intval($requestId));
        if (!$row) {
            return;
        }
        $applicantUserId = $row['user_id'];
        if (empty($applicantUserId)) {
            return;
        }
        $targetUserIds = array_values(array_unique($this->parseUserIdArray($targetUserIds)));
        // Loại trừ người comment khỏi danh sách nhận
        if (!empty($targetUserIds)) {
            $targetUserIds = array_values(array_filter($targetUserIds, function($id) use ($commentUserId) {
                return $id !== $commentUserId;
            }));
        }
        if (empty($targetUserIds)) {
            return;
        }

        $typeLabel = $this->getRequestTypeLabel($requestType);
        $applicantName = $this->getRealname($applicantUserId);
        $commentUserName = $this->getRealname($commentUserId);
        $url = $this->getBaseUrl() . '/form/detail.php?id=' . intval($requestId);

        $subject = '';

        if($commentUserName !== $applicantName) {
            $subject = '[' . $commentUserName . '] が[' . $applicantName . ']の[' . $typeLabel . ']にコメントしました';
        }
        else{
            $subject = '[' . $commentUserName . '] が[' . $typeLabel . ']の申請にコメントしました';
        }

        // Lấy nội dung comment mới nhất của commentUserId (nếu có)
        $commentText = '';
        if (!empty($row['comments'])) {
            $comments = json_decode($row['comments'], true);
            if (is_array($comments)) {
                for ($i = count($comments) - 1; $i >= 0; $i--) {
                    $c = $comments[$i];
                    if (isset($c['user_id']) && $c['user_id'] === $commentUserId && !empty($c['message'])) {
                        $commentText = trim((string)$c['message']);
                        break;
                    }
                }
            }
        }

        $body = $subject . "。\n\n"
              . "コメント者: " . $commentUserName . "\n\n";
        if ($commentText !== '') {
            $body .= "【コメント内容】\n" . $commentText . "\n\n";
        }
        $body .= "詳細: " . $url . "\n";

        foreach ($targetUserIds as $uid) {
            $this->sendEmailToUser($uid, $subject, $body);
        }
    }

    /**
     * Xóa đơn.
     * - Người đăng ký: chỉ được xóa khi status = draft hoặc pending.
     * - Khi người đăng ký xóa đơn pending: gửi thông báo cho người chỉ định duyệt.
     * - Administrator: được xóa bất kể status（承認者・総務は不可）.
     */
    function delete_request() {
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
        $row = $this->fetchOne("SELECT id, type, status, user_id, approver_user_id, schedule_id, data FROM {$this->table} WHERE id = $id");
        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => '申請が見つかりません。']);
            exit;
        }
        $isAdmin = !empty($_SESSION['authority']) && $_SESSION['authority'] === 'administrator';
        $isApplicant = $row['user_id'] === $_SESSION['userid'];

        $canDelete = false;
        if ($isAdmin) {
            $canDelete = true;
        } elseif ($isApplicant && in_array($row['status'], ['draft', 'pending'], true)) {
            $canDelete = true;
        }
        if (!$canDelete) {
            http_response_code(403);
            echo json_encode(['error' => 'この申請を削除する権限がありません。']);
            exit;
        }

        $wasPending = ($row['status'] === 'pending');
        $approverUserId = $row['approver_user_id'];
        $requestType = $row['type'];
        $applicantUserId = $row['user_id'];

        $deleted = $this->query_delete(['id' => $id]);
        if (!$deleted) {
            http_response_code(500);
            echo json_encode(['error' => '削除に失敗しました。']);
            exit;
        }

        if (!empty($row['schedule_id'])) {
            $this->deleteScheduleForRequest((int) $row['schedule_id']);
        }

        if ($wasPending && $isApplicant && !empty($this->parseApproverUserIds($approverUserId))) {
            $currentUserid = isset($_SESSION['userid']) ? $_SESSION['userid'] : '';
            if (!$this->userIsDesignatedApprover($approverUserId, $currentUserid)) {
                $this->sendRequestDeletedNotification($id, $requestType, $applicantUserId, $approverUserId, isset($row['data']) ? $row['data'] : null);
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }

    private function sendRequestDeletedNotification($requestId, $requestType, $applicantUserId, $approverUserId, $requestDataJson = null) {
        try {
            require_once(DIR_MODEL . 'NotificationService.php');
            $notiService = new NotificationService();
            $typeLabel = $this->getRequestTypeLabel($requestType);
            $applicantName = $this->getRealname($applicantUserId);
            $operator = isset($_SESSION['realname']) ? $_SESSION['realname'] : '';
            if ($operator !== $applicantName) {
                $message = ($operator !== '' ? '[' . $operator . '] ' : '') . 'が[' . $applicantName . ']の[' . $typeLabel . ']の申請を削除しました';
            } else {
                $message = ($operator !== '' ? '[' . $operator . '] ' : '') . 'が[' . $typeLabel . ']の申請を削除しました';
            }
            $payload = [
                'event' => 'form_request_deleted',
                'title' => $typeLabel . 'が削除されました',
                'message' => $message,
                'data' => [
                    'request_id' => $requestId,
                    'request_type' => $requestType,
                    'deleted_by_user_id' => $applicantUserId,
                    'url' => '/form/index.php'
                ],
                'request_id' => $requestId,
                'user_ids' => $this->parseApproverUserIds($approverUserId)
            ];
            $notiService->create($payload);

            // Email notification to designated approvers about deleted pending request
            foreach ($this->parseApproverUserIds($approverUserId) as $uid) {
                $this->sendRequestDeletedEmail($requestId, $requestType, $uid, $applicantUserId, $requestDataJson);
            }
        } catch (Exception $e) {
            error_log('Failed to send request deleted notification: ' . $e->getMessage());
        }
    }

    // Helper: get environment variable with multiple fallbacks (.env, $_ENV, $_SERVER)
    private function env($key, $default = '') {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        static $envCache = null;
        if ($envCache === null) {
            $envCache = [];
            // request.php is in application/model → project root is two levels up
            $envPath = dirname(__DIR__, 2) . '/.env';
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
                    list($k, $v) = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim($v);
                    if ($k !== '' && $v !== '') {
                        $envCache[$k] = $v;
                    }
                }
            }
        }
        if (isset($envCache[$key]) && $envCache[$key] !== '') {
            return $envCache[$key];
        }
        return $default;
    }

    // Helper: enqueue email vào bảng email_queue để worker xử lý, tránh chặn API
    // $subject phải là UTF-8 thuần (không MIME-encode); worker/PHPMailer sẽ encode khi gửi.
    private function sendEmailToUser($userId, $subject, $body) {
        if (empty($userId)) {
            return;
        }
        $subject = trim((string) $subject);
        $body = (string) $body;
        if ($subject === '' || $body === '') {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $table = DB_PREFIX . 'email_queue';
        // Lưu user_id, subject, body, trạng thái pending; worker sẽ lookup email thật khi gửi
        $sql = "INSERT INTO {$table} (user_id, subject, body, status, attempts, created_at, updated_at) VALUES ("
             . "'" . $this->quote($userId) . "', "
             . "'" . $this->quote($subject) . "', "
             . "'" . $this->quote($body) . "', "
             . "'pending', "
             . "0, "
             . "'" . $this->quote($now) . "', "
             . "'" . $this->quote($now) . "')";
        try {
            $this->query($sql);
        } catch (\Exception $e) {
            error_log('Failed to enqueue email: ' . $e->getMessage());
        }
    }

    private function getBaseUrl() {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        return $scheme . '://' . $host;
    }

    private function getRealname($userid) {
        if (empty($userid)) return $userid;
        $row = $this->fetchOne("SELECT realname FROM " . DB_PREFIX . "user WHERE userid = '" . $this->quote($userid) . "'");
        return $row && !empty($row['realname']) ? $row['realname'] : $userid;
    }

    private function formatHolidayWorkBreakTime($bt) {
        $bt = trim((string)$bt);
        if ($bt === '') return '';
        $labels = ['0.5' => '0.5h', '1' => '1h', '1.5' => '1.5h', '2' => '2h', '2.5' => '2.5h', '3' => '3h', '3.5' => '3.5h', '4' => '4h'];
        if (isset($labels[$bt])) return $labels[$bt];
        $minuteToHour = [30 => '0.5h', 60 => '1h', 90 => '1.5h', 120 => '2h', 150 => '2.5h', 180 => '3h', 210 => '3.5h', 240 => '4h'];
        if (ctype_digit($bt) && isset($minuteToHour[(int)$bt])) return $minuteToHour[(int)$bt];
        return $bt . 'h';
    }

    /**
     * Format request data as plain text for email body (chi tiết nội dung đơn).
     * @param int $requestId
     * @return string
     */
    private function formatRequestDetailForEmail($requestId) {
        $row = $this->fetchOne("SELECT type, data, user_id FROM {$this->table} WHERE id = " . intval($requestId));
        if (!$row || !isset($row['data'])) {
            return '';
        }
        $raw = $row['data'];
        // Chuẩn hóa data: ưu tiên JSON, fallback serialize, cuối cùng là plain text
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $data = $decoded;
            } else {
                $unser = @unserialize($raw);
                if (is_array($unser)) {
                    $data = $unser;
                } else {
                    $data = $raw;
                }
            }
        } else {
            $data = $raw;
        }
        if (!is_array($data)) {
            $text = trim((string) $data);
            if ($text === '') {
                return '';
            }
            return "【申請内容】\n" . $text;
        }
        $type = $row['type'];
        $lines = [];
        $fmt = function($label, $value) {
            if ($value === null || $value === '') return;
            return $label . ': ' . trim((string) $value);
        };
        if ($type === 'leave') {
            if (!empty($data['start_datetime'])) $lines[] = $fmt('期間（開始）', $data['start_datetime']);
            if (!empty($data['end_datetime'])) $lines[] = $fmt('期間（終了）', $data['end_datetime']);
            if (isset($data['days']) && $data['days'] !== '') $lines[] = $fmt('日間', $data['days']);
            if (!empty($data['leave_type'])) $lines[] = $fmt('休暇種別', $data['leave_type']);
            if (!empty($data['paid_type'])) $lines[] = $fmt('有給休暇', $data['paid_type']);
            if (!empty($data['unpaid_type'])) $lines[] = $fmt('無給休暇', $data['unpaid_type']);
            if (!empty($data['reason'])) $lines[] = $fmt('事由', $data['reason']);
            if (!empty($data['note'])) $lines[] = $fmt('注記', $data['note']);
        } elseif ($type === 'outing') {
            if (!empty($data['date'])) $lines[] = $fmt('日付', $data['date']);
            if (!empty($data['start_time'])) $lines[] = $fmt('開始時刻', $data['start_time']);
            if (!empty($data['end_time'])) $lines[] = $fmt('終了時刻', $data['end_time']);
            if (!empty($data['destination'])) $lines[] = $fmt('行先', $data['destination']);
            if (!empty($data['reason'])) $lines[] = $fmt('事由', $data['reason']);
            if (!empty($data['note'])) $lines[] = $fmt('注記', $data['note']);
        } elseif ($type === 'trip') {
            if (!empty($data['start_datetime'])) $lines[] = $fmt('期間（開始）', $data['start_datetime']);
            if (!empty($data['end_datetime'])) $lines[] = $fmt('期間（終了）', $data['end_datetime']);
            if (isset($data['days']) && $data['days'] !== '') $lines[] = $fmt('日間', $data['days']);
            if (!empty($data['destination'])) $lines[] = $fmt('行先', $data['destination']);
            if (!empty($data['reason'])) $lines[] = $fmt('事由', $data['reason']);
            if (!empty($data['note'])) $lines[] = $fmt('注記', $data['note']);
        } elseif ($type === 'holiday_work') {
            if (!empty($data['date'])) $lines[] = $fmt('日付', $data['date']);
            if (!empty($data['start_time'])) $lines[] = $fmt('開始時刻', $data['start_time']);
            if (isset($data['break_time']) && $data['break_time'] !== '') {
                $lines[] = $fmt('休憩時間', $this->formatHolidayWorkBreakTime($data['break_time']));
            }
            if (!empty($data['end_time'])) $lines[] = $fmt('終了時刻', $data['end_time']);
            if (!empty($data['reason'])) $lines[] = $fmt('事由', $data['reason']);
            if (!empty($data['note'])) $lines[] = $fmt('注記', $data['note']);
        } elseif ($type === 'overtime') {
            if (!empty($data['date'])) $lines[] = $fmt('日付', $data['date']);
            if (!empty($data['start_time'])) $lines[] = $fmt('開始時刻', $data['start_time']);
            if (!empty($data['end_time'])) $lines[] = $fmt('終了時刻', $data['end_time']);
            if (!empty($data['purpose'])) {
                $p = is_array($data['purpose']) ? implode('、', $data['purpose']) : $data['purpose'];
                $lines[] = $fmt('用途', $p);
            }
            if (!empty($data['reason'])) $lines[] = $fmt('事由', $data['reason']);
            if (!empty($data['note'])) $lines[] = $fmt('注記', $data['note']);
        } elseif ($type === 'attendance_correction') {
            if (!empty($data['date'])) $lines[] = $fmt('日付', $data['date']);
            if (!empty($data['time'])) $lines[] = $fmt('時間', $data['time']);
            if (!empty($data['correction_type'])) $lines[] = $fmt('区分', $data['correction_type']);
            if (!empty($data['reason'])) $lines[] = $fmt('事由', $data['reason']);
            if (!empty($data['note'])) $lines[] = $fmt('注記', $data['note']);
        } elseif ($type === 'travel_expense') {
            // 交通費精算書: 明細行と合計金額をメールに出力
            if (!empty($data['lines']) && is_array($data['lines'])) {
                $idx = 1;
                foreach ($data['lines'] as $line) {
                    if (!is_array($line)) continue;
                    $rowParts = [];
                    if (!empty($line['date'])) {
                        $rowParts[] = '日付: ' . $line['date'];
                    }
                    if (!empty($line['route'])) {
                        $rowParts[] = '路線: ' . $line['route'];
                    }
                    if (!empty($line['from']) || !empty($line['to'])) {
                        $rowParts[] = '区間: ' . ($line['from'] ?? '') . ' → ' . ($line['to'] ?? '');
                    }
                    if (isset($line['amount']) && $line['amount'] !== '') {
                        $rowParts[] = '金額: ' . $line['amount'] . '円';
                    }
                    if (!empty($line['way'])) {
                        $rowParts[] = '往復/片道: ' . $line['way'];
                    }
                    if (!empty($line['note'])) {
                        $rowParts[] = '備考: ' . $line['note'];
                    }
                    if (!empty($rowParts)) {
                        $lines[] = '明細' . $idx . ': ' . implode(' / ', $rowParts);
                        $idx++;
                    }
                }
            }
            if (isset($data['total_amount']) && $data['total_amount'] !== '') {
                $lines[] = $fmt('合計金額', $data['total_amount'] . '円');
            }
            if (!empty($data['note'])) $lines[] = $fmt('注記', $data['note']);
        } elseif ($type === 'expense') {
            // 経費精算書: 明細行と小計・合計をメールに出力
            if (!empty($data['lines']) && is_array($data['lines'])) {
                $idx = 1;
                foreach ($data['lines'] as $line) {
                    if (!is_array($line)) continue;
                    $rowParts = [];
                    if (!empty($line['date'])) {
                        $rowParts[] = '日付: ' . $line['date'];
                    }
                    if (!empty($line['content'])) {
                        $rowParts[] = '内容: ' . $line['content'];
                    }
                    if (!empty($line['payee'])) {
                        $rowParts[] = '支払先: ' . $line['payee'];
                    }
                    if (isset($line['amount']) && $line['amount'] !== '') {
                        $rowParts[] = '金額（税抜）: ' . $line['amount'] . '円';
                    }
                    if (isset($line['tax']) && $line['tax'] !== '') {
                        $rowParts[] = '消費税: ' . $line['tax'] . '円';
                    }
                    if (!empty($line['reduced_tax'])) {
                        $rowParts[] = '軽減税率: ' . $line['reduced_tax'];
                    }
                    if (!empty($line['note'])) {
                        $rowParts[] = '備考: ' . $line['note'];
                    }
                    if (!empty($rowParts)) {
                        $lines[] = '明細' . $idx . ': ' . implode(' / ', $rowParts);
                        $idx++;
                    }
                }
            }
            if (isset($data['subtotal_amount']) && $data['subtotal_amount'] !== '') {
                $lines[] = $fmt('小計（金額（税抜））', $data['subtotal_amount'] . '円');
            }
            if (isset($data['subtotal_tax']) && $data['subtotal_tax'] !== '') {
                $lines[] = $fmt('小計（消費税）', $data['subtotal_tax'] . '円');
            }
            if (isset($data['total_with_tax']) && $data['total_with_tax'] !== '') {
                $lines[] = $fmt('合計（税込）', $data['total_with_tax'] . '円');
            }
            if (!empty($data['note'])) $lines[] = $fmt('注記', $data['note']);
        } elseif ($type === 'trip_expense') {
            // 出張旅費精算書: 画面フィールドと同等にメール本文を組み立てる
            if (!empty($data['destination'])) $lines[] = $fmt('出張先', $data['destination']);
            if (!empty($data['start_date'])) $lines[] = $fmt('期間（開始）', $data['start_date']);
            if (!empty($data['end_date'])) $lines[] = $fmt('期間（終了）', $data['end_date']);
            if (!empty($data['settlement_date'])) $lines[] = $fmt('精算日', $data['settlement_date']);
            if (!empty($data['trip_type'])) $lines[] = $fmt('国内/海外', $data['trip_type']);

            if (isset($data['per_diem']) && $data['per_diem'] !== '') $lines[] = $fmt('日当', $data['per_diem'] . '円');
            if (isset($data['trip_days']) && $data['trip_days'] !== '') $lines[] = $fmt('日間', $data['trip_days']);
            if (isset($data['trip_allowance']) && $data['trip_allowance'] !== '') $lines[] = $fmt('出張手当', $data['trip_allowance'] . '円');

            if (isset($data['advance_amount']) && $data['advance_amount'] !== '') $lines[] = $fmt('仮払金', $data['advance_amount'] . '円');
            if (isset($data['line_total']) && $data['line_total'] !== '') $lines[] = $fmt('明細合計', $data['line_total'] . '円');
            if (isset($data['net_total']) && $data['net_total'] !== '') $lines[] = $fmt('仮払金差引合計', $data['net_total'] . '円');
            if (isset($data['final_amount']) && $data['final_amount'] !== '') $lines[] = $fmt('精算額', $data['final_amount'] . '円');

            if (!empty($data['receipts']) && is_array($data['receipts'])) {
                $names = array_map(function ($a) {
                    return isset($a['original_name']) ? $a['original_name'] : (isset($a['filename']) ? $a['filename'] : '');
                }, $data['receipts']);
                $names = array_filter($names);
                if (count($names)) {
                    $lines[] = $fmt('請求書・領収書等', implode('、', $names));
                }
            }

            if (!empty($data['lines']) && is_array($data['lines'])) {
                $idx = 1;
                foreach ($data['lines'] as $line) {
                    if (!is_array($line)) continue;
                    $rowParts = [];
                    if (!empty($line['date'])) $rowParts[] = '日付: ' . $line['date'];
                    if (!empty($line['item'])) $rowParts[] = '項目: ' . $line['item'];
                    if (isset($line['transportation']) && $line['transportation'] !== '') $rowParts[] = '交通費: ' . $line['transportation'] . '円';
                    if (isset($line['accommodation']) && $line['accommodation'] !== '') $rowParts[] = '宿泊費: ' . $line['accommodation'] . '円';
                    if (isset($line['entertainment']) && $line['entertainment'] !== '') $rowParts[] = '交際費: ' . $line['entertainment'] . '円';
                    if (isset($line['meal']) && $line['meal'] !== '') $rowParts[] = '食費: ' . $line['meal'] . '円';
                    if (isset($line['other']) && $line['other'] !== '') $rowParts[] = 'その他: ' . $line['other'] . '円';
                    if (isset($line['total']) && $line['total'] !== '') $rowParts[] = '合計: ' . $line['total'] . '円';
                    if (!empty($line['note'])) $rowParts[] = '備考: ' . $line['note'];
                    if (!empty($rowParts)) {
                        $lines[] = '明細' . $idx . ': ' . implode(' / ', $rowParts);
                        $idx++;
                    }
                }
            }

            if (!empty($data['note'])) $lines[] = $fmt('備考', $data['note']);
        } elseif ($type === 'commuting_allowance') {
            // 通勤手当申請書: 画面フィールドと同等にメール本文を組み立てる
            if (!empty($data['application_type'])) $lines[] = $fmt('申請区分', $data['application_type']);
            if (!empty($data['address'])) $lines[] = $fmt('住所', $data['address']);
            if (!empty($data['nearest_station'])) $lines[] = $fmt('最寄駅', $data['nearest_station']);
            if (!empty($data['effective_from'])) $lines[] = $fmt('適用開始日', $data['effective_from']);

            if (isset($data['total_amount']) && $data['total_amount'] !== '') $lines[] = $fmt('合計片道運賃', $data['total_amount'] . '円');
            if (isset($data['one_month_commuter_pass']) && $data['one_month_commuter_pass'] !== '') $lines[] = $fmt('１か月定期代', $data['one_month_commuter_pass'] . '円');

            if (!empty($data['receipts']) && is_array($data['receipts'])) {
                $names = array_map(function ($a) {
                    return isset($a['original_name']) ? $a['original_name'] : (isset($a['filename']) ? $a['filename'] : '');
                }, $data['receipts']);
                $names = array_filter($names);
                if (count($names)) {
                    $lines[] = $fmt('請求書・領収書等', implode('、', $names));
                }
            }

            if (!empty($data['lines']) && is_array($data['lines'])) {
                $idx = 1;
                foreach ($data['lines'] as $line) {
                    if (!is_array($line)) continue;
                    $rowParts = [];
                    if (!empty($line['railway_company'])) $rowParts[] = '鉄道会社名: ' . $line['railway_company'];
                    if (!empty($line['line_name'])) $rowParts[] = '路線名: ' . $line['line_name'];
                    if (!empty($line['section_from']) || !empty($line['section_to'])) {
                        $rowParts[] = '利用区間: ' . ($line['section_from'] ?? '') . ' → ' . ($line['section_to'] ?? '');
                    }
                    if (isset($line['one_way_fare']) && $line['one_way_fare'] !== '') $rowParts[] = '片道運賃: ' . $line['one_way_fare'] . '円';
                    if (!empty($rowParts)) {
                        $lines[] = '明細' . $idx . ': ' . implode(' / ', $rowParts);
                        $idx++;
                    }
                }
            }

            if (!empty($data['note'])) $lines[] = $fmt('備考', $data['note']);
        } elseif ($type === 'purchase') {
            if (!empty($data['lines']) && is_array($data['lines'])) {
                $idx = 1;
                foreach ($data['lines'] as $line) {
                    if (!is_array($line)) continue;
                    $rowParts = [];
                    if (!empty($line['manufacturer'])) $rowParts[] = 'メーカー: ' . $line['manufacturer'];
                    if (!empty($line['product_code'])) $rowParts[] = '商品コード: ' . $line['product_code'];
                    if (!empty($line['product_name'])) $rowParts[] = '商品名: ' . $line['product_name'];
                    if (isset($line['quantity']) && $line['quantity'] !== '') $rowParts[] = '数量: ' . $line['quantity'];
                    if (isset($line['unit_price']) && $line['unit_price'] !== '') $rowParts[] = '単価: ' . $line['unit_price'] . '円';
                    if (isset($line['amount_with_tax']) && $line['amount_with_tax'] !== '') $rowParts[] = '金額（税込み）: ' . $line['amount_with_tax'] . '円';
                    if (!empty($rowParts)) {
                        $lines[] = '明細' . $idx . ': ' . implode(' / ', $rowParts);
                        $idx++;
                    }
                }
            }
            if (isset($data['total_amount']) && $data['total_amount'] !== '') {
                $lines[] = $fmt('合計金額', $data['total_amount'] . '円');
            }
            if (!empty($data['reason'])) $lines[] = $fmt('事由・用途', $data['reason']);
            if (!empty($data['note'])) $lines[] = $fmt('注記', $data['note']);
        } elseif ($type === 'it_support') {
            if (!empty($data['category'])) $lines[] = $fmt('区分', $data['category']);
            if (!empty($data['subject'])) $lines[] = $fmt('件名', $data['subject']);
            if (!empty($data['description'])) $lines[] = $fmt('内容・詳細', $data['description']);
            if (!empty($data['priority'])) $lines[] = $fmt('緊急度', $data['priority']);
            if (!empty($data['attachments']) && is_array($data['attachments'])) {
                $names = array_map(function ($a) {
                    return isset($a['original_name']) ? $a['original_name'] : (isset($a['filename']) ? $a['filename'] : '');
                }, $data['attachments']);
                $lines[] = $fmt('添付資料', implode('、', array_filter($names)));
            }
            if (!empty($data['note'])) $lines[] = $fmt('注記', $data['note']);
        } else {
            foreach ($data as $k => $v) {
                if ($v === null || $v === '' || is_array($v)) continue;
                $lines[] = $k . ': ' . trim((string) $v);
            }
        }
        $lines = array_filter($lines);
        if (empty($lines)) {
            return '';
        }
        return "【申請内容】\n" . implode("\n", $lines);
    }

    /** Format request detail from type + data array (for deleted request when row no longer exists). */
    private function formatRequestDetailFromData($requestType, $data) {
        if (!is_array($data)) {
            $data = is_string($data) ? json_decode($data, true) : [];
        }
        if (empty($data)) {
            return '';
        }
        $lines = [];
        $fmt = function($label, $value) {
            if ($value === null || $value === '') return null;
            return $label . ': ' . trim((string) $value);
        };
        if ($requestType === 'leave') {
            if (!empty($data['start_datetime'])) $lines[] = $fmt('期間（開始）', $data['start_datetime']);
            if (!empty($data['end_datetime'])) $lines[] = $fmt('期間（終了）', $data['end_datetime']);
            if (isset($data['days']) && $data['days'] !== '') $lines[] = $fmt('日間', $data['days']);
            if (!empty($data['leave_type'])) $lines[] = $fmt('休暇種別', $data['leave_type']);
            if (!empty($data['paid_type'])) $lines[] = $fmt('有給休暇', $data['paid_type']);
            if (!empty($data['unpaid_type'])) $lines[] = $fmt('無給休暇', $data['unpaid_type']);
            if (!empty($data['reason'])) $lines[] = $fmt('事由', $data['reason']);
            if (!empty($data['note'])) $lines[] = $fmt('注記', $data['note']);
        } elseif ($requestType === 'outing') {
            if (!empty($data['date'])) $lines[] = $fmt('日付', $data['date']);
            if (!empty($data['start_time'])) $lines[] = $fmt('開始時刻', $data['start_time']);
            if (!empty($data['end_time'])) $lines[] = $fmt('終了時刻', $data['end_time']);
            if (!empty($data['destination'])) $lines[] = $fmt('行先', $data['destination']);
            if (!empty($data['reason'])) $lines[] = $fmt('事由', $data['reason']);
            if (!empty($data['note'])) $lines[] = $fmt('注記', $data['note']);
        } elseif ($requestType === 'trip') {
            if (!empty($data['start_datetime'])) $lines[] = $fmt('期間（開始）', $data['start_datetime']);
            if (!empty($data['end_datetime'])) $lines[] = $fmt('期間（終了）', $data['end_datetime']);
            if (isset($data['days']) && $data['days'] !== '') $lines[] = $fmt('日間', $data['days']);
            if (!empty($data['destination'])) $lines[] = $fmt('行先', $data['destination']);
            if (!empty($data['reason'])) $lines[] = $fmt('事由', $data['reason']);
            if (!empty($data['note'])) $lines[] = $fmt('注記', $data['note']);
        } elseif ($requestType === 'holiday_work') {
            if (!empty($data['date'])) $lines[] = $fmt('日付', $data['date']);
            if (!empty($data['start_time'])) $lines[] = $fmt('開始時刻', $data['start_time']);
            if (isset($data['break_time']) && $data['break_time'] !== '') {
                $lines[] = $fmt('休憩時間', $this->formatHolidayWorkBreakTime($data['break_time']));
            }
            if (!empty($data['end_time'])) $lines[] = $fmt('終了時刻', $data['end_time']);
            if (!empty($data['reason'])) $lines[] = $fmt('事由', $data['reason']);
            if (!empty($data['note'])) $lines[] = $fmt('注記', $data['note']);
        } elseif ($requestType === 'overtime') {
            if (!empty($data['date'])) $lines[] = $fmt('日付', $data['date']);
            if (!empty($data['start_time'])) $lines[] = $fmt('開始時刻', $data['start_time']);
            if (!empty($data['end_time'])) $lines[] = $fmt('終了時刻', $data['end_time']);
            if (!empty($data['purpose'])) {
                $p = is_array($data['purpose']) ? implode('、', $data['purpose']) : $data['purpose'];
                $lines[] = $fmt('用途', $p);
            }
            if (!empty($data['reason'])) $lines[] = $fmt('事由', $data['reason']);
            if (!empty($data['note'])) $lines[] = $fmt('注記', $data['note']);
        } elseif ($requestType === 'attendance_correction') {
            if (!empty($data['date'])) $lines[] = $fmt('日付', $data['date']);
            if (!empty($data['time'])) $lines[] = $fmt('時間', $data['time']);
            if (!empty($data['correction_type'])) $lines[] = $fmt('区分', $data['correction_type']);
            if (!empty($data['reason'])) $lines[] = $fmt('事由', $data['reason']);
            if (!empty($data['note'])) $lines[] = $fmt('注記', $data['note']);
        } elseif ($requestType === 'travel_expense') {
            if (!empty($data['lines']) && is_array($data['lines'])) {
                $idx = 1;
                foreach ($data['lines'] as $line) {
                    if (!is_array($line)) continue;
                    $rowParts = [];
                    if (!empty($line['date'])) {
                        $rowParts[] = '日付: ' . $line['date'];
                    }
                    if (!empty($line['route'])) {
                        $rowParts[] = '路線: ' . $line['route'];
                    }
                    if (!empty($line['from']) || !empty($line['to'])) {
                        $rowParts[] = '区間: ' . ($line['from'] ?? '') . ' → ' . ($line['to'] ?? '');
                    }
                    if (isset($line['amount']) && $line['amount'] !== '') {
                        $rowParts[] = '金額: ' . $line['amount'] . '円';
                    }
                    if (!empty($line['way'])) {
                        $rowParts[] = '往復/片道: ' . $line['way'];
                    }
                    if (!empty($line['note'])) {
                        $rowParts[] = '備考: ' . $line['note'];
                    }
                    if (!empty($rowParts)) {
                        $lines[] = '明細' . $idx . ': ' . implode(' / ', $rowParts);
                        $idx++;
                    }
                }
            }
            if (isset($data['total_amount']) && $data['total_amount'] !== '') {
                $lines[] = $fmt('合計金額', $data['total_amount'] . '円');
            }
            if (!empty($data['note'])) $lines[] = $fmt('注記', $data['note']);
        } elseif ($requestType === 'expense') {
            if (!empty($data['lines']) && is_array($data['lines'])) {
                $idx = 1;
                foreach ($data['lines'] as $line) {
                    if (!is_array($line)) continue;
                    $rowParts = [];
                    if (!empty($line['date'])) {
                        $rowParts[] = '日付: ' . $line['date'];
                    }
                    if (!empty($line['content'])) {
                        $rowParts[] = '内容: ' . $line['content'];
                    }
                    if (!empty($line['payee'])) {
                        $rowParts[] = '支払先: ' . $line['payee'];
                    }
                    if (isset($line['amount']) && $line['amount'] !== '') {
                        $rowParts[] = '金額（税抜）: ' . $line['amount'] . '円';
                    }
                    if (isset($line['tax']) && $line['tax'] !== '') {
                        $rowParts[] = '消費税: ' . $line['tax'] . '円';
                    }
                    if (!empty($line['reduced_tax'])) {
                        $rowParts[] = '軽減税率: ' . $line['reduced_tax'];
                    }
                    if (!empty($line['note'])) {
                        $rowParts[] = '備考: ' . $line['note'];
                    }
                    if (!empty($rowParts)) {
                        $lines[] = '明細' . $idx . ': ' . implode(' / ', $rowParts);
                        $idx++;
                    }
                }
            }
            if (isset($data['subtotal_amount']) && $data['subtotal_amount'] !== '') {
                $lines[] = $fmt('小計（金額（税抜））', $data['subtotal_amount'] . '円');
            }
            if (isset($data['subtotal_tax']) && $data['subtotal_tax'] !== '') {
                $lines[] = $fmt('小計（消費税）', $data['subtotal_tax'] . '円');
            }
            if (isset($data['total_with_tax']) && $data['total_with_tax'] !== '') {
                $lines[] = $fmt('合計（税込）', $data['total_with_tax'] . '円');
            }
            if (!empty($data['note'])) $lines[] = $fmt('注記', $data['note']);
        } elseif ($requestType === 'trip_expense') {
            // 出張旅費精算書（削除メール用フォーマット）
            if (!empty($data['destination'])) $lines[] = $fmt('出張先', $data['destination']);
            if (!empty($data['start_date'])) $lines[] = $fmt('期間（開始）', $data['start_date']);
            if (!empty($data['end_date'])) $lines[] = $fmt('期間（終了）', $data['end_date']);
            if (!empty($data['settlement_date'])) $lines[] = $fmt('精算日', $data['settlement_date']);
            if (!empty($data['trip_type'])) $lines[] = $fmt('国内/海外', $data['trip_type']);

            if (isset($data['per_diem']) && $data['per_diem'] !== '') $lines[] = $fmt('日当', $data['per_diem'] . '円');
            if (isset($data['trip_days']) && $data['trip_days'] !== '') $lines[] = $fmt('日間', $data['trip_days']);
            if (isset($data['trip_allowance']) && $data['trip_allowance'] !== '') $lines[] = $fmt('出張手当', $data['trip_allowance'] . '円');

            if (isset($data['advance_amount']) && $data['advance_amount'] !== '') $lines[] = $fmt('仮払金', $data['advance_amount'] . '円');
            if (isset($data['line_total']) && $data['line_total'] !== '') $lines[] = $fmt('明細合計', $data['line_total'] . '円');
            if (isset($data['net_total']) && $data['net_total'] !== '') $lines[] = $fmt('仮払金差引合計', $data['net_total'] . '円');
            if (isset($data['final_amount']) && $data['final_amount'] !== '') $lines[] = $fmt('精算額', $data['final_amount'] . '円');

            if (!empty($data['receipts']) && is_array($data['receipts'])) {
                $names = array_map(function ($a) {
                    return isset($a['original_name']) ? $a['original_name'] : (isset($a['filename']) ? $a['filename'] : '');
                }, $data['receipts']);
                $names = array_filter($names);
                if (count($names)) {
                    $lines[] = $fmt('請求書・領収書等', implode('、', $names));
                }
            }

            if (!empty($data['lines']) && is_array($data['lines'])) {
                $idx = 1;
                foreach ($data['lines'] as $line) {
                    if (!is_array($line)) continue;
                    $rowParts = [];
                    if (!empty($line['date'])) $rowParts[] = '日付: ' . $line['date'];
                    if (!empty($line['item'])) $rowParts[] = '項目: ' . $line['item'];
                    if (isset($line['transportation']) && $line['transportation'] !== '') $rowParts[] = '交通費: ' . $line['transportation'] . '円';
                    if (isset($line['accommodation']) && $line['accommodation'] !== '') $rowParts[] = '宿泊費: ' . $line['accommodation'] . '円';
                    if (isset($line['entertainment']) && $line['entertainment'] !== '') $rowParts[] = '交際費: ' . $line['entertainment'] . '円';
                    if (isset($line['meal']) && $line['meal'] !== '') $rowParts[] = '食費: ' . $line['meal'] . '円';
                    if (isset($line['other']) && $line['other'] !== '') $rowParts[] = 'その他: ' . $line['other'] . '円';
                    if (isset($line['total']) && $line['total'] !== '') $rowParts[] = '合計: ' . $line['total'] . '円';
                    if (!empty($line['note'])) $rowParts[] = '備考: ' . $line['note'];
                    if (!empty($rowParts)) {
                        $lines[] = '明細' . $idx . ': ' . implode(' / ', $rowParts);
                        $idx++;
                    }
                }
            }

            if (!empty($data['note'])) $lines[] = $fmt('備考', $data['note']);
        } elseif ($requestType === 'commuting_allowance') {
            // 通勤手当申請書（削除メール用フォーマット）
            if (!empty($data['application_type'])) $lines[] = $fmt('申請区分', $data['application_type']);
            if (!empty($data['address'])) $lines[] = $fmt('住所', $data['address']);
            if (!empty($data['nearest_station'])) $lines[] = $fmt('最寄駅', $data['nearest_station']);
            if (!empty($data['effective_from'])) $lines[] = $fmt('適用開始日', $data['effective_from']);

            if (isset($data['total_amount']) && $data['total_amount'] !== '') $lines[] = $fmt('合計片道運賃', $data['total_amount'] . '円');
            if (isset($data['one_month_commuter_pass']) && $data['one_month_commuter_pass'] !== '') $lines[] = $fmt('１か月定期代', $data['one_month_commuter_pass'] . '円');

            if (!empty($data['receipts']) && is_array($data['receipts'])) {
                $names = array_map(function ($a) {
                    return isset($a['original_name']) ? $a['original_name'] : (isset($a['filename']) ? $a['filename'] : '');
                }, $data['receipts']);
                $names = array_filter($names);
                if (count($names)) {
                    $lines[] = $fmt('請求書・領収書等', implode('、', $names));
                }
            }

            if (!empty($data['lines']) && is_array($data['lines'])) {
                $idx = 1;
                foreach ($data['lines'] as $line) {
                    if (!is_array($line)) continue;
                    $rowParts = [];
                    if (!empty($line['railway_company'])) $rowParts[] = '鉄道会社名: ' . $line['railway_company'];
                    if (!empty($line['line_name'])) $rowParts[] = '路線名: ' . $line['line_name'];
                    if (!empty($line['section_from']) || !empty($line['section_to'])) {
                        $rowParts[] = '利用区間: ' . ($line['section_from'] ?? '') . ' → ' . ($line['section_to'] ?? '');
                    }
                    if (isset($line['one_way_fare']) && $line['one_way_fare'] !== '') $rowParts[] = '片道運賃: ' . $line['one_way_fare'] . '円';
                    if (!empty($rowParts)) {
                        $lines[] = '明細' . $idx . ': ' . implode(' / ', $rowParts);
                        $idx++;
                    }
                }
            }

            if (!empty($data['note'])) $lines[] = $fmt('備考', $data['note']);
        } elseif ($requestType === 'purchase') {
            if (!empty($data['lines']) && is_array($data['lines'])) {
                $idx = 1;
                foreach ($data['lines'] as $line) {
                    if (!is_array($line)) continue;
                    $rowParts = [];
                    if (!empty($line['manufacturer'])) $rowParts[] = 'メーカー: ' . $line['manufacturer'];
                    if (!empty($line['product_code'])) $rowParts[] = '商品コード: ' . $line['product_code'];
                    if (!empty($line['product_name'])) $rowParts[] = '商品名: ' . $line['product_name'];
                    if (isset($line['quantity']) && $line['quantity'] !== '') $rowParts[] = '数量: ' . $line['quantity'];
                    if (isset($line['unit_price']) && $line['unit_price'] !== '') $rowParts[] = '単価: ' . $line['unit_price'] . '円';
                    if (isset($line['amount_with_tax']) && $line['amount_with_tax'] !== '') $rowParts[] = '金額（税込み）: ' . $line['amount_with_tax'] . '円';
                    if (!empty($rowParts)) {
                        $lines[] = '明細' . $idx . ': ' . implode(' / ', $rowParts);
                        $idx++;
                    }
                }
            }
            if (isset($data['total_amount']) && $data['total_amount'] !== '') {
                $lines[] = $fmt('合計金額', $data['total_amount'] . '円');
            }
            if (!empty($data['reason'])) $lines[] = $fmt('事由・用途', $data['reason']);
            if (!empty($data['note'])) $lines[] = $fmt('注記', $data['note']);
        } elseif ($requestType === 'it_support') {
            if (!empty($data['category'])) $lines[] = $fmt('区分', $data['category']);
            if (!empty($data['subject'])) $lines[] = $fmt('件名', $data['subject']);
            if (!empty($data['description'])) $lines[] = $fmt('内容・詳細', $data['description']);
            if (!empty($data['priority'])) $lines[] = $fmt('緊急度', $data['priority']);
            if (!empty($data['attachments']) && is_array($data['attachments'])) {
                $names = array_map(function ($a) {
                    return isset($a['original_name']) ? $a['original_name'] : (isset($a['filename']) ? $a['filename'] : '');
                }, $data['attachments']);
                $lines[] = $fmt('添付資料', implode('、', array_filter($names)));
            }
            if (!empty($data['note'])) $lines[] = $fmt('注記', $data['note']);
        } else {
            foreach ($data as $k => $v) {
                if ($v === null || $v === '' || is_array($v)) continue;
                $lines[] = $k . ': ' . trim((string) $v);
            }
        }
        $lines = array_filter($lines);
        if (empty($lines)) return '';
        return "【申請内容】\n" . implode("\n", $lines);
    }

    /**
     * Format diff (old -> new) between two data snapshots for email when editing.
     * Chỉ liệt kê những trường có thay đổi.
     *
     * @param string $requestType
     * @param mixed $oldDataRaw  JSON string hoặc array cũ
     * @param mixed $newDataRaw  JSON string hoặc array mới
     * @return string
     */
    private function formatRequestDiffForEmail($requestType, $oldDataRaw, $newDataRaw) {
        $old = is_array($oldDataRaw) ? $oldDataRaw : (is_string($oldDataRaw) ? json_decode($oldDataRaw, true) : []);
        $new = is_array($newDataRaw) ? $newDataRaw : (is_string($newDataRaw) ? json_decode($newDataRaw, true) : []);
        if (!is_array($old) || !is_array($new)) {
            return '';
        }
        $lines = [];
        $addDiff = function($label, $key) use (&$lines, $old, $new) {
            $ov = array_key_exists($key, $old) ? $old[$key] : '';
            $nv = array_key_exists($key, $new) ? $new[$key] : '';
            if ($ov === $nv) {
                return;
            }
            // Chuyển array thành chuỗi đọc được
            if (is_array($ov)) $ov = implode('、', $ov);
            if (is_array($nv)) $nv = implode('、', $nv);
            $ovStr = trim((string) $ov);
            $nvStr = trim((string) $nv);
            if ($ovStr === '' && $nvStr === '') {
                return;
            }
            if ($ovStr === '') $ovStr = '（なし）';
            if ($nvStr === '') $nvStr = '（なし）';
            $lines[] = $label . ': ' . $ovStr . ' → ' . $nvStr;
        };

        if ($requestType === 'leave') {
            $addDiff('期間（開始）', 'start_datetime');
            $addDiff('期間（終了）', 'end_datetime');
            $addDiff('日間', 'days');
            $addDiff('休暇種別', 'leave_type');
            $addDiff('有給休暇', 'paid_type');
            $addDiff('無給休暇', 'unpaid_type');
            $addDiff('事由', 'reason');
            $addDiff('注記', 'note');
        } elseif ($requestType === 'outing') {
            $addDiff('日付', 'date');
            $addDiff('開始時刻', 'start_time');
            $addDiff('終了時刻', 'end_time');
            $addDiff('行先', 'destination');
            $addDiff('事由', 'reason');
            $addDiff('注記', 'note');
        } elseif ($requestType === 'trip') {
            $addDiff('期間（開始）', 'start_datetime');
            $addDiff('期間（終了）', 'end_datetime');
            $addDiff('日間', 'days');
            $addDiff('行先', 'destination');
            $addDiff('事由', 'reason');
            $addDiff('注記', 'note');
        } elseif ($requestType === 'holiday_work') {
            $addDiff('日付', 'date');
            $addDiff('開始時刻', 'start_time');
            $addDiff('休憩時間', 'break_time');
            $addDiff('終了時刻', 'end_time');
            $addDiff('事由', 'reason');
            $addDiff('注記', 'note');
        } elseif ($requestType === 'overtime') {
            $addDiff('日付', 'date');
            $addDiff('開始時刻', 'start_time');
            $addDiff('終了時刻', 'end_time');
            $addDiff('用途', 'purpose');
            $addDiff('備考', 'note');
        } elseif ($requestType === 'attendance_correction') {
            $addDiff('日付', 'date');
            $addDiff('時間', 'time');
            $addDiff('区分', 'correction_type');
            $addDiff('事由', 'reason');
            $addDiff('注記', 'note');
        } elseif ($requestType === 'travel_expense') {
            // 明細行の差分は項目ごとに見るとノイズが多いので、ここでは合計金額と備考のみを差分表示
            $addDiff('合計金額', 'total_amount');
            $addDiff('備考', 'note');
        } elseif ($requestType === 'expense') {
            // 経費精算書も明細行が多くなるため、小計・合計と備考のみを差分表示
            $addDiff('小計（金額（税抜））', 'subtotal_amount');
            $addDiff('小計（消費税）', 'subtotal_tax');
            $addDiff('合計（税込）', 'total_with_tax');
            $addDiff('備考', 'note');
        } elseif ($requestType === 'trip_expense') {
            $addDiff('出張先', 'destination');
            $addDiff('期間（開始）', 'start_date');
            $addDiff('期間（終了）', 'end_date');
            $addDiff('精算日', 'settlement_date');
            $addDiff('国内/海外', 'trip_type');
            $addDiff('日当', 'per_diem');
            $addDiff('日間', 'trip_days');
            $addDiff('出張手当', 'trip_allowance');
            $addDiff('仮払金', 'advance_amount');
            $addDiff('明細合計', 'line_total');
            $addDiff('仮払金差引合計', 'net_total');
            $addDiff('精算額', 'final_amount');
            $addDiff('備考', 'note');
        } elseif ($requestType === 'commuting_allowance') {
            $addDiff('申請区分', 'application_type');
            $addDiff('住所', 'address');
            $addDiff('最寄駅', 'nearest_station');
            $addDiff('適用開始日', 'effective_from');
            $addDiff('１か月定期代', 'one_month_commuter_pass');
            $addDiff('合計片道運賃', 'total_amount');
            $addDiff('備考', 'note');
        } elseif ($requestType === 'purchase') {
            $addDiff('合計金額', 'total_amount');
            $addDiff('事由・用途', 'reason');
            $addDiff('注記', 'note');
        } elseif ($requestType === 'it_support') {
            $addDiff('区分', 'category');
            $addDiff('件名', 'subject');
            $addDiff('内容・詳細', 'description');
            $addDiff('緊急度', 'priority');
            $oldAtt = isset($old['attachments']) && is_array($old['attachments']) ? $old['attachments'] : [];
            $newAtt = isset($new['attachments']) && is_array($new['attachments']) ? $new['attachments'] : [];
            $oldNames = array_map(function ($a) {
                return isset($a['original_name']) ? $a['original_name'] : (isset($a['filename']) ? $a['filename'] : '');
            }, $oldAtt);
            $newNames = array_map(function ($a) {
                return isset($a['original_name']) ? $a['original_name'] : (isset($a['filename']) ? $a['filename'] : '');
            }, $newAtt);
            if (implode('、', $oldNames) !== implode('、', $newNames)) {
                $lines[] = '添付資料: ' . (count($oldNames) ? implode('、', $oldNames) : '（なし）') . ' → ' . (count($newNames) ? implode('、', $newNames) : '（なし）');
            }
            $addDiff('注記', 'note');
        } else {
            foreach ($new as $k => $v) {
                if (is_array($v)) continue;
                $addDiff($k, $k);
            }
        }

        $lines = array_filter($lines);
        if (empty($lines)) {
            return '';
        }
        return "【変更内容】\n" . implode("\n", $lines);
    }

    private function sendRequestCreatedEmail($requestId, $requestType, $applicantUserId, $approverUserId, $subject, $applicantName) {
        $approverIds = $this->parseApproverUserIds($approverUserId);
        if (empty($approverIds)) {
            return;
        }
        $url = $this->getBaseUrl() . '/form/detail.php?id=' . intval($requestId);
        $footText = "ご承認のほど、よろしくお願いいたします。\n";
        $detail = $this->formatRequestDetailForEmail($requestId);
        $body = $subject . "。\n\n"
              . "申請者: " . $applicantName . "\n\n"
              . ($detail ? $detail . "\n\n" : '')
              . "詳細: " . $url . "\n"
              . $footText;
        foreach ($approverIds as $uid) {
            $this->sendEmailToUser($uid, $subject, $body);
        }
    }

    private function sendRequestStatusEmail($requestId, $requestType, $status, $userId, $applicantName) {
        if (empty($userId)) {
            return;
        }
        $typeLabel = $this->getRequestTypeLabel($requestType);
        $url = $this->getBaseUrl() . '/form/detail.php?id=' . intval($requestId);
        // Tiêu đề: [người thao tác] [申請者][種別]...
        $operator = isset($_SESSION['realname']) ? $_SESSION['realname'] : '';
        $statusText = $status === 'approved' ? '承認' : ($status === 'rejected' ? '却下' : '更新');
        if($operator !== $applicantName) {
            $subject = ($operator !== '' ? '[' . $operator . '] ' : '') . 'が[' . $applicantName . ']の[' . $typeLabel . ']の申請を' . $statusText . 'しました';
        }
        else{
            $subject = ($operator !== '' ? '[' . $operator . '] ' : '') . 'が[' . $typeLabel . ']の申請を' . $statusText . 'しました';
        }
        $detail = $this->formatRequestDetailForEmail($requestId);
        
        $body = $subject . "。\n\n"
              . "申請者: " . $applicantName . "\n\n"
              . ($detail ? $detail . "\n\n" : '')
              . "詳細: " . $url . "\n";
        $this->sendEmailToUser($userId, $subject, $body);
    }

    private function sendRequestDeletedEmail($requestId, $requestType, $approverUserId, $applicantUserId, $requestDataJson = null) {
        if (empty($approverUserId)) {
            return;
        }
        $typeLabel = $this->getRequestTypeLabel($requestType);
        $applicantName = $this->getRealname($applicantUserId);
        $url = $this->getBaseUrl() . '/form/detail.php?id=' . intval($requestId);
        // Tiêu đề: [người thao tác] [申請者][種別]...
        $operator = isset($_SESSION['realname']) ? $_SESSION['realname'] : '';
        if($operator !== $applicantName) {
            $subject = ($operator !== '' ? '[' . $operator . '] ' : '') . 'が[' . $applicantName . ']の[' . $typeLabel . ']の申請を削除しました';
        }
        else{
            $subject = ($operator !== '' ? '[' . $operator . '] ' : '') . 'が[' . $typeLabel . ']の申請を削除しました';
        }
        $detail = $requestDataJson !== null
            ? $this->formatRequestDetailFromData($requestType, $requestDataJson)
            : $this->formatRequestDetailForEmail($requestId);
        $body = $subject . "。\n\n"
              . "申請者: " . $applicantName . "\n\n"
              . ($detail ? $detail . "\n\n" : '')
              . "詳細: " . $url . "\n";
        $this->sendEmailToUser($approverUserId, $subject, $body);
    }

    /**
     * Gửi email khi nội dung đơn được chỉnh sửa.
     * - Người nhận: người được chỉ định duyệt (approver_user_id). Nếu không có thì gửi cho administrator.
     * - Chủ đề: [người thao tác] [申請者][種別]が更新されました
     * - Nội dung: hiển thị 申請者, chi tiết thay đổi (旧 -> 新) và link chi tiết.
     */
    private function sendRequestUpdatedEmail($requestId, $requestType, $status, $userId, $oldDataJson = null) {
        // Lấy người chỉ định duyệt, nếu không có thì lấy admin
        $targetUserIds = [];
        $req = $this->fetchOne("SELECT approver_user_id FROM {$this->table} WHERE id = " . intval($requestId));
        $designated = $req ? $this->parseApproverUserIds($req['approver_user_id']) : array();
        if (!empty($designated)) {
            $targetUserIds = $designated;
        } else {
            $admins = $this->fetchAll("SELECT userid FROM " . DB_PREFIX . "user WHERE authority = 'administrator' AND (is_suspend IS NULL OR is_suspend = 0)");
            $targetUserIds = array_map(function($a){ return $a['userid']; }, $admins);
        }
        if (empty($targetUserIds)) {
            return;
        }

        $typeLabel = $this->getRequestTypeLabel($requestType);
        $applicantName = $this->getRealname($userId);
        $operator = isset($_SESSION['realname']) ? $_SESSION['realname'] : '';
        $url = $this->getBaseUrl() . '/form/detail.php?id=' . intval($requestId);
        if($operator !== $applicantName) {
            $subject = ($operator !== '' ? '[' . $operator . '] ' : '') . 'が[' . $applicantName . ']の[' . $typeLabel . ']の申請を編集しました';
        }
        else{
            $subject = ($operator !== '' ? '[' . $operator . '] ' : '') . 'が[' . $typeLabel . ']の申請を編集しました';
        }
        // Ưu tiên hiển thị phần diff (cũ -> mới); nếu không có diff thì fallback về chi tiết hiện tại
        $detail = '';
        if ($oldDataJson !== null) {
            $currentRow = $this->fetchOne("SELECT data FROM {$this->table} WHERE id = " . intval($requestId));
            if ($currentRow && isset($currentRow['data'])) {
                $detail = $this->formatRequestDiffForEmail($requestType, $oldDataJson, $currentRow['data']);
            }
        }
        if ($detail === '') {
            $detail = $this->formatRequestDetailForEmail($requestId);
        }
        $footText = "ご承認のほど、よろしくお願いいたします。\n";
        $body = $subject . "。\n\n"
              . "申請者: " . $applicantName . "\n\n"
              . ($detail ? $detail . "\n\n" : '')
              . "詳細: " . $url . "\n"
              . $footText;

        // Gửi cho từng user mục tiêu
        foreach ($targetUserIds as $uid) {
            $this->sendEmailToUser($uid, $subject, $body);
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

        // Giữ nguyên tên gốc (kể cả tiếng Nhật), chỉ loại bỏ ký tự nguy hiểm
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $baseName = $ext
            ? mb_substr($originalName, 0, mb_strrpos($originalName, '.'), 'UTF-8')
            : $originalName;
        // Loại bỏ dấu / \ và ký tự điều khiển
        $baseName = str_replace(['/', '\\'], '_', $baseName);
        $baseName = preg_replace('/[\x00-\x1F\x7F]/u', '', $baseName);
        $baseName = trim($baseName);
        if ($baseName === '') {
            $baseName = 'file';
        }
        // Giới hạn độ dài phần tên gốc để tránh quá 255 byte trên filesystem
        if (mb_strlen($baseName, 'UTF-8') > 100) {
            $baseName = mb_substr($baseName, 0, 100, 'UTF-8');
        }

        // Sinh chuỗi duy nhất và gắn SAU tên gốc
        $uniqueToken = uniqid();
        $uniqueName = $baseName . '_' . $uniqueToken . ($ext ? '.' . $ext : '');
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
            case 'purchase':
                return '購入申請';
            case 'it_support':
                return 'ITサポート';
            default:
                return '申請';
        }
    }

} 