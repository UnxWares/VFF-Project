<?php

namespace App\Services\Accidents;

use App\Enums\AccidentDatePrecision;
use App\Enums\AccidentKind;
use App\Enums\AccidentLinkConfidence;
use App\Enums\AccidentLocationPrecision;
use App\Enums\AccidentReviewStatus;
use App\Models\Accident;
use App\Models\AccidentLine;
use App\Models\AccidentSource;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Import des accidents (vff:import-accidents) : collecte (Wikidata et Wikipédia, bilan des
 * enquêtes du BEA-TT), localisation, fusion des sources qui décrivent le même accident,
 * rattachement aux lignes, bilan retenu, résumé, écriture.
 *
 * Idempotent : un accident est retrouvé par son élément Wikidata ou par l'identifiant d'une de
 * ses sources (accident_sources.provider + external_id) ; son slug ne change plus une fois créé.
 * Seules les tables accidents, accident_lines et accident_sources sont écrites. Un accident
 * absent des sources lors d'un import n'est supprimé que sur demande ($prune, option --prune) ;
 * sinon il est seulement compté dans le rapport.
 */
final class AccidentImporter
{
    /** Notes qui mettent une fiche « à vérifier ». */
    public const REVIEW_CODES = [
        'toll_mismatch', 'toll_ambiguous', 'date_mismatch', 'date_ambiguous', 'location_commune_only',
        'commune_not_found', 'commune_ambiguous', 'level_crossing_not_found', 'outside_france',
        'no_location', 'no_line', 'line_uncertain', 'hinted_line_far', 'place_from_list', 'list_kind_hidden',
    ];

    /** Éléments rangés parmi les accidents qui n'en sont pas (vérifiés à la main). */
    public const EXCLUDED_ITEMS = [
        // Affaire de Tarnac : affaire judiciaire (sabotages de caténaires de 2008), pas un accident.
        'Q60504015' => true,
    ];

    /** Ordre de préférence des sources pour le bilan : l'enquête officielle d'abord. */
    private const DEATHS_PRIORITY = ['bea-tt', 'wikidata', 'wikipedia', ManualAccidentCollector::PROVIDER];

    private const INJURIES_PRIORITY = ['wikidata', 'wikipedia', ManualAccidentCollector::PROVIDER];

    /** Deux fiches décrivent le même accident : dates à un jour près et lieux à moins de 20 km. */
    private const SAME_EVENT_MAX_M = 20000;

    /** … ou même commune et dates à une semaine près (divergence signalée). */
    private const SAME_COMMUNE_MAX_DAYS = 7;

    /**
     * Puce de liste qui nomme une gare ou une ville du parcours du train (terminus de la relation, gare voisine)
     * au lieu du lieu de l'accident : à plus de 20 km, elle décrit encore une fiche sourcée du même jour et de la même
     * nature jusqu'à 150 km. Mesures du 22/11/2026 : doublons à 20, 32, 105 (Caen pour Cléon, relation Rouen - Caen)
     * et 119 km (Marseille pour Saint-Raphaël, relation Marseille - Nice) ; le plus proche vrai accident distinct
     * d'une fiche sourcée, de même jour et de même nature, est à 281 km (Fromental et Brétigny-sur-Orge, 12/07/2013).
     */
    public const ROUTE_MAX_M = 150000;

    /** @var array<string, true> identifiants des puces de liste lues par cet import */
    private array $listIds = [];

    /** @var array<int, true> fiches de liste déjà retrouvées par rapprochement de secours pendant cet import */
    private array $relinked = [];

    public function __construct(
        private readonly WikidataAccidentCollector $wikidata,
        private readonly BeaTtAccidentCollector $beaTt,
        private readonly WikipediaListCollector $list,
        private readonly WikipediaEditionCollector $editions,
        private readonly ManualAccidentCollector $manual,
        private readonly AccidentSlugTable $slugs,
        private readonly AccidentEditionMatcher $editionMatcher,
        private readonly AccidentLocator $locator,
        private readonly AccidentLineMatcher $matcher,
        private readonly AccidentSummary $summary,
    ) {}

    /** Fournisseurs lus par défaut ; l'import complet les lit tous. */
    public const SOURCES = ['wikidata', 'bea-tt', 'wikipedia-list', 'wikipedia-editions', 'manual'];

    /** Fournisseurs des listes de Wikipédia en anglais et en allemand (accident_sources.provider). */
    public const EDITION_PROVIDERS = ['wikipedia-en', 'wikipedia-de'];

    /**
     * @param  list<string>  $sources  fournisseurs à lire : wikidata (avec les articles de Wikipédia), bea-tt, wikipedia-list (listes
     *                                 d'accidents), wikipedia-editions (listes en anglais et en allemand, qui recoupent les fiches
     *                                 existantes sans en créer), manual (fiches rédigées à la main, database/data/accident_records_manual.php)
     */
    public function run(AccidentSourceClient $client, array $sources = self::SOURCES, bool $dryRun = false, bool $prune = false): AccidentImportResult
    {
        $result = new AccidentImportResult;
        $startedAt = now();

        $wd = in_array('wikidata', $sources, true) ? $this->wikidata->collect($client) : [];
        $bea = in_array('bea-tt', $sources, true) ? $this->beaTt->collect($client) : [];
        $listed = in_array('wikipedia-list', $sources, true) ? $this->list->collect($client) : [];
        $written = in_array('manual', $sources, true) ? $this->manual->collect() : [];
        $result->collected = ['wikidata' => count($wd), 'bea-tt' => count($bea), 'wikipedia-list' => count($listed), 'wikipedia-editions' => 0, 'manual' => count($written)];
        $this->listIds = [];
        $this->relinked = [];
        foreach ($listed as $l) {
            foreach ($l->sources as $s) {
                $this->listIds[$s->externalId] = true;
            }
        }
        $result->rejected = ['wikidata' => $this->wikidata->rejected, 'bea-tt' => $this->beaTt->rejected];
        if ($this->beaTt->warning !== null) {
            $result->warnings[] = $this->beaTt->warning;
        }

        foreach ([...$wd, ...$bea, ...$listed, ...$written] as $c) {
            $this->locator->locate($c);
        }
        [$candidates, $result->merged] = $this->merge($wd, $bea);
        // Les fiches rédigées à la main complètent les autres sources comme les puces des listes.
        [$candidates, $known] = $this->mergeList($candidates, [...$listed, ...$written], partial: count(array_intersect(['wikidata', 'bea-tt'], $sources)) < 2, result: $result);

        $seen = [];
        foreach ($known as [$candidate, $accidentId]) {
            if (! $dryRun) {
                $this->attachToKnown($candidate, $accidentId);
            }
            $seen[] = $accidentId;
        }
        foreach ($candidates as $c) {
            $skip = $this->skipReason($c);
            if ($skip !== null) {
                $result->skipped[] = ['key' => $c->key, 'name' => $c->labels['fr'] ?? $c->communeName ?? $c->key, 'reason' => $skip];

                continue;
            }
            $prepared = $this->prepare($c);
            $result->count('precision', $c->locationPrecision->value ?? 'none');
            $result->count('confidence', $prepared['links'][0]['confidence']->value ?? 'none');
            $result->count('kind', $prepared['attributes']['kind']);
            if ($dryRun) {
                $result->preview[] = $prepared['attributes'] + ['lines' => count($prepared['links'])];

                continue;
            }
            $accident = $this->persist($c, $prepared, $result);
            $seen[] = $accident->id;
            if ($accident->review_status === AccidentReviewStatus::ToReview) {
                $result->toReview[$accident->slug] = $accident->review_notes ?? [];
            }
        }
        if (! $dryRun) {
            // Les alias (doublons déclarés) ne sont jamais retirés : leur ancienne adresse redirige vers la fiche retenue.
            $unseen = Accident::query()->withoutGlobalScopes()->whereNotIn('id', $seen)->whereNull('alias_of_id')->where('imported_at', '<', $startedAt);
            $result->unseen = (clone $unseen)->pluck('slug')->all();
            // Retrait sur demande seulement, et jamais après un import partiel (une seule source lue).
            if ($prune && array_diff(self::SOURCES, $sources) === [] && $client->missing === []) {
                $unseen->delete();
                $result->pruned = $result->unseen;
            }
        }
        if (in_array('wikipedia-editions', $sources, true)) {
            $this->attachEditions($client, $dryRun, $result);
        }
        $result->requests = $client->requests;
        $result->missing = $client->missing;

        return $result;
    }

    /**
     * Listes d'accidents des Wikipédia en anglais et en allemand : chaque entrée qui décrit un accident déjà en
     * base (même jour, commune nommée comme lieu) lui ajoute sa source, jamais une fiche. C'est la seconde lecture
     * que vff:verify-accidents compare à la fiche ; l'import ne confirme rien lui-même.
     *
     * Une entrée qui ne rapproche aucune fiche est comptée (`editions`) et listée dans le bilan. Les sources de
     * ces listes qui ne sont plus lues (entrée corrigée, page remaniée) sont retirées quand toutes les pages ont été lues.
     */
    private function attachEditions(AccidentSourceClient $client, bool $dryRun, AccidentImportResult $result): void
    {
        $entries = $this->editions->collect($client);
        $result->collected['wikipedia-editions'] = count($entries);
        $kept = [];
        foreach ($entries as $entry) {
            [$status, $accident, $place] = $this->editionMatcher->match($entry);
            $result->count('editions', $status);
            if ($accident === null) {
                $result->editionsUnmatched[] = ['key' => $entry->lang.':'.$entry->date.' '.($entry->places[0]['name'] ?? '?'), 'reason' => $status];

                continue;
            }
            $externalId = ($entry->role === 'article' ? $entry->lang.'-article:' : $entry->lang.':').$entry->date.'-'.$accident->commune_insee;
            $kept[$entry->lang === 'de' ? 'wikipedia-de' : 'wikipedia-en'][] = $externalId;
            if ($dryRun) {
                continue;
            }
            $provider = $entry->lang === 'de' ? 'wikipedia-de' : 'wikipedia-en';
            $row = AccidentSource::query()->firstOrNew(['provider' => $provider, 'external_id' => $externalId]);
            $row->fill([
                'accident_id' => $accident->id, 'role' => $entry->role, 'title' => Str::limit($entry->title, 297), 'url' => $entry->url(),
                'licence' => WikipediaEditionCollector::LICENCE, 'licence_url' => WikipediaEditionCollector::LICENCE_URL,
                'revision' => $entry->revision,
                'facts' => array_filter([
                    'date' => $entry->date, 'commune_insee' => $accident->commune_insee, 'place' => $place,
                    'referenced' => $entry->referenced, 'references' => $entry->references,
                ], fn ($v) => $v !== []),
                'retrieved_at' => now(),
            ])->save();
        }
        if (! $dryRun && $this->editions->complete && $client->missing === []) {
            foreach (self::EDITION_PROVIDERS as $provider) {
                AccidentSource::query()->where('provider', $provider)->whereNotIn('external_id', $kept[$provider] ?? [])->delete();
            }
        }
    }

    /**
     * Fusion : une rangée du BEA-TT qui décrit un accident déjà connu de Wikidata (même jour à un
     * près, même commune ou lieux à moins de 20 km) enrichit cette fiche au lieu d'en créer une.
     *
     * @param  list<AccidentCandidate>  $wd
     * @param  list<AccidentCandidate>  $bea
     * @return array{0: list<AccidentCandidate>, 1: int}
     */
    public function merge(array $wd, array $bea): array
    {
        $kept = $wd;
        $merged = 0;
        foreach ($bea as $b) {
            $target = null;
            foreach ($wd as $w) {
                if ($this->sameEvent($w, $b)) {
                    $target = $w;
                    break;
                }
            }
            if ($target === null) {
                $kept[] = $b;

                continue;
            }
            $this->absorb($target, $b);
            $merged++;
        }

        return [$kept, $merged];
    }

    public function sameEvent(AccidentCandidate $a, AccidentCandidate $b): bool
    {
        if ($a->date === null || $b->date === null || $a->precision === null || $b->precision === null) {
            return false;
        }
        $sameDate = match (min($a->precision->rank(), $b->precision->rank())) {
            AccidentDatePrecision::Day->rank() => abs(Carbon::parse($a->date)->diffInDays(Carbon::parse($b->date))) <= 1,
            AccidentDatePrecision::Month->rank() => substr($a->date, 0, 7) === substr($b->date, 0, 7),
            default => substr($a->date, 0, 4) === substr($b->date, 0, 4),
        };
        $sameCommune = $a->communeInsee !== null && $a->communeInsee === $b->communeInsee;
        // Même commune et dates à une semaine près : même accident, dates à vérifier (absorb()).
        if ($sameCommune && ! $sameDate && $a->precision === AccidentDatePrecision::Day && $b->precision === AccidentDatePrecision::Day
            && abs(Carbon::parse($a->date)->diffInDays(Carbon::parse($b->date))) <= self::SAME_COMMUNE_MAX_DAYS) {
            return true;
        }
        if (! $sameDate) {
            return false;
        }
        if ($sameCommune) {
            return true;
        }
        if ($a->lat !== null && $b->lat !== null && $a->lng !== null && $b->lng !== null) {
            return self::distanceM($a->lat, $a->lng, $b->lat, $b->lng) <= self::SAME_EVENT_MAX_M;
        }

        return $a->departmentCode !== null && $a->departmentCode === $b->departmentCode;
    }

    /** Distance orthodromique en mètres. */
    public static function distanceM(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371008.8;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($h)));
    }

    /** Une rangée du BEA-TT complète la fiche Wikidata du même accident. */
    private function absorb(AccidentCandidate $w, AccidentCandidate $b): void
    {
        // Dates différentes : celle de l'enquête officielle est retenue, l'écart est signalé.
        if ($b->date !== null && $w->date !== null && $b->date !== $w->date && $w->precision === AccidentDatePrecision::Day) {
            $w->note('date_mismatch', "Wikidata {$w->date}, BEA-TT {$b->date}");
            $w->date = $b->date;
            $w->dateSource = $b->dateSource;
        } elseif ($b->date !== null && $w->precision !== null && $w->precision !== AccidentDatePrecision::Day) {
            $w->date = $b->date;
            $w->precision = $b->precision;
            $w->dateSource = $b->dateSource;
        }
        foreach ($b->sources as $s) {
            $w->addSource($s);
        }
        foreach ($b->deaths as $p => $n) {
            $w->deaths[$p] = $n;
        }
        $w->tollUrls += $b->tollUrls;
        foreach ($b->trains as $t) {
            if (! array_any($w->trains, fn ($x) => $x['label'] !== null && $x['label'] === $t['label'])) {
                $w->addTrain($t, $t['source']);
            }
        }
        // La nature tirée d'un simple libellé cède devant l'intitulé de l'enquête.
        if ($w->kind === null || $w->kindSource === 'label') {
            $w->kind = $b->kind;
            $w->kindSource = $b->kindSource;
        }
        if ($w->locationPrecision === AccidentLocationPrecision::Commune && $b->locationPrecision !== null
            && $b->locationPrecision->matchRadiusM() < $w->locationPrecision->matchRadiusM()) {
            $w->lat = $b->lat;
            $w->lng = $b->lng;
            $w->locationPrecision = $b->locationPrecision;
            $w->locationSource = $b->locationSource;
            $w->lineCodes = [...$w->lineCodes, ...$b->lineCodes];
            $w->levelCrossingPk = $b->levelCrossingPk;
            $w->notes = array_values(array_filter($w->notes, fn ($n) => $n['code'] !== 'location_commune_only'));
        }
    }

    /**
     * Les accidents des listes de Wikipédia complètent les autres sources : une puce qui décrit un
     * accident déjà connu (même règle que le BEA-TT) ajoute sa source à cette fiche et le bilan qui
     * lui manque, sans rien remplacer ; les autres deviennent des fiches « à vérifier ».
     *
     * Import partiel (une des deux autres sources non lue) : les fiches déjà en base issues de ces
     * sources servent aussi de cibles (knownAccidentId).
     *
     * @param  list<AccidentCandidate>  $kept
     * @param  list<AccidentCandidate>  $listed
     * @return array{0: list<AccidentCandidate>, 1: list<array{0: AccidentCandidate, 1: int}>}
     */
    private function mergeList(array $kept, array $listed, bool $partial, AccidentImportResult $result): array
    {
        $targets = $kept;
        $known = [];
        foreach ($listed as $l) {
            $target = array_find($targets, fn (AccidentCandidate $t) => $this->sameEvent($t, $l));
            if ($target !== null) {
                $this->absorbListed($target, $l);
                $result->count('list', 'merged');

                continue;
            }
            $target = $this->routeTarget($targets, $l);
            if ($target !== null) {
                $this->absorbListed($target, $l);
                $result->count('list', 'route');

                continue;
            }
            $accidentId = $partial ? $this->knownAccidentId($l) : null;
            if ($accidentId !== null) {
                $known[] = [$l, $accidentId];
                $result->count('list', 'attached');

                continue;
            }
            $kept[] = $l;
        }

        return [$kept, $known];
    }

    /** Une puce de liste complète la fiche du même accident : sa source, le bilan et la nature manquants. */
    private function absorbListed(AccidentCandidate $target, AccidentCandidate $listed): void
    {
        foreach ($listed->sources as $s) {
            $target->addSource($s);
        }
        if ($target->deaths === [] && isset($listed->deaths['wikipedia'])) {
            $target->deaths['wikipedia'] = $listed->deaths['wikipedia'];
            $target->tollUrls += $listed->tollUrls;
        }
        if ($target->injuries === [] && isset($listed->injuries['wikipedia'])) {
            $target->injuries['wikipedia'] = $listed->injuries['wikipedia'];
        }
        // Une puce seule ne masque jamais une fiche d'une autre source : la nature de la cible est
        // gardée et la fiche signalée pour relecture.
        if ($listed->kind !== null && in_array($listed->kind->value, AccidentKind::SOURCED_ONLY, true)) {
            $target->note('list_kind_hidden', $listed->kind->value);

            return;
        }
        if ($target->kind === null || $target->kindSource === 'label') {
            $target->kind = $listed->kind ?? $target->kind;
            $target->kindSource = $listed->kind !== null ? $listed->kindSource : $target->kindSource;
        }
    }

    /**
     * Fiche sourcée (Wikidata, article, BEA-TT) que décrit une puce de liste dont le lieu est une gare ou une ville du
     * parcours du train (onRoute) : une seule doit répondre, sinon la puce garde sa propre fiche (deux accidents
     * mélangés seraient pires qu'un doublon, que l'invariant accidents.same_day_nearby signale).
     *
     * @param  list<AccidentCandidate>  $targets
     */
    private function routeTarget(array $targets, AccidentCandidate $listed): ?AccidentCandidate
    {
        $found = array_values(array_filter($targets, fn (AccidentCandidate $t) => $this->onRoute(
            $listed, $t->date, $t->precision, $t->kind, [$t->communeName, ...array_values($t->labels)], $t->lat, $t->lng, $t->lineArticles,
        )));

        return count($found) === 1 ? $found[0] : null;
    }

    /**
     * La puce de liste décrit-elle cet accident, nommé par une gare ou une ville du parcours ? Même jour (dates au
     * jour des deux côtés), même nature lue des deux côtés, et l'un de ces liens : la commune de la fiche (ou un mot
     * de son nom) est citée par la puce (« reliant Rouen à Caen [...] au niveau de Cléon »), la puce et la fiche citent
     * la même ligne, ou les deux lieux sont à moins de ROUTE_MAX_M.
     *
     * @param  list<?string>  $names  commune et noms de la fiche
     * @param  list<string>  $lineArticles  articles de ligne cités par les sources de la fiche
     */
    public function onRoute(AccidentCandidate $listed, ?string $date, ?AccidentDatePrecision $precision, ?AccidentKind $kind,
        array $names, ?float $lat, ?float $lng, array $lineArticles = []): bool
    {
        if ($listed->date === null || $listed->date !== $date || $listed->precision !== AccidentDatePrecision::Day
            || $precision !== AccidentDatePrecision::Day || $listed->kind === null || $listed->kind !== $kind) {
            return false;
        }
        if ($this->citesPlace($listed, $names)) {
            return true;
        }
        if (array_intersect($listed->lineArticles, $lineArticles) !== []) {
            return true;
        }

        return $listed->lat !== null && $listed->lng !== null && $lat !== null && $lng !== null
            && self::distanceM($listed->lat, $listed->lng, $lat, $lng) <= self::ROUTE_MAX_M;
    }

    /**
     * Un lieu cité par la puce (lieu retenu, autres lieux, gare) nomme la commune de la fiche : même nom, ou premier
     * mot d'un nom composé (« Nurieux » pour Nurieux-Volognat), ou mot du nom de la fiche (« Lagny-Pomponne »).
     *
     * @param  list<?string>  $names
     */
    private function citesPlace(AccidentCandidate $listed, array $names): bool
    {
        $cited = array_filter(array_map(
            fn (?string $n): string => Str::slug((string) $n),
            [$listed->communeName, $listed->stationHint, ...array_column($listed->placeHints, 0)],
        ), fn (string $s): bool => mb_strlen($s) >= 4);
        foreach ($names as $name) {
            $slug = Str::slug((string) $name);
            if ($slug === '') {
                continue;
            }
            foreach ($cited as $c) {
                if ($slug === $c || str_starts_with($slug, $c.'-') || (str_contains((string) $name, ' ') && str_contains('-'.$slug.'-', '-'.$c.'-'))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Fiche déjà en base qui décrit le même accident (import partiel) : même commune ou moins de
     * 20 km, à un jour près. Seules les fiches qui ont une source autre qu'une liste (Wikidata,
     * BEA-TT) sont des cibles, comme à l'import complet ; une puce déjà rattachée retrouve sa fiche.
     */
    private function knownAccidentId(AccidentCandidate $c): ?int
    {
        if ($c->date === null) {
            return null;
        }
        $notFromList = fn ($q) => $q->where(fn ($w) => $w->where('provider', '!=', 'wikipedia')->orWhere('role', '!=', 'list'));
        $attached = AccidentSource::query()->where('provider', 'wikipedia')->where('role', 'list')
            ->whereIn('external_id', array_map(fn (AccidentSourceRef $s) => $s->externalId, $c->sources))->value('accident_id');
        if ($attached !== null && Accident::query()->withoutGlobalScopes()->whereKey($attached)->whereHas('sources', $notFromList)->exists()) {
            return (int) $attached;
        }
        // Sans lieu, le rapprochement ne tiendrait qu'à la date : n'importe quel accident du même jour.
        if ($c->communeInsee === null && ($c->lat === null || $c->lng === null)) {
            return null;
        }
        $query = Accident::query()->withoutGlobalScopes()
            ->whereBetween('occurred_on', [Carbon::parse($c->date)->subDay()->toDateString(), Carbon::parse($c->date)->addDay()->toDateString()])
            ->whereHas('sources', $notFromList);
        $query->where(function ($q) use ($c): void {
            if ($c->communeInsee !== null) {
                $q->orWhere('commune_insee', $c->communeInsee);
            }
            if ($c->lat !== null && $c->lng !== null) {
                $q->orWhereRaw('ST_DWithin(geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$c->lng, $c->lat, self::SAME_EVENT_MAX_M]);
            }
        });
        $id = $query->orderBy('id')->value('id');
        if ($id !== null) {
            return (int) $id;
        }
        // Gare ou ville du parcours (onRoute) : fiche sourcée publiable du même jour et de la même nature, une seule.
        if ($c->kind === null || $c->precision !== AccidentDatePrecision::Day) {
            return null;
        }
        $found = Accident::query()->withoutGlobalScopes()->withCoordinates()
            ->whereNull('alias_of_id')->whereDate('occurred_on', $c->date)->where('date_precision', AccidentDatePrecision::Day->value)
            ->where('kind', $c->kind->value)->whereHas('sources', $notFromList)->with('sources')->get()
            ->filter(fn (Accident $a): bool => $this->onRoute(
                $c, $a->occurred_on->toDateString(), $a->date_precision, $a->kind, [$a->commune_name, ...array_values($a->name)],
                $a->lat !== null ? (float) $a->lat : null, $a->lng !== null ? (float) $a->lng : null,
                $a->sources->flatMap(fn (AccidentSource $s): array => (array) ($s->facts['line_articles'] ?? []))->values()->all(),
            ))->values();

        return $found->count() === 1 ? $found->first()->id : null;
    }

    /** Ajoute la source de liste à une fiche déjà en base et complète son bilan s'il manque ; rien d'autre ne change. */
    private function attachToKnown(AccidentCandidate $c, int $accidentId): void
    {
        DB::transaction(function () use ($c, $accidentId): void {
            $accident = Accident::query()->withoutGlobalScopes()->findOrFail($accidentId);
            foreach ($c->sources as $s) {
                $row = AccidentSource::query()->firstOrNew(['provider' => $s->provider, 'external_id' => $s->externalId]);
                $row->fill([
                    'accident_id' => $accident->id, 'role' => $s->role, 'title' => Str::limit($s->title, 297),
                    'url' => $s->url, 'licence' => $s->licence, 'licence_url' => $s->licenceUrl,
                    'revision' => $s->revision, 'facts' => $s->facts !== [] ? $s->facts : null, 'retrieved_at' => now(),
                ]);
                // Un même lien ne figure qu'une fois par accident : la puce relue sous un nouvel identifiant
                // (lieu ou bilan corrigé sur Wikipédia, identifiants refaits) remplace l'ancienne ligne.
                AccidentSource::query()->where('accident_id', $accident->id)->where('url', $s->url)
                    ->when($row->exists, fn ($q) => $q->where('id', '!=', $row->id))->delete();
                $row->save();
            }
            $changes = [];
            if ($accident->deaths === null && isset($c->deaths['wikipedia'])) {
                $changes = ['deaths' => $c->deaths['wikipedia'], 'toll_source' => 'wikipedia', 'toll_source_url' => $c->tollUrls['wikipedia'] ?? null];
            }
            if ($accident->injuries === null && isset($c->injuries['wikipedia'])) {
                $changes['injuries'] = $c->injuries['wikipedia'];
            }
            if ($changes !== []) {
                $accident->forceFill($changes)->save();
            }
        });
    }

    /** Candidat hors du périmètre (hors de France), null s'il est gardé. */
    private function skipReason(AccidentCandidate $c): ?string
    {
        if ($c->date === null || $c->precision === null) {
            return 'no_date';
        }
        // Aucune commune française à moins de 15 km : hors du territoire (même déclaré français,
        // comme une ligne de l'Algérie coloniale).
        if ($c->hasNote('outside_france')) {
            return 'outside_france';
        }
        // Métro et tramway urbains : hors des lignes de VFF (comme les transports guidés urbains
        // du BEA-TT, écartés à la lecture).
        if ($c->urbanTransit) {
            return 'urban_transit';
        }
        // Point hors des communes françaises et pays voisin déclaré (rive allemande du Rhin), ou
        // à plus d'un kilomètre sans pays déclaré : accident d'un pays voisin, pas un pont frontalier.
        if ($c->offshoreM !== null && ! $c->declaredInFrance && ($c->declaredElsewhere || $c->offshoreM > 1000)) {
            return 'outside_france';
        }
        if ($c->wikidataId !== null && isset(self::EXCLUDED_ITEMS[$c->wikidataId])) {
            return 'excluded';
        }
        if (str_starts_with($c->key, 'wikipedia-list:')) {
            // Heurts de personnes : sujet délicat, jamais publiés (la liste n'en dit rien d'utile).
            if ($c->kind === AccidentKind::PersonStruck) {
                return 'person_struck';
            }
            // Puce dont le lieu n'a pas pu être identifié : ni point, ni ligne, ni commune.
            if ($c->lat === null && $c->communeInsee === null) {
                return 'no_place';
            }
        }
        if ($c->lat === null && $c->communeInsee === null && ! $c->declaredInFrance && ! str_starts_with($c->key, 'bea-tt:')) {
            return 'not_in_france';
        }

        return null;
    }

    /**
     * Attributs à écrire : bilan retenu, nom, résumé, rattachements, état de relecture.
     *
     * @return array{attributes: array<model-property<Accident>, mixed>, links: list<array<string, mixed>>, station: ?array{id: int, distance_m: int}, review: bool}
     */
    public function prepare(AccidentCandidate $c): array
    {
        $kind = $c->kind ?? AccidentKind::Other;
        $deaths = $this->decide($c->deaths, self::DEATHS_PRIORITY);
        $injuries = $this->decide($c->injuries, self::INJURIES_PRIORITY);
        foreach (['deaths' => $deaths, 'injuries' => $injuries] as $field => $d) {
            if ($d['disputed']) {
                $c->note('toll_mismatch', ($field === 'deaths' ? 'morts' : 'blessés').' : '.$d['detail']);
            }
        }

        $links = $this->matcher->match($c);
        if ($c->lat === null) {
            $c->note('no_location', '');
        } elseif ($links === []) {
            $c->note('no_line', '');
        }
        $station = $this->matcher->nearestStation($c);
        $review = array_any($c->notes, fn ($n) => in_array($n['code'], self::REVIEW_CODES, true))
            || (($links[0]['confidence'] ?? null) === AccidentLinkConfidence::Low);

        $operators = array_values(array_unique($c->operators));
        $trains = array_map(fn ($t) => $t + ['operator' => null], $c->trains);
        if (count($operators) === 1 && $trains !== []) {
            $trains = array_map(fn ($t) => ['operator' => $t['operator'] ?? $operators[0]] + $t, $trains);
        } elseif ($operators !== []) {
            $trains[] = ['label' => null, 'category' => null, 'number' => null, 'material' => null, 'operator' => implode(', ', $operators), 'source' => null];
        }

        $attributes = [
            'wikidata_id' => $c->wikidataId,
            'occurred_on' => $c->date,
            'date_precision' => $c->precision?->value,
            'occurred_at_time' => $c->time,
            'kind' => $kind->value,
            'name' => $this->names($c, $kind),
            'commune_insee' => $c->communeInsee,
            'commune_name' => $c->communeName,
            'department_code' => $c->departmentCode,
            'geom' => $c->lat !== null && $c->lng !== null ? Point::makeGeodetic($c->lat, $c->lng) : null,
            'location_precision' => $c->locationPrecision?->value,
            'location_source' => $c->lat !== null ? $c->locationSource : null,
            'nearest_station_id' => $station['id'] ?? null,
            'nearest_station_distance_m' => $station['distance_m'] ?? null,
            'deaths' => $deaths['value'],
            'injuries' => $injuries['value'],
            'toll_source' => $deaths['source'] ?? $injuries['source'],
            'toll_source_url' => $c->tollUrls[$deaths['source'] ?? ''] ?? null,
            'trains' => $trains !== [] ? $trains : null,
            'wikipedia' => $c->wikipedia !== [] ? $c->wikipedia : null,
            'summary' => $this->summary->write([
                'date' => (string) $c->date,
                'precision' => $c->precision ?? AccidentDatePrecision::Year,
                'kind' => $kind,
                'commune' => $c->communeName,
                'department' => $c->departmentCode,
                'trains' => $c->trains,
                'deaths' => $deaths['value'],
                'injuries' => $injuries['value'],
                'toll_disputed' => $deaths['disputed'] || $injuries['disputed'],
            ]),
            // Jamais « ok » à l'import : seule la vérification (vff:verify-accidents) confirme la date et le lieu.
            'review_status' => AccidentReviewStatus::ToReview->value,
            'review_notes' => $c->notes !== [] ? $c->notes : null,
            'provenance' => array_filter([
                'date' => $c->dateSource,
                'kind' => $c->kindSource,
                'location' => $c->lat !== null ? $c->locationSource : null,
                'deaths' => $deaths['source'],
                'injuries' => $injuries['source'],
                'name' => isset($c->labels['fr']) ? 'wikidata' : 'vff',
            ]),
        ];

        return ['attributes' => $attributes, 'links' => $links, 'station' => $station, 'review' => $review];
    }

    /**
     * Valeur retenue parmi les observations des sources, dans l'ordre de préférence.
     *
     * @param  array<string, int>  $observations
     * @param  list<string>  $priority
     * @return array{value: ?int, source: ?string, disputed: bool, detail: string}
     */
    public function decide(array $observations, array $priority): array
    {
        foreach ($priority as $provider) {
            if (isset($observations[$provider])) {
                $distinct = array_unique(array_values($observations));
                $labels = ['bea-tt' => 'BEA-TT', 'wikidata' => 'Wikidata', 'wikipedia' => 'Wikipédia', ManualAccidentCollector::PROVIDER => 'fiche rédigée'];

                return [
                    'value' => $observations[$provider],
                    'source' => $provider,
                    'disputed' => count($distinct) > 1,
                    'detail' => implode(', ', array_map(fn ($p, $n) => ($labels[$p] ?? $p)." {$n}", array_keys($observations), $observations)),
                ];
            }
        }

        return ['value' => null, 'source' => null, 'disputed' => false, 'detail' => ''];
    }

    /**
     * Nom par langue : libellé Wikidata, sinon nature et commune (« Déraillement à Cléon »).
     *
     * @return array<string, string>
     */
    private function names(AccidentCandidate $c, AccidentKind $kind): array
    {
        return $this->summary->names($c->labels, $kind, $c->communeName);
    }

    /**
     * Écriture d'un accident, de ses rattachements et de ses sources (une transaction).
     *
     * Une fiche que la vérification a déjà confirmée (règle gardée dans `verification`) reste « ok » si
     * l'import ne lui trouve aucun point à vérifier : rejouer l'import ne la fait pas osciller.
     *
     * @param  array{attributes: array<model-property<Accident>, mixed>, links: list<array<string, mixed>>, station: ?array{id: int, distance_m: int}, review: bool}  $prepared
     */
    private function persist(AccidentCandidate $c, array $prepared, AccidentImportResult $result): Accident
    {
        return DB::transaction(function () use ($c, $prepared, $result): Accident {
            $accident = $this->existing($c) ?? new Accident;
            $created = ! $accident->exists;
            $confirmed = ! $created && $accident->review_status === AccidentReviewStatus::Ok && ($accident->verification['rule'] ?? null) !== null;
            $accident->fill($prepared['attributes'] + ['imported_at' => now()]);
            if ($confirmed && ! $prepared['review']) {
                $accident->review_status = AccidentReviewStatus::Ok;
            }
            if ($created) {
                $accident->slug = $this->retainedSlug($c) ?? $this->uniqueSlug($c, $prepared['attributes']['name']['fr']);
            }
            $accident->save();
            $result->count('written', $created ? 'created' : 'updated');

            AccidentLine::query()->where('accident_id', $accident->id)->delete();
            foreach ($prepared['links'] as $link) {
                $row = new AccidentLine;
                $row->forceFill([
                    'accident_id' => $accident->id,
                    'line_id' => $link['line_id'],
                    'is_primary' => $link['is_primary'],
                    'era_year' => $link['era_year'],
                    'distance_m' => $link['distance_m'],
                    'pk_m' => $link['pk_m'],
                    'section_from_station_id' => $link['section_from_station_id'],
                    'section_to_station_id' => $link['section_to_station_id'],
                    'method' => $link['method'],
                    'confidence' => $link['confidence'],
                    'score' => $link['score'],
                ])->save();
            }

            $keep = [];
            foreach ($c->sources as $s) {
                $row = AccidentSource::query()->where('provider', $s->provider)->where('external_id', $s->externalId)->first();
                if ($row !== null && $row->accident_id !== $accident->id) {
                    $this->retire((int) $row->accident_id, $accident, $result);
                }
                $row ??= new AccidentSource(['provider' => $s->provider, 'external_id' => $s->externalId]);
                $row->fill([
                    'accident_id' => $accident->id,
                    'role' => $s->role,
                    'title' => Str::limit($s->title, 297),
                    'url' => $s->url,
                    'licence' => $s->licence,
                    'licence_url' => $s->licenceUrl,
                    'revision' => $s->revision,
                    'facts' => $s->facts !== [] ? $s->facts : null,
                    'retrieved_at' => now(),
                ]);
                // Un même lien ne figure qu'une fois par accident.
                AccidentSource::query()->where('accident_id', $accident->id)->where('url', $s->url)
                    ->when($row->exists, fn ($q) => $q->where('id', '!=', $row->id))->delete();
                $row->save();
                $keep[] = $row->id;
            }
            // Les sources des listes anglaise et allemande se renouvellent dans attachEditions, pas ici.
            AccidentSource::query()->where('accident_id', $accident->id)->whereNotIn('id', $keep)->whereNotIn('provider', self::EDITION_PROVIDERS)->delete();

            return $accident;
        });
    }

    /** Fiche existante : par l'élément Wikidata, sinon par l'identifiant d'une de ses sources. */
    private function existing(AccidentCandidate $c): ?Accident
    {
        if ($c->wikidataId !== null && ($a = Accident::query()->withoutGlobalScopes()->where('wikidata_id', $c->wikidataId)->first()) !== null) {
            return $a;
        }
        foreach ($c->sources as $s) {
            $id = AccidentSource::query()->where('provider', $s->provider)->where('external_id', $s->externalId)->value('accident_id');
            if ($id !== null && ($a = Accident::query()->withoutGlobalScopes()->find($id)) !== null
                && ($a->wikidata_id === null || $a->wikidata_id === $c->wikidataId)) {
                return $a;
            }
        }

        return str_starts_with($c->key, 'wikipedia-list:') ? $this->relinkedListAccident($c) : null;
    }

    /**
     * Rapprochement de secours d'une puce de liste dont l'identifiant a changé (lieu ou bilan corrigé sur
     * Wikipédia) : la fiche de liste du même jour dont la puce n'a pas été relue, à la même commune, ou à
     * défaut au même bilan et à la même nature. Une seule fiche doit répondre, sinon aucune n'est choisie
     * (une nouvelle fiche vaut mieux que deux accidents mélangés).
     */
    private function relinkedListAccident(AccidentCandidate $c): ?Accident
    {
        if ($c->date === null || $c->precision !== AccidentDatePrecision::Day) {
            return null;
        }
        $orphans = Accident::query()->withoutGlobalScopes()->whereNull('wikidata_id')
            ->whereDate('occurred_on', $c->date)
            ->whereHas('sources', fn ($q) => $q->where('provider', 'wikipedia')->where('role', 'list'))
            ->whereDoesntHave('sources', fn ($q) => $q->where(fn ($w) => $w->where('provider', '!=', 'wikipedia')->orWhere('role', '!=', 'list')))
            ->with('sources')
            ->get()
            ->filter(fn (Accident $a): bool => ! isset($this->relinked[$a->id])
                && $a->sources->every(fn (AccidentSource $s): bool => ! isset($this->listIds[(string) $s->external_id])));

        $kind = $c->kind ?? AccidentKind::Other;
        $deaths = $c->deaths['wikipedia'] ?? null;
        $sameCommune = $orphans->filter(fn (Accident $a): bool => $c->communeInsee !== null && $a->commune_insee === $c->communeInsee);
        $sameFacts = $orphans->filter(fn (Accident $a): bool => $deaths !== null && $a->deaths === $deaths && $a->kind === $kind);
        $match = match (true) {
            $sameCommune->count() === 1 => $sameCommune->first(),
            $sameCommune->isEmpty() && $sameFacts->count() === 1 => $sameFacts->first(),
            default => null,
        };
        if ($match !== null) {
            $this->relinked[$match->id] = true;
        }

        return $match;
    }

    /**
     * Une source passe d'une ancienne fiche à celle-ci (accident désormais connu de Wikidata) :
     * l'ancienne fiche, qui faisait doublon, est retirée si elle n'a plus d'autre source.
     */
    private function retire(int $oldId, Accident $into, AccidentImportResult $result): void
    {
        if ($oldId === $into->id) {
            return;
        }
        $others = AccidentSource::query()->where('accident_id', $oldId)->count();
        // Une fiche alias (doublon déclaré) reste en base, même sans source : son adresse redirige vers la fiche retenue.
        $alias = Accident::query()->withoutGlobalScopes()->whereKey($oldId)->whereNotNull('alias_of_id')->exists();
        if ($others <= 1 && ! $alias) {
            $slug = Accident::query()->withoutGlobalScopes()->whereKey($oldId)->value('slug');
            Accident::query()->withoutGlobalScopes()->whereKey($oldId)->delete();
            $result->retired[] = "{$slug} → {$into->slug}";
        }
    }

    /**
     * Slug retenu d'une fiche renommée (database/data/accident_slugs.php), s'il est libre : une base reconstruite
     * redonne ainsi les noms actuels, que les fichiers de contexte portent.
     */
    private function retainedSlug(AccidentCandidate $c): ?string
    {
        $slug = $this->slugs->retained($c->sources);

        return $slug !== null && ! Accident::query()->withoutGlobalScopes()->where('slug', $slug)->exists() ? $slug : null;
    }

    /** Slug : date (selon sa précision) puis commune ou nom, suffixe numérique si déjà pris. */
    private function uniqueSlug(AccidentCandidate $c, string $name): string
    {
        $date = match ($c->precision) {
            AccidentDatePrecision::Day => (string) $c->date,
            AccidentDatePrecision::Month => substr((string) $c->date, 0, 7),
            default => substr((string) $c->date, 0, 4),
        };
        $base = Str::limit($date.'-'.Str::slug($c->communeName ?? $name), 150, '');
        $slug = $base;
        for ($i = 2; $this->slugs->isRetired($slug) || Accident::query()->withoutGlobalScopes()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
