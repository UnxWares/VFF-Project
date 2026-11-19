<?php

namespace App\Services\Accidents;

use App\Enums\AccidentDatePrecision;

/**
 * Ce qu'UNE source dit d'un accident : sa date, et son lieu quand il a pu être ramené à une commune
 * (par ses coordonnées, par le nom de commune qu'elle cite, ou par le code INSEE de Wikidata).
 * Une valeur que la source ne donne pas, ou qu'on n'a pas su lire avec certitude, reste nulle : elle
 * ne confirme ni ne contredit rien.
 */
final readonly class AccidentClaim
{
    /** Famille officielle : enquête du BEA-TT, source primaire. */
    public const OFFICIAL = 'official';

    /** Wikipédia (articles et listes d'accidents) : une seule famille, les pages se citent entre elles. */
    public const WIKIPEDIA = 'wikipedia';

    /** Wikidata, quand la déclaration lue cite une source qui n'est pas un import de Wikimedia. */
    public const WIKIDATA = 'wikidata';

    /** Wikidata, déclaration sans référence : pas un import déclaré, mais rien ne prouve non plus qu'elle ne vienne pas de Wikipédia. */
    public const WIKIDATA_BARE = 'wikidata-bare';

    public function __construct(
        public int $sourceId,
        public string $provider,
        public string $role,
        public string $dateFamily,
        public string $placeFamily,
        public string $url,
        public string $title,
        public string $retrievedAt,
        public ?string $date,
        public ?AccidentDatePrecision $datePrecision,
        public ?string $communeInsee,
        public ?string $communeName,
        public ?float $lat,
        public ?float $lng,
    ) {}

    /**
     * Référence gardée dans accidents.verification : qui dit quoi, où le relire et quand cela a été lu.
     *
     * @return array<string, mixed>
     */
    public function reference(): array
    {
        return array_filter([
            'provider' => $this->provider,
            'role' => $this->role,
            'url' => $this->url,
            'date' => $this->date,
            'commune_insee' => $this->communeInsee,
            'commune' => $this->communeName,
            'lat' => $this->lat !== null ? round($this->lat, 5) : null,
            'lng' => $this->lng !== null ? round($this->lng, 5) : null,
            'retrieved_at' => $this->retrievedAt,
        ], fn ($v) => $v !== null);
    }
}
