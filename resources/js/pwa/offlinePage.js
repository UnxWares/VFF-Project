// Page « hors ligne » : document HTML autonome (styles et scripts en ligne, aucune ressource du site sauf
// l'icône rangée à l'installation du service worker). Elle est fabriquée par la page dans la langue de
// l'utilisateur, avec les textes de lang/*/pwa.php, puis rangée dans le cache `vff-offline` ; le service
// worker la sert quand une navigation échoue faute de réseau.
import { OFFLINE_CACHE, OFFLINE_KEY } from './rules.js';

const ESCAPES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

function escapeHtml(text) {
    return String(text).replace(/[&<>"']/g, (char) => ESCAPES[char]);
}

/**
 * @param {{lang: string, documentTitle: string, title: string, text: string, retry: string}} messages
 * @returns {string}
 */
export function renderOfflineHtml({ lang, documentTitle, title, text, retry }) {
    return `<!doctype html>
<html lang="${escapeHtml(lang)}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="robots" content="noindex">
<title>${escapeHtml(documentTitle)}</title>
<style>
:root { color-scheme: light; --bg: #f8f6f1; --text: #25221e; --muted: #57524a; --accent: #ff6500; --on-accent: #1c1a17; --focus: #a94300; }
:root[data-theme='dark'] { color-scheme: dark; --bg: #0f1419; --text: #e8ecec; --muted: #94a3b8; --focus: #ff7a26; }
* { box-sizing: border-box; }
body { margin: 0; min-height: 100dvh; display: grid; place-items: center; padding: 24px 16px; background: var(--bg); color: var(--text); font: 1.0625rem/1.6 system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
main { max-width: 32rem; text-align: center; }
img { display: block; margin: 0 auto 24px; border-radius: 50%; }
h1 { margin: 0 0 12px; font-size: clamp(1.5rem, 1.3rem + 1vw, 2rem); line-height: 1.2; }
p { margin: 0 0 28px; color: var(--muted); }
button { min-height: 44px; padding: 10px 24px; border: 2px solid var(--accent); border-radius: 8px; background: var(--accent); color: var(--on-accent); font: inherit; cursor: pointer; }
button:hover { filter: brightness(0.94); }
button:focus-visible { outline: 2px solid var(--focus); outline-offset: 2px; }
</style>
<script>
(function () {
    var saved = document.cookie.match(/(?:^|; )theme=(dark|light)(?:;|$)/);
    var dark = saved ? saved[1] === 'dark' : !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
})();
</script>
</head>
<body>
<main>
<img src="/icons/icon-192.png" width="96" height="96" alt="">
<h1>${escapeHtml(title)}</h1>
<p>${escapeHtml(text)}</p>
<button type="button" id="retry">${escapeHtml(retry)}</button>
</main>
<script>
document.getElementById('retry').addEventListener('click', function () { location.reload(); });
window.addEventListener('online', function () { location.reload(); });
</script>
</body>
</html>
`;
}

/** Range (ou remplace) la page hors ligne dans le cache que le service worker interroge. */
export async function storeOfflinePage(cacheStorage, html) {
    const cache = await cacheStorage.open(OFFLINE_CACHE);
    await cache.put(
        OFFLINE_KEY,
        new Response(html, { headers: { 'Content-Type': 'text/html; charset=utf-8' } }),
    );
}
