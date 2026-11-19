<?php

namespace App\Services\Signalling;

use App\Enums\SignallingRole;
use App\Services\WikipediaLineParser;

/**
 * Mises en service de signalisation lues dans un tableau d'article (wikitable), une ligne du tableau à la fois.
 * Reçoit le tableau en texte brut (WikipediaLineParser::toPlainText garde `{|`, `|-`, `|`, `!`).
 *
 * Les en-têtes désignent les colonnes utiles : date (« Date », « Année », « Mise en service »), système
 * (« Signalisation », « Système », « Cantonnement », « Block »), portion (« Section », « Tronçon », « De »,
 * « À »). Sans en-tête reconnu, toute la ligne du tableau est lue. Une ligne donne un fait quand elle cite un
 * seul système et une seule date ; plusieurs dates ou plusieurs systèmes donnent un fait peu sûr (`low`,
 * jamais montré). Une ligne au futur, d'une dépose ou d'un projet ne donne rien, comme dans le texte.
 *
 * Relevé du 19/11/2026 : aucun des 860 articles de lignes lus n'a de tableau qui cite un système de
 * signalisation. La lecture est prête pour une révision qui en ajouterait un (`--refresh`).
 */
final class SignallingTableReader
{
    private const NEGATIVE = '/\b(?:sera|seront|prévu\w*|projet\w*|futur\w*|envisag\w+|report\w*|supprim\w+|dépos\w+|retir\w+|abandonn\w+|HS|hors\s+service)\b/iu';

    private const MAX_EXCERPT = 200;

    public function __construct(private readonly WikipediaLineParser $parser) {}

    /**
     * @param  list<string>  $lines  lignes du tableau, de `{|` à `|}` compris
     * @param  list<string>  $ends  extrémités de la ligne
     * @return list<array<string, mixed>>
     */
    public function facts(array $lines, array $ends, \DateTimeInterface $today): array
    {
        [$headers, $rows] = $this->parse($lines);
        $columns = $this->columns($headers);
        $facts = [];
        foreach ($rows as $cells) {
            $text = implode(' ; ', $cells);
            if (preg_match(self::NEGATIVE, $text) || str_contains($text, '⟦')) {
                continue;
            }
            $systemText = $columns['system'] !== null ? ($cells[$columns['system']] ?? '') : $text;
            $dateText = $columns['date'] !== null ? ($cells[$columns['date']] ?? '') : $text;
            $mentions = SignallingSystems::mentions($systemText);
            $dates = array_values(array_filter($this->parser->findDates($dateText), fn (array $d) => $d['precision'] !== 'decade'));
            if ($mentions === [] || $dates === [] || strtotime($dates[0]['date']) > $today->getTimestamp()) {
                continue;
            }
            $section = $this->section($cells, $columns, $text, $ends);
            if ($section !== null && ($section['other_line'] ?? false)) {
                continue;
            }
            $date = $dates[0];
            $doubtful = count(array_unique(array_column($dates, 'date'))) > 1
                || count(array_unique(array_column($mentions, 'system'))) > 1
                || SignallingSystems::tooEarly($mentions[0]['system'], $date['year']);
            foreach (array_unique(array_column($mentions, 'system')) as $system) {
                $facts[] = [
                    'system' => $system,
                    'role' => SignallingRole::forSystem($system)->value ?? SignallingRole::Block->value,
                    'date' => $date['date'],
                    'precision' => $date['precision'],
                    'scope' => $this->scope($section),
                    'section_from' => $section['from'] ?? null,
                    'section_to' => $section['to'] ?? null,
                    'pk_start_m' => $section['pk_start'] ?? null,
                    'pk_end_m' => $section['pk_end'] ?? null,
                    'confidence' => $doubtful ? 'low' : ($date['precision'] === 'year' ? 'medium' : 'high'),
                    'origin' => 'table',
                    'excerpt' => self::excerpt($text),
                ];
            }
        }

        return $facts;
    }

    /**
     * En-têtes et lignes du tableau, cellules nettoyées (attributs `style=… |` retirés).
     *
     * @param  list<string>  $lines
     * @return array{0: list<string>, 1: list<list<string>>}
     */
    private function parse(array $lines): array
    {
        $headers = [];
        $rows = [];
        $current = [];
        foreach ($lines as $raw) {
            $line = trim($raw);
            if ($line === '' || str_starts_with($line, '{|') || str_starts_with($line, '|+')) {
                continue;
            }
            if (str_starts_with($line, '|-') || str_starts_with($line, '|}')) {
                if ($current !== []) {
                    $rows[] = $current;
                }
                $current = [];

                continue;
            }
            if (str_starts_with($line, '!')) {
                foreach (preg_split('/\s*!!\s*|\s*\|\|\s*/u', mb_substr($line, 1)) ?: [] as $cell) {
                    $headers[] = self::cell($cell);
                }

                continue;
            }
            if (str_starts_with($line, '|')) {
                foreach (explode('||', mb_substr($line, 1)) as $cell) {
                    $current[] = self::cell($cell);
                }
            } elseif ($current !== []) {
                // Suite de la cellule précédente sur une nouvelle ligne.
                $current[array_key_last($current)] .= ' '.$line;
            }
        }
        if ($current !== []) {
            $rows[] = $current;
        }

        return [$headers, $rows];
    }

    /**
     * Index des colonnes utiles d'après les en-têtes (null : colonne absente).
     *
     * @param  list<string>  $headers
     * @return array{date: ?int, system: ?int, section: ?int, from: ?int, to: ?int}
     */
    private function columns(array $headers): array
    {
        $out = ['date' => null, 'system' => null, 'section' => null, 'from' => null, 'to' => null];
        foreach ($headers as $i => $h) {
            $key = match (true) {
                (bool) preg_match('/\b(?:date|année|mise\s+en\s+service)\b/iu', $h) => 'date',
                (bool) preg_match('/\b(?:signalisation|système|cantonnement|block|bloc)\b/iu', $h) => 'system',
                (bool) preg_match('/\b(?:section|tronçon|parcours|portion)\b/iu', $h) => 'section',
                (bool) preg_match('/^(?:de|depuis|origine|début)$/iu', $h) => 'from',
                (bool) preg_match('/^(?:à|a|jusqu[’\']à|fin|destination)$/iu', $h) => 'to',
                default => null,
            };
            if ($key !== null && $out[$key] === null) {
                $out[$key] = $i;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $cells
     * @param  array{date: ?int, system: ?int, section: ?int, from: ?int, to: ?int}  $columns
     * @param  list<string>  $ends
     * @return array<string, mixed>|null
     */
    private function section(array $cells, array $columns, string $text, array $ends): ?array
    {
        if ($columns['from'] !== null && $columns['to'] !== null) {
            $from = trim($cells[$columns['from']] ?? '');
            $to = trim($cells[$columns['to']] ?? '');

            return $from !== '' && $to !== '' ? ['from' => $from, 'to' => $to, 'pk_start' => null, 'pk_end' => null, 'whole_line' => false, 'other_line' => false] : null;
        }
        $source = $columns['section'] !== null ? ($cells[$columns['section']] ?? '') : $text;

        return $this->parser->findSection($source, $ends);
    }

    /** @param  array<string, mixed>|null  $section */
    private function scope(?array $section): string
    {
        return match (true) {
            $section === null => 'unspecified',
            (bool) ($section['whole_line'] ?? false) => 'whole_line',
            ($section['from'] ?? null) !== null || ($section['to'] ?? null) !== null || ($section['pk_start'] ?? null) !== null => 'section',
            default => 'unspecified',
        };
    }

    /** Texte d'une cellule : sans l'attribut de mise en forme qui la précède (`style="…" | texte`). */
    private static function cell(string $cell): string
    {
        $cell = trim($cell);
        if (preg_match('/^[a-z-]+\s*=\s*"[^"]*"(?:\s+[a-z-]+\s*=\s*"[^"]*")*\s*\|(?!\|)\s*(.*)$/isu', $cell, $m)) {
            $cell = $m[1];
        }

        return trim((string) preg_replace('/\s+/u', ' ', $cell));
    }

    private static function excerpt(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', str_replace(['—', '–', '⟦', '⟧'], ['-', '-', '', ''], $text)));

        return mb_strlen($text) > self::MAX_EXCERPT ? rtrim(mb_substr($text, 0, self::MAX_EXCERPT - 1)).'…' : $text;
    }
}
