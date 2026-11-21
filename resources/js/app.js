import { createInertiaApp, router } from '@inertiajs/svelte';
import i18next from 'i18next';
import { mount } from 'svelte';
import { applyPageTranslations } from './lib/i18n.js';
import { installPatrick } from './lib/patrick.js';
import { dropStaleServerSeo } from './lib/serverSeo.js';
import { ogLocale } from './lib/structuredData.js';

// Lazy import : chaque page Svelte = chunk séparé chargé à la demande.
// Bénéfice énorme pour connexions lentes : on télécharge ~50 KB pour Home
// au lieu de 1.3 MB qui contiendrait MapLibre pour TOUS les visiteurs.
const pages = import.meta.glob('./Pages/**/*.svelte');

createInertiaApp({
    resolve: async (name) => {
        const importer = pages[`./Pages/${name}.svelte`];
        if (!importer) {
            throw new Error(`Page Inertia introuvable : ${name}`);
        }
        return importer();
    },
    async setup({ el, App, props }) {
        // Dictionnaire de la langue (fichier gardé en cache, déjà demandé par la page) avant le premier rendu.
        await applyPageTranslations(props.initialPage.props);

        router.on('navigate', (event) => {
            applyPageTranslations(event.detail.page.props);
            // Pages sans composant SeoHead : les balises de partage du serveur ne les décrivent plus
            // (la description redevient celle du site).
            dropStaleServerSeo(
                location.pathname,
                ogLocale(event.detail.page.props.locale),
                i18next.t('common.app_description'),
            );
        });

        // Barre de progression discrète sur les navigations Inertia (utile
        // pendant le download du chunk de la page suivante sur co lente).
        let progressEl = null;
        let progressTimer = null;
        router.on('start', () => {
            // Délai 150ms : pas de barre si la navigation est instantanée.
            progressTimer = setTimeout(() => {
                progressEl = document.createElement('div');
                progressEl.className = 'vff-route-progress';
                document.body.appendChild(progressEl);
            }, 150);
        });
        router.on('finish', () => {
            clearTimeout(progressTimer);
            progressEl?.remove();
            progressEl = null;
        });

        mount(App, { target: el, props });

        // Clin d'œil « patrick » : seul le guetteur de clavier est chargé ici, le dessin à la demande.
        installPatrick({ t: (key) => String(i18next.t(key)) });

        // Application web installable : service worker et bandeau de mise à jour, chargés après le montage.
        import('./pwa/index.js').then(({ initPwa }) => initPwa());
    },
});
