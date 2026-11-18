// Documentation REST alternative (/docs/swagger) : Swagger UI.
// Même spécification OpenAPI que Scalar (/docs/api.json, générée par Scramble).
import SwaggerUI from 'swagger-ui-dist/swagger-ui-es-bundle.js';
import 'swagger-ui-dist/swagger-ui.css';
import '../../css/docs/docs.css';
import '../../css/docs/swagger.css';
import { setupThemeToggle } from './theme.js';

const root = document.getElementById('vff-docs-app');
root.replaceChildren();

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

setupThemeToggle();
