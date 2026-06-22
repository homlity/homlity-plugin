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

    if (window.elementorFrontend && window.elementorFrontend.utils && typeof window.elementorFrontend.utils.swiper === 'function') {
      try {
        return window.elementorFrontend.utils.swiper(el, config);
      } catch (e) {
        // Fallback to global constructor below.
      }
    }

    return new window.Swiper(el, config);
  }

  function initGallery(node) {
    if (!node || node.dataset.swiperReady === '1' || !window.Swiper) return;

    var container = node.querySelector('.swiper');
    if (!container || container._homlitySwiper) return;

    var layout = node.dataset.layout || 'slider';
    var desktop = parseIntSafe(node.dataset.slidesDesktop, 1);
    var tablet = parseIntSafe(node.dataset.slidesTablet, 1);
    var mobile = parseIntSafe(node.dataset.slidesMobile, 1);
    var autoplay = node.dataset.autoplay === '1';
    var loop = node.dataset.loop !== '0';
    var showArrows = node.dataset.showArrows !== '0';
    var showPagination = node.dataset.showPagination !== '0';
    var speed = parseIntSafe(node.dataset.speed, 520);

    var cssGap = 12;
    try {
      var rawGap = window.getComputedStyle(container).getPropertyValue('--homlity-gallery-gap');
      var parsedGap = parseFloat(String(rawGap || '').replace('px', '').trim());
      if (!isNaN(parsedGap)) cssGap = parsedGap;
    } catch (e) {}

    var thumbsNode = node.querySelector('.property-gallery__thumbs');
    var thumbsSwiper = null;
    var slidesCount = container.querySelectorAll('.swiper-wrapper > .swiper-slide').length;
    var maxSlidesPerView = Math.max(desktop, tablet, mobile);
    var canLoop = loop && slidesCount > maxSlidesPerView;
    var thumbsPerView = parseIntSafe(node.dataset.thumbsPerView, 4);
    thumbsPerView = Math.max(1, thumbsPerView);

    if (layout === 'slider' && thumbsNode && !thumbsNode._homlitySwiper) {
      thumbsSwiper = createSwiperInstance(thumbsNode, {
        slidesPerView: thumbsPerView,
        spaceBetween: 10,
        freeMode: true,
        watchSlidesProgress: true,
        breakpoints: {
          768: { slidesPerView: Math.max(Math.floor(thumbsPerView * 0.7), 2) },
          1024: { slidesPerView: thumbsPerView }
        }
      });

      if (thumbsSwiper) {
        thumbsNode._homlitySwiper = thumbsSwiper;
      }
    } else if (thumbsNode && thumbsNode._homlitySwiper) {
      thumbsSwiper = thumbsNode._homlitySwiper;
    }

    var config = {
      slidesPerView: mobile,
      spaceBetween: cssGap,
      breakpoints: {
        768: { slidesPerView: tablet },
        1024: { slidesPerView: desktop }
      }
    };

    if (layout === 'slider') {
      config.loop = canLoop;
      config.speed = speed;

      var paginationEl = node.querySelector('.swiper-pagination');
      if (showPagination && paginationEl) {
        config.pagination = { el: paginationEl, clickable: true };
      }

      var nextEl = node.querySelector('.swiper-button-next');
      var prevEl = node.querySelector('.swiper-button-prev');
      if (showArrows && nextEl && prevEl) {
        config.navigation = { nextEl: nextEl, prevEl: prevEl };
      }

      if (autoplay) {
        config.autoplay = { delay: 3800, disableOnInteraction: false };
      }

      if (thumbsSwiper) {
        config.thumbs = { swiper: thumbsSwiper };
      }
    } else {
      config.freeMode = true;
      config.loop = canLoop;
      config.speed = speed;
      config.watchSlidesProgress = true;
    }

    var swiper = createSwiperInstance(container, config);
    if (!swiper) return;

    container._homlitySwiper = swiper;
    node._homlitySwiper = swiper;
    node._homlityThumbsSwiper = thumbsSwiper;
    node.dataset.swiperReady = '1';
  }

  function updateGallery(node) {
    if (!node) return;

    if (node.dataset.swiperReady !== '1') {
      initGallery(node);
      return;
    }

    var container = node.querySelector('.swiper');
    if (container && container._homlitySwiper && typeof container._homlitySwiper.update === 'function') {
      container._homlitySwiper.update();
    }

    var thumbsNode = node.querySelector('.property-gallery__thumbs');
    if (thumbsNode && thumbsNode._homlitySwiper && typeof thumbsNode._homlitySwiper.update === 'function') {
      thumbsNode._homlitySwiper.update();
    }
  }

  function boot(scope) {
    var root = scope && scope.querySelectorAll ? scope : document;
    root.querySelectorAll('[data-homlity-swiper-gallery="1"]').forEach(initGallery);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { boot(document); });
  } else {
    boot(document);
  }

  if (window.elementorFrontend && window.elementorFrontend.hooks && typeof window.elementorFrontend.hooks.addAction === 'function') {
    window.elementorFrontend.hooks.addAction('frontend/element_ready/property_gallery.default', function ($scope) {
      boot($scope && $scope[0] ? $scope[0] : document);
    });
    window.elementorFrontend.hooks.addAction('frontend/element_ready/property_media_tabs.default', function ($scope) {
      boot($scope && $scope[0] ? $scope[0] : document);
    });
  }

  window.initHomlitySwiperGallery = initGallery;
  window.updateHomlitySwiperGallery = updateGallery;
})();
