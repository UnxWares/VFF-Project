import { createInertiaApp, router } from '@inertiajs/svelte';
import { mount } from 'svelte';
import { setupI18n } from './lib/i18n.js';
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
    setup({ el, App, props }) {
        setupI18n({
            locale: props.initialPage.props.locale,
            translations: props.initialPage.props.translations,
        });

        router.on('navigate', (event) => {
            setupI18n({
                locale: event.detail.page.props.locale,
                translations: event.detail.page.props.translations,
            });
            // Pages sans composant SeoHead : les balises de partage du serveur ne les décrivent plus.
            dropStaleServerSeo(location.pathname, ogLocale(event.detail.page.props.locale));
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

        // Application web installable : service worker et bandeau de mise à jour, chargés après le montage.
        import('./pwa/index.js').then(({ initPwa }) => initPwa());
    },
});
