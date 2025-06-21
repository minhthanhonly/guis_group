<?php
/**
 * Pusher Configuration with Environment Variables
 * 
 * This system now uses environment variables for better security and flexibility.
 * 
 * To set up:
 * 1. Create a .env file in your project root
 * 2. Add your Pusher credentials to the .env file
 * 3. The system will automatically load the configuration
 */

// Example .env file content:
/*
PUSHER_APP_ID=your_app_id
PUSHER_KEY=your_key
PUSHER_SECRET=your_secret
PUSHER_CLUSTER=ap1
PUSHER_USE_TLS=true
*/

// The system automatically loads these values from:
// 1. Environment variables (for production)
// 2. .env file (for development)

// Files that use environment variables:
// 1. api/NotificationAPI.php - Uses EnvConfig::getPusherConfig()
// 2. application/library/pusher_helper.php - Uses EnvConfig::getPusherConfig()
// 3. js/notification.js - Gets config from server endpoint

// Security Benefits:
// - No hardcoded credentials in source code
// - Different configurations for different environments
// - Easy to manage in production deployments
// - Credentials can be rotated without code changes

// Environment Variables Priority:
// 1. System environment variables (highest priority)
// 2. .env file values
// 3. Default values (lowest priority)

// Production Deployment:
// Set environment variables on your server:
// export PUSHER_APP_ID=your_app_id
// export PUSHER_KEY=your_key
// export PUSHER_SECRET=your_secret
// export PUSHER_CLUSTER=ap1
// export PUSHER_USE_TLS=true

// Development Setup:
// Create .env file in project root with your Pusher credentials

// Testing:
// 1. Ensure .env file exists with correct credentials
// 2. Open test_pusher.html in your browser
// 3. Check browser console for connection status
// 4. Use test buttons to simulate different events

// Troubleshooting:
// 1. Check that .env file exists and has correct format
// 2. Verify Pusher credentials are valid
// 3. Check browser console for configuration errors
// 4. Ensure env_config.php is loading correctly

echo "Pusher Configuration with Environment Variables\n";
echo "===============================================\n";
echo "1. Create a .env file in your project root\n";
echo "2. Add your Pusher credentials to the .env file\n";
echo "3. The system will automatically load the configuration\n";
echo "4. No need to modify source code for different environments\n";
?> 