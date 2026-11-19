// Propositions « événement daté » et « image » : contrôles des formulaires avant l'envoi et
// affichage des propositions (Contribute/ProposeEvent, ProposeMedia, Thanks, Mine,
// Validate/Index). Mêmes règles que le serveur (App\Services\Contributions\EventContributions,
// MediaContributions) : le navigateur signale les erreurs tout de suite, le serveur reste juge.

import { formatStationDate } from '$lib/stationDates.js';
import { NBSP, formatLocale } from '$lib/typo.js';
import { formatPk } from './segmentChanges.js';

export const EVENT_TYPE = 'line_event';
export const MEDIA_TYPE = 'media';

/** Première année acceptée pour une date (TraceContributionService::normalizeDate). */
export const MIN_YEAR = 1750;

/** Types d'image acceptés (ImageSanitizer::MIMES). */
export const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

/** Longueurs des champs, comme les règles du serveur. */
export const LIMITS = {
    sourceTitle: [3, 300],
    sourceUrl: 500,
    description: 300,
    notes: 2000,
    place: 250,
    title: [3, 250],
    caption: 800,
    author: [2, 200],
};

const pad = (n, size = 2) => String(n).padStart(size, '0');
const text = (v) => String(v ?? '').trim();

/**
 * Date envoyée au serveur selon sa précision : « 1890 », « 1890-06 » ou « 1890-06-16 ».
 * @param {string} precision `day`, `month` ou `year`
 * @param {number|string|null} year
 * @param {number|string|null} [month]
 * @param {number|string|null} [day]
 */
export function composeDate(precision, year, month = null, day = null) {
    const y = text(year);
    if (!/^\d{4}$/.test(y)) return '';
    if (precision === 'year') return y;
    const m = Number(month);
    if (!Number.isInteger(m) || m < 1 || m > 12) return '';
    if (precision === 'month') return `${y}-${pad(m)}`;
    const d = Number(day);
    if (!Number.isInteger(d) || d < 1 || d > 31) return '';
    return `${y}-${pad(m)}-${pad(d)}`;
}

/**
 * Vrai si la date composée existe (29 février compris) et tombe entre 1750 et l'année en cours
 * plus 30 ans, comme côté serveur.
 * @param {string} value date de composeDate()
 * @param {string} precision `day`, `month` ou `year`
 * @param {Date} [now]
 */
export function isValidDate(value, precision, now = new Date()) {
    const match = /^(\d{4})(?:-(\d{2}))?(?:-(\d{2}))?$/.exec(value ?? '');
    if (!match) return false;
    const [y, m, d] = [Number(match[1]), Number(match[2] ?? 0), Number(match[3] ?? 0)];
    if (y < MIN_YEAR || y > now.getFullYear() + 30) return false;
    if (precision === 'year') return match[2] === undefined;
    if (precision === 'month') return m >= 1 && m <= 12 && match[3] === undefined;
    const date = new Date(Date.UTC(y, m - 1, d));
    return date.getUTCFullYear() === y && date.getUTCMonth() === m - 1 && date.getUTCDate() === d;
}

/**
 * Noms des mois dans la langue de la page, pour une liste de choix.
 * @param {string} lang
 * @returns {{ value: number, label: string }[]}
 */
export function monthOptions(lang) {
    const format = new Intl.DateTimeFormat(formatLocale(lang), { month: 'long', timeZone: 'UTC' });
    return Array.from({ length: 12 }, (_, i) => ({
        value: i + 1,
        label: format.format(new Date(Date.UTC(2000, i, 1))),
    }));
}

/** Point kilométrique saisi en kilomètres et mètres → mètres (null si incomplet). */
export function pkMeters(km, m) {
    if (text(km) === '' && text(m) === '') return null;
    const total = Number(km || 0) * 1000 + Number(m || 0);
    return Number.isFinite(total) && total >= 0 ? Math.round(total) : null;
}

/** Adresse web absolue en http ou https. */
export function isWebUrl(value) {
    try {
        const url = new URL(text(value));
        return url.protocol === 'http:' || url.protocol === 'https:';
    } catch {
        return false;
    }
}

/**
 * Erreurs du formulaire « Proposer un événement daté », par champ (mêmes clés que le serveur).
 * @param {object} f valeurs du formulaire (date déjà composée)
 * @param {(key: string, opts?: object) => any} t
 * @param {Date} [now]
 * @returns {Record<string, string>}
 */
export function eventErrors(f, t, now = new Date()) {
    /** @type {Record<string, string>} */
    const e = {};
    if (!f.line_slug) e.line_slug = t('proposals.errors.line_required');
    if (!isValidDate(f.date, f.precision, now)) e.date = t('editor.errors.bad_date');
    if (f.portion === 'places') {
        if (!text(f.from) || !text(f.to)) e.to = t('proposals.event.errors.places');
        else if (text(f.from).toLowerCase() === text(f.to).toLowerCase())
            e.to = t('proposals.event.errors.places_same');
    }
    if (f.portion === 'pk') {
        if (f.pk_start_m === null || f.pk_end_m === null)
            e.pk_end_m = t('proposals.event.errors.pk');
        else if (!(f.pk_end_m > f.pk_start_m)) e.pk_end_m = t('proposals.event.errors.pk_order');
    }
    if (f.type === 'other' && !text(f.description))
        e.description = t('proposals.event.errors.description_other');
    const title = text(f.source_title);
    if (title.length < LIMITS.sourceTitle[0])
        e.source_title = t('proposals.event.errors.source_title');
    if (f.source_kind !== 'print' && !text(f.source_url))
        e.source_url = t('proposals.event.errors.source_url');
    else if (text(f.source_url) && !isWebUrl(f.source_url))
        e.source_url = t('editor.errors.bad_url');
    if (f.source_kind === 'document' && !f.source_license)
        e.source_license = t('proposals.event.errors.source_license');
    return e;
}

/**
 * Titre « File:Nom.jpg » d'une adresse de Wikimedia Commons, null si l'adresse n'en désigne pas
 * (même lecture que CommonsFileResolver::fileTitle côté serveur).
 * @param {string} input
 */
export function commonsFileTitle(input) {
    const value = text(input);
    const ns = /^(?:File|Fichier|Datei|Image):(.+)$/iu;
    let name = null;
    const direct = ns.exec(value);
    if (direct) {
        name = direct[1];
    } else if (/^https?:\/\//i.test(value)) {
        let url;
        try {
            url = new URL(value);
        } catch {
            return null;
        }
        const host = url.hostname.toLowerCase();
        if (host === 'commons.wikimedia.org' || host === 'commons.m.wikimedia.org') {
            const wiki = /^\/wiki\/(.+)$/.exec(url.pathname);
            const page = wiki ? safeDecode(wiki[1]) : (url.searchParams.get('title') ?? '');
            name = ns.exec(page)?.[1] ?? null;
        } else if (host === 'upload.wikimedia.org') {
            const file = /^\/wikipedia\/commons\/(?:thumb\/)?[0-9a-f]\/[0-9a-f]{2}\/([^/]+)/.exec(
                url.pathname,
            );
            name = file ? safeDecode(file[1]) : null;
        }
    }
    name = text(name?.replaceAll('_', ' '));
    if (!name || name.length > 240 || /[#<>[\]|{}/]/.test(name)) return null;
    return `File:${name.charAt(0).toLocaleUpperCase()}${name.slice(1)}`;
}

function safeDecode(value) {
    try {
        return decodeURIComponent(value);
    } catch {
        return value;
    }
}

/**
 * Erreur d'un fichier choisi, avant l'envoi : type annoncé par le navigateur et taille. Le
 * serveur relit le type dans le fichier lui-même.
 * @param {{ type: string, size: number }|null} file
 * @param {{ max_bytes: number }} limits
 * @param {(key: string, opts?: object) => any} t
 */
export function fileError(file, limits, t) {
    if (!file) return t('proposals.media.errors.file_required');
    if (!IMAGE_MIMES.includes(file.type)) return t('proposals.media.errors.file_type');
    if (file.size > limits.max_bytes)
        return t('proposals.media.errors.file_size', {
            max: Math.round(limits.max_bytes / 1048576),
        });
    return null;
}

/**
 * Erreur de dimensions d'une image lue dans le navigateur (null si elle convient).
 * @param {number} width
 * @param {number} height
 * @param {{ min_side: number, max_side: number }} limits
 * @param {(key: string, opts?: object) => any} t
 */
export function dimensionsError(width, height, limits, t) {
    const ok = [width, height].every((v) => v >= limits.min_side && v <= limits.max_side);
    return ok
        ? null
        : t('proposals.media.errors.file_dimensions', {
              min: limits.min_side,
              max: limits.max_side,
          });
}

/**
 * Erreurs du formulaire « Proposer une image », par champ.
 * @param {object} f valeurs du formulaire
 * @param {{ max_bytes: number, min_side: number, max_side: number }} limits
 * @param {(key: string, opts?: object) => any} t
 * @param {{ width: number, height: number }|null} [dimensions] lues sur l'aperçu
 * @param {Date} [now]
 * @returns {Record<string, string>}
 */
export function mediaErrors(f, limits, t, dimensions = null, now = new Date()) {
    /** @type {Record<string, string>} */
    const e = {};
    if (f.target_type === 'station') {
        if (!f.station_slug) e.station_slug = t('proposals.errors.station_required');
    } else if (!f.line_slug) {
        e.line_slug = t('proposals.errors.line_required');
    } else if (f.target_type === 'event' && !f.event_id) {
        e.event_id = t('proposals.media.errors.event');
    }
    if (f.source === 'commons') {
        if (!commonsFileTitle(f.commons_url))
            e.commons_url = t('proposals.media.errors.commons_url');
        return e;
    }
    const file = fileError(f.file, limits, t);
    if (file) e.file = file;
    else if (dimensions) {
        const dims = dimensionsError(dimensions.width, dimensions.height, limits, t);
        if (dims) e.file = dims;
    }
    if (text(f.title).length < LIMITS.title[0]) e.title = t('proposals.media.errors.title');
    if (text(f.author).length < LIMITS.author[0]) e.author = t('proposals.media.errors.author');
    if (!f.license) e.license = t('proposals.media.errors.license');
    if (f.captured_precision && !isValidDate(f.captured_date, f.captured_precision, now))
        e.captured_date = t('editor.errors.bad_date');
    const lat = text(f.lat);
    const lng = text(f.lng);
    if (lat || lng) {
        const [la, lo] = [Number(lat.replace(',', '.')), Number(lng.replace(',', '.'))];
        if (!lat || !lng || !(Math.abs(la) <= 90) || !(Math.abs(lo) <= 180))
            e.lng = t('proposals.media.errors.coordinates');
    }
    if (!f.rights_confirmed) e.rights_confirmed = t('proposals.media.errors.rights');
    return e;
}

/** Nombre décimal saisi avec une virgule ou un point → nombre envoyé (null si vide). */
export function decimal(value) {
    const v = text(value).replace(',', '.');
    return v === '' ? null : Number(v);
}

/**
 * Taille d'un fichier lisible : « 850 ko », « 2,4 Mo » (« 2.4 MB » en anglais, « 2,4 MB » en allemand).
 * @param {number} bytes
 * @param {string} lang
 */
export function formatBytes(bytes, lang) {
    const units = lang === 'fr' ? ['o', 'ko', 'Mo'] : ['B', 'kB', 'MB'];
    let value = Number(bytes) || 0;
    let unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }
    const digits = unit === 2 && value < 10 ? 1 : 0;
    const number = value.toLocaleString(formatLocale(lang), { maximumFractionDigits: digits });
    return `${number}${NBSP}${units[unit]}`;
}

function translated(t, key, fallback) {
    const label = t(key);
    return label === key ? fallback : label;
}

/**
 * Libellé d'un type d'événement (clés editor.event_types.*).
 * @param {string} type
 * @param {(key: string, opts?: object) => any} t
 */
export function eventTypeLabel(type, t) {
    return translated(t, `editor.event_types.${type}`, type);
}

/**
 * Portion d'un événement en clair : « Toute la ligne », « de A à B », « du PK 1+000 au PK 4+500 ».
 * @param {{ kind?: string, from?: string, to?: string, pk_start_m?: number, pk_end_m?: number }|null} portion
 * @param {(key: string, opts?: object) => any} t
 */
export function portionText(portion, t) {
    if (portion?.kind === 'places')
        return t('proposals.review.places_range', { from: portion.from, to: portion.to });
    if (portion?.kind === 'pk')
        return t('proposals.review.pk_range', {
            start: formatPk(portion.pk_start_m),
            end: formatPk(portion.pk_end_m),
        });
    return t('proposals.review.whole_line');
}

/**
 * Faits d'une proposition d'événement, dans l'ordre d'affichage : type, date, portion, précision,
 * source. Chaque fait : { id, label, text, href?, note? } (href : lien externe ; note : complément
 * écrit après le texte, hors du lien, comme la nature et la licence de la source).
 * @param {object} p charge utile de la contribution
 * @param {(key: string, opts?: object) => any} t
 * @param {string} lang
 */
export function eventFacts(p, t, lang) {
    const extra = p.reuse
        ? translated(t, `proposals.reuses.${p.reuse}`, p.reuse)
        : p.heritage_kind
          ? translated(t, `proposals.heritage_kinds.${p.heritage_kind}`, p.heritage_kind)
          : null;
    const source = p.source ?? {};
    // Nature et licence de la source : en complément du titre, hors du lien vers la source.
    const sourceNote = [
        source.kind
            ? translated(t, `proposals.event.source_kinds.${source.kind}`, source.kind)
            : null,
        source.license
            ? translated(t, `proposals.licenses.${source.license}`, source.license)
            : null,
    ].filter(Boolean);
    return [
        {
            id: 'type',
            label: t('proposals.review.type'),
            text: [eventTypeLabel(p.event_type, t), extra].filter(Boolean).join(', '),
        },
        {
            id: 'date',
            label: t('proposals.review.date'),
            text: formatStationDate(p.date, p.precision, null, t, lang) ?? '',
        },
        { id: 'portion', label: t('proposals.review.portion'), text: portionText(p.portion, t) },
        p.description && {
            id: 'description',
            label: t('proposals.review.description'),
            text: p.description,
        },
        {
            id: 'source',
            label: t('proposals.review.source'),
            text: source.title ?? '',
            href: isWebUrl(source.url) ? source.url : null,
            note: sourceNote.join(', ') || null,
        },
    ].filter(Boolean);
}

/**
 * Fiche à laquelle se rattache une image : la ligne, la gare, ou l'événement de la ligne.
 * @param {object} p charge utile
 * @param {(key: string, opts?: object) => any} t
 * @param {string} lang
 */
export function mediaTargetText(p, t, lang) {
    if (p.target_type === 'station') return p.station_name ?? '';
    const line = [p.line_name, p.line_code].filter(Boolean).join(' ');
    if (p.target_type !== 'event' || !p.event) return line;
    const event = t('proposals.review.event_of', {
        type: eventTypeLabel(p.event.type, t),
        date: formatStationDate(p.event.date, p.event.precision, null, t, lang) ?? '',
    });
    return `${line}, ${event}`;
}

/**
 * Faits d'une proposition d'image : origine, fiche, titre, auteur, licence, prise de vue, fichier.
 * @param {object} p charge utile
 * @param {(key: string, opts?: object) => any} t
 * @param {string} lang
 */
export function mediaFacts(p, t, lang) {
    const commons = p.source === 'commons' ? (p.commons ?? {}) : null;
    const facts = [
        {
            id: 'origin',
            label: t('proposals.review.origin'),
            text: t(
                commons
                    ? 'proposals.review.kind_media_commons'
                    : 'proposals.review.kind_media_upload',
            ),
            href: commons?.source_url ?? null,
        },
        {
            id: 'target',
            label: t('proposals.review.attached_to'),
            text: mediaTargetText(p, t, lang),
        },
        { id: 'title', label: t('proposals.review.title'), text: commons?.title ?? p.title },
        (commons?.caption ?? p.caption) && {
            id: 'caption',
            label: t('proposals.review.caption'),
            text: commons?.caption ?? p.caption,
        },
        {
            id: 'author',
            label: t('proposals.review.author'),
            text: commons?.author ?? p.author ?? '',
        },
        {
            id: 'license',
            label: t('proposals.review.license'),
            text: commons?.license ?? p.license_name ?? '',
            href: commons?.license_url ?? p.license_url ?? null,
        },
    ];
    if (!commons && p.captured)
        facts.push({
            id: 'captured',
            label: t('proposals.review.captured'),
            text: formatStationDate(p.captured.date, p.captured.precision, null, t, lang) ?? '',
        });
    if (!commons && p.place)
        facts.push({ id: 'place', label: t('proposals.review.place'), text: p.place });
    if (!commons && p.location)
        facts.push({
            id: 'coordinates',
            label: t('proposals.review.coordinates'),
            text: [p.location.lat, p.location.lng]
                .map((v) =>
                    Number(v).toLocaleString(formatLocale(lang), { maximumFractionDigits: 6 }),
                )
                .join(', '),
        });
    const width = commons?.width ?? p.file?.width;
    const height = commons?.height ?? p.file?.height;
    if (width && height)
        facts.push({
            id: 'file',
            label: t('proposals.review.file'),
            text: p.file
                ? t('proposals.review.file_facts', {
                      width,
                      height,
                      size: formatBytes(p.file.size, lang),
                  })
                : `${width} × ${height}`,
        });
    return facts.filter(Boolean);
}

/**
 * Métadonnées retirées d'un fichier déposé, en clair : « EXIF, position GPS, XMP ».
 * @param {string[]|null|undefined} kinds
 * @param {(key: string, opts?: object) => any} t
 */
export function removedMetadataText(kinds, t) {
    const list = (kinds ?? []).map((k) => translated(t, `proposals.review.removed_kinds.${k}`, k));
    return list.length
        ? t('proposals.review.removed', { list: list.join(', ') })
        : t('proposals.review.removed_none');
}

/**
 * Titre d'une proposition dans les listes (« Mes contributions ») : la ligne, la gare ou le titre
 * de l'image.
 * @param {object} p charge utile
 */
export function proposalHeading(p) {
    return p.line_name ?? p.station_name ?? p.title ?? p.commons?.title ?? '';
}
