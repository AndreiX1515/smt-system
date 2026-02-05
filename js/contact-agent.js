// Contact Agent Page JavaScript

let map = null;
let userLocation = null;
let agentMarkers = [];
let userMarker = null;
let agents = [];

// Manila default coordinates (fallback)
const DEFAULT_LOCATION = {
    lat: 14.5995,
    lng: 120.9842
};

// Initialize Google Map
function initMap() {
    const mapElement = document.getElementById('map');
    const mapLoading = document.getElementById('mapLoading');

    if (!mapElement) return;

    // Create map centered on default location
    map = new google.maps.Map(mapElement, {
        center: DEFAULT_LOCATION,
        zoom: 12,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: true,
        styles: [
            {
                featureType: 'poi',
                elementType: 'labels',
                stylers: [{ visibility: 'off' }]
            }
        ]
    });

    // Show map, hide loading
    mapElement.style.display = 'block';
    if (mapLoading) mapLoading.style.display = 'none';

    // Get user's GPS location
    getUserLocation();

    // Load agent locations
    loadAgents();
}

// Get user's current GPS location
function getUserLocation() {
    const gpsStatus = document.getElementById('gpsStatus');

    if (!navigator.geolocation) {
        updateGpsStatus('error', 'Geolocation is not supported by your browser');
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (position) => {
            userLocation = {
                lat: position.coords.latitude,
                lng: position.coords.longitude
            };

            updateGpsStatus('success', 'Location detected successfully');

            // Center map on user location
            if (map) {
                map.setCenter(userLocation);

                // Add user marker
                if (userMarker) userMarker.setMap(null);
                userMarker = new google.maps.Marker({
                    position: userLocation,
                    map: map,
                    title: 'Your Location',
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 10,
                        fillColor: '#4285F4',
                        fillOpacity: 1,
                        strokeColor: '#ffffff',
                        strokeWeight: 3
                    },
                    zIndex: 999
                });
            }

            // Re-sort agents by distance if already loaded
            if (agents.length > 0) {
                sortAndRenderAgents();
            }
        },
        (error) => {
            let errorMessage = 'Unable to retrieve your location';
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    errorMessage = 'Location access denied. Showing all agents.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMessage = 'Location information unavailable';
                    break;
                case error.TIMEOUT:
                    errorMessage = 'Location request timed out';
                    break;
            }
            updateGpsStatus('error', errorMessage);
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 300000 // 5 minutes cache
        }
    );
}

// Update GPS status display
function updateGpsStatus(status, message) {
    const gpsStatus = document.getElementById('gpsStatus');
    if (!gpsStatus) return;

    gpsStatus.className = 'gps-status ' + status;
    gpsStatus.innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/>
        </svg>
        <span>${message}</span>
    `;
}

// Load agents from API
async function loadAgents() {
    try {
        const response = await fetch('../backend/api/public-agents.php');
        const result = await response.json();

        if (result.success && result.data && result.data.length > 0) {
            agents = result.data;
            sortAndRenderAgents();
        } else {
            renderNoAgents();
        }
    } catch (error) {
        console.error('Error loading agents:', error);
        renderNoAgents('Unable to load agent locations. Please try again later.');
    }
}

// Sort agents by distance and render
function sortAndRenderAgents() {
    // Calculate distance if user location is available
    if (userLocation) {
        agents.forEach(agent => {
            if (agent.latitude && agent.longitude) {
                agent.distance = calculateDistance(
                    userLocation.lat, userLocation.lng,
                    parseFloat(agent.latitude), parseFloat(agent.longitude)
                );
            } else {
                agent.distance = Infinity;
            }
        });

        // Sort by distance
        agents.sort((a, b) => a.distance - b.distance);
    }

    // Clear existing markers
    agentMarkers.forEach(marker => marker.setMap(null));
    agentMarkers = [];

    // Add markers for each agent
    const bounds = new google.maps.LatLngBounds();

    if (userLocation) {
        bounds.extend(userLocation);
    }

    agents.forEach((agent, index) => {
        if (agent.latitude && agent.longitude) {
            const position = {
                lat: parseFloat(agent.latitude),
                lng: parseFloat(agent.longitude)
            };

            const marker = new google.maps.Marker({
                position: position,
                map: map,
                title: agent.storeName || agent.companyName || 'Agent',
                label: {
                    text: String(index + 1),
                    color: 'white',
                    fontSize: '12px',
                    fontWeight: 'bold'
                },
                icon: {
                    path: 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z',
                    fillColor: '#FF6B6B',
                    fillOpacity: 1,
                    strokeColor: '#ffffff',
                    strokeWeight: 2,
                    scale: 1.5,
                    anchor: new google.maps.Point(12, 24),
                    labelOrigin: new google.maps.Point(12, 9)
                }
            });

            // Info window
            const infoWindow = new google.maps.InfoWindow({
                content: `
                    <div style="padding: 8px; max-width: 200px;">
                        <strong>${agent.storeName || agent.companyName || 'Agent'}</strong><br>
                        <span style="color: #666; font-size: 12px;">${agent.storeAddress || ''}</span>
                    </div>
                `
            });

            marker.addListener('click', () => {
                infoWindow.open(map, marker);
                highlightAgentCard(agent.id);
            });

            agentMarkers.push(marker);
            bounds.extend(position);
        }
    });

    // Fit map to show all markers
    if (agentMarkers.length > 0) {
        map.fitBounds(bounds);
        // Don't zoom in too much
        const listener = google.maps.event.addListenerOnce(map, 'idle', () => {
            if (map.getZoom() > 15) map.setZoom(15);
        });
    }

    // Render agent list
    renderAgentList();
}

// Calculate distance between two points (Haversine formula)
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth's radius in km
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
              Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
              Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
}

function toRad(deg) {
    return deg * (Math.PI / 180);
}

// Format distance for display
function formatDistance(km) {
    if (km === Infinity || isNaN(km)) return '';
    if (km < 1) {
        return Math.round(km * 1000) + ' m away';
    }
    return km.toFixed(1) + ' km away';
}

// Render agent list
function renderAgentList() {
    const agentList = document.getElementById('agentList');
    if (!agentList) return;

    if (agents.length === 0) {
        renderNoAgents();
        return;
    }

    const html = agents.map((agent, index) => {
        const distanceText = userLocation && agent.distance !== Infinity
            ? `<div class="agent-distance">${formatDistance(agent.distance)}</div>`
            : '';

        const phoneNumber = agent.phone || agent.contactPhone || '';
        const callBtn = phoneNumber
            ? `<a href="tel:${phoneNumber}" class="btn line lg">
                <img src="../images/ico_phone.svg" alt="" style="width:16px;height:16px;margin-right:4px;">Call
               </a>`
            : '';

        return `
            <li class="agent-card" data-agent-id="${agent.id}" onclick="focusAgent(${index})">
                <div class="agent-name">
                    <span style="display:inline-block;width:20px;height:20px;background:#FF6B6B;color:white;border-radius:50%;text-align:center;line-height:20px;font-size:12px;margin-right:8px;">${index + 1}</span>
                    ${agent.storeName || agent.companyName || 'Agent'}
                </div>
                ${agent.storeAddress ? `<div class="agent-address">${agent.storeAddress}</div>` : ''}
                ${phoneNumber ? `<div class="agent-contact"><img src="../images/ico_phone.svg" alt="" style="width:14px;height:14px;margin-right:4px;vertical-align:middle;">${phoneNumber}</div>` : ''}
                ${distanceText}
                <div class="agent-actions">
                    ${callBtn}
                    <button class="btn primary lg" onclick="event.stopPropagation(); openDirections(${agent.latitude}, ${agent.longitude}, '${encodeURIComponent(agent.storeName || agent.companyName || 'Agent')}')">
                        <img src="../images/ico_location_white.svg" alt="" style="width:16px;height:16px;margin-right:4px;" onerror="this.style.display='none'">Directions
                    </button>
                </div>
            </li>
        `;
    }).join('');

    agentList.innerHTML = html;
}

// Render no agents message
function renderNoAgents(message = 'No agent locations available at this time.') {
    const agentList = document.getElementById('agentList');
    if (!agentList) return;

    agentList.innerHTML = `
        <li class="no-agents">
            <img src="../images/ico_location_gray.svg" alt="" onerror="this.style.display='none'">
            <p>${message}</p>
        </li>
    `;
}

// Focus on a specific agent on the map
function focusAgent(index) {
    if (!map || !agentMarkers[index]) return;

    const marker = agentMarkers[index];
    map.setCenter(marker.getPosition());
    map.setZoom(16);

    // Trigger click on marker to show info window
    google.maps.event.trigger(marker, 'click');

    // Highlight the card
    highlightAgentCard(agents[index].id);
}

// Highlight agent card
function highlightAgentCard(agentId) {
    // Remove previous highlight
    document.querySelectorAll('.agent-card.selected').forEach(el => {
        el.classList.remove('selected');
    });

    // Add highlight to selected card
    const card = document.querySelector(`.agent-card[data-agent-id="${agentId}"]`);
    if (card) {
        card.classList.add('selected');
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

// Open directions in Google Maps
function openDirections(lat, lng, name) {
    let url;

    if (userLocation) {
        // With user's location
        url = `https://www.google.com/maps/dir/?api=1&origin=${userLocation.lat},${userLocation.lng}&destination=${lat},${lng}&destination_place_id=${encodeURIComponent(name)}`;
    } else {
        // Without user's location - just show destination
        url = `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
    }

    window.open(url, '_blank');
}

// Make functions globally available
window.initMap = initMap;
window.focusAgent = focusAgent;
window.openDirections = openDirections;
