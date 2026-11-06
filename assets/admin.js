/* Mediebibliotek-vælger til app-ikon og logo. */
(function ($) {
  'use strict';
  $(document).on('click', '.ibg-media-choose', function (e) {
    e.preventDefault();
    var box = $(this).closest('.ibg-media');
    var frame = wp.media({ title: 'Vælg billede', library: { type: 'image' }, multiple: false });
    frame.on('select', function () {
      var a = frame.state().get('selection').first().toJSON();
      box.find('input[type=hidden]').val(a.id);
      var url = (a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url);
      box.find('img').attr('src', url).show();
    });
    frame.open();
  });
  $(document).on('click', '.ibg-media-remove', function (e) {
    e.preventDefault();
    var box = $(this).closest('.ibg-media');
    box.find('input[type=hidden]').val('');
    box.find('img').attr('src', '').hide();
  });
})(jQuery);
