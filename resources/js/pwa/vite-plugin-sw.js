// Plugin Vite : à la fin d'un `vite build`, compile le service worker (resources/js/pwa/sw.js) en un fichier
// unique public/sw.js (à la racine : sa portée couvre tout le site).
//
// La version injectée (__VFF_SW_VERSION__) est une empreinte des fichiers du build : dès qu'un déploiement
// change un fichier, le service worker change d'octets, le navigateur le détecte et la page propose le
// rechargement. Un build sans changement redonne le même fichier, donc aucune fausse mise à jour.
import { createHash } from 'node:crypto';
import { build } from 'vite';

/** Empreinte courte et stable d'une liste de noms de fichiers (leur nom contient déjà leur empreinte de contenu). */
export function buildVersion(fileNames) {
    return createHash('sha256')
        .update([...fileNames].sort().join('\n'))
        .digest('hex')
        .slice(0, 12);
}

export function serviceWorkerPlugin({ entry = 'resources/js/pwa/sw.js' } = {}) {
    let root;

    return {
        name: 'vff-service-worker',
        apply: 'build',
        configResolved(config) {
            root = config.root;
        },
        async writeBundle(_options, bundle) {
            await build({
                root,
                configFile: false,
                logLevel: 'warn',
                publicDir: false,
                define: { __VFF_SW_VERSION__: JSON.stringify(buildVersion(Object.keys(bundle))) },
                build: {
                    outDir: 'public',
                    emptyOutDir: false,
                    copyPublicDir: false,
                    target: 'es2022',
                    lib: {
                        entry,
                        formats: ['iife'],
                        name: 'vffServiceWorker',
                        fileName: () => 'sw.js',
                    },
                },
            });
        },
    };
}
