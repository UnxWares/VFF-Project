// Propositions « événement daté » et « image » (resources/js/Components/Contribute/proposals.js) :
// dates à leur précision, contrôles des formulaires avant l'envoi (mêmes règles que le serveur),
// adresse Wikimedia Commons, affichage des propositions pour les validateurs et l'auteur.
import { describe, expect, it } from 'vitest';
import {
    commonsFileTitle,
    composeDate,
    decimal,
    dimensionsError,
    eventErrors,
    eventFacts,
    fileError,
    formatBytes,
    isValidDate,
    mediaErrors,
    mediaFacts,
    mediaTargetText,
    monthOptions,
    pkMeters,
    portionText,
    proposalHeading,
    removedMetadataText,
} from '../../resources/js/Components/Contribute/proposals.js';

const DICT = {
    'editor.errors.bad_date': 'Date invalide',
    'editor.errors.bad_url': 'Lien invalide',
    'editor.event_types.closure_passenger': 'Fermeture aux voyageurs',
    'editor.event_types.opening': 'Ouverture',
    'editor.event_types.conversion': 'Reconversion de l’emprise',
    'proposals.errors.line_required': 'Ligne ?',
    'proposals.errors.station_required': 'Gare ?',
    'proposals.errors.material_required': 'Série ?',
    'proposals.event.errors.places': 'Deux extrémités',
    'proposals.event.errors.places_same': 'Extrémités identiques',
    'proposals.event.errors.pk': 'Deux PK',
    'proposals.event.errors.pk_order': 'PK dans le désordre',
    'proposals.event.errors.description_other': 'Précision ?',
    'proposals.event.errors.source_title': 'Titre ?',
    'proposals.event.errors.source_url': 'Adresse ?',
    'proposals.event.errors.source_license': 'Licence ?',
    'proposals.event.source_kinds.document': 'Document numérisé',
    'proposals.licenses.public_domain': 'Domaine public',
    'proposals.reuses.greenway': 'Voie verte',
    'proposals.media.errors.event': 'Événement ?',
    'proposals.media.errors.commons_url': 'Adresse Commons ?',
    'proposals.media.errors.file_required': 'Fichier ?',
    'proposals.media.errors.file_type': 'JPEG, PNG ou WebP',
    'proposals.media.errors.file_size': 'Plus de {max} Mo',
    'proposals.media.errors.file_dimensions': 'De {min} à {max} pixels',
    'proposals.media.errors.title': 'Titre ?',
    'proposals.media.errors.author': 'Auteur ?',
    'proposals.media.errors.license': 'Licence ?',
    'proposals.media.errors.coordinates': 'Coordonnées ?',
    'proposals.media.errors.rights': 'Droits ?',
    'proposals.review.type': 'Type',
    'proposals.review.date': 'Date',
    'proposals.review.portion': 'Portion',
    'proposals.review.description': 'Précision',
    'proposals.review.source': 'Source',
    'proposals.review.whole_line': 'Toute la ligne',
    'proposals.review.places_range': 'de {from} à {to}',
    'proposals.review.pk_range': 'du PK {start} au PK {end}',
    'proposals.review.origin': 'Origine',
    'proposals.review.attached_to': 'Fiche',
    'proposals.review.event_of': '{type} du {date}',
    'proposals.review.title': 'Titre',
    'proposals.review.caption': 'Légende',
    'proposals.review.author': 'Auteur',
    'proposals.review.license': 'Licence',
    'proposals.review.captured': 'Prise de vue',
    'proposals.review.place': 'Lieu',
    'proposals.review.coordinates': 'Coordonnées',
    'proposals.review.file': 'Fichier',
    'proposals.review.file_facts': '{width} × {height} pixels, {size}',
    'proposals.review.kind_media_commons': 'Image de Wikimedia Commons',
    'proposals.review.kind_media_upload': 'Image déposée',
    'proposals.review.removed': 'Métadonnées retirées : {list}.',
    'proposals.review.removed_none': 'Aucune métadonnée.',
    'proposals.review.removed_kinds.exif': 'EXIF',
    'proposals.review.removed_kinds.gps': 'position GPS',
};
const t = (key, opts = {}) =>
    (DICT[key] ?? key).replace(/\{(\w+)\}/g, (_, k) => String(opts[k] ?? ''));
const NOW = new Date('2026-11-19T12:00:00Z');
const LIMITS = { max_bytes: 10 * 1024 * 1024, min_side: 400, max_side: 12000 };

describe('dates à leur précision', () => {
    it('compose la date selon la précision', () => {
        expect(composeDate('year', 1890)).toBe('1890');
        expect(composeDate('month', '1890', '6')).toBe('1890-06');
        expect(composeDate('day', 1890, 6, 16)).toBe('1890-06-16');
        expect(composeDate('day', 1890, 6, '')).toBe('');
        expect(composeDate('month', 1890, 13)).toBe('');
        expect(composeDate('year', '189')).toBe('');
    });

    it('refuse les dates qui n’existent pas ou hors bornes', () => {
        expect(isValidDate('1890-06-16', 'day', NOW)).toBe(true);
        expect(isValidDate('1900-02-29', 'day', NOW)).toBe(false);
        expect(isValidDate('2024-02-29', 'day', NOW)).toBe(true);
        expect(isValidDate('1890-06', 'day', NOW)).toBe(false);
        expect(isValidDate('1890-06', 'month', NOW)).toBe(true);
        expect(isValidDate('1890', 'year', NOW)).toBe(true);
        expect(isValidDate('1749', 'year', NOW)).toBe(false);
        expect(isValidDate('2056', 'year', NOW)).toBe(true);
        expect(isValidDate('2057', 'year', NOW)).toBe(false);
        expect(isValidDate('', 'year', NOW)).toBe(false);
    });

    it('nomme les mois dans la langue de la page', () => {
        expect(monthOptions('fr')[5]).toEqual({ value: 6, label: 'juin' });
        expect(monthOptions('en')[0].label).toBe('January');
        expect(monthOptions('de')).toHaveLength(12);
    });

    it('convertit un PK saisi en kilomètres et mètres', () => {
        expect(pkMeters(12, 345)).toBe(12345);
        expect(pkMeters('4', '')).toBe(4000);
        expect(pkMeters('', '')).toBeNull();
    });
});

describe('formulaire « Proposer un événement daté »', () => {
    const valid = {
        line_slug: 'rfn-610000',
        type: 'closure_passenger',
        precision: 'day',
        date: '1938-05-15',
        portion: 'places',
        from: 'Saint-Cloud',
        to: 'Versailles',
        description: '',
        source_kind: 'document',
        source_title: 'Rapport 1938',
        source_url: 'https://gallica.bnf.fr/ark:/1',
        source_license: 'public_domain',
        pk_start_m: null,
        pk_end_m: null,
    };

    it('accepte une proposition complète', () => {
        expect(eventErrors(valid, t, NOW)).toEqual({});
    });

    it('signale chaque règle du serveur sur le bon champ', () => {
        expect(eventErrors({ ...valid, line_slug: '' }, t, NOW)).toEqual({ line_slug: 'Ligne ?' });
        expect(eventErrors({ ...valid, date: '1938-02-30' }, t, NOW)).toEqual({
            date: 'Date invalide',
        });
        expect(eventErrors({ ...valid, to: '' }, t, NOW)).toEqual({ to: 'Deux extrémités' });
        expect(eventErrors({ ...valid, to: 'saint-cloud' }, t, NOW)).toEqual({
            to: 'Extrémités identiques',
        });
        expect(eventErrors({ ...valid, portion: 'pk', pk_start_m: 5000 }, t, NOW)).toEqual({
            pk_end_m: 'Deux PK',
        });
        expect(
            eventErrors({ ...valid, portion: 'pk', pk_start_m: 5000, pk_end_m: 5000 }, t, NOW),
        ).toEqual({
            pk_end_m: 'PK dans le désordre',
        });
        expect(eventErrors({ ...valid, type: 'other' }, t, NOW)).toEqual({
            description: 'Précision ?',
        });
        expect(eventErrors({ ...valid, source_title: 'JO' }, t, NOW)).toEqual({
            source_title: 'Titre ?',
        });
        expect(eventErrors({ ...valid, source_url: '' }, t, NOW)).toEqual({
            source_url: 'Adresse ?',
        });
        expect(eventErrors({ ...valid, source_url: 'ftp://x.org' }, t, NOW)).toEqual({
            source_url: 'Lien invalide',
        });
        expect(eventErrors({ ...valid, source_license: '' }, t, NOW)).toEqual({
            source_license: 'Licence ?',
        });
    });

    it('n’exige ni adresse ni licence pour un ouvrage imprimé', () => {
        const print = { ...valid, source_kind: 'print', source_url: '', source_license: '' };
        expect(eventErrors(print, t, NOW)).toEqual({});
        expect(eventErrors({ ...print, portion: 'whole', from: '', to: '' }, t, NOW)).toEqual({});
    });
});

describe('adresse Wikimedia Commons', () => {
    it.each([
        ['https://commons.wikimedia.org/wiki/File:Gare_de_Nontron.jpg', 'File:Gare de Nontron.jpg'],
        [
            'https://commons.wikimedia.org/wiki/File:Gare_de_Ch%C3%A2teauroux.jpg',
            'File:Gare de Châteauroux.jpg',
        ],
        ['https://commons.m.wikimedia.org/wiki/Fichier:A.png', 'File:A.png'],
        ['https://commons.wikimedia.org/w/index.php?title=File:A_b.jpg&oldid=3', 'File:A b.jpg'],
        [
            'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/X.jpg/500px-X.jpg',
            'File:X.jpg',
        ],
        ['file:petite gare.jpg', 'File:Petite gare.jpg'],
        ['https://fr.wikipedia.org/wiki/Fichier:A.jpg', null],
        ['https://commons.wikimedia.org/wiki/Category:Gares', null],
        ['File:A|B.jpg', null],
        ['', null],
    ])('%s → %s', (input, expected) => {
        expect(commonsFileTitle(input)).toBe(expected);
    });
});

describe('formulaire « Proposer une image »', () => {
    const jpeg = { type: 'image/jpeg', size: 2 * 1024 * 1024 };
    const upload = {
        source: 'upload',
        target_type: 'line',
        line_slug: 'rfn-610000',
        station_slug: '',
        event_id: '',
        commons_url: '',
        file: jpeg,
        title: 'Gare de Nontron',
        author: 'Marie Durand',
        license: 'cc-by-sa-4.0',
        captured_precision: '',
        captured_date: '',
        lat: '',
        lng: '',
        rights_confirmed: true,
    };

    it('contrôle le type et la taille du fichier choisi', () => {
        expect(fileError(null, LIMITS, t)).toBe('Fichier ?');
        expect(fileError({ type: 'image/gif', size: 10 }, LIMITS, t)).toBe('JPEG, PNG ou WebP');
        expect(fileError({ type: 'image/png', size: LIMITS.max_bytes + 1 }, LIMITS, t)).toBe(
            'Plus de 10 Mo',
        );
        expect(fileError(jpeg, LIMITS, t)).toBeNull();
        expect(dimensionsError(300, 900, LIMITS, t)).toBe('De 400 à 12000 pixels');
        expect(dimensionsError(400, 12000, LIMITS, t)).toBeNull();
    });

    it('accepte un envoi complet et signale chaque oubli', () => {
        expect(mediaErrors(upload, LIMITS, t, { width: 1600, height: 1200 }, NOW)).toEqual({});
        expect(mediaErrors({ ...upload, line_slug: '' }, LIMITS, t)).toEqual({
            line_slug: 'Ligne ?',
        });
        expect(mediaErrors({ ...upload, target_type: 'station' }, LIMITS, t)).toEqual({
            station_slug: 'Gare ?',
        });
        expect(mediaErrors({ ...upload, target_type: 'event' }, LIMITS, t)).toEqual({
            event_id: 'Événement ?',
        });
        // Une série de matériel : la série est demandée, pas la ligne.
        expect(
            mediaErrors({ ...upload, target_type: 'material', line_slug: '' }, LIMITS, t),
        ).toEqual({ material_slug: 'Série ?' });
        expect(
            mediaErrors(
                { ...upload, target_type: 'material', line_slug: '', material_slug: 'x-73500' },
                LIMITS,
                t,
                { width: 1600, height: 1200 },
                NOW,
            ),
        ).toEqual({});
        expect(mediaErrors(upload, LIMITS, t, { width: 200, height: 1200 })).toEqual({
            file: 'De 400 à 12000 pixels',
        });
        expect(
            mediaErrors(
                { ...upload, title: 'A', author: '', license: '', rights_confirmed: false },
                LIMITS,
                t,
            ),
        ).toEqual({
            title: 'Titre ?',
            author: 'Auteur ?',
            license: 'Licence ?',
            rights_confirmed: 'Droits ?',
        });
        expect(
            mediaErrors(
                { ...upload, captured_precision: 'day', captured_date: '1972-02-31' },
                LIMITS,
                t,
                null,
                NOW,
            ),
        ).toEqual({ captured_date: 'Date invalide' });
        expect(mediaErrors({ ...upload, lat: '45,52' }, LIMITS, t)).toEqual({
            lng: 'Coordonnées ?',
        });
        expect(mediaErrors({ ...upload, lat: '45,52', lng: '0,66' }, LIMITS, t)).toEqual({});
        expect(mediaErrors({ ...upload, lat: '95', lng: '0' }, LIMITS, t)).toEqual({
            lng: 'Coordonnées ?',
        });
    });

    it('ne demande que l’adresse pour un fichier Commons', () => {
        const commons = {
            ...upload,
            source: 'commons',
            file: null,
            title: '',
            author: '',
            license: '',
        };
        expect(
            mediaErrors(
                { ...commons, commons_url: 'https://commons.wikimedia.org/wiki/File:A.jpg' },
                LIMITS,
                t,
            ),
        ).toEqual({});
        expect(
            mediaErrors({ ...commons, commons_url: 'https://exemple.org/a.jpg' }, LIMITS, t),
        ).toEqual({
            commons_url: 'Adresse Commons ?',
        });
    });

    it('lit les décimales avec une virgule ou un point', () => {
        expect(decimal('45,5292')).toBe(45.5292);
        expect(decimal(' 0.66 ')).toBe(0.66);
        expect(decimal('')).toBeNull();
    });

    it('écrit la taille d’un fichier dans la langue', () => {
        expect(formatBytes(850 * 1024, 'fr')).toBe('850 ko');
        expect(formatBytes(2.4 * 1048576, 'fr')).toBe('2,4 Mo');
        expect(formatBytes(2.4 * 1048576, 'en')).toBe('2.4 MB');
        expect(formatBytes(12 * 1048576, 'de')).toBe('12 MB');
    });
});

describe('affichage des propositions', () => {
    const event = {
        line_name: 'Ligne de Paris à Versailles',
        event_type: 'closure_passenger',
        date: '1938-05-01',
        precision: 'month',
        portion: { kind: 'pk', pk_start_m: 1000, pk_end_m: 4500 },
        description: 'Autocars à la place des trains.',
        source: {
            kind: 'document',
            title: 'Rapport 1938',
            url: 'https://gallica.bnf.fr/x',
            license: 'public_domain',
        },
    };

    it('décrit un événement : type, date à sa précision, portion, source', () => {
        const facts = eventFacts(event, t, 'fr');
        expect(facts.map((f) => f.id)).toEqual([
            'type',
            'date',
            'portion',
            'description',
            'source',
        ]);
        expect(facts[1].text).toBe('mai 1938');
        expect(facts[2].text).toBe('du PK 1+000 au PK 4+500');
        expect(facts[4]).toEqual({
            id: 'source',
            label: 'Source',
            text: 'Rapport 1938',
            href: 'https://gallica.bnf.fr/x',
            note: 'Document numérisé, Domaine public',
        });
        const conversion = eventFacts(
            { ...event, event_type: 'conversion', reuse: 'greenway', description: null },
            t,
            'fr',
        );
        expect(conversion[0].text).toBe('Reconversion de l’emprise, Voie verte');
        expect(conversion.map((f) => f.id)).not.toContain('description');
    });

    it('écrit la portion en clair', () => {
        expect(portionText({ kind: 'whole' }, t)).toBe('Toute la ligne');
        expect(portionText({ kind: 'places', from: 'A', to: 'B' }, t)).toBe('de A à B');
        expect(portionText(null, t)).toBe('Toute la ligne');
    });

    it('décrit une image déposée et sa fiche', () => {
        const upload = {
            source: 'upload',
            target_type: 'event',
            line_name: 'Ligne de Paris à Versailles',
            line_code: '999000',
            event: { id: 7, type: 'opening', date: '1839-08-02', precision: 'day' },
            title: 'Gare de Saint-Cloud',
            author: 'Marie Durand',
            license_name: 'CC BY-SA 4.0',
            license_url: 'https://creativecommons.org/licenses/by-sa/4.0/',
            captured: { date: '1972-01-01', precision: 'year' },
            place: 'Saint-Cloud',
            location: { lat: 48.8445, lng: 2.2196 },
            file: { width: 1200, height: 1600, size: 850 * 1024 },
        };
        expect(mediaTargetText(upload, t, 'fr')).toBe(
            'Ligne de Paris à Versailles 999000, Ouverture du 2 août 1839',
        );
        expect(
            mediaTargetText(
                { target_type: 'material', material_slug: 'x-73500', material_name: 'X 73500' },
                t,
                'fr',
            ),
        ).toBe('X 73500');
        const facts = Object.fromEntries(mediaFacts(upload, t, 'fr').map((f) => [f.id, f]));
        expect(facts.origin.text).toBe('Image déposée');
        expect(facts.license.href).toBe('https://creativecommons.org/licenses/by-sa/4.0/');
        expect(facts.captured.text).toBe('1972');
        expect(facts.coordinates.text).toBe('48,8445, 2,2196');
        expect(facts.file.text).toBe('1200 × 1600 pixels, 850 ko');
        expect(proposalHeading(upload)).toBe('Ligne de Paris à Versailles');
    });

    it('décrit une image de Commons avec ses métadonnées lues sur Commons', () => {
        const commons = {
            source: 'commons',
            target_type: 'station',
            station_name: 'Nontron',
            commons: {
                title: 'Gare de Nontron',
                author: 'Jean Dupont',
                license: 'CC BY-SA 4.0',
                license_url: 'https://creativecommons.org/licenses/by-sa/4.0',
                source_url: 'https://commons.wikimedia.org/wiki/File:Gare_de_Nontron.jpg',
                width: 3000,
                height: 2000,
            },
        };
        const facts = Object.fromEntries(mediaFacts(commons, t, 'fr').map((f) => [f.id, f]));
        expect(facts.origin).toMatchObject({
            text: 'Image de Wikimedia Commons',
            href: commons.commons.source_url,
        });
        expect(facts.target.text).toBe('Nontron');
        expect(facts.author.text).toBe('Jean Dupont');
        expect(facts.file.text).toBe('3000 × 2000');
        expect(facts.captured).toBeUndefined();
        expect(proposalHeading(commons)).toBe('Nontron');
    });

    it('liste les métadonnées retirées', () => {
        expect(removedMetadataText(['exif', 'gps'], t)).toBe(
            'Métadonnées retirées : EXIF, position GPS.',
        );
        expect(removedMetadataText([], t)).toBe('Aucune métadonnée.');
    });
});
