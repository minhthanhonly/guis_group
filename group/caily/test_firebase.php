<?php
require_once 'env_config.php';
require_once 'application/library/firebase_helper.php';

echo "<h1>Firebase Configuration Test</h1>";

// Test environment configuration
echo "<h2>1. Environment Configuration</h2>";
$config = EnvConfig::getFirebaseConfig();
echo "<pre>";
print_r($config);
echo "</pre>";

// Test Firebase helper
echo "<h2>2. Firebase Helper Test</h2>";
try {
    $firebase = new FirebaseHelper();
    $result = $firebase->testConnection();
    
    echo "<h3>Connection Test Result:</h3>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    
    if ($result['success']) {
        echo "<p style='color: green;'>✅ Firebase connection successful!</p>";
        
        // Test sending a notification
        echo "<h3>3. Test Notification</h3>";
        $notificationResult = $firebase->sendToAll('test_notification', [
            'title' => 'Test Notification',
            'message' => 'This is a test notification from PHP',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        if ($notificationResult) {
            echo "<p style='color: green;'>✅ Test notification sent successfully!</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to send test notification</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Firebase connection failed: " . $result['error'] . "</p>";
        
        // Test different SSL configurations
        echo "<h3>3. SSL Configuration Test</h3>";
        $sslResults = $firebase->testConnectionWithSSL();
        
        echo "<h4>SSL Test Results:</h4>";
        echo "<pre>";
        print_r($sslResults);
        echo "</pre>";
        
        // Show which configuration works
        $workingConfig = null;
        foreach ($sslResults as $configName => $result) {
            if ($result['success']) {
                $workingConfig = $configName;
                break;
            }
        }
        
        if ($workingConfig) {
            echo "<p style='color: green;'>✅ Working SSL configuration found: <strong>$workingConfig</strong></p>";
        } else {
            echo "<p style='color: red;'>❌ No SSL configuration worked. Check your Firebase setup.</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Test API endpoint
echo "<h2>4. API Endpoint Test</h2>";
$apiUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/api/NotificationAPI.php';

echo "<h3>Test API Configuration:</h3>";
$configResponse = file_get_contents($apiUrl . '?method=get_config');
echo "<pre>";
print_r(json_decode($configResponse, true));
echo "</pre>";

echo "<h3>Test API Connection:</h3>";
$testResponse = file_get_contents($apiUrl . '?method=test');
echo "<pre>";
print_r(json_decode($testResponse, true));
echo "</pre>";

// SSL Certificate Information
echo "<h2>5. SSL Certificate Information</h2>";
echo "<h3>cURL SSL Info:</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://www.google.com');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_exec($ch);
$sslInfo = curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT);
curl_close($ch);

echo "<p>SSL Verify Result: " . ($sslInfo === 0 ? 'OK' : 'Failed (' . $sslInfo . ')') . "</p>";

// Check if cacert.pem exists
$cacertPaths = [
    __DIR__ . '/cacert.pem',
    'C:/xampp/php/extras/ssl/cacert.pem',
    'C:/wamp/bin/php/php8.x.x/extras/ssl/cacert.pem',
    '/etc/ssl/certs/ca-certificates.crt',
    '/usr/local/share/certs/ca-root-nss.crt'
];

echo "<h3>CA Certificate Paths:</h3>";
foreach ($cacertPaths as $path) {
    $exists = file_exists($path);
    echo "<p>" . ($exists ? "✅" : "❌") . " $path " . ($exists ? "(exists)" : "(not found)") . "</p>";
}

echo "<h2>6. Setup Instructions</h2>";
echo "<p>To complete Firebase setup:</p>";
echo "<ol>";
echo "<li>Create a Firebase project at <a href='https://console.firebase.google.com/' target='_blank'>Firebase Console</a></li>";
echo "<li>Enable Realtime Database in your Firebase project</li>";
echo "<li>Set up database rules to allow read/write access</li>";
echo "<li>Add your Firebase configuration to the .env file:</li>";
echo "</ol>";

echo "<pre>";
echo "FIREBASE_API_KEY=your_api_key\n";
echo "FIREBASE_AUTH_DOMAIN=your_project.firebaseapp.com\n";
echo "FIREBASE_PROJECT_ID=your_project_id\n";
echo "FIREBASE_STORAGE_BUCKET=your_project.appspot.com\n";
echo "FIREBASE_MESSAGING_SENDER_ID=your_sender_id\n";
echo "FIREBASE_APP_ID=your_app_id\n";
echo "FIREBASE_DATABASE_URL=https://your_project_id-default-rtdb.firebaseio.com\n";
echo "</pre>";

echo "<h2>7. SSL Troubleshooting</h2>";
echo "<p>If you're still having SSL issues:</p>";
echo "<ol>";
echo "<li><strong>For Development:</strong> The current configuration disables SSL verification (safe for local development)</li>";
echo "<li><strong>For Production:</strong> Download the latest CA certificate bundle:</li>";
echo "</ol>";

echo "<pre>";
echo "# Download latest CA certificates\n";
echo "curl -o cacert.pem https://curl.se/ca/cacert.pem\n";
echo "</pre>";

echo "<p>Then update the Firebase helper to use proper SSL verification:</p>";
echo "<pre>";
echo "curl_setopt(\$ch, CURLOPT_SSL_VERIFYPEER, true);\n";
echo "curl_setopt(\$ch, CURLOPT_SSL_VERIFYHOST, 2);\n";
echo "curl_setopt(\$ch, CURLOPT_CAINFO, __DIR__ . '/cacert.pem');\n";
echo "</pre>";

echo "<h2>8. Database Rules</h2>";
echo "<p>Set your Firebase Realtime Database rules to:</p>";
echo "<pre>";
echo "{\n";
echo "  \"rules\": {\n";
echo "    \"notifications\": {\n";
echo "      \".read\": true,\n";
echo "      \".write\": true\n";
echo "    },\n";
echo "    \"test\": {\n";
echo "      \".read\": true,\n";
echo "      \".write\": true\n";
echo "    }\n";
echo "  }\n";
echo "}\n";
echo "</pre>";
?> 