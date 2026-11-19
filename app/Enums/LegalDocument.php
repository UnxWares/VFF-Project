<?php

namespace App\Enums;

/**
 * Textes dont un changement important doit être annoncé aux titulaires d'un compte :
 * les CGU (« au moins 15 jours avant son entrée en vigueur ») et la politique de
 * confidentialité (« les titulaires d'un compte sont prévenus », sans délai fixé).
 */
enum LegalDocument: string
{
    case UseConditions = 'use-conditions';
    case Privacy = 'privacy';

    /** Délai minimal, en jours, entre l'envoi de l'information et l'entrée en vigueur. */
    public function minimumNoticeDays(): int
    {
        return match ($this) {
            self::UseConditions => 15,
            self::Privacy => 0,
        };
    }

    /** Version en vigueur : date AAAA-MM-JJ de la page publiée (config/legal.php). */
    public function currentVersion(): string
    {
        return (string) config('legal.versions.'.$this->value);
    }

    /** Nom de la route de la page publique du texte. */
    public function routeName(): string
    {
        return $this->value;
    }
}
