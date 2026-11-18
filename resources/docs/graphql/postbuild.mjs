// Après `magidoc generate` (script pnpm docs:graphql), deux corrections sur le site généré :
// 1. feuilles de style : retire les polices IBM Plex chargées depuis le CDN d'IBM (s81c.com). Les
//    @font-face gardent leurs sources local() ; le site utilise ses propres polices (vff.css) ;
// 2. pages HTML : langue du document (fr, le contenu est en français), thème du site (cookie `theme`,
//    sinon réglage du système : le gabarit ne lit que le système) et titre d'onglet avec le nom de
//    la référence. Les libellés de l'interface Magidoc (Guide, Queries, Types, Search) restent en
//    anglais : le gabarit n'a pas de traduction.
import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const root = 'public/docs/graphql';

let removed = 0;
const assets = join(root, '_app/immutable/assets');
for (const name of readdirSync(assets).filter((f) => f.endsWith('.css'))) {
    const file = join(assets, name);
    const css = readFileSync(file, 'utf8');
    const out = css.replace(/,?url\(https:\/\/[^)]*s81c\.com[^)]*\)\s*format\("[^"]*"\)/g, () => {
        removed++;
        return '';
    });
    if (out !== css) writeFileSync(file, out);
}
console.log(`Polices IBM Plex distantes retirées : ${removed} source(s).`);

function htmlFiles(dir) {
    const files = [];
    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        if (entry.name === '_app') continue;
        const path = join(dir, entry.name);
        if (entry.isDirectory()) files.push(...htmlFiles(path));
        else if (entry.name.endsWith('.html')) files.push(path);
    }
    return files;
}

const DARK_QUERY =
    /const prefersDarkScheme = window\.matchMedia\(\s*'\(prefers-color-scheme: dark\)'\s*\)\.matches/;
const COOKIE_THEME =
    'const saved = document.cookie.match(/(?:^|; )theme=(dark|light)(?:;|$)/); ' +
    "const prefersDarkScheme = saved ? saved[1] === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches";

let patched = 0;
for (const file of htmlFiles(root)) {
    const html = readFileSync(file, 'utf8');
    const out = html
        .replace('<html lang="en">', '<html lang="fr">')
        .replace('alt="Magidoc"', 'alt="VFF - Voies Ferrées de France"')
        .replace(DARK_QUERY, COOKIE_THEME)
        .replace(
            /<title>\s*([^<]*?)\s*<\/title>/,
            (_, t) => `<title>${t} - VFF API GraphQL</title>`,
        );
    if (out !== html) {
        writeFileSync(file, out);
        patched++;
    }
}
console.log(`Pages HTML corrigées (langue, thème du site, titre) : ${patched}.`);
