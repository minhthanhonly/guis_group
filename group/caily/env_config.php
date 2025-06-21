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
    private static $config = null;
    
    public static function get($key, $default = null) {
        if (self::$config === null) {
            self::loadConfig();
        }
        
        return isset(self::$config[$key]) ? self::$config[$key] : $default;
    }
    
    private static function loadConfig() {
        self::$config = [];
        
        // Load from .env file if exists
        $envFile = __DIR__ . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    $parts = explode('=', $line, 2);
                    if (count($parts) === 2) {
                        $envKey = trim($parts[0]);
                        $envValue = trim($parts[1]);
                        self::$config[$envKey] = $envValue;
                    }
                }
            }
        }
        
        // Override with system environment variables
        $envVars = [
            'FIREBASE_API_KEY',
            'FIREBASE_AUTH_DOMAIN', 
            'FIREBASE_PROJECT_ID',
            'FIREBASE_STORAGE_BUCKET',
            'FIREBASE_MESSAGING_SENDER_ID',
            'FIREBASE_APP_ID',
            'FIREBASE_DATABASE_URL'
        ];
        
        foreach ($envVars as $var) {
            $value = getenv($var);
            if ($value !== false) {
                self::$config[$var] = $value;
            }
        }
    }
    
    public static function getFirebaseConfig() {
        return [
            'apiKey' => self::get('FIREBASE_API_KEY'),
            'authDomain' => self::get('FIREBASE_AUTH_DOMAIN'),
            'projectId' => self::get('FIREBASE_PROJECT_ID'),
            'storageBucket' => self::get('FIREBASE_STORAGE_BUCKET'),
            'messagingSenderId' => self::get('FIREBASE_MESSAGING_SENDER_ID'),
            'appId' => self::get('FIREBASE_APP_ID'),
            'databaseURL' => self::get('FIREBASE_DATABASE_URL')
        ];
    }
}
?> 