// Profil à plat d'une rame TGV Sud-Est dans sa livrée orange d'origine, dessiné pour VFF à l'échelle (aucune
// image reprise). Cotes publiées : rame de 200,19 m, deux motrices de 22,15 m (bogies à 14 m d'entraxe),
// remorques d'extrémité R1 et R8 de 21,845 m, six remorques intermédiaires de 18,70 m entre bogies Jacobs
// partagés, bogies de 3 m d'empattement, roues de 885 mm, caisse de 3,42 m au-dessus du rail. Le nez, le toit,
// les portes et la livrée suivent des photos de profil (sources et licences dans le rapport
// storage/app/reports/agents/nuit-2111/patrick-dessin.md).
// Unité du dessin : le décimètre. x part du nez de la motrice de tête (à gauche), h est la hauteur au-dessus du rail.

/** Longueurs de caisse (dm) : motrice, remorque d'extrémité, remorque intermédiaire. */
export const CAR_LENGTHS = { power: 221.5, end: 218.45, middle: 187 };

/** Longueur de la rame (dm) : 2 motrices, R1 et R8, six intermédiaires, soit 200,19 m. */
export const TRAIN_LENGTH = 2 * CAR_LENGTHS.power + 2 * CAR_LENGTHS.end + 6 * CAR_LENGTHS.middle;

/** Couleurs de la livrée : orange VFF, gris ardoise du bandeau des baies, blanc des bandes. */
export const LIVERY = {
    orange: '#ff6500',
    skirt: '#d95300',
    white: '#ffffff',
    band: '#3b3835',
    glass: '#1d1b1a',
    roof: '#c9c4bb',
    gutter: '#a39d93',
    cover: '#8c867d',
    under: '#2e2a27',
    wheel: '#1b1918',
    hub: '#6d675f',
    frame: '#4d4843',
};

// Bandes de la livrée, de bas en haut (dm au-dessus du rail).
const SKIRT = 4.4;
const BEVEL = 6.2;
const WHITE = 9.9;
const BAND = 13.7;
const FILET = 22.2;
const UPPER = 23;
const CANT_POWER = 27.4;
const CANT_TRAILER = 28.2;
const ROOF_POWER = 31.8;
const ROOF_TRAILER = 32.6;
const COVER = 34.2;

/** Rayon des roues (dm) : 885 mm de diamètre publié, arrondi à 0,88 m. */
const WHEEL_R = 4.4;
const HALF_WHEELBASE = 15;

// Positions des articulations (dm depuis le nez) : J0 motrice/R1, J1 à J7 bogies Jacobs, J8 R8/motrice.
const J0 = CAR_LENGTHS.power;
const JACOBS = Array.from({ length: 7 }, (_, i) => J0 + CAR_LENGTHS.end + i * CAR_LENGTHS.middle);
const J8 = TRAIN_LENGTH - CAR_LENGTHS.power;
/** Bogie moteur de R1 et de R8, sous l'extrémité côté motrice, à 18,70 m du bogie Jacobs voisin. */
const TRAILER_MOTOR_BOGIES = [JACOBS[0] - CAR_LENGTHS.middle, JACOBS[6] + CAR_LENGTHS.middle];

/** Cadre clair des baies, comme les encadrements des vitres sur les photos. */
const FRAME = ` rx="1.4" stroke="${LIVERY.hub}" stroke-width="0.4"`;

/** Hauteur du dessin (dm) : pantographe levé, ou replié sur le toit. */
export const PROFILE_TOP = { raised: 52, folded: 39 };

let uid = 0;

/** Arrondi au dixième, pour un balisage court. */
const r = (v) => Math.round(v * 10) / 10;

/**
 * Rectangle dessiné entre deux hauteurs.
 * @param {(h: number) => number} y
 */
function band(y, x0, x1, h0, h1, fill, extra = '') {
    return `<rect x="${r(x0)}" y="${y(h1)}" width="${r(x1 - x0)}" height="${r(h1 - h0)}" fill="${fill}"${extra}/>`;
}

/** Bogie : châssis sombre dans l'évidement de la caisse, deux essieux. `motor` : bogie moteur, plus long. */
function bogie(y, cx, motor = false) {
    const half = motor ? 22 : 20.5;
    const wheels = [-HALF_WHEELBASE, HALF_WHEELBASE]
        .map(
            (dx) =>
                `<circle cx="${r(cx + dx)}" cy="${y(WHEEL_R)}" r="${WHEEL_R}" fill="${LIVERY.wheel}"/>` +
                `<circle cx="${r(cx + dx)}" cy="${y(WHEEL_R)}" r="1.3" fill="${LIVERY.hub}"/>`,
        )
        .join('');

    return band(y, cx - half, cx + half, 1.2, motor ? 9 : 8.4, LIVERY.under, ' rx="1.6"') + wheels;
}

/**
 * Pantographe unijambiste, replié sur le toit ou levé vers la caténaire, articulation côté remorques.
 * Bras et archet en `currentColor` (gris du texte secondaire) : lisibles sur fond clair comme sombre.
 */
function pantograph(y, base, raised) {
    /** @type {[number, number]} */
    const hinge = [base + 17, ROOF_POWER + 1.6];
    /** @type {[number, number]} */
    const knee = raised ? [base + 2, 41.6] : [base + 1.5, ROOF_POWER + 2.2];
    /** @type {[number, number]} */
    const head = raised ? [base + 15, 50.2] : [base + 14, ROOF_POWER + 2.9];
    /** @param {[number, number]} point x et h, en dm */
    const pt = ([x, h]) => `${r(x)} ${y(h)}`;

    return (
        band(y, base, base + 19, ROOF_POWER, ROOF_POWER + 0.8, LIVERY.cover) +
        band(y, base + 1, base + 2.6, ROOF_POWER, ROOF_POWER + 1.8, LIVERY.hub) +
        band(y, base + 16, base + 17.6, ROOF_POWER, ROOF_POWER + 1.8, LIVERY.hub) +
        `<path d="M${pt(hinge)}L${pt(knee)}L${pt(head)}" fill="none" stroke="currentColor" stroke-width="0.9" stroke-linejoin="round" stroke-linecap="round"/>` +
        `<path d="M${pt([head[0] - 3.6, head[1] + 0.4])}H${r(head[0] + 3.6)}" stroke="currentColor" stroke-width="1.1" stroke-linecap="round"/>`
    );
}

/**
 * Motrice dessinée nez à gauche (x = 0) ; la motrice de queue en est le miroir.
 * Nez, pare-brise, porte de cabine, bandeau gris cerclé de blanc, persiennes, capot et deux pantographes.
 */
function powerCar(y, clipId, raised) {
    const end = CAR_LENGTHS.power - 2;
    const outline =
        `M${end} ${y(SKIRT)}V${y(ROOF_POWER - 1.6)}Q${end} ${y(ROOF_POWER)} ${end - 1.6} ${y(ROOF_POWER)}` +
        `H58Q54.4 ${y(ROOF_POWER)} 52 ${y(30.8)}L34 ${y(24)}` +
        `C22 ${y(23.2)} 9.5 ${y(21.6)} 4.2 ${y(18.6)}C1.2 ${y(16.8)} 0 ${y(15)} 0 ${y(12.8)}` +
        `C0 ${y(10.2)} 1.2 ${y(7.8)} 3.6 ${y(6.4)}L6.4 ${y(SKIRT)}Z`;
    const louvres = Array.from(
        { length: 26 },
        (_, i) => `M${r(64.2 + i * 2.2)} ${y(31.3)}v3.1`,
    ).join('');

    return (
        `<clipPath id="${clipId}"><path d="${outline}"/></clipPath>` +
        `<g clip-path="url(#${clipId})">` +
        band(y, 0, end, SKIRT, ROOF_POWER, LIVERY.orange) +
        band(y, 0, end, SKIRT, BEVEL, LIVERY.skirt) +
        band(y, 0, end, WHITE, BAND, LIVERY.white) +
        band(y, 60, end, CANT_POWER, ROOF_POWER, LIVERY.roof) +
        `<path d="M61.2 ${y(BAND)}V${y(20.8)}Q61.2 ${y(UPPER)} 63.4 ${y(UPPER)}H${end}V${y(BAND)}Z" fill="${LIVERY.white}"/>` +
        `<path d="M62 ${y(BAND)}V${y(20.6)}Q62 ${y(FILET)} 63.6 ${y(FILET)}H${end}V${y(BAND)}Z" fill="${LIVERY.band}"/>` +
        `<path d="M34.4 ${y(23.9)}L52.4 ${y(30.9)}H61.8V${y(25.8)}H52.6L39.6 ${y(22.3)}Z" fill="${LIVERY.frame}"/>` +
        `<path d="M36.2 ${y(24.1)}L50.8 ${y(29.8)}V${y(26.2)}L41 ${y(23.1)}Z" fill="${LIVERY.glass}"/>` +
        band(y, 0.8, 7.2, 15, 16.6, LIVERY.glass, ' rx="0.8"') +
        `<circle cx="2.6" cy="${y(15.8)}" r="0.7" fill="#fff4d6"/>` +
        '</g>' +
        band(y, 63, 120, 27.8, ROOF_POWER, LIVERY.band, ' rx="0.6"') +
        `<path d="${louvres}" stroke="${LIVERY.cover}" stroke-width="0.7"/>` +
        `<path d="M120 ${y(ROOF_POWER)}C120 ${y(33.6)} 121 ${y(COVER)} 124 ${y(COVER)}H164C167 ${y(COVER)} 168 ${y(33.6)} 168 ${y(ROOF_POWER)}Z" fill="${LIVERY.cover}"/>` +
        band(
            y,
            52.6,
            61.4,
            10.4,
            30.9,
            'none',
            ' rx="1" stroke="#000" stroke-opacity="0.3" stroke-width="0.5"',
        ) +
        band(y, 53.8, 60.2, 26.8, 30.3, LIVERY.glass, ' rx="0.8"') +
        pantograph(y, 172, false) +
        pantograph(y, 196, raised) +
        bogie(y, 40.75, true) +
        bogie(y, 180.75, true)
    );
}

/**
 * Remorque entre x0 et x1 (caisse, bandes, toit arrondi aux extrémités), porte à `door`, baies de `width`
 * espacées de `pitch` à partir de `first`.
 */
function trailer(y, x0, x1, { door, first, count, width, pitch }) {
    const roof =
        `<path d="M${r(x0)} ${y(CANT_TRAILER)}V${y(ROOF_TRAILER - 3)}Q${r(x0)} ${y(ROOF_TRAILER)} ${r(x0 + 3)} ${y(ROOF_TRAILER)}` +
        `H${r(x1 - 3)}Q${r(x1)} ${y(ROOF_TRAILER)} ${r(x1)} ${y(ROOF_TRAILER - 3)}V${y(CANT_TRAILER)}Z" fill="${LIVERY.roof}"/>`;
    const windows = Array.from({ length: count }, (_, i) =>
        band(y, first + i * pitch, first + i * pitch + width, 15, 21.2, LIVERY.glass, FRAME),
    ).join('');

    return (
        roof +
        band(y, x0, x1, SKIRT, CANT_TRAILER, LIVERY.orange) +
        band(y, x0, x1, CANT_TRAILER - 0.4, CANT_TRAILER, LIVERY.gutter) +
        band(y, x0, x1, SKIRT, BEVEL, LIVERY.skirt) +
        band(y, x0, x1, WHITE, BAND, LIVERY.white) +
        band(y, x0, x1, BAND, FILET, LIVERY.band) +
        band(y, x0, x1, FILET, UPPER, LIVERY.white) +
        windows +
        band(
            y,
            door,
            door + 9,
            9.6,
            26.4,
            'none',
            ' rx="1" stroke="#000" stroke-opacity="0.3" stroke-width="0.5"',
        ) +
        band(y, door + 2.4, door + 6.6, 15.4, 21.4, LIVERY.glass, ' rx="1"')
    );
}

/**
 * Les dix caisses avec leur étendue (dm) : M1, R1 à R8, M2. Portes : R1 côté motrice, juste derrière son bogie
 * moteur ; R2 à R7 à l'extrémité avant ; R8 en miroir de R1. Baies : première classe (R1 à R3) plus larges,
 * voiture bar (R4) avec un office aveugle, seconde classe (R5 à R8).
 */
function cars() {
    const first = { width: 13, pitch: 16.4 };
    const second = { width: 11, pitch: 14 };
    const list = [{ kind: 'power', x0: 0, x1: J0 }];
    list.push({
        kind: 'trailer',
        x0: J0 + 2,
        x1: JACOBS[0] - 1.4,
        opts: { door: J0 + 45.5, first: J0 + 61.5, count: 9, ...first },
    });
    JACOBS.slice(0, 6).forEach((j, i) => {
        const x0 = j + 1.4;
        const x1 = j + CAR_LENGTHS.middle - 1.4;
        const opts =
            i < 2
                ? { door: j + 4, first: j + 20, count: 10, ...first }
                : i === 2
                  ? { door: j + 4, first: j + 52, count: 8, ...first }
                  : { door: j + 4, first: j + 24, count: 11, ...second };
        list.push({ kind: 'trailer', x0, x1, opts });
    });
    list.push({
        kind: 'trailer',
        x0: JACOBS[6] + 1.4,
        x1: J8 - 2,
        opts: { door: J8 - 54.5, first: JACOBS[6] + 7, count: 11, ...second },
    });
    list.push({ kind: 'power', x0: J8, x1: TRAIN_LENGTH, mirrored: true });

    return list;
}

/**
 * Profil SVG de la rame (ou d'une partie : `from` et `to` en dm depuis le nez de tête), décoratif.
 *
 * @param {{
 *   number?: string,
 *   from?: number,
 *   to?: number,
 *   raised?: boolean,
 *   rail?: boolean,
 *   scale?: number,
 *   className?: string,
 *   fit?: string,
 *   id?: string,
 * }} [options] `raised` lève le pantographe de la motrice de queue ; `scale` en pixels par décimètre ;
 *   `fit` : valeur de preserveAspectRatio ; `id` : préfixe stable des identifiants internes (découpe du nez).
 */
export function tgvSudEstSvg({
    number = '01',
    from = 0,
    to = TRAIN_LENGTH,
    raised = false,
    rail = false,
    scale = 1,
    className = 'vff-tgv-sud-est',
    fit = 'xMinYMax meet',
    id = `vff-tgvse-${++uid}`,
} = {}) {
    const top = raised ? PROFILE_TOP.raised : PROFILE_TOP.folded;
    const bottom = rail ? 1 : 0;
    const y = (h) => r(top - h);
    const visible = cars().filter((c) => c.x1 > from && c.x0 < to);

    const bellows = [J0, ...JACOBS, J8]
        .filter((x) => x > from - 4 && x < to + 4)
        .map((x) => band(y, x - 2.6, x + 2.6, 8, 31, LIVERY.under))
        .join('');
    const body = visible
        .map((c) => {
            if (c.kind === 'trailer') {
                return trailer(y, c.x0, c.x1, c.opts);
            }
            const flip = c.mirrored
                ? ` transform="translate(${r(TRAIN_LENGTH)} 0) scale(-1 1)"`
                : '';
            const doorX = c.mirrored ? TRAIN_LENGTH - 61.4 + 1.5 : 54;

            return (
                `<g${flip}>${powerCar(y, `${id}-${c.mirrored ? 'b' : 'a'}`, raised && c.mirrored)}</g>` +
                `<text x="${r(doorX)}" y="${y(16.2)}" fill="${LIVERY.white}" font-family="Inter, Arial, sans-serif" font-size="4.6" font-weight="700">${number}</text>`
            );
        })
        .join('');
    const bogies = [...JACOBS, ...TRAILER_MOTOR_BOGIES]
        .filter((x) => x > from - 25 && x < to + 25)
        .map((x) => bogie(y, x, TRAILER_MOTOR_BOGIES.includes(x)))
        .join('');
    const ground = rail
        ? `<rect x="${r(from)}" y="${y(0)}" width="${r(to - from)}" height="1" fill="currentColor" opacity="0.35"/>`
        : '';
    const width = r(to - from);
    const height = top + bottom;

    return (
        `<svg class="${className}" viewBox="${r(from)} 0 ${width} ${height}" width="${r(width * scale)}" height="${r(height * scale)}" preserveAspectRatio="${fit}" aria-hidden="true" focusable="false">` +
        bellows +
        body +
        bogies +
        ground +
        '</svg>'
    );
}
