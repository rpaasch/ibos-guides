// Pakker pluginet som ibos-guides.zip (uden udviklingsfiler).
import fs from 'node:fs';
import path from 'node:path';
import archiver from 'archiver';

const ROOT = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const OUT = path.join(ROOT, 'ibos-guides.zip');
const INCLUDE = ['ibos-guides.php', 'uninstall.php', 'index.php', 'readme.txt', 'includes', 'assets', 'import'];
fs.rmSync(OUT, { force: true });
const out = fs.createWriteStream(OUT);
const zip = archiver('zip', { zlib: { level: 9 } });
zip.pipe(out);
for (const f of INCLUDE) {
  const p = path.join(ROOT, f);
  if (fs.statSync(p).isDirectory()) zip.directory(p, 'ibos-guides/' + f);
  else zip.file(p, { name: 'ibos-guides/' + f });
}
await zip.finalize();
await new Promise((r) => out.on('close', r));
console.log(`ibos-guides.zip: ${Math.round(fs.statSync(OUT).size / 1024)} kB`);
