// Tracé de la carte d'une fiche de ligne (portions de LinePhases : géométrie simplifiée, longueur, sources),
// lu à part au moment où la carte s'affiche (App\Http\Controllers\LinePortionsController) : la page n'en
// porte que le nombre et l'adresse, qui contient l'empreinte de sa version. Le navigateur garde la réponse
// en cache (adresse immuable), et le service worker en garde une copie pour la fiche relue hors ligne
// (pwa/rules.js, `linedata`).

/** Tracés gardés en mémoire pour la visite (retour sur une fiche déjà ouverte) : les plus récents. */
const MAX_KEPT = 4;

/** @type {Map<string, Promise<Array<{ length_m: number, geometry: any, sources?: any }>>>} */
const kept = new Map();

/**
 * Portions d'une fiche, dans l'ordre que désignent les phases de la page. Une même adresse n'est demandée
 * qu'une fois ; un échec (réseau, réponse inattendue) n'est pas gardé, la visite suivante réessaie.
 *
 * @param {string} url adresse donnée par la page (`portions_url`)
 * @param {typeof fetch} [fetchImpl]
 * @returns {Promise<Array<{ length_m: number, geometry: any, sources?: any }>>}
 */
export function loadLinePortions(url, fetchImpl = fetch) {
    const hit = kept.get(url);
    if (hit) {
        // Plus récent : remis en fin de liste.
        kept.delete(url);
        kept.set(url, hit);
        return hit;
    }

    const request = fetchImpl(url, { headers: { Accept: 'application/json' } })
        .then((response) => {
            if (!response.ok) throw new Error(`Tracé de la ligne : HTTP ${response.status}`);
            return response.json();
        })
        .then((body) => {
            if (!Array.isArray(body?.portions))
                throw new Error('Tracé de la ligne : réponse inattendue');
            return body.portions;
        });
    request.catch(() => {
        if (kept.get(url) === request) kept.delete(url);
    });

    kept.set(url, request);
    for (const oldest of [...kept.keys()].slice(0, Math.max(0, kept.size - MAX_KEPT))) {
        kept.delete(oldest);
    }
    return request;
}

/** Vide la mémoire des tracés (tests). */
export function forgetLinePortions() {
    kept.clear();
}
