// Fixtures communes : espion de console et de réseau, langue, thème, axe-core.
import AxeBuilder from '@axe-core/playwright';
import { expect, test as base } from '@playwright/test';

// Ce qui n'est pas une erreur du site : navigation annulée par la page suivante.
const IGNORE_ECHEC = /ERR_ABORTED/;

// Défauts du site déjà constatés : signalés dans le rapport (annotation), sans faire échouer
// la suite. Retirer l'entrée dès que le défaut est corrigé.
export const DEFAUTS_CONNUS = [
    {
        regle: 'label-content-name-mismatch',
        cible: '.lang-trigger',
        note: 'Le nom accessible du bouton de langue ne contient pas son texte visible (WCAG 2.5.3).',
    },
];

export const test = base.extend({
    // Surveille la page : console en erreur, requêtes échouées, réponses HTTP >= 400.
    espion: async ({ page }, use) => {
        const erreurs = { console: [], reseau: [], http: [] };
        page.on('console', (m) => {
            if (m.type() === 'error') erreurs.console.push(m.text());
        });
        page.on('pageerror', (e) => erreurs.console.push(`exception : ${e.message}`));
        page.on('requestfailed', (r) => {
            const raison = r.failure()?.errorText ?? '';
            if (!IGNORE_ECHEC.test(raison)) erreurs.reseau.push(`${r.url()} (${raison})`);
        });
        page.on('response', (r) => {
            if (r.status() >= 400) erreurs.http.push(`${r.status()} ${r.url()}`);
        });
        await use(erreurs);
    },
});

export { expect };

/** Pose la langue (cookie chiffré posé par le serveur) puis le thème (cookie lu avant le rendu). */
export async function preparer(page, { lang = 'fr', theme = null } = {}) {
    if (lang !== 'fr') await page.goto(`/locale/${lang}`);
    if (theme) {
        const url = test.info().config.projects[0].use.baseURL ?? process.env.E2E_BASE_URL;
        await page.context().addCookies([{ name: 'theme', value: theme, url }]);
    }
}

/**
 * Charge une page et vérifie le socle : statut HTTP, titre, élément clé visible, console propre,
 * aucun appel réseau en échec, axe-core sans violation grave (serious ou critical).
 */
export async function verifier(page, espion, { chemin, statut = 200, titre, cle, axe = true }) {
    const reponse = await page.goto(chemin, { waitUntil: 'load' });
    expect(reponse.status(), `statut HTTP de ${chemin}`).toBe(statut);
    await expect(cle(page).first()).toBeVisible();
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveTitle(titre);

    if (axe) {
        const rapport = await new AxeBuilder({ page }).analyze();
        const graves = [];
        for (const v of rapport.violations) {
            if (!['serious', 'critical'].includes(v.impact)) continue;
            const connu = DEFAUTS_CONNUS.find(
                (d) => d.regle === v.id && v.nodes.every((n) => String(n.target).includes(d.cible)),
            );
            if (connu)
                test.info().annotations.push({
                    type: 'défaut connu',
                    description: `${chemin} : ${v.id} ${connu.cible}`,
                });
            else graves.push(v);
        }
        expect(
            graves.map((v) => `${v.id} (${v.impact}) x${v.nodes.length} : ${v.nodes[0]?.target}`),
            `violations axe-core de ${chemin}`,
        ).toEqual([]);
    }

    // Le statut 404 de la page d'erreur elle-même n'est pas un échec réseau.
    const http = espion.http.filter(
        (l) => !(statut >= 400 && l === `${statut} ${new URL(chemin, page.url()).href}`),
    );
    // Chrome journalise le statut de la page d'erreur elle-même : attendu quand on teste un 404.
    const console = espion.console.filter(
        (l) => !(statut >= 400 && l.includes(`status of ${statut}`)),
    );
    expect(console, `erreurs de console de ${chemin}`).toEqual([]);
    expect(espion.reseau, `requêtes échouées de ${chemin}`).toEqual([]);
    expect(http, `réponses HTTP en erreur de ${chemin}`).toEqual([]);
}
