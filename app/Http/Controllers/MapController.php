<?php

namespace App\Http\Controllers;

use App\Models\Era;
use App\Services\NetworkFigures;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MapController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Maps', $this->mapProps());
    }

    /**
     * Carte intégrable en iframe (/embed/maps) : mêmes ères que la carte
     * principale (toutes les décennies seedées) au lieu d'une liste codée en dur.
     */
    public function embed(): Response
    {
        return Inertia::render('Embed/Maps', $this->mapProps());
    }

    /**
     * Props communes aux deux cartes.
     *
     * TOUTES les ères sont renvoyées (1900 → 2030) : le sélecteur doit fonctionner
     * sur chaque décennie. `has_data` / `segment_count` permettent au front de
     * signaler les décennies pas encore reconstituées plutôt que de les masquer.
     *
     * `schematic` : la vue ferroviaire (plan schématique, config maps.schematic) est proposée.
     *
     * `open_km` : longueur en service de l'ère d'après « Le réseau en chiffres » déjà calculé
     * (vff:compute-network-figures, rangé dans le cache), affichée par la lecture des époques ; null si le
     * calcul n'a pas encore été fait (la carte ne le lance jamais).
     *
     * @return array{eras: Collection<int, array{year: int, label: string, is_current: bool, segment_count: int, has_data: bool, open_km: int|null}>, currentEraYear: int, dataVersion: string, schematic: bool}
     */
    private function mapProps(): array
    {
        // Dernière modification des segments (index line_segments.updated_at : lecture immédiate).
        $lastUpdate = DB::table('line_segments')->max('updated_at');
        // Nombre de segments par ère : relecture des 383 000 segments (environ 50 ms), gardée en cache
        // tant que les segments ne changent pas (clé = dernière modification) et 10 min au plus (une
        // suppression seule ne change pas la clé).
        $segCounts = collect(Cache::remember(
            'map_era_segment_counts:'.($lastUpdate ?? 'none'),
            600,
            fn (): array => DB::table('line_segments')
                ->select('era_id', DB::raw('COUNT(*) AS n'))
                ->groupBy('era_id')->pluck('n', 'era_id')->map(fn ($n): int => (int) $n)->all(),
        ));

        $openKm = $this->openKmByYear();

        $eras = Era::orderBy('year')
            ->get(['id', 'year', 'label', 'is_current'])
            ->map(fn ($e) => [
                'year' => (int) $e->year,
                'label' => $e->label,
                'is_current' => (bool) $e->is_current,
                'segment_count' => (int) ($segCounts[$e->id] ?? 0),
                'has_data' => (int) ($segCounts[$e->id] ?? 0) > 0,
                'open_km' => $openKm[(int) $e->year] ?? null,
            ])->values();

        $current = $eras->firstWhere('is_current', true) ?? $eras->last();

        // Version de données dérivée de la dernière modification des segments :
        // change automatiquement à chaque (re)classification/import → bust du
        // cache des tuiles MVT côté navigateur, sans hard-refresh manuel.
        $dataVersion = $lastUpdate
            ? (string) strtotime((string) $lastUpdate).'-'.$segCounts->sum()
            : 'v0';

        return [
            'eras' => $eras,
            'currentEraYear' => $current['year'] ?? 2030,
            'dataVersion' => $dataVersion,
            'schematic' => (bool) config('maps.schematic'),
        ];
    }

    /**
     * Longueur en service par année d'ère, lue dans le cache de « Le réseau en chiffres » sans jamais la
     * calculer (lecture de la carte : aucune requête lourde).
     *
     * @return array<int, int>
     */
    private function openKmByYear(): array
    {
        $figures = Cache::get(NetworkFigures::CACHE_KEY);
        if (! is_array($figures) || ! is_array($figures['decades'] ?? null)) {
            return [];
        }
        $byYear = [];
        foreach ($figures['decades'] as $decade) {
            if (is_array($decade) && isset($decade['year'], $decade['open_km']) && is_numeric($decade['open_km'])) {
                $byYear[(int) $decade['year']] = (int) $decade['open_km'];
            }
        }

        return $byYear;
    }
}
