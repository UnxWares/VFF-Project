import { describe, expect, it } from 'vitest';
import { legalTypography } from '../../resources/js/lib/legalTypography.js';

const NB = ' ';

describe('legalTypography', () => {
    it('met une espace insécable avant les deux-points et les points-virgules en français', () => {
        expect(legalTypography('<p>Licence : ODbL ; voir plus</p>')).toBe(
            `<p>Licence${NB}: ODbL${NB}; voir plus</p>`,
        );
    });

    it('ne touche pas aux attributs ni aux balises', () => {
        const html = '<a href="https://x.org/a : b" title="a : b">lien : texte</a>';
        expect(legalTypography(html)).toBe(
            `<a href="https://x.org/a : b" title="a : b">lien${NB}: texte</a>`,
        );
    });

    it('laisse les marqueurs de variable et les entités', () => {
        expect(legalTypography('<p>mise à jour : :date &amp; Connexions</p>')).toBe(
            `<p>mise à jour${NB}: :date &amp; Connexions</p>`,
        );
    });

    it('lie les chiffres des nombres et les guillemets', () => {
        expect(legalTypography('<p>capital de 1 000 € « Voies »</p>')).toBe(
            `<p>capital de 1${NB}000${NB}€ «${NB}Voies${NB}»</p>`,
        );
        expect(legalTypography('<p>SIRET 945 207 991 00015</p>')).toBe(
            `<p>SIRET 945${NB}207${NB}991 00015</p>`,
        );
    });

    it('ne change pas les espaces en anglais mais garde les licences ensemble', () => {
        expect(legalTypography('<p>Licence : CC BY-SA 4.0</p>', 'en')).toBe(
            `<p>Licence : <span class="nowrap">CC${NB}BY-SA${NB}4.0</span></p>`,
        );
    });

    it('lie CC0 et les variantes sans version', () => {
        expect(legalTypography('<li>Wikidata : CC0 ;</li>')).toBe(
            `<li>Wikidata${NB}: <span class="nowrap">CC0</span>${NB};</li>`,
        );
        expect(legalTypography('<p>CC BY-SA</p>', 'de')).toBe(
            `<p><span class="nowrap">CC${NB}BY-SA</span></p>`,
        );
    });
});
