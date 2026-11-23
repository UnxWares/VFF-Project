import { describe, expect, it } from 'vitest';
import {
    articleTitles,
    correctionRows,
    datingNotice,
    hasDocumentFacts,
    historyRows,
    periodBounds,
    phaseMoment,
    statusText,
    statusesAt,
    portionText,
    systemLabel,
} from '$lib/signallingHistory.js';

// Traduction de test : la clé, avec les paramètres (« key|from=A|to=B »), sauf les libellés connus.
const known = {
    'lines.signalling.block': 'Cantonnement :',
    'lines.signalling.cabin': 'Signalisation en cabine :',
    'lines.signalling.protection': 'Contrôle de vitesse :',
    'lines.signalling.remote_control': 'Commande centralisée :',
    'signalling_history.system.CC': 'Commande centralisée (CCVU, CCR, PCD)',
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

    it('la commande centralisée a son propre rôle, ni cantonnement ni contrôle de vitesse', () => {
        const [row] = historyRows(
            [
                fact({
                    system: 'CC',
                    role: 'remote_control',
                    date: '1962-01-21',
                    date_precision: 'day',
                }),
            ],
            { t, lang: 'fr', pkWord: 'PK' },
        );
        expect(row.kind).toBe('Commande centralisée :');
        expect(row.system).toBe('Commande centralisée (CCVU, CCR, PCD)');
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

describe('origine et portion placée (deuxième passe)', () => {
    const ctx = { t, lang: 'fr', pkWord: 'PK' };

    it('une TVM datée de l’ouverture d’une LGV porte une note, une phrase de l’article aucune', () => {
        const rows = historyRows(
            [
                fact({ origin: 'text' }),
                fact({
                    system: 'TVM-430',
                    role: 'cab',
                    origin: 'opening',
                    scope: 'unspecified',
                    section: null,
                }),
                fact({
                    system: 'BM',
                    origin: 'infobox',
                    date: '1985-01-01',
                    date_precision: 'year',
                }),
            ],
            ctx,
        );
        expect(rows.map((r) => r.note)).toEqual([
            null,
            'signalling_history.origin.opening',
            'signalling_history.origin.infobox',
        ]);
        expect(rows[1].kind).toBe('Signalisation en cabine :');
    });

    it('une portion placée par ses gares donne ses PK', () => {
        const text = portionText(
            fact({
                section: {
                    from: 'Garches',
                    to: 'Saint-Nom-la-Bretèche',
                    location: 'stations',
                    pk_start_m: 16714,
                    pk_end_m: 29913,
                },
            }),
            t,
            'PK',
        );
        expect(text).toContain('16+714');
        expect(text).toContain('29+913');
        expect(text).toContain('from=Garches');
    });
});

describe('faits datés par des documents', () => {
    const doc = fact({
        origin: 'document',
        scope: 'whole_line',
        section: null,
        source: {
            provider: 'manual',
            title: 'Décision',
            url: 'https://www.securite-ferroviaire.fr/a.pdf',
            revision: null,
            documents: [
                {
                    publisher: 'EPSF',
                    title: 'Décision',
                    url: 'https://www.securite-ferroviaire.fr/a.pdf',
                    published: '2017-11-10',
                },
            ],
        },
    });

    it('un lien par document, nommé par son éditeur ; aucun pour un fait de Wikipédia', () => {
        const [row, wiki] = historyRows([doc, fact()], {
            t,
            lang: 'fr-FR',
            pkWord: 'PK',
        });
        expect(row.documents).toEqual([
            { url: 'https://www.securite-ferroviaire.fr/a.pdf', label: 'EPSF', title: 'Décision' },
        ]);
        expect(row.note).toBeNull();
        expect(wiki.documents).toEqual([]);
    });

    it('la note de Wikipédia ne cite que les articles, la note des documents ne vient qu’avec eux', () => {
        expect(articleTitles([doc, fact()])).toEqual(['Ligne de A à B']);
        expect(articleTitles([doc])).toEqual([]);
        expect(hasDocumentFacts([doc, fact()])).toBe(true);
        expect(hasDocumentFacts([fact()])).toBe(false);
    });
});

describe('statusesAt : situation à la date choisie sur la fiche', () => {
    const at = (date, precision = 'day') => phaseMoment({ date, precision });
    const tvm = fact({
        system: 'TVM-300',
        role: 'cab',
        date: '1983-09-25',
        date_precision: 'day',
        section: { from: 'A', to: 'B', pk_start_m: 1000, pk_end_m: 50000 },
    });
    const tvm430 = fact({
        system: 'TVM-430',
        role: 'cab',
        date: '1994-07-03',
        date_precision: 'day',
        section: { from: 'A', to: 'B', pk_start_m: 900, pk_end_m: 50100 },
    });
    const kvb = fact({
        system: 'KVB',
        role: 'speed_control',
        date: '1990-01-01',
        date_precision: 'year',
        scope: 'whole_line',
        section: null,
    });

    it('pas encore, en vigueur, remplacé par un fait du même groupe qui recouvre la portion', () => {
        expect(statusesAt([tvm, tvm430], at('1980-01-01')).map((s) => s.status)).toEqual([
            'later',
            'later',
        ]);
        expect(statusesAt([tvm, tvm430], at('1990-06-10')).map((s) => s.status)).toEqual([
            'in_force',
            'later',
        ]);
        const late = statusesAt([tvm, tvm430], at('2000-01-01'));
        expect(late.map((s) => s.status)).toEqual(['replaced', 'in_force']);
        expect(late[0].by.system).toBe('TVM-430');
    });

    it('le KVB (contrôle de vitesse) ne remplace jamais un cantonnement', () => {
        const bal = fact({
            date: '1970-01-01',
            date_precision: 'year',
            scope: 'whole_line',
            section: null,
        });
        expect(statusesAt([bal, kvb], at('2000-01-01')).map((s) => s.status)).toEqual([
            'in_force',
            'in_force',
        ]);
    });

    it('une date à l’année près qui contient la date choisie reste incertaine', () => {
        expect(statusesAt([kvb], at('1990-06-10'))[0].status).toBe('uncertain');
        expect(statusesAt([kvb], at('1990-12-31'))[0].status).toBe('in_force');
        expect(statusesAt([kvb], at('1990-01-01', 'year'))[0].status).toBe('uncertain');
    });

    it('un changement non daté vaut la fin de sa période, comme la carte par ère', () => {
        expect(phaseMoment({ date: '1950-12-31', precision: 'range' })).toEqual({
            start: '1950-12-31',
            end: '1950-12-31',
            date: '1950-12-31',
            precision: 'day',
        });
        expect(periodBounds('2024-02-01', 'month')).toEqual(['2024-02-01', '2024-02-29']);
        expect(phaseMoment(null)).toBeNull();
    });

    it('textes de la situation', () => {
        const [s] = statusesAt([tvm, tvm430], at('2000-01-01'));
        expect(statusText(s, tvm, t, 'fr')).toBe(
            'signalling_history.status.replaced|system=TVM-430|when=3 juillet 1994',
        );
        expect(statusText({ status: 'uncertain' }, kvb, t, 'fr')).toBe(
            'signalling_history.status.uncertain_year',
        );
    });
});

describe('correctionRows', () => {
    const correction = (over = {}) => ({
        system: 'ETCS',
        date: '2021-08-01',
        date_precision: 'month',
        section: { from: 'Uckange', to: 'Zoufftgen' },
        original: {
            provider: 'wikipedia',
            title: 'Ligne de Metz-Ville à Zoufftgen',
            url: 'https://fr.wikipedia.org/w/index.php?oldid=1',
        },
        action: 'neutralize',
        reason: 'la décision de l’EPSF date de 2016',
        sources: [
            {
                publisher: 'EPSF',
                title: 'Décision 681',
                url: 'https://epsf.test/681',
                published: '2016-08-01',
            },
            {
                publisher: 'EPSF',
                title: 'Rapport 2017',
                url: 'https://epsf.test/2017',
                published: '2018',
            },
            {
                publisher: 'Presse',
                title: 'Article',
                url: 'https://presse.test/a',
                published: null,
            },
        ],
        ...over,
    });
    const ctx = { t, lang: 'fr', pkWord: 'PK' };

    it('dit la date écartée, sa portion, où elle a été lue et ce que VFF en fait', () => {
        const [r] = correctionRows([correction()], ctx);
        expect(r.when).toBe('août 2021');
        expect(r.iso).toBe('2021-08-01');
        expect(r.system).toBe('ETCS');
        expect(r.portion).toBe('signalling_history.section.between|from=Uckange|to=Zoufftgen');
        expect(r.action).toBe('signalling_history.corrections.action.neutralize');
        expect(r.reason).toBe('la décision de l’EPSF date de 2016');
        expect(r.original).toEqual({
            url: 'https://fr.wikipedia.org/w/index.php?oldid=1',
            title: 'Ligne de Metz-Ville à Zoufftgen',
            label: 'signalling_history.source.link',
        });
    });

    it('nomme chaque document par son éditeur, avec sa date de publication à sa précision', () => {
        const [r] = correctionRows([correction()], ctx);
        expect(r.documents.map((d) => [d.label, d.published])).toEqual([
            ['EPSF', '1 août 2016'],
            ['EPSF', '2018'],
            ['Presse', null],
        ]);
    });

    it('un remplacement, un fait de document et une portion inconnue', () => {
        const [r] = correctionRows(
            [
                correction({
                    action: 'replace',
                    section: null,
                    original: { provider: 'manual', title: 'Communiqué', url: 'https://x.test' },
                }),
            ],
            ctx,
        );
        expect(r.action).toBe('signalling_history.corrections.action.replace');
        expect(r.portion).toBeNull();
        expect(r.original.label).toBe('signalling_history.corrections.original_document');
    });
});

describe('datingNotice', () => {
    const undated = { status: 'undated', checked_at: '2026-11-23' };

    it('donne la mention et la date de la dernière vérification pour une ligne sans date', () => {
        expect(datingNotice(undated, t, 'fr')).toEqual({
            message: 'signalling_history.dating.undated',
            checked: 'signalling_history.dating.checked|date=23 novembre 2026',
        });
    });

    it('ne dit rien d une ligne datée, hors périmètre ou inconnue', () => {
        expect(datingNotice({ status: 'dated', checked_at: '2026-11-23' }, t, 'fr')).toBeNull();
        expect(
            datingNotice({ status: 'not_applicable', checked_at: '2026-11-23' }, t, 'fr'),
        ).toBeNull();
        expect(datingNotice(null, t, 'fr')).toBeNull();
        expect(datingNotice(undefined, t, 'fr')).toBeNull();
    });
});
