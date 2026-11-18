<?php

namespace App\Services\StationService;

use Generator;
use RuntimeException;
use ZipArchive;

/**
 * Fichiers d'un flux GTFS, archive .zip ou répertoire, lus ligne par ligne
 * (stop_times.txt dépasse le gigaoctet pour certains réseaux : jamais en entier en mémoire).
 */
final readonly class GtfsArchive
{
    /** @var array<string, string> nom du fichier => chemin lisible par fopen() */
    private array $entries;

    public function __construct(string $path)
    {
        $entries = [];
        if (is_dir($path)) {
            foreach (glob(rtrim($path, '/').'/*.txt') ?: [] as $file) {
                $entries[basename($file)] = $file;
            }
        } else {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::RDONLY) !== true) {
                throw new RuntimeException("GTFS illisible : {$path}");
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                $entries[basename($name)] ??= 'zip://'.$path.'#'.$name;
            }
            $zip->close();
        }
        $this->entries = $entries;
    }

    public function has(string $file): bool
    {
        return isset($this->entries[$file]);
    }

    /**
     * Lignes d'un fichier, en tableaux associatifs (en-tête sans BOM ni espaces).
     *
     * @return Generator<int, array<string, string>>
     */
    public function rows(string $file): Generator
    {
        if (! $this->has($file)) {
            return;
        }
        $handle = fopen($this->entries[$file], 'rb');
        if ($handle === false) {
            throw new RuntimeException("GTFS : {$file} illisible");
        }
        try {
            $header = fgetcsv($handle, null, ',', '"', '');
            if (! is_array($header)) {
                return;
            }
            $header = array_map(fn (?string $h): string => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)), $header);
            $width = count($header);
            while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }
                $values = array_pad(array_slice($row, 0, $width), $width, '');
                yield array_combine($header, array_map(fn (?string $v): string => trim((string) $v), $values));
            }
        } finally {
            fclose($handle);
        }
    }
}
