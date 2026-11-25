// Section « Registre de l'infrastructure (RINF) » de la fiche ligne (LineRinf.svelte) : la réponse de
// GET /api/v1/lines/{line}/rinf-parameters (App\Services\Rinf\RinfParameterComparison) mise en résumé lisible d'un
// coup d'œil, valeur par valeur avec les PK quand elle change le long de la ligne, et l'accord avec SNCF Réseau.
// Règles retenues le 25/11/2026 : SNCF Réseau reste la référence de la carte, le registre est montré à côté avec ses
// écarts ; le KVB que le registre déclare sur les voies à TVM ou ETCS (`kvb_only`) est montré mais pas compté comme un
// désaccord ; le DAAT, le Crocodile et le KVB sont montrés avec leur source. Fonctions pures, sans DOM ; les textes
// passent par `t` (i18n) et la langue de la page.

import { formatLocale, formatNumber, NBSP, withUnit } from './typo.js';
import { voltageText } from './lineElectrification.js';

/** Paramètres montrés, dans l'ordre de la section (ordre de `parameters[]` de l'API). */
export const ROWS = [
    'electrification',
    'max_speed',
    'train_protection',
    'radio',
    'gauging',
    'load_capability',
    'track_gauge',
];

/** Paramètres que l'API compare à SNCF Réseau. */
export const COMPARED = ['electrification', 'max_speed', 'train_protection', 'radio'];

/** Au-delà de ce nombre de valeurs successives, le résumé ne les liste plus par PK. */
export const MAX_RUNS = 6;

/** Écartement standard (mm) : l'écartement n'est montré que si le registre en déclare un autre. */
export const STANDARD_GAUGE_MM = 1435;

/** Terme du glossaire (ancre de /glossary) de chaque paramètre. */
export const ROW_TERMS = {
    electrification: 'electrification',
    max_speed: 'vitesse-maximale',
    train_protection: 'controle-de-vitesse',
    radio: 'gsm-r',
    gauging: 'gabarit',
    load_capability: 'categorie-de-charge',
    track_gauge: 'ecartement',
};

/** Terme du glossaire de chaque système de contrôle de vitesse ou de protection déclaré. */
export const SYSTEM_TERMS = {
    'ETCS L1': 'ertms-niveau-1',
    'ETCS L2': 'ertms-niveau-2',
    TVM: 'tvm',
    'TVM 300': 'tvm-300',
    'TVM 430': 'tvm-430',
    KVB: 'kvb',
    KVBP: 'kvb',
    DAAT: 'daat',
    Crocodile: 'crocodile',
};

/**
 * Le registre décrit-il la ligne ? Une ligne qu'il ne décrit pas reçoit des listes vides (section absente).
 * @param {any} data réponse de l'API
 */
export function hasRinf(data) {
    return (
        Array.isArray(data?.sections) &&
        data.sections.length > 0 &&
        (data.parameters ?? []).some((p) => (p.portions ?? []).some((x) => x.rinf))
    );
}

/**
 * Versions GSM-R nommées par le vocabulaire de l'ERA (« Baseline 1 ») ; un code qu'il ne définit pas
 * (« gsmr-versions/10 ») n'est pas montré.
 * @param {{ values: string[] }} v
 */
export function gsmrVersions(v) {
    return (v.values ?? []).filter((x) => /^Baseline\b/.test(x));
}

/**
 * Clé d'affichage d'une valeur du registre : deux portions voisines de même clé ne font qu'une dans le résumé.
 * @param {string} key paramètre
 * @param {any} v valeur (`rinf` d'une portion)
 */
export function displayKey(key, v) {
    switch (key) {
        case 'max_speed':
            return String(v.speed_kmh ?? v.key);
        case 'train_protection':
            return v.values.length ? v.values.join('+') : 'none';
        case 'radio':
            return v.key === 'gsm-r' ? `gsm-r|${gsmrVersions(v).join('+')}` : 'none';
        case 'gauging':
            return v.values.length ? v.values.join('+') : String(v.key);
        case 'load_capability':
            return `${v.key}|${v.values.join('+')}`;
        default:
            return String(v.key);
    }
}

/**
 * Valeurs successives du registre le long de la ligne, tronçon par tronçon puis dans l'ordre des PK : les portions
 * voisines de même valeur affichée sont réunies (par-dessus un trou de PK aussi, tant que le PK avance ; deux
 * tronçons qui partagent des PK restent séparés). `length_m` : longueur décrite, trous exclus.
 * @param {{ key: string, portions: any[] }} parameter
 * @returns {Array<{ part: number, from_m: number, to_m: number, from: string, to: string, length_m: number, key: string, value: any }>}
 */
export function runs(parameter) {
    const items = parameter.portions
        .filter((p) => p.rinf)
        .map((p) => ({
            part: p.rg_troncon ?? 0,
            from_m: p.pk_start_m,
            to_m: p.pk_end_m,
            from: p.pk_start_label,
            to: p.pk_end_label,
            length_m: p.length_m,
            key: displayKey(parameter.key, p.rinf),
            value: p.rinf,
        }))
        .sort((a, b) => a.part - b.part || a.from_m - b.from_m || a.to_m - b.to_m);
    /** @type {typeof items} */
    const out = [];
    for (const item of items) {
        const last = out.at(-1);
        if (
            last &&
            last.key === item.key &&
            (last.part === item.part || item.from_m >= last.to_m)
        ) {
            if (item.to_m > last.to_m) {
                last.to_m = item.to_m;
                last.to = item.to;
            }
            last.length_m += item.length_m;
            continue;
        }
        out.push({ ...item });
    }
    return out;
}

/**
 * Résumé d'un paramètre : une seule valeur (`single`), quelques valeurs avec leurs PK (`runs`), l'étendue des
 * vitesses (`range`) ou, au-delà de MAX_RUNS valeurs successives, les valeurs avec leur longueur (`shares`).
 * Null quand le registre ne dit rien.
 * @param {{ key: string, portions: any[] }} parameter
 */
export function summarize(parameter) {
    const list = runs(parameter);
    if (!list.length) return null;
    const keys = [...new Set(list.map((r) => r.key))];
    if (keys.length === 1) return { kind: 'single', value: list[0].value, runs: list };
    if (list.length <= MAX_RUNS) return { kind: 'runs', runs: list };
    if (parameter.key === 'max_speed') {
        const speeds = list.map((r) => r.value.speed_kmh).filter((s) => Number.isFinite(s));
        return { kind: 'range', min: Math.min(...speeds), max: Math.max(...speeds), runs: list };
    }
    /** @type {Map<string, { value: any, length_m: number }>} */
    const byKey = new Map();
    for (const r of list) {
        const entry = byKey.get(r.key) ?? { value: r.value, length_m: 0 };
        entry.length_m += r.length_m;
        byKey.set(r.key, entry);
    }
    const shares = [...byKey.values()].sort((a, b) => b.length_m - a.length_m);
    return {
        kind: 'shares',
        shares: shares.slice(0, MAX_RUNS),
        more: Math.max(0, shares.length - MAX_RUNS),
        runs: list,
    };
}

/**
 * La ligne du paramètre est-elle montrée ? L'écartement seulement s'il diffère de l'écartement standard.
 * @param {string} key
 * @param {ReturnType<typeof summarize>} summary
 */
export function showRow(key, summary) {
    if (!summary) return false;
    if (key !== 'track_gauge') return true;
    return summary.runs.some((r) => Number(r.value.key) !== STANDARD_GAUGE_MM);
}

/**
 * Accord avec SNCF Réseau d'un paramètre comparé, null pour un paramètre sans autre source. `pct` : part de la
 * longueur comparée (décrite par les deux sources) où elles sont d'accord, arrondie vers le bas (jamais « 100 % »
 * avec un écart), le KVB des voies à TVM ou ETCS (`kvb_only_m`) compté d'accord. `gaps` : désaccords comptés, dans
 * l'ordre du tronçon et des PK.
 * @param {{ key: string, summary: Record<string, number>, portions: any[] }} parameter
 */
export function agreement(parameter) {
    if (!COMPARED.includes(parameter.key)) return null;
    const s = parameter.summary;
    const kvbOnly = s.kvb_only_m ?? 0;
    const compared = s.agree_m + s.disagree_m;
    if (compared <= 0) {
        return {
            status: s.vff_m > 0 ? 'no_overlap' : 'no_other',
            pct: null,
            compared_m: 0,
            kvb_only_m: 0,
            gaps: [],
        };
    }
    const agreeing = Math.min(compared, s.agree_m + kvbOnly);
    const pct = Math.floor((100 * agreeing) / compared);
    const status =
        agreeing >= compared ? 'all' : agreeing === 0 ? 'none' : pct < 1 ? 'share_low' : 'share';
    const gaps = parameter.portions
        .filter((p) => p.status === 'disagree' && !p.kvb_only)
        .sort(
            (a, b) =>
                (a.rg_troncon ?? 0) - (b.rg_troncon ?? 0) ||
                a.pk_start_m - b.pk_start_m ||
                a.pk_end_m - b.pk_end_m,
        );
    return { status, pct, compared_m: compared, kvb_only_m: kvbOnly, gaps };
}

/** Kilomètres d'une longueur en mètres, au dixième (« 0,9 », « 255 »). */
export function kmText(m, lang) {
    return formatNumber(m / 1000, lang, { maximumFractionDigits: 1 });
}

/**
 * Liste dans la langue de la page (« a, b et c », « a ou b »).
 * @param {string[]} items
 * @param {string} lang
 * @param {'conjunction'|'disjunction'} [type]
 */
export function listText(items, lang, type = 'conjunction') {
    try {
        return new Intl.ListFormat(formatLocale(lang), { style: 'long', type }).format(items);
    } catch {
        return items.join(', ');
    }
}

/**
 * Électrification d'après sa clé (`1500V-DC`, `25000V-50Hz`, `none`, `electrified`) ; deux voies d'une section
 * qui ne disent pas la même chose (`1500V-DC|none`) : les deux, « ou ».
 * @param {string} key
 * @param {(k: string, o?: any) => string} t
 * @param {string} lang
 */
export function electrificationText(key, t, lang) {
    const fmt = (n) => formatNumber(n, lang);
    const one = (k) => {
        if (k === 'none') return t('rinf.value.not_electrified');
        const m = /^(\d+)V-(?:(DC)|([\d.]+)Hz)$/.exec(k);
        if (!m) return t('rinf.value.electrified_unknown');
        const volt = voltageText(Number(m[1]), fmt);
        return m[2]
            ? t('rinf.value.dc', { volt })
            : t('rinf.value.ac', { volt, freq: `${fmt(Number(m[3]))}${NBSP}Hz` });
    };
    return listText(String(key).split('|').map(one), lang, 'disjunction');
}

/**
 * Nom affiché d'un système déclaré (« ETCS L2 » : « ETCS niveau 2 »), jamais coupé en fin de ligne (« TVM 300 »).
 * @param {string} system
 * @param {(k: string, o?: any) => string} t
 */
export function systemLabel(system, t) {
    const etcs = /^ETCS L(\d)$/.exec(system);
    return (etcs ? t('rinf.value.etcs_level', { level: etcs[1] }) : system).replaceAll(' ', NBSP);
}

/**
 * Séparateurs d'une liste de `count` éléments dans la langue de la page (« , », « et »), pour la composer élément
 * par élément (liens) : `seps[i - 1]` précède l'élément `i`.
 * @param {number} count
 * @param {string} lang
 * @returns {string[]}
 */
export function listSeparators(count, lang) {
    if (count < 2) return [];
    try {
        const parts = new Intl.ListFormat(formatLocale(lang), {
            style: 'long',
            type: 'conjunction',
        }).formatToParts(Array.from({ length: count }, (_, i) => `x${i}`));
        const seps = [];
        let pending = '';
        for (const part of parts) {
            if (part.type === 'literal') pending += part.value;
            else {
                seps.push(pending);
                pending = '';
            }
        }
        return seps.slice(1);
    } catch {
        return Array.from({ length: count - 1 }, () => ', ');
    }
}

/**
 * Systèmes déclarés au registre, chacun avec son terme du glossaire (null s'il n'en a pas) et le séparateur qui le
 * précède dans la liste (« , », « et »).
 * @param {string[]} values
 * @param {(k: string, o?: any) => string} t
 * @param {string} lang
 * @returns {Array<{ system: string, label: string, term: string|null, before: string }>}
 */
export function protectionTokens(values, t, lang) {
    const seps = listSeparators(values.length, lang);
    return values.map((system, i) => ({
        system,
        label: systemLabel(system, t),
        term: SYSTEM_TERMS[system] ?? null,
        before: i === 0 ? '' : seps[i - 1],
    }));
}

/**
 * Première mention de chaque terme du glossaire dans des listes de systèmes : seule elle devient un lien (`link`),
 * les suivantes restent du texte, comme les sigles des autres sections.
 * @template {{ term: string|null }} T
 * @param {T[][]} lists
 * @returns {Array<Array<T & { link: boolean }>>}
 */
export function linkFirst(lists) {
    const seen = new Set();
    return lists.map((tokens) =>
        tokens.map((token) => {
            const link = token.term !== null && !seen.has(token.term);
            if (token.term !== null) seen.add(token.term);
            return { ...token, link };
        }),
    );
}

/**
 * Catégorie de charge EN 15528 et classes nationales du gestionnaire (« D4 (classe nationale 5) »).
 * @param {{ key: string, values: string[] }} v
 * @param {(k: string, o?: any) => string} t
 * @param {string} lang
 */
export function loadText(v, t, lang) {
    const classes = v.values ?? [];
    if (!classes.length) return v.key;
    const list = listText(classes, lang);
    if (!v.key)
        return t('rinf.value.national_class_only', { classes: list, count: classes.length });
    return t('rinf.value.national_class', {
        category: v.key,
        classes: list,
        count: classes.length,
    });
}

/**
 * Valeur du registre en texte (résumé et écarts).
 * @param {string} key paramètre
 * @param {any} v valeur `rinf`
 * @param {(k: string, o?: any) => string} t
 * @param {string} lang
 */
export function rinfText(key, v, t, lang) {
    switch (key) {
        case 'electrification':
            return electrificationText(v.key, t, lang);
        case 'max_speed':
            return withUnit(v.speed_kmh ?? Number(v.key), 'km/h', lang);
        case 'train_protection':
            return v.values.length
                ? listText(
                      v.values.map((s) => systemLabel(s, t)),
                      lang,
                  )
                : t('rinf.value.none_declared');
        case 'radio': {
            if (v.key !== 'gsm-r') return t('rinf.value.gsmr_no');
            const versions = gsmrVersions(v);
            return versions.length
                ? t('rinf.value.gsmr_version', { version: listText(versions, lang) })
                : t('rinf.value.gsmr_yes');
        }
        case 'gauging':
            return listText(v.values.length ? v.values : [String(v.key)], lang);
        case 'load_capability':
            return loadText(v, t, lang);
        case 'track_gauge':
            return withUnit(Number(v.key), 'mm', lang);
        default:
            return String(v.key);
    }
}

/**
 * Valeur d'un côté d'un écart : le registre avec tout ce qu'il déclare, SNCF Réseau avec la valeur comparée
 * (contrôle de vitesse : les systèmes que les deux savent nommer ; radio : GSM-R ou non).
 * @param {string} key paramètre
 * @param {'rinf'|'vff'} side
 * @param {any} v
 * @param {(k: string, o?: any) => string} t
 * @param {string} lang
 */
export function gapText(key, side, v, t, lang) {
    if (key === 'radio') return v.key === 'gsm-r' ? 'GSM-R' : t('rinf.value.no_gsmr');
    if (side === 'rinf') return rinfText(key, v, t, lang);
    if (key === 'train_protection') {
        return v.key === 'none'
            ? t('rinf.value.no_speed_control')
            : listText(
                  v.key.split('+').map((s) => systemLabel(s, t)),
                  lang,
              );
    }
    return rinfText(key, v, t, lang);
}
