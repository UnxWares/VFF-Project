<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Résout la PORTION de ligne concernée par chaque événement daté
 * (line_events.section_geom), quelle que soit la source de l'événement.
 *
 * Pourquoi : la carte par décennie découpe chaque ligne selon ses événements
 * (« fermée aux voyageurs de X à Y en 1939 », « déclassée du PK 54,320 au
 * PK 64,100 en 1970 »). Il faut donc transformer ces descriptions en
 * géométries posées sur le tracé de la ligne.
 *
 * Tracé porteur de la ligne (dans l'ordre) : line_reference_geometries (tracé
 * de référence du chef de projet), sinon union des source_traces rattachés
 * (line_id), sinon sncf_line_geometries (code SNCF). Il est fusionné
 * (ST_LineMerge) puis transformé en GRAPHE (morceaux = arêtes) : les
 * MultiLineString à trous sont recousues par des « ponts » rectilignes
 * (≤ 5 km) entre composantes, et un chemin entre deux points se calcule
 * par Dijkstra - robuste aux tracés en plusieurs morceaux et aux embranchements.
 * Calculs métriques en Lambert-93 (EPSG:2154).
 *
 * Méthodes, par ordre de préférence :
 *   - pk : PK SNCF calés sur le tracé par des POINTS DE CONTRÔLE officiels
 *     (sncf_gares, sncf_level_crossings, extrémités des tronçons SNCF,
 *     source_stations) puis interpolation linéaire entre points consécutifs.
 *     On ne suppose JAMAIS PK = distance géométrique : seulement au-delà du
 *     dernier point de contrôle (extrapolation à pente 1). Les points
 *     incohérents (ordre des PK ≠ ordre sur le tracé) sont écartés par une
 *     plus longue sous-suite croissante ;
 *   - places : section_from / section_to géocodés, projetés sur le tracé, puis
 *     plus court chemin entre les deux projections. Géocodage, dans l'ordre :
 *     (1) gares : sncf_gares, gares consolidées (stations, multi-sources) et
 *         leurs autres noms fiables (station_sources : UIC, Wikidata, nom
 *         voisin), bonus pour les gares rattachées à la ligne (station_lines) ;
 *     (2) communes (table communes : actuelles, déléguées, associées = noms
 *         anciens) : accepté si le tracé traverse le contour communal
 *         (commune_contours), sinon centre à moins de COMMUNE_MAX_DIST_M ;
 *     (3) hameaux et lieux-dits (BD TOPO, service de géocodage IGN, index
 *         POI) : nom exact, à moins de HAMLET_MAX_DIST_M, jamais ambigu.
 *     Correspondance floue (unaccent + pg_trgm, « Saint » ≡ « St », cœur du
 *     nom comparé sans les mots de liaison), variantes (« X - Y » à double nom,
 *     « Bif. de X », « X-Ville », noms anciens). Si un lieu existe AU-DELÀ
 *     d'une extrémité du tracé de référence (tracé incomplet, frontière), la
 *     portion s'arrête à cette extrémité quand elle est bien entre les deux
 *     lieux (cf. beyondTrace()). Tracé fragmenté : trous jusqu'à GAP_WIDE_M
 *     en dernier recours ;
 *   - trace : événement rattaché à un tracé source (source_trace_id) ;
 *   - whole_line : aucune portion décrite → toute la ligne, SAUF si la citation
 *     nomme une section, s'il s'agit d'une réouverture, ou d'une fermeture
 *     postérieure à une fermeture partielle, sans mention explicite de toute
 *     la ligne, ni d'une exploitation associative de vff:import-heritage-lines
 *     (cf. wholeLineObjection()) : une réouverture partielle prise pour
 *     la ligne entière remettrait en service des sections restées fermées ;
 *   - reste de la ligne (stocké en « places ») : fermeture SANS portion dont le
 *     texte vise LA ligne (« la ligne est fermée », dernière date de fermeture
 *     de l'infobox…) après des fermetures partielles toutes localisées : la
 *     portion est le tracé non couvert par ces fermetures (cf. remainder()) ;
 *   - unresolved : échec, raison dans section_resolution_note. Mieux vaut une
 *     portion non résolue qu'une portion douteuse.
 */
class SectionResolver
{
    private const SRID = 2154;

    private const GAP_MAX_M = 5000;

    /**
     * Trous plus larges (tracé de référence fragmenté) : seulement en dernier
     * recours entre deux lieux, si les trous font au plus WIDE_GAP_SHARE du chemin.
     */
    private const GAP_WIDE_M = 15000;

    private const WIDE_GAP_SHARE = 0.35;

    /** Lieu au-delà d'une extrémité libre du tracé : distance maximale à cette extrémité. */
    private const BEYOND_MAX_M = 25000;

    /**
     * Lieu étranger (FOREIGN_PLACES) à moins de cette distance du tracé, sans être au-delà
     * d'une extrémité : le tracé passe la frontière ou s'arrête juste devant (Genève à
     * 0,1 km, Irun à 0,5 km, Vintimille à 0,3 km, Dillingen à 0,4 km). La portion va
     * jusqu'au point du tracé le plus proche du lieu.
     */
    private const FOREIGN_NEAR_M = 3500;

    /**
     * L'extrémité E est « entre » A et B : A → E → B (à vol d'oiseau) au plus
     * BEYOND_DETOUR fois A → B (+ 1 km). À vol d'oiseau, pas le long de la voie :
     * une ligne sinueuse (Agen → Auch → Tarbes) ne doit pas faire échouer le test.
     */
    private const BEYOND_DETOUR = 1.2;

    /**
     * Lieux étrangers proches de la frontière (gare principale, WGS84) : le
     * tracé français s'arrête à la frontière, ces lieux sont donc toujours
     * « au-delà de l'extrémité ». Servent uniquement à la troncature.
     */
    private const FOREIGN_PLACES = [
        'geneve' => [6.1424, 46.2102], 'geneve cornavin' => [6.1424, 46.2102], 'geneve eaux vives' => [6.1660, 46.2003],
        'chene bourg' => [6.1953, 46.1958], 'crassier' => [6.1650, 46.3750], 'coppet' => [6.1920, 46.3160],
        'la chaux de fonds' => [6.8255, 47.0997], 'le locle' => [6.7473, 47.0566], 'bienne' => [7.2470, 47.1330],
        'delemont' => [7.3436, 47.3622], 'porrentruy' => [7.0760, 47.4150], 'bale' => [7.5896, 47.5476],
        'vallorbe' => [6.3790, 46.7120], 'le chatelard frontiere' => [6.9590, 46.0590],
        'weil' => [7.6110, 47.5930], 'weil am rhein' => [7.6110, 47.5930], 'vieux brisach' => [7.5790, 48.0280],
        'kehl' => [7.8130, 48.5770], 'berg en allemagne' => [8.2023, 48.9706], 'sarrebruck' => [6.9910, 49.2410],
        'dillingen' => [6.7250, 49.3560], 'perl' => [6.3870, 49.4720], 'treves' => [6.6510, 49.7590],
        'luxembourg' => [6.1340, 49.6000], 'noertzange' => [6.0530, 49.5100], 'rumelange' => [6.0300, 49.4600],
        'athus' => [5.8360, 49.5640], 'arlon' => [5.8140, 49.6830], 'bertrix' => [5.2530, 49.8550], 'muno' => [5.1640, 49.7160],
        'erquelines' => [4.1100, 50.3060], 'quevy' => [3.9370, 50.3410], 'quievrain' => [3.6830, 50.4040],
        'mons' => [3.9420, 50.4540], 'tournai' => [3.3960, 50.6140], 'mouscron' => [3.2260, 50.7400], 'menin' => [3.1220, 50.7960],
        'courtrai' => [3.2650, 50.8240], 'bruxelles' => [4.3570, 50.8360], 'dinant' => [4.9120, 50.2600],
        'vintimille' => [7.6067, 43.7906], 'coni' => [7.5420, 44.3840], 'bardonneche' => [6.7090, 45.0770],
        'irun' => [-1.7920, 43.3390], 'canfranc' => [-0.5210, 42.7120], 'port bou' => [3.1580, 42.4260], 'portbou' => [3.1580, 42.4260],
        'puigcerda' => [1.9310, 42.4310],
    ];

    /** @var array<string, list<array>>|null graphe recousu jusqu'à GAP_WIDE_M (calculé à la demande) */
    private ?array $adjWide = null;

    private const CP_MAX_DIST_M = 300;

    private const STATION_MAX_DIST_M = 2500;

    /**
     * Avantage, dans geocodeStations(), de la gare consolidée (table stations, nom principal ou autre nom) de la ligne
     * sur la gare SNCF homonyme de la même ligne : l'extrémité de la portion tombe sur la gare que montrent la carte et
     * les ères des gares (vff:build-station-eras, invariant stations.active_without_track), pas sur le point de la liste
     * des gares SNCF. Tonnerre (830000) : point SNCF à 136 m de la gare, la section Paris - Tonnerre de 1849 s'arrêtait
     * avant la gare, terminus jusqu'à l'ouverture de Tonnerre - Dijon en 1851 (chantier invariants-gares, 23/11/2026).
     * 0,01 = 200 m d'écart au tracé (pénalité d/20000) : ne départage que des candidats presque à égalité.
     */
    private const CONSOLIDATED_STATION_BONUS = 0.01;

    private const COMMUNE_MAX_DIST_M = 6000;

    /** Hameau / lieu-dit (BD TOPO) : la gare qui porte son nom est au bord de la voie. */
    private const HAMLET_MAX_DIST_M = 1500;

    /** Deux candidats homonymes acceptables à plus de cette distance l'un de l'autre sur le tracé : ambigu. */
    private const AMBIGUOUS_M = 2500;

    /** Noms anciens ou graphies historiques fréquentes dans les sources → nom actuel. */
    private const OLD_NAMES = [
        'napoleonville' => 'Pontivy',
        'napoleon vendee' => 'La Roche-sur-Yon',
        'bourbon vendee' => 'La Roche-sur-Yon',
        'napoleon st leu' => 'Saint-Leu-la-Forêt',
        'les aubrais orleans' => 'Les Aubrais',
        'chalons sur marne' => 'Châlons-en-Champagne',
        'alais' => 'Alès',
    ];

    /** Fermetures dont les portions antérieures sont retranchées pour obtenir le « reste de la ligne ». */
    private const CLOSURE_FAMILY = [
        'closure' => ['closure', 'declassification', 'dismantling', 'conversion'],
        'closure_passenger' => ['closure_passenger', 'closure', 'declassification', 'dismantling', 'conversion'],
        'closure_freight' => ['closure_freight', 'closure', 'declassification', 'dismantling', 'conversion'],
        'declassification' => ['declassification', 'dismantling', 'conversion'],
        'dismantling' => ['dismantling', 'conversion'],
    ];

    /**
     * Le texte vise LA ligne (et non une portion) : « la ligne est fermée »,
     * « fermeture de la ligne », « le reste de la ligne »…
     */
    private const LINE_CLOSURE_TEXT = '/\b(?:la|cette) ligne (?:est |a été |fut |sera |avait été |se trouve |sont )?(?:\p{L}+ ){0,2}(?:fermée|déclassée|déposée|close|désaffectée|neutralisée|supprimée|abandonnée|démontée|démantelée|retranchée)|\b(?:fermeture|déclassement|dépose|désaffectation|suppression|abandon|démontage|neutralisation|retranchement)(?: \p{L}+){0,2} (?:de|à) (?:la|cette) ligne|\breste de la ligne|\bligne (?:est )?(?:définitivement )?(?:fermée|déclassée) (?:à tout trafic|au trafic)|(?:^|[.;] )elle (?:est |a été |fut )?(?:\p{L}+ ){0,2}(?:fermée|déclassée|déposée|close|neutralisée)|\bdéfinitivement (?:fermée|déclassée|close|abandonnée)|\b(?:la|cette) ligne cess(?:a|e|era) d.être exploitée/u';

    /** @var array<int, array{len: float, a: string, b: string}> */
    private array $parts = [];

    /** @var array<string, list<array{0: string, 1: float, 2: array}>> */
    private array $adj = [];

    /** @var array<string, array{0: float, 1: float}> */
    private array $nodes = [];

    private ?string $carrier = null;

    /** Calage PK : axe (pièces ordonnées) + points (m, pk). */
    private ?array $calib = null;

    private ?string $calibError = null;

    /** @var array<string, ?array> cache de géocodage par ligne */
    private array $placeCache = [];

    private object $line;

    /** Nom normalisé sans mots de liaison (sur, la, de, st…) : comparaison du « cœur » du nom. */
    private const CORE = "btrim(regexp_replace(regexp_replace(%s, '\\m(sur|sous|la|le|les|lez|de|du|des|en|et|st|ste|d|l|aux|au)\\M', ' ', 'g'), '\\s+', ' ', 'g'))";

    private string $norm = "btrim(regexp_replace(regexp_replace(regexp_replace(lower(unaccent(%s)), '\\msainte\\M', 'ste', 'g'), '\\msaint\\M', 'st', 'g'), '[^a-z0-9]+', ' ', 'g'))";

    public function __construct(private WikipediaLineParser $text) {}

    /**
     * Résout les événements en attente d'une ligne.
     *
     * @param  'pending'|'retry'|'whole'|'all'  $scope  pending = NULL/pending ; retry = + unresolved ;
     *                                                  whole = revérifier les « toute la ligne » ; all = tout recalculer
     * @return array<string, int> compteurs par section_resolution
     */
    public function resolveLine(int $lineId, ?string $source = null, string $scope = 'pending'): array
    {
        $q = DB::table('line_events')->where('line_id', $lineId)
            ->select('id', 'type', 'event_date', 'section_from', 'section_to', 'section_pk_start_m', 'section_pk_end_m', 'source_trace_id', 'section_resolution_note', 'source_quote', 'description', 'source');
        if ($source) {
            $q->where('source', $source);
        }
        match ($scope) {
            'pending' => $q->where(fn ($w) => $w->whereNull('section_resolution')->orWhere('section_resolution', 'pending')),
            'whole' => $q->where('section_resolution', 'whole_line'),
            'retry' => $q->where(fn ($w) => $w->whereNull('section_resolution')->orWhereIn('section_resolution', ['pending', 'unresolved']))
                // une portion déclarée inconnue par la source reste inconnue
                ->where(fn ($w) => $w->whereNull('section_resolution_note')->orWhere('section_resolution_note', 'not like', 'Portion non précisée%')),
            default => $q->where(fn ($w) => $w->whereNull('section_resolution_note')->orWhere('section_resolution_note', 'not like', 'Portion non précisée%'))
                // une portion déjà posée par l'importeur depuis SON tracé (ohm…) fait foi
                ->where(fn ($w) => $w->whereNull('section_resolution')->orWhere('section_resolution', '<>', 'trace')),
        };
        // Événements à portion d'abord (le « reste de la ligne » d'une fermeture
        // sans portion se calcule sur les fermetures partielles déjà posées),
        // puis ceux sans portion, dans l'ordre chronologique.
        $events = $q->orderByRaw('(section_from IS NULL AND section_to IS NULL AND section_pk_start_m IS NULL AND source_trace_id IS NULL), CASE WHEN section_from IS NULL AND section_to IS NULL AND section_pk_start_m IS NULL THEN event_date END, id')->get();
        if ($events->isEmpty()) {
            return [];
        }

        $this->line = DB::table('lines')->where('id', $lineId)->first(['id', 'code', 'name']);
        // Première fermeture PARTIELLE connue (toutes sources) : une fermeture
        // ultérieure sans portion ne peut pas valoir d'office pour toute la ligne.
        $this->firstPartialClosure = DB::table('line_events')->where('line_id', $lineId)
            ->whereIn('type', ['closure', 'closure_passenger', 'closure_freight', 'declassification', 'dismantling'])
            ->where(fn ($w) => $w->whereNotNull('section_from')->orWhereNotNull('section_to')->orWhereNotNull('section_pk_start_m'))
            ->min('event_date');
        $this->calib = null;
        $this->calibError = null;
        $this->placeCache = [];
        $this->loadCarrier();

        $stats = [];
        foreach ($events as $e) {
            $r = $this->resolveEvent($e);
            $stats[$r] = ($stats[$r] ?? 0) + 1;
        }

        return $stats;
    }

    private function resolveEvent(object $e): string
    {
        if ($this->carrier === null) {
            return $this->unresolved($e->id, 'Aucun tracé pour cette ligne (ni tracé de référence, ni tracé source rattaché, ni géométrie SNCF)');
        }
        // Événement rattaché à un tracé source : ce tracé EST la portion.
        if ($e->source_trace_id && $this->byTrace($e->id)) {
            return 'trace';
        }
        $hasPk = $e->section_pk_start_m !== null && $e->section_pk_end_m !== null;
        $hasFrom = trim((string) $e->section_from) !== '';
        $hasTo = trim((string) $e->section_to) !== '';
        $why = [];

        if ($hasPk) {
            $res = $this->byPk((int) $e->section_pk_start_m, (int) $e->section_pk_end_m);
            if (isset($res['pieces'])) {
                return $this->store($e->id, $res['pieces'], 'pk', $res['note']);
            }
            $why[] = $res['error'];
        }
        if ($hasFrom && $hasTo) {
            $res = $this->byPlaces((string) $e->section_from, (string) $e->section_to);
            if (isset($res['pieces'])) {
                return $this->store($e->id, $res['pieces'], 'places', $res['note']);
            }
            $why[] = $res['error'];
        } elseif ($hasFrom xor $hasTo) {
            if (! $hasPk) {
                $why[] = 'une seule extrémité citée (« '.($hasFrom ? $e->section_from : $e->section_to).' »)';
            }
        }
        if ($why) {
            return $this->unresolved($e->id, implode(' ; ', $why));
        }
        if ($why = $this->wholeLineObjection($e)) {
            // Fermeture de LA ligne après des fermetures partielles : le reste.
            if (str_starts_with($why, 'Fermeture sans portion postérieure')) {
                $rem = $this->remainder($e);
                if (isset($rem['resolution'])) {
                    return $rem['resolution'];
                }
                if (isset($rem['why'])) {
                    $why .= ' ; '.$rem['why'];
                }
            }

            return $this->unresolved($e->id, $why);
        }
        DB::update(
            "UPDATE line_events SET section_geom = (SELECT ST_Multi(ST_Transform(ST_LineMerge(ST_Collect(geom)), 4326)) FROM sr_parts),
                section_length_m = (SELECT ROUND(SUM(ST_Length(geom))) FROM sr_parts),
                section_resolution = 'whole_line', section_resolution_note = ? WHERE id = ?",
            ['Aucune portion décrite : toute la ligne (tracé '.$this->carrier.')', $e->id],
        );

        return 'whole_line';
    }

    private ?string $firstPartialClosure = null;

    private function byTrace(int $eventId): bool
    {
        return DB::update(
            "UPDATE line_events le SET section_geom = ST_Multi(ST_LineMerge(st.geom)), section_resolution = 'trace',
                section_resolution_note = 'Tracé source n° '||st.id||' ('||st.source||')', section_length_m = ROUND(ST_Length(st.geom::geography))
             FROM source_traces st WHERE st.id = le.source_trace_id AND le.id = ?
               AND ST_GeometryType(ST_Multi(ST_LineMerge(st.geom))) = 'ST_MultiLineString'",
            [$eventId],
        ) > 0;
    }

    /**
     * Garde-fous avant d'appliquer un événement SANS portion à toute la ligne
     * (une réouverture partielle prise pour la ligne entière remettrait en
     * service, sur la carte, des sections restées fermées) :
     *   - la citation nomme une section / un tronçon / une partie → portion inconnue ;
     *   - réouverture sans mention explicite de toute la ligne → suspecte ;
     *   - fermeture / déclassement / dépose sans portion APRÈS une fermeture
     *     partielle, sans mention explicite de toute la ligne → portion à préciser.
     */
    private function wholeLineObjection(object $e): ?string
    {
        $text = mb_strtolower(trim(($e->source_quote ?? '').' '.($e->description ?? '')));
        if (preg_match(WikipediaLineParser::EXPLICIT_WHOLE_LINE, $text)) {
            return null;
        }
        $quote = (string) $e->source_quote;
        if (! str_starts_with($quote, 'Infobox') && ! str_starts_with($quote, 'Wikidata')
            && preg_match('/\b(sections?|tronçons?|portions?|parties?|antennes?|branches?)\b|\bentre \p{Lu}/u', $quote)) {
            return 'Portion citée par la source mais non identifiée : jamais appliqué à toute la ligne';
        }
        if (($e->source ?? null) === 'heritage' && str_starts_with($e->type, 'heritage')) {
            // vff:import-heritage-lines : la portion d'une exploitation associative vient d'un tracé
            // observé (IGN, OSM) ou d'une section nommée. Sans l'un ni l'autre, elle n'est jamais
            // « toute la ligne » : une association n'exploite qu'une partie d'une ligne du réseau
            // (174000 : 53 km « patrimoine » pour les 11 km du train de la vallée de la Canner).
            return 'Exploitation associative sans portion observée ni nommée : jamais appliquée à toute la ligne';
        }
        if (str_starts_with($e->type, 'reopening')) {
            return 'Réouverture sans portion explicite : non appliquée à toute la ligne (à préciser)';
        }
        if (in_array($e->type, ['closure', 'closure_passenger', 'closure_freight', 'declassification', 'dismantling'], true)
            && $this->firstPartialClosure !== null && $e->event_date > $this->firstPartialClosure) {
            return 'Fermeture sans portion postérieure à une fermeture partielle ('.$this->firstPartialClosure.') : portion à préciser';
        }

        return null;
    }

    /**
     * « Reste de la ligne » : fermeture (déclassement, dépose) SANS portion,
     * postérieure à des fermetures partielles, dont le texte vise LA ligne.
     * La portion est le tracé qui n'est pas déjà couvert par les fermetures
     * antérieures de même nature ou plus fortes (cf. CLOSURE_FAMILY).
     *
     * Garde-fous (sinon : non résolu, raison donnée) :
     *   - texte explicite (LINE_CLOSURE_TEXT), ou dernière date de fermeture
     *     de l'infobox sans « partiel », ou date de fermeture Wikidata (P3999)
     *     qui est la dernière fermeture connue de la ligne ;
     *   - aucune ouverture / réouverture postérieure (elle pourrait remettre en
     *     service une partie du « reste ») ni contexte de travaux ;
     *   - toutes les fermetures partielles antérieures de la famille sont
     *     localisées (sinon le reste engloberait des portions inconnues).
     *
     * @return array{resolution?: string, why?: string}
     */
    private function remainder(object $e): array
    {
        $family = self::CLOSURE_FAMILY[$e->type] ?? null;
        if (! $family) {
            return [];
        }
        $quote = trim((string) $e->source_quote);
        $text = mb_strtolower($quote.' '.($e->description ?? ''));
        $year = (int) substr((string) $e->event_date, 0, 4);
        if (preg_match('/réouverture|rouvert|rénov|renouvel|reconstru|travaux|modernis|partiel|une (?:des )?voies?\b|double voie|voie unique|seconde? voie/u', $text)) {
            return ['why' => 'reste de la ligne non appliqué (contexte de travaux, de réouverture ou fermeture partielle)'];
        }

        $later = DB::selectOne(
            "SELECT COUNT(*) FILTER (WHERE type LIKE 'opening%' OR type LIKE 'reopening%') AS reopen,
                    COUNT(*) FILTER (WHERE type = ANY(?::text[]) AND event_date > ?) AS closures
             FROM line_events WHERE line_id = ? AND event_date > ?",
            ['{'.implode(',', array_keys(self::CLOSURE_FAMILY)).',conversion}', $e->event_date, $this->line->id, $e->event_date],
        );
        if (str_starts_with($quote, 'Infobox')) {
            preg_match_all('/fermeture\d*\s*=\s*(\d{4})/u', $quote, $m);
            $explicit = $m[1] && (int) max($m[1]) === $year;
        } elseif (str_starts_with($quote, 'Wikidata')) {
            $explicit = str_contains($quote, 'P3999') && (int) $later->closures === 0;
        } else {
            $explicit = (bool) preg_match(self::LINE_CLOSURE_TEXT, $text);
        }
        if (! $explicit) {
            return [];
        }
        if ((int) $later->reopen > 0) {
            return ['why' => 'reste de la ligne non appliqué : ouverture ou réouverture postérieure'];
        }

        $famSql = '{'.implode(',', $family).'}';
        // Fermetures antérieures de la famille dont la portion reste inconnue
        // (citée mais non localisée, ou sans portion et non appliquée).
        $pending = DB::selectOne(
            "SELECT string_agg(id::text, ', ' ORDER BY event_date) AS ids FROM line_events
             WHERE line_id = ? AND id <> ? AND type = ANY(?::text[]) AND event_date <= ?
               AND NOT (section_resolution IS NOT NULL AND section_resolution IN ('pk', 'places', 'trace', 'whole_line'))
               AND NOT (section_resolution IS NULL AND section_from IS NULL AND section_to IS NULL AND section_pk_start_m IS NULL)",
            [$this->line->id, $e->id, $famSql, $e->event_date],
        )->ids;
        if ($pending) {
            return ['why' => "reste de la ligne non calculable : fermeture(s) antérieure(s) non localisée(s) (événements {$pending})"];
        }

        $r = DB::selectOne(
            "WITH prev AS (
                SELECT ST_Buffer(ST_Union(ST_Transform(section_geom, 2154)), 30) AS b, string_agg(id::text, ', ' ORDER BY event_date) AS ids
                FROM line_events
                WHERE line_id = ? AND id <> ? AND type = ANY(?::text[]) AND event_date <= ?
                  AND section_geom IS NOT NULL AND section_resolution IN ('pk', 'places', 'trace', 'whole_line')
             ), car AS (SELECT ST_Union(geom) AS g FROM sr_parts),
             rest AS (
                SELECT ST_Collect(d.geom) AS g FROM car, prev,
                       ST_Dump(ST_CollectionExtract(CASE WHEN prev.b IS NULL THEN car.g ELSE ST_Difference(car.g, prev.b) END, 2)) d
                WHERE ST_Length(d.geom) >= 200
             )
             SELECT ST_Length(car.g) AS total, COALESCE(ST_Length(rest.g), 0) AS rest, prev.ids,
                    ST_Multi(ST_Transform(ST_LineMerge(rest.g), 4326)) AS g
             FROM car, prev, rest",
            [$this->line->id, $e->id, $famSql, $e->event_date],
        );
        if (! $r || $r->total <= 0) {
            return [];
        }
        if ($r->rest < max(300, 0.02 * $r->total)) {
            return ['why' => 'reste de la ligne vide : déjà couverte par les fermetures antérieures ('.$r->ids.')'];
        }
        if ($r->ids === null || $r->rest >= 0.98 * $r->total) {
            DB::update(
                "UPDATE line_events SET section_geom = (SELECT ST_Multi(ST_Transform(ST_LineMerge(ST_Collect(geom)), 4326)) FROM sr_parts),
                    section_length_m = (SELECT ROUND(SUM(ST_Length(geom))) FROM sr_parts),
                    section_resolution = 'whole_line', section_resolution_note = ? WHERE id = ?",
                [($r->ids === null ? 'Fermeture de la ligne après fermetures partielles d\'une autre nature' : 'Fermeture de la ligne, fermetures antérieures ('.$r->ids.') négligeables')
                    .' : toute la ligne (tracé '.$this->carrier.')', $e->id],
            );

            return ['resolution' => 'whole_line'];
        }
        $note = sprintf('Reste de la ligne : %.1f km sur %.1f non couverts par les fermetures antérieures (%s) ; le texte vise la ligne entière (tracé %s)',
            $r->rest / 1000, $r->total / 1000, $r->ids, $this->carrier);
        $n = DB::update(
            "UPDATE line_events SET section_geom = ?::geometry, section_length_m = ?, section_resolution = 'places', section_resolution_note = ?
             WHERE id = ? AND ST_GeometryType(?::geometry) = 'ST_MultiLineString'",
            [$r->g, (int) round($r->rest), mb_substr($note, 0, 500), $e->id, $r->g],
        );

        return $n ? ['resolution' => 'places'] : ['why' => 'reste de la ligne : géométrie invalide'];
    }

    // ------------------------------------------------------------------
    // Tracé porteur → graphe
    // ------------------------------------------------------------------

    /** Tracés sources supplémentaires (ids) à inclure : tests, corrections manuelles. */
    public array $extraTraceIds = [];

    /** Ordre de précision des tracés sources (les plus précis d'abord). */
    private const TRACE_ORDER = ['bdtopo', 'osm', 'ohm', 'archeoferroviaire'];

    /**
     * Tracé porteur : tracé de référence s'il existe ; sinon géométrie SNCF,
     * complétée source par source (de la plus précise à la moins précise)
     * par les seules parties des tracés rattachés qui ne doublent pas déjà
     * le tracé accumulé (> 60 m) - évite les doublons parallèles multi-sources.
     */
    private function loadCarrier(): void
    {
        $this->carrier = null;
        $this->parts = $this->adj = $this->nodes = [];
        $this->adjWide = null;
        DB::statement('DROP TABLE IF EXISTS sr_parts');
        DB::statement('DROP TABLE IF EXISTS sr_axis');
        DB::statement('DROP TABLE IF EXISTS sr_acc');
        DB::statement('CREATE TEMP TABLE sr_acc (geom geometry(Geometry, 2154))');

        $labels = [];
        if (Schema::hasTable('line_reference_geometries')
            && DB::affectingStatement('INSERT INTO sr_acc SELECT ST_Transform(geom, 2154) FROM line_reference_geometries WHERE line_id = ?', [$this->line->id]) > 0) {
            $labels[] = 'référence';
        }
        if (! $labels) {
            if ($this->line->code && DB::selectOne('SELECT EXISTS (SELECT 1 FROM sncf_line_geometries WHERE code_ligne = ?) e', [$this->line->code])->e) {
                DB::insert('INSERT INTO sr_acc SELECT ST_Union(ST_Transform(geom, 2154)) FROM sncf_line_geometries WHERE code_ligne = ?', [$this->line->code]);
                $labels[] = 'SNCF';
            }
            $extra = '{'.implode(',', array_map('intval', $this->extraTraceIds)).'}';
            $srcs = DB::table('source_traces')->where(fn ($w) => $w->where('line_id', $this->line->id)->orWhereRaw('id = ANY(?::bigint[])', [$extra]))
                ->distinct()->pluck('source')->all();
            usort($srcs, fn ($a, $b) => (array_search($a, self::TRACE_ORDER, true) === false ? 99 : array_search($a, self::TRACE_ORDER, true))
                <=> (array_search($b, self::TRACE_ORDER, true) === false ? 99 : array_search($b, self::TRACE_ORDER, true)));
            foreach ($srcs as $src) {
                $added = DB::selectOne(
                    'WITH t AS (SELECT ST_Union(ST_Transform(geom, 2154)) g FROM source_traces
                                WHERE source = ? AND (line_id = ? OR id = ANY(?::bigint[]))),
                          acc AS (SELECT ST_Buffer(ST_Union(geom), 60) b FROM sr_acc),
                          d AS (SELECT ST_CollectionExtract(CASE WHEN acc.b IS NULL THEN t.g ELSE ST_Difference(t.g, acc.b) END, 2) g FROM t, acc)
                     SELECT ST_Length(g) len, g FROM d',
                    [$src, $this->line->id, $extra],
                );
                if ($added && $added->len > 100) {
                    DB::insert('INSERT INTO sr_acc VALUES (?::geometry)', [$added->g]);
                    $labels[] = $src;
                }
            }
        }
        if ($labels) {
            DB::statement(
                'CREATE TEMP TABLE sr_parts AS
                 SELECT (row_number() OVER ())::int AS id, d.geom::geometry(LineString, 2154) AS geom
                 FROM (SELECT (ST_Dump(ST_LineMerge(ST_Union(ST_SnapToGrid(geom, 0.5))))).geom AS geom FROM sr_acc) d
                 WHERE ST_Length(d.geom) > 20'
            );
            DB::statement('CREATE INDEX ON sr_parts USING GIST (geom)');
            if (DB::selectOne('SELECT COUNT(*) n FROM sr_parts')->n > 0) {
                $this->carrier = implode(' + ', $labels);
            }
        }
        if ($this->carrier === null) {
            DB::statement('DROP TABLE IF EXISTS sr_parts');
            DB::statement('CREATE TEMP TABLE sr_parts (id int, geom geometry(LineString, 2154))');

            return;
        }

        $key = fn (float $x, float $y) => sprintf('%.1f,%.1f', round($x * 2) / 2, round($y * 2) / 2);
        foreach (DB::select('SELECT id, ST_Length(geom) len, ST_X(ST_StartPoint(geom)) ax, ST_Y(ST_StartPoint(geom)) ay,
                ST_X(ST_EndPoint(geom)) bx, ST_Y(ST_EndPoint(geom)) by FROM sr_parts') as $p) {
            $a = $key($p->ax, $p->ay);
            $b = $key($p->bx, $p->by);
            $this->nodes[$a] = [$p->ax, $p->ay];
            $this->nodes[$b] = [$p->bx, $p->by];
            $this->parts[$p->id] = ['len' => (float) $p->len, 'a' => $a, 'b' => $b];
            $this->adj[$a][] = [$b, (float) $p->len, ['part' => $p->id, 'f1' => 0.0, 'f2' => 1.0]];
            $this->adj[$b][] = [$a, (float) $p->len, ['part' => $p->id, 'f1' => 1.0, 'f2' => 0.0]];
        }
        $this->bridgeComponents();
    }

    /**
     * Recoud les composantes disjointes (trous du tracé, tronçon partagé avec
     * une autre ligne…) par des ponts rectilignes entre extrémités les plus
     * proches, à la Kruskal, jusqu'à GAP_MAX_M.
     */
    private function bridgeComponents(float $maxGap = self::GAP_MAX_M): void
    {
        $comp = [];
        $c = 0;
        foreach (array_keys($this->nodes) as $n) {
            if (isset($comp[$n])) {
                continue;
            }
            $stack = [$n];
            $comp[$n] = $c;
            while ($stack) {
                $u = array_pop($stack);
                foreach ($this->adj[$u] ?? [] as [$v]) {
                    if (! isset($comp[$v])) {
                        $comp[$v] = $c;
                        $stack[] = $v;
                    }
                }
            }
            $c++;
        }
        if ($c < 2) {
            return;
        }

        // Extrémités libres (degré 1) en priorité ; à défaut tous les nœuds.
        $ends = array_filter(array_keys($this->nodes), fn ($n) => count($this->adj[$n] ?? []) === 1);
        if (count(array_unique(array_map(fn ($n) => $comp[$n], $ends))) < $c) {
            $ends = array_keys($this->nodes);
        }
        $ends = array_values($ends);
        $pairs = [];
        $cnt = count($ends);
        for ($i = 0; $i < $cnt; $i++) {
            [$xi, $yi] = $this->nodes[$ends[$i]];
            for ($j = $i + 1; $j < $cnt; $j++) {
                if ($comp[$ends[$i]] === $comp[$ends[$j]]) {
                    continue;
                }
                [$xj, $yj] = $this->nodes[$ends[$j]];
                $d = hypot($xi - $xj, $yi - $yj);
                if ($d <= $maxGap) {
                    $pairs[] = [$d, $ends[$i], $ends[$j]];
                }
            }
        }
        usort($pairs, fn ($a, $b) => $a[0] <=> $b[0]);
        $parent = range(0, $c - 1);
        $find = function ($x) use (&$parent, &$find) {
            return $parent[$x] === $x ? $x : ($parent[$x] = $find($parent[$x]));
        };
        foreach ($pairs as [$d, $u, $v]) {
            $ru = $find($comp[$u]);
            $rv = $find($comp[$v]);
            if ($ru === $rv) {
                continue;
            }
            $parent[$ru] = $rv;
            $this->adj[$u][] = [$v, $d, ['gap' => true]];
            $this->adj[$v][] = [$u, $d, ['gap' => true]];
        }
    }

    /**
     * Plus court chemin en autorisant des trous jusqu'à GAP_WIDE_M (dernier
     * recours, tracé de référence fragmenté).
     */
    private function pathWide(array $from, array $to): ?array
    {
        $save = $this->adj;
        if ($this->adjWide === null) {
            $this->bridgeComponents(self::GAP_WIDE_M);
            $this->adjWide = $this->adj;
            $this->adj = $save;
        }
        $this->adj = $this->adjWide;
        try {
            return $this->path($from, $to);
        } finally {
            $this->adj = $save;
        }
    }

    /**
     * Projette des points (lon/lat) sur le tracé.
     *
     * @param  array<string, array{0: float, 1: float}>  $points  clé => [lon, lat]
     * @return array<string, array{part: int, f: float, d: float}>
     */
    private function locate(array $points): array
    {
        $out = [];
        foreach (array_chunk($points, 500, true) as $chunk) {
            $values = [];
            $bind = [];
            foreach ($chunk as $k => [$lon, $lat]) {
                $values[] = '(?::text, ?::float8, ?::float8)';
                array_push($bind, (string) $k, $lon, $lat);
            }
            $rows = DB::select(
                'SELECT v.k, n.id, n.f, n.d FROM (VALUES '.implode(',', $values).') v(k, lon, lat)
                 CROSS JOIN LATERAL (
                    SELECT p.id, ST_LineLocatePoint(p.geom, q.pt) f, ST_Distance(p.geom, q.pt) d
                    FROM sr_parts p, (SELECT ST_Transform(ST_SetSRID(ST_MakePoint(v.lon, v.lat), 4326), 2154) pt) q
                    ORDER BY p.geom <-> q.pt LIMIT 1) n',
                $bind,
            );
            foreach ($rows as $r) {
                $out[$r->k] = ['part' => (int) $r->id, 'f' => (float) $r->f, 'd' => (float) $r->d];
            }
        }

        return $out;
    }

    /**
     * Plus court chemin entre deux positions sur le graphe.
     *
     * @return array{pieces: list<array>, length: float, gap: float}|null
     */
    private function path(array $from, array $to): ?array
    {
        $extra = [];
        $link = function (string $v, array $loc) use (&$extra) {
            $p = $this->parts[$loc['part']];
            $extra[$v][] = [$p['a'], $loc['f'] * $p['len'], ['part' => $loc['part'], 'f1' => $loc['f'], 'f2' => 0.0]];
            $extra[$v][] = [$p['b'], (1 - $loc['f']) * $p['len'], ['part' => $loc['part'], 'f1' => $loc['f'], 'f2' => 1.0]];
            $extra[$p['a']][] = [$v, $loc['f'] * $p['len'], ['part' => $loc['part'], 'f1' => 0.0, 'f2' => $loc['f']]];
            $extra[$p['b']][] = [$v, (1 - $loc['f']) * $p['len'], ['part' => $loc['part'], 'f1' => 1.0, 'f2' => $loc['f']]];
        };
        $link('@S', $from);
        $link('@T', $to);
        if ($from['part'] === $to['part']) {
            $len = abs($to['f'] - $from['f']) * $this->parts[$from['part']]['len'];
            $extra['@S'][] = ['@T', $len, ['part' => $from['part'], 'f1' => $from['f'], 'f2' => $to['f']]];
        }

        $dist = ['@S' => 0.0];
        $prev = [];
        $pq = new \SplPriorityQueue;
        $pq->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
        $pq->insert('@S', 0.0);
        $done = [];
        while (! $pq->isEmpty()) {
            $top = $pq->extract();
            $u = $top['data'];
            if (isset($done[$u])) {
                continue;
            }
            $done[$u] = true;
            if ($u === '@T') {
                break;
            }
            foreach (isset($extra[$u]) ? array_merge($this->adj[$u] ?? [], $extra[$u]) : ($this->adj[$u] ?? []) as [$v, $w, $edge]) {
                $nd = $dist[$u] + $w;
                if (! isset($dist[$v]) || $nd < $dist[$v] - 1e-9) {
                    $dist[$v] = $nd;
                    $prev[$v] = [$u, $edge, $w];
                    $pq->insert($v, -$nd);
                }
            }
        }
        if (! isset($dist['@T'])) {
            return null;
        }

        $pieces = [];
        $gap = 0.0;
        for ($v = '@T'; $v !== '@S'; $v = $prev[$v][0]) {
            [, $edge, $w] = $prev[$v];
            if (isset($edge['gap'])) {
                $gap += $w;
                $pieces[] = ['gap' => $w];
            } else {
                $pieces[] = $edge + ['len' => $w];
            }
        }

        return ['pieces' => array_reverse($pieces), 'length' => $dist['@T'], 'gap' => $gap];
    }

    // ------------------------------------------------------------------
    // Méthode PK
    // ------------------------------------------------------------------

    /**
     * @return array{pieces?: list<array>, note?: string, error?: string}
     */
    private function byPk(int $pkA, int $pkB): array
    {
        if ($this->calib === null && $this->calibError === null) {
            $this->calibrate();
        }
        if ($this->calib === null) {
            return ['error' => 'PK non calables : '.$this->calibError];
        }

        $c = $this->calib;
        $m1 = $this->pkToMeasure(min($pkA, $pkB));
        $m2 = $this->pkToMeasure(max($pkA, $pkB));
        $len = $c['length'];
        $outside = max(0, -$m1) + max(0, $m2 - $len);
        $m1c = max(0.0, min($len, $m1));
        $m2c = max(0.0, min($len, $m2));
        if ($m2c - $m1c < 1) {
            return ['error' => sprintf('PK %s à %s hors du tracé connu (tracé calé de PK %s à PK %s)',
                $this->km($pkA), $this->km($pkB), $this->km($c['pk_min_axis']), $this->km($c['pk_max_axis']))];
        }
        $note = sprintf('PK %s à %s calés sur %d points de contrôle (%s)', $this->km($pkA), $this->km($pkB), count($c['points']), $c['sources']);
        if ($outside > 200) {
            $note .= sprintf(' ; %d m hors du tracé connu, tronqués', $outside);
        }
        if ($min = $this->nearestControlGap(min($pkA, $pkB), max($pkA, $pkB))) {
            $note .= $min;
        }

        return ['pieces' => $this->slicePieces($c['pieces'], $m1c, $m2c), 'note' => $note];
    }

    private function nearestControlGap(int $a, int $b): string
    {
        // Distance au point de contrôle le plus proche de chaque borne (fiabilité).
        $d = function (int $pk) {
            $best = PHP_INT_MAX;
            foreach ($this->calib['points'] as [, $p]) {
                $best = min($best, abs($p - $pk));
            }

            return $best;
        };
        $worst = max($d($a), $d($b));

        return $worst > 3000 ? sprintf(' ; borne à %.1f km du point de contrôle le plus proche', $worst / 1000) : '';
    }

    private function haversine(array $a, array $b): float
    {
        [$lon1, $lat1] = array_map('deg2rad', $a);
        [$lon2, $lat2] = array_map('deg2rad', $b);
        $h = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(($lon2 - $lon1) / 2) ** 2;

        return 2 * 6371000 * asin(min(1, sqrt($h)));
    }

    private function km(int|float $m): string
    {
        return number_format($m / 1000, 3, ',', '');
    }

    /**
     * Calage des PK : points de contrôle projetés sur un AXE (chemin du plus
     * petit au plus grand PK, prolongé aux deux bouts), puis plus longue
     * sous-suite où PK et position croissent ensemble.
     *
     * Deux passes : (1) points officiels SNCF (gares, PN, extrémités des
     * tronçons) ; (2) + « lieux à PK connu » - gares des sources sans
     * coordonnées (source_stations) et couples lieu/PK des événements
     * (« De Verneuil-l'Étang à Fontenay-Trésigny (PK 54,320 à 64,100) »),
     * géocodés sur des gares / stations. La passe 2 étend le calage aux
     * sections déclassées, où la SNCF ne publie plus rien.
     */
    private function calibrate(): void
    {
        $cps = [];
        $add = function (string $kind, $rows) use (&$cps) {
            foreach ($rows as $i => $r) {
                if ($r->pk === null || $r->lon === null || $r->lat === null) {
                    continue;
                }
                $cps["{$kind}:{$i}"] = ['lon' => (float) $r->lon, 'lat' => (float) $r->lat, 'pk' => (int) $r->pk, 'kind' => $kind];
            }
        };
        if ($this->line->code) {
            $add('gare', DB::select('SELECT pk_m pk, lon, lat FROM sncf_gares WHERE code_ligne = ? AND pk_m IS NOT NULL', [$this->line->code]));
            if (Schema::hasTable('sncf_level_crossings')) {
                $add('PN', DB::select('SELECT pk_m pk, lon, lat FROM sncf_level_crossings WHERE code_ligne = ? AND pk_m IS NOT NULL', [$this->line->code]));
            }
            $add('tronçon', DB::select(
                'SELECT pk_debut_m pk, ST_X(ST_StartPoint(geom)) lon, ST_Y(ST_StartPoint(geom)) lat FROM sncf_line_geometries WHERE code_ligne = ?
                 UNION ALL SELECT pk_fin_m, ST_X(ST_EndPoint(geom)), ST_Y(ST_EndPoint(geom)) FROM sncf_line_geometries WHERE code_ligne = ?',
                [$this->line->code, $this->line->code],
            ));
        }
        if (Schema::hasTable('source_stations')) {
            $add('gare source', DB::select('SELECT pk_m pk, lon, lat FROM source_stations WHERE line_id = ? AND pk_m IS NOT NULL', [$this->line->id]));
        }
        // Gares consolidées rattachées à la ligne avec un PK SNCF (utile pour les
        // lignes sans code ou dont les gares SNCF portent un autre code).
        $consolidated = Schema::hasTable('station_lines') ? $this->consolidatedPk() : [];
        $add('gare consolidée', $consolidated['sncf'] ?? []);

        $first = count($cps) >= 2 ? $this->calibrateWith($cps) : null;
        $named = $this->namedPkPoints($first);
        // PK lignes-oubliées des gares consolidées (positions issues d'autres
        // sources que lignes-oubliées) : même contrôle de repère que les gares nommées.
        $lo = [];
        foreach ($consolidated['lignes-oubliees'] ?? [] as $i => $r) {
            $lo["gare consolidée PK LO:{$i}"] = ['lon' => (float) $r->lon, 'lat' => (float) $r->lat, 'pk' => (int) $r->pk, 'kind' => 'gare consolidée PK LO'];
        }
        if ($lo) {
            $named += $this->checkPkOrigin($first, $lo);
        }
        if ($named) {
            $this->calib = null;
            $second = $this->calibrateWith($cps + $named);
            // On garde la passe 2 si elle couvre au moins autant que la passe 1.
            if ($second && (! $first || count($second['points']) >= count($first['points']))) {
                $this->calib = $second;

                return;
            }
        }
        if ($first) {
            $this->calib = $first;

            return;
        }
        $this->calibError ??= count($cps) + count($named) < 2
            ? 'moins de deux points de contrôle (gares / PN / tronçons SNCF / lieux à PK connu)'
            : 'points de contrôle incohérents avec le tracé';
    }

    /**
     * PK des gares consolidées de la ligne (station_lines), par provenance du PK.
     * Les PK projetés (calculés depuis un tracé) sont exclus : circulaires. Les
     * gares placées par lignes-oubliées (position déduite d'un PK) aussi.
     *
     * @return array<string, list<object>>
     */
    private function consolidatedPk(): array
    {
        $out = [];
        foreach (DB::select(
            "SELECT sl.pk_source, sl.pk_m pk, ST_X(s.geom) lon, ST_Y(s.geom) lat FROM station_lines sl JOIN stations s ON s.id = sl.station_id
             WHERE sl.line_id = ? AND sl.pk_m IS NOT NULL AND sl.pk_source IN ('sncf', 'lignes-oubliees')
               AND COALESCE(s.position_source, '') <> 'lignes-oubliees' AND NOT s.position_flagged",
            [$this->line->id],
        ) as $r) {
            $out[$r->pk_source][] = $r;
        }

        return $out;
    }

    /** Distance maximale d'un point de contrôle au tracé, selon sa nature. */
    private function cpMaxDist(string $kind): float
    {
        if (str_starts_with($kind, 'gare source') || str_starts_with($kind, 'gare consolidée')) {
            return 1000;
        }

        return $this->carrier === 'SNCF' ? self::CP_MAX_DIST_M : 2 * self::CP_MAX_DIST_M;
    }

    /**
     * Lieux nommés à PK connu, géocodés sur des gares / stations proches du tracé.
     *
     * @return array<string, array{lon: float, lat: float, pk: int, kind: string}>
     */
    private function namedPkPoints(?array $first): array
    {
        $out = [];
        $byName = []; // nom normalisé → [lon, lat] (géocodage une seule fois)
        $geo = function (string $name) use (&$byName): ?array {
            $k = $this->text->norm($name);
            if (! array_key_exists($k, $byName)) {
                $g = $this->geocodeStations($name);
                $byName[$k] = $g && $g['loc']['d'] <= self::CP_MAX_DIST_M ? $g : null;
            }

            return $byName[$k];
        };
        $point = fn (array $g) => DB::selectOne(
            'SELECT ST_X(pt) lon, ST_Y(pt) lat FROM (SELECT ST_Transform(ST_LineInterpolatePoint(geom, ?), 4326) pt FROM sr_parts WHERE id = ?) q',
            [max(0.0, min(1.0, $g['loc']['f'])), $g['loc']['part']],
        );

        // Gares nommées à PK connu : lignes-oubliées (source_stations sans
        // coordonnées) et schémas de ligne Wikipédia.
        $named = [];
        if (Schema::hasTable('source_stations')) {
            foreach (DB::select('SELECT name, pk_m, source FROM source_stations WHERE line_id = ? AND pk_m IS NOT NULL AND lat IS NULL', [$this->line->id]) as $r) {
                $named[] = [$r->name, (int) $r->pk_m, 'gare '.$r->source];
            }
        }
        if (Schema::hasTable('wikipedia_line_stations')) {
            foreach (DB::select('SELECT name, pk_m FROM wikipedia_line_stations WHERE line_id = ?', [$this->line->id]) as $r) {
                $named[] = [$r->name, (int) $r->pk_m, 'gare schéma Wikipédia'];
            }
        }
        $byKind = [];
        foreach ($named as $i => [$name, $pk, $kind]) {
            if (! ($g = $geo($name))) {
                continue;
            }
            $p = $point($g);
            $byKind[$kind]["{$kind}:{$i}"] = ['lon' => (float) $p->lon, 'lat' => (float) $p->lat, 'pk' => $pk, 'kind' => $kind];
        }
        foreach ($byKind as $kind => $cands) {
            $out += $this->checkPkOrigin($first, $cands);
        }

        $pairs = DB::select(
            'SELECT DISTINCT section_from, section_to, section_pk_start_m a, section_pk_end_m b FROM line_events
             WHERE line_id = ? AND section_from IS NOT NULL AND section_to IS NOT NULL
               AND section_pk_start_m IS NOT NULL AND section_pk_end_m IS NOT NULL AND section_pk_end_m - section_pk_start_m > 300',
            [$this->line->id],
        );
        foreach ($pairs as $i => $r) {
            if ($this->text->norm($r->section_from) === $this->text->norm($r->section_to)) {
                continue;
            }
            $ga = $geo($r->section_from);
            $gb = $geo($r->section_to);
            if (! $ga || ! $gb) {
                continue;
            }
            // Cohérence : la distance sur le tracé entre les deux lieux doit
            // correspondre à l'écart de PK (± 20 %), sinon les bornes de la
            // section ne sont pas les gares (« section à X (PK …) »).
            $pp = $this->path($ga['loc'], $gb['loc']);
            $dpk = $r->b - $r->a;
            if (! $pp || abs($pp['length'] - $dpk) > max(400, 0.2 * $dpk)) {
                continue;
            }
            $pa = $point($ga);
            $pb = $point($gb);
            // Quel lieu porte le plus petit PK ? Selon le calage officiel s'il
            // existe, sinon l'ordre d'écriture (« de X à Y (PK a à b) »).
            $fromIsMin = true;
            if ($first) {
                $m = $this->measureOnAxis($first, [
                    'a' => ['lon' => (float) $pa->lon, 'lat' => (float) $pa->lat, 'kind' => 'x'],
                    'b' => ['lon' => (float) $pb->lon, 'lat' => (float) $pb->lat, 'kind' => 'x'],
                ], 5000);
                if (isset($m['a'], $m['b'])) {
                    $fromIsMin = $m['a'] <= $m['b'];
                }
            }
            $out["lieu+PK:{$i}:a"] = ['lon' => (float) $pa->lon, 'lat' => (float) $pa->lat, 'pk' => (int) ($fromIsMin ? $r->a : $r->b), 'kind' => 'lieu+PK'];
            $out["lieu+PK:{$i}:b"] = ['lon' => (float) $pb->lon, 'lat' => (float) $pb->lat, 'pk' => (int) ($fromIsMin ? $r->b : $r->a), 'kind' => 'lieu+PK'];
        }
        // Un même lieu cité avec plusieurs PK (sections contiguës) : on garde la médiane.
        $groups = [];
        foreach ($out as $k => $c) {
            $groups[sprintf('%.5f,%.5f', $c['lon'], $c['lat'])][] = $k;
        }
        foreach ($groups as $keys) {
            if (count($keys) < 2) {
                continue;
            }
            $pks = array_map(fn ($k) => $out[$k]['pk'], $keys);
            sort($pks);
            $med = $pks[intdiv(count($pks), 2)];
            foreach (array_slice($keys, 1) as $k) {
                unset($out[$k]);
            }
            $out[$keys[0]]['pk'] = $med;
        }

        return $out;
    }

    /**
     * Les PK d'une source nommée (schéma Wikipédia, lignes-oubliées) sont-ils
     * dans le même repère que la SNCF ? On compare, sur la partie déjà calée,
     * le PK annoncé au PK déduit du calage officiel : écart médian faible →
     * on garde (sauf points aberrants) ; écart constant → origine décalée,
     * on recale ; écarts dispersés → on écarte toute la source.
     */
    private function checkPkOrigin(?array $first, array $cands): array
    {
        if (! $first || ! $cands) {
            return $cands;
        }
        $meas = $this->measureOnAxis($first, $cands, 400);
        $lo = $first['points'][0][0];
        $hi = end($first['points'])[0];
        $diffs = [];
        foreach ($meas as $k => $m) {
            if ($m < $lo - 500 || $m > $hi + 500) {
                continue;
            }
            $diffs[$k] = $cands[$k]['pk'] - $this->measureToPkWith($first, $m);
        }
        if (count($diffs) < 2) {
            return $cands;
        } // rien à comparer : le filtre monotone tranchera
        $sorted = array_values($diffs);
        sort($sorted);
        $med = $sorted[intdiv(count($sorted), 2)];
        $dev = array_map(fn ($d) => abs($d - $med), $sorted);
        sort($dev);
        $mad = $dev[intdiv(count($dev), 2)];
        if ($mad > 500) {
            return [];
        } // repère incohérent
        $shift = abs($med) > 600 ? $med : 0;
        foreach ($cands as $k => &$c) {
            if (isset($diffs[$k]) && abs($diffs[$k] - $med) > 1500) {
                unset($cands[$k]);

                continue;
            }
            $c['pk'] = (int) round($c['pk'] - $shift);
        }
        unset($c);

        return $cands;
    }

    private function measureToPkWith(array $calib, float $m): float
    {
        $pts = $calib['points'];
        $n = count($pts);
        if ($m <= $pts[0][0]) {
            return $pts[0][1] - ($pts[0][0] - $m);
        }
        if ($m >= $pts[$n - 1][0]) {
            return $pts[$n - 1][1] + ($m - $pts[$n - 1][0]);
        }
        for ($i = 0; $i < $n - 1; $i++) {
            [$m1, $p1] = $pts[$i];
            [$m2, $p2] = $pts[$i + 1];
            if ($m >= $m1 && $m <= $m2) {
                return $p1 + ($m - $m1) * ($p2 - $p1) / max(1e-6, $m2 - $m1);
            }
        }

        return $pts[$n - 1][1];
    }

    /**
     * @param  array<string, array{lon: float, lat: float, pk: int, kind: string}>  $cps
     */
    private function calibrateWith(array $cps): ?array
    {
        $loc = $this->locate(array_map(fn ($c) => [$c['lon'], $c['lat']], $cps));
        foreach ($cps as $k => $c) {
            // Tracé non SNCF (référence multi-sources) : décalages plus forts tolérés.
            if (! isset($loc[$k]) || $loc[$k]['d'] > $this->cpMaxDist($c['kind'])) {
                unset($cps[$k]);
            } else {
                $cps[$k]['loc'] = $loc[$k];
            }
        }
        if (count($cps) < 2) {
            $this->calibError = 'points de contrôle trop loin du tracé';

            return null;
        }

        $excluded = [];
        for ($iter = 0; $iter < 10; $iter++) {
            $usable = array_diff_key($cps, $excluded);
            if (count($usable) < 2) {
                break;
            }
            uasort($usable, fn ($a, $b) => $a['pk'] <=> $b['pk']);
            $kA = array_key_first($usable);
            $kB = array_key_last($usable);
            if ($usable[$kA]['pk'] === $usable[$kB]['pk']) {
                break;
            }
            $axis = $this->buildAxis($usable[$kA]['loc'], $usable[$kB]['loc']);
            if (! $axis) {
                $excluded[$kA] = true;

                continue;
            }
            $meas = $this->measureOnAxis($axis, $usable);
            $pts = [];
            foreach ($meas as $k => $m) {
                $pts[] = [$m, $usable[$k]['pk'], $k];
            }
            usort($pts, fn ($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
            $keep = $this->dropSlopeOutliers($this->longestIncreasing($pts));
            $kept = array_column($keep, 2);
            if (count($keep) >= 2 && in_array($kA, $kept, true) && in_array($kB, $kept, true)) {
                if (self::stretchedCalibration($keep)) {
                    // Pas de point de contrôle à écarter avec certitude : pas de calage (repli sur les lieux).
                    $this->calibError = 'points de contrôle incohérents avec le tracé (tracé plus de '.self::MAX_PK_STRETCH.' fois plus long ou plus court que l\'écart de PK)';

                    return null;
                }
                $kinds = array_count_values(array_map(fn ($k) => $usable[$k]['kind'], $kept));
                arsort($kinds);

                return [
                    'pieces' => $axis['pieces'],
                    'length' => $axis['length'],
                    'points' => array_map(fn ($p) => [$p[0], $p[1]], $keep),
                    'pk_min_axis' => (int) round($keep[0][1] - $keep[0][0]),
                    'pk_max_axis' => (int) round(end($keep)[1] + ($axis['length'] - end($keep)[0])),
                    'sources' => implode(', ', array_map(fn ($k, $v) => "{$v} {$k}", array_keys($kinds), $kinds)),
                ];
            }
            // Les extrêmes sont incohérents : on écarte tout ce que le filtre
            // monotone a rejeté (axe recalculé entre les extrêmes retenus).
            if (count($keep) >= 2) {
                foreach (array_keys($usable) as $k) {
                    if (! in_array($k, $kept, true)) {
                        $excluded[$k] = true;
                    }
                }
            } else {
                $excluded[$kA] = true;
            }
        }
        $this->calibError = 'points de contrôle incohérents avec le tracé';

        return null;
    }

    /**
     * Axe = [bout de ligne au-delà de A] + chemin A→B + [bout au-delà de B].
     */
    private function buildAxis(array $locA, array $locB): ?array
    {
        $main = $this->path($locA, $locB);
        if (! $main || $main['length'] < 1) {
            return null;
        }
        $before = $this->extension($locA, $locB);
        $after = $this->extension($locB, $locA);
        $pieces = array_merge($before ? $this->reversePieces($before['pieces']) : [], $main['pieces'], $after['pieces'] ?? []);
        $length = 0.0;
        foreach ($pieces as $p) {
            $length += $p['gap'] ?? $p['len'];
        }

        DB::statement('DROP TABLE IF EXISTS sr_axis');
        $sql = $this->piecesSql($pieces, true);
        if (! $sql) {
            return null;
        }
        DB::statement("CREATE TEMP TABLE sr_axis AS SELECT ST_MakeLine(pt ORDER BY seq, k) AS geom FROM ({$sql[0]}) s", $sql[1]);

        return ['pieces' => $pieces, 'length' => $length];
    }

    /**
     * Prolongement de l'axe au-delà de $loc, à l'opposé de $other : chemin
     * vers le nœud le plus lointain situé « derrière » $loc.
     */
    private function extension(array $loc, array $other): ?array
    {
        // Deux Dijkstra « toutes destinations » au lieu d'un chemin par nœud.
        $dLoc = $this->distancesFrom($loc);
        $dOther = $this->distancesFrom($other);
        $base = $this->path($other, $loc)['length'] ?? null;
        if ($base === null) {
            return null;
        }
        $bestNode = null;
        $bestLen = 50.0;
        foreach ($this->nodes as $n => $_) {
            if (count($this->adj[$n] ?? []) !== 1 || ! isset($dLoc[$n], $dOther[$n])) {
                continue;
            }
            // « derrière » : passer par $loc pour aller de $other au nœud.
            if ($dOther[$n] < $base + 0.8 * $dLoc[$n]) {
                continue;
            }
            if ($dLoc[$n] > $bestLen) {
                $bestLen = $dLoc[$n];
                $bestNode = $n;
            }
        }

        return $bestNode ? $this->path($loc, $this->nodeLoc($bestNode)) : null;
    }

    /** @return array<string, float> distance sur le graphe depuis une position vers chaque nœud */
    private function distancesFrom(array $loc): array
    {
        $p = $this->parts[$loc['part']];
        $dist = [];
        $pq = new \SplPriorityQueue;
        foreach ([[$p['a'], $loc['f'] * $p['len']], [$p['b'], (1 - $loc['f']) * $p['len']]] as [$n, $d]) {
            if (! isset($dist[$n]) || $d < $dist[$n]) {
                $dist[$n] = $d;
                $pq->insert($n, -$d);
            }
        }
        $done = [];
        while (! $pq->isEmpty()) {
            $u = $pq->extract();
            if (isset($done[$u])) {
                continue;
            }
            $done[$u] = true;
            foreach ($this->adj[$u] ?? [] as [$v, $w]) {
                $nd = $dist[$u] + $w;
                if (! isset($dist[$v]) || $nd < $dist[$v]) {
                    $dist[$v] = $nd;
                    $pq->insert($v, -$nd);
                }
            }
        }

        return $dist;
    }

    private function nodeLoc(string $n): array
    {
        foreach ($this->adj[$n] as [, , $edge]) {
            if (isset($edge['part'])) {
                return ['part' => $edge['part'], 'f' => $edge['f1']];
            }
        }

        return ['part' => array_key_first($this->parts), 'f' => 0.0];
    }

    private function reversePieces(array $pieces): array
    {
        return array_map(fn ($p) => isset($p['gap']) ? $p : ['part' => $p['part'], 'f1' => $p['f2'], 'f2' => $p['f1'], 'len' => $p['len']], array_reverse($pieces));
    }

    /** @return array<string, float> clé du point → mesure (m) le long de l'axe */
    private function measureOnAxis(array $axis, array $cps, ?float $maxDist = null): array
    {
        $values = [];
        $bind = [];
        foreach ($cps as $k => $c) {
            $values[] = '(?::text, ?::float8, ?::float8)';
            array_push($bind, (string) $k, $c['lon'], $c['lat']);
        }
        $rows = DB::select(
            'SELECT v.k, ST_LineLocatePoint(a.geom, q.pt) * ? AS m, ST_Distance(a.geom, q.pt) AS d
             FROM (VALUES '.implode(',', $values).') v(k, lon, lat)
             CROSS JOIN sr_axis a
             CROSS JOIN LATERAL (SELECT ST_Transform(ST_SetSRID(ST_MakePoint(v.lon, v.lat), 4326), 2154) pt) q',
            array_merge([$axis['length']], $bind),
        );
        $out = [];
        foreach ($rows as $r) {
            $max = $maxDist ?? $this->cpMaxDist($cps[$r->k]['kind'] ?? '');
            if ($r->d <= $max) {
                $out[$r->k] = (float) $r->m;
            }
        }

        return $out;
    }

    /**
     * Plus longue sous-suite (déjà triée par position) aux PK strictement croissants.
     */
    private function longestIncreasing(array $pts): array
    {
        $n = count($pts);
        if ($n === 0) {
            return [];
        }
        $len = array_fill(0, $n, 1);
        $prev = array_fill(0, $n, -1);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $i; $j++) {
                if ($pts[$j][1] < $pts[$i][1] && $pts[$j][0] < $pts[$i][0] && $len[$i] < $len[$j] + 1) {
                    $len[$i] = $len[$j] + 1;
                    $prev[$i] = $j;
                }
            }
        }
        $i = array_search(max($len), $len, true);
        $out = [];
        for (; $i >= 0; $i = $prev[$i]) {
            $out[] = $pts[$i];
        }

        return array_reverse($out);
    }

    /** Rapport longueur sur le tracé / écart de PK au-delà duquel un calage est impossible. */
    public const MAX_PK_STRETCH = 3;

    /**
     * Calage impossible : entre les points de contrôle extrêmes retenus, le tracé est plus de
     * MAX_PK_STRETCH fois plus long, ou plus court, que l'écart de leurs PK (le PK suit la
     * distance réelle, à quelques % près). Signe d'un point de contrôle mal placé (gare
     * homonyme, PK d'une autre origine, forme SNCF généralisée) qu'aucun troisième point ne
     * permet d'écarter : 632000 Monsempron-Libos - Cahors, PK 608,230 à 614,675 (6,4 km) calés
     * à 47,2 km l'un de l'autre, d'où des portions étirées sept fois et une voie « déposée » en
     * 1980 puis en 1990 sur 8,4 km ; puis, ce point écarté, PK 610,826 à 614,675 (3,8 km) calés
     * à 1,1 km l'un de l'autre.
     *
     * @param  list<array{0: float, 1: int}>  $points  [mesure sur le tracé (m), PK (m)], par mesure croissante
     */
    public static function stretchedCalibration(array $points): bool
    {
        if (count($points) < 2) {
            return false;
        }
        $first = $points[0];
        $last = $points[count($points) - 1];
        $dPk = abs($last[1] - $first[1]);

        $dM = abs($last[0] - $first[0]);

        return $dPk > 0 && ($dM > self::MAX_PK_STRETCH * $dPk || $dM * self::MAX_PK_STRETCH < $dPk);
    }

    /**
     * Écarte un point isolé dont les deux pentes (Δposition / ΔPK) avec ses
     * voisins sont aberrantes (> ×3 ou < ÷3) : PK mal saisi, gare homonyme…
     */
    private function dropSlopeOutliers(array $pts): array
    {
        $changed = true;
        while ($changed && count($pts) > 3) {
            $changed = false;
            for ($i = 1; $i < count($pts) - 1; $i++) {
                $s1 = ($pts[$i][0] - $pts[$i - 1][0]) / max(1, $pts[$i][1] - $pts[$i - 1][1]);
                $s2 = ($pts[$i + 1][0] - $pts[$i][0]) / max(1, $pts[$i + 1][1] - $pts[$i][1]);
                $bad = fn ($s) => $s > 3 || $s < 1 / 3;
                $sAround = ($pts[$i + 1][0] - $pts[$i - 1][0]) / max(1, $pts[$i + 1][1] - $pts[$i - 1][1]);
                if ($bad($s1) && $bad($s2) && ! $bad($sAround)) {
                    array_splice($pts, $i, 1);
                    $changed = true;
                    break;
                }
            }
        }

        return $pts;
    }

    private function pkToMeasure(int $pk): float
    {
        $pts = $this->calib['points'];
        $n = count($pts);
        if ($pk <= $pts[0][1]) {
            return $pts[0][0] - ($pts[0][1] - $pk);
        }
        if ($pk >= $pts[$n - 1][1]) {
            return $pts[$n - 1][0] + ($pk - $pts[$n - 1][1]);
        }
        for ($i = 0; $i < $n - 1; $i++) {
            [$m1, $p1] = $pts[$i];
            [$m2, $p2] = $pts[$i + 1];
            if ($pk >= $p1 && $pk <= $p2) {
                return $m1 + ($pk - $p1) * ($m2 - $m1) / max(1, $p2 - $p1);
            }
        }

        return $pts[$n - 1][0];
    }

    /** Sous-ensemble des pièces de l'axe entre deux mesures. */
    private function slicePieces(array $pieces, float $m1, float $m2): array
    {
        $out = [];
        $cum = 0.0;
        foreach ($pieces as $p) {
            $len = $p['gap'] ?? $p['len'];
            $s = $cum;
            $e = $cum + $len;
            $cum = $e;
            if ($e <= $m1 || $s >= $m2 || isset($p['gap']) || $len <= 0) {
                continue;
            }
            $a = max(0.0, ($m1 - $s) / $len);
            $b = min(1.0, ($m2 - $s) / $len);
            $out[] = [
                'part' => $p['part'],
                'f1' => $p['f1'] + ($p['f2'] - $p['f1']) * $a,
                'f2' => $p['f1'] + ($p['f2'] - $p['f1']) * $b,
                'len' => ($b - $a) * $len,
            ];
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Méthode « lieux »
    // ------------------------------------------------------------------

    /**
     * @return array{pieces?: list<array>, note?: string, error?: string}
     */
    private function byPlaces(string $from, string $to): array
    {
        $ends = [];
        foreach (['a' => $from, 'b' => $to] as $k => $name) {
            $g = $this->geocode($name);
            if ($g) {
                $ends[$k] = $g;

                continue;
            }
            // Repli : PK de la gare homonyme de la ligne, puis calage PK.
            $pk = $this->placePk($name);
            if ($pk) {
                if ($this->calib === null && $this->calibError === null) {
                    $this->calibrate();
                }
                if ($this->calib) {
                    $m = $this->pkToMeasure($pk['pk']);
                    if ($m >= -200 && $m <= $this->calib['length'] + 200 && ($loc = $this->measureToLoc($m))) {
                        $ends[$k] = ['loc' => $loc, 'label' => sprintf('gare « %s » PK %s (calage PK)', $pk['label'], $this->km($pk['pk']))];
                    }
                }
            }
        }
        if (! isset($ends['a'], $ends['b'])) {
            return $this->beyondTrace($from, $to, $ends);
        }
        [$a, $b] = [$ends['a'], $ends['b']];

        $path = $this->path($a['loc'], $b['loc']);
        if (! $path) {
            $path = $this->pathWide($a['loc'], $b['loc']);
            if (! $path) {
                return ['error' => "pas de chemin sur le tracé entre « {$from} » et « {$to} »"];
            }
            if ($path['gap'] > self::WIDE_GAP_SHARE * $path['length']) {
                return ['error' => sprintf('pas de chemin sur le tracé entre « %s » et « %s » (trous de %.1f km sur %.1f)', $from, $to, $path['gap'] / 1000, $path['length'] / 1000)];
            }
        }
        if ($path['length'] - $path['gap'] < 50) {
            return ['error' => "extrémités confondues sur le tracé (« {$from} » / « {$to} »)"];
        }
        // Gare à double nom qui contient l'autre extrémité (« La Côte-St-André-le-Rival »
        // pour « La Côte-St-André » face à « Le Rival ») : extrémités indiscernables.
        foreach ([[$a, $to], [$b, $from]] as [$g, $other]) {
            $gn = ' '.$this->text->norm((string) ($g['name'] ?? '')).' ';
            $on = $this->text->norm($other);
            if ($on !== '' && mb_strlen($on) >= 3 && str_contains($gn, ' '.$on.' ') && ! str_contains(' '.$this->text->norm($from === $other ? $to : $from).' ', ' '.$on.' ')) {
                return ['error' => "extrémités indiscernables : « {$g['name']} » contient « {$other} »"];
            }
        }
        if ($path['length'] - $path['gap'] < 300) {
            return ['error' => sprintf('extrémités trop proches sur le tracé (%d m entre « %s » et « %s ») : portion douteuse', $path['length'] - $path['gap'], $from, $to)];
        }
        // Deux lieux éloignés l'un de l'autre mais projetés presque au même
        // endroit : ils sont hors du tracé connu (portion non tracée).
        if (isset($a['pt'], $b['pt'])) {
            $straight = $this->haversine($a['pt'], $b['pt']);
            if ($straight > 1500 && $path['length'] < 0.5 * $straight) {
                return ['error' => sprintf('« %s » et « %s » sont à %.1f km l\'un de l\'autre mais tombent à %d m d\'écart sur le tracé : portion hors du tracé connu', $from, $to, $straight / 1000, $path['length'])];
            }
        }
        $note = sprintf('« %s » → %s ; « %s » → %s', $from, $a['label'], $to, $b['label']);
        if ($path['gap'] > 0) {
            $note .= sprintf(' ; %d m de trou(s) dans le tracé', $path['gap']);
        }

        return ['pieces' => array_values(array_filter($path['pieces'], fn ($p) => ! isset($p['gap']))), 'note' => $note];
    }

    /**
     * Une extrémité (au moins) n'est pas près du tracé. Cas traité : le lieu
     * existe (gare ou commune de ce nom exact) AU-DELÀ d'une extrémité libre
     * du tracé connu (tracé de référence incomplet), à moins de BEYOND_MAX_M,
     * et l'extrémité E est bien ENTRE les deux lieux (A → E → B pas plus de
     * BEYOND_DETOUR fois plus long que A → B à vol d'oiseau) : la portion
     * est arrêtée à l'extrémité du tracé (seule partie dessinable). Si les deux
     * lieux sont au-delà de deux extrémités différentes, la portion est le tracé
     * entre ces extrémités. Sinon : non résolu, avec la raison précise pour
     * chaque lieu (inconnu, trouvé à N km du tracé, au-delà de l'extrémité).
     *
     * @param  array<string, array>  $ends  extrémités déjà localisées ('a' / 'b')
     * @return array{pieces?: list<array>, note?: string, error?: string}
     */
    private function beyondTrace(string $from, string $to, array $ends): array
    {
        $far = [];
        foreach (['a' => $from, 'b' => $to] as $k => $name) {
            if (! isset($ends[$k])) {
                $far[$k] = $this->locateAnywhere($name);
            }
        }
        $usable = fn ($f) => $f && ! $f['ambiguous'] && (($f['beyond'] && $f['loc']['d'] <= self::BEYOND_MAX_M)
            || ($f['kind'] === 'lieu étranger' && $f['loc']['d'] <= self::FOREIGN_NEAR_M));
        // Pour chaque borne : position sur le tracé + point réel du lieu.
        $pos = [];
        foreach (['a', 'b'] as $k) {
            if (isset($ends[$k]['pt'])) {
                $pos[$k] = ['loc' => $ends[$k]['loc'], 'pt' => $ends[$k]['pt'], 'label' => $ends[$k]['label'], 'out' => (float) $ends[$k]['loc']['d']];
            } elseif (isset($far[$k]) && $usable($far[$k])) {
                $pos[$k] = ['loc' => $far[$k]['loc'], 'pt' => $far[$k]['pt'], 'out' => $far[$k]['loc']['d'],
                    'label' => $far[$k]['label'].($far[$k]['beyond'] ? ' au-delà de l\'extrémité du tracé' : sprintf(' à %.1f km du tracé (frontière)', $far[$k]['loc']['d'] / 1000))];
            }
        }
        if (isset($pos['a'], $pos['b']) && $far) {
            $path = $this->path($pos['a']['loc'], $pos['b']['loc']);
            $straight = $this->haversine($pos['a']['pt'], $pos['b']['pt']);
            $inner = $path ? $path['length'] - $path['gap'] : 0.0;
            // Points du tracé où tombent A et B (l'extrémité pour un lieu au-delà).
            $qa = $this->locPoint($pos['a']['loc']);
            $qb = $this->locPoint($pos['b']['loc']);
            $via = $this->haversine($pos['a']['pt'], $qa) + $this->haversine($qa, $qb) + $this->haversine($qb, $pos['b']['pt']);
            if ($path && $inner >= 300 && ($path['gap'] <= self::WIDE_GAP_SHARE * $path['length'])
                && $via <= self::BEYOND_DETOUR * $straight + 1000
                && $path['length'] <= 2.5 * $this->haversine($qa, $qb) + 2000) {
                $note = sprintf('« %s » → %s ; « %s » → %s ; portion arrêtée au tracé connu (%.1f km tracés sur %.1f km à vol d\'oiseau)',
                    $from, $pos['a']['label'], $to, $pos['b']['label'], $inner / 1000, $straight / 1000);

                return ['pieces' => array_values(array_filter($path['pieces'], fn ($p) => ! isset($p['gap']))), 'note' => $note];
            }
        }
        $why = [];
        foreach ($far as $k => $f) {
            $name = $k === 'a' ? $from : $to;
            if (! $f) {
                $why[] = "« {$name} » (inconnu)";
            } elseif ($f['ambiguous']) {
                $why[] = sprintf('« %s » (homonymes à %.1f km du tracé)', $name, $f['loc']['d'] / 1000);
            } elseif ($f['beyond']) {
                $why[] = sprintf('« %s » (%s à %.1f km au-delà de l\'extrémité du tracé)', $name, $f['kind'], $f['loc']['d'] / 1000);
            } else {
                $why[] = sprintf('« %s » (%s à %.1f km du tracé)', $name, $f['kind'], $f['loc']['d'] / 1000);
            }
        }

        return ['error' => 'lieu introuvable près du tracé : '.implode(', ', $why)
            .(isset($pos['a'], $pos['b']) ? ' ; extrémité du tracé pas entre les deux lieux' : '')];
    }

    /**
     * Lieu de ce nom EXACT n'importe où (gares consolidées et SNCF, communes) :
     * le plus proche du tracé, projeté. Sert au diagnostic et à la troncature
     * au-delà d'une extrémité.
     *
     * @return array{loc: array, pt: array, label: string, kind: string, beyond: bool, ambiguous: bool}|null
     */
    private function locateAnywhere(string $name): ?array
    {
        $this->ensureNames();
        $communes = $this->ensureCommunes();
        $qs = array_values(array_unique(array_filter(array_map(fn ($v) => $this->text->norm($v), $this->variants($name)))));
        if (! $qs) {
            return null;
        }
        $arr = '{'.implode(',', array_map(fn ($q) => '"'.str_replace('"', '', $q).'"', $qs)).'}';
        $rows = DB::select(
            "WITH c AS (SELECT ST_Collect(geom) g FROM sr_parts)
             SELECT * FROM (
                SELECT DISTINCT ON (kind, round(lon::numeric, 3), round(lat::numeric, 3)) label, kind, lon, lat FROM (
                    SELECT n.label, CASE WHEN n.kind LIKE 'commune%' THEN 'commune' ELSE 'gare' END AS kind, n.lon, n.lat FROM sr_names n
                    WHERE n.nl = ANY(?::text[]) AND n.kind <> 'commune (gare SNCF)'
                    ".($communes ? "UNION ALL SELECT s.nom, 'commune', ST_X(s.geom), ST_Y(s.geom) FROM sr_communes s WHERE s.nl = ANY(?::text[])" : '').'
                ) u
             ) v, c
             ORDER BY ST_Distance(ST_Transform(ST_SetSRID(ST_MakePoint(v.lon, v.lat), 4326), 2154), c.g)
             LIMIT 6',
            $communes ? [$arr, $arr] : [$arr],
        );
        if (! $rows) {
            foreach ($qs as $q) {
                if (isset(self::FOREIGN_PLACES[$q])) {
                    [$lon, $lat] = self::FOREIGN_PLACES[$q];
                    $rows = [(object) ['label' => $name, 'kind' => 'lieu étranger', 'lon' => $lon, 'lat' => $lat]];
                    break;
                }
            }
        }
        if (! $rows) {
            return null;
        }
        $pts = [];
        foreach ($rows as $i => $r) {
            $pts["x{$i}"] = [(float) $r->lon, (float) $r->lat];
        }
        $loc = $this->locate($pts);
        $best = $rows[0];
        $l = $loc['x0'] ?? null;
        if (! $l) {
            return null;
        }
        // Homonyme assez proche lui aussi, ailleurs : ambigu.
        $ambiguous = false;
        foreach (array_slice($rows, 1) as $i => $r) {
            $o = $loc['x'.($i + 1)] ?? null;
            if ($o && $o['d'] <= max(2 * $l['d'], $l['d'] + 5000)
                && $this->haversine([(float) $best->lon, (float) $best->lat], [(float) $r->lon, (float) $r->lat]) > 5000) {
                $ambiguous = true;
            }
        }

        return ['loc' => $l, 'pt' => [(float) $best->lon, (float) $best->lat], 'kind' => $best->kind, 'ambiguous' => $ambiguous,
            'beyond' => $this->beyondEnd($l), 'label' => sprintf('%s « %s »', $best->kind, $best->label)];
    }

    /**
     * Géocode un nom de lieu À PROXIMITÉ du tracé : gares (toutes sources),
     * puis communes, puis hameaux / lieux-dits, puis communes au nom approché,
     * chaque fois sur toutes les variantes du nom.
     *
     * @return array{loc: array, label: string, pt?: array}|null
     */
    public function geocode(string $name): ?array
    {
        $key = $this->text->norm($name);
        if (array_key_exists($key, $this->placeCache)) {
            return $this->placeCache[$key];
        }

        $variants = $this->variants($name);
        // Lieux-dits : pas sur la variante grossière « premier mot » (« Paris-Lyon » → « Paris »).
        $fine = $this->variants($name, false);
        $best = null;
        foreach ([['geocodeStations', null], ['geocodeCommune', false], ['geocodeHamlet', null], ['geocodeCommune', true]] as [$method, $arg]) {
            foreach ($method === 'geocodeHamlet' ? $fine : $variants as $v) {
                if ($best = $arg === null ? $this->{$method}($v) : $this->{$method}($v, $arg)) {
                    break 2;
                }
            }
        }

        return $this->placeCache[$key] = $best;
    }

    /**
     * « La Varenne - Chennevières » → [La Varenne - Chennevières, La Varenne, Chennevières] ;
     * « L'embranchement de Auray » → Auray ; « Calais-Ville » → Calais ;
     * « Napoléonville » → Pontivy (noms anciens, cf. OLD_NAMES).
     */
    private function variants(string $name, bool $coarse = true): array
    {
        $n = trim(preg_replace('/\s*\(.*?\)\s*/u', ' ', $name));
        $n = preg_replace('/^(?:l[’\']emplacement (?:de la |de l[’\']|du |des |de |d[’\'])|(?:la )?(?:section|ligne|tronçon) (?:de la |de l[’\']|du |des |de |d[’\']))/iu', '', $n);
        $n = preg_replace('/^(?:l[’\']|la |le )?(?:(?:ancienne?s? |entrée (?:du |de la |des )?)?(?:gare|halte|station|arrêt|bif(?:urcation)?\.?|embranchement|raccordement|poste|PN|tunnel|viaduc|carrières?|mines?|usine|barrage|dépôt|triage|sucrerie|cimenterie)s?(?:\s+internationale)?\s+(?:de la |de l[’\']|du |des |de |d[’\']))/iu', '', $n);
        $n = preg_replace('/\s+(?:Ville|Voyageurs|Marchandises|Centre|Nord|Sud|Est|Ouest|Midi|Gare|Triage|Bif|Port|Maritime|État|Etat)$/u', '', $n) ?: $n;
        $out = [$n];
        foreach (preg_split('/\s+[-–—]\s+|\s*\/\s*/u', $n) as $p) {
            $out[] = trim($p);
        }
        // « Paris-Bastille » → « Paris » ; « Boissy-Saint-Léger » reste entier.
        if ($coarse && preg_match('/^([\p{Lu}][\p{L}\']+)-(\p{Lu}[\p{L}\']+)$/u', $n, $m) && ! preg_match('/^(Saint|Sainte|St|Ste|Mont|Le|La|Les|Bourg|Port|Pont|Villeneuve|Villers|Ville|Fontaine|Château|Neuf|Vieux)$/u', $m[1])) {
            $out[] = $m[1];
        }
        // Suffixes de gare accolés : « Calais-Ville », « Saint-Béat-Carrières », « Grandcamp-les-Bains ».
        foreach ($out as $v) {
            $short = preg_replace('/-(?:Ville|Voyageurs|Marchandises|Centre|Gare|Triage|Port|Maritime|Plage|Carrières|Embranchement|Bif|Bifurcation|Mines|Usine|Halte|État|Etat|les-Bains|Bains)$/u', '', $v);
            if ($short && $short !== $v) {
                $out[] = $short;
            }
        }
        // Noms anciens ou graphies historiques.
        foreach ($out as $v) {
            $k = $this->text->norm($v);
            if (isset(self::OLD_NAMES[$k])) {
                $out[] = self::OLD_NAMES[$k];
            }
        }

        return array_values(array_unique(array_filter($out, fn ($x) => mb_strlen($x) >= 2)));
    }

    private bool $namesReady = false;

    /**
     * Prépare les tables de noms (une fois par session) : à appeler hors
     * transaction quand les résolutions sont faites à blanc (--dry-run).
     */
    public function prepare(): void
    {
        $this->ensureNames();
        $this->ensureCommunes();
    }

    /**
     * Table temporaire des noms de lieux connus, normalisés et indexés en
     * trigrammes, construite une fois par session :
     *   - gares SNCF (et leur commune, pour les gares de la ligne) ;
     *   - gares consolidées (stations) et leurs autres noms selon les sources
     *     (station_sources : OHM = noms historiques, Wikidata, OSM, SNCF
     *     voyageurs). Un nom apparié par simple proximité n'est gardé que s'il
     *     ressemble au nom de la gare. Les positions déduites d'un PK
     *     lignes-oubliées ou d'un schéma de ligne Wikipédia (placées par
     *     placeStations() : circulaires) et les positions signalées douteuses sont écartées, ainsi que
     *     les noms lignes-oubliées rattachés à une autre gare (« Lodève »
     *     apparié à « Lodève-Le Bosc » depuis un point d'ancrage) ;
     *   - line_ids : lignes de la gare (station_lines), pour le bonus « même ligne ».
     */
    private function ensureNames(): void
    {
        if ($this->namesReady) {
            return;
        }
        $n = sprintf($this->norm, 'label');
        $union = ["SELECT libelle AS label, lon, lat, 'gare SNCF' AS kind, code_ligne, NULL::bigint[] AS line_ids FROM sncf_gares WHERE lat IS NOT NULL AND libelle IS NOT NULL",
            "SELECT commune, lon, lat, 'commune (gare SNCF)', code_ligne, NULL FROM sncf_gares WHERE lat IS NOT NULL AND commune IS NOT NULL"];
        if (Schema::hasTable('source_stations')) {
            $union[] = "SELECT name, lon, lat, 'gare '||source, NULL, ARRAY[line_id] FROM source_stations WHERE lat IS NOT NULL AND lon IS NOT NULL";
        }
        $links = Schema::hasTable('station_lines')
            ? 'LEFT JOIN (SELECT station_id, array_agg(line_id) AS ids FROM station_lines GROUP BY station_id) sl ON sl.station_id = s.id'
            : 'LEFT JOIN (SELECT NULL::bigint AS station_id, NULL::bigint[] AS ids) sl ON false';
        $reliable = Schema::hasColumn('stations', 'position_source')
            ? "COALESCE(s.position_source, '') NOT IN ('lignes-oubliees', 'wikipedia_schema') AND NOT COALESCE(s.position_flagged, false)" : 'true';
        $union[] = "SELECT s.name, ST_X(s.geom), ST_Y(s.geom), 'gare', NULL, sl.ids FROM stations s {$links} WHERE {$reliable}";
        if (Schema::hasTable('station_sources')) {
            $ns = sprintf($this->norm, 'ss.name');
            $nst = sprintf($this->norm, 's.name');
            $union[] = "SELECT ss.name, ST_X(s.geom), ST_Y(s.geom), 'gare (nom '||ss.source||')', NULL, sl.ids
                FROM station_sources ss JOIN stations s ON s.id = ss.station_id {$links}
                WHERE ss.name IS NOT NULL AND {$reliable} AND {$ns} <> {$nst} AND ss.source NOT IN ('lignes-oubliees', 'wikipedia_schema')
                  AND (ss.match_method IS DISTINCT FROM 'near' OR similarity({$ns}, {$nst}) >= 0.4)";
        }
        DB::statement('DROP TABLE IF EXISTS sr_names');
        DB::statement("CREATE TEMP TABLE sr_names AS
            SELECT DISTINCT ON (nl, kind, round(lon::numeric, 4), round(lat::numeric, 4)) label, lon, lat, kind, code_ligne, line_ids, nl,
                   ST_SetSRID(ST_MakePoint(lon, lat), 4326) AS geom
            FROM (SELECT u.*, {$n} AS nl FROM (".implode(' UNION ALL ', $union).") u) v
            WHERE nl <> '' AND lon IS NOT NULL AND lat IS NOT NULL");
        DB::statement('CREATE INDEX ON sr_names USING GIN (nl gin_trgm_ops)');
        DB::statement('CREATE INDEX ON sr_names USING GIST (geom)');
        DB::statement('ANALYZE sr_names');
        $this->namesReady = true;
    }

    private function geocodeStations(string $name): ?array
    {
        $q = $this->text->norm($name);
        if ($q === '') {
            return null;
        }
        $this->ensureNames();
        $rows = DB::select(
            'WITH env AS (SELECT ST_Transform(ST_SetSRID(ST_Expand(ST_Extent(geom), 6000)::geometry, 2154), 4326) e FROM sr_parts)
             SELECT n.label, n.lon, n.lat, n.kind, n.nl, similarity(n.nl, ?) AS sim, similarity('.sprintf(self::CORE, 'n.nl').', '.sprintf(self::CORE, '?').") AS core_sim,
                    COALESCE((n.code_ligne IS NOT DISTINCT FROM ? AND n.code_ligne IS NOT NULL) OR ? = ANY(n.line_ids), false) AS same_line
             FROM sr_names n, env
             WHERE n.geom && env.e
               AND (n.nl = ? OR n.nl LIKE ? OR ? LIKE n.nl||' %' OR n.nl % ?)
             ORDER BY (n.nl = ?) DESC, sim DESC
             LIMIT 30",
            [$q, $q, $this->line->code, $this->line->id, $q, $q.' %', $q, $q, $q],
        );
        // Nom approché : il faut aussi que le « cœur » du nom se ressemble
        // (« Aire-sur-la-Lys » n'est pas « Sailly-sur-la-Lys »).
        $rows = array_values(array_filter($rows, fn ($r) => $r->nl === $q || ((float) $r->sim >= 0.45 && (float) $r->core_sim >= 0.5)
            || str_starts_with($r->nl, $q.' ') || str_starts_with($q, $r->nl.' ')));
        // « commune (gare SNCF) » : seulement les gares de CETTE ligne (sinon point arbitraire dans la commune).
        $rows = array_values(array_filter($rows, fn ($r) => ! str_starts_with($r->kind, 'commune') || $r->same_line));
        if (! $rows) {
            return null;
        }
        $pts = [];
        foreach ($rows as $i => $r) {
            $pts["c{$i}"] = [(float) $r->lon, (float) $r->lat];
        }
        $loc = $this->locate($pts);
        $best = null;
        foreach ($rows as $i => $r) {
            $l = $loc["c{$i}"] ?? null;
            if (! $l || $l['d'] > self::STATION_MAX_DIST_M || $this->beyondEnd($l)) {
                continue;
            }
            $exact = $r->nl === $q;
            $consolidated = $exact && $r->same_line && ($r->kind === 'gare' || str_starts_with($r->kind, 'gare (nom '));
            $score = ($exact ? 1.0 : (float) $r->sim) + ($r->same_line ? 0.3 : 0) - $l['d'] / 20000
                - (str_starts_with($r->kind, 'commune') ? 0.15 : 0) + ($consolidated ? self::CONSOLIDATED_STATION_BONUS : 0);
            if (! $best || $score > $best[0]) {
                $best = [$score, ['loc' => $l, 'pt' => [(float) $r->lon, (float) $r->lat], 'name' => $r->label, 'label' => sprintf('%s « %s »%s (%d m du tracé)', $r->kind, $r->label, $r->same_line ? ' de la ligne' : '', $l['d'])]];
            }
        }

        return $best[1] ?? null;
    }

    /**
     * Meilleur candidat (nom exact d'abord, puis le plus proche du tracé) ;
     * null si deux candidats de même rang, tous deux acceptables, tombent à
     * plus de AMBIGUOUS_M l'un de l'autre sur le tracé (homonymes : « le Rival »).
     *
     * @param  list<array{exact: bool, loc: array, pt: array, label: string, rank?: int, name?: ?string, crossed?: bool}>  $cands
     */
    private function pickUnambiguous(array $cands): ?array
    {
        if (! $cands) {
            return null;
        }
        foreach ($cands as &$c) {
            $c['rank'] ??= $c['exact'] ? 2 : 1;
        }
        unset($c);
        usort($cands, fn ($a, $b) => [$b['rank'], $a['loc']['d']] <=> [$a['rank'], $b['loc']['d']]);
        $best = $cands[0];
        foreach (array_slice($cands, 1) as $c) {
            if ($c['rank'] !== $best['rank']) {
                break;
            }
            if ($this->locDistance($best['loc'], $c['loc']) > self::AMBIGUOUS_M) {
                return null;
            }
        }

        return ['loc' => $best['loc'], 'pt' => $best['pt'], 'label' => $best['label'], 'name' => $best['name'] ?? null, 'crossed' => $best['crossed'] ?? null];
    }

    /** Position sur le tracé → [lon, lat]. */
    private function locPoint(array $l): array
    {
        $p = DB::selectOne(
            'SELECT ST_X(pt) lon, ST_Y(pt) lat FROM (SELECT ST_Transform(ST_LineInterpolatePoint(geom, ?), 4326) pt FROM sr_parts WHERE id = ?) q',
            [max(0.0, min(1.0, $l['f'])), $l['part']],
        );

        return [(float) $p->lon, (float) $p->lat];
    }

    /** Distance (m) entre deux positions sur le tracé. */
    private function locDistance(array $a, array $b): float
    {
        return (float) DB::selectOne(
            'SELECT ST_Distance(ST_LineInterpolatePoint(p.geom, ?), ST_LineInterpolatePoint(q.geom, ?)) d FROM sr_parts p, sr_parts q WHERE p.id = ? AND q.id = ?',
            [max(0.0, min(1.0, $a['f'])), max(0.0, min(1.0, $b['f'])), $a['part'], $b['part']],
        )->d;
    }

    /**
     * Point projeté sur une extrémité libre du tracé alors qu'il en est loin :
     * le lieu est AU-DELÀ du tracé connu (portion fermée non tracée…).
     */
    private function beyondEnd(array $l): bool
    {
        if ($l['d'] <= 400) {
            return false;
        }
        $p = $this->parts[$l['part']] ?? null;
        if (! $p) {
            return false;
        }
        $node = $l['f'] < 0.002 ? $p['a'] : ($l['f'] > 0.998 ? $p['b'] : null);

        return $node !== null && count($this->adj[$node] ?? []) === 1;
    }

    /**
     * PK d'un lieu d'après les gares de la ligne (sncf_gares, source_stations) :
     * repli quand le lieu n'est pas géocodable mais que les PK sont calables.
     */
    private function placePk(string $name): ?array
    {
        $q = $this->text->norm($name);
        if ($q === '') {
            return null;
        }
        $n = sprintf($this->norm, 'label');
        $union = ['SELECT libelle AS label, pk_m FROM sncf_gares WHERE code_ligne = ? AND pk_m IS NOT NULL'];
        $bind = [(string) $this->line->code];
        if (Schema::hasTable('source_stations')) {
            $union[] = 'SELECT name, pk_m FROM source_stations WHERE line_id = ? AND pk_m IS NOT NULL';
            $bind[] = $this->line->id;
        }
        if (Schema::hasTable('wikipedia_line_stations')) {
            $union[] = 'SELECT name, pk_m FROM wikipedia_line_stations WHERE line_id = ?';
            $bind[] = $this->line->id;
        }
        $rows = DB::select("SELECT label, pk_m, {$n} AS nl, similarity({$n}, ?) AS sim FROM (".implode(' UNION ALL ', $union).") u
            ORDER BY ({$n} = ?) DESC, similarity({$n}, ?) DESC LIMIT 1", array_merge([$q], $bind, [$q, $q]));
        $r = $rows[0] ?? null;
        if (! $r || ! ($r->nl === $q || (float) $r->sim >= 0.6 || str_starts_with($r->nl, $q.' '))) {
            return null;
        }

        return ['pk' => (int) $r->pk_m, 'label' => $r->label];
    }

    /** Mesure sur l'axe calé → position sur le graphe. */
    private function measureToLoc(float $m): ?array
    {
        $cum = 0.0;
        $m = max(0.0, min($this->calib['length'], $m));
        foreach ($this->calib['pieces'] as $p) {
            $len = $p['gap'] ?? $p['len'];
            if ($m <= $cum + $len && ! isset($p['gap']) && $len > 0) {
                $t = ($m - $cum) / $len;

                return ['part' => $p['part'], 'f' => $p['f1'] + ($p['f2'] - $p['f1']) * $t, 'd' => 0.0];
            }
            $cum += $len;
        }

        return null;
    }

    private ?bool $communesReady = null;

    /** Communes (actuelles, déléguées, associées) normalisées : une fois par session. */
    private function ensureCommunes(): bool
    {
        if ($this->communesReady !== null) {
            return $this->communesReady;
        }
        if (! Schema::hasTable('communes') || ! DB::table('communes')->exists()) {
            return $this->communesReady = false;
        }
        $n = sprintf($this->norm, 'nom');
        DB::statement('DROP TABLE IF EXISTS sr_communes');
        DB::statement("CREATE TEMP TABLE sr_communes AS SELECT nom, type, code_insee, {$n} AS nl, geom FROM communes");
        DB::statement('CREATE INDEX ON sr_communes (nl text_pattern_ops)');
        DB::statement('CREATE INDEX ON sr_communes USING GIN (nl gin_trgm_ops)');
        DB::statement('CREATE INDEX ON sr_communes USING GIST (geom)');
        DB::statement('ANALYZE sr_communes');

        return $this->communesReady = true;
    }

    /**
     * Commune de ce nom (ou dont le nom commence par lui : « Carsac » →
     * « Carsac-Aillac ») près du tracé. Les communes déléguées et associées
     * portent les noms anciens (communes fusionnées).
     *   - le tracé traverse le contour communal (commune actuelle, ± 200 m) :
     *     point du tracé dans la commune le plus proche du centre, à moins de
     *     COMMUNE_MAX_DIST_M du centre ;
     *   - $looseMode : nom approché (faute de frappe à 3 lettres près,
     *     « Clémont-sur-Sauldre » pour « Clémont »), seulement si le tracé
     *     traverse la commune ; essayé en tout dernier recours ;
     *   - sinon centre à moins de COMMUNE_MAX_DIST_M du tracé, pas au-delà
     *     d'une extrémité libre ;
     *   - deux candidats acceptables à des endroits différents : ambigu, rejeté.
     */
    private function geocodeCommune(string $name, bool $looseMode = false): ?array
    {
        if (! $this->ensureCommunes()) {
            return $this->geocodeCommuneApi($name);
        }
        $q = $this->text->norm($name);
        if (mb_strlen($q) < 3) {
            return null;
        }
        $contours = Schema::hasTable('commune_contours');
        $rows = DB::select(
            'WITH env AS (SELECT ST_Transform(ST_SetSRID(ST_Expand(ST_Extent(geom), 15000)::geometry, 2154), 4326) e FROM sr_parts)
             SELECT c.nom, c.type, c.nl, c.nl = ? AS exact, similarity(c.nl, ?) AS sim, ST_X(c.geom) lon, ST_Y(c.geom) lat, x.id AS part, x.f, x.d
             FROM sr_communes c CROSS JOIN env
             LEFT JOIN LATERAL (
                SELECT p.id, ST_LineLocatePoint(p.geom, ST_ClosestPoint(ST_Intersection(p.geom, b.g), b.c)) f,
                       ST_Distance(ST_Intersection(p.geom, b.g), b.c) d
                FROM (SELECT ST_Buffer(ST_Transform(ct.geom, 2154), 200) g, ST_Transform(c.geom, 2154) c
                      FROM commune_contours ct WHERE '.($contours ? "ct.code_insee = c.code_insee AND c.type = 'commune-actuelle'" : 'false').") b
                JOIN sr_parts p ON ST_Intersects(p.geom, b.g)
                ORDER BY d LIMIT 1) x ON true
             WHERE (c.nl = ? OR c.nl LIKE ? OR ? LIKE c.nl||' sur %' OR ? LIKE c.nl||' en %' OR ? LIKE c.nl||' les %' OR c.nl % ?)
               AND c.geom && env.e
             ORDER BY c.nl = ? DESC, similarity(c.nl, ?) DESC LIMIT 20",
            [$q, $q, $q, $q.' %', $q, $q, $q, $q, $q, $q],
        );
        if (! $rows) {
            return null;
        }
        $pts = [];
        foreach ($rows as $i => $r) {
            $pts["c{$i}"] = [(float) $r->lon, (float) $r->lat];
        }
        $near = $this->locate($pts);
        $cands = [];
        foreach ($rows as $i => $r) {
            $l = $near["c{$i}"] ?? null;
            // Nom approché (faute de frappe, « X-sur-Y » pour « X ») : seulement
            // si le tracé traverse la commune.
            $loose = ! $r->exact && ! str_starts_with($r->nl, $q.' ');
            if ($loose !== $looseMode) {
                continue;
            }
            // Faute de frappe (même longueur à 3 lettres près) ou « X-sur-Y » pour « X ».
            $typo = (float) $r->sim >= 0.6 && abs(mb_strlen($r->nl) - mb_strlen($q)) <= 3;
            $reverse = preg_match('/^'.preg_quote($r->nl, '/').' (?:sur|en|les) /', $q);
            if ($loose && (! ($typo || $reverse) || $r->part === null || (float) $r->d > self::COMMUNE_MAX_DIST_M)) {
                continue;
            }
            if ($r->part !== null && (float) $r->d <= self::COMMUNE_MAX_DIST_M) {
                $loc = ['part' => (int) $r->part, 'f' => (float) $r->f, 'd' => (float) $r->d];
                $how = sprintf('tracé dans la commune, à %d m du centre', $r->d);
            } elseif ($l && $l['d'] <= self::COMMUNE_MAX_DIST_M && ! $this->beyondEnd($l)) {
                $loc = $l;
                $how = sprintf('centre à %d m du tracé', $l['d']);
            } else {
                continue;
            }
            $kind = $r->type === 'commune-actuelle' ? 'commune' : str_replace('commune-', 'commune ', $r->type);
            $cands[] = ['exact' => (bool) $r->exact, 'rank' => $r->exact ? 2 : ($loose ? 0 : 1), 'loc' => $loc, 'pt' => [(float) $r->lon, (float) $r->lat],
                'name' => $r->nom, 'label' => sprintf('%s « %s »%s (%s)', $kind, $r->nom, $loose ? ' (nom approché)' : '', $how),
                'crossed' => $r->part !== null && (float) $r->d <= self::COMMUNE_MAX_DIST_M];
        }

        return $this->pickUnambiguous($cands);
    }

    /** @var array<string, ?array> mémo de processus des lieux-dits */
    private static array $poiMemo = [];

    /**
     * Hameau, quartier ou lieu-dit habité (BD TOPO, service de géocodage de la
     * Géoplateforme IGN, index POI ; cache 30 jours) : nom exact, à moins de
     * HAMLET_MAX_DIST_M du tracé, jamais au-delà d'une extrémité, jamais ambigu,
     * jamais un toponyme courant (article + un mot).
     */
    private function geocodeHamlet(string $name): ?array
    {
        $q = $this->text->norm($name);
        // Article + un seul mot (« le Rival », « le Châtelard », « la Barre ») :
        // toponyme trop courant, un homonyme proche du tracé est probable.
        if (mb_strlen($q) < 4 || preg_match('/^(?:le|la|les|l) \S+$/', $q)) {
            return null;
        }
        // Nom d'une commune ou d'une gare existante (ailleurs, puisqu'elle n'a pas
        // été trouvée près du tracé) : un lieu-dit homonyme serait un faux ami.
        $this->ensureNames();
        if (DB::selectOne("SELECT EXISTS (SELECT 1 FROM sr_names WHERE nl = ? AND kind NOT LIKE 'commune%') e", [$q])->e
            || ($this->ensureCommunes() && DB::selectOne('SELECT EXISTS (SELECT 1 FROM sr_communes WHERE nl = ?) e', [$q])->e)) {
            return null;
        }
        $c = DB::selectOne('SELECT ST_X(c) lon, ST_Y(c) lat FROM (SELECT ST_Transform(ST_Centroid(ST_Collect(geom)), 4326) c FROM sr_parts) q');
        if (! $c || $c->lon === null) {
            return null;
        }
        $lon = round((float) $c->lon, 1);
        $lat = round((float) $c->lat, 1);
        $key = 'vff:geo:poi:'.md5("{$q}|{$lon}|{$lat}");
        if (! array_key_exists($key, self::$poiMemo)) {
            $cached = Cache::get($key);
            if (! is_array($cached) || ! array_key_exists('ok', $cached)) {
                try {
                    $res = Http::withUserAgent('VFF-Bot/1.0 (+https://vff-project.org)')->connectTimeout(3)->timeout(8)
                        ->get('https://data.geopf.fr/geocodage/search', ['q' => $name, 'index' => 'poi', 'limit' => 20, 'lat' => $lat, 'lon' => $lon]);
                    $cached = $res->ok()
                        ? ['ok' => true, 'list' => array_map(fn ($f) => [
                            'nom' => $f['properties']['toponym'] ?? ($f['properties']['name'][0] ?? ''),
                            'cat' => (array) ($f['properties']['category'] ?? []),
                            'city' => ((array) ($f['properties']['city'] ?? []))[0] ?? null,
                            'lon' => $f['geometry']['coordinates'][0] ?? null,
                            'lat' => $f['geometry']['coordinates'][1] ?? null,
                        ], $res->json('features') ?? [])]
                        : ['ok' => false, 'list' => null];
                } catch (\Throwable) {
                    $cached = ['ok' => false, 'list' => null];
                }
                Cache::put($key, $cached, $cached['ok'] ? now()->addDays(30) : now()->addHour());
            }
            self::$poiMemo[$key] = $cached['list'];
        }
        $pts = [];
        $keep = [];
        foreach (self::$poiMemo[$key] ?? [] as $i => $f) {
            if ($f['lon'] === null || $this->text->norm((string) $f['nom']) !== $q) {
                continue;
            }
            $cats = implode(' ', $f['cat']);
            if (! preg_match('/habité|habitation|quartier|hameau|gare|halte/u', $cats) || str_contains($cats, 'non habité')) {
                continue;
            }
            $pts["h{$i}"] = [(float) $f['lon'], (float) $f['lat']];
            $keep["h{$i}"] = $f;
        }
        if (! $pts) {
            return null;
        }
        $cands = [];
        foreach ($this->locate($pts) as $k => $l) {
            if ($l['d'] > self::HAMLET_MAX_DIST_M || $this->beyondEnd($l)) {
                continue;
            }
            $f = $keep[$k];
            $cands[] = ['exact' => true, 'loc' => $l, 'pt' => [(float) $f['lon'], (float) $f['lat']], 'name' => $f['nom'],
                'label' => sprintf('lieu-dit « %s »%s (BD TOPO, %d m du tracé)', $f['nom'], $f['city'] ? ' à '.$f['city'] : '', $l['d'])];
        }

        return $this->pickUnambiguous($cands);
    }

    /**
     * Repli si la table communes est vide : API Découpage administratif
     * (geo.api.gouv.fr, données IGN/INSEE, Licence Ouverte), cache 30 jours.
     */
    /** @var array<string, ?array> mémo de processus des communes (évite les allers-retours cache) */
    private static array $communeMemo = [];

    private function geocodeCommuneApi(string $name): ?array
    {
        $key = 'vff:geo:commune:'.md5($this->text->norm($name));
        if (! array_key_exists($key, self::$communeMemo)) {
            $cached = Cache::get($key);
            if (! is_array($cached) || ! array_key_exists('ok', $cached)) {
                try {
                    $res = Http::withUserAgent('VFF-Bot/1.0 (+https://vff-project.org)')->connectTimeout(3)->timeout(6)
                        ->get('https://geo.api.gouv.fr/communes', ['nom' => $name, 'fields' => 'nom,centre', 'boost' => 'population', 'limit' => 8]);
                    $cached = $res->ok()
                        ? ['ok' => true, 'list' => array_map(fn ($c) => ['nom' => $c['nom'], 'lon' => $c['centre']['coordinates'][0] ?? null, 'lat' => $c['centre']['coordinates'][1] ?? null], $res->json() ?? [])]
                        : ['ok' => false, 'list' => null];
                } catch (\Throwable) {
                    $cached = ['ok' => false, 'list' => null];
                }
                // Succès : 30 jours ; échec (lenteur, quota) : 1 h, pour ne pas le repayer à chaque événement.
                Cache::put($key, $cached, $cached['ok'] ? now()->addDays(30) : now()->addHour());
            }
            self::$communeMemo[$key] = $cached['list'];
        }
        $list = self::$communeMemo[$key];
        if (! $list) {
            return null;
        }
        $q = $this->text->norm($name);
        $pts = [];
        foreach ($list as $i => $c) {
            if ($c['lon'] === null) {
                continue;
            }
            $n = $this->text->norm($c['nom']);
            // Nom exact, ou commune dont le nom commence par le lieu (« Chennevières » → « Chennevières-sur-Marne »).
            if ($n === $q || str_starts_with($n, $q.' ')) {
                $pts["k{$i}"] = [$c['lon'], $c['lat']];
            }
        }
        if (! $pts) {
            return null;
        }
        $loc = $this->locate($pts);
        $best = null;
        foreach ($loc as $k => $l) {
            if ($l['d'] > self::COMMUNE_MAX_DIST_M || $this->beyondEnd($l)) {
                continue;
            }
            if (! $best || $l['d'] < $best['loc']['d']) {
                $c = $list[(int) substr($k, 1)];
                $best = ['loc' => $l, 'pt' => [(float) $c['lon'], (float) $c['lat']], 'label' => sprintf('commune « %s » (%d m du tracé)', $c['nom'], $l['d'])];
            }
        }

        return $best;
    }

    // ------------------------------------------------------------------
    // Écriture
    // ------------------------------------------------------------------

    /**
     * SQL des points ordonnés d'une suite de pièces (pour matérialiser l'axe).
     *
     * @return array{0: string, 1: array}|null
     */
    private function piecesSql(array $pieces, bool $oriented): ?array
    {
        $values = [];
        $bind = [];
        $seq = 0;
        foreach ($pieces as $p) {
            if (isset($p['gap'])) {
                continue;
            }
            if (abs($p['f2'] - $p['f1']) < 1e-9) {
                continue;
            }
            $values[] = '(?::int, ?::int, ?::float8, ?::float8)';
            array_push($bind, $seq++, $p['part'], max(0.0, min(1.0, $p['f1'])), max(0.0, min(1.0, $p['f2'])));
        }
        if (! $values) {
            return null;
        }

        return ['SELECT v.seq, (dp).path[1] AS k, (dp).geom AS pt FROM (
                   SELECT v.seq, ST_DumpPoints(CASE WHEN v.f1 <= v.f2 THEN ST_LineSubstring(p.geom, v.f1, v.f2)
                        ELSE ST_Reverse(ST_LineSubstring(p.geom, v.f2, v.f1)) END) AS dp
                   FROM (VALUES '.implode(',', $values).') v(seq, id, f1, f2) JOIN sr_parts p ON p.id = v.id) v', $bind];
    }

    private function store(int $eventId, array $pieces, string $method, string $note): string
    {
        $values = [];
        $bind = [];
        foreach ($pieces as $p) {
            if (abs($p['f2'] - $p['f1']) < 1e-9) {
                continue;
            }
            $f1 = max(0.0, min(1.0, min($p['f1'], $p['f2'])));
            $f2 = max(0.0, min(1.0, max($p['f1'], $p['f2'])));
            if ($f2 - $f1 < 1e-9) {
                continue;
            }
            $values[] = '(?::int, ?::float8, ?::float8)';
            array_push($bind, $p['part'], $f1, $f2);
        }
        if (! $values) {
            return $this->unresolved($eventId, 'portion de longueur nulle');
        }
        $n = DB::update(
            'UPDATE line_events SET section_geom = s.g, section_length_m = s.len, section_resolution = ?, section_resolution_note = ?
             FROM (SELECT ST_Multi(ST_Transform(ST_LineMerge(ST_Collect(ST_LineSubstring(p.geom, v.f1, v.f2))), 4326)) AS g,
                          ROUND(SUM(ST_Length(ST_LineSubstring(p.geom, v.f1, v.f2)))) AS len
                   FROM (VALUES '.implode(',', $values).") v(id, f1, f2) JOIN sr_parts p ON p.id = v.id) s
             WHERE line_events.id = ? AND ST_GeometryType(s.g) = 'ST_MultiLineString'",
            array_merge([$method, mb_substr($note, 0, 500)], $bind, [$eventId]),
        );

        return $n ? $method : $this->unresolved($eventId, 'géométrie résultante invalide');
    }

    private function unresolved(int $eventId, string $why): string
    {
        DB::update(
            "UPDATE line_events SET section_geom = NULL, section_length_m = NULL, section_resolution = 'unresolved', section_resolution_note = ? WHERE id = ?",
            [mb_substr($why, 0, 500), $eventId],
        );

        return 'unresolved';
    }

    // ------------------------------------------------------------------
    // Gares nommées des listes historiques (vff:import-station-sources --source=pk)
    // ------------------------------------------------------------------

    /** Écart toléré entre la position d'une gare connue homonyme et celle de son PK calé (m). */
    public const STATION_PK_AGREE_M = 2000;

    /**
     * Gare placée par sa commune homonyme : le tracé traverse la commune, à au plus cette
     * distance de son centre (m ; au-delà, la gare est plutôt celle d'une autre ligne).
     */
    public const STATION_COMMUNE_MAX_M = 3000;

    /** Gare placée par son PK : au plus à cette distance (m de PK) du premier / dernier point de contrôle. */
    public const PK_EXTRAPOLATE_M = 2000;

    /**
     * Précision annoncée d'une gare placée par son PK (m) : base selon le calage, plus
     * PK_PRECISION_SLOPE × l'écart au point de contrôle le plus proche. Valeurs tirées de la
     * validation croisée (vff:import-station-sources --source=pk --dry-run --validate).
     */
    public const PK_PRECISION_OFFICIAL_M = 100;

    public const PK_PRECISION_STATIONS_M = 150;

    public const PK_PRECISION_COMMUNES_M = 800;

    public const PK_PRECISION_SLOPE = 0.05;

    /** Gare placée par son PK à plus de cette distance (m) de la commune homonyme traversée : écartée. */
    public const PK_COMMUNE_CONFLICT_M = 5000;

    /** Précision minimale annoncée d'une gare placée par sa commune (m). */
    public const COMMUNE_PRECISION_M = 500;

    /**
     * Gares nommées d'une ligne (lignes-oubliées, schémas de ligne Wikipédia)
     * posées sur son tracé, comme les portions des événements.
     *
     * Par groupe (une même origine de PK : une fiche, un schéma) :
     *   1. chaque nom est géocodé sur une gare connue proche du tracé (≤
     *      CP_MAX_DIST_M, geocodeStations : SNCF, gares consolidées et leurs
     *      autres noms), sinon sur une commune que le tracé traverse (geocodeCommune) ;
     *   2. calages des PK du groupe : le calage officiel de la ligne
     *      (calibrate()) si les PK du groupe y concordent (écart médian ≤ 500 m
     *      sur au moins deux gares connues, origine décalée recalée, comme
     *      checkPkOrigin()), puis un calage propre au groupe sur ses gares et
     *      communes géocodées (calibrateWith() : ordre croissant, pentes,
     *      étirement) ; une gare prend le premier calage dont les points de
     *      contrôle encadrent son PK à PK_EXTRAPOLATE_M près ;
     *   3. position : la gare connue du même nom à moins de STATION_PK_AGREE_M
     *      de son PK calé (sa position exacte : la consolidation la reconnaît,
     *      jamais de doublon), sinon le PK calé (sur le tracé connu
     *      seulement), sinon le point du tracé le plus proche du centre de la
     *      commune homonyme, si le tracé la traverse à moins de
     *      STATION_COMMUNE_MAX_M de son centre (gare sans PK ou groupe non calable) ;
     *      dans ces deux cas, une gare connue du même nom exact à moins de
     *      STATION_SAME_NAME_NEAR_M du point trouvé est retenue à sa place (gare
     *      hors du tracé : terminus au-delà d'un tracé incomplet, gare voisine).
     *
     * Précision estimée (precision_m) : gare connue, celle de sa source (null) ;
     * PK calé, PK_PRECISION_OFFICIAL_M (calage officiel) ou
     * PK_PRECISION_STATIONS_M / PK_PRECISION_COMMUNES_M (calage du groupe sur
     * des gares / des communes) + PK_PRECISION_SLOPE × l'écart au point de
     * contrôle le plus proche ; commune, la distance du centre au tracé (au
     * moins COMMUNE_PRECISION_M).
     *
     * @param  list<array{key: string, name: string, pk: ?int, group: string}>  $stations
     * @return array{placed: array<string, array{lon: float, lat: float, method: string, precision_m: ?int, pk_m: ?int, note: string}>, residuals: list<int>, groups: array<string, string>, loo: list<array{0: int, 1: int, 2: int, 3: string}>}
     */
    public function placeStations(int $lineId, array $stations, bool $validate = false): array
    {
        $out = ['placed' => [], 'residuals' => [], 'groups' => [], 'loo' => []];
        $line = DB::table('lines')->where('id', $lineId)->first(['id', 'code', 'name']);
        if (! $line || ! $stations) {
            return $out;
        }
        $this->line = $line;
        $this->calib = null;
        $this->calibError = null;
        $this->placeCache = [];
        $this->loadCarrier();
        if ($this->carrier === null) {
            return $out;
        }
        $this->calibrate();
        $official = $this->calib;

        $groups = [];
        foreach ($stations as $s) {
            $groups[$s['group']][] = $s;
        }
        foreach ($groups as $group => $rows) {
            // 1. Géocodage des noms sur le tracé.
            $geo = [];
            foreach ($rows as $r) {
                $st = $this->geocodeStations($r['name']);
                if ($st && $st['loc']['d'] <= self::CP_MAX_DIST_M) {
                    $sn = $this->text->norm((string) $st['name']);
                    $q = $this->text->norm($r['name']);
                    // same : nom de la gare connue = nom cherché, éventuellement précisé (« Bagnoles-de-l'Orne »
                    // → « Bagnoles-de-l'Orne-Tessé-la-Madeleine ») ; sinon nom seulement voisin
                    // (« Camps-les-Brignoles » → « Brignoles » : un autre lieu).
                    $geo[$r['key']] = ['kind' => 'station', 'exact' => $sn === $q, 'same' => str_starts_with($sn.' ', $q.' ')] + $st;

                    continue;
                }
                if ($c = $this->geocodeCommune($r['name'])) {
                    $geo[$r['key']] = ['kind' => 'commune', 'exact' => true] + $c;
                }
            }

            // 2. Calages des PK du groupe, par ordre de préférence.
            $calibs = $this->groupCalibrations($rows, $geo, $official);
            $out['groups'][$group] = $calibs ? implode(' ; ', array_column($calibs, 'how')) : $this->groupError;
            if ($validate) {
                foreach ($calibs as $c) {
                    if ($c['calib'] !== $official) {
                        array_push($out['loo'], ...$this->leaveOneOut($rows, $geo, $c['base']));
                    }
                }
            }

            // 3. Positions.
            foreach ($rows as $r) {
                $g = $geo[$r['key']] ?? null;
                $pkPt = null;
                $used = null;
                foreach ($r['pk'] === null ? [] : $calibs as $c) {
                    $pk = $r['pk'] - $c['shift'];
                    $pks = array_column($c['calib']['points'], 1);
                    // Jamais plus de PK_EXTRAPOLATE_M au-delà du premier / dernier point de contrôle.
                    if ($pk < min($pks) - self::PK_EXTRAPOLATE_M || $pk > max($pks) + self::PK_EXTRAPOLATE_M
                        || array_filter($c['bad'] ?? [], fn ($b) => $pk > $b[0] && $pk < $b[1])) {
                        continue;
                    }
                    $this->calib = $c['calib'];
                    $m = $this->pkToMeasure($pk);
                    if ($m >= -200 && $m <= $c['calib']['length'] + 200 && ($loc = $this->measureToLoc($m))) {
                        $pkPt = $this->locPoint($loc);
                        $used = $c + ['gap' => min(array_map(fn ($p) => abs($p - $pk), $pks))];
                        break;
                    }
                }
                $this->calib = $official;
                $pkOfficial = $used && $used['calib'] === $official ? $r['pk'] - $used['shift'] : null;
                if ($g && $g['kind'] === 'station' && $pkPt) {
                    $out['residuals'][] = (int) round($this->haversine($g['pt'], $pkPt));
                }
                // Gare connue : même nom exact (toujours, sinon doublon de la gare à quelques km), ou
                // nom approché dont la position concorde avec le PK calé (sans PK, un nom approché
                // n'est pas une preuve : « Rouen-Orléans » n'est pas la gare d'Orléans).
                if ($g && $g['kind'] === 'station' && ($g['exact'] || ($pkPt && $this->haversine($g['pt'], $pkPt) <= self::STATION_PK_AGREE_M))) {
                    $out['placed'][$r['key']] = ['lon' => $g['pt'][0], 'lat' => $g['pt'][1], 'method' => 'station', 'precision_m' => null,
                        'pk_m' => $pkOfficial, 'note' => $g['label']];
                } elseif ($g && $g['kind'] === 'station' && ($g['same'] || ! $pkPt)) {
                    // Gare connue du même nom (précisé), loin du PK calé : PK d'un autre repère, rien.
                    continue;
                } elseif ($pkPt && $g && $g['kind'] === 'commune' && $g['crossed']
                    && $this->haversine($this->locPoint($g['loc']), $pkPt) > self::PK_COMMUNE_CONFLICT_M) {
                    // PK calé loin de la commune homonyme que le tracé traverse : repères mélangés, rien.
                    continue;
                } elseif (($at = $pkPt ?? ($g && $g['kind'] === 'commune' && $g['crossed'] && $g['loc']['d'] <= self::STATION_COMMUNE_MAX_M ? $this->locPoint($g['loc']) : null))
                    && ($k = $this->knownStationNear($r['name'], $at))) {
                    // Gare connue du même nom près du point calé mais pas sur le tracé (tracé qui s'arrête avant le
                    // terminus, gare d'une ligne voisine, calage décalé) : c'est elle, jamais un doublon à quelques km.
                    $out['placed'][$r['key']] = ['lon' => (float) $k->lon, 'lat' => (float) $k->lat, 'method' => 'station', 'precision_m' => null,
                        'pk_m' => $pkOfficial, 'note' => sprintf('%s « %s » à %d m du point calé', $k->kind, $k->label, $k->d)];
                } elseif ($pkPt) {
                    $out['placed'][$r['key']] = ['lon' => $pkPt[0], 'lat' => $pkPt[1], 'method' => 'pk',
                        'precision_m' => (int) round($used['base'] + self::PK_PRECISION_SLOPE * $used['gap']), 'pk_m' => $pkOfficial,
                        'note' => sprintf('PK %s calé (%s), %.1f km du point de contrôle le plus proche', $this->km($r['pk']), $used['how'], $used['gap'] / 1000)];
                } elseif ($g && $g['kind'] === 'commune' && $g['crossed'] && $g['loc']['d'] <= self::STATION_COMMUNE_MAX_M) {
                    $pt = $this->locPoint($g['loc']);
                    $out['placed'][$r['key']] = ['lon' => $pt[0], 'lat' => $pt[1], 'method' => 'commune',
                        'precision_m' => max(self::COMMUNE_PRECISION_M, (int) round($g['loc']['d'])), 'pk_m' => null, 'note' => $g['label']];
                }
            }
        }
        $this->calib = $official;

        return $out;
    }

    /** Raison de l'absence de calage du dernier groupe (groupCalibrations). */
    private string $groupError = '';

    /** Gare connue du même nom (exact) à moins de cette distance (m) du point calé d'une gare nommée : c'est elle. */
    public const STATION_SAME_NAME_NEAR_M = 8000;

    /**
     * Gare connue (SNCF, gares consolidées et leurs autres noms ; pas les positions déduites des listes)
     * de ce nom exact la plus proche d'un point, à moins de STATION_SAME_NAME_NEAR_M.
     *
     * @param  array{0: float, 1: float}  $pt
     */
    private function knownStationNear(string $name, array $pt): ?object
    {
        $q = $this->text->norm($name);
        if ($q === '') {
            return null;
        }
        $r = DB::selectOne(
            "SELECT label, kind, lon, lat, round(ST_Distance(geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography))::int AS d
             FROM sr_names WHERE nl = ? AND kind NOT LIKE 'commune%' AND geom && ST_Expand(ST_SetSRID(ST_MakePoint(?, ?), 4326), 0.15)
             ORDER BY d LIMIT 1",
            [$pt[0], $pt[1], $q, $pt[0], $pt[1]],
        );

        return $r && $r->d <= self::STATION_SAME_NAME_NEAR_M ? $r : null;
    }

    /**
     * Calages des PK d'un groupe de gares nommées, par ordre de préférence :
     * le calage officiel de la ligne si les PK du groupe y concordent (avec le
     * décalage d'origine), puis un calage propre au groupe sur ses gares et
     * communes géocodées (sert au-delà des points de contrôle officiels :
     * section fermée que la SNCF ne décrit plus).
     *
     * @param  list<array{key: string, name: string, pk: ?int, group: string}>  $rows
     * @param  array<string, array<string, mixed>>  $geo
     * @return list<array{calib: array, shift: int, base: int, how: string, bad?: list<array{0: int, 1: int}>}>
     */
    private function groupCalibrations(array $rows, array $geo, ?array $official): array
    {
        $this->groupError = '';
        if (! array_filter($rows, fn ($r) => $r['pk'] !== null)) {
            $this->groupError = 'sans PK';

            return [];
        }
        $anchors = $this->groupAnchors($rows, $geo);
        $out = [];

        // Concordance avec le calage officiel : gares connues d'abord (≤ 500 m), sinon communes (≤ 1 500 m).
        if ($official) {
            $this->axisTable($official['pieces']);
            foreach ([['station', 2, 500], [null, 3, 1500]] as [$kind, $min, $maxMad]) {
                $set = $kind ? array_filter($anchors, fn ($a) => $a['geo'] === $kind) : $anchors;
                if (count($set) < $min) {
                    continue;
                }
                $diffs = [];
                $lo = $official['points'][0][0];
                $hi = end($official['points'])[0];
                foreach ($this->measureOnAxis($official, $set, 1000) as $k => $m) {
                    if ($m >= $lo - 500 && $m <= $hi + 500) {
                        $diffs[] = $set[$k]['pk'] - $this->measureToPkWith($official, $m);
                    }
                }
                if (count($diffs) < $min) {
                    continue;
                }
                sort($diffs);
                $med = $diffs[intdiv(count($diffs), 2)];
                $dev = array_map(fn ($d) => abs($d - $med), $diffs);
                sort($dev);
                if ($dev[intdiv(count($dev), 2)] <= $maxMad) {
                    $shift = abs($med) > 600 ? (int) round($med) : 0;
                    $out[] = ['calib' => $official, 'shift' => $shift, 'base' => self::PK_PRECISION_OFFICIAL_M,
                        'how' => 'calage officiel de la ligne'.($shift ? sprintf(', origine décalée de %s km', $this->km($shift)) : '')];
                    break;
                }
            }
        }

        // Calage propre au groupe.
        if (count($anchors) < 2) {
            $this->groupError = 'moins de deux gares ou communes à PK connu';

            return $out;
        }
        $savedError = $this->calibError;
        $calib = $this->calibrateWith($anchors);
        $this->calibError = $savedError;
        if (! $calib) {
            $this->groupError = 'PK incohérents avec le tracé';

            return $out;
        }
        $stations = count(array_filter($anchors, fn ($a) => $a['geo'] === 'station'));
        $out[] = ['calib' => $calib, 'shift' => 0, 'base' => $stations >= 2 ? self::PK_PRECISION_STATIONS_M : self::PK_PRECISION_COMMUNES_M,
            'how' => sprintf('calage du schéma sur %d gares et %d communes', $stations, count($anchors) - $stations),
            'bad' => self::distortedPkRanges($calib['points'])];

        return $out;
    }

    /** Rapport longueur sur le tracé / écart de PK toléré entre deux points de contrôle d'un schéma. */
    public const PK_SLOPE_MAX = 1.5;

    /**
     * Intervalles de PK où un calage de schéma est déformé : entre deux points de contrôle
     * consécutifs, le tracé est plus de PK_SLOPE_MAX fois plus long (ou plus court) que l'écart
     * de PK (repères mélangés dans un même schéma : « 13,724 » sur une ligne, « 15,7 » sur
     * l'autre). Aucune gare n'y est placée par son PK.
     *
     * @param  list<array{0: float, 1: int}>  $points  [mesure sur le tracé (m), PK (m)], par mesure croissante
     * @return list<array{0: int, 1: int}>
     */
    public static function distortedPkRanges(array $points): array
    {
        $bad = [];
        for ($i = 1; $i < count($points); $i++) {
            $dm = $points[$i][0] - $points[$i - 1][0];
            $dp = $points[$i][1] - $points[$i - 1][1];
            if ($dp > 0 && ($dm > self::PK_SLOPE_MAX * $dp || $dm * self::PK_SLOPE_MAX < $dp) && abs($dm - $dp) > 300) {
                $bad[] = [(int) $points[$i - 1][1], (int) $points[$i][1]];
            }
        }

        return $bad;
    }

    /**
     * Points d'appui d'un groupe : gares connues (nom exact) et communes traversées, à PK connu.
     *
     * @param  list<array{key: string, name: string, pk: ?int, group: string}>  $rows
     * @param  array<string, array<string, mixed>>  $geo
     * @return array<string, array{lon: float, lat: float, pk: int, kind: string, geo: string}>
     */
    private function groupAnchors(array $rows, array $geo): array
    {
        $anchors = [];
        foreach ($rows as $r) {
            $g = $geo[$r['key']] ?? null;
            if ($r['pk'] === null || ! $g || ! $g['exact']
                || ($g['kind'] === 'commune' && ! ($g['crossed'] && $g['loc']['d'] <= self::STATION_COMMUNE_MAX_M))) {
                continue;
            }
            $pt = $g['kind'] === 'station' ? $g['pt'] : $this->locPoint($g['loc']);
            $anchors[$r['key']] = ['lon' => $pt[0], 'lat' => $pt[1], 'pk' => (int) $r['pk'], 'kind' => 'gare source', 'geo' => $g['kind']];
        }

        return $anchors;
    }

    /**
     * Validation croisée d'un calage propre au groupe : chaque gare connue du groupe (nom exact, à
     * PK) est replacée par un calage SANS elle ; écart (m) à sa position réelle, avec la
     * précision de base et la précision annoncée ([écart, base, précision, nom]).
     *
     * @param  list<array{key: string, name: string, pk: ?int, group: string}>  $rows
     * @param  array<string, array<string, mixed>>  $geo
     * @return list<array{0: int, 1: int, 2: int, 3: string}>
     */
    private function leaveOneOut(array $rows, array $geo, int $base): array
    {
        $out = [];
        $savedCalib = $this->calib;
        $savedError = $this->calibError;
        foreach ($rows as $r) {
            $g = $geo[$r['key']] ?? null;
            if ($r['pk'] === null || ! $g || $g['kind'] !== 'station' || ! $g['exact']) {
                continue;
            }
            $c = $this->groupCalibrations(array_values(array_filter($rows, fn ($x) => $x['key'] !== $r['key'])), $geo, null)[0] ?? null;
            $calib = $c['calib'] ?? null;
            $pks = $calib ? array_column($calib['points'], 1) : [];
            if (! $calib || $r['pk'] < min($pks) - self::PK_EXTRAPOLATE_M || $r['pk'] > max($pks) + self::PK_EXTRAPOLATE_M
                || array_filter($c['bad'] ?? [], fn ($b) => $r['pk'] > $b[0] && $r['pk'] < $b[1])) {
                continue;
            }
            $this->calib = $calib;
            $m = $this->pkToMeasure($r['pk']);
            if ($m >= 0 && $m <= $calib['length'] && ($loc = $this->measureToLoc($m))) {
                $gap = min(array_map(fn ($p) => abs($p - $r['pk']), $pks));
                $out[] = [(int) round($this->haversine($g['pt'], $this->locPoint($loc))), $base, (int) round($base + self::PK_PRECISION_SLOPE * $gap), $r['name']];
            }
        }
        $this->calib = $savedCalib;
        $this->calibError = $savedError;

        return $out;
    }

    /** Recrée sr_axis (axe d'un calage) pour measureOnAxis(). */
    private function axisTable(array $pieces): void
    {
        DB::statement('DROP TABLE IF EXISTS sr_axis');
        $sql = $this->piecesSql($pieces, true);
        if ($sql) {
            DB::statement("CREATE TEMP TABLE sr_axis AS SELECT ST_MakeLine(pt ORDER BY seq, k) AS geom FROM ({$sql[0]}) s", $sql[1]);
        }
    }
}
