<?php

namespace App\Services\Materials;

use App\Enums\MaterialType;

/**
 * Règles de lecture du matériel roulant dans Wikidata (database/data/materiel_wikidata.php) :
 * périmètre, catégorie VFF d'après les classes Wikidata, conversions d'unités.
 */
final readonly class WikidataMaterialRules
{
    /**
     * @param  array{
     *     rolling_stock_class: string, country: string,
     *     excluded_operators: array<string, string>, excluded_roots: array<string, string>,
     *     type_rules: list<array{0: string, 1: array<string, string>}>,
     *     category_rules: list<array{0: string, 1: string}>,
     *     speed_units: array<string, float>, power_units: array<string, float>, millimetre: string,
     *     service_status_values: array<string, string>, fleet_min_year: int,
     *     bimode_needs_electric: list<string>, electric_classes: list<string>
     * }  $config
     */
    public function __construct(private array $config) {}

    public static function default(): self
    {
        return new self(require database_path('data/materiel_wikidata.php'));
    }

    public function rollingStockClass(): string
    {
        return $this->config['rolling_stock_class'];
    }

    public function country(): string
    {
        return $this->config['country'];
    }

    public function millimetre(): string
    {
        return $this->config['millimetre'];
    }

    /** @return list<string> toutes les classes que les règles consultent (catégories et exclusions) */
    public function roots(): array
    {
        $roots = array_keys($this->config['excluded_roots']);
        foreach ($this->config['type_rules'] as [, $classes]) {
            $roots = [...$roots, ...array_keys($classes)];
        }

        return array_values(array_unique($roots));
    }

    /**
     * Une série dont TOUS les exploitants sont hors de France n'a pas circulé en France.
     *
     * @param  list<string>  $operators  identifiants Wikidata des exploitants
     */
    public function onlyForeignOperators(array $operators): bool
    {
        return $operators !== [] && array_diff($operators, array_keys($this->config['excluded_operators'])) === [];
    }

    /**
     * Classe écartée (métro) parmi les ancêtres de la série, ou null.
     *
     * @param  list<string>  $ancestors
     */
    public function excludedRoot(array $ancestors): ?string
    {
        foreach ($ancestors as $qid) {
            if (isset($this->config['excluded_roots'][$qid])) {
                return $qid;
            }
        }

        return null;
    }

    /**
     * Catégorie VFF : première règle dont une classe figure parmi les ancêtres de la série.
     *
     * @param  list<string>  $ancestors
     * @return array{0: MaterialType, 1: string}|null catégorie et classe Wikidata qui l'a décidée
     */
    public function typeFor(array $ancestors): ?array
    {
        $set = array_flip($ancestors);
        $electric = array_intersect_key($set, array_flip($this->config['electric_classes'])) !== [];
        foreach ($this->config['type_rules'] as [$type, $classes]) {
            foreach (array_keys($classes) as $qid) {
                // Une classe « bimode » ambiguë ne compte que pour une série qui descend aussi d'une classe électrique.
                if (isset($set[$qid]) && ! ($type === MaterialType::Bimode->value && ! $electric && in_array($qid, $this->config['bimode_needs_electric'], true))) {
                    return [MaterialType::from($type), $qid];
                }
            }
        }

        return null;
    }

    /**
     * Repli sur les catégories de l'article Wikipédia : catégorie VFF (ou `exclude` pour le
     * métro) et catégorie Wikipédia qui l'a décidée.
     *
     * @param  list<string>  $categories  noms sans le préfixe « Catégorie: »
     * @return array{0: MaterialType|null, 1: string}|null null : aucune règle ne s'applique ; type null : matériel écarté
     */
    public function typeFromCategories(array $categories): ?array
    {
        foreach ($this->config['category_rules'] as [$type, $pattern]) {
            foreach ($categories as $category) {
                if (preg_match($pattern, $category) === 1) {
                    return [$type === 'exclude' ? null : MaterialType::from($type), $category];
                }
            }
        }

        return null;
    }

    /** État VFF (`in_service`, `withdrawn`) d'une valeur de P5817, nul si la valeur n'est pas reconnue. */
    public function serviceStatus(string $value): ?string
    {
        return $this->config['service_status_values'][$value] ?? null;
    }

    /** Année avant laquelle un effectif cité par une infobox n'atteste plus la situation actuelle. */
    public function fleetMinYear(): int
    {
        return $this->config['fleet_min_year'];
    }

    /** Vitesse en km/h arrondie, ou null si l'unité est inconnue ou la valeur absurde. */
    public function speedKmh(float $amount, string $unit): ?int
    {
        $factor = $this->config['speed_units'][$unit] ?? null;
        $kmh = $factor !== null ? (int) round($amount * $factor) : null;

        return $kmh !== null && $kmh > 0 && $kmh < 1000 ? $kmh : null;
    }

    /** Puissance en kW arrondie, ou null si l'unité est inconnue ou la valeur absurde. */
    public function powerKw(float $amount, string $unit): ?int
    {
        $factor = $this->config['power_units'][$unit] ?? null;
        $kw = $factor !== null ? (int) round($amount * $factor) : null;

        return $kw !== null && $kw > 0 && $kw < 100_000 ? $kw : null;
    }
}
