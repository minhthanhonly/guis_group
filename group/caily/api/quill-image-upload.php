<?php
require_once('../application/loader.php');

header('Content-Type: application/json');

// Kiểm tra quyền truy cập
if (!isset($_SESSION['userid'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Kiểm tra method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Debug logging
    error_log('Upload request received: ' . json_encode($_POST));
    error_log('Files received: ' . json_encode($_FILES));
    
    // Kiểm tra file upload
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No image file uploaded or upload error: ' . ($_FILES['image']['error'] ?? 'unknown'));
    }

    // Kiểm tra project_id
    if (!isset($_POST['project_id']) || empty($_POST['project_id'])) {
        throw new Exception('Project ID is required. Received: ' . json_encode($_POST));
    }
    
    // Kiểm tra task_id (optional)
    $taskId = isset($_POST['task_id']) && !empty($_POST['task_id']) ? intval($_POST['task_id']) : null;
    
    $projectId = intval($_POST['project_id']);
    if ($projectId <= 0) {
        throw new Exception('Invalid Project ID: ' . $_POST['project_id']);
    }
    
    $file = $_FILES['image'];
    
    // Kiểm tra file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.');
    }
    
    // Kiểm tra file size (max 5MB)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        throw new Exception('File size too large. Maximum size is 5MB.');
    }
    
    // Tạo thư mục upload theo project_id và task_id (nếu có)
    if ($taskId) {
        $uploadDir = '../assets/upload/project-images/' . $projectId . '/tasks/' . $taskId . '/';
    } else {
        $uploadDir = '../assets/upload/project-images/' . $projectId . '/';
    }
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Giữ nguyên tên file gốc để có thể replace
    $filename = $file['name'];
    
    // Kiểm tra nếu file đã tồn tại thì thêm timestamp
    $filepath = $uploadDir . $filename;
    
    // Upload file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to upload file');
    }
    
    // Tạo URL để trả về
    if ($taskId) {
        $imageUrl = '/assets/upload/project-images/' . $projectId . '/tasks/' . $taskId . '/' . $filename;
    } else {
        $imageUrl = '/assets/upload/project-images/' . $projectId . '/' . $filename;
    }
    
    // Trả về kết quả cho Quill
    echo json_encode([
        'success' => true,
        'url' => $imageUrl,
        'filename' => $filename
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?> 