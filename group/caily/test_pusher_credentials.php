<?php
require_once('env_config.php');

echo "<h1>Pusher Credentials Test</h1>";

// Get Pusher configuration
$pusher_config = EnvConfig::getPusherConfig();

echo "<h2>Configuration from .env:</h2>";
echo "<pre>";
foreach ($pusher_config as $key => $value) {
    if ($key === 'secret') {
        echo "$key: " . substr($value, 0, 10) . "...\n";
    } else {
        echo "$key: $value\n";
    }
}
echo "</pre>";

// Test 1: Check if credentials are not default
echo "<h2>Test 1: Credentials Check</h2>";
if ($pusher_config['app_id'] === 'your_app_id' || 
    $pusher_config['key'] === 'your_key' || 
    $pusher_config['secret'] === 'your_secret') {
    echo "<p style='color: red;'>❌ Error: You're still using default credentials. Please update your .env file with real Pusher credentials.</p>";
} else {
    echo "<p style='color: green;'>✅ Credentials look good (not default values)</p>";
}

// Test 2: Test network connectivity to Pusher
echo "<h2>Test 2: Network Connectivity</h2>";
$cluster = $pusher_config['cluster'];
$app_id = $pusher_config['app_id'];
$url = "https://api-$cluster.pusherapp.com/apps/$app_id/events";

echo "<p>Testing connection to: $url</p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "<p style='color: red;'>❌ Network Error: $error</p>";
} else {
    echo "<p style='color: green;'>✅ Network connection successful (HTTP $http_code)</p>";
}

// Test 3: Test with minimal data
echo "<h2>Test 3: Minimal API Test</h2>";
if ($pusher_config['app_id'] !== 'your_app_id') {
    try {
        require_once('application/library/pusher-php-server/src/Pusher.php');
        $pusher = new Pusher\Pusher(
            $pusher_config['key'],
            $pusher_config['secret'],
            $pusher_config['app_id'],
            [
                'cluster' => $pusher_config['cluster'],
                'useTLS' => $pusher_config['useTLS']
            ]
        );
        
        echo "<p>✅ Pusher instance created</p>";
        
        // Test with minimal data
        $result = $pusher->trigger('test-channel', 'test-event', ['test' => 'data']);
        echo "<p style='color: green;'>✅ API call successful</p>";
        echo "<pre>" . print_r($result, true) . "</pre>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ API Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠️ Skipping API test - using default credentials</p>";
}

// Test 4: Check .env file
echo "<h2>Test 4: .env File Check</h2>";
if (file_exists('.env')) {
    echo "<p style='color: green;'>✅ .env file exists</p>";
    $env_content = file_get_contents('.env');
    if (strpos($env_content, 'your_app_id') !== false) {
        echo "<p style='color: red;'>❌ .env file still contains placeholder values</p>";
    } else {
        echo "<p style='color: green;'>✅ .env file appears to have real values</p>";
    }
} else {
    echo "<p style='color: red;'>❌ .env file not found</p>";
}

echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li>Make sure you have a real Pusher account at <a href='https://pusher.com' target='_blank'>pusher.com</a></li>";
echo "<li>Create a new app in your Pusher dashboard</li>";
echo "<li>Copy the real credentials to your .env file</li>";
echo "<li>Make sure your server can make outbound HTTPS requests</li>";
echo "</ol>";
?> 