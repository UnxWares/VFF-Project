<?php

namespace App\Services;

/**
 * Cas légitimes écartés d'un invariant de DataInvariants (database/data/invariant_exceptions.php).
 *
 * Une exception n'est pas une correction : la donnée est juste (ou connue pour contredire sa voisine), c'est la règle de
 * l'invariant qui ne peut pas la comprendre (inauguration distincte de l'ouverture au public, halte ouverte après sa
 * section, ligne dont la fin est inconnue...). Chaque entrée porte sa justification, sa source et sa date de relecture.
 *
 * Une entrée est :
 *   - invariant : clé de DataInvariants::INVARIANTS (« stations.opened_before_lines », « lines.km_drop_without_event »...) ;
 *   - slug : slug de la gare (invariants « stations.* ») ou de la ligne (invariants « lines.* ») ;
 *   - reason : pourquoi le cas est légitime, faits seulement ;
 *   - source_url : lien https qui le montre (permalien de l'article, page de la source) ;
 *   - reviewed_at : date de la relecture (AAAA-MM-JJ).
 *
 * Une entrée mal formée, en double ou d'un invariant inconnu est refusée par validate() ; le test du fichier versionné échoue.
 */
final class InvariantExceptions
{
    /** @var list<array<string, mixed>> */
    private array $entries;

    /** @param  list<array<string, mixed>>|null  $entries  défaut : database/data/invariant_exceptions.php */
    public function __construct(?array $entries = null)
    {
        $this->entries = $entries ?? (is_file($f = database_path('data/invariant_exceptions.php')) ? require $f : []);
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->entries;
    }

    /**
     * Slugs écartés pour un invariant.
     *
     * @return list<string>
     */
    public function slugs(string $invariant): array
    {
        $slugs = [];
        foreach ($this->entries as $e) {
            if (($e['invariant'] ?? null) === $invariant && is_string($e['slug'] ?? null)) {
                $slugs[] = $e['slug'];
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * Erreurs de forme d'une entrée (vide si valide).
     *
     * @param  array<string, mixed>  $e
     * @return list<string>
     */
    public static function validate(array $e): array
    {
        $errors = [];
        foreach (['invariant', 'slug', 'reason', 'source_url', 'reviewed_at'] as $k) {
            if (! is_string($e[$k] ?? null) || trim($e[$k]) === '') {
                $errors[] = "{$k} manquant";
            }
        }
        if (is_string($e['invariant'] ?? null) && ! array_key_exists($e['invariant'], DataInvariants::INVARIANTS)) {
            $errors[] = 'invariant inconnu « '.$e['invariant'].' »';
        }
        if (is_string($e['slug'] ?? null) && preg_match('/^[a-z0-9][a-z0-9-]*$/', $e['slug']) !== 1) {
            $errors[] = 'slug mal formé « '.$e['slug'].' »';
        }
        if (is_string($e['source_url'] ?? null) && ! str_starts_with($e['source_url'], 'https://')) {
            $errors[] = 'source_url : lien https attendu';
        }
        if (is_string($e['reviewed_at'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $e['reviewed_at']) !== 1) {
            $errors[] = 'reviewed_at : date AAAA-MM-JJ attendue';
        }
        foreach (['reason', 'source_url'] as $k) {
            if (is_string($e[$k] ?? null) && preg_match('/\x{2014}| -- /u', $e[$k]) === 1) {
                $errors[] = "{$k} : pas de tiret long";
            }
        }

        return $errors;
    }

    /**
     * Erreurs de tout le jeu (formes et doublons invariant + slug).
     *
     * @return list<string>
     */
    public function errors(): array
    {
        $errors = [];
        $seen = [];
        foreach ($this->entries as $i => $e) {
            foreach (self::validate($e) as $m) {
                $errors[] = "entrée {$i} : {$m}";
            }
            $key = ($e['invariant'] ?? '').'|'.($e['slug'] ?? '');
            if (isset($seen[$key])) {
                $errors[] = "entrée {$i} : doublon de l'entrée {$seen[$key]} ({$key})";
            }
            $seen[$key] = $i;
        }

        return $errors;
    }
}
