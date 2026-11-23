<?php

namespace App\Services\Timetable;

/**
 * Comment lire un flux GTFS pour la desserte d'un jour type : lignes ferroviaires gardées, type de train de chaque course.
 */
final readonly class TimetableProfile
{
    /** Types de train rendus par l'API (clés stables). */
    public const TYPES = ['ter', 'intercites', 'tgv', 'transilien', 'other'];

    /** Produit lu dans l'identifiant d'arrêt SNCF (« StopPoint:OCETGV INOUI-87571000 ») => type ; les cars ne sont pas listés. */
    public const SNCF_PRODUCTS = [
        'Train TER' => 'ter', 'TramTrain' => 'ter',
        'TGV INOUI' => 'tgv', 'OUIGO' => 'tgv', 'ICE' => 'tgv', 'Lyria' => 'tgv',
        'INTERCITES' => 'intercites', 'INTERCITES de nuit' => 'intercites',
        'Navette' => 'other', 'Train' => 'other',
    ];

    /**
     * @param  list<int>  $routeTypes  route_type gardés (0 tramway, 2 train)
     * @param  list<string>|null  $agencies  agency_id gardés (null : tous)
     * @param  string|null  $productPattern  expression du produit dans stop_id (type fixe si null)
     */
    public function __construct(
        public string $key,
        public array $routeTypes,
        public ?array $agencies = null,
        public ?string $productPattern = null,
        public string $fixedType = 'other',
    ) {}

    public static function sncf(): self
    {
        return new self('sncf', [0, 2], null, '/^StopPoint:OCE(.+)-\d+$/');
    }

    /** Transilien et RER (agences IDFM:1046 et IDFM:71) : les TER d'Île-de-France sont déjà dans le flux SNCF. */
    public static function transilien(): self
    {
        return new self('transilien', [2], ['IDFM:1046', 'IDFM:71'], null, 'transilien');
    }

    /** Trains régionaux d'une autre autorité que SNCF (ZOU !, Transdev Rail Sud, Chemins de fer de la Corse) : type TER. */
    public static function regional(string $key): self
    {
        return new self($key, [0, 2], null, null, 'ter');
    }

    /** Type de la course d'après l'arrêt de sa première ligne ; null pour un car ou un produit inconnu. */
    public function typeOfStop(string $stopId): ?string
    {
        if ($this->productPattern === null) {
            return $this->fixedType;
        }

        return preg_match($this->productPattern, $stopId, $m) === 1 ? (self::SNCF_PRODUCTS[$m[1]] ?? null) : null;
    }
}
