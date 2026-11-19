// Après `magidoc generate` (script pnpm docs:graphql), corrections sur le site généré :
// 1. feuilles de style : retire les polices IBM Plex chargées depuis le CDN d'IBM (s81c.com). Les
//    @font-face gardent leurs sources local() ; le site utilise ses propres polices (vff.css) ;
// 2. modules JS compilés et pages HTML : corrections d'accessibilité (RGAA) décrites dans a11y.mjs
//    (logo, bouton « Voir aussi », étiquette de la recherche, liens « # », titre des pages,
//    paragraphes qui contiennent des blocs, lien d'évitement) ;
// 3. pages HTML : thème du site (cookie `theme`, sinon réglage du système : le gabarit ne lit que le
//    système), titre d'onglet avec le nom de la référence et langue (RGAA 8.7) : les libellés de
//    l'interface Magidoc (Guide, Queries, Types, Arguments, Search) sont en anglais, le gabarit n'a pas
//    de traduction, la page reste donc `lang="en"` ; les textes rédigés par VFF (guide, descriptions
//    du schéma, panneau « Voir aussi ») sont en français et balisés `lang="fr"` par un petit script
//    inséré dans chaque page. Le script reste actif quand SvelteKit change de page sans recharger le
//    document.
import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

import { patchPage, patchScript, TITLE_SUFFIX } from './a11y.mjs';

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

// Corrections attendues au moins une fois sur l'ensemble du site : si l'une manque, le gabarit a
// changé et la correction doit être revue (la génération s'arrête plutôt que de perdre un correctif).
function requireAll(kind, totals, names) {
    const missing = names.filter((name) => !totals[name]);
    if (missing.length) {
        console.error(
            `Corrections d'accessibilité introuvables (${kind}) : ${missing.join(', ')}.`,
        );
        process.exit(1);
    }
}
function addCounts(totals, counts) {
    for (const [name, n] of Object.entries(counts)) totals[name] = (totals[name] ?? 0) + n;
}

function filesOf(dir, extension, skip = null) {
    const files = [];
    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        if (entry.name === skip) continue;
        const path = join(dir, entry.name);
        if (entry.isDirectory()) files.push(...filesOf(path, extension, skip));
        else if (entry.name.endsWith(extension)) files.push(path);
    }
    return files;
}

const scriptTotals = {};
for (const file of filesOf(join(root, '_app/immutable'), '.js')) {
    const code = readFileSync(file, 'utf8');
    const result = patchScript(code);
    addCounts(scriptTotals, result.counts);
    if (result.code !== code) writeFileSync(file, result.code);
}
requireAll('modules JS', scriptTotals, Object.keys(patchScript('').counts));
console.log('Modules JS corrigés (accessibilité) :', JSON.stringify(scriptTotals));

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
        document.querySelectorAll('.bx--header-panel').forEach(function (el) { el.lang = 'fr'; });
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

let patched = 0;
const pageTotals = {};
for (const file of filesOf(root, '.html', '_app')) {
    const html = readFileSync(file, 'utf8');
    const accessible = patchPage(html);
    // La page d'accueil (/docs/graphql) n'est qu'une redirection vers le guide.
    if (!html.includes('http-equiv="refresh"')) addCounts(pageTotals, accessible.counts);
    const out = accessible.html
        .replace('<html lang="fr">', '<html lang="en">')
        .replace(DARK_QUERY, COOKIE_THEME)
        .replace(
            /<title>\s*([^<]*?)\s*<\/title>/,
            (_, t) => `<title>${t.endsWith(TITLE_SUFFIX) ? t : t + TITLE_SUFFIX}</title>`,
        );
    const withLang = out.includes(LANG_MARKER)
        ? out
        : out.replace('</head>', `${LANG_SCRIPT}</head>`);
    if (withLang !== html) {
        writeFileSync(file, withLang);
        patched++;
    }
}
requireAll('pages HTML', pageTotals, [
    'logo',
    'header-action',
    'hashtag-heading',
    'hashtag-link',
    'skip-link',
    'paragraphs',
]);
console.log(`Pages HTML corrigées (langue, thème du site, titre, accessibilité) : ${patched}.`);
console.log('Pages HTML corrigées (accessibilité) :', JSON.stringify(pageTotals));
