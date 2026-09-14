(function () {
  'use strict';

  function parseJsonSafe(raw, fallback) {
    if (!raw) return fallback;
    try {
      var parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : fallback;
    } catch (e) {
      return fallback;
    }
  }

  function toNumber(value, fallback) {
    var n = Number(value);
    return Number.isFinite(n) ? n : fallback;
  }

  function isValidLatLng(lat, lng) {
    return Number.isFinite(lat) && Number.isFinite(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
  }

  function debounce(fn, delay) {
    var timer = null;
    return function () {
      var args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function () {
        fn.apply(null, args);
      }, delay);
    };
  }

  function initLeafletNode(node) {
    if (!node || node.dataset.mapReady === '1' || !window.L) return;

    var lat = parseFloat(node.dataset.lat || '');
    var lng = parseFloat(node.dataset.lng || '');
    var zoom = parseInt(node.dataset.zoom || '16', 10);
    var title = node.dataset.title || '';
    if (!isValidLatLng(lat, lng)) return;

    var related = [];
    try {
      related = JSON.parse(node.dataset.relatedMarkers || '[]');
      if (!Array.isArray(related)) related = [];
    } catch (e) {
      related = [];
    }

    var map = window.L.map(node, { scrollWheelZoom: false }).setView([lat, lng], Number.isFinite(zoom) ? zoom : 16);
    node.dataset.mapReady = '1';
    node.__homlityLeafletMap = map;

    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var iconUrl    = node.dataset.markerIcon || '';
    var markerBg   = node.dataset.markerBgColor || '#ffffff';
    var bgStyle    = 'background-color:' + markerBg + ';';
    var customIcon = iconUrl
      ? window.L.divIcon({
          html: '<span class="homlity-map-marker__wrap" style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;border:1px solid rgba(15,23,42,.16);border-radius:50%;box-shadow:0 2px 7px rgba(15,23,42,.28);' + bgStyle + '">'
              + '<img src="' + iconUrl + '" width="22" height="22" style="object-fit:contain;" alt=""/>'
              + '</span>',
          iconSize: [36, 36],
          iconAnchor: [18, 36],
          popupAnchor: [0, -38],
          className: ''
        })
      : null;

    var marker = customIcon
      ? window.L.marker([lat, lng], { icon: customIcon }).addTo(map)
      : window.L.marker([lat, lng]).addTo(map);
    if (title) marker.bindPopup(title);

    var bounds = [[lat, lng]];
    related.forEach(function (item) {
      var rLat = parseFloat(item && item.lat);
      var rLng = parseFloat(item && item.lng);
      if (!isValidLatLng(rLat, rLng)) return;
      var mk = customIcon
        ? window.L.marker([rLat, rLng], { icon: customIcon }).addTo(map)
        : window.L.marker([rLat, rLng]).addTo(map);
      if (item && item.title && item.url) {
        mk.bindPopup('<a href="' + String(item.url) + '">' + String(item.title) + '</a>');
      }
      bounds.push([rLat, rLng]);
    });

    if (bounds.length > 1) {
      map.fitBounds(bounds, { padding: [24, 24], maxZoom: Number.isFinite(zoom) ? zoom : 16 });
    }
  }

  function HomlityPropertyMap(root) {
    this.root = root;
    this.settings = parseJsonSafe(root.getAttribute('data-settings') || '', {});
    this.lat = toNumber(root.getAttribute('data-lat'), NaN);
    this.lng = toNumber(root.getAttribute('data-lng'), NaN);
    this.tabs = Array.prototype.slice.call(root.querySelectorAll('.property-map__tab[data-map-tab]'));
    this.panels = Array.prototype.slice.call(root.querySelectorAll('.property-map__panel[data-map-panel]'));
    this.state = {
      mapInitialized: false,
      iframeLoaded: false
    };
  }

  HomlityPropertyMap.prototype.init = function () {
    if (this.root.dataset.mapTabsReady === '1') return;
    this.root.dataset.mapTabsReady = '1';
    this.bindTabs();
    this.initMap();

    var initialTab = (this.settings.initial_tab === 'street' ? 'street' : 'map');
    this.showPanel(initialTab, true);

    var self = this;
    window.addEventListener('resize', debounce(function () {
      self.resizeActiveMap();
    }, 120));
  };

  HomlityPropertyMap.prototype.bindTabs = function () {
    var self = this;
    this.tabs.forEach(function (tab, idx) {
      tab.addEventListener('click', function () {
        if (tab.classList.contains('is-disabled') || tab.getAttribute('aria-disabled') === 'true') return;
        self.showPanel(tab.getAttribute('data-map-tab') || 'map', false);
      });
      tab.addEventListener('keydown', function (event) {
        var key = event.key;
        if (key === 'ArrowRight' || key === 'ArrowLeft') {
          event.preventDefault();
          var dir = key === 'ArrowRight' ? 1 : -1;
          var next = (idx + dir + self.tabs.length) % self.tabs.length;
          self.tabs[next].focus();
        }
        if (key === 'Enter' || key === ' ') {
          event.preventDefault();
          tab.click();
        }
      });
    });

    var copyBtn = this.root.querySelector('[data-map-action="copy"]');
    if (copyBtn) {
      copyBtn.addEventListener('click', this.copyCoordinates.bind(this));
    }
  };

  HomlityPropertyMap.prototype.showPanel = function (panelName, force) {
    var self = this;
    this.tabs.forEach(function (tab) {
      var active = tab.getAttribute('data-map-tab') === panelName;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.tabIndex = active ? 0 : -1;
    });

    this.panels.forEach(function (panel) {
      var active = panel.getAttribute('data-map-panel') === panelName;
      panel.classList.toggle('is-active', active);
      panel.hidden = !active;
    });

    if (panelName === 'street') {
      this.activateStreetView(force);
    }

    requestAnimationFrame(function () {
      self.resizeActiveMap();
    });
  };

  HomlityPropertyMap.prototype.initMap = function () {
    if (this.state.mapInitialized) return;
    var leaf = this.root.querySelector('.homlity-front-leaflet-map');
    if (leaf) {
      initLeafletNode(leaf);
    }
    this.state.mapInitialized = true;
  };

  HomlityPropertyMap.prototype.activateStreetView = function () {
    this.loadStreetIframe();
  };

  HomlityPropertyMap.prototype.loadStreetIframe = function () {
    var panel = this.root.querySelector('.property-map__panel[data-map-panel="street"]');
    if (!panel || this.state.iframeLoaded) return;

    var iframe = panel.querySelector('iframe[data-map-src]');
    if (!iframe) {
      this.showStreetFallback();
      return;
    }

    var src = iframe.getAttribute('data-map-src') || '';
    if (!src) {
      this.showStreetFallback('error');
      return;
    }
    // Cross-origin iframe load does not prove coverage. Let Google render its response.
    iframe.hidden = false;
    iframe.src = src;
    this.state.iframeLoaded = true;
    this.hideStreetFallback();
  };

  HomlityPropertyMap.prototype.showStreetFallback = function (reason) {
    var fallback = this.root.querySelector('[data-map-street-fallback]');
    if (fallback) {
      fallback.hidden = false;
      var message = fallback.querySelector('[data-map-street-message]');
      if (message) message.textContent = reason === 'error'
        ? this.settings.street_error_message : this.settings.street_unavailable_message;
    }
    var canvas = this.root.querySelector('.property-map__street-canvas');
    if (canvas) canvas.hidden = true;
    var panel = this.root.querySelector('.property-map__panel[data-map-panel="street"]');
    if (panel) {
      var iframe = panel.querySelector('iframe[data-map-src]');
      if (iframe) iframe.hidden = true;
    }
  };

  HomlityPropertyMap.prototype.hideStreetFallback = function () {
    var fallback = this.root.querySelector('[data-map-street-fallback]');
    if (fallback) fallback.hidden = true;
    var iframe = this.root.querySelector('.property-map__panel[data-map-panel="street"] iframe');
    if (iframe) iframe.hidden = false;
  };

  HomlityPropertyMap.prototype.resizeActiveMap = function () {
    var isMapActive = this.root.querySelector('.property-map__panel[data-map-panel="map"].is-active');
    var leaf = this.root.querySelector('.homlity-front-leaflet-map');
    if (isMapActive && leaf && leaf.__homlityLeafletMap && typeof leaf.__homlityLeafletMap.invalidateSize === 'function') {
      leaf.__homlityLeafletMap.invalidateSize();
    }


  };

  HomlityPropertyMap.prototype.copyCoordinates = function (event) {
    event.preventDefault();
    var message = this.root.querySelector('[data-map-copy-feedback]');
    var text = this.lat + ', ' + this.lng;
    if (!navigator.clipboard || !navigator.clipboard.writeText) return;

    navigator.clipboard.writeText(text).then(function () {
      if (message) {
        message.hidden = false;
        setTimeout(function () { message.hidden = true; }, 1800);
      }
    });
  };

  function initAll(scope) {
    var root = scope && scope.querySelectorAll ? scope : document;
    root.querySelectorAll('[data-property-map]').forEach(function (node) {
      if (node.__homlityPropertyMap) return;
      var instance = new HomlityPropertyMap(node);
      node.__homlityPropertyMap = instance;
      instance.init();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { initAll(document); });
  } else {
    initAll(document);
  }

  window.addEventListener('elementor/frontend/init', function () {
    if (!window.elementorFrontend || !window.elementorFrontend.hooks) return;
    window.elementorFrontend.hooks.addAction('frontend/element_ready/property_map.default', function ($scope) {
      initAll($scope[0]);
    });
  });
})();
