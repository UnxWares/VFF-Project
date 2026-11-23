import { describe, expect, it } from 'vitest';
import {
    absoluteUrl,
    breadcrumbSchema,
    compact,
    imageObject,
    lineSchema,
    ogLocale,
    serializeJsonLd,
    shorten,
    stationSchema,
    websiteSchema,
    communeSchema,
    datasetSchema,
    wikidataUrl,
    wikipediaUrl,
} from '../../resources/js/lib/structuredData.js';

const ORIGIN = 'https://vff-project.org';

// Média réel de la base (API /api/v1/lines/rfn-956000/media).
const MEDIA = {
    id: 186,
    title: 'Paris-vincennes 1859 jms',
    caption: "Map of the just-opened 'Paris à Vincennes (et Saint-Maur)' line",
    thumb_url: 'https://thumb.wikimedia.org/a/500px-x.png',
    display_url: 'https://thumb.wikimedia.org/a/1280px-x.png',
    source_url: 'https://commons.wikimedia.org/wiki/File:Paris-vincennes_1859_jms.png',
    width: 7176,
    height: 6056,
    author: 'ThePromenader',
    attribution: 'ThePromenader, Public domain, via Wikimedia Commons',
    license: 'Public domain',
    license_url: null,
};

describe('compact', () => {
    it('retire les valeurs vides, récursivement', () => {
        expect(
            compact({ a: 1, b: null, c: '', d: [], e: { f: undefined }, g: [null, 'x'], h: 0 }),
        ).toEqual({ a: 1, g: ['x'], h: 0 });
    });
});

describe('serializeJsonLd', () => {
    it('échappe « < » pour qu’aucun texte ne ferme la balise script', () => {
        const json = serializeJsonLd({ name: '</script><script>alert(1)</script>' });
        expect(json).not.toContain('<');
        expect(JSON.parse(json).name).toBe('</script><script>alert(1)</script>');
    });
});

describe('adresses', () => {
    it('rend une adresse absolue', () => {
        expect(absoluteUrl(ORIGIN, '/lines/rfn-956000')).toBe(
            'https://vff-project.org/lines/rfn-956000',
        );
    });
    it('n’accepte qu’un identifiant Wikidata valide', () => {
        expect(wikidataUrl('Q3239771')).toBe('https://www.wikidata.org/wiki/Q3239771');
        expect(wikidataUrl('3239771')).toBeNull();
        expect(wikidataUrl(null)).toBeNull();
    });
    it('construit l’adresse d’un article de Wikipédia en français', () => {
        expect(wikipediaUrl('Ligne de Paris-Bastille à Marles-en-Brie')).toBe(
            'https://fr.wikipedia.org/wiki/Ligne_de_Paris-Bastille_%C3%A0_Marles-en-Brie',
        );
        expect(wikipediaUrl(null)).toBeNull();
    });
});

describe('shorten', () => {
    it('garde un texte court tel quel', () => {
        expect(shorten('Ligne  de\nVincennes')).toBe('Ligne de Vincennes');
    });
    it('coupe un texte long sur un mot', () => {
        const out = shorten('mot '.repeat(100), 50);
        expect(out.length).toBeLessThanOrEqual(50);
        expect(out.endsWith('mot…')).toBe(true);
    });
});

describe('websiteSchema', () => {
    const data = websiteSchema({
        origin: ORIGIN,
        name: 'Voies Ferrées de France',
        alternateName: 'VFF',
        description: 'Encyclopédie',
        lang: 'fr',
    });
    const [site, org] = data['@graph'];

    it('décrit le site et sa recherche vers la page de résultats', () => {
        expect(site['@type']).toBe('WebSite');
        expect(site.url).toBe('https://vff-project.org/');
        expect(site.potentialAction.target.urlTemplate).toBe(
            'https://vff-project.org/search?q={search_term_string}',
        );
        expect(site.potentialAction['query-input']).toBe('required name=search_term_string');
        expect(site.publisher['@id']).toBe(org['@id']);
    });
    it('nomme UnxWares comme éditeur, avec l’icône du site pour logo', () => {
        expect(org).toMatchObject({
            '@type': 'Organization',
            name: 'UnxWares',
            legalName: 'UnxWares SAS',
        });
        expect(org.logo).toEqual({
            '@type': 'ImageObject',
            url: 'https://vff-project.org/icons/icon-512.png',
            width: 512,
            height: 512,
        });
    });
});

describe('breadcrumbSchema', () => {
    it('numérote les étapes à partir de 1, en adresses absolues', () => {
        const data = breadcrumbSchema(ORIGIN, [
            { name: 'Voies Ferrées de France', path: '/' },
            { name: 'Lignes', path: '/lines' },
        ]);
        expect(data.itemListElement).toEqual([
            {
                '@type': 'ListItem',
                position: 1,
                name: 'Voies Ferrées de France',
                item: `${ORIGIN}/`,
            },
            { '@type': 'ListItem', position: 2, name: 'Lignes', item: `${ORIGIN}/lines` },
        ]);
    });
});

describe('imageObject', () => {
    it('reprend auteur, crédit et page source du média', () => {
        const img = compact(imageObject(MEDIA));
        expect(img).toMatchObject({
            '@type': 'ImageObject',
            contentUrl: MEDIA.display_url,
            thumbnailUrl: MEDIA.thumb_url,
            creator: { '@type': 'Person', name: 'ThePromenader' },
            creditText: MEDIA.attribution,
            acquireLicensePage: MEDIA.source_url,
        });
    });
    it('n’invente pas d’adresse de licence : nom de la licence seulement', () => {
        const img = compact(imageObject(MEDIA));
        expect(img.license).toBeUndefined();
        expect(img.copyrightNotice).toBe('Public domain');
    });
    it('donne l’adresse de licence quand la base en a une', () => {
        const img = compact(
            imageObject({
                ...MEDIA,
                license: 'CC BY-SA 4.0',
                license_url: 'https://creativecommons.org/licenses/by-sa/4.0/',
            }),
        );
        expect(img.license).toBe('https://creativecommons.org/licenses/by-sa/4.0/');
        expect(img.copyrightNotice).toBeUndefined();
    });
    it('raccourcit une légende de Commons très longue', () => {
        const img = imageObject({ ...MEDIA, caption: 'mot '.repeat(200) });
        expect(img.caption.length).toBeLessThanOrEqual(300);
    });
    it('ignore un média sans image', () => {
        expect(imageObject({ title: 'x' })).toBeNull();
    });
});

describe('lineSchema', () => {
    const line = {
        slug: 'rfn-956000',
        name: 'Ligne de Paris-Bastille à Marles-en-Brie',
        alt_names: ['Ligne de Vincennes'],
        code: '956000',
        wikidata: 'Q3239771',
        wikipedia_title: 'Ligne de Paris-Bastille à Marles-en-Brie',
        regions: ['Île-de-France'],
    };

    it('décrit la ligne comme un lieu typé « ligne de chemin de fer »', () => {
        const data = compact(lineSchema({ origin: ORIGIN, line, images: [MEDIA] }));
        expect(data['@type']).toBe('Place');
        expect(data.additionalType).toBe('https://www.wikidata.org/wiki/Q728937');
        expect(data.url).toBe(`${ORIGIN}/lines/rfn-956000`);
        expect(data.identifier.value).toBe('956000');
        expect(data.alternateName).toEqual(['Ligne de Vincennes']);
        expect(data.sameAs).toHaveLength(2);
        expect(data.containedInPlace[0].name).toBe('Île-de-France');
        expect(data.image[0].creator.name).toBe('ThePromenader');
    });
    it('n’écrit rien pour une donnée absente', () => {
        const data = compact(lineSchema({ origin: ORIGIN, line: { slug: 'x', name: 'Ligne X' } }));
        expect(Object.keys(data).sort()).toEqual(
            ['@context', '@id', '@type', 'additionalType', 'name', 'url'].sort(),
        );
    });
});

describe('lineSchema : terminus, longueur, ligne touristique', () => {
    const line = {
        slug: 'rfn-1',
        name: 'Ligne touristique',
        current_traffic: 'heritage',
        facts: [
            { key: 'length', scope: 'item', km: 409 },
            { key: 'length', scope: 'line', km: 12.5 },
        ],
    };
    const stations = [
        { slug: 'a', name: 'Départ', is_terminal: true },
        { slug: 'm', name: 'Milieu', is_terminal: false },
        { slug: 'z', name: 'Arrivée', is_terminal: true },
    ];
    it('cite les gares terminus et la longueur de la ligne entière', () => {
        const data = compact(
            lineSchema({ origin: ORIGIN, line, stations, lengthLabel: 'Longueur' }),
        );
        expect(data['@type']).toBe('TouristAttraction');
        expect(data.containsPlace.map((s) => s.name)).toEqual(['Départ', 'Arrivée']);
        expect(data.containsPlace[0].url).toBe(`${ORIGIN}/stations/a`);
        expect(data.additionalProperty).toMatchObject({
            name: 'Longueur',
            value: 12.5,
            unitCode: 'KMT',
        });
    });
    it('reste un lieu quand le trafic n’est pas touristique, sans longueur sans fait', () => {
        const data = compact(
            lineSchema({ origin: ORIGIN, line: { ...line, current_traffic: 'mixed', facts: [] } }),
        );
        expect(data['@type']).toBe('Place');
        expect(data.additionalProperty).toBeUndefined();
        expect(data.containsPlace).toBeUndefined();
    });
});

describe('communeSchema', () => {
    it('décrit la ville, son code INSEE, son département et ses gares', () => {
        const data = compact(
            communeSchema({
                origin: ORIGIN,
                commune: {
                    slug: '93010-bondy',
                    name: 'Bondy',
                    insee: '93010',
                    department_name: 'Seine-Saint-Denis',
                },
                stations: [{ slug: 'bondy', name: 'Bondy' }],
            }),
        );
        expect(data['@type']).toBe('City');
        expect(data.identifier.value).toBe('93010');
        expect(data.containedInPlace.name).toBe('Seine-Saint-Denis');
        expect(data.containsPlace[0].url).toBe(`${ORIGIN}/stations/bondy`);
    });
    it('cite 20 gares au plus', () => {
        const stations = Array.from({ length: 30 }, (_, i) => ({ slug: `s${i}`, name: `S${i}` }));
        expect(
            communeSchema({ origin: ORIGIN, commune: { slug: 'x', name: 'X' }, stations })
                .containsPlace,
        ).toHaveLength(20);
    });
});

describe('datasetSchema', () => {
    const datasets = [
        {
            kind: 'events',
            generated_on: '2026-11-20',
            archive: { url: '/downloads/open-data/events.zip', bytes: 1000 },
            files: [
                { name: 'events.csv', url: '/downloads/open-data/events/events.csv', bytes: 4000 },
            ],
        },
        {
            kind: 'network',
            era: 2030,
            archive: { url: '/downloads/open-data/n.zip', bytes: 2000 },
            files: [
                { name: 'lines.geojson', url: '/downloads/open-data/n/lines.geojson', bytes: 9000 },
            ],
        },
    ];
    const data = compact(
        datasetSchema({
            origin: ORIGIN,
            datasets,
            licence: { url: 'https://opendatacommons.org/licenses/odbl/1-0/' },
            name: 'Données ouvertes',
            description: 'Description',
            lang: 'fr',
            siteName: 'Voies Ferrées de France',
            partName: (d) => d.kind,
            partDescription: (d) => `desc ${d.kind}`,
        }),
    );
    it('est sous licence ODbL, avec un jeu par entrée et ses fichiers', () => {
        expect(data['@type']).toBe('Dataset');
        expect(data.license).toBe('https://opendatacommons.org/licenses/odbl/1-0/');
        expect(data.isAccessibleForFree).toBe(true);
        expect(data.hasPart).toHaveLength(2);
        expect(data.hasPart[0].distribution[0].contentUrl).toBe(
            `${ORIGIN}/downloads/open-data/events.zip`,
        );
        expect(data.hasPart[0].distribution[1].encodingFormat).toBe('text/csv');
        expect(data.hasPart[1].distribution[1].encodingFormat).toBe('application/geo+json');
        expect(data.creator.legalName).toBe('UnxWares SAS');
    });
});

describe('stationSchema', () => {
    const station = {
        slug: 'abancourt',
        name: 'Abancourt',
        alt_names: [{ name: 'Abancourt (Oise)', source: 'wikidata' }],
        city: 'Abancourt',
        uic_code: '87313759',
        wikidata_id: 'Q2136489',
        lat: 49.69,
        lng: 1.77,
        position_flagged: false,
    };

    it('décrit la gare comme TrainStation avec position, commune et code UIC', () => {
        const data = compact(stationSchema({ origin: ORIGIN, station }));
        expect(data['@type']).toBe('TrainStation');
        expect(data.geo).toEqual({ '@type': 'GeoCoordinates', latitude: 49.69, longitude: 1.77 });
        expect(data.address).toEqual({
            '@type': 'PostalAddress',
            addressLocality: 'Abancourt',
            addressCountry: 'FR',
        });
        expect(data.identifier).toEqual({
            '@type': 'PropertyValue',
            propertyID: 'UIC',
            value: '87313759',
        });
        expect(data.alternateName).toEqual(['Abancourt (Oise)']);
        expect(data.sameAs).toEqual(['https://www.wikidata.org/wiki/Q2136489']);
    });
    it('ne donne pas pour sûre une position signalée comme douteuse', () => {
        const data = compact(
            stationSchema({ origin: ORIGIN, station: { ...station, position_flagged: true } }),
        );
        expect(data.geo).toBeUndefined();
    });
});

describe('ogLocale', () => {
    it('traduit la langue de la page', () => {
        expect(ogLocale('fr')).toBe('fr_FR');
        expect(ogLocale('en')).toBe('en_GB');
        expect(ogLocale('de')).toBe('de_DE');
        expect(ogLocale('xx')).toBe('fr_FR');
    });
});
