// Données structurées schema.org (JSON-LD) et balises de partage (Open Graph, Twitter) des pages
// publiques : accueil, fiches ligne et gare. Fonctions pures, testées par
// tests/js/structuredData.test.js ; le composant Components/SeoHead.svelte les pose dans <head>.
//
// Règle : rien d'inventé. Une propriété n'est écrite que si la donnée existe dans la base (pas de
// licence devinée, pas de coordonnées approchées, pas de logo d'emprunt) ; une valeur vide est
// retirée de l'objet plutôt qu'écrite à null.
import { mediaCaption } from './mediaCredits.js';

/** Éditeur et hébergeur de VFF (mentions légales). */
export const PUBLISHER = Object.freeze({
    '@type': 'Organization',
    name: 'UnxWares',
    legalName: 'UnxWares SAS',
    url: 'https://www.unxwares.com',
});

/** Logo de l'éditeur : l'icône du site (public/icons), carrée, 512 px. */
const LOGO_PATH = '/icons/icon-512.png';
const LOGO_SIZE = 512;

/** Gares d'une commune citées dans ses données structurées. */
const COMMUNE_STATIONS = 20;

/** Élément Wikidata « ligne de chemin de fer », type précis d'une fiche ligne (schema.org n'en a pas). */
const WIKIDATA_RAILWAY_LINE = 'https://www.wikidata.org/wiki/Q728937';

/**
 * Retire les clés vides (undefined, null, chaîne vide, tableau vide), récursivement.
 * @param {any} value
 * @returns {any}
 */
export function compact(value) {
    if (Array.isArray(value)) {
        return value.map(compact).filter((v) => !isEmpty(v));
    }
    if (value && typeof value === 'object') {
        const out = {};
        for (const [k, v] of Object.entries(value)) {
            const c = compact(v);
            if (!isEmpty(c)) out[k] = c;
        }
        return out;
    }
    return value;
}

function isEmpty(v) {
    return (
        v === undefined ||
        v === null ||
        v === '' ||
        (Array.isArray(v) && v.length === 0) ||
        (typeof v === 'object' && !Array.isArray(v) && Object.keys(v).length === 0)
    );
}

/**
 * Texte JSON prêt pour une balise <script type="application/ld+json"> : « < » échappé, pour qu'un
 * texte de la base (« </script> ») ne puisse jamais fermer la balise.
 * @param {object|object[]} data
 */
export function serializeJsonLd(data) {
    return JSON.stringify(compact(data)).replace(/</g, '\\u003c');
}

/**
 * Adresse absolue à partir d'un chemin du site.
 * @param {string} origin ex. « https://vff-project.org »
 * @param {string} path ex. « /lines/rfn-570000 »
 */
export function absoluteUrl(origin, path) {
    return new URL(path, origin).href;
}

/** Identifiant Wikidata valide (« Q12345 ») en adresse, sinon null. */
export function wikidataUrl(id) {
    return typeof id === 'string' && /^Q\d+$/.test(id.trim())
        ? `https://www.wikidata.org/wiki/${id.trim()}`
        : null;
}

/** Article de Wikipédia en français (les extraits des fiches en viennent). */
export function wikipediaUrl(title) {
    if (!title) return null;
    return `https://fr.wikipedia.org/wiki/${encodeURIComponent(String(title).replace(/ /g, '_'))}`;
}

/** Texte ramené à `max` caractères au plus, coupé sur un mot, avec « … ». */
export function shorten(text, max = 300) {
    if (!text) return null;
    const flat = String(text).replace(/\s+/g, ' ').trim();
    if (flat.length <= max) return flat;
    const cut = flat.slice(0, max - 1);
    const space = cut.lastIndexOf(' ');
    return `${(space > max * 0.6 ? cut.slice(0, space) : cut).replace(/[\s,;:.]+$/, '')}…`;
}

/**
 * Éditeur de VFF, avec son logo (adresse absolue).
 * @param {string} origin
 */
export function publisher(origin) {
    return {
        ...PUBLISHER,
        '@id': absoluteUrl(origin, '/#publisher'),
        logo: {
            '@type': 'ImageObject',
            url: absoluteUrl(origin, LOGO_PATH),
            width: LOGO_SIZE,
            height: LOGO_SIZE,
        },
    };
}

/**
 * Accueil : le site (avec sa recherche, qui mène à la page de résultats) et son éditeur.
 * @param {{ origin: string, name: string, alternateName?: string, description?: string, lang: string }} o
 */
export function websiteSchema({ origin, name, alternateName, description, lang }) {
    return {
        '@context': 'https://schema.org',
        '@graph': [
            {
                '@type': 'WebSite',
                '@id': absoluteUrl(origin, '/#website'),
                url: absoluteUrl(origin, '/'),
                name,
                alternateName,
                description,
                inLanguage: lang,
                publisher: { '@id': absoluteUrl(origin, '/#publisher') },
                potentialAction: {
                    '@type': 'SearchAction',
                    target: {
                        '@type': 'EntryPoint',
                        urlTemplate: `${absoluteUrl(origin, '/search')}?q={search_term_string}`,
                    },
                    'query-input': 'required name=search_term_string',
                },
            },
            publisher(origin),
        ],
    };
}

/**
 * Fil d'Ariane.
 * @param {string} origin
 * @param {Array<{ name: string, path: string }>} items du plus général au plus précis
 */
export function breadcrumbSchema(origin, items) {
    return {
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: items.map((it, i) => ({
            '@type': 'ListItem',
            position: i + 1,
            name: it.name,
            item: absoluteUrl(origin, it.path),
        })),
    };
}

/**
 * Média de la base (ressource MediaResource de l'API) en ImageObject, avec auteur et licence tels
 * que la base les donne. Null sans image affichable.
 * @param {Record<string, any>} m
 * @param {string|null} [lang] langue de la page : la légende de cette langue quand elle existe (`captions`)
 */
export function imageObject(m, lang = null) {
    const url = m?.display_url ?? m?.thumb_url;
    if (!url) return null;
    return {
        '@type': 'ImageObject',
        contentUrl: url,
        thumbnailUrl: m.thumb_url !== url ? m.thumb_url : undefined,
        name: m.title,
        caption: shorten(mediaCaption(m, lang)),
        width: m.width || undefined,
        height: m.height || undefined,
        creator: m.author ? { '@type': 'Person', name: m.author } : undefined,
        creditText: m.attribution,
        // `license` attend une adresse : seulement quand la base en a une (pas de lien deviné).
        license: m.license_url,
        copyrightNotice: m.license && !m.license_url ? m.license : undefined,
        acquireLicensePage: m.source_url,
    };
}

/**
 * Fiche ligne : un lieu (Place, ou TouristAttraction pour une ligne au trafic touristique) précisé par
 * l'élément Wikidata « ligne de chemin de fer », avec ses gares terminus (`containsPlace`) et sa
 * longueur officielle quand elle décrit la ligne entière (`additionalProperty`, en km).
 * @param {{ origin: string, line: Record<string, any>, description?: string|null, images?: Array<any>, stations?: Array<any>, lengthLabel?: string, lang?: string|null }} o
 */
export function lineSchema({
    origin,
    line,
    description = null,
    images = [],
    stations = [],
    lengthLabel = 'Longueur officielle (km)',
    lang = null,
}) {
    const url = absoluteUrl(origin, `/lines/${line.slug}`);
    const length = (line.facts ?? []).find(
        (f) => f.key === 'length' && f.scope === 'line' && Number.isFinite(f.km),
    )?.km;
    return {
        '@context': 'https://schema.org',
        '@type': line.current_traffic === 'heritage' ? 'TouristAttraction' : 'Place',
        '@id': `${url}#line`,
        additionalType: WIKIDATA_RAILWAY_LINE,
        url,
        name: line.name,
        alternateName: (line.alt_names ?? []).filter((n) => n && n !== line.name),
        description: shorten(description),
        identifier: line.code
            ? { '@type': 'PropertyValue', propertyID: 'Code de ligne RFN', value: line.code }
            : undefined,
        sameAs: [wikidataUrl(line.wikidata), wikipediaUrl(line.wikipedia_title)].filter(Boolean),
        containedInPlace: (line.regions ?? []).map((r) => ({
            '@type': 'AdministrativeArea',
            name: typeof r === 'string' ? r : r?.name,
        })),
        containsPlace: stations
            .filter((s) => s?.is_terminal && s.slug && s.name)
            .map((s) => ({
                '@type': 'TrainStation',
                name: s.name,
                url: absoluteUrl(origin, `/stations/${s.slug}`),
            })),
        additionalProperty:
            length === undefined
                ? undefined
                : {
                      '@type': 'PropertyValue',
                      name: lengthLabel,
                      value: length,
                      unitCode: 'KMT',
                      unitText: 'km',
                  },
        image: images.map((m) => imageObject(m, lang)).filter(Boolean),
    };
}

/**
 * Fiche gare : TrainStation, avec sa position et sa commune.
 * @param {{ origin: string, station: Record<string, any>, description?: string|null }} o
 */
export function stationSchema({ origin, station, description = null }) {
    const url = absoluteUrl(origin, `/stations/${station.slug}`);
    // Position signalée comme douteuse (sources éloignées) : pas de coordonnées données pour sûres.
    const hasGeo =
        Number.isFinite(station.lat) && Number.isFinite(station.lng) && !station.position_flagged;
    return {
        '@context': 'https://schema.org',
        '@type': 'TrainStation',
        '@id': `${url}#station`,
        url,
        name: station.name,
        alternateName: (station.alt_names ?? [])
            .map((n) => (typeof n === 'string' ? n : n?.name))
            .filter((n) => n && n !== station.name),
        description: shorten(description),
        identifier: station.uic_code
            ? { '@type': 'PropertyValue', propertyID: 'UIC', value: String(station.uic_code) }
            : undefined,
        geo: hasGeo
            ? { '@type': 'GeoCoordinates', latitude: station.lat, longitude: station.lng }
            : undefined,
        address: station.city
            ? {
                  '@type': 'PostalAddress',
                  addressLocality: station.city,
                  addressCountry: 'FR',
              }
            : undefined,
        sameAs: [wikidataUrl(station.wikidata_id)].filter(Boolean),
    };
}

/**
 * Fiche de commune : la ville (City), son département, son code INSEE et ses gares.
 * @param {{ origin: string, commune: Record<string, any>, stations?: Array<any> }} o
 */
export function communeSchema({ origin, commune, stations = [] }) {
    const url = absoluteUrl(origin, `/communes/${commune.slug}`);
    return {
        '@context': 'https://schema.org',
        '@type': 'City',
        '@id': `${url}#commune`,
        url,
        name: commune.name,
        identifier: commune.insee
            ? { '@type': 'PropertyValue', propertyID: 'INSEE', value: String(commune.insee) }
            : undefined,
        containedInPlace: commune.department_name
            ? { '@type': 'AdministrativeArea', name: commune.department_name }
            : undefined,
        address: { '@type': 'PostalAddress', addressLocality: commune.name, addressCountry: 'FR' },
        containsPlace: stations
            .filter((s) => s?.slug && s.name)
            .slice(0, COMMUNE_STATIONS)
            .map((s) => ({
                '@type': 'TrainStation',
                name: s.name,
                url: absoluteUrl(origin, `/stations/${s.slug}`),
            })),
    };
}

/**
 * Jeu de données ouvert (/open-data) : le catalogue sous licence ODbL, un jeu par entrée (`hasPart`)
 * et ses fichiers à télécharger (`distribution`). Aucune source n'est nommée.
 * @param {{ origin: string, datasets: Array<any>, licence?: { url?: string }, name: string, description: string, lang: string, keywords?: string, siteName: string, partName: (d: any) => string, partDescription: (d: any) => string }} o
 */
export function datasetSchema({
    origin,
    datasets,
    licence = {},
    name,
    description,
    lang,
    keywords,
    siteName,
    partName,
    partDescription,
}) {
    const url = absoluteUrl(origin, '/open-data');
    const licenceUrl = licence.url;
    const download = (path, bytes, format) => ({
        '@type': 'DataDownload',
        contentUrl: absoluteUrl(origin, path),
        encodingFormat: format,
        contentSize: bytes ? `${bytes} B` : undefined,
    });
    const pub = publisher(origin);
    return {
        '@context': 'https://schema.org',
        '@type': 'Dataset',
        '@id': `${url}#dataset`,
        url,
        name,
        description,
        license: licenceUrl,
        isAccessibleForFree: true,
        inLanguage: lang,
        creator: pub,
        publisher: pub,
        isPartOf: { '@type': 'WebSite', name: siteName, url: absoluteUrl(origin, '/') },
        keywords,
        hasPart: datasets.map((d) => ({
            '@type': 'Dataset',
            name: partName(d),
            description: partDescription(d),
            url,
            license: licenceUrl,
            isAccessibleForFree: true,
            inLanguage: lang,
            dateModified: d.generated_on || undefined,
            distribution: [
                download(d.archive?.url ?? '', d.archive?.bytes, 'application/zip'),
                ...(d.files ?? []).map((f) =>
                    download(
                        f.url,
                        f.bytes,
                        f.name.endsWith('.geojson') ? 'application/geo+json' : 'text/csv',
                    ),
                ),
            ],
        })),
    };
}

/** Code de langue de la page en locale Open Graph (« fr » → « fr_FR »). */
export function ogLocale(lang) {
    return { fr: 'fr_FR', en: 'en_GB', de: 'de_DE' }[lang] ?? 'fr_FR';
}
