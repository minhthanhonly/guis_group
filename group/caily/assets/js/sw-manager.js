// Service Worker Manager for Background Uploads
class ServiceWorkerManager {
    constructor() {
        this.swRegistration = null;
        this.uploadChannel = null;
        this.init();
    }

    async init() {
        if ('serviceWorker' in navigator) {
            try {
                // Determine the correct path for service worker based on current location
                let swPath = '/sw-upload.js';
                
                // If we're in a subdirectory, adjust the path
                const currentPath = window.location.pathname;
                if (currentPath.includes('/project/') || currentPath.includes('/caily/')) {
                    // Go up to root directory
                    swPath = '/sw-upload.js';
                }
                
                console.log('Attempting to register Service Worker at:', swPath);
                
                this.swRegistration = await navigator.serviceWorker.register(swPath);
                console.log('Service Worker registered successfully:', this.swRegistration);
                
                // Update status indicator
                this.updateStatusIndicator('connected');
                
                // Listen for service worker updates
                // this.swRegistration.addEventListener('updatefound', () => {
                //     const newWorker = this.swRegistration.installing;
                //     newWorker.addEventListener('statechange', () => {
                //         if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                //             // New service worker available
                //             this.showUpdateNotification();
                //         }
                //     });
                // });
                
            } catch (error) {
                console.error('Service Worker registration failed:', error);
                this.updateStatusIndicator('disconnected');
            }
        } else {
            console.warn('Service Worker not supported');
            this.updateStatusIndicator('disconnected');
        }
    }

    showUpdateNotification() {
        // Show notification to user about new version
        if (confirm('A new version is available. Reload to update?')) {
            window.location.reload();
        }
    }
    
    updateStatusIndicator(status) {
        const statusElement = document.getElementById('sw-status');
        const statusText = document.getElementById('sw-status-text');
        
        if (statusElement && statusText) {
            statusElement.className = `sw-status ${status}`;
            statusText.textContent = status === 'connected' ? 'Background Upload Ready' : 'Upload Service Unavailable';
        }
    }

    async uploadFile(file, uploadUrl, additionalData = {}) {
        return new Promise((resolve, reject) => {
            if (!this.swRegistration || !this.swRegistration.active) {
                // Fallback to regular upload if service worker not available
                this.fallbackUpload(file, uploadUrl, additionalData).then(resolve).catch(reject);
                return;
            }

            // Create message channel for communication
            const channel = new MessageChannel();
            this.uploadChannel = channel;

            // Handle messages from service worker
            channel.port1.onmessage = (event) => {
                const { type, data, error, progress, fileName } = event.data;
                
                switch (type) {
                    case 'UPLOAD_PROGRESS':
                        this.onProgress(progress, fileName);
                        break;
                    case 'UPLOAD_SUCCESS':
                        this.onSuccess(data, fileName);
                        resolve(data);
                        break;
                    case 'UPLOAD_ERROR':
                        this.onError(error, fileName);
                        reject(new Error(error));
                        break;
                    case 'UPLOAD_ABORTED':
                        this.onAbort(fileName);
                        reject(new Error('Upload aborted'));
                        break;
                }
            };

            // Send upload request to service worker
            this.swRegistration.active.postMessage({
                type: 'UPLOAD_FILE',
                file: file,
                uploadUrl: uploadUrl,
                additionalData: additionalData
            }, [channel.port2]);
        });
    }

    // Fallback upload method (using axios with progress)
    async fallbackUpload(file, uploadUrl, additionalData = {}) {
        console.log(additionalData);
        try {
            const formData = new FormData();
            formData.append('image', file);
            
            // Add additional data to form
            Object.keys(additionalData).forEach(key => {
                formData.append(key, additionalData[key]);
            });
            
            // Simulate progress updates since axios doesn't support upload progress
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += Math.random() * 10;
                if (progress > 90) progress = 90; // Don't reach 100% until complete
                this.onProgress(Math.round(progress), file.name);
            }, 200);
            
            const response = await axios.post(uploadUrl, formData, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                },
                withCredentials: true // Include cookies for session
            });
            
            clearInterval(progressInterval);
            
            // Send 100% progress
            this.onProgress(100, file.name);
            
            this.onSuccess(response.data, file.name);
            return response.data;
            
        } catch (error) {
            this.onError(error.response?.data?.error || error.message || 'Upload failed', file.name);
            throw error;
        }
    }

    // Event handlers - can be overridden
    onProgress(progress, fileName) {
        console.log(`Upload progress for ${fileName}: ${progress}%`);
        // Dispatch custom event for progress updates
        window.dispatchEvent(new CustomEvent('uploadProgress', {
            detail: { progress, fileName }
        }));
    }

    onSuccess(data, fileName) {
        console.log(`Upload successful for ${fileName}:`, data);
        // Dispatch custom event for success
        window.dispatchEvent(new CustomEvent('uploadSuccess', {
            detail: { data, fileName }
        }));
    }

    onError(error, fileName) {
        console.error(`Upload error for ${fileName}:`, error);
        // Dispatch custom event for errors
        window.dispatchEvent(new CustomEvent('uploadError', {
            detail: { error, fileName }
        }));
    }

    onAbort(fileName) {
        console.log(`Upload aborted for ${fileName}`);
        // Dispatch custom event for abort
        window.dispatchEvent(new CustomEvent('uploadAbort', {
            detail: { fileName }
        }));
    }

    // Abort current upload
    abortUpload() {
        if (this.uploadChannel) {
            this.uploadChannel.port1.postMessage({ type: 'ABORT_UPLOAD' });
        }
    }
}

// Global instance
window.swManager = new ServiceWorkerManager(); 