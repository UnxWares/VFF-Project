// Pages du guide de la référence GraphQL (magidoc.mjs), dans les trois langues du site.
//
// Les textes sont des fichiers Markdown (guide/{fr,en,de}/*.md) ; les requêtes d'exemple sont communes
// aux trois langues (guide/examples/*.graphql, variables dans *.variables.json) et insérées par des
// marqueurs : `{{query:nom}}` (bloc graphql), `{{variables:nom}}` (bloc json des variables),
// `{{origin}}` (adresse du site), `{{siteRoot}}` (préfixe des liens entre pages : vide, Magidoc ajoute lui-même /docs/graphql). Un test PHP (GraphqlGuideTest) valide chaque requête contre le schéma.
import { readdirSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const GUIDE_DIR = join(dirname(fileURLToPath(import.meta.url)), 'guide');

/** Rubriques du guide : une par langue, l'URL est celle du titre (/docs/graphql/guide-en/…). */
export const GUIDES = [
    {
        locale: 'fr',
        title: 'Guide',
        pages: [
            { title: 'Introduction', file: 'introduction.md' },
            { title: 'Premiers pas', file: 'premiers-pas.md' },
        ],
    },
    {
        locale: 'en',
        title: 'Guide (EN)',
        pages: [
            { title: 'Introduction', file: 'introduction.md' },
            { title: 'Getting started', file: 'getting-started.md' },
        ],
    },
    {
        locale: 'de',
        title: 'Guide (DE)',
        pages: [
            { title: 'Grundlagen', file: 'grundlagen.md' },
            { title: 'Erste Schritte', file: 'erste-schritte.md' },
        ],
    },
];

function example(name, extension) {
    return readFileSync(join(GUIDE_DIR, 'examples', `${name}${extension}`), 'utf8').trimEnd();
}

/** Remplace les marqueurs d'un texte ; échoue si un exemple manque ou si un marqueur reste. */
export function expand(markdown, origin, siteRoot = '') {
    const out = markdown
        .replaceAll('{{origin}}', origin)
        .replaceAll('{{siteRoot}}', siteRoot)
        .replace(
            /\{\{query:([\w-]+)\}\}/g,
            (_, name) => `\`\`\`graphql\n${example(name, '.graphql')}\n\`\`\``,
        )
        .replace(
            /\{\{variables:([\w-]+)\}\}/g,
            (_, name) => `\`\`\`json\n${example(name, '.variables.json')}\n\`\`\``,
        );
    const left = out.match(/\{\{[^}]*\}\}/);
    if (left) throw new Error(`Marqueur du guide non résolu : ${left[0]}`);
    return out;
}

/** Pages au format `pages` de Magidoc. */
export function guidePages(origin) {
    return GUIDES.map((guide) => ({
        title: guide.title,
        content: guide.pages.map((page) => ({
            title: page.title,
            content: expand(readFileSync(join(GUIDE_DIR, guide.locale, page.file), 'utf8'), origin),
        })),
    }));
}

/** Fichiers présents mais absents de GUIDES (oubli de déclaration). */
export function orphanFiles() {
    const orphans = [];
    for (const guide of GUIDES) {
        const declared = guide.pages.map((p) => p.file);
        for (const file of readdirSync(join(GUIDE_DIR, guide.locale))) {
            if (!declared.includes(file)) orphans.push(`${guide.locale}/${file}`);
        }
    }
    return orphans;
}

if (process.argv[1] === fileURLToPath(import.meta.url) && process.argv.includes('--check')) {
    const orphans = orphanFiles();
    if (orphans.length) {
        console.error(`Pages du guide non déclarées : ${orphans.join(', ')}`);
        process.exit(1);
    }
    const pages = guidePages('https://vff-project.org');
    console.log(
        `guide ok : ${pages.length} langues, ${pages.flatMap((g) => g.content).length} pages`,
    );
}
