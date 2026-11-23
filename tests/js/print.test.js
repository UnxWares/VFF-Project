import { describe, expect, it } from 'vitest';
import {
    applyFooterVariables,
    cssString,
    footerVariables,
    installPrint,
    prepareForPrint,
    printAddress,
} from '../../resources/js/lib/print.js';

const t = (key, options) =>
    ({
        'common.print.printed_on': `Imprimé le ${options?.date}`,
        'common.print.page': 'Page',
        'common.print.of': 'sur',
    })[key] ?? key;

describe('cssString', () => {
    it('protège les guillemets, les barres obliques et les retours à la ligne', () => {
        expect(cssString('a"b\\c\nd')).toBe('"a\\"b\\\\c d"');
    });
});

describe('printAddress', () => {
    it('retire le fragment et garde la requête', () => {
        expect(printAddress('https://vff-project.org/lines/x?era=1900#carte')).toBe(
            'https://vff-project.org/lines/x?era=1900',
        );
    });
    it('rend une adresse invalide sans son fragment', () => {
        expect(printAddress('pas une adresse#x')).toBe('pas une adresse');
    });
});

describe('footerVariables', () => {
    it('donne l’adresse, la date longue dans la langue et les mots du pied de page', () => {
        const v = footerVariables({
            href: 'https://vff-project.org/stations/paris-est#a',
            now: new Date('2026-11-23T10:00:00Z'),
            locale: 'fr',
            t,
        });
        expect(v['--vff-print-address']).toBe('"https://vff-project.org/stations/paris-est"');
        expect(v['--vff-print-date']).toBe('"Imprimé le 23 novembre 2026"');
        expect(v['--vff-print-page']).toBe('"Page"');
        expect(v['--vff-print-of']).toBe('"sur"');
    });
    it('retombe sur la date ISO si la langue est inconnue', () => {
        const v = footerVariables({
            href: 'https://x.y/',
            now: new Date('2026-11-23T10:00:00Z'),
            locale: 'xx-invalide-!!',
            t,
        });
        expect(v['--vff-print-date']).toBe('"Imprimé le 2026-11-23"');
    });
});

function fakeDoc() {
    const attrs = new Map();
    const detail = (open) => ({
        open,
        setAttribute() {
            this.open = true;
        },
        removeAttribute() {
            this.open = false;
        },
    });
    const details = [detail(false), detail(false)];
    const imgs = [{ loading: 'lazy' }, { loading: 'lazy' }];
    const button = {
        expanded: 'false',
        isConnected: true,
        click() {
            this.expanded = this.expanded === 'false' ? 'true' : 'false';
        },
        getAttribute() {
            return this.expanded;
        },
    };
    const buttons = [button];
    const link = (href) => ({
        href,
        marks: new Set(),
        setAttribute(n) {
            this.marks.add(n);
        },
        removeAttribute(n) {
            this.marks.delete(n);
        },
    });
    const links = [link('https://vff-project.org/stations/x'), link('https://fr.wikipedia.org/w')];
    return {
        details,
        imgs,
        buttons,
        links,
        attrs,
        location: { origin: 'https://vff-project.org' },
        querySelectorAll: (sel) =>
            sel.startsWith('details')
                ? details
                : sel.includes('button')
                  ? buttons
                  : sel.includes(' a[')
                    ? links
                    : imgs,
    };
}

describe('prepareForPrint', () => {
    it('ouvre les blocs repliables, déplie les listes et charge les images, puis remet tout', () => {
        const doc = fakeDoc();
        const restore = prepareForPrint(doc);
        expect(doc.details.every((d) => d.open)).toBe(true);
        expect(doc.buttons[0].expanded).toBe('true');
        expect(doc.links[0].marks.has('data-print-internal')).toBe(true);
        expect(doc.links[1].marks.has('data-print-internal')).toBe(false);
        expect(doc.imgs.every((i) => i.loading === 'eager')).toBe(true);
        restore();
        expect(doc.details.every((d) => !d.open)).toBe(true);
        expect(doc.buttons[0].expanded).toBe('false');
        expect(doc.links[0].marks.size).toBe(0);
        expect(doc.imgs.every((i) => i.loading === 'lazy')).toBe(true);
    });
});

describe('applyFooterVariables', () => {
    it('pose chaque variable sur l’élément racine', () => {
        const set = new Map();
        applyFooterVariables(
            { style: { setProperty: (k, v) => set.set(k, v) } },
            { href: 'https://x.y/', now: new Date(), locale: 'fr', t },
        );
        expect([...set.keys()].sort()).toEqual([
            '--vff-print-address',
            '--vff-print-date',
            '--vff-print-of',
            '--vff-print-page',
        ]);
    });
});

describe('installPrint', () => {
    it('prépare avant l’impression, défait après, et se débranche', () => {
        const listeners = {};
        const doc = fakeDoc();
        doc.documentElement = { style: { setProperty() {} } };
        const win = {
            document: doc,
            location: { href: 'https://vff-project.org/lines/x' },
            addEventListener: (n, f) => (listeners[n] = f),
            removeEventListener: (n) => delete listeners[n],
        };
        const off = installPrint(win, t, () => 'fr');
        listeners.beforeprint();
        expect(doc.details.every((d) => d.open)).toBe(true);
        listeners.afterprint();
        expect(doc.details.every((d) => !d.open)).toBe(true);
        off();
        expect(Object.keys(listeners)).toEqual([]);
    });
});
