<?php
/**
 * Download file from application/upload/form/ (e.g. 交通費精算書 attachment)
 * Usage: form/download.php?file=unique_filename.pdf&request_id=123
 * Only 申請者 (applicant), 承認者 (designated approver), or administrator may download.
 */
require_once('../application/loader.php');
$filename = isset($_GET['file']) ? basename($_GET['file']) : '';
$requestId = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
if ($filename === '' || preg_match('/\.\./', $filename)) {
    http_response_code(400);
    exit;
}
if ($requestId <= 0) {
    http_response_code(403);
    exit;
}
$userId = isset($_SESSION['userid']) ? $_SESSION['userid'] : '';
$isAdmin = isset($_SESSION['authority']) && $_SESSION['authority'] === 'administrator';
if ($userId === '') {
    http_response_code(403);
    exit;
}
require_once(dirname(__DIR__) . '/application/model/request.php');
$requestModel = new Request();
$requestModel->connect();
$row = $requestModel->fetchOne("SELECT user_id, approver_user_id FROM " . DB_PREFIX . "requests WHERE id = " . $requestId);
if (!$row) {
    http_response_code(404);
    exit;
}
$isApplicant = ($row['user_id'] === $userId);
$isApprover = !empty($row['approver_user_id']) && $row['approver_user_id'] === $userId;
if (!$isAdmin && !$isApplicant && !$isApprover) {
    http_response_code(403);
    exit;
}
$uploadDir = dirname(__DIR__) . '/application/upload/form/';
$filePath = $uploadDir . $filename;
if (!is_file($filePath)) {
    http_response_code(404);
    exit;
}
$mime = @mime_content_type($filePath);
$downloadName = basename($filename);
$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

// Thiết lập header theo trình duyệt để hiển thị đúng tên file tiếng Nhật
if (preg_match('/MSIE|Trident/', $userAgent)) {
    // IE: dùng Shift_JIS
    $ieName = mb_convert_encoding($downloadName, 'SJIS', 'UTF-8');
    header('Cache-Control: public');
    header('Pragma: public');
    header('Content-Type: application/octet-stream; name="' . $ieName . '"');
    header('Content-Disposition: attachment; filename="' . $ieName . '"');
} else {
    // Trình duyệt hiện đại: dùng UTF-8 với filename*
    $encoded = rawurlencode($downloadName);
    $ext = '';
    if (strpos($downloadName, '.') !== false) {
        $ext = substr($downloadName, strrpos($downloadName, '.'));
    }
    $fallback = 'download' . $ext;
    header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
    header("Content-Disposition: attachment; filename=\"{$fallback}\"; filename*=UTF-8''{$encoded}");
}

header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
