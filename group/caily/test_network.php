<?php
echo "<h1>Network Connectivity Test</h1>";

// Test 1: Basic internet connectivity
echo "<h2>Test 1: Basic Internet Connectivity</h2>";

$test_urls = [
    'https://www.google.com' => 'Google',
    'https://www.github.com' => 'GitHub',
    'https://api-ap3.pusherapp.com' => 'Pusher API',
    'https://pusher.com' => 'Pusher Website'
];

foreach ($test_urls as $url => $name) {
    echo "<h3>Testing $name ($url):</h3>";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $start_time = microtime(true);
    $response = curl_exec($ch);
    $end_time = microtime(true);
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $total_time = round(($end_time - $start_time) * 1000, 2);
    
    curl_close($ch);
    
    if ($error) {
        echo "<p style='color: red;'>❌ Error: $error (${total_time}ms)</p>";
    } else {
        echo "<p style='color: green;'>✅ Success: HTTP $http_code (${total_time}ms)</p>";
    }
}

// Test 2: DNS resolution
echo "<h2>Test 2: DNS Resolution</h2>";
$domains = [
    'api-ap3.pusherapp.com',
    'pusher.com',
    'google.com'
];

foreach ($domains as $domain) {
    $ip = gethostbyname($domain);
    if ($ip !== $domain) {
        echo "<p style='color: green;'>✅ $domain resolves to $ip</p>";
    } else {
        echo "<p style='color: red;'>❌ $domain DNS resolution failed</p>";
    }
}

// Test 3: Port connectivity
echo "<h2>Test 3: Port Connectivity</h2>";
$hosts = [
    'api-ap3.pusherapp.com:443' => 'Pusher API (HTTPS)',
    'pusher.com:443' => 'Pusher Website (HTTPS)'
];

foreach ($hosts as $host => $description) {
    list($domain, $port) = explode(':', $host);
    $connection = @fsockopen($domain, $port, $errno, $errstr, 5);
    
    if ($connection) {
        echo "<p style='color: green;'>✅ $description: Connected</p>";
        fclose($connection);
    } else {
        echo "<p style='color: red;'>❌ $description: Failed ($errstr)</p>";
    }
}

// Test 4: cURL info
echo "<h2>Test 4: cURL Information</h2>";
echo "<p>cURL version: " . curl_version()['version'] . "</p>";
echo "<p>SSL version: " . curl_version()['ssl_version'] . "</p>";
echo "<p>HTTP version: " . curl_version()['http_version'] . "</p>";

// Test 5: Environment info
echo "<h2>Test 5: Environment Information</h2>";
echo "<p>PHP version: " . phpversion() . "</p>";
echo "<p>Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</p>";
echo "<p>OS: " . php_uname() . "</p>";

// Test 6: Proxy settings
echo "<h2>Test 6: Proxy Settings</h2>";
$proxy_vars = ['http_proxy', 'https_proxy', 'HTTP_PROXY', 'HTTPS_PROXY'];
$proxy_found = false;

foreach ($proxy_vars as $var) {
    $value = getenv($var);
    if ($value) {
        echo "<p>Proxy found: $var = $value</p>";
        $proxy_found = true;
    }
}

if (!$proxy_found) {
    echo "<p>No proxy settings found</p>";
}

echo "<h2>Possible Solutions:</h2>";
echo "<ol>";
echo "<li><strong>Check firewall:</strong> Make sure outbound HTTPS (port 443) is allowed</li>";
echo "<li><strong>Check proxy:</strong> If you're behind a proxy, configure it in PHP</li>";
echo "<li><strong>Check DNS:</strong> Make sure DNS resolution is working</li>";
echo "<li><strong>Check internet:</strong> Make sure the server has internet access</li>";
echo "<li><strong>Try different cluster:</strong> Change cluster from 'ap3' to 'ap1' or 'us2'</li>";
echo "</ol>";

echo "<h2>Quick Fix - Try Different Cluster:</h2>";
echo "<p>Edit your .env file and change:</p>";
echo "<pre>PUSHER_CLUSTER=ap3</pre>";
echo "<p>to:</p>";
echo "<pre>PUSHER_CLUSTER=ap1</pre>";
echo "<p>or:</p>";
echo "<pre>PUSHER_CLUSTER=us2</pre>";
?> 