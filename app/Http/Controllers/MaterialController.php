<?php

namespace App\Http\Controllers;

use App\Enums\MaterialType;
use App\Http\Controllers\Concerns\ReadsListFilters;
use App\Http\Resources\MaterialDetailResource;
use App\Models\LineMaterial;
use App\Models\LineMaterialPeriod;
use App\Models\Material;
use App\Services\Api\MaterialQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class MaterialController extends Controller
{
    use ReadsListFilters;

    /** Séries par page de l'annuaire. */
    private const PER_PAGE = 48;

    /** Tris proposés par l'annuaire (sous-ensemble de MaterialQuery::SORTS), le premier par défaut. */
    public const INDEX_SORTS = ['name', 'in_service_from', '-in_service_from', '-max_speed_kmh', '-units_built', '-lines_count'];

    /** Lignes parcourues lues au plus sur une fiche (la série la plus citée en compte 43). */
    private const MAX_LINES = 100;

    /** Ordre des fiabilités, du lien le plus sûr au moins sûr. */
    private const CONFIDENCE_RANK = ['high' => 0, 'medium' => 1, 'low' => 2];

    /** Largeur des vignettes de l'annuaire : taille standard des miniatures Commons (affichées à 120 px). */
    private const THUMB_WIDTH = 250;

    /**
     * Annuaire : recherche (nom, autres noms, constructeur, numéros), catégorie et tri lus dans
     * l'adresse, filtrés côté serveur par MaterialQuery (mêmes règles que `GET /api/v1/materials`).
     * Une valeur inconnue est ignorée, jamais une erreur : l'adresse se partage telle quelle.
     */
    public function index(Request $request, MaterialQuery $query): Response
    {
        $q = mb_substr($this->filterString($request, 'q', trim: true), 0, 100);
        $type = MaterialType::tryFrom($this->filterString($request, 'type'))->value ?? '';
        $sort = $this->filterString($request, 'sort');
        $sort = in_array($sort, self::INDEX_SORTS, true) ? $sort : self::INDEX_SORTS[0];

        // Colonnes choisies avant apply(), qui y ajoute le nombre de lignes (`lines_count`).
        $columns = Material::query()->select(['materials.slug', 'materials.name', 'materials.type', 'materials.builder',
            'materials.builders', 'materials.designations', 'materials.in_service_from', 'materials.in_service_to', 'materials.units_built',
            'materials.max_speed_kmh', 'materials.image', 'materials.service_status']);
        $list = $query->apply($columns, ['q' => $q, 'type' => $type, 'sort' => $sort])
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Nombre de séries par catégorie pour la recherche en cours (puces du filtre).
        $counts = $query->whereSearch(Material::query(), $q)
            ->toBase()
            ->selectRaw('type, count(*) AS n')
            ->groupBy('type')
            ->pluck('n', 'type')
            ->map(fn ($n): int => (int) $n);

        return Inertia::render('Materials/Index', [
            'materials' => [
                'data' => collect($list->items())->map(fn (Material $m): array => [
                    'slug' => $m->slug,
                    'name' => $m->name,
                    'type' => $m->getRawOriginal('type'),
                    // `builder` : texte joint, coupé à 255 caractères (« … ») ; `builders` : tous les noms (Wikidata
                    // P176), sans doublon, d'où la carte tire « trois noms et N autres » comme la fiche ligne.
                    // Un constructeur sans libellé (identifiant Wikidata seul) n'est dans aucun des deux.
                    'builder' => $m->builder,
                    'builders' => $m->builderNames(),
                    'designations' => $m->designations ?? [],
                    'in_service_from' => $m->in_service_from,
                    'in_service_to' => $m->in_service_to,
                    'units_built' => $m->units_built,
                    'max_speed_kmh' => $m->max_speed_kmh,
                    'service_status' => $m->service_status,
                    'lines_count' => (int) $m->lines_count,
                    'thumb' => self::thumbOf($m->image),
                ])->all(),
                'current_page' => $list->currentPage(),
                'last_page' => $list->lastPage(),
                'total' => $list->total(),
            ],
            // Catégories dans l'ordre de l'énumération, avec leur nombre (0 si aucune ne correspond).
            'counts' => collect(MaterialType::cases())
                ->map(fn (MaterialType $t): array => ['type' => $t->value, 'count' => $counts[$t->value] ?? 0])
                ->filter(fn (array $c): bool => $c['count'] > 0 || $c['type'] === $type)
                ->values(),
            'filters' => ['q' => $q, 'type' => $type, 'sort' => $sort],
            'sorts' => self::INDEX_SORTS,
            'overall' => Material::count(),
        ]);
    }

    /**
     * Fiche d'une série. Les clés historiques (slug, name, type, builder, années, description ;
     * lignes : slug, name, code) sont inchangées ; les autres sont des ajouts pour la fiche enrichie
     * (caractéristiques Wikidata, image Commons et ses droits, preuve de chaque lien).
     */
    public function show(Material $material): Response
    {
        $links = $material->lineLinks()
            ->with(['line:id,slug,code,name', 'shownPeriods'])
            ->join('lines', 'lines.id', '=', 'line_materials.line_id')
            ->orderBy('lines.name')
            ->select('line_materials.*')
            ->limit(self::MAX_LINES)
            ->get();
        $detail = MaterialDetailResource::make($material)->resolve(request());
        // Fiche : constructeurs et exploitants sans libellé (identifiant Wikidata seul) non affichés comme des
        // noms ; l'API les garde, avec leur `wikidata_id`.
        foreach (['builders', 'operators'] as $field) {
            $detail[$field] = array_values(array_filter($detail[$field] ?? [], fn (array $e): bool => Material::isReadableName($e['name'] ?? null)));
        }

        return Inertia::render('Materials/Show', [
            'material' => [
                'slug' => $material->slug,
                'name' => $material->name,
                'type' => $material->type,
                'builder' => $material->builder,
                'in_service_from' => $material->in_service_from,
                'in_service_to' => $material->in_service_to,
                'description' => $material->description ?? null,
            ] + Arr::only($detail, [
                'type_label', 'designations', 'builders', 'operators', 'units_built', 'max_speed_kmh', 'power_kw',
                'electrification', 'gauge_mm', 'gauges', 'wheel_arrangement', 'fleet_numbers', 'image',
                'commons_category_url', 'wikidata_url', 'wikipedia', 'field_sources', 'service', 'scope',
            ]),
            // Une ligne par entrée : quand plusieurs sources relient la série à la même ligne (import et
            // contribution validée), le lien le plus sûr est montré, avec les périodes de tous les liens.
            'lines' => $links->groupBy('line_id')->map(function ($group) {
                /** @var LineMaterial $link */
                $link = $group->sortBy(fn (LineMaterial $l): int => self::CONFIDENCE_RANK[$l->confidence] ?? 3)->first();
                $periods = $group->flatMap(fn (LineMaterial $l) => $l->shownPeriods)
                    ->sortBy(fn (LineMaterialPeriod $p): string => ($p->start_date ?? $p->end_date)?->toDateString() ?? '');

                return [
                    'slug' => $link->line->slug,
                    'name' => $link->line->name,
                    'code' => $link->line->code,
                    'confidence' => $link->confidence,
                    'source_url' => $link->source_url,
                    // Première preuve (la plus forte) : nature, article cité, section et phrase ; pour une
                    // contribution validée, titre de la source citée.
                    'kind' => $link->evidence[0]['kind'] ?? null,
                    'article' => $link->evidence[0]['article'] ?? null,
                    'section' => $link->evidence[0]['section'] ?? null,
                    'quote' => $link->evidence[0]['quote'] ?? null,
                    'source_title' => $link->evidence[0]['title'] ?? null,
                    // Périodes de circulation sur la ligne datées par une source (sûres seulement).
                    'periods' => $periods->map(fn (LineMaterialPeriod $p): array => $p->toSheet())->values()->all(),
                ];
            })->values(),
            // Nombre total de lignes parcourues (la liste est bornée à MAX_LINES).
            'lines_total' => $material->lineLinks()->distinct()->count('line_id'),
        ]);
    }

    /**
     * Vignette de l'annuaire : miniature Commons ramenée à 250 px quand l'original est plus large
     * (les miniatures de 500 px reçues de l'API pèsent quatre fois plus pour une image de 120 px).
     *
     * @param  array<string, mixed>|null  $image
     * @return array{url: string, width: int|null, height: int|null}|null
     */
    private static function thumbOf(?array $image): ?array
    {
        $url = $image['thumb_url'] ?? null;
        if (! is_string($url) || $url === '') {
            return null;
        }
        $width = isset($image['width']) ? (int) $image['width'] : null;
        if ($width !== null && $width > self::THUMB_WIDTH && substr_count($url, '/500px-') === 1) {
            $url = str_replace('/500px-', '/'.self::THUMB_WIDTH.'px-', $url);
        }

        return ['url' => $url, 'width' => $width, 'height' => isset($image['height']) ? (int) $image['height'] : null];
    }
}
