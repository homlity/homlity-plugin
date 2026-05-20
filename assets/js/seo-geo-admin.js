(function ($) {
  'use strict';

  /* ── Tab navigation ──────────────────────────────────────────────────── */
  function initTabs() {
    var $tabs   = $('.homlity-tabs-nav .nav-tab');
    var $panels = $('.homlity-seo-tab');
    var $input  = $('#homlity-current-tab');

    // Restore active tab from URL hash or query param
    var urlParams = new URLSearchParams(window.location.search);
    var initTab   = window.location.hash.replace('#tab-', '') || urlParams.get('tab') || 'general';
    activateTab(initTab);

    $tabs.on('click', function (e) {
      e.preventDefault();
      var tab = $(this).data('tab');
      activateTab(tab);
      history.replaceState(null, '', window.location.pathname + window.location.search.replace(/([?&])tab=[^&]*/, '') + (window.location.search ? '&' : '?') + 'tab=' + tab);
    });

    function activateTab(tab) {
      $tabs.removeClass('nav-tab-active');
      $panels.removeClass('is-active');
      $tabs.filter('[data-tab="' + tab + '"]').addClass('nav-tab-active');
      $('#tab-' + tab).addClass('is-active');
      $input.val(tab);
    }
  }

  /* ── Color picker ────────────────────────────────────────────────────── */
  function initColorPickers() {
    $('.homlity-color-picker').wpColorPicker();
  }

  /* ── Media uploader (image fields) ──────────────────────────────────── */
  function initMediaUploaders() {
    $(document).on('click', '.homlity-img-upload', function () {
      var $btn     = $(this);
      var $field   = $btn.closest('.homlity-img-field');
      var $urlInput = $field.find('.homlity-img-url');
      var $remove  = $field.find('.homlity-img-remove');
      var $preview = $field.find('.homlity-img-preview-wrap');

      if ($btn.data('frame')) {
        $btn.data('frame').open();
        return;
      }

      var frame = wp.media({
        title:    (homlitySeoGeo && homlitySeoGeo.uploadTitle) || 'Seleccionar imagen',
        button:   { text: (homlitySeoGeo && homlitySeoGeo.uploadButton) || 'Usar esta imagen' },
        multiple: false,
      });

      frame.on('select', function () {
        var attachment = frame.state().get('selection').first().toJSON();
        var url = attachment.url;
        $urlInput.val(url).trigger('change');
        $preview.html('<img src="' + url + '" class="homlity-img-preview">');
        $remove.show();
      });

      $btn.data('frame', frame);
      frame.open();
    });

    $(document).on('click', '.homlity-img-remove', function () {
      var $field = $(this).closest('.homlity-img-field');
      $field.find('.homlity-img-url').val('').trigger('change');
      $field.find('.homlity-img-preview-wrap').empty();
      $(this).hide();
    });
  }

  /* ── Repeater: collapse / expand rows ───────────────────────────────── */
  $(document).on('click', '.homlity-repeater-toggle', function () {
    var $body = $(this).closest('.homlity-repeater-row').find('.homlity-repeater-body');
    var collapsed = $body.hasClass('is-collapsed');
    $body.toggleClass('is-collapsed', !collapsed);
    $(this).text(collapsed ? 'Colapsar' : 'Expandir');
  });

  /* ── Repeater: remove row ────────────────────────────────────────────── */
  $(document).on('click', '.homlity-repeater-remove', function () {
    if (!window.confirm('¿Eliminar este elemento?')) {
      return;
    }
    $(this).closest('.homlity-repeater-row').remove();
  });

  /* ── Coverage zones repeater ─────────────────────────────────────────── */
  function getNextZoneIndex() {
    var max = -1;
    $('#homlity-coverage-zones .homlity-repeater-row').each(function () {
      var idx = parseInt($(this).data('index'), 10);
      if (!isNaN(idx) && idx > max) {
        max = idx;
      }
    });
    return max + 1;
  }

  $('#homlity-add-zone').on('click', function () {
    var template = document.getElementById('homlity-zone-template');
    if (!template) return;
    var idx  = getNextZoneIndex();
    var html = template.innerHTML.replace(/__IDX__/g, String(idx));
    var $row = $(html);
    $row.attr('data-index', idx);
    $('#homlity-coverage-zones').append($row);
  });

  /* ── FAQ repeater ────────────────────────────────────────────────────── */
  function getNextFaqIndex() {
    var max = -1;
    $('#homlity-global-faqs .homlity-repeater-row').each(function () {
      var idx = parseInt($(this).data('index'), 10);
      if (!isNaN(idx) && idx > max) {
        max = idx;
      }
    });
    return max + 1;
  }

  $('#homlity-add-faq').on('click', function () {
    var template = document.getElementById('homlity-faq-template');
    if (!template) return;
    var idx  = getNextFaqIndex();
    var html = template.innerHTML.replace(/__IDX__/g, String(idx));
    var $row = $(html);
    $row.attr('data-index', idx);
    $('#homlity-global-faqs').append($row);
    // Init color pickers in new row if any (future proofing)
    $row.find('.homlity-color-picker').wpColorPicker();
  });

  /* ── Live zone title preview ─────────────────────────────────────────── */
  $(document).on('input', '.homlity-zone-city-input, .homlity-zone-neighborhood-input', function () {
    var $row      = $(this).closest('.homlity-repeater-row');
    var city      = $row.find('.homlity-zone-city-input').val();
    var neighborhood = $row.find('.homlity-zone-neighborhood-input').val();
    var parts     = [city, neighborhood].filter(Boolean);
    $row.find('.homlity-zone-title-preview').text(parts.join(', '));
  });

  /* ── Live FAQ question preview ───────────────────────────────────────── */
  $(document).on('input', '.homlity-faq-question-input', function () {
    var val = $(this).val();
    var preview = val.length > 60 ? val.substring(0, 60) + '…' : val;
    $(this).closest('.homlity-repeater-row').find('.homlity-faq-title-preview').text(preview || 'Nueva pregunta');
  });

  /* ── Init ────────────────────────────────────────────────────────────── */
  $(document).ready(function () {
    initTabs();
    initColorPickers();
    initMediaUploaders();
  });

}(jQuery));
