// Après `magidoc generate` (script pnpm docs:graphql), deux corrections sur le site généré :
// 1. feuilles de style : retire les polices IBM Plex chargées depuis le CDN d'IBM (s81c.com). Les
//    @font-face gardent leurs sources local() ; le site utilise ses propres polices (vff.css) ;
// 2. pages HTML : thème du site (cookie `theme`, sinon réglage du système : le gabarit ne lit que le
//    système), titre d'onglet avec le nom de la référence et langue (RGAA 8.7) : les libellés de
//    l'interface Magidoc (Guide, Queries, Types, Arguments, Search) sont en anglais, le gabarit n'a pas
//    de traduction, la page reste donc `lang="en"` ; les textes rédigés par VFF (guide, descriptions
//    du schéma) sont en français et balisés `lang="fr"` par un petit script inséré dans chaque page.
//    Le script reste actif quand SvelteKit change de page sans recharger le document.
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

// Blocs de texte français : tout le guide, et les descriptions (paragraphes et listes) des
// pages de requêtes et de types. Les libellés du gabarit (titres « Arguments », étiquettes « Query »,
// noms de champs) restent en anglais.
const LANG_MARKER = 'data-vff-lang';
const LANG_SCRIPT = `<script ${LANG_MARKER}>
(function () {
    var french = '.md-paragraph, .markdown-list-style';
    function mark() {
        var main = document.getElementById('main-content');
        if (!main) return;
        if (location.pathname.indexOf('/guide/') !== -1) main.lang = 'fr';
        else main.removeAttribute('lang');
        main.querySelectorAll(french).forEach(function (el) { el.lang = 'fr'; });
    }
    new MutationObserver(mark).observe(document.documentElement, { childList: true, subtree: true });
    document.addEventListener('DOMContentLoaded', mark);
})();
</script>`;

const TITLE_SUFFIX = ' - VFF API GraphQL';
let patched = 0;
for (const file of htmlFiles(root)) {
    const html = readFileSync(file, 'utf8');
    const out = html
        .replace('<html lang="fr">', '<html lang="en">')
        .replace('alt="Magidoc"', 'alt="VFF - Voies Ferrées de France"')
        .replace(DARK_QUERY, COOKIE_THEME)
        .replace(
            /<title>\s*([^<]*?)\s*<\/title>/,
            (_, t) => `<title>${t.endsWith(TITLE_SUFFIX) ? t : t + TITLE_SUFFIX}</title>`,
        );
    const withLang = out.includes(LANG_MARKER) ? out : out.replace('</head>', `${LANG_SCRIPT}</head>`);
    if (withLang !== html) {
        writeFileSync(file, withLang);
        patched++;
    }
}
console.log(`Pages HTML corrigées (langue, thème du site, titre) : ${patched}.`);
