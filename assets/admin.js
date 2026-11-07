/* App-indstillinger i editoren: billedvælger, live tjek af adresse og farve, bekræftelse. */
(function ($) {
  'use strict';
  var cfg = window.ibgAdmin || {};

  // ---------- App-ikon og logo ----------
  $(document).on('click', '.ibg-media-choose', function (e) {
    e.preventDefault();
    var box = $(this).closest('.ibg-media');
    var frame = wp.media({ title: 'Vælg billede', library: { type: 'image' }, multiple: false });
    frame.on('select', function () {
      var a = frame.state().get('selection').first().toJSON();
      box.find('input[type=hidden]').val(a.id);
      var url = (a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url);
      box.find('img').attr('src', url).show();
      box.find('.ibg-media-remove').prop('hidden', false);
    });
    frame.open();
  });
  $(document).on('click', '.ibg-media-remove', function (e) {
    e.preventDefault();
    var box = $(this).closest('.ibg-media');
    box.find('input[type=hidden]').val('');
    box.find('img').attr('src', '').hide();
    $(this).prop('hidden', true);
    box.find('.ibg-media-choose').trigger('focus');
    if (window.wp && wp.a11y && wp.a11y.speak) { wp.a11y.speak(cfg.removed || ''); }
  });

  // ---------- "Dette er en guide": bekræft, før en app fjernes ----------
  $(document).on('change', '#ibg-is-guide', function () {
    if (!this.checked && $(this).attr('data-was') === '1' && !window.confirm(cfg.untick || '')) {
      this.checked = true;
    }
  });

  // ---------- Adresse: tjek mens der skrives ----------
  var timer = null;
  var last = '';
  $(document).on('input', '#ibg-slug', function () {
    var input = $(this);
    clearTimeout(timer);
    timer = setTimeout(function () {
      var val = String(input.val() || '').trim();
      if (val === last) { return; }
      last = val;
      $.post(cfg.ajax, { action: 'ibos_guides_slug_check', _ajax_nonce: input.data('nonce'), post: input.data('post'), slug: val })
        .done(function (r) {
          if (!r || !r.success) { return; }
          var d = r.data || {};
          var live = $('#ibg-slug-live');
          // Den gemte fejlbesked hører til den forrige adresse – skjul den, når der skrives.
          input.closest('.ibg-field').find('.ibg-saved-error').remove();
          if (d.ok) {
            live.removeClass('ibg-bad').text((cfg.address || '%s').replace('%s', d.url));
            input.removeAttr('aria-invalid');
          } else {
            live.addClass('ibg-bad').text(d.message || '');
            input.attr('aria-invalid', 'true');
          }
        });
    }, 500);
  });

  // ---------- Temafarve: kontrast mens der vælges ----------
  function lum(hex) {
    var c = [1, 3, 5].map(function (i) {
      var v = parseInt(hex.substr(i, 2), 16) / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  }
  function ratio(hex) { return (1.05) / (lum(hex) + 0.05); }
  function showRatio(hex) {
    var live = $('#ibg-color-live');
    if (!/^#[0-9a-f]{6}$/i.test(hex)) { live.addClass('ibg-bad').text('Skriv farvekoden som # og 6 tegn, fx #00656c.'); return; }
    var r = ratio(hex);
    var txt = 'Kontrast til den hvide tekst: ' + r.toFixed(1).replace('.', ',') + ':1';
    live.toggleClass('ibg-bad', r < 4.5).text(r < 4.5 ? txt + ' – for lys. Vælg en mørkere farve; ellers gemmes den ikke.' : txt + ' – god.');
    $('#ibg-color-hex').closest('.ibg-field').find('.ibg-saved-error').remove();
  }
  $(document).on('input change', '#ibg-color', function () {
    $('#ibg-color-hex').val(this.value);
    showRatio(this.value);
  });
  $(document).on('input', '#ibg-color-hex', function () {
    var v = String(this.value || '').trim();
    if (/^[0-9a-f]{6}$/i.test(v)) { v = '#' + v; }
    if (/^#[0-9a-f]{6}$/i.test(v)) { $('#ibg-color').val(v.toLowerCase()); }
    showRatio(v);
  });
})(jQuery);
