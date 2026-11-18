// Après `magidoc generate` (script pnpm docs:graphql) : retire de la feuille de style du
// gabarit Carbon les polices IBM Plex chargées depuis le CDN d'IBM (s81c.com). Les
// @font-face gardent leurs sources local() ; le site utilise ses propres polices (vff.css).
import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const dir = 'public/docs/graphql/_app/immutable/assets';
let removed = 0;
for (const name of readdirSync(dir).filter((f) => f.endsWith('.css'))) {
    const file = join(dir, name);
    const css = readFileSync(file, 'utf8');
    const out = css.replace(/,?url\(https:\/\/[^)]*s81c\.com[^)]*\)\s*format\("[^"]*"\)/g, () => {
        removed++;
        return '';
    });
    if (out !== css) writeFileSync(file, out);
}
console.log(`Polices IBM Plex distantes retirées : ${removed} source(s).`);
