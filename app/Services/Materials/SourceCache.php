<?php

namespace App\Services\Materials;

use Illuminate\Support\Facades\File;

/**
 * Cache local des réponses Wikidata, Commons et Wikipédia de l'import du matériel roulant, dans
 * storage/app/data/materiel/ (dossier marqué `.nobackup` : contenu régénérable).
 *
 * Chaque étape garde une table « clé → valeur » dans un fichier JSON : un nouvel appel ne demande
 * aux serveurs que les clés absentes, et `--refresh` repart de zéro. Les relances de l'import sont
 * ainsi quasi gratuites pour les sources. Les tests remplacent ce stockage (load/save) par un
 * tableau en mémoire.
 */
class SourceCache
{
    public function __construct(private readonly string $directory) {}

    public static function default(): self
    {
        return new self(storage_path('app/data/materiel'));
    }

    /** @return array<string, mixed> table enregistrée (vide si absente ou illisible) */
    public function load(string $name): array
    {
        $path = $this->path($name);
        if (! is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $data */
    public function save(string $name, array $data): void
    {
        File::ensureDirectoryExists($this->directory);
        if (! is_file($this->directory.'/.nobackup')) {
            File::put($this->directory.'/.nobackup', '');
        }
        File::put($this->path($name), (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Complète la table `$name` pour les clés manquantes, par lots, et l'enregistre après chaque lot
     * (une coupure ne perd que le lot en cours).
     *
     * @param  list<string>  $keys
     * @param  callable(list<string>): array<string, mixed>  $fetch  renvoie les valeurs du lot, par clé
     * @return array<string, mixed> valeurs des clés demandées (une clé que la source ignore vaut null)
     */
    public function fill(string $name, array $keys, int $batch, callable $fetch, bool $refresh = false): array
    {
        $table = $refresh ? [] : $this->load($name);
        $missing = array_values(array_filter(array_unique($keys), fn (string $k): bool => ! array_key_exists($k, $table)));
        foreach (array_chunk($missing, max(1, $batch)) as $chunk) {
            $values = $fetch($chunk);
            foreach ($chunk as $key) {
                $table[$key] = $values[$key] ?? null;
            }
            $this->save($name, $table);
        }

        return array_intersect_key($table, array_flip($keys));
    }

    private function path(string $name): string
    {
        return $this->directory.'/'.preg_replace('/[^a-z0-9_-]/i', '_', $name).'.json';
    }
}
