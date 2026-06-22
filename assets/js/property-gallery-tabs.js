(function () {
    'use strict';

    function activateLazyMedia(panel) {
        if (!panel) return;

        panel.querySelectorAll('iframe[data-src]').forEach(function (iframe) {
            if (!iframe.getAttribute('src')) {
                iframe.setAttribute('src', iframe.getAttribute('data-src'));
            }
            iframe.removeAttribute('data-src');
        });
    }

    function initPannellumInPanel(panel) {
        if (!panel || !window.pannellum) return;

        panel.querySelectorAll('[data-homlity-panorama="1"]').forEach(function (node) {
            if (node.dataset.viewerReady === '1') return;
            var panorama = node.dataset.panoramaUrl || '';
            if (!panorama) return;

            try {
                window.pannellum.viewer(node, {
                    type: 'equirectangular',
                    panorama: panorama,
                    autoLoad: node.dataset.autoload !== '0',
                    showZoomCtrl: node.dataset.zoomCtrl !== '0',
                    showFullscreenCtrl: node.dataset.fullscreenCtrl !== '0',
                    hfov: Number(node.dataset.hfov || 100),
                    pitch: Number(node.dataset.pitch || 0),
                    yaw: Number(node.dataset.yaw || 0)
                });
                node.dataset.viewerReady = '1';
            } catch (e) {
                node.dataset.viewerReady = 'error';
            }
        });
    }

    function updatePanelMedia(panel) {
        if (!panel) return;

        activateLazyMedia(panel);
        initPannellumInPanel(panel);

        requestAnimationFrame(function () {
            panel.querySelectorAll('[data-homlity-swiper-gallery="1"]').forEach(function (gallery) {
                if (window.updateHomlitySwiperGallery) {
                    window.updateHomlitySwiperGallery(gallery);
                } else if (window.initHomlitySwiperGallery) {
                    window.initHomlitySwiperGallery(gallery);
                }
            });

            panel.querySelectorAll('.swiper').forEach(function (swiperEl) {
                if (swiperEl.swiper && typeof swiperEl.swiper.update === 'function') {
                    swiperEl.swiper.update();
                }
            });
        });
    }

    function setActive(container, target) {
        var buttons = container.querySelectorAll('.property-gallery-tabs__btn');
        var panels = container.querySelectorAll('.property-gallery-tabs__panel');

        buttons.forEach(function (btn) {
            var isActive = btn.dataset.tab === target;
            btn.classList.toggle('is-active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
            btn.setAttribute('tabindex', isActive ? '0' : '-1');
        });

        panels.forEach(function (panel) {
            var isActive = panel.dataset.panel === target;
            panel.classList.toggle('is-active', isActive);
            panel.hidden = !isActive;
            if (isActive) {
                updatePanelMedia(panel);
            }
        });
    }

    function initTabs(container) {
        if (!container || container.dataset.tabsReady === '1') return;
        container.dataset.tabsReady = '1';

        var buttons = Array.from(container.querySelectorAll('.property-gallery-tabs__btn'));
        if (!buttons.length) {
            container.querySelectorAll('.property-gallery-tabs__panel.is-active').forEach(updatePanelMedia);
            return;
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                setActive(container, btn.dataset.tab);
            });

            btn.addEventListener('keydown', function (event) {
                var key = event.key;
                var idx = buttons.indexOf(btn);
                var targetIdx = -1;

                if (key === 'ArrowRight') targetIdx = (idx + 1) % buttons.length;
                if (key === 'ArrowLeft') targetIdx = (idx - 1 + buttons.length) % buttons.length;
                if (key === 'Home') targetIdx = 0;
                if (key === 'End') targetIdx = buttons.length - 1;

                if (targetIdx >= 0) {
                    event.preventDefault();
                    buttons[targetIdx].focus();
                    return;
                }

                if (key === 'Enter' || key === ' ') {
                    event.preventDefault();
                    setActive(container, btn.dataset.tab);
                }
            });
        });

        var active = container.querySelector('.property-gallery-tabs__btn.is-active');
        if (active) {
            setActive(container, active.dataset.tab);
        }
    }

    function boot(scope) {
        var root = scope && scope.querySelectorAll ? scope : document;
        root.querySelectorAll('.property-gallery-tabs').forEach(initTabs);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { boot(document); });
    } else {
        boot(document);
    }

    if (window.elementorFrontend && window.elementorFrontend.hooks && typeof window.elementorFrontend.hooks.addAction === 'function') {
        var reinitTabs = function ($el) {
            boot($el && $el[0] ? $el[0] : document);
        };
        window.elementorFrontend.hooks.addAction('frontend/element_ready/property_gallery.default', reinitTabs);
        window.elementorFrontend.hooks.addAction('frontend/element_ready/property_media_tabs.default', reinitTabs);
    }
})();
