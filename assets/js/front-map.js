(function () {
    function initLeafletIcons() {
        if (!window.L || !window.L.Icon || !window.L.Icon.Default) {
            return;
        }

        const iconUrl = window.homlityLeafletAssets?.iconUrl || '';
        const iconRetinaUrl = window.homlityLeafletAssets?.iconRetinaUrl || '';
        const shadowUrl = window.homlityLeafletAssets?.shadowUrl || '';
        if (!iconUrl || !iconRetinaUrl || !shadowUrl) {
            return;
        }

        window.L.Icon.Default.mergeOptions({
            iconUrl,
            iconRetinaUrl,
            shadowUrl,
        });
    }

    function makeCustomIcon(url) {
        return window.L.icon({
            iconUrl:      url,
            iconSize:     [32, 32],
            iconAnchor:   [16, 32],
            popupAnchor:  [0, -34],
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
        node.__homlityLeafletMap = map;

        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);

        const iconUrl     = node.dataset.markerIcon || '';
        const fallbackUrl = node.dataset.markerIconFallback || '';

        function addMarker(url) {
            const icon   = url ? makeCustomIcon(url) : undefined;
            const marker = icon
                ? window.L.marker([lat, lng], { icon }).addTo(map)
                : window.L.marker([lat, lng]).addTo(map);
            if (title) {
                marker.bindPopup(title);
            }
        }

        if (iconUrl) {
            const img = new Image();
            img.onload  = function () { addMarker(iconUrl); };
            img.onerror = function () { addMarker(fallbackUrl); };
            img.src = iconUrl;
        } else {
            addMarker(fallbackUrl);
        }

        node.addEventListener('homlity:map-resize', function () {
            if (node.__homlityLeafletMap && typeof node.__homlityLeafletMap.invalidateSize === 'function') {
                setTimeout(function () {
                    node.__homlityLeafletMap.invalidateSize();
                }, 50);
            }
        });
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
