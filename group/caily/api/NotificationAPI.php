<?php
require_once '../application/loader.php';
require_once '../application/library/firebase_helper.php';

class NotificationAPI {
    private $firebase;
    
    public function __construct() {
        $this->firebase = new FirebaseHelper();
    }
    
    public function handleRequest() {
        $method = $_GET['method'] ?? '';
        
        switch ($method) {
            case 'test':
                return $this->testConnection();
            case 'send':
                return $this->sendNotification();
            case 'get_config':
                return $this->getConfig();
            default:
                return ['error' => 'Invalid method'];
        }
    }
    
    private function testConnection() {
        $result = $this->firebase->testConnection();
        return $result;
    }
    
    private function sendNotification() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $input = $_POST;
        }
        
        $channel = $input['channel'] ?? 'global';
        $event = $input['event'] ?? 'notification';
        $data = $input['data'] ?? [];
        
        $success = $this->firebase->sendNotification($channel, $event, $data);
        
        if ($success) {
            return ['success' => true, 'message' => 'Notification sent'];
        } else {
            return ['error' => 'Failed to send notification'];
        }
    }
    
    private function getConfig() {
        $config = EnvConfig::getFirebaseConfig();
        
        // Only return public config (no sensitive data)
        return [
            'apiKey' => $config['apiKey'],
            'authDomain' => $config['authDomain'],
            'projectId' => $config['projectId'],
            'storageBucket' => $config['storageBucket'],
            'messagingSenderId' => $config['messagingSenderId'],
            'appId' => $config['appId'],
            'databaseURL' => $config['databaseURL']
        ];
    }
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $api = new NotificationAPI();
    $result = $api->handleRequest();
    
    header('Content-Type: application/json');
    echo json_encode($result);
}
?> 