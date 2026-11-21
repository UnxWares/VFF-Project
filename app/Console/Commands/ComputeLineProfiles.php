<?php

namespace App\Console\Commands;

use App\Models\Line;
use App\Models\LineProfile;
use App\Services\Profiles\GeoplateformeClient;
use App\Services\Profiles\LineProfileBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Profils en long des lignes (tables `line_profiles` et `line_profile_points`), le long du tracé de
 * référence de chaque ligne (toutes époques), d'après la BD TOPO et le RGE ALTI de l'IGN (Licence
 * Ouverte 2.0). Requêtes polies : une à la fois, une seconde de pause après chacune, réponses
 * gardées par tuile fixe dans storage/app/data/altimetrie/ (dossier .nobackup).
 *
 *   php artisan vff:compute-line-profiles --line=rfn-900000 --line=rfn-420000
 *   php artisan vff:compute-line-profiles --limit=20            # lignes sans profil, les plus longues d'abord
 *   php artisan vff:compute-line-profiles --all --force         # toutes les lignes qui ont un tracé
 *   php artisan vff:compute-line-profiles --line=rfn-5000 --dry-run   # parcours et nombre de points, sans appel
 *   php artisan vff:compute-line-profiles --all --dry-run --report=storage/app/x.csv   # couverture, sans appel
 *   php artisan vff:compute-line-profiles --all --prefill-tiles # remplit le cache de la BD TOPO, sans calcul
 *   php artisan vff:compute-line-profiles --all --stats-only    # chiffres recalculés depuis line_profile_points
 *
 * Une ligne déjà calculée (avec --force ou --line) dont le profil ne peut plus l'être (tracé
 * retiré, aucune altitude) perd son ancien profil, et la sortie le signale. Le calcul s'arrête
 * proprement si le cache dépasse --max-cache-mb.
 */
class ComputeLineProfiles extends Command
{
    protected $signature = 'vff:compute-line-profiles
        {--line=* : Slug d\'une ligne (option répétable)}
        {--limit=10 : Nombre de lignes au plus sans --line (lignes sans profil, les plus longues d\'abord)}
        {--all : Toutes les lignes qui ont un tracé (sans limite)}
        {--force : Recalculer aussi les lignes qui ont déjà un profil}
        {--dry-run : Afficher le parcours et le nombre de points sans appeler l\'IGN ni écrire}
        {--report= : Avec --dry-run, fichier CSV de la couverture de chaque ligne}
        {--prefill-tiles : Remplir le cache des tuiles de la BD TOPO des lignes choisies, sans calculer}
        {--stats-only : Recalculer seulement les chiffres (déclivité, dénivelés) à partir des points déjà en base, sans appeler l\'IGN}
        {--max-cache-mb=4096 : Taille du cache au-delà de laquelle la commande s\'arrête}';

    protected $description = 'Calcule le profil en long des lignes sur leur tracé de référence (BD TOPO et RGE ALTI de l\'IGN).';

    public function handle(LineProfileBuilder $builder, GeoplateformeClient $client): int
    {
        $lines = $this->lines();
        if ($lines === []) {
            $this->warn('Aucune ligne à traiter.');

            return self::SUCCESS;
        }
        if ($this->option('prefill-tiles')) {
            return $this->prefill($builder, $client, $lines);
        }
        if ($this->option('stats-only')) {
            $withheld = 0;
            foreach ($lines as $line) {
                $profile = $builder->recomputeStats($line);
                $withheld += $profile?->max_grade_withheld !== null ? 1 : 0;
            }
            $this->info(sprintf('Chiffres recalculés pour %d ligne(s), déclivité non publiée pour %d.', count($lines), $withheld));

            return self::SUCCESS;
        }

        $report = $this->option('dry-run') && $this->option('report') ? fopen((string) $this->option('report'), 'w') : null;
        if ($report !== null && $report !== false) {
            fputcsv($report, ['slug', 'reference_m', 'path_m', 'covered_m', 'off_path_m', 'gaps', 'points'], escape: '');
        }

        $failures = 0;
        foreach ($lines as $n => $line) {
            $label = "{$line->slug} ({$line->name})";
            try {
                if ($this->option('dry-run')) {
                    $plan = $builder->plan($line);
                    if (is_resource($report) && $plan !== null) {
                        fputcsv($report, [$line->slug, (int) $plan['trace_length_m'], (int) $plan['path']->length(), (int) $plan['covered_m'],
                            (int) $plan['off_path_m'], count($plan['path']->gaps), count($plan['samples'])], escape: '');
                    }
                    $this->line($plan === null
                        ? "{$label} : aucun tracé"
                        : sprintf('%s : parcours %.1f km (%.1f km mesurés) sur %.1f km de référentiel, %.1f km hors parcours, %d trou(s), pas %d m, %d points',
                            $label, $plan['path']->length() / 1000, $plan['covered_m'] / 1000, $plan['trace_length_m'] / 1000,
                            $plan['off_path_m'] / 1000, count($plan['path']->gaps), $plan['step'], count($plan['samples'])));

                    continue;
                }
                if ($n % 25 === 0 && $this->cacheTooLarge($client)) {
                    return self::FAILURE;
                }
                $had = LineProfile::query()->where('line_id', $line->id)->exists();
                $profile = $builder->compute($line);
                if ($profile === null) {
                    $why = match ($builder->skipReason()) {
                        'no_trace' => 'aucun tracé',
                        'outside_france' => 'tracé entièrement hors de France (aucune altitude de l\'IGN)',
                        default => 'aucune altitude',
                    };
                    $this->warn("{$label} : {$why}, profil non calculé"
                        .($had ? ', ancien profil supprimé (il ne correspondait plus au tracé)' : ''));

                    continue;
                }
                $o = $profile->altitude_sources;
                $this->info(sprintf('%s : %.1f km (%.1f km mesurés sur %.1f km de référentiel, %d trou(s)), %d à %d m, +%d m / -%d m, déclivité max %s, %d ouvrage(s), %d gare(s), PK calé sur %d ; points BD TOPO %d, RGE ALTI %d, interpolés %d, sans altitude %d',
                    $label, $profile->length_m / 1000, (int) $profile->covered_m / 1000, $profile->trace_length_m / 1000, count($profile->gaps),
                    $profile->min_altitude_m, $profile->max_altitude_m, $profile->ascent_m, $profile->descent_m, $profile->max_grade_permil === null ? 'non publiée ('.$profile->max_grade_withheld.')' : sprintf('%.1f ‰', $profile->max_grade_permil),
                    count($profile->structures), count($profile->stations), $profile->pk_anchors,
                    $o['bdtopo'], $o['rge_alti'], $o['interpolated'], $o['missing']));
            } catch (Throwable $e) {
                $failures++;
                $this->error("{$label} : {$e->getMessage()}");
            }
        }
        if (is_resource($report)) {
            fclose($report);
        }

        if (! $this->option('dry-run')) {
            $this->line("Appels aux services de l'IGN : {$builder->networkCalls()}");
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Tuiles de la BD TOPO des lignes choisies, demandées une à une si elles manquent au cache.
     *
     * @param  list<Line>  $lines
     */
    private function prefill(LineProfileBuilder $builder, GeoplateformeClient $client, array $lines): int
    {
        $tiles = [];
        foreach ($lines as $line) {
            foreach ($builder->tilesFor($line) as [$x, $y]) {
                $tiles["{$x}_{$y}"] = [$x, $y];
            }
        }
        $missing = array_values(array_filter($tiles, fn (array $t): bool => ! $client->hasTile($t[0], $t[1])));
        $this->info(sprintf('%d tuile(s) pour %d ligne(s), %d à demander.', count($tiles), count($lines), count($missing)));
        foreach ($missing as $k => [$x, $y]) {
            if ($k % 50 === 0) {
                if ($this->cacheTooLarge($client)) {
                    return self::FAILURE;
                }
                $this->line(sprintf('%d / %d tuiles, cache %d Mo', $k, count($missing), $this->cacheMb($client)));
            }
            try {
                $client->railTracksInTile($x, $y);
            } catch (Throwable $e) {
                $this->error("Tuile {$x}_{$y} : {$e->getMessage()}");
            }
        }
        $this->info(sprintf('Cache rempli : %d Mo, %d appel(s) à l\'IGN.', $this->cacheMb($client), $client->networkCalls()));

        return self::SUCCESS;
    }

    private function cacheTooLarge(GeoplateformeClient $client): bool
    {
        $mb = $this->cacheMb($client);
        if ($mb > (int) $this->option('max-cache-mb')) {
            $this->error("Cache de {$mb} Mo, au-delà de --max-cache-mb : arrêt.");

            return true;
        }

        return false;
    }

    /** Taille du cache sur disque, en Mo (parcours non récursif des sous-dossiers connus). */
    private function cacheMb(GeoplateformeClient $client): int
    {
        $bytes = 0;
        foreach (['bdtopo-tuiles', 'rge-alti-noeuds'] as $kind) {
            foreach (glob($client->cacheDir().'/'.$kind.'/*.json.gz') ?: [] as $file) {
                $bytes += (int) filesize($file);
            }
        }

        return intdiv($bytes, 1024 * 1024);
    }

    /** @return list<Line> */
    private function lines(): array
    {
        $slugs = array_values(array_filter((array) $this->option('line')));
        if ($slugs !== []) {
            $found = Line::query()->whereIn('slug', $slugs)->get()->keyBy('slug');
            foreach (array_diff($slugs, $found->keys()->all()) as $missing) {
                $this->error("Ligne inconnue : {$missing}");
            }

            return array_values(array_filter(array_map(fn (string $s): ?Line => $found->get($s), $slugs)));
        }

        // Lignes qui ont un tracé de référence (à défaut, des tronçons), les plus longues d'abord.
        $lengths = DB::table('line_reference_parts')->selectRaw('line_id, SUM(length_m) AS m')->groupBy('line_id')
            ->unionAll(
                DB::table('line_segments')->selectRaw('line_id, 0 AS m')
                    ->where('status', '<>', 'service_track')
                    ->whereNotIn('line_id', DB::table('line_reference_parts')->select('line_id'))
                    ->groupBy('line_id'),
            );
        $query = Line::query()
            ->select('lines.*')
            ->joinSub($lengths, 'len', 'len.line_id', '=', 'lines.id')
            ->orderByDesc('len.m')
            ->orderBy('lines.id');
        if (! $this->option('all')) {
            $query->limit(max(1, (int) $this->option('limit')));
        }
        if ($this->option('stats-only')) {
            $query->whereIn('lines.id', LineProfile::query()->select('line_id'));
        } elseif (! $this->option('force') && ! $this->option('prefill-tiles') && ! $this->option('dry-run')) {
            $query->whereNotIn('lines.id', LineProfile::query()->select('line_id'));
        }

        return $query->get()->unique('id')->values()->all();
    }
}
