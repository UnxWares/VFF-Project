// Schéma des voies rendu côté serveur (svelte/server) : le tableau de parcours est l'alternative textuelle du
// dessin (chaque objet dessiné y a sa ligne, avec sa provenance), le dessin est décoratif dans une région nommée
// atteinte au clavier, les onglets suivent le motif de l'ARIA.
import { readFileSync } from 'node:fs';
import { render } from 'svelte/server';
import { describe, expect, it } from 'vitest';
import { setupI18n } from '$lib/i18n.js';
import { normalize, routeRows } from '$lib/trackLayout.js';
import TrackLayout from '$components/Lines/TrackLayout.svelte';
import TrackRouteTable from '$components/Lines/TrackRouteTable.svelte';

const raw = (name) =>
    JSON.parse(
        readFileSync(new URL(`./fixtures/trackLayout/${name}.json`, import.meta.url), 'utf8'),
    );

setupI18n({
    locale: 'fr',
    translations: {
        'tracks.row.signal': 'Signal :ref',
        'tracks.row.switch': 'Aiguillage :ref',
        'tracks.source.rinf': 'RINF',
        'tracks.source.osm': 'OpenStreetMap',
        'tracks.source.vff': 'déduit par VFF',
        'tracks.source.missing': 'donnée absente',
        'tracks.source.approx': 'position approchée',
        'tracks.schema_label': 'Schéma des voies, du PK :from au PK :to',
    },
});

describe('schéma des voies (rendu serveur)', () => {
    for (const name of ['single-track', 'large-station']) {
        it(`donne une ligne du tableau par objet (${name})`, () => {
            const layout = raw(name);
            const { body } = render(TrackLayout, {
                props: { layout, slug: 'essai', lineName: 'Ligne d’essai' },
            });
            const expected = routeRows(normalize(layout));
            const keys = [...body.matchAll(/<tr data-key="([^"]+)"/g)].map((m) => m[1]);
            expect(keys).toEqual(expected.map((r) => r.key));
            // Chaque signal et chaque aiguillage du dessin est nommé dans le tableau.
            for (const s of layout.signals) expect(keys).toContain(`sig-${s.id}`);
            for (const s of layout.switches) expect(keys).toContain(`sw-${s.id}`);
            for (const op of layout.operational_points) expect(body).toContain(op.name);
        });
    }

    it('nomme la région du dessin, cache le dessin aux lecteurs d écran et décrit le clavier', () => {
        const { body } = render(TrackLayout, {
            props: { layout: raw('single-track'), slug: 'essai', lineName: 'Ligne d’essai' },
        });
        expect(body).toMatch(
            /role="region"[^>]*aria-label="Schéma des voies, du PK 0\+000 au PK 32\+400"[^>]*aria-describedby="tl-help"[^>]*tabindex="0"/,
        );
        expect(body).toMatch(/<svg[^>]*aria-hidden="true"[^>]*>/);
        expect(body).toContain('id="tl-help"');
        // Onglets : un seul sélectionné et atteignable par Tab, les autres par les flèches.
        expect([...body.matchAll(/role="tab"/g)]).toHaveLength(3);
        expect([...body.matchAll(/aria-selected="true"/g)]).toHaveLength(1);
        expect(body).toMatch(/id="tl-tab-schema"[^>]*tabindex="0"/);
        expect(body).toMatch(/id="tl-tab-map"[^>]*tabindex="-1"/);
    });

    it('écrit la provenance en toutes lettres derrière chaque badge', () => {
        const data = normalize(raw('single-track'));
        const rows = routeRows(data).filter((r) =>
            ['op-op-sp', 'sw-sw-val-1', 'miss-0'].includes(r.key),
        );
        const { body } = render(TrackRouteTable, {
            props: {
                rows,
                lang: 'fr',
                t: (k, o) => (o?.ref ? `${k}:${o.ref}` : k),
                onselect: () => {},
                tracksById: new Map(data.tracks.map((tr) => [tr.id, tr])),
                selected: 'sw-sw-val-1',
            },
        });
        // Saint-Pré : OpenStreetMap, position approchée (badge O et badge ≈).
        expect(body).toContain('tracks.source.osm, tracks.source.approx');
        expect(body).toContain('>≈</span>');
        // Le manque de la couverture : « ! ».
        expect(body).toMatch(/b-missing[^>]*><span aria-hidden="true">!<\/span>/);
        // La ligne choisie est marquée pour les technologies d'assistance.
        expect(body).toMatch(/<tr data-key="sw-sw-val-1"[^>]*aria-current="true"/);
    });
});
