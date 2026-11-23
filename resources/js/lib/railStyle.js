// Palette et règles de rendu des tracés VFF, partagées par la carte principale
// (RailMap), sa légende (MapLegend), la carte intégrable et la mini-carte des
// fiches ligne : une seule source de vérité pour les couleurs.
//
// Principe :
//   - COULEUR d'une portion en service = son TRAFIC à la décennie affichée
//     (line_segments.traffic_type : passenger | mixed | freight | unknown) ;
//   - TRAIT (plein / tirets / pointillés) et teinte neutre = son STATUT quand
//     elle ne porte plus de trafic (fermée, déposée, disparue…) ;
//   - patrimoine/tourisme (is_heritage) : vert, prioritaire sur le trafic ;
//   - LGV : couleur DÉDIÉE (violet) et trait plus épais, pour distinguer
//     nettement le réseau à grande vitesse du réseau classique. Règle métier :
//     « si y'a du passager, y'a du fret » → le réseau classique ouvert aux
//     voyageurs est « mixte » ; seules les LGV sont « voyageurs seuls ».

/** Statuts portant du trafic (cf. App\Enums\SegmentStatus::isActive). */
export const ACTIVE_STATUSES = ['in_service', 'single_track'];

/** Couleur du réseau à grande vitesse (LGV et raccordements LGV). */
export const LGV_COLOR = '#7c3aed';

/**
 * Catégories des portions en service. `lgv` = réseau à grande vitesse
 * (is_lgv, ou traffic_type « passenger » qui n'est plus produit que pour les
 * LGV) ; les autres clés = traffic_type du réseau classique.
 */
export const TRAFFIC_STYLES = [
    { key: 'lgv', color: LGV_COLOR, thick: true }, // violet, trait épais : grande vitesse
    { key: 'mixed', color: '#ff6500' }, // orange VFF : réseau classique voyageurs + fret
    { key: 'freight', color: '#0284c7' }, // bleu : réseau classique fret seul
    { key: 'unknown', color: '#475569' }, // ardoise : trafic non renseigné
];

export const HERITAGE_COLOR = '#16a34a';

/**
 * Portions exploitées par une ASSOCIATION (line_segments.is_heritage +
 * heritage_kind) : vert patrimoine, trait plein pour un train touristique ou
 * un musée roulant (voie en service), pointillés pour un vélorail (voie en
 * place, plus de trains : statut « abandoned » côté moteur, mais JAMAIS dans
 * la couche rouge des lignes fermées). `key` = clé de visibilité de la légende.
 */
export const VELORAIL_DASH = [0.8, 1.6];
export const HERITAGE_STYLES = [
    { key: 'heritage', color: HERITAGE_COLOR, dash: null, labelKey: 'maps.legend.heritage_train' },
    {
        key: 'velorail',
        color: HERITAGE_COLOR,
        dash: VELORAIL_DASH,
        labelKey: 'maps.legend.heritage_velorail',
    },
];

/** Expression MapLibre : portion associative sans trains (vélorail). */
export function isVelorailExpr() {
    return [
        'all',
        ['==', ['get', 'is_heritage'], true],
        ['!', ['match', ['get', 'status'], ACTIVE_STATUSES, true, false]],
    ];
}

export const THREATENED_COLOR = '#dc2626';
/** Halo des lignes fermées pour lesquelles VFF soutient une campagne de réouverture. */
export const REOPENING_COLOR = '#0d9488'; // turquoise : distinct du vert « patrimoine »

/**
 * Statuts hors « en service ». `dash` = motif MapLibre (en largeurs de trait),
 * null = trait plein. `toggle` = clé de visibilité utilisée par la légende.
 */
export const STATUS_STYLES = [
    { key: 'service_track', color: '#94a3b8', dash: null, toggle: 'service_track' },
    { key: 'construction', color: '#ca8a04', dash: [2, 1], toggle: 'construction' },
    { key: 'abandoned', color: '#dc2626', dash: [2.5, 1.5], toggle: 'abandoned' },
    { key: 'dismantled', color: '#78716c', dash: [1, 1.5], toggle: 'dismantled' },
    { key: 'razed', color: '#a8a29e', dash: [0.6, 1.6], toggle: 'dismantled' },
];

/** @type {Record<string, string>} traffic_type → couleur */
export const TRAFFIC_COLOR = {
    ...Object.fromEntries(TRAFFIC_STYLES.map((t) => [t.key, t.color])),
    passenger: LGV_COLOR, // « voyageurs seuls » = grande vitesse
};
export const STATUS_COLOR = Object.fromEntries(STATUS_STYLES.map((s) => [s.key, s.color]));

/**
 * Gares : couleur du contour selon leur statut à la décennie affichée
 * (station_eras, cf. App\Services\StationTimeline). Mêmes teintes que les
 * lignes, car une gare ne peut pas être plus active que ses lignes : gare
 * voyageurs = orange du réseau mixte, fret seul = bleu du fret, touristique =
 * vert patrimoine. Une gare fermée (ou de statut inconnu) prend une teinte
 * neutre, JAMAIS celle d'une gare ouverte (stations.geojson exporte aussi
 * les gares fermées de l'ère). Vérifié par `php artisan vff:check-consistency` (contrôle stations.palette).
 *
 * Gare « incertaine » (status = uncertain, station_eras.uncertain) : ses
 * sources la disent ouverte mais aucune voie de l'ère ne le confirme (pas de
 * tronçon, même fermé, à moins de 300 m ; lignes non datées ; réseau non
 * modélisé). Elle n'est JAMAIS montrée ouverte : contour ardoise et disque
 * grisé, distincts du gris chaud des gares fermées.
 */
export const STATION_CLOSED_COLOR = '#a8a29e';
export const STATION_UNCERTAIN_COLOR = '#64748b';
export const STATION_UNCERTAIN_FILL = '#e2e8f0';
export const STATION_STYLES = [
    { key: 'active', color: TRAFFIC_COLOR.mixed, labelKey: 'maps.legend.station_active' },
    { key: 'tourist', color: HERITAGE_COLOR, labelKey: 'maps.legend.station_tourist' },
    { key: 'no_commercial', color: TRAFFIC_COLOR.freight, labelKey: 'maps.legend.station_freight' },
    {
        key: 'uncertain',
        color: STATION_UNCERTAIN_COLOR,
        fill: STATION_UNCERTAIN_FILL,
        labelKey: 'maps.legend.station_uncertain',
    },
    { key: 'declassified', color: STATION_CLOSED_COLOR, labelKey: 'maps.legend.station_closed' },
];

/** Expression MapLibre : couleur d'une gare ; statut absent ou inconnu = fermée (jamais orange). */
export function stationColorExpr() {
    return [
        'match',
        ['coalesce', ['get', 'status'], 'declassified'],
        ...STATION_STYLES.filter((s) => s.key !== 'declassified').flatMap((s) => [s.key, s.color]),
        STATION_CLOSED_COLOR,
    ];
}

/** Expression MapLibre : fond d'une gare (blanc ; grisé si incertaine). */
export function stationFillExpr() {
    return ['match', ['get', 'status'], 'uncertain', STATION_UNCERTAIN_FILL, '#ffffff'];
}

/** Expression MapLibre : épaisseur du contour d'une gare (plus fin si incertaine). */
export function stationStrokeWidthExpr() {
    return ['match', ['get', 'status'], 'uncertain', 1.5, 2];
}

/**
 * Voies partagées entre lignes (troncs communs, raccordements, ligne
 * particulière posée sur une ligne RFN…) : la carte montre l'état le PLUS
 * ACTIF de la voie (App\Support\TrackPriority : en service LGV > mixte > fret
 * > inconnu, puis associatif, puis fermée, puis déposée).
 *   - NOT_COVERED : écarte la partie d'une portion recouverte par une ligne
 *     plus active (tuiles : covered = true, cf. vff:build-shared-track) ;
 *   - DRAW_ORDER : `line-sort-key`, la portion la plus active dessinée au-dessus
 *     (tuiles : draw_rank).
 */
export const NOT_COVERED = ['!=', ['get', 'covered'], true];
export const DRAW_ORDER = ['coalesce', ['get', 'draw_rank'], 0];

/** Expression MapLibre : vrai pour une portion du réseau à grande vitesse. */
export function isLgvExpr() {
    return ['any', ['==', ['get', 'is_lgv'], true], ['==', ['get', 'traffic_type'], 'passenger']];
}

/** Expression MapLibre : couleur d'une portion EN SERVICE (LGV, sinon son trafic). */
export function trafficColorExpr() {
    return [
        'case',
        isLgvExpr(),
        LGV_COLOR,
        [
            'match',
            ['coalesce', ['get', 'traffic_type'], 'unknown'],
            ...['mixed', 'freight'].flatMap((k) => [k, TRAFFIC_COLOR[k]]),
            TRAFFIC_COLOR.unknown,
        ],
    ];
}

/**
 * Expression MapLibre : couleur complète d'une portion (patrimoine > statut non
 * actif > trafic). Utilisée par les couches « en service » et la mini-carte.
 */
export function segmentColorExpr() {
    return [
        'case',
        ['==', ['get', 'is_heritage'], true],
        HERITAGE_COLOR,
        ['match', ['get', 'status'], ACTIVE_STATUSES, true, false],
        trafficColorExpr(),
        [
            'match',
            ['get', 'status'],
            ...STATUS_STYLES.flatMap((s) => [s.key, s.color]),
            TRAFFIC_COLOR.unknown,
        ],
    ];
}

/**
 * Style CSS d'une pastille de légende reproduisant le trait (plein, tirets,
 * pointillés) et la couleur de la carte.
 */
export function swatchStyle(color, dash = null, thick = false) {
    const h = thick ? 6 : 4;
    if (!dash) return `background:${color};height:${h}px;`;
    const on = Math.max(2, Math.round(dash[0] * 4));
    const off = Math.max(2, Math.round(dash[1] * 4));
    return `background:repeating-linear-gradient(90deg, ${color} 0 ${on}px, transparent ${on}px ${on + off}px);height:${h}px;`;
}

/**
 * Clés de visibilité de la légende (toutes visibles par défaut).
 *   lgv | mixed | freight | unknown | heritage | velorail | service_track |
 *   construction | abandoned | dismantled
 */
export const TOGGLE_KEYS = [
    ...TRAFFIC_STYLES.map((t) => t.key),
    'heritage',
    'velorail',
    'service_track',
    'construction',
    'abandoned',
    'dismantled',
];

/**
 * Modes d'affichage de la carte (sélecteur de la légende) : la COULEUR des
 * tracés suit le trafic (défaut), l'électrification ou l'écartement de la
 * portion à la décennie affichée ; le TRAIT suit toujours le statut.
 * Données : line_segments.electrified / voltage_v / electrification_source et
 * gauge_mm / gauge_class (vff:enrich-era-segments), exposées par les tuiles.
 */
export const MAP_MODES = ['traffic', 'electrification', 'gauge'];

/**
 * Électrification : une couleur par tension. « Date inconnue » = portion
 * électrifiée aujourd'hui dont on ignore si elle l'était déjà à cette époque.
 */
export const ELECTRIFICATION_STYLES = [
    { key: 'ac25', color: '#dc2626', labelKey: 'maps.electrification.ac25' },
    { key: 'dc1500', color: '#2563eb', labelKey: 'maps.electrification.dc1500' },
    { key: 'dc3000', color: '#7c3aed', labelKey: 'maps.electrification.dc3000' },
    { key: 'ac15', color: '#059669', labelKey: 'maps.electrification.ac15' },
    { key: 'low', color: '#0891b2', labelKey: 'maps.electrification.low' },
    { key: 'other', color: '#c2410c', labelKey: 'maps.electrification.other' },
    { key: 'unknown_voltage', color: '#ec4899', labelKey: 'maps.electrification.unknown_voltage' },
    { key: 'unknown_date', color: '#fbbf24', labelKey: 'maps.electrification.unknown_date' },
    { key: 'none', color: '#9ca3af', labelKey: 'maps.electrification.none' },
];
const ELEC_COLOR = Object.fromEntries(ELECTRIFICATION_STYLES.map((s) => [s.key, s.color]));

/** Expression MapLibre : couleur d'une portion selon son électrification à l'ère. */
export function electrificationColorExpr() {
    const v = ['to-number', ['coalesce', ['get', 'voltage_v'], 0]];
    return [
        'case',
        ['any', ['==', ['get', 'electrified'], true], ['==', ['get', 'electrified'], 'true']],
        [
            'case',
            ['==', v, 0],
            ELEC_COLOR.unknown_voltage,
            ['==', v, 25000],
            ELEC_COLOR.ac25,
            ['==', v, 1500],
            ELEC_COLOR.dc1500,
            ['==', v, 3000],
            ELEC_COLOR.dc3000,
            ['==', v, 15000],
            ELEC_COLOR.ac15,
            ['<=', v, 1000],
            ELEC_COLOR.low,
            ELEC_COLOR.other,
        ],
        ['==', ['get', 'electrification_source'], 'unknown'],
        ELEC_COLOR.unknown_date,
        ELEC_COLOR.none,
    ];
}

/** Écartement : voie normale, métrique, étroite (600 Decauville, 700, 750…), large, inconnu. */
export const GAUGE_STYLES = [
    { key: 'standard', color: '#1d4ed8', labelKey: 'maps.gauge.standard' },
    { key: 'metre', color: '#ea580c', labelKey: 'maps.gauge.metre' },
    { key: 'narrow', color: '#16a34a', labelKey: 'maps.gauge.narrow' },
    { key: 'broad', color: '#9333ea', labelKey: 'maps.gauge.broad' },
    { key: 'unknown', color: '#cbd5e1', labelKey: 'maps.gauge.unknown' },
];
const GAUGE_COLOR = Object.fromEntries(GAUGE_STYLES.map((s) => [s.key, s.color]));

/** Expression MapLibre : couleur d'une portion selon son écartement à l'ère. */
export function gaugeColorExpr() {
    return [
        'match',
        ['coalesce', ['get', 'gauge_class'], 'unknown'],
        ...['standard', 'metre', 'narrow', 'broad'].flatMap((k) => [k, GAUGE_COLOR[k]]),
        GAUGE_COLOR.unknown,
    ];
}

/**
 * Colorations de la carte /maps (choix « Colorer les tracés selon » de la légende) : les modes de
 * MAP_MODES et deux colorations thématiques, la vitesse maximale (`speed_class` des tuiles, voir
 * App\Support\SpeedClass) et la signalisation (tranches du calque « Signalisation » posées sur le réseau
 * en teinte neutre). La carte intégrable garde MAP_MODES.
 */
export const MAP_COLORINGS = ['traffic', 'speed', 'electrification', 'signalling', 'gauge'];

/**
 * Vitesse maximale : quatre classes (bornes de App\Support\SpeedClass) et « inconnue ». Teintes
 * distinctes en clair comme en sombre, de la plus lente à la plus rapide ; la carte écrit aussi la classe
 * le long des tracés en zoomant et la liste de ce qui est affiché la donne en texte (lib/lineColoring.js).
 */
export const SPEED_STYLES = [
    { key: 'v100', cls: 1, color: '#15803d', labelKey: 'maps.coloring.speed.v100' },
    { key: 'v160', cls: 2, color: '#2563eb', labelKey: 'maps.coloring.speed.v160' },
    { key: 'v220', cls: 3, color: '#9333ea', labelKey: 'maps.coloring.speed.v220' },
    { key: 'v220_plus', cls: 4, color: '#dc2626', labelKey: 'maps.coloring.speed.v220_plus' },
    { key: 'unknown', cls: null, color: '#9ca3af', labelKey: 'maps.coloring.speed.unknown' },
];
const SPEED_COLOR = Object.fromEntries(SPEED_STYLES.map((s) => [s.key, s.color]));

/** Expression MapLibre : couleur d'une portion selon sa classe de vitesse maximale. */
export function speedColorExpr() {
    return [
        'match',
        ['to-number', ['coalesce', ['get', 'speed_class'], 0]],
        ...SPEED_STYLES.filter((s) => s.cls !== null).flatMap((s) => [s.cls, s.color]),
        SPEED_COLOR.unknown,
    ];
}

/**
 * Teinte neutre du réseau sous les tranches de signalisation (coloration « Signalisation ») : une
 * portion sans tranche connue reste visible, sans couleur qui la ferait passer pour une famille.
 */
export const SIGNALLING_BASE_COLOR = '#a8b0bc';

/**
 * Expression MapLibre : couleur d'une portion selon le mode d'affichage.
 * En mode trafic, `fallback` = la couleur propre de la couche (statut).
 */
export function modeColorExpr(mode, fallback) {
    if (mode === 'electrification') return electrificationColorExpr();
    if (mode === 'gauge') return gaugeColorExpr();
    if (mode === 'speed') return speedColorExpr();
    if (mode === 'signalling') return SIGNALLING_BASE_COLOR;
    return fallback;
}
