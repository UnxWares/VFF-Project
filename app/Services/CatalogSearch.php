<?php

namespace App\Services;

use App\Models\Accident;
use App\Models\Era;
use App\Models\Line;
use App\Models\Material;
use App\Services\Materials\NotableUnits;
use App\Support\Search\SearchFields;
use App\Support\Search\SearchQuery;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
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

    /** En dessous de ce nombre de fiches trouvées, la liste est complétée par des fiches approchées. */
    public const FUZZY_BELOW = 3;

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

        $sq = SearchQuery::parse($q);
        if ($sq->isEmpty() && self::dateCondition($q) === null) {
            return ['lines' => [], 'stations' => [], 'materials' => [], 'accidents' => []];
        }
        $digits = $sq->digits();

        // Ligne « en service » : au moins une portion en service à l'ère courante.
        $eraId = Era::where('is_current', true)->value('id') ?? Era::orderByDesc('year')->value('id');

        $lines = $lineLimit <= 0 ? [] : $this->ranked(
            Line::query()->toBase(),
            $sq,
            SearchFields::keys('line', 'lines.'),
            // Code à 6 chiffres, espaces ignorées : « 570 000 », « 570000 » et « 5700 » trouvent 570000.
            $digits !== null && strlen($digits) >= 3 ? ["replace(COALESCE(lines.code, ''), ' ', '') LIKE ?", [$digits.'%'], "replace(COALESCE(lines.code, ''), ' ', '') = ?", [$digits]] : null,
            [["CASE WHEN EXISTS (
                SELECT 1 FROM line_segments s
                WHERE s.line_id = lines.id AND s.era_id = ? AND s.status IN ('in_service', 'single_track')
            ) THEN 0 ELSE 1 END", [$eraId]], ['lines.name COLLATE "fr-x-icu"', []], ['lines.id', []]],
            ['lines.slug', 'lines.name', 'lines.code'],
            $lineLimit,
        )->map(fn ($l) => [
            'type' => 'line',
            'slug' => (string) $l->slug,
            'name' => (string) $l->name,
            'code' => $l->code,
            'approximate' => (bool) $l->approximate,
        ])->values()->all();

        // Gare : code UIC (début, 4 chiffres au moins) ou numéro de département (« 44 », « 2A », « 971 »).
        $stationExtra = null;
        if ($digits !== null && strlen($digits) >= 4) {
            $stationExtra = ['stations.uic_code LIKE ?', [$digits.'%'], 'stations.uic_code = ?', [$digits]];
        } elseif (preg_match('/^(\d{2,3}|2[ab])$/i', trim($q)) === 1) {
            $stationExtra = ['stations.department_code = ?', [strtoupper(trim($q))], 'FALSE', []];
        }
        $stations = $stationLimit <= 0 ? [] : $this->ranked(
            DB::table('stations'),
            $sq,
            SearchFields::keys('station', 'stations.'),
            $stationExtra,
            // À pertinence égale : gares ouvertes aux voyageurs, puis les plus desservies, puis l'ordre alphabétique.
            [["CASE WHEN stations.status = 'active' THEN 0 ELSE 1 END", []],
                ['(SELECT COUNT(*) FROM station_lines sl WHERE sl.station_id = stations.id) DESC', []],
                ['stations.name COLLATE "fr-x-icu"', []], ['stations.id', []]],
            ['stations.slug', 'stations.name', 'stations.city', 'stations.department_code', DB::raw('ST_X(stations.geom) AS lng'), DB::raw('ST_Y(stations.geom) AS lat')],
            $stationLimit,
        )->all();

        // Surnom d'une rame remarquable (« Patrick », « TGV Patrick ») : sa série vient en tête, avec la rame.
        $notable = $materialLimit <= 0 ? [] : $this->notableUnits->matching($q);
        $materials = $materialLimit <= 0 ? [] : $this->ranked(
            Material::query()->toBase(),
            $sq,
            SearchFields::keys('material', 'materials.'),
            $notable === [] ? null : ['materials.slug IN ('.implode(',', array_fill(0, count($notable), '?')).')', array_keys($notable), 'materials.slug IN ('.implode(',', array_fill(0, count($notable), '?')).')', array_keys($notable)],
            [['materials.name COLLATE "fr-x-icu"', []], ['materials.id', []]],
            ['materials.slug', 'materials.name', 'materials.type', 'materials.builder'],
            $materialLimit,
        )->map(fn ($m) => [
            'type' => 'material',
            'slug' => (string) $m->slug,
            'name' => (string) $m->name,
            'kind' => $m->type,
            'builder' => $m->builder,
            'notable_unit' => $notable[$m->slug] ?? null,
            'approximate' => (bool) $m->approximate,
        ])->values()->all();

        $accidents = $accidentLimit <= 0 ? [] : $this->accidents($q, $sq, $accidentLimit);

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
                'approximate' => (bool) $r->approximate,
            ], $stations)),
            'materials' => $materials,
            'accidents' => $accidents,
        ];
    }

    /**
     * Accidents publiés dont un nom (français, anglais ou allemand) ou la commune correspond, ou, pour une requête
     * qui est une date, dont la date correspond. Même pertinence que les gares (sur le nom français) ; à pertinence égale, ordre alphabétique
     * français du nom, puis date.
     *
     * @return list<array<string, mixed>>
     */
    private function accidents(string $q, SearchQuery $sq, int $limit): array
    {
        // Une date ne cherche que par la date (« 01/03/1950 » ne doit pas trouver l'accident du 1er mars par ses chiffres).
        $date = self::dateCondition($q);
        $rows = $this->ranked(
            Accident::query()->withCoordinates(),
            $date === null ? $sq : SearchQuery::parse(''),
            SearchFields::keys('accident', 'accidents.'),
            $date === null ? null : [$date[0], $date[1], 'FALSE', []],
            [["COALESCE(accidents.name->>'fr', accidents.slug) COLLATE \"fr-x-icu\"", []], ['accidents.occurred_on', []], ['accidents.id', []]],
            [],
            $limit,
        );

        return $rows->map(fn (Accident $a) => [
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
            'approximate' => (bool) $a->getAttribute('approximate'),
        ])->values()->all();
    }

    /**
     * Fiches trouvées par SearchQuery, classées (rang du nom, puis `$tieBreak`), puis, s'il en manque, complétées
     * par les plus ressemblantes (fautes de frappe), marquées `approximate`.
     *
     * @template TBuilder of QueryBuilder|EloquentBuilder
     *
     * @param  TBuilder  $base
     * @param  array{0: string, 1: string}  $keys  clés SQL du nom et de tous les noms (SearchFields::keys)
     * @param  array{0: string, 1: list<mixed>, 2: string, 3: list<mixed>}|null  $extra  autre correspondance (code, date) et sa condition d'égalité (rang 0)
     * @param  list<array{0: string, 1: list<mixed>}>  $tieBreak
     * @param  list<mixed>  $columns  colonnes lues (vide : celles déjà choisies par `$base`)
     * @return Collection<int, mixed>
     */
    private function ranked(QueryBuilder|EloquentBuilder $base, SearchQuery $sq, array $keys, ?array $extra, array $tieBreak, array $columns, int $limit): Collection
    {
        [$nameKey, $allKey] = $keys;
        [$match, $matchBind] = $sq->matchSql($allKey, anywhere: true);
        [$rank, $rankBind] = $sq->rankSql($nameKey, $allKey);
        $exact = (clone $base)
            ->where(function ($w) use ($match, $matchBind, $extra): void {
                $w->whereRaw("({$match})", $matchBind);
                if ($extra !== null) {
                    $w->orWhereRaw("({$extra[0]})", $extra[1]);
                }
            })
            ->orderByRaw($extra === null ? $rank : "CASE WHEN {$extra[2]} THEN 0 ELSE {$rank} END", [...($extra[3] ?? []), ...$rankBind]);
        foreach ($tieBreak as [$sql, $bind]) {
            $exact->orderByRaw($sql, $bind);
        }
        $select = fn ($b, bool $approximate) => $columns === []
            ? $b->addSelect(DB::raw(($approximate ? 'TRUE' : 'FALSE').' AS approximate'))
            : $b->select([...$columns, DB::raw(($approximate ? 'TRUE' : 'FALSE').' AS approximate')]);
        $found = $select($exact, false)->limit($limit)->get();

        // Fautes de frappe : seulement quand la correspondance exacte ne donne rien ou peu.
        if ($found->count() >= min($limit, self::FUZZY_BELOW) || ! $sq->canBeFuzzy()) {
            return $found;
        }
        // Fautes de frappe cherchées dans le nom seul (SearchQuery::where).
        [$fuzzy, $fuzzyBind] = $sq->fuzzySql($nameKey);
        [$order, $orderBind] = $sq->fuzzyOrderSql($nameKey);
        $approx = (clone $base)->whereRaw($fuzzy, $fuzzyBind)->whereRaw("NOT ({$match})", $matchBind)
            ->when($extra !== null, fn ($b) => $b->whereRaw("NOT COALESCE(({$extra[0]}), FALSE)", $extra[1]))
            ->orderByRaw($order, $orderBind);
        foreach ($tieBreak as [$sql, $bind]) {
            $approx->orderByRaw($sql, $bind);
        }

        return $found->concat($select($approx, true)->limit($limit - $found->count())->get());
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
    public static function dateCondition(string $q): ?array
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
