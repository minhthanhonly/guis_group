<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Simple test data for mention functionality
$testUsers = array(
    array(
        'id' => 1,
        'userid' => 'admin',
        'user_name' => '管理者',
        'role' => 'administrator',
        'authority' => 'administrator'
    ),
    array(
        'id' => 2,
        'userid' => 'user1',
        'user_name' => '田中太郎',
        'role' => 'manager',
        'authority' => 'manager'
    ),
    array(
        'id' => 3,
        'userid' => 'user2',
        'user_name' => '佐藤花子',
        'role' => 'member',
        'authority' => 'member'
    ),
    array(
        'id' => 4,
        'userid' => 'user3',
        'user_name' => '鈴木一郎',
        'role' => 'member',
        'authority' => 'member'
    ),
    array(
        'id' => 5,
        'userid' => 'user4',
        'user_name' => '高橋美咲',
        'role' => 'member',
        'authority' => 'member'
    )
);

// Filter by search term if provided
$search = isset($_GET['search']) ? $_GET['search'] : '';
if ($search) {
    $testUsers = array_filter($testUsers, function($user) use ($search) {
        return stripos($user['user_name'], $search) !== false;
    });
}

echo json_encode(array_values($testUsers));
?> 