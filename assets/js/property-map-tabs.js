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
    var markerBg   = node.dataset.markerBgColor || '';
    var bgStyle    = markerBg ? 'background-color:' + markerBg + ';' : '';
    var customIcon = iconUrl
      ? window.L.divIcon({
          html: '<span class="homlity-map-marker__wrap" style="display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,.3);' + bgStyle + '">'
              + '<img src="' + iconUrl + '" width="22" height="22" style="object-fit:contain;" alt=""/>'
              + '</span>',
          iconSize: [32, 32],
          iconAnchor: [16, 32],
          popupAnchor: [0, -34],
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
      streetViewChecked: false,
      streetViewAvailable: false,
      streetViewInitialized: false,
      iframeLoaded: false
    };
    this.streetData = null;
    this.panorama = null;
    this.mapApiPromise = null;
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

  HomlityPropertyMap.prototype.ensureGoogleMapsApi = function () {
    var self = this;
    if (window.google && window.google.maps) {
      return Promise.resolve();
    }
    if (this.mapApiPromise) return this.mapApiPromise;

    var key = this.settings.google_maps_api_key || '';
    if (!key) return Promise.reject(new Error('Google Maps API key missing'));

    this.mapApiPromise = new Promise(function (resolve, reject) {
      var cb = 'homlityMapInit_' + self.root.getAttribute('data-map-id');
      window[cb] = function () {
        resolve();
        try { delete window[cb]; } catch (e) {}
      };
      var script = document.createElement('script');
      script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key) + '&libraries=maps&callback=' + cb;
      script.async = true;
      script.defer = true;
      script.onerror = function () { reject(new Error('Google Maps API load failed')); };
      document.head.appendChild(script);
    });

    return this.mapApiPromise;
  };

  HomlityPropertyMap.prototype.checkStreetViewAvailability = function () {
    var self = this;
    if (this.state.streetViewChecked) {
      return Promise.resolve(this.state.streetViewAvailable);
    }

    this.state.streetViewChecked = true;
    if (!isValidLatLng(this.lat, this.lng)) {
      this.state.streetViewAvailable = false;
      return Promise.resolve(false);
    }

    return this.ensureGoogleMapsApi().then(function () {
      return new Promise(function (resolve) {
        var service = new window.google.maps.StreetViewService();
        service.getPanorama({
          location: { lat: self.lat, lng: self.lng },
          radius: toNumber(self.settings.street_radius, 100),
          source: window.google.maps.StreetViewSource.OUTDOOR
        }, function (data, status) {
          self.state.streetViewAvailable = status === window.google.maps.StreetViewStatus.OK;
          self.streetData = self.state.streetViewAvailable ? data : null;
          self.toggleStreetTabAvailability();
          resolve(self.state.streetViewAvailable);
        });
      });
    }).catch(function () {
      self.state.streetViewAvailable = false;
      self.toggleStreetTabAvailability();
      return false;
    });
  };

  HomlityPropertyMap.prototype.toggleStreetTabAvailability = function () {
    var tab = this.root.querySelector('.property-map__tab[data-map-tab="street"]');
    if (!tab) return;

    if (this.state.streetViewAvailable) {
      tab.hidden = false;
      tab.classList.remove('is-disabled');
      tab.setAttribute('aria-disabled', 'false');
      return;
    }

    var behavior = this.settings.street_unavailable_behavior || 'disabled';
    if (behavior === 'hide') {
      tab.hidden = true;
      if (tab.classList.contains('is-active')) {
        this.showPanel('map', true);
      }
    } else {
      tab.hidden = false;
      tab.classList.add('is-disabled');
      tab.setAttribute('aria-disabled', 'true');
    }
  };

  HomlityPropertyMap.prototype.activateStreetView = function (force) {
    var self = this;
    var mode = this.settings.street_mode || 'google_js';

    if (mode === 'iframe') {
      this.loadStreetIframe();
      return;
    }

    this.checkStreetViewAvailability().then(function (ok) {
      if (!ok) {
        self.showStreetFallback();
        if (!force && self.settings.street_unavailable_behavior === 'external') {
          var fallbackUrl = self.root.getAttribute('data-street-external-url') || '';
          if (fallbackUrl) window.open(fallbackUrl, self.settings.open_new_tab ? '_blank' : '_self', 'noopener,noreferrer');
        }
        return;
      }
      self.initStreetView();
    });
  };

  HomlityPropertyMap.prototype.initStreetView = function () {
    if (this.state.streetViewInitialized || !this.streetData || !(window.google && window.google.maps)) return;

    var canvas = this.root.querySelector('.property-map__street-canvas');
    if (!canvas) return;

    this.panorama = new window.google.maps.StreetViewPanorama(canvas, {
      pano: this.streetData.location && this.streetData.location.pano ? this.streetData.location.pano : undefined,
      position: { lat: this.lat, lng: this.lng },
      pov: {
        heading: toNumber(this.settings.street_heading, 0),
        pitch: toNumber(this.settings.street_pitch, 0)
      },
      zoom: toNumber(this.settings.street_zoom, 1),
      addressControl: true,
      fullscreenControl: this.settings.street_controls === true,
      motionTracking: false,
      zoomControl: this.settings.street_controls === true
    });

    this.state.streetViewInitialized = true;
    this.hideStreetFallback();
  };

  HomlityPropertyMap.prototype.loadStreetIframe = function () {
    var panel = this.root.querySelector('.property-map__panel[data-map-panel="street"]');
    if (!panel || this.state.iframeLoaded) return;

    var iframe = panel.querySelector('iframe[data-map-src]');
    if (!iframe) {
      this.showStreetFallback();
      return;
    }

    // Show the iframe immediately so the user sees it loading
    iframe.hidden = false;
    iframe.src = iframe.getAttribute('data-map-src') || '';
    this.state.iframeLoaded = true;

    var self = this;
    // Give the iframe enough time to load before showing the fallback
    var timeout = setTimeout(function () {
      // Only show fallback if the iframe hasn't loaded yet
      if (iframe.hidden === false && !self.state.iframeReady) {
        self.showStreetFallback();
      }
    }, 10000);

    iframe.addEventListener('load', function () {
      clearTimeout(timeout);
      self.state.iframeReady = true;
      // Ensure fallback is hidden and iframe is visible
      var fallback = self.root.querySelector('[data-map-street-fallback]');
      if (fallback) fallback.hidden = true;
      iframe.hidden = false;
    }, { once: true });
  };

  HomlityPropertyMap.prototype.showStreetFallback = function () {
    var fallback = this.root.querySelector('[data-map-street-fallback]');
    if (fallback) fallback.hidden = false;
    // Hide both canvas and iframe when showing the fallback
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
    // Show the appropriate element based on the mode
    var mode = (this.settings || {}).street_mode || 'google_js';
    if (mode === 'iframe') {
      var panel = this.root.querySelector('.property-map__panel[data-map-panel="street"]');
      if (panel) {
        var iframe = panel.querySelector('iframe[data-map-src]');
        if (iframe) iframe.hidden = false;
      }
    } else {
      var canvas = this.root.querySelector('.property-map__street-canvas');
      if (canvas) canvas.hidden = false;
    }
  };

  HomlityPropertyMap.prototype.resizeActiveMap = function () {
    var isMapActive = this.root.querySelector('.property-map__panel[data-map-panel="map"].is-active');
    var leaf = this.root.querySelector('.homlity-front-leaflet-map');
    if (isMapActive && leaf && leaf.__homlityLeafletMap && typeof leaf.__homlityLeafletMap.invalidateSize === 'function') {
      leaf.__homlityLeafletMap.invalidateSize();
    }

    if (this.panorama && typeof this.panorama.setPov === 'function') {
      var pov = this.panorama.getPov();
      this.panorama.setPov(pov);
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
