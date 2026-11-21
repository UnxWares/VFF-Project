// Recensement des icônes bootstrap-icons utilisées par le site, pour le sous-ensemble de la police
// (scripts/bootstrap-icons/subset.sh) et pour le test qui le contrôle (tests/js/bootstrap-icons-subset.test.js).
//
// Règle qui rend le recensement sûr : une icône s'écrit toujours en entier dans le code, `bi-nom`, dans une
// classe ou une chaîne (`'bi-geo-alt'`, `icon: 'bi-map'`, `class="bi {cond ? 'bi-eye' : 'bi-eye-slash'}"`).
// Jamais de nom construit (`bi-${nom}`, `'bi-' + nom`, `bi-{nom}`) : findBuiltNames() les signale, et le test
// échoue s'il en trouve. Les noms viennent seulement du code (aucune table ne porte de nom d'icône) ; un mot
// du texte qui ressemble à un nom (« bi-mode ») est écarté s'il n'est pas une icône de la police.
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

/** Dossiers lus, depuis la racine du dépôt. */
export const SCANNED_DIRS = [
    'resources/js',
    'resources/views',
    'resources/css',
    'app',
    'lang',
    'config',
    'routes',
    'database/data',
];

/** Extensions lues. */
export const SCANNED_EXTENSIONS = ['.svelte', '.js', '.mjs', '.ts', '.php', '.css', '.html'];

/** Feuille générée (elle porte les noms gardés) : jamais relue, sinon une icône retirée y resterait. */
export const SUBSET_CSS = 'resources/css/bootstrap-icons-subset.css';

/** Police réduite, servie par le build (importée par SUBSET_CSS). */
export const SUBSET_FONT = 'resources/fonts/bootstrap-icons-subset.woff2';

/** Correspondance nom → point de code de la police complète (paquet bootstrap-icons). */
export const ICON_MAP = 'node_modules/bootstrap-icons/font/bootstrap-icons.json';

const NAME = /\bbi-[a-z0-9]+(?:-[a-z0-9]+)*/g;
// `bi-${…}`, `bi-{…}` (Svelte), `'bi-' +`, `"bi-" +`, `+ 'bi-'`, `.concat('bi-'` : noms construits.
const BUILT = /bi-\$\{|bi-\{|['"`]bi-['"`]\s*\+|\+\s*['"`]bi-['"`]|concat\(\s*['"`]bi-['"`]/g;

/** Fichiers lus, chemins relatifs à la racine, dans un ordre stable. */
export function scannedFiles(root) {
    const files = [];
    const walk = (dir) => {
        let entries;
        try {
            entries = readdirSync(join(root, dir), { withFileTypes: true });
        } catch {
            return;
        }
        for (const entry of entries.sort((a, b) => a.name.localeCompare(b.name))) {
            const path = join(dir, entry.name);
            if (entry.isDirectory()) {
                if (entry.name !== 'node_modules' && !entry.name.startsWith('.')) walk(path);
            } else if (
                SCANNED_EXTENSIONS.some((ext) => entry.name.endsWith(ext)) &&
                path !== SUBSET_CSS
            ) {
                files.push(path);
            }
        }
    };
    for (const dir of SCANNED_DIRS) walk(dir);
    return files;
}

/** Noms des icônes de la police complète, avec leur point de code. */
export function iconMap(root) {
    return JSON.parse(readFileSync(join(root, ICON_MAP), 'utf8'));
}

/**
 * Icônes utilisées (noms sans le préfixe `bi-`), triées, et pour chacune un fichier où elle apparaît.
 *
 * @param {string} root racine du dépôt
 * @returns {Map<string, string>}
 */
export function usedIcons(root) {
    const known = iconMap(root);
    const used = new Map();
    for (const file of scannedFiles(root)) {
        const text = readFileSync(join(root, file), 'utf8');
        for (const [match] of text.matchAll(NAME)) {
            const name = match.slice(3);
            if (Object.hasOwn(known, name) && !used.has(name)) used.set(name, file);
        }
    }
    return new Map([...used].sort(([a], [b]) => a.localeCompare(b)));
}

/**
 * Noms d'icônes construits dans le code (le recensement ne peut pas les voir) : `fichier:ligne`.
 *
 * @param {string} root
 * @returns {string[]}
 */
export function findBuiltNames(root) {
    const found = [];
    for (const file of scannedFiles(root)) {
        const lines = readFileSync(join(root, file), 'utf8').split('\n');
        lines.forEach((line, index) => {
            if (line.match(BUILT)) found.push(`${file}:${index + 1}`);
        });
    }
    return found;
}
