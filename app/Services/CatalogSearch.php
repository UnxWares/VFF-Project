<?php

namespace App\Services;

use App\Models\Era;
use App\Models\Line;
use App\Models\Material;
use Illuminate\Support\Facades\DB;

/**
 * Recherche par nom dans l'encyclopédie : lignes (nom ou code à 6 chiffres), gares (avec ville et
 * département pour distinguer les homonymes) et matériels roulants. Une seule implémentation pour
 * l'API REST (/api/v1/search, autocomplétion) et le serveur MCP (outil `search`).
 *
 * Pertinence (lignes et gares) : correspondance exacte, début du nom, mot du nom (après une espace,
 * un tiret ou une apostrophe), puis le reste ; à pertinence égale, les lignes en service passent
 * avant les lignes fermées, puis ordre alphabétique français. Insensible à la casse et aux accents.
 */
final class CatalogSearch
{
    /** Longueur minimale de la requête (caractères) : en dessous, listes vides. */
    public const MIN_LENGTH = 2;

    /**
     * Lignes : type, slug, name, code ; gares : type, slug, name, city, department_code, lat, lng ;
     * matériels : type, slug, name, kind, builder.
     *
     * @return array{lines: list<array<string, mixed>>, stations: list<array<string, mixed>>, materials: list<array<string, mixed>>}
     */
    public function search(mixed $raw, int $lineLimit = 8, int $stationLimit = 8, int $materialLimit = 5): array
    {
        $q = self::normalizeQuery($raw);
        if (mb_strlen($q) < self::MIN_LENGTH) {
            return ['lines' => [], 'stations' => [], 'materials' => []];
        }

        // Jokers LIKE (% _) et caractère d'échappement (\) pris au pied de la
        // lettre : « 100% » cherche « 100% », « %% » ne renvoie pas tout.
        $needle = addcslashes($q, '\\%_');
        $like = '%'.$needle.'%';

        // Pertinence (0 = meilleure) : correspondance exacte, début, mot du nom (après un espace,
        // un tiret ou une apostrophe), puis le reste. Même gabarit pour les lignes (nom ou code,
        // espaces du code ignorés : « 420000 » trouve « 420 000 ») et les gares.
        $exact = $needle;
        $prefix = $needle.'%';
        $words = ['% '.$needle.'%', '%-'.$needle.'%', "%'".$needle.'%', '%’'.$needle.'%'];
        $wordSql = implode(' OR ', array_fill(0, count($words), 'unaccent(%1$s) ILIKE unaccent(?)'));
        $noSpace = str_replace(' ', '', $needle);

        // Ligne « en service » : au moins une portion en service à l'ère courante.
        $eraId = Era::where('is_current', true)->value('id') ?? Era::orderByDesc('year')->value('id');

        $lineRank = 'CASE
            WHEN unaccent(lines.name) ILIKE unaccent(?) OR unaccent(replace(lines.code, \' \', \'\')) ILIKE unaccent(?) THEN 0
            WHEN unaccent(lines.name) ILIKE unaccent(?) OR unaccent(replace(lines.code, \' \', \'\')) ILIKE unaccent(?) THEN 1
            WHEN '.sprintf($wordSql, 'lines.name').' THEN 2
            ELSE 3 END';
        $lines = $lineLimit <= 0 ? [] : Line::query()
            ->where(function ($w) use ($like, $noSpace) {
                $w->whereRaw('unaccent(name) ILIKE unaccent(?)', [$like])
                    ->orWhereRaw('unaccent(code) ILIKE unaccent(?)', [$like])
                    ->orWhereRaw("unaccent(replace(code, ' ', '')) ILIKE unaccent(?)", ['%'.$noSpace.'%']);
            })
            ->orderByRaw($lineRank, [$exact, $noSpace, $prefix, $noSpace.'%', ...$words])
            ->orderByRaw('CASE WHEN EXISTS (
                SELECT 1 FROM line_segments s
                WHERE s.line_id = lines.id AND s.era_id = ? AND s.status IN (\'in_service\', \'single_track\')
            ) THEN 0 ELSE 1 END', [$eraId])
            ->orderByRaw('name COLLATE "fr-x-icu"')
            ->orderBy('id')
            ->limit($lineLimit)
            ->get(['slug', 'name', 'code'])
            ->map(fn ($l) => [
                'type' => 'line',
                'slug' => (string) $l->slug,
                'name' => (string) $l->name,
                'code' => $l->code,
            ])->values()->all();

        $stations = $stationLimit <= 0 ? [] : DB::select(
            'SELECT slug, name, city, department_code,
                   ST_X(geom) AS lng, ST_Y(geom) AS lat
            FROM stations
            WHERE unaccent(name) ILIKE unaccent(?)
            ORDER BY
              CASE
                WHEN unaccent(name) ILIKE unaccent(?) THEN 0
                WHEN unaccent(name) ILIKE unaccent(?) THEN 1
                WHEN '.sprintf($wordSql, 'name').' THEN 2
                ELSE 3 END,
              name COLLATE "fr-x-icu",
              id
            LIMIT ?',
            [$like, $exact, $prefix, ...$words, $stationLimit],
        );

        $materials = $materialLimit <= 0 ? [] : Material::query()
            ->whereRaw('unaccent(name) ILIKE unaccent(?)', [$like])
            ->limit($materialLimit)
            ->get(['slug', 'name', 'type', 'builder'])
            ->map(fn ($m) => [
                'type' => 'material',
                'slug' => (string) $m->slug,
                'name' => (string) $m->name,
                'kind' => $m->type,
                'builder' => $m->builder,
            ])->values()->all();

        return [
            'lines' => $lines,
            'stations' => array_values(array_map(fn ($r) => [
                'type' => 'station',
                'slug' => (string) $r->slug,
                'name' => (string) $r->name,
                'city' => $r->city,
                'department_code' => $r->department_code,
                'lat' => (float) $r->lat,
                'lng' => (float) $r->lng,
            ], $stations)),
            'materials' => $materials,
        ];
    }

    /**
     * Requête utilisable telle quelle par PostgreSQL : chaîne seulement (?q[]=…
     * provoquait « Array to string conversion »), UTF-8 valide et sans caractère
     * de contrôle (l'octet nul ou un octet invalide faisaient échouer la requête SQL).
     */
    public static function normalizeQuery(mixed $raw): string
    {
        if (! is_string($raw)) {
            return '';
        }
        $q = mb_scrub($raw, 'UTF-8');

        return trim((string) preg_replace('/[\x{0000}-\x{001F}\x{007F}]+/u', ' ', $q));
    }
}
