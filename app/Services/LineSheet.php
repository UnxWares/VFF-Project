<?php

namespace App\Services;

use App\Models\Line;
use App\Models\LineMaterialPeriod;
use App\Services\Signalling\SignallingRanges;
use App\Support\Advocacy;
use App\Support\FrenchGrammar;
use App\Support\Lgv;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fiche complète d'une ligne, telle que la page /lines/{slug} la montre (identité, chiffres clés,
 * électrification, signalisation, phases datées, chronique, gares, sections nommées, sources) :
 * une seule construction pour la page web (LineController::show) et l'API publique
 * (GET /api/v1/lines/{line}, GraphQL `Line.sheet`). Lecture seule.
 */
final class LineSheet
{
    /** Statuts portant du trafic (cf. SegmentStatus::isActive / NetworkStats). */
    private const ACTIVE_STATUSES = "('in_service','single_track')";

    /**
     * Clés de la fiche que la page web n'affiche pas (build($line, forPage: true)) : `segments` (la carte
     * de la fiche dessine `portions`), `events` (la chronique les reprend, avec leur portion) et
     * `era_summary` (le tableau des décennies lit `decades`). L'API REST et GraphQL les donnent toujours.
     */
    public const PAGE_OMITTED = ['segments', 'events', 'era_summary'];

    /** Gestionnaires d'infrastructure (Wikidata) : jamais « exploitants » des trains, avec leurs années d'existence. */
    public const INFRASTRUCTURE_MANAGERS = [
        'Q1071978' => ['name' => 'Réseau ferré de France', 'from' => 1997, 'to' => 2014],
        'Q21605526' => ['name' => 'SNCF Réseau', 'from' => 2015, 'to' => null],
    ];

    /**
     * Fiche détaillée d'une ligne ferroviaire (props de la page Lines/Show).
     *
     * Stratégie :
     *   - Ère affichée = ère COURANTE si la ligne y a des portions, sinon la
     *     décennie la plus récente (par ANNÉE - les id d'ères ne suivent pas la
     *     chronologie : 1990-2030 = 1-5, 1900-1980 = 6-14 ; l'ancien
     *     `max('era_id')` aurait affiché 1980 au lieu de 2030).
     *   - Géométries de l'ère affichée en UNE requête (avant : 1 requête par
     *     segment) + synthèse par décennie (km en service / fermés / déposés,
     *     trafic dominant) pour la frise « évolution ».
     *   - Événements lus bruts : type (nouveaux types tolérés), précision de
     *     date, portion (section_from → section_to), source.
     *
     * `$forPage` : version de la page web (LineController::show), sans ce que la page n'affiche pas
     * (PAGE_OMITTED : géométrie de l'ère affichée, liste brute des événements, synthèse par ère), soit
     * environ 500 Ko de moins sur la LGV Sud-Est. L'API et GraphQL gardent la fiche complète.
     *
     * @return array<string, mixed>
     */
    public function build(Line $line, bool $forPage = false): array
    {
        // Médias (envois locaux, Commons…) servis paginés par GET /api/v1/lines/{slug}/media (LineGallery).
        $line->load('materials');
        $materialPeriods = LineMaterialPeriod::query()
            ->with('link:id,material_id')
            ->whereHas('link', fn ($q) => $q->where('line_id', $line->id))
            ->whereIn('confidence', LineMaterialPeriod::SHOWN_CONFIDENCES)
            ->orderByRaw('COALESCE(start_date, end_date)')
            ->orderBy('end_date')
            ->get()
            ->groupBy(fn (LineMaterialPeriod $p): int => $p->link->material_id)
            ->map(fn ($periods): array => $periods->map(fn (LineMaterialPeriod $p): array => $p->toSheet())->values()->all())
            ->all();

        // Trafic dominant d'une ère : un trafic connu passe avant « non renseigné », même plus court
        // (Nice - Digne : « Trafic non renseigné » en tête d'une ligne desservie par des trains régionaux).
        $eraRows = DB::select(
            'SELECT e.id, e.year, e.is_current,
                    SUM(CASE WHEN ls.status IN '.self::ACTIVE_STATUSES." THEN COALESCE(ls.length_meters, ST_Length(ls.geom::geography)) ELSE 0 END) / 1000.0 AS active_km,
                    SUM(CASE WHEN ls.status IN ('abandoned','service_track','construction') THEN COALESCE(ls.length_meters, ST_Length(ls.geom::geography)) ELSE 0 END) / 1000.0 AS closed_km,
                    SUM(CASE WHEN ls.status IN ('dismantled','razed') THEN COALESCE(ls.length_meters, ST_Length(ls.geom::geography)) ELSE 0 END) / 1000.0 AS gone_km,
                    SUM(CASE WHEN ls.is_heritage AND ls.status IN ".self::ACTIVE_STATUSES.' THEN COALESCE(ls.length_meters, ST_Length(ls.geom::geography)) ELSE 0 END) / 1000.0 AS heritage_km,
                    SUM(CASE WHEN ls.is_heritage THEN COALESCE(ls.length_meters, ST_Length(ls.geom::geography)) ELSE 0 END) / 1000.0 AS heritage_all_km,
                    -- Trafic dominant hors portions associatives (sans trafic commercial renseigné).
                    (SELECT x.traffic_type FROM line_segments x
                      WHERE x.line_id = ls.line_id AND x.era_id = e.id AND x.status IN '.self::ACTIVE_STATUSES.'
                        AND NOT COALESCE(x.is_heritage, false)
                      GROUP BY x.traffic_type
                      ORDER BY COALESCE(x.traffic_type, \'unknown\') = \'unknown\', SUM(COALESCE(x.length_meters, ST_Length(x.geom::geography))) DESC LIMIT 1) AS traffic
             FROM line_segments ls JOIN eras e ON e.id = ls.era_id
             WHERE ls.line_id = ?
             GROUP BY e.id, e.year, e.is_current, ls.line_id
             ORDER BY e.year',
            [$line->id],
        );
        $eraSummary = collect($eraRows)->map(fn ($r) => [
            'year' => (int) $r->year,
            'is_current' => (bool) $r->is_current,
            'active_km' => round((float) $r->active_km, 1),
            'closed_km' => round((float) $r->closed_km, 1),
            'gone_km' => round((float) $r->gone_km, 1),
            'heritage_km' => round((float) $r->heritage_all_km, 1),
            // Train touristique majoritaire : couleur et libellé patrimoine, comme sur la carte.
            'traffic' => (float) $r->heritage_km > 0 && (float) $r->heritage_km * 2 >= (float) $r->active_km ? 'heritage' : $r->traffic,
            'state' => (float) $r->active_km > 0 ? 'open' : ((float) $r->closed_km > 0 ? 'closed' : 'gone'),
        ])->values();

        $displayEra = collect($eraRows)->firstWhere('is_current', true) ?? collect($eraRows)->last();
        $latestEraId = $displayEra?->id;
        $latestSegmentIds = $latestEraId
            ? $line->segments()->where('era_id', $latestEraId)->pluck('id')->all()
            : [];

        // Gares : TOUTES les gares de la ligne (toutes ères, même fermées ou sur une portion
        // déposée), dans l'ordre du tracé, avec leur état à chaque décennie. Une seule source,
        // App\Services\LineStations (agent « gares », mêmes données que la carte et la fiche
        // gare) pour l'en-tête, la liste, la carte et les décennies : aucun repli divergent.
        // Calculées une fois (requête des terminus comprise : environ 130 à 210 ms sur la LGV Sud-Est),
        // puis reprises par la vue « décennie par décennie » (LineStations::byEraOf).
        $lineStationData = app(LineStations::class)->forLine($line->id);
        $stations = $this->lineStations($lineStationData);

        // Événements : lecture brute (pas de cast enum → un type ajouté en base
        // par un import ne fait pas planter la fiche).
        $events = DB::table('line_events')
            ->where('line_id', $line->id)
            ->orderBy('event_date')->orderBy('id')
            ->get(['id', 'type', 'event_date', 'date_precision', 'description',
                'section_from', 'section_to', 'source', 'source_url', 'confidence'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'type' => $e->type,
                'event_date' => substr((string) $e->event_date, 0, 10),
                'date_precision' => in_array($e->date_precision, ['day', 'month', 'year'], true) ? $e->date_precision : 'day',
                'section_from' => $e->section_from,
                'section_to' => $e->section_to,
                // Charte : pas de tiret long à l'affichage (descriptions importées « … - Wikipédia »).
                'description' => $e->description === null ? null : preg_replace('/\s*—\s*/u', ' - ', $e->description),
                'source' => $e->source,
                'source_url' => $e->source_url,
                'confidence' => $e->confidence,
            ])->values();

        $stats = app(LineStats::class)->forEra($line, $latestSegmentIds, $latestEraId, $events->count());
        // Compteur de l'en-tête = la liste des gares (toutes, fermées comprises), et parmi
        // elles celles ouvertes aujourd'hui.
        $stats['station_count'] = $stations->count();
        $stats['station_open_count'] = $stations->filter(fn ($s) => in_array($s['status_today'], StationTimeline::OPEN_STATUSES, true))->count();

        // Phases de la ligne (vraies dates de line_events, état de toute la ligne à chaque
        // phase) et chronique (événements par portion, notes de source, désaccords).
        $phases = LinePhases::forLine($line);
        $decades = $this->decadesWithStations($phases['decades'], LineStations::byEraOf($lineStationData));

        // UNE longueur par fiche (chantier « UI unifiée », constats « six longueurs pour la même
        // ligne ») : celle du tracé que VFF dessine, toutes portions comprises (en service, fermées,
        // déposées), à son plus grand développement. C'est la longueur du rail « Aujourd'hui », des
        // phases, de la carte, du tableau des décennies et de la portion « Toute la ligne » de la
        // chronique. Avant : longueur des formes SNCF (généralisées, 121 km sur 610000 pour un tracé
        // de 124,7 km), sans rapport avec le reste de la page ; elle reste en `length_sncf_km`.
        // `active_length_km` = la part en service aujourd'hui, de la même mesure ; plus de second
        // chiffre « à son apogée » (`length_greatest_km` toujours null).
        $lineLengthM = self::lineLengthM($phases);
        if ($lineLengthM > 0) {
            $stats['length_km'] = round($lineLengthM / 1000, 1);
            $today = collect($phases['decades'])->firstWhere('is_current', true) ?? collect($phases['decades'])->last();
            if ($today !== null) {
                $stats['active_length_km'] = round(array_sum(array_intersect_key($today['length_by_category'], array_flip(LinePhases::ACTIVE))) / 1000, 1);
            }
        }
        $stats['length_greatest_km'] = null;
        $stats['length_definition'] = $lineLengthM > 0 ? 'trace' : ($stats['length_sncf_km'] !== null ? 'sncf' : 'segments');
        $chronicle = LineChronicle::forLine($line, $phases['used_events'], $stations->all(), $lineLengthM > 0 ? $lineLengthM : null);

        $segmentsGeoJson = [];
        if ($latestEraId && ! $forPage) {
            $features = collect(DB::select(
                "SELECT ls.id, ls.status, COALESCE(ls.traffic_type, 'unknown') AS traffic_type,
                        ls.is_heritage, ls.max_speed_kmh, ls.source,
                        ".Lgv::sql('l', 'ls').' AS is_lgv,
                        ST_AsGeoJSON(ls.geom, 6) AS gj
                 FROM line_segments ls
                 JOIN lines l ON l.id = ls.line_id
                 WHERE ls.line_id = ? AND ls.era_id = ?
                 ORDER BY ls.pk_start_m NULLS LAST, ls.id',
                [$line->id, $latestEraId],
            ))->map(fn ($seg) => [
                'type' => 'Feature',
                'properties' => [
                    'id' => $seg->id,
                    'status' => $seg->status,
                    'traffic_type' => $seg->traffic_type,
                    'is_heritage' => (bool) $seg->is_heritage,
                    // Même règle LGV que les tuiles de la carte (couleur violette).
                    'is_lgv' => (bool) $seg->is_lgv,
                    'max_speed_kmh' => $seg->max_speed_kmh,
                    'source' => $seg->source,
                ],
                'geometry' => json_decode($seg->gj, true),
            ])->values();

            $statusCounts = $features->countBy(fn ($f) => $f['properties']['status']);
            $segmentsGeoJson[] = [
                'era_year' => (int) $displayEra->year,
                'status' => $statusCounts->sortDesc()->keys()->first(),
                'features' => $features,
            ];
        }

        // Sans la signalisation en cabine déjà nommée (« TVM 300 » une fois, pas deux).
        $signallingRanges = $latestEraId
            ? app(SignallingRanges::class)->forLine($line->id, $latestEraId, withoutBlockSystems: true)
            : [];

        // Sections nommées (LGV Sud-Est / Rhône-Alpes / Méditerranée…)
        $sections = DB::table('line_sections')
            ->where('line_id', $line->id)
            ->orderBy('pk_start_m')
            ->get(['name', 'alt_names', 'pk_start_m', 'pk_end_m',
                'opening_year_from', 'opening_year_to', 'description', 'source_url'])
            ->map(fn ($s) => [
                'name' => $s->name,
                'alt_names' => $s->alt_names ? json_decode($s->alt_names, true) : [],
                'pk_start_m' => (int) $s->pk_start_m,
                'pk_end_m' => (int) $s->pk_end_m,
                'pk_start_label' => sprintf('%d+%03d', intdiv($s->pk_start_m, 1000), $s->pk_start_m % 1000),
                'pk_end_label' => sprintf('%d+%03d', intdiv($s->pk_end_m, 1000), $s->pk_end_m % 1000),
                'length_km' => round(($s->pk_end_m - $s->pk_start_m) / 1000, 1),
                'opening_year_from' => $s->opening_year_from,
                'opening_year_to' => $s->opening_year_to,
                'description' => $s->description,
                // Description tirée de Wikipédia, en français : lang="fr" sur une page EN ou DE.
                'description_lang' => $s->description !== null && app()->getLocale() !== 'fr' ? 'fr' : null,
                'source_url' => $s->source_url,
            ])->values()->all();
        // Étapes de mise en service de chaque section : les phases datées de ses années d'ouverture.
        $sections = self::sectionStages($sections, $phases['phases']);

        // Charge les métadonnées encyclopédiques + sources
        $encyclopedia = DB::table('lines')
            ->where('id', $line->id)
            ->first([
                'length_official_m', 'gauge_mm_default', 'nb_voies_default',
                'max_speed_kmh_official', 'concession_year', 'wikipedia_title',
                'archeo_url', 'regions', 'departements', 'historical_operators',
                'encyclopedia_updated_at', 'network',
            ]);
        $sources = DB::table('line_sources')->where('line_id', $line->id)
            ->get(['source_type', 'source_url', 'fetched_at', 'fields_provided']);

        $currentSummary = $eraSummary->firstWhere('year', (int) ($displayEra->year ?? 0));

        $sheet = [
            'line' => [
                'id' => $line->id,
                'slug' => $line->slug,
                'name' => $line->name,
                // Autres noms, avec leur source (cf. historicalNames) ; `alt_names` = les noms seuls.
                'alt_names' => array_column($names = $this->historicalNames($line), 'name'),
                'names' => $names,
                'wikidata' => is_array($line->alt_names) ? ($line->alt_names['wikidata'] ?? null) : null,
                'code' => $line->code,
                'type' => $line->getRawOriginal('type'), // nullable (colonne non curée)
                'network' => $encyclopedia->network ?? null, // principal | secondaire | militaire | particulier
                'display_era_year' => $displayEra ? (int) $displayEra->year : null,
                'current_traffic' => $currentSummary['traffic'] ?? null,
                'current_state' => $currentSummary['state'] ?? null,
                'is_threatened' => (bool) $line->is_threatened,
                // {kind, title, message, cta_label, cta_url, lang} dans la langue de la page, ou null.
                'advocacy' => Advocacy::localized($line->advocacy, app()->getLocale()),
                'historical_operator' => $line->historical_operator,
                // Typographie adaptée (annoncée sous l'extrait) : « no 752 000 » de l'article → « n° 752 000 ».
                'description' => $line->description === null ? null
                    : (string) preg_replace('/(?<!\p{L})no (?=\d)/u', 'n° ', LineChronicle::dashes($line->description)),
                'description_source' => $this->descriptionSource($line),
                // Langue des textes de la ligne qui restent en français sur une page EN ou DE (extrait de
                // Wikipédia, nom officiel et autres noms : noms propres français) : attribut lang.
                'description_lang' => $line->description !== null && app()->getLocale() !== 'fr' ? 'fr' : null,
                'name_lang' => app()->getLocale() !== 'fr' ? 'fr' : null,
                // Ligne à grande vitesse (même règle que la carte) : signalisation en cabine, pas de BAL général.
                'is_lgv' => (bool) ($line->is_lgv ?? false) || Lgv::nameIsLgv($line->name),
                'length_official_m' => $encyclopedia->length_official_m ?? null,
                'gauge_mm_default' => $encyclopedia->gauge_mm_default ?? null,
                'nb_voies_default' => $encyclopedia->nb_voies_default ?? null,
                'max_speed_kmh_official' => $encyclopedia->max_speed_kmh_official ?? null,
                'concession_year' => $encyclopedia->concession_year ?? null,
                'wikipedia_title' => $encyclopedia->wikipedia_title ?? null,
                'archeo_url' => $encyclopedia->archeo_url ?? null,
                'regions' => $encyclopedia->regions ? json_decode($encyclopedia->regions, true) : [],
                'departements' => $encyclopedia->departements ? json_decode($encyclopedia->departements, true) : [],
                // Rôles et années relus (cf. operators()) : gestionnaire d'infrastructure, jamais
                // « exploitant » avant la mise en service ; `ongoing` = toujours en fonction.
                'historical_operators' => self::operators(
                    $encyclopedia->historical_operators ? (array) json_decode($encyclopedia->historical_operators, true) : [],
                    self::firstOpeningYear($phases['phases']),
                ),
                'encyclopedia_updated_at' => $encyclopedia->encyclopedia_updated_at ?? null,
                // Bloc « Fiche » : chaque valeur avec ce qu'elle décrit (ligne entière, ou élément
                // Wikidata qui n'en décrit qu'une partie), sa source et sa date (cf. LineFacts).
                'facts' => LineFacts::build($line, $encyclopedia, $stats, $displayEra ? (int) $displayEra->year : null,
                    (bool) ($displayEra->is_current ?? false)),
            ],
            // Données de la fiche : sources de la ligne (line_sources, lien réparé) et sources des
            // tronçons affichés (tracé, vitesses, électrification, signalisation), cf. dataSources().
            'sources' => $this->dataSources($line, $sources, $latestEraId),
            'stats' => $stats,
            'segments' => $segmentsGeoJson,
            'era_summary' => $eraSummary,
            // Carte de la fiche : portions (géométrie une fois), phases datées, phase « au meilleur ».
            'portions' => $phases['portions'],
            'phases' => $phases['phases'],
            'best_phase' => $phases['best'],
            // Ère courante : écarts entre l'état reconstitué de la dernière décennie et les sources
            // d'aujourd'hui qu'aucun événement ne date (pas des phases, cf. LinePhases).
            'phases_unexplained' => $phases['unexplained'],
            'decades' => $decades,
            'chronicle' => $chronicle,
            'stations' => $stations,
            'signalling' => $signallingRanges,
            'sections' => $sections,
            'events' => $events,
            // Exploitations associatives (trains touristiques, vélorails), cf. vff:import-heritage-lines.
            'heritage' => $this->heritageOperations($line->id),
            // Par nom, dans l'ordre naturel (« BB 9200 » avant « BB 15000 », accents ignorés) ; périodes de
            // circulation sur la ligne datées par une source (vff:import-material-periods), les plus anciennes d'abord.
            'materials' => $line->materials->sortBy(fn ($m): string => Str::ascii($m->name), SORT_NATURAL | SORT_FLAG_CASE)->values()->map(fn ($m) => [
                'slug' => $m->slug,
                'name' => $m->name,
                'type' => $m->type,
                // `builder` : noms joints, coupés à 255 caractères (« … ») pour les séries aux nombreux
                // constructeurs ; `builders` : la liste complète des noms (Wikidata P176), sans doublon. Un
                // constructeur sans libellé (identifiant Wikidata seul) n'est dans aucun des deux.
                'builder' => $m->builder,
                'builders' => $m->builderNames(),
                'in_service_from' => $m->in_service_from,
                'in_service_to' => $m->in_service_to,
                'periods' => $materialPeriods[$m->id] ?? [],
            ]),
        ];

        return $forPage ? array_diff_key($sheet, array_flip(self::PAGE_OMITTED)) : $sheet;
    }

    /**
     * Étapes de mise en service des sections nommées (LGV Paris - Lyon ouverte en 1981 puis en 1983) :
     * pour chaque section, les phases datées (LinePhases, vraies dates de line_events) qui mettent
     * en service une partie de la ligne pendant ses années d'ouverture (line_sections, de
     * opening_year_from à opening_year_to). Rien n'est déduit au-delà :
     *   - une phase non datée (« entre 1941 et 1950 ») n'est pas une étape ;
     *   - les raccords fondus dans la phase (changements « minor ») ne comptent pas dans sa longueur ;
     *   - une phase dont l'année tombe dans les années de deux sections n'est rattachée à aucune
     *     (les sections n'ont pas de tracé propre : pas de rattachement deviné) ;
     *   - une phase plus longue que la section (PK de fin moins PK de début, à STAGE_LENGTH_TOLERANCE
     *     près) en déborde : elle date aussi une autre partie de la ligne (LGV Est européenne : une
     *     seule date de 2007 pour 409 km, dont les 106 km de la seconde phase ouverte en 2016), elle
     *     n'est rattachée à aucune section.
     * Chaque étape : `phase` (indice dans les phases de la fiche), `date`, `precision`, `length_m`
     * (longueur mise en service) et `event_ids` (événements qui la datent, pour leurs sources).
     *
     * @param  list<array<string, mixed>>  $sections  sections de la fiche (opening_year_from, opening_year_to)
     * @param  list<array<string, mixed>>  $phases  phases de LinePhases (date, precision, changes)
     * @return list<array<string, mixed>> les sections, chacune avec `stages`
     */
    public static function sectionStages(array $sections, array $phases): array
    {
        $years = array_map(function (array $s): ?array {
            $from = isset($s['opening_year_from']) ? (int) $s['opening_year_from'] : null;
            $to = isset($s['opening_year_to']) ? (int) $s['opening_year_to'] : $from;

            return $from === null ? null : [$from, max($from, (int) $to)];
        }, $sections);
        $maxLength = array_map(fn (array $s): ?float => isset($s['pk_start_m'], $s['pk_end_m'])
            ? ((int) $s['pk_end_m'] - (int) $s['pk_start_m']) * (1 + self::STAGE_LENGTH_TOLERANCE) : null, $sections);
        $stages = array_fill(0, count($sections), []);
        foreach ($phases as $index => $phase) {
            if (($phase['precision'] ?? 'range') === 'range') {
                continue;
            }
            $length = 0;
            $eventIds = [];
            foreach ($phase['changes'] ?? [] as $c) {
                if (($c['minor'] ?? false) || ! LinePhases::isActive((string) $c['to']) || LinePhases::isActive((string) $c['from'])) {
                    continue;
                }
                $length += (int) $c['length_m'];
                if ($c['event_id'] !== null && ! in_array((int) $c['event_id'], $eventIds, true)) {
                    $eventIds[] = (int) $c['event_id'];
                }
            }
            if ($length === 0) {
                continue;
            }
            $year = (int) substr((string) $phase['date'], 0, 4);
            $matches = array_keys(array_filter($years, fn (?array $y): bool => $y !== null && $year >= $y[0] && $year <= $y[1]));
            if (count($matches) !== 1 || ($maxLength[$matches[0]] !== null && $length > $maxLength[$matches[0]])) {
                continue;
            }
            $stages[$matches[0]][] = ['phase' => $index, 'date' => (string) $phase['date'], 'precision' => (string) $phase['precision'],
                'length_m' => $length, 'event_ids' => $eventIds];
        }
        foreach ($sections as $i => &$s) {
            $s['stages'] = $stages[$i];
        }
        unset($s);

        return $sections;
    }

    /** Écart toléré entre la longueur d'une étape (tracé VFF) et celle de sa section (PK des sources). */
    public const STAGE_LENGTH_TOLERANCE = 0.1;

    /** PK lisible : « 7+735 » ; avant l'origine de la ligne : « -0+250 ». */
    private function formatPkLabel(int $m): string
    {
        return ($m < 0 ? '-' : '').sprintf('%d+%03d', intdiv(abs($m), 1000), abs($m) % 1000);
    }

    /**
     * Exploitations associatives de la ligne (line_events heritage_start /
     * heritage_end) : une entrée par exploitation et par portion, avec ses
     * périodes (une interruption donne deux périodes). Le nom et l'exploitant
     * viennent de la description normalisée de vff:import-heritage-lines
     * (« Exploitation associative : NOM (exploitant : X), train touristique… »).
     *
     * @return list<array<string, mixed>>
     */
    private function heritageOperations(int $lineId): array
    {
        $rows = DB::table('line_events')->where('line_id', $lineId)
            ->whereIn('type', ['heritage_start', 'heritage_end'])
            ->orderBy('event_date')->orderBy('id')
            ->get(['type', 'heritage_kind', 'event_date', 'date_precision', 'description', 'section_from', 'section_to',
                'section_length_m', 'source_url', 'confidence']);
        $ops = [];
        foreach ($rows as $r) {
            $name = $operator = null;
            if (preg_match('/^[^:]*exploitation associative\s*:\s*(.+?),\s*(?:train touristique|vélorail|musée)/iu', (string) $r->description, $m)) {
                $name = $m[1];
                if (preg_match('/^(.+?)\s*\(exploitant : (.+)\)$/u', $name, $o)) {
                    [$name, $operator] = [$o[1], $o[2]];
                }
            }
            $key = $r->heritage_kind.'|'.$name.'|'.$r->section_length_m.'|'.$r->section_from;
            $ops[$key] ??= [
                'name' => $name, 'operator' => $operator, 'kind' => $r->heritage_kind ?? 'tourist_train',
                'from' => $r->section_from, 'to' => $r->section_to,
                'length_km' => $r->section_length_m ? round($r->section_length_m / 1000, 1) : null,
                'periods' => [], 'description' => null, 'source_url' => $r->source_url,
            ];
            $date = ['date' => substr((string) $r->event_date, 0, 10), 'precision' => $r->date_precision];
            if ($r->type === 'heritage_start') {
                $ops[$key]['periods'][] = ['start' => $date, 'end' => null,
                    // Début non daté : exploitation seulement attestée à cette date.
                    'attested' => str_contains((string) $r->description, 'date de début non trouvée')];
                $ops[$key]['description'] ??= preg_replace('/\s*—\s*/u', ' - ', (string) $r->description);
            } else {
                $last = array_key_last($ops[$key]['periods']);
                if ($last !== null && $ops[$key]['periods'][$last]['end'] === null) {
                    $ops[$key]['periods'][$last]['end'] = $date;
                }
            }
        }

        return array_values(array_filter($ops, fn ($o) => $o['periods'] !== []));
    }

    /**
     * TOUTES les gares de la ligne, quelle que soit l'ère, même fermées ou sur une portion
     * déposée, dans l'ordre du tracé, avec leurs dates et leur état à chaque décennie.
     *
     * Source unique : App\Services\LineStations::forLine (agent « gares » : station_lines,
     * station_eras, stations.closed_at, les mêmes données que la carte et la fiche gare).
     * Pas de repli (comptage spatial, line_stops d'une seule ère) : il divergerait de la carte
     * et de la fiche gare. Une ligne sans gare rattachée affiche une liste vide.
     *
     * @param  array{stations: list<array<string, mixed>>}  $data  résultat de LineStations::forLine
     * @return Collection<int, array<mixed>>
     */
    private function lineStations(array $data): Collection
    {
        return collect($data['stations'])->map(fn ($s) => $this->stationProps($s))->values();
    }

    /** Gare de LineStations::forLine au format de la fiche (états par ère réduits au statut affiché). */
    private function stationProps(array $s): array
    {
        $eras = [];
        foreach ($s['eras'] ?? [] as $year => $e) {
            $status = is_array($e) ? ($e['status'] ?? null) : $e;
            if ($status !== null) {
                $eras[(int) $year] = $status;
            }
        }

        return [
            'slug' => $s['slug'],
            'name' => $s['name'],
            'type' => $s['type'] ?? null,
            'city' => $s['city'] ?? null,
            'department_code' => $s['department_code'] ?? null,
            'lng' => $s['lng'],
            'lat' => $s['lat'],
            'pk_m' => $s['pk_m'],
            'pk_label' => $s['pk_m'] === null ? null : $this->formatPkLabel((int) $s['pk_m']),
            'is_terminal' => (bool) $s['is_terminal'],
            'link_method' => $s['link_method'] ?? null,
            'served_from' => $s['served_from'] ?? null,
            'served_until' => $s['served_until'] ?? null,
            'served_until_kind' => $s['served_until_kind'] ?? null,
            'years_source' => $s['years_source'] ?? null,
            'opened_at' => $s['opened_at'],
            'opened_precision' => $s['opened_precision'],
            'closed_at' => $s['closed_at'],
            'closed_precision' => $s['closed_precision'],
            'closed_after' => $s['closed_after'] ?? null,
            'closed_year' => $s['closed_year'] ?? null,
            // Statut du jour (null : la gare n'existe plus) ; repris par era_status pour la carte.
            'status_today' => $s['status_today'],
            'era_status' => $s['status_today'],
            'effective_status' => $s['status_today'],
            'eras' => (object) $eras,
        ];
    }

    /**
     * Longueur de la ligne (m) : le plus grand total, toutes catégories (en service, fermée, déposée),
     * des phases et des décennies de LinePhases, c'est-à-dire l'échelle du rail de l'en-tête. 0 sans
     * tracé.
     *
     * @param  array{phases: list<array<string, mixed>>, decades: list<array<string, mixed>>}  $phases
     */
    public static function lineLengthM(array $phases): int
    {
        $max = 0;
        foreach ([...$phases['phases'], ...$phases['decades']] as $p) {
            $max = max($max, (int) array_sum($p['length_by_category'] ?? []));
        }

        return $max;
    }

    /**
     * Décennie par décennie : km par catégorie (LinePhases) et, pour les gares, le résumé
     * App\Services\LineStations::byEra tel quel (ouvertes, incertaines, fermées, gares
     * fermées dans la décennie avec la date de la fiche gare).
     *
     * @param  list<array<string, mixed>>  $decades
     * @param  list<array<string, mixed>>  $byEra
     * @return list<array<string, mixed>>
     */
    private function decadesWithStations(array $decades, array $byEra): array
    {
        $byYear = [];
        foreach ($byEra as $e) {
            $byYear[(int) $e['year']] = $e;
        }
        foreach ($decades as &$d) {
            $e = $byYear[(int) $d['year']] ?? null;
            $d['stations'] = [
                'open' => $e['open'] ?? 0,
                'uncertain' => $e['uncertain'] ?? 0,
                'closed' => $e['closed'] ?? 0,
                'closures' => array_map(fn ($c) => [
                    'slug' => $c['slug'], 'name' => $c['name'], 'closed_at' => $c['closed_at'],
                    'closed_precision' => $c['closed_precision'], 'closed_after' => $c['closed_after'] ?? null,
                    // Fermeture provisoire : la gare rouvre à une décennie suivante.
                    'reopened' => (bool) ($c['reopened'] ?? false),
                ], $e['closures'] ?? []),
            ];
        }
        unset($d);

        return $decades;
    }

    /**
     * Autres noms de la ligne, chacun avec sa source : noms successifs et sections nommées
     * (vff:import-line-names), sections nommées (line_sections), nom lignes-oubliees, noms
     * archeoferroviaire, fiches fusionnées, titre de l'article Wikipédia. Sans doublon (casse,
     * accents et ponctuation ignorés) ni le nom principal.
     *
     * @return list<array{name: string, kind: string, source: ?string, url: ?string, from: ?int, to: ?int}>
     */
    private function historicalNames(Line $line): array
    {
        $alt = is_array($line->alt_names) ? $line->alt_names : [];
        // Même nom à la forme près : « Ligne Limoges-Bénédictins - Angoulême », « ligne de St-Germain-des-Fossés
        // à Darsac » ou « Voie ferrée Angoulême-Limoges » = mêmes lieux (ordre, articles, « St » / « Saint »,
        // « (LGV) » ignorés) : un seul nom, et jamais le titre lui-même (cf. nameKey).
        $key = fn (string $n) => self::nameKey($n);
        $seen = [$key($line->name) => true];
        $out = [];
        $add = function (?string $name, string $kind, ?string $source, ?string $url = null, ?int $from = null, ?int $to = null) use (&$out, &$seen, $key) {
            $name = $name === null ? '' : trim(LineChronicle::dashes($name));
            if ($name === '') {
                return;
            }
            $k = $key($name);
            if (isset($seen[$k])) {
                // Deux formes du même nom : celle qui commence par « Ligne » plutôt que « Voie ferrée ».
                $i = $seen[$k];
                if ($i !== true && ! preg_match('/^ligne\b/iu', $out[$i]['name']) && preg_match('/^ligne\b/iu', $name)) {
                    $out[$i]['name'] = FrenchGrammar::ucfirst($name);
                }

                return;
            }
            $seen[$k] = count($out);
            $out[] = ['name' => FrenchGrammar::ucfirst($name), 'kind' => $kind, 'source' => $source, 'url' => $url, 'from' => $from, 'to' => $to];
        };

        foreach (is_array($alt['names'] ?? null) ? $alt['names'] : [] as $n) {
            if (is_array($n)) {
                $add($n['name'] ?? null, $n['kind'] ?? 'name', $n['source'] ?? null, $n['source_url'] ?? null,
                    isset($n['from']) ? (int) $n['from'] : null, isset($n['to']) ? (int) $n['to'] : null);
            }
        }
        foreach (DB::table('line_sections')->where('line_id', $line->id)->orderBy('pk_start_m')
            ->get(['name', 'alt_names', 'opening_year_from', 'opening_year_to', 'source_url']) as $s) {
            $add($s->name, 'section', 'wikipedia', $s->source_url, $s->opening_year_from, $s->opening_year_to);
            foreach (json_decode((string) $s->alt_names, true) ?: [] as $a) {
                $add(is_string($a) ? $a : null, 'section', 'wikipedia', $s->source_url, $s->opening_year_from, $s->opening_year_to);
            }
        }
        if ($line->wikipedia_title) {
            $add($line->wikipedia_title, 'name', 'wikipedia', 'https://fr.wikipedia.org/wiki/'.rawurlencode(str_replace(' ', '_', $line->wikipedia_title)));
        }
        $lo = $alt['lignes_oubliees'] ?? null;
        if (is_array($lo)) {
            $add($lo['name'] ?? null, 'name', 'lignes-oubliees', $lo['url'] ?? null);
        }
        if (($alt['source'] ?? null) === 'archeoferroviaire' || ($alt['name_source'] ?? null) === 'archeoferroviaire') {
            foreach (is_array($alt['labels'] ?? null) ? $alt['labels'] : [] as $l) {
                $add(is_string($l) ? $l : null, 'name', 'archeoferroviaire', $line->archeo_url);
            }
        }
        foreach (is_array($alt['merged'] ?? null) ? $alt['merged'] : [] as $m) {
            $add(is_array($m) ? ($m['name'] ?? null) : null, 'merged', 'vff', null);
        }
        foreach (is_array($alt['sections'] ?? null) ? $alt['sections'] : [] as $m) {
            $add(is_string($m) ? $m : null, 'merged', 'vff', null);
        }

        // Nom d'une AUTRE ligne de VFF (Wikidata cite parfois un raccordement parmi les alias : 610000
        // « Raccordement de Limoges-Puy-Imbert à Limoges-Montjovis », qui est la ligne 590306) : ce
        // n'est pas un autre nom de celle-ci. Les fiches fusionnées (kind merged) restent.
        $others = $out === [] ? [] : array_flip(array_map(fn ($n) => Str::lower($n), DB::table('lines')->where('id', '<>', $line->id)
            ->whereIn(DB::raw('lower(name)'), array_map(fn ($n) => Str::lower($n['name']), $out))->pluck('name')->all()));

        return array_values(array_filter($out, fn ($n) => $n['kind'] === 'merged' || ! isset($others[Str::lower($n['name'])])));
    }

    /** Mots qui ne distinguent pas deux noms de la même ligne (article, « ligne », « voie ferrée »…). */
    private const NAME_STOPWORDS = ['ligne', 'lignes', 'voie', 'ferree', 'chemin', 'chemins', 'fer', 'de', 'd', 'du', 'des',
        'a', 'au', 'aux', 'la', 'le', 'les', 'l', 'et'];

    /**
     * Clé d'un nom de ligne : ses lieux, sans ordre ni article, « St » = « Saint », sans la mention
     * « (LGV) ». « Ligne de St-Germain-des-Fossés à Darsac » = « ligne Saint-Germain-des-Fossés - Darsac ».
     */
    public static function nameKey(string $name): string
    {
        $n = Str::lower(Str::ascii((string) preg_replace('/\s*\((?:lgv|ligne a grande vitesse)\)\s*$/iu', '', $name)));
        $words = array_filter(preg_split('/[^a-z0-9]+/', $n) ?: [], fn ($w) => $w !== '' && ! in_array($w, self::NAME_STOPWORDS, true));
        $words = array_map(fn ($w) => match ($w) {
            'st' => 'saint',
            'ste' => 'sainte',
            default => $w,
        }, $words);
        sort($words);

        return implode(' ', $words);
    }

    /**
     * Source du texte « À propos » : introduction Wikipédia (vff:import-line-descriptions,
     * line_sources « wikipedia-intro » : titre, version, licence CC BY-SA 4.0), ou sources
     * citées par la ligne déclarée à la main (database/data/lignes_hors_rfn.php).
     */
    private function descriptionSource(Line $line): ?array
    {
        if ($line->description === null) {
            return null;
        }
        $src = DB::table('line_sources')->where('line_id', $line->id)->where('source_type', 'wikipedia-intro')
            ->first(['source_url', 'fetched_at', 'fields_provided']);
        if ($src) {
            $meta = json_decode((string) $src->fields_provided, true) ?: [];

            return [
                'type' => 'wikipedia',
                'title' => $meta['title'] ?? $line->wikipedia_title,
                'url' => $src->source_url,
                'article_url' => isset($meta['title']) ? 'https://fr.wikipedia.org/wiki/'.rawurlencode(str_replace(' ', '_', $meta['title'])) : $src->source_url,
                'fetched_at' => substr((string) $src->fetched_at, 0, 10),
                'license' => 'CC BY-SA 4.0',
                'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/deed.fr',
            ];
        }
        $alt = is_array($line->alt_names) ? $line->alt_names : [];
        $urls = array_values(array_filter(is_array($alt['sources'] ?? null) ? $alt['sources'] : [], 'is_string'));

        return $urls ? ['type' => 'curated', 'urls' => $urls] : ['type' => 'curated', 'urls' => []];
    }

    /** Année de la première mise en service de la ligne (première phase d'ouverture datée), ou null. */
    public static function firstOpeningYear(array $phases): ?int
    {
        foreach ($phases as $ph) {
            if (($ph['kind'] ?? null) === 'growth' && ($ph['precision'] ?? null) !== 'range') {
                return (int) substr((string) $ph['date'], 0, 4);
            }
        }

        return null;
    }

    /**
     * Exploitants de la ligne (Wikidata P137 / P127), relus pour la fiche :
     *   - Réseau ferré de France et SNCF Réseau gèrent l'infrastructure, ils n'exploitent pas de
     *     trains : rôle `gestionnaire`, années bornées par leur existence (RFF : 1997-2014) ;
     *   - un exploitant ne l'est pas avant la mise en service : début ramené à l'année d'ouverture
     *     (« Compagnie des Charentes 1868-1878 » sur une ligne ouverte en 1875 : 1875-1878 ; la date
     *     de 1868 est celle de la concession) ;
     *   - `ongoing` : toujours en fonction (début connu, pas de fin) : « depuis 2015 ».
     *
     * @param  array<mixed>  $operators  valeurs JSON de lines.historical_operators
     * @return list<array<string, mixed>>
     */
    public static function operators(array $operators, ?int $openingYear): array
    {
        $out = [];
        foreach ($operators as $o) {
            if (! is_array($o)) {
                continue;
            }
            $manager = self::INFRASTRUCTURE_MANAGERS[$o['wikidata'] ?? ''] ?? null;
            foreach (self::INFRASTRUCTURE_MANAGERS as $m) {
                $manager ??= ($o['operator'] ?? null) === $m['name'] ? $m : null;
            }
            $from = isset($o['from_year']) ? (int) $o['from_year'] : null;
            $to = isset($o['to_year']) ? (int) $o['to_year'] : null;
            if ($manager !== null) {
                if (($o['role'] ?? null) === 'exploitant') {
                    $o['role'] = 'gestionnaire';
                }
                $from = $from === null ? $manager['from'] : max($from, $manager['from']);
                $to = $manager['to'] === null ? $to : ($to === null ? $manager['to'] : min($to, $manager['to']));
            } elseif (($o['role'] ?? null) === 'exploitant' && $openingYear !== null && $from !== null && $from < $openingYear
                && ($to === null || $to >= $openingYear)) {
                $from = $openingYear;
            }
            $o['from_year'] = $from;
            $o['to_year'] = $to;
            $o['ongoing'] = $from !== null && $to === null;
            $out[] = $o;
        }

        return $out;
    }

    /** Jeux de données SNCF Réseau (Open Data) cités par la fiche. */
    private const SNCF_DATASETS = 'https://ressources.data.sncf.com/explore/dataset/';

    /**
     * Données de la fiche (« Notes et sources ») : les sources de la ligne (line_sources) et celles des
     * tronçons affichés, chacune une fois (type unique), avec un lien valide :
     *   - classement SNCF : lien vers le portail (l'adresse stockée porte la liste des jeux entre
     *     parenthèses, `datasets`) ; omis quand aucun signal SNCF n'a servi (ligne hors réseau ferré
     *     national, comme le train des Pignes) ;
     *   - tracé : OpenStreetMap, IGN BD TOPO, OpenHistoricalMap, archeoferroviaire, formes SNCF selon
     *     les tronçons ; vitesses, électrification et signalisation SNCF quand elles sont affichées.
     *
     * @param  Collection<int, \stdClass>  $sources
     * @return list<array<string, mixed>>
     */
    private function dataSources(Line $line, Collection $sources, ?int $eraId): array
    {
        /** @var array<string, array<string, mixed>> $out */
        $out = [];
        foreach ($sources as $s) {
            $fields = $s->fields_provided ? (array) json_decode($s->fields_provided, true) : [];
            $entry = ['type' => $s->source_type, 'url' => $s->source_url, 'fetched_at' => $s->fetched_at, 'fields' => $fields];
            if ($s->source_type === 'sncf-classification') {
                if (($fields['signal'] ?? null) === 'aucun') {
                    continue;
                }
                if (preg_match('#^(https?://\S+?)/?\s*\((.+)\)\s*$#u', (string) $s->source_url, $m)) {
                    $entry['url'] = $m[1].'/';
                    $entry['datasets'] = array_values(array_filter(array_map('trim', explode(',', str_replace('…', '', $m[2])))));
                }
            }
            $out[$s->source_type] = $entry;
        }
        if ($eraId !== null) {
            $seg = DB::selectOne(<<<'SQL'
                SELECT ARRAY_TO_JSON(ARRAY_AGG(DISTINCT source) FILTER (WHERE source IS NOT NULL)) AS traces,
                       BOOL_OR(max_speed_source = 'sncf') AS sncf_speed,
                       BOOL_OR(electrification_source = 'sncf') AS sncf_elec,
                       EXISTS (SELECT 1 FROM line_signalling WHERE line_id = ? AND era_id = ?) AS sncf_sig
                FROM line_segments WHERE line_id = ? AND era_id = ?
            SQL, [$line->id, $eraId, $line->id, $eraId]);
            $traces = [
                'osm' => 'https://www.openstreetmap.org/',
                'bdtopo' => 'https://geoservices.ign.fr/bdtopo',
                'ohm' => 'https://www.openhistoricalmap.org/',
                'archeoferroviaire' => $line->archeo_url ?: 'http://archeoferroviaire.free.fr/',
                'sncf' => self::SNCF_DATASETS.'formes-des-lignes-du-rfn/',
            ];
            foreach ((array) json_decode((string) ($seg->traces ?? '[]'), true) as $src) {
                $type = $src === 'sncf' ? 'sncf-shapes' : $src;
                if (array_key_exists($src, $traces) && ! isset($out[$type])) {
                    $out[$type] = ['type' => $type, 'url' => $traces[$src], 'fetched_at' => null, 'fields' => ['geometry']];
                }
            }
            foreach ([
                'sncf-speed' => [(bool) ($seg->sncf_speed ?? false), 'vitesse-maximale-nominale-sur-ligne', 'max_speed_kmh'],
                'sncf-electrification' => [(bool) ($seg->sncf_elec ?? false), 'liste-des-lignes-electrifiees', 'electrified'],
                'sncf-signalling' => [(bool) ($seg->sncf_sig ?? false), 'mode-de-cantonnement-des-lignes', 'signalling'],
            ] as $type => [$used, $dataset, $field]) {
                if ($used) {
                    $out[$type] = ['type' => $type, 'url' => self::SNCF_DATASETS.$dataset.'/', 'fetched_at' => null, 'fields' => [$field]];
                }
            }
        }

        return array_values($out);
    }
}
