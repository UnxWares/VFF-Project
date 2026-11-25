// Section « Registre de l'infrastructure (RINF) » de la fiche ligne : lecture des réponses réelles de
// GET /api/v1/lines/{line}/rinf-parameters (25/11/2026, fixtures/lineRinf) en résumé, accord et écarts
// (lib/lineRinf.js), puis rendu serveur du contenu de la section (LineRinfFacts).
import { readFileSync } from 'node:fs';
import { render } from 'svelte/server';
import { describe, expect, it } from 'vitest';
import { setupI18n } from '$lib/i18n.js';
import {
    agreement,
    electrificationText,
    gapText,
    hasRinf,
    linkFirst,
    listSeparators,
    loadText,
    protectionTokens,
    rinfText,
    runs,
    showRow,
    summarize,
} from '$lib/lineRinf.js';
import LineRinfFacts from '$components/Lines/LineRinfFacts.svelte';

const NB = ' ';
const raw = (code) =>
    JSON.parse(readFileSync(new URL(`./fixtures/lineRinf/${code}.json`, import.meta.url), 'utf8'));
const param = (data, key) => data.parameters.find((p) => p.key === key);

// Textes français de lang/fr/rinf.php utilisés ici (même forme : placeholders `:name`).
const FR = {
    'rinf.value.not_electrified': 'non électrifiée',
    'rinf.value.electrified_unknown': 'électrifiée, tension non précisée',
    'rinf.value.dc': ':volt continu',
    'rinf.value.ac': ':volt alternatif :freq',
    'rinf.value.speed_range': 'de :min à :max',
    'rinf.value.none_declared': 'aucun système déclaré',
    'rinf.value.gsmr_yes': 'oui',
    'rinf.value.gsmr_version': 'oui (:version)',
    'rinf.value.gsmr_no': 'non',
    'rinf.value.no_gsmr': 'pas de GSM-R',
    'rinf.value.no_speed_control': 'aucun contrôle de vitesse',
    'rinf.value.etcs_level': 'ETCS niveau :level',
    'rinf.value.national_class_one': ':category (classe nationale :classes)',
    'rinf.value.national_class_other': ':category (classes nationales :classes)',
    'rinf.value.national_class_only_one': 'classe nationale :classes',
    'rinf.value.national_class_only_other': 'classes nationales :classes',
    'rinf.value.more_one': 'et une autre valeur',
    'rinf.value.more_other': 'et :count autres valeurs',
    'rinf.pk_range': `PK${NB}:from à :to`,
    'rinf.param.electrification': 'Électrification',
    'rinf.param.max_speed': 'Vitesse déclarée au registre',
    'rinf.param.train_protection': 'Contrôle de vitesse et ETCS',
    'rinf.param.radio': 'GSM-R',
    'rinf.param.gauging': 'Gabarit',
    'rinf.param.load_capability': 'Catégorie de charge',
    'rinf.param.track_gauge': 'Écartement',
    'rinf.agreement.all': 'Même valeur chez SNCF Réseau sur toute la longueur comparée.',
    'rinf.agreement.share': `D’accord avec SNCF Réseau sur :pct${NB}% de la longueur comparée.`,
    'rinf.agreement.no_other': 'SNCF Réseau ne publie pas cette donnée pour la ligne.',
    'rinf.agreement.kvb_only':
        'Le registre déclare aussi le KVB sur les voies équipées de TVM ou d’ETCS, où SNCF Réseau ne le met pas (:km km) : cet écart n’est pas compté.',
    'rinf.agreement.speed_rule': 'Règle des vitesses.',
    'rinf.gaps_one': 'Voir l’écart',
    'rinf.gaps_other': 'Voir les :count écarts',
    'rinf.gaps_label': 'Écarts avec SNCF Réseau : :param',
    'rinf.gap_rinf': `Registre${NB}: :value`,
    'rinf.gap_sncf': `SNCF Réseau${NB}: :value`,
    'rinf.intro': 'Le gestionnaire déclare ses voies au RINF.',
    'rinf.sources': 'Sources',
    'rinf.fetched': 'relevé le :date',
    'rinf.licence': 'licence :licence',
    'common.new_tab': 'nouvel onglet',
};
setupI18n({ locale: 'fr', translations: FR });
const t = (k, o) => {
    let s = FR[k] ?? k;
    if (o?.count !== undefined && FR[`${k}_one`])
        s = o.count === 1 ? FR[`${k}_one`] : FR[`${k}_other`];
    for (const [name, v] of Object.entries(o ?? {})) s = s.replaceAll(`:${name}`, String(v));
    return s;
};

describe('lineRinf : résumé du registre', () => {
    it('ne montre la section que pour une ligne que le registre décrit', () => {
        expect(hasRinf(raw('570000'))).toBe(true);
        // Ligne absente du registre : l'API répond 200 et des listes vides.
        const empty = {
            sections: [],
            parameters: [{ key: 'electrification', portions: [] }],
            platforms: { items: [] },
            sources: [],
        };
        expect(hasRinf(empty)).toBe(false);
        expect(hasRinf(null)).toBe(false);
    });

    it('réunit une même valeur sur les trois tronçons de Paris - Bordeaux, sans PK', () => {
        const e = param(raw('570000'), 'electrification');
        expect(e.portions.map((p) => p.rg_troncon)).toEqual([1, 2, 3]);
        const list = runs(e);
        expect(list).toHaveLength(1);
        expect([list[0].from, list[0].to, list[0].length_m]).toEqual(['0+000', '583+844', 579272]);
        expect(summarize(e).kind).toBe('single');
        expect(electrificationText(summarize(e).value.key, t, 'fr')).toBe(
            `1${NB}500${NB}V continu`,
        );
    });

    it('donne les PK quand la valeur change le long de la ligne (LGV Sud-Est)', () => {
        const s = summarize(param(raw('752000'), 'electrification'));
        expect(s.kind).toBe('runs');
        expect(s.runs.map((r) => [r.from, r.to, r.value.key])).toEqual([
            ['-0+168', '0+994', '1500V-DC'],
            ['0+994', '710+547', '25000V-50Hz'],
            ['710+547', '711+163', '1500V-DC'],
        ]);
        expect(electrificationText('25000V-50Hz', t, 'fr')).toBe(`25${NB}kV alternatif 50${NB}Hz`);
        expect(electrificationText('none', t, 'fr')).toBe('non électrifiée');
        expect(electrificationText('1500V-DC|none', t, 'fr')).toBe(
            `1${NB}500${NB}V continu ou non électrifiée`,
        );
    });

    it('résume les vitesses nombreuses par leur étendue', () => {
        const s = summarize(param(raw('570000'), 'max_speed'));
        expect(s.kind).toBe('range');
        expect([s.min, s.max]).toEqual([60, 220]);
        // Quelques vitesses (Limoges - Périgueux) : listées avec leurs PK.
        const few = summarize(param(raw('611000'), 'max_speed'));
        expect(few.kind).toBe('runs');
        expect(few.runs.map((r) => rinfText('max_speed', r.value, t, 'fr'))).toEqual([
            `110${NB}km/h`,
            `140${NB}km/h`,
            `130${NB}km/h`,
        ]);
    });

    it('nomme les systèmes déclarés avec leur terme du glossaire', () => {
        const tokens = protectionTokens(['ETCS L2', 'KVB', 'Crocodile'], t, 'fr');
        expect(tokens.map((x) => [x.before, x.label, x.term])).toEqual([
            ['', `ETCS${NB}niveau${NB}2`, 'ertms-niveau-2'],
            [', ', 'KVB', 'kvb'],
            [' et ', 'Crocodile', 'crocodile'],
        ]);
        // Anglais du site : britannique, sans virgule avant « and ».
        expect(listSeparators(3, 'en')).toEqual([', ', ' and ']);
        expect(listSeparators(1, 'fr')).toEqual([]);
        expect(protectionTokens(['RSDD/SCMT'], t, 'fr')[0].term).toBeNull();
        // Seule la première mention d'un terme est un lien (KVBP renvoie au KVB).
        const linked = linkFirst([
            protectionTokens(['KVB', 'Crocodile'], t, 'fr'),
            protectionTokens(['KVBP', 'DAAT', 'RSDD/SCMT'], t, 'fr'),
        ]);
        expect(linked.map((l) => l.map((x) => x.link))).toEqual([
            [true, true],
            [false, true, false],
        ]);
    });

    it('écrit la catégorie de charge, la radio et l’écartement', () => {
        expect(loadText({ key: 'D4', values: ['5'] }, t, 'fr')).toBe('D4 (classe nationale 5)');
        expect(loadText({ key: 'D4', values: ['4-', '4+', '5'] }, t, 'fr')).toBe(
            'D4 (classes nationales 4-, 4+ et 5)',
        );
        expect(loadText({ key: '', values: ['voie locale'] }, t, 'fr')).toBe(
            'classe nationale voie locale',
        );
        expect(rinfText('radio', { key: 'gsm-r', values: ['Baseline 1'] }, t, 'fr')).toBe(
            'oui (Baseline 1)',
        );
        // Code que le vocabulaire de l'ERA ne définit pas : GSM-R dit, version tue.
        expect(rinfText('radio', { key: 'gsm-r', values: ['gsmr-versions/10'] }, t, 'fr')).toBe(
            'oui',
        );
        expect(rinfText('track_gauge', { key: '1668', values: [] }, t, 'fr')).toBe(
            `1${NB}668${NB}mm`,
        );
        // L'écartement n'est montré que s'il diffère de l'écartement standard.
        expect(showRow('track_gauge', summarize(param(raw('570000'), 'track_gauge')))).toBe(false);
        const iberian = {
            key: 'track_gauge',
            portions: [{ ...param(raw('570000'), 'track_gauge').portions[0] }],
        };
        iberian.portions[0].rinf = { ...iberian.portions[0].rinf, key: '1668' };
        expect(showRow('track_gauge', summarize(iberian))).toBe(true);
    });
});

describe('lineRinf : accord avec SNCF Réseau', () => {
    it('ne compte pas le KVB que le registre déclare sur les voies à TVM ou ETCS', () => {
        const p = param(raw('752000'), 'train_protection');
        expect(p.summary.agree_m).toBe(0);
        const a = agreement(p);
        // 255 km de « TVM 430 et KVB » contre TVM 430 : comptés d'accord, montrés dans une note.
        expect([a.status, a.pct, a.kvb_only_m]).toEqual(['share', 35, 255023]);
        expect(a.gaps).toHaveLength(7);
        expect(a.gaps.some((g) => g.kvb_only)).toBe(false);
        const etcs = a.gaps.find((g) => g.pk_start_label === '1+790');
        expect(gapText('train_protection', 'rinf', etcs.rinf, t, 'fr')).toBe(
            `ETCS${NB}niveau${NB}2, TVM${NB}300, KVB et Crocodile`,
        );
        expect(gapText('train_protection', 'vff', etcs.vff, t, 'fr')).toBe(`TVM${NB}300`);
    });

    it('montre le DAAT et le Crocodile du registre contre le KVB de SNCF Réseau (Limoges - Périgueux)', () => {
        const a = agreement(param(raw('611000'), 'train_protection'));
        expect([a.status, a.pct]).toEqual(['share', 47]);
        expect(a.gaps).toHaveLength(1);
        const [g] = a.gaps;
        expect([g.pk_start_label, g.pk_end_label]).toEqual(['448+478', '499+246']);
        expect(gapText('train_protection', 'rinf', g.rinf, t, 'fr')).toBe('DAAT et Crocodile');
        expect(gapText('train_protection', 'vff', g.vff, t, 'fr')).toBe('KVB');
        expect(gapText('train_protection', 'vff', { key: 'none', values: ['BAL'] }, t, 'fr')).toBe(
            'aucun contrôle de vitesse',
        );
    });

    it('dit quand SNCF Réseau ne publie rien et n’arrondit jamais un écart à 100 %', () => {
        const luchon = raw('668000');
        expect(agreement(param(luchon, 'train_protection')).status).toBe('no_other');
        expect(agreement(param(luchon, 'gauging'))).toBeNull();
        const e = agreement(param(luchon, 'electrification'));
        expect([e.status, e.pct, e.gaps.length]).toEqual(['share', 1, 1]);
        const nearly = {
            key: 'max_speed',
            summary: { agree_m: 99950, disagree_m: 50, vff_m: 100000, kvb_only_m: 0 },
            portions: [],
        };
        expect(agreement(nearly).pct).toBe(99);
        expect(agreement({ ...nearly, summary: { ...nearly.summary, agree_m: 0 } }).status).toBe(
            'none',
        );
        expect(agreement(param(raw('570000'), 'radio')).status).toBe('all');
    });
});

describe('LineRinfFacts (rendu serveur)', () => {
    it('donne le résumé, les écarts, la note du KVB, les liens du glossaire et les sources', () => {
        const { body } = render(LineRinfFacts, { props: { data: raw('752000') } });
        // Une liste de définitions, pas de tableau.
        expect(body).toContain('<dl class="facts');
        expect(body).not.toContain('<table');
        expect(body).toContain('Vitesse déclarée au registre');
        expect(body).toContain(`PK${NB}0+994 à 710+547`);
        expect(body).toContain('cet écart n’est pas compté');
        expect(body).toContain('Voir les 7 écarts');
        expect(body).toContain(
            `Registre${NB}: ETCS${NB}niveau${NB}2, TVM${NB}300, KVB et Crocodile`,
        );
        expect(body).toContain(`SNCF Réseau${NB}: TVM${NB}300`);
        // Termes du glossaire : chaque paramètre, puis la première mention de chaque système.
        for (const term of [
            'gabarit',
            'categorie-de-charge',
            'gsm-r',
            'controle-de-vitesse',
            'ertms-niveau-2',
            'kvb',
            'crocodile',
        ])
            expect(body).toContain(`href="/glossary#${term}"`);
        expect(body.match(/href="\/glossary#kvb"/g)).toHaveLength(1);
        // Écartement standard : pas de ligne.
        expect(body).not.toContain('Écartement');
        // Mention de la source du RINF et licences.
        expect(body).toContain(
            'RINF data © European Union Agency for Railways (ERA Knowledge Graph), modified by VFF',
        );
        expect(body).toContain('licence ODbL 1.0');
    });

    it('liste un écart unique sans le replier (Limoges - Périgueux)', () => {
        const { body } = render(LineRinfFacts, { props: { data: raw('611000') } });
        expect(body).not.toContain('<details');
        expect(body).toContain(`Registre${NB}: DAAT et Crocodile`);
        expect(body).toContain(`SNCF Réseau${NB}: KVB`);
        expect(body).toContain('href="/glossary#daat"');
    });
});
