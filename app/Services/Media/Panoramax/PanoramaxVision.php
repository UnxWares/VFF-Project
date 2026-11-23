<?php

namespace App\Services\Media\Panoramax;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Tri des photos de rue par vision (décision du 23/11) : une note « la gare est visible » (0 à 1) par vignette,
 * calculée hors de PHP par `scripts/panoramax-vision/run.sh` (CLIP ViT-B-16 DataComp, poids sous licence MIT) et gardée
 * dans `scores.json`. Cette classe télécharge les vignettes à noter (instance IGN seulement, une toutes les
 * PAUSE_MS, User-Agent de VFF) et relit les notes.
 *
 * Cache : `storage/app/data/panoramax/vision/` (`.nobackup`) : `thumbs/<id>.jpg` (environ 25 Ko chacune),
 * `scores.json`, `embeddings.npz` (vecteurs d'image, pour régler les descriptions sans relire les images),
 * `a-noter.txt` (photos candidates sans note au dernier passage).
 *
 * Réglage (rapport aprem-2111/panoramax-vision.md) : seuils MIN_SCORE et MIN_SCORE_SCENE, réglés sur 250 photos
 * étiquetées à la main et mesurés sur 100 autres.
 */
class PanoramaxVision
{
    public const PAUSE_MS = 2000;

    /**
     * Instances dont les vignettes peuvent être lues par le tri : l'IGN seulement (le robots.txt de
     * panoramax.openstreetmap.fr refuse les robots d'IA).
     */
    public const INSTANCES = ['ign'];

    /**
     * Note minimale d'une photo retenue (descriptions v3 de score.py). Réglage du 23/11 sur 250 photos étiquetées à
     * la main (150 candidates, graine 2311 ; 100 photos retenues par les descriptions v2, graine 2312) : à 0,7, une
     * photo hors sujet sur 43 retenues, 33 photos de gare sur 46 gardées. Mesure sur un troisième échantillon
     * (rapport aprem-2111/panoramax-vision.md).
     */
    public const MIN_SCORE = 0.7;

    /**
     * Note minimale d'une photo qui passe aussi la règle des abords (bâtiment de la gare dans le champ). Égale à
     * MIN_SCORE : sur l'échantillon de réglage, la règle des abords ne séparait pas mieux les photos que la note
     * seule (9 photos : 2 de gare, 6 douteuses, 1 hors sujet). Elle reste un bonus dans le classement.
     */
    public const MIN_SCORE_SCENE = 0.7;

    /** Seuil imposé par `--min-score` (null : MIN_SCORE). Jamais sous MIN_SCORE. */
    public ?float $minScore = null;

    /** @var ?array<string, float> */
    private ?array $scores = null;

    private float $lastCall = 0.0;

    /** Vignettes téléchargées (dernier passage). */
    public int $downloaded = 0;

    /** @var list<string> photos candidates sans note (dernier passage) */
    public array $unscored = [];

    public function __construct(
        private readonly ?string $dir = null,
        private readonly int $pauseMs = self::PAUSE_MS,
    ) {}

    /** Note de la photo, null si elle n'a pas encore été notée. */
    public function score(string $id): ?float
    {
        if ($this->scores === null) {
            $file = $this->dir().'/scores.json';
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            $this->scores = is_array($data) && is_array($data['scores'] ?? null)
                ? array_map('floatval', array_filter($data['scores'], 'is_numeric'))
                : [];
        }

        return $this->scores[$id] ?? null;
    }

    /** Modèle et version des descriptions des notes en cache (« inconnu » sans fichier). */
    public function model(): string
    {
        $file = $this->dir().'/scores.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) ? ((string) ($data['model'] ?? '')).' '.((string) ($data['prompts'] ?? '')) : 'inconnu';
    }

    /**
     * Vignette de la photo en cache ; téléchargée si elle manque (sauf `$cacheOnly`). Faux si elle n'est pas
     * disponible.
     *
     * @param  array<string, mixed>  $f
     */
    public function fetch(array $f, bool $cacheOnly = false): bool
    {
        $id = (string) ($f['id'] ?? '');
        $url = PanoramaxSelection::thumb($f);
        if ($id === '' || preg_match('/^[A-Za-z0-9-]+$/', $id) !== 1 || $url === null) {
            return false;
        }
        $file = $this->dir().'/thumbs/'.$id.'.jpg';
        if (is_file($file)) {
            return true;
        }
        if ($cacheOnly) {
            return false;
        }
        $wait = $this->lastCall + $this->pauseMs / 1000 - microtime(true);
        if ($wait > 0) {
            usleep((int) ($wait * 1_000_000));
        }
        $this->lastCall = microtime(true);
        try {
            $res = Http::withHeaders(['User-Agent' => PanoramaxClient::USER_AGENT])->timeout(30)->retry(2, 5000, throw: false)->get($url);
        } catch (ConnectionException) {
            return false;
        }
        if (! $res->successful() || ! str_starts_with($res->body(), "\xFF\xD8")) {
            return false;
        }
        file_put_contents($file.'.tmp', $res->body());
        rename($file.'.tmp', $file);
        $this->downloaded++;

        return true;
    }

    /** Écrit la liste des photos sans note du dernier passage (`a-noter.txt`) et la remet à zéro. */
    public function flushUnscored(): void
    {
        file_put_contents($this->dir().'/a-noter.txt', $this->unscored === [] ? '' : implode("\n", array_unique($this->unscored))."\n");
    }

    private function dir(): string
    {
        $dir = $this->dir ?? storage_path('app/data/panoramax/vision');
        if (! is_dir($dir.'/thumbs')) {
            mkdir($dir.'/thumbs', 0775, true);
        }
        if (! is_file($dir.'/.nobackup')) {
            touch($dir.'/.nobackup');
        }

        return $dir;
    }
}
