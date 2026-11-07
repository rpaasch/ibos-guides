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

/* Videoblokken: fortæl redaktøren, at video kræver internet, og hvad siden så skal indeholde. */
(function () {
  'use strict';
  if (!window.wp || !wp.hooks || !wp.compose || !wp.element || !wp.components || !wp.blockEditor) { return; }
  var el = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var Notice = wp.components.Notice;
  var PanelBody = wp.components.PanelBody;
  var Inspector = wp.blockEditor.InspectorControls;
  var lines = [
    'Videoer gemmes ikke i appen, så de kræver internet. Uden net ser borgeren en besked om, at videoen kræver internet.',
    'Skriv derfor det vigtigste som tekst eller punkter på siden, så den kan bruges uden video – og med skærmlæser.',
    'Tilføj undertekster: vælg "Tekstspor" i værktøjslinjen og upload en .vtt-fil.',
    'Hold videoen kort, og brug mp4. Store filer bruger borgerens mobildata.'
  ];
  var withVideoHelp = wp.compose.createHigherOrderComponent(function (BlockEdit) {
    return function (props) {
      if (props.name !== 'core/video') { return el(BlockEdit, props); }
      return el(Fragment, null,
        el(Notice, { status: 'info', isDismissible: false, className: 'ibg-video-notice' },
          el('strong', null, 'Video kræver internet. '), lines[1]),
        el(BlockEdit, props),
        el(Inspector, null,
          el(PanelBody, { title: 'Video i appen', initialOpen: true },
            el('ul', { style: { listStyle: 'disc', paddingLeft: '1.2em', margin: 0 } },
              lines.map(function (t, i) { return el('li', { key: i, style: { marginBottom: '.5em' } }, t); })))));
    };
  }, 'withVideoHelp');
  wp.hooks.addFilter('editor.BlockEdit', 'ibos-guides/video-help', withVideoHelp);
})();
