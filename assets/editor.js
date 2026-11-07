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
