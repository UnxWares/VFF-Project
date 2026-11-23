<?php

namespace App\Console\Commands;

use App\Services\Accidents\AccidentSourceClient;
use App\Services\Accidents\AccidentVerificationResult;
use App\Services\Accidents\AccidentVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Vérifie les fiches d'accidents d'après les sources déjà lues (cf. App\Services\Accidents\AccidentVerifier) :
 * date et lieu confirmés par une source primaire ou par deux sources indépendantes (fiche « ok » ; la presse numérisée
 * du contexte, quand le fichier en écrit les faits, compte pour une famille), doublons
 * gardés en alias, nature reclassée quand la source la dit, ligne citée par la source rattachée avec son
 * permalien.
 *
 * Rejouable et idempotente : un second passage ne change rien. Elle repart des faits déjà gardés dans
 * accident_sources et du cache de storage/app/data/accidents ; les seules requêtes possibles (lecture
 * seule, polies) suivent les redirections d'articles de ligne de Wikipédia, et sont mises en cache.
 * Après vff:import-accidents (qui refait les rattachements et le statut), la rejouer : l'import ne met
 * jamais une fiche « ok », et une fiche publique n'est annoncée aux webhooks (accident.published)
 * qu'après sa première vérification.
 *
 *   vff:verify-accidents --dry-run     # tout calculer, rien garder
 *   vff:verify-accidents --offline     # aucune requête, cache seul
 *   vff:verify-accidents --demote      # rend aussi « à vérifier » les fiches « ok » qu'aucune règle ne confirme
 */
class VerifyAccidents extends Command
{
    protected $signature = 'vff:verify-accidents
        {--dry-run : Calcule tout dans une transaction annulée : les chiffres sont exacts, rien n\'est écrit}
        {--offline : N\'utilise que le cache local, aucune requête réseau}
        {--demote : Rend aussi « à vérifier » les fiches « ok » qu\'aucune règle ne confirme}
        {--report= : Fichier JSON du bilan détaillé}';

    protected $description = 'Vérifie les accidents : date et lieu confirmés par deux sources ou une source primaire, doublons, nature, ligne citée';

    public function handle(AccidentVerifier $verifier): int
    {
        $client = new AccidentSourceClient(AccidentSourceClient::defaultCacheDir(), offline: (bool) $this->option('offline'));
        $result = $verifier->run($client, (bool) $this->option('dry-run'), (bool) $this->option('demote'));
        $this->show($result, (bool) $this->option('dry-run'));

        if (($path = $this->option('report')) !== null) {
            File::ensureDirectoryExists(dirname((string) $path));
            File::put((string) $path, (string) json_encode(get_object_vars($result), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->line("Bilan détaillé : {$path}");
        }

        return self::SUCCESS;
    }

    private function show(AccidentVerificationResult $r, bool $dryRun): void
    {
        foreach ($r->warnings as $warning) {
            $this->warn($warning);
        }
        $this->info(($dryRun ? 'Simulation : ' : '').sprintf(
            'fiches publiées %d, dont ok %d et à vérifier %d (avant : %d, %d) ; nature « autre » %d (avant : %d).',
            $r->after['total'], $r->after['ok'], $r->after['to_review'], $r->before['ok'], $r->before['to_review'], $r->after['other'], $r->before['other'],
        ));
        $byRule = array_count_values($r->promoted);
        $this->line(sprintf('  passées à « ok » : %d (%s)', count($r->promoted), implode(', ', array_map(fn ($k, $n) => "{$k} {$n}", array_keys($byRule), $byRule)) ?: 'aucune'));
        $this->line(sprintf('  « ok » confirmées entre autres par la presse numérisée : %d', count($r->pressConfirmed)));
        $this->line('  « ok » par règle : '.(implode(', ', array_map(fn ($k, $n) => "{$k} {$n}", array_keys($r->rules), $r->rules)) ?: 'aucune'));
        $this->line(sprintf('  « ok » sans confirmation (laissées telles quelles) : %d ; rendues « à vérifier » : %d', count($r->unconfirmedOk), count($r->demoted)));
        $this->line(sprintf('  alias : %d ; doublons possibles : %d ; natures reclassées : %d', count($r->aliases), count($r->possibleDuplicates), count($r->reclassified)));
        $this->line(sprintf('  fiches annoncées aux webhooks (première vérification) : %d', $dryRun ? 0 : count($r->announced)));
        $this->line('  lignes citées : '.(implode(', ', array_map(fn ($k, $n) => "{$k} {$n}", array_keys($r->citedLines), $r->citedLines)) ?: 'aucune').' ; titres sans ligne VFF : '.count($r->unresolvedLineTitles));
        if ($this->output->isVerbose()) {
            foreach ($r->aliases as $a) {
                $this->line("  alias {$a['alias']} de {$a['of']} ({$a['reason']})");
            }
            foreach ($r->reclassified as $k) {
                $this->line("  nature {$k['slug']} : {$k['from']} -> {$k['to']}");
            }
            foreach ($r->demoted as $slug) {
                $this->line("  rendue à vérifier : {$slug}");
            }
        }
    }
}
