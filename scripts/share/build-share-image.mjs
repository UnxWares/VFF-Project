// Image de partage de l'accueil (Open Graph et Twitter) : 1 200 x 630 px, réseau ferré en service tracé
// en orange VFF sur fond sombre chaud, logo et nom du site à gauche.
//
//   node scripts/share/build-share-image.mjs
//
// Entrées : scripts/share/network.json (réseau simplifié, voir export-network.sh et network.sql),
// resources/images/vff.png (logo), polices Gabarito et Lato de @fontsource. Sortie :
// public/share/accueil-reseau-1200x630.jpg. Navigateur : Chromium de Playwright (@playwright/test).
import { createRequire } from 'node:module';
import { readFileSync, writeFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const require = createRequire(join(root, 'package.json'));
const { chromium } = require('@playwright/test');

const W = 1200;
const H = 630;
const network = JSON.parse(readFileSync(join(root, 'scripts/share/network.json'), 'utf8'));

// Projection équirectangulaire corrigée de la latitude moyenne, cadrée sur la France métropolitaine.
const bbox = { lonMin: -5.3, lonMax: 9.7, latMin: 41.2, latMax: 51.2 };
const k = Math.cos((46.2 * Math.PI) / 180);
const margin = 36;
const scale = (H - 2 * margin) / (bbox.latMax - bbox.latMin);
const mapW = (bbox.lonMax - bbox.lonMin) * k * scale;
const originX = W - margin - mapW;
const px = ([lon, lat]) => [
    originX + (lon - bbox.lonMin) * k * scale,
    margin + (bbox.latMax - lat) * scale,
];
const toPath = (geometry) =>
    (geometry?.coordinates ?? [])
        .map((line) =>
            line
                .map((p, i) => {
                    const [x, y] = px(p);
                    return `${i ? 'L' : 'M'}${x.toFixed(1)} ${y.toFixed(1)}`;
                })
                .join(''),
        )
        .join('');

const font = (family, weight) =>
    pathToFileURL(
        join(
            root,
            `node_modules/@fontsource/${family}/files/${family}-latin-${weight}-normal.woff2`,
        ),
    ).href;
const logo = `data:image/png;base64,${readFileSync(join(root, 'resources/images/vff.png')).toString('base64')}`;

const html = `<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>
@font-face { font-family: Gabarito; font-weight: 700; src: url(${font('gabarito', 700)}); }
@font-face { font-family: Lato; font-weight: 400; src: url(${font('lato', 400)}); }
@font-face { font-family: Lato; font-weight: 700; src: url(${font('lato', 700)}); }
html, body { margin: 0; }
body { position: relative; width: ${W}px; height: ${H}px; overflow: hidden; background: #1d1a17; color: #f7f3ec; }
svg { position: absolute; inset: 0; }
.logo { position: absolute; left: 64px; top: 64px; width: 148px; height: 148px; border-radius: 50%; background: #fff; }
.name { position: absolute; left: 64px; top: 262px; width: 460px; margin: 0; font: 700 68px/1.08 Gabarito, sans-serif; letter-spacing: -0.01em; }
.tag { position: absolute; left: 64px; top: 462px; margin: 0; font: 400 30px/1.3 Lato, sans-serif; color: #d9d2c8; }
.url { position: absolute; left: 64px; bottom: 52px; margin: 0; font: 700 28px/1 Lato, sans-serif; color: #ff6500; }
</style></head><body>
<svg width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" fill="none" stroke-linecap="round" stroke-linejoin="round">
<path d="${toPath(network.reseau)}" stroke="#ff6500" stroke-width="1.1" opacity="0.8"/>
<path d="${toPath(network.lgv)}" stroke="#f7f3ec" stroke-width="1.7" opacity="0.95"/>
</svg>
<img class="logo" src="${logo}" alt="">
<h1 class="name">Voies Ferrées de France</h1>
<p class="tag">L’encyclopédie du rail</p>
<p class="url">vff-project.org</p>
</body></html>`;

const dir = mkdtempSync(join(tmpdir(), 'vff-share-'));
const file = join(dir, 'share.html');
writeFileSync(file, html);
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: W, height: H }, deviceScaleFactor: 1 });
await page.goto(pathToFileURL(file).href);
await page.evaluate(() => globalThis.document.fonts.ready);
const out = join(root, 'public/share/accueil-reseau-1200x630.jpg');
await page.screenshot({ path: out, type: 'jpeg', quality: 88 });
await browser.close();
console.log(out);
