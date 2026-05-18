(function () {
    'use strict';

    var cfg = window.homlityContactTracking || {};
    if (!cfg.ajaxUrl || !cfg.nonce) {
        return;
    }

    var sent = Object.create(null);

    function detectType(href) {
        var lower = (href || '').toLowerCase();
        if (lower.indexOf('mailto:') === 0) return 'email';
        if (lower.indexOf('tel:') === 0) return 'phone';
        if (lower.indexOf('wa.me/') !== -1 || lower.indexOf('whatsapp.com/') !== -1 || lower.indexOf('api.whatsapp.com/') !== -1) {
            return 'whatsapp';
        }
        return '';
    }

    function detectPropertyId(node) {
        var explicit = parseInt(node.getAttribute('data-property-id') || '', 10);
        if (explicit > 0) return explicit;

        var card = node.closest('[data-property-id]');
        if (card) {
            var fromCard = parseInt(card.getAttribute('data-property-id') || '', 10);
            if (fromCard > 0) return fromCard;
        }

        var body = document.body;
        if (body) {
            var match = (body.className || '').match(/postid-(\d+)/);
            if (match && match[1]) {
                return parseInt(match[1], 10) || 0;
            }
        }

        return 0;
    }

    function sendEvent(propertyId, eventType) {
        if (!propertyId || !eventType) return;
        var key = propertyId + '|' + eventType;
        if (sent[key]) return;
        sent[key] = true;

        var body = new URLSearchParams({
            action: 'homlity_track_contact_click',
            nonce: cfg.nonce,
            property_id: String(propertyId),
            event_type: eventType
        });

        fetch(cfg.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
            credentials: 'same-origin',
            keepalive: true
        }).catch(function () {});
    }

    document.addEventListener('click', function (evt) {
        var link = evt.target && evt.target.closest ? evt.target.closest('a[href]') : null;
        if (!link) return;

        var type = link.getAttribute('data-homlity-contact-type') || detectType(link.getAttribute('href') || '');
        if (!type) return;
        if (type !== 'whatsapp' && type !== 'phone' && type !== 'email') return;

        var propertyId = detectPropertyId(link);
        if (propertyId <= 0) return;

        sendEvent(propertyId, type);
    }, true);
}());
