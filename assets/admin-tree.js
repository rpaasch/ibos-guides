/* Guides → Oversigt: træk og slip, "Mere"-paneler, bekræftelse før papirkurv og en lille
   "✓"-besked ved den side, der lige er ændret. Knapperne ↑/↓ virker også uden mus. */
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

  // Synlig bekræftelse lige ved rækken (beskeden øverst kan være rullet ud af syne).
  function chip($li, text) {
    var $row = $li.children('.ibg-row');
    $row.find('.ibg-chip').remove();
    var $c = $('<span class="ibg-chip" aria-hidden="true"></span>').text('✓ ' + text);
    $row.find('.ibg-title').append($c);
    $li.addClass('ibg-flash');
    window.setTimeout(function () { $c.fadeOut(400, function () { $c.remove(); }); $li.removeClass('ibg-flash'); }, 4000);
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
        chip($item, cfg.saved || 'Gemt');
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
        start: function (e, ui) { $wrap.addClass('ibg-dragging'); closeMore(); ui.item.data('ibgFrom', ui.item.parent()[0]).data('ibgIndex', ui.item.index()); },
        stop: function (e, ui) {
          $wrap.removeClass('ibg-dragging');
          // Lagt tilbage samme sted: intet at gemme.
          if (ui.item.parent()[0] === ui.item.data('ibgFrom') && ui.item.index() === ui.item.data('ibgIndex')) { return; }
          save(ui.item);
        }
      });
    });
  }

  // Efter ↑/↓, Flyt til, Kopiér og Papirkurv genindlæses siden: læs beskeden op, og sæt
  // fokus tilbage, hvor tastaturbrugeren var – ikke øverst på siden.
  (function () {
    var msg = $status.attr('data-announce');
    var f = ($status.attr('data-focus') || '').split(':');
    if (!msg) { return; }
    var $li = $('#ibg-item-' + parseInt(f[1], 10));
    var $target = $();
    if (f[0] === 'moved') {
      var dir = new URLSearchParams(location.search).get('ibg_dir');
      $target = $li.find('> .ibg-row .ibg-' + (dir === 'down' ? 'down' : 'up') + ' button').filter(function () { return $(this).css('visibility') !== 'hidden'; });
      if (!$target.length) { $target = $li.find('> .ibg-row .ibg-up button, > .ibg-row .ibg-down button').filter(function () { return $(this).css('visibility') !== 'hidden'; }); }
    } else if (f[0] === 'trashed') {
      $target = $('#ibg-undo');
    }
    if (!$target.length) { $target = $li.find('> .ibg-row .ibg-edit').first(); }
    // Efter WordPress' egne scripts (de kan ellers flytte fokus igen ved indlæsning).
    $(window).on('load', function () {
      window.setTimeout(function () {
        $target.first().trigger('focus');
        say(msg);
        if ($li.length) { chip($li, cfg.done && cfg.done[f[0]] ? cfg.done[f[0]] : (cfg.saved || 'Gemt')); }
      }, 50);
    });
  })();

  $(document).on('submit', '.ibg-trash', function (e) {
    var msg = (cfg.confirm || '').replace('%s', $(this).data('title'));
    if (!window.confirm(msg)) { e.preventDefault(); }
  });

  // "Mere": ét panel åbent ad gangen; Escape lukker og sender fokus tilbage til knappen.
  function closeMore() {
    $('.ibg-more-toggle[aria-expanded="true"]').each(function () {
      $(this).attr('aria-expanded', 'false');
      $('#' + $(this).attr('aria-controls')).prop('hidden', true);
    });
  }
  $(document).on('click', '.ibg-more-toggle', function () {
    var open = $(this).attr('aria-expanded') === 'true';
    closeMore();
    if (!open) {
      $(this).attr('aria-expanded', 'true');
      $('#' + $(this).attr('aria-controls')).prop('hidden', false);
    }
  });
  $(document).on('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    var $btn = $('.ibg-more-toggle[aria-expanded="true"]');
    if ($btn.length) { closeMore(); $btn.trigger('focus'); }
  });
})(jQuery);
