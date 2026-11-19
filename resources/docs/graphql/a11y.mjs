// Corrections d'accessibilité (RGAA) de la référence GraphQL générée par Magidoc (gabarit
// carbon-multi-page), appliquées par postbuild.mjs après `magidoc generate`. Le gabarit est téléchargé
// et compilé à chaque génération : on ne peut pas le modifier, on corrige donc le site produit, à deux
// endroits qui doivent rester d'accord pour que l'hydratation de SvelteKit retrouve le même DOM :
// - les modules JS compilés (_app/immutable/**/*.js), qui rendent les pages lors d'une navigation ;
// - les pages HTML pré-rendues, lues avant l'hydratation et sans JavaScript.
//
// Chaque correction compte ses remplacements : postbuild.mjs s'arrête si l'une d'elles ne trouve plus
// rien (nouvelle version du gabarit), au lieu de publier une référence qui aurait perdu un correctif.

/** Logo de l'en-tête, seul contenu du lien vers l'accueil de la référence (RGAA 1.3, 6.1). */
export const LOGO_ALT = 'VFF GraphQL API, home';
/** Fin du titre de chaque page (RGAA 8.6) : le gabarit ne donne que « Query - line ». */
export const TITLE_SUFFIX = ' - VFF API GraphQL';
/** Nom du bouton qui ouvre le panneau des liens « Voir aussi » (RGAA 7.1), dans la langue du panneau. */
export const SEE_ALSO_LABEL = 'Voir aussi';
/** Étiquette du champ de recherche (RGAA 11.1), dans la langue de l'interface (anglais). */
export const SEARCH_LABEL = 'Search the reference';
/** Nom des liens « # » posés à côté des intertitres (RGAA 6.1). */
export const SECTION_LINK_LABEL = 'Link to this section';
/** Lien d'évitement (RGAA 12.7), premier élément de la page ; cible : la zone principale du gabarit. */
export const SKIP_LINK = '<a class="vff-skip-link" href="#main-content">Skip to main content</a>';

const BLOCK_TAG = /<(?:div|p|ul|ol|dl|pre|table|section|blockquote|figure|h[1-6])[\s>]/;

/**
 * Position du « ` » qui ferme le gabarit littéral JavaScript ouvert juste avant `start`, en tenant
 * compte des échappements et des gabarits imbriqués dans les `${…}`.
 *
 * @param {string} code
 * @param {number} start premier caractère du contenu du gabarit
 * @returns {number} index du « ` » fermant, -1 s'il manque
 */
export function templateEnd(code, start) {
    for (let i = start; i < code.length; i++) {
        const c = code[i];
        if (c === '\\') i++;
        else if (c === '`') return i;
        else if (c === '$' && code[i + 1] === '{') {
            i = expressionEnd(code, i + 2);
            if (i < 0) return -1;
        }
    }
    return -1;
}

/** Position de l'accolade qui ferme une expression `${…}` commencée en `start`. */
function expressionEnd(code, start) {
    let depth = 0;
    for (let i = start; i < code.length; i++) {
        const c = code[i];
        if (c === '`') {
            i = templateEnd(code, i + 1);
            if (i < 0) return -1;
        } else if (c === "'" || c === '"') {
            for (i++; i < code.length && code[i] !== c; i++) if (code[i] === '\\') i++;
        } else if (c === '{') depth++;
        else if (c === '}') {
            if (depth === 0) return i;
            depth--;
        }
    }
    return -1;
}

/**
 * Remplace par des `div` les paragraphes qui peuvent contenir des blocs (RGAA 8.2) : le gabarit range
 * des descriptions (déjà en `<p class="md-paragraph">`) et des étiquettes Carbon (`div.bx--tag`) dans des
 * `<p>`. Le navigateur referme alors le premier paragraphe en lisant la page pré-rendue, le DOM ne
 * correspond plus à celui que Svelte attend, et l'hydratation échoue (`hydration_mismatch`).
 *
 * Un paragraphe est changé s'il contient un emplacement de contenu (`<!>` dans un gabarit compilé,
 * marqueur `<!--` dans une page pré-rendue) ou une balise de bloc ; les paragraphes de texte
 * (`md-paragraph`, rendu du markdown) restent des paragraphes.
 *
 * @param {string} markup gabarit compilé ou page HTML
 * @returns {{markup: string, count: number}}
 */
export function blockParagraphsToDivs(markup) {
    const opens = [];
    const pairs = [];
    const tag = /<(\/?)p(?=[\s>])([^>]*)>/g;
    for (let m; (m = tag.exec(markup)); ) {
        if (m[1] === '') opens.push({ start: m.index, end: tag.lastIndex, attrs: m[2] });
        else {
            const open = opens.pop();
            if (open) pairs.push({ open, close: { start: m.index, end: tag.lastIndex } });
        }
    }
    const changes = pairs.filter(({ open, close }) => {
        if (/class="md-paragraph/.test(open.attrs)) return false;
        const inner = markup.slice(open.end, close.start);
        return inner.includes('<!') || BLOCK_TAG.test(inner);
    });
    const edits = changes
        .flatMap(({ open, close }) => [
            { start: open.start, end: open.end, text: `<div${open.attrs}>` },
            { start: close.start, end: close.end, text: '</div>' },
        ])
        .sort((a, b) => b.start - a.start);
    let out = markup;
    for (const e of edits) out = out.slice(0, e.start) + e.text + out.slice(e.end);

    return { markup: out, count: changes.length };
}

/**
 * Remplacement compté : `pattern` doit être global.
 *
 * @param {string} text
 * @param {RegExp} pattern
 * @param {string | ((...args: string[]) => string)} replacement
 * @returns {{text: string, count: number}}
 */
function countedReplace(text, pattern, replacement) {
    let count = 0;
    const out = text.replace(pattern, (...args) => {
        count++;
        return typeof replacement === 'string'
            ? replacement.replace(/\$(\d)/g, (_, n) => args[Number(n)] ?? '')
            : replacement(...args);
    });

    return { text: out, count };
}

/**
 * Corrections des modules JS compilés. Renvoie le code corrigé et, pour chaque correction, le nombre
 * de remplacements faits dans ce fichier.
 *
 * @param {string} code
 * @returns {{code: string, counts: Record<string, number>}}
 */
export function patchScript(code) {
    const counts = {};
    const apply = (name, pattern, replacement) => {
        const r = countedReplace(code, pattern, replacement);
        code = r.text;
        counts[name] = r.count;
    };

    // RGAA 1.3 : le logo (lien vers l'accueil de la référence) s'appelait « Magidoc ».
    apply('logo', /alt:`Magidoc`/g, `alt:\`${LOGO_ALT}\``);

    // RGAA 7.1 : bouton d'en-tête (HeaderAction de Carbon) sans nom ni état ; il ouvre le panneau des
    // liens « Voir aussi ». Le nom et l'état ouvert ou fermé sont posés avant les attributs transmis.
    apply(
        'header-action',
        /\(\{type:`button`,\.\.\.(\w+),class:(\w+\(\w+\)),\[(\w+)\]:\{"bx--header__action":!0,"bx--header__action--active":(\w+)\(\)/g,
        (_, rest, cls, key, open) =>
            `({type:\`button\`,lang:\`fr\`,"aria-label":\`${SEE_ALSO_LABEL}\`,"aria-expanded":String(!!${open}()),...${rest},class:${cls},[${key}]:{"bx--header__action":!0,"bx--header__action--active":${open}()`,
    );

    // RGAA 11.1 : champ de recherche de l'en-tête sans étiquette (l'étiquette de Carbon était vide).
    apply(
        'search-label',
        /autocomplete:`off`,size:`sm`\}/g,
        `autocomplete:\`off\`,size:\`sm\`,labelText:\`${SEARCH_LABEL}\`}`,
    );

    // RGAA 6.1 et 9.1 : lien « # » rangé dans un second intertitre vide de sens. L'intertitre devient
    // neutre (role="none"), le lien reçoit un nom.
    apply(
        'hashtag-heading',
        /(\w+)\((\w+),0,`header-base header-hashtag svelte-(\w+)`\);/g,
        (match, _fn, el) => `${match}${el}.setAttribute(\`role\`,\`none\`);`,
    );
    apply(
        'hashtag-link',
        /<a class="hashtag svelte-(\w+)">#<\/a>/g,
        `<a class="hashtag svelte-$1" aria-label="${SECTION_LINK_LABEL}" lang="en">#</a>`,
    );

    // RGAA 8.6 : titre de l'onglet complété par le nom de la référence, aussi après une navigation.
    let titles = 0;
    for (let at = code.indexOf('.title=`'); at !== -1; at = code.indexOf('.title=`', at + 1)) {
        const start = at + '.title=`'.length;
        const end = templateEnd(code, start);
        if (end < 0 || code.slice(start, end).endsWith(TITLE_SUFFIX)) continue;
        code = code.slice(0, end) + TITLE_SUFFIX + code.slice(end);
        titles++;
    }
    counts.title = titles;

    // RGAA 8.2 : paragraphes qui contiennent des blocs (voir blockParagraphsToDivs).
    let paragraphs = 0;
    code = code.replace(/`(<[^`]*)`/g, (whole, template) => {
        if (!/<p[\s>]/.test(template)) return whole;
        const r = blockParagraphsToDivs(template);
        paragraphs += r.count;
        return r.count ? `\`${r.markup}\`` : whole;
    });
    counts.paragraphs = paragraphs;

    return { code, counts };
}

/**
 * Corrections des pages HTML pré-rendues : mêmes changements que dans les modules JS, pour que la page
 * lue avant l'hydratation (ou sans JavaScript) soit déjà correcte, plus le lien d'évitement.
 *
 * @param {string} html
 * @returns {{html: string, counts: Record<string, number>}}
 */
export function patchPage(html) {
    const counts = {};
    const apply = (name, pattern, replacement) => {
        const r = countedReplace(html, pattern, replacement);
        html = r.text;
        counts[name] = r.count;
    };

    apply('logo', /alt="(?:Magidoc|VFF - Voies Ferrées de France)"/g, `alt="${LOGO_ALT}"`);
    apply(
        'header-action',
        /<button type="button" class="bx--header__action">/g,
        `<button type="button" lang="fr" aria-label="${SEE_ALSO_LABEL}" aria-expanded="false" class="bx--header__action">`,
    );
    apply(
        'hashtag-heading',
        /<(h[2-6]) class="header-base header-hashtag svelte-(\w+)">/g,
        '<$1 class="header-base header-hashtag svelte-$2" role="none">',
    );
    apply(
        'hashtag-link',
        /<a href="([^"]*)" class="hashtag svelte-(\w+)">#<\/a>/g,
        `<a href="$1" class="hashtag svelte-$2" aria-label="${SECTION_LINK_LABEL}" lang="en">#</a>`,
    );
    if (!html.includes('class="vff-skip-link"'))
        apply('skip-link', /<body>/g, `<body>${SKIP_LINK}`);
    else counts['skip-link'] = 1;
    const r = blockParagraphsToDivs(html);
    html = r.markup;
    counts.paragraphs = r.count;

    return { html, counts };
}
