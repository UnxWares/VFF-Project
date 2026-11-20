<?php

namespace App\Console\Commands;

use App\Services\ArgentanWebFerro\ArgentanClient;
use App\Services\ArgentanWebFerro\GalleryInventory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

/**
 * Photos LIBRES d'Argentan Web Ferroviaire (argentanwebferro_photos, republishable = vrai :
 * œuvres de l'auteur sous CC BY-SA 4.0) → table media, en COPIE LOCALE.
 *
 * Pourquoi une copie et pas un lien direct : l'hébergement de l'auteur est un serveur mutualisé
 * personnel ; afficher ses images en lien direct sur les fiches de VFF ferait porter le trafic
 * de VFF à son serveur sans son accord. On copie une seule fois, poliment (1 requête / 2,5 s),
 * la version 1024 px que la galerie publie déjà, et VFF la sert lui-même avec auteur, licence
 * et lien vers la page de la photo.
 *
 * Par défaut : SIMULATION (aucun téléchargement, aucune écriture) et chiffrage (nombre, volume,
 * durée). --apply copie et insère : à ne lancer qu'avec le feu vert de l'utilisateur.
 *
 *   vff:import-argentanwebferro-media            # chiffrage seulement
 *   vff:import-argentanwebferro-media --apply    # copie + insertion dans media (feu vert requis)
 */
class ImportArgentanWebFerroMedia extends Command
{
    protected $signature = 'vff:import-argentanwebferro-media
        {--apply : Copie les fichiers et insère dans media (feu vert de l\'utilisateur requis)}
        {--limit= : Nombre maximal de photos traitées}';

    protected $description = 'Photos libres d\'Argentan Web Ferroviaire → media (copie locale 1024 px, crédit et licence) ; simulation par défaut';

    /** Taille moyenne d'une image 1024 px de la galerie, mesurée sur 6 photos le 2026-11-19 (146 à 388 Ko). */
    private const AVERAGE_KB = 300;

    private const DELAY_MS = 2500;

    /** Dossier des copies sur le disque public (servi sous /storage par le lien public/storage). */
    public const DIRECTORY = 'media/argentanwebferro';

    public function handle(): int
    {
        $query = DB::table('argentanwebferro_photos')->where('republishable', true)->whereNotNull('media_payload')->orderBy('piwigo_id');
        if ($this->option('limit')) {
            $query->limit((int) $this->option('limit'));
        }
        $rows = $query->get();
        $attached = $rows->filter(fn ($r) => json_decode((string) $r->media_payload, true)['mediable_id'] !== null);
        $count = $attached->count();
        $mb = round($count * self::AVERAGE_KB / 1024, 1);
        $minutes = (int) ceil($count * self::DELAY_MS / 60000);
        $this->info("{$rows->count()} photo(s) republiable(s), {$count} rattachée(s) à une ligne ou une gare.");
        $this->line("Chiffrage : {$count} téléchargement(s), environ {$mb} Mo copiés dans storage/app/public/media/argentanwebferro, environ {$minutes} min à 1 requête / 2,5 s.");

        if (! $this->option('apply')) {
            $this->line('Simulation : rien n\'est téléchargé ni inséré. --apply après le feu vert de l\'utilisateur.');

            return self::SUCCESS;
        }

        $disk = Storage::disk('public');
        // Copies régénérables (la commande peut les retélécharger) : hors sauvegardes, hors git (storage/app/public/.gitignore).
        if (! $disk->exists(self::DIRECTORY.'/.nobackup')) {
            $disk->put(self::DIRECTORY.'/.nobackup', '');
        }
        $done = 0;
        $bytes = 0;
        $last = 0.0;
        foreach ($attached as $row) {
            $payload = json_decode((string) $row->media_payload, true);
            $path = (string) $payload['file_path'];
            if (! $disk->exists($path)) {
                $wait = $last + self::DELAY_MS / 1000 - microtime(true);
                if ($wait > 0) {
                    Sleep::for((int) ceil($wait * 1000))->milliseconds();
                }
                $response = Http::withHeaders(['User-Agent' => ArgentanClient::USER_AGENT])->timeout(60)->get((string) $row->display_source_url);
                $last = microtime(true);
                if (! $response->successful() || ! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
                    $this->warn("Photo {$row->piwigo_id} : téléchargement impossible ({$response->status()})");

                    continue;
                }
                $disk->put($path, $response->body());
            }
            $bytes += $disk->size($path);
            // thumb_url et display_url restent vides : l'API les calcule depuis file_path (Storage::url), quel que soit le domaine.
            DB::table('media')->upsert([[
                ...array_diff_key($payload, ['provider_meta' => true]),
                'thumb_url' => null, 'display_url' => null,
                'mime_type' => 'image/jpeg', 'file_size' => $disk->size($path),
                'provider_meta' => json_encode($payload['provider_meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(), 'updated_at' => now(),
            ]], ['provider', 'external_id', 'mediable_type', 'mediable_id']);
            $done++;
        }
        $this->info("{$done} photo(s) copiée(s) et insérée(s) dans media (provider = ".GalleryInventory::PROVIDER.'), '
            .round($bytes / 1048576, 1).' Mo dans storage/app/public/'.self::DIRECTORY.'.');

        return self::SUCCESS;
    }
}
