# Pusher Notification System Setup Guide

## Overview
This system replaces the previous SSE (Server-Sent Events) implementation with Pusher for real-time notifications. Pusher provides a more reliable and scalable WebSocket-based solution.

## Features
- Real-time notifications for form requests (creation, updates, approvals, comments)
- Project and task updates
- Time entry notifications
- User role-based filtering
- Automatic reconnection
- Beautiful notification UI with animations
- **Environment-based configuration** using `.env` file

## Setup Instructions

### 1. Create Pusher Account
1. Go to [https://pusher.com/](https://pusher.com/)
2. Sign up for a free account
3. Create a new app
4. Note down your app credentials:
   - App ID
   - Key
   - Secret
   - Cluster

### 2. Environment Configuration

#### Create `.env` file
Create a `.env` file in your project root with the following content:

```env
# Pusher Configuration
PUSHER_APP_ID=your_app_id
PUSHER_KEY=your_key
PUSHER_SECRET=your_secret
PUSHER_CLUSTER=ap1
PUSHER_USE_TLS=true

# Database Configuration (if needed)
DB_HOST=localhost
DB_NAME=your_database
DB_USER=your_username
DB_PASS=your_password

# Application Configuration
APP_ENV=development
APP_DEBUG=true
```

**Replace the placeholder values with your actual Pusher credentials.**

#### Environment Variables Priority
The system loads configuration in this order:
1. **System environment variables** (highest priority - for production)
2. **`.env` file values** (for development)
3. **Default values** (lowest priority)

### 3. Production Deployment

For production, set environment variables on your server:

```bash
export PUSHER_APP_ID=your_app_id
export PUSHER_KEY=your_key
export PUSHER_SECRET=your_secret
export PUSHER_CLUSTER=ap1
export PUSHER_USE_TLS=true
```

### 4. Test the System

1. Ensure `.env` file exists with correct credentials
2. Open `test_pusher.html` in your browser
3. Check the connection status
4. Use the test buttons to simulate different events
5. Open multiple tabs to see real-time notifications

### 5. Integration with Existing Code

The system automatically integrates with:
- Form request creation (`form/index.php`)
- Request status updates (`form/detail.php`)
- Comment additions
- Project management system

## File Structure

```
├── api/
│   └── NotificationAPI.php          # Pusher API endpoints
├── application/
│   ├── library/
│   │   └── pusher_helper.php        # Server-side notification helper
│   └── model/
│       └── request.php              # Updated with notification triggers
├── js/
│   └── notification.js              # Client-side notification manager
├── libs/
│   └── pusher-php-server/
│       └── src/
│           └── Pusher.php           # Pusher PHP library
├── env_config.php                   # Environment configuration loader
├── .env                             # Environment variables (create this)
├── test_pusher.html                 # Test page
├── pusher_config.php                # Configuration guide
└── PUSHER_SETUP.md                  # This file
```

## API Endpoints

### Configuration
- `GET /api/NotificationAPI.php?action=config`
  - Returns Pusher configuration (without secret)
  - Used by client-side to get connection details

### Authentication
- `POST /api/NotificationAPI.php?action=auth`
  - Authenticates private channels
  - Requires `channel_name` and `socket_id` parameters

### Trigger Events
- `POST /api/NotificationAPI.php?action=trigger`
  - Triggers notification events
  - Requires `channel`, `event`, and `data` parameters

### Webhooks (Optional)
- `POST /api/NotificationAPI.php?action=webhook`
  - Handles Pusher webhooks for advanced features

## Notification Events

| Event | Description | Triggered When |
|-------|-------------|----------------|
| `request_created` | New request created | User submits a new form request |
| `request_updated` | Request updated | Request content is modified |
| `request_status_changed` | Status changed | Request is approved/rejected |
| `request_comment_added` | Comment added | New comment on request |
| `project_updated` | Project updated | Project details changed |
| `task_updated` | Task updated | Task details changed |
| `comment_added` | Task comment | Comment added to task |
| `time_entry_updated` | Time entry | Time tracking updated |

## Security Considerations

1. **Never expose Pusher secret in client-side code**
2. **Use private channels for sensitive data**
3. **Implement proper authentication for private channels**
4. **Use environment variables for production credentials**
5. **Keep `.env` file out of version control**
6. **Use different credentials for development and production**

## Development vs Production

### Development
- Use free Pusher plan
- Create `.env` file with your credentials
- Set `PUSHER_USE_TLS` to `false` for local development without SSL
- Test with `test_pusher.html`

### Production
- Consider upgrading to paid Pusher plan
- Set `PUSHER_USE_TLS` to `true`
- Use system environment variables instead of `.env` file
- Implement proper error handling
- Monitor Pusher usage and limits

## Environment Configuration

### `.env` File Format
```env
# Comments start with #
PUSHER_APP_ID=your_app_id
PUSHER_KEY=your_key
PUSHER_SECRET=your_secret
PUSHER_CLUSTER=ap1
PUSHER_USE_TLS=true
```

### Environment Variables
The system supports both `.env` files and system environment variables:

```php
// Load from .env file
EnvConfig::load();

// Get configuration
$config = EnvConfig::getPusherConfig();
```

## Troubleshooting

### Configuration Issues
1. Check that `.env` file exists and has correct format
2. Verify Pusher credentials are valid
3. Ensure `env_config.php` is loading correctly
4. Check file permissions for `.env` file

### Connection Issues
1. Check browser console for errors
2. Verify Pusher credentials are correct
3. Ensure server can make outbound HTTPS requests
4. Check auth endpoint accessibility

### Notification Issues
1. Verify event names match between server and client
2. Check user role filtering logic
3. Ensure notification manager is initialized
4. Check for JavaScript errors

### Performance Issues
1. Monitor Pusher usage limits
2. Implement proper error handling
3. Consider rate limiting for high-traffic scenarios

## Migration from SSE

The system has been completely migrated from SSE to Pusher:

### Removed Files
- `test_sse.php` - SSE test endpoint
- `test_sse.html` - SSE test page
- `test_notification.html` - Old notification test

### Benefits of Pusher over SSE
- Better browser compatibility
- Automatic reconnection
- More reliable connection
- Better error handling
- Scalable infrastructure
- Built-in authentication
- **Environment-based configuration**

## Security Best Practices

1. **Never commit `.env` file to version control**
2. **Use different credentials for each environment**
3. **Rotate credentials regularly**
4. **Monitor Pusher usage and logs**
5. **Implement proper error handling**
6. **Use private channels for sensitive data**

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review Pusher documentation at [https://pusher.com/docs](https://pusher.com/docs)
3. Check browser console for error messages
4. Verify `.env` file configuration is correct
5. Ensure all environment variables are set properly 