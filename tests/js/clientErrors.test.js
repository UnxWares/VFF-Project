import { describe, expect, it, vi } from 'vitest';
import {
    MAX_PER_PAGE,
    buildPayload,
    createReporter,
    stripUrl,
} from '../../resources/js/lib/clientErrors.js';

describe('stripUrl', () => {
    it('retire la requête et le fragment', () => {
        expect(stripUrl('https://vff-project.org/lines/x?token=abc#top')).toBe(
            'https://vff-project.org/lines/x',
        );
    });
});

describe('buildPayload', () => {
    it('borne le message et la pile', () => {
        const p = buildPayload(
            { type: 'error', message: 'x'.repeat(900), stack: 'y'.repeat(5000) },
            { page: 'https://a.b/c?d=1' },
        );
        expect(p.message).toHaveLength(300);
        expect(p.stack).toHaveLength(1500);
        expect(p.page).toBe('https://a.b/c');
    });
});

describe('createReporter', () => {
    it('envoie une erreur une seule fois', () => {
        const send = vi.fn();
        const report = createReporter({ send, page: () => 'https://a.b/' });
        expect(report({ type: 'error', message: 'm', line: 1 })).toBe(true);
        expect(report({ type: 'error', message: 'm', line: 1 })).toBe(false);
        expect(send).toHaveBeenCalledTimes(1);
    });

    it('plafonne le nombre d envois par page', () => {
        const send = vi.fn();
        const report = createReporter({ send, page: () => 'https://a.b/' });
        for (let i = 0; i < 20; i++) report({ type: 'error', message: `m${i}` });
        expect(send).toHaveBeenCalledTimes(MAX_PER_PAGE);
    });

    it('respecte l echantillonnage', () => {
        const send = vi.fn();
        const report = createReporter({
            send,
            page: () => 'https://a.b/',
            sampleRate: 0.5,
            random: () => 0.9,
        });
        expect(report({ type: 'error', message: 'm' })).toBe(false);
        expect(send).not.toHaveBeenCalled();
    });
});
