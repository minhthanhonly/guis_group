// Service Worker for Background File Uploads
const CACHE_NAME = 'upload-cache-v2';

// Install event - cache necessary files
self.addEventListener('install', (event) => {
    console.log('Service Worker installing...');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                return cache.addAll([
                    '/assets/js/axios.min.js',
                    '/assets/css/app-chat.css'
                ]);
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    console.log('Service Worker activating...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

// Message event - handle upload requests
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'UPLOAD_FILE') {
        handleFileUpload(event.data.file, event.data.uploadUrl, event.ports[0], event.data.additionalData);
    }
});

// Handle file upload in background
async function handleFileUpload(file, uploadUrl, port, additionalData = {}) {
    try {
        // Create FormData
        const formData = new FormData();
        formData.append('image', file);
        
        // Add additional data to form
        Object.keys(additionalData).forEach(key => {
            formData.append(key, additionalData[key]);
        });
        
        // Simulate progress updates (axios doesn't support progress events)
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += Math.random() * 10;
            if (progress > 90) progress = 90; // Don't reach 100% until complete
            
            port.postMessage({
                type: 'UPLOAD_PROGRESS',
                progress: Math.round(progress),
                fileName: file.name
            });
        }, 200);
        
        // Use fetch API (axios not available in service worker)
        const response = await fetch(uploadUrl, {
            method: 'POST',
            body: formData,
            credentials: 'include' // Include cookies for session
        });
        
        clearInterval(progressInterval);
        
        if (response.ok) {
            try {
                const data = await response.json();
                
                // Send 100% progress
                port.postMessage({
                    type: 'UPLOAD_PROGRESS',
                    progress: 100,
                    fileName: file.name
                });
                
                port.postMessage({
                    type: 'UPLOAD_SUCCESS',
                    data: data,
                    fileName: file.name
                });
            } catch (error) {
                port.postMessage({
                    type: 'UPLOAD_ERROR',
                    error: 'Invalid response format',
                    fileName: file.name
                });
            }
        } else {
            const errorText = await response.text();
            let errorMessage = `HTTP ${response.status}: ${response.statusText}`;
            
            try {
                const errorData = JSON.parse(errorText);
                errorMessage = errorData.error || errorMessage;
            } catch (e) {
                // Use text as error message
            }
            
            port.postMessage({
                type: 'UPLOAD_ERROR',
                error: errorMessage,
                fileName: file.name
            });
        }
        
    } catch (error) {
        port.postMessage({
            type: 'UPLOAD_ERROR',
            error: error.response?.data?.error || error.message || 'Upload failed',
            fileName: file.name
        });
    }
}

// Fetch event - serve cached resources
self.addEventListener('fetch', (event) => {
    if (event.request.url.includes('/api/upload-image')) {
        // Don't cache upload requests
        return;
    }
    
    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                return response || fetch(event.request);
            })
    );
}); 