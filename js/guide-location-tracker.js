/**
 *     
 * Guide Real-time Location Tracking System
 */

class GuideLocationTracker {
    constructor(options = {}) {
        this.options = {
            apiUrl: '../user/guide-location-api.php',
            updateInterval: 30000, // 30
            autoUpdate: false,
            maxRetries: 3,
            retryDelay: 5000,
            watchOptions: {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 60000 // 1
            },
            ...options
        };
        
        this.watchId = null;
        this.isTracking = false;
        this.retryCount = 0;
        this.lastKnownPosition = null;
        this.updateTimer = null;
        this.listeners = {};
        
        this.init();
    }
    
    init() {
        console.log('Guide Location Tracker initialized');
        
        //   
        if (!navigator.geolocation) {
            this.emit('error', { message: '     .' });
            return;
        }
        
        //    
        window.addEventListener('beforeunload', () => {
            this.stopTracking();
        });
        
        //   
        window.addEventListener('online', () => {
            console.log('Network restored, resuming tracking');
            if (this.isTracking) {
                this.resumeTracking();
            }
        });
        
        window.addEventListener('offline', () => {
            console.log('Network lost, pausing tracking');
            this.pauseTracking();
        });
    }
    
    //   
    on(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
    }
    
    off(event, callback) {
        if (this.listeners[event]) {
            const index = this.listeners[event].indexOf(callback);
            if (index > -1) {
                this.listeners[event].splice(index, 1);
            }
        }
    }
    
    emit(event, data) {
        if (this.listeners[event]) {
            this.listeners[event].forEach(callback => callback(data));
        }
    }
    
    //   
    async startTracking() {
        if (this.isTracking) {
            console.log('Already tracking location');
            return;
        }
        
        try {
            //    
            const response = await this.apiCall('track_location', {}, 'POST');
            if (!response.success) {
                throw new Error(response.error || '  .');
            }
            
            this.isTracking = true;
            this.retryCount = 0;
            
            //   
            await this.getCurrentPosition();
            
            //   
            this.watchId = navigator.geolocation.watchPosition(
                (position) => this.onPositionUpdate(position),
                (error) => this.onPositionError(error),
                this.options.watchOptions
            );
            
            //   
            if (this.options.autoUpdate) {
                this.setupAutoUpdate();
            }
            
            this.emit('started', { message: '  .' });
            console.log('Location tracking started');
            
        } catch (error) {
            console.error('Failed to start tracking:', error);
            this.emit('error', { message: error.message });
        }
    }
    
    //   
    async stopTracking() {
        if (!this.isTracking) {
            return;
        }
        
        this.isTracking = false;
        
        //   
        if (this.watchId) {
            navigator.geolocation.clearWatch(this.watchId);
            this.watchId = null;
        }
        
        //    
        if (this.updateTimer) {
            clearInterval(this.updateTimer);
            this.updateTimer = null;
        }
        
        try {
            //    
            await this.apiCall('stop_tracking', {}, 'POST');
        } catch (error) {
            console.error('Failed to notify server about tracking stop:', error);
        }
        
        this.emit('stopped', { message: '  .' });
        console.log('Location tracking stopped');
    }
    
    //     
    getCurrentPosition() {
        return new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.onPositionUpdate(position);
                    resolve(position);
                },
                (error) => {
                    this.onPositionError(error);
                    reject(error);
                },
                this.options.watchOptions
            );
        });
    }
    
    //   
    async onPositionUpdate(position) {
        const { latitude, longitude, accuracy } = position.coords;
        const timestamp = position.timestamp;
        
        console.log(`Position update: ${latitude}, ${longitude} (accuracy: ${accuracy}m)`);
        
        //     
        if (accuracy > 100) {
            console.log('Position accuracy too low, skipping update');
            return;
        }
        
        //    
        if (this.lastKnownPosition) {
            const distance = this.calculateDistance(
                this.lastKnownPosition.latitude,
                this.lastKnownPosition.longitude,
                latitude,
                longitude
            );
            
            // 10m    ( )
            if (distance < 0.01) { // 0.01km = 10m
                console.log('Movement too small, skipping update');
                return;
            }
        }
        
        this.lastKnownPosition = { latitude, longitude, accuracy, timestamp };
        
        //   
        try {
            await this.updateLocationOnServer(latitude, longitude);
            this.retryCount = 0; //    
        } catch (error) {
            console.error('Failed to update location on server:', error);
            this.handleRetry();
        }
        
        this.emit('position', {
            latitude,
            longitude,
            accuracy,
            timestamp
        });
    }
    
    //   
    onPositionError(error) {
        let message = '    .';
        
        switch (error.code) {
            case error.PERMISSION_DENIED:
                message = '   .';
                break;
            case error.POSITION_UNAVAILABLE:
                message = '    .';
                break;
            case error.TIMEOUT:
                message = '    .';
                break;
        }
        
        console.error('Position error:', error);
        this.emit('error', { message, error });
        
        //  
        this.handleRetry();
    }
    
    //   
    async updateLocationOnServer(latitude, longitude, locationName = '', address = '', notes = '') {
        const data = {
            latitude,
            longitude,
            location_name: locationName || this.generateLocationName(latitude, longitude),
            address,
            notes
        };
        
        const response = await this.apiCall('update_location', data, 'POST');
        
        if (!response.success) {
            throw new Error(response.error || '  .');
        }
        
        return response;
    }
    
    //   
    async manualUpdate(locationData) {
        try {
            const response = await this.updateLocationOnServer(
                locationData.latitude,
                locationData.longitude,
                locationData.locationName || '',
                locationData.address || '',
                locationData.notes || ''
            );
            
            this.emit('updated', response);
            return response;
        } catch (error) {
            this.emit('error', { message: error.message });
            throw error;
        }
    }
    
    //   
    async getGuideLocation(guideId = null) {
        const action = guideId ? 'get_guide_location' : 'get_location';
        const params = guideId ? { guide_id: guideId } : {};
        
        try {
            const response = await this.apiCall(action, params, 'GET');
            return response;
        } catch (error) {
            console.error('Failed to get guide location:', error);
            throw error;
        }
    }
    
    //   
    setupAutoUpdate() {
        if (this.updateTimer) {
            clearInterval(this.updateTimer);
        }
        
        this.updateTimer = setInterval(async () => {
            if (this.isTracking && this.lastKnownPosition) {
                try {
                    await this.getCurrentPosition();
                } catch (error) {
                    console.error('Auto update failed:', error);
                }
            }
        }, this.options.updateInterval);
    }
    
    //  
    handleRetry() {
        if (this.retryCount < this.options.maxRetries) {
            this.retryCount++;
            console.log(`Retrying in ${this.options.retryDelay}ms (attempt ${this.retryCount})`);
            
            setTimeout(() => {
                if (this.isTracking && this.lastKnownPosition) {
                    this.updateLocationOnServer(
                        this.lastKnownPosition.latitude,
                        this.lastKnownPosition.longitude
                    ).catch(error => {
                        console.error('Retry failed:', error);
                        this.handleRetry();
                    });
                }
            }, this.options.retryDelay);
        } else {
            console.error('Max retries exceeded');
            this.emit('error', { message: '    .' });
        }
    }
    
    //   /
    pauseTracking() {
        if (this.watchId) {
            navigator.geolocation.clearWatch(this.watchId);
            this.watchId = null;
        }
        
        if (this.updateTimer) {
            clearInterval(this.updateTimer);
            this.updateTimer = null;
        }
    }
    
    resumeTracking() {
        if (this.isTracking) {
            this.watchId = navigator.geolocation.watchPosition(
                (position) => this.onPositionUpdate(position),
                (error) => this.onPositionError(error),
                this.options.watchOptions
            );
            
            if (this.options.autoUpdate) {
                this.setupAutoUpdate();
            }
        }
    }
    
    // API 
    async apiCall(action, data = {}, method = 'GET') {
        const url = new URL(this.options.apiUrl, window.location.origin);
        url.searchParams.set('action', action);
        
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin'
        };
        
        if (method === 'POST') {
            options.body = JSON.stringify(data);
        } else {
            Object.keys(data).forEach(key => {
                url.searchParams.set(key, data[key]);
            });
        }
        
        const response = await fetch(url, options);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    }
    
    //  
    calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371; //   (km)
        const dLat = this.degToRad(lat2 - lat1);
        const dLon = this.degToRad(lon2 - lon1);
        const a = 
            Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(this.degToRad(lat1)) * Math.cos(this.degToRad(lat2)) * 
            Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    }
    
    degToRad(deg) {
        return deg * (Math.PI/180);
    }
    
    generateLocationName(latitude, longitude) {
        //    (  API  )
        return ` ${latitude.toFixed(4)}, ${longitude.toFixed(4)}`;
    }
    
    //  
    enableBatteryOptimization() {
        this.options.updateInterval = 60000; // 1 
        this.options.watchOptions.maximumAge = 300000; // 5
        this.options.watchOptions.timeout = 15000; // 15
        
        if (this.updateTimer) {
            this.setupAutoUpdate();
        }
    }
    
    disableBatteryOptimization() {
        this.options.updateInterval = 30000; // 30 
        this.options.watchOptions.maximumAge = 60000; // 1
        this.options.watchOptions.timeout = 10000; // 10
        
        if (this.updateTimer) {
            this.setupAutoUpdate();
        }
    }
    
    //  
    getStatus() {
        return {
            isTracking: this.isTracking,
            lastKnownPosition: this.lastKnownPosition,
            retryCount: this.retryCount,
            watchId: this.watchId,
            hasGeolocation: !!navigator.geolocation,
            isOnline: navigator.onLine
        };
    }
}

//      
window.GuideLocationTracker = GuideLocationTracker;

//   
window.initializeLocationTracking = function(options = {}) {
    const tracker = new GuideLocationTracker(options);
    
    //   
    tracker.on('started', (data) => {
        console.log('Tracking started:', data);
        showNotification(data.message, 'success');
    });
    
    tracker.on('stopped', (data) => {
        console.log('Tracking stopped:', data);
        showNotification(data.message, 'info');
    });
    
    tracker.on('position', (data) => {
        console.log('Position updated:', data);
        updateLocationDisplay(data);
    });
    
    tracker.on('updated', (data) => {
        console.log('Location updated on server:', data);
        showNotification(' .', 'success');
    });
    
    tracker.on('error', (data) => {
        console.error('Location error:', data);
        showNotification(data.message, 'error');
    });
    
    return tracker;
};

// UI  
function showNotification(message, type = 'info') {
    //   UI 
    console.log(`[${type.toUpperCase()}] ${message}`);
    
    //    (    UI )
    if (typeof toastr !== 'undefined') {
        toastr[type](message);
    } else {
        alert(message);
    }
}

function updateLocationDisplay(location) {
    //    
    const elements = {
        latitude: document.getElementById('current-latitude'),
        longitude: document.getElementById('current-longitude'),
        accuracy: document.getElementById('current-accuracy'),
        timestamp: document.getElementById('last-update')
    };
    
    if (elements.latitude) elements.latitude.textContent = location.latitude.toFixed(6);
    if (elements.longitude) elements.longitude.textContent = location.longitude.toFixed(6);
    if (elements.accuracy) elements.accuracy.textContent = `${Math.round(location.accuracy)}m`;
    if (elements.timestamp) elements.timestamp.textContent = new Date(location.timestamp).toLocaleString();
}

//    ( )
if ('getBattery' in navigator) {
    navigator.getBattery().then(function(battery) {
        //      
        if (battery.level < 0.2) {
            console.log('Low battery detected, enabling optimization mode');
            // tracker.enableBatteryOptimization();
        }
        
        battery.addEventListener('levelchange', function() {
            if (battery.level < 0.2) {
                // tracker.enableBatteryOptimization();
            } else if (battery.level > 0.5) {
                // tracker.disableBatteryOptimization();
            }
        });
    });
}

//   (ES6  )
// export default GuideLocationTracker;