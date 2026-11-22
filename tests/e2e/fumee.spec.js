// Suite de fumée : chaque page répond, a son titre, son élément clé, une console propre,
// aucun appel réseau en échec et aucune violation axe-core grave. Deux viewports (projets
// Playwright), français puis anglais, mode sombre sur deux pages.
import { expect, preparer, test, verifier } from './fixtures.js';

const LIGNE = '/lines/rfn-878000'; // Andelot-en-Montagne - La Cluse
const GARE = '/stations/andelot';
const ACCIDENT = '/accidents/1890-09-16-andelot-en-montagne';

const h1 = (page) => page.locator('main h1, h1');

// Une entrée par page : titre attendu par langue, élément clé.
const PAGES = [
    { nom: 'accueil', chemin: '/', titre: { fr: /VFF/, en: /VFF/ }, cle: h1 },
    { nom: 'liste des lignes', chemin: '/lines', titre: { fr: /VFF/, en: /VFF/ }, cle: h1 },
    {
        nom: 'fiche ligne Andelot - La Cluse',
        chemin: LIGNE,
        titre: {
            fr: /Andelot-en-Montagne à La Cluse - VFF/,
            en: /Andelot-en-Montagne .*La Cluse.* - VFF/,
        },
        cle: h1,
    },
    {
        nom: 'liste des gares',
        chemin: '/stations',
        titre: { fr: /Gares et haltes - VFF/, en: /VFF/ },
        cle: h1,
    },
    {
        nom: 'fiche gare',
        chemin: GARE,
        titre: { fr: /Andelot - VFF/, en: /Andelot - VFF/ },
        cle: h1,
    },
    {
        nom: 'liste des accidents',
        chemin: '/accidents',
        titre: { fr: /Accidents ferroviaires en France - VFF/, en: /VFF/ },
        cle: h1,
    },
    {
        nom: 'fiche accident',
        chemin: ACCIDENT,
        titre: { fr: /Collision à Andelot-en-Montagne - VFF/, en: /Andelot-en-Montagne - VFF/ },
        cle: h1,
    },
    {
        nom: 'page API',
        chemin: '/api',
        titre: { fr: /API - VFF/, en: /API access - VFF/ },
        cle: h1,
    },
    {
        nom: 'connexion',
        chemin: '/login',
        titre: { fr: /Connexion - VFF/, en: /VFF/ },
        cle: (p) => p.locator('#email'),
    },
    {
        nom: 'page introuvable',
        chemin: '/cette-page-n-existe-pas',
        statut: 404,
        titre: { fr: /Page introuvable - VFF/, en: /VFF/ },
        cle: h1,
    },
];

for (const lang of ['fr', 'en']) {
    test.describe(`pages en ${lang}`, () => {
        for (const p of PAGES) {
            test(p.nom, async ({ page, espion }) => {
                await preparer(page, { lang });
                await verifier(page, espion, { ...p, titre: p.titre[lang] });
            });
        }
    });
}

test.describe('documentation REST', () => {
    test("/docs/api s'ouvre (Scalar)", async ({ page, espion }) => {
        await verifier(page, espion, {
            chemin: '/docs/api',
            titre: /API REST de VFF/,
            cle: (p) => p.getByRole('main'),
        });
        await expect(page.getByRole('heading', { level: 1, name: /API publique/ })).toBeVisible();
    });
});

test.describe('carte /maps', () => {
    test('la carte se crée', async ({ page, espion }) => {
        await verifier(page, espion, {
            chemin: '/maps',
            titre: /Carte du rail français - VFF/,
            cle: (p) => p.locator('.maplibregl-canvas'),
        });
        const taille = await page.locator('.maplibregl-canvas').boundingBox();
        expect(taille.width).toBeGreaterThan(200);
        expect(taille.height).toBeGreaterThan(200);
        await expect(page.getByRole('button', { name: 'Zoom avant' })).toBeVisible();
    });

    test("le sélecteur d'ère change l'ère", async ({ page }) => {
        await page.goto('/maps', { waitUntil: 'load' });
        const frise = page.getByRole('slider', { name: 'Décennie affichée' });
        await expect(frise).toBeVisible();
        const avant = await frise.getAttribute('aria-valuenow');
        await frise.focus();
        await page.keyboard.press('ArrowLeft');
        await expect(frise).not.toHaveAttribute('aria-valuenow', avant);
        await expect(page).toHaveURL(/[?&]era=\d{4}/);
        // Les boutons de période changent aussi l'ère affichée.
        await page.getByRole('button', { name: '1830-1890' }).click();
        await expect(page.getByRole('button', { name: '1830-1890' })).toHaveAttribute(
            'aria-pressed',
            'true',
        );
    });

    test('la légende répond', async ({ page }) => {
        await page.goto('/maps', { waitUntil: 'load' });
        // Repliée par défaut sur mobile : on la déplie.
        const bascule = page.locator('.legend-toggle');
        await expect(bascule).toBeVisible();
        if ((await bascule.getAttribute('aria-expanded')) === 'false') await bascule.click();
        const groupe = page.locator('fieldset.legend-modes');
        await expect(groupe).toBeVisible();
        const puce = (nom) => groupe.locator('label.mode-chip', { hasText: nom });
        await puce('Électrification').click();
        await expect(puce('Électrification').locator('input')).toBeChecked();
        await puce('Écartement').click();
        await expect(puce('Écartement').locator('input')).toBeChecked();
        await expect(puce('Électrification').locator('input')).not.toBeChecked();
    });
});

test.describe('recherche', () => {
    test("Ctrl+K ouvre la recherche et trouve la ligne, la gare et l'accident", async ({
        page,
        espion,
    }) => {
        await page.goto('/lines', { waitUntil: 'load' });
        const champ = page.getByRole('dialog').getByRole('combobox');
        // Le raccourci n'existe qu'une fois la page hydratée : on réessaie jusqu'à l'ouverture.
        await expect(async () => {
            await page.keyboard.press('Control+k');
            await expect(champ).toBeVisible({ timeout: 2000 });
        }).toPass({ timeout: 20_000 });
        await champ.fill('andelot');
        const resultats = page.getByRole('dialog').getByRole('option');
        await expect(resultats.filter({ hasText: 'La Cluse' }).first()).toBeVisible();
        await expect(resultats.filter({ hasText: 'Collision' }).first()).toBeVisible();
        await resultats.filter({ hasText: 'La Cluse' }).first().click();
        await expect(page).toHaveURL(/\/lines\/rfn-878000/);
        expect(espion.console).toEqual([]);
    });
});

test.describe('mode sombre', () => {
    for (const { nom, chemin, titre } of [
        { nom: 'accueil', chemin: '/', titre: /VFF/ },
        { nom: 'fiche ligne', chemin: LIGNE, titre: /La Cluse - VFF/ },
    ]) {
        test(nom, async ({ page, espion }) => {
            await preparer(page, { theme: 'dark' });
            await verifier(page, espion, { chemin, titre, cle: h1 });
            await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
        });
    }
});
