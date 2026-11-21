// i18n front : i18next initialisé avec les chaînes Laravel aplaties (clés
// « namespace.cle »), placeholders Laravel « :nom » convertis en « {{nom}} ».
// i18next est un singleton : les tests s'enchaînent dans l'ordre (amorçage,
// puis rechargements après navigation ou changement de langue).
import { readFileSync } from 'node:fs';
import i18next from 'i18next';
import { get } from 'svelte/store';
import { describe, expect, it, vi } from 'vitest';
import {
    CANONICAL,
    LOCALES,
    SUPPORTED,
    applyPageTranslations,
    fetchTranslations,
    i18n,
    setupI18n,
} from '$lib/i18n.js';

const FR = {
    'home.title': 'Bonjour :name',
    'footer.copyright': 'Voies Ferrées de France - VFF © :start-:end - Tous droits réservés',
    'itinerary.stops': 'Trajet (:count arrêts)',
    'legal.publisher.paragraph':
        'Contact : <a href="mailto:contact@vff-project.org">contact@vff-project.org</a>',
    'stations.sources.names.inferred:line_event': 'Événement de ligne',
    'maps.only_fr': 'Seulement en français',
    'common.time': 'Départ à 10:30',
};

describe('locales', () => {
    it('mêmes locales que le back (App\\Http\\Middleware\\SetLocale::SUPPORTED), la canonique en premier', () => {
        const php = readFileSync(
            new URL('../../app/Http/Middleware/SetLocale.php', import.meta.url),
            'utf8',
        );
        const supported = [
            ...php.match(/const SUPPORTED = \[([^\]]*)\]/)[1].matchAll(/'([a-z]+)'/g),
        ].map((m) => m[1]);
        expect(SUPPORTED).toEqual(supported);
        expect(CANONICAL).toBe(supported[0]);
        expect(LOCALES.map((l) => l.code)).toEqual(SUPPORTED);
    });
});

describe('setupI18n', () => {
    it('amorçage : locale courante et ses chaînes, placeholders Laravel convertis', () => {
        setupI18n({ locale: 'fr', translations: FR });

        expect(i18next.language).toBe('fr');
        expect(i18next.t('home.title', { name: 'Baptiste' })).toBe('Bonjour Baptiste');
        expect(i18next.t('footer.copyright', { start: 2024, end: 2026 })).toBe(
            'Voies Ferrées de France - VFF © 2024-2026 - Tous droits réservés',
        );
        expect(i18next.t('itinerary.stops', { count: 4 })).toBe('Trajet (4 arrêts)');
    });

    it('le store Svelte expose la même instance', () => {
        expect(get(i18n).t('home.title', { name: 'Ada' })).toBe('Bonjour Ada');
    });

    it("le HTML des pages légales n'est pas échappé et ses liens mailto: restent intacts", () => {
        expect(i18next.t('legal.publisher.paragraph')).toBe(FR['legal.publisher.paragraph']);
    });

    it("un deux-points qui n'introduit pas un placeholder est laissé tel quel", () => {
        expect(i18next.t('common.time')).toBe('Départ à 10:30');
    });

    it("une clé Laravel contenant « : » se résout (pas de séparateur d'espace de noms)", () => {
        expect(
            i18next.t('stations.sources.names.inferred:line_event', {
                defaultValue: 'inferred:line_event',
            }),
        ).toBe('Événement de ligne');
    });

    it('clé absente : la clé elle-même (jamais null ni chaîne vide)', () => {
        expect(i18next.t('maps.cle_absente')).toBe('maps.cle_absente');
    });

    it('rechargement dans une autre langue : nouvelles chaînes, repli sur le français', () => {
        setupI18n({ locale: 'en', translations: { 'home.title': 'Hello :name' } });

        expect(i18next.language).toBe('en');
        expect(i18next.t('home.title', { name: 'Ada' })).toBe('Hello Ada');
        expect(i18next.t('maps.only_fr')).toBe('Seulement en français');
    });

    it('rechargement dans la même langue : les chaînes sont fusionnées et écrasées', () => {
        setupI18n({
            locale: 'en',
            translations: { 'home.title': 'Hi :name', 'home.subtitle': 'Rail map' },
        });

        expect(i18next.t('home.title', { name: 'Ada' })).toBe('Hi Ada');
        expect(i18next.t('home.subtitle')).toBe('Rail map');
    });

    it('locale non prise en charge : la locale canonique', () => {
        setupI18n({ locale: 'es', translations: { 'home.title': 'Hola :name' } });

        expect(i18next.language).toBe(CANONICAL);
        expect(i18next.t('home.title', { name: 'Ada' })).toBe('Hola Ada');
    });

    it('sans chaînes : ne plante pas', () => {
        expect(() => setupI18n({ locale: 'de', translations: undefined })).not.toThrow();
        expect(i18next.language).toBe('de');
    });
});

// Dictionnaire servi à part (/i18n/{locale}/{empreinte}.json, prop `translations_url`).
describe('applyPageTranslations', () => {
    const json = (body, status = 200) => ({
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    });

    it('lit le dictionnaire de la page une fois, puis le réutilise à chaque navigation', async () => {
        const fetchImpl = vi.fn(async () => json({ 'nav.maps': 'Karten' }));
        const props = { locale: 'de', translations_url: '/i18n/de/aaaaaaaaaaaa.json' };

        await applyPageTranslations(props, fetchImpl);
        await applyPageTranslations(props, fetchImpl);
        await applyPageTranslations({ ...props, component: 'Lines/Index' }, fetchImpl);

        expect(fetchImpl).toHaveBeenCalledTimes(1);
        expect(fetchImpl).toHaveBeenCalledWith('/i18n/de/aaaaaaaaaaaa.json', {
            credentials: 'same-origin',
        });
        expect(i18next.language).toBe('de');
        expect(i18next.t('nav.maps')).toBe('Karten');
    });

    it('nouvelle adresse (autre langue, nouveau déploiement) : nouveau dictionnaire', async () => {
        const fetchImpl = vi.fn(async () => json({ 'nav.maps': 'Maps :count' }));

        await applyPageTranslations(
            { locale: 'en', translations_url: '/i18n/en/bbbbbbbbbbbb.json' },
            fetchImpl,
        );

        expect(fetchImpl).toHaveBeenCalledTimes(1);
        expect(i18next.language).toBe('en');
        expect(i18next.t('nav.maps', { count: 3 })).toBe('Maps 3');
    });

    it('page d erreur qui porte ses chaînes : utilisées sans requête', async () => {
        const fetchImpl = vi.fn();

        await applyPageTranslations(
            { locale: 'fr', translations: { 'errors.404.title': 'Page introuvable' } },
            fetchImpl,
        );

        expect(fetchImpl).not.toHaveBeenCalled();
        expect(i18next.t('errors.404.title')).toBe('Page introuvable');
    });

    it('réessaie une fois une coupure passagère, et ne plante jamais', async () => {
        const flaky = vi
            .fn()
            .mockRejectedValueOnce(new TypeError('Failed to fetch'))
            .mockResolvedValueOnce(json({ 'nav.maps': 'Cartes' }));
        await applyPageTranslations(
            { locale: 'fr', translations_url: '/i18n/fr/cccccccccccc.json' },
            flaky,
        );
        expect(flaky).toHaveBeenCalledTimes(2);
        expect(i18next.t('nav.maps')).toBe('Cartes');

        const error = vi.spyOn(console, 'error').mockImplementation(() => {});
        const down = vi.fn(async () => json({}, 503));
        await expect(
            applyPageTranslations(
                { locale: 'fr', translations_url: '/i18n/fr/dddddddddddd.json' },
                down,
            ),
        ).resolves.toBeUndefined();
        expect(down).toHaveBeenCalledTimes(2);
        expect(i18next.t('nav.maps')).toBe('Cartes'); // ce qu'i18next avait déjà
        error.mockRestore();
    });

    it('deux demandes rapprochées de la même adresse : une seule requête', async () => {
        const fetchImpl = vi.fn(async () => json({ a: 'b' }));
        const url = '/i18n/fr/eeeeeeeeeeee.json';

        await Promise.all([fetchTranslations(url, fetchImpl), fetchTranslations(url, fetchImpl)]);

        expect(fetchImpl).toHaveBeenCalledTimes(1);
    });
});
