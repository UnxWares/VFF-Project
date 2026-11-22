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
//    document ;
// 4. politique de sécurité du contenu (csp.mjs) : scripts en ligne sortis en fichiers, gestionnaires en
//    ligne retirés, empreintes des attributs `style` relevées dans csp-style-hashes.json.
import { mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

import { patchPage, patchScript, TITLE_SUFFIX } from './a11y.mjs';
import { externalise, inlineLeftovers, SCRIPT_DIR, scriptStyleHashes } from './csp.mjs';

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
let unthemed = 0;
const pageTotals = {};
for (const file of filesOf(root, '.html', '_app')) {
    const html = readFileSync(file, 'utf8');
    const accessible = patchPage(html);
    // La page d'accueil (/docs/graphql) n'est qu'une redirection vers le guide.
    const redirect = html.includes('http-equiv="refresh"');
    if (!redirect) addCounts(pageTotals, accessible.counts);
    // Magidoc 7 écrivait `lang="fr"` (langue du schéma), Magidoc 9 écrit déjà `lang="en"` : la langue
    // de l'interface est posée dans les deux cas.
    const out = accessible.html
        .replace(DARK_QUERY, COOKIE_THEME)
        .replace(/<html lang="[^"]*">/, '<html lang="en">')
        .replace(
            /<title>\s*([^<]*?)\s*<\/title>/,
            (_, t) => `<title>${t.endsWith(TITLE_SUFFIX) ? t : t + TITLE_SUFFIX}</title>`,
        );
    // Chaque page (hors redirection) doit lire le thème du site : sinon le script de détection du
    // gabarit a changé et la page suivrait le système au lieu du réglage de VFF.
    if (!redirect && !out.includes(COOKIE_THEME)) unthemed++;
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
if (unthemed) {
    console.error(
        `Thème du site introuvable : ${unthemed} page(s) gardent la détection du gabarit.`,
    );
    process.exit(1);
}

// Politique de sécurité du contenu : plus aucun script en ligne (fichiers externes), plus de
// gestionnaire en ligne ; les attributs `style` du rendu serveur sont admis par empreinte.
const scriptDir = join(root, SCRIPT_DIR);
mkdirSync(scriptDir, { recursive: true });
const styleHashes = new Set();
let externalScripts = 0;
for (const file of filesOf(join(root, '_app/immutable'), '.js')) {
    for (const hash of scriptStyleHashes(readFileSync(file, 'utf8'))) styleHashes.add(hash);
}
for (const file of filesOf(root, '.html', '_app')) {
    const html = readFileSync(file, 'utf8');
    const result = externalise(html);
    for (const [name, content] of result.scripts) {
        writeFileSync(join(scriptDir, name), content);
        externalScripts++;
    }
    for (const hash of result.styles) styleHashes.add(hash);
    const left = inlineLeftovers(result.html);
    if (left.length) {
        console.error(`Contenu en ligne restant dans ${file} : ${left.join(' ; ')}`);
        process.exit(1);
    }
    if (result.html !== html) writeFileSync(file, result.html);
}
writeFileSync(
    'resources/docs/graphql/csp-style-hashes.json',
    JSON.stringify([...styleHashes].sort(), null, 4) + '\n',
);
console.log(
    `CSP : ${externalScripts} script(s) en ligne sortis en fichiers, ${styleHashes.size} empreinte(s) de style.`,
);
console.log(`Pages HTML corrigées (langue, thème du site, titre, accessibilité) : ${patched}.`);
console.log('Pages HTML corrigées (accessibilité) :', JSON.stringify(pageTotals));
