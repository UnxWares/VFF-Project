// Corrections d'accessibilité de l'audit du soir (19/11/2026, RGAA 4.1.2), rendues côté serveur
// (svelte/server) : zones d'annonce toujours présentes (7.5), champs obligatoires signalés (11.10),
// états qui ne tiennent pas à la seule couleur (3.1), balises de présentation (8.9).
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { render } from 'svelte/server';
import { describe, expect, it } from 'vitest';
import { setupI18n } from '$lib/i18n.js';
import { STATION_STYLES } from '$lib/railStyle.js';
import ApiTokens from '../../resources/js/Components/Profile/ApiTokens.svelte';
import Webhooks from '../../resources/js/Components/Profile/Webhooks.svelte';
import LayerPicker from '../../resources/js/Components/Map/LayerPicker.svelte';
import LineDecades from '../../resources/js/Components/Lines/LineDecades.svelte';
import LineStations from '../../resources/js/Components/Lines/LineStations.svelte';

setupI18n({
    locale: 'fr',
    translations: {
        'common.required': 'obligatoire',
        'webhooks.events_legend': 'Événements à recevoir',
        'maps.layers.button': 'Calques',
        'maps.layers.active': ':count calques affichés',
        'lines.categories.mixed': 'Voyageurs et fret',
        'lines.categories.closed': 'Fermée, voie en place',
        'lines.sheet.decades.legend': 'Légende de la composition de la ligne',
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

const ACCESS = {
    tokens: [],
    profile: 'member',
    per_minute: 600,
    anonymous_per_minute: 300,
    max_tokens: 5,
    can_create: true,
    suspended: false,
    new_token: { id: 1, plain: '1|secret' },
};
const WEBHOOKS = {
    subscriptions: [],
    events: ['contribution.applied', 'current_era.refreshed'],
    max: 3,
    attempts: 3,
    disable_after: 5,
    can_create: true,
    suspended: false,
    new_secret: { id: 2, secret: 'whsec_x' },
};

describe('Profil : jetons d’API et webhooks', () => {
    it('annonce « jeton copié » dans une zone role="status" déjà présente, vide au chargement', () => {
        const body = draw(ApiTokens, { access: ACCESS });
        expect(body).toMatch(/<p class="copy-status" role="status"><\/p>/);
        // Une zone masquée par display: none sortirait de l'arbre d'accessibilité.
        expect(source('Components/Profile/ApiTokens.svelte')).not.toMatch(
            /copy-status:empty\s*\{\s*display:\s*none/,
        );
    });

    it('fait de même pour le secret d’un webhook, et garde la zone d’erreur des événements', () => {
        const body = draw(Webhooks, { webhooks: WEBHOOKS });
        expect(body).toMatch(/<p class="copy-status" role="status"><\/p>/);
        expect(body).toMatch(/id="webhook-events-error"[^>]*aria-live="polite"/);
        const css = source('Components/Profile/Webhooks.svelte');
        expect(css).not.toMatch(/:empty\s*\{\s*display:\s*none/);
    });

    it('signale dans la légende que choisir au moins un événement est obligatoire', () => {
        const body = draw(Webhooks, { webhooks: WEBHOOKS });
        expect(body).toMatch(
            /<legend>Événements à recevoir<span class="required"> \(obligatoire\)<\/span><\/legend>/,
        );
    });
});

describe('Champs obligatoires signalés dans le libellé (RGAA 11.10)', () => {
    // Pages entières (gabarit, en-tête) : vérifiées sur leur code, le rendu de Field étant testé
    // dans uiComponents.test.js.
    const fieldBlock = (code, id) => {
        const start = code.indexOf(`id="${id}`);
        return code.slice(start, code.indexOf('{/snippet}', start));
    };

    it('motif de suspension d’un compte', () => {
        const block = fieldBlock(source('Pages/Validate/Accounts.svelte'), 'reason-{a.id}');
        expect(block).toMatch(/\n\s+required\n/);
        expect(block).toMatch(/<textarea[\s\S]*required/);
    });

    it('mot de passe qui confirme la suppression du compte', () => {
        const block = fieldBlock(source('Pages/Auth/Profile.svelte'), 'delete-password');
        expect(block).toMatch(/\n\s+required\n/);
    });
});

describe('Bouton « Calques » de la carte', () => {
    it('fermé : état annoncé, panneau masqué et piloté par le bouton', () => {
        const body = draw(LayerPicker, {});
        expect(body).toMatch(
            /<button type="button" class="layer-toggle" aria-expanded="false" aria-controls="([^"]+)-panel">/,
        );
        expect(body).toMatch(/<div class="layer-panel" id="[^"]+-panel" hidden="">/);
        expect(body).not.toContain('layer-count');
    });

    it('avec des calques : le nombre est dit en texte, pas seulement par le contour orange', () => {
        const body = draw(LayerPicker, { data: ['accidents', 'greenways'] });
        expect(body).toContain('class="layer-toggle active"');
        expect(body).toContain('<span class="layer-count" aria-hidden="true">2</span>');
        expect(body).toContain('<span class="vff-sr-only">, 2 calques affichés</span>');
        // Contour de l'état actif à 3:1 au moins sur le fond (orange foncé, pas l'orange vif).
        expect(source('Components/Map/LayerPicker.svelte')).toMatch(
            /\.layer-toggle\.active\s*\{\s*border-color:\s*var\(--vff-accent-strong\)/,
        );
    });
});

describe('Fiche ligne : la couleur ne porte jamais seule l’état', () => {
    it('chaque état du fil des décennies a sa propre forme, sauf « ouverte » (case pleine)', () => {
        const css = source('Components/Lines/LineStations.svelte');
        const shaped = STATION_STYLES.map((s) => s.key).filter((k) => k !== 'active');
        for (const key of shaped) {
            expect(css, key).toMatch(new RegExp(`\\.tick\\[data-status='${key}'\\]\\s*\\{`));
        }
    });

    it('le fil porte l’état de chaque décennie, lu aussi en clair', () => {
        const body = draw(LineStations, {
            eraYears: [1930, 1940],
            stations: [
                {
                    slug: 'verneuil',
                    name: 'Verneuil-sur-Vienne',
                    status_today: 'no_commercial',
                    eras: { 1930: 'active', 1940: 'no_commercial' },
                },
            ],
        });
        expect(body).toMatch(/<span class="tick" data-status="active"/);
        expect(body).toMatch(/<span class="tick" data-status="no_commercial"/);
        expect(body).toMatch(
            /<span class="vff-sr-only">[^<]+<\/span><span class="life" aria-hidden="true">/,
        );
    });

    it('les pastilles de la légende du ruban sont décoratives (span masqué, pas <i>)', () => {
        const body = draw(LineDecades, {
            decades: [
                {
                    year: 1930,
                    is_current: false,
                    length_by_category: { mixed: 1000, closed: 500 },
                    active_m: 1000,
                    stations: { open: 1, closed: 0, uncertain: 0, closures: [] },
                },
            ],
        });
        const legend = body.slice(body.indexOf('<ul class="legend"'));
        expect(legend).toMatch(
            /<li><span class="swatch" style="[^"]*" aria-hidden="true"><\/span>Voyageurs et fret<\/li>/,
        );
        expect(legend).not.toContain('<i');
    });
});
