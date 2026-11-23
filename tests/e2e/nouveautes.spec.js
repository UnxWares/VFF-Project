// Nouveautés du 23/11/2026 : redirections 301 des anciennes adresses, groupe replié « Cités, sans service attesté »,
// annuaire des gares (fermeture en intervalle), « Autour de moi », quiz « Culture du rail » et glossaire.
// Données : E2eSeeder (gare fermée entre 1951 et 1960, séries citées sans service attesté, un sujet par famille du quiz).
import { expect, preparer, test, verifier } from './fixtures.js';

const h1 = (page) => page.locator('main h1, h1');

test.describe('anciennes adresses (301)', () => {
    test('une fiche de gare fusionnée renvoie vers la fiche qui l’a reçue', async ({
        page,
        request,
    }) => {
        const reponse = await request.get('/stations/osm-4242424242-andelot', { maxRedirects: 0 });
        expect(reponse.status()).toBe(301);
        expect(new URL(reponse.headers().location).pathname).toBe('/stations/andelot');

        await page.goto('/stations/osm-4242424242-andelot', { waitUntil: 'load' });
        await expect(page).toHaveURL(/\/stations\/andelot$/);
        await expect(h1(page).first()).toContainText('Andelot');
    });

    test('une adresse de gare inconnue reste une 404', async ({ request }) => {
        const reponse = await request.get('/stations/osm-1-inconnue', { maxRedirects: 0 });
        expect(reponse.status()).toBe(404);
    });

    test('l’ancienne adresse « archeo-… » d’une ligne renvoie vers l’adresse neutre', async ({
        page,
        request,
    }) => {
        const reponse = await request.get('/lines/archeo-antenne-de-la-flotte', {
            maxRedirects: 0,
        });
        expect(reponse.status()).toBe(301);
        expect(new URL(reponse.headers().location).pathname).toBe('/lines/antenne-de-la-flotte');

        await page.goto('/lines/archeo-antenne-de-la-flotte', { waitUntil: 'load' });
        await expect(page).toHaveURL(/\/lines\/antenne-de-la-flotte$/);
        await expect(h1(page).first()).toContainText('Antenne de La Flotte');
    });

    test('le sous-chemin et les paramètres sont gardés par la redirection', async ({ request }) => {
        const reponse = await request.get('/lines/archeo-antenne-de-la-flotte?era=2030', {
            maxRedirects: 0,
        });
        expect(reponse.status()).toBe(301);
        const adresse = new URL(reponse.headers().location);
        expect(adresse.pathname).toBe('/lines/antenne-de-la-flotte');
        expect(adresse.search).toBe('?era=2030');
    });
});

test.describe('fiche de ligne : cités, sans service attesté', () => {
    test('le groupe est replié, nomme les séries et la raison', async ({ page }) => {
        await page.goto('/lines/rfn-878000', { waitUntil: 'load' });
        const groupe = page.locator('details.doubtful');
        await expect(groupe).toBeAttached({ timeout: 20_000 });
        await groupe.scrollIntoViewIfNeeded();
        await expect(groupe.locator('summary')).toHaveText('Cités, sans service attesté (2)');
        await expect(groupe).not.toHaveAttribute('open', '');
        await expect(groupe.getByRole('link', { name: 'BB 9999' })).toBeHidden();

        await groupe.locator('summary').click();
        await expect(groupe).toHaveAttribute('open', '');
        await expect(groupe).toContainText(
            'Les sources citent ces séries sur la ligne sans dire qu’elles y ont assuré le service.',
        );
        await expect(groupe.getByRole('link', { name: 'BB 9999' })).toHaveAttribute(
            'href',
            /\/materials\/bb-9999-essais$/,
        );
        await expect(groupe).toContainText('essais');
        await expect(groupe.getByRole('link', { name: 'Z 9999' })).toBeVisible();
        await expect(groupe).toContainText('commande');
    });

    test('les séries douteuses ne sont pas des tuiles de la section matériel', async ({ page }) => {
        await page.goto('/lines/rfn-878000', { waitUntil: 'load' });
        await expect(page.locator('details.doubtful')).toBeAttached({ timeout: 20_000 });
        const tuiles = page.locator('li.material-card');
        await expect(tuiles.filter({ hasText: 'BB 9101' })).toHaveCount(1);
        await expect(tuiles.filter({ hasText: 'BB 9999' })).toHaveCount(0);
        await expect(tuiles.filter({ hasText: 'Z 9999' })).toHaveCount(0);
    });
});

test.describe('annuaire des gares : fermeture en intervalle', () => {
    test('la fermeture encadrée s’écrit « entre … et … », jamais comme une année', async ({
        page,
        espion,
    }) => {
        await verifier(page, espion, {
            chemin: '/stations?q=andelot',
            titre: /Gares et haltes - VFF/,
            cle: (p) => p.getByRole('table'),
        });
        const ligne = page.getByRole('row').filter({ hasText: 'Andelot-le-Haut' });
        await expect(ligne).toContainText('entre 1951 et 1960');
        await expect(ligne).toContainText('1862');
        // La cellule de fermeture porte l'intervalle seul, pas l'année de la borne.
        await expect(ligne.getByRole('cell').last()).toHaveText('entre 1951 et 1960');
        // Une gare encore ouverte n'a pas de fermeture.
        const ouverte = page.getByRole('row').filter({ hasText: 'Ouverte aux voyageurs' });
        await expect(ouverte).not.toContainText('entre');
    });

    test('la même fermeture en anglais', async ({ page }) => {
        await preparer(page, { lang: 'en' });
        await page.goto('/stations?q=andelot', { waitUntil: 'load' });
        await expect(page.getByRole('row').filter({ hasText: 'Andelot-le-Haut' })).toContainText(
            'between 1951 and 1960',
        );
    });
});

test.describe('« Autour de moi »', () => {
    test('autour d’une commune : la fermeture en intervalle n’est jamais écrite comme une année', async ({
        page,
    }) => {
        await page.goto('/nearby?commune=39009-andelot-en-montagne', { waitUntil: 'load' });
        const gares = page.getByRole('heading', { name: /^Gares/ });
        await expect(gares).toBeVisible({ timeout: 20_000 });
        const haut = page.locator('li', { hasText: 'Andelot-le-Haut' });
        await expect(haut).toContainText('Aujourd’hui : fermée');
        await expect(haut).toContainText('Ouverte en 1862.');
        await expect(haut).not.toContainText(/fermée en/i);
        await expect(haut).not.toContainText('1960');
        const gare = page
            .locator('li', { hasText: 'Aujourd’hui : ouverte aux voyageurs' })
            .filter({ hasText: /^Andelot\b(?!-le)/ });
        await expect(gare.first()).toContainText('Ouverte en 1862.');
        await expect(page.getByRole('heading', { name: /^Lignes/ })).toBeVisible();
    });

    test('sans position ni commune, la page invite à en choisir une', async ({ page }) => {
        await page.goto('/nearby', { waitUntil: 'load' });
        await expect(h1(page).first()).toBeVisible();
        await expect(page.getByRole('heading', { name: /^Gares/ })).toHaveCount(0);
    });
});

/**
 * Joue une série du quiz « Culture du rail » (graine donnée) en choisissant toujours la première proposition, et
 * rend, par invite de question, le texte révélé après la validation (la question, puis le fait et ses sources).
 */
async function jouerSerie(page, graine, langue = 'fr') {
    const valider =
        langue === 'fr' ? /Valider/ : langue === 'en' ? /Check my answer/ : /Antwort prüfen/;
    await page.goto(`/quiz?game=facts&level=easy&seed=${graine}`, { waitUntil: 'load' });
    const vues = [];
    for (let i = 0; i < 12; i++) {
        const choix = page.getByRole('radio');
        if (!(await choix.count())) break;
        const avant = await page.locator('main').innerText();
        await choix.first().check();
        await page.getByRole('button', { name: valider }).click();
        await expect(
            page.getByText(/bonne réponse|right answer|richtige Antwort/).first(),
        ).toBeVisible();
        vues.push({ avant, apres: await page.locator('main').innerText() });
        const suivante = page.getByRole('button', {
            name: /Question suivante|Voir le résultat|Next question|See|Nächste Frage|Ergebnis/,
        });
        if (!(await suivante.count())) break;
        await suivante.first().click();
    }
    return vues;
}

test.describe('quiz « Culture du rail » : familles tirées des données du jour', () => {
    test('chaque famille est posée sans réponse avant le choix, puis révélée avec sa source', async ({
        page,
    }) => {
        test.slow();
        const trouvees = new Map();
        for (const graine of ['e2e00001', 'e2e00002', 'e2e00003', 'e2e00004']) {
            for (const v of await jouerSerie(page, graine)) {
                const famille = [
                    ['cabine', /Quel système de signalisation en cabine/],
                    ['accident', /En quelle année cet accident a-t-il eu lieu/],
                    ['gare', /En quelle année cette gare a-t-elle ouvert/],
                    ['matériel', /Laquelle de ces séries de matériel est citée/],
                ].find(([, motif]) => motif.test(v.avant))?.[0];
                if (famille && !trouvees.has(famille)) trouvees.set(famille, v);
            }
            if (trouvees.size === 4) break;
        }
        expect([...trouvees.keys()].sort()).toEqual(['accident', 'cabine', 'gare', 'matériel']);

        const cabine = trouvees.get('cabine');
        expect(cabine.avant).not.toContain('23 mai 1993');
        expect(cabine.apres).toContain(
            'La ligne « LGV de démonstration » est équipée du système TVM 430',
        );
        expect(cabine.apres).toContain('23 mai 1993');
        expect(cabine.apres).toContain('Bilan LOTI de démonstration');

        const accident = trouvees.get('accident');
        // La question donne la nature et la commune, jamais la date ni le bilan.
        expect(accident.avant).toContain('Collision à Andelot-en-Montagne');
        expect(accident.avant).not.toContain('16 septembre');
        expect(accident.apres).toContain('Collision à Andelot-en-Montagne, le 16 septembre 1890.');
        // Jamais de bilan humain ni de nom dans la question de l'accident.
        expect(accident.apres).not.toMatch(/\b(morts?|blessés?|victimes?)\b/i);
        expect(accident.apres).toContain('Wikipédia');

        const gare = trouvees.get('gare');
        expect(gare.apres).toContain('La gare « Andelot » a ouvert le 4 mars 1862.');

        const materiel = trouvees.get('matériel');
        expect(materiel.avant).not.toContain('citée parmi le matériel');
        expect(materiel.apres).toContain('La série BB 9101 est citée parmi le matériel de');
        expect(materiel.apres).toContain(
            'Aucune source ne rattache à cette ligne les autres séries proposées',
        );
        // Les séries douteuses du jeu ne sont jamais proposées comme mauvaises réponses.
        expect(materiel.avant).not.toMatch(/BB 9999|Z 9999/);
    });

    test('les dates et les noms de système suivent la langue de la page', async ({ page }) => {
        test.slow();
        await preparer(page, { lang: 'en' });
        let revele = null;
        for (const graine of ['e2e00001', 'e2e00002', 'e2e00003', 'e2e00004']) {
            const vues = await jouerSerie(page, graine, 'en');
            revele = vues.find((v) => /cab signalling system/.test(v.avant))?.apres ?? null;
            if (revele) break;
        }
        expect(revele).not.toBeNull();
        expect(revele).toContain('23 May 1993');
        expect(revele).toContain('TVM 430');
    });

    test('la page du jeu s’ouvre sans erreur', async ({ page, espion }) => {
        await verifier(page, espion, {
            chemin: '/quiz?game=facts&level=easy&seed=e2e00001',
            titre: /VFF/,
            cle: h1,
        });
        await expect(page.getByRole('radio').first()).toBeVisible();
    });
});

test.describe('glossaire : nouveaux termes et renvois', () => {
    test('les notions du 23/11 sont là, avec leurs renvois', async ({ page, espion }) => {
        await verifier(page, espion, {
            chemin: '/glossary',
            titre: /Glossaire du vocabulaire ferroviaire - VFF/,
            cle: h1,
        });
        for (const id of [
            'tvm-300',
            'tvm-430',
            'ertms-niveau-1',
            'ertms-niveau-2',
            'bmu',
            'prci',
            'voie-partagee',
            'declivite',
            'bifurcation-lgv',
            'gare-de-bifurcation',
            'embarcadere',
            'suspension-de-service',
            'fermeture-fret',
            'declassement',
            'element-automoteur',
            'rame-a-turbine-a-gaz',
            'famille-et-version',
            'materiel-preserve',
        ]) {
            await expect(page.locator(`dfn#${id}`), `terme ${id}`).toBeAttached();
        }
        const terme = page.locator('.term', { has: page.locator('dfn#tvm-300') });
        await expect(terme).toContainText('Voir aussi');
        const renvoi = terme.getByRole('link', { name: 'TVM 430' });
        await expect(renvoi).toHaveAttribute('href', '#tvm-430');
        await renvoi.click();
        await expect(page).toHaveURL(/#tvm-430$/);
        await expect(page.locator('dfn#tvm-430')).toBeInViewport();
    });

    test('chaque renvoi mène à un terme qui existe', async ({ page }) => {
        await page.goto('/glossary', { waitUntil: 'load' });
        const ids = new Set(await page.locator('dfn').evaluateAll((l) => l.map((d) => d.id)));
        const renvois = await page
            .locator('.term .extra a[href^="#"]')
            .evaluateAll((l) => l.map((a) => a.getAttribute('href').slice(1)));
        expect(renvois.length).toBeGreaterThan(100);
        const manquants = renvois.filter((id) => !ids.has(id));
        expect(manquants).toEqual([]);
    });

    test('la recherche filtre les termes', async ({ page }) => {
        await page.goto('/glossary', { waitUntil: 'load' });
        const total = await page.locator('dfn').count();
        await page.locator('#glossary-q').fill('TVM');
        await expect(page.locator('dfn#tvm-300')).toBeVisible();
        await expect(page.locator('dfn#tvm-430')).toBeVisible();
        await expect(page.locator('dfn#embarcadere')).toHaveCount(0);
        expect(await page.locator('dfn').count()).toBeLessThan(total);
        await page.locator('#glossary-q').fill('zzzzzz-introuvable');
        await expect(page.locator('dfn')).toHaveCount(0);
    });

    for (const [lang, nom] of [
        ['en', 'Gradient'],
        ['de', 'Steigung'],
    ]) {
        test(`les termes existent en ${lang}`, async ({ page }) => {
            await preparer(page, { lang });
            await page.goto('/glossary', { waitUntil: 'load' });
            await expect(page.locator('dfn#rampe')).toContainText(nom);
            await expect(page.locator('dfn#tvm-300')).toBeAttached();
            await expect(page.locator('dfn#embarcadere')).toBeAttached();
        });
    }
});
