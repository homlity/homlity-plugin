(function () {
  function initMapTabs(tabsWrap) {
    var root = tabsWrap.closest('.property-map');
    if (!root || tabsWrap.dataset.mapTabsReady === '1') return;
    tabsWrap.dataset.mapTabsReady = '1';

    var tabs   = root.querySelectorAll('.property-map__tab[data-map-tab]');
    var panels = root.querySelectorAll('.property-map__panel[data-map-panel]');

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var target = tab.getAttribute('data-map-tab') || '';
        tabs.forEach(function (btn) {
          var active = btn === tab;
          btn.classList.toggle('is-active', active);
          btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach(function (panel) {
          var active = panel.getAttribute('data-map-panel') === target;
          panel.classList.toggle('is-active', active);
          panel.hidden = !active;
        });
        if (target === 'street') {
          var streetPanel = root.querySelector('.property-map__panel[data-map-panel="street"]');
          if (streetPanel) {
            var streetFrame = streetPanel.querySelector('iframe[data-map-src]');
            var fallbackWrap = streetPanel.querySelector('[data-map-street-fallback]');
            var fallbackUrl = streetPanel.getAttribute('data-map-fallback-url') || '';
            if (fallbackWrap) {
              fallbackWrap.hidden = true;
            }
            if (streetFrame && (!streetFrame.src || streetFrame.src === 'about:blank')) {
              streetFrame.src = streetFrame.getAttribute('data-map-src') || '';
            }
            if (streetFrame) {
              var loaded = false;
              var markLoaded = function () {
                loaded = true;
              };
              streetFrame.addEventListener('load', markLoaded, { once: true });
              setTimeout(function () {
                if (loaded) return;
                if (fallbackWrap) {
                  fallbackWrap.hidden = false;
                }
                if (fallbackUrl) {
                  window.open(fallbackUrl, '_blank', 'noopener,noreferrer');
                }
              }, 3500);
            }
          }
        }
        if (target === 'map') {
          root.querySelectorAll('.homlity-front-leaflet-map').forEach(function (node) {
            node.dispatchEvent(new CustomEvent('homlity:map-resize'));
          });
        }
      });
    });
  }

  function boot() {
    document.querySelectorAll('.property-map [data-map-tabs]').forEach(initMapTabs);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  // Re-init when Elementor renders a widget in the editor.
  window.addEventListener('elementor/frontend/init', function () {
    if (window.elementorFrontend && window.elementorFrontend.hooks) {
      window.elementorFrontend.hooks.addAction('frontend/element_ready/property_map.default', function ($el) {
        $el[0].querySelectorAll('[data-map-tabs]').forEach(initMapTabs);
      });
    }
  });
})();
