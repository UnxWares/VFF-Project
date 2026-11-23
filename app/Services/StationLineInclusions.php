<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Liens gare - ligne ajoutés à la main (database/data/station_line_inclusions.php), lus par
 * vff:attach-stations : contraire des exclusions (StationLineExclusions), pour une gare réellement desservie
 * par une ligne que ni la liste SNCF, ni la proximité, ni les terminus nommés ne lui rattachent.
 *
 * Le lien créé a la méthode « reviewed_link » (relu à la main d'après une source), traitée comme un lien
 * ferme (contrairement aux terminus nommés ou relus). Un couple déjà rattaché, par n'importe quelle méthode,
 * n'est pas réécrit. Une exclusion du même couple l'emporte (elle passe après). Les entrées ont la forme de
 * celles de StationLineExclusions (station, line, reason, source_url, reviewed_at). Une entrée mal formée,
 * en double ou dont la gare ou la ligne n'existe pas (ou n'est pas unique) n'est jamais appliquée.
 */
final class StationLineInclusions
{
    public const METHOD = 'reviewed_link';

    /** @var list<array<string, mixed>> */
    private array $entries;

    /** @param  list<array<string, mixed>>|null  $entries  défaut : database/data/station_line_inclusions.php */
    public function __construct(?array $entries = null)
    {
        $this->entries = $entries ?? (is_file($f = database_path('data/station_line_inclusions.php')) ? require $f : []);
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->entries;
    }

    /**
     * Erreurs de forme d'une entrée (vide si valide) : mêmes règles que les exclusions.
     *
     * @param  array<string, mixed>  $e
     * @return list<string>
     */
    public static function validate(array $e): array
    {
        return StationLineExclusions::validate($e);
    }

    /**
     * Couples résolus dans la base (numéro et message pour ceux qui ne le sont pas).
     *
     * @return array{valid: list<array{station_id: int, line_id: int, entry: array<string, mixed>}>, errors: list<string>}
     */
    public function resolve(): array
    {
        $errors = [];
        $valid = [];
        $seen = [];
        foreach ($this->entries as $i => $e) {
            $label = 'inclusion n° '.$i.' ('.(is_string($e['station'] ?? null) ? $e['station'] : '?').' sur '.(is_string($e['line'] ?? null) ? $e['line'] : '?').')';
            if (($formErrors = self::validate($e)) !== []) {
                $errors[] = $label.' : '.implode(' ; ', $formErrors);

                continue;
            }
            $stations = DB::table('stations')->where(preg_match('/^Q\d+$/', $e['station']) ? 'wikidata_id' : 'slug', $e['station'])->pluck('id');
            $lines = DB::table('lines')->where(fn ($q) => $q->where('slug', $e['line'])->orWhere('code', $e['line']))->pluck('id');
            $problems = array_filter([self::problem('gare', $stations->count()), self::problem('ligne', $lines->count())]);
            if ($problems !== []) {
                $errors[] = $label.' : '.implode(' ; ', $problems);

                continue;
            }
            $key = $stations->first().'|'.$lines->first();
            if (isset($seen[$key])) {
                $errors[] = $label.' : doublon de l\'inclusion n° '.$seen[$key];

                continue;
            }
            $seen[$key] = $i;
            $valid[] = ['station_id' => (int) $stations->first(), 'line_id' => (int) $lines->first(), 'entry' => $e];
        }

        return ['valid' => $valid, 'errors' => $errors];
    }

    private static function problem(string $what, int $found): ?string
    {
        return match (true) {
            $found === 0 => $what.' introuvable',
            $found > 1 => $what.' ambiguë ('.$found.' correspondances)',
            default => null,
        };
    }

    /** Provenance du lien : « relu:<source_url> », tronquée comme celle des terminus relus. */
    public static function sourceRef(array $e): string
    {
        return mb_substr('relu:'.$e['source_url'], 0, 160);
    }
}
