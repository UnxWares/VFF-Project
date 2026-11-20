<?php

namespace App\Services;

use App\Models\Accident;
use App\Models\Era;
use App\Models\Line;
use App\Models\Material;
use App\Services\Materials\NotableUnits;
use Illuminate\Support\Facades\DB;

/**
 * Recherche par nom dans l'encyclopédie : lignes (nom ou code à 6 chiffres), gares (avec ville et
 * département pour distinguer les homonymes), matériels roulants et accidents (nom dans l'une des
 * trois langues, commune ou date). Une seule implémentation pour l'API REST (/api/v1/search,
 * autocomplétion), GraphQL (`search`) et le serveur MCP (outil `search`).
 *
 * Accidents : seuls les accidents publiés sortent (portée PublicAccidents, comme partout ailleurs :
 * un accident masqué ne s'obtient ni par son nom ni par sa date). Une date se cherche en toutes
 * lettres chiffrées : année (`1891`), mois (`07/2013`, `2013-07`) ou jour (`12/07/2013`,
 * `2013-07-12`) ; un accident dont seul le mois ou l'année est connu ne répond pas à un jour précis.
 *
 * Pertinence (lignes, gares et accidents) : correspondance exacte, début du nom, mot du nom (après une espace,
 * un tiret ou une apostrophe), puis le reste ; à pertinence égale, les lignes en service passent
 * avant les lignes fermées, puis ordre alphabétique français. Insensible à la casse et aux accents.
 */
final class CatalogSearch
{
    /** Longueur minimale de la requête (caractères) : en dessous, listes vides. */
    public const MIN_LENGTH = 2;

    public function __construct(private readonly NotableUnits $notableUnits) {}

    /**
     * Lignes : type, slug, name, code ; gares : type, slug, name, city, department_code, lat, lng ;
     * matériels : type, slug, name, kind, builder, notable_unit (rame remarquable trouvée par son surnom, sinon nul) ; accidents : type, slug, name (français), names (fr,
     * en, de), date, date_precision, kind, commune, department_code, lat, lng (nuls sans position).
     *
     * @return array{lines: list<array<string, mixed>>, stations: list<array<string, mixed>>, materials: list<array<string, mixed>>, accidents: list<array<string, mixed>>}
     */
    public function search(mixed $raw, int $lineLimit = 8, int $stationLimit = 8, int $materialLimit = 5, int $accidentLimit = 5): array
    {
        $q = self::normalizeQuery($raw);
        if (mb_strlen($q) < self::MIN_LENGTH) {
            return ['lines' => [], 'stations' => [], 'materials' => [], 'accidents' => []];
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

        // Surnom d'une rame remarquable (« Patrick », « TGV Patrick ») : sa série vient en tête, avec la rame.
        $notable = $materialLimit <= 0 ? [] : $this->notableUnits->matching($q);
        $materials = $materialLimit <= 0 ? [] : Material::query()
            ->where(function ($w) use ($like, $notable): void {
                $w->whereRaw('unaccent(name) ILIKE unaccent(?)', [$like])->orWhereIn('slug', array_keys($notable));
            })
            ->orderByRaw('CASE WHEN slug IN ('.implode(',', array_fill(0, max(1, count($notable)), '?')).') THEN 0 ELSE 1 END', array_keys($notable) ?: [''])
            ->orderBy('id')
            ->limit($materialLimit)
            ->get(['slug', 'name', 'type', 'builder'])
            ->map(fn ($m) => [
                'type' => 'material',
                'slug' => (string) $m->slug,
                'name' => (string) $m->name,
                'kind' => $m->type,
                'builder' => $m->builder,
                'notable_unit' => $notable[$m->slug] ?? null,
            ])->values()->all();

        $accidents = $accidentLimit <= 0 ? [] : $this->accidents($q, $needle, $accidentLimit);

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
            'accidents' => $accidents,
        ];
    }

    /**
     * Accidents publiés dont un nom (français, anglais ou allemand), la commune ou la date correspond.
     * Même pertinence que les gares : exact, début, mot, reste (le reste comprend le lieu et la date
     * lus dans l'identifiant et les dates cherchées par leur chiffre) ; à pertinence égale, ordre
     * alphabétique français du nom, puis date.
     *
     * @return list<array<string, mixed>>
     */
    private function accidents(string $q, string $needle, int $limit): array
    {
        $fields = ["accidents.name->>'fr'", "accidents.name->>'en'", "accidents.name->>'de'", 'accidents.commune_name'];
        $match = fn (string $pattern): array => [
            implode(' OR ', array_map(fn (string $f) => "unaccent(COALESCE({$f}, '')) ILIKE unaccent(?)", $fields)),
            array_fill(0, count($fields), $pattern),
        ];
        [$exactSql, $exactBind] = $match($needle);
        [$prefixSql, $prefixBind] = $match($needle.'%');
        [$containsSql, $containsBind] = $match('%'.$needle.'%');
        $wordSql = [];
        $wordBind = [];
        foreach (['% '.$needle.'%', '%-'.$needle.'%', "%'".$needle.'%', '%’'.$needle.'%'] as $pattern) {
            [$sql, $bind] = $match($pattern);
            $wordSql[] = "({$sql})";
            array_push($wordBind, ...$bind);
        }
        $date = self::dateCondition($q);

        return Accident::query()
            ->withCoordinates()
            ->where(function ($w) use ($containsSql, $containsBind, $needle, $date): void {
                $w->whereRaw("({$containsSql})", $containsBind)
                    // L'identifiant commence par la date (2013-07-12-bretigny-sur-orge) : « 2013-07 » le trouve.
                    ->orWhereRaw('unaccent(accidents.slug) ILIKE unaccent(?)', ['%'.$needle.'%']);
                if ($date !== null) {
                    $w->orWhereRaw("({$date[0]})", $date[1]);
                }
            })
            ->orderByRaw(
                "CASE WHEN {$exactSql} THEN 0 WHEN {$prefixSql} THEN 1 WHEN ".implode(' OR ', $wordSql).' THEN 2 ELSE 3 END',
                [...$exactBind, ...$prefixBind, ...$wordBind],
            )
            ->orderByRaw("COALESCE(accidents.name->>'fr', accidents.slug) COLLATE \"fr-x-icu\"")
            ->orderBy('accidents.occurred_on')
            ->orderBy('accidents.id')
            ->limit($limit)
            ->get()
            ->map(fn (Accident $a) => [
                'type' => 'accident',
                'slug' => $a->slug,
                'name' => $a->nameIn('fr'),
                'names' => [
                    'fr' => $a->nameIn('fr'),
                    'en' => $a->nameIn('en'),
                    'de' => $a->nameIn('de'),
                ],
                'date' => $a->occurred_on->toDateString(),
                'date_precision' => $a->date_precision->value,
                'kind' => $a->kind->value,
                'commune' => $a->commune_name,
                'department_code' => $a->department_code,
                'lat' => $a->lat,
                'lng' => $a->lng,
            ])->values()->all();
    }

    /**
     * Condition SQL d'une requête qui est une date : année (`1891`), mois (`07/2013`, `2013-07`) ou
     * jour (`12/07/2013`, `2013-07-12`, séparateurs `/`, `-` ou `.`), sinon null. Une date impossible
     * (31/02/2013) ne correspond à rien. Un jour précis ne retient que les accidents datés au jour,
     * un mois ceux datés au jour ou au mois : les autres portent une date par défaut (1er du mois,
     * 1er janvier) qui n'est pas leur date.
     *
     * @return array{0: string, 1: list<string|int>}|null
     */
    private static function dateCondition(string $q): ?array
    {
        $q = trim($q);
        $never = ['FALSE', []];

        if (preg_match('/^\d{4}$/', $q) === 1) {
            return ['EXTRACT(YEAR FROM accidents.occurred_on) = ?', [(int) $q]];
        }
        if (preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/', $q, $m) === 1) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{4})[\/.-](\d{1,2})[\/.-](\d{1,2})$/', $q, $m) === 1) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[\/.-](\d{4})$/', $q, $m) === 1) {
            [$month, $year, $day] = [(int) $m[1], (int) $m[2], null];
        } elseif (preg_match('/^(\d{4})[\/.-](\d{1,2})$/', $q, $m) === 1) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], null];
        } else {
            return null;
        }

        if ($month < 1 || $month > 12 || ($day !== null && ! checkdate($month, $day, $year))) {
            return $never;
        }
        if ($day !== null) {
            return ["accidents.occurred_on = ? AND accidents.date_precision = 'day'", [sprintf('%04d-%02d-%02d', $year, $month, $day)]];
        }

        return [
            "accidents.occurred_on BETWEEN ? AND ? AND accidents.date_precision IN ('day', 'month')",
            [sprintf('%04d-%02d-01', $year, $month), date('Y-m-t', (int) mktime(0, 0, 0, $month, 1, $year))],
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
