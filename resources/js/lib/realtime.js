/**
 * Temps réel du site (App\Support\Realtime côté serveur) : connexion Laravel Echo au serveur Reverb
 * auto-hébergé, chargée seulement quand la page reçoit une configuration (temps réel activé). Tant
 * que le temps réel est désactivé, ni laravel-echo ni pusher-js ne sont téléchargés.
 */

/** Événements de contribution diffusés (App\Enums\ContributionRealtimeEvent). */
export const CONTRIBUTION_EVENTS = [
    'contribution.submitted',
    'contribution.approved',
    'contribution.applied',
    'contribution.rejected',
];

let echoPromise = null;

/** Instance Echo partagée par la page, créée au premier abonnement. */
function connect(config) {
    echoPromise ??= Promise.all([import('laravel-echo'), import('pusher-js')]).then(
        ([{ default: Echo }, { default: Pusher }]) =>
            new Echo({
                broadcaster: 'reverb',
                key: config.key,
                wsHost: config.host,
                wsPort: config.port,
                wssPort: config.port,
                forceTLS: config.scheme === 'https',
                enabledTransports: ['ws', 'wss'],
                authEndpoint: config.auth_endpoint,
                Pusher,
            }),
    );
    return echoPromise;
}

/**
 * Écoute les événements de contribution d'un canal privé ; `onEvent` reçoit la charge utile.
 * Renvoie la fonction qui arrête l'écoute (à appeler au démontage). Sans configuration : rien.
 */
export function listenPrivate(config, channel, onEvent) {
    if (!config?.key || !config?.host) return () => {};
    let stopped = false;
    let echo = null;
    connect(config)
        .then((instance) => {
            if (stopped) return;
            echo = instance;
            const subscription = echo.private(channel);
            for (const name of CONTRIBUTION_EVENTS) subscription.listen(`.${name}`, onEvent);
        })
        .catch(() => {
            // Serveur injoignable ou bibliothèque absente : la page reste utilisable, sans direct.
        });
    return () => {
        stopped = true;
        echo?.leave(channel);
    };
}

/**
 * Rafraîchissement regroupé : plusieurs événements rapprochés donnent un seul appel à `refresh`,
 * et rien ne part tant que `isBusy()` est vrai (formulaire de refus ouvert, envoi en cours) ; le
 * rafraîchissement attend alors l'appel suivant à `flush()`.
 *
 * @param {() => void} refresh
 * @param {{ delay?: number, isBusy?: () => boolean }} [options]
 */
export function createRefreshScheduler(refresh, { delay = 800, isBusy = () => false } = {}) {
    let timer = null;
    let pending = false;

    function run() {
        timer = null;
        if (isBusy()) return;
        pending = false;
        refresh();
    }

    return {
        request() {
            pending = true;
            if (timer === null) timer = setTimeout(run, delay);
        },
        flush() {
            if (pending && timer === null) timer = setTimeout(run, delay);
        },
        cancel() {
            if (timer !== null) clearTimeout(timer);
            timer = null;
            pending = false;
        },
        get pending() {
            return pending;
        },
    };
}
