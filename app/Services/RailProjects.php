<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * « Projets : réouvertures et lignes nouvelles » : les projets ferroviaires français pas encore en service
 * (réouvertures au service voyageurs, lignes nouvelles et LGV, RER métropolitains, électrifications annoncées), avec
 * leur état, la date de chaque étape et la source de chaque fait daté. Page `/projects` et `GET /api/v1/projects`.
 * Lecture seule : rien n'est écrit en base.
 *
 * Les données viennent du catalogue versionné `database/data/projets_ferroviaires.php` (sources officielles d'abord,
 * presse régionale ensuite). Seules les fiches de ligne sont lues en base, pour relier un projet à sa ligne quand elle
 * existe. Un projet dont la mise en service a eu lieu sort du catalogue.
 *
 * Règles :
 *   - une étape `done` est franchie (date passée, source citée) ; une étape non franchie est une date PRÉVUE, jamais
 *     présentée comme acquise : la page et l'API disent « prévue en 2028 selon … » ;
 *   - l'état du projet est la dernière étape franchie, dans l'ordre étude, débat, déclaration d'utilité publique,
 *     financement, travaux ; `suspended` quand le catalogue le dit (projet suspendu, reporté sans date ou abandonné) ;
 *   - le type de projet et l'état n'ont que les valeurs de KINDS et STATES.
 */
final class RailProjects
{
    public const CACHE_KEY = 'rail_projects:v1';

    public const CATALOGUE = 'database/data/projets_ferroviaires.php';

    /** Types de projet, dans l'ordre d'affichage des filtres. */
    public const KINDS = ['reopening', 'new_line', 'lgv', 'rer', 'electrification'];

    /** Étapes, de la plus précoce à la plus avancée. */
    public const STAGES = ['study', 'debate', 'dup', 'funding', 'works', 'service', 'commissioning'];

    /** États d'un projet, dans l'ordre d'avancement (`suspended` en dernier). */
    public const STATES = ['study', 'debate', 'dup', 'funding', 'works', 'suspended'];

    /** Précisions de date. */
    public const PRECISIONS = ['year', 'month', 'day'];

    /**
     * Données de la page et de l'API, gardées en cache une heure.
     *
     * @return array{projects: list<array<string, mixed>>, regions: list<string>, kinds: list<string>, states: list<string>, checked: ?string}
     */
    public function get(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, fn (): array => $this->build());
    }

    /** @return array{projects: list<array<string, mixed>>, regions: list<string>, kinds: list<string>, states: list<string>, checked: ?string} */
    public function build(): array
    {
        $catalogue = self::catalogue();
        $slugs = [];
        foreach ($catalogue as $entry) {
            foreach ($entry['line_slugs'] ?? [] as $slug) {
                $slugs[] = $slug;
            }
        }
        $lines = $slugs === [] ? [] : DB::table('lines')->whereIn('slug', array_values(array_unique($slugs)))->pluck('name', 'slug')->all();

        $projects = [];
        $regions = [];
        $checked = null;
        foreach ($catalogue as $entry) {
            $project = $this->project($entry, $lines);
            $projects[] = $project;
            foreach ($project['regions'] as $region) {
                $regions[$region] = true;
            }
            $checked = max($checked ?? '', (string) ($entry['checked'] ?? '')) ?: $checked;
        }
        usort($projects, fn (array $a, array $b): int => strcmp(self::fold($a['name']), self::fold($b['name'])));
        $regions = array_keys($regions);
        sort($regions);

        return ['projects' => $projects, 'regions' => $regions, 'kinds' => self::KINDS, 'states' => self::STATES, 'checked' => $checked];
    }

    /** @return list<array<string, mixed>> */
    public static function catalogue(): array
    {
        /** @var list<array<string, mixed>> */
        return require base_path(self::CATALOGUE);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, string>  $lines  nom des fiches de ligne existantes, par slug
     * @return array<string, mixed>
     */
    private function project(array $entry, array $lines): array
    {
        $stages = [];
        foreach ($entry['stages'] as $stage) {
            $stages[] = [
                'kind' => $stage['kind'],
                'date' => $stage['date'],
                'precision' => self::precision((string) $stage['date']),
                'done' => (bool) $stage['done'],
                'note' => $stage['note'] ?? null,
                'source' => $stage['source'],
            ];
        }
        usort($stages, fn (array $a, array $b): int => [$a['date'], array_search($a['kind'], self::STAGES, true)] <=> [$b['date'], array_search($b['kind'], self::STAGES, true)]);

        $projectLines = [];
        foreach ($entry['line_slugs'] ?? [] as $slug) {
            if (isset($lines[$slug])) {
                $projectLines[] = ['slug' => $slug, 'name' => $lines[$slug]];
            }
        }

        $planned = array_values(array_filter($stages, fn (array $s): bool => ! $s['done']));
        $commissioning = array_values(array_filter($planned, fn (array $s): bool => $s['kind'] === 'commissioning'));

        return [
            'id' => $entry['id'],
            'name' => $entry['name'],
            'kind' => $entry['kind'],
            'state' => self::state($entry),
            'regions' => $entry['regions'],
            'porteurs' => $entry['porteurs'],
            'lines' => $projectLines,
            'summary' => $entry['summary'],
            'stages' => $stages,
            'next_stage' => $planned[0] ?? null,
            'planned_commissioning' => $commissioning[0] ?? null,
            'checked' => $entry['checked'] ?? null,
        ];
    }

    /** @param array<string, mixed> $entry */
    public static function state(array $entry): string
    {
        if (($entry['suspended'] ?? false) === true) {
            return 'suspended';
        }
        $state = 'study';
        foreach ($entry['stages'] as $stage) {
            if ($stage['done'] && $stage['kind'] !== 'commissioning'
                && array_search($stage['kind'], self::STATES, true) > array_search($state, self::STATES, true)) {
                $state = $stage['kind'];
            }
        }

        return $state;
    }

    /** Précision d'une date de catalogue : `year` (2028), `month` (2028-03) ou `day` (2028-03-12). */
    public static function precision(string $date): string
    {
        return match (strlen($date)) {
            4 => 'year',
            7 => 'month',
            default => 'day',
        };
    }

    /** Minuscules sans accents, pour trier par nom. */
    private static function fold(string $text): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return strtolower($ascii === false ? $text : $ascii);
    }
}
