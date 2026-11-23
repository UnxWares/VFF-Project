<?php

namespace App\Services\Communes;

use App\Support\FrenchDepartments;
use App\Support\Search\SearchQuery;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Communes qui ont du rail : celles où se trouve au moins une gare ou une halte, ou que traverse ou traversait
 * au moins une ligne. C'est la condition d'existence d'une fiche « Le rail à <commune> » (/communes/{insee}-{nom}).
 *
 * Deux sources pour la liste complète (recherche, plan du site) :
 * - la table `commune_rail`, remplie par `vff:build-commune-rail` (gares et lignes de toutes les ères) ;
 * - à défaut (table absente ou vide), les communes dont un contour contient une gare, calculées à la volée
 *   et gardées en cache : les communes que seule une ligne traverse (LGV sans gare) n'y figurent pas encore,
 *   mais leur fiche répond quand même, car CommuneSheet la vérifie commune par commune.
 *
 * Lecture seule : rien n'est écrit dans la base.
 */
final class CommuneRail
{
    /** Durée de garde de la liste (secondes). */
    public const TTL = 86400;

    /** Au plus autant de communes dans les résultats de recherche. */
    public const SEARCH_LIMIT = 8;

    /** Code INSEE : cinq chiffres, ou 2A / 2B puis trois chiffres (Corse). */
    public const INSEE_PATTERN = '(?:[0-9]{5}|2[AB][0-9]{3})';

    /** Texte cherché d'une commune : son nom normalisé, précédé d'une espace comme les clés de SearchText. */
    /** Colonnes lues d'une commune trouvée. */
    public const COLUMNS = ['communes.code_insee', 'communes.nom', 'communes.departement', 'communes.population'];

    private const KEY = "(' ' || communes.nom_norm)";

    /** Adresse relative d'une fiche : `16015-angouleme`. */
    public static function slug(string $insee, string $name): string
    {
        $words = Str::slug($name);

        return $words === '' ? $insee : "{$insee}-{$words}";
    }

    /** Code INSEE d'une adresse `16015-angouleme` (ou `16015`), null si elle n'en commence pas par un. */
    public static function inseeOf(string $slug): ?string
    {
        return preg_match('/^('.self::INSEE_PATTERN.')(?:-[a-z0-9-]*)?$/', $slug, $m) === 1 ? $m[1] : null;
    }

    /** La table `commune_rail` est-elle là (migration appliquée) ? */
    public static function hasTable(): bool
    {
        static $has = null;

        return $has ??= Schema::hasTable('commune_rail');
    }

    /**
     * Codes INSEE des communes qui ont du rail.
     *
     * @return list<string>
     */
    public function codes(): array
    {
        $table = self::hasTable() && DB::table('commune_rail')->exists();

        /** @var list<string> */
        return Cache::remember('communes:rail:'.($table ? 'table' : 'stations'), self::TTL, fn (): array => $table
            ? DB::table('commune_rail')->orderBy('code_insee')->pluck('code_insee')->all()
            : DB::table('stations as s')
                ->join('commune_contours as c', fn ($j) => $j->whereRaw('ST_Contains(c.geom, s.geom)'))
                ->whereRaw("COALESCE(s.provenance->>'kind', '') NOT IN ('technical', 'excluded')")
                ->distinct()->orderBy('c.code_insee')->pluck('c.code_insee')->all());
    }

    /**
     * Communes qui ont du rail et correspondent à la requête, classées par pertinence du nom (mêmes règles de lecture
     * que le reste de la recherche : casse, accents, tirets, abréviations, petits mots), puis les plus peuplées. Pas
     * de noms approchés : une commune se trouve par son nom.
     *
     * @return list<array{type: string, insee: string, slug: string, name: string, department_code: ?string, department_name: ?string, population: ?int}>
     */
    public function search(string $q, int $limit = self::SEARCH_LIMIT): array
    {
        $builder = $this->builder($q);

        return $builder === null ? [] : array_map(self::present(...), $this->ordered($builder, $q)->limit($limit)->get(self::COLUMNS)->all());
    }

    /**
     * Les communes qui ont du rail et dont le nom correspond à la requête (sans ordre) ; null si la requête ne dit
     * rien (vide, ou des chiffres : un numéro de département n'est pas un nom de commune).
     */
    public function builder(string $q): ?Builder
    {
        $search = SearchQuery::parse($q);
        if ($search->isEmpty() || $search->digits() !== null) {
            return null;
        }
        $builder = DB::table('communes')->where('communes.type', 'commune-actuelle')
            ->whereRaw('communes.code_insee = ANY(?::text[])', ['{'.implode(',', $this->codes()).'}']);

        return $search->where($builder, [self::KEY, self::KEY]);
    }

    /** Ordre de pertinence du nom, puis les plus peuplées, puis le code (ordre stable). */
    public function ordered(Builder $builder, string $q): Builder
    {
        return SearchQuery::parse($q)->orderByRelevance($builder, self::KEY, self::KEY)
            ->orderByDesc('communes.population')->orderBy('communes.code_insee');
    }

    /**
     * Une ligne de `communes` présentée (adresse, département), pour la recherche.
     *
     * @return array{type: string, insee: string, slug: string, name: string, department_code: ?string, department_name: ?string, population: ?int}
     */
    public static function present(object $row): array
    {
        return [
            'type' => 'commune',
            'insee' => (string) $row->code_insee,
            'slug' => self::slug((string) $row->code_insee, (string) $row->nom),
            'name' => (string) $row->nom,
            'department_code' => $row->departement,
            'department_name' => $row->departement === null ? null : (FrenchDepartments::ALL[$row->departement][0] ?? null),
            'population' => $row->population === null ? null : (int) $row->population,
        ];
    }
}
