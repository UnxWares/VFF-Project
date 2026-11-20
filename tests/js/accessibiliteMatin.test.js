// Corrections de l'audit RGAA des écrans du 20/11/2026 (propositions sourcées, relecture, galeries),
// rendues côté serveur (svelte/server) quand le composant se rend seul, lues dans la source sinon :
// zones d'annonce toujours présentes (7.5), champs obligatoires signalés (11.10), titres des tableaux
// (5.4), alternatives courtes (1.3).
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { render } from 'svelte/server';
import { describe, expect, it } from 'vitest';
import { setupI18n } from '$lib/i18n.js';
import EntityPicker from '../../resources/js/Components/Contribute/EntityPicker.svelte';
import PartialDateFields from '../../resources/js/Components/Contribute/PartialDateFields.svelte';
import ProposalReview from '../../resources/js/Components/Contribute/ProposalReview.svelte';

setupI18n({
    locale: 'fr',
    translations: {
        'common.required': 'obligatoire',
        'proposals.picker.line_label': 'Rechercher une ligne',
        'proposals.event.year': 'Année',
        'proposals.event.month': 'Mois',
        'proposals.event.day': 'Jour',
        'proposals.review.similar_title': 'Événements du même genre déjà connus sur cette ligne',
    },
});

/** HTML sans marqueurs d'hydratation ni suffixe de classe propre au composant (svelte-xxxx). */
const clean = (body) =>
    body
        .replace(/<!--.*?-->/gs, '')
        .replace(/ class="svelte-[a-z0-9]+"/g, '')
        .replace(/ svelte-[a-z0-9]+/g, '')
        .replace(/>\s+</g, '><');
const draw = (component, props) => clean(render(component, { props }).body);
const source = (path) =>
    readFileSync(fileURLToPath(new URL(`../../resources/js/${path}`, import.meta.url)), 'utf8');

describe('Zones d’annonce des formulaires de proposition (RGAA 7.5)', () => {
    it('garde la zone d’état de la recherche dans la page, vide au chargement, en role="status"', () => {
        const body = draw(EntityPicker, { kind: 'line', id: 'event-line' });
        expect(body).toMatch(/<p class="status" role="status"><\/p>/);
        // Une zone masquée par display: none sort de l'arbre d'accessibilité : son premier message serait perdu.
        expect(source('Components/Contribute/EntityPicker.svelte')).not.toMatch(
            /:empty\s*\{\s*display:\s*none/,
        );
    });

    it('annonce aussi le nombre de résultats trouvés', () => {
        const code = source('Components/Contribute/EntityPicker.svelte');
        expect(code).toMatch(/proposals\.picker\.count/);
        for (const lang of ['fr', 'en', 'de']) {
            const php = readFileSync(
                fileURLToPath(new URL(`../../lang/${lang}/proposals.php`, import.meta.url)),
                'utf8',
            );
            expect(php).toMatch(/'count_one' => ':count /);
            expect(php).toMatch(/'count_other' => ':count /);
        }
    });

    it.each([
        ['Pages/Contribute/ProposeMedia.svelte', 'status'],
        ['Pages/Contribute/ProposeLineMaterial.svelte', 'check'],
        ['Pages/Contribute/ProposeSeries.svelte', 'qid-result'],
        ['Pages/Contribute/ProposeSeries.svelte', 'impact'],
    ])('%s : la zone .%s est en role="status" et jamais retirée de la page', (path, cls) => {
        const code = source(path);
        expect(code).toMatch(new RegExp(`class="${cls}" role="status"`));
        expect(code).not.toMatch(new RegExp(`class="${cls}" aria-live`));
        expect(code).not.toMatch(/:empty\s*\{\s*display:\s*none/);
    });
});

describe('Champs obligatoires signalés (RGAA 11.10)', () => {
    it('marque l’année, le mois et le jour « (obligatoire) », chacun avec son étiquette for', () => {
        const body = draw(PartialDateFields, {
            id: 'event-date',
            legend: 'Date',
            precision: 'day',
        });
        for (const part of ['year', 'month', 'day']) {
            expect(body).toMatch(new RegExp(`<label class="vff-label" for="event-date-${part}">`));
            expect(body).toMatch(new RegExp(`id="event-date-${part}"`));
        }
        expect(body.match(/\(obligatoire\)/g)).toHaveLength(3);
        // Plus d'étiquette qui enveloppe la liste des mois (son nom reprenait toutes les options).
        expect(body).not.toMatch(/<label class="part">/);
    });

    it('n’affiche aucun champ de date quand la date est inconnue', () => {
        const body = draw(PartialDateFields, {
            id: 'media-captured',
            legend: 'Date',
            precision: '',
            allowUnknown: true,
            unknownLabel: 'Date inconnue',
        });
        expect(body).not.toMatch(/obligatoire/);
    });

    it('signale les bornes du PK et la case des droits d’une image', () => {
        expect(source('Pages/Contribute/ProposeEvent.svelte')).toMatch(
            /proposals\.event\.pk_\$\{bound\}`\)\}<span\s+class="required"/,
        );
        expect(source('Pages/Contribute/ProposeMedia.svelte')).toMatch(
            /proposals\.media\.rights_confirmed'\)\}<span\s+class="required"/,
        );
    });
});

describe('Relecture d’une proposition', () => {
    it('donne pour titre à chaque tableau l’intertitre qui le précède (RGAA 5.4)', () => {
        const body = draw(ProposalReview, {
            contribution: {
                id: 7,
                type: 'line_event',
                payload: {
                    type: 'opening',
                    precision: 'year',
                    date: '1900',
                    portion: 'whole',
                    source_kind: 'web',
                    source_title: 'Journal officiel',
                    source_url: 'https://example.org/jo',
                },
                similar_events: [
                    {
                        id: 1,
                        type: 'opening',
                        date: '1890',
                        precision: 'year',
                        from: null,
                        to: null,
                        pk_start_m: null,
                        pk_end_m: null,
                        source: 'osm',
                        source_url: null,
                    },
                ],
            },
        });
        expect(body).toMatch(/<h3 id="similar-7">/);
        expect(body).toMatch(/<table class="vff-table" aria-labelledby="similar-7">/);
        const code = source('Components/Contribute/ProposalReview.svelte');
        expect(code.match(/<table class="vff-table" aria-labelledby=/g)).toHaveLength(3);
    });
});

describe('Images légendées (RGAA 1.9)', () => {
    it('donne aux aperçus légendés d’une image proposée role="group" et un aria-label', () => {
        const code = source('Pages/Contribute/ProposeMedia.svelte');
        expect(code.match(/<figure\s+class="preview"\s+role=/g)).toHaveLength(2);
        expect(code).toMatch(/aria-label=\{figureLabel\(\[/);
    });

    it('ne met plus de <figure> autour de l’image relue, suivie d’un lien et non d’une légende', () => {
        const code = source('Components/Contribute/ProposalReview.svelte');
        expect(code).not.toMatch(/<figure|<figcaption/);
    });
});

describe('Mes contributions (RGAA 10.11, 13.5)', () => {
    it('ne liste les changements que pour un tronçon, jamais ceux d’une série en JSON brut', () => {
        expect(source('Pages/Contribute/Mine.svelte')).toMatch(
            /changes = PROPOSAL_TYPES\.includes\(c\.type\)\s*\?\s*\[\]\s*:\s*changeEntries\(/,
        );
    });
});

describe('Alternatives des images (RGAA 1.3.9)', () => {
    it.each([
        ['Components/Lines/LineChronicle.svelte', /conciseAlt\(mediaHeading\(m\)/],
        ['Components/Sheet/SheetGallery.svelte', /alt=\{conciseAlt\(heading\(active\)\)\}/],
        ['Components/Contribute/ProposalReview.svelte', /conciseAlt\(imageTitle/],
    ])('%s raccourcit l’alternative', (path, pattern) => {
        expect(source(path)).toMatch(pattern);
    });
});
