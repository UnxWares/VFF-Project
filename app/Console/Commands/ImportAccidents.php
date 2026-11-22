<?php

namespace App\Console\Commands;

use App\Services\Accidents\AccidentImporter;
use App\Services\Accidents\AccidentImportResult;
use App\Services\Accidents\AccidentSourceClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Accidents et événements graves du rail français → tables accidents, accident_lines,
 * accident_sources (cf. App\Services\Accidents\AccidentImporter).
 *
 * Sources (database/data/source_rights.php) : Wikidata (CC0) et Wikipédia en français (articles et
 * listes d'accidents, et listes en anglais et en allemand : faits seulement, lien vers la révision lue ; CC BY-SA 4.0), bilan général des
 * enquêtes du BEA-TT (Licence Ouverte 2.0). Requêtes polies, cache local dans storage/app/data/accidents.
 * Idempotente ; n'écrit dans aucune table du moteur ; une fiche disparue des sources est
 * signalée, et retirée seulement avec --prune (import complet, toutes sources lues).
 *
 *   vff:import-accidents                       # Wikidata, Wikipédia et BEA-TT
 *   vff:import-accidents --offline             # cache seulement, aucune requête réseau
 *   vff:import-accidents --only=bea-tt --dry-run
 *   vff:import-accidents --only=wikipedia-list # listes d'accidents de Wikipédia seulement
 *   vff:import-accidents --only=wikipedia-editions # listes en anglais et en allemand : recoupent les fiches, n'en créent pas
 *   vff:import-accidents --only=manual        # fiches rédigées à la main (database/data/accident_records_manual.php)
 *   vff:import-accidents --refresh --report=storage/app/data/accidents/dernier-import.json
 */
class ImportAccidents extends Command
{
    protected $signature = 'vff:import-accidents
        {--only=* : Sources à lire : wikidata (avec ses articles de Wikipédia), bea-tt, wikipedia-list (listes d\'accidents de Wikipédia), wikipedia-editions (listes de Wikipédia en anglais et en allemand : seconde source des fiches existantes), manual (fiches rédigées à la main, database/data/accident_records_manual.php) ; toutes par défaut}
        {--offline : N\'utilise que le cache local, aucune requête réseau}
        {--refresh : Ignore le cache et relit les sources (le cache est réécrit)}
        {--dry-run : Calcule tout sans écrire en base}
        {--prune : Retire les fiches absentes des sources (import complet seulement : toutes sources, rien ne manque au cache)}
        {--report= : Fichier JSON du bilan détaillé (cas douteux, écarts)}';

    protected $description = 'Importe les accidents ferroviaires en France (Wikidata, Wikipédia, BEA-TT) : date, lieu, ligne, bilan, sources';

    public function handle(AccidentImporter $importer): int
    {
        $only = array_values(array_filter((array) $this->option('only')));
        $sources = $only !== [] ? $only : AccidentImporter::SOURCES;
        if (($unknown = array_diff($sources, AccidentImporter::SOURCES)) !== []) {
            $this->error('Source inconnue : '.implode(', ', $unknown).' (attendu : '.implode(', ', AccidentImporter::SOURCES).')');

            return self::INVALID;
        }

        $client = new AccidentSourceClient(
            AccidentSourceClient::defaultCacheDir(),
            offline: (bool) $this->option('offline'),
            refresh: (bool) $this->option('refresh'),
        );
        $result = $importer->run($client, $sources, (bool) $this->option('dry-run'), (bool) $this->option('prune'));
        $this->show($result);

        if (($path = $this->option('report')) !== null) {
            File::ensureDirectoryExists(dirname((string) $path));
            File::put((string) $path, (string) json_encode(get_object_vars($result), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->line("Bilan détaillé : {$path}");
        }

        return self::SUCCESS;
    }

    private function show(AccidentImportResult $r): void
    {
        foreach ($r->warnings as $w) {
            $this->warn($w);
        }
        $this->info(sprintf(
            'Lus : %d (Wikidata), %d (BEA-TT), %d (listes de Wikipédia), %d (listes anglaise et allemande), %d (fiches rédigées à la main) ; %d rangée(s) du BEA-TT fusionnée(s) ; %d hors périmètre ; %d requête(s).',
            $r->collected['wikidata'] ?? 0, $r->collected['bea-tt'] ?? 0, $r->collected['wikipedia-list'] ?? 0, $r->collected['wikipedia-editions'] ?? 0, $r->collected['manual'] ?? 0, $r->merged, count($r->skipped), $r->requests,
        ));
        $skippedBy = array_count_values(array_column($r->skipped, 'reason'));
        ksort($skippedBy);
        if ($skippedBy !== []) {
            $this->line('  hors périmètre : '.implode(', ', array_map(fn ($k, $n) => "{$k} {$n}", array_keys($skippedBy), $skippedBy)));
        }
        foreach ($r->rejected as $source => $reasons) {
            foreach ($reasons as $reason => $n) {
                $this->line("  écartés à la lecture ({$source}, {$reason}) : {$n}");
            }
        }
        foreach ($r->counts as $group => $counts) {
            arsort($counts);
            $this->line("  {$group} : ".implode(', ', array_map(fn ($k, $n) => "{$k} {$n}", array_keys($counts), $counts)));
        }
        if ($r->toReview !== []) {
            $this->warn(count($r->toReview).' fiche(s) à vérifier.');
            if ($this->output->isVerbose()) {
                foreach ($r->toReview as $slug => $notes) {
                    $this->line("  {$slug} : ".implode(' ; ', array_map(fn ($n) => trim($n['code'].' '.$n['detail']), $notes)));
                }
            }
        }
        foreach ($r->retired as $line) {
            $this->line("  doublon retiré : {$line}");
        }
        foreach ($r->pruned as $slug) {
            $this->line("  fiche retirée (absente des sources) : {$slug}");
        }
        if ($r->unseen !== [] && $r->pruned === []) {
            $this->warn(count($r->unseen).' fiche(s) en base absente(s) des sources (non supprimées) : '.implode(', ', array_slice($r->unseen, 0, 10)));
        }
        if ($r->missing !== []) {
            $this->warn(count($r->missing).' élément(s) absent(s) du cache (mode hors ligne).');
        }
    }
}
