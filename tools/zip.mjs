// Pakker pluginet som ibos-guides.zip ud fra git (kun committede filer), reproducerbart:
// sorterede filer og fast tidsstempel, så samme commit altid giver samme zip (samme SHA-256).
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { ZipArchive } from 'archiver';

const ROOT = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const OUT = path.join(ROOT, 'ibos-guides.zip');
const INCLUDE = ['ibos-guides.php', 'uninstall.php', 'index.php', 'readme.txt', 'LICENSE', 'includes/', 'assets/', 'import/'];
const ref = process.argv[2] || 'HEAD';
const files = execFileSync('git', ['-C', ROOT, 'ls-tree', '-r', '--name-only', ref], { encoding: 'utf8' })
  .split('\n').filter((f) => f && INCLUDE.some((i) => (i.endsWith('/') ? f.startsWith(i) : f === i))).sort();
const date = new Date('2026-01-01T00:00:00Z');
fs.rmSync(OUT, { force: true });
const out = fs.createWriteStream(OUT);
const zip = new ZipArchive({ zlib: { level: 9 } });
zip.pipe(out);
for (const f of files) {
  const buf = execFileSync('git', ['-C', ROOT, 'show', `${ref}:${f}`], { maxBuffer: 64 * 1024 * 1024 });
  zip.append(buf, { name: 'ibos-guides/' + f, date, mode: 0o644 });
}
await zip.finalize();
await new Promise((r) => out.on('close', r));
const sha = crypto.createHash('sha256').update(fs.readFileSync(OUT)).digest('hex');
console.log(`ibos-guides.zip: ${files.length} filer, ${Math.round(fs.statSync(OUT).size / 1024)} kB, SHA-256 ${sha} (fra ${ref})`);
