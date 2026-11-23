<?php

declare(strict_types=1);

namespace App\Services\Heritage;

use Illuminate\Support\Facades\Http;

/**
 * Base Mérimée des monuments historiques (Plateforme ouverte du patrimoine, ministère de la Culture), jeu
 * « Immeubles protégés au titre des Monuments Historiques » de data.gouv.fr, publié sous Licence Ouverte 2.0
 * (`lov2`). Seul le fichier officiel du ministère est lu, pas les copies de tiers.
 *
 * Le fichier (une centaine de Mo, séparateur `|`) est téléchargé une fois dans `<dossier>/merimee.csv`, avec son
 * `meta.json` (licence, éditeur et date de mise à jour relus sur data.gouv.fr à chaque téléchargement ; rien n'est
 * gardé si la licence ou l'éditeur changent) et un `.nobackup`. Seules les notices de gare et d'ouvrage de gare
 * sont gardées (`railwayRecords`).
 */
final class MerimeeSource
{
    public const DATASET_API = 'https://www.data.gouv.fr/api/1/datasets/immeubles-proteges-au-titre-des-monuments-historiques-2/';

    public const CSV_URL = 'https://ministere-culture.s3.sbg.io.cloud.ovh.net/POP/merimee.csv';

    public const NOTICE_URL = 'https://www.pop.culture.gouv.fr/notice/merimee/';

    public const USER_AGENT = 'VFF-Bot/1.0 (+https://vff-project.org)';

    /** Dénominations de gare qui ne sont pas des gares ferroviaires. */
    private const NOT_RAIL = ['pont', 'viaduc', 'tunnel', 'gare routière', 'gare maritime', 'funiculaire', 'station de métro', 'aéroport', 'hôtel de voyageurs'];

    /** Mots d'un ouvrage de gare (halle, marquise, rotonde, château d'eau, quai) quand la notice parle de chemin de fer. */
    private const WORKS_TITLE = '/\b(halle|marquise|rotonde|ch[aâ]teau d.eau|r[ée]servoir|quai|passerelle|d[ée]p[ôo]t|remise)\b/iu';

    private const RAIL_TITLE = '/\b(gare|SNCF|ferroviaire|chemin de fer|PLM|P\.?O\.?|PO-Midi|Orl[ée]ans)\b/iu';

    /**
     * Télécharge le jeu s'il manque (ou avec `$refresh`) après avoir vérifié sa licence et son éditeur.
     *
     * @return array{path: string, license: string, modified: ?string}
     *
     * @throws \RuntimeException jeu injoignable, licence ou éditeur inattendus
     */
    public function load(string $dir, bool $refresh = false): array
    {
        $dir = rtrim($dir, '/');
        $csv = $dir.'/merimee.csv';
        $metaFile = $dir.'/meta.json';
        if (! $refresh && is_file($csv) && filesize($csv) > 0 && is_file($metaFile)) {
            $meta = json_decode((string) file_get_contents($metaFile), true);
            if (is_array($meta) && isset($meta['license'])) {
                return ['path' => $csv, 'license' => (string) $meta['license'], 'modified' => $meta['modified'] ?? null];
            }
        }

        $http = Http::withUserAgent(self::USER_AGENT)->timeout(120);
        $meta = $http->get(self::DATASET_API);
        if (! $meta->ok()) {
            throw new \RuntimeException('data.gouv.fr a répondu une erreur pour le jeu Mérimée : rien lu.');
        }
        $license = (string) $meta->json('license');
        $publisher = (string) $meta->json('organization.name');
        if ($license !== 'lov2' || $publisher !== 'Ministère de la Culture') {
            throw new \RuntimeException("Licence ou éditeur inattendus pour Mérimée (« {$license} », « {$publisher} ») : rien lu.");
        }
        $modified = null;
        foreach ((array) $meta->json('resources') as $resource) {
            if (($resource['url'] ?? '') === self::CSV_URL) {
                $modified = substr((string) ($resource['last_modified'] ?? ''), 0, 10) ?: null;
            }
        }

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (! is_file($dir.'/.nobackup')) {
            touch($dir.'/.nobackup');
        }
        $tmp = $csv.'.part';
        $download = Http::withUserAgent(self::USER_AGENT)->timeout(600)->sink($tmp)->get(self::CSV_URL);
        if (! $download->ok() || ! is_file($tmp) || filesize($tmp) < 1_000_000) {
            throw new \RuntimeException('Le fichier Mérimée du ministère n\'a pas pu être téléchargé : rien lu.');
        }
        rename($tmp, $csv);
        file_put_contents($metaFile, json_encode(['license' => $license, 'publisher' => $publisher, 'modified' => $modified,
            'fetched_at' => now()->toDateTimeString(), 'url' => self::CSV_URL], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return ['path' => $csv, 'license' => $license, 'modified' => $modified];
    }

    /**
     * Notices de gare et d'ouvrage de gare du fichier, sans les autres monuments.
     *
     * @return list<array<string, mixed>> voir `record()`
     */
    public function railwayRecords(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Fichier Mérimée illisible : {$path}");
        }
        $header = fgetcsv($handle, 0, '|', '"', '');
        if (! is_array($header)) {
            fclose($handle);
            throw new \RuntimeException('Fichier Mérimée vide.');
        }
        $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF");
        $out = [];
        while (($cells = fgetcsv($handle, 0, '|', '"', '')) !== false) {
            if (count($cells) !== count($header)) {
                continue;
            }
            $row = array_combine($header, $cells);
            $record = self::record($row);
            if ($record !== null) {
                $out[] = $record;
            }
        }
        fclose($handle);

        return $out;
    }

    /**
     * Notice normalisée, ou null quand elle n'est ni une gare ni un ouvrage de gare, ou n'a plus de protection ni de label.
     *
     * Clés : `ref` (PA…), `title`, `denomination`, `element` (`building` | `works`), `commune`, `department`,
     * `lat`, `lng` (WGS 84, nuls si absents), `protection` (`classified` | `registered` | null), `protection_date`
     * (AAAA-MM-JJ), `scope` (`partial` | null), `designations` (liste `{kind, date}`), `architects` (liste
     * `{name, role}`), `built_from`, `built_to`, `built_period`.
     *
     * @param  array<string, string>  $row
     * @return array<string, mixed>|null
     */
    public static function record(array $row): ?array
    {
        $ref = trim($row['Reference'] ?? '');
        $element = self::element($row);
        if ($ref === '' || $element === null) {
            return null;
        }
        $designations = self::designations((string) ($row['Date_et_typologie_de_la_protection'] ?? ''));
        $label = trim((string) ($row['Date_de_Label'] ?? ''));
        if ($label !== '') {
            $designations[] = ['kind' => 'acr', 'date' => self::date($label)];
        }
        if ($designations === []) {
            return null;
        }
        $primary = null;
        foreach (['classified', 'registered'] as $kind) {
            foreach ($designations as $d) {
                if ($d['kind'] === $kind) {
                    $primary = $d;
                    break 2;
                }
            }
        }
        $coordinates = array_map('trim', explode(',', (string) ($row['coordonnees_au_format_WGS84'] ?? '')));
        $lat = count($coordinates) === 2 && is_numeric($coordinates[0]) ? (float) $coordinates[0] : null;
        $lng = count($coordinates) === 2 && is_numeric($coordinates[1]) ? (float) $coordinates[1] : null;
        [$from, $to] = self::years((string) ($row['Datation_de_l_edifice'] ?? ''));

        return [
            'ref' => $ref,
            'title' => trim((string) ($row['Titre_editorial_de_la_notice'] ?? '')),
            'denomination' => trim((string) ($row['Denomination_de_l_edifice'] ?? '')),
            'element' => $element,
            'commune' => trim((string) ($row['Commune_forme_editoriale'] ?? '')),
            'department' => trim((string) ($row['Departement_format_numerique'] ?? '')),
            'lat' => $lat,
            'lng' => $lng,
            'protection' => $primary['kind'] ?? null,
            'protection_date' => $primary['date'] ?? null,
            'scope' => $primary !== null && str_contains(mb_strtolower((string) ($row['Typologie_de_la_protection'] ?? '')), 'partiellement') ? 'partial' : null,
            'designations' => $designations,
            'architects' => self::architects((string) ($row['Auteur_de_l_edifice'] ?? '')),
            'built_from' => $from,
            'built_to' => $to,
            'built_period' => self::period((string) ($row['Siecle_de_la_campagne_principale_de_construction'] ?? '')),
        ];
    }

    /** `building` pour une gare, `works` pour un ouvrage de gare (remise, halle, marquise, rotonde), sinon null. */
    public static function element(array $row): ?string
    {
        $tokens = array_map(fn (string $t): string => mb_strtolower(trim($t)), explode(';', (string) ($row['Denomination_de_l_edifice'] ?? '')));
        $title = (string) ($row['Titre_editorial_de_la_notice'] ?? '');
        foreach ($tokens as $t) {
            if (in_array($t, self::NOT_RAIL, true)) {
                return null;
            }
        }
        if (in_array('gare', $tokens, true)) {
            return 'building';
        }
        if (in_array('remise ferroviaire', $tokens, true)) {
            return 'works';
        }
        if (preg_match(self::WORKS_TITLE, $title) === 1 && preg_match(self::RAIL_TITLE, $title) === 1
            && ! in_array('hôtel de voyageurs', $tokens, true)) {
            return 'works';
        }

        return null;
    }

    /**
     * Protections d'un champ « 1984/12/28 : inscrit MH ; 1913/02/15 : classé MH » ; les protections déclassées ou radiées
     * sont écartées.
     *
     * @return list<array{kind: string, date: ?string}>
     */
    public static function designations(string $field): array
    {
        $out = [];
        foreach (explode(';', $field) as $entry) {
            $entry = trim($entry);
            if ($entry === '' || preg_match('/d[ée]class|radi/iu', $entry) === 1) {
                continue;
            }
            $kind = str_contains(mb_strtolower($entry), 'classé') ? 'classified' : (str_contains(mb_strtolower($entry), 'inscrit') ? 'registered' : null);
            if ($kind !== null) {
                $out[] = ['kind' => $kind, 'date' => self::date($entry)];
            }
        }

        return $out;
    }

    /** Date AAAA/MM/JJ trouvée dans un texte, en AAAA-MM-JJ ; null sinon ou si elle n'existe pas. */
    public static function date(string $text): ?string
    {
        if (preg_match('/\b(\d{4})[\/-](\d{2})[\/-](\d{2})\b/', $text, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        return null;
    }

    /**
     * Auteurs « Laloux Victor (architecte);Varenne Henri (sculpteur) » : nom (« Victor Laloux » quand le nom est à deux
     * mots, « Nom Prénom » dans la base) et rôle.
     *
     * @return list<array{name: string, role: ?string}>
     */
    public static function architects(string $field): array
    {
        $out = [];
        foreach (explode(';', $field) as $entry) {
            $entry = trim($entry);
            if ($entry === '' || ! preg_match('/^(.+?)\s*\(([^)]+)\)$/u', $entry, $m)) {
                continue;
            }
            $name = trim($m[1]);
            $words = preg_split('/\s+/u', $name) ?: [];
            if (mb_strtoupper($name) === $name && mb_strlen($name) > 3) {
                // « BACHMANN » : majuscules de la base, mises en casse de nom propre.
                $name = mb_convert_case($name, MB_CASE_TITLE);
            } elseif (count($words) === 2 && ! str_contains($name, '.')) {
                $name = $words[1].' '.$words[0];
            }
            $out[] = ['name' => $name, 'role' => mb_strtolower(trim($m[2]))];
        }

        return $out;
    }

    /** Années de la datation « 1889;1898 » ou « 1925 » : début et fin (null si absente ou incohérente). */
    public static function years(string $field): array
    {
        preg_match_all('/\b(1[0-9]{3}|20[0-9]{2})\b/', $field, $m);
        $years = array_map('intval', $m[1]);
        if ($years === []) {
            return [null, null];
        }

        return [min($years), max($years) !== min($years) ? max($years) : null];
    }

    /** Siècle de la campagne principale : « 4e quart 19e siècle », sans les valeurs multiples. */
    public static function period(string $field): ?string
    {
        $field = trim(explode(';', $field)[0]);

        return $field !== '' ? mb_substr($field, 0, 60) : null;
    }
}
