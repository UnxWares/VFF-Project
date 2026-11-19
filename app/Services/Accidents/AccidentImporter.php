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
        'no_location', 'no_line', 'line_uncertain', 'hinted_line_far',
    ];

    /** Éléments rangés parmi les accidents qui n'en sont pas (vérifiés à la main). */
    public const EXCLUDED_ITEMS = [
        // Affaire de Tarnac : affaire judiciaire (sabotages de caténaires de 2008), pas un accident.
        'Q60504015' => true,
    ];

    /** Ordre de préférence des sources pour le bilan : l'enquête officielle d'abord. */
    private const DEATHS_PRIORITY = ['bea-tt', 'wikidata', 'wikipedia'];

    private const INJURIES_PRIORITY = ['wikidata', 'wikipedia'];

    /** Deux fiches décrivent le même accident : dates à un jour près et lieux à moins de 20 km. */
    private const SAME_EVENT_MAX_M = 20000;

    /** … ou même commune et dates à une semaine près (divergence signalée). */
    private const SAME_COMMUNE_MAX_DAYS = 7;

    public function __construct(
        private readonly WikidataAccidentCollector $wikidata,
        private readonly BeaTtAccidentCollector $beaTt,
        private readonly AccidentLocator $locator,
        private readonly AccidentLineMatcher $matcher,
        private readonly AccidentSummary $summary,
    ) {}

    /** @param list<string> $sources fournisseurs à lire : wikidata (avec Wikipédia), bea-tt */
    public function run(AccidentSourceClient $client, array $sources = ['wikidata', 'bea-tt'], bool $dryRun = false, bool $prune = false): AccidentImportResult
    {
        $result = new AccidentImportResult;
        $startedAt = now();

        $wd = in_array('wikidata', $sources, true) ? $this->wikidata->collect($client) : [];
        $bea = in_array('bea-tt', $sources, true) ? $this->beaTt->collect($client) : [];
        $result->collected = ['wikidata' => count($wd), 'bea-tt' => count($bea)];
        $result->rejected = ['wikidata' => $this->wikidata->rejected, 'bea-tt' => $this->beaTt->rejected];
        if ($this->beaTt->warning !== null) {
            $result->warnings[] = $this->beaTt->warning;
        }

        foreach ([...$wd, ...$bea] as $c) {
            $this->locator->locate($c);
        }
        [$candidates, $result->merged] = $this->merge($wd, $bea);

        $seen = [];
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
            $unseen = Accident::query()->whereNotIn('id', $seen)->where('imported_at', '<', $startedAt);
            $result->unseen = (clone $unseen)->pluck('slug')->all();
            // Retrait sur demande seulement, et jamais après un import partiel (une seule source lue).
            if ($prune && count($sources) === 2 && $client->missing === []) {
                $unseen->delete();
                $result->pruned = $result->unseen;
            }
        }
        $result->requests = $client->requests;
        $result->missing = $client->missing;

        return $result;
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
        if ($c->lat === null && $c->communeInsee === null && ! $c->declaredInFrance && ! str_starts_with($c->key, 'bea-tt:')) {
            return 'not_in_france';
        }

        return null;
    }

    /**
     * Attributs à écrire : bilan retenu, nom, résumé, rattachements, état de relecture.
     *
     * @return array{attributes: array<string, mixed>, links: list<array<string, mixed>>, station: ?array{id: int, distance_m: int}}
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
            'review_status' => ($review ? AccidentReviewStatus::ToReview : AccidentReviewStatus::Ok)->value,
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

        return ['attributes' => $attributes, 'links' => $links, 'station' => $station];
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
                $labels = ['bea-tt' => 'BEA-TT', 'wikidata' => 'Wikidata', 'wikipedia' => 'Wikipédia'];

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
        $out = [];
        foreach (AccidentSummary::LOCALES as $locale) {
            if (isset($c->labels[$locale])) {
                $out[$locale] = $c->labels[$locale];

                continue;
            }
            $kindName = __('accidents.names.kinds.'.$kind->value, [], $locale);
            $out[$locale] = $c->communeName !== null
                ? __('accidents.names.at_commune', ['kind' => $kindName, 'commune' => $c->communeName], $locale)
                : $kindName;
        }

        return $out;
    }

    /**
     * Écriture d'un accident, de ses rattachements et de ses sources (une transaction).
     *
     * @param  array{attributes: array<string, mixed>, links: list<array<string, mixed>>, station: ?array{id: int, distance_m: int}}  $prepared
     */
    private function persist(AccidentCandidate $c, array $prepared, AccidentImportResult $result): Accident
    {
        return DB::transaction(function () use ($c, $prepared, $result): Accident {
            $accident = $this->existing($c) ?? new Accident;
            $created = ! $accident->exists;
            $accident->fill($prepared['attributes'] + ['imported_at' => now()]);
            if ($created) {
                $accident->slug = $this->uniqueSlug($c, $prepared['attributes']['name']['fr']);
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
            AccidentSource::query()->where('accident_id', $accident->id)->whereNotIn('id', $keep)->delete();

            return $accident;
        });
    }

    /** Fiche existante : par l'élément Wikidata, sinon par l'identifiant d'une de ses sources. */
    private function existing(AccidentCandidate $c): ?Accident
    {
        if ($c->wikidataId !== null && ($a = Accident::query()->where('wikidata_id', $c->wikidataId)->first()) !== null) {
            return $a;
        }
        foreach ($c->sources as $s) {
            $id = AccidentSource::query()->where('provider', $s->provider)->where('external_id', $s->externalId)->value('accident_id');
            if ($id !== null && ($a = Accident::query()->find($id)) !== null
                && ($a->wikidata_id === null || $a->wikidata_id === $c->wikidataId)) {
                return $a;
            }
        }

        return null;
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
        if ($others <= 1) {
            $slug = Accident::query()->whereKey($oldId)->value('slug');
            Accident::query()->whereKey($oldId)->delete();
            $result->retired[] = "{$slug} → {$into->slug}";
        }
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
        for ($i = 2; Accident::query()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
