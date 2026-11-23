// Formulaires de contribution (événement daté, image) : validation avant l'envoi, messages d'erreur, envoi réussi,
// refus sans compte. Le membre est créé une fois par processus (fixtures.js, testMembre) dans la base de la suite.
import { deflateSync } from 'node:zlib';
import { expect, test, testMembre } from './fixtures.js';

const LIGNE = 'rfn-878000';

/** CRC-32 d'un tampon (les blocs d'un PNG en portent un). */
function crc32(tampon) {
    let crc = ~0;
    for (const octet of tampon) {
        crc ^= octet;
        for (let k = 0; k < 8; k++) crc = (crc >>> 1) ^ (0xedb88320 & -(crc & 1));
    }
    return ~crc >>> 0;
}

/** PNG gris uni de `cote` pixels de côté (niveau de gris au choix : le serveur refuse deux fichiers identiques) (aucune dépendance : blocs IHDR, IDAT, IEND écrits à la main). */
function png(cote, gris = 0x80) {
    const bloc = (type, donnees) => {
        const corps = Buffer.concat([Buffer.from(type, 'ascii'), donnees]);
        const longueur = Buffer.alloc(4);
        longueur.writeUInt32BE(donnees.length);
        const somme = Buffer.alloc(4);
        somme.writeUInt32BE(crc32(corps));
        return Buffer.concat([longueur, corps, somme]);
    };
    const entete = Buffer.alloc(13);
    entete.writeUInt32BE(cote, 0);
    entete.writeUInt32BE(cote, 4);
    entete[8] = 8; // 8 bits par canal
    entete[9] = 0; // niveaux de gris
    const lignes = Buffer.alloc((cote + 1) * cote, gris); // octet de filtre 0 puis pixels gris
    for (let y = 0; y < cote; y++) lignes[y * (cote + 1)] = 0;
    return Buffer.concat([
        Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
        bloc('IHDR', entete),
        bloc('IDAT', deflateSync(lignes)),
        bloc('IEND', Buffer.alloc(0)),
    ]);
}

/** Texte du récapitulatif d'erreurs en tête du formulaire (il prend le focus après un envoi refusé). */
const recapitulatif = (page) => page.locator('#proposal-errors');

test.describe('sans compte', () => {
    for (const chemin of ['/contribute/event', '/contribute/media']) {
        test(`${chemin} renvoie à la connexion`, async ({ page }) => {
            await page.goto(`${chemin}?line=${LIGNE}`, { waitUntil: 'load' });
            await expect(page).toHaveURL(/\/login$/);
            await expect(page.locator('#email')).toBeVisible();
        });
    }

    for (const chemin of ['/contribute/event', '/contribute/media']) {
        test(`un envoi ${chemin} sans session est refusé`, async ({ request }) => {
            const reponse = await request.post(chemin, {
                form: { line_slug: LIGNE },
                maxRedirects: 0,
            });
            // Jamais enregistré : redirection vers la connexion, ou jeton de session absent (419).
            expect([302, 401, 419]).toContain(reponse.status());
            if (reponse.status() === 302) expect(reponse.headers().location).toMatch(/\/login$/);
        });
    }
});

testMembre.describe('proposer un événement daté', () => {
    testMembre(
        'un formulaire vide est refusé avec les trois erreurs attendues',
        async ({ page }) => {
            await page.goto(`/contribute/event?line=${LIGNE}`, { waitUntil: 'load' });
            await expect(
                page.getByRole('heading', { level: 1, name: 'Proposer un événement daté' }),
            ).toBeVisible();
            await expect(page.getByText('Ligne choisie')).toBeVisible();
            const requetes = [];
            page.on('request', (r) => r.method() === 'POST' && requetes.push(r.url()));

            await page.getByRole('button', { name: 'Envoyer l’événement' }).click();

            const recap = recapitulatif(page);
            await expect(recap).toContainText('3 champs à corriger avant l’envoi');
            await expect(recap).toContainText('Date invalide pour la précision choisie');
            await expect(recap).toContainText(
                'Indiquez le titre ou la référence de la source, en trois caractères au moins.',
            );
            await expect(recap).toContainText(
                'Indiquez l’adresse de la source, en http:// ou https://.',
            );
            await expect(recap).toBeFocused();
            // Contrôlé dans le navigateur : rien n'est parti vers le serveur.
            expect(requetes).toEqual([]);
            await expect(page).toHaveURL(/\/contribute\/event/);
            // Chaque erreur du récapitulatif mène à son champ.
            await expect(recap.getByRole('link', { name: /adresse de la source/ })).toHaveAttribute(
                'href',
                '#event-source-url',
            );
        },
    );

    testMembre(
        'le type « Autre » demande une phrase, une adresse mal écrite est refusée',
        async ({ page }) => {
            await page.goto(`/contribute/event?line=${LIGNE}`, { waitUntil: 'load' });
            await page.locator('#event-type').selectOption('other');
            await page.getByRole('radio', { name: 'Année', exact: true }).check();
            await page.locator('#event-date-year').fill('1890');
            await page.locator('#event-source-title').fill('Journal officiel du 16 juin 1890');
            await page.locator('#event-source-url').fill('pas-une-adresse');

            await page.getByRole('button', { name: 'Envoyer l’événement' }).click();

            const recap = recapitulatif(page);
            await expect(recap).toContainText('champs à corriger avant l’envoi');
            // Deux erreurs : la phrase du type « Autre » et l'adresse.
            await expect(recap.getByRole('link')).toHaveCount(2);
            await expect(recap).toContainText('adresse');
            await expect(page).toHaveURL(/\/contribute\/event/);
            // Corrigé : les erreurs disparaissent et l'envoi part.
            await page.locator('#event-description').fill('Ouverture au service des marchandises.');
            await page
                .locator('#event-source-url')
                .fill('https://example.org/journal-officiel-1890');
            await page.getByRole('button', { name: 'Envoyer l’événement' }).click();
            await page.waitForURL(/\/contribute\/thanks\/\d+$/);
            await expect(
                page.getByRole('heading', { level: 1, name: 'Proposition envoyée' }),
            ).toBeVisible();
        },
    );

    testMembre('une proposition complète est envoyée et récapitulée', async ({ page }) => {
        await page.goto(`/contribute/event?line=${LIGNE}`, { waitUntil: 'load' });
        await page.locator('#event-type').selectOption('opening');
        await page.getByRole('radio', { name: 'Année', exact: true }).check();
        await page.locator('#event-date-year').fill('1862');
        await page.locator('#event-source-title').fill('Livret Chaix de 1862');
        await page.locator('#event-source-url').fill('https://example.org/chaix-1862');

        await page.getByRole('button', { name: 'Envoyer l’événement' }).click();

        await page.waitForURL(/\/contribute\/thanks\/\d+$/);
        await expect(
            page.getByRole('heading', { level: 1, name: 'Proposition envoyée' }),
        ).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Récapitulatif' })).toBeVisible();
        await expect(page.locator('main')).toContainText('Andelot-en-Montagne à La Cluse');
    });
});

testMembre.describe('proposer une image', () => {
    testMembre(
        'un formulaire vide est refusé avec les cinq erreurs attendues',
        async ({ page }) => {
            await page.goto(`/contribute/media?line=${LIGNE}`, { waitUntil: 'load' });
            await expect(
                page.getByRole('heading', { level: 1, name: 'Proposer une image' }),
            ).toBeVisible();

            await page.getByRole('button', { name: 'Envoyer l’image' }).click();

            const recap = recapitulatif(page);
            await expect(recap).toContainText('5 champs à corriger avant l’envoi');
            for (const message of [
                'Choisissez un fichier.',
                'Indiquez un titre de trois caractères au moins.',
                'Indiquez l’auteur, en deux caractères au moins.',
                'Choisissez une licence dans la liste.',
                'Confirmez que vous avez le droit de partager ce fichier.',
            ]) {
                await expect(recap).toContainText(message);
            }
            await expect(recap).toBeFocused();
            await expect(page).toHaveURL(/\/contribute\/media/);
        },
    );

    testMembre(
        'un fichier qui n’est pas une image, ou trop petit, est refusé',
        async ({ page }) => {
            await page.goto(`/contribute/media?line=${LIGNE}`, { waitUntil: 'load' });
            await page.locator('#media-source-upload').check();

            await page.locator('#media-file').setInputFiles({
                name: 'notes.txt',
                mimeType: 'text/plain',
                buffer: Buffer.from('pas une image'),
            });
            await page.getByRole('button', { name: 'Envoyer l’image' }).click();
            await expect(recapitulatif(page)).toContainText('JPEG, PNG ou WebP');

            await page
                .locator('#media-file')
                .setInputFiles({ name: 'minuscule.png', mimeType: 'image/png', buffer: png(40) });
            await expect(page.locator('main')).toContainText(
                'Chaque côté de l’image doit mesurer de 400 à 12',
            );
        },
    );

    testMembre('une image complète est envoyée', async ({ page }) => {
        await page.goto(`/contribute/media?line=${LIGNE}`, { waitUntil: 'load' });
        await page.locator('#media-source-upload').check();
        await page.locator('#media-file').setInputFiles({
            name: 'gare.png',
            mimeType: 'image/png',
            buffer: png(420, 1 + Math.floor(Math.random() * 250)),
        });
        await page.locator('#media-title').fill('Gare d’Andelot, côté voies');
        await page.locator('#media-author').fill('Contributrice de test');
        await page.locator('#media-license-1').check();
        await page.locator('#media-rights').check();

        await page.getByRole('button', { name: 'Envoyer l’image' }).click();

        await page.waitForURL(/\/contribute\/thanks\/\d+$/);
        await expect(
            page.getByRole('heading', { level: 1, name: 'Proposition envoyée' }),
        ).toBeVisible();
    });
});
