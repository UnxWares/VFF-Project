// Tests de bout en bout (fumée) : `bin/pnpm test:e2e`. Voir docs/TESTS-E2E.md.
//
// Adresse cible : E2E_BASE_URL (par défaut le serveur de dev). Le navigateur vient du cache
// de Playwright (~/.cache/ms-playwright) ; à défaut : `bin/pnpm exec playwright install chromium`
// ou l'image de conteneur officielle (docs/TESTS-E2E.md).
import { defineConfig } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL || 'http://192.168.1.12:8000';
const sortie = process.env.E2E_OUTPUT_DIR || 'storage/app/reports/e2e';

export default defineConfig({
    testDir: 'tests/e2e',
    outputDir: `${sortie}/traces`,
    reporter: process.env.CI
        ? [['list'], ['html', { open: 'never', outputFolder: `${sortie}/html` }]]
        : [['list']],
    timeout: 60_000,
    expect: { timeout: 15_000 },
    fullyParallel: true,
    workers: Number(process.env.E2E_WORKERS || 2),
    retries: process.env.CI ? 1 : 0,
    use: {
        baseURL,
        // Langue du navigateur : le site s'y fie tant qu'aucun cookie de langue n'est posé.
        locale: 'fr-FR',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        // Les pages de l'API et de la carte parlent à la machine de dev : certificat local toléré.
        ignoreHTTPSErrors: true,
    },
    projects: [
        { name: 'bureau-1280', use: { viewport: { width: 1280, height: 800 } } },
        {
            name: 'mobile-390',
            use: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true },
        },
    ],
});
