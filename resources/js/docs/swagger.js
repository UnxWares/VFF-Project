// Documentation REST alternative (/docs/swagger) : Swagger UI.
// Même spécification OpenAPI que Scalar (/docs/api.json, générée par Scramble).
import SwaggerUI from 'swagger-ui-dist/swagger-ui-es-bundle.js';
import 'swagger-ui-dist/swagger-ui.css';
import '../../css/docs/docs.css';
import '../../css/docs/swagger.css';
import { markContentLanguage } from './language.js';
import { isDark, setupThemeToggle } from './theme.js';

const root = document.getElementById('vff-docs-app');
root.replaceChildren();

// Thème sombre natif de Swagger UI : classe `dark-mode` sur <html>, alignée sur <html data-theme>.
const syncTheme = (dark) => document.documentElement.classList.toggle('dark-mode', dark);
syncTheme(isDark());

SwaggerUI({
    url: root.dataset.specUrl,
    domNode: root,
    deepLinking: true,
    // Pas d'appel au validateur en ligne de swagger.io.
    validatorUrl: null,
    docExpansion: 'list',
    defaultModelsExpandDepth: 0,
    displayRequestDuration: true,
    tryItOutEnabled: false,
    filter: true,
    syntaxHighlight: { activate: true, theme: 'monokai' },
});

// Interface de Swagger UI en anglais (page déclarée `lang="en"`) ; textes de la spécification en français.
markContentLanguage(
    root,
    '.renderedMarkdown, .markdown, .opblock-summary-description, .opblock-tag small',
    'fr',
);

setupThemeToggle(syncTheme);
