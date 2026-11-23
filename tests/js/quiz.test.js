import { describe, expect, it } from 'vitest';
import {
    answerHref,
    bestKey,
    difficultyKey,
    factHints,
    GAMES,
    lineHints,
    materialHints,
    quizHref,
    readBest,
    saveBest,
    scaleBar,
    scoreOf,
    tracePath,
    traceViewBox,
} from '$lib/quiz.js';

const t = (key, opts) => (opts ? `${key} ${JSON.stringify(opts)}` : key);

/** Stockage en mémoire, ou qui refuse tout (navigation privée, quota). */
function memoryStorage() {
    const data = new Map();
    return {
        getItem: (k) => (data.has(k) ? data.get(k) : null),
        setItem: (k, v) => data.set(k, String(v)),
    };
}
const refusing = {
    getItem() {
        throw new Error('SecurityError');
    },
    setItem() {
        throw new Error('QuotaExceededError');
    },
};

describe('tracé', () => {
    it('écrit une sous-ligne par partie et ignore les parties trop courtes', () => {
        expect(
            tracePath([
                [
                    [0, 0],
                    [10, 5],
                ],
                [[3, 3]],
                [
                    [20, 20],
                    [30, 25],
                    [40, 20],
                ],
            ]),
        ).toBe('M0 0 L10 5 M20 20 L30 25 L40 20');
        expect(tracePath(null)).toBe('');
    });

    it('garde une marge et la place de la barre d’échelle', () => {
        expect(traceViewBox({ width: 1000, height: 400 })).toBe('-40 -40 1080 520');
    });

    it('choisit une longueur ronde qui tient dans un quart du tracé', () => {
        // 1 000 unités de 100 m : 100 km de large, barre de 25 km au plus.
        expect(scaleBar(100, 1000)).toEqual({ km: 25, units: 250 });
        expect(scaleBar(10, 1000)).toEqual({ km: 2, units: 200 });
        // Tracé minuscule : la plus petite barre.
        expect(scaleBar(0.1, 1000)).toEqual({ km: 0.5, units: 5000 });
        expect(scaleBar(0, 1000)).toBeNull();
    });
});

describe('adresses', () => {
    it('partage la graine et vérifie une réponse', () => {
        expect(quizHref('lines', 'hard')).toBe('/quiz?game=lines&level=hard');
        expect(quizHref('materials', 'easy', 'ab12')).toBe(
            '/quiz?game=materials&level=easy&seed=ab12',
        );
        expect(quizHref('materials', 'easy', 'ab12', '0a1b2c3d')).toBe(
            '/quiz?game=materials&level=easy&seed=ab12&v=0a1b2c3d',
        );
        // Une version sans graine n'a pas de sens : le serveur tire une graine et calcule la version.
        expect(quizHref('lines', 'easy', null, '0a1b2c3d')).toBe('/quiz?game=lines&level=easy');
    });

    it('envoie la version de la série avec chaque réponse', () => {
        expect(
            answerHref(
                { game: 'lines', level: 'easy', seed: 'ab12', version: '0a1b2c3d' },
                3,
                'rfn-610000',
            ),
        ).toBe('/quiz/answer?game=lines&level=easy&seed=ab12&v=0a1b2c3d&q=3&choice=rfn-610000');
    });
});

describe('indices en texte', () => {
    it('décrit la ligne sans valeur absente', () => {
        const rows = lineHints(
            {
                length_km: 152.8,
                era_year: 1930,
                era_is_current: false,
                opened: 1861,
                in_service: false,
                closed: 1953,
                departments: ['Gard', 'Lozère'],
            },
            t,
            'fr',
        );
        expect(rows.map((r) => r.id)).toEqual([
            'length',
            'era',
            'opened',
            'today',
            'closed',
            'departments',
        ]);
        expect(rows[0].value).toBe('quiz.hints.km {"value":"152,8"}');
        expect(rows[1].value).toBe('quiz.hints.era_past {"year":1930}');
        expect(rows[3].value).toBe('quiz.hints.out_of_service');
        expect(rows[5]).toMatchObject({ value: 'Gard, Lozère', lang: 'fr' });
        expect(
            lineHints({ length_km: 5, in_service: null, departments: [] }, t, 'fr').map(
                (r) => r.id,
            ),
        ).toEqual(['length']);
        expect(lineHints(null, t, 'fr')).toEqual([]);
    });

    it('décrit la série', () => {
        const rows = materialHints(
            {
                type: 'locomotive_electrique',
                from: 1996,
                to: null,
                builders: ['Alstom'],
                max_speed_kmh: 220,
                units_built: 60,
            },
            t,
            'en',
        );
        expect(rows.map((r) => [r.id, r.value])).toEqual([
            ['type', 'materials.type.locomotive_electrique'],
            ['service', 'materials.service.from {"year":1996}'],
            ['builders', 'Alstom'],
            ['speed', 'materials.unit.kmh {"value":"220"}'],
            ['units', '60'],
        ]);
        expect(materialHints({ from: 1962, to: 1998 }, t, 'fr')[0].value).toBe('1962-1998');
    });
});

describe('score', () => {
    it('compte les bonnes réponses', () => {
        expect(scoreOf({ 0: { correct: true }, 1: { correct: false }, 2: { correct: true } })).toBe(
            2,
        );
        expect(scoreOf(undefined)).toBe(0);
    });

    it('garde le meilleur score dans le navigateur seulement s’il progresse', () => {
        const s = memoryStorage();
        expect(readBest(s, 'lines', 'easy')).toBeNull();
        expect(saveBest(s, 'lines', 'easy', 6)).toBe(6);
        expect(saveBest(s, 'lines', 'easy', 4)).toBe(6);
        expect(saveBest(s, 'lines', 'easy', 9)).toBe(9);
        expect(s.getItem(bestKey('lines', 'easy'))).toBe('9');
        expect(readBest(s, 'lines', 'hard')).toBeNull();
    });

    it('survit à un stockage refusé ou absent', () => {
        expect(readBest(refusing, 'materials', 'hard')).toBeNull();
        expect(saveBest(refusing, 'materials', 'hard', 7)).toBe(7);
        expect(saveBest(undefined, 'materials', 'hard', 3)).toBe(3);
        const s = memoryStorage();
        s.setItem(bestKey('lines', 'easy'), 'abc');
        expect(readBest(s, 'lines', 'easy')).toBeNull();
    });
});

describe('jeu « Culture du rail »', () => {
    it('est un des jeux proposés', () => {
        expect(GAMES).toContain('facts');
    });

    it('garde les indices déjà rédigés par le serveur, avec un identifiant', () => {
        expect(factHints([{ label: 'Commune', value: 'Amiens' }])).toEqual([
            { id: 'Commune', label: 'Commune', value: 'Amiens' },
        ]);
        expect(factHints(undefined)).toEqual([]);
    });

    it('traduit le degré de difficulté en clé, et borne les valeurs étranges', () => {
        expect([1, 2, 3].map(difficultyKey)).toEqual(['easy', 'medium', 'hard']);
        expect(difficultyKey(0)).toBe('medium');
        expect(difficultyKey(9)).toBe('hard');
        expect(difficultyKey(undefined)).toBe('medium');
    });

    it('construit une adresse de série pour ce jeu', () => {
        expect(quizHref('facts', 'hard', 'abcd1234', 'deadbeef')).toBe(
            '/quiz?game=facts&level=hard&seed=abcd1234&v=deadbeef',
        );
    });
});
