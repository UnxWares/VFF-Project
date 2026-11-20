<?php

namespace App\Services\ArgentanWebFerro;

use App\Enums\ArgentanPhotoClass;
use App\Models\Line;
use App\Models\Station;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Galerie Piwigo d'Argentan Web Ferroviaire (/AWF/), album « Lignes Normandes » →
 * argentanwebferro_photos : métadonnées, classe de droits, ligne et gare VFF, et entrée
 * PRÊTE pour la table media (media_payload), jamais insérée ici.
 *
 * Droits : la galerie affiche en pied de page « Ce(tte) œuvre est mise à disposition selon les
 * termes de la Licence Creative Commons Attribution - Partage dans les Mêmes Conditions 4.0
 * International. » Cette licence ne vaut que pour les œuvres de l'auteur : seules les photos
 * relues comme œuvres propres (ArgentanPhotoClass::OwnWork) sont republiables. Cartes postales
 * anciennes, archives et photos créditées à d'autres photographes sont inventoriées mais
 * exclues ; une photo non relue reste « unknown ».
 */
final class GalleryInventory
{
    /** Album « Lignes Normandes » de la galerie. */
    public const ALBUM_LIGNES_NORMANDES = 28;

    public const PROVIDER = 'argentanwebferro';

    public const AUTHOR = 'Thierry Danglos';

    public const LICENSE = 'CC BY-SA 4.0';

    /** Même forme que les médias Commons déjà en base. */
    public const LICENSE_URL = 'https://creativecommons.org/licenses/by-sa/4.0';

    /** Mots-clés de la galerie qui nomment un autre photographe que l'auteur (pwg.tags.getList). */
    public const PHOTOGRAPHER_TAGS = [
        914 => 'Jean-Christophe Pagnucco', 915 => 'Laurent Choubrac', 916 => 'Emmanuel Desnos',
        917 => 'Olivier Gebenholtz', 1109 => 'Marc Mitou',
    ];

    /** @var list<string> */
    public array $warnings = [];

    public function __construct(
        private readonly ArgentanClient $client,
        private readonly Curation $curation,
        private readonly VffReader $reader,
    ) {}

    /** @return list<array<string, mixed>> lignes de argentanwebferro_photos */
    public function collect(): array
    {
        try {
            $albums = $this->albums();
            $images = $this->images();
            $credited = $this->creditedByTag();
        } catch (RuntimeException $e) {
            $this->warnings[] = 'galerie indisponible : '.$e->getMessage();

            return [];
        }
        $refIds = $this->reader->lineIds(array_values(array_unique(array_merge(...array_values($this->curation->photoAlbums ?: [[]])))));
        $classifier = new PhotoClassifier($this->curation->photos, $credited);

        $rows = [];
        foreach ($images as $img) {
            $paths = [];
            foreach ($img['categories'] ?? [] as $cat) {
                if (isset($albums[(int) $cat['id']])) {
                    $paths[] = $albums[(int) $cat['id']];
                }
            }
            [$class, $basis] = $classifier->classify($img);
            [$lineId, $stationId] = $this->attach($paths, $refIds);
            $row = [
                'piwigo_id' => (int) $img['id'], 'name' => self::cut($img['name'] ?? null, 300), 'file' => self::cut($img['file'] ?? null, 300),
                'width' => $img['width'] ?? null, 'height' => $img['height'] ?? null,
                'date_creation' => $img['date_creation'] ?? null, 'date_available' => $img['date_available'] ?? null,
                'lat' => null, 'lon' => null,
                'albums' => json_encode($paths, JSON_UNESCAPED_UNICODE), 'tags' => json_encode([], JSON_UNESCAPED_UNICODE),
                'page_url' => (string) $img['page_url'],
                'thumb_source_url' => $img['derivatives']['thumb']['url'] ?? null,
                'display_source_url' => $img['derivatives']['medium']['url'] ?? ($img['element_url'] ?? null),
                'photo_class' => $class->value, 'class_basis' => $basis, 'republishable' => $class->republishable(),
                'author' => $class === ArgentanPhotoClass::OwnWork ? self::AUTHOR : null,
                'depicted_year' => $class === ArgentanPhotoClass::OwnWork ? self::shotYear((string) ($img['name'] ?? ''), $img['date_creation'] ?? null) : null,
                'line_id' => $lineId, 'station_id' => $stationId,
            ];
            $row['media_payload'] = $class->republishable() ? json_encode(self::mediaPayload($row, $paths), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $rows[] = $row;
        }

        return $rows;
    }

    /** @param  list<array<string, mixed>>  $rows */
    public function persist(array $rows): int
    {
        return DB::transaction(function () use ($rows): int {
            $now = now();
            foreach (array_chunk($rows, 300) as $chunk) {
                $chunk = array_map(fn (array $r) => [...$r, 'created_at' => $now, 'updated_at' => $now], $chunk);
                DB::table('argentanwebferro_photos')->upsert($chunk, ['piwigo_id'], array_values(array_diff(array_keys($chunk[0]), ['piwigo_id', 'created_at'])));
            }
            DB::table('argentanwebferro_photos')->whereNotIn('piwigo_id', array_column($rows, 'piwigo_id') ?: [0])->delete();

            return count($rows);
        });
    }

    /**
     * Entrée prête pour la table media (copie locale de taille raisonnable, cf. ImportArgentanWebFerroMedia),
     * rattachée à la ligne ; la gare de l'album est gardée dans provider_meta pour une future galerie de gare.
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $paths
     * @return array<string, mixed>
     */
    public static function mediaPayload(array $row, array $paths): array
    {
        $title = (string) ($row['name'] ?? '');

        return [
            // Seules les galeries de lignes sont affichées aujourd'hui (LineMediaController) : la gare reste dans provider_meta.
            'mediable_type' => Line::class,
            'mediable_id' => $row['line_id'],
            'type' => 'photo',
            'provider' => self::PROVIDER,
            'external_id' => (string) $row['piwigo_id'],
            'source_url' => $row['page_url'],
            'file_path' => 'media/argentanwebferro/'.$row['piwigo_id'].'.jpg',
            'thumb_url' => null,
            'display_url' => null,
            'title' => $title !== '' ? $title : null,
            'caption' => $paths[0] ?? null,
            'author' => self::AUTHOR,
            'license' => self::LICENSE,
            'license_url' => self::LICENSE_URL,
            // Même forme que les crédits Commons (« Auteur, CC BY-SA 4.0, via Wikimedia Commons »).
            'attribution' => self::AUTHOR.', '.self::LICENSE.', via Argentan Web Ferroviaire',
            'rights_status' => 'open_license',
            'commercial_ok' => true,
            'share_alike' => true,
            'width' => $row['width'],
            'height' => $row['height'],
            'depicted_year' => $row['depicted_year'],
            'captured_at' => $row['depicted_year'] !== null ? substr((string) $row['date_creation'], 0, 10) : null,
            'provider_meta' => [
                'piwigo_id' => $row['piwigo_id'], 'albums' => $paths, 'class_basis' => $row['class_basis'],
                'licence_source' => 'pied de page de la galerie https://argentanwebferro.fr/AWF/ (CC BY-SA 4.0)',
                'station_id' => $row['station_id'], 'station_type' => Station::class, 'source_image' => $row['display_source_url'],
            ],
        ];
    }

    /** @return array<int, string> album → chemin « Lignes Normandes / Ligne / Gare » */
    private function albums(): array
    {
        $out = [];
        foreach ($this->client->piwigo('pwg.categories.getList', ['recursive' => 'true', 'fullname' => 'true'])['categories'] ?? [] as $c) {
            $name = html_entity_decode((string) $c['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (str_starts_with($name, 'Ferroviaire / Lignes Normandes')) {
                $out[(int) $c['id']] = substr($name, strlen('Ferroviaire / '));
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function images(): array
    {
        $all = [];
        for ($page = 0; $page < 20; $page++) {
            $result = $this->client->piwigo('pwg.categories.getImages', [
                'cat_id' => (string) self::ALBUM_LIGNES_NORMANDES, 'recursive' => 'true', 'per_page' => '500', 'page' => (string) $page,
            ]);
            $batch = $result['images'] ?? [];
            $all = [...$all, ...$batch];
            if (count($batch) < 500) {
                break;
            }
        }

        return $all;
    }

    /** @return array<int, string> photo → photographe nommé par un mot-clé */
    private function creditedByTag(): array
    {
        $out = [];
        foreach (self::PHOTOGRAPHER_TAGS as $tagId => $name) {
            try {
                for ($page = 0; $page < 20; $page++) {
                    $result = $this->client->piwigo('pwg.tags.getImages', ['tag_id' => (string) $tagId, 'per_page' => '500', 'page' => (string) $page]);
                    $batch = $result['images'] ?? [];
                    foreach ($batch as $img) {
                        $out[(int) $img['id']] = $name;
                    }
                    if (count($batch) < 500) {
                        break;
                    }
                }
            } catch (RuntimeException $e) {
                $this->warnings[] = "mot-clé {$name} illisible : ".$e->getMessage();
            }
        }

        return $out;
    }

    /**
     * Ligne et gare VFF d'après l'album : « Lignes Normandes / Paris-Granville / Surdon ».
     *
     * @param  list<string>  $paths
     * @param  array<string, int>  $refIds
     * @return array{0: ?int, 1: ?int}
     */
    private function attach(array $paths, array $refIds): array
    {
        $fallback = null;
        foreach ($paths as $path) {
            $parts = explode(' / ', $path);
            $lineIds = array_values(array_filter(array_map(fn (string $r) => $refIds[$r] ?? null, $this->curation->photoAlbums[$parts[1] ?? ''] ?? [])));
            if ($lineIds === []) {
                continue;
            }
            $fallback ??= $lineIds[0];
            if (isset($parts[2])) {
                foreach ($lineIds as $lineId) {
                    $station = $this->reader->stationOnLine($lineId, $parts[2]);
                    if ($station !== null) {
                        return [$lineId, $station];
                    }
                }
            }
        }

        return [$fallback, null];
    }

    /**
     * Année de prise de vue : celle de la légende de l'auteur (« Montsecret - 1989 », « Vignats -
     * 14/09/2019 »), sinon celle de l'EXIF quand la galerie donne une heure, sinon inconnue.
     */
    public static function shotYear(string $name, ?string $dateCreation): ?int
    {
        if (preg_match('/(?<!\d)(19[5-9]\d|20[0-3]\d)(?!\d)/u', $name, $m)) {
            return (int) $m[1];
        }
        if ($dateCreation === null || str_ends_with($dateCreation, '00:00:00')) {
            return null;
        }

        return (int) substr($dateCreation, 0, 4);
    }

    private static function cut(mixed $value, int $max): ?string
    {
        return $value === null ? null : mb_substr((string) $value, 0, $max);
    }
}
