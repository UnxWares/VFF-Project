<?php

namespace App\Services\OpenData;

use App\Enums\AccidentKind;
use App\Enums\SignallingFamily;
use App\Services\Lines\LineSlugRedirects;
use App\Services\Lines\NeutralLineSlugs;
use App\Services\Materials\PreservedRollingStock;
use App\Services\Signalling\LineSignallingHistory;
use App\Services\Signalling\SignallingHistoryState;
use Illuminate\Support\Facades\DB;

/**
 * Jeux de données ouverts « de la deuxième vague » (vff:export-open-data) : historique de la signalisation par
 * époque, matériel préservé, projets de lignes et étapes, infrastructure SNCF (voies, radio, quais, cantonnement),
 * sources des accidents relus, redirections d'adresses, profils en long. Lecture seule de la base et des fichiers
 * versionnés de database/data.
 *
 * Même règle que OpenDataExporter : chaque donnée garde la clé de son fournisseur (colonne `source`, ou `*_source`),
 * seules les sources autorisées par OpenDataRights entrent, le reste est retiré et compté dans le OpenDataLedger sans
 * être nommé. Aucun texte rédigé n'est repris (ni extrait, ni citation, ni résumé, ni note) et aucune donnée
 * personnelle (auteur d'un article, titre d'une référence de presse, personne citée).
 *
 * Données d'un fichier versionné (matériel préservé avec son complément, projets, déclivités, redirections de lignes) : injectables
 * par le constructeur, pour les tests.
 */
final class OpenDataSuiteExporter
{
    public const SIGNALLING_EVENT_FIELDS = ['event_id', 'line_id', 'line_slug', 'line_code', 'system', 'family', 'role', 'event_date',
        'date_precision', 'scope', 'section_from', 'section_to', 'section_pk_start_m', 'section_pk_end_m', 'section_location',
        'confidence', 'origin', 'verification', 'source', 'licence', 'source_title', 'source_revision', 'source_url'];

    public const SIGNALLING_ERA_FIELDS = ['era', 'in_force_on', 'line_slug', 'line_code', 'event_id', 'system', 'family', 'role',
        'event_date', 'scope', 'section_from', 'section_to', 'source', 'licence'];

    public const PRESERVED_UNIT_FIELDS = ['material_slug', 'number', 'designation', 'venue_slug', 'place_said', 'state', 'protection_kind',
        'protection_date', 'protection_year', 'protection_ref', 'sources', 'source_urls'];

    public const PRESERVED_VENUE_FIELDS = ['venue_slug', 'name', 'type', 'commune', 'tourist_railway', 'pieces', 'sources'];

    public const PROJECT_FIELDS = ['project_id', 'name', 'kind', 'regions', 'line_slugs', 'suspended', 'checked_on', 'source', 'licence'];

    public const PROJECT_STAGE_FIELDS = ['project_id', 'stage', 'stage_date', 'date_precision', 'done', 'source', 'licence', 'source_url',
        'source_published', 'link_role'];

    /**
     * Fournisseurs du registre dont un lien d'étape de projet n'est qu'une citation (presse, ministères, collectivités
     * cités ponctuellement) ; les domaines absents du registre sont traités de même. Décision de Baptiste du 23/11/2026.
     */
    public const PROJECT_CITED_PROVIDERS = ['tiers-cites'];

    public const TRACK_FIELDS = ['line_slug', 'line_code', 'section_rank', 'pk_start_m', 'pk_end_m', 'regime', 'tracks', 'source', 'licence',
        'dataset_modified_on'];

    public const RADIO_FIELDS = ['line_slug', 'line_code', 'section_rank', 'pk_start_m', 'pk_end_m', 'system', 'label', 'source', 'licence',
        'dataset_modified_on'];

    public const PLATFORM_FIELDS = ['station_slug', 'sncf_station_name', 'line_code', 'track_code', 'section_rank', 'label', 'length_m',
        'pk_start_m', 'pk_end_m', 'lat', 'lon', 'match_method', 'source', 'licence', 'dataset_modified_on'];

    public const BLOCK_FIELDS = ['line_slug', 'line_code', 'pk_start_m', 'pk_end_m', 'signalling', 'signalling_systems', 'train_protection',
        'kvb_equipped', 'block_label', 'source', 'licence'];

    public const ACCIDENT_SOURCE_FIELDS = ['accident_id', 'accident_slug', 'context_checked_on', 'position', 'provider_family', 'kind',
        'reliability', 'access', 'published', 'source', 'licence', 'source_url'];

    public const STATION_REDIRECT_FIELDS = ['old_slug', 'station_slug', 'source', 'licence'];

    public const LINE_REDIRECT_FIELDS = ['old_slug', 'line_slug', 'source', 'licence'];

    public const PROFILE_FIELDS = ['line_slug', 'line_code', 'status', 'step_m', 'length_m', 'trace_length_m', 'covered_m', 'off_path_m',
        'min_altitude_m', 'max_altitude_m', 'ascent_m', 'descent_m', 'max_grade_permil', 'max_grade_from_m', 'max_grade_to_m',
        'max_grade_direction', 'grade_status', 'source', 'licence'];

    public const PROFILE_POINT_FIELDS = ['line_slug', 'seq', 'distance_m', 'pk_m', 'altitude_m', 'altitude_source', 'trace_source',
        'interpolated', 'structure', 'branch'];

    public const GRADE_FIELDS = ['line_slug', 'max_grade_permil', 'covered_share', 'source', 'licence', 'source_title', 'source_url'];

    /** Rubriques de confiance lues dans l'historique de la signalisation (LineSignallingHistory::SHOWN). */
    private const SHOWN = LineSignallingHistory::SHOWN;

    /** @var array<string, mixed>|null */
    private ?array $preserved;

    /** @var list<array<string, mixed>>|null */
    private ?array $projects;

    /** @var array<string, string>|null */
    private ?array $lineRedirects;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $wikipediaGrades;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $sncfGrades;

    /**
     * @param  string  $today  jour de l'export : fin de l'ère courante dans l'historique de la signalisation
     * @param  array<string, mixed>|null  $preserved
     * @param  list<array<string, mixed>>|null  $projects
     * @param  array<string, string>|null  $lineRedirects
     * @param  array<string, array<string, mixed>>|null  $wikipediaGrades
     * @param  array<string, array<string, mixed>>|null  $sncfGrades
     */
    public function __construct(private readonly OpenDataRights $rights, private readonly string $today, ?array $preserved = null,
        ?array $projects = null, ?array $lineRedirects = null, ?array $wikipediaGrades = null, ?array $sncfGrades = null)
    {
        $this->preserved = $preserved;
        $this->projects = $projects;
        $this->lineRedirects = $lineRedirects;
        $this->wikipediaGrades = $wikipediaGrades;
        $this->sncfGrades = $sncfGrades;
    }

    // ------------------------------------------------------------------------------------------------------------
    // Signalisation : historique daté et état en vigueur à chaque époque
    // ------------------------------------------------------------------------------------------------------------

    /**
     * Mises en service de signalisation (sûres : confiance haute ou moyenne), sans extrait ni document ni correction,
     * puis l'état en vigueur à la fin de chaque époque (SignallingHistoryState), calculé sur les seuls faits libres.
     *
     * @param  list<int>|null  $lineIds
     */
    public function signalling(string $dir, OpenDataLedger $ledger, ?array $lineIds = null): void
    {
        $events = OpenDataFiles::csv("{$dir}/signalisation-evenements.csv", self::SIGNALLING_EVENT_FIELDS);
        $query = DB::table('line_signalling_events as e')
            ->join('lines as l', 'l.id', '=', 'e.line_id')
            ->whereIn('e.confidence', self::SHOWN)
            ->when($lineIds !== null, fn ($q) => $q->whereIn('e.line_id', $lineIds ?? []))
            ->orderBy('e.line_id')->orderByRaw('e.event_date NULLS LAST')->orderBy('e.id')
            ->select(['e.id', 'e.line_id', 'l.slug', 'l.code', 'e.system', 'e.role', 'e.event_date', 'e.date_precision', 'e.scope',
                'e.section_from', 'e.section_to', 'e.located_pk_start_m', 'e.located_pk_end_m', 'e.pk_start_m', 'e.pk_end_m',
                'e.section_location', 'e.confidence', 'e.origin', 'e.verification', 'e.source', 'e.source_title', 'e.source_revision',
                'e.source_url']);

        /** @var array<int, list<array<string, mixed>>> $factsByLine */
        $factsByLine = [];
        $lines = [];
        foreach ($query->cursor() as $e) {
            $provider = $this->rights->provider('line_signalling_events', $e->source, $e->source_url);
            if (($refusal = $this->rights->refusal($provider)) !== null) {
                $ledger->withhold('signalling.events', $refusal);

                continue;
            }
            $ledger->cite($provider);
            $known = SignallingFamily::fromCode((string) $e->system);
            $family = $known instanceof SignallingFamily ? $known->value : (string) $e->role;
            $pkStart = $this->int($e->located_pk_start_m ?? $e->pk_start_m);
            $pkEnd = $this->int($e->located_pk_end_m ?? $e->pk_end_m);
            // Le titre d'un article de Wikipédia est un nom ; celui d'un document relevé à la main n'est jamais repris.
            $title = $e->source === 'wikipedia' ? $e->source_title : null;
            $events->row([(int) $e->id, (int) $e->line_id, $e->slug, $e->code, $e->system, $family, $e->role, $e->event_date,
                $e->date_precision, $e->scope, $e->section_from, $e->section_to, $pkStart, $pkEnd, $e->section_location,
                $e->confidence, $e->origin, $e->verification, $provider, $this->licence($provider), $title,
                $title !== null ? $this->int($e->source_revision) : null, $this->safeUrl($e->source_url, $provider)]);
            if ($e->event_date !== null) {
                $factsByLine[(int) $e->line_id][] = ['id' => (int) $e->id, 'system' => (string) $e->system, 'role' => (string) $e->role,
                    'date' => (string) $e->event_date, 'scope' => (string) $e->scope, 'family' => $family,
                    'section' => $e->scope === 'section' ? ['from' => $e->section_from, 'to' => $e->section_to, 'pk_start_m' => $pkStart, 'pk_end_m' => $pkEnd] : null,
                    'section_from' => $e->section_from, 'section_to' => $e->section_to, 'source' => ['provider' => (string) $e->source],
                    'provider' => $provider];
                $lines[(int) $e->line_id] = [(string) $e->slug, $e->code];
            }
        }
        $ledger->count('signalisation-evenements.csv', $events->close());

        $eras = DB::table('eras')->orderBy('year')->get(['year', 'is_current']);
        $states = OpenDataFiles::csv("{$dir}/signalisation-par-epoque.csv", self::SIGNALLING_ERA_FIELDS);
        foreach ($factsByLine as $lineId => $facts) {
            $facts = SignallingHistoryState::withoutRestatements($facts);
            [$slug, $code] = $lines[$lineId];
            foreach ($eras as $era) {
                $until = SignallingHistoryState::until((int) $era->year, (bool) $era->is_current, new \DateTimeImmutable($this->today));
                foreach (SignallingHistoryState::inForce($facts, $until) as $f) {
                    $states->row([(int) $era->year, $until, $slug, $code, $f['id'], $f['system'], $f['family'], $f['role'], $f['date'],
                        $f['scope'], $f['section_from'], $f['section_to'], $f['provider'], $this->licence($f['provider'])]);
                }
            }
        }
        $ledger->count('signalisation-par-epoque.csv', $states->close());
    }

    // ------------------------------------------------------------------------------------------------------------
    // Matériel préservé
    // ------------------------------------------------------------------------------------------------------------

    /**
     * Exemplaires préservés (numéro, lieu, état, protection au titre des monuments historiques) et leurs lieux de
     * conservation, seulement avec les sources autorisées de chaque fait (Wikipédia, Palissy, Wikidata).
     *
     * @param  list<int>|null  $lineIds
     */
    public function preserved(string $dir, OpenDataLedger $ledger, ?array $lineIds = null): void
    {
        // Mêmes exemplaires que le site : le fichier de base et son complément, fusionnés par PreservedRollingStock::merge.
        $data = $this->preserved ??= PreservedRollingStock::merge(
            $this->shaped($this->load('preserved_rolling_stock.php')),
            $this->shaped($this->load('preserved_rolling_stock_complement.php')),
        );
        $venues = is_array($data['venues'] ?? null) ? $data['venues'] : [];
        $only = $lineIds !== null
            ? array_flip(DB::table('materials as m')->whereIn('m.id', DB::table('line_materials')->whereIn('line_id', $lineIds)->select('material_id'))->pluck('slug')->all())
            : null;

        $csv = OpenDataFiles::csv("{$dir}/materiel-preserve-exemplaires.csv", self::PRESERVED_UNIT_FIELDS);
        /** @var array<string, array{pieces: int, sources: array<string, true>}> $used */
        $used = [];
        $write = function (?string $material, array $unit) use ($csv, $ledger, &$used): void {
            $kept = [];
            $urls = [];
            $reason = null;
            foreach (is_array($unit['src'] ?? null) ? $unit['src'] : [] as $src) {
                $provider = (string) ($src[0] ?? '');
                if (($refusal = $this->rights->refusal($provider)) !== null) {
                    $reason = $refusal;

                    continue;
                }
                $kept[$provider] = true;
                if (($url = $this->preservedUrl($provider, $src)) !== null) {
                    $urls[$url] = true;
                }
            }
            if ($kept === []) {
                $ledger->withhold('preserved.units', $reason ?? OpenDataRights::UNKNOWN_SOURCE);

                return;
            }
            foreach (array_keys($kept) as $provider) {
                $ledger->cite($provider);
            }
            $mh = isset($kept['palissy']) && is_array($unit['mh'] ?? null) ? $unit['mh'] : null;
            $providers = $this->sorted(array_keys($kept));
            $csv->row([$material, $unit['number'] ?? null, $unit['title'] ?? null, $unit['venue'] ?? null, $unit['where'] ?? null,
                $unit['state'] ?? null, $mh['kind'] ?? null, $mh['date'] ?? null, $mh['year'] ?? null, $mh['ref'] ?? null,
                implode('|', $providers), implode(' ', array_keys($urls))]);
            if (is_string($unit['venue'] ?? null)) {
                $used[$unit['venue']]['pieces'] = ($used[$unit['venue']]['pieces'] ?? 0) + 1;
                foreach ($providers as $p) {
                    $used[$unit['venue']]['sources'][$p] = true;
                }
            }
        };
        foreach (is_array($data['units'] ?? null) ? $data['units'] : [] as $slug => $units) {
            if ($only !== null && ! isset($only[(string) $slug])) {
                continue;
            }
            foreach ($units as $unit) {
                $write((string) $slug, $unit);
            }
        }
        if ($only === null) {
            foreach (is_array($data['others'] ?? null) ? $data['others'] : [] as $unit) {
                $write(null, $unit);
            }
        }
        $ledger->count('materiel-preserve-exemplaires.csv', $csv->close());

        $places = OpenDataFiles::csv("{$dir}/materiel-preserve-lieux.csv", self::PRESERVED_VENUE_FIELDS);
        foreach ($venues as $slug => $venue) {
            if (! isset($used[(string) $slug])) {
                continue;
            }
            $places->row([$slug, $venue['name'] ?? null, $venue['type'] ?? null, $venue['commune'] ?? null, $venue['tourist_railway'] ?? null,
                $used[(string) $slug]['pieces'], implode('|', $this->sorted(array_keys($used[(string) $slug]['sources'])))]);
        }
        $ledger->count('materiel-preserve-lieux.csv', $places->close());
    }

    /** @param  array<int, mixed>  $src  [source, référence, version] */
    private function preservedUrl(string $provider, array $src): ?string
    {
        $ref = (string) ($src[1] ?? '');
        if ($ref === '') {
            return null;
        }

        return match ($provider) {
            'wikipedia' => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $ref))
                .(isset($src[2]) ? '&oldid='.(int) $src[2] : ''),
            'wikidata' => 'https://www.wikidata.org/wiki/'.rawurlencode($ref),
            'palissy' => 'https://www.pop.culture.gouv.fr/notice/palissy/'.rawurlencode($ref),
            default => null,
        };
    }

    // ------------------------------------------------------------------------------------------------------------
    // Projets de lignes
    // ------------------------------------------------------------------------------------------------------------

    /**
     * Projets ferroviaires et leurs étapes datées. Un projet (nom, type, régions) est un relevé de VFF. Une étape
     * (nature, date, franchie ou prévue) est rédigée par VFF : si le domaine de son lien est un fournisseur libre du
     * registre, elle lui est attribuée (`link_role` = source) ; si le lien vient de la presse, d'un ministère ou d'une
     * collectivité (domaine absent du registre, ou fournisseur de PROJECT_CITED_PROVIDERS), elle sort comme relevé de
     * VFF et le lien n'est qu'une citation (`link_role` = citation). Tout autre fournisseur (non commercial, accord,
     * réutilisation interdite) retire l'étape. Ni résumé, ni note, ni libellé de la source, ni porteur de projet.
     *
     * @param  list<int>|null  $lineIds
     */
    public function projects(string $dir, OpenDataLedger $ledger, ?array $lineIds = null): void
    {
        $catalogue = $this->projects ??= $this->load('projets_ferroviaires.php');
        $slugs = $lineIds !== null ? array_flip(DB::table('lines')->whereIn('id', $lineIds)->pluck('slug')->all()) : null;

        $projects = OpenDataFiles::csv("{$dir}/projets.csv", self::PROJECT_FIELDS);
        $stages = OpenDataFiles::csv("{$dir}/projets-etapes.csv", self::PROJECT_STAGE_FIELDS);
        foreach ($catalogue as $project) {
            $lineSlugs = array_values(array_filter((array) ($project['line_slugs'] ?? []), 'is_string'));
            if ($slugs !== null && array_filter($lineSlugs, fn (string $s): bool => isset($slugs[$s])) === []) {
                continue;
            }
            $ledger->cite('vff');
            $projects->row([$project['id'] ?? null, $project['name'] ?? null, $project['kind'] ?? null,
                implode('|', (array) ($project['regions'] ?? [])), implode('|', $lineSlugs), ! empty($project['suspended']) ? 'true' : 'false',
                $project['checked'] ?? null, 'vff', $this->licence('vff')]);
            foreach (is_array($project['stages'] ?? null) ? $project['stages'] : [] as $stage) {
                $url = is_array($stage['source'] ?? null) ? ($stage['source']['url'] ?? null) : null;
                $provider = is_string($url) ? $this->rights->registry()->providerForUrl($url) : null;
                $role = 'source';
                if (($refusal = $this->rights->refusal($provider)) !== null) {
                    if (! is_string($url) || ($provider !== null && ! in_array($provider, self::PROJECT_CITED_PROVIDERS, true))) {
                        $ledger->withhold('projects.stages', $refusal);

                        continue;
                    }
                    [$provider, $role] = ['vff', 'citation'];
                }
                $ledger->cite($provider);
                $date = (string) ($stage['date'] ?? '');
                $stages->row([$project['id'] ?? null, $stage['kind'] ?? null, $date, strlen($date) === 4 ? 'year' : (strlen($date) === 7 ? 'month' : 'day'),
                    ! empty($stage['done']) ? 'true' : 'false', $provider, $this->licence($provider), $url,
                    is_array($stage['source'] ?? null) ? ($stage['source']['published'] ?? null) : null, $role]);
            }
        }
        $ledger->count('projets.csv', $projects->close());
        $ledger->count('projets-etapes.csv', $stages->close());
    }

    // ------------------------------------------------------------------------------------------------------------
    // Infrastructure SNCF
    // ------------------------------------------------------------------------------------------------------------

    /**
     * Voies, liaison radio sol-train, quais et cantonnement actuel de SNCF Réseau (jeux SNCF Open Data, ODbL),
     * rattachés aux lignes et aux gares de VFF.
     *
     * @param  list<int>|null  $lineIds
     */
    public function infrastructure(string $dir, OpenDataLedger $ledger, ?array $lineIds = null): void
    {
        $this->portions($dir, 'voies.csv', 'line_track_portions', self::TRACK_FIELDS, $ledger, $lineIds,
            fn (object $r): array => [$r->regime, $this->int($r->tracks)]);
        $this->portions($dir, 'radio.csv', 'line_radio_portions', self::RADIO_FIELDS, $ledger, $lineIds,
            fn (object $r): array => [$r->system, $r->label]);

        $platforms = OpenDataFiles::csv("{$dir}/quais.csv", self::PLATFORM_FIELDS);
        $rows = DB::table('station_platforms as p')
            ->leftJoin('stations as s', 's.id', '=', 'p.station_id')
            ->when($lineIds !== null, fn ($q) => $q->whereIn('p.station_id', DB::table('station_lines')->whereIn('line_id', $lineIds ?? [])->select('station_id')))
            ->orderBy('p.code_ligne')->orderBy('p.pk_start_m')->orderBy('p.id')
            ->select(['s.slug', 'p.sncf_station_name', 'p.code_ligne', 'p.code_voie', 'p.rg_troncon', 'p.label', 'p.length_m', 'p.pk_start_m',
                'p.pk_end_m', 'p.lat', 'p.lon', 'p.match_method', 'p.source', 'p.dataset_modified']);
        foreach ($rows->cursor() as $p) {
            $provider = $this->rights->provider('station_platforms', $p->source);
            if (($refusal = $this->rights->refusal($provider)) !== null) {
                $ledger->withhold('infrastructure.platforms', $refusal);

                continue;
            }
            $ledger->cite($provider);
            $platforms->row([$p->slug, $p->sncf_station_name, $p->code_ligne, $p->code_voie, $this->int($p->rg_troncon), $p->label,
                $this->int($p->length_m), $this->int($p->pk_start_m), $this->int($p->pk_end_m),
                $p->lat !== null ? round((float) $p->lat, 6) : null, $p->lon !== null ? round((float) $p->lon, 6) : null,
                $p->match_method, $provider, $this->licence($provider), $p->dataset_modified]);
        }
        $ledger->count('quais.csv', $platforms->close());

        // Cantonnement et contrôle de vitesse actuels (jeux de SNCF Réseau, une seule source déclarée).
        $provider = $this->rights->provider('line_signalling', 'sncf');
        $block = OpenDataFiles::csv("{$dir}/cantonnement.csv", self::BLOCK_FIELDS);
        $rows = DB::table('line_signalling as ls')
            ->join('lines as l', 'l.id', '=', 'ls.line_id')
            ->when($lineIds !== null, fn ($q) => $q->whereIn('ls.line_id', $lineIds ?? []))
            ->orderBy('l.code')->orderBy('ls.pk_start_m')->orderBy('ls.id')
            ->select(['l.slug', 'l.code', 'ls.pk_start_m', 'ls.pk_end_m', 'ls.signalling', 'ls.signalling_systems', 'ls.train_protection',
                'ls.kvb_equipped', 'ls.source_label']);
        foreach ($rows->cursor() as $r) {
            if (($refusal = $this->rights->refusal($provider)) !== null) {
                $ledger->withhold('infrastructure.block', $refusal);

                continue;
            }
            $ledger->cite($provider);
            $block->row([$r->slug, $r->code, $this->int($r->pk_start_m), $this->int($r->pk_end_m), $r->signalling,
                $this->jsonList($r->signalling_systems), $this->jsonList($r->train_protection),
                $r->kvb_equipped === null ? null : ($r->kvb_equipped ? 'true' : 'false'), $r->source_label, $provider, $this->licence($provider)]);
        }
        $ledger->count('cantonnement.csv', $block->close());
    }

    /**
     * Portions de ligne d'un jeu SNCF (voies, radio) : lignes et PK, une colonne de plus par jeu.
     *
     * @param  list<string>  $fields
     * @param  list<int>|null  $lineIds
     * @param  callable(object): array{0: mixed, 1: mixed}  $extra
     */
    private function portions(string $dir, string $file, string $table, array $fields, OpenDataLedger $ledger, ?array $lineIds, callable $extra): void
    {
        $csv = OpenDataFiles::csv("{$dir}/{$file}", $fields);
        $rows = DB::table("{$table} as t")
            ->leftJoin('lines as l', 'l.id', '=', 't.line_id')
            ->when($lineIds !== null, fn ($q) => $q->whereIn('t.line_id', $lineIds ?? []))
            ->orderBy('t.code_ligne')->orderBy('t.rg_troncon')->orderBy('t.pk_start_m')->orderBy('t.id')
            ->select(array_merge(['l.slug', 't.code_ligne', 't.rg_troncon', 't.pk_start_m', 't.pk_end_m', 't.source', 't.dataset_modified'],
                $table === 'line_track_portions' ? ['t.regime', 't.tracks'] : ['t.system', 't.label']));
        foreach ($rows->cursor() as $r) {
            $provider = $this->rights->provider($table, $r->source);
            if (($refusal = $this->rights->refusal($provider)) !== null) {
                $ledger->withhold("infrastructure.{$file}", $refusal);

                continue;
            }
            $ledger->cite($provider);
            [$a, $b] = $extra($r);
            $csv->row([$r->slug, $r->code_ligne, $this->int($r->rg_troncon), $this->int($r->pk_start_m), $this->int($r->pk_end_m), $a, $b,
                $provider, $this->licence($provider), $r->dataset_modified]);
        }
        $ledger->count($file, $csv->close());
    }

    // ------------------------------------------------------------------------------------------------------------
    // Accidents relus : familles de sources
    // ------------------------------------------------------------------------------------------------------------

    /**
     * Références des accidents publiés dont le contexte a été relu (documents lus) : famille de source (nature et
     * fiabilité), accès, date de publication et lien, rien d'autre. Ni titre, ni auteur, ni éditeur, ni note, ni fait
     * relevé : un titre de presse peut nommer une victime. Les accidents dont la fiche concerne une personne
     * (heurt de personne) n'y figurent pas.
     *
     * @param  list<int>|null  $lineIds
     */
    public function accidentSources(string $dir, OpenDataLedger $ledger, ?array $lineIds = null): void
    {
        $csv = OpenDataFiles::csv("{$dir}/accidents-sources.csv", self::ACCIDENT_SOURCE_FIELDS);
        $publishable = AccidentKind::publishableSql('a');
        $sample = $lineIds !== null ? 'AND a.id IN (SELECT accident_id FROM accident_lines WHERE line_id = ANY(?::bigint[]))' : '';
        $personal = "'".implode("', '", AccidentKind::SOURCED_ONLY)."'";
        $rows = DB::cursor(<<<SQL
            SELECT a.id AS accident_id, a.slug, a.context_checked_on::text AS context_checked_on, r.position, r.provider, r.kind,
                   r.reliability, r.access, r.published, r.url
            FROM accidents a
            JOIN accident_references r ON r.accident_id = a.id
            WHERE {$publishable} AND a.alias_of_id IS NULL AND a.context_checked_on IS NOT NULL AND a.kind NOT IN ({$personal}) {$sample}
            ORDER BY a.occurred_on NULLS LAST, a.id, r.position, r.id
        SQL, $lineIds !== null ? ['{'.implode(',', array_map('intval', $lineIds)).'}'] : []);

        foreach ($rows as $r) {
            $provider = $this->rights->provider('accident_references', $r->provider, $r->url);
            if (($refusal = $this->rights->refusal($provider)) !== null) {
                $ledger->withhold('accidents.references', $refusal);

                continue;
            }
            $ledger->cite($provider);
            $csv->row([(int) $r->accident_id, $r->slug, $r->context_checked_on, $this->int($r->position), $r->provider, $r->kind,
                $r->reliability, $r->access, $r->published, $provider, $this->licence($provider), $this->safeUrl($r->url, $provider)]);
        }
        $ledger->count('accidents-sources.csv', $csv->close());
    }

    // ------------------------------------------------------------------------------------------------------------
    // Redirections d'adresses
    // ------------------------------------------------------------------------------------------------------------

    /**
     * Anciennes adresses de gares (fiches fondues) et de lignes (renommées). Une ancienne adresse qui porte le nom
     * d'une source non libre en préfixe est retirée : elle nommerait cette source dans le fichier publié.
     *
     * @param  list<int>|null  $lineIds
     */
    public function redirects(string $dir, OpenDataLedger $ledger, ?array $lineIds = null): void
    {
        $provider = 'vff';
        $stations = OpenDataFiles::csv("{$dir}/redirections-gares.csv", self::STATION_REDIRECT_FIELDS);
        $rows = DB::table('station_redirects as r')
            ->join('stations as s', 's.id', '=', 'r.station_id')
            ->when($lineIds !== null, fn ($q) => $q->whereIn('r.station_id', DB::table('station_lines')->whereIn('line_id', $lineIds ?? [])->select('station_id')))
            ->orderBy('r.old_slug')->select(['r.old_slug', 's.slug']);
        foreach ($rows->cursor() as $r) {
            if ($this->namesNonOpenSource($r->old_slug)) {
                $ledger->withhold('redirects.stations', OpenDataRights::NOT_OPEN);

                continue;
            }
            $ledger->cite($provider);
            $stations->row([$r->old_slug, $r->slug, $provider, $this->licence($provider)]);
        }
        $ledger->count('redirections-gares.csv', $stations->close());

        $lines = OpenDataFiles::csv("{$dir}/redirections-lignes.csv", self::LINE_REDIRECT_FIELDS);
        $map = $this->lineRedirects ??= LineSlugRedirects::read(LineSlugRedirects::path());
        $resolver = new LineSlugRedirects($map);
        $only = $lineIds !== null ? array_flip(DB::table('lines')->whereIn('id', $lineIds)->pluck('slug')->all()) : null;
        $old = array_keys($map);
        sort($old);
        foreach ($old as $oldSlug) {
            $target = $resolver->target((string) $oldSlug);
            if ($target === null || ($only !== null && ! isset($only[$target]))) {
                continue;
            }
            if ($this->namesNonOpenSource((string) $oldSlug)) {
                $ledger->withhold('redirects.lines', OpenDataRights::NOT_OPEN);

                continue;
            }
            $ledger->cite($provider);
            $lines->row([$oldSlug, $target, $provider, $this->licence($provider)]);
        }
        $ledger->count('redirections-lignes.csv', $lines->close());
    }

    /** Un slug qui commence par le préfixe d'une source non autorisée (préfixe de neutralisation, clé du registre). */
    private function namesNonOpenSource(string $slug): bool
    {
        if (str_starts_with($slug, NeutralLineSlugs::PREFIX)) {
            return true;
        }
        foreach (array_keys($this->rights->registry()->providers()) as $key) {
            if (! $this->rights->allows((string) $key) && str_starts_with($slug, (string) $key)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------------------------------------------------
    // Profils en long
    // ------------------------------------------------------------------------------------------------------------

    /**
     * Profils en long : mesure du terrain (altitudes IGN le long du tracé de la ligne), déclivités publiées par des
     * sources libres (SNCF Réseau, Wikipédia) et points du profil. Un profil mesuré sur un tracé qui n'est pas d'une
     * source libre n'est pas publié (ni ses mesures, ni ses points) : la position le long du tracé en dépend.
     *
     * @param  list<int>|null  $lineIds
     */
    public function profiles(string $dir, OpenDataLedger $ledger, ?array $lineIds = null): void
    {
        $traces = array_values(array_intersect($this->rights->allowedSources('source_traces'), OpenDataExporter::REPLACEMENT_TRACE_SOURCES));
        $quote = fn (string $v): string => "'".str_replace("'", "''", $v)."'";
        $traceOk = $traces === [] ? 'FALSE' : 'COALESCE(p.trace_source IN ('.implode(', ', array_map($quote, $traces)).'), FALSE)';
        $altitudes = $this->rights->allowedSources('line_profile_points');
        $altitudeOk = $altitudes === [] ? 'p.source IS NULL' : '(p.source IS NULL OR p.source IN ('.implode(', ', array_map($quote, $altitudes)).'))';
        $ok = "({$traceOk} AND {$altitudeOk})";
        $sample = $lineIds !== null ? 'AND p.line_id = ANY(?::bigint[])' : '';
        $bind = $lineIds !== null ? ['{'.implode(',', array_map('intval', $lineIds)).'}'] : [];

        $blocked = [];
        foreach (DB::select("SELECT p.line_profile_id AS id, COUNT(*) FILTER (WHERE NOT {$ok}) AS n FROM line_profile_points p WHERE TRUE {$sample} GROUP BY 1", $bind) as $r) {
            $blocked[(int) $r->id] = (int) $r->n;
        }

        $profiles = OpenDataFiles::csv("{$dir}/profils-lignes.csv", self::PROFILE_FIELDS);
        $published = [];
        $rows = DB::table('line_profiles as p')
            ->join('lines as l', 'l.id', '=', 'p.line_id')
            ->when($lineIds !== null, fn ($q) => $q->whereIn('p.line_id', $lineIds ?? []))
            ->orderBy('l.slug')->orderBy('p.id')
            ->select(['p.id', 'l.slug', 'l.code', 'p.source', 'p.step_m', 'p.length_m', 'p.trace_length_m', 'p.covered_m', 'p.off_path_m',
                'p.min_altitude_m', 'p.max_altitude_m', 'p.ascent_m', 'p.descent_m', 'p.max_grade_permil', 'p.max_grade_from_m',
                'p.max_grade_to_m', 'p.max_grade_direction', 'p.max_grade_withheld']);
        foreach ($rows->cursor() as $p) {
            $provider = $this->rights->provider('line_profiles', $p->source);
            $refusal = $this->rights->refusal($provider) ?? (($blocked[(int) $p->id] ?? 0) > 0 ? OpenDataRights::NOT_OPEN : null);
            if ($refusal !== null) {
                $ledger->withhold('profiles.measures', $refusal);
                $profiles->row([$p->slug, $p->code, 'withheld', ...array_fill(0, 14, null), null, null]);

                continue;
            }
            $ledger->cite($provider);
            $published[(int) $p->id] = true;
            $profiles->row([$p->slug, $p->code, 'published', $this->int($p->step_m), $this->int($p->length_m), $this->int($p->trace_length_m),
                $this->int($p->covered_m), $this->int($p->off_path_m), $p->min_altitude_m, $p->max_altitude_m, $p->ascent_m, $p->descent_m,
                $p->max_grade_permil, $this->int($p->max_grade_from_m), $this->int($p->max_grade_to_m), $p->max_grade_direction,
                $p->max_grade_withheld, $provider, $this->licence($provider)]);
        }
        $ledger->count('profils-lignes.csv', $profiles->close());

        // Points : ceux d'un profil publié, en flux, une ligne par point.
        $points = OpenDataFiles::csv("{$dir}/profils-points.csv", self::PROFILE_POINT_FIELDS);
        $altitudeProvider = fn (?string $s): ?string => $s === null ? null : $this->rights->provider('line_profile_points', $s);
        $traceProvider = fn (?string $s): ?string => $s === null ? null : $this->rights->provider('source_traces', $s);
        $cited = [];
        if ($published !== []) {
            $query = DB::table('line_profile_points as p')
                ->join('lines as l', 'l.id', '=', 'p.line_id')
                ->whereIn('p.line_profile_id', array_keys($published))
                ->orderBy('l.slug')->orderBy('p.line_profile_id')->orderBy('p.seq')
                ->select(['l.slug', 'p.seq', 'p.distance_m', 'p.pk_m', 'p.altitude_m', 'p.source', 'p.trace_source', 'p.interpolated',
                    'p.structure', 'p.branch']);
            foreach ($query->cursor() as $pt) {
                $a = $altitudeProvider($pt->source);
                $t = $traceProvider($pt->trace_source);
                if ($this->rights->refusal($t) !== null || ($a !== null && $this->rights->refusal($a) !== null)) {
                    continue;
                }
                $cited[(string) $a] = true;
                $cited[(string) $t] = true;
                $points->row([$pt->slug, $this->int($pt->seq), $this->int($pt->distance_m), $this->int($pt->pk_m), $pt->altitude_m, $a, $t,
                    $pt->interpolated ? 'true' : 'false', $pt->structure, $this->int($pt->branch)]);
            }
        }
        foreach (array_keys($cited) as $provider) {
            $ledger->cite($provider === '' ? null : $provider);
        }
        $written = $points->close();
        $total = (int) (DB::selectOne('SELECT COUNT(*) AS n FROM line_profile_points p WHERE TRUE '.$sample, $bind)->n ?? 0);
        if ($total > $written) {
            // Points des profils non publiés et points d'un tracé ou d'une altitude non libres.
            $ledger->withhold('profiles.points', OpenDataRights::NOT_OPEN, OpenDataLedger::REMOVED, $total - $written);
        }
        $ledger->count('profils-points.csv', $written);

        // Déclivités maximales publiées par des sources libres (SNCF Réseau, Wikipédia) : valeur, source et lien.
        $grades = OpenDataFiles::csv("{$dir}/declivites-sourcees.csv", self::GRADE_FIELDS);
        $only = $lineIds !== null ? array_flip(DB::table('lines')->whereIn('id', $lineIds)->pluck('slug')->all()) : null;
        $wikipedia = $this->wikipediaGrades ??= $this->load('sourced_grades.php');
        $sncf = $this->sncfGrades ??= $this->load('sourced_grades_sncf.php');
        foreach ([$wikipedia, $sncf] as $set) {
            $slugs = array_keys($set);
            sort($slugs);
            foreach ($slugs as $slug) {
                if ($only !== null && ! isset($only[(string) $slug])) {
                    continue;
                }
                $g = $set[$slug];
                $provider = is_string($g['provider'] ?? null) ? $g['provider'] : null;
                if (($refusal = $this->rights->refusal($provider)) !== null) {
                    $ledger->withhold('profiles.grades', $refusal);

                    continue;
                }
                $ledger->cite($provider);
                $grades->row([$slug, $g['max_grade_permil'] ?? null, $g['covered_share'] ?? null, $provider, $this->licence($provider),
                    $g['source_title'] ?? null, $this->safeUrl($g['source_url'] ?? null, $provider)]);
            }
        }
        $ledger->count('declivites-sourcees.csv', $grades->close());
    }

    // ------------------------------------------------------------------------------------------------------------
    // Outils
    // ------------------------------------------------------------------------------------------------------------

    /**
     * Fichier versionné de database/data.
     *
     * @return array<array-key, mixed>
     */
    private function load(string $file): array
    {
        $path = database_path("data/{$file}");
        $data = is_file($path) ? require $path : [];

        return is_array($data) ? $data : [];
    }

    /**
     * Fichier du matériel préservé avec ses trois parties (un fichier absent n'en a aucune).
     *
     * @param  array<array-key, mixed>  $data
     * @return array{venues: array<string, array<string, mixed>>, units: array<string, list<array<string, mixed>>>, others: list<array<string, mixed>>}
     */
    private function shaped(array $data): array
    {
        /** @var array{venues: array<string, array<string, mixed>>, units: array<string, list<array<string, mixed>>>, others: list<array<string, mixed>>} $shaped */
        $shaped = $data + ['venues' => [], 'units' => [], 'others' => []];

        return $shaped;
    }

    private function licence(?string $provider): ?string
    {
        if ($provider === null) {
            return null;
        }
        $info = $this->rights->registry()->provider($provider);

        return $info === null ? null : OpenDataRights::shortLicence($provider, (string) ($info['licence'] ?? ''));
    }

    /** Lien cité, gardé seulement s'il désigne le fournisseur retenu ou aucun fournisseur déclaré. */
    private function safeUrl(?string $url, ?string $provider): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        $host = $this->rights->registry()->providerForUrl($url);

        return $host === null || $host === $provider || $this->rights->allows($host) ? $url : null;
    }

    private function int(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    /** Liste d'un champ JSON (tableau de chaînes) en texte « a|b », null si vide. */
    private function jsonList(mixed $json): ?string
    {
        $list = is_string($json) ? json_decode($json, true) : $json;
        if (! is_array($list) || $list === []) {
            return null;
        }

        return implode('|', array_map(fn ($x): string => is_scalar($x) ? (string) $x : (string) json_encode($x, JSON_UNESCAPED_UNICODE), $list));
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }
}
