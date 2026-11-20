// Vue « décennie par décennie » de la fiche ligne (LineDecades.svelte), rendu serveur : gares fermées
// entre-temps avec la date de la fiche gare ou l'intervalle de la frise, ruban de composition avec sa
// légende et son texte pour les lecteurs d'écran (la couleur ne porte jamais seule l'information).
import { describe, expect, it } from 'vitest';

describe('LineDecades (rendu serveur)', async () => {
    const { render } = await import('svelte/server');
    const { setupI18n } = await import('$lib/i18n.js');
    const LineDecades = (await import('../../resources/js/Components/Lines/LineDecades.svelte'))
        .default;
    setupI18n({
        locale: 'fr',
        translations: {
            'lines.decades.today': 'Aujourd’hui',
            'lines.decades.more_closures': 'et :count autres',
            'lines.sheet.decades.span': ':from à :to',
            'lines.sheet.decades.see': 'Carte',
            'lines.sheet.decades.see_label': 'Voir sur la carte l’état de :year (phase du :date)',
            'lines.sheet.decades.reopened': 'rouverte ensuite',
            'lines.sheet.decades.legend': 'Légende de la composition de la ligne',
            'lines.categories.mixed': 'Voyageurs et fret',
            'lines.categories.freight': 'Fret seul',
            'lines.categories.closed': 'Fermée, voie en place',
            'stations.dates.range': 'entre :from et :to',
        },
    });

    const stations = (open, closed, closures = []) => ({ open, closed, uncertain: 0, closures });
    const DECADES = [
        {
            year: 1930,
            is_current: false,
            length_by_category: { mixed: 13871 },
            active_m: 13871,
            stations: stations(3, 0),
        },
        {
            year: 1940,
            is_current: false,
            length_by_category: { mixed: 13871 },
            active_m: 13871,
            stations: stations(2, 1, [
                {
                    slug: 'nogent',
                    name: 'Nogent',
                    closed_at: '1940-12-31',
                    closed_precision: 'range',
                    closed_after: 1930,
                    reopened: true,
                },
            ]),
        },
        {
            year: 2000,
            is_current: false,
            length_by_category: { freight: 7000, closed: 6871 },
            active_m: 7000,
            stations: stations(1, 2, [
                {
                    slug: 'lonlay',
                    name: 'Lonlay-le-Tesson',
                    closed_at: '1991-05-26',
                    closed_precision: 'day',
                    closed_after: null,
                    reopened: false,
                },
            ]),
        },
    ];
    const PHASES = [{ date: '1869-12-06', precision: 'day', to_year: 1870 }];

    it('nomme chaque fermeture : date de la fiche gare, ou intervalle de la frise et réouverture', () => {
        const { body } = render(LineDecades, { props: { decades: DECADES, phases: PHASES } });
        expect(body).toContain('Lonlay-le-Tesson');
        expect(body).toContain('(26 mai 1991)');
        expect(body).toContain('Nogent');
        expect(body).toContain('(entre 1931 et 1940, rouverte ensuite)');
    });

    it('légende du ruban et composition lue par les lecteurs d’écran', () => {
        const { body } = render(LineDecades, { props: { decades: DECADES, phases: PHASES } });
        expect(body).toContain('aria-label="Légende de la composition de la ligne"');
        const legend = body.slice(body.indexOf('class="legend'));
        expect(legend.indexOf('Voyageurs et fret')).toBeLessThan(legend.indexOf('Fret seul'));
        expect(legend.indexOf('Fret seul')).toBeLessThan(legend.indexOf('Fermée, voie en place'));
        expect(body).toContain('Fret seul 7 km, Fermée, voie en place 6,9 km');
    });

    it('sans décennie : pas de légende', () => {
        const { body } = render(LineDecades, { props: { decades: [], phases: [] } });
        expect(body).not.toContain('class="legend');
    });
});
