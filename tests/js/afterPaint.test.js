import { describe, expect, it } from 'vitest';
import { afterPaint } from '../../resources/js/lib/afterPaint.js';

describe('afterPaint', () => {
    it('attend l’image suivante puis une tâche à part', async () => {
        const order = [];
        let frame = null;
        let task = null;
        const promise = afterPaint({
            raf: (cb) => (frame = cb),
            defer: (cb) => (task = cb),
        }).then(() => order.push('résolue'));
        expect(frame).toBeTypeOf('function');
        expect(task).toBeNull();
        frame();
        order.push('image');
        expect(task).toBeTypeOf('function');
        await Promise.resolve();
        expect(order).toEqual(['image']);
        task();
        await promise;
        expect(order).toEqual(['image', 'résolue']);
    });

    it('passe à la tâche suivante sans requestAnimationFrame', async () => {
        let task = null;
        const promise = afterPaint({ raf: null, defer: (cb) => (task = cb) });
        expect(task).toBeTypeOf('function');
        task();
        await expect(promise).resolves.toBeUndefined();
    });
});
