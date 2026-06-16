// Service Worker Manager for Background Uploads
// Bump when sw-upload.js behavior changes (must match CACHE_NAME in sw-upload.js)
const SW_VERSION = '2.2';
const SW_VERSION_KEY = 'caily_sw_version';

class ServiceWorkerManager {
    constructor() {
        this.swRegistration = null;
        this.uploadChannel = null;
        this.init();
    }

    async cleanupServiceWorkers() {
        const registrations = await navigator.serviceWorker.getRegistrations();
        await Promise.all(registrations.map((registration) => registration.unregister()));

        if ('caches' in window) {
            const cacheNames = await caches.keys();
            await Promise.all(cacheNames.map((name) => caches.delete(name)));
        }

        return registrations.length;
    }

    async migrateServiceWorkerIfNeeded() {
        const storedVersion = localStorage.getItem(SW_VERSION_KEY);
        if (storedVersion === SW_VERSION) {
            return false;
        }

        const hadRegistration = await this.cleanupServiceWorkers();
        localStorage.setItem(SW_VERSION_KEY, SW_VERSION);

        if (hadRegistration > 0 || navigator.serviceWorker.controller) {
            window.location.reload();
            return true;
        }

        return false;
    }

    async init() {
        if ('serviceWorker' in navigator) {
            try {
                if (await this.migrateServiceWorkerIfNeeded()) {
                    return;
                }

                const swPath = '/sw-upload.js';
                console.log('Attempting to register Service Worker at:', swPath);

                this.swRegistration = await navigator.serviceWorker.register(swPath);
                console.log('Service Worker registered successfully:', this.swRegistration);

                navigator.serviceWorker.addEventListener('controllerchange', () => {
                    if (!this._reloadingForSwUpdate) {
                        this._reloadingForSwUpdate = true;
                        window.location.reload();
                    }
                });

                await this.swRegistration.update();
            } catch (error) {
                console.error('Service Worker registration failed:', error);
            }
        } else {
            console.warn('Service Worker not supported');
        }
    }

    showUpdateNotification() {
        // Show notification to user about new version
        if (confirm('A new version is available. Reload to update?')) {
            window.location.reload();
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

    // Fallback upload method (using XMLHttpRequest with real progress)
    async fallbackUpload(file, uploadUrl, additionalData = {}) {
        console.log('Starting fallback upload for:', file.name, additionalData);
        
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('image', file);
            
            // Add additional data to form
            Object.keys(additionalData).forEach(key => {
                formData.append(key, additionalData[key]);
            });
            
            const xhr = new XMLHttpRequest();
            
            // Real upload progress
            xhr.upload.addEventListener('progress', (event) => {
                if (event.lengthComputable) {
                    const progress = Math.round((event.loaded / event.total) * 100);
                    this.onProgress(progress, file.name);
                }
            });
            
            xhr.addEventListener('load', () => {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        console.log('Upload response for', file.name, ':', response);
                        
                        if (response.success) {
                            this.onSuccess(response, file.name);
                            resolve(response);
                        } else {
                            this.onError(response.error || 'Upload failed', file.name);
                            reject(new Error(response.error || 'Upload failed'));
                        }
                    } catch (e) {
                        console.error('Failed to parse response:', xhr.responseText);
                        this.onError('Invalid response format', file.name);
                        reject(new Error('Invalid response format'));
                    }
                } else {
                    console.error('HTTP Error:', xhr.status, xhr.statusText);
                    this.onError(`HTTP ${xhr.status}: ${xhr.statusText}`, file.name);
                    reject(new Error(`HTTP ${xhr.status}: ${xhr.statusText}`));
                }
            });
            
            xhr.addEventListener('error', () => {
                console.error('Network error during upload');
                this.onError('Network error', file.name);
                reject(new Error('Network error'));
            });
            
            xhr.addEventListener('timeout', () => {
                console.error('Upload timeout');
                this.onError('Upload timeout', file.name);
                reject(new Error('Upload timeout'));
            });
            
            xhr.open('POST', uploadUrl);
            xhr.timeout = 300000; // 5 minutes timeout
            xhr.send(formData);
        });
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