<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalisation des noms de gares pour le dédoublonnage multi-sources.
 *
 *   « Gare de Saint-Pol-de-Léon »  → « saint pol de leon »
 *   « Halte de St-Aubin (Ille-et-Vilaine) » → « saint aubin »
 *   « Morlaix (gare) » → « morlaix »
 *
 * Volontairement agressive (suffixes « Ville », « Centre » conservés : ils
 * distinguent deux gares d'une même commune, ex. « Pontoise » / « Pontoise
 * Ville »). La décision finale combine toujours nom ET distance.
 */
final class StationNames
{
    public static function normalize(?string $name): ?string
    {
        if ($name === null || trim($name) === '') {
            return null;
        }
        $s = Str::lower(Str::ascii($name));
        // Contenu entre parenthèses : souvent le département ou « gare ».
        $s = preg_replace('/\([^)]*\)/', ' ', $s);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        $s = ' '.trim($s).' ';
        // Préfixes descriptifs.
        $s = preg_replace('/^ (ancien |ancienne |ex )?(batiment( voyageurs)?( de la| de l| du| des)? |bv( de la| de l| du| des)? )?(gare|halte|arret|station|point d arret|bv|batiment voyageurs)( sncf| ferroviaire| voyageurs)?( de la| de l| des| du| de| d)? /', ' ', $s);
        $s = ' '.preg_replace('/ (gare|halte|sncf|ter)$/', '', rtrim($s)).' ';
        $s = preg_replace('/ ste /', ' sainte ', $s);
        $s = preg_replace('/ st /', ' saint ', $s);
        $s = preg_replace('/ s /', ' sur ', $s);
        $s = preg_replace('/\s+/', ' ', trim($s));

        return $s === '' ? null : $s;
    }

    /**
     * Libellé SNCF (« ST-POL-DE-LEON », « Brest ») → casse lisible.
     */
    public static function prettify(string $name): string
    {
        if (mb_strtoupper($name) !== $name) {
            return $name;
        }
        $lower = mb_convert_case(mb_strtolower($name), MB_CASE_TITLE);
        $out = preg_replace_callback('/\b(De|Du|Des|La|Le|Les|Sur|Sous|En|Et|Lès|Lez|Aux|Au|D|L)\b/u',
            fn ($m) => mb_strtolower($m[1]), $lower);
        // Lettre qui suit une élision (« L'ISLE-ADAM » → « L'Isle-Adam ») ; l'article en
        // tête du nom garde sa majuscule (« LA ROCHELLE » → « La Rochelle », pas « la Rochelle »).
        $out = preg_replace_callback("/(?<=['’])\\p{Ll}/u", fn ($m) => mb_strtoupper($m[0]), $out);

        return mb_strtoupper(mb_substr($out, 0, 1)).mb_substr($out, 1);
    }
}
