<?php

namespace App\Console\Commands;

use App\Models\Era;
use App\Services\Traction\LineElectrificationAggregate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Recalcule `lines.electrified` et `lines.voltage_v` depuis les tronçons de l'ère courante
 * (cf. App\Services\Traction\LineElectrificationAggregate). Écrit seulement la table `lines` ;
 * à lancer après vff:enrich-era-segments (électrification des tronçons) et vff:classify-lines.
 */
class AggregateLineElectrification extends Command
{
    protected $signature = 'vff:aggregate-line-electrification {--era= : année de l\'ère (courante par défaut)}
        {--dry-run : calcule et affiche sans écrire} {--show=40 : lignes à afficher par sens}';

    protected $description = 'Drapeau électrifiée des lignes : part des tronçons en service électrifiés (seuil de la fiche), pas un seul tronçon.';

    public function handle(LineElectrificationAggregate $aggregate): int
    {
        $era = $this->option('era')
            ? Era::where('year', $this->option('era'))->firstOrFail()
            : Era::where('is_current', true)->firstOrFail();
        $dry = (bool) $this->option('dry-run');

        $r = $aggregate->apply($era->id, ! $dry);
        $this->info(sprintf('Électrification des lignes - ère %d%s : %d lignes, %d passent à électrifiée, %d à non électrifiée, %d tensions changées, %d inchangées.',
            $era->year, $dry ? ' [DRY-RUN]' : '', $r['lines'], count($r['to_true']), count($r['to_false']), $r['voltage_changed'], $r['unchanged']));
        foreach (['to_true' => 'Passent à électrifiée', 'to_false' => 'Passent à non électrifiée'] as $key => $title) {
            if ($r[$key] === []) {
                continue;
            }
            $this->line($title.' :');
            foreach (array_slice($r[$key], 0, (int) $this->option('show')) as $label) {
                $this->line('  '.$label);
            }
        }
        if (! $dry) {
            Cache::forget('home_stats');
        }

        return self::SUCCESS;
    }
}
