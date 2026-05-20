(function () {
  function initGallery(node) {
    if (!window.Swiper || !node || node.dataset.swiperReady === '1') return;
    var container = node.querySelector('.swiper');
    if (!container) return;

    var layout = node.dataset.layout || 'slider';
    var desktop = parseInt(node.dataset.slidesDesktop || '3', 10);
    var tablet = parseInt(node.dataset.slidesTablet || '2', 10);
    var mobile = parseInt(node.dataset.slidesMobile || '1', 10);
    var autoplay = node.dataset.autoplay === '1';
    var loop = node.dataset.loop !== '0';
    var showArrows = node.dataset.showArrows !== '0';
    var showPagination = node.dataset.showPagination !== '0';
    var speed = parseInt(node.dataset.speed || '520', 10);
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
    var thumbsPerView = parseInt(node.dataset.thumbsPerView || '4', 10);
    if (isNaN(thumbsPerView) || thumbsPerView < 1) thumbsPerView = 4;

    if (layout === 'slider' && thumbsNode) {
      thumbsSwiper = new window.Swiper(thumbsNode, {
        slidesPerView: thumbsPerView,
        spaceBetween: 10,
        freeMode: true,
        watchSlidesProgress: true,
        breakpoints: {
          768: { slidesPerView: Math.max(Math.floor(thumbsPerView * 0.7), 2) },
          1024: { slidesPerView: thumbsPerView }
        }
      });
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
      if (showPagination && node.querySelector('.swiper-pagination')) {
        config.pagination = { el: node.querySelector('.swiper-pagination'), clickable: true };
      }
      if (showArrows && node.querySelector('.swiper-button-next') && node.querySelector('.swiper-button-prev')) {
        config.navigation = {
          nextEl: node.querySelector('.swiper-button-next'),
          prevEl: node.querySelector('.swiper-button-prev')
        };
      }
      if (autoplay) {
        config.autoplay = { delay: 3800, disableOnInteraction: false };
      }
      if (thumbsSwiper) {
        config.thumbs = { swiper: thumbsSwiper };
      }
    } else {
      config.freeMode = true;
      config.loop = loop;
      config.speed = speed;
      config.watchSlidesProgress = true;
    }

    new window.Swiper(container, config);
    node.dataset.swiperReady = '1';
  }

  function boot() {
    document.querySelectorAll('[data-homlity-swiper-gallery="1"]').forEach(initGallery);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.initHomlitySwiperGallery = initGallery;
})();
