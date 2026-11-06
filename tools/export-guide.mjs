// Eksporterer en Markdown-guide (repo med content/ og site.config.json) til import/<key>.json,
// som pluginets importør kan læse. Billeder hentes ved import fra GitHub (raw.githubusercontent.com).
//
// Brug: node tools/export-guide.mjs <repo-mappe> <nøgle> <github-ejer/repo> [commit] [farve]
// <commit> skal være en commit, der findes på GitHub (fx origin/main). Billederne hentes ved import
// fra præcis den commit, og hvert billedes SHA-256 gemmes og tjekkes ved import.
// Fx:   node tools/export-guide.mjs ../O-og-M om-teknik rpaasch/O-og-M main '#00628b'
// Nøglen bliver også appens adresse i WordPress (fx /om-teknik/).
import fs from 'node:fs';
import path from 'node:path';
import MarkdownIt from 'markdown-it';
import sharp from 'sharp';
import { readContent, flatten } from './content.mjs';
import { execFileSync } from 'node:child_process';
import crypto from 'node:crypto';

const [repoDir, key, ghRepo, commitArg = 'origin/main', colorArg] = process.argv.slice(2);
if (!repoDir || !key || !ghRepo) {
  console.error('Brug: node tools/export-guide.mjs <repo-mappe> <nøgle> <ejer/repo> [branch]');
  process.exit(1);
}
const ROOT = path.resolve(repoDir);
const cfg = JSON.parse(fs.readFileSync(path.join(ROOT, 'site.config.json'), 'utf8'));
const warnings = [];
const root = readContent(path.join(ROOT, 'content'), (w) => warnings.push(w), []);
const md = new MarkdownIt({ html: true });
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const images = new Map(); // repo-relativ sti -> true

function imageRef(token, srcFile) {
  const src = token.attrGet('src');
  const abs = path.resolve(path.dirname(srcFile), decodeURI(src));
  const rel = path.relative(ROOT, abs).split(path.sep).join('/');
  if (!fs.existsSync(abs)) { warnings.push(`${srcFile}: billede mangler ${src}`); return ''; }
  images.set(rel, true);
  const alt = (token.children || []).map((c) => c.content).join('').trim();
  const data = { src: rel, alt: /^dekorativ/i.test(alt) ? '' : alt, caption: token.attrGet('title') || '', decorative: /^dekorativ/i.test(alt) };
  return `<!-- ibg:image ${JSON.stringify(data)} /-->`;
}

function inline(tok, srcFile) {
  // Inline-indhold uden billeder (billeder håndteres som egne blokke).
  const kids = (tok.children || []).filter((c) => c.type !== 'image');
  return md.renderer.renderInline(kids, md.options, {}).trim();
}
function inlineImages(tok, srcFile) {
  return (tok.children || []).filter((c) => c.type === 'image').map((c) => imageRef(c, srcFile)).filter(Boolean);
}

// Serialiserer markdown-it-tokens til Gutenberg-blokke.
function toBlocks(tokens, srcFile) {
  const out = [];
  let i = 0;
  const findClose = (start, openType, closeType) => {
    let depth = 0;
    for (let j = start; j < tokens.length; j++) {
      if (tokens[j].type === openType) depth++;
      if (tokens[j].type === closeType && --depth === 0) return j;
    }
    return tokens.length - 1;
  };
  const list = (start, end, ordered) => {
    const items = [];
    let j = start + 1;
    while (j < end) {
      if (tokens[j].type === 'list_item_open') {
        const close = findClose(j, 'list_item_open', 'list_item_close');
        const texts = [];
        const imgs = [];
        let nested = '';
        for (let k = j + 1; k < close; k++) {
          const t = tokens[k];
          if (t.type === 'inline' && t.level === tokens[j].level + 2) { texts.push(inline(t, srcFile)); imgs.push(...inlineImages(t, srcFile)); }
          if (t.type === 'bullet_list_open' || t.type === 'ordered_list_open') {
            const c = findClose(k, t.type, t.type.replace('open', 'close'));
            nested += list(k, c, t.type === 'ordered_list_open');
            k = c;
          }
        }
        items.push(`<!-- wp:list-item -->\n<li>${texts.filter(Boolean).join('<br>')}${nested}</li>\n<!-- /wp:list-item -->`);
        out.push(...imgs.map((x) => `__IMG_AFTER__${x}`));
        j = close + 1;
      } else j++;
    }
    const attrs = ordered ? ' {"ordered":true}' : '';
    const tag = ordered ? 'ol' : 'ul';
    return `<!-- wp:list${attrs} -->\n<${tag} class="wp-block-list">${items.join('\n')}</${tag}>\n<!-- /wp:list -->`;
  };

  while (i < tokens.length) {
    const t = tokens[i];
    if (t.type === 'heading_open') {
      const level = Math.max(2, Number(t.tag.slice(1)));
      const attrs = level === 2 ? '' : ` {"level":${level}}`;
      out.push(`<!-- wp:heading${attrs} -->\n<h${level} class="wp-block-heading">${inline(tokens[i + 1], srcFile)}</h${level}>\n<!-- /wp:heading -->`);
      i += 3;
    } else if (t.type === 'paragraph_open') {
      const tok = tokens[i + 1];
      const text = inline(tok, srcFile).replace(/^(<br>\s*)+|(<br>\s*)+$/g, '').trim();
      if (text) out.push(`<!-- wp:paragraph -->\n<p>${text}</p>\n<!-- /wp:paragraph -->`);
      out.push(...inlineImages(tok, srcFile));
      i += 3;
    } else if (t.type === 'bullet_list_open' || t.type === 'ordered_list_open') {
      const close = findClose(i, t.type, t.type.replace('open', 'close'));
      const before = out.length;
      const block = list(i, close, t.type === 'ordered_list_open');
      // Billeder fundet i listepunkter placeres efter listen.
      const imgs = out.splice(before).map((x) => x.replace('__IMG_AFTER__', ''));
      out.push(block, ...imgs);
      i = close + 1;
    } else if (t.type === 'blockquote_open') {
      const close = findClose(i, 'blockquote_open', 'blockquote_close');
      const inner = toBlocks(tokens.slice(i + 1, close), srcFile);
      out.push(`<!-- wp:quote -->\n<blockquote class="wp-block-quote">${inner}</blockquote>\n<!-- /wp:quote -->`);
      i = close + 1;
    } else if (t.type === 'hr') {
      out.push('<!-- wp:separator -->\n<hr class="wp-block-separator has-alpha-channel-opacity"/>\n<!-- /wp:separator -->');
      i++;
    } else if (t.type === 'html_block') {
      out.push(`<!-- wp:html -->\n${t.content.trim()}\n<!-- /wp:html -->`);
      i++;
    } else if (t.type === 'fence' || t.type === 'code_block') {
      out.push(`<!-- wp:code -->\n<pre class="wp-block-code"><code>${esc(t.content)}</code></pre>\n<!-- /wp:code -->`);
      i++;
    } else if (t.type === 'table_open') {
      const close = findClose(i, 'table_open', 'table_close');
      out.push(`<!-- wp:table -->\n<figure class="wp-block-table">${md.renderer.render(tokens.slice(i, close + 1), md.options, {})}</figure>\n<!-- /wp:table -->`);
      i = close + 1;
    } else i++;
  }
  return out.join('\n\n');
}

function bodyBlocks(node) {
  if (!node.srcFile) return '';
  const tokens = md.parse(node.body || '', {});
  // Første "# overskrift" er titlen og udelades.
  const h = tokens.findIndex((t) => t.type === 'heading_open' && t.tag === 'h1');
  if (h !== -1) tokens.splice(h, 3);
  return toBlocks(tokens, node.srcFile);
}

const all = flatten(root);
const idOf = new Map(all.map((n, i) => [n, i === 0 ? 'root' : 'p' + i]));
const pages = all.slice(1).map((n) => ({
  key: idOf.get(n),
  parent: idOf.get(n.parent),
  title: n.title,
  menu_title: n.menuTitle !== n.title ? n.menuTitle : '',
  slug: n.slug,
  order: Number.isFinite(n.order) ? n.order : 999,
  excerpt: n.description,
  hidden: n.hidden,
  content: bodyBlocks(n),
}));

// Ikon som PNG (base64) – GD i WordPress kan ikke læse SVG.
const iconSrc = path.join(ROOT, cfg.icon || 'assets/icon.svg');
const color = colorArg || cfg.themeColor;
let iconPng = '';
if (fs.existsSync(iconSrc)) {
  let buf = fs.readFileSync(iconSrc);
  // Farv SVG-ikonets baggrund med guidens farve.
  if (/\.svg$/i.test(iconSrc) && cfg.themeColor) buf = Buffer.from(buf.toString('utf8').split(cfg.themeColor).join(color));
  iconPng = (await sharp(buf, { density: 384 }).resize(512, 512).png().toBuffer()).toString('base64');
}

// Fastlås til en commit og beregn hash ud fra filerne i netop den commit.
const commit = execFileSync('git', ['-C', ROOT, 'rev-parse', commitArg], { encoding: 'utf8' }).trim();
if (!/^[0-9a-f]{40}$/.test(commit)) throw new Error('Ugyldig commit: ' + commit);
const hashes = {};
for (const rel of images.keys()) {
  const buf = execFileSync('git', ['-C', ROOT, 'show', `${commit}:${rel}`], { maxBuffer: 64 * 1024 * 1024 });
  hashes[rel] = crypto.createHash('sha256').update(buf).digest('hex');
}

const out = {
  format: 2,
  key,
  source: `https://github.com/${ghRepo}`,
  raw_base: `https://raw.githubusercontent.com/${ghRepo}/${commit}/`,
  guide: {
    title: cfg.appName,
    slug: key,
    short_name: cfg.shortName || cfg.appName,
    excerpt: root.description || cfg.description || '',
    color,
    publisher: cfg.footer?.publisher || '',
    colophon: cfg.footer?.colophon || '',
    menu: cfg.menu === 'list' ? 'list' : 'tiles',
    content: bodyBlocks(root),
    icon_png: iconPng,
  },
  pages,
  images: [...images.keys()],
  hashes,
};
const file = path.join(path.dirname(new URL(import.meta.url).pathname), '..', 'import', `${key}.json`);
fs.writeFileSync(file, JSON.stringify(out, null, 1));
console.log(`${key}: ${pages.length} sider, ${images.size} billeder → ${path.relative(process.cwd(), file)} (${Math.round(fs.statSync(file).size / 1024)} kB)`);
if (warnings.length) console.log('Advarsler:\n  ' + warnings.join('\n  '));
