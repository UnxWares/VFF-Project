<?php

namespace Tests\Feature\Performance;

use App\Services\LinePhases;
use App\Services\LineStations;
use App\Services\LineStats;
use App\Support\LineDataSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsRailNetwork;
use Tests\TestCase;

/**
 * Calculs géographiques de la fiche de ligne gardés en cache (LinePhases::forLine, extrémités de
 * LineStations::terminals, longueurs de LineStats::forEra) : même résultat que sans cache, et recalcul dès
 * que les données lues changent (LineDataSignature), même par une écriture SQL brute sans `updated_at` et
 * dans la même transaction (les tests tournent dans une seule transaction, comme RefreshDatabase).
 */
class LineSheetCacheTest extends TestCase
{
    use BuildsRailNetwork;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRailNetwork();
        DB::table('station_lines')->insert([
            ['station_id' => $this->origin->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk', 'pk_m' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['station_id' => $this->destination->id, 'line_id' => $this->line->id, 'method' => 'sncf_pk', 'pk_m' => 17_000, 'created_at' => now(), 'updated_at' => now()],
        ]);
        Cache::flush();
    }

    /**
     * Résultat d'un appel et requêtes SQL qu'il a lancées.
     *
     * @return array{0: mixed, 1: list<string>}
     */
    private function measure(callable $call): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $result = $call();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return [$result, $queries];
    }

    /** @param list<string> $queries */
    private function hits(array $queries, string $needle): int
    {
        return count(array_filter($queries, fn (string $sql): bool => str_contains($sql, $needle)));
    }

    public function test_signature_changes_on_raw_writes_inside_one_transaction(): void
    {
        $id = $this->line->id;
        $before = LineDataSignature::segments($id);
        $this->assertSame($before, LineDataSignature::segments($id), 'stable sans écriture');

        DB::update('UPDATE line_segments SET status = status WHERE line_id = ?', [$id]);
        $afterUpdate = LineDataSignature::segments($id);
        $this->assertNotSame($before, $afterUpdate, 'mise à jour sans updated_at');

        $all = LineDataSignature::segmentsEventsAndEras($id);
        DB::update('UPDATE line_events SET description = description WHERE line_id = ?', [$id]);
        $this->assertNotSame($all, $all2 = LineDataSignature::segmentsEventsAndEras($id), 'événement réécrit');
        DB::update('UPDATE eras SET label = label WHERE id = ?', [$this->pastEra->id]);
        $this->assertNotSame($all2, $all3 = LineDataSignature::segmentsEventsAndEras($id), 'ère réécrite');
        DB::update('UPDATE lines SET name = name WHERE id = ?', [$id]);
        $this->assertNotSame($all3, LineDataSignature::segmentsEventsAndEras($id), 'ligne réécrite');

        DB::delete('DELETE FROM line_segments WHERE line_id = ? AND era_id = ?', [$id, $this->pastEra->id]);
        $this->assertNotSame($afterUpdate, LineDataSignature::segments($id), 'tronçon supprimé');
    }

    public function test_signature_follows_the_sncf_line_categories(): void
    {
        $id = $this->line->id;
        DB::update('UPDATE lines SET code = ? WHERE id = ?', ['999001', $id]);
        $before = LineDataSignature::segmentsEventsAndEras($id);

        // Catégorie d'une autre ligne : rien ne change.
        DB::table('sncf_line_catlig')->insert(['code_ligne' => '999002', 'catlig' => 'Ligne à grande vitesse']);
        $this->assertSame($before, LineDataSignature::segmentsEventsAndEras($id));

        // Catégorie SNCF de la ligne (lue par Lgv::sql dans les phases) : ajoutée, puis modifiée.
        DB::table('sncf_line_catlig')->insert(['code_ligne' => '999001', 'catlig' => 'Ligne à grande vitesse']);
        $this->assertNotSame($before, $added = LineDataSignature::segmentsEventsAndEras($id), 'catégorie ajoutée');
        DB::update("UPDATE sncf_line_catlig SET catlig = 'Ligne conventionnelle normale' WHERE code_ligne = '999001'");
        $this->assertNotSame($added, LineDataSignature::segmentsEventsAndEras($id), 'catégorie modifiée');
    }

    public function test_phases_are_read_once_and_recomputed_when_segments_change(): void
    {
        [$first, $q1] = $this->measure(fn () => LinePhases::forLine($this->line->fresh(), '2026-11-21'));
        [$second, $q2] = $this->measure(fn () => LinePhases::forLine($this->line->fresh(), '2026-11-21'));

        $this->assertNotEmpty($first['portions']);
        $this->assertSame($first, $second, 'même résultat lu dans le cache');
        $this->assertSame(1, $this->hits($q1, 'CREATE TEMP TABLE tmp_line_portions'));
        $this->assertSame(0, $this->hits($q2, 'CREATE TEMP TABLE tmp_line_portions'), 'second appel : rien de recalculé');

        // Le calcul du jour reste fait à chaque appel : seule la lecture en base est gardée.
        Cache::flush();
        $this->assertSame(LinePhases::forLine($this->line->fresh(), '1990-01-01'), LinePhases::forLine($this->line->fresh(), '1990-01-01'));

        DB::update("UPDATE line_segments SET status = 'abandoned' WHERE line_id = ? AND era_id = ?", [$this->line->id, $this->currentEra->id]);
        [$third, $q3] = $this->measure(fn () => LinePhases::forLine($this->line->fresh(), '2026-11-21'));
        $this->assertSame(1, $this->hits($q3, 'CREATE TEMP TABLE tmp_line_portions'), 'tronçon modifié : recalcul');
        Cache::flush();
        $this->assertSame(LinePhases::forLine($this->line->fresh(), '2026-11-21'), $third, 'identique au calcul sans cache');
    }

    public function test_terminal_ends_are_cached_until_the_segments_change(): void
    {
        $stations = app(LineStations::class);
        [$first, $q1] = $this->measure(fn () => $stations->forLine($this->line->id));
        [$second, $q2] = $this->measure(fn () => $stations->forLine($this->line->id));

        $this->assertSame($first, $second);
        $this->assertSame(1, $this->hits($q1, 'ST_DistanceSphere(a.p, b.p)'));
        $this->assertSame(0, $this->hits($q2, 'ST_DistanceSphere(a.p, b.p)'));

        DB::update('UPDATE line_segments SET geom = ST_Reverse(geom) WHERE line_id = ?', [$this->line->id]);
        [, $q3] = $this->measure(fn () => $stations->forLine($this->line->id));
        $this->assertSame(1, $this->hits($q3, 'ST_DistanceSphere(a.p, b.p)'), 'tracé modifié : recalcul');
    }

    public function test_era_lengths_are_cached_and_follow_the_segments(): void
    {
        $stats = app(LineStats::class);
        $ids = $this->line->segments()->where('era_id', $this->currentEra->id)->pluck('id')->all();
        [$first, $q1] = $this->measure(fn () => $stats->forEra($this->line->fresh(), $ids, $this->currentEra->id, 1));
        [$second, $q2] = $this->measure(fn () => $stats->forEra($this->line->fresh(), $ids, $this->currentEra->id, 1));

        $this->assertSame($first, $second);
        $this->assertSame(1, $this->hits($q1, 'AS total_len_m'));
        $this->assertSame(0, $this->hits($q2, 'AS total_len_m'));
        $this->assertGreaterThan(0, $first['length_track_km']);

        DB::update("UPDATE line_segments SET status = 'abandoned' WHERE line_id = ?", [$this->line->id]);
        [$third] = $this->measure(fn () => $stats->forEra($this->line->fresh(), $ids, $this->currentEra->id, 1));
        $this->assertSame(0.0, $third['active_length_km'], 'statut changé en SQL brut : relu');
    }
}
