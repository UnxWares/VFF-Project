<?php

namespace Tests\Feature;

use App\Models\Line;
use App\Services\SingleAxis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Nettoyage final (SingleAxis::tidy), étape 1 quater : bulle courte au bout d'une portion longue, dont un côté longe le
 * dessin d'une autre ligne (voie de cette ligne empruntée deux fois), retirée sans perdre de contact à 0 m.
 *
 * Extrait réel (tests/Fixtures/Bubble/lyon-perrache.json, lu en lecture seule sur vff le 23/11/2026) : carré de 600 m
 * à la sortie sud de Lyon-Perrache, tracés de référence de 750000 et 830000, voies BD TOPO et OSM, gares. 830000 y
 * dessine deux fois, à 3 m, 33 m de voie entre les deux mêmes nœuds : le début de sa portion de 2,4 km emprunte la
 * voie convergente que dessine 750000. Sources et licences : tests/Fixtures/SOURCES.md.
 * Réseaux fabriqués en Lambert-93 autour de (650 000, 6 860 000) : portion B d'ouest en est sur y = 0 jusqu'à Q (40, 0),
 * portion A qui part de P (0, 0), sur B, longe B à 3 m au nord et rejoint Q, puis continue vers l'est.
 */
class SingleAxisBubbleTest extends TestCase
{
    use RefreshDatabase;

    private const X0 = 650000;

    private const Y0 = 6860000;

    private function wkt(array $pts): string
    {
        return 'LINESTRING('.implode(',', array_map(fn ($p) => (self::X0 + $p[0]).' '.(self::Y0 + $p[1]), $pts)).')';
    }

    private function part(int $lineId, array $pts): void
    {
        DB::table('line_reference_parts')->insert([
            'line_id' => $lineId, 'source' => 'bdtopo', 'source_trace_ids' => '[]', 'matched_source' => null,
            'length_m' => 0, 'created_at' => now(), 'updated_at' => now(),
            'geom' => DB::raw("ST_Multi(ST_Transform(ST_GeomFromText('".$this->wkt($pts)."', 2154), 4326))"),
        ]);
    }

    private function loadFixture(string $name): array
    {
        $c = json_decode((string) file_get_contents(base_path("tests/Fixtures/Bubble/{$name}.json")), true);
        foreach ($c['lines'] as $l) {
            DB::table('lines')->insert(['id' => $l['id'], 'slug' => $l['slug'], 'name' => $l['name'], 'code' => $l['code'],
                'gauge_mm_default' => $l['gauge'], 'network' => $l['network'], 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ($c['parts'] as $p) {
            DB::insert('INSERT INTO line_reference_parts (id, line_id, source, source_trace_ids, matched_source, matched_trace_ids, length_m, geom, created_at, updated_at)
                SELECT ?, ?, ?, CAST(? AS jsonb), ?, CAST(? AS jsonb), 0, g, NOW(), NOW() FROM (SELECT ST_Multi(ST_GeomFromEWKT(?)) AS g) t WHERE NOT ST_IsEmpty(g)',
                [$p['id'], $p['line'], $p['source'], json_encode($p['ids'] ?? []), $p['matched'], $p['mids'] === null ? null : json_encode($p['mids']), $p['ewkt']]);
        }
        foreach ($c['traces'] as $t) {
            DB::insert('INSERT INTO source_traces (id, source, source_ref, layer, line_id, properties, match_score, geom, seen_at, created_at, updated_at)
                SELECT ?, ?, ?, ?, ?, CAST(? AS jsonb), ?, g, NOW(), NOW(), NOW() FROM (SELECT ST_Multi(ST_GeomFromEWKT(?)) AS g) x WHERE NOT ST_IsEmpty(g)',
                [$t['id'], $t['source'], $t['ref'], $t['layer'], $t['line'], $t['props'] ? json_encode($t['props']) : null, $t['score'], $t['ewkt']]);
        }
        foreach ($c['stations'] as $s) {
            DB::insert('INSERT INTO stations (id, slug, name, geom, created_at, updated_at) VALUES (?, ?, ?, ST_GeomFromEWKT(?), NOW(), NOW())',
                [$s['id'], 's'.$s['id'], $s['name'], $s['ewkt']]);
        }

        return $c;
    }

    /** Arêtes du dessin noué de la ligne en double (critère de l'invariant segments.duplicates : 5 m, 90 %, 10 m au moins). */
    private function bubbles(int $lineId): array
    {
        return DB::select(<<<'SQL'
            WITH e AS (SELECT row_number() OVER () AS i, d.geom AS g
                       FROM (SELECT ST_Node(ST_Collect(ST_Transform(x.geom, 2154))) AS g FROM line_reference_parts p, LATERAL ST_Dump(p.geom) x WHERE p.line_id = :l) n,
                            LATERAL ST_Dump(n.g) d)
            SELECT ROUND(ST_Length(a.g)) AS la, ROUND(ST_Length(b.g)) AS lb FROM e a JOIN e b ON b.i > a.i AND b.g && ST_Expand(a.g, 5)
            WHERE (ST_DWithin(ST_LineInterpolatePoint(a.g, 0.5), b.g, 5) OR ST_DWithin(ST_LineInterpolatePoint(b.g, 0.5), a.g, 5))
              AND LEAST(ST_Length(a.g), ST_Length(b.g)) >= 10
              AND ST_Length(ST_Intersection(a.g, ST_Buffer(b.g, 5))) > 0.9 * LEAST(ST_Length(a.g), ST_Length(b.g))
            SQL, ['l' => $lineId]);
    }

    /** Extrémités des autres lignes posées (0,5 m) sur le dessin de la ligne, et extrémités de la ligne posées sur les autres. */
    private function contacts(int $lineId): array
    {
        return array_map(fn ($r) => $r->k, DB::select(<<<'SQL'
            WITH a AS (SELECT d.geom AS g FROM line_reference_parts p, LATERAL ST_Dump(ST_Transform(p.geom, 2154)) d WHERE p.line_id = :l),
            o AS (SELECT p.line_id, d.geom AS g FROM line_reference_parts p, LATERAL ST_Dump(ST_Transform(p.geom, 2154)) d WHERE p.line_id <> :l2),
            pts AS (SELECT o.line_id, x.p FROM o, LATERAL (VALUES (ST_StartPoint(o.g)), (ST_EndPoint(o.g))) x(p)
                    UNION ALL SELECT 0, x.p FROM a, LATERAL (VALUES (ST_StartPoint(a.g)), (ST_EndPoint(a.g))) x(p))
            SELECT DISTINCT pts.line_id || ':' || ROUND(ST_X(pts.p)) || ':' || ROUND(ST_Y(pts.p)) AS k FROM pts
            WHERE (pts.line_id <> 0 AND EXISTS (SELECT 1 FROM a WHERE ST_DWithin(a.g, pts.p, 0.5)))
               OR (pts.line_id = 0 AND EXISTS (SELECT 1 FROM o WHERE ST_DWithin(o.g, pts.p, 0.5)))
            ORDER BY 1
            SQL, ['l' => $lineId, 'l2' => $lineId]));
    }

    /** Extrémités de la ligne qui ne touchent rien (ni la ligne, ni une autre). */
    private function freeEnds(int $lineId): array
    {
        return array_map(fn ($r) => $r->k, DB::select(<<<'SQL'
            WITH s AS (SELECT row_number() OVER () AS i, d.geom AS g FROM line_reference_parts p, LATERAL ST_Dump(ST_Transform(p.geom, 2154)) d WHERE p.line_id = :l),
            e AS (SELECT i, ST_StartPoint(g) AS p FROM s UNION ALL SELECT i, ST_EndPoint(g) FROM s)
            SELECT ROUND(ST_X(e.p)) || ':' || ROUND(ST_Y(e.p)) AS k FROM e
            WHERE NOT EXISTS (SELECT 1 FROM s WHERE s.i <> e.i AND ST_DWithin(s.g, e.p, 0.5))
              AND NOT EXISTS (SELECT 1 FROM line_reference_parts o WHERE o.line_id <> :l2 AND ST_DWithin(ST_Transform(o.geom, 2154), e.p, 0.5))
            ORDER BY 1
            SQL, ['l' => $lineId, 'l2' => $lineId]));
    }

    private function length(int $lineId): float
    {
        return (float) DB::selectOne('SELECT COALESCE(SUM(ST_Length(ST_Transform(geom, 2154))), 0) AS m FROM line_reference_parts WHERE line_id = ?', [$lineId])->m;
    }

    /** Distance (m) du dessin de la ligne au point (Lambert-93, relatif à X0, Y0 si $rel). */
    private function distance(int $lineId, float $x, float $y, bool $rel = true): float
    {
        return (float) DB::selectOne('SELECT MIN(ST_Distance(ST_Transform(geom, 2154), ST_SetSRID(ST_MakePoint(?, ?), 2154))) AS d FROM line_reference_parts WHERE line_id = ?',
            [$rel ? self::X0 + $x : $x, $rel ? self::Y0 + $y : $y, $lineId])->d;
    }

    /** Ligne L avec la bulle fabriquée (côté A au nord de 3 m, de longueur $span), et ligne M (sans tracé si $m vide). */
    private function network(float $span, array $m): array
    {
        $l = Line::factory()->create(['code' => '900001']);
        $o = Line::factory()->create(['code' => '900002']);
        $this->part($l->id, [[-300, 0], [$span, 0]]);
        $this->part($l->id, [[0, 0], [8, 3], [$span - 8, 3], [$span, 0], [400, 0]]);
        if ($m) {
            $this->part($o->id, $m);
        }

        return [$l->id, $o->id];
    }

    public function test_lyon_perrache_bubble_on_the_converging_track_of_750000_is_removed(): void
    {
        $this->loadFixture('lyon-perrache');
        $this->assertCount(1, $this->bubbles(830), 'bulle de 33 m présente dans l\'extrait');
        $contacts = $this->contacts(830);
        $free = $this->freeEnds(830);
        $len = $this->length(830);

        $stats = (new SingleAxis)->tidy(830);

        $this->assertSame([], $this->bubbles(830));
        $this->assertSame($contacts, $this->contacts(830), 'aucun contact à 0 m perdu ni ajouté');
        $this->assertSame($free, $this->freeEnds(830), 'aucun bout libre de plus');
        $this->assertEqualsWithDelta(33.0, $len - $this->length(830), 3.0);
        $this->assertEqualsWithDelta(33.0, $stats['bubble_m'] ?? 0, 3.0);
        // Côté gardé : la voie de 830000 (fin de la portion 114) ; côté retiré : la voie convergente, dessinée par 750000.
        $this->assertLessThan(0.1, $this->distance(830, 841668.8, 6518319.0, false));
        $this->assertGreaterThan(1.0, $this->distance(830, 841664.0, 6518319.8, false));
        $this->assertLessThan(0.5, $this->distance(626, 841664.0, 6518319.8, false));
        $this->assertSame(1, DB::table('line_reference_duplicates')->where('line_id', 830)->where('reason', 'superposition')->count());

        // Idempotent.
        $again = (new SingleAxis)->tidy(830);
        $this->assertSame(0.0, (float) ($again['bubble_m'] ?? 0));
    }

    public function test_bubble_along_another_line_loses_the_side_it_draws_and_keeps_the_junction(): void
    {
        // M dessine le côté A (voie convergente) et finit en Q.
        [$l, $o] = $this->network(40, [[-150, 60], [8, 3], [32, 3], [40, 0]]);
        $this->assertCount(1, $this->bubbles($l));

        (new SingleAxis)->tidy($l);

        $this->assertSame([], $this->bubbles($l));
        $this->assertLessThan(0.01, $this->distance($l, 20, 0), 'côté B gardé');
        $this->assertGreaterThan(2.5, $this->distance($l, 20, 3), 'côté A retiré');
        $this->assertLessThan(0.5, $this->distance($l, 40, 0), 'M touche encore L en Q');
        $this->assertSame(['649700:6860000', '650400:6860000'], $this->freeEnds($l));
    }

    public function test_bubble_whose_track_side_is_drawn_by_another_line_loses_the_tail_of_b(): void
    {
        // M dessine le côté B (sur y = 0) et finit en Q : la fin de B part, B finit en P sur A.
        [$l] = $this->network(40, [[-150, -50], [-10, -1], [0, 0], [40, 0]]);

        $stats = (new SingleAxis)->tidy($l);

        $this->assertSame([], $this->bubbles($l));
        $this->assertLessThan(0.01, $this->distance($l, 20, 3), 'côté A gardé');
        $this->assertGreaterThan(2.5, $this->distance($l, 20, 0), 'fin de B retirée');
        $this->assertEqualsWithDelta(40.0, $stats['bubble_m'] ?? 0, 0.5);
        $this->assertSame(['649700:6860000', '650400:6860000'], $this->freeEnds($l));
    }

    public function test_bubble_without_another_line_is_left_to_the_other_steps(): void
    {
        [$l] = $this->network(40, []);
        $len = $this->length($l);

        (new SingleAxis)->tidy($l);

        $this->assertCount(1, $this->bubbles($l));
        $this->assertEqualsWithDelta($len, $this->length($l), 0.01);
    }

    public function test_bubble_side_holding_the_end_of_another_line_stays(): void
    {
        // M dessine le côté A, et une troisième ligne N finit en son milieu, à 3 m de B : le retirer laisserait N détachée.
        [$l] = $this->network(40, [[-150, 60], [8, 3], [32, 3], [40, 0]]);
        $n = Line::factory()->create(['code' => '900003']);
        $this->part($n->id, [[20, 80], [20, 3]]);
        $len = $this->length($l);

        (new SingleAxis)->tidy($l);

        $this->assertCount(1, $this->bubbles($l));
        $this->assertEqualsWithDelta($len, $this->length($l), 0.01);
        $this->assertLessThan(0.5, $this->distance($l, 20, 3), 'N touche encore L');
    }

    public function test_bubble_longer_than_a_short_run_stays(): void
    {
        [$l] = $this->network(150, [[-150, 60], [8, 3], [142, 3], [150, 0]]);
        $len = $this->length($l);

        (new SingleAxis)->tidy($l);

        $this->assertEqualsWithDelta($len, $this->length($l), 0.01);
    }
}
