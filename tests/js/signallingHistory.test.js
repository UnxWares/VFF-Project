import { describe, expect, it } from 'vitest';
import { articleTitles, historyRows, portionText, systemLabel } from '$lib/signallingHistory.js';

// Traduction de test : la clé, avec les paramètres (« key|from=A|to=B »), sauf les libellés connus.
const known = {
    'lines.signalling.block': 'Cantonnement :',
    'lines.signalling.cabin': 'Signalisation en cabine :',
    'lines.signalling.protection': 'Contrôle de vitesse :',
    'lines.signalling.codes.BAL': 'BAL, block automatique lumineux',
    'lines.signalling.codes.KVB': 'KVB, contrôle de vitesse par balises',
    'signalling_history.system.BM': 'BM, block manuel',
};
const t = (key, opts) =>
    key in known
        ? known[key]
        : opts
          ? `${key}|${Object.entries(opts)
                .map(([k, v]) => `${k}=${v}`)
                .join('|')}`
          : key;

const fact = (over = {}) => ({
    system: 'BAL',
    role: 'block',
    date: '1962-06-01',
    date_precision: 'month',
    reliability: 'high',
    scope: 'section',
    section: { from: 'Garches', to: 'Saint-Nom-la-Bretèche', pk_start_m: null, pk_end_m: null },
    source: {
        provider: 'wikipedia',
        title: 'Ligne de A à B',
        url: 'https://fr.wikipedia.org/w/index.php?oldid=1',
        revision: 1,
    },
    ...over,
});

describe('systemLabel', () => {
    it('préfère le libellé propre à l’historique, puis celui des tranches, puis le code', () => {
        expect(systemLabel('BM', t)).toBe('BM, block manuel');
        expect(systemLabel('BAL', t)).toBe('BAL, block automatique lumineux');
        expect(systemLabel('TVM-300', t)).toBe('TVM-300');
    });
});

describe('portionText', () => {
    it('toute la ligne, portion non précisée', () => {
        expect(portionText(fact({ scope: 'whole_line', section: null }), t, 'PK')).toBe(
            'signalling_history.section.whole_line',
        );
        expect(portionText(fact({ scope: 'unspecified', section: null }), t, 'PK')).toBe(
            'signalling_history.section.unspecified',
        );
    });

    it('lieux tels que l’article les donne', () => {
        expect(portionText(fact(), t, 'PK')).toBe(
            'signalling_history.section.between|from=Garches|to=Saint-Nom-la-Bretèche',
        );
        expect(portionText(fact({ section: { from: 'Garches', to: null } }), t, 'PK')).toBe(
            'signalling_history.section.from|from=Garches|to=null',
        );
        expect(portionText(fact({ section: { from: null, to: 'Melun' } }), t, 'PK')).toBe(
            'signalling_history.section.to|from=null|to=Melun',
        );
    });

    it('PK en km+mmm, avec les lieux entre parenthèses quand il y en a', () => {
        const pk = { pk_start_m: 41700, pk_end_m: 48200 };
        expect(portionText(fact({ section: { from: null, to: null, ...pk } }), t, 'PK')).toBe(
            'signalling_history.section.pk|pk=PK|from=41+700|to=48+200',
        );
        expect(
            portionText(fact({ section: { from: 'Lyon', to: 'Ambérieu', ...pk } }), t, 'PK'),
        ).toBe(
            'signalling_history.section.between|from=Lyon|to=Ambérieu (signalling_history.section.pk|pk=PK|from=41+700|to=48+200)',
        );
    });

    it('une portion vide retombe sur « non précisée »', () => {
        expect(portionText(fact({ section: { from: null, to: null } }), t, 'PK')).toBe(
            'signalling_history.section.unspecified',
        );
    });
});

describe('historyRows', () => {
    const rows = historyRows(
        [
            fact(),
            fact({
                system: 'KVB',
                role: 'speed_control',
                date: '1989-01-01',
                date_precision: 'year',
                scope: 'unspecified',
                section: null,
            }),
        ],
        { t, lang: 'fr', pkWord: 'PK' },
    );

    it('la date suit la précision : mois et année, jamais un jour inventé', () => {
        expect(rows[0].when).toBe('juin 1962');
        expect(rows[1].when).toBe('1989');
        expect(rows.map((r) => r.iso)).toEqual(['1962-06-01', '1989-01-01']);
    });

    it('le KVB est un contrôle de vitesse, jamais un cantonnement', () => {
        expect(rows[0].kind).toBe('Cantonnement :');
        expect(rows[1].kind).toBe('Contrôle de vitesse :');
        expect(rows[1].system).toContain('KVB');
    });

    it('chaque ligne a une clé unique et le lien de sa source', () => {
        expect(new Set(rows.map((r) => r.key)).size).toBe(2);
        expect(rows[0].url).toBe('https://fr.wikipedia.org/w/index.php?oldid=1');
    });

    it('les titres d’articles sont dédoublonnés', () => {
        expect(
            articleTitles([fact(), fact(), fact({ source: { title: 'Autre', url: 'u' } })]),
        ).toEqual(['Ligne de A à B', 'Autre']);
    });
});
