const pinIcon = {
    iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
    iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41],
};

document.addEventListener('DOMContentLoaded', () => {
    if (typeof homlityPluginMap === 'undefined') return;
    const { defaultLat, defaultLng } = homlityPluginMap;
    const mapContainer = document.getElementById('property_map_preview');
    if (!mapContainer || typeof L === 'undefined') return;

    const latInput = document.getElementById('property_latitude');
    const lngInput = document.getElementById('property_longitude');
    const addressInput = document.getElementById('property_address');

    const map = L.map(mapContainer).setView([defaultLat, defaultLng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    const marker = L.marker([defaultLat, defaultLng], { draggable: true, icon: L.icon(pinIcon) }).addTo(map);

    function updateInputs(lat, lng) {
        if (latInput) latInput.value = lat.toFixed(6);
        if (lngInput) lngInput.value = lng.toFixed(6);
    }

    function moveMarker(lat, lng) {
        marker.setLatLng([lat, lng]);
        map.setView([lat, lng], 14);
        updateInputs(lat, lng);
    }

    marker.on('dragend', () => {
        const pos = marker.getLatLng();
        updateInputs(pos.lat, pos.lng);
    });

    if (latInput && lngInput) {
        latInput.addEventListener('change', () => {
            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);
            if (!isNaN(lat) && !isNaN(lng)) {
                moveMarker(lat, lng);
            }
        });
        lngInput.addEventListener('change', () => {
            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);
            if (!isNaN(lat) && !isNaN(lng)) {
                moveMarker(lat, lng);
            }
        });
    }

    let debounceTimer = null;
    function debounce(fn) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fn, 500);
    }

    async function geocode(query) {
        const url = new URL('https://nominatim.openstreetmap.org/search');
        url.searchParams.set('q', query);
        url.searchParams.set('format', 'json');
        url.searchParams.set('limit', '1');
        const res = await fetch(url.toString(), {
            headers: { 'Accept-Language': 'es' },
        });
        if (!res.ok) return null;
        const data = await res.json();
        if (!data.length) return null;
        return { lat: parseFloat(data[0].lat), lng: parseFloat(data[0].lon) };
    }

    function buildQuery() {
        const country = document.querySelector('#property_country option:checked')?.text || '';
        const state = document.querySelector('#property_state option:checked')?.text || '';
        const city = document.querySelector('#property_city option:checked')?.text || '';
        const neighborhood = document.querySelector('#property_neighborhood option:checked')?.text || '';
        const address = addressInput?.value || '';
        return [address, neighborhood, city, state, country].filter(Boolean).join(', ');
    }

    async function geocodeAndUpdate() {
        const query = buildQuery();
        if (!query) return;
        const result = await geocode(query);
        if (result) {
            moveMarker(result.lat, result.lng);
        }
    }

    if (addressInput) {
        addressInput.addEventListener('input', () => debounce(geocodeAndUpdate));
    }

    ['property_country', 'property_state', 'property_city', 'property_neighborhood'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', () => debounce(geocodeAndUpdate));
        }
    });
});
