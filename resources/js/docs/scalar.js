// Documentation REST principale (/docs/api) : Scalar API Reference.
// Spécification OpenAPI 3.1 générée par Scramble (/docs/api.json).
import { createApiReference } from '@scalar/api-reference';
import '@scalar/api-reference/style.css';
import '../../css/docs/docs.css';
import '../../css/docs/scalar.css';
import { isDark, setupThemeToggle } from './theme.js';

const root = document.getElementById('vff-docs-app');
const locale = root.dataset.locale;
// Le message « Chargement… » du HTML est retiré avant le montage : Vue ne doit pas tenter de réutiliser
// ce contenu (avertissement d'hydratation dans la console).
root.replaceChildren();

const reference = createApiReference(root, {
    url: root.dataset.specUrl,
    // Souveraineté : ni télémétrie, ni polices chargées chez Scalar, ni agent IA distant.
    telemetry: false,
    withDefaultFonts: false,
    agent: { disabled: true },
    mcp: { disabled: true },
    showDeveloperTools: 'never',
    hideDarkModeToggle: true,
    forceDarkModeState: isDark() ? 'dark' : 'light',
    // Langue de l'interface Scalar (fr, en et de sont fournies par le paquet).
    localization: { locale: ['fr', 'en', 'de'].includes(locale) ? locale : 'en' },
    theme: 'default',
    layout: 'modern',
    defaultOpenAllTags: true,
    hideModelNames: false,
    documentDownloadType: 'json',
    defaultHttpClient: { targetKey: 'shell', clientKey: 'curl' },
    metaData: { title: root.dataset.title },
});

setupThemeToggle((dark) => {
    reference.updateConfiguration({ forceDarkModeState: dark ? 'dark' : 'light' });
});
