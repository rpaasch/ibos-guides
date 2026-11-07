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

  // Ny egenskab på videoblokken (registreres også på serveren). Standard: ja.
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

  // Egen komponent, så React-hooks altid kaldes i samme rækkefølge.
  function VideoHelp(props) {
    var BlockEdit = props.ibgBlockEdit;
    var id = props.attributes.id;
    var offline = props.attributes.ibgOffline !== false;
    var media = wp.data.useSelect(function (select) {
      return id ? select('core').getMedia(id, { context: 'view' }) : null;
    }, [id]);
    var size = media && media.media_details && media.media_details.filesize;
    var sizeText = size ? ' (ca. ' + mb(size) + ' MB)' : '';
    var big = size && size > BIG;
    var auto = !!props.attributes.autoplay;
    var editProps = Object.assign({}, props);
    delete editProps.ibgBlockEdit;
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
      auto ? el(Notice, { status: 'warning', isDismissible: false, className: 'ibg-video-notice' },
        el('strong', null, 'Afspil automatisk er slået til. '),
        'Slå det fra under Indstillinger – lyd og bevægelse, der starter af sig selv, er en tilgængelighedsfejl.') : null,
      el(BlockEdit, editProps),
      el(Inspector, null,
        el(PanelBody, { title: 'Video i appen', initialOpen: true },
          el(Toggle, {
            label: 'Kan hentes til brug uden internet',
            checked: offline,
            onChange: function (v) { props.setAttributes({ ibgOffline: v ? undefined : false }); },
            help: offline
              ? 'Borgeren kan hente videoen på siden "Installér appen" – fx på wifi – og se den uden net' + sizeText + '. Slå kun valget fra ved meget lange videoer.'
              : 'Videoen kan kun ses med internet.'
          }),
          big ? el('p', { style: { color: '#b32d2e' } }, 'Videoen fylder ' + mb(size) + ' MB. Store videoer bruger meget plads og data på borgerens telefon. Overvej at dele den op eller gøre den kortere.') : null,
          el('ul', { style: { listStyle: 'disc', paddingLeft: '1.2em', margin: 0 } },
            el('li', { style: { marginBottom: '.5em' } }, 'Tilføj undertekster: vælg "Tekstspor" i værktøjslinjen og overfør en .vtt-fil med typen "Undertekster".'),
            el('li', { style: { marginBottom: '.5em' } }, 'Brug mp4, og hold videoen kort – gerne under et par minutter og under 50 MB.'),
            el('li', { style: { marginBottom: '.5em' } }, 'Slå ikke "Afspil automatisk" til – lyd og bevægelse, der starter af sig selv, forstyrrer skærmlæsere.'),
            el('li', null, 'Skriv det vigtigste som tekst på siden, så den også giver mening uden video.')))));
  }

  var withVideoHelp = wp.compose.createHigherOrderComponent(function (BlockEdit) {
    return function (props) {
      if (props.name !== 'core/video') { return el(BlockEdit, props); }
      return el(VideoHelp, Object.assign({}, props, { ibgBlockEdit: BlockEdit }));
    };
  }, 'withVideoHelp');
  wp.hooks.addFilter('editor.BlockEdit', 'ibos-guides/video-help', withVideoHelp);
})();

/* Tilgængelighedstjek – live, mens der skrives, og lige før "Udgiv". */
(function () {
  'use strict';
  if (!window.wp || !wp.plugins || !wp.data || !wp.element || !wp.components) { return; }
  var el = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var DocPanel = (wp.editor && wp.editor.PluginDocumentSettingPanel) || (wp.editPost && wp.editPost.PluginDocumentSettingPanel);
  var PrePublish = (wp.editor && wp.editor.PluginPrePublishPanel) || (wp.editPost && wp.editPost.PluginPrePublishPanel);
  if (!DocPanel) { return; }

  function txt(v) { return v == null ? '' : String(v); }
  function plain(v) { return txt(v).replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim(); }

  // Samme regler som tjekket på serveren (includes/guide.php), men på de blokke, der står i
  // editoren lige nu.
  function check(blocks, title, parentTitle) {
    var out = [];
    var seen = {};
    var last = 1;
    var skipped = false;
    function add(key, text, id) { if (!seen[key]) { seen[key] = 1; out.push({ text: text, id: id }); } }
    function walk(list) {
      list.forEach(function (b) {
        var a = b.attributes || {};
        var content = txt(a.content) + txt(a.value) + txt(a.citation);
        if (content.indexOf('[✎ ') !== -1) {
          add('sample', 'Der står stadig eksempeltekst fra et mønster ([✎ …]). Skriv den om, eller slet blokken.', b.clientId);
        }
        if (/<a\b[^>]*>\s*(klik her|læs mere|her)\s*<\/a>/i.test(content)) {
          add('link', 'Et link hedder "klik her", "her" eller "læs mere". Skriv i stedet, hvor linket fører hen.', b.clientId);
        }
        if (b.name === 'core/heading') {
          var lvl = a.level || 2;
          if (lvl === 1) { add('h1', 'Brug ikke Overskrift 1 i teksten – sidens titel er allerede overskrift 1. Brug H2.', b.clientId); }
          if (!skipped && lvl > last + 1) {
            skipped = true;
            add('skip', 'Overskriftsniveauerne springer fra H' + last + ' til H' + lvl + '. Brug niveauerne i rækkefølge.', b.clientId);
          }
          last = lvl;
        }
        if (b.name === 'core/image') {
          var deco = a.isDecorative || /(^|\s)dekorativ(\s|$)/.test(txt(a.className));
          if (!a.url) {
            add('img-empty-' + b.clientId, 'En billedblok er tom. Vælg et billede, eller slet blokken.', b.clientId);
          } else if (!deco && !plain(a.alt)) {
            add('alt-' + b.clientId, 'Et billede mangler alt-tekst. Beskriv billedet i feltet "Alternativ tekst" – eller markér det som dekorativt, hvis det kun er pynt.', b.clientId);
          }
        }
        if (b.name === 'core/video') {
          if (!a.src) {
            add('vid-empty-' + b.clientId, 'En videoblok er tom. Vælg en video, eller slet blokken.', b.clientId);
          } else {
            var caps = (a.tracks || []).some(function (t) { return t && (t.kind === 'captions' || t.kind === 'subtitles' || !t.kind); });
            if (!caps) { add('caps-' + b.clientId, 'En video mangler undertekster. Vælg "Tekstspor" i videoblokkens værktøjslinje, og overfør en .vtt-fil.', b.clientId); }
            if (a.autoplay) { add('auto-' + b.clientId, 'En video afspilles automatisk. Slå "Afspil automatisk" fra.', b.clientId); }
          }
        }
        if (b.innerBlocks && b.innerBlocks.length) { walk(b.innerBlocks); }
      });
    }
    walk(blocks);
    if (!plain(title)) {
      out.unshift({ text: 'Siden mangler en titel.' });
    } else if (parentTitle && plain(title).toLocaleLowerCase('da') === plain(parentTitle).toLocaleLowerCase('da')) {
      out.unshift({ text: 'Siden har samme titel som niveauet over den ("' + plain(parentTitle) + '"). Det gør menuen og brødkrummerne svære at skelne – især med skærmlæser.' });
    }
    return out;
  }

  function useIssues() {
    var d = wp.data.useSelect(function (select) {
      var ed = select('core/editor');
      var parent = ed.getEditedPostAttribute('parent');
      var rec = parent ? select('core').getEntityRecord('postType', ed.getCurrentPostType(), parent) : null;
      return {
        blocks: select('core/block-editor').getBlocks(),
        title: ed.getEditedPostAttribute('title'),
        parentTitle: rec && rec.title ? (rec.title.raw || rec.title.rendered) : ''
      };
    }, []);
    return wp.element.useMemo(function () { return check(d.blocks, d.title, d.parentTitle); }, [d.blocks, d.title, d.parentTitle]);
  }

  function List(props) {
    var issues = props.issues;
    if (!issues.length) { return el('p', null, 'Ingen problemer fundet.'); }
    return el('ul', { style: { listStyle: 'disc', paddingLeft: '1.2em', margin: 0 } }, issues.map(function (i, n) {
      return el('li', { key: n, style: { marginBottom: '.6em' } }, i.text, i.id ? el(Fragment, null, ' ',
        el(wp.components.Button, {
          variant: 'link',
          onClick: function () { wp.data.dispatch('core/block-editor').selectBlock(i.id); }
        }, 'Vis blokken')) : null);
    }));
  }

  var PANEL = 'ibos-guides-check/ibg-check-live';
  wp.plugins.registerPlugin('ibos-guides-check', {
    render: function () {
      var issues = useIssues();
      // Panelet åbnes, når editoren indlæses, så tjekket ikke gemmer sig i et lukket panel.
      wp.element.useEffect(function () {
        var store = wp.data.select('core/editor');
        var act = wp.data.dispatch('core/editor');
        if (store && store.isEditorPanelOpened && act && act.toggleEditorPanelOpened && !store.isEditorPanelOpened(PANEL)) {
          act.toggleEditorPanelOpened(PANEL);
        }
      }, []);
      var n = issues.length;
      return el(Fragment, null,
        el(DocPanel, { name: 'ibg-check-live', title: n ? 'Tilgængelighedstjek (' + n + ')' : 'Tilgængelighedstjek' }, el(List, { issues: issues })),
        PrePublish ? el(PrePublish, { title: n ? 'Tjek før udgivelse: ' + n + (n === 1 ? ' ting' : ' ting') + ' at se på' : 'Tjek før udgivelse: alt ser fint ud', initialOpen: n > 0 },
          n ? el('p', null, 'Du kan godt udgive, men borgerne vil se det sådan her. Ret det gerne først:') : null,
          el(List, { issues: issues })) : null);
    }
  });
})();

/* "Forhåndsvis i appen": gemmer (kladder) eller autogemmer (udgivne sider) og åbner siden i
   appens design i et nyt vindue. */
(function () {
  'use strict';
  if (!window.wp || !wp.plugins || !wp.data || !wp.element || !wp.components || !window.ibgEditor) { return; }
  var el = wp.element.createElement;
  var StatusInfo = (wp.editor && wp.editor.PluginPostStatusInfo) || (wp.editPost && wp.editPost.PluginPostStatusInfo);
  if (!StatusInfo) { return; }

  function openPreview() {
    var ed = wp.data.select('core/editor');
    var act = wp.data.dispatch('core/editor');
    var id = ed.getCurrentPostId();
    // Vinduet åbnes med det samme (ellers blokeres det som pop op), og adressen sættes, når der er gemt.
    var w = window.open('', 'ibg-preview-' + id);
    if (w) {
      w.document.title = 'Forhåndsvisning';
      w.document.body.innerHTML = '<p style="font:16px/1.5 system-ui,sans-serif;padding:1rem">Gemmer og åbner forhåndsvisningen …</p>';
    }
    var status = ed.getEditedPostAttribute('status');
    var save;
    if (!ed.isEditedPostDirty() && !ed.isEditedPostNew()) {
      save = Promise.resolve();
    } else if (status === 'publish' || status === 'private' || status === 'future') {
      // Udgivne sider: ændringerne gemmes som autogem, så borgerne ikke ser dem endnu.
      save = act.autosave();
    } else {
      save = act.savePost();
    }
    Promise.resolve(save).then(function () {
      var url = window.ibgEditor.preview + '&id=' + encodeURIComponent(id);
      if (w) { w.location.href = url; } else { window.open(url, 'ibg-preview-' + id); }
    }, function () {
      if (w) { w.document.body.innerHTML = '<p style="font:16px/1.5 system-ui,sans-serif;padding:1rem">Siden kunne ikke gemmes. Prøv igen.</p>'; }
    });
  }

  // Udgivne sider: gem ændringen uden at borgerne ser den – den udgives senere sammen med
  // resten af guiden ("Gennemse og udgiv guiden" i Guides → Oversigt).
  function saveDraftChange() {
    var ed = wp.data.select('core/editor');
    var notices = wp.data.dispatch('core/notices');
    if (!ed.isEditedPostDirty()) {
      notices.createNotice('info', 'Der er ingen ændringer at gemme.', { type: 'snackbar' });
      return;
    }
    Promise.resolve(wp.data.dispatch('core/editor').autosave()).then(function () {
      notices.createNotice('success', 'Ændringen er gemt, men ikke udgivet. Borgerne ser den først, når du udgiver guiden under Guides → Oversigt (eller trykker Gem her).', { id: 'ibg-draft-change', isDismissible: true });
    }, function () {
      notices.createNotice('error', 'Ændringen kunne ikke gemmes. Prøv igen.', { type: 'snackbar' });
    });
  }

  function Box() {
    var status = wp.data.useSelect(function (select) { return select('core/editor').getEditedPostAttribute('status'); }, []);
    var small = { margin: '.5em 0 0', color: '#50575e', fontSize: '12px' };
    return el(StatusInfo, { className: 'ibg-preview-row' },
      el('div', { style: { width: '100%' } },
        el(wp.components.Button, { variant: 'secondary', onClick: openPreview, style: { width: '100%', justifyContent: 'center' } }, 'Forhåndsvis i appen'),
        el('p', { style: small }, 'Viser siden, som borgerne vil se den – også kladder og ændringer, der ikke er udgivet. Åbner i et nyt vindue.'),
        status === 'publish' ? el(wp.element.Fragment, null,
          el(wp.components.Button, { variant: 'secondary', onClick: saveDraftChange, style: { width: '100%', justifyContent: 'center', marginTop: '.75em' } }, 'Gem uden at udgive'),
          el('p', { style: small }, 'Siden er udgivet, så "Gem" viser ændringen for borgerne med det samme. Arbejder du på flere sider, så gem her og udgiv det hele på én gang under Guides → Oversigt.')) : null));
  }

  wp.plugins.registerPlugin('ibos-guides-preview', { render: Box });
})();
