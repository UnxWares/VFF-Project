import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const listen = vi.fn();
const leave = vi.fn();
const priv = vi.fn(() => ({ listen }));
const echoOptions = [];

vi.mock('laravel-echo', () => ({
    default: class {
        constructor(options) {
            echoOptions.push(options);
        }
        private(channel) {
            return priv(channel);
        }
        leave(channel) {
            leave(channel);
        }
    },
}));
vi.mock('pusher-js', () => ({ default: class {} }));

const { CONTRIBUTION_EVENTS, createRefreshScheduler, listenPrivate } =
    await import('../../resources/js/lib/realtime.js');

const config = {
    key: 'cle-publique',
    host: 'vff-project.org',
    port: 443,
    scheme: 'https',
    auth_endpoint: '/broadcasting/auth',
};

describe('listenPrivate', () => {
    it('ne charge rien sans configuration (temps réel désactivé)', () => {
        const stop = listenPrivate(null, 'review-queue', () => {});
        stop();
        expect(priv).not.toHaveBeenCalled();
    });

    it('écoute les quatre événements de contribution du canal privé, puis le quitte', async () => {
        const onEvent = vi.fn();
        const stop = listenPrivate(config, 'review-queue', onEvent);
        await vi.waitFor(() => expect(priv).toHaveBeenCalledWith('review-queue'));
        expect(listen.mock.calls.map(([name]) => name)).toEqual(
            CONTRIBUTION_EVENTS.map((name) => `.${name}`),
        );
        expect(echoOptions[0]).toMatchObject({
            broadcaster: 'reverb',
            key: 'cle-publique',
            wsHost: 'vff-project.org',
            wssPort: 443,
            forceTLS: true,
            authEndpoint: '/broadcasting/auth',
        });
        stop();
        expect(leave).toHaveBeenCalledWith('review-queue');
    });
});

describe('createRefreshScheduler', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('regroupe les événements rapprochés en un seul rafraîchissement', () => {
        const refresh = vi.fn();
        const s = createRefreshScheduler(refresh, { delay: 500 });
        s.request();
        s.request();
        s.request();
        vi.advanceTimersByTime(500);
        expect(refresh).toHaveBeenCalledTimes(1);
        expect(s.pending).toBe(false);
    });

    it('attend la fin d’un refus en cours, puis rafraîchit au flush', () => {
        const refresh = vi.fn();
        let busy = true;
        const s = createRefreshScheduler(refresh, { delay: 100, isBusy: () => busy });
        s.request();
        vi.advanceTimersByTime(100);
        expect(refresh).not.toHaveBeenCalled();
        expect(s.pending).toBe(true);
        busy = false;
        s.flush();
        vi.advanceTimersByTime(100);
        expect(refresh).toHaveBeenCalledTimes(1);
    });

    it('n’envoie rien après cancel ni sur un flush sans demande', () => {
        const refresh = vi.fn();
        const s = createRefreshScheduler(refresh, { delay: 100 });
        s.flush();
        s.request();
        s.cancel();
        vi.advanceTimersByTime(500);
        expect(refresh).not.toHaveBeenCalled();
    });
});
