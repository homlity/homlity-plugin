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

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character];
        });
    }

    function markerPopup(data) {
        data = data || {};
        var title = escapeHtml(data.title || '');
        var prices = Array.isArray(data.prices) ? data.prices : [];
        var operations = Array.isArray(data.operations) ? data.operations : [];
        var location = Array.isArray(data.location) ? data.location : [];
        var pricesHtml = prices.map(function (price) {
            var label = escapeHtml(price && price.label ? price.label : '');
            var amount = escapeHtml(price && price.amount ? price.amount : '');
            return '<span class="homlity-map-popup__price-line">' + (label ? '<small>' + label + '</small>' : '') + amount + '</span>';
        }).join('');
        var features = [['bedrooms', 'fa-bed', 'Habitaciones'], ['bathrooms', 'fa-bath', 'Baños'], ['parking', 'fa-car', 'Parqueaderos']]
            .filter(function (feature) { return data[feature[0]] !== '' && data[feature[0]] != null; })
            .map(function (feature) {
                return '<span class="homlity-map-popup__feature" aria-label="' + feature[2] + ': ' + escapeHtml(data[feature[0]]) + '" title="' + feature[2] + '">' +
                    '<i class="fas ' + feature[1] + '" aria-hidden="true"></i><b>' + escapeHtml(data[feature[0]]) + '</b></span>';
            })
            .join('');

        return '<div class="homlity-map-popup">' +
            (data.thumbnail ? '<img src="' + escapeHtml(data.thumbnail) + '" alt="" class="homlity-map-popup__img">' : '') +
            (title ? '<a href="' + escapeHtml(data.url || '#') + '" class="homlity-map-popup__title">' + title + '</a>' : '') +
            (operations.length ? '<span class="homlity-map-popup__operation">' + escapeHtml(operations.join(' · ')) + '</span>' : '') +
            (pricesHtml ? '<div class="homlity-map-popup__prices">' + pricesHtml + '</div>' : '') +
            (features ? '<div class="homlity-map-popup__features">' + features + '</div>' : '') +
            (location.length ? '<span class="homlity-map-popup__location">' + escapeHtml(location.join(' · ')) + '</span>' : '') +
        '</div>';
    }

    function initMap(node) {
        if (!window.L) {
            return;
        }

        const lat = parseFloat(node.dataset.lat || '');
        const lng = parseFloat(node.dataset.lng || '');
        const zoom = parseInt(node.dataset.zoom || '16', 10);
        const title = node.dataset.title || '';
        let markerData = {};
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
        try {
            markerData = JSON.parse(node.dataset.markerPopup || '{}') || {};
        } catch (e) {
            markerData = {};
        }
        if (!markerData.title && title) markerData.title = title;

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
            if (markerData.title) {
                marker.bindPopup(markerPopup(markerData));
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
                if (rTitle) {
                    marker.bindPopup(markerPopup(item));
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
