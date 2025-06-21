<?php
/**
 * Environment Configuration Loader
 * 
 * This file loads environment variables from .env file
 * Create a .env file in your project root with the following variables:
 * 
 * PUSHER_APP_ID=your_app_id
 * PUSHER_KEY=your_key
 * PUSHER_SECRET=your_secret
 * PUSHER_CLUSTER=ap1
 * PUSHER_USE_TLS=true
 */

class EnvConfig {
    private static $env = [];
    
    public static function load($envFile = '.env') {
        if (!file_exists($envFile)) {
            error_log("Environment file not found: $envFile");
            return false;
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
                
                self::$env[$key] = $value;
            }
        }
        
        return true;
    }
    
    public static function get($key, $default = null) {
        // Check environment variables first (for production)
        $envValue = getenv($key);
        if ($envValue !== false) {
            return $envValue;
        }
        
        // Then check loaded .env values
        return isset(self::$env[$key]) ? self::$env[$key] : $default;
    }
    
    public static function getPusherConfig() {
        return [
            'app_id' => self::get('PUSHER_APP_ID', 'your_app_id'),
            'key' => self::get('PUSHER_KEY', 'your_key'),
            'secret' => self::get('PUSHER_SECRET', 'your_secret'),
            'cluster' => self::get('PUSHER_CLUSTER', 'ap1'),
            'useTLS' => self::get('PUSHER_USE_TLS', 'true') === 'true'
        ];
    }
}

// Auto-load environment variables
EnvConfig::load();
?> 