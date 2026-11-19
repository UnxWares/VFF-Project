<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Line;
use App\Models\Media;
use App\Services\Api\MediaQuery;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

#[Group('Lignes', "Une ligne en détail : chronologie, gares dans l'ordre des points kilométriques, conversion de coordonnées en PK, signalisation, médias.", weight: 2)]
class LineMediaController extends Controller
{
    /**
     * Médias d'une ligne.
     *
     * Photos, cartes postales, plans, cartes et documents d'archive liés à la
     * ligne, paginés.
     *
     * **Droits** : les médias externes (Wikimedia Commons…) ne sont pas
     * hébergés par VFF ; `thumb_url` (vignette) et `display_url` (grande
     * taille) pointent chez le fournisseur. Chaque média porte ses propres
     * droits, à afficher avec l'image : `attribution` (ligne de crédit prête à
     * l'emploi), `author`, `license` (telle que déclarée par la source, `null`
     * si inconnue), `license_url`, `source_url` (page d'origine),
     * `rights_status` (`public_domain` domaine public, `open_license` licence
     * libre), `commercial_ok` (usage commercial permis), `share_alike`
     * (partage dans les mêmes conditions exigé), `attribution_required`.
     *
     * **Datation** : `depicted_year` est l'année représentée (pas celle de la
     * mise en ligne), `depicted_year_to` la fin d'une fourchette, et
     * `date_precision` dit ce qu'on en sait : `day`, `month`, `year`, `decade`,
     * `circa` (vers), `range` (fourchette), `before` / `after` (avant, après).
     * `captured_at` : date de prise de vue (AAAA-MM-JJ) quand elle est connue.
     *
     * `facets` compte les médias de la ligne par type, période, droits et
     * fournisseur, sur l'ensemble des médias de la ligne (sans les filtres).
     */
    #[PathParameter('line', 'Identifiant (slug) de la ligne, tel que renvoyé par la recherche ou par les GeoJSON (`line_slug`). Les lignes du réseau ferré national sont nommées `rfn-` suivi de leur code à 6 chiffres.', example: 'rfn-420000')]
    #[QueryParameter('page', 'Numéro de page, à partir de 1.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', 'Nombre de médias par page (1 à 60).', type: 'int', default: 24, example: 24)]
    #[QueryParameter('sort', "Ordre : `oldest` (époque représentée la plus ancienne d'abord), `newest` (la plus récente d'abord), `featured` (médias mis en avant d'abord). Les médias non datés viennent en dernier.", type: 'string', default: 'oldest', example: 'oldest')]
    #[QueryParameter('type', 'Type de média : `photo`, `postcard` (carte postale), `map` (carte), `plan`, `document`.', type: 'string', example: 'postcard')]
    #[QueryParameter('period', 'Époque représentée : `pre1900` (avant 1900), `1900_1944`, `1945_1969`, `1970_1999`, `since2000` (depuis 2000), `undated` (non daté). Bornes incluses.', type: 'string', example: '1900_1944')]
    #[ApiResponse(200, 'Une page de médias de la ligne, avec la pagination et les compteurs par filtre.', examples: [[
        'line' => ['slug' => 'rfn-420000', 'code' => '420000', 'name' => 'Ligne de Paris-Montparnasse à Brest'],
        'data' => [[
            'id' => 42917, 'provider' => 'commons', 'provider_label' => 'Wikimedia Commons', 'type' => 'photo',
            'title' => 'Ouest 111 Buddicom', 'caption' => 'Ouest 111 Buddicom',
            'thumb_url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/38/Ouest_111_Buddicom.jpg/500px-Ouest_111_Buddicom.jpg',
            'display_url' => 'https://upload.wikimedia.org/wikipedia/commons/3/38/Ouest_111_Buddicom.jpg',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:Ouest_111_Buddicom.jpg',
            'width' => 586, 'height' => 424, 'depicted_year' => 1850, 'depicted_year_to' => null, 'date_precision' => 'circa',
            'captured_at' => null, 'author' => null, 'attribution' => 'Public domain, via Wikimedia Commons',
            'license' => 'Public domain', 'license_url' => null, 'rights_status' => 'public_domain',
            'commercial_ok' => true, 'share_alike' => false, 'attribution_required' => false, 'featured' => false, 'location' => null,
        ]],
        'meta' => ['current_page' => 1, 'per_page' => 24, 'total' => 800, 'last_page' => 34, 'sort' => 'oldest'],
        'facets' => [
            'types' => ['photo' => 753, 'postcard' => 42, 'map' => 5],
            'periods' => ['pre1900' => 7, '1900_1944' => 41, '1945_1969' => 2, '1970_1999' => 0, 'since2000' => 729, 'undated' => 21],
            'rights' => ['public_domain' => 175, 'open_license' => 625],
            'providers' => ['commons' => 800],
        ],
    ]])]
    #[ApiResponse(404, 'Ligne inconnue (slug introuvable).')]
    public function index(Line $line, Request $request, MediaQuery $query): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:60',
            'sort' => 'nullable|in:oldest,newest,featured',
            'type' => 'nullable|in:photo,postcard,map,plan,document',
            'period' => 'nullable|in:'.implode(',', [...array_keys(MediaQuery::PERIODS), 'undated']),
        ]);

        $perPage = $request->integer('per_page', 24);
        $page = max(1, $request->integer('page', 1));
        $sort = $request->input('sort', 'oldest');

        $base = fn (): Builder => Media::query()
            ->where('mediable_type', Line::class)
            ->where('mediable_id', $line->id);

        $q = $base();
        if ($request->filled('type')) {
            $q->where('type', $request->input('type'));
        }
        if ($request->filled('period')) {
            $query->wherePeriod($q, $request->string('period')->toString());
        }

        $total = (clone $q)->count();
        match ($sort) {
            'newest' => $q->orderByRaw('depicted_year DESC NULLS LAST'),
            'featured' => $q->orderByDesc('is_featured')->orderByRaw('depicted_year ASC NULLS LAST'),
            default => $q->orderByRaw('depicted_year ASC NULLS LAST'),
        };
        $items = $q->orderBy('id')
            ->select('*')
            ->selectRaw('ST_Y(location) AS lat, ST_X(location) AS lng')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return response()->json([
            'line' => ['slug' => $line->slug, 'code' => $line->code, 'name' => $line->name],
            'data' => $items->map(fn (Media $m) => MediaResource::make($m)->resolve())->values(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'sort' => $sort,
            ],
            'facets' => $this->facets($line),
        ])->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Compteurs pour les filtres (sur l'ensemble des médias de la ligne).
     *
     * @return array<string, mixed>
     */
    private function facets(Line $line): array
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
            [Line::class, $line->id],
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
