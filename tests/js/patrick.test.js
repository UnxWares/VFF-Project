// Clin d'œil « patrick » : séquence au clavier, champs de saisie ignorés, page /embed, mouvement réduit.
import { describe, expect, it, vi } from 'vitest';
import {
    PATRICK_KEY_GAP_MS,
    createSequenceWatcher,
    isEmbedPath,
    isTypingTarget,
    nextProgress,
} from '$lib/patrick.js';
import { CROSSING_MS, PATRICK_HREF, patrickPlan, patrickSvg } from '$lib/patrickTrain.js';

const key = (k, target = { tagName: 'BODY' }, extra = {}) => ({ key: k, target, ...extra });

/** Tape un mot touche par touche, une touche toutes les 100 ms. */
function type(handler, word, clock, target) {
    for (const k of word) {
        clock.t += 100;
        handler(key(k, target));
    }
}

describe('séquence « patrick »', () => {
    it('avance lettre à lettre et repart sur une lettre fausse', () => {
        expect(nextProgress(0, 'p')).toBe(1);
        expect(nextProgress(1, 'A')).toBe(2);
        expect(nextProgress(2, 'x')).toBe(0);
        // « pp » : la seconde lettre recommence le mot.
        expect(nextProgress(1, 'p')).toBe(1);
        expect(nextProgress(3, 'Shift')).toBe(0);
    });

    it('déclenche une fois le mot tapé, majuscules comprises', () => {
        const onMatch = vi.fn();
        const clock = { t: 0 };
        const handler = createSequenceWatcher({ onMatch, now: () => clock.t });
        type(handler, 'xxPaTrIcK', clock);
        expect(onMatch).toHaveBeenCalledTimes(1);
        type(handler, 'patric', clock);
        expect(onMatch).toHaveBeenCalledTimes(1);
    });

    it('ignore une frappe dans un champ de saisie ou un contenu éditable', () => {
        const onMatch = vi.fn();
        const clock = { t: 0 };
        const handler = createSequenceWatcher({ onMatch, now: () => clock.t });
        for (const tagName of ['INPUT', 'TEXTAREA', 'SELECT', 'input']) {
            type(handler, 'patrick', clock, { tagName });
        }
        type(handler, 'patrick', clock, { tagName: 'DIV', isContentEditable: true });
        expect(onMatch).not.toHaveBeenCalled();
        expect(isTypingTarget({ tagName: 'BUTTON' })).toBe(false);
        expect(isTypingTarget(null)).toBe(false);
    });

    it('une lettre tapée dans un champ au milieu du mot fait recommencer', () => {
        const onMatch = vi.fn();
        const clock = { t: 0 };
        const handler = createSequenceWatcher({ onMatch, now: () => clock.t });
        type(handler, 'patr', clock);
        type(handler, 'i', clock, { tagName: 'INPUT' });
        type(handler, 'ck', clock);
        expect(onMatch).not.toHaveBeenCalled();
    });

    it('ignore les raccourcis (Ctrl, Alt, Méta) et une pause trop longue', () => {
        const onMatch = vi.fn();
        const clock = { t: 0 };
        const handler = createSequenceWatcher({ onMatch, now: () => clock.t });
        for (const k of 'patrick') {
            clock.t += 100;
            handler(key(k, { tagName: 'BODY' }, { ctrlKey: true }));
        }
        type(handler, 'pat', clock);
        clock.t += PATRICK_KEY_GAP_MS + 1;
        type(handler, 'rick', clock);
        expect(onMatch).not.toHaveBeenCalled();
    });

    it('ne joue rien sur les pages intégrées /embed', () => {
        expect(isEmbedPath('/embed/maps')).toBe(true);
        expect(isEmbedPath('/embed')).toBe(true);
        expect(isEmbedPath('/embedded')).toBe(false);
        expect(isEmbedPath('/materials/tgv-sud-est')).toBe(false);
        const onMatch = vi.fn();
        const clock = { t: 0 };
        const handler = createSequenceWatcher({
            onMatch,
            now: () => clock.t,
            isDisabled: () => true,
        });
        type(handler, 'patrick', clock);
        expect(onMatch).not.toHaveBeenCalled();
    });
});

describe('passage de la rame', () => {
    it('mouvement réduit : la bulle seule, sans rame qui défile', () => {
        expect(patrickPlan(true).animate).toBe(false);
        expect(patrickPlan(false)).toEqual({ animate: true, lifetimeMs: expect.any(Number) });
        expect(patrickPlan(false).lifetimeMs).toBeGreaterThan(CROSSING_MS);
    });

    it('silhouette décorative en orange VFF, cachée aux lecteurs d’écran', () => {
        const svg = patrickSvg();
        expect(svg).toContain('aria-hidden="true"');
        expect(svg).toContain('#ff6500');
        expect(svg).not.toMatch(/<image|href=/);
        expect(PATRICK_HREF).toBe('/materials/tgv-sud-est');
    });
});

describe('dates des rames remarquables', async () => {
    const { notableDate, notableFactDate } = await import('$lib/notableUnits.js');
    const t = (key, o) => `${o.from} au ${o.to}`;

    it('écrit chaque date à sa précision', () => {
        expect(notableDate('1978-06-01', 'month', 'fr')).toBe('juin 1978');
        expect(notableDate('1981-01-01', 'year', 'de')).toBe('1981');
        expect(notableDate('2020-03-01', 'day', 'fr')).toBe('1er mars 2020');
        expect(notableDate('2020-03-01', 'day', 'en')).toBe('1 March 2020');
        expect(notableDate(null, null)).toBeNull();
    });

    it('période sur une même année : l’année une seule fois', () => {
        const fact = { date: '2020-02-29', date_to: '2020-03-01', precision: 'day' };
        expect(notableFactDate(fact, t, 'fr')).toBe('29 février au 1er mars 2020');
    });
});

describe('recherche par surnom', async () => {
    const { searchGroups } = await import('$lib/siteSearch.js');

    it('montre la rame trouvée par son surnom à la place du constructeur', () => {
        const t = (key, o) =>
            key === 'materials.notable.unit' ? `Rame ${o.number}, « ${o.nickname} »` : key;
        const groups = searchGroups({
            query: 'patrick',
            suggestions: false,
            searched: true,
            t,
            idPrefix: 's',
            results: {
                lines: [],
                stations: [],
                materials: [
                    {
                        slug: 'tgv-sud-est',
                        name: 'TGV Sud-Est',
                        builder: 'Alstom',
                        notable_unit: { number: '01', nickname: 'Patrick' },
                    },
                ],
            },
        });
        const item = groups.find((g) => g.type === 'material').items[0];
        expect(item.href).toBe('/materials/tgv-sud-est');
        expect(item.detail).toBe('Rame 01, « Patrick »');
    });
});

describe('branchement sur le document', async () => {
    const { installPatrick } = await import('$lib/patrick.js');

    it('ne fait rien sans document (rendu serveur)', () => {
        expect(() => installPatrick({ t: (k) => k })).not.toThrow();
    });

    it('guette la séquence sur le document, jamais sur une page /embed', () => {
        const listeners = [];
        vi.stubGlobal('document', { addEventListener: (type, fn) => listeners.push([type, fn]) });
        vi.stubGlobal('location', { pathname: '/embed/maps' });
        try {
            installPatrick({ t: (k) => k });
            expect(listeners.map(([type]) => type)).toEqual(['keydown']);
            // Page intégrée : la séquence complète ne charge pas le module d'affichage.
            for (const k of 'patrick') listeners[0][1](key(k));
        } finally {
            vi.unstubAllGlobals();
        }
    });

    it('la pause maximale entre deux touches est permise, pas une milliseconde de plus', () => {
        const onMatch = vi.fn();
        const clock = { t: 10_000 };
        const handler = createSequenceWatcher({ onMatch, now: () => clock.t });
        for (const k of 'patrick') {
            clock.t += PATRICK_KEY_GAP_MS;
            handler(key(k));
        }
        expect(onMatch).toHaveBeenCalledTimes(1);
        // Une touche hors séquence (Maj, flèche) remet à zéro.
        type(handler, 'pat', clock);
        clock.t += 100;
        handler(key('Shift'));
        type(handler, 'rick', clock);
        expect(onMatch).toHaveBeenCalledTimes(1);
        // Un événement déjà traité ailleurs (defaultPrevented) aussi.
        type(handler, 'patri', clock);
        handler({ ...key('c'), defaultPrevented: true });
        type(handler, 'k', clock);
        expect(onMatch).toHaveBeenCalledTimes(1);
    });
});
