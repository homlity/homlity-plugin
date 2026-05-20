(function () {
  function initFaqList(list) {
    if (list.dataset.faqReady === '1') return;
    list.dataset.faqReady = '1';

    var layout        = list.dataset.layout         || 'accordion';
    var firstOpen     = list.dataset.firstOpen      === 'true';
    var allowMultiple = list.dataset.allowMultiple  === 'true';
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

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.addEventListener('elementor/frontend/init', function () {
    if (!window.elementorFrontend || !window.elementorFrontend.hooks) return;
    window.elementorFrontend.hooks.addAction(
      'frontend/element_ready/homlity_property_faq.default',
      function ($el) {
        $el[0].querySelectorAll('.homlity-property-faq-list').forEach(function (list) {
          list.dataset.faqReady = '';
          initFaqList(list);
        });
      }
    );
  });
})();
