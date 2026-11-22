<?php

namespace App\Services\Archives\Sncf;

use App\Services\Archives\ArchiveDate;

/**
 * Lecture d'une notice de l'export JSON du jeu de données « Archives SNCF » (archives-sncf-sardo,
 * ressources.data.sncf.com, ODbL). Seuls le titre, le type, le classement, le service versant, la boîte, le
 * titulaire des droits, la date et le lien sont repris ; la description et les mots-clés ne le sont pas.
 *
 * Notices écartées (null) : sans titre ou sans lien vers archives.sncf.com, d'un type inconnu, ou classées dans
 * un thème qui porte sur des personnes (EXCLUDED_THEMES : fichiers des cheminots blessés et tués, contentieux,
 * questions de personnel) ; VFF ne rattache aux lignes aucune notice sur un agent.
 *
 * Date : début et fin du dossier (années) ; à défaut, date de prise de vue (« 1958-09-27 00:00:00 »,
 * « 15/06/1861 », « 06/1944 », « 1937 », « 1950-1955 ») ou de production. Une date de prise de vue au premier
 * du mois est lue au mois, et au 1er janvier à l'année : le fonds y range les photographies datées du seul mois
 * ou de la seule année.
 */
final class SardoParser
{
    public const URL_PREFIX = 'https://www.archives.sncf.com/';

    /** Types du jeu de données → types de VFF. */
    private const TYPES = ['Document' => 'document', 'Image' => 'image', 'Photo' => 'image', 'Vidéo' => 'video'];

    /** Thèmes qui portent sur des personnes (en capitales, sans accents) : jamais rattachés. */
    public const EXCLUDED_THEMES = [
        'FICHIERS DES CHEMINOTS BLESSES ET TUES',
        'DOSSIERS DE CONTENTIEUX',
        'QUESTIONS DE PERSONNEL',
    ];

    /**
     * Nom de compagnie : « Compagnie du chemin de fer de Paris à Orléans », « Compagnie des chemins de fer de
     * Paris à Lyon et à la Méditerranée », « Cie de l'Est ». Il se termine au premier mot en minuscules qui
     * n'est pas un mot de liaison (« relative », « concernant »), ou à une ponctuation.
     */
    private const COMPANY = '/\b(?:[Cc]ompagnies?|C[Ii][Ee]S?)\b(?:(?:[\s\x{00A0}]+|(?<=[\'’]))(?:g[ée]n[ée]rales?|internationales?|du|des|de|d[\'’]|[Cc]hemins?|fer|à|au|aux|et|la|le|les|l[\'’]|sur|en|\p{Lu}[\p{L}\'’.-]*))+/u';

    /**
     * @param  array<string, mixed>  $row  une notice de l'export JSON
     */
    public function parse(array $row): ?SardoRecord
    {
        $title = self::clean($row['titre'] ?? null);
        $url = self::clean($row['url'] ?? null);
        $type = self::TYPES[(string) ($row['type_de_document'] ?? '')] ?? null;
        if ($title === null || $url === null || ! str_starts_with($url, self::URL_PREFIX) || $type === null) {
            return null;
        }
        $theme = self::clean($row['theme'] ?? null);
        if ($theme !== null && self::excluded($theme)) {
            return null;
        }

        return new SardoRecord(
            url: $url,
            reference: self::clean($row['ndeg_dossier'] ?? null),
            title: $title,
            matchText: self::listedLines(self::withoutCompanies($title)),
            docType: $type,
            theme: $theme,
            subTheme: self::clean($row['sous_theme'] ?? null),
            holder: self::clean($row['service_proprietaire'] ?? null),
            box: self::clean($row['ndeg_boite'] ?? null),
            rights: self::clean($row['droits'] ?? null),
            date: self::date($row),
        );
    }

    /** Titre où chaque nom de compagnie est remplacé par le mot « compagnie » (ses lieux ne comptent pas). */
    public static function withoutCompanies(string $title): string
    {
        return (string) preg_replace(self::COMPANY, 'compagnie', $title);
    }

    /**
     * « située sur les lignes du Mans à Mézidon et d'Argentan à Granville » : chaque trajet de la liste qui suit
     * « lignes » est une ligne, et le mot « ligne » est répété devant lui (« et ligne d'Argentan à Granville »)
     * pour qu'ArchiveMatcher le lise comme une voie ferrée, jusqu'à la fin de la proposition (. ; : ou parenthèse).
     */
    public static function listedLines(string $text): string
    {
        return (string) preg_replace_callback(
            '/\blignes\s[^.;:()]*/u',
            fn (array $m): string => (string) preg_replace('/(\set|,)\s+(?=(?:de|du|des)\s|d[\'’])/u', '$1 ligne ', $m[0]),
            $text,
        );
    }

    /** @param array<string, mixed> $row */
    public static function date(array $row): ArchiveDate
    {
        $from = self::year($row['debut'] ?? null);
        $to = self::year($row['fin'] ?? null);
        if ($from !== null) {
            if ($to === null || $to <= $from) {
                return new ArchiveDate((string) $from, $from, null, null, 'year');
            }

            return new ArchiveDate("{$from}-{$to}", $from, $to, null, 'range');
        }

        return self::shotDate(self::clean($row['date_de_prise_de_vue'] ?? null))
            ?? self::shotDate(self::clean($row['date_de_production'] ?? null))
            ?? ArchiveDate::unknown();
    }

    /** Date de prise de vue ou de production, dans l'une des formes du jeu de données ; null si illisible. */
    private static function shotDate(?string $value): ?ArchiveDate
    {
        if ($value === null) {
            return null;
        }
        $value = (string) preg_replace('/\s+/', '', $value);
        $ymd = null;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(\d{2}:\d{2}:\d{2})?$/', $value, $m) === 1) {
            $ymd = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m) === 1) {
            $ymd = [(int) $m[3], (int) $m[2], (int) $m[1]];
        }
        if ($ymd !== null) {
            [$y, $mo, $d] = $ymd;
            if (! checkdate($mo, $d, $y)) {
                return null;
            }
            if ($d !== 1) {
                return new ArchiveDate(sprintf('%04d-%02d-%02d', $y, $mo, $d), $y, null, sprintf('%04d-%02d-%02d', $y, $mo, $d), 'day');
            }
            if ($mo !== 1) {
                return new ArchiveDate(sprintf('%04d-%02d', $y, $mo), $y, null, sprintf('%04d-%02d-01', $y, $mo), 'month');
            }

            return new ArchiveDate((string) $y, $y, null, null, 'year');
        }
        if (preg_match('/^(\d{2})\/(\d{4})$/', $value, $m) === 1 && (int) $m[1] >= 1 && (int) $m[1] <= 12) {
            return new ArchiveDate(sprintf('%s-%s', $m[2], $m[1]), (int) $m[2], null, sprintf('%s-%s-01', $m[2], $m[1]), 'month');
        }
        if (preg_match('/^(\d{4})$/', $value, $m) === 1) {
            return new ArchiveDate($m[1], (int) $m[1], null, null, 'year');
        }
        if (preg_match('/^(\d{4})-(\d{4})$/', $value, $m) === 1 && (int) $m[2] > (int) $m[1]) {
            return new ArchiveDate($value, (int) $m[1], (int) $m[2], null, 'range');
        }

        return null;
    }

    private static function year(mixed $value): ?int
    {
        $value = self::clean($value);

        return $value !== null && preg_match('/^\d{4}$/', $value) === 1 ? (int) $value : null;
    }

    private static function excluded(string $theme): bool
    {
        $plain = mb_strtoupper(strtr($theme, ['é' => 'e', 'É' => 'E', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ç' => 'c', 'ô' => 'o', 'î' => 'i', 'û' => 'u']));
        foreach (self::EXCLUDED_THEMES as $excluded) {
            if (str_contains($plain, $excluded)) {
                return true;
            }
        }

        return false;
    }

    /** Texte sans espaces insécables ni espaces en trop ; null si vide. */
    private static function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $value));

        return $value === '' ? null : $value;
    }
}
