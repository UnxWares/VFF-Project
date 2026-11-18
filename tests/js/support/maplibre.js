// Évaluation RÉELLE des expressions MapLibre de railStyle.js (même moteur que
// la carte : @maplibre/maplibre-gl-style-spec), sur des features fictives.
import { createExpression, featureFilter, latest } from '@maplibre/maplibre-gl-style-spec';

const GLOBALS = { zoom: 8 };

/** Spécification d'une propriété de style, ex. spec('paint_line', 'line-color'). */
export function spec(group, property) {
    const s = latest[group]?.[property];
    if (!s) throw new Error(`Propriété inconnue : ${group}.${property}`);
    return s;
}

/** Compile l'expression pour la propriété donnée ; lève une erreur lisible si elle est invalide. */
export function compile(expression, propertySpec) {
    const parsed = createExpression(expression, propertySpec);
    if (parsed.result !== 'success') {
        throw new Error(`Expression invalide : ${parsed.value.map((e) => `${e.key}: ${e.message}`).join(' ; ')}`);
    }
    return parsed.value;
}

/** Valeur de l'expression pour une feature de propriétés `properties`. */
export function evaluate(expression, propertySpec, properties) {
    return compile(expression, propertySpec).evaluate(GLOBALS, { type: 2, properties });
}

/** Couleur évaluée, en hexadécimal minuscule (#rrggbb). */
export function colorOf(expression, properties, propertySpec = spec('paint_line', 'line-color')) {
    return toHex(evaluate(expression, propertySpec, properties));
}

export function toHex(color) {
    if (typeof color === 'string') return color.toLowerCase();
    const channel = (v) => Math.round(v * 255).toString(16).padStart(2, '0');
    return `#${channel(color.r)}${channel(color.g)}${channel(color.b)}`;
}

/** Filtre MapLibre (couche `filter`) appliqué à une feature linéaire. */
export function matches(filter, properties) {
    return featureFilter(filter).filter(GLOBALS, { type: 2, properties });
}
