<?php

namespace App\Services\Materials;

/**
 * Portions et relations nommées dans la phrase qui date une période de circulation (texte brut, liens rendus
 * par leur libellé, comme `line_material_periods.source_excerpt`) : chaque relation est la suite des lieux
 * dans l'ordre de la phrase. MaterialPeriodPortion les place ensuite sur la ligne ; ce lecteur ne connaît
 * aucune gare et ne décide rien.
 *
 * Formes lues :
 *   - suite de lieux séparés par un tiret entouré d'espaces ou un tiret demi-cadratin (« Lyon - Ambérieu -
 *     Culoz - Bellegarde - Genève », « Le Havre – Rolleville ») ; un nom à tiret collé (« Paris-Nantes »)
 *     reste un seul lieu : c'est souvent un nom de gare (« Paris-Montparnasse ») ;
 *   - « entre X et Y » ;
 *   - « de X à Y », éventuellement « et Z » (« de Marseille à Nice et Vintimille ») ;
 *   - « via X (et Y) » juste après l'un d'eux : arrêts intermédiaires (« Lyon - Genève via Culoz et Bellegarde »).
 *
 * Écarté :
 *   - une suite collée à une barre oblique (« Paris - Caen - Cherbourg / Trouville - Deauville ») : on ne sait
 *     pas à quels arrêts la variante se rattache ;
 *   - une portion qui parle de vitesse, d'électrification ou de travaux (« apte à 200 km/h entre Bernay et
 *     Lisieux ») : ce n'est pas le parcours de la série ;
 *   - « de X à Y » après un dépôt, un atelier ou une compagnie (« du dépôt de Chambéry », « Compagnie du chemin
 *     de fer de Paris à Orléans ») : ce n'est pas un parcours ;
 *   - un autre terminus au choix (« entre Paris et Toulouse ou Bordeaux »).
 * Un sigle de service collé au premier lieu (« TER Paris - Rouen ») est retiré.
 * Un nom de lieu commence par une capitale : « de 1963 à 1969 » et « entre 1962 et 1990 » ne sont pas lus.
 */
final class MaterialPortionReader
{
    /** Mot d'un nom de lieu, à capitale initiale (« Saint-Pierre-des-Corps », « Les Sables-d'Olonne »). */
    private const WORD = '\p{Lu}[\p{L}’\'.]*';

    /** Nom de lieu : mots à capitale reliés par un tiret collé ou une espace, avec leurs articles et particules. */
    private const NAME = self::WORD.'(?:(?:-|\s)(?:(?:sur|sous|en|lès|lez|les|le|la|de|du|des|aux|au)(?:-|\s))?(?:d[’\']|l[’\'])?'.self::WORD.')*';

    /** Séparateur d'arrêts : tiret demi-cadratin ou cadratin, ou tiret qui touche une espace. */
    private const SEPARATOR = '(?:\s*[–—]\s*|\s+-\s*|\s*-\s+)';

    /** Contexte qui ne décrit pas le parcours de la série (vitesse, électrification, travaux). */
    private const OFF_TOPIC = '/km\/h|\bvitesses?\b|électrifi|caténaire|\btravaux\b|\bvoie\s+unique\b|\bdoublement\b/iu';

    /** Avant « de X à Y » : X est un dépôt, un atelier, un centre, une compagnie (« du dépôt de Chambéry », « Compagnie du chemin de fer de Paris à Orléans »). */
    private const DEPOT_BEFORE = '/\b(?:dépôts?|ateliers?|technicentres?|centres?(?:\s+autorails)?|établissements?|remises?|compagnie\s+(?:\S+\s+){0,4}?chemins?\s+de\s+fer|chemins?\s+de\s+fer)\s*$/iu';

    /** Largeur (caractères) du contexte lu de part et d'autre d'une portion. */
    private const CONTEXT = 40;

    /**
     * Relations nommées par la phrase, chacune la liste de ses lieux (deux au moins), dans l'ordre de la phrase.
     *
     * @return list<list<string>>
     */
    public function relations(string $text): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $found = [];
        $taken = [];
        $patterns = [
            // « entre X et Y » ; « de X à Y (et Z) » ; puis les suites de lieux.
            '/\bentre\s+(?<a>'.self::NAME.')\s+et\s+(?<b>'.self::NAME.')/u',
            '/\b(?:de|d[’\']|depuis)\s*(?<a>'.self::NAME.')\s+(?:à|au|jusqu[’\']à)\s+(?<b>'.self::NAME.')(?:\s+et\s+(?<c>'.self::NAME.'))?/u',
            '/(?<chain>'.self::NAME.'(?:'.self::SEPARATOR.self::NAME.')+)/u',
        ];
        foreach ($patterns as $k => $pattern) {
            if (! preg_match_all($pattern, $text, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($all as $m) {
                [$start, $length] = [$m[0][1], strlen($m[0][0])];
                // Déjà lu par une forme précédente (« entre Lyon - Perrache et… ») : la première forme gagne.
                foreach ($taken as [$s, $l]) {
                    if ($start < $s + $l && $s < $start + $length) {
                        continue 2;
                    }
                }
                $before = substr($text, max(0, $start - self::CONTEXT), min(self::CONTEXT, $start));
                $after = substr($text, $start + $length, self::CONTEXT);
                if (preg_match(self::OFF_TOPIC, $before.' '.$after)) {
                    continue;
                }
                if ($k === 1 && preg_match(self::DEPOT_BEFORE, substr($text, max(0, $start - self::CONTEXT), $start - max(0, $start - self::CONTEXT)))) {
                    continue;
                }
                // Variante à la barre oblique, avant ou après, ou autre terminus (« entre Paris et Toulouse ou
                // Bordeaux ») : la relation n'est pas sûre.
                if (preg_match('~^\s*/~u', $after) || preg_match('~/\s*$~u', $before) || preg_match('/^\s+ou\s/u', $after)) {
                    $taken[] = [$start, $length];

                    continue;
                }
                $places = $k === 2
                    ? array_map('trim', preg_split('/'.self::SEPARATOR.'/u', $m['chain'][0]) ?: [])
                    : [$m['a'][0], $m['b'][0], ...(isset($m['c']) && $m['c'][1] >= 0 ? [$m['c'][0]] : [])];
                // Sigle de service collé au premier lieu (« TER Paris ») retiré, point final de la phrase aussi.
                $places = array_map(fn (string $p): string => rtrim((string) preg_replace('/^\p{Lu}{2,}\s+(?=\p{Lu})/u', '', $p), '.'), $places);
                $via = $this->via($after);
                if ($via !== []) {
                    array_splice($places, count($places) - 1, 0, $via);
                }
                $taken[] = [$start, $length];
                if (count($places) >= 2) {
                    $found[$start] = $places;
                }
            }
        }
        ksort($found);

        return array_values($found);
    }

    /**
     * Arrêts intermédiaires « via X (et Y) » juste après une relation.
     *
     * @return list<string>
     */
    private function via(string $after): array
    {
        if (! preg_match('/^\s*,?\s*via\s+(?<a>'.self::NAME.')(?:\s+et\s+(?<b>'.self::NAME.'))?/u', $after, $m)) {
            return [];
        }

        return array_values(array_filter(array_map(fn (string $p): string => rtrim($p, '.'), [$m['a'], $m['b'] ?? '']), fn (string $p): bool => $p !== ''));
    }
}
