<?php

declare(strict_types=1);

namespace App\Support\Basemap;

/**
 * Fond de carte libre auto-hébergé : une archive PMTiles (données OpenStreetMap au schéma Protomaps, ODbL)
 * rangée dans storage/app/data/pmtiles et servie par VFF lui-même (BasemapArchiveController). Les cartes la
 * lisent par plages d'octets avec le module `pmtiles` (resources/js/lib/basemap.js).
 *
 * Réglages : config/maps.php, clé `basemap`. Préparation et mise à jour : docs/FOND-DE-CARTE.md.
 */
final readonly class BasemapArchive
{
    /** Nom de fichier admis : il entre dans une adresse et un chemin, rien d'autre qu'un nom simple. */
    public const NAME_PATTERN = '[a-z0-9-]{1,64}\.pmtiles';

    /** Attribution exigée par l'ODbL et demandée par Protomaps, affichée par la carte. */
    public const ATTRIBUTION = '<a href="https://protomaps.com">Protomaps</a> '
        .'<a href="https://www.openstreetmap.org/copyright">© OpenStreetMap</a>';

    public function __construct(
        public ?string $archive,
        public string $directory,
        public bool $online,
        public int $maxAge,
    ) {}

    public static function fromConfig(): self
    {
        $archive = config('maps.basemap.archive');

        return new self(
            archive: is_string($archive) && $archive !== '' ? $archive : null,
            directory: (string) config('maps.basemap.directory'),
            online: (bool) config('maps.basemap.online'),
            maxAge: max(0, (int) config('maps.basemap.max_age')),
        );
    }

    /** Vrai si l'archive configurée a un nom valide et se trouve bien sur le disque. */
    public function available(): bool
    {
        $path = $this->path();

        return $path !== null && is_file($path) && is_readable($path);
    }

    /** Vrai si `$name` désigne l'archive configurée (seul fichier que la route sert). */
    public function serves(string $name): bool
    {
        return $this->archive !== null && hash_equals($this->archive, $name) && $this->available();
    }

    /** Chemin de l'archive, ou null si aucun nom valide n'est configuré. */
    public function path(): ?string
    {
        if ($this->archive === null || preg_match('/\A'.self::NAME_PATTERN.'\z/', $this->archive) !== 1) {
            return null;
        }

        return rtrim($this->directory, '/').'/'.$this->archive;
    }

    /** Adresse publique de l'archive (relative au site). */
    public function url(): ?string
    {
        return $this->archive !== null && $this->available()
            ? route('basemap.archive', ['archive' => $this->archive], false)
            : null;
    }

    /**
     * Ce que les cartes reçoivent (propriété Inertia partagée `basemap`) : null sans archive. `online` : le
     * fond libre remplace aussi les tuiles OpenStreetMap en ligne.
     *
     * @return array{url: string, online: bool, attribution: string}|null
     */
    public function clientConfig(): ?array
    {
        $url = $this->url();

        return $url === null ? null : [
            'url' => $url,
            'online' => $this->online,
            'attribution' => self::ATTRIBUTION,
        ];
    }
}
