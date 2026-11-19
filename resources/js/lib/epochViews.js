/**
 * Vues de la carte par époque : une décennie (vue habituelle), deux décennies comparées, ou
 * toutes les décennies empilées. Logique pure (URL, paires d'années, styles des calques),
 * partagée par la page /maps, la carte et la légende ; testée dans tests/js/epochViews.test.js.
 *
 * Les couleurs de ces calques s'ajoutent à celles de railStyle.js sans les modifier. Elles
 * passent le contrôle de palette du guide de visualisation (écarts de teinte et de clarté
 * lisibles en vision des couleurs atypique) ; le trait double toujours la couleur.
 */

/** Vues proposées, dans l'ordre du sélecteur. */
export const VIEWS = ['era', 'compare', 'stack'];

/**
 * Changements entre deux décennies (couche `vff-compare`, propriété `change`), dans l'ordre de la
 * légende et du résumé. `dash` : motif MapLibre en largeurs de trait (null = trait plein) ;
 * `width` : facteur de largeur ; les tronçons restés en service restent discrets (contexte).
 */
export const COMPARE_CHANGES = [
    { key: 'opened', color: '#1f6fd1', dash: null, width: 1.5, opacity: 1 },
    { key: 'closed', color: '#e0531f', dash: [2.5, 1.5], width: 1.5, opacity: 1 },
    { key: 'removed', color: '#8e2a5c', dash: [1, 1.5], width: 1.3, opacity: 1 },
    { key: 'kept', color: '#8b929c', dash: null, width: 0.8, opacity: 0.75 },
];

/**
 * Toutes les époques empilées : classes de la dernière décennie en service (propriété
 * `last_year` de la couche `vff-stack`), du plus clair (disparu tôt) au plus foncé (encore en
 * service). Une seule teinte, clarté décroissante : progression lisible sans arc-en-ciel.
 * `upTo` : dernière décennie de la classe (incluse) ; null = décennie actuelle.
 * Coupures choisies d'après les données (km par décennie de dernière mise en service) :
 * fermetures des années 1930, de l'après-guerre, des années 1960-1970, puis réseau actuel.
 */
export const STACK_CLASSES = [
    { key: 'before1940', upTo: 1930, color: '#86b6ef' },
    { key: 'y1940', upTo: 1950, color: '#5598e7' },
    { key: 'y1960', upTo: 1970, color: '#2a78d6' },
    { key: 'y1980', upTo: 'beforeCurrent', color: '#1c5cab' },
    { key: 'today', upTo: null, color: '#0d366b' },
];

const toYear = (value) => {
    const n = Number.parseInt(value ?? '', 10);
    return Number.isFinite(n) ? n : null;
};

/**
 * Deux décennies distinctes, dans l'ordre chronologique. Si elles sont égales, la seconde passe à
 * la décennie suivante (ou précédente pour la dernière). Renvoie null sans au moins deux ères.
 *
 * @param {number} a
 * @param {number} b
 * @param {number[]} eras décennies disponibles, croissantes
 * @returns {[number, number] | null}
 */
export function orderPair(a, b, eras) {
    if (eras.length < 2) return null;
    const known = (y) => (eras.includes(y) ? y : null);
    let first = known(a) ?? eras[0];
    let second = known(b) ?? eras[eras.length - 1];
    if (first === second) {
        const i = eras.indexOf(first);
        second = i < eras.length - 1 ? eras[i + 1] : eras[i - 1];
    }
    return first < second ? [first, second] : [second, first];
}

/**
 * Paire proposée en passant à la comparaison : la décennie affichée et le réseau actuel, ou
 * l'apogée du réseau (1930) et aujourd'hui si la carte montre déjà le réseau actuel.
 */
export function defaultPair(selected, eras, currentEra) {
    const latest = currentEra ?? eras[eras.length - 1];
    if (selected !== latest) return orderPair(selected, latest, eras);
    return orderPair(eras.includes(1930) ? 1930 : eras[0], latest, eras);
}

/**
 * Lit la vue dans l'adresse : `?era=1930&compare=1980` (comparaison), `?view=stack` (toutes les
 * époques), sinon `?era=1930` (une décennie). Les valeurs inconnues sont ignorées.
 *
 * @param {string} search partie « ?… » de l'adresse
 * @param {number[]} eras décennies disponibles, croissantes
 * @returns {{ view: 'era' | 'compare' | 'stack', era: number | null, compare: number | null }}
 */
export function readViewParams(search, eras) {
    const params = new URLSearchParams(search);
    const era = toYear(params.get('era'));
    const compare = toYear(params.get('compare'));
    const eraKnown = era !== null && eras.includes(era) ? era : null;
    if (params.get('view') === 'stack') return { view: 'stack', era: eraKnown, compare: null };
    if (compare !== null && eras.includes(compare)) {
        const pair = orderPair(eraKnown ?? eras[eras.length - 1], compare, eras);
        if (pair) return { view: 'compare', era: pair[0], compare: pair[1] };
    }
    return { view: 'era', era: eraKnown, compare: null };
}

/**
 * Écrit la vue dans l'adresse (les autres paramètres sont gardés). La décennie la plus récente
 * n'est pas écrite en vue « une décennie » (adresse courte du réseau actuel).
 *
 * @param {string} href adresse actuelle
 * @param {{ view: string, era: number, compare?: number | null }} state
 * @param {number} latest décennie la plus récente
 * @returns {string} nouvelle adresse
 */
export function writeViewParams(href, { view, era, compare = null }, latest) {
    const url = new URL(href);
    const params = url.searchParams;
    params.delete('view');
    params.delete('compare');
    params.delete('era');
    if (view === 'stack') {
        params.set('view', 'stack');
    } else if (view === 'compare' && compare !== null) {
        params.set('era', String(era));
        params.set('compare', String(compare));
    } else if (era !== latest) {
        params.set('era', String(era));
    }
    return url.toString();
}

/** Couleur des tracés de la comparaison (MapLibre `line-color`). */
export function compareColorExpr() {
    return [
        'match',
        ['get', 'change'],
        ...COMPARE_CHANGES.flatMap((c) => [c.key, c.color]),
        '#8b929c',
    ];
}

/** Largeur des tracés de la comparaison selon le zoom et le changement (`line-width`). */
export function compareWidthExpr() {
    const factor = [
        'match',
        ['get', 'change'],
        ...COMPARE_CHANGES.flatMap((c) => [c.key, c.width]),
        1,
    ];
    return [
        'interpolate',
        ['linear'],
        ['zoom'],
        4,
        ['*', 1.4, factor],
        8,
        ['*', 2.4, factor],
        12,
        ['*', 4, factor],
        16,
        ['*', 6, factor],
    ];
}

/** Opacité : les tronçons restés en service en retrait (contexte), les changements nets. */
export function compareOpacityExpr() {
    return ['match', ['get', 'change'], ...COMPARE_CHANGES.flatMap((c) => [c.key, c.opacity]), 1];
}

/** Ordre de dessin : changements au-dessus des tronçons restés en service (`line-sort-key`). */
export function compareSortExpr() {
    return ['match', ['get', 'change'], 'kept', 0, 'removed', 1, 'closed', 2, 3];
}

/** Bornes (dernière décennie incluse) de chaque classe de la vue empilée. */
export function stackBounds(currentEra) {
    return STACK_CLASSES.map((c) => ({
        ...c,
        upTo: c.upTo === 'beforeCurrent' ? currentEra - 10 : c.upTo === null ? currentEra : c.upTo,
    }));
}

/** Couleur de la vue empilée selon la dernière décennie en service (`line-color`). */
export function stackColorExpr(currentEra) {
    const bounds = stackBounds(currentEra);
    // step : couleur de la première classe, puis changement au-delà de chaque borne.
    const steps = bounds.slice(1).flatMap((c, i) => [bounds[i].upTo + 1, c.color]);
    return ['step', ['get', 'last_year'], bounds[0].color, ...steps];
}

/**
 * Libellé de chaque classe de la vue empilée : [clé i18n, paramètres]. Les décennies sont des
 * instantanés au 31/12 : « 1940 - 1950 » = dernière carte en service en 1940 ou en 1950.
 */
export function stackLabels(currentEra) {
    const bounds = stackBounds(currentEra);
    return bounds.map((c, i) => {
        if (i === 0) return { ...c, labelKey: 'maps.stack.until', params: { year: c.upTo } };
        if (c.upTo === currentEra) return { ...c, labelKey: 'maps.stack.today', params: {} };
        return {
            ...c,
            labelKey: 'maps.stack.range',
            params: { from: bounds[i - 1].upTo + 10, to: c.upTo },
        };
    });
}

/**
 * Résumé chiffré d'une comparaison (réponse de /api/v1/eras/{a}/compare/{b}) : les changements
 * dans l'ordre de la légende, la longueur en service à chaque date et le solde.
 *
 * @param {{ changes?: Record<string, { km: number, lines: number }>, open_km?: { from: number, to: number } } | null} data
 */
export function compareSummary(data) {
    if (!data?.changes) return null;
    const rows = COMPARE_CHANGES.map((c) => ({
        key: c.key,
        color: c.color,
        dash: c.dash,
        km: data.changes[c.key]?.km ?? 0,
        lines: data.changes[c.key]?.lines ?? 0,
    }));
    const openBefore = data.open_km?.from ?? 0;
    const openAfter = data.open_km?.to ?? 0;
    return {
        rows,
        openBefore,
        openAfter,
        balance: openAfter - openBefore,
        unchanged: rows.every((r) => r.key === 'kept' || r.km === 0),
    };
}
