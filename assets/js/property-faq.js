(function () {
  function initFaqList(list) {
    if (list.dataset.faqReady === '1') return;
    list.dataset.faqReady = '1';

    var layout        = list.dataset.layout        || 'accordion';
    var firstOpen     = list.dataset.firstOpen     === 'true';
    var allowMultiple = list.dataset.allowMultiple === 'true';
    var isAccordion   = layout === 'accordion';

    var items = Array.prototype.slice.call(list.querySelectorAll('.homlity-property-faq-item'));

    items.forEach(function (item, idx) {
      var btn    = item.querySelector('.homlity-property-faq-question');
      var answer = item.querySelector('.homlity-property-faq-answer');
      if (!btn || !answer) return;

      if (!isAccordion) {
        item.classList.add('is-active');
        btn.setAttribute('aria-expanded', 'true');
        answer.removeAttribute('hidden');
        return;
      }

      if (firstOpen && idx === 0) {
        item.classList.add('is-active');
        btn.setAttribute('aria-expanded', 'true');
        answer.removeAttribute('hidden');
      }

      btn.addEventListener('click', function () {
        var isOpen = item.classList.contains('is-active');

        if (!allowMultiple) {
          items.forEach(function (other) {
            if (other === item) return;
            other.classList.remove('is-active');
            var ob = other.querySelector('.homlity-property-faq-question');
            var oa = other.querySelector('.homlity-property-faq-answer');
            if (ob) ob.setAttribute('aria-expanded', 'false');
            if (oa) oa.setAttribute('hidden', '');
          });
        }

        if (isOpen) {
          item.classList.remove('is-active');
          btn.setAttribute('aria-expanded', 'false');
          answer.setAttribute('hidden', '');
        } else {
          item.classList.add('is-active');
          btn.setAttribute('aria-expanded', 'true');
          answer.removeAttribute('hidden');
        }
      });

      btn.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          btn.click();
        }
      });
    });
  }

  function boot() {
    document.querySelectorAll('.homlity-property-faq-list').forEach(initFaqList);
  }

  // Register Elementor widget-ready handler (once only).
  var elementorHandlerRegistered = false;
  function registerElementorHandler() {
    if (elementorHandlerRegistered) return;
    if (!window.elementorFrontend || !window.elementorFrontend.hooks) return;
    elementorHandlerRegistered = true;
    window.elementorFrontend.hooks.addAction(
      'frontend/element_ready/homlity_property_faq.default',
      function ($el) {
        // $el is a jQuery object; $el[0] is the native DOM node.
        var root = $el && $el[0] ? $el[0] : $el;
        if (root) root.querySelectorAll('.homlity-property-faq-list').forEach(initFaqList);
      }
    );
  }

  // Boot on DOMContentLoaded (front-end, no Elementor).
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  // Register the Elementor element_ready hook.
  // Elementor may fire 'elementor/frontend/init' via jQuery (older) or native
  // CustomEvent (newer). Support both, plus the case where it already fired.
  if (window.elementorFrontend) {
    registerElementorHandler();
  } else {
    window.addEventListener('elementor/frontend/init', registerElementorHandler);
    if (window.jQuery) {
      window.jQuery(window).on('elementor/frontend/init', registerElementorHandler);
    }
  }
})();
