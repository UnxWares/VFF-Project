// Teintes des calques thématiques facultatifs de la carte (passages à niveau, signalisation) :
// cinq teintes catégorielles du guide dataviz, dans un ordre fixe, validées par son script
// (validate_palette.js, mode clair : séparation pour les daltonismes ΔE ≥ 9, vision normale ΔE ≥ 19).
// Leur contraste sur le fond de carte est faible pour trois d'entre elles : chaque marque a un
// liseré clair, et la légende et les bulles donnent toujours le libellé en texte.
export const OVERLAY_COLORS = ['#2a78d6', '#1baf7a', '#eda100', '#e87ba4', '#6250d6'];

/** Liseré clair autour des marques, lisible sur le fond OpenStreetMap comme sur les tracés. */
export const OVERLAY_HALO = '#ffffff';
