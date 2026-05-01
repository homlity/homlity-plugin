/* global L, homlityListingI18n */
(function () {
    'use strict';

    var i18n = window.homlityListingI18n || {};

    function PropertyListing(el) {
        this.el               = el;
        this.ajaxUrl          = el.dataset.ajaxUrl;
        this.nonce            = el.dataset.nonce;
        this.perPage          = parseInt(el.dataset.perPage, 10) || 12;
        this.columns          = parseInt(el.dataset.columns, 10) || 3;
        this.mapZoom          = parseInt(el.dataset.mapZoom, 10) || 12;
        this.template         = el.dataset.template || 'default';
        this.queryMode        = el.dataset.queryMode || 'custom';
        this.search           = el.dataset.search || '';
        this.featured         = el.dataset.featured || '';
        this.presetCategory   = el.dataset.presetCategory || '';
        this.presetOperation  = el.dataset.presetOperation || '';
        this.presetType       = el.dataset.presetType || '';
        this.presetTag        = el.dataset.presetTag || '';
        this.presetFeature    = el.dataset.presetFeature || '';
        this.presetCountry    = el.dataset.presetCountry || '';
        this.presetState      = el.dataset.presetState || '';
        this.presetCity       = el.dataset.presetCity || '';
        this.presetNeighborhood = el.dataset.presetNeighborhood || '';
        this.presetNearby     = el.dataset.presetNearby || '';
        this.geoLatitude      = el.dataset.geoLatitude || '';
        this.geoLongitude     = el.dataset.geoLongitude || '';
        this.geoRadiusKm      = el.dataset.geoRadiusKm || '';
        this.priceMin         = el.dataset.priceMin || '';
        this.priceMax         = el.dataset.priceMax || '';
        this.bedrooms         = el.dataset.bedrooms || '';
        this.bathrooms        = el.dataset.bathrooms || '';
        this.currentPage      = 1;
        this.view             = el.dataset.view || 'grid';
        this.mapInstance      = null;
        this.mapMarkers       = [];

        try {
            this.mapData = JSON.parse(el.dataset.mapData || '[]');
        } catch (e) {
            this.mapData = [];
        }

        this.grid        = el.querySelector('.property-listing__grid');
        this.mapContainer = el.querySelector('.property-listing__map-container');
        this.mapEl       = el.querySelector('[id$="-map"]');
        this.countEl     = el.querySelector('.property-listing__count-number');
        this.pagination  = el.querySelector('.property-listing__pagination');
        this.form        = el.querySelector('.property-listing__filters');
        this.sortSelect  = el.querySelector('.property-listing__sort');
        this.viewToggle  = el.querySelector('.property-listing__view-toggle');
        this.resetBtn    = el.querySelector('.property-listing__filter-reset');
        this.overlay     = el.querySelector('.property-listing__overlay');

        this._bindEvents();

        if (this.view === 'map') {
            this._initMap(this.mapData);
        }
    }

    PropertyListing.prototype._bindEvents = function () {
        var self = this;

        if (this.form) {
            this.form.addEventListener('submit', function (e) {
                e.preventDefault();
                self.currentPage = 1;
                self._fetch();
            });
        }

        if (this.resetBtn) {
            this.resetBtn.addEventListener('click', function () {
                if (self.form) {
                    self.form.reset();
                }
                self.currentPage = 1;
                self._fetch();
            });
        }

        if (this.sortSelect) {
            this.sortSelect.addEventListener('change', function () {
                self.currentPage = 1;
                self._fetch();
            });
        }

        if (this.viewToggle) {
            this.viewToggle.addEventListener('click', function (e) {
                var btn = e.target.closest('.property-listing__view-btn');
                if (btn) {
                    self._setView(btn.dataset.view);
                }
            });
        }

        if (this.pagination) {
            this.pagination.addEventListener('click', function (e) {
                var btn = e.target.closest('.property-listing__page-btn');
                if (!btn) return;
                self.currentPage = parseInt(btn.dataset.page, 10);
                self._fetch();
                self.el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
    };

    PropertyListing.prototype._setView = function (view) {
        this.view = view;
        this.el.dataset.view = view;

        var gridBtn = this.el.querySelector('.property-listing__view-btn--grid');
        var mapBtn  = this.el.querySelector('.property-listing__view-btn--map');

        if (view === 'grid') {
            if (this.grid)         this.grid.removeAttribute('hidden');
            if (this.mapContainer) this.mapContainer.setAttribute('hidden', '');
            if (gridBtn) gridBtn.classList.add('is-active');
            if (mapBtn)  mapBtn.classList.remove('is-active');
        } else {
            if (this.grid)         this.grid.setAttribute('hidden', '');
            if (this.mapContainer) this.mapContainer.removeAttribute('hidden');
            if (gridBtn) gridBtn.classList.remove('is-active');
            if (mapBtn)  mapBtn.classList.add('is-active');

            if (!this.mapInstance) {
                this._initMap(this.mapData);
            } else {
                var self = this;
                setTimeout(function () { self.mapInstance.invalidateSize(); }, 100);
            }
        }
    };

    PropertyListing.prototype._getFormParams = function () {
        var params = {};
        if (!this.form) return params;

        var data = new FormData(this.form);
        data.forEach(function (value, key) {
            if (value !== '') params[key] = value;
        });

        return params;
    };

    PropertyListing.prototype._fetch = function () {
        var self = this;
        var formParams = this._getFormParams();
        var orderby = this.sortSelect ? this.sortSelect.value : 'date';

        var body = new URLSearchParams(Object.assign({
            action:            'homlity_listing',
            nonce:             this.nonce,
            per_page:          this.perPage,
            page:              this.currentPage,
            orderby:           orderby,
            query_mode:        this.queryMode,
            template:          this.template,
            search:            this.search,
            featured:          this.featured,
            preset_category:   this.presetCategory,
            preset_operation:  this.presetOperation,
            preset_type:       this.presetType,
            preset_tag:        this.presetTag,
            preset_feature:    this.presetFeature,
            preset_country:    this.presetCountry,
            preset_state:      this.presetState,
            preset_city:       this.presetCity,
            preset_neighborhood: this.presetNeighborhood,
            preset_nearby:     this.presetNearby,
            geo_latitude:      this.geoLatitude,
            geo_longitude:     this.geoLongitude,
            geo_radius_km:     this.geoRadiusKm,
            price_min:         this.priceMin,
            price_max:         this.priceMax,
            bedrooms:          this.bedrooms,
            bathrooms:         this.bathrooms,
        }, formParams));

        this._setLoading(true);

        fetch(this.ajaxUrl, {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    body.toString(),
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) return;
                var d = res.data;

                if (self.grid) {
                    self.grid.innerHTML = d.html ||
                        '<p class="property-listing__empty">' + (i18n.noResults || 'No se encontraron inmuebles.') + '</p>';
                }

                if (self.countEl) {
                    self.countEl.textContent = d.total;
                }

                self.mapData = d.map_data || [];
                self._updateMap(self.mapData);
                self._updatePagination(d.pages);
            })
            .catch(function () {})
            .finally(function () {
                self._setLoading(false);
            });
    };

    PropertyListing.prototype._setLoading = function (loading) {
        if (loading) {
            this.el.classList.add('property-listing--loading');
            if (this.overlay) this.overlay.removeAttribute('hidden');
        } else {
            this.el.classList.remove('property-listing--loading');
            if (this.overlay) this.overlay.setAttribute('hidden', '');
        }
    };

    PropertyListing.prototype._updatePagination = function (pages) {
        if (!this.pagination) return;

        if (!pages || pages <= 1) {
            this.pagination.hidden = true;
            return;
        }

        this.pagination.hidden = false;
        var html = '';
        for (var i = 1; i <= pages; i++) {
            html += '<button type="button" class="property-listing__page-btn' +
                (i === this.currentPage ? ' is-active' : '') +
                '" data-page="' + i + '">' + i + '</button>';
        }
        this.pagination.innerHTML = html;
    };

    PropertyListing.prototype._initMap = function (properties) {
        if (!this.mapEl || typeof L === 'undefined') return;

        if (this.mapInstance) {
            this.mapMarkers.forEach(function (m) { this.mapInstance.removeLayer(m); }, this);
            this.mapMarkers = [];
            this.mapInstance.remove();
            this.mapInstance = null;
        }

        var withCoords = properties.filter(function (p) { return p.lat && p.lng; });
        var center     = [4.5709, -74.2973];

        if (withCoords.length > 0) {
            center = [withCoords[0].lat, withCoords[0].lng];
        }

        this.mapInstance = L.map(this.mapEl).setView(center, this.mapZoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        }).addTo(this.mapInstance);

        this._addMarkers(withCoords);

        if (withCoords.length > 1 && this.mapMarkers.length > 1) {
            var group = L.featureGroup(this.mapMarkers);
            this.mapInstance.fitBounds(group.getBounds().pad(0.1));
        }
    };

    PropertyListing.prototype._addMarkers = function (properties) {
        var self = this;

        properties.forEach(function (p) {
            var featuresHtml = '';
            if (p.bedrooms)  featuresHtml += '<span>' + p.bedrooms + ' hab.</span>';
            if (p.bathrooms) featuresHtml += '<span>' + p.bathrooms + ' ba&#241;.</span>';
            if (p.area)      featuresHtml += '<span>' + p.area + ' m&#178;</span>';

            var popup =
                '<div class="homlity-map-popup">' +
                    (p.thumbnail ? '<img src="' + p.thumbnail + '" alt="" class="homlity-map-popup__img">' : '') +
                    '<a href="' + p.permalink + '" class="homlity-map-popup__title">' + p.title + '</a>' +
                    (p.price ? '<span class="homlity-map-popup__price">' + p.price + '</span>' : '') +
                    (featuresHtml ? '<div class="homlity-map-popup__features">' + featuresHtml + '</div>' : '') +
                '</div>';

            var marker = L.marker([p.lat, p.lng])
                .addTo(self.mapInstance)
                .bindPopup(popup);

            self.mapMarkers.push(marker);
        });
    };

    PropertyListing.prototype._updateMap = function (properties) {
        if (!this.mapInstance) {
            if (this.view === 'map') {
                this._initMap(properties);
            }
            return;
        }

        this.mapMarkers.forEach(function (m) { this.mapInstance.removeLayer(m); }, this);
        this.mapMarkers = [];

        var withCoords = properties.filter(function (p) { return p.lat && p.lng; });
        this._addMarkers(withCoords);

        if (withCoords.length > 1 && this.mapMarkers.length > 1) {
            var group = L.featureGroup(this.mapMarkers);
            this.mapInstance.fitBounds(group.getBounds().pad(0.1));
        }
    };

    // ── Bootstrap ─────────────────────────────────────────────────────────────

    function initAll() {
        document.querySelectorAll('.property-listing').forEach(function (el) {
            if (!el.dataset.hlInit) {
                el.dataset.hlInit = '1';
                new PropertyListing(el);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    // Re-init when Elementor renders a widget in the editor
    if (window.elementorFrontend) {
        window.elementorFrontend.hooks.addAction(
            'frontend/element_ready/property_listing.default',
            function ($scope) {
                $scope[0].querySelectorAll('.property-listing').forEach(function (el) {
                    if (!el.dataset.hlInit) {
                        el.dataset.hlInit = '1';
                        new PropertyListing(el);
                    }
                });
            }
        );
    }
})();
