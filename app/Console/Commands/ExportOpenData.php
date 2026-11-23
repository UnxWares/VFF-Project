<?php

namespace App\Console\Commands;

use App\Models\Era;
use App\Services\Lines\NeutralLineSlugs;
use App\Services\OpenData\OpenDataCatalogue;
use App\Services\OpenData\OpenDataExporter;
use App\Services\OpenData\OpenDataLedger;
use App\Services\OpenData\OpenDataLicenceViolation;
use App\Services\OpenData\OpenDataPackage;
use App\Services\OpenData\OpenDataRights;
use App\Support\SourceRights;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Jeux de données ouverts de VFF, sous ODbL 1.0 : réseau d'une ère (lignes, sections, gares en CSV et
 * GeoJSON), événements datés, accidents publiés et matériel roulant, chacun avec LISEZMOI.txt,
 * LICENCE.txt et une archive ZIP, et le catalogue lu par la page « Données ouvertes » (/open-data), fichiers servis en statique sous /downloads/open-data/.
 *
 * Lecture seule de la base. Lancée à la main (pas de tâche planifiée). Seules les sources autorisées
 * par le registre des droits entrent (OpenDataRights) ; le reste est retiré ou remplacé et compté.
 *
 *   php artisan vff:export-open-data                      # ère courante + jeux thématiques
 *   php artisan vff:export-open-data --all-eras           # toutes les ères
 *   php artisan vff:export-open-data --era=1930 --only=network
 *   php artisan vff:export-open-data --era=1930 --lines=rfn-420000,rfn-610000   # échantillon
 *
 * Idempotente : même base et même --date donnent les mêmes fichiers, à l'octet près.
 */
class ExportOpenData extends Command
{
    protected $signature = 'vff:export-open-data
        {--era=* : Ère(s) du réseau (années de décennie) ; défaut : l\'ère courante}
        {--all-eras : Réseau de toutes les ères}
        {--only=* : Jeux à produire parmi network, events, accidents, materials (défaut : tous)}
        {--lines= : Échantillon : slugs de lignes séparés par des virgules (écrit hors du dossier public)}
        {--output= : Dossier de sortie (défaut : public/downloads/open-data, ou storage/app/open-data-sample pour un échantillon)}
        {--date= : Date du jeu (AAAA-MM-JJ, défaut : aujourd\'hui)}';

    protected $description = 'Produit les jeux de données ouverts (ODbL) : réseau par ère, événements, accidents, matériel (lecture seule).';

    public const KINDS = ['network', 'events', 'accidents', 'materials'];

    public function handle(): int
    {
        $only = array_values(array_filter((array) $this->option('only'))) ?: self::KINDS;
        if ($unknown = array_diff($only, self::KINDS)) {
            $this->error('Jeu inconnu : '.implode(', ', $unknown).'. Choix : '.implode(', ', self::KINDS).'.');

            return self::INVALID;
        }
        $date = (string) ($this->option('date') ?: now('Europe/Paris')->toDateString());
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $this->error('Date attendue au format AAAA-MM-JJ.');

            return self::INVALID;
        }

        $lineIds = null;
        if (is_string($slugs = $this->option('lines')) && $slugs !== '') {
            $wanted = array_values(array_filter(array_map('trim', explode(',', $slugs))));
            $lineIds = DB::table('lines')->whereIn('slug', $wanted)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (count($lineIds) !== count($wanted)) {
                $this->error('Ligne(s) inconnue(s) dans --lines.');

                return self::INVALID;
            }
        }
        $root = (string) ($this->option('output') ?: ($lineIds !== null ? storage_path('app/open-data-sample') : OpenDataCatalogue::publicRoot()));
        $norm = fn (string $p): string => realpath($p) ?: rtrim($p, '/');
        if ($lineIds !== null && $norm($root) === $norm(OpenDataCatalogue::publicRoot())) {
            $this->error('Un échantillon (--lines) ne s\'écrit pas dans le dossier public.');

            return self::INVALID;
        }

        $eras = collect();
        if (in_array('network', $only, true)) {
            $years = array_map('intval', array_filter((array) $this->option('era')));
            $eras = $this->option('all-eras') ? Era::orderBy('year')->get()
                : ($years !== [] ? Era::whereIn('year', $years)->orderBy('year')->get()
                    : Era::where('is_current', true)->get());
            if ($years !== [] && $eras->count() !== count(array_unique($years))) {
                $this->error('Ère(s) inconnue(s) dans --era.');

                return self::INVALID;
            }
        }

        // Un slug à préfixe de source (« archeo-… ») nommerait dans les fichiers une source qui n'y entre pas.
        if (DB::table('lines')->where('slug', 'like', NeutralLineSlugs::PREFIX.'%')->exists()) {
            $this->error('Slugs de ligne à préfixe de source : lancer d\'abord php artisan vff:neutralize-line-slugs.');

            return self::FAILURE;
        }

        $rights = new OpenDataRights(new SourceRights);
        $exporter = new OpenDataExporter($rights);
        $package = new OpenDataPackage($rights);
        $this->ensureDir($root);
        // Fichiers régénérables : hors des sauvegardes (convention .nobackup).
        if (! is_file("{$root}/.nobackup")) {
            touch("{$root}/.nobackup");
        }

        $jobs = [];
        foreach ($eras as $era) {
            $year = (int) $era->year;
            $jobs[] = [['id' => "reseau-{$year}", 'kind' => 'network', 'era' => $year,
                'title_fr' => $era->is_current ? "réseau au {$date}" : "réseau de la décennie {$year}",
                'title_en' => $era->is_current ? "network on {$date}" : "network of the {$year}s"],
                fn (string $dir, OpenDataLedger $l) => $exporter->network($era, $dir, $l, $lineIds)];
        }
        foreach ([
            'events' => ['evenements', 'événements datés des lignes', 'dated line events', $exporter->events(...)],
            'accidents' => ['accidents', 'accidents publiés', 'published accidents', $exporter->accidents(...)],
            'materials' => ['materiel', 'matériel roulant', 'rolling stock', $exporter->materials(...)],
        ] as $kind => [$id, $fr, $en, $run]) {
            if (in_array($kind, $only, true)) {
                $jobs[] = [['id' => $id, 'kind' => $kind, 'era' => null, 'title_fr' => $fr, 'title_en' => $en],
                    fn (string $dir, OpenDataLedger $l) => $run($dir, $l, $lineIds)];
            }
        }

        $entries = [];
        foreach ($jobs as [$meta, $run]) {
            $dir = "{$root}/{$meta['id']}";
            $this->ensureDir($dir);
            $ledger = new OpenDataLedger;
            $started = microtime(true);
            $run($dir, $ledger);
            try {
                $entry = $package->finish($root, $meta, $ledger, $date);
            } catch (OpenDataLicenceViolation $e) {
                $this->error($e->getMessage().' Catalogue non mis à jour.');

                return self::FAILURE;
            }
            $entries[] = $entry;
            $this->line(sprintf('%s : %s, %s Ko, %d retiré(s), %d remplacé(s), %.1f s', $meta['id'],
                implode(', ', array_map(fn ($f, $n) => "{$f} {$n}", array_keys($ledger->rows()), $ledger->rows())),
                number_format($entry['archive']['bytes'] / 1024, 0, ',', ' '), $entry['removed'], $entry['replaced'], microtime(true) - $started));
        }
        OpenDataCatalogue::merge($root, $entries);
        $this->info(count($entries)." jeu(x) écrit(s) dans {$root}.");

        return self::SUCCESS;
    }

    private function ensureDir(string $dir): void
    {
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Impossible de créer {$dir}.");
        }
    }
}
