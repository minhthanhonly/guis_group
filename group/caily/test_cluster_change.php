<?php
require_once('env_config.php');

echo "<h1>Testing Cluster Change</h1>";

// Get current configuration
$pusher_config = EnvConfig::getPusherConfig();

echo "<h2>Current Configuration:</h2>";
echo "<pre>";
foreach ($pusher_config as $key => $value) {
    if ($key === 'secret') {
        echo "$key: " . substr($value, 0, 10) . "...\n";
    } else {
        echo "$key: $value\n";
    }
}
echo "</pre>";

// Test different clusters
$clusters = ['ap1', 'ap2', 'us1', 'us2', 'eu'];

echo "<h2>Testing Different Clusters:</h2>";

foreach ($clusters as $cluster) {
    echo "<h3>Testing cluster: $cluster</h3>";
    
    $url = "https://api-$cluster.pusherapp.com";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $start_time = microtime(true);
    $response = curl_exec($ch);
    $end_time = microtime(true);
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $total_time = round(($end_time - $start_time) * 1000, 2);
    
    curl_close($ch);
    
    if ($error) {
        echo "<p style='color: red;'>❌ $cluster: $error (${total_time}ms)</p>";
    } else {
        echo "<p style='color: green;'>✅ $cluster: HTTP $http_code (${total_time}ms)</p>";
    }
}

echo "<h2>Recommendation:</h2>";
echo "<p>Based on the test results above, choose the cluster with the fastest response time and no errors.</p>";
echo "<p>Then update your .env file with:</p>";
echo "<pre>PUSHER_CLUSTER=best_cluster_name</pre>";

echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li>Choose the best cluster from the test above</li>";
echo "<li>Update your .env file with the new cluster</li>";
echo "<li>Test again with test_pusher_credentials.php</li>";
echo "<li>If successful, test with test_pusher.html</li>";
echo "</ol>";
?> 