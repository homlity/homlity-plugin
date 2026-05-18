(function () {
    'use strict';

    function triggerSwiperUpdate(panel) {
        var gallery = panel.querySelector('[data-homlity-swiper-gallery]');
        if (!gallery) return;

        // If Swiper wasn't initialized yet (panel was hidden on load), init now
        if (gallery.dataset.swiperReady !== '1' && window.initHomlitySwiperGallery) {
            window.initHomlitySwiperGallery(gallery);
            return;
        }

        // Otherwise call update on existing Swiper instances
        var swipers = panel.querySelectorAll('.swiper');
        swipers.forEach(function (el) {
            if (el.swiper) {
                el.swiper.update();
            }
        });
    }

    function initTabs(container) {
        if (!container || container.dataset.tabsReady === '1') {
            return;
        }
        container.dataset.tabsReady = '1';

        var buttons = container.querySelectorAll('.property-gallery-tabs__btn');
        var panels  = container.querySelectorAll('.property-gallery-tabs__panel');

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = btn.dataset.tab;

                buttons.forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                    b.setAttribute('aria-selected', b === btn ? 'true' : 'false');
                });

                panels.forEach(function (panel) {
                    var isActive = panel.dataset.panel === target;
                    panel.classList.toggle('is-active', isActive);
                    if (isActive) {
                        triggerSwiperUpdate(panel);
                    }
                });
            });
        });
    }

    function init() {
        document.querySelectorAll('.property-gallery-tabs').forEach(initTabs);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Elementor editor re-init
    if (
        window.elementorFrontend &&
        window.elementorFrontend.hooks &&
        typeof window.elementorFrontend.hooks.addAction === 'function'
    ) {
        var reinitTabs = function ($el) {
            if ($el && $el[0]) {
                $el[0].querySelectorAll('.property-gallery-tabs').forEach(initTabs);
            }
        };
        window.elementorFrontend.hooks.addAction('frontend/element_ready/property_gallery.default', reinitTabs);
        window.elementorFrontend.hooks.addAction('frontend/element_ready/property_media_tabs.default', reinitTabs);
    }
})();
