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
        return window.L.divIcon({
            className: 'homlity-map-marker',
            html: '<span class="homlity-map-marker__wrap"><img src="' + url + '" width="22" height="22" alt=""></span>',
            iconSize:     [36, 36],
            iconAnchor:   [18, 36],
            popupAnchor:  [0, -38],
        });
    }

    function makeRelatedIcon() {
        return window.L.divIcon({
            className: 'homlity-related-marker',
            html: '<span class="homlity-related-marker__dot"></span>',
            iconSize: [18, 18],
            iconAnchor: [9, 9],
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
        let relatedMarkers = [];

        if (Number.isNaN(lat) || Number.isNaN(lng)) {
            return;
        }

        try {
            relatedMarkers = JSON.parse(node.dataset.relatedMarkers || '[]');
            if (!Array.isArray(relatedMarkers)) {
                relatedMarkers = [];
            }
        } catch (e) {
            relatedMarkers = [];
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
            return marker;
        }

        const boundsPoints = [[lat, lng]];

        function addRelatedMarkers() {
            relatedMarkers.forEach(function (item) {
                const rLat = parseFloat(item && item.lat);
                const rLng = parseFloat(item && item.lng);
                if (Number.isNaN(rLat) || Number.isNaN(rLng)) {
                    return;
                }

                const marker = window.L.marker([rLat, rLng], { icon: makeRelatedIcon() }).addTo(map);
                const rTitle = (item && item.title) ? String(item.title) : '';
                const rUrl = (item && item.url) ? String(item.url) : '';
                if (rTitle && rUrl) {
                    marker.bindPopup('<a href="' + rUrl + '">' + rTitle + '</a>');
                } else if (rTitle) {
                    marker.bindPopup(rTitle);
                }
                boundsPoints.push([rLat, rLng]);
            });

            if (boundsPoints.length > 1) {
                map.fitBounds(boundsPoints, { padding: [28, 28], maxZoom: Number.isNaN(zoom) ? 16 : zoom });
            }
        }

        if (iconUrl) {
            const img = new Image();
            img.onload  = function () { addMarker(iconUrl); addRelatedMarkers(); };
            img.onerror = function () { addMarker(fallbackUrl); addRelatedMarkers(); };
            img.src = iconUrl;
        } else {
            addMarker(fallbackUrl);
            addRelatedMarkers();
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
