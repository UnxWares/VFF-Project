<?php

namespace App\Services\Sncf;

/**
 * Point kilométrique (PK) tel que SNCF Réseau l'écrit dans ses jeux Open Data, en mètres.
 *
 * Trois écritures existent.
 *   - « 016+953 » : kilomètre, signe, mètres : 16 953 m. « 000-168 » : 168 m en deçà du kilomètre 0 (-168).
 *   - « -001+200 » : PK négatif (-1 200 m).
 *   - « D+841 », « H+566 » : une lettre remplace le numéro du kilomètre sur les lignes 983000
 *     (Invalides - Quai-d'Orsay) et 984000 (Quai-d'Orsay - Paris-Austerlitz) du RER C. La lettre est
 *     le rang du kilomètre dans l'alphabet (A = 0, B = 1, ... D = 3, H = 7), chaque lettre valant
 *     1 000 m : la longueur du quai B de Musée-d'Orsay (jeu « liste-des-quais ») est de 216 m, de
 *     « D+822 » à « E+038 », et la ligne 984000 mesure 3 767 m sur sa forme pour « D+841 » à « H+566 »
 *     (4 x 1 000 + 566 - 841 = 3 725 m). Le sens relatif est donc établi ; l'origine absolue
 *     (la lettre A) est une convention, sans conséquence tant que tous les jeux sont lus de la même façon.
 */
final class SncfPk
{
    /** @return int|null mètres, null si l'écriture n'est pas reconnue */
    public static function meters(?string $pk): ?int
    {
        if ($pk === null || ! preg_match('/^(-?)(\d+|[A-Za-z])([+\-])(\d{1,4})$/', trim($pk), $m)) {
            return null;
        }
        $km = ctype_alpha($m[2]) ? ord(strtoupper($m[2])) - ord('A') : (int) $m[2];
        $value = $km * 1000 + ($m[3] === '-' ? -(int) $m[4] : (int) $m[4]);

        return $m[1] === '-' ? -$value : $value;
    }
}
