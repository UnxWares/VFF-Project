// Signal « carte partielle » : nombre de cartes affichées sans réseau, avec seulement ce qui a déjà été vu
// sur cet appareil (tracés, gares, fond de carte libre des zones déjà vues). Lu par OfflineCopyNotice, qui
// ajoute alors une phrase à son message. Module à part, sans dépendance, pour que le gabarit commun des
// pages n'embarque pas le code des cartes.

const partial = $state({ count: 0 });

/** Nombre de cartes affichées sans réseau (lu par OfflineCopyNotice). */
export const partialMaps = {
    get count() {
        return partial.count;
    },
};

/**
 * Une carte s'affiche sans réseau. Renvoie la fonction à appeler quand la carte disparaît (un second appel
 * ne fait rien).
 *
 * @returns {() => void}
 */
export function reportPartialMap() {
    partial.count += 1;
    let released = false;
    return () => {
        if (released) return;
        released = true;
        partial.count -= 1;
    };
}
