<?php

namespace App\Services\Signalling;

/**
 * Points remarquables des lignes qui ne sont pas des gares (bifurcations, raccordements, origines et fins de
 * ligne, frontières) : `database/data/line_landmarks.php`, lu par SignallingSectionLocator pour placer les
 * portions nommées par ces lieux. Lecture et contrôle du fichier, sans accès à la base ; le format est décrit
 * en tête du fichier.
 */
final class LineLandmarksFile
{
    public const KINDS = ['junction', 'line_start', 'line_end', 'border'];

    public const METHODS = ['sncf_line_end', 'sncf_connection', 'osm_milestones', 'sncf_calibrated'];

    public static function defaultPath(): string
    {
        return database_path('data/line_landmarks.php');
    }

    /**
     * Points du fichier, contrôlés et rangés par ligne ; une erreur par point refusé (le point n'est pas gardé).
     *
     * @param  list<array<string, mixed>>  $entries
     * @return array{points: array<int|string, list<array{name: string, keys: list<string>, kind: string, pk_m: int, lat: float, lon: float, method: string}>>, errors: list<string>}
     */
    public static function parse(array $entries): array
    {
        $points = [];
        $errors = [];
        $taken = [];
        foreach ($entries as $i => $entry) {
            $error = self::error($entry);
            $keys = [];
            if ($error === null) {
                $line = (string) $entry['line'];
                foreach ([$entry['name'], ...$entry['aliases'] ?? []] as $name) {
                    $key = SignallingSectionLocator::key((string) $name);
                    if (isset($taken[$line][$key]) || in_array($key, $keys, true)) {
                        $error = "nom « {$name} » déjà pris sur la ligne {$line}";
                        break;
                    }
                    $keys[] = $key;
                }
            }
            if ($error !== null) {
                $errors[] = sprintf('point n° %d (%s %s) : %s', $i + 1, $entry['line'] ?? '?', $entry['name'] ?? '?', $error);

                continue;
            }
            $line = (string) $entry['line'];
            foreach ($keys as $key) {
                $taken[$line][$key] = true;
            }
            $points[$line][] = [
                'name' => trim((string) $entry['name']),
                'keys' => $keys,
                'kind' => (string) $entry['kind'],
                'pk_m' => (int) $entry['pk_m'],
                'lat' => (float) $entry['lat'],
                'lon' => (float) $entry['lon'],
                'method' => (string) $entry['method'],
            ];
        }

        return ['points' => $points, 'errors' => $errors];
    }

    /**
     * Points du fichier par ligne ; un fichier fautif est une erreur (un point faux placerait mal des faits).
     *
     * @return array<int|string, list<array{name: string, keys: list<string>, kind: string, pk_m: int, lat: float, lon: float, method: string}>>
     */
    public static function load(?string $path = null): array
    {
        $path ??= self::defaultPath();
        $entries = is_file($path) ? require $path : [];
        if (! is_array($entries) || ! array_is_list($entries)) {
            throw new \UnexpectedValueException("{$path} doit renvoyer une liste de points.");
        }
        ['points' => $points, 'errors' => $errors] = self::parse($entries);
        if ($errors !== []) {
            throw new \UnexpectedValueException("{$path} : ".implode(' ; ', $errors));
        }

        return $points;
    }

    /** @param  mixed  $entry */
    private static function error($entry): ?string
    {
        if (! is_array($entry)) {
            return 'pas un tableau';
        }
        $text = fn (string $k): bool => isset($entry[$k]) && is_string($entry[$k]) && trim($entry[$k]) !== '';

        return match (true) {
            ! isset($entry['line']) || ! is_string($entry['line']) || ! preg_match('/^\d{6}$/', $entry['line']) => 'code de ligne à 6 chiffres attendu',
            ! $text('name') || SignallingSectionLocator::key($entry['name']) === '' => 'nom manquant',
            isset($entry['aliases']) && (! is_array($entry['aliases']) || ! array_is_list($entry['aliases'])
                || array_filter($entry['aliases'], fn ($a): bool => ! is_string($a) || SignallingSectionLocator::key($a) === '') !== []) => 'aliases : liste de noms attendue',
            ! in_array($entry['kind'] ?? null, self::KINDS, true) => 'kind inconnu (junction, line_start, line_end, border)',
            ! isset($entry['pk_m']) || ! is_int($entry['pk_m']) || $entry['pk_m'] < -10_000 || $entry['pk_m'] > 2_000_000 => 'pk_m : entier en mètres attendu',
            ! isset($entry['lat'], $entry['lon']) || ! is_float($entry['lat']) || ! is_float($entry['lon'])
                || $entry['lat'] < 41.0 || $entry['lat'] > 51.5 || $entry['lon'] < -5.5 || $entry['lon'] > 10.0 => 'position hors de France',
            ! in_array($entry['method'] ?? null, self::METHODS, true) => 'method inconnue',
            ! isset($entry['sources']) || ! is_array($entry['sources']) || $entry['sources'] === []
                || array_filter($entry['sources'], fn ($s): bool => ! is_array($s) || ! is_string($s['url'] ?? null) || ! str_starts_with($s['url'], 'https://')) !== [] => 'au moins une source avec une adresse https',
            isset($entry['note']) && ! is_string($entry['note']) => 'note : texte attendu',
            default => null,
        };
    }
}
