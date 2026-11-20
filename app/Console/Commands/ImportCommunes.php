<?php

namespace App\Console\Commands;

use App\Services\PlaceNames;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Importe le gazetteer des communes (table `communes`) depuis l'API
 * Découpage administratif (geo.api.gouv.fr ; jeu « Contours administratifs » d'Etalab sous ODbL,
 * https://www.data.gouv.fr/datasets/contours-administratifs, vérifié le 2026-10-20).
 *
 * Pourquoi : vff:link-traces a besoin de situer les toponymes des libellés
 * (« Carhaix Morlaix ») pour vérifier qu'un tracé passe bien par les lieux
 * qu'il nomme, et de nommer les tracés anonymes d'après la commune la plus
 * proche. Les anciennes communes associées/déléguées sont incluses : les gares
 * historiques portent souvent leur nom.
 *
 * Idempotent (upsert sur code INSEE + type). Copie brute datée dans
 * storage/app/sources/geo-api/.
 *
 *   php artisan vff:import-communes
 */
class ImportCommunes extends Command
{
    protected $signature = 'vff:import-communes';

    protected $description = 'Importe les communes (actuelles + associées/déléguées) dans la table communes (gazetteer).';

    private const BASE = 'https://geo.api.gouv.fr';

    public function handle(): int
    {
        $actuelles = $this->fetch('/communes?fields=nom,code,centre,codeDepartement,population&format=json', 'communes');
        $anciennes = $this->fetch('/communes_associees_deleguees?fields=nom,code,type,centre,chefLieu,codeDepartement&format=json', 'communes-associees-deleguees');

        $rows = [];
        foreach ($actuelles as $c) {
            $rows[] = $this->row($c, 'commune-actuelle');
        }
        foreach ($anciennes as $c) {
            $rows[] = $this->row($c, $c['type'] ?? 'commune-deleguee');
        }
        $rows = array_filter($rows);

        $n = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            $values = [];
            $bindings = [];
            foreach ($chunk as $r) {
                $values[] = '(?, ?, ?, ?, ?, ?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326), NOW(), NOW())';
                array_push($bindings, $r['code'], $r['type'], $r['nom'], $r['nom_norm'], $r['dep'], $r['pop'], $r['chef'], $r['lon'], $r['lat']);
            }
            DB::statement('INSERT INTO communes (code_insee, type, nom, nom_norm, departement, population, chef_lieu, geom, created_at, updated_at)
                VALUES '.implode(',', $values).'
                ON CONFLICT (code_insee, type) DO UPDATE SET nom = EXCLUDED.nom, nom_norm = EXCLUDED.nom_norm,
                    departement = EXCLUDED.departement, population = EXCLUDED.population, chef_lieu = EXCLUDED.chef_lieu,
                    geom = EXCLUDED.geom, updated_at = NOW()', $bindings);
            $n += count($chunk);
        }

        $this->info("✓ {$n} communes en base (".count($actuelles).' actuelles, '.count($anciennes).' associées/déléguées).');

        return self::SUCCESS;
    }

    /** @return list<array<string, mixed>> */
    private function fetch(string $path, string $name): array
    {
        $this->line("→ {$name}…");
        $body = Http::withHeaders(['User-Agent' => 'VFF-Bot/1.0 (+https://vff-project.org)'])
            ->timeout(180)->get(self::BASE.$path)->throw()->body();
        Storage::disk('local')->put("sources/geo-api/{$name}-".now()->format('Y-m-d').'.json', $body);

        return json_decode($body, true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed>|null */
    private function row(array $c, string $type): ?array
    {
        $coords = $c['centre']['coordinates'] ?? null;
        if (! $coords || empty($c['code']) || empty($c['nom'])) {
            return null;
        }

        return [
            'code' => $c['code'], 'type' => $type, 'nom' => $c['nom'],
            'nom_norm' => PlaceNames::normalize($c['nom']), 'dep' => $c['codeDepartement'] ?? null,
            'pop' => $c['population'] ?? null, 'chef' => $c['chefLieu'] ?? null,
            'lon' => $coords[0], 'lat' => $coords[1],
        ];
    }
}
