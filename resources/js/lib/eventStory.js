// Histoire d'un événement de ligne (Pages/Lines/Story.svelte, Components/Stories/StoryCarousel.svelte) :
// légende datée de chaque image (« Photo de 1934, quatre ans après »), date des documents de Gallica, titre de
// la page. Données : App\Services\Stories\EventStories (`gap_years` : écart signé en années avec l'événement).
// Fonctions pures, testées par tests/js/eventStory.test.js ; `t` est la fonction de traduction de la page.

import { formatEventDate } from './linePhases.js';
import { decadeOf, formatLocale } from './typo.js';

const MEDIA_TYPES = ['photo', 'postcard', 'map', 'plan', 'document'];

/** Nombre d'années en lettres de 2 à 10 (« quatre »), en chiffres au-delà. */
export function yearsWord(n, t) {
    const key = `stories.numbers.${n}`;
    const word = n >= 2 && n <= 10 ? t(key) : null;
    return word && word !== key ? word : String(n);
}

/**
 * Écart avec l'événement en toutes lettres : « la même année », « un an après », « quatre ans avant » ;
 * « environ quatre ans avant » pour une date approchée (`approx`) ; null pour une image sans date
 * (`attached` : « image rattachée à l'événement »).
 * @param {number|null} gap écart signé en années
 * @param {(key: string, opts?: object) => string} t
 * @param {boolean} [attached]
 * @param {boolean} [approx]
 * @returns {string|null}
 */
export function gapPhrase(gap, t, attached = false, approx = false) {
    if (gap === null || gap === undefined) return attached ? t('stories.gap.attached') : null;
    const about = approx ? 'about_' : '';
    if (gap === 0) return t(`stories.gap.${about}same_year`);
    const n = Math.abs(gap);
    return t(`stories.gap.${about}${gap > 0 ? 'after' : 'before'}`, {
        count: n,
        years: yearsWord(n, t),
    });
}

/** Nature d'un média au singulier (« Photo », « Carte postale »), photo par défaut. */
export function mediaTypeLabel(type, t) {
    return t(`stories.media_types.${MEDIA_TYPES.includes(type) ? type : 'photo'}`);
}

/**
 * Légende datée d'une image, qui dit l'incertitude de sa date : « Photo de 1934, quatre ans après »,
 * « Carte des environs de 1840, environ trois ans avant », « Photo des années 1840, de trois ans avant à
 * six ans après », « Photo datée de 1840 à 1845, de trois ans avant à deux ans après », « Photo sans date,
 * image rattachée à l'événement ».
 * @param {{ type?: string, depicted_year?: number|null, date_precision?: string|null, gap_years?: number|null, gap_years_min?: number|null, gap_years_max?: number|null, attached?: boolean }} m
 * @param {(key: string, opts?: object) => string} t
 * @param {string} [lang]
 */
export function imageLegend(m, t, lang = 'fr') {
    const type = mediaTypeLabel(m.type, t);
    const year = m.depicted_year ?? null;
    if (year === null) {
        const gap = gapPhrase(null, t, m.attached === true);
        const undated = t('stories.undated', { type });
        return gap ? `${undated}, ${gap}` : undated;
    }
    const min = m.gap_years_min;
    const max = m.gap_years_max;
    const span = Number.isInteger(min) && Number.isInteger(max) && min !== max;
    let dated;
    let gap;
    if (m.date_precision === 'circa') {
        dated = t('stories.dated_circa', { type, year });
        gap = gapPhrase(m.gap_years ?? null, t, false, true);
    } else if (span) {
        dated =
            m.date_precision === 'decade'
                ? t('stories.dated_decade', { type, decade: decadeOf(year - (year % 10), lang) })
                : t('stories.dated_range', { type, from: year, to: year + (max - min) });
        gap = t('stories.gap.between', { from: gapPhrase(min, t), to: gapPhrase(max, t) });
    } else {
        dated = t('stories.dated', { type, year });
        gap = gapPhrase(m.gap_years ?? null, t);
    }
    return gap ? `${dated}, ${gap}` : dated;
}

/**
 * Date d'un document de Gallica à sa précision (même règles que la section « Dans les archives » de la
 * fiche) : « 1838 », « 12 mai 1843 », « vers 1850 », « 1840-1845 ».
 * @param {{ year?: number|null, year_to?: number|null, exact?: string|null, precision?: string }} date
 */
export function documentDate(date, t, lang = 'fr') {
    switch (date?.precision) {
        case 'day':
        case 'month':
            return date.exact
                ? formatEventDate(date.exact, date.precision, formatLocale(lang))
                : String(date.year);
        case 'circa':
            return t('archives.line.circa', { year: date.year });
        case 'range':
            return date.year_to ? `${date.year}-${date.year_to}` : String(date.year);
        case 'year':
            return String(date.year);
        default:
            return date?.year ? String(date.year) : t('archives.line.undated');
    }
}

/**
 * Titre de la page : nature de l'événement et portion en toutes lettres, « Ouverture de Vernon-Giverny à
 * Port-Villez » ; nature seule quand la portion est inconnue.
 * @param {string} type
 * @param {string|null} sectionText portion (`text.section_text` du serveur), déjà dans la langue de la page
 */
export function storyTitle(type, sectionText, t) {
    const key = `stories.titles.${type}`;
    const label = t(key);
    const event = label === key ? type : label;
    return sectionText ? t('stories.title_section', { event, section: sectionText }) : event;
}

/** Index après un pas de `delta` dans un carrousel de `count` images, borné (pas de retour au début). */
export function stepIndex(index, delta, count) {
    if (count <= 0) return 0;
    return Math.min(count - 1, Math.max(0, index + delta));
}

/**
 * Documents de Gallica regroupés comme dans la section « Dans les archives » de la fiche : plusieurs notices de
 * même titre, même date et même type (cinq photographies d'un même déraillement) forment une entrée, avec
 * les liens des autres vues (`more_urls`) et toutes leurs cotes (`shelfmarks`).
 * @param {Array<Record<string, any>>} documents documents de l'histoire (forme de l'API des archives)
 */
export function groupDocuments(documents) {
    const groups = new Map();
    for (const d of documents) {
        const key = `${String(d.title).toLowerCase()}|${d.date?.label ?? ''}|${d.type}`;
        const g = groups.get(key);
        if (g) {
            g.more_urls.push(d.gallica_url);
            if (d.shelfmark && !g.shelfmarks.includes(d.shelfmark)) g.shelfmarks.push(d.shelfmark);
            continue;
        }
        groups.set(key, {
            ...d,
            shelfmarks: d.shelfmark ? [d.shelfmark] : [],
            more_urls: [],
        });
    }
    return [...groups.values()];
}

/** « Alfred de Villemandy » pour « Villemandy, Alfred de » (forme de la notice) ; une collectivité reste telle quelle. */
export function authorName(name) {
    const m = /^([^,]+), ([^,]+)$/.exec(name);
    return m ? `${m[2]} ${m[1]}` : name;
}

/**
 * Métadonnées d'un document en une ligne : « Image · Agence Rol · cote EI-13 (1338) » (deux auteurs au plus).
 * @param {{ type: string, authors?: Array<string>, shelfmarks?: Array<string> }} d
 */
export function documentMeta(d, t) {
    return [
        t(`archives.line.types.${d.type}`),
        d.authors?.length ? d.authors.slice(0, 2).map(authorName).join(', ') : null,
        d.shelfmarks?.length
            ? t('archives.line.shelfmark', {
                  shelfmark: d.shelfmarks.join(', '),
                  count: d.shelfmarks.length,
              })
            : null,
    ]
        .filter(Boolean)
        .join(' · ');
}
