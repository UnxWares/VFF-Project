<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Remplit `commune_rail` : les communes actuelles où se trouve une gare ou une halte, ou que traverse ou traversait
 * une ligne (50 m au moins de voie dans la commune, à l'une des ères). Une commune qui n'a plus de rail est retirée.
 * Lit les contours (`commune_contours`, 100 m de simplification), les gares et les portions de ligne ; n'écrit que
 * dans `commune_rail`. Une ère à la fois (une requête de quelques secondes chacune).
 *
 *   php artisan vff:build-commune-rail
 *   php artisan vff:build-commune-rail --dry-run   # compte sans écrire
 *
 * À relancer après un import de gares ou de portions de ligne ; la recherche et le plan du site relisent la table
 * au plus tard au bout d'un jour (cache).
 */
class BuildCommuneRail extends Command
{
    protected $signature = 'vff:build-commune-rail {--dry-run : Compte les communes sans écrire}';

    protected $description = 'Liste les communes qui ont une gare ou une ligne (fiches « Le rail à … »)';

    public function handle(): int
    {
        $counts = [];
        $stations = DB::table('stations as s')
            ->join('commune_contours as c', fn ($j) => $j->whereRaw('ST_Contains(c.geom, s.geom)'))
            ->whereRaw("COALESCE(s.provenance->>'kind', '') NOT IN ('technical', 'excluded')")
            ->groupBy('c.code_insee')->selectRaw('c.code_insee, COUNT(*) AS n')->pluck('n', 'code_insee');
        foreach ($stations as $insee => $n) {
            $counts[(string) $insee] = ['stations' => (int) $n, 'lines' => []];
        }
        $this->line(sprintf('%d communes ont une gare.', count($counts)));

        foreach (DB::table('eras')->orderBy('year')->pluck('id', 'year') as $year => $eraId) {
            // ST_MakeValid : une vingtaine de contours ont une auto-intersection, qui fait échouer ST_Intersection.
            $pairs = DB::select(<<<'SQL'
                SELECT c.code_insee, g.line_id
                FROM line_segments g
                JOIN commune_contours c ON g.geom && c.geom AND ST_Intersects(g.geom, c.geom)
                WHERE g.era_id = ?
                GROUP BY c.code_insee, g.line_id
                HAVING SUM(ST_Length(ST_Intersection(g.geom, ST_MakeValid(c.geom))::geography)) >= 50
            SQL, [$eraId]);
            foreach ($pairs as $p) {
                $counts[(string) $p->code_insee] ??= ['stations' => 0, 'lines' => []];
                $counts[(string) $p->code_insee]['lines'][(int) $p->line_id] = true;
            }
            $this->line(sprintf('Ère %d : %d communes en tout.', $year, count($counts)));
        }
        $this->info(sprintf('%d communes ont du rail.', count($counts)));
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $builtAt = Carbon::now();
        foreach (array_chunk(array_keys($counts), 1000) as $codes) {
            DB::table('commune_rail')->upsert(
                array_map(fn (string $insee): array => [
                    'code_insee' => $insee,
                    'stations_count' => $counts[$insee]['stations'],
                    'lines_count' => count($counts[$insee]['lines']),
                    'built_at' => $builtAt,
                ], $codes),
                ['code_insee'],
                ['stations_count', 'lines_count', 'built_at'],
            );
        }
        // Les communes qui n'ont plus de rail ne sont pas dans cette passe : leur ligne date d'avant.
        DB::table('commune_rail')->where('built_at', '<', $builtAt)->delete();
        Cache::forget('communes:rail:table');
        $this->info('commune_rail à jour.');

        return self::SUCCESS;
    }
}
