<?php

namespace App\Services\Accidents;

use App\Enums\AccidentDatePrecision;
use App\Models\Accident;
use App\Models\AccidentSource;
use App\Services\PlaceNames;
use Illuminate\Support\Facades\DB;

/**
 * Relit chaque source d'une fiche pour en tirer ce qu'ELLE dit de la date et du lieu, sans passer par la
 * fiche fusionnée (qui mêle les sources) : les faits gardés dans accident_sources.facts, ramenés à une
 * commune quand c'est possible.
 *
 *   - Wikidata et articles de Wikipédia : coordonnées (commune dont le contour les contient), à défaut le
 *     code INSEE de Wikidata ;
 *   - listes de Wikipédia : nom de commune cité et département, retrouvés dans la table des communes
 *     (une seule commune de ce nom, jamais un choix au hasard) ;
 *   - BEA-TT : commune et département de l'intitulé de l'enquête, retrouvés de la même façon.
 *
 * Famille d'une source (AccidentClaim), pour la date et pour le lieu : le BEA-TT est officiel ; les
 * articles et listes de Wikipédia forment une seule famille ; Wikidata en forme une autre, sauf pour une
 * déclaration (P585 pour la date, P625 ou P131 pour le lieu) importée de Wikimedia (référence « importé
 * depuis », P143) ou d'un élément absent du cache : elle compte avec Wikipédia, dont elle n'est qu'une copie.
 * Une déclaration sans référence reste une famille à part mais est marquée (WIKIDATA_BARE) : elle ne
 * prouve pas son indépendance.
 */
final class AccidentClaimReader
{
    /** Au-delà de cette distance au contour, un point n'est pas dans la commune (marge de tracé). */
    private const OUTSIDE_COMMUNE_M = 100;

    /** @var array<string, ?object> */
    private array $byName = [];

    private readonly string $cacheDir;

    /** @param ?string $cacheDir cache des sources (par défaut storage/app/data/accidents) */
    public function __construct(
        private readonly AccidentLocator $locator,
        private readonly BeaTtTitleReader $titles,
        ?string $cacheDir = null,
    ) {
        $this->cacheDir = $cacheDir ?? AccidentSourceClient::defaultCacheDir();
    }

    /** @return list<AccidentClaim> */
    public function read(Accident $accident): array
    {
        $claims = [];
        foreach ($accident->sources as $source) {
            $facts = $source->facts ?? [];
            $precision = isset($facts['date_precision']) ? AccidentDatePrecision::tryFrom((string) $facts['date_precision']) : AccidentDatePrecision::Day;
            $date = isset($facts['date']) && is_string($facts['date']) ? $facts['date'] : null;
            [$commune, $lat, $lng] = $this->place($source, $facts);
            $claims[] = new AccidentClaim(
                sourceId: $source->id,
                provider: $source->provider,
                role: $source->role,
                dateFamily: $this->family($source, 'P585'),
                placeFamily: $this->family($source, $lat !== null ? 'P625' : 'P131'),
                url: $source->url,
                title: $source->title,
                retrievedAt: $source->retrieved_at->toIso8601String(),
                date: $date,
                datePrecision: $date !== null ? ($precision ?? AccidentDatePrecision::Day) : null,
                communeInsee: $commune?->code_insee,
                communeName: $commune?->nom,
                lat: $lat,
                lng: $lng,
            );
        }

        return $claims;
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return array{0: ?object, 1: ?float, 2: ?float} commune, et coordonnées quand la source en donne
     */
    private function place(AccidentSource $source, array $facts): array
    {
        $lat = isset($facts['lat']) ? (float) $facts['lat'] : null;
        $lng = isset($facts['lng']) ? (float) $facts['lng'] : null;
        if ($lat !== null && $lng !== null) {
            $commune = $this->locator->communeAt($lat, $lng);

            return [$commune !== null && (float) $commune->distance_m <= self::OUTSIDE_COMMUNE_M ? $commune : null, $lat, $lng];
        }
        if (isset($facts['commune_insee'])) {
            return [DB::selectOne('SELECT code_insee, nom FROM communes WHERE code_insee = ? ORDER BY (type = \'commune-actuelle\') DESC LIMIT 1', [(string) $facts['commune_insee']]), null, null];
        }
        if ($source->provider === 'bea-tt') {
            $read = $this->titles->read($source->title, (string) ($facts['mode'] ?? ''));

            return [$read['place'] !== null ? $this->communeByName($read['place'], $read['department']) : null, null, null];
        }
        if ($source->role === 'list' && isset($facts['place'])) {
            return [$this->communeByName((string) $facts['place'], isset($facts['department']) ? (string) $facts['department'] : null), null, null];
        }

        return [null, null, null];
    }

    /** La seule commune de ce nom (dans le département quand il est connu), sinon rien. */
    private function communeByName(string $name, ?string $department): ?object
    {
        $key = PlaceNames::normalize($name).'|'.$department;
        if (! array_key_exists($key, $this->byName)) {
            $rows = DB::select(
                'SELECT code_insee, nom FROM communes WHERE nom_norm = ? AND (?::text IS NULL OR departement = ?) ORDER BY (type = \'commune-actuelle\') DESC',
                [PlaceNames::normalize($name), $department, $department],
            );
            $codes = array_unique(array_map(fn ($r) => $r->code_insee, $rows));
            $this->byName[$key] = count($codes) === 1 ? $rows[0] : null;
        }

        return $this->byName[$key];
    }

    private function family(AccidentSource $source, string $property): string
    {
        return match ($source->provider) {
            'bea-tt' => AccidentClaim::OFFICIAL,
            'wikidata' => $this->wikidataFamily((string) ($source->external_id ?? ''), $property),
            default => AccidentClaim::WIKIPEDIA,
        };
    }

    /**
     * Famille d'une déclaration Wikidata d'après ses références : une déclaration importée de Wikimedia (P143,
     * P4656) ou absente du cache compte avec Wikipédia ; sinon, sourcée si chaque déclaration cite autre chose,
     * sans référence dans le cas contraire (P813 « date de consultation » ne compte pas comme source).
     */
    private function wikidataFamily(string $qid, string $property): string
    {
        $path = $this->cacheDir.'/wikidata/entities/'.$qid.'.json';
        $entity = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $statements = is_array($entity) ? ($entity['claims'][$property] ?? []) : [];
        if ($statements === []) {
            return AccidentClaim::WIKIPEDIA;
        }
        $snaks = fn (array $statement): array => array_merge([], ...array_map(fn (array $r): array => array_keys($r['snaks'] ?? []), $statement['references'] ?? []));
        if (array_any($statements, fn (array $st): bool => array_intersect($snaks($st), ['P143', 'P4656']) !== [])) {
            return AccidentClaim::WIKIPEDIA;
        }

        return array_all($statements, fn (array $st): bool => array_diff($snaks($st), ['P813']) !== [])
            ? AccidentClaim::WIKIDATA : AccidentClaim::WIKIDATA_BARE;
    }
}
