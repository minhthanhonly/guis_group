<?php
require_once('../application/loader.php');
require_once('../env_config.php');

// Get Pusher configuration from environment variables
$pusher_config = EnvConfig::getPusherConfig();

// Initialize Pusher
require_once('../application/library/pusher-php-server/src/Pusher.php');
$pusher = new Pusher\Pusher(
    $pusher_config['key'],
    $pusher_config['secret'],
    $pusher_config['app_id'],
    [
        'cluster' => $pusher_config['cluster'],
        'useTLS' => $pusher_config['useTLS']
    ]
);

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'config':
        // Provide Pusher configuration to client (without secret)
        header('Content-Type: application/json');
        echo json_encode([
            'key' => $pusher_config['key'],
            'cluster' => $pusher_config['cluster'],
            'useTLS' => $pusher_config['useTLS']
        ]);
        break;
        
    case 'auth':
        // Handle Pusher authentication
        $channel_name = $_POST['channel_name'] ?? '';
        $socket_id = $_POST['socket_id'] ?? '';
        
        if (empty($channel_name) || empty($socket_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing channel_name or socket_id']);
            exit;
        }
        
        // Authenticate the channel
        $auth = $pusher->socket_auth($channel_name, $socket_id);
        echo $auth;
        break;
        
    case 'trigger':
        // Trigger a notification event
        $channel = $_POST['channel'] ?? '';
        $event = $_POST['event'] ?? '';
        $data = $_POST['data'] ?? [];
        
        if (empty($channel) || empty($event)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing channel or event']);
            exit;
        }
        
        try {
            $result = $pusher->trigger($channel, $event, $data);
            echo json_encode(['success' => true, 'result' => $result]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
        
    case 'webhook':
        // Handle Pusher webhooks (optional, for advanced features)
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if ($data) {
            // Process webhook data
            // You can log events, update database, etc.
            error_log('Pusher webhook received: ' . json_encode($data));
        }
        
        echo json_encode(['success' => true]);
        break;
        
    default:
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?> 