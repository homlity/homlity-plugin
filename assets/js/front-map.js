(function () {
    function initLeafletIcons() {
        if (!window.L || !window.L.Icon || !window.L.Icon.Default) {
            return;
        }

        window.L.Icon.Default.mergeOptions({
            iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
            iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
            shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
        });
    }

    function initMap(node) {
        if (!window.L) {
            return;
        }

        const lat = parseFloat(node.dataset.lat || '');
        const lng = parseFloat(node.dataset.lng || '');
        const zoom = parseInt(node.dataset.zoom || '16', 10);
        const title = node.dataset.title || '';

        if (Number.isNaN(lat) || Number.isNaN(lng)) {
            return;
        }

        const map = window.L.map(node, {
            scrollWheelZoom: false,
        }).setView([lat, lng], Number.isNaN(zoom) ? 16 : zoom);

        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);

        const marker = window.L.marker([lat, lng]).addTo(map);
        if (title) {
            marker.bindPopup(title);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initLeafletIcons();

        document.querySelectorAll('.homlity-front-leaflet-map').forEach(function (node) {
            if (node.dataset.mapReady === '1') {
                return;
            }

            node.dataset.mapReady = '1';
            initMap(node);
        });
    });
}());
