<?php

namespace App\Services\Media\OpenData;

/**
 * Passe une ligne brute d'un jeu ouvert d'images (colonnes du jeu) à une forme commune, selon la
 * correspondance de champs du jeu (database/data/open_data_media.php). Sans réseau ni base.
 *
 * Résultat : `['ok' => true, 'candidate' => [...]]` ou `['ok' => false, 'reason' => '...']` (motifs : `sans_image`,
 * `sans_identifiant`, `sans_titre`, `hors_open_data`, `hors_france`, `hors_fonds`, `licence_inconnue`, `non_ferroviaire`, `hors_periode`).
 * La licence est lue ici, ligne par ligne quand le jeu la porte par image (Albert-Kahn) : une valeur qu'aucune
 * règle ne reconnaît refuse l'image, jamais de licence présumée.
 */
final class OpenDataNormalizer
{
    /**
     * @param  array<string, mixed>  $dataset
     * @param  array<string, mixed>  $row
     * @return array{ok: true, candidate: array<string, mixed>}|array{ok: false, reason: string}
     */
    public static function normalize(array $dataset, array $row): array
    {
        $f = $dataset['fields'];
        if (($dataset['repair'] ?? null) === 'cp1250-as-cp1252') {
            $row = OpenDataText::repairCp1250($row);
        }
        $get = static function (string $notion) use ($f, $row): ?string {
            $column = $f[$notion] ?? null;
            $value = $column !== null ? ($row[$column] ?? null) : null;
            if ($value === null || is_array($value)) {
                return null;
            }
            $value = trim((string) $value);

            return $value === '' || $value === '*' || $value === '/' ? null : $value;
        };

        if (isset($dataset['require_true']) && ($row[$dataset['require_true']] ?? null) !== true) {
            return ['ok' => false, 'reason' => 'hors_open_data'];
        }
        // Titre qui trahit un document étranger (« Viadotto … [Viaduc … » : titre d'origine italien suivi de sa traduction).
        if (isset($dataset['exclude_title']) && preg_match($dataset['exclude_title'], (string) $get('title')) === 1) {
            return ['ok' => false, 'reason' => 'hors_france'];
        }
        // Colonnes qui doivent porter un motif (ex. École des ponts : le dépôt est l'École, seule source sous Licence Ouverte).
        foreach ($dataset['require_pattern'] ?? [] as $notion => $pattern) {
            if (preg_match($pattern, (string) $get($notion)) !== 1) {
                return ['ok' => false, 'reason' => 'hors_fonds'];
            }
        }
        // Colonnes dont une valeur trahit une vue hors de France (sujet « Monte-Carlo (Monaco) ») : refusée.
        foreach ($dataset['reject_pattern'] ?? [] as $notion => $pattern) {
            if (preg_match($pattern, (string) $get($notion)) === 1) {
                return ['ok' => false, 'reason' => 'hors_france'];
            }
        }
        if (isset($dataset['country']) && $get('country') !== null && $get('country') !== $dataset['country']) {
            return ['ok' => false, 'reason' => 'hors_france'];
        }

        $id = $get('id');
        $title = $get('title');
        if (! empty($dataset['split_dash']) && $id !== null && str_contains($id, ' - ')) {
            [$id, $title] = array_map('trim', explode(' - ', $id, 2));
        }
        $series = null;
        if (($dataset['clean_title'] ?? null) === 'dashes' && $title !== null) {
            // Titres qui portent « lieu : sujet » (Pireneas) : seulement la typographie du site, tirets simples.
            $title = trim(str_replace(["\u{2013}", "\u{2014}"], '-', html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        } elseif (! empty($dataset['clean_title']) && $title !== null) {
            [$title, $series] = OpenDataText::cleanTitle($title);
            if ($title === '') {
                return ['ok' => false, 'reason' => 'sans_titre'];
            }
        }
        if ($id === null || $id === '') {
            return ['ok' => false, 'reason' => 'sans_identifiant'];
        }
        // La licence d'abord : une image « non-diffusable » n'a pas d'adresse, et c'est sa licence qu'il faut rapporter.
        $rights = self::rights($dataset, $get('license'));
        if ($rights === null) {
            return ['ok' => false, 'reason' => 'licence_inconnue'];
        }
        $image = $get('image');
        if ($image === null || preg_match('#^https?://#i', $image) !== 1) {
            return ['ok' => false, 'reason' => 'sans_image'];
        }

        $parts = [];
        foreach ($dataset['rail']['fields'] as $notion) {
            $parts[] = $get($notion);
        }
        if (preg_match($dataset['rail']['pattern'], OpenDataText::withoutStreets(implode(' | ', array_filter($parts)))) !== 1) {
            return ['ok' => false, 'reason' => 'non_ferroviaire'];
        }

        $date = OpenDataText::date($get('date'), (bool) ($dataset['round_years_circa'] ?? false));
        if ($date['year'] !== null && (($dataset['min_year'] ?? null) !== null && $date['year'] < $dataset['min_year'] - 1 || ($dataset['max_year'] ?? null) !== null && $date['year'] > $dataset['max_year'] + 1)) {
            return ['ok' => false, 'reason' => 'hors_periode'];
        }

        [$lat, $lng] = self::point($get('point'), $row);
        $communeRaw = $get('commune');
        if ($communeRaw !== null && ($dataset['commune_split'] ?? '') !== '') {
            $communeRaw = trim(explode($dataset['commune_split'], $communeRaw)[0]);
        }
        // Liste de lieux indexés mêlant commune, canton et arrondissement : la commune est celle que le titre nomme.
        if ($communeRaw !== null && ($dataset['commune_from_list'] ?? '') !== '') {
            $communeRaw = OpenDataText::communeFromList($communeRaw, $title, $dataset['commune_from_list']);
        }
        $commune = $dataset['fixed_commune'] ?? self::commune($communeRaw);
        if (! empty($dataset['upgrade_https'])) {
            $image = (string) preg_replace('#^http://#i', 'https://', $image);
        }
        $thumb = $get('thumb') ?? $image;
        if (($dataset['thumb_suffix'] ?? '') !== '' && preg_match('#/files/[0-9a-f]{16,}$#', $image) === 1) {
            $thumb = $image.$dataset['thumb_suffix'];
        }

        return ['ok' => true, 'candidate' => [
            'id' => $id,
            'inventory' => $get('inventory'),
            'title' => $title,
            'title_alt' => $get('title_alt'),
            'caption' => $get('caption') ?? $series,
            'author' => $get('author'),
            'commune' => $commune,
            'place' => $get('place'),
            'address' => $get('address'),
            'station_hint' => $get('station_hint'),
            'themes' => $get('themes'),
            'subjects' => ($dataset['subjects_evidence'] ?? true) ? $get('subjects') : null,
            'insee' => $get('insee'),
            'source' => $get('source'),
            // Producteur propre à la ligne (`credit_template` : « {repository} » remplacé), sinon celui du jeu.
            'credit' => isset($dataset['credit_template']) ? str_replace('{repository}', (string) ($get('repository') ?? $dataset['credit']), $dataset['credit_template']) : null,
            'date_raw' => $get('date'),
            'captured_at' => $date['captured_at'],
            'date_precision' => $date['precision'],
            'year' => $date['year'],
            'year_to' => $date['year_to'],
            'lat' => $lat,
            'lng' => $lng,
            'image' => $image,
            'thumb' => $thumb,
            'rights' => $rights,
        ]];
    }

    /**
     * Droits de la ligne : bloc fixe du jeu, ou règle qui reconnaît la valeur de la colonne de licence.
     *
     * @param  array<string, mixed>  $dataset
     * @return array<string, mixed>|null
     */
    public static function rights(array $dataset, ?string $value): ?array
    {
        $license = $dataset['license'];
        if (($license['kind'] ?? '') === 'fixed') {
            return $license;
        }
        if ($value === null) {
            return null;
        }
        foreach ($license['map'] as $pattern => $rights) {
            if (preg_match($pattern, $value) === 1) {
                return $rights + ['declared' => $value];
            }
        }

        return null;
    }

    /** Commune sans arrondissement ni complément entre parenthèses (« Paris (VIIe arr.) » devient « Paris »). */
    public static function commune(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) preg_replace('/\s*\(.*?\)\s*/u', ' ', $value));

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{0: ?float, 1: ?float}
     */
    private static function point(?string $raw, array $row): array
    {
        if (isset($row['__lat'], $row['__lng'])) {
            return self::valid((float) $row['__lat'], (float) $row['__lng']);
        }
        if ($raw !== null && preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $raw, $m) === 1) {
            return self::valid((float) $m[1], (float) $m[2]);
        }

        return [null, null];
    }

    /** @return array{0: ?float, 1: ?float} */
    private static function valid(float $lat, float $lng): array
    {
        // La France métropolitaine et les outre-mer rentrent dans cette boîte large ; le reste est une erreur de saisie.
        return abs($lat) <= 90 && abs($lng) <= 180 && ($lat !== 0.0 || $lng !== 0.0) ? [round($lat, 7), round($lng, 7)] : [null, null];
    }
}
