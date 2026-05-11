import { createInertiaApp, router } from '@inertiajs/svelte';
import { mount } from 'svelte';
import { setupI18n } from './lib/i18n.js';

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.svelte', { eager: true });
        const page = pages[`./Pages/${name}.svelte`];
        if (!page) {
            throw new Error(`Page Inertia introuvable : ${name}`);
        }
        return page;
    },
    setup({ el, App, props }) {
        // Initialise i18next avec la locale + traductions servies par le backend
        // au tout premier rendu de l'app (depuis page.props partagées via Inertia).
        setupI18n({
            locale: props.initialPage.props.locale,
            translations: props.initialPage.props.translations,
        });

        // Re-synchronise i18next à chaque navigation Inertia (locale ou translations
        // peuvent avoir changé, ex. après POST /locale).
        router.on('navigate', (event) => {
            setupI18n({
                locale: event.detail.page.props.locale,
                translations: event.detail.page.props.translations,
            });
        });

        mount(App, { target: el, props });
    },
});
