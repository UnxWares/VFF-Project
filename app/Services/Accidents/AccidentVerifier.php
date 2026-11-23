<?php

namespace App\Services\Accidents;

use App\Enums\AccidentDatePrecision;
use App\Enums\AccidentKind;
use App\Enums\AccidentReviewStatus;
use App\Enums\WebhookEvent;
use App\Models\Accident;
use App\Models\AccidentLine;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vérification des fiches d'accidents (vff:verify-accidents), rejouable : chaque passage repart des sources
 * déjà lues (accident_sources.facts et leur cache), sans rien redemander, et un second passage ne change rien.
 *
 *   1. doublons : deux fiches du même accident, l'une gardée en alias (AccidentAliasFinder) ;
 *   2. nature : une fiche « autre » prend la nature que sa source dit (AccidentKindFromSources) ;
 *   3. ligne : la ligne que la source cite est rattachée, avec son titre et le permalien (AccidentCitedLines) ;
 *   4. date et lieu : chaque source est relue séparément (AccidentClaimReader) et comparée à la fiche.
 *
 * Une fiche est « ok » seulement si :
 *   - une source primaire fiable (enquête du BEA-TT) donne la date ET la commune de la fiche (`primary_source`) ; ou
 *   - deux sources de familles différentes (AccidentClaim : officielle, Wikipédia, Wikidata, liste anglaise ou
 *     allemande avec sa propre référence) donnent toutes deux la date ET la commune, sans qu'aucune autre ne dise autre chose (`two_sources` ; `wikidata_wikipedia`
 *     quand ce sont seulement Wikidata sans référence et Wikipédia, que l'on distingue parce que l'une peut
 *     être copiée de l'autre ; `wikipedia_editions` quand l'une des deux est une liste en anglais ou en allemand
 *     qui cite sa propre référence et l'autre Wikipédia en français).
 *   La presse numérisée (Delpher, Deutsches Zeitungsportal, Europeana, Gallica, Le Temps...) forme UNE famille
 *   (AccidentClaim::PRESS), quel que soit le nombre d'articles : un article la compte seulement s'il donne la date de la
 *   fiche (au jour, ou « hier » dans la presse du lendemain), sa commune et son bilan (le même nombre de morts, ou un
 *   bilan provisoire, marqué comme tel dans le fichier de contexte, inférieur ou égal) ; un article qui donne un autre
 *   jour ou une autre commune contredit la fiche, un autre bilan la laisse sans confirmation (`verification.deaths`).
 * et qu'aucune note de doute sur la date, le lieu ou le bilan ne reste (BLOCKING). La précision du lieu
 * reste celle que la source donne (commune, gare, point) ; le rattachement à une ligne porte sa propre
 * confiance. Une fiche déjà « ok » n'est jamais rendue « à vérifier » pour un défaut de confirmation : seulement
 * quand deux sources se contredisent (note `verification_conflict` ou `alias_toll_mismatch`).
 */
final class AccidentVerifier
{
    public const RULE_PRIMARY = 'primary_source';

    public const RULE_TWO_SOURCES = 'two_sources';

    /** Wikidata (déclarations sans référence) et Wikipédia : deux pages, mais la première est souvent copiée de la seconde. */
    public const RULE_WIKIMEDIA_PAIR = 'wikidata_wikipedia';

    /** Wikipédia et une liste en anglais ou en allemand qui cite sa propre référence : indépendantes, mais deux pages Wikimedia. */
    public const RULE_EDITIONS = 'wikipedia_editions';

    /** Une commune citée à moins de cette distance du point de la fiche n'est pas une contradiction (gare voisine, hameau). */
    private const NEARBY_COMMUNE_M = 15000;

    /** Notes qui laissent la fiche « à vérifier » même quand la date et le lieu sont confirmés. */
    public const BLOCKING = [
        'toll_mismatch', 'toll_ambiguous', 'date_mismatch', 'date_ambiguous', 'commune_not_found', 'commune_ambiguous',
        'level_crossing_not_found', 'outside_france', 'no_location', 'hinted_line_far', 'place_corrected',
        'possible_duplicate', 'alias_toll_mismatch', 'verification_conflict',
    ];

    /** Notes que la vérification écrit elle-même et recalcule à chaque passage. */
    private const OWN_NOTES = ['possible_duplicate', 'alias_toll_mismatch', 'verification_conflict'];

    private const PROVIDER_LABELS = [
        'bea-tt' => 'BEA-TT', 'wikidata' => 'Wikidata', 'wikipedia' => 'Wikipédia',
        'wikipedia-en' => 'Wikipédia en anglais', 'wikipedia-de' => 'Wikipédia en allemand',
        'presse' => 'presse numérisée', 'delpher' => 'Delpher', 'gallica' => 'Gallica', 'europeana' => 'Europeana',
        'letempsarchives' => 'archives du Temps', 'retronews' => 'RetroNews',
    ];

    public function __construct(
        private readonly AccidentClaimReader $claims,
        private readonly AccidentAliasFinder $aliases,
        private readonly AccidentKindFromSources $kinds,
        private readonly AccidentCitedLines $citedLines,
        private readonly AccidentSummary $summary,
        private readonly WebhookDispatcher $webhooks,
    ) {}

    /**
     * @param  bool  $dryRun  tout est calculé et écrit dans une transaction annulée à la fin : les chiffres sont exacts, rien ne reste
     * @param  bool  $demote  rend aussi « à vérifier » les fiches « ok » qu'aucune règle ne confirme
     */
    public function run(AccidentSourceClient $client, bool $dryRun = false, bool $demote = false): AccidentVerificationResult
    {
        $result = new AccidentVerificationResult;
        DB::beginTransaction();
        try {
            $result->before = $this->counts();
            $possible = $this->mergeDuplicates($result);
            $this->reclassify($client, $result);
            $accidents = $this->accidents();
            $this->citedLines->prepare($client, $accidents->all(), $result);
            foreach ($accidents as $accident) {
                $this->citedLines->apply($accident, $result);
                $accident->refresh()->load('sources', 'references', 'aliases.sources');
                $firstVerification = $accident->verified_at === null;
                $this->verify($accident, $possible[$accident->id] ?? [], $demote, $result);
                if ($firstVerification) {
                    $this->announce($accident, $result);
                }
            }
            $result->after = $this->counts();
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        return $result;
    }

    /**
     * Fiche vérifiée pour la première fois : annoncée aux abonnés (`accident.published`) si elle est publique,
     * jamais un alias ni une nature masquée. L'annonce attend la validation de la transaction : une
     * simulation (annulée) n'annonce rien.
     */
    private function announce(Accident $accident, AccidentVerificationResult $result): void
    {
        if ($accident->verified_at === null || $accident->alias_of_id !== null || ! Accident::query()->whereKey($accident->id)->exists()) {
            return;
        }
        $result->announced[] = $accident->slug;
        $this->webhooks->dispatch(WebhookEvent::AccidentPublished, 'accident', $accident->slug, route('api.accidents.show', ['accident' => $accident->slug]));
    }

    /**
     * Fiches publiables ou masquées, hors alias, avec leurs sources.
     *
     * @return Collection<int, Accident>
     */
    private function accidents(): Collection
    {
        return Accident::query()->withoutGlobalScopes()->whereNull('alias_of_id')->with('sources')->orderBy('id')->get();
    }

    /** @return array<string, int> fiches publiées : total, ok, to_review, other */
    private function counts(): array
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE review_status = \'ok\') AS ok,
                    COUNT(*) FILTER (WHERE review_status = \'to_review\') AS to_review,
                    COUNT(*) FILTER (WHERE kind = \'other\') AS other
             FROM accidents WHERE alias_of_id IS NULL AND '.AccidentKind::publishableSql(),
        );

        return ['total' => (int) $row->total, 'ok' => (int) $row->ok, 'to_review' => (int) $row->to_review, 'other' => (int) $row->other];
    }

    /**
     * Doublons : fusionne ceux qui ont chacun leur description, signale les autres.
     *
     * @return array<int, list<string>> fiche → slugs des fiches qui sont peut-être le même accident
     */
    private function mergeDuplicates(AccidentVerificationResult $result): array
    {
        $possible = [];
        foreach ($this->aliases->pairs() as $pair) {
            if ($pair['alias'] !== null) {
                $pair['alias']->forceFill(['alias_of_id' => $pair['keep']->id])->save();
                $result->aliases[] = [
                    'alias' => $pair['alias']->slug, 'of' => $pair['keep']->slug,
                    'reason' => 'même jour, '.($pair['distance_m'] !== null ? 'à '.$pair['distance_m'].' m, ' : '').'chacune décrite par Wikidata ou un article',
                ];

                continue;
            }
            $possible[$pair['keep']->id][] = $pair['other']->slug;
            $possible[$pair['other']->id][] = $pair['keep']->slug;
            $result->possibleDuplicates[] = $pair['keep']->slug.' / '.$pair['other']->slug;
        }

        return $possible;
    }

    /** Fiches « autre » : la nature que leur source dit. */
    private function reclassify(AccidentSourceClient $client, AccidentVerificationResult $result): void
    {
        $others = Accident::query()->withoutGlobalScopes()->whereNull('alias_of_id')->where('kind', AccidentKind::Other->value)->with('sources')->orderBy('id')->get();
        if ($others->isEmpty()) {
            return;
        }
        $listKinds = $this->kinds->listKinds($client);
        if ($listKinds === []) {
            $result->warnings[] = 'Pages des listes de Wikipédia absentes du cache : la nature des puces de liste n\'a pas été relue.';
        }
        foreach ($others as $accident) {
            $found = $this->kinds->read($accident, $listKinds);
            if ($found === null) {
                continue;
            }
            $provenance = $accident->provenance ?? [];
            $provenance['kind'] = $found['source']->provider;
            $verification = $accident->verification ?? [];
            $verification['kind'] = ['value' => $found['kind']->value, 'from' => AccidentKind::Other->value, 'source' => [
                'provider' => $found['source']->provider, 'role' => $found['source']->role, 'url' => $found['source']->url,
                'retrieved_at' => $found['source']->retrieved_at->toIso8601String(),
            ]];
            $notes = $accident->review_notes ?? [];
            $labels = (($accident->provenance['name'] ?? 'vff') === 'vff') ? [] : $accident->name;
            $accident->forceFill([
                'kind' => $found['kind']->value,
                'provenance' => $provenance,
                'verification' => $verification,
                'name' => $this->summary->names($labels, $found['kind'], $accident->commune_name),
                'summary' => $this->summary->write([
                    'date' => $accident->occurred_on->toDateString(),
                    'precision' => $accident->date_precision,
                    'kind' => $found['kind'],
                    'commune' => $accident->commune_name,
                    'department' => $accident->department_code,
                    'trains' => $accident->trains ?? [],
                    'deaths' => $accident->deaths,
                    'injuries' => $accident->injuries,
                    'toll_disputed' => array_any($notes, fn (array $n): bool => $n['code'] === 'toll_mismatch'),
                ]),
            ])->save();
            $result->reclassified[] = ['slug' => $accident->slug, 'from' => AccidentKind::Other->value, 'to' => $found['kind']->value];
        }
    }

    /**
     * Date et lieu d'une fiche comparés à ce que chacune de ses sources en dit ; statut, notes et trace de la
     * vérification mis à jour.
     *
     * @param  list<string>  $possibleDuplicates
     */
    private function verify(Accident $accident, array $possibleDuplicates, bool $demote, AccidentVerificationResult $result): void
    {
        $claims = $this->claims->read($accident);
        $date = $this->compare($claims, $accident, 'date');
        $place = $this->compare($claims, $accident, 'place');
        $deaths = $this->pressDeaths($claims, $accident);

        $rule = null;
        if (in_array(AccidentClaim::OFFICIAL, $date['families'], true) && in_array(AccidentClaim::OFFICIAL, $place['families'], true)
            && ! $this->officialDisagrees($date, $place)) {
            $rule = self::RULE_PRIMARY;
        } elseif (count($date['families']) >= 2 && count($place['families']) >= 2 && $date['disagree'] === [] && $place['disagree'] === []) {
            // Étiquette de chaque fait, puis la plus faible des deux : une source officielle qui ne
            // confirme que la date ne rend pas indépendantes les deux pages Wikimedia qui donnent le lieu.
            $rule = $this->weakestRule($date['families'], $place['families']);
        }

        $current = $accident->review_status;
        $notes = array_values(array_filter($accident->review_notes ?? [], fn (array $n): bool => ! in_array($n['code'], self::OWN_NOTES, true)));
        $own = [];
        $conflict = $date['disagree'] !== [] || $place['disagree'] !== [];
        if ($conflict && $rule !== self::RULE_PRIMARY) {
            $own[] = ['code' => 'verification_conflict', 'detail' => $this->conflictDetail($date['disagree'], $place['disagree'], $accident)];
        }
        foreach ($accident->aliases as $alias) {
            if ($accident->deaths !== null && $alias->deaths !== null && $accident->deaths !== $alias->deaths) {
                $own[] = ['code' => 'alias_toll_mismatch', 'detail' => ($accident->toll_source !== null ? self::PROVIDER_LABELS[$accident->toll_source] ?? $accident->toll_source : 'fiche').' '.$accident->deaths
                    .', autre élément '.($alias->wikidata_id ?? $alias->slug).' '.$alias->deaths];
            }
        }
        if ($possibleDuplicates !== [] && $current !== AccidentReviewStatus::Ok) {
            $own[] = ['code' => 'possible_duplicate', 'detail' => implode(', ', $possibleDuplicates)];
        }
        // Une ligne citée par la source mais retrouvée nulle part près du lieu : ligne ou lieu à vérifier.
        if ($accident->geom !== null && ! array_any($notes, fn (array $n): bool => $n['code'] === 'hinted_line_far')
            && AccidentLine::query()->where('accident_id', $accident->id)->whereNull('distance_m')->whereNotNull('cited_as')->exists()) {
            $notes[] = ['code' => 'hinted_line_far', 'detail' => 'ligne citée par la source, introuvable près du lieu lu'];
        }
        $notes = [...$notes, ...$own];

        $blocked = array_any($notes, fn (array $n): bool => in_array($n['code'], self::BLOCKING, true));
        $status = match (true) {
            $rule !== null && ! $blocked => AccidentReviewStatus::Ok,
            $current === AccidentReviewStatus::Ok && ($own !== [] || ($demote && $rule === null)) => AccidentReviewStatus::ToReview,
            default => $current,
        };

        $verification = array_filter([
            'rule' => $rule,
            'date' => ['value' => $accident->occurred_on->toDateString(), 'precision' => $accident->date_precision->value]
                + ['confirmed_by' => $date['confirm'], 'disagreeing' => $date['disagree']],
            'place' => array_filter(['commune_insee' => $accident->commune_insee, 'commune' => $accident->commune_name,
                'precision' => $accident->location_precision?->value, 'provenance' => $accident->provenance['location'] ?? null])
                + ['confirmed_by' => $place['confirm'], 'disagreeing' => $place['disagree']]
                + ($place['nearby'] !== [] ? ['nearby' => $place['nearby']] : []),
            'deaths' => $deaths,
            'families' => ['date' => $date['families'], 'place' => $place['families']],
            'kind' => $accident->verification['kind'] ?? null,
            'aliases' => $accident->aliases->map(fn (Accident $a): array => [
                'wikidata_id' => $a->wikidata_id,
                'slug' => $a->slug,
                'deaths' => $a->deaths,
                'sources' => $a->sources->map(fn ($s): array => ['provider' => $s->provider, 'role' => $s->role, 'url' => $s->url, 'retrieved_at' => $s->retrieved_at->toIso8601String()])->all(),
            ])->sortBy('slug')->values()->all() ?: null,
        ], fn ($v) => $v !== null);

        $changed = $this->normalize($accident->verification ?? []) !== $this->normalize($verification) || $status !== $current
            || $this->normalize($accident->review_notes ?? []) !== $this->normalize($notes);
        if ($changed) {
            $accident->forceFill([
                'verification' => $verification,
                'review_notes' => $notes !== [] ? $notes : null,
                'review_status' => $status->value,
                'verified_at' => now(),
            ])->save();
        }

        if ($status === AccidentReviewStatus::Ok && $current !== AccidentReviewStatus::Ok) {
            $result->promoted[$accident->slug] = (string) $rule;
        } elseif ($status === AccidentReviewStatus::ToReview && $current === AccidentReviewStatus::Ok) {
            $result->demoted[] = $accident->slug;
        } elseif ($status === AccidentReviewStatus::Ok && $rule === null) {
            $result->unconfirmedOk[] = $accident->slug;
        }
        if ($status === AccidentReviewStatus::Ok) {
            $result->count('rules', $rule ?? 'none');
            if (in_array(AccidentClaim::PRESS, $date['families'], true)) {
                $result->pressConfirmed[] = $accident->slug;
            }
        }
    }

    /**
     * Ce que les sources disent d'un fait de la fiche : celles qui concordent (avec leur famille), celles
     * qui divergent (une valeur lue avec certitude, mais autre).
     *
     * @param  list<AccidentClaim>  $claims
     *                                       Une commune citée à moins de NEARBY_COMMUNE_M du point de la fiche (gare voisine, hameau) ne confirme
     *                                       rien et ne contredit rien : elle est gardée à part (`nearby`).
     * @return array{confirm: list<array<string, mixed>>, disagree: list<array<string, mixed>>, nearby: list<array<string, mixed>>, families: list<string>}
     */
    private function compare(array $claims, Accident $accident, string $fact): array
    {
        $confirm = [];
        $disagree = [];
        $nearby = [];
        $families = [];
        foreach ($claims as $claim) {
            if ($fact === 'date') {
                if ($claim->date === null || $claim->datePrecision === null) {
                    continue;
                }
                $agrees = $this->sameDate($accident->occurred_on->toDateString(), $accident->date_precision, $claim->date, $claim->datePrecision);
                // Une source moins précise que la fiche (l'année seule pour une date au jour) ne la
                // confirme pas ; elle la contredit encore si elle donne une autre année ou un autre mois.
                if ($agrees && $claim->datePrecision->rank() < $accident->date_precision->rank()) {
                    continue;
                }
                $family = $claim->dateFamily;
            } else {
                if ($claim->communeInsee === null || $accident->commune_insee === null) {
                    continue;
                }
                $agrees = $claim->communeInsee === $accident->commune_insee;
                $family = $claim->placeFamily;
            }
            // Un article de presse qui ne confirme pas les trois faits à la fois ne confirme rien.
            if ($agrees && $family === AccidentClaim::PRESS && ! $this->pressConfirms($claim, $accident)) {
                continue;
            }
            if ($agrees) {
                $confirm[] = $claim->reference() + ['family' => $family];
                $families[$family] = true;
            } elseif ($fact === 'place' && $this->communeIsNear($accident, (string) $claim->communeInsee)) {
                $nearby[] = $claim->reference() + ['family' => $family];
            } else {
                $disagree[] = $claim->reference() + ['family' => $family];
            }
        }

        return ['confirm' => $confirm, 'disagree' => $disagree, 'nearby' => $nearby, 'families' => array_keys($families)];
    }

    /**
     * Un article de presse confirme la fiche s'il en donne la date, la commune et le bilan : le même nombre de morts,
     * ou un bilan provisoire inférieur ou égal (les morts à l'hôpital viennent ensuite).
     */
    private function pressConfirms(AccidentClaim $claim, Accident $accident): bool
    {
        return $claim->date !== null
            && $this->sameDate($accident->occurred_on->toDateString(), $accident->date_precision, $claim->date, AccidentDatePrecision::Day)
            && $claim->communeInsee !== null && $claim->communeInsee === $accident->commune_insee
            && $this->tollAgrees($claim, $accident);
    }

    private function tollAgrees(AccidentClaim $claim, Accident $accident): bool
    {
        if ($claim->deaths === null || $accident->deaths === null) {
            return false;
        }

        return $claim->deathsProvisional ? $claim->deaths <= $accident->deaths : $claim->deaths === $accident->deaths;
    }

    /**
     * Bilan de la fiche comparé à celui des articles de presse qui en donnent un, nul quand aucun n'en donne.
     *
     * @param  list<AccidentClaim>  $claims
     * @return array{value: ?int, confirmed_by: list<array<string, mixed>>, disagreeing: list<array<string, mixed>>}|null
     */
    private function pressDeaths(array $claims, Accident $accident): ?array
    {
        $confirm = [];
        $disagree = [];
        foreach ($claims as $claim) {
            if ($claim->dateFamily !== AccidentClaim::PRESS || $claim->deaths === null) {
                continue;
            }
            if ($this->tollAgrees($claim, $accident)) {
                $confirm[] = $claim->reference() + ['family' => AccidentClaim::PRESS];
            } else {
                $disagree[] = $claim->reference() + ['family' => AccidentClaim::PRESS];
            }
        }

        return $confirm === [] && $disagree === [] ? null : ['value' => $accident->deaths, 'confirmed_by' => $confirm, 'disagreeing' => $disagree];
    }

    /** Le point de référence de la commune citée est-il à moins de NEARBY_COMMUNE_M du point de la fiche ? */
    private function communeIsNear(Accident $accident, string $insee): bool
    {
        $distance = DB::scalar(
            'SELECT MIN(ST_Distance(a.geom::geography, c.geom::geography)) FROM accidents a, communes c WHERE a.id = ? AND c.code_insee = ? AND a.geom IS NOT NULL',
            [$accident->id, $insee],
        );

        return $distance !== null && (float) $distance <= self::NEARBY_COMMUNE_M;
    }

    /**
     * Une source officielle qui dit autre chose que la fiche sur la date ou le lieu : la règle primaire
     * ne s'applique pas (la fiche a repris la valeur d'une autre source).
     *
     * @param  array{disagree: list<array<string, mixed>>}  $date
     * @param  array{disagree: list<array<string, mixed>>}  $place
     */
    private function officialDisagrees(array $date, array $place): bool
    {
        return array_any([...$date['disagree'], ...$place['disagree']], fn (array $c): bool => ($c['family'] ?? null) === AccidentClaim::OFFICIAL);
    }

    /**
     * Nom de la règle « deux sources » : la plus faible des deux (date, lieu). Une source officielle qui ne confirme
     * que la date ne rend pas plus indépendantes les pages Wikimedia qui donnent le lieu.
     *
     * @param  list<string>  $dateFamilies
     * @param  list<string>  $placeFamilies
     */
    private function weakestRule(array $dateFamilies, array $placeFamilies): string
    {
        $rank = fn (array $families): int => match (true) {
            $this->isWikimediaPair($families) => 0,
            array_diff($families, [AccidentClaim::WIKIPEDIA, AccidentClaim::WIKIDATA_BARE, AccidentClaim::WIKIPEDIA_EDITION]) === [] => 1,
            default => 2,
        };

        return [self::RULE_WIKIMEDIA_PAIR, self::RULE_EDITIONS, self::RULE_TWO_SOURCES][min($rank($dateFamilies), $rank($placeFamilies))];
    }

    /**
     * Familles qui confirment un fait : seulement Wikipédia et Wikidata sans référence, dont l'une peut être
     * copiée de l'autre.
     *
     * @param  list<string>  $families
     */
    private function isWikimediaPair(array $families): bool
    {
        return array_diff($families, [AccidentClaim::WIKIPEDIA, AccidentClaim::WIKIDATA_BARE]) === [];
    }

    /** Deux dates concordent au jour, au mois ou à l'année selon la moins précise des deux. */
    private function sameDate(string $a, AccidentDatePrecision $pa, string $b, AccidentDatePrecision $pb): bool
    {
        return match (min($pa->rank(), $pb->rank())) {
            AccidentDatePrecision::Day->rank() => $a === $b,
            AccidentDatePrecision::Month->rank() => substr($a, 0, 7) === substr($b, 0, 7),
            default => substr($a, 0, 4) === substr($b, 0, 4),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $dates
     * @param  list<array<string, mixed>>  $places
     */
    private function conflictDetail(array $dates, array $places, Accident $accident): string
    {
        $parts = [];
        foreach ($dates as $c) {
            $parts[] = 'date '.($c['date'] ?? '?').' ('.(self::PROVIDER_LABELS[$c['provider']] ?? $c['provider']).') au lieu de '.$accident->occurred_on->toDateString();
        }
        foreach ($places as $c) {
            $parts[] = 'lieu '.($c['commune'] ?? '?').' ('.(self::PROVIDER_LABELS[$c['provider']] ?? $c['provider']).') au lieu de '.($accident->commune_name ?? '?');
        }

        return implode(' ; ', $parts);
    }

    /**
     * Forme comparable : jsonb ne garde pas l'ordre des clés ; les listes gardent le leur.
     *
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function normalize(array $value): array
    {
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $this->normalize($v);
            }
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
