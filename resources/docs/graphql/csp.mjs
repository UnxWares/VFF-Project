// Politique de sécurité du contenu de la référence GraphQL (Magidoc) : aucun script ni style en ligne
// sans empreinte. Appliqué par postbuild.mjs après les autres corrections :
// - chaque <script> en ligne devient un fichier `_app/vff/<empreinte>.js` (nom = début du SHA-256 du
//   contenu, donc dédoublonné) ; le script de démarrage de SvelteKit y garde ses imports, réécrits en
//   chemins absolus puisqu'ils étaient relatifs à la page ;
// - les gestionnaires `onload`/`onerror` du gabarit (`this.__e=event`, rejeu d'événements d'images avant
//   l'hydratation) sont retirés : l'hydratation fonctionne sans ;
// - les attributs `style` écrits par le rendu côté serveur sont admis par leur empreinte SHA-256
//   (`style-src-attr 'unsafe-hashes'`), listée dans csp-style-hashes.json, que lit
//   App\Support\Security\ContentSecurityPolicy et que deploy/nginx/vff-project.org.conf recopie.
import { createHash } from 'node:crypto';

/** Dossier (sous la racine du site généré) des scripts sortis des pages. */
export const SCRIPT_DIR = '_app/vff';
/** Adresse publique de la référence. */
export const PUBLIC_BASE = '/docs/graphql';

const INLINE_SCRIPT = /<script(?![^>]*\bsrc=)([^>]*)>([\s\S]*?)<\/script>/g;
const STYLE_ATTRIBUTE = /\sstyle="([^"]*)"/g;
const EVENT_ATTRIBUTE = /\son(?:load|error)="this\.__e=event"/g;

export function sha256(text) {
    return createHash('sha256').update(text).digest('base64');
}

/** Valeur d'attribut HTML décodée (entités courantes). */
function decode(value) {
    return value
        .replace(/&quot;/g, '"')
        .replace(/&#39;/g, "'")
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&amp;/g, '&');
}

/**
 * Sort les scripts en ligne de la page, retire les gestionnaires d'événements en ligne et relève les
 * empreintes des attributs `style`.
 *
 * @param {string} html page générée
 * @returns {{html: string, scripts: Map<string, string>, styles: Set<string>, redirect: boolean}}
 *   `scripts` : nom de fichier (sous SCRIPT_DIR) => contenu à écrire
 */
export function externalise(html) {
    const scripts = new Map();
    const redirect = html.includes('http-equiv="refresh"');
    const out = html.replace(EVENT_ATTRIBUTE, '').replace(INLINE_SCRIPT, (_, attributes, code) => {
        // Redirection de l'accueil : la balise <meta http-equiv="refresh"> suffit.
        if (redirect) return '';
        const content = code
            .trim()
            // Imports relatifs à la page (`../_app/...`) : le fichier externe est ailleurs.
            .replace(/import\("(?:\.\.?\/)+_app\//g, `import("${PUBLIC_BASE}/_app/`);
        const name = `${createHash('sha256').update(content).digest('hex').slice(0, 16)}.js`;
        scripts.set(name, content + '\n');
        return `<script${attributes.replace(/\sdata-vff-lang/, '')} src="${PUBLIC_BASE}/${SCRIPT_DIR}/${name}"></script>`;
    });
    const styles = new Set();
    for (const match of out.matchAll(STYLE_ATTRIBUTE))
        styles.add(`sha256-${sha256(decode(match[1]))}`);
    return { html: out, scripts, styles, redirect };
}

/** Reste-t-il un script en ligne, un gestionnaire en ligne ou un `<style>` dans la page ? */
export function inlineLeftovers(html) {
    const found = [];
    for (const m of html.matchAll(INLINE_SCRIPT))
        found.push(`script en ligne : ${m[2].trim().slice(0, 60)}`);
    for (const m of html.matchAll(/\son[a-z]+="[^"]*"/g))
        found.push(`gestionnaire en ligne :${m[0]}`);
    for (const m of html.matchAll(/<style[\s>]/g)) found.push(`balise ${m[0]}`);
    return found;
}

/** Empreintes (`'sha256-…'`) des attributs `style` d'une page, telles que la CSP les attend. */
export function styleHashes(html) {
    return [...html.matchAll(STYLE_ATTRIBUTE)].map((m) => `sha256-${sha256(decode(m[1]))}`);
}

/**
 * Empreintes des attributs `style` écrits en dur dans les gabarits HTML d'un module JS (annonceur de
 * SvelteKit, gabarits de Svelte) : le navigateur les lit comme du HTML en ligne lors du rendu côté client.
 */
export function scriptStyleHashes(code) {
    return [...code.matchAll(/\sstyle=\\?"([^"\\]*)\\?"/g)].map(
        (m) => `sha256-${sha256(decode(m[1]))}`,
    );
}
