// Copies de pages servies hors ligne (resources/js/pwa/offlineCopy.js) : date ajoutée à l'objet page d'Inertia.
import { describe, expect, it } from 'vitest';
import {
    htmlFromInertiaCopy,
    markHtmlCopy,
    markPage,
    readPageFromHtml,
    writePageToHtml,
} from '../../resources/js/pwa/offlineCopy.js';

const SAVED = '2026-11-19T12:00:00.000Z';

function html(page) {
    // Comme app.blade.php : JSON_HEX_TAG échappe < et >.
    const json = JSON.stringify(page).replace(/</g, '\\u003C').replace(/>/g, '\\u003E');
    return `<!DOCTYPE html><html lang="fr"><head><title>T</title></head><body><script data-page="app" type="application/json">${json}</script><div id="app"></div></body></html>`;
}

describe('markPage', () => {
    it('ajoute la date sans toucher aux autres propriétés', () => {
        const page = { component: 'Lines/Show', props: { line: { name: 'A' } }, url: '/lines/a' };
        expect(markPage(page, SAVED)).toEqual({
            ...page,
            props: { line: { name: 'A' }, offline_copy: { saved_at: SAVED } },
        });
        expect(page.props).not.toHaveProperty('offline_copy');
    });
});

describe('markHtmlCopy', () => {
    it('ajoute la date à l objet page du document', () => {
        const marked = markHtmlCopy(html({ component: 'Home', props: { a: 1 } }), SAVED);
        expect(readPageFromHtml(marked).props).toEqual({ a: 1, offline_copy: { saved_at: SAVED } });
    });

    it('garde le JSON sûr dans la balise script (texte saisi contenant des balises)', () => {
        const page = { component: 'Home', props: { note: '<!--<script></script> $& $1' } };
        const marked = markHtmlCopy(html(page), SAVED);
        expect(marked).not.toContain('<!--<script>');
        expect(marked.match(/<\/script>/g)).toHaveLength(1);
        expect(readPageFromHtml(marked).props.note).toBe('<!--<script></script> $& $1');
    });

    it('rend le document inchangé quand l objet page est introuvable ou illisible', () => {
        const ssr = '<html><body><div id="app" data-page="{}">rendu serveur</div></body></html>';
        expect(markHtmlCopy(ssr, SAVED)).toBe(ssr);
        const broken = '<script data-page="app" type="application/json">{pas du json</script>';
        expect(markHtmlCopy(broken, SAVED)).toBe(broken);
    });
});

describe('htmlFromInertiaCopy', () => {
    it('glisse la réponse Inertia d une page dans le document d une autre', () => {
        const shell = html({ component: 'Lines/Index', props: { secret: 'non' } });
        const body = htmlFromInertiaCopy(
            shell,
            { component: 'Lines/Show', props: { title: 'B' }, url: '/lines/b' },
            SAVED,
        );
        const page = readPageFromHtml(body);
        expect(page.component).toBe('Lines/Show');
        expect(page.props).toEqual({ title: 'B', offline_copy: { saved_at: SAVED } });
        expect(body).not.toContain('Lines/Index');
    });

    it('refuse une enveloppe sans objet page', () => {
        expect(writePageToHtml('<html></html>', {})).toBeNull();
        expect(htmlFromInertiaCopy('<html></html>', { props: {} }, SAVED)).toBeNull();
    });
});
