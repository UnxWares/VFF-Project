<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Importe le statut effectif des gares depuis SNCF `frequentation-gares`.
 *
 * Règles :
 *   - Gare avec total_voyageurs_2024 > 0  → station_eras.status = 'active'
 *   - Gare présente dans le dataset mais fréquentation nulle sur 3 dernières
 *     années → 'no_commercial' (gare encore référencée mais sans circulation
 *     voyageurs régulière)
 *   - Gares absentes du dataset → non touchées (peuvent être fret-seul ou
 *     historiques, à curer manuellement)
 *
 * Matching : nom exact normalisé (lowercase + sans accents + sans tirets
 * superflus).
 */
class ImportSncfStationStatus extends Command
{
    protected $signature = 'vff:import-sncf-station-status {--era=}';

    protected $description = 'Importe le statut voyageurs des gares depuis SNCF frequentation-gares.';

    private const SNCF_DATASET = 'frequentation-gares';

    public function handle(): int
    {
        $eraId = $this->option('era')
            ? DB::table('eras')->where('year', $this->option('era'))->value('id')
            : DB::table('eras')->where('is_current', true)->value('id');

        // Ère inconnue (--era=1855) ou aucune ère courante : erreur propre, aucune écriture.
        if (! $eraId) {
            $this->error('Ère introuvable (option --era ou ère courante).');

            return self::FAILURE;
        }

        $this->info("Import statut gares (era_id={$eraId})…");

        // Préparer index des stations VFF par nom normalisé
        $this->info('  → Indexation stations VFF…');
        $byName = [];
        DB::table('stations')->select(['id', 'name', 'city'])->orderBy('id')
            ->chunk(1000, function ($chunk) use (&$byName) {
                foreach ($chunk as $s) {
                    $key = $this->normalize($s->name);
                    $byName[$key][] = ['id' => $s->id, 'city' => $s->city];
                }
            });
        $this->info('    '.count($byName).' noms normalisés indexés');

        // Fetch + apply
        $this->info('  → Fetch + application…');
        $active = 0;
        $passive = 0;
        $unmatched = 0;
        $ambiguous = 0;
        $offset = 0;
        $limit = 100;
        do {
            $response = Http::timeout(60)
                ->get('https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/'.self::SNCF_DATASET.'/records',
                    ['limit' => $limit, 'offset' => $offset]);
            if (! $response->successful()) {
                $this->error("SNCF HTTP {$response->status()} (offset {$offset}) : import interrompu.");

                return self::FAILURE;
            }
            $records = $response->json('results') ?? [];
            if (! $records) {
                break;
            }

            foreach ($records as $r) {
                $name = $r['nom_gare'] ?? null;
                if (! $name) {
                    continue;
                }
                $key = $this->normalize($name);
                $candidates = $byName[$key] ?? [];
                if (empty($candidates)) {
                    $unmatched++;

                    continue;
                }

                // Désambiguer par fuzzy match si plusieurs
                $stationId = count($candidates) === 1
                    ? $candidates[0]['id']
                    : $this->disambiguate($candidates, $r);
                if (! $stationId) {
                    $ambiguous++;

                    continue;
                }

                $f2024 = (int) ($r['total_voyageurs_2024'] ?? 0);
                $f2023 = (int) ($r['total_voyageurs_2023'] ?? 0);
                $f2022 = (int) ($r['total_voyageurs_2022'] ?? 0);
                $status = ($f2024 > 0 || $f2023 > 0 || $f2022 > 0) ? 'active' : 'no_commercial';

                DB::table('station_eras')->updateOrInsert(
                    ['station_id' => $stationId, 'era_id' => $eraId],
                    [
                        'status' => $status,
                        'note' => "Source: SNCF frequentation-gares 2024 ({$f2024} voyageurs)",
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
                $status === 'active' ? $active++ : $passive++;
            }
            $offset += $limit;
        } while (count($records) === $limit && $offset < 5000);

        $this->info("    ✓ {$active} actives, {$passive} no_commercial, {$unmatched} non trouvées, {$ambiguous} ambiguës");

        return self::SUCCESS;
    }

    private function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = preg_replace('/[^\w\s-]/u', '', $name);

        return $name;
    }

    /**
     * Choisit la station candidate quand plusieurs partagent le même nom
     * normalisé : celle du département du code postal SNCF. Sans code postal,
     * ou si aucune (ou plusieurs) gare de ce département : ambiguë (null), pour
     * ne jamais attribuer la fréquentation d'une gare à un homonyme.
     * (Avant : le département était cherché dans le NOM de la ville, ce qui
     * n'arrivait jamais, et le premier homonyme était toujours retenu.)
     */
    private function disambiguate(array $candidates, array $sncfRecord): ?int
    {
        $sncfPostal = (string) ($sncfRecord['code_postal'] ?? '');
        if (strlen($sncfPostal) < 2) {
            return null;
        }
        $dept = substr($sncfPostal, 0, 2);
        $matches = DB::table('stations')->whereIn('id', array_column($candidates, 'id'))
            ->where('department_code', $dept)->pluck('id');

        return $matches->count() === 1 ? (int) $matches->first() : null;
    }
}
