// Vignettes de Wikimedia Commons servies par VFF (relais /media/commons/, App\Http\Controllers\CommonsImageController).
// Quand le relais est activé, le serveur partage son préfixe dans la propriété Inertia `commons_proxy` ; toute
// image de Commons affichée par le site passe par commonsSrc(), qui remplace alors
// `https://upload.wikimedia.org/wikipedia/commons/…` (ou thumb.wikimedia.org) par `/media/commons/…`. Le
// navigateur ne contacte plus Wikimedia et ne reçoit plus ses cookies. Le crédit, la licence et le lien vers
// la page Commons ne changent pas : seule l'adresse de l'image change. Relais désactivé, ou adresse d'une
// autre forme : l'adresse reste telle quelle.
import { page } from '@inertiajs/svelte';

/** Largeurs de vignette que le relais accepte (App\Support\Media\CommonsImagePath::WIDTHS). */
export const THUMB_WIDTHS = [250, 330, 500, 568, 960, 1280];

const COMMONS = /^https:\/\/(?:upload|thumb)\.wikimedia\.org\/wikipedia\/commons\/([^?#\s\\]+)$/;
const SEGMENT = /^(?:[A-Za-z0-9._~!$&'()*+,;=:@-]|%[0-9A-Fa-f]{2})+$/;
const RASTER = /\.(?:jpe?g|png|gif|webp)$/i;
const THUMB_NAME = /^(?:(?:lossy|lossless)-)?(?:page[1-9]\d{0,3}-)?([1-9]\d{1,3})px-/;

/**
 * Chemin relatif d'une image de Commons que le relais sert (vignette aux largeurs de VFF ou original
 * JPEG, PNG, GIF, WebP), sinon null. Même liste blanche que le serveur, sans l'empreinte MD5 qu'il vérifie.
 * @param {string} url
 * @returns {string|null}
 */
export function commonsPath(url) {
    const match = COMMONS.exec(url);
    if (!match) return null;
    const path = match[1];
    const segments = path.split('/');
    if (!segments.every((s) => SEGMENT.test(s) && s !== '.' && s !== '..')) return null;
    const thumb = segments[0] === 'thumb';
    const [h, hh, file, name] = thumb ? segments.slice(1) : segments;
    if (
        segments.length !== (thumb ? 5 : 3) ||
        !/^[0-9a-f]$/.test(h) ||
        !/^[0-9a-f]{2}$/.test(hh) ||
        !hh.startsWith(h)
    ) {
        return null;
    }
    if (!thumb) return RASTER.test(file) ? path : null;
    const width = THUMB_NAME.exec(name);
    return width && THUMB_WIDTHS.includes(Number(width[1])) && RASTER.test(name) ? path : null;
}

/**
 * Adresse d'image à afficher : celle du relais quand `prefix` est donné et que l'image vient de Commons, sinon
 * l'adresse reçue, inchangée.
 * @template T
 * @param {T} url
 * @param {string|null|undefined} prefix préfixe du relais (`/media/commons/`), null s'il est désactivé
 * @returns {T|string}
 */
export function relayCommonsUrl(url, prefix) {
    if (!prefix || typeof url !== 'string') return url;
    const path = commonsPath(url);
    return path === null ? url : `${prefix}${path}`;
}

/**
 * Adresse d'une image de Commons pour un `src` ou un `srcset`, d'après la configuration partagée par le
 * serveur (propriété `commons_proxy`).
 * @template T
 * @param {T} url
 * @returns {T|string}
 */
export function commonsSrc(url) {
    return relayCommonsUrl(url, page?.props?.commons_proxy ?? null);
}
