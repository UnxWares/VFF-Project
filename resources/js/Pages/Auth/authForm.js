// Aides communes aux formulaires de compte (connexion, inscription, mots de passe).
import { tick } from 'svelte';

/** Adresse e-mail plausible (la règle stricte reste côté serveur). */
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** @param {string} value */
export function isEmail(value) {
    return EMAIL.test(value.trim());
}

/** Longueur minimale d'un mot de passe (règle du serveur : Password::min(8)). */
export const PASSWORD_MIN = 8;

/**
 * Après un envoi refusé, met le focus sur le premier champ en erreur, dans l'ordre d'affichage.
 * Attend le rendu : aria-invalid est posé par Field une fois l'erreur affichée.
 * @param {string[]} ids identifiants des champs, dans l'ordre de la page
 */
export async function focusFirstError(ids) {
    await tick();
    for (const id of ids) {
        const el = document.getElementById(id);
        if (el && el.getAttribute('aria-invalid') === 'true') {
            el.focus();
            return;
        }
    }
}
