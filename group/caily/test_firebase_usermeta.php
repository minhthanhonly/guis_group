<?php
require_once __DIR__ . '/application/library/firebase_helper.php';

$userId = isset($_GET['user_id']) ? $_GET['user_id'] : 'testuser';
$meta = [
    'last_notification_time' => time(),
    'last_notification_id' => rand(1000, 9999)
];

$firebase = new FirebaseHelper();
$result = $firebase->updateUserNotificationMeta($userId, $meta);

header('Content-Type: application/json');
echo json_encode([
    'user_id' => $userId,
    'meta' => $meta,
    'result' => $result
]); 