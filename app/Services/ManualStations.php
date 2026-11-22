<?php

namespace App\Services;

/**
 * Gares saisies à la main (database/data/gares_manuelles.php) : gares qu'aucune source importée ne porte
 * (SNCF, Wikidata, OSM, OHM) et qu'une source citable situe et date. vff:import-station-sources les enregistre
 * dans `station_sources` (source « manual »), vff:consolidate-stations en fait des gares sans jamais les fondre
 * dans une autre gare ni les rattacher à une autre par proximité.
 *
 * Une entrée mal formée n'est jamais importée (validate) : la commande la signale.
 */
final class ManualStations
{
    public const SOURCE = 'manual';

    public const PRECISIONS = ['day', 'month', 'year'];

    /** @var list<array<string, mixed>> */
    private array $entries;

    /** @param  list<array<string, mixed>>|null  $entries  défaut : database/data/gares_manuelles.php */
    public function __construct(?array $entries = null)
    {
        $this->entries = $entries ?? (is_file($f = database_path('data/gares_manuelles.php')) ? require $f : []);
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
        foreach (['ref', 'name', 'source_url', 'quote', 'reason'] as $k) {
            if (! is_string($e[$k] ?? null) || trim($e[$k]) === '') {
                $errors[] = "{$k} manquant";
            }
        }
        if (is_string($e['ref'] ?? null) && preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $e['ref']) !== 1) {
            $errors[] = 'ref : slug minuscule';
        }
        if (is_string($e['source_url'] ?? null) && ! str_starts_with($e['source_url'], 'https://')) {
            $errors[] = 'source_url : lien https';
        }
        $lat = $e['lat'] ?? null;
        $lon = $e['lon'] ?? null;
        // Métropole et outre-mer proche : on borne large mais on refuse les permutations lat / lon.
        if (! is_numeric($lat) || ! is_numeric($lon) || $lat < 41 || $lat > 52 || $lon < -5.5 || $lon > 10) {
            $errors[] = 'lat, lon : coordonnées WGS 84 de France métropolitaine';
        }
        foreach (['opened', 'closed'] as $k) {
            $date = $e["{$k}_on"] ?? null;
            if ($date === null) {
                continue;
            }
            if (! is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                $errors[] = "{$k}_on : date AAAA-MM-JJ";
            }
            if (! in_array($e["{$k}_precision"] ?? null, self::PRECISIONS, true)) {
                $errors[] = "{$k}_precision : day, month ou year";
            }
        }
        if (isset($e['opened_on'], $e['closed_on']) && is_string($e['opened_on']) && is_string($e['closed_on']) && $e['closed_on'] < $e['opened_on']) {
            $errors[] = 'fermeture avant l\'ouverture';
        }
        foreach (['name', 'quote', 'reason'] as $k) {
            if (is_string($e[$k] ?? null) && (str_contains($e[$k], "\u{2014}") || str_contains($e[$k], "\u{2013}") || str_contains($e[$k], '--'))) {
                $errors[] = "{$k} : tiret long";
            }
        }

        return $errors;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->entries;
    }

    /**
     * Enregistrements prêts pour ImportStationSources::upsertSource (entrées valides seulement), et erreurs
     * par ref des autres.
     *
     * @return array{records: list<array<string, mixed>>, errors: array<string, list<string>>}
     */
    public function records(): array
    {
        $records = [];
        $errors = [];
        $seen = [];
        foreach ($this->entries as $i => $e) {
            $key = is_string($e['ref'] ?? null) ? $e['ref'] : "#{$i}";
            $err = self::validate($e);
            if (isset($seen[$key])) {
                $err[] = 'ref en double';
            }
            $seen[$key] = true;
            if ($err !== []) {
                $errors[$key] = $err;

                continue;
            }
            $records[] = [
                'source_ref' => $e['ref'],
                'name' => $e['name'],
                'lat' => (float) $e['lat'],
                'lon' => (float) $e['lon'],
                'kind' => 'gare',
                'lifecycle' => isset($e['closed_on']) ? 'historic' : 'active',
                'opened_on' => $e['opened_on'] ?? null,
                'opened_precision' => $e['opened_precision'] ?? null,
                'closed_on' => $e['closed_on'] ?? null,
                'closed_precision' => $e['closed_precision'] ?? null,
                'passenger' => true,
                'properties' => array_filter([
                    'source_url' => $e['source_url'], 'quote' => $e['quote'], 'reason' => $e['reason'], 'network' => $e['network'] ?? null,
                ]),
            ];
        }

        return ['records' => $records, 'errors' => $errors];
    }
}
