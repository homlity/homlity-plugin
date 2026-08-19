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
        this.presetTagIds     = el.dataset.presetTagIds || '';
        this.presetFeature    = el.dataset.presetFeature || '';
        this.presetCountry    = el.dataset.presetCountry || '';
        this.presetState      = el.dataset.presetState || '';
        this.presetCity       = el.dataset.presetCity || '';
        this.presetLocality   = el.dataset.presetLocality || '';
        this.presetNeighborhood = el.dataset.presetNeighborhood || '';
        this.presetNearby     = el.dataset.presetNearby || '';
        this.presetAgent      = el.dataset.presetAgent || '';
        this.geoLatitude      = el.dataset.geoLatitude || '';
        this.geoLongitude     = el.dataset.geoLongitude || '';
        this.geoRadiusKm      = el.dataset.geoRadiusKm || '';
        this.priceMin         = el.dataset.priceMin || '';
        this.priceMax         = el.dataset.priceMax || '';
        this.bedrooms         = el.dataset.bedrooms || '';
        this.bathrooms        = el.dataset.bathrooms || '';
        this.parking          = el.dataset.parking || '';
        this.areaMin          = el.dataset.areaMin || '';
        this.areaMax          = el.dataset.areaMax || '';
        this.category         = el.dataset.category || '';
        this.operation        = el.dataset.operation || '';
        this.type             = el.dataset.type || '';
        this.tag              = el.dataset.tag || '';
        this.feature          = el.dataset.feature || '';
        this.country          = el.dataset.country || '';
        this.state            = el.dataset.state || '';
        this.city             = el.dataset.city || '';
        this.locality         = el.dataset.locality || '';
        this.neighborhood     = el.dataset.neighborhood || '';
        this.nearby           = el.dataset.nearby || '';
        this.cardMediaMode    = el.dataset.cardMediaMode || 'single';
        this.cardVisualPreset = el.dataset.cardVisualPreset || 'default';
        this.cardHoverEffect  = el.dataset.cardHoverEffect || 'lift';
        this.cardShowTitle    = el.dataset.cardShowTitle || '1';
        this.cardShowExcerpt  = el.dataset.cardShowExcerpt || '1';
        this.cardShowOperation = el.dataset.cardShowOperation || '1';
        this.cardShowPrice    = el.dataset.cardShowPrice || '1';
        this.cardShowFeatures = el.dataset.cardShowFeatures || '1';
        this.cardShowWhatsapp = el.dataset.cardShowWhatsapp || '1';
        this.cardWhatsappLabel = el.dataset.cardWhatsappLabel || '';
        this.cardWhatsappShowIcon = el.dataset.cardWhatsappShowIcon || '1';
        this.cardWhatsappIconPosition = el.dataset.cardWhatsappIconPosition || 'left';
        this.cardWhatsappIcon = el.dataset.cardWhatsappIcon || '{}';
        this.cardFeatureArea = el.dataset.cardFeatureArea || '1';
        this.cardFeatureBedrooms = el.dataset.cardFeatureBedrooms || '1';
        this.cardFeatureBathrooms = el.dataset.cardFeatureBathrooms || '1';
        this.cardFeatureParking = el.dataset.cardFeatureParking || '1';
        this.cardFeatureAreaLot = el.dataset.cardFeatureAreaLot || '1';
        this.cardFeatureAreaPrivate = el.dataset.cardFeatureAreaPrivate || '1';
        this.cardFeatureAreaBuilt = el.dataset.cardFeatureAreaBuilt || '1';
        this.cardFeatureAge = el.dataset.cardFeatureAge || '1';
        this.cardFeatureCondition = el.dataset.cardFeatureCondition || '1';
        this.cardFeatureCode = el.dataset.cardFeatureCode || '1';
        this.cardFeatureIconArea = el.dataset.cardFeatureIconArea || 'grid';
        this.cardFeatureIconBedrooms = el.dataset.cardFeatureIconBedrooms || 'bed';
        this.cardFeatureIconBathrooms = el.dataset.cardFeatureIconBathrooms || 'bath';
        this.cardFeatureIconParking = el.dataset.cardFeatureIconParking || 'car';
        this.cardFeatureIconAreaLot = el.dataset.cardFeatureIconAreaLot || 'lot';
        this.cardFeatureIconAreaPrivate = el.dataset.cardFeatureIconAreaPrivate || 'home';
        this.cardFeatureIconAreaBuilt = el.dataset.cardFeatureIconAreaBuilt || 'ruler';
        this.cardFeatureIconAge = el.dataset.cardFeatureIconAge || 'clock';
        this.cardFeatureIconCondition = el.dataset.cardFeatureIconCondition || 'diamond';
        this.cardFeatureIconCode = el.dataset.cardFeatureIconCode || 'hash';
        this.listTabId        = el.dataset.listTabId || '';
        this.mapTabId         = el.dataset.mapTabId || '';
        this.currentPage      = parseInt(el.dataset.currentPage || '1', 10) || 1;
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
        this.gridContainers = Array.prototype.slice.call(el.querySelectorAll('.property-listing__grid'));
        this.mapContainers = Array.prototype.slice.call(el.querySelectorAll('.property-listing__map-container'));
        this.listTab     = this.listTabId ? el.querySelector('#' + this.listTabId) : null;
        this.mapTab      = this.mapTabId ? el.querySelector('#' + this.mapTabId) : null;
        this.mapEl       = el.querySelector('[id$="-map"]');
        this.countEl     = el.querySelector('.property-listing__count-number');
        this.pagination  = el.querySelector('.property-listing__pagination');
        this.form        = el.querySelector('.property-listing__filters');
        this.sortSelect  = el.querySelector('.property-listing__sort');
        this.viewToggle  = el.querySelector('.property-listing__view-toggle');
        this.resetBtn    = el.querySelector('.property-listing__filter-reset');
        this.overlay     = el.querySelector('.property-listing__overlay');

        this._bindEvents();
        this._initCardSwipers();

        if (this.view === 'map') {
            this._initMap(this.mapData);
        }
    }

    PropertyListing.prototype._setElementVisible = function (el, visible) {
        if (!el) return;
        if (visible) {
            el.removeAttribute('hidden');
            el.style.display = '';
        } else {
            el.setAttribute('hidden', '');
            el.style.display = 'none';
        }
    };

    PropertyListing.prototype._initCardSwipers = function () {
        if (typeof window.Swiper === 'undefined') {
            return;
        }

        var sliders = this.el.querySelectorAll('[data-homlity-card-swiper="1"]');
        sliders.forEach(function (sliderNode) {
            if (sliderNode.dataset.swiperReady === '1') {
                return;
            }

            var paginationEl = sliderNode.querySelector('.swiper-pagination');
            var nextEl = sliderNode.querySelector('.swiper-button-next');
            var prevEl = sliderNode.querySelector('.swiper-button-prev');

            var swiper = new window.Swiper(sliderNode, {
                slidesPerView: 1,
                spaceBetween: 0,
                loop: true,
                speed: 420,
                watchOverflow: true,
                observer: true,
                observeParents: true,
                allowTouchMove: true,
                grabCursor: true,
                pagination: paginationEl ? { el: paginationEl, clickable: true } : undefined,
                navigation: (nextEl && prevEl) ? { nextEl: nextEl, prevEl: prevEl } : undefined
            });

            // Slider is nested inside a card link; prevent accidental navigation
            // when interacting with slider controls.
            sliderNode.addEventListener('click', function (e) {
                if (e.target.closest('.swiper-button-next, .swiper-button-prev, .swiper-pagination, .swiper-pagination-bullet')) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });

            var moved = false;
            swiper.on('touchMove', function () {
                moved = true;
            });
            swiper.on('sliderFirstMove', function () {
                moved = true;
            });
            sliderNode.addEventListener('click', function (e) {
                if (moved) {
                    e.preventDefault();
                    e.stopPropagation();
                    moved = false;
                }
            }, true);

            sliderNode.dataset.swiperReady = '1';
        });
    };

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
                if (!btn || btn.disabled) return;
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
        var isBootstrapTabs = !!(this.listTab && this.mapTab);

        if (view === 'grid') {
            if (isBootstrapTabs) {
                this.listTab.classList.add('show', 'active');
                this.mapTab.classList.remove('show', 'active');
            } else {
                this.gridContainers.forEach(function (node) {
                    this._setElementVisible(node, true);
                }, this);
                this.mapContainers.forEach(function (node) {
                    this._setElementVisible(node, false);
                }, this);
            }
            if (gridBtn) gridBtn.classList.add('is-active');
            if (mapBtn)  mapBtn.classList.remove('is-active');
        } else {
            if (isBootstrapTabs) {
                this.mapTab.classList.add('show', 'active');
                this.listTab.classList.remove('show', 'active');
            } else {
                this.gridContainers.forEach(function (node) {
                    this._setElementVisible(node, false);
                }, this);
                this.mapContainers.forEach(function (node) {
                    this._setElementVisible(node, true);
                }, this);
            }
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
            preset_tag_ids:    this.presetTagIds,
            preset_feature:    this.presetFeature,
            preset_country:    this.presetCountry,
            preset_state:      this.presetState,
            preset_city:       this.presetCity,
            preset_locality:   this.presetLocality,
            preset_neighborhood: this.presetNeighborhood,
            preset_nearby:     this.presetNearby,
            preset_agent:      this.presetAgent,
            geo_latitude:      this.geoLatitude,
            geo_longitude:     this.geoLongitude,
            geo_radius_km:     this.geoRadiusKm,
            price_min:         this.priceMin,
            price_max:         this.priceMax,
            bedrooms:          this.bedrooms,
            bathrooms:         this.bathrooms,
            parking:           this.parking,
            area_min:          this.areaMin,
            area_max:          this.areaMax,
            category:          this.category,
            operation:         this.operation,
            type:              this.type,
            tag:               this.tag,
            feature:           this.feature,
            country:           this.country,
            state:             this.state,
            city:              this.city,
            locality:          this.locality,
            neighborhood:      this.neighborhood,
            nearby:            this.nearby,
            card_media_mode:   this.cardMediaMode,
            card_visual_preset: this.cardVisualPreset,
            card_hover_effect: this.cardHoverEffect,
            card_show_title:   this.cardShowTitle,
            card_show_excerpt: this.cardShowExcerpt,
            card_show_operation: this.cardShowOperation,
            card_show_price:   this.cardShowPrice,
            card_show_features: this.cardShowFeatures,
            card_show_whatsapp: this.cardShowWhatsapp,
            card_whatsapp_label: this.cardWhatsappLabel,
            card_whatsapp_show_icon: this.cardWhatsappShowIcon,
            card_whatsapp_icon_position: this.cardWhatsappIconPosition,
            card_whatsapp_icon: this.cardWhatsappIcon,
            card_feature_area: this.cardFeatureArea,
            card_feature_bedrooms: this.cardFeatureBedrooms,
            card_feature_bathrooms: this.cardFeatureBathrooms,
            card_feature_parking: this.cardFeatureParking,
            card_feature_area_lot: this.cardFeatureAreaLot,
            card_feature_area_private: this.cardFeatureAreaPrivate,
            card_feature_area_built: this.cardFeatureAreaBuilt,
            card_feature_age: this.cardFeatureAge,
            card_feature_condition: this.cardFeatureCondition,
            card_feature_code: this.cardFeatureCode,
            card_feature_icon_area: this.cardFeatureIconArea,
            card_feature_icon_bedrooms: this.cardFeatureIconBedrooms,
            card_feature_icon_bathrooms: this.cardFeatureIconBathrooms,
            card_feature_icon_parking: this.cardFeatureIconParking,
            card_feature_icon_area_lot: this.cardFeatureIconAreaLot,
            card_feature_icon_area_private: this.cardFeatureIconAreaPrivate,
            card_feature_icon_area_built: this.cardFeatureIconAreaBuilt,
            card_feature_icon_age: this.cardFeatureIconAge,
            card_feature_icon_condition: this.cardFeatureIconCondition,
            card_feature_icon_code: this.cardFeatureIconCode,
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
                        '<p class="property-listing__empty">' + (i18n.noResults || 'No se han encontrado inmuebles para esta consulta.') + '</p>';
                    self._initCardSwipers();
                }

                if (self.countEl) {
                    self.countEl.textContent = d.total;
                }

                self.mapData = d.map_data || [];
                self._updateMap(self.mapData);
                self._updatePagination(d.pages);
                self._syncBrowserUrl(orderby);
            })
            .catch(function () {})
            .finally(function () {
                self._setLoading(false);
            });
    };

    PropertyListing.prototype._syncBrowserUrl = function (orderby) {
        if (!window.history || typeof window.history.replaceState !== 'function') {
            return;
        }

        var url = new URL(window.location.href);
        var defaultOrder = this.el.dataset.defaultOrder || 'date';

        if (orderby && orderby !== defaultOrder) {
            url.searchParams.set('orden', orderby);
        } else {
            url.searchParams.delete('orden');
        }

        if (this.currentPage > 1) {
            url.searchParams.set('pagina', String(this.currentPage));
        } else {
            url.searchParams.delete('pagina');
        }

        window.history.replaceState(window.history.state, '', url.toString());
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
        this.pagination.dataset.current = String(this.currentPage);
        this.pagination.dataset.pages = String(pages);
        this.pagination.setAttribute('aria-label', i18n.paginationLabel || 'Paginación de inmuebles');

        var current = Math.min(pages, Math.max(1, this.currentPage));
        var radius = 2;
        var windowSize = (radius * 2) + 1;
        var start = Math.max(1, current - radius);
        var end = Math.min(pages, current + radius);
        if (start === 1) {
            end = Math.min(pages, windowSize);
        } else if (end === pages) {
            start = Math.max(1, pages - windowSize + 1);
        }

        var edgeButton = function (page, label, ariaLabel, symbol, symbolAfter, disabled) {
            return '<button type="button" class="property-listing__page-btn property-listing__page-btn--edge"' +
                ' data-page="' + page + '" aria-label="' + ariaLabel + '"' + (disabled ? ' disabled' : '') + '>' +
                (symbolAfter ? '' : '<span aria-hidden="true">' + symbol + '</span>') +
                '<span class="property-listing__page-label">' + label + '</span>' +
                (symbolAfter ? '<span aria-hidden="true">' + symbol + '</span>' : '') +
                '</button>';
        };

        var html = edgeButton(1, i18n.first || 'Inicio', i18n.firstAria || 'Ir al inicio', '«', false, current === 1);
        html += edgeButton(Math.max(1, current - 1), i18n.previous || 'Anterior', i18n.previousAria || 'Página anterior', '‹', false, current === 1);
        if (start > 1) {
            html += '<span class="property-listing__page-ellipsis" aria-hidden="true">…</span>';
        }
        for (var page = start; page <= end; page++) {
            var pageAria = (i18n.pageAria || 'Página %d').replace('%d', String(page));
            html += '<button type="button" class="property-listing__page-btn' +
                (page === current ? ' is-active' : '') + '" data-page="' + page + '" aria-label="' + pageAria + '"' +
                (page === current ? ' aria-current="page"' : '') + '>' + page + '</button>';
        }
        if (end < pages) {
            html += '<span class="property-listing__page-ellipsis" aria-hidden="true">…</span>';
        }
        html += edgeButton(Math.min(pages, current + 1), i18n.next || 'Siguiente', i18n.nextAria || 'Página siguiente', '›', true, current === pages);
        html += edgeButton(pages, i18n.last || 'Final', i18n.lastAria || 'Ir al final', '»', true, current === pages);
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
    function bindElementorHook() {
        var ef = window.elementorFrontend;
        if (!ef) {
            return;
        }
        var hooks = ef.hooks;
        if (!hooks || typeof hooks.addAction !== 'function') {
            return;
        }
        hooks.addAction(
            'frontend/element_ready/property_listing.default',
            function ($scope) {
                var scopeNode = $scope && $scope[0] ? $scope[0] : document;
                scopeNode.querySelectorAll('.property-listing').forEach(function (el) {
                    if (!el.dataset.hlInit) {
                        el.dataset.hlInit = '1';
                        new PropertyListing(el);
                    }
                });
            }
        );
    }

    bindElementorHook();
    window.addEventListener('elementor/frontend/init', bindElementorHook);
})();
