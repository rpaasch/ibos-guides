/* Guide-sider: overskriftsblokken tilbyder kun H2–H4 (sidens titel er H1). */
(function () {
  'use strict';
  if (!window.wp || !wp.hooks) { return; }
  wp.hooks.addFilter('blocks.registerBlockType', 'ibos-guides/heading-levels', function (settings, name) {
    if (name !== 'core/heading' || !settings.attributes || !settings.attributes.levelOptions) { return settings; }
    var attrs = Object.assign({}, settings.attributes, {
      levelOptions: Object.assign({}, settings.attributes.levelOptions, { default: [2, 3, 4] })
    });
    return Object.assign({}, settings, { attributes: attrs });
  });
})();

/* Videoblokken: valget "Kan hentes til brug uden internet" og råd til redaktøren. */
(function () {
  'use strict';
  if (!window.wp || !wp.hooks || !wp.compose || !wp.element || !wp.components || !wp.blockEditor || !wp.data) { return; }
  var el = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var Notice = wp.components.Notice;
  var PanelBody = wp.components.PanelBody;
  var Toggle = wp.components.ToggleControl;
  var Inspector = wp.blockEditor.InspectorControls;
  var BIG = 50 * 1048576; // Over 50 MB: advar.

  // Ny egenskab på videoblokken. Standard: ja (gemmes kun i blokken, hvis den slås fra).
  wp.hooks.addFilter('blocks.registerBlockType', 'ibos-guides/video-offline-attr', function (settings, name) {
    if (name !== 'core/video') { return settings; }
    return Object.assign({}, settings, {
      attributes: Object.assign({}, settings.attributes, { ibgOffline: { type: 'boolean', default: true } })
    });
  });

  function mb(bytes) {
    var m = bytes / 1048576;
    return (m < 10 ? Math.max(0.1, Math.round(m * 10) / 10) : Math.round(m)).toLocaleString('da-DK');
  }

  var withVideoHelp = wp.compose.createHigherOrderComponent(function (BlockEdit) {
    return function (props) {
      if (props.name !== 'core/video') { return el(BlockEdit, props); }
      var id = props.attributes.id;
      var offline = props.attributes.ibgOffline !== false;
      var media = wp.data.useSelect(function (select) {
        return id ? select('core').getMedia(id, { context: 'view' }) : null;
      }, [id]);
      var size = media && media.media_details && media.media_details.filesize;
      var sizeText = size ? ' (ca. ' + mb(size) + ' MB)' : '';
      var big = size && size > BIG;
      var notice = offline
        ? el(Notice, { status: big ? 'warning' : 'info', isDismissible: false, className: 'ibg-video-notice' },
            el('strong', null, 'Kan hentes til brug uden internet' + sizeText + '. '),
            big ? 'Videoen er stor – overvej at gøre den kortere. ' : '',
            'Skriv også det vigtigste som tekst på siden, så den kan bruges uden lyd og med skærmlæser.')
        : el(Notice, { status: 'info', isDismissible: false, className: 'ibg-video-notice' },
            el('strong', null, 'Kræver internet. '),
            'Skriv derfor det vigtigste som tekst eller punkter på siden, så den kan bruges uden video – og med skærmlæser.');
      return el(Fragment, null,
        notice,
        el(BlockEdit, props),
        el(Inspector, null,
          el(PanelBody, { title: 'Video i appen', initialOpen: true },
            el(Toggle, {
              label: 'Kan hentes til brug uden internet',
              checked: offline,
              onChange: function (v) { props.setAttributes({ ibgOffline: v ? undefined : false }); },
              help: offline
                ? 'Borgeren kan hente videoen på siden "Installér appen" – fx på wifi – og se den uden net' + sizeText + '.'
                : 'Videoen kan kun ses med internet. Slå det fra ved meget lange videoer.'
            }),
            big ? el('p', { style: { color: '#b32d2e' } }, 'Videoen fylder ' + mb(size) + ' MB. Store videoer bruger meget plads og data på borgerens telefon. Overvej at dele den op eller gøre den kortere.') : null,
            el('ul', { style: { listStyle: 'disc', paddingLeft: '1.2em', margin: 0 } },
              el('li', { style: { marginBottom: '.5em' } }, 'Tilføj undertekster: vælg "Tekstspor" i værktøjslinjen og upload en .vtt-fil.'),
              el('li', { style: { marginBottom: '.5em' } }, 'Brug mp4, og hold videoen kort – gerne under et par minutter.'),
              el('li', null, 'Skriv det vigtigste som tekst på siden, så den også giver mening uden video.')))));
    };
  }, 'withVideoHelp');
  wp.hooks.addFilter('editor.BlockEdit', 'ibos-guides/video-help', withVideoHelp);
})();
