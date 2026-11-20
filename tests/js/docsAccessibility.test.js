import { describe, expect, it } from 'vitest';
import {
    blockParagraphsToDivs,
    LOGO_ALT,
    patchPage,
    patchScript,
    SEARCH_LABEL,
    SECTION_LINK_LABEL,
    SEE_ALSO_LABEL,
    templateEnd,
    TITLE_SUFFIX,
} from '../../resources/docs/graphql/a11y.mjs';
import {
    accessibilityPlugin,
    FILTER_INPUT_ID,
    FILTER_LABEL,
    transformRender,
    withFilterLabel,
    withoutDeepLink,
    withoutMain,
} from '../../resources/js/docs/swaggerAccessibility.js';

// Corrections RGAA des documentations de l'API : post-traitement de Magidoc (/docs/graphql) et greffon
// de Swagger UI (/docs/swagger). Les extraits reprennent le code réellement produit par le gabarit
// Magidoc carbon-multi-page (noms minifiés compris).

describe('Magidoc : modules JS compilés', () => {
    it('donne au logo un texte de remplacement utile', () => {
        const { code, counts } = patchScript('q(r,()=>({src:Nt,alt:`Magidoc`,...n}))');
        expect(code).toBe(`q(r,()=>({src:Nt,alt:\`${LOGO_ALT}\`,...n}))`);
        expect(counts.logo).toBe(1);
    });

    it('nomme le bouton « Voir aussi » et expose son état ouvert ou fermé', () => {
        const source =
            'q(I,()=>({type:`button`,...c,class:l(f),[de]:{"bx--header__action":!0,"bx--header__action--active":m(),"bx--header__action--text":y()}}))';
        const { code, counts } = patchScript(source);
        expect(counts['header-action']).toBe(1);
        expect(code).toContain(`"aria-label":\`${SEE_ALSO_LABEL}\``);
        expect(code).toContain('lang:`fr`');
        expect(code).toContain('"aria-expanded":String(!!m())');
        // Les attributs transmis par l'appelant passent après : un nom explicite reste prioritaire.
        expect(code.indexOf('"aria-label"')).toBeLessThan(code.indexOf('...c'));
    });

    it("étiquette le champ de recherche de l'en-tête", () => {
        const { code } = patchScript(
            'Ot(n,{get placeholder(){return l(e)},autocomplete:`off`,size:`sm`})',
        );
        expect(code).toContain(`labelText:\`${SEARCH_LABEL}\``);
    });

    it('neutralise le second intertitre des liens « # » et nomme le lien', () => {
        const source =
            'var Nt=n(`<a class="hashtag svelte-xid2sy">#</a>`);a(S(n),()=>`h${i()}`,!1,(t,n)=>{B(t,0,`header-base header-hashtag svelte-xid2sy`);var i=Nt();h(n,i)})';
        const { code, counts } = patchScript(source);
        expect(counts['hashtag-heading']).toBe(1);
        expect(counts['hashtag-link']).toBe(1);
        expect(code).toContain(
            'B(t,0,`header-base header-hashtag svelte-xid2sy`);t.setAttribute(`role`,`none`);',
        );
        expect(code).toContain(`aria-label="${SECTION_LINK_LABEL}" lang="en">#</a>`);
    });

    it("ajoute le nom de la référence à chaque titre d'onglet, gabarits imbriqués compris", () => {
        const source =
            'd.title=`Query - ${(n(m()),o(()=>m().field.name))??``}`;d.title=`\n    ${o(()=>m().page?.section?`${m().page.section} - `:``)??``}${o(()=>m().page.title)??``}\n  `';
        const { code, counts } = patchScript(source);
        expect(counts.title).toBe(2);
        expect(code).toContain(`field.name))??\`\`}${TITLE_SUFFIX}\``);
        expect(code).toContain(`page.title)??\`\`}\n  ${TITLE_SUFFIX}\``);
        expect(patchScript(code).counts.title).toBe(0);
    });

    it('change en div les paragraphes qui reçoivent du contenu, pas ceux de texte', () => {
        const source =
            'ke=t(`<p><!></p>`),Ae=t(`<p class="arg-name-section svelte-1rqh83z"><span> </span> <!></p> <!>`),x=n(`<p class="md-paragraph svelte-1q0nzsz"><!></p>`),y=n(`<a class="title"><p> </p></a>`)';
        const { code, counts } = patchScript(source);
        expect(counts.paragraphs).toBe(2);
        expect(code).toContain('ke=t(`<div><!></div>`)');
        expect(code).toContain(
            '`<div class="arg-name-section svelte-1rqh83z"><span> </span> <!></div> <!>`',
        );
        expect(code).toContain('`<p class="md-paragraph svelte-1q0nzsz"><!></p>`');
        expect(code).toContain('`<a class="title"><p> </p></a>`');
    });

    it('trouve la fin des gabarits littéraux malgré les échappements et les imbrications', () => {
        const code = '`a\\`b${`c${"}"}`}d`;x';
        expect(templateEnd(code, 1)).toBe(code.indexOf(';') - 1);
        expect(templateEnd('`sans fin', 1)).toBe(-1);
    });
});

describe('Magidoc : pages pré-rendues', () => {
    const page = [
        '<html lang="fr"><head></head><body>',
        '<div style="display: contents"><header>',
        '<img src="/docs/graphql/vff.png" alt="Magidoc"/>',
        '<button type="button" class="bx--header__action"><svg></svg></button></header>',
        '<main id="main-content"><div class="parent svelte-xid2sy">',
        '<h2 id="arguments" class="header header-base svelte-xid2sy">Arguments</h2>',
        '<h2 class="header-base header-hashtag svelte-xid2sy"><a href="#arguments" class="hashtag svelte-xid2sy">#</a><!----></h2></div>',
        '<p><!--[0--><p class="md-paragraph svelte-1q0nzsz">Slug de la ligne.</p><!--]--></p>',
        '<p class="arg-name-section svelte-1rqh83z"><span>slug</span> <div class="bx--tag">String</div></p>',
        '<p class="md-paragraph svelte-1q0nzsz">Texte.</p>',
        '</main></div></body></html>',
    ].join('');

    it('applique les mêmes corrections que dans les modules JS, plus le lien d’évitement', () => {
        const { html, counts } = patchPage(page);
        expect(html).toContain(`alt="${LOGO_ALT}"`);
        expect(html).toContain(
            `<button type="button" lang="fr" aria-label="${SEE_ALSO_LABEL}" aria-expanded="false" class="bx--header__action">`,
        );
        expect(html).toContain('<h2 class="header-base header-hashtag svelte-xid2sy" role="none">');
        expect(html).toContain(
            `<a href="#arguments" class="hashtag svelte-xid2sy" aria-label="${SECTION_LINK_LABEL}" lang="en">#</a>`,
        );
        expect(html.indexOf('class="vff-skip-link" href="#main-content"')).toBe(
            html.indexOf('<body>') + '<body><a '.length,
        );
        expect(counts.paragraphs).toBe(2);
        expect(html).toContain(
            '<div><!--[0--><p class="md-paragraph svelte-1q0nzsz">Slug de la ligne.</p><!--]--></div>',
        );
        expect(html).toContain('<div class="arg-name-section svelte-1rqh83z">');
        expect(html).toContain('<p class="md-paragraph svelte-1q0nzsz">Texte.</p>');
    });

    it('ne pose le lien d’évitement qu’une fois', () => {
        const once = patchPage(page).html;
        expect(patchPage(once).html.match(/vff-skip-link/g)).toHaveLength(1);
    });

    it('referme chaque paragraphe changé au bon endroit', () => {
        const { markup } = blockParagraphsToDivs('<p>a<p>b</p><ul><li>c</li></ul></p><p>d</p>');
        expect(markup).toBe('<div>a<p>b</p><ul><li>c</li></ul></div><p>d</p>');
    });
});

// React minimal : juste ce que le greffon consomme (éléments immuables, clonage, fragments).
const ELEMENT = Symbol('element');
const FakeReact = {
    Fragment: Symbol('fragment'),
    isValidElement: (node) => node?.$$typeof === ELEMENT,
    createElement(type, props, ...children) {
        const { key = null, ref = null, ...rest } = props ?? {};
        if (children.length) rest.children = children.length === 1 ? children[0] : children;
        return Object.freeze({ $$typeof: ELEMENT, type, key, ref, props: Object.freeze(rest) });
    },
    cloneElement(element, props, ...children) {
        return FakeReact.createElement(
            element.type,
            { key: element.key, ref: element.ref, ...element.props, ...props },
            ...(children.length ? children : [element.props.children]),
        );
    },
};
const h = FakeReact.createElement;

describe('Swagger UI : greffon d’accessibilité', () => {
    it('remplace la zone principale de Swagger UI par un div (une seule zone principale)', () => {
        const row = () => null;
        const tree = h(
            'div',
            { className: 'swagger-ui' },
            h('main', { id: 'operations', tabIndex: '-1' }, h(row, null, 'ops')),
        );
        const out = withoutMain(tree, FakeReact);
        const inner = out.props.children;
        expect(inner.type).toBe('div');
        expect(inner.props.id).toBe('operations');
        expect(inner.props.children.type).toBe(row);
    });

    it('rend inchangé un arbre sans zone principale', () => {
        const tree = h('div', null, h('span', null, 'x'));
        expect(withoutMain(tree, FakeReact)).toBe(tree);
    });

    it('donne une étiquette visible et reliée au champ de filtre', () => {
        const tree = h(
            'div',
            { className: 'filter-container' },
            h('input', { className: 'operation-filter-input', placeholder: 'Filter by tag' }),
        );
        const fragment = withFilterLabel(tree, FakeReact).props.children;
        const [label, input] = fragment.props.children;
        expect(label.type).toBe('label');
        expect(label.props.htmlFor).toBe(FILTER_INPUT_ID);
        expect(label.props.children).toBe(FILTER_LABEL);
        expect(input.props.id).toBe(FILTER_INPUT_ID);
    });

    it('remplace le lien profond du chemin par son texte', () => {
        const DeepLink = () => null;
        const tree = h(
            'span',
            { className: 'opblock-summary-path' },
            h(DeepLink, { enabled: true, isShown: true, path: 'Lignes/get', text: '/lines' }),
        );
        const out = withoutDeepLink(tree, FakeReact);
        expect(out.props.children.type).toBe('span');
        expect(out.props.children.props.children).toBe('/lines');
    });

    it('traverse le garde-fou d’erreurs de Swagger UI et garde la même sous-classe d’un rendu à l’autre', () => {
        class Layout {
            render() {
                return h('main', { id: 'operations' });
            }
        }
        class ErrorBoundary {
            render() {
                return null;
            }
        }
        class WithErrorBoundary {
            render() {
                return h(ErrorBoundary, null, h(Layout, { a: 1 }));
            }
        }
        const Wrapped = transformRender(withoutMain)(WithErrorBoundary, { React: FakeReact });
        const first = new Wrapped().render();
        const second = new Wrapped().render();
        expect(first.type).toBe(ErrorBoundary);
        const Inner = first.props.children.type;
        expect(second.props.children.type).toBe(Inner);
        expect(first.props.children.props.a).toBe(1);
        expect(new Inner().render().type).toBe('div');
    });

    it('transforme directement le rendu d’un composant sans garde-fou', () => {
        class Layout {
            render() {
                return h('main', null);
            }
        }
        const Wrapped = transformRender(withoutMain)(Layout, { React: FakeReact });
        expect(new Wrapped().render().type).toBe('div');
    });

    it('enveloppe les trois composants corrigés', () => {
        expect(Object.keys(accessibilityPlugin().wrapComponents).sort()).toEqual([
            'BaseLayout',
            'FilterContainer',
            'OperationSummaryPath',
        ]);
    });
});
