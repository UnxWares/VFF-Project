// Position GPS enregistrée dans une photo JPEG (EXIF), lue dans le navigateur avant l'envoi du
// formulaire « Proposer une image ». Rien n'est envoyé : le formulaire propose seulement de
// recopier cette position dans les coordonnées, que le contributeur peut corriger ou laisser
// vides. Le serveur retire de toute façon l'EXIF du fichier (ImageSanitizer).
//
// Lecture de la structure seule : segments JPEG jusqu'au premier APP1 « Exif », en-tête TIFF
// (II ou MM), IFD0, pointeur GPS (0x8825), puis latitude (1, 2) et longitude (3, 4) en degrés,
// minutes et secondes rationnels. Tout fichier inattendu donne null, sans erreur.

const TAG_GPS_IFD = 0x8825;
const TAG_LAT_REF = 0x0001;
const TAG_LAT = 0x0002;
const TAG_LNG_REF = 0x0003;
const TAG_LNG = 0x0004;
const TYPE_ASCII = 2;
const TYPE_RATIONAL = 5;

/** Octets lus au plus : l'EXIF se trouve au début du fichier (segment APP1, 64 Kio au plus). */
export const EXIF_READ_BYTES = 256 * 1024;

/**
 * Entrées d'un répertoire TIFF (IFD), par étiquette.
 * @param {DataView} view
 * @param {number} tiff début de l'en-tête TIFF dans `view`
 * @param {number} offset position du répertoire, relative à `tiff`
 * @param {boolean} little ordre des octets « II »
 * @returns {Map<number, { type: number, count: number, value: number }>|null}
 */
function readIfd(view, tiff, offset, little) {
    const start = tiff + offset;
    if (offset < 8 || start + 2 > view.byteLength) return null;
    const count = view.getUint16(start, little);
    if (start + 2 + count * 12 > view.byteLength) return null;
    const entries = new Map();
    for (let i = 0; i < count; i++) {
        const at = start + 2 + i * 12;
        const type = view.getUint16(at + 2, little);
        const n = view.getUint32(at + 4, little);
        // Valeur sur 4 octets au plus : dans l'entrée ; sinon, son adresse relative à l'en-tête TIFF.
        const inline = type === TYPE_ASCII && n <= 4;
        entries.set(view.getUint16(at, little), {
            type,
            count: n,
            value: inline ? at + 8 : tiff + view.getUint32(at + 8, little),
        });
    }
    return entries;
}

/**
 * Degrés décimaux d'une valeur « degrés, minutes, secondes » (trois rationnels), null si illisible.
 * @param {DataView} view
 * @param {{ type: number, count: number, value: number }|undefined} entry
 * @param {boolean} little
 */
function readDegrees(view, entry, little) {
    if (!entry || entry.type !== TYPE_RATIONAL || entry.count !== 3) return null;
    if (entry.value + 24 > view.byteLength) return null;
    const parts = [0, 1, 2].map((i) => {
        const num = view.getUint32(entry.value + i * 8, little);
        const den = view.getUint32(entry.value + i * 8 + 4, little);
        return den === 0 ? NaN : num / den;
    });
    if (parts.some((v) => !Number.isFinite(v))) return null;
    return parts[0] + parts[1] / 60 + parts[2] / 3600;
}

/**
 * Première lettre d'une référence (« N », « S », « E », « W »).
 * @param {DataView} view
 * @param {{ type: number, count: number, value: number }|undefined} entry
 */
function readRef(view, entry) {
    if (!entry || entry.type !== TYPE_ASCII || entry.value >= view.byteLength) return '';
    return String.fromCharCode(view.getUint8(entry.value)).toUpperCase();
}

/**
 * Début de l'en-tête TIFF du segment EXIF d'un JPEG, null s'il n'y en a pas.
 * @param {DataView} view
 */
function exifStart(view) {
    if (view.byteLength < 4 || view.getUint16(0) !== 0xffd8) return null;
    let at = 2;
    while (at + 4 <= view.byteLength) {
        if (view.getUint8(at) !== 0xff) return null;
        const marker = view.getUint8(at + 1);
        // Début des données de l'image (SOS) ou fin : plus de métadonnées à chercher.
        if (marker === 0xda || marker === 0xd9) return null;
        const length = view.getUint16(at + 2);
        if (length < 2) return null;
        const isExif =
            marker === 0xe1 &&
            at + 10 <= view.byteLength &&
            view.getUint32(at + 4) === 0x45786966 && // « Exif »
            view.getUint16(at + 8) === 0;
        if (isExif) return at + 10;
        at += 2 + length;
    }
    return null;
}

/**
 * Position GPS d'une photo JPEG, en degrés décimaux arrondis à 6 décimales, ou null (pas de JPEG,
 * pas d'EXIF, pas de position, position hors des bornes ou au point 0, 0 laissé par certains appareils).
 * @param {ArrayBuffer} buffer début du fichier (EXIF_READ_BYTES suffisent)
 * @returns {{ lat: number, lng: number }|null}
 */
export function exifPosition(buffer) {
    const view = new DataView(buffer);
    const tiff = exifStart(view);
    if (tiff === null || tiff + 8 > view.byteLength) return null;
    const order = view.getUint16(tiff);
    if (order !== 0x4949 && order !== 0x4d4d) return null;
    const little = order === 0x4949;
    if (view.getUint16(tiff + 2, little) !== 42) return null;

    const ifd0 = readIfd(view, tiff, view.getUint32(tiff + 4, little), little);
    const pointer = ifd0?.get(TAG_GPS_IFD);
    // Pointeur GPS : une seule valeur LONG, l'adresse du répertoire GPS (readIfd l'a déjà ajoutée au début TIFF).
    if (!pointer || pointer.count !== 1) return null;
    const gps = readIfd(view, tiff, pointer.value - tiff, little);
    if (!gps) return null;

    const lat = readDegrees(view, gps.get(TAG_LAT), little);
    const lng = readDegrees(view, gps.get(TAG_LNG), little);
    if (lat === null || lng === null) return null;
    const signedLat = readRef(view, gps.get(TAG_LAT_REF)) === 'S' ? -lat : lat;
    const signedLng = readRef(view, gps.get(TAG_LNG_REF)) === 'W' ? -lng : lng;
    if (Math.abs(signedLat) > 90 || Math.abs(signedLng) > 180) return null;
    if (signedLat === 0 && signedLng === 0) return null;
    const round = (/** @type {number} */ v) => Math.round(v * 1e6) / 1e6;
    return { lat: round(signedLat), lng: round(signedLng) };
}

/**
 * Position GPS d'un fichier choisi dans le formulaire (lecture locale du début du fichier), null
 * pour un fichier qui n'est pas un JPEG ou qui n'en a pas.
 * @param {Blob} file
 * @returns {Promise<{ lat: number, lng: number }|null>}
 */
export async function fileExifPosition(file) {
    if (file.type !== 'image/jpeg') return null;
    try {
        return exifPosition(await file.slice(0, EXIF_READ_BYTES).arrayBuffer());
    } catch {
        return null;
    }
}
