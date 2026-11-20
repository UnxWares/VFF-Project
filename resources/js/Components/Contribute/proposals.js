// Propositions « événement daté », « image », « matériel sur une ligne » et « série de matériel » :
// contrôles des formulaires avant l'envoi et affichage des propositions (Contribute/ProposeEvent,
// ProposeMedia, ProposeLineMaterial, ProposeSeries, Thanks, Mine, Validate/Index). Mêmes règles que le
// serveur (App\Services\Contributions\EventContributions, MediaContributions,
// LineMaterialContributions, MaterialSeriesContributions) : le navigateur signale les erreurs tout de
// suite, le serveur reste juge.

import { formatStationDate } from '$lib/stationDates.js';
import { NBSP, formatLocale } from '$lib/typo.js';
import { formatPk } from './segmentChanges.js';

export const EVENT_TYPE = 'line_event';
export const MEDIA_TYPE = 'media';
export const LINE_MATERIAL_TYPE = 'line_material';
export const SERIES_TYPE = 'material_series';

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
    quote: [10, 600],
    seriesName: [2, 120],
    builder: [2, 255],
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
    return { ...e, ...sourceErrors(f, t) };
}

/**
 * Erreurs de la source d'une proposition (champs `source_*`, SourceCitation côté serveur) : titre
 * ou référence, adresse sauf pour un ouvrage papier, licence d'un document numérisé.
 * @param {{ source_kind: string, source_title: string, source_url: string|null, source_license: string|null }} f
 * @param {(key: string, opts?: object) => any} t
 * @returns {Record<string, string>}
 */
export function sourceErrors(f, t) {
    /** @type {Record<string, string>} */
    const e = {};
    if (text(f.source_title).length < LIMITS.sourceTitle[0])
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
 * Erreurs du formulaire « Proposer un matériel sur une ligne », par champ (mêmes clés que le
 * serveur). `quoteRequired` : le contrôle de compatibilité a trouvé un conflit avec une règle, la
 * phrase de la source devient obligatoire.
 * @param {object} f valeurs du formulaire (dates déjà composées, null sans date)
 * @param {(key: string, opts?: object) => any} t
 * @param {{ quoteRequired?: boolean, now?: Date }} [options]
 * @returns {Record<string, string>}
 */
export function lineMaterialErrors(f, t, { quoteRequired = false, now = new Date() } = {}) {
    /** @type {Record<string, string>} */
    const e = {};
    if (!f.line_slug) e.line_slug = t('proposals.errors.line_required');
    if (!f.material_slug) e.material_slug = t('proposals.errors.material_required');
    for (const bound of ['start', 'end']) {
        const precision = f[`${bound}_precision`];
        if (precision && !isValidDate(f[`${bound}_date`], precision, now))
            e[`${bound}_date`] = t('editor.errors.bad_date');
    }
    if (!e.start_date && !e.end_date && f.start_date && f.end_date) {
        if (paddedDate(f.end_date) < paddedDate(f.start_date))
            e.end_date = t('proposals.line_material.errors.end_before_start');
    }
    const [from, to] = [text(f.section_from), text(f.section_to)];
    if (from || to) {
        if (!from || !to) e.section_to = t('proposals.event.errors.places');
        else if (from.toLowerCase() === to.toLowerCase())
            e.section_to = t('proposals.event.errors.places_same');
        else if (!f.start_date && !f.end_date)
            e.start_date = t('proposals.line_material.errors.section_without_date');
    }
    const quote = text(f.quote);
    if (quote && (quote.length < LIMITS.quote[0] || quote.length > LIMITS.quote[1]))
        e.quote = t('proposals.line_material.errors.quote_length', {
            min: LIMITS.quote[0],
            max: LIMITS.quote[1],
        });
    else if (!quote && quoteRequired) e.quote = t('proposals.line_material.errors.quote_required');
    return { ...e, ...sourceErrors(f, t) };
}

/** Date au jour, au mois ou à l'année ramenée au premier jour, pour comparer deux bornes. */
function paddedDate(value) {
    const [y, m = '01', d = '01'] = String(value).split('-');
    return `${y}-${m}-${d}`;
}

/**
 * Identifiant « Q123 » d'une saisie Wikidata (identifiant seul ou adresse d'un élément), null si
 * la saisie n'en désigne pas (même lecture que WikidataItemResolver::normalize côté serveur).
 * @param {string|null} input
 */
export function normalizeQid(input) {
    let value = text(input);
    const url =
        /^https?:\/\/(?:www\.|m\.)?wikidata\.org\/(?:wiki\/(?:Special:EntityPage\/)?|entity\/)(Q\d+)(?:[#?].*)?$/i.exec(
            value,
        );
    if (url) value = url[1];
    value = value.toUpperCase();
    return /^Q[1-9]\d{0,11}$/.test(value) ? value : null;
}

/**
 * Erreurs du formulaire « Proposer une série », par champ.
 * @param {object} f valeurs du formulaire
 * @param {(key: string, opts?: object) => any} t
 * @param {{ minYear?: number, now?: Date }} [options]
 * @returns {Record<string, string>}
 */
export function seriesErrors(f, t, { minYear = 1800, now = new Date() } = {}) {
    /** @type {Record<string, string>} */
    const e = {};
    if (f.mode === 'correction' && !f.material_slug)
        e.material_slug = t('proposals.series.errors.material');
    if (f.mode === 'new') {
        if (text(f.name).length < LIMITS.seriesName[0]) e.name = t('proposals.series.errors.name');
        if (!f.type) e.type = t('proposals.series.errors.type');
    }
    const maxYear = now.getFullYear() + 30;
    const years = {};
    for (const key of ['in_service_from', 'in_service_to']) {
        const raw = text(f[key]);
        if (!raw) continue;
        const year = Number(raw);
        if (!/^\d{4}$/.test(raw) || year < minYear || year > maxYear)
            e[key] = t('proposals.series.errors.year', { min: minYear });
        else years[key] = year;
    }
    if (years.in_service_from && years.in_service_to && years.in_service_to < years.in_service_from)
        e.in_service_to = t('proposals.series.errors.years_order');
    if (text(f.wikidata_id) && !normalizeQid(f.wikidata_id))
        e.wikidata_id = t('proposals.series.errors.qid_format');
    return { ...e, ...sourceErrors(f, t) };
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
    return p.line_name ?? p.station_name ?? p.material_name ?? p.title ?? p.commons?.title ?? '';
}

/**
 * Source d'une proposition en fait affichable : titre (lien vers l'adresse quand il y en a une),
 * nature et licence en complément.
 * @param {{ kind?: string, title?: string, url?: string|null, license?: string|null }|null} source
 * @param {(key: string, opts?: object) => any} t
 */
export function sourceFact(source, t) {
    const s = source ?? {};
    const note = [
        s.kind ? translated(t, `proposals.event.source_kinds.${s.kind}`, s.kind) : null,
        s.license ? translated(t, `proposals.licenses.${s.license}`, s.license) : null,
    ].filter(Boolean);
    return {
        id: 'source',
        label: t('proposals.review.source'),
        text: s.title ?? '',
        href: isWebUrl(s.url) ? s.url : null,
        note: note.join(', ') || null,
    };
}

/**
 * Faits d'une proposition « matériel sur une ligne » : série, ligne, circulation, début et fin de
 * la période, portion parcourue, source, phrase citée.
 * @param {object} p charge utile
 * @param {(key: string, opts?: object) => any} t
 * @param {string} lang
 */
export function lineMaterialFacts(p, t, lang) {
    const period = p.period ?? null;
    const date = (bound) =>
        bound ? (formatStationDate(bound.date, bound.precision, null, t, lang) ?? '') : null;
    return [
        { id: 'series', label: t('proposals.review.series'), text: p.material_name ?? '' },
        {
            id: 'line',
            label: t('proposals.review.line'),
            text: [p.line_name, p.line_code].filter(Boolean).join(' '),
        },
        {
            id: 'circulation',
            label: t('proposals.review.circulation'),
            text: translated(
                t,
                `proposals.line_material.circulations.${p.circulation}`,
                p.circulation ?? '',
            ),
        },
        period?.start && {
            id: 'start',
            label: t('proposals.review.start'),
            text: date(period.start),
        },
        period?.end && { id: 'end', label: t('proposals.review.end'), text: date(period.end) },
        period?.from && {
            id: 'section',
            label: t('proposals.review.section'),
            text: t('proposals.review.places_range', { from: period.from, to: period.to }),
        },
        sourceFact(p.source, t),
        p.quote && { id: 'quote', label: t('proposals.review.quote'), text: p.quote },
    ].filter(Boolean);
}

/**
 * Libellé d'un écartement : celui de la liste courante (« 1 435 mm (voie normale) »), sinon la
 * largeur seule (« 891 mm »).
 * @param {number|string} mm
 * @param {(key: string, opts?: object) => any} t
 */
export function gaugeLabel(mm, t) {
    return translated(
        t,
        `proposals.series.gauges.${mm}`,
        String(t('proposals.series.gauge_other', { mm })),
    );
}

/**
 * Valeur d'un champ de série en clair : catégorie, écartements, systèmes d'électrification.
 * @param {string} field
 * @param {any} value
 * @param {(key: string, opts?: object) => any} t
 */
export function seriesValueText(field, value, t) {
    if (
        value === null ||
        value === undefined ||
        value === '' ||
        (Array.isArray(value) && !value.length)
    )
        return t('proposals.series.none');
    if (field === 'type') return translated(t, `materials.type.${value}`, value);
    if (field === 'gauge_mm') return gaugeLabel(value, t);
    if (field === 'gauges') return value.map((mm) => gaugeLabel(mm, t)).join(', ');
    if (field === 'electrification')
        return value.map((k) => translated(t, `proposals.series.systems.${k}`, k)).join(', ');
    return String(value);
}

/** Ordre d'affichage des champs d'une série (MaterialSeriesContributions::FIELDS). */
export const SERIES_FIELDS = [
    'name',
    'type',
    'builder',
    'in_service_from',
    'in_service_to',
    'gauges',
    'electrification',
    'wikidata_id',
];

/** Champs qu'une correction peut effacer (MaterialSeriesContributions::CLEARABLE). */
export const SERIES_CLEARABLE = [
    'builder',
    'in_service_from',
    'in_service_to',
    'gauges',
    'electrification',
];

/**
 * Comparaison de deux valeurs de série (listes comprises, sans tenir compte de l'ordre).
 * @param {any} a
 * @param {any} b
 */
function sameValue(a, b) {
    if (Array.isArray(a) || Array.isArray(b)) {
        const left = [...(a ?? [])].map(String).sort();
        const right = [...(b ?? [])].map(String).sort();
        return left.length === right.length && left.every((v, i) => v === right[i]);
    }
    return (a ?? null) === (b ?? null);
}

/**
 * Ce que le formulaire envoie pour une correction : seulement les champs changés, et dans `clear`
 * les champs que l'auteur a vidés alors que la fiche avait une valeur (effacement explicite). Un
 * champ inchangé n'est pas renvoyé : l'identifiant Wikidata de la fiche, par exemple, ne fait pas
 * appeler Wikidata.
 * @param {Record<string, any>} current valeurs actuelles de la fiche
 * @param {Record<string, any>} entered valeurs saisies (vides : null ou liste vide)
 * @returns {{ values: Record<string, any>, clear: string[] }}
 */
export function seriesCorrection(current, entered) {
    /** @type {Record<string, any>} */
    const values = {};
    const clear = [];
    for (const field of SERIES_FIELDS) {
        const now = current[field] ?? null;
        const next = entered[field] ?? null;
        const empty = next === null || (Array.isArray(next) && !next.length);
        const hadValue = now !== null && !(Array.isArray(now) && !now.length);
        if (empty) {
            if (hadValue && SERIES_CLEARABLE.includes(field)) clear.push(field);
        } else if (!sameValue(now, next)) {
            values[field] = next;
        }
    }
    return { values, clear };
}

/**
 * Lignes « champ, avant, après » d'une proposition de série : les valeurs données pour une
 * nouvelle série (avant vide), les changements d'une correction.
 * @param {object} p charge utile
 * @param {(key: string, opts?: object) => any} t
 * @returns {{ field: string, label: string, before: string|null, after: string, cleared: boolean }[]}
 */
export function seriesChanges(p, t) {
    const isNew = p.mode === 'new';
    const source = isNew ? (p.values ?? {}) : (p.changes ?? {});
    return SERIES_FIELDS.filter((f) => source[f] !== undefined).map((field) => {
        // Correction qui efface une valeur : montrée comme un effacement, pas comme « non renseigné ».
        const cleared = !isNew && source[field].after === null;
        return {
            field,
            label: t(`proposals.review.fields.${field}`),
            before: isNew ? null : seriesValueText(field, source[field].before, t),
            after: cleared
                ? String(t('proposals.review.cleared'))
                : seriesValueText(field, isNew ? source[field] : source[field].after, t),
            cleared,
        };
    });
}

/**
 * Faits d'une proposition de série : nature (nouvelle série, correction), série, élément Wikidata
 * vérifié, source. Les valeurs proposées sont dans seriesChanges().
 * @param {object} p charge utile
 * @param {(key: string, opts?: object) => any} t
 */
export function seriesFacts(p, t) {
    return [
        {
            id: 'mode',
            label: t('proposals.review.mode'),
            text: t(
                p.mode === 'new'
                    ? 'proposals.review.kind_series_new'
                    : 'proposals.review.kind_series_correction',
            ),
        },
        { id: 'series', label: t('proposals.review.series'), text: p.material_name ?? '' },
        p.wikidata?.id && {
            id: 'wikidata',
            label: t('proposals.review.wikidata_item'),
            text: [p.wikidata.id, p.wikidata.label].filter(Boolean).join(', '),
            href: `https://www.wikidata.org/wiki/${p.wikidata.id}`,
            note: p.wikidata.description ?? null,
        },
        sourceFact(p.source, t),
    ].filter(Boolean);
}

/**
 * Clé du libellé de la nature d'une proposition (espace de validation, « Mes contributions »).
 * @param {{ type: string, payload?: any }} c
 */
export function proposalKindKey(c) {
    const p = c.payload ?? {};
    if (c.type === EVENT_TYPE) return 'proposals.review.kind_event';
    if (c.type === MEDIA_TYPE)
        return p.source === 'commons'
            ? 'proposals.review.kind_media_commons'
            : 'proposals.review.kind_media_upload';
    if (c.type === LINE_MATERIAL_TYPE) return 'proposals.review.kind_line_material';
    if (c.type === SERIES_TYPE)
        return p.mode === 'new'
            ? 'proposals.review.kind_series_new'
            : 'proposals.review.kind_series_correction';
    return null;
}

/**
 * Faits d'une proposition sourcée selon son type (événement, image, matériel, série).
 * @param {{ type: string, payload?: any }} c
 * @param {(key: string, opts?: object) => any} t
 * @param {string} lang
 */
export function proposalFacts(c, t, lang) {
    const p = c.payload ?? {};
    if (c.type === EVENT_TYPE) return eventFacts(p, t, lang);
    if (c.type === MEDIA_TYPE) return mediaFacts(p, t, lang);
    if (c.type === LINE_MATERIAL_TYPE) return lineMaterialFacts(p, t, lang);
    if (c.type === SERIES_TYPE) return seriesFacts(p, t);
    return [];
}

/** Types de proposition sourcée relus par ProposalReview. */
export const PROPOSAL_TYPES = [EVENT_TYPE, MEDIA_TYPE, LINE_MATERIAL_TYPE, SERIES_TYPE];
