<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Liens gare - ligne écartés à la main (database/data/station_line_exclusions.php), lus par
 * vff:attach-stations : un couple exclu n'est jamais rattaché, quelle que soit la méthode qui l'aurait
 * produit (sncf_pk, source_pk, wikidata_p81, segment, trace, junction, terminus_name, terminus_reviewed).
 *
 * Contrairement à l'action « unlink » de gares_corrections.php (lien gare - élément Wikidata) et aux
 * décisions « none » de station_terminus_overrides.php (terminus nommés seulement), une exclusion défait
 * le couple lui-même dans station_lines. Elle vaut aussi pour les liens déjà en base : au passage suivant de
 * vff:attach-stations, quelle que soit l'option (rattachement complet, --years-only, --termini-only), le
 * couple exclu est retiré avant le calcul du PK et des années de desserte. Elle l'emporte sur une décision
 * « attach » de station_terminus_overrides.php pour le même couple.
 *
 * Une entrée est :
 *   - station : élément Wikidata (« Q2260089 », s'il est unique dans la base) ou slug de la gare ;
 *   - line : code de la ligne (« 750000 ») ou son slug ;
 *   - reason : pourquoi le lien est faux, faits seulement ;
 *   - source_url : lien https (permalien de l'article, de la carte ou du schéma qui montre la gare hors de la ligne) ;
 *   - reviewed_at : date de la relecture (AAAA-MM-JJ).
 *
 * Une entrée mal formée, en double ou dont la gare ou la ligne n'existe pas (ou n'est pas unique) n'est jamais
 * appliquée : la commande la signale (resolve) et le test du fichier versionné échoue.
 */
final class StationLineExclusions
{
    /** @var list<array<string, mixed>> */
    private array $entries;

    /** @param  list<array<string, mixed>>|null  $entries  défaut : database/data/station_line_exclusions.php */
    public function __construct(?array $entries = null)
    {
        $this->entries = $entries ?? (is_file($f = database_path('data/station_line_exclusions.php')) ? require $f : []);
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->entries;
    }

    /**
     * Erreurs de forme d'une entrée (vide si valide).
     *
     * @param  array<string, mixed>  $e
     * @return list<string>
     */
    public static function validate(array $e): array
    {
        $errors = [];
        foreach (['station', 'line', 'reason', 'source_url', 'reviewed_at'] as $k) {
            if (! is_string($e[$k] ?? null) || trim($e[$k]) === '') {
                $errors[] = "{$k} manquant";
            }
        }
        foreach (['station', 'line'] as $k) {
            if (is_string($e[$k] ?? null) && $e[$k] !== trim($e[$k])) {
                $errors[] = "{$k} : espaces autour";
            }
        }
        if (is_string($e['source_url'] ?? null) && $e['source_url'] !== '' && ! preg_match('#^https://\S+$#', $e['source_url'])) {
            $errors[] = 'source_url : lien https';
        }
        if (is_string($e['reviewed_at'] ?? null) && $e['reviewed_at'] !== ''
            && (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $e['reviewed_at']) || ! checkdate((int) substr($e['reviewed_at'], 5, 2), (int) substr($e['reviewed_at'], 8, 2), (int) substr($e['reviewed_at'], 0, 4)))) {
            $errors[] = 'reviewed_at : date AAAA-MM-JJ';
        }
        foreach (['reason', 'source_url'] as $k) {
            if (is_string($e[$k] ?? null) && (str_contains($e[$k], "\u{2014}") || str_contains($e[$k], "\u{2013}") || str_contains($e[$k], '--'))) {
                $errors[] = "{$k} : tiret long";
            }
        }
        $unknown = array_diff(array_keys($e), ['station', 'line', 'reason', 'source_url', 'reviewed_at']);
        if ($unknown !== []) {
            $errors[] = 'clés inconnues : '.implode(', ', $unknown);
        }

        return $errors;
    }

    /**
     * Couples exclus résolus dans la base : identifiants de gare et de ligne, entrée d'origine. Les entrées
     * invalides, introuvables, ambiguës ou en double (même couple) sont rendues dans « errors » (numéro de
     * l'entrée, message) et ne sont pas appliquées.
     *
     * @return array{valid: list<array{station_id: int, line_id: int, entry: array<string, mixed>}>, errors: list<string>}
     */
    public function resolve(): array
    {
        $errors = [];
        $valid = [];
        $seen = [];
        foreach ($this->entries as $i => $e) {
            $label = 'exclusion n° '.$i.' ('.(is_string($e['station'] ?? null) ? $e['station'] : '?').' sur '.(is_string($e['line'] ?? null) ? $e['line'] : '?').')';
            if (($formErrors = self::validate($e)) !== []) {
                $errors[] = $label.' : '.implode(' ; ', $formErrors);

                continue;
            }
            $stations = DB::table('stations')->where(preg_match('/^Q\d+$/', $e['station']) ? 'wikidata_id' : 'slug', $e['station'])->pluck('id');
            $lines = DB::table('lines')->where(function ($q) use ($e) {
                $q->where('slug', $e['line'])->orWhere('code', $e['line']);
            })->pluck('id');
            $problems = array_filter([self::problem('gare', $stations->count()), self::problem('ligne', $lines->count())]);
            if ($problems !== []) {
                $errors[] = $label.' : '.implode(' ; ', $problems);

                continue;
            }
            $key = $stations->first().'|'.$lines->first();
            if (isset($seen[$key])) {
                $errors[] = $label.' : doublon de l\'exclusion n° '.$seen[$key];

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

    /**
     * Retire de station_lines les couples exclus (nombre rendu). Rien d'autre n'est touché.
     *
     * @param  list<array{station_id: int, line_id: int, entry: array<string, mixed>}>  $couples
     */
    public static function remove(array $couples): int
    {
        $removed = 0;
        foreach ($couples as $c) {
            $removed += DB::table('station_lines')->where('station_id', $c['station_id'])->where('line_id', $c['line_id'])->delete();
        }

        return $removed;
    }
}
