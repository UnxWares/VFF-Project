<?php

namespace App\Services\OpenData;

use App\Support\CsvSafe;
use RuntimeException;

/**
 * Écriture en flux des fichiers d'un jeu (CSV UTF-8 séparé par des virgules, GeoJSON RFC 7946) : mémoire
 * constante, sortie déterministe (même base, même fichier à l'octet près).
 *
 * CSV : une cellule de texte qui commence par =, +, -, @ est préfixée d'une apostrophe (App\Support\CsvSafe),
 * pour qu'un tableur ne la lise pas comme une formule ; les nombres restent intacts.
 */
final class OpenDataFiles
{
    private const JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION;

    /** @var resource */
    private $handle;

    private int $rows = 0;

    private bool $geojson;

    /** @param  list<string>  $header  en-tête CSV (ignoré pour un GeoJSON) */
    private function __construct(private readonly string $path, array $header, ?string $name)
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Impossible d'écrire {$path}.");
        }
        $this->handle = $handle;
        $this->geojson = $name !== null;
        if ($this->geojson) {
            fwrite($this->handle, '{"type":"FeatureCollection","name":'.json_encode($name, self::JSON).',"features":['."\n");
        } else {
            fputcsv($this->handle, $header, ',', '"', '');
        }
    }

    /** @param  list<string>  $header */
    public static function csv(string $path, array $header): self
    {
        return new self($path, $header, null);
    }

    public static function geojson(string $path, string $name): self
    {
        return new self($path, [], $name);
    }

    /** @param  array<array-key, mixed>  $row valeurs dans l'ordre de l'en-tête */
    public function row(array $row): void
    {
        fputcsv($this->handle, CsvSafe::row($row), ',', '"', '');
        $this->rows++;
    }

    /**
     * Entité GeoJSON ; la géométrie est le texte de ST_AsGeoJSON (null : entité sans géométrie).
     *
     * @param  array<string, mixed>  $properties
     */
    public function feature(int|string $id, ?string $geometry, array $properties): void
    {
        fwrite($this->handle, ($this->rows > 0 ? ",\n" : '').'{"type":"Feature","id":'.json_encode($id, self::JSON)
            .',"geometry":'.($geometry ?? 'null').',"properties":'.json_encode($properties, self::JSON).'}');
        $this->rows++;
    }

    /** Ferme le fichier et rend le nombre de lignes (ou d'entités) écrites. */
    public function close(): int
    {
        if ($this->geojson) {
            fwrite($this->handle, "\n]}\n");
        }
        fclose($this->handle);

        return $this->rows;
    }

    public function path(): string
    {
        return $this->path;
    }
}
