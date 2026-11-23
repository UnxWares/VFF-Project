<?php

declare(strict_types=1);

namespace App\Services\Heritage;

use App\Support\FrenchDepartments;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * « Patrimoine ferroviaire protégé » : les bâtiments voyageurs, ouvrages de gare et ouvrages d'art (viaducs, ponts,
 * tunnels) classés ou inscrits aux monuments historiques ou labellisés « Architecture contemporaine remarquable ».
 * Page `/heritage` et `GET /api/v1/heritage`. Lecture seule, résultat gardé une heure.
 *
 * Deux tables lues, toutes deux alimentées par des commandes d'import (jamais écrites ici) :
 *   - `station_heritage` (vff:import-station-heritage) : bâtiments et ouvrages de gare, avec architecte, construction,
 *     style, notice Mérimée et image libre ; les objets seulement « recensés à l'Inventaire » ne sont pas repris, ils
 *     ne sont ni protégés ni labellisés ;
 *   - `structures` (vff:import-structures) : ouvrages d'art protégés, avec la ligne qui les porte ; `structure_heritage`
 *     (vff:import-structure-heritage), quand elle existe, y ajoute l'arrêté, les auteurs et la datation de la notice
 *     Mérimée rapprochée de l'ouvrage (et les ouvrages que Wikidata ne disait pas protégés). Wikidata ne donne ni
 *     architecte ni style ; la commune est celle dont le centre est le plus proche.
 *
 * Tant qu'une table manque, sa part est vide (aucune erreur).
 */
final class HeritageIndex
{
    public const CACHE_KEY = 'heritage_index:v1';

    /** Protections affichées, de la plus forte à la plus faible. */
    public const PROTECTIONS = ['classified', 'registered', 'acr'];

    /** Objets : le bâtiment voyageurs, l'ouvrage de gare, puis les ouvrages d'art. */
    public const OBJECTS = ['building', 'works', 'viaduct', 'bridge', 'tunnel'];

    /** Époques de construction (ordre d'affichage) ; `unknown` : aucune date ni siècle connus. */
    public const ERAS = ['before_1850', '1850_1899', '1900_1944', '1945_1999', 'from_2000', 'unknown'];

    public const SORTS = ['name', 'protection_date', 'built'];

    private const MERIMEE_URL = MerimeeSource::NOTICE_URL;

    /**
     * @return array{items: list<array<string, mixed>>, facets: array<string, mixed>, attribution: string}
     */
    public function get(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, fn (): array => $this->build());
    }

    /** @return array{items: list<array<string, mixed>>, facets: array<string, mixed>, attribution: string} */
    public function build(): array
    {
        $items = $this->mergeArchitectSpellings(array_merge($this->stationItems(), $this->structureItems()));
        usort($items, fn (array $a, array $b): int => $this->byName($a, $b));

        return ['items' => $items, 'facets' => $this->facets($items), 'attribution' => StationHeritage::ATTRIBUTION];
    }

    /**
     * Filtre et trie une liste d'objets (même logique que la page, côté navigateur).
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array{protection?: ?string, object?: ?string, style?: ?string, architect?: ?string, region?: ?string, era?: ?string}  $f
     * @return list<array<string, mixed>>
     */
    public static function filter(array $items, array $f, string $sort = 'name'): array
    {
        $out = array_values(array_filter($items, fn (array $i): bool => (empty($f['protection']) || $i['protection'] === $f['protection'])
            && (empty($f['object']) || $i['object'] === $f['object'])
            && (empty($f['style']) || $i['style'] === $f['style'])
            && (empty($f['architect']) || in_array($f['architect'], $i['architects'], true))
            && (empty($f['region']) || $i['region'] === $f['region'])
            && (empty($f['era']) || $i['era'] === $f['era'])));

        $byName = fn (array $a, array $b): int => self::foldName($a['name']) <=> self::foldName($b['name']);
        usort($out, match ($sort) {
            // Les plus récentes d'abord, dates inconnues en dernier.
            'protection_date' => fn (array $a, array $b): int => [$a['protection_date'] === null, $b['protection_date'] ?? ''] <=> [$b['protection_date'] === null, $a['protection_date'] ?? ''] ?: $byName($a, $b),
            // Les plus anciennes d'abord, dates inconnues en dernier.
            'built' => fn (array $a, array $b): int => [$a['built']['year'] === null, $a['built']['year'] ?? 0] <=> [$b['built']['year'] === null, $b['built']['year'] ?? 0] ?: $byName($a, $b),
            default => $byName,
        });

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function stationItems(): array
    {
        if (! StationHeritage::ready()) {
            return [];
        }
        try {
            $rows = DB::select(
                'SELECT h.id, h.element, h.protection, h.protection_date, h.scope, h.title, h.architects, h.built_from, h.built_to, h.built_period,
                        h.style, h.ref, h.ref_url, h.image_url, h.image_source_url, h.image_license, h.image_author, h.image_file,
                        s.slug, s.name, s.city, s.department_code, ST_Y(s.geom) AS lat, ST_X(s.geom) AS lng
                   FROM station_heritage h JOIN stations s ON s.id = h.station_id
                  WHERE h.protection IN (\'classified\', \'registered\', \'acr\')'
            );
        } catch (\Throwable) {
            return [];
        }

        $items = [];
        foreach ($rows as $r) {
            $architects = [];
            foreach ((array) json_decode((string) $r->architects, true) as $a) {
                if (is_array($a) && ($a['name'] ?? '') !== '') {
                    $architects[] = (string) $a['name'];
                }
            }
            $from = $r->built_from !== null ? (int) $r->built_from : null;
            $items[] = $this->item(
                id: 'st'.$r->id, object: (string) $r->element, name: (string) $r->name, title: $r->title,
                protection: (string) $r->protection, date: $r->protection_date, scope: $r->scope,
                architects: $architects, from: $from, to: $r->built_to !== null ? (int) $r->built_to : null, period: $r->built_period,
                style: $r->style, commune: $r->city, department: $r->department_code,
                reference: (string) $r->ref, referenceUrl: $r->ref_url,
                image: $r->image_url !== null ? ['url' => $r->image_url, 'source_url' => $r->image_source_url, 'license' => $r->image_license, 'author' => $r->image_author] : null,
                station: ['slug' => (string) $r->slug, 'name' => (string) $r->name], line: null, lat: (float) $r->lat, lng: (float) $r->lng,
            );
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function structureItems(): array
    {
        $detail = Schema::hasTable('structure_heritage');
        try {
            // La notice Mérimée rapprochée la plus protégée de l'ouvrage, quand la table existe.
            $rows = DB::select(
                'SELECT st.id, st.kind, st.name, st.merimee_ref, st.image, st.built_on, st.lat, st.lon,
                        '.($detail ? 'COALESCE(NULLIF(st.heritage, \'protected\'), sh.protection) AS heritage, sh.protection_date, sh.scope, sh.architects, sh.built_from AS sh_from,
                        sh.built_to AS sh_to, sh.built_period AS sh_period, sh.ref AS sh_ref, sh.ref_url AS sh_ref_url, sh.title AS sh_title,' : 'st.heritage, NULL AS protection_date, NULL AS scope, NULL AS architects, NULL AS sh_from,
                        NULL AS sh_to, NULL AS sh_period, NULL AS sh_ref, NULL AS sh_ref_url, NULL AS sh_title,').'
                        c.nom AS commune, c.departement,
                        l.slug AS line_slug, l.name AS line_name
                   FROM structures st
                   '.($detail ? 'LEFT JOIN LATERAL (SELECT * FROM structure_heritage h WHERE h.structure_id = st.id
                                       ORDER BY CASE h.protection WHEN \'classified\' THEN 1 WHEN \'registered\' THEN 2 ELSE 3 END, (h.architects IS NULL), h.ref LIMIT 1) sh ON true' : '').'
                   LEFT JOIN LATERAL (SELECT nom, departement FROM communes WHERE type = \'commune-actuelle\'
                                       ORDER BY geom <-> ST_SetSRID(ST_MakePoint(st.lon, st.lat), 4326) LIMIT 1) c ON true
                   LEFT JOIN LATERAL (SELECT l2.slug, l2.name FROM line_structures ls JOIN lines l2 ON l2.id = ls.line_id
                                       WHERE ls.structure_id = st.id ORDER BY ls.offset_m, ls.line_id LIMIT 1) l ON true
                  WHERE '.($detail ? '(st.heritage IN (\'classified\', \'registered\') OR sh.protection IN (\'classified\', \'registered\', \'acr\'))' : 'st.heritage IN (\'classified\', \'registered\')')
            );
        } catch (\Throwable) {
            return [];
        }

        $items = [];
        foreach ($rows as $r) {
            $img = json_decode((string) $r->image, true);
            $free = is_array($img) && ($img['rights_status'] ?? null) === 'open_license' && ! empty($img['thumb_url']);
            $architects = [];
            foreach ((array) json_decode((string) $r->architects, true) as $a) {
                if (is_array($a) && ($a['name'] ?? '') !== '') {
                    $architects[] = (string) $a['name'];
                }
            }
            $from = $r->built_on !== null ? (int) substr((string) $r->built_on, 0, 4) : ($r->sh_from !== null ? (int) $r->sh_from : null);
            $reference = $r->merimee_ref ?? $r->sh_ref;
            $items[] = $this->item(
                id: 'ou'.$r->id, object: in_array($r->kind, ['viaduct', 'bridge', 'tunnel'], true) ? (string) $r->kind : 'bridge',
                name: (string) ($r->name ?? ''), title: $r->sh_title, protection: (string) $r->heritage, date: $r->protection_date, scope: $r->scope,
                architects: $architects, from: $from, to: $r->sh_to !== null ? (int) $r->sh_to : null, period: $from === null ? $r->sh_period : null,
                style: null, commune: $r->commune, department: $r->departement,
                reference: $reference, referenceUrl: $r->merimee_ref !== null ? self::MERIMEE_URL.$r->merimee_ref : $r->sh_ref_url,
                image: $free ? ['url' => $img['thumb_url'], 'source_url' => $img['source_url'] ?? null, 'license' => $img['license'] ?? null, 'author' => $img['author'] ?? null] : null,
                station: null, line: $r->line_slug !== null ? ['slug' => (string) $r->line_slug, 'name' => (string) $r->line_name] : null,
                lat: (float) $r->lat, lng: (float) $r->lon,
            );
        }

        return $items;
    }

    /**
     * @param  list<string>  $architects
     * @param  ?array<string, mixed>  $image
     * @param  ?array<string, string>  $station
     * @param  ?array<string, string>  $line
     * @return array<string, mixed>
     */
    private function item(string $id, string $object, string $name, ?string $title, string $protection, mixed $date, ?string $scope, array $architects,
        ?int $from, ?int $to, ?string $period, ?string $style, ?string $commune, ?string $department, ?string $reference, ?string $referenceUrl,
        ?array $image, ?array $station, ?array $line, float $lat, float $lng): array
    {
        $dep = $department !== null ? FrenchDepartments::find($department) : null;
        $year = $from ?? self::periodYear($period);

        return [
            'id' => $id,
            'object' => $object,
            'name' => $name,
            'title' => $title,
            'protection' => $protection,
            'protection_date' => $date !== null ? substr((string) $date, 0, 10) : null,
            'scope' => $scope,
            'architects' => $architects,
            'built' => ['from' => $from, 'to' => $to, 'period' => $period, 'year' => $year],
            'era' => self::era($year),
            'style' => self::style($style),
            'commune' => $commune,
            'department' => $department,
            'region' => $dep[1] ?? null,
            'reference' => $reference !== null ? ['id' => $reference, 'url' => $referenceUrl] : null,
            'image' => $image,
            'station' => $station,
            'line' => $line,
            'lat' => round($lat, 5),
            'lng' => round($lng, 5),
        ];
    }

    /**
     * Une même personne écrite de plusieurs façons (« Juste Lisch », « Juste LISCH ») ne fait qu'un architecte : les
     * graphies qui ne diffèrent que par la casse, les accents, les tirets ou les espaces sont fusionnées, la graphie
     * gardée est celle qui n'est pas toute en majuscules (sinon la première rencontrée).
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function mergeArchitectSpellings(array $items): array
    {
        $canonical = [];
        foreach ($items as $i) {
            foreach ($i['architects'] as $name) {
                $key = trim((string) preg_replace('/[^a-z0-9]+/', ' ', self::foldName($name)));
                if (! isset($canonical[$key]) || (mb_strtoupper($canonical[$key]) === $canonical[$key] && mb_strtoupper($name) !== $name)
                    || (preg_match('/[a-z]/u', $canonical[$key]) && preg_match('/[A-Z]{2}/u', $canonical[$key]) && ! preg_match('/[A-Z]{2}/u', $name))) {
                    $canonical[$key] = $name;
                }
            }
        }
        foreach ($items as &$i) {
            $i['architects'] = array_values(array_unique(array_map(
                fn (string $n): string => $canonical[trim((string) preg_replace('/[^a-z0-9]+/', ' ', self::foldName($n)))], $i['architects'])));
        }

        return $items;
    }

    /** Année de construction connue, sinon estimée au milieu de la période que donne Mérimée (« 2e quart 19e siècle »). */
    public static function periodYear(?string $period): ?int
    {
        if ($period === null || ! preg_match('/(?:(\d)(?:er|e) (quart|moitié) )?(\d{1,2})e siècle/u', $period, $m)) {
            return null;
        }
        $base = ((int) $m[3] - 1) * 100;

        return match ($m[2]) {
            'quart' => $base + ((int) $m[1] - 1) * 25 + 12,
            'moitié' => $base + ((int) $m[1] - 1) * 50 + 25,
            default => $base + 50,
        };
    }

    public static function era(?int $year): string
    {
        return match (true) {
            $year === null => 'unknown',
            $year < 1850 => 'before_1850',
            $year < 1900 => '1850_1899',
            $year < 1945 => '1900_1944',
            $year < 2000 => '1945_1999',
            default => 'from_2000',
        };
    }

    /** « architecture Art nouveau » devient « Art nouveau », « néo-Renaissance » devient « Néo-Renaissance ». */
    private static function style(?string $style): ?string
    {
        $s = trim((string) $style);
        $s = (string) preg_replace('/^architecture\s+/iu', '', $s);

        return $s === '' ? null : mb_strtoupper(mb_substr($s, 0, 1)).mb_substr($s, 1);
    }

    /**
     * Valeurs de chaque filtre avec leur nombre d'objets, tirées de la liste complète.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function facets(array $items): array
    {
        $count = fn (string $key): array => array_count_values(array_filter(array_column($items, $key), fn ($v): bool => $v !== null));
        $named = function (array $counts): array {
            uksort($counts, fn ($a, $b): int => self::foldName((string) $a) <=> self::foldName((string) $b));

            return array_map(fn ($name, $n): array => ['name' => (string) $name, 'count' => $n], array_keys($counts), array_values($counts));
        };
        $architects = [];
        foreach ($items as $i) {
            foreach ($i['architects'] as $a) {
                $architects[$a] = ($architects[$a] ?? 0) + 1;
            }
        }
        $ordered = fn (array $order, array $counts): array => array_map(fn (string $k): array => ['key' => $k, 'count' => $counts[$k]], array_values(array_filter($order, fn (string $k): bool => isset($counts[$k]))));

        return [
            'protections' => $ordered(self::PROTECTIONS, $count('protection')),
            'objects' => $ordered(self::OBJECTS, $count('object')),
            'eras' => $ordered(self::ERAS, $count('era')),
            'styles' => $named($count('style')),
            'regions' => $named($count('region')),
            'architects' => $named($architects),
        ];
    }

    private function byName(array $a, array $b): int
    {
        return self::foldName($a['name']) <=> self::foldName($b['name']);
    }

    private static function foldName(string $s): string
    {
        return strtolower(Str::ascii($s));
    }
}
