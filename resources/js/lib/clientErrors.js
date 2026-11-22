// Erreurs JavaScript du navigateur, envoyées au serveur (POST /api/client-errors, App\Http\Controllers\
// ClientErrorController) pour qu'on les voie dans le journal `client-errors`. Aucune donnée personnelle :
// ni cookie, ni compte, ni contenu de la page, ni adresse avec sa requête. Au plus 5 envois par page
// chargée, chaque erreur une seule fois, échantillonnage réglé par CLIENT_ERRORS_SAMPLE_RATE.
// Logique pure testée par tests/js/clientErrors.test.js.

export const ENDPOINT = '/api/client-errors';
export const MAX_PER_PAGE = 5;

/** Adresse sans requête ni fragment. */
export function stripUrl(url) {
    try {
        const u = new URL(String(url), 'http://x.invalid');
        return String(url).startsWith('http')
            ? u.origin + u.pathname
            : String(url).split(/[?#]/)[0];
    } catch {
        return '';
    }
}

/**
 * Charge utile d'une erreur, bornée en taille.
 *
 * @param {{ type: string, message?: unknown, source?: unknown, line?: unknown, column?: unknown, stack?: unknown }} raw
 * @param {{ page: string, release?: string }} context
 */
export function buildPayload(raw, context) {
    return {
        type: raw.type,
        message: String(raw.message ?? '').slice(0, 300),
        page: stripUrl(context.page),
        source: raw.source ? stripUrl(raw.source) : null,
        line: Number.isFinite(Number(raw.line)) ? Number(raw.line) : null,
        column: Number.isFinite(Number(raw.column)) ? Number(raw.column) : null,
        stack: raw.stack ? String(raw.stack).slice(0, 1500) : null,
        release: context.release ?? null,
    };
}

/**
 * Crée le guetteur : renvoie `report(raw)`, qui envoie ou ignore (doublon, plafond, échantillon).
 *
 * @param {{ send: (payload: object) => void, page: () => string, release?: string, sampleRate?: number, random?: () => number }} options
 */
export function createReporter({ send, page, release, sampleRate = 1, random = Math.random }) {
    const seen = new Set();
    return function report(raw) {
        if (seen.size >= MAX_PER_PAGE) return false;
        if (random() >= sampleRate) return false;
        const key = `${raw.type}|${raw.message}|${raw.source}|${raw.line}`;
        if (seen.has(key)) return false;
        seen.add(key);
        send(buildPayload(raw, { page: page(), release }));
        return true;
    };
}

/** Branche le guetteur sur la fenêtre du navigateur. */
export function installClientErrors({ release, sampleRate } = {}) {
    const report = createReporter({
        page: () => location.href,
        release,
        sampleRate,
        send: (payload) => {
            try {
                fetch(ENDPOINT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                    keepalive: true,
                    credentials: 'omit',
                }).catch(() => {});
            } catch {
                // Le suivi ne doit jamais casser la page.
            }
        },
    });
    window.addEventListener('error', (e) => {
        report({
            type: 'error',
            message: e.message,
            source: e.filename,
            line: e.lineno,
            column: e.colno,
            stack: e.error?.stack,
        });
    });
    window.addEventListener('unhandledrejection', (e) => {
        const r = e.reason;
        report({ type: 'rejection', message: r?.message ?? r, stack: r?.stack });
    });
}
