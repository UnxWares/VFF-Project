// Glossaire : filtrage, regroupement et repérage des sigles (lib/glossary.js), puis rendu serveur du texte
// à sigles (svelte/server). La page entière est vérifiée dans un navigateur sans fenêtre.
import { render } from 'svelte/server';
import { describe, expect, it } from 'vitest';
import { setupI18n } from '$lib/i18n.js';
import { abbrSlug, fold, groupTerms, matchesTerm, splitAbbreviations } from '$lib/glossary.js';
import GlossaryText from '../../resources/js/Components/GlossaryText.svelte';

const terms = [
    {
        slug: 'bal',
        name: 'BAL',
        full: 'Block automatique lumineux',
        definition: 'Mode de cantonnement automatique.',
        theme: 'signalling',
        abbr: 'BAL',
        letter: 'B',
        see: [{ slug: 'kvb', name: 'KVB' }],
        example: { path: '/lines/rfn-830000', name: 'Paris-Lyon à Marseille' },
    },
    {
        slug: 'ere',
        name: 'Ère',
        full: null,
        definition: 'Instantané du réseau à une date.',
        theme: 'history',
        abbr: null,
        letter: 'E',
        see: [],
        example: { path: '/chronology', page: 'chronology' },
    },
    {
        slug: 'kvb',
        name: 'KVB',
        full: 'Contrôle de vitesse par balises',
        definition: 'Contrôle de vitesse.',
        theme: 'signalling',
        abbr: 'KVB',
        letter: 'K',
        see: [],
        example: null,
    },
];

setupI18n({
    locale: 'fr',
    translations: {
        'glossary.title': 'Glossaire',
        'glossary.subtitle': ':total termes',
        'glossary.results': ':shown sur :total',
        'glossary.themes.signalling': 'Signalisation',
        'glossary.themes.history': 'Histoire administrative',
        'glossary.pages.chronology': 'Chronologie',
        'glossary.view_theme': 'Par thème',
        'glossary.view_alpha': 'Par ordre alphabétique',
        'glossary.permalink': 'Lien vers le terme :term',
        'glossary.abbr_link': 'Définition de :term dans le glossaire',
        'glossary.abbr.BAL': 'Block automatique lumineux',
        'glossary.abbr.KVB': 'Contrôle de vitesse par balises',
        'glossary.abbr.GSM-R': 'Global System for Mobile Communications - Railway',
        'glossary.abbr.TVM': 'Transmission voie-machine',
    },
});

describe('fold et matchesTerm', () => {
    it('ignore accents et casse', () => {
        expect(fold('Écartement')).toBe('ecartement');
        expect(matchesTerm(terms[1], 'ERE')).toBe(true);
        expect(matchesTerm(terms[0], 'automatique')).toBe(true);
        expect(matchesTerm(terms[0], 'vapeur')).toBe(false);
        expect(matchesTerm(terms[0], '   ')).toBe(true);
    });
});

describe('groupTerms', () => {
    it('regroupe par thème dans l’ordre donné, sans groupe vide', () => {
        const groups = groupTerms(terms, 'theme', ['infrastructure', 'signalling', 'history']);
        expect(groups.map((g) => g.key)).toEqual(['signalling', 'history']);
        expect(groups[0].terms.map((x) => x.slug)).toEqual(['bal', 'kvb']);
    });
    it('regroupe par lettre dans l’ordre reçu', () => {
        expect(groupTerms(terms, 'alpha', []).map((g) => g.key)).toEqual(['B', 'E', 'K']);
    });
});

describe('splitAbbreviations', () => {
    const sigles = ['BAL', 'KVB', 'GSM-R', 'TVM', 'ETCS'];
    it('ne lie que la première occurrence de chaque sigle', () => {
        const parts = splitAbbreviations('Le BAL et le KVB, puis le BAL encore.', sigles);
        expect(parts.filter((p) => 'abbr' in p).map((p) => p.abbr)).toEqual(['BAL', 'KVB']);
        expect(parts.map((p) => ('abbr' in p ? p.abbr : p.text)).join('')).toBe(
            'Le BAL et le KVB, puis le BAL encore.',
        );
    });
    it('lit GSM-R en entier et reconnaît TVM dans TVM-300, mais pas dans un mot', () => {
        const a = splitAbbreviations('GSM-R et TVM-300', sigles).filter((p) => 'abbr' in p);
        expect(a.map((p) => p.abbr)).toEqual(['GSM-R', 'TVM']);
        expect(splitAbbreviations('ALBALTROS et LEBAL', sigles)).toEqual([
            { text: 'ALBALTROS et LEBAL' },
        ]);
    });
    it('rend un texte sans sigle ou sans liste tel quel', () => {
        expect(splitAbbreviations('rien', sigles)).toEqual([{ text: 'rien' }]);
        expect(splitAbbreviations('BAL', [])).toEqual([{ text: 'BAL' }]);
        expect(abbrSlug('GSM-R')).toBe('gsm-r');
    });
});

describe('rendu serveur', () => {
    it('GlossaryText relie la première occurrence à sa définition, avec title et nom accessible', () => {
        const { body } = render(GlossaryText, { props: { text: 'BAL puis BAL, avec GSM-R.' } });
        expect(body.match(/<abbr/g)).toHaveLength(2);
        expect(body).toContain('href="/glossary#bal"');
        expect(body).toContain('title="Block automatique lumineux"');
        expect(body).toContain('aria-label="Définition de BAL dans le glossaire"');
        expect(body).toContain('href="/glossary#gsm-r"');
    });
});
