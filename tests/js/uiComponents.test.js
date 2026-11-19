// Composants d'interface communs, rendus côté serveur (svelte/server) : l'accessibilité qu'ils
// promettent (rôles, aria-current, aria-describedby, aria-invalid, niveaux de titre) doit rester dans
// le HTML produit, quelle que soit la page qui les utilise.
import { createRawSnippet } from 'svelte';
import { render } from 'svelte/server';
import { describe, expect, it } from 'vitest';
import { setupI18n } from '$lib/i18n.js';
import Pagination from '../../resources/js/Components/Directory/Pagination.svelte';
import EmptyState from '../../resources/js/Components/ui/EmptyState.svelte';
import Field from '../../resources/js/Components/ui/Field.svelte';
import Notice from '../../resources/js/Components/ui/Notice.svelte';

setupI18n({
    locale: 'fr',
    translations: {
        'common.pagination': 'Pagination',
        'common.previous': 'Précédent',
        'common.next': 'Suivant',
        'common.page_of': 'Page :current sur :last',
    },
});

/** HTML sans marqueurs d'hydratation ni suffixe de classe propre au composant (svelte-xxxx). */
const clean = (body) =>
    body
        .replace(/<!--.*?-->/gs, '')
        .replace(/ class="svelte-[a-z0-9]+"/g, '')
        .replace(/ svelte-[a-z0-9]+/g, '')
        .replace(/>\s+</g, '><');
const draw = (component, props) => clean(render(component, { props }).body);

const text = (html) => createRawSnippet(() => ({ render: () => html }));
const control = createRawSnippet((attrs) => ({
    render: () =>
        `<input id="${attrs().id}" aria-describedby="${attrs()['aria-describedby'] ?? ''}" aria-invalid="${attrs()['aria-invalid'] ?? ''}">`,
}));

describe('Pagination', () => {
    const html = (current, last) => draw(Pagination, { current, last, onpage: () => {} });

    it('est une navigation nommée, avec la page courante signalée et l’état annoncé', () => {
        const body = html(6, 90);
        expect(body).toContain('<nav class="pagination" aria-label="Pagination">');
        expect(body.match(/aria-current="page"/g)).toHaveLength(1);
        expect(body).toMatch(/aria-current="page"[^>]*>6</);
        expect(body).toContain('aria-live="polite"');
        expect(body).toContain('Page 6 sur 90');
    });

    it('garde la première et la dernière page, avec des trous masqués aux lecteurs d’écran', () => {
        const body = html(6, 90);
        expect(body).toMatch(/>1</);
        expect(body).toMatch(/>90</);
        expect(body.match(/class="gap" aria-hidden="true"/g)).toHaveLength(2);
    });

    it('désactive Précédent à la première page et Suivant à la dernière', () => {
        const first = html(1, 5);
        expect(first).toMatch(/<button[^>]*class="step"[^>]*disabled[^>]*><i[^>]*chevron-left/);
        expect(first).not.toMatch(/class="step"[^>]*disabled[^>]*><span>Suivant/);

        const last = html(5, 5);
        expect(last).toMatch(/class="step"[^>]*disabled[^>]*><span>Suivant/);

        expect(html(3, 5)).not.toContain('disabled');
    });

    it('les textes fournis remplacent les textes communs', () => {
        const body = draw(Pagination, {
            current: 2,
            last: 4,
            onpage: () => {},
            label: 'Gares',
            status: '2/4',
        });
        expect(body).toContain('aria-label="Gares"');
        expect(body).toContain('2/4');
    });
});

describe('Notice', () => {
    const html = (props) => draw(Notice, { children: text('<span>Message</span>'), ...props });

    it('annonce poliment par défaut (role=status) et dit le ton par une icône et une classe', () => {
        const body = html({ tone: 'success' });
        expect(body).toContain('role="status"');
        expect(body).toContain('vff-notice success');
        expect(body).toContain('bi-check2-circle');
        expect(body).toContain('Message');
    });

    it('une erreur bloquante est une alerte, un message présent au chargement n’est pas annoncé', () => {
        expect(html({ tone: 'danger', live: 'assertive' })).toContain('role="alert"');
        expect(html({ live: 'off' })).not.toContain('role=');
    });

    it('cache l’icône aux lecteurs d’écran et accepte un titre et une autre icône', () => {
        const body = html({ title: 'Attention', icon: 'bi-x' });
        expect(body).toContain('aria-hidden="true"');
        expect(body).toContain('<p class="vff-notice-title">Attention</p>');
        expect(body).toContain('bi-x');
    });

    it('un ton inconnu retombe sur l’information', () => {
        expect(html({ tone: 'autre' })).toContain('bi-info-circle');
    });
});

describe('EmptyState', () => {
    it('sans niveau de titre, le titre est un paragraphe (hors du plan de la page)', () => {
        const body = draw(EmptyState, { title: 'Aucun résultat' });
        expect(body).toContain('<p class="vff-empty-title">Aucun résultat</p>');
        expect(body).not.toMatch(/<h\d/);
    });

    it('avec un niveau, le titre entre dans le plan de la page', () => {
        const body = draw(EmptyState, { title: 'Aucun résultat', headingLevel: 2 });
        expect(body).toContain('<h2 class="vff-empty-title">Aucun résultat</h2>');
    });

    it('affiche le texte, l’icône décorative et les actions seulement quand ils existent', () => {
        const none = draw(EmptyState, { title: 'Vide' });
        expect(none).not.toContain('vff-empty-text');
        expect(none).not.toContain('vff-empty-actions');
        expect(none).not.toContain('<i');

        const full = draw(EmptyState, {
            title: 'Vide',
            text: 'Essayez autre chose',
            icon: 'bi-search',
            children: text('<button>Réinitialiser</button>'),
        });
        expect(full).toContain('<p class="vff-empty-text">Essayez autre chose</p>');
        expect(full).toContain('bi-search" aria-hidden="true"');
        expect(full).toContain(
            '<div class="vff-empty-actions"><button>Réinitialiser</button></div>',
        );
    });
});

describe('Field', () => {
    const html = (props) =>
        draw(Field, { id: 'email', label: 'Adresse', children: control, ...props });

    it('relie le libellé au contrôle', () => {
        const body = html({});
        expect(body).toContain('<label class="vff-label" for="email">Adresse</label>');
        expect(body).toContain('id="email"');
    });

    it('sans aide ni erreur : aucun aria-describedby, aucun aria-invalid', () => {
        const body = html({});
        expect(body).toContain('aria-describedby=""');
        expect(body).toContain('aria-invalid=""');
        expect(body).not.toContain('vff-hint');
    });

    it('relie l’aide et l’erreur au contrôle et marque le champ invalide', () => {
        const body = html({ hint: 'Votre adresse', error: ['Trop court.', 'Sans arobase.'] });
        expect(body).toContain('aria-describedby="email-hint email-error"');
        expect(body).toContain('aria-invalid="true"');
        expect(body).toContain('<p class="vff-hint" id="email-hint">Votre adresse</p>');
        expect(body).toContain('Trop court. Sans arobase.');
    });

    it('annonce l’erreur dans une zone aria-live, présente même quand elle est vide', () => {
        expect(html({})).toContain('<p class="vff-error" id="email-error" aria-live="polite">');
    });

    it('écrit la mention « obligatoire » dans le libellé d’un champ requis (RGAA 11.10)', () => {
        expect(html({})).not.toContain('vff-required');
        const body = html({ required: true });
        expect(body).toMatch(
            /<label class="vff-label" for="email">Adresse<span class="vff-required[^"]*"> \(/,
        );
    });

    it('cache le libellé visuellement mais le garde pour les lecteurs d’écran', () => {
        expect(html({ labelHidden: true })).toContain('vff-label vff-sr-only');
    });
});
