<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\Media\ThumbnailGenerator;
use Illuminate\Console\Command;

/**
 * Miniatures des images déposées déjà publiées (320 et 1 280 px, WebP), pour celles qui n'en ont
 * pas encore. Les nouvelles images en reçoivent à la publication ; cette commande rattrape les
 * images publiées avant l'ajout de GD, ou celles dont la création a échoué.
 *
 *   php artisan vff:media-thumbnails           # images sans miniature
 *   php artisan vff:media-thumbnails --force   # toutes les images déposées
 */
class MediaThumbnails extends Command
{
    protected $signature = 'vff:media-thumbnails
        {--force : Refait aussi les miniatures des images qui en ont déjà}';

    protected $description = 'Crée les miniatures (320 et 1 280 px) des images déposées par les contributeurs';

    public function handle(ThumbnailGenerator $thumbnails): int
    {
        if (! ThumbnailGenerator::available()) {
            $this->error('GD (JPEG, PNG, WebP) manque dans cette installation de PHP : reconstruisez l\'image (docker/php/Containerfile).');

            return self::FAILURE;
        }

        $done = 0;
        $failed = 0;
        Media::query()
            ->where('provider', Media::PROVIDER_UPLOAD)
            ->whereNotNull('file_path')
            ->when(! $this->option('force'), fn ($q) => $q->whereRaw("(provider_meta->'thumbs') is null"))
            ->orderBy('id')
            ->each(function (Media $media) use ($thumbnails, &$done, &$failed): void {
                $thumbnails->forMedia($media) === [] ? $failed++ : $done++;
            });

        $this->info(sprintf('%d image(s) traitée(s), %d sans miniature (fichier absent ou illisible).', $done, $failed));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
