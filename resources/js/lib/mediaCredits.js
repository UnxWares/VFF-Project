// Page /credits/media : regroupe les licences et les auteurs tels que la base les stocke (valeurs
// importées de Wikimedia Commons : « CC BY-SA 2.0 fr », « Public domain », « poudou99 » et « Poudou99 »).
// Aucune donnée n'est modifiée : seul l'affichage regroupe les variantes d'une même licence ou d'un même auteur.

/**
 * Licence normalisée d'une valeur brute.
 * @param {string|null|undefined} raw
 * @returns {{ id: string, name?: string, labelKey?: string }} `name` : nom propre (jamais traduit) ;
 *   `labelKey` : clé `credits.media.licenses.*` (ou `no_license`) à traduire.
 */
export function normalizeLicense(raw) {
    const value = (raw ?? '').trim();
    if (value === '') return { id: 'none', labelKey: 'no_license' };
    const keyed = {
        'public domain': 'public_domain',
        'no restrictions': 'no_restrictions',
        attribution: 'attribution',
        'licence ouverte': 'licence_ouverte',
        fal: 'fal',
    }[value.toLowerCase()];
    if (keyed) return { id: keyed, labelKey: keyed };
    const cc = value.match(/^(CC BY(?:-SA)?) (\d\.\d)(?: [a-z]{2})?$/);
    if (cc) return { id: `${cc[1]} ${cc[2]}`, name: `${cc[1]} ${cc[2]}` };
    if (/^GFDL(?: [\d.]+)?$/.test(value)) return { id: 'GFDL', name: 'GFDL' };
    if (/^GPL(?:v?\d)?$/.test(value)) return { id: 'GPL', name: 'GPL' };
    return { id: value, name: value };
}

/** Adresse du texte de la licence, quand elle est connue ; `lang` : fr, en ou de. */
export function licenseUrl(name, lang = 'fr') {
    if (!name) return null;
    const deed = ['fr', 'de'].includes(lang) ? lang : 'en';
    const cc = name.match(/^CC (BY(?:-SA)?) (\d\.\d)$/);
    if (cc)
        return `https://creativecommons.org/licenses/${cc[1].toLowerCase()}/${cc[2]}/deed.${deed}`;
    if (name === 'CC0') return `https://creativecommons.org/publicdomain/zero/1.0/deed.${deed}`;
    if (name === 'ODbL') return 'https://opendatacommons.org/licenses/odbl/1-0/';
    return null;
}

/**
 * Regroupe les lignes `{ license, count }` par licence normalisée.
 * @returns {{ id: string, name?: string, labelKey?: string, count: number }[]} triées par nombre décroissant
 */
export function groupLicenses(rows) {
    const groups = new Map();
    for (const row of rows ?? []) {
        const norm = normalizeLicense(row.license);
        const entry = groups.get(norm.id) ?? { ...norm, count: 0 };
        entry.count += Number(row.count) || 0;
        groups.set(norm.id, entry);
    }
    return [...groups.values()].sort((a, b) => b.count - a.count);
}

/**
 * Regroupe les lignes `{ author, count }` qui ne diffèrent que par la casse ou les espaces ; garde
 * l'écriture la plus fréquente.
 * @returns {{ author: string|null, count: number }[]} triées par nombre décroissant
 */
export function mergeAuthors(rows) {
    const groups = new Map();
    for (const row of rows ?? []) {
        const name = (row.author ?? '').replace(/\s+/g, ' ').trim();
        const key = name.toLocaleLowerCase();
        const entry = groups.get(key) ?? { total: 0, spellings: new Map() };
        const count = Number(row.count) || 0;
        entry.total += count;
        entry.spellings.set(name, (entry.spellings.get(name) ?? 0) + count);
        groups.set(key, entry);
    }
    return [...groups.values()]
        .map((g) => ({
            author: [...g.spellings.entries()].sort((a, b) => b[1] - a[1])[0][0] || null,
            count: g.total,
        }))
        .sort((a, b) => b.count - a.count);
}

// Crédit d'une image publiée (galerie des fiches, images des événements de la chronologie) :
// mêmes règles partout, d'après les champs de MediaResource.

/** Titre lisible : la légende de la source quand elle est courte (phrase), sinon le titre du fichier. */
export function mediaHeading(m) {
    return m?.caption && m.caption.length <= 160 ? m.caption : (m?.title ?? '');
}

/** Licence affichée telle que fournie par la source ; à défaut, le statut des droits traduit. */
export function mediaLicenseLabel(m, t) {
    return m?.license ?? t(`media.rights.${m?.rights_status ?? 'unknown'}`);
}

/** Adresse du texte de la licence, sinon la page de la source ; null quand aucune n'est connue. */
export function mediaLicenseHref(m) {
    return m?.license_url ?? m?.source_url ?? null;
}
