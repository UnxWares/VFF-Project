<?php

namespace App\Console\Commands;

use App\Support\I18nChecker;
use Illuminate\Console\Command;

/**
 * Vérifie la complétude des traductions EN/DE par rapport au FR canonique.
 *
 *   php artisan vff:i18n-check                 # CI : échoue si une clé FR manque
 *   php artisan vff:i18n-check --strict        # inclut les fichiers « surveillés »
 *   php artisan vff:i18n-check --locales=de    # une seule locale
 *   php artisan vff:i18n-check --no-usage      # sans scan de resources/js
 */
class I18nCheck extends Command
{
    protected $signature = 'vff:i18n-check
        {--locales=en,de : Locales comparées au FR (séparées par des virgules)}
        {--strict : Les fichiers surveillés (autres chantiers) et les clés inconnues du front deviennent bloquants}
        {--no-usage : Ne pas scanner resources/js à la recherche de clés t(\'…\') inconnues}';

    protected $description = 'Vérifie que chaque clé de traduction FR existe en EN et DE (placeholders compris)';

    public function handle(): int
    {
        $locales = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('locales')))));
        $checker = new I18nChecker(lang_path(), I18nChecker::WATCH_ONLY, (bool) $this->option('strict'));

        $issues = $checker->check($locales);
        if (! $this->option('no-usage')) {
            $issues = [...$issues, ...$checker->checkUsage(resource_path('js'))];
        }

        $errors = array_values(array_filter($issues, fn ($i) => $i['severity'] === I18nChecker::SEVERITY_ERROR));
        $warnings = array_values(array_filter($issues, fn ($i) => $i['severity'] === I18nChecker::SEVERITY_WARNING));

        $this->line(sprintf(
            'Locale canonique : <info>%s</info> (%d fichiers) - comparée à : <info>%s</info>',
            I18nChecker::CANONICAL,
            count($checker->namespaces(I18nChecker::CANONICAL)),
            implode(', ', $locales),
        ));
        if (! $this->option('strict')) {
            $this->line('Fichiers surveillés (non bloquants) : '.implode(', ', I18nChecker::WATCH_ONLY));
        }
        $this->newLine();

        if ($errors) {
            $this->error(sprintf(' %d erreur(s) ', count($errors)));
            $this->renderIssues($errors);
        }

        if ($warnings) {
            $this->warn(sprintf('%d avertissement(s)', count($warnings)));
            $this->renderIssues($warnings);
        }

        if (! $errors) {
            $this->info('✓ Aucune clé FR manquante dans '.implode(', ', $locales).'.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    /**
     * @param  list<array{severity: string, type: string, locale: string, namespace: string, key: ?string, detail: ?string}>  $issues
     */
    protected function renderIssues(array $issues): void
    {
        $labels = [
            'missing_file' => 'fichier manquant',
            'missing_key' => 'clé manquante',
            'placeholder_mismatch' => 'placeholders',
            'empty_value' => 'valeur vide',
            'extra_key' => 'clé absente du FR',
            'orphan_file' => 'fichier absent du FR',
            'unknown_usage' => 'clé front inconnue',
        ];

        $this->table(
            ['Locale', 'Clé', 'Problème', 'Détail'],
            array_map(fn ($i) => [
                $i['locale'],
                $i['namespace'].($i['key'] !== null ? '.'.$i['key'] : '.*'),
                $labels[$i['type']] ?? $i['type'],
                mb_strimwidth((string) $i['detail'], 0, 70, '…'),
            ], $issues),
        );
    }
}
