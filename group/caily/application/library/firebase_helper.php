<?php
require_once __DIR__ . '/../../env_config.php';

class FirebaseHelper {
    private $databaseUrl;
    private $apiKey;
    
    public function __construct() {
        $config = EnvConfig::getFirebaseConfig();
        $this->databaseUrl = $config['databaseURL'];
        $this->apiKey = $config['apiKey'];
    }
    
    /**
     * Send notification to Firebase Realtime Database
     */
    public function sendNotification($channel, $event, $data) {
        if (!$this->databaseUrl) {
            error_log('Firebase database URL not configured');
            return false;
        }
        
        $notification = [
            'last_comment_id' => $data['comment_id'],
        ];
        
        $url = $this->databaseUrl . '/notifications/' . $channel . '.json';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notification));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        // SSL Configuration - Handle certificate issues
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification for development
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Disable host verification for development
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  // Follow redirects
        
        // Alternative: If you want to use proper SSL verification, uncomment these lines:
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        // curl_setopt($ch, CURLOPT_CAINFO, '/path/to/cacert.pem'); // Path to CA certificate bundle
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log('Firebase cURL error: ' . $error);
            return false;
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        } else {
            error_log('Firebase HTTP error: ' . $httpCode . ' - ' . $response);
            return false;
        }
    }
    
    /**
     * Send notification to specific user
     */
    public function sendToUser($userId, $event, $data) {
        return $this->sendNotification('user_' . $userId, $event, $data);
    }
    
    /**
     * Send notification to all users
     */
    public function sendToAll($event, $data) {
        return $this->sendNotification('global', $event, $data);
    }
    
    /**
     * Send notification to administrators
     */
    public function sendToAdmins($event, $data) {
        return $this->sendNotification('admins', $event, $data);
    }
    
    /**
     * Test Firebase connection
     */
    public function testConnection() {
        if (!$this->databaseUrl) {
            return ['success' => false, 'error' => 'Database URL not configured'];
        }
        
        $url = $this->databaseUrl . '/test.json';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['test' => 'connection', 'timestamp' => time()]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        // SSL Configuration - Handle certificate issues
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification for development
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Disable host verification for development
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  // Follow redirects
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'error' => 'cURL error: ' . $error];
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'message' => 'Connection successful'];
        } else {
            return ['success' => false, 'error' => 'HTTP error: ' . $httpCode . ' - ' . $response];
        }
    }
    
    /**
     * Test connection with different SSL configurations
     */
    public function testConnectionWithSSL() {
        if (!$this->databaseUrl) {
            return ['success' => false, 'error' => 'Database URL not configured'];
        }
        
        $url = $this->databaseUrl . '/test.json';
        $testData = json_encode(['test' => 'connection', 'timestamp' => time()]);
        
        // Test different SSL configurations
        $configs = [
            'no_ssl_verify' => [
                'ssl_verifypeer' => false,
                'ssl_verifyhost' => false
            ],
            'ssl_verify_only' => [
                'ssl_verifypeer' => true,
                'ssl_verifyhost' => false
            ],
            'full_ssl_verify' => [
                'ssl_verifypeer' => true,
                'ssl_verifyhost' => 2
            ]
        ];
        
        $results = [];
        
        foreach ($configs as $configName => $sslConfig) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $testData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            // Apply SSL configuration
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslConfig['ssl_verifypeer']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslConfig['ssl_verifyhost']);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            $results[$configName] = [
                'success' => !$error && $httpCode >= 200 && $httpCode < 300,
                'http_code' => $httpCode,
                'error' => $error,
                'response' => $response
            ];
        }
        
        return $results;
    }
    
    /**
     * Update user notification meta (last_notification_time, last_notification_id)
     */
    public function updateUserNotificationMeta($userId, $meta = []) {
        if (!$this->databaseUrl) {
            error_log('Firebase database URL not configured');
            return false;
        }
        $url = $this->databaseUrl . '/user_meta/user_' . $userId . '.json';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($meta));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) {
            error_log('Firebase cURL error: ' . $error);
            return false;
        }
        return $httpCode >= 200 && $httpCode < 300;
    }
}
?> 