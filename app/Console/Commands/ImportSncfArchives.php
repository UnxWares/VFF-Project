<?php

namespace App\Console\Commands;

use App\Services\Archives\ArchiveMatcher;
use App\Services\Archives\CatalogueSruClient;
use App\Services\Archives\LinePlaceIndex;
use App\Services\Archives\Sncf\SncfArchivesImporter;
use App\Support\CsvSafe;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Notices du jeu de données « Archives SNCF » (Service Archives Documentation du groupe SNCF, SARDO) rattachées
 * aux lignes → table line_archive_records (section « Dans les archives » de la fiche ligne,
 * GET /api/v1/lines/{slug}/archive-records).
 *
 * Le jeu de données (12 447 notices de dossiers, photographies et films numérisés, ODbL) est lu dans sa copie
 * locale (storage/app/data/archives-sncf/archives-sncf-sardo.json, dossier marqué `.nobackup`) ; `--download`
 * la remplace par l'export de ressources.data.sncf.com (une seule requête, User-Agent générique). Seuls le
 * titre, le type, le classement, la boîte, la date et le lien vers archives.sncf.com sont gardés : ni la
 * description, ni l'image, ni le document. La description (champ du jeu entier, ODbL) n'est lue que pour les
 * notices dont le titre ne nomme aucune ligne (SncfArchivesImporter), jamais enregistrée. N'écrit dans aucune table du moteur.
 *
 *   vff:import-sncf-archives --download --dry-run --report=storage/app/data/archives-sncf/candidats.csv
 *   vff:import-sncf-archives
 */
class ImportSncfArchives extends Command
{
    public const EXPORT_URL = 'https://ressources.data.sncf.com/api/explore/v2.1/catalog/datasets/archives-sncf-sardo/exports/json';

    protected $signature = 'vff:import-sncf-archives
        {--download : Télécharge l\'export du jeu de données (sinon la copie locale est lue)}
        {--file= : Fichier JSON à lire à la place de la copie locale}
        {--threshold= : Score minimal d\'une notice rattachée (9 par défaut, sur 11)}
        {--dry-run : Calcule tout sans écrire en base}
        {--report= : Fichier CSV de toutes les notices jugées, retenues ou non, avec leur score et ses raisons}';

    protected $description = 'Rattache aux lignes les notices des Archives SNCF qui les nomment (métadonnées ODbL et lien, jamais l\'image)';

    public function handle(SncfArchivesImporter $importer): int
    {
        $threshold = $this->option('threshold') === null ? ArchiveMatcher::THRESHOLD : (int) $this->option('threshold');
        if ($threshold < 1 || $threshold > ArchiveMatcher::MAX_SCORE) {
            $this->error('Seuil invalide : de 1 à 11.');

            return self::INVALID;
        }

        $path = $this->option('file') !== null ? (string) $this->option('file') : self::localCopy();
        if ($this->option('download') && ! $this->download($path)) {
            return self::FAILURE;
        }
        if (! is_file($path)) {
            $this->error("Jeu de données absent : {$path} (relancez avec --download).");

            return self::FAILURE;
        }
        $rows = json_decode((string) File::get($path), true);
        if (! is_array($rows) || ! array_is_list($rows)) {
            $this->error("Fichier illisible : {$path} (liste JSON de notices attendue).");

            return self::FAILURE;
        }
        /** @var list<array<string, mixed>> $rows */
        $rows = array_values(array_filter($rows, 'is_array'));

        $result = $importer->run(LinePlaceIndex::load(), $rows, $threshold);
        $s = $result['stats'];
        $this->info(sprintf(
            'Notices : %d ; lues : %d (%d écartées : sans titre ni lien, type inconnu ou thème sur des personnes) ; '
            .'jugées : %d ; retenues : %d (dont %d par la description) sur %d ligne(s) ; ambiguës : %d.',
            $s['rows'], $s['kept'], $s['excluded'], $s['judged'], $s['accepted'], $s['by_description'], $s['lines_with_records'], $s['ambiguous'],
        ));

        if (($report = $this->option('report')) !== null) {
            $this->writeReport((string) $report, $result['candidates']);
            $this->line("Notices jugées : {$report}");
        }
        if ($this->option('dry-run')) {
            $this->line('Essai (--dry-run) : rien n\'est écrit.');

            return self::SUCCESS;
        }
        $stored = $importer->store($result, date('Y-m-d H:i:s', (int) filemtime($path)));
        $this->info("Notices enregistrées : {$stored['written']} ; notices des Archives SNCF en base : {$stored['before']} avant, {$stored['after']} après.");

        return self::SUCCESS;
    }

    public static function localCopy(): string
    {
        return storage_path('app/data/archives-sncf/archives-sncf-sardo.json');
    }

    private function download(string $path): bool
    {
        File::ensureDirectoryExists(dirname($path));
        // Copie régénérable : hors sauvegarde (convention .nobackup du poste de développement).
        if (! is_file(dirname($path).'/.nobackup')) {
            File::put(dirname($path).'/.nobackup', '');
        }
        try {
            $response = Http::withUserAgent(CatalogueSruClient::USER_AGENT)->timeout(300)->get(self::EXPORT_URL);
        } catch (ConnectionException $e) {
            $this->error('Téléchargement impossible : '.$e->getMessage());

            return false;
        }
        if (! $response->successful() || ! is_array($response->json())) {
            $this->error("Téléchargement refusé (HTTP {$response->status()}) : la copie locale n'est pas modifiée.");

            return false;
        }
        File::put($path, $response->body());
        $this->line("Jeu de données téléchargé : {$path}");

        return true;
    }

    /** @param list<array<string, mixed>> $rows */
    private function writeReport(string $path, array $rows): void
    {
        File::ensureDirectoryExists(dirname($path));
        $out = fopen($path, 'w');
        if ($out === false) {
            $this->error("Impossible d'écrire {$path}");

            return;
        }
        fputcsv($out, ['accepted', 'line', 'line_name', 'score', 'reasons', 'rejected', 'places', 'read', 'year', 'date', 'type', 'title', 'url'], escape: '');
        foreach ($rows as $r) {
            fputcsv($out, CsvSafe::row([$r['accepted'] ? 'oui' : 'non', $r['line'], $r['line_name'], $r['score'], $r['reasons'], $r['rejected'],
                $r['places'], $r['read'], $r['year'], $r['date'], $r['type'], $r['title'], $r['url']]), escape: '');
        }
        fclose($out);
    }
}
