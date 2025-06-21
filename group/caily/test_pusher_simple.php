<?php
require_once('env_config.php');

// Get Pusher configuration
$pusher_config = EnvConfig::getPusherConfig();

echo "<h1>Pusher Test</h1>";
echo "<h2>Configuration:</h2>";
echo "<pre>" . print_r($pusher_config, true) . "</pre>";

// Test if we can create Pusher instance
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
    echo "<h2>✅ Pusher instance created successfully</h2>";
    
    // Test trigger
    $test_data = [
        'message' => 'Test from simple endpoint',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo "<h2>Testing trigger...</h2>";
    $result = $pusher->trigger('notifications', 'test_event', $test_data);
    echo "<h3>✅ Trigger result:</h3>";
    echo "<pre>" . print_r($result, true) . "</pre>";
    
} catch (Exception $e) {
    echo "<h2>❌ Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}

// Test config endpoint
echo "<h2>Config endpoint test:</h2>";
echo "<p><a href='/api/NotificationAPI.php?action=config' target='_blank'>Test config endpoint</a></p>";

// Test trigger endpoint
echo "<h2>Trigger endpoint test:</h2>";
echo "<form method='post' action='/api/NotificationAPI.php?action=trigger'>";
echo "<input type='hidden' name='channel' value='notifications'>";
echo "<input type='hidden' name='event' value='test_event'>";
echo "<input type='hidden' name='data' value='" . json_encode($test_data) . "'>";
echo "<button type='submit'>Test Trigger Endpoint</button>";
echo "</form>";
?> 