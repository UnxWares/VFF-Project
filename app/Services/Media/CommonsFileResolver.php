<?php

namespace App\Services\Media;

use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Lecture d'un fichier Wikimedia Commons proposé par un contributeur (formulaire « Proposer une
 * image », mutation GraphQL) : adresse → titre du fichier → métadonnées normalisées par
 * CommonsMetadata (auteur, licence, miniature), puis verdict sur ses droits.
 *
 * Une seule requête à l'API publique de Commons, faite par le serveur pendant la requête du site
 * (client « impatient » : pas de longues reprises), avec le User-Agent générique de CommonsClient.
 * Un fichier trouvé reste une heure en cache : vérifier l'adresse puis envoyer le formulaire ne
 * coûte qu'un appel.
 */
final class CommonsFileResolver
{
    /** Refus : format que la galerie n'affiche pas (audio, vidéo, modèle 3D). */
    public const REFUSED_FORMAT = 'format';

    /** Refus : licence inconnue, non libre ou non commerciale. */
    public const REFUSED_LICENSE = 'license';

    private const CACHE_SECONDS = 3600;

    private CommonsClient $client;

    public function __construct()
    {
        $this->client = new CommonsClient(minIntervalMs: 0, patient: false);
    }

    /**
     * Titre « File:Nom du fichier.jpg » d'une adresse Commons : page du fichier (wiki, version
     * mobile, index.php?title=…), fichier original ou miniature sur upload.wikimedia.org, ou titre
     * seul. Espaces soulignés, encodage d'URL et noms d'espace traduits (Fichier, Datei) normalisés.
     */
    public static function fileTitle(string $input): ?string
    {
        $input = trim($input);
        $name = null;
        if (preg_match('#^(?:File|Fichier|Datei|Image):(.+)$#iu', $input, $m)) {
            $name = $m[1];
        } elseif (preg_match('#^https?://#i', $input)) {
            $parts = parse_url($input);
            $host = strtolower((string) ($parts['host'] ?? ''));
            $path = (string) ($parts['path'] ?? '');
            parse_str((string) ($parts['query'] ?? ''), $query);
            if (in_array($host, ['commons.wikimedia.org', 'commons.m.wikimedia.org'], true)) {
                $page = preg_match('#^/wiki/(.+)$#', $path, $m) ? rawurldecode($m[1]) : (is_string($query['title'] ?? null) ? $query['title'] : '');
                $name = preg_match('#^(?:File|Fichier|Datei|Image):(.+)$#iu', $page, $m) ? $m[1] : null;
            } elseif ($host === 'upload.wikimedia.org' && preg_match('#^/wikipedia/commons/(?:thumb/)?[0-9a-f]/[0-9a-f]{2}/([^/]+)#', $path, $m)) {
                $name = rawurldecode($m[1]);
            }
        }
        $name = $name !== null ? trim(str_replace('_', ' ', $name)) : '';
        if ($name === '' || mb_strlen($name) > 240 || preg_match('/[#<>\[\]|{}\/]/', $name) === 1) {
            return null;
        }

        return 'File:'.mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1);
    }

    /**
     * Métadonnées normalisées du fichier (CommonsMetadata::normalize, plus `page_id`), ou null s'il
     * n'existe pas sur Commons.
     *
     * @return array<string, mixed>|null
     *
     * @throws RuntimeException Commons injoignable ou en erreur
     * @throws HttpClientException réponse HTTP en erreur (Commons surchargé)
     */
    public function lookup(string $title): ?array
    {
        $key = 'commons-file:'.sha1($title);
        $cached = Cache::get($key);
        if (is_array($cached)) {
            $this->allow($cached);

            return $cached;
        }

        $page = $this->client->fileInfoByTitle($title);
        if ($page === null) {
            return null;
        }
        $meta = ['page_id' => $page['pageid'], ...CommonsMetadata::normalize(['title' => $page['title'], 'info' => $page['info']])];
        Cache::put($key, $meta, self::CACHE_SECONDS);
        $this->allow($meta);

        return $meta;
    }

    /**
     * Le fichier cherché peut s'afficher en aperçu par le relais des vignettes (liste des fichiers cités,
     * CommonsAllowlist) avant d'entrer dans la base.
     *
     * @param  array<string, mixed>  $meta
     */
    private function allow(array $meta): void
    {
        if (! config('commons_proxy.enabled')) {
            return;
        }
        $allowlist = app(CommonsAllowlist::class);
        foreach (['thumb_url', 'display_url'] as $key) {
            if (isset($meta[$key]) && is_string($meta[$key])) {
                $allowlist->add($meta[$key]);
            }
        }
    }

    /**
     * Raison du refus d'un fichier, null s'il peut entrer dans VFF : un format d'image ou de
     * document numérisé, et une licence libre déclarée par Commons qui permet un usage commercial.
     *
     * @param  array<string, mixed>  $meta  métadonnées de lookup()
     */
    public static function refusal(array $meta): ?string
    {
        return match (true) {
            ! in_array($meta['mime'] ?? null, CommonsMetadata::ACCEPTED_MIMES, true) => self::REFUSED_FORMAT,
            ! in_array($meta['rights_status'] ?? null, ['public_domain', 'open_license'], true),
            ($meta['commercial_ok'] ?? null) === false => self::REFUSED_LICENSE,
            default => null,
        };
    }
}
