<?php
echo "<h1>Firebase API Test</h1>";

// Test API endpoints
$baseUrl = 'http://localhost/caily'; // Adjust this to your local URL

echo "<h2>1. Test API Configuration</h2>";
$configUrl = $baseUrl . '/api/NotificationAPI.php?method=get_config';
echo "<p>Testing: $configUrl</p>";

$configResponse = @file_get_contents($configUrl);
if ($configResponse !== false) {
    $config = json_decode($configResponse, true);
    echo "<pre>";
    print_r($config);
    echo "</pre>";
} else {
    echo "<p style='color: red;'>❌ Failed to get configuration</p>";
}

echo "<h2>2. Test API Connection</h2>";
$testUrl = $baseUrl . '/api/NotificationAPI.php?method=test';
echo "<p>Testing: $testUrl</p>";

$testResponse = @file_get_contents($testUrl);
if ($testResponse !== false) {
    $result = json_decode($testResponse, true);
    echo "<pre>";
    print_r($result);
    echo "</pre>";
} else {
    echo "<p style='color: red;'>❌ Failed to test connection</p>";
}

echo "<h2>3. Test Send Notification</h2>";
$sendUrl = $baseUrl . '/api/NotificationAPI.php?method=send';
echo "<p>Testing: $sendUrl</p>";

// Use cURL for POST request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $sendUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'channel' => 'global',
    'event' => 'test_notification',
    'data' => [
        'title' => 'API Test',
        'message' => 'This is a test from API endpoint',
        'timestamp' => date('Y-m-d H:i:s')
    ]
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$sendResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($sendResponse !== false) {
    $result = json_decode($sendResponse, true);
    echo "<p>HTTP Code: $httpCode</p>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
} else {
    echo "<p style='color: red;'>❌ Failed to send notification</p>";
}

echo "<h2>4. Manual Test Instructions</h2>";
echo "<p>If the API tests fail, you can test manually:</p>";
echo "<ol>";
echo "<li>Open your browser</li>";
echo "<li>Go to: <a href='$configUrl' target='_blank'>$configUrl</a></li>";
echo "<li>Go to: <a href='$testUrl' target='_blank'>$testUrl</a></li>";
echo "<li>Use a tool like Postman to test POST requests to: $sendUrl</li>";
echo "</ol>";

echo "<h2>5. Next Steps</h2>";
echo "<p>If all tests pass:</p>";
echo "<ol>";
echo "<li>✅ Firebase backend is working</li>";
echo "<li>✅ API endpoints are accessible</li>";
echo "<li>Next: Test frontend integration with <a href='test_firebase_notification.html' target='_blank'>test_firebase_notification.html</a></li>";
echo "</ol>";
?> 