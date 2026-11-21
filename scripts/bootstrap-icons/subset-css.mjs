// Feuille réduite de bootstrap-icons : celle du paquet, avec seulement les icônes que le site utilise
// (used-icons.mjs) et la police réduite à la place de la police complète. Écrit SUBSET_CSS et affiche la
// liste des points de code à garder dans la police (format de pyftsubset --unicodes). Lancé par subset.sh.
//
// Usage : node scripts/bootstrap-icons/subset-css.mjs [racine du dépôt]
import { readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { ICON_MAP, SUBSET_CSS, findBuiltNames, iconMap, usedIcons } from './used-icons.mjs';

const root = process.argv[2] ?? process.cwd();
const built = findBuiltNames(root);
if (built.length) {
    console.error(
        `Noms d'icônes construits dans le code, recensement impossible :\n${built.join('\n')}`,
    );
    process.exit(1);
}

const used = usedIcons(root);
const codes = iconMap(root);
const upstream = readFileSync(
    join(root, 'node_modules/bootstrap-icons/font/bootstrap-icons.css'),
    'utf8',
);
const version = JSON.parse(
    readFileSync(join(root, 'node_modules/bootstrap-icons/package.json'), 'utf8'),
).version;

// En-tête du paquet (licence, @font-face, règle commune), jusqu'à la première règle d'icône.
const firstRule = upstream.search(/^\.bi-[a-z0-9-]+::before/m);
const fontFace =
    /src: url\("\.\/fonts\/bootstrap-icons\.woff2\?[0-9a-f]+"\) format\("woff2"\),\s*url\("\.\/fonts\/bootstrap-icons\.woff\?[0-9a-f]+"\) format\("woff"\);/;
const head = upstream.slice(0, firstRule);
if (firstRule < 0 || !fontFace.test(head)) {
    console.error('Feuille de bootstrap-icons inattendue : en-tête ou @font-face introuvable.');
    process.exit(1);
}

const rules = [];
for (const line of upstream.slice(firstRule).split('\n')) {
    const match = line.match(/^\.bi-([a-z0-9-]+)::before \{ content: "\\([0-9a-f]+)"; \}$/);
    if (match && used.has(match[1])) {
        if (parseInt(match[2], 16) !== codes[match[1]]) {
            console.error(`Point de code de bi-${match[1]} différent de ${ICON_MAP}.`);
            process.exit(1);
        }
        rules.push(line);
    }
}
if (rules.length !== used.size) {
    const kept = new Set(rules.map((r) => r.match(/^\.bi-([a-z0-9-]+)/)[1]));
    console.error(
        `Règles introuvables : ${[...used.keys()].filter((n) => !kept.has(n)).join(', ')}`,
    );
    process.exit(1);
}

const css = [
    `/* Fichier généré par scripts/bootstrap-icons/subset.sh (bootstrap-icons ${version}, ${used.size} icônes) :`,
    "   ne pas modifier à la main. Après l'ajout d'une icône dans le code, relancer le script ; le test",
    '   tests/js/bootstrap-icons-subset.test.js échoue tant que la police réduite ne la contient pas. */',
    head
        .trimEnd()
        .replace(fontFace, 'src: url("../fonts/bootstrap-icons-subset.woff2") format("woff2");'),
    '',
    ...rules,
    '',
].join('\n');
writeFileSync(join(root, SUBSET_CSS), css);

console.log(
    [...used.keys()].map((name) => `U+${codes[name].toString(16).toUpperCase()}`).join(','),
);
