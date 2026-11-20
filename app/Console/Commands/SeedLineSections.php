<?php

namespace App\Console\Commands;

use App\Models\Line;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * SQUELETTE (#162) - ajout MANUEL et additif d'une section nommée à une ligne.
 *
 * Contexte : la table `line_sections` (sections commerciales/historiques affichées
 * sur la fiche ligne) n'est aujourd'hui peuplée QUE pour les LGV
 * (cf. Database\Seeders\LineSectionsSeeder). La dérivation d'un statut « par
 * section » pour les lignes CLASSIQUES suppose de peupler `line_sections`
 * au-delà des LGV - travail de curation non automatisé.
 *
 * Cette commande NE seed PAS en masse : elle ajoute UNE section à la fois, pour
 * permettre d'amorcer cette curation à la main, ligne par ligne, sans toucher
 * aux segments ni à la géométrie existante (100 % additif).
 *
 * Voir docs/LINE-SECTIONS.md pour la note de cadrage.
 */
class SeedLineSections extends Command
{
    protected $signature = 'vff:seed-line-sections
        {--code= : Code RFN de la ligne classique (ex: 420000 ou "420 000")}
        {--name= : Nom de la section (ex: "Section Tours - Vierzon")}
        {--pk-start= : PK de début en mètres (ex: 0)}
        {--pk-end= : PK de fin en mètres (ex: 90000)}
        {--alt-names= : Noms alternatifs, séparés par des virgules (optionnel)}
        {--opening-from= : Année d\'ouverture (début), optionnel}
        {--opening-to= : Année d\'ouverture (fin), optionnel}
        {--description= : Description libre, optionnel}
        {--source-url= : URL source (Wikipedia, archive…), optionnel}';

    protected $description = 'SQUELETTE (#162) : ajoute UNE section nommée à une ligne (classique) dans line_sections. Additif, pas de seed en masse.';

    public function handle(): int
    {
        $code = (string) $this->option('code');
        $name = (string) $this->option('name');
        $pkStart = $this->option('pk-start');
        $pkEnd = $this->option('pk-end');

        if ($code === '' || $name === '' || $pkStart === null || $pkEnd === null) {
            $this->error('Options requises : --code, --name, --pk-start, --pk-end');

            return self::FAILURE;
        }

        $pkStart = (int) $pkStart;
        $pkEnd = (int) $pkEnd;

        if ($pkEnd <= $pkStart) {
            $this->error("PK incohérents : --pk-end ({$pkEnd}) doit être > --pk-start ({$pkStart})");

            return self::FAILURE;
        }

        // Résolution de la ligne par code RFN, en tolérant les espaces (« 420 000 »).
        $line = Line::query()
            ->whereRaw("REGEXP_REPLACE(code, '\\s+', '', 'g') = ?", [
                preg_replace('/\s+/', '', $code),
            ])
            ->first();

        if (! $line) {
            $this->error("Ligne {$code} introuvable");

            return self::FAILURE;
        }

        $altNames = null;
        if ($this->option('alt-names')) {
            $parsed = array_values(array_filter(array_map(
                'trim',
                explode(',', (string) $this->option('alt-names')),
            )));
            $altNames = $parsed === [] ? null : json_encode($parsed);
        }

        // Idempotence douce : on évite un doublon exact (même ligne + même nom + mêmes PK).
        $exists = DB::table('line_sections')
            ->where('line_id', $line->id)
            ->where('name', $name)
            ->where('pk_start_m', $pkStart)
            ->where('pk_end_m', $pkEnd)
            ->exists();

        if ($exists) {
            $this->warn("Section déjà présente pour {$line->name} ({$code}) : « {$name} », rien à faire");

            return self::SUCCESS;
        }

        DB::table('line_sections')->insert([
            'line_id' => $line->id,
            'name' => $name,
            'alt_names' => $altNames,
            'pk_start_m' => $pkStart,
            'pk_end_m' => $pkEnd,
            'opening_year_from' => $this->option('opening-from') !== null
                ? (int) $this->option('opening-from')
                : null,
            'opening_year_to' => $this->option('opening-to') !== null
                ? (int) $this->option('opening-to')
                : null,
            'description' => $this->option('description') ?: null,
            'source_url' => $this->option('source-url') ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $startLabel = sprintf('%d+%03d', intdiv($pkStart, 1000), $pkStart % 1000);
        $endLabel = sprintf('%d+%03d', intdiv($pkEnd, 1000), $pkEnd % 1000);

        $this->info("✓ {$line->name} ({$code}) → section « {$name} » (PK {$startLabel} → {$endLabel}) ajoutée");

        return self::SUCCESS;
    }
}
