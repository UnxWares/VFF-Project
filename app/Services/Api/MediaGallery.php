<?php

namespace App\Services\Api;

use App\Http\Resources\MediaResource;
use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Galerie d'une fiche (ligne, gare, série de matériel) : une page de médias dans l'ordre de l'époque
 * représentée, et les compteurs des filtres. Même réponse pour `GET /api/v1/lines/{line}/media`,
 * `/stations/{station}/media` et `/materials/{material}/media`, lue par le composant de galerie des
 * fiches du site.
 *
 * Seuls les médias publiés sont dans `media` : une image proposée et encore en attente de validation
 * reste dans la table `contributions` (et son fichier dans la réserve privée), elle n'apparaît donc
 * jamais ici.
 */
final readonly class MediaGallery
{
    public const SORTS = ['oldest', 'newest', 'featured'];

    public function __construct(private MediaQuery $query) {}

    /**
     * Règles de la requête (pagination, ordre, filtres), communes aux trois points d'accès.
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:60',
            'sort' => 'nullable|in:'.implode(',', self::SORTS),
            'type' => 'nullable|in:photo,postcard,map,plan,document',
            'period' => 'nullable|in:'.implode(',', [...array_keys(MediaQuery::PERIODS), 'undated']),
        ];
    }

    /**
     * Une page de la galerie de `$subject`, avec `meta` (pagination) et `facets` (compteurs sur tous
     * ses médias, sans les filtres).
     *
     * @param  array{page?: int, per_page?: int, sort?: string, type?: ?string, period?: ?string}  $params  valeurs validées par rules()
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>, facets: array<string, mixed>}
     */
    public function page(Model $subject, array $params): array
    {
        $perPage = (int) ($params['per_page'] ?? 24);
        $page = max(1, (int) ($params['page'] ?? 1));
        $sort = (string) ($params['sort'] ?? 'oldest');

        $q = $this->query->forSubject($subject);
        if (filled($params['type'] ?? null)) {
            $q->where('type', $params['type']);
        }
        if (filled($params['period'] ?? null)) {
            $this->query->wherePeriod($q, (string) $params['period']);
        }

        $total = (clone $q)->count();
        $items = $this->query->galleryOrder($q, $sort)
            ->select('*')
            ->selectRaw('ST_Y(location) AS lat, ST_X(location) AS lng')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return [
            'data' => $items->map(fn (Media $m): array => MediaResource::make($m)->resolve())->values()->all(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'sort' => $sort,
            ],
            'facets' => $this->facets($subject),
        ];
    }

    /** Nombre de médias publiés d'une fiche (la page ne pose pas la galerie quand il vaut 0). */
    public function count(Model $subject): int
    {
        return $this->query->forSubject($subject)->count();
    }

    /**
     * Compteurs pour les filtres (sur l'ensemble des médias de la fiche) : `types`, `periods`
     * (toutes les tranches, même à 0), `rights` et `providers`.
     *
     * @return array{types: array<string, int>, periods: array<string, int>, rights: array<string, int>, providers: array<string, int>}
     */
    public function facets(Model $subject): array
    {
        $cases = [];
        foreach (MediaQuery::PERIODS as $key => [$from, $to]) {
            $cond = array_filter([
                $from !== null ? "depicted_year >= {$from}" : null,
                $to !== null ? "depicted_year <= {$to}" : null,
            ]);
            $cases[] = 'WHEN '.implode(' AND ', $cond)." THEN '{$key}'";
        }
        $periodSql = 'CASE WHEN depicted_year IS NULL THEN \'undated\' '.implode(' ', $cases).' END';

        $rows = DB::select(
            "SELECT type, {$periodSql} AS period, rights_status, provider, COUNT(*) AS n
             FROM media WHERE mediable_type = ? AND mediable_id = ?
             GROUP BY 1, 2, 3, 4",
            [$subject::class, $subject->getKey()],
        );

        $out = ['types' => [], 'periods' => array_fill_keys([...array_keys(MediaQuery::PERIODS), 'undated'], 0), 'rights' => [], 'providers' => []];
        foreach ($rows as $r) {
            $n = (int) $r->n;
            $out['types'][$r->type] = ($out['types'][$r->type] ?? 0) + $n;
            $out['periods'][$r->period] += $n;
            $out['rights'][$r->rights_status] = ($out['rights'][$r->rights_status] ?? 0) + $n;
            $out['providers'][$r->provider] = ($out['providers'][$r->provider] ?? 0) + $n;
        }

        return $out;
    }
}
