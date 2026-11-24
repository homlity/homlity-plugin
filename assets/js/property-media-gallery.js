/**
 * Galería de fotos del inmueble: Splide (carrusel + miniaturas) y PhotoSwipe (lightbox con zoom).
 *
 * Marcado que espera (templates/parts/property-gallery.php):
 * - [data-hml-carousel] raíz del carrusel. Dentro: .property-gallery__main (Splide principal),
 *   .property-gallery__thumbs opcional, .property-gallery__counter y .property-gallery__expand.
 * - [data-homlity-gallery="light"] mosaico; cada a.property-gallery__item--light abre el lightbox.
 *
 * Un carrusel dentro de una pestaña oculta mide 0 px, así que se monta cuando se vuelve visible.
 */
(function () {
    'use strict';

    var i18n = window.homlityMediaGalleryI18n || {};
    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var DEFAULT_SIZE = { width: 1600, height: 1067 };
    var sizeCache = {};
    var watcher = null;

    function int(value, fallback) {
        var n = parseInt(value, 10);
        return isNaN(n) ? fallback : n;
    }

    function isVisible(el) {
        return !!(el && el.getClientRects().length && el.offsetWidth > 0);
    }

    function cssGap(el, fallback) {
        try {
            var raw = window.getComputedStyle(el).getPropertyValue('--homlity-gallery-gap');
            var gap = parseFloat(String(raw || '').trim());
            return isNaN(gap) ? fallback : gap;
        } catch (e) {
            return fallback;
        }
    }

    function splideI18n() {
        var map = {};
        ['prev', 'next', 'first', 'last', 'slideX', 'pageX', 'play', 'pause', 'carousel', 'slideLabel'].forEach(function (key) {
            if (i18n[key]) map[key] = i18n[key];
        });
        return map;
    }

    /* ── Lightbox ────────────────────────────────────────────────────────── */

    function collectItems(links) {
        return links.map(function (link) {
            var img = link.querySelector('img');
            var width = int(link.getAttribute('data-pswp-width'), 0);
            var height = int(link.getAttribute('data-pswp-height'), 0);
            return {
                src: link.getAttribute('href'),
                msrc: img ? img.currentSrc || img.src : '',
                alt: img ? img.alt : '',
                width: width,
                height: height,
            };
        });
    }

    // Las fotos que vienen del CRM no traen medidas. Se usan las de la miniatura si ya cargó
    // (misma URL) o se descargan y se refresca la diapositiva al conocerlas.
    function resolveSize(item, index, getLightbox) {
        if (item.width && item.height) return;
        var cached = sizeCache[item.src];
        if (cached) {
            item.width = cached.width;
            item.height = cached.height;
            return;
        }
        item.width = DEFAULT_SIZE.width;
        item.height = DEFAULT_SIZE.height;
        var probe = new Image();
        probe.onload = function () {
            sizeCache[item.src] = { width: probe.naturalWidth, height: probe.naturalHeight };
            item.width = probe.naturalWidth;
            item.height = probe.naturalHeight;
            var lightbox = getLightbox();
            if (lightbox && lightbox.pswp) lightbox.pswp.refreshSlideContent(index);
        };
        probe.src = item.src;
    }

    function createLightbox(items, options) {
        if (!window.PhotoSwipeLightbox || !window.PhotoSwipe || !items.length) return null;

        var lightbox = new window.PhotoSwipeLightbox({
            dataSource: items,
            pswpModule: window.PhotoSwipe,
            bgOpacity: 0.97,
            loop: true,
            wheelToZoom: true,
            showHideAnimationType: reducedMotion ? 'none' : 'zoom',
            padding: { top: 48, bottom: 48, left: 16, right: 16 },
            closeTitle: i18n.close,
            zoomTitle: i18n.zoom,
            arrowPrevTitle: i18n.prev,
            arrowNextTitle: i18n.next,
            errorMsg: i18n.error,
            mainClass: 'hml-pswp',
        });

        lightbox.addFilter('itemData', function (itemData, index) {
            resolveSize(itemData, index, function () { return lightbox; });
            return itemData;
        });

        // La animación de zoom sale de la foto visible en el carrusel.
        lightbox.addFilter('thumbEl', function (thumbEl, data, index) {
            return (options.thumbFor && options.thumbFor(index)) || thumbEl;
        });
        lightbox.addFilter('placeholderSrc', function (src, slide) {
            return slide.data.msrc || src;
        });

        if (options.onChange) {
            lightbox.on('change', function () { options.onChange(lightbox.pswp.currIndex); });
        }
        if (options.onOpen) lightbox.on('beforeOpen', options.onOpen);
        if (options.onClose) lightbox.on('close', options.onClose);

        lightbox.init();
        return lightbox;
    }

    /* ── Carrusel ────────────────────────────────────────────────────────── */

    function mountCarousel(root) {
        if (root.dataset.hmlReady === '1' || !window.Splide) return;
        var mainEl = root.querySelector('.property-gallery__main');
        if (!mainEl) return;

        root.dataset.hmlReady = '1';

        var kind = root.dataset.kind || 'photos';
        var count = mainEl.querySelectorAll('.splide__slide').length;
        var desktop = int(root.dataset.perPageDesktop, 1);
        var tablet = int(root.dataset.perPageTablet, desktop);
        var mobile = int(root.dataset.perPageMobile, 1);
        var maxPerPage = Math.max(desktop, tablet, mobile);
        var loop = root.dataset.loop === '1' && count > maxPerPage;
        var autoplay = root.dataset.autoplay === '1' && count > 1 && !reducedMotion && kind !== 'embed';
        var arrows = root.dataset.arrows !== '0' && count > desktop;
        var pagination = root.dataset.pagination !== '0' && count > 1 && count <= 10;
        var speed = reducedMotion ? 0 : int(root.dataset.speed, 520);

        // Los enlaces se leen antes de montar: Splide clona las diapositivas en modo loop.
        var links = Array.prototype.slice.call(mainEl.querySelectorAll('.property-gallery__slide-link'));
        var items = root.dataset.lightbox === '1' ? collectItems(links) : [];

        var main = new window.Splide(mainEl, {
            type: loop ? 'loop' : 'slide',
            rewind: !loop,
            perPage: desktop,
            perMove: 1,
            gap: cssGap(root, 12),
            speed: speed,
            easing: 'cubic-bezier(0.25, 1, 0.5, 1)',
            arrows: arrows,
            pagination: pagination,
            autoplay: autoplay,
            interval: 4500,
            pauseOnHover: true,
            pauseOnFocus: true,
            keyboard: 'focused',
            drag: kind === 'embed' ? false : (kind === 'masonry' ? 'free' : true),
            snap: kind === 'masonry',
            lazyLoad: false,
            breakpoints: {
                1023: { perPage: tablet, arrows: root.dataset.arrows !== '0' && count > tablet },
                767: { perPage: mobile, arrows: root.dataset.arrows !== '0' && count > mobile },
            },
            i18n: splideI18n(),
        });

        var thumbsEl = root.querySelector('.property-gallery__thumbs');
        var thumbs = null;
        if (thumbsEl && count > 1) {
            var perView = Math.max(2, int(root.dataset.thumbsPerView, 5));
            thumbs = new window.Splide(thumbsEl, {
                isNavigation: true,
                perPage: perView,
                perMove: 1,
                gap: 10,
                rewind: true,
                pagination: false,
                arrows: count > perView,
                focus: 'center',
                trimSpace: true,
                speed: speed,
                keyboard: false,
                updateOnMove: true,
                breakpoints: {
                    1023: { perPage: Math.max(3, Math.min(perView, 5)), arrows: false },
                    767: { perPage: Math.max(3, Math.min(perView, 4)), arrows: false },
                },
                i18n: splideI18n(),
            });
            main.sync(thumbs);
        } else if (thumbsEl) {
            thumbsEl.hidden = true;
        }

        var counter = root.querySelector('.property-gallery__counter');
        function updateCounter() {
            if (counter) counter.textContent = (main.index + 1) + ' / ' + count;
        }
        main.on('mounted move', updateCounter);

        main.mount();
        if (thumbs) thumbs.mount();
        if (counter && count < 2) counter.hidden = true;

        var lightbox = null;
        function openAt(index) {
            if (!items.length) return;
            if (!lightbox) {
                lightbox = createLightbox(items, {
                    thumbFor: function (index) {
                        var slide = main.Components.Slides.getAt(index);
                        return slide ? slide.slide.querySelector('img') : null;
                    },
                    onChange: function (index) { if (main.index !== index) main.go(index); },
                    onOpen: function () {
                        var autoplayer = main.Components.Autoplay;
                        if (autoplayer) autoplayer.pause();
                    },
                    onClose: function () {
                        var autoplayer = main.Components.Autoplay;
                        if (autoplayer && autoplay) autoplayer.play();
                    },
                });
            }
            if (lightbox) lightbox.loadAndOpen(index);
        }

        if (items.length) {
            mainEl.addEventListener('click', function (event) {
                var link = event.target.closest('.property-gallery__slide-link');
                if (!link) return;
                event.preventDefault();
                openAt(int(link.getAttribute('data-index'), 0));
            });
            var expand = root.querySelector('.property-gallery__expand');
            if (expand) {
                expand.addEventListener('click', function () { openAt(main.index); });
            }
        } else {
            var expandBtn = root.querySelector('.property-gallery__expand');
            if (expandBtn) expandBtn.hidden = true;
        }

        root._hmlSplide = main;
        root._hmlThumbs = thumbs;
    }

    /* ── Mosaico ─────────────────────────────────────────────────────────── */

    function mountMosaic(root) {
        if (root.dataset.hmlReady === '1') return;
        var links = Array.prototype.slice.call(root.querySelectorAll('.property-gallery__item--light'));
        if (!links.length) return;
        root.dataset.hmlReady = '1';

        var items = collectItems(links);
        var lightbox = null;
        links.forEach(function (link, index) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                if (!lightbox) {
                    lightbox = createLightbox(items, {
                        thumbFor: function (i) { return links[i] ? links[i].querySelector('img') : null; },
                    });
                }
                if (lightbox) lightbox.loadAndOpen(index);
            });
        });
    }

    /* ── Arranque ────────────────────────────────────────────────────────── */

    function whenVisible(root) {
        if (isVisible(root)) {
            mountCarousel(root);
            return;
        }
        if (!('ResizeObserver' in window)) return;
        if (!watcher) {
            watcher = new ResizeObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (isVisible(entry.target)) {
                        watcher.unobserve(entry.target);
                        mountCarousel(entry.target);
                    }
                });
            });
        }
        watcher.observe(root);
    }

    function boot(scope) {
        var root = scope && scope.querySelectorAll ? scope : document;
        root.querySelectorAll('[data-hml-carousel]').forEach(function (node) {
            if (node.dataset.hmlReady === '1') {
                if (node._hmlSplide) node._hmlSplide.refresh();
                if (node._hmlThumbs) node._hmlThumbs.refresh();
                return;
            }
            whenVisible(node);
        });
        root.querySelectorAll('[data-homlity-gallery="light"]').forEach(mountMosaic);
    }

    window.homlityMediaGallery = { boot: boot, refresh: boot };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { boot(document); });
    } else {
        boot(document);
    }

    function hookElementor() {
        if (!window.elementorFrontend || !window.elementorFrontend.hooks) return;
        ['property_gallery', 'property_media_tabs'].forEach(function (name) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/' + name + '.default', function ($scope) {
                boot($scope && $scope[0] ? $scope[0] : document);
            });
        });
    }
    if (window.elementorFrontend && window.elementorFrontend.hooks) {
        hookElementor();
    } else if (window.jQuery) {
        window.jQuery(window).on('elementor/frontend/init', hookElementor);
    }
})();
