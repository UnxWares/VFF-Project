/**
 * Raccourcit un texte à la fin d'un mot pour qu'il tienne dans `maxWidth` pixels : les mots entiers
 * qui rentrent, suivis de « … », jamais une coupure au milieu d'un mot. Le texte entier est rendu
 * tel quel s'il tient. `measure(texte)` donne la largeur en pixels.
 *
 * @param {string} text
 * @param {number} maxWidth
 * @param {(text: string) => number} measure
 * @returns {string}
 */
export function fitWords(text, maxWidth, measure) {
    if (!text || !(maxWidth > 0) || measure(text) <= maxWidth) return text;
    const words = text.split(' ');
    for (let n = words.length - 1; n >= 1; n--) {
        const cut = `${words
            .slice(0, n)
            .join(' ')
            .replace(/[,;:\s]+$/u, '')}…`;
        if (measure(cut) <= maxWidth) return cut;
    }
    return text;
}
