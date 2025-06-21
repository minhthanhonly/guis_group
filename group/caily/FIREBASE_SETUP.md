# Firebase Integration Setup Guide

## Overview
This guide explains how to set up Firebase Realtime Database for real-time notifications in the CAILY project management system.

## Prerequisites
- A Google account
- Access to Firebase Console
- Basic understanding of Firebase services

## Step 1: Create Firebase Project

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Click "Create a project" or "Add project"
3. Enter a project name (e.g., "caily-notifications")
4. Choose whether to enable Google Analytics (optional)
5. Click "Create project"

## Step 2: Enable Realtime Database

1. In your Firebase project, go to "Realtime Database" in the left sidebar
2. Click "Create database"
3. Choose a location (select the closest to your users)
4. Start in test mode (we'll update security rules later)
5. Click "Done"

## Step 3: Get Firebase Configuration

1. In your Firebase project, click the gear icon (⚙️) next to "Project Overview"
2. Select "Project settings"
3. Scroll down to "Your apps" section
4. Click the web icon (</>)
5. Register your app with a nickname (e.g., "caily-web")
6. Copy the configuration object that looks like this:

```javascript
const firebaseConfig = {
  apiKey: "your-api-key",
  authDomain: "your-project.firebaseapp.com",
  projectId: "your-project-id",
  storageBucket: "your-project.appspot.com",
  messagingSenderId: "123456789",
  appId: "1:123456789:web:abcdef123456",
  databaseURL: "https://your-project-id-default-rtdb.firebaseio.com"
};
```

## Step 4: Configure Environment Variables

Create or update your `.env` file in the project root with the Firebase configuration:

```env
# Firebase Configuration
FIREBASE_API_KEY=your-api-key
FIREBASE_AUTH_DOMAIN=your-project.firebaseapp.com
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_STORAGE_BUCKET=your-project.appspot.com
FIREBASE_MESSAGING_SENDER_ID=123456789
FIREBASE_APP_ID=1:123456789:web:abcdef123456
FIREBASE_DATABASE_URL=https://your-project-id-default-rtdb.firebaseio.com
```

## Step 5: Set Database Security Rules

In your Firebase Realtime Database, go to the "Rules" tab and set the following rules:

```json
{
  "rules": {
    "notifications": {
      ".read": true,
      ".write": true
    },
    "test": {
      ".read": true,
      ".write": true
    }
  }
}
```

**Note:** These rules allow public read/write access for testing. For production, you should implement proper authentication and authorization.

## Step 6: Test the Integration

### Backend Testing
1. **Test Firebase Configuration:**
   - Visit `http://your-domain/test_firebase.php`
   - Check if Firebase configuration is loaded correctly
   - Verify connection test passes

2. **Test API Endpoints:**
   - Visit `http://your-domain/test_api.php`
   - Check if API endpoints are accessible
   - Verify notification sending works

### Frontend Testing
1. **Test Firebase SDK Loading:**
   - Visit `http://your-domain/test_firebase_sdk.html`
   - Check if Firebase SDK loads correctly
   - Verify SDK version compatibility

2. **Test Frontend Integration:**
   - Visit `http://your-domain/test_firebase_notification.html`
   - Check if Firebase SDK loads correctly
   - Test sending notifications from different channels

### Real Application Testing
1. **Test Real Notifications:**
   - Create a new leave request
   - Add comments to requests
   - Approve/reject requests
   - Check if notifications appear in real-time

## Step 7: Common Issues and Solutions

### Issue 1: SSL Certificate Problems
If you encounter SSL certificate errors:
```
cURL error: SSL certificate problem: unable to get local issuer certificate
```

**Solution:** The current configuration automatically disables SSL verification for development. For production, download CA certificates:
```bash
curl -o cacert.pem https://curl.se/ca/cacert.pem
```

### Issue 2: Firebase SDK Not Defined
If you see the error:
```
Failed to initialize: firebase is not defined
```

**Solutions:**

1. **Check Network Connectivity:**
   - Ensure your server can access `https://www.gstatic.com`
   - Check if CDN is blocked by firewall

2. **Try Different SDK Versions:**
   The system automatically tries these versions:
   - `9.22.0` (latest)
   - `9.0.0` (stable)
   - `8.10.1` (legacy)

3. **Manual SDK Loading:**
   Add these scripts to your HTML head:
   ```html
   <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js"></script>
   <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-database-compat.js"></script>
   ```

4. **Check Browser Console:**
   - Open browser developer tools
   - Look for network errors loading Firebase scripts
   - Check for JavaScript errors

### Issue 3: CORS Errors
If you see CORS-related errors:

**Solution:** Ensure your Firebase project allows your domain:
1. Go to Firebase Console → Project Settings
2. Add your domain to "Authorized domains"
3. For local development, add `localhost`

### Issue 4: Database Rules Errors
If you see permission errors:

**Solution:** Update your database rules to allow read/write:
```json
{
  "rules": {
    "notifications": {
      ".read": true,
      ".write": true
    }
  }
}
```

## Step 8: Production Security (Optional)

For production environments, consider implementing:

1. **Firebase Authentication** for user management
2. **Custom security rules** based on user roles
3. **Server-side validation** of notification data

Example production rules:
```json
{
  "rules": {
    "notifications": {
      "$channel": {
        ".read": "auth != null && (root.child('users').child(auth.uid).child('role').val() === 'admin' || $channel === 'user_' + auth.uid || $channel === 'global')",
        ".write": "auth != null && root.child('users').child(auth.uid).child('role').val() === 'admin'"
      }
    }
  }
}
```

## Troubleshooting

### Common Issues

1. **"Database URL not configured"**
   - Check that `FIREBASE_DATABASE_URL` is set in your `.env` file
   - Verify the URL format: `https://your-project-id-default-rtdb.firebaseio.com`

2. **"Failed to get Firebase configuration"**
   - Check that all Firebase environment variables are set
   - Verify the API endpoint `/api/NotificationAPI.php?method=get_config` returns valid data

3. **"Firebase SDK not loaded"**
   - Check internet connection
   - Verify Firebase CDN URLs are accessible
   - Check browser console for JavaScript errors
   - Try different SDK versions

4. **"Connection failed"**
   - Verify database rules allow read/write access
   - Check if database is in the correct region
   - Ensure project is not suspended

5. **"SSL certificate problem"**
   - For development: SSL verification is disabled by default
   - For production: Download and configure CA certificates
   - Check the SSL troubleshooting section above

6. **"firebase is not defined"**
   - Check network connectivity to Firebase CDN
   - Try alternative SDK versions
   - Check browser console for loading errors
   - Verify no JavaScript errors are blocking execution

### Debug Steps

1. **Check Environment Variables:**
   ```php
   <?php
   require_once 'env_config.php';
   $config = EnvConfig::getFirebaseConfig();
   print_r($config);
   ?>
   ```

2. **Test API Endpoint:**
   ```bash
   curl "http://your-domain/api/NotificationAPI.php?method=test"
   ```

3. **Check Browser Console:**
   - Open browser developer tools
   - Look for Firebase-related errors
   - Check network tab for failed requests

4. **Test SSL Configuration:**
   - Run `test_firebase.php` to check SSL settings
   - Look for SSL test results in the output

5. **Test Firebase SDK:**
   - Run `test_firebase_sdk.html` to check SDK loading
   - Look for SDK version compatibility issues

## File Structure

After setup, your notification system will use these files:

```
├── env_config.php                    # Environment configuration
├── application/library/
│   └── firebase_helper.php          # Firebase helper class
├── api/
│   └── NotificationAPI.php          # API endpoints
├── assets/js/
│   └── notification.js              # Frontend notification manager
├── application/model/
│   └── request.php                  # Updated with Firebase notifications
├── test_firebase.php                # Backend test file
├── test_api.php                     # API test file
├── test_firebase_sdk.html           # SDK loading test file
└── test_firebase_notification.html  # Frontend test file
```

## Features

The Firebase integration provides:

- **Real-time notifications** for form requests, comments, and status changes
- **Role-based filtering** (user-specific, admin-only, global)
- **Automatic reconnection** when connection is lost
- **Toast notifications** with animations
- **Click-to-navigate** functionality
- **Auto-dismiss** after 5 seconds
- **SSL certificate handling** for both development and production
- **Multiple SDK version support** for compatibility

## Support

If you encounter issues:

1. Check the troubleshooting section above
2. Review Firebase Console logs
3. Check server error logs
4. Test with the provided test files
5. Verify all environment variables are correctly set
6. Check SSL configuration if using HTTPS
7. Test Firebase SDK loading with `test_firebase_sdk.html` 