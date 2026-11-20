// Section « Dans les archives » de la fiche ligne (revue du commit bb27386, points 7 et 8) : sur une page en
// anglais ou en allemand, seul le titre de la notice de la BnF est en français (l'aide pour lecteur d'écran
// suit la langue de la page) ; une entrée qui regroupe deux exemplaires montre leurs deux cotes.
import i18next from 'i18next';
import { render } from 'svelte/server';
import { describe, expect, it } from 'vitest';
import { setupI18n } from '$lib/i18n.js';
import LineArchives from '$components/Lines/LineArchives.svelte';

const item = {
    title: "Ode sur l'amiral Dumont d'Urville",
    type: 'monograph',
    date: '1842',
    year: 1842,
    year_to: null,
    exact: null,
    date_precision: 'year',
    authors: [],
    shelfmarks: ['YE-54393', 'YE-54394'],
    url: 'https://gallica.bnf.fr/ark:/12148/a',
    more_urls: ['https://gallica.bnf.fr/ark:/12148/b'],
};
const archives = { total: 2, fetched_on: '2026-11-20', items: [item] };
const translations = {
    'archives.line.shelfmark_one': 'cote :shelfmark',
    'archives.line.shelfmark_other': 'cotes :shelfmark',
};

describe('section des archives', () => {
    it('ne met lang="fr" que sur le titre, pas sur l aide du lien', () => {
        setupI18n({ locale: 'en', translations: {} });
        expect(i18next.language).toBe('en');
        const { body } = render(LineArchives, { props: { archives } });

        expect(body).toContain(`<span lang="fr">${item.title}</span>`);
        // Le lien lui-même ne porte pas de langue : son aide masquée est dans la langue de la page.
        expect(body).not.toMatch(/<a[^>]*lang="fr"/);
    });

    it('montre les deux cotes d une entrée regroupée', () => {
        setupI18n({ locale: 'fr', translations });
        const { body } = render(LineArchives, { props: { archives } });

        expect(body).toContain('cotes YE-54393, YE-54394');
    });
});
