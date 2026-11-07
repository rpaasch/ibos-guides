/* Guides → Oversigt: træk og slip, bekræftelse før papirkurv. Uden JavaScript virker
   knapperne ↑/↓ og "Mere → Flyt til" stadig. */
(function ($) {
  'use strict';
  var cfg = window.ibgTree || {};
  var $wrap = $('.ibg-tree-wrap');
  var $status = $('#ibg-tree-status');

  function say(text) {
    // Tøm først, så skærmlæsere også læser den samme besked igen.
    $status.text('');
    window.setTimeout(function () { $status.text(text); }, 60);
  }

  function fail(res) {
    var msg = (res && res.data && res.data.message) || cfg.error;
    window.alert(msg);
    window.location.reload();
  }

  function save($item) {
    var $list = $item.parent();
    var ids = $list.children('li').map(function () { return $(this).data('id'); }).get();
    $wrap.addClass('is-saving');
    say(cfg.saving);
    $.post(cfg.ajax, { action: 'ibos_guides_reorder', _ajax_nonce: cfg.nonce, parent: $list.data('parent'), order: ids })
      .done(function (res) {
        if (!res || !res.success) { fail(res); return; }
        say(res.data.message);
        $item.addClass('ibg-flash');
        window.setTimeout(function () { $item.removeClass('ibg-flash'); }, 1600);
      })
      .fail(function (xhr) { fail(xhr && xhr.responseJSON); })
      .always(function () { $wrap.removeClass('is-saving'); });
  }

  if ($.fn.sortable) {
    $('.ibg-list').each(function () {
      var $list = $(this);
      var depth = $list.data('depth');
      var guide = $list.data('guide');
      $list.sortable({
        items: '> li',
        handle: '.ibg-handle-d' + depth,
        // Sider kan flyttes mellem kapitler i samme guide; kapitler inden for guiden.
        connectWith: '.ibg-list[data-depth="' + depth + '"][data-guide="' + guide + '"]',
        axis: 'y',
        tolerance: 'pointer',
        placeholder: 'ibg-placeholder',
        forcePlaceholderSize: true,
        start: function () { $wrap.addClass('ibg-dragging'); $('.ibg-more[open]').removeAttr('open'); },
        stop: function (e, ui) { $wrap.removeClass('ibg-dragging'); save(ui.item); }
      });
    });
  }

  $(document).on('submit', '.ibg-trash', function (e) {
    var msg = (cfg.confirm || '').replace('%s', $(this).data('title'));
    if (!window.confirm(msg)) { e.preventDefault(); }
  });

  // Kun én "Mere"-menu åben ad gangen; Escape lukker den.
  // "toggle" bobler ikke op, så der lyttes i capture-fasen.
  document.addEventListener('toggle', function (e) {
    var el = e.target;
    if (el.classList && el.classList.contains('ibg-more') && el.open) {
      $('.ibg-more[open]').not(el).removeAttr('open');
    }
  }, true);
  $(document).on('keydown', function (e) {
    if (e.key === 'Escape') {
      var $open = $('.ibg-more[open]');
      if ($open.length) { $open.removeAttr('open').find('summary').trigger('focus'); }
    }
  });
})(jQuery);
