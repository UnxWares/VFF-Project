<?php

namespace App\Services\Greenways;

use Illuminate\Support\Str;

/**
 * Nom sous lequel une voie verte est présentée (fiche ligne, API) : il doit désigner un itinéraire.
 * Les noms qu'OpenStreetMap donne aux relations et aux ways couvrent aussi des lieux (« Camping »,
 * « Le Lac »), des types de voie (« Voie verte ») et des rues (« Rue du Denier ») : ce ne sont pas
 * des itinéraires, et ils ne servent pas de nom de groupe. Le way reste rattaché à la ligne, sans
 * nom, et sa propre fiche (`ways[].name`) garde le nom OSM tel quel.
 *
 * Règle écrite, sans devinette : seuls sont écartés
 *   1. un nom (de relation ou de way) qui se réduit à un des mots de GENERIC (lieu, équipement ou
 *      type de voie, avec ou sans article), comme « Camping », « Le Lac », « Voie Verte » ;
 *   2. un nom de WAY qui commence par un des mots de ROAD_WORDS (rue, avenue, route, allée…) : c'est
 *      alors le nom d'une voie de circulation. « Avenue Verte » (itinéraire London - Paris) est
 *      conservé, la voie verte étant son propre nom.
 * Un nom de relation est donc l'itinéraire par construction (relation `route=bicycle|mtb`) ; seul le
 * nom d'un lieu ou d'un type de voie y est écarté. Un nom qui ne tombe dans aucune de ces deux
 * règles est gardé tel quel, même s'il paraît douteux : rien n'est deviné.
 */
final class GreenwayNames
{
    /** Noms entiers qui ne désignent aucun itinéraire (sans accent, minuscules, sans article initial). */
    public const GENERIC = [
        'camping', 'parking', 'lac', 'musee', 'musees', 'gare', 'ecole', 'stade', 'piscine', 'cimetiere', 'mairie',
        'eglise', 'plage', 'port', 'zone', 'centre', 'bourg', 'village', 'ville',
        'voie verte', 'voie douce', 'piste cyclable', 'piste', 'veloroute', 'voie', 'chemin', 'sentier', 'itineraire',
        'itineraire cyclable', 'boucle', 'liaison', 'circuit', 'parcours',
    ];

    /** Premiers mots d'un nom de way qui désigne une voie de circulation (sans accent, minuscules). */
    public const ROAD_WORDS = [
        'rue', 'avenue', 'boulevard', 'route', 'allee', 'impasse', 'place', 'quai', 'cours', 'passage', 'square',
        'esplanade', 'chaussee',
    ];

    /**
     * Nom affiché d'un way rattaché à une ligne : celui de son itinéraire cyclable OSM s'il est retenu,
     * sinon le sien s'il est retenu, sinon null. Seule règle de nom des voies vertes : calculée une fois
     * par `vff:link-greenways` (colonne `line_greenways.display_name`), puis lue telle quelle par la
     * fiche, l'API REST, GraphQL, le serveur MCP et les tuiles de la carte.
     */
    public static function displayName(?string $relationName, ?string $wayName): ?string
    {
        return self::relationName($relationName) ?? self::wayName($wayName);
    }

    /** Nom d'une relation OSM (itinéraire cyclable) à retenir comme nom de voie verte, ou null. */
    public static function relationName(?string $name): ?string
    {
        $name = self::trimmed($name);

        return $name === null || self::isGeneric($name) ? null : $name;
    }

    /** Nom d'un way OSM à retenir comme nom de voie verte, ou null. */
    public static function wayName(?string $name): ?string
    {
        $name = self::trimmed($name);

        return $name === null || self::isGeneric($name) || self::isRoad($name) ? null : $name;
    }

    private static function isGeneric(string $name): bool
    {
        return in_array(self::normalized($name), self::GENERIC, true);
    }

    private static function isRoad(string $name): bool
    {
        $words = implode('|', self::ROAD_WORDS);

        return preg_match('/^(?:'.$words.')(?![a-z])(?!\s+verte\b)/', self::normalized($name, false)) === 1;
    }

    /** Sans accent ni majuscule, apostrophes droites ; l'article initial est retiré avec `$article`. */
    private static function normalized(string $name, bool $article = true): string
    {
        $name = Str::ascii(mb_strtolower(str_replace('’', "'", $name)));
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        return $article ? (preg_replace('/^(?:(?:les|le|la)\s+|l\')/', '', $name) ?? $name) : $name;
    }

    private static function trimmed(?string $name): ?string
    {
        $name = $name === null ? null : trim($name);

        return $name === '' ? null : $name;
    }
}
