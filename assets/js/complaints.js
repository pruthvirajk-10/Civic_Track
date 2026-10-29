/**
 * CivicTrack — Complaint Form, Leaflet Map & Interactive Features
 */

// Initialize Leaflet Map for Location Selection
// Initialize Leaflet Map for Location Selection with Pune Defaults
function initComplaintMap(defaultLat = 18.5204, defaultLng = 73.8567, zoom = 13) {
    const mapElement = document.getElementById('complaintMap');
    if (!mapElement || typeof L === 'undefined') return;

    // Ensure Leaflet default marker icons resolve reliably
    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    });

    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    const addressInput = document.getElementById('address');

    // Check if initial values exist in inputs
    const initialLat = (latInput && latInput.value) ? parseFloat(latInput.value) : defaultLat;
    const initialLng = (lngInput && lngInput.value) ? parseFloat(lngInput.value) : defaultLng;

    const map = L.map('complaintMap').setView([initialLat, initialLng], zoom);

    // OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Marker
    let marker = L.marker([initialLat, initialLng], {
        draggable: true
    }).addTo(map);

    // Reverse geocode lat/lng to human-readable street address using Nominatim
    let reverseGeocodeTimer = null;
    function reverseGeocode(lat, lng, forceUpdateAddress = false) {
        clearTimeout(reverseGeocodeTimer);
        reverseGeocodeTimer = setTimeout(() => {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.address) {
                    const addr = data.address;
                    const parts = [
                        addr.road || addr.pedestrian || addr.suburb || '',
                        addr.neighbourhood || addr.residential || '',
                        addr.city_district || addr.suburb || addr.city || 'Pune'
                    ].filter(p => p.trim() !== '');

                    const resolvedAddress = parts.join(', ') || data.display_name.split(',').slice(0, 3).join(',');

                    // Only autofill address if empty or forceUpdate requested
                    if (addressInput && (forceUpdateAddress || addressInput.value.trim() === '')) {
                        addressInput.value = resolvedAddress;
                    }
                }
            })
            .catch(() => {
                // Network or reverse geocoding request silently ignored
            });
        }, 400);
    }

    // Update form inputs when marker is moved
    function updateCoordinates(lat, lng, forceGeocode = false) {
        if (latInput) latInput.value = lat.toFixed(6);
        if (lngInput) lngInput.value = lng.toFixed(6);
        reverseGeocode(lat, lng, forceGeocode);
    }

    marker.on('dragend', function (e) {
        const position = marker.getLatLng();
        updateCoordinates(position.lat, position.lng, false);
    });

    // Move marker when map is clicked
    map.on('click', function (e) {
        marker.setLatLng(e.latlng);
        updateCoordinates(e.latlng.lat, e.latlng.lng, false);
    });

    // Detect GPS button with desktop/IP fallback
    const detectGpsBtn = document.getElementById('detectGpsBtn');
    if (detectGpsBtn) {
        detectGpsBtn.addEventListener('click', function () {
            detectGpsBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Locating...';
            detectGpsBtn.disabled = true;

            const applyLocation = (lat, lng, label = 'Location Detected') => {
                map.setView([lat, lng], 16);
                marker.setLatLng([lat, lng]);
                updateCoordinates(lat, lng, true);
                detectGpsBtn.innerHTML = `<i class="bi bi-geo-alt-fill me-1 text-success"></i> ${label}`;
                detectGpsBtn.disabled = false;
            };

            const fallbackToPune = () => {
                // If browser geolocation fails, default smoothly to Pune center
                applyLocation(defaultLat, defaultLng, 'Pune Centered');
            };

            if (!navigator.geolocation) {
                fallbackToPune();
                return;
            }

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    applyLocation(position.coords.latitude, position.coords.longitude, 'GPS Located');
                },
                function (error) {
                    // Try low accuracy before falling back to default
                    navigator.geolocation.getCurrentPosition(
                        function (pos) {
                            applyLocation(pos.coords.latitude, pos.coords.longitude, 'Located');
                        },
                        function () {
                            fallbackToPune();
                        },
                        { enableHighAccuracy: false, timeout: 6000, maximumAge: 60000 }
                    );
                },
                { enableHighAccuracy: true, timeout: 5000, maximumAge: 30000 }
            );
        });
    }

    // Ensure map tiles calculate correctly
    setTimeout(() => { map.invalidateSize(); }, 300);
}

// Single Image Preview Handler
function initImagePreview(inputId, previewContainerId) {
    const input = document.getElementById(inputId);
    const container = document.getElementById(previewContainerId);

    if (!input || !container) return;

    input.addEventListener('change', function () {
        container.innerHTML = '';
        const file = this.files[0];
        if (!file) return;

        // Size check
        if (file.size > 5 * 1024 * 1024) {
            alert('File size exceeds the 5MB limit. Please select a smaller photo.');
            this.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            const previewCard = document.createElement('div');
            previewCard.className = 'd-flex align-items-center gap-3 p-2 border rounded bg-white mt-2 shadow-sm';
            previewCard.innerHTML = `
                <img src="${e.target.result}" alt="Preview" style="width: 70px; height: 70px; object-fit: cover; border-radius: 8px;">
                <div class="flex-grow-1 overflow-hidden">
                    <p class="mb-0 text-truncate fw-semibold text-dark small">${file.name}</p>
                    <small class="text-muted">${(file.size / (1024 * 1024)).toFixed(2)} MB</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger me-2" onclick="clearFileInput('${inputId}', '${previewContainerId}')">
                    <i class="bi bi-x-lg"></i>
                </button>
            `;
            container.appendChild(previewCard);
        };
        reader.readAsDataURL(file);
    });
}

function clearFileInput(inputId, previewContainerId) {
    const input = document.getElementById(inputId);
    const container = document.getElementById(previewContainerId);
    if (input) input.value = '';
    if (container) container.innerHTML = '';
}

// Interactive Star Rating Widget
function initStarRating(ratingContainerId, hiddenInputId) {
    const container = document.getElementById(ratingContainerId);
    const input = document.getElementById(hiddenInputId);
    if (!container || !input) return;

    const stars = container.querySelectorAll('.star');

    function updateStars(val) {
        stars.forEach(star => {
            const starVal = parseInt(star.getAttribute('data-value'));
            if (starVal <= val) {
                star.classList.add('bi-star-fill', 'text-warning');
                star.classList.remove('bi-star', 'text-muted');
            } else {
                star.classList.add('bi-star', 'text-muted');
                star.classList.remove('bi-star-fill', 'text-warning');
            }
        });
    }

    stars.forEach(star => {
        star.addEventListener('click', function () {
            const val = parseInt(this.getAttribute('data-value'));
            input.value = val;
            updateStars(val);
        });

        star.addEventListener('mouseenter', function () {
            const val = parseInt(this.getAttribute('data-value'));
            updateStars(val);
        });
    });

    container.addEventListener('mouseleave', function () {
        const currentVal = parseInt(input.value) || 0;
        updateStars(currentVal);
    });
}
