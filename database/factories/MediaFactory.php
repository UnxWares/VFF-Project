<?php

namespace Database\Factories;

use App\Enums\MediaType;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(MediaType::cases());

        $extension = match ($type) {
            MediaType::Photo, MediaType::Postcard => 'jpg',
            MediaType::Plan, MediaType::Document => 'pdf',
            MediaType::Map => 'png',
        };

        $mime = match ($type) {
            MediaType::Photo, MediaType::Postcard => 'image/jpeg',
            MediaType::Plan, MediaType::Document => 'application/pdf',
            MediaType::Map => 'image/png',
        };

        $filename = fake()->slug(3).'.'.$extension;

        return [
            // mediable_type / mediable_id doivent être set par l'appelant via ->for(...)
            'mediable_type' => 'App\\Models\\Line',
            'mediable_id' => 1,
            'type' => $type->value,
            'file_path' => "media/{$filename}",
            'mime_type' => $mime,
            'file_size' => fake()->numberBetween(100_000, 5_000_000),
            'title' => fake()->sentence(4),
            'caption' => fake()->paragraph(1),
            'author' => fake()->name(),
            'copyright' => fake()->optional()->company(),
            'provider' => 'upload',
            'license' => 'CC-BY-SA-4.0',
            'rights_status' => 'open_license',
            'captured_at' => fake()->dateTimeBetween('-100 years', 'now')->format('Y-m-d'),
            'location' => null,
            'exif_data' => null,
        ];
    }
}
