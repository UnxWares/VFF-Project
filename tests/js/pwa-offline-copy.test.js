// Copies de pages servies hors ligne (resources/js/pwa/offlineCopy.js) : date ajoutée à l'objet page d'Inertia.
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import {
    fillOnceProps,
    hasEssentialOnceProps,
    htmlFromInertiaCopy,
    markHtmlCopy,
    markPage,
    missingOnceProps,
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

// Vraies réponses du serveur de développement (19/11/2026), réduites : traductions à trois clés, listes
// à deux éléments. Une visite Inertia porte X-Inertia-Except-Once-Props : la réponse rangée annonce ses
// props « once » (`onceProps`) sans les porter.
const fixture = (name) =>
    readFileSync(fileURLToPath(new URL(`./fixtures/offline/${name}`, import.meta.url)), 'utf8');
const HOME_FR = fixture('home-fr.html');
const HOME_EN = fixture('home-en.html');
const ACCIDENTS_FR = fixture('accidents-fr.html');
const LINES = JSON.parse(fixture('lines.inertia.json'));
const ACCIDENTS_1950 = JSON.parse(fixture('accidents-decade-1950.inertia.json'));

describe('props « once » d une copie Inertia', () => {
    it('repère ce que la réponse rangée annonce sans le porter', () => {
        expect(missingOnceProps(LINES)).toEqual(['translations.fr']);
        expect(missingOnceProps(ACCIDENTS_1950)).toEqual(['translations.fr', 'accidents.facets']);
        expect(missingOnceProps(readPageFromHtml(ACCIDENTS_FR))).toEqual([]);
    });

    it('rend ses traductions à une page reconstruite dans une enveloppe de même langue', () => {
        const page = readPageFromHtml(htmlFromInertiaCopy(HOME_FR, LINES, SAVED));
        expect(page.component).toBe('Lines/Index');
        expect(page.props.translations).toEqual(readPageFromHtml(HOME_FR).props.translations);
        expect(page.props.translations['common.app_description']).toBeTruthy();
        expect(page.props.lines).toEqual(LINES.props.lines);
        expect(page.props.offline_copy).toEqual({ saved_at: SAVED });
        expect(LINES.props).not.toHaveProperty('translations'); // copie d origine intacte
    });

    it('ne prend jamais les traductions d une autre langue : pas de page sans texte', () => {
        expect(readPageFromHtml(HOME_EN).onceProps).toHaveProperty(['translations.en']);
        expect(htmlFromInertiaCopy(HOME_EN, LINES, SAVED)).toBeNull();
        expect(hasEssentialOnceProps(fillOnceProps(LINES, readPageFromHtml(HOME_EN)))).toBe(false);
    });

    it('reprend aussi les menus des accidents d une enveloppe qui les porte, avec leur expiration', () => {
        const shell = readPageFromHtml(ACCIDENTS_FR);
        const page = readPageFromHtml(htmlFromInertiaCopy(ACCIDENTS_FR, ACCIDENTS_1950, SAVED));
        expect(page.props.facets).toEqual(shell.props.facets);
        expect(page.props.translations).toEqual(shell.props.translations);
        expect(page.props.accidents).toEqual(ACCIDENTS_1950.props.accidents);
        expect(page.props.filters.decade).toBe(1950);
        expect(page.onceProps['accidents.facets'].expiresAt).toBe(
            shell.onceProps['accidents.facets'].expiresAt,
        );
    });

    it('garde une copie à qui seuls les menus manquent : la page a une valeur par défaut', () => {
        const page = readPageFromHtml(htmlFromInertiaCopy(HOME_FR, ACCIDENTS_1950, SAVED));
        expect(page.props.translations['common.app_description']).toBeTruthy();
        expect(page.props).not.toHaveProperty('facets');
        expect(missingOnceProps(page)).toEqual(['accidents.facets']);
        expect(hasEssentialOnceProps(page)).toBe(true);
    });

    it('compte comme fournies les clés que le navigateur recolle lui-même', () => {
        expect(hasEssentialOnceProps(LINES)).toBe(false);
        expect(hasEssentialOnceProps(LINES, ['translations.fr'])).toBe(true);
        expect(hasEssentialOnceProps(LINES, ['translations.en'])).toBe(false);
    });
});
