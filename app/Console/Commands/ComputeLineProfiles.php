<?php

namespace App\Console\Commands;

use App\Models\Line;
use App\Models\LineProfile;
use App\Services\Profiles\LineProfileBuilder;
use Illuminate\Console\Command;
use Throwable;

/**
 * Profils en long des lignes (table `line_profiles`), d'après le RGE ALTI et la BD TOPO de l'IGN
 * (Licence Ouverte 2.0). Requêtes polies : une à la fois, une seconde de pause après chacune,
 * réponses gardées dans storage/app/data/altimetrie/ (dossier .nobackup).
 *
 *   php artisan vff:compute-line-profiles --line=rfn-900000 --line=rfn-420000
 *   php artisan vff:compute-line-profiles --limit=20            # lignes sans profil, les plus longues d'abord
 *   php artisan vff:compute-line-profiles --line=rfn-5000 --dry-run   # parcours et nombre de points, sans appel
 *
 * Une ligne déjà calculée (avec --force ou --line) dont le profil ne peut plus l'être (tracé
 * retiré, aucune altitude) perd son ancien profil, et la sortie le signale.
 *
 * Coût : une requête d'altitude pour 2 000 points, une requête WFS par tranche de 10 km. Une
 * ligne de 600 km : 2 requêtes d'altitude et une soixantaine de requêtes WFS, environ une minute.
 */
class ComputeLineProfiles extends Command
{
    protected $signature = 'vff:compute-line-profiles
        {--line=* : Slug d\'une ligne (option répétable)}
        {--limit=10 : Nombre de lignes au plus sans --line (lignes sans profil, les plus longues d\'abord)}
        {--force : Recalculer aussi les lignes qui ont déjà un profil}
        {--dry-run : Afficher le parcours et le nombre de points sans appeler l\'IGN ni écrire}';

    protected $description = 'Calcule le profil en long des lignes (altitudes RGE ALTI de l\'IGN, ouvrages BD TOPO).';

    public function handle(LineProfileBuilder $builder): int
    {
        $lines = $this->lines();
        if ($lines === []) {
            $this->warn('Aucune ligne à traiter.');

            return self::SUCCESS;
        }

        $failures = 0;
        foreach ($lines as $line) {
            $label = "{$line->slug} ({$line->name})";
            try {
                if ($this->option('dry-run')) {
                    $plan = $builder->plan($line);
                    $this->line($plan === null
                        ? "{$label} : aucun tracé"
                        : sprintf('%s : ère %d, parcours %.1f km sur %.1f km de tracé, %d lacune(s), pas %d m, %d points',
                            $label, $plan['era']->year, $plan['path']->length() / 1000, $plan['trace_length_m'] / 1000,
                            count($plan['path']->gaps), $plan['step'], count($plan['samples'])));

                    continue;
                }
                $had = LineProfile::query()->where('line_id', $line->id)->exists();
                $profile = $builder->compute($line);
                if ($profile === null) {
                    $this->warn("{$label} : aucun tracé ou aucune altitude, profil non calculé"
                        .($had ? ', ancien profil supprimé (il ne correspondait plus au tracé)' : ''));

                    continue;
                }
                $o = $profile->altitude_sources;
                $this->info(sprintf('%s : %.1f km, %d à %d m, +%d m / -%d m, déclivité max %.1f ‰, %d ouvrage(s), %d gare(s) ; points BD TOPO %d, RGE ALTI %d, interpolés %d, sans altitude %d',
                    $label, $profile->length_m / 1000, $profile->min_altitude_m, $profile->max_altitude_m,
                    $profile->ascent_m, $profile->descent_m, $profile->max_grade_permil,
                    count($profile->structures), count($profile->stations),
                    $o['bdtopo'], $o['rge_alti'], $o['interpolated'], $o['missing']));
            } catch (Throwable $e) {
                $failures++;
                $this->error("{$label} : {$e->getMessage()}");
            }
        }

        if (! $this->option('dry-run')) {
            $this->line("Appels aux services de l'IGN : {$builder->networkCalls()}");
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
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

        // Lignes de l'ère courante, les plus longues d'abord, sans profil (sauf --force).
        $query = Line::query()
            ->select('lines.*')
            ->join('line_segments', 'line_segments.line_id', '=', 'lines.id')
            ->join('eras', fn ($j) => $j->on('eras.id', '=', 'line_segments.era_id')->where('eras.is_current', true))
            ->where('line_segments.status', '<>', 'service_track')
            ->groupBy('lines.id')
            ->orderByRaw('SUM(ST_Length(line_segments.geom::geography)) DESC')
            ->limit(max(1, (int) $this->option('limit')));
        if (! $this->option('force')) {
            $query->whereNotIn('lines.id', LineProfile::query()->select('line_id'));
        }

        return $query->get()->all();
    }
}
