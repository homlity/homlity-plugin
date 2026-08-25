(function () {
  'use strict';

  function parseIntSafe(value, fallback) {
    var n = parseInt(value, 10);
    return isNaN(n) ? fallback : n;
  }

  function createSwiperInstance(el, config) {
    if (!el || !window.Swiper) {
      return null;
    }

    // Elementor keeps its own Swiper build; using its factory when present
    // avoids loading a second copy and keeps editor previews in sync.
    if (window.elementorFrontend && window.elementorFrontend.utils && typeof window.elementorFrontend.utils.swiper === 'function') {
      try {
        return window.elementorFrontend.utils.swiper(el, config);
      } catch (e) {
        // Fall through to the global constructor below.
      }
    }

    return new window.Swiper(el, config);
  }

  function initHeroSlider(node) {
    if (!node || node.dataset.swiperReady === '1' || !window.Swiper) return;

    var container = node.querySelector('.swiper');
    if (!container || container._homlitySwiper) return;

    var layout = node.dataset.layout || 'hero';
    var desktop = Math.max(1, parseIntSafe(node.dataset.slidesDesktop, 1));
    var tablet = Math.max(1, parseIntSafe(node.dataset.slidesTablet, 1));
    var mobile = Math.max(1, parseIntSafe(node.dataset.slidesMobile, 1));
    var autoplay = node.dataset.autoplay === '1';
    var autoplayDelay = Math.max(1000, parseIntSafe(node.dataset.autoplayDelay, 5000));
    var pauseOnHover = node.dataset.pauseOnHover === '1';
    var loop = node.dataset.loop === '1';
    var effect = node.dataset.effect === 'fade' ? 'fade' : 'slide';
    var speed = Math.max(100, parseIntSafe(node.dataset.speed, 600));
    var showArrows = node.dataset.showArrows === '1';
    var showPagination = node.dataset.showPagination === '1';
    var paginationType = node.dataset.paginationType || 'bullets';

    var slidesCount = container.querySelectorAll('.swiper-wrapper > .swiper-slide').length;
    var maxSlidesPerView = Math.max(desktop, tablet, mobile);

    // Swiper duplicates slides to fake the loop; with too few of them the
    // carousel jumps instead of looping, so it is disabled in that case.
    var canLoop = loop && slidesCount > maxSlidesPerView;

    var gap = 0;
    if (layout === 'cards') {
      try {
        var rawGap = window.getComputedStyle(node).getPropertyValue('--hml-hero-slide-gap');
        var parsedGap = parseFloat(String(rawGap || '').replace('px', '').trim());
        if (!isNaN(parsedGap)) gap = parsedGap;
      } catch (e) {}
    }

    var config = {
      slidesPerView: mobile,
      spaceBetween: gap,
      speed: speed,
      loop: canLoop,
      watchOverflow: true,
      a11y: { enabled: true },
      breakpoints: {
        768: { slidesPerView: tablet, spaceBetween: gap },
        1024: { slidesPerView: desktop, spaceBetween: gap }
      }
    };

    // Fade only makes sense with a single slide on screen at a time.
    if (effect === 'fade' && maxSlidesPerView === 1) {
      config.effect = 'fade';
      config.fadeEffect = { crossFade: true };
    }

    if (autoplay) {
      config.autoplay = {
        delay: autoplayDelay,
        disableOnInteraction: false,
        pauseOnMouseEnter: pauseOnHover
      };
    }

    if (showPagination) {
      var paginationEl = node.querySelector('.swiper-pagination');
      if (paginationEl) {
        config.pagination = {
          el: paginationEl,
          type: ['bullets', 'fraction', 'progressbar'].indexOf(paginationType) !== -1
            ? paginationType
            : 'bullets',
          clickable: paginationType === 'bullets'
        };
      }
    }

    if (showArrows) {
      var nextEl = node.querySelector('.swiper-button-next');
      var prevEl = node.querySelector('.swiper-button-prev');
      if (nextEl && prevEl) {
        config.navigation = { nextEl: nextEl, prevEl: prevEl };
      }
    }

    var swiper = createSwiperInstance(container, config);
    if (!swiper) return;

    container._homlitySwiper = swiper;
    node._homlitySwiper = swiper;
    node.dataset.swiperReady = '1';
  }

  function updateHeroSlider(node) {
    if (!node) return;

    if (node.dataset.swiperReady !== '1') {
      initHeroSlider(node);
      return;
    }

    var container = node.querySelector('.swiper');
    if (container && container._homlitySwiper && typeof container._homlitySwiper.update === 'function') {
      container._homlitySwiper.update();
    }
  }

  function boot(scope) {
    var root = scope && scope.querySelectorAll ? scope : document;
    root.querySelectorAll('[data-homlity-hero-slider="1"]').forEach(initHeroSlider);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { boot(document); });
  } else {
    boot(document);
  }

  if (window.elementorFrontend && window.elementorFrontend.hooks && typeof window.elementorFrontend.hooks.addAction === 'function') {
    window.elementorFrontend.hooks.addAction('frontend/element_ready/property_hero_slider.default', function ($scope) {
      boot($scope && $scope[0] ? $scope[0] : document);
    });
  }

  window.initHomlityHeroSlider = initHeroSlider;
  window.updateHomlityHeroSlider = updateHeroSlider;
})();
