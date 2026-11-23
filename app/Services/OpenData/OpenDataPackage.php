<?php

namespace App\Services\OpenData;

use RuntimeException;
use ZipArchive;

/**
 * Mise en forme d'un jeu de données ouvert déjà écrit dans son dossier : LISEZMOI.txt (français puis
 * anglais), LICENCE.txt (licence, crédits des sources citées, texte officiel de l'ODbL 1.0), archive
 * ZIP déterministe (dates des fichiers fixées au jour du jeu) et entrée du catalogue.
 *
 * Les textes nomment les fournisseurs CITÉS et comptent ce qui a été écarté, sans nommer les sources
 * écartées.
 */
final class OpenDataPackage
{
    public const README = 'LISEZMOI.txt';

    public const LICENCE_FILE = 'LICENCE.txt';

    public const SITE = 'https://vff-project.org';

    /** Description des fichiers : nom => [français, anglais]. */
    public const FILES = [
        'lignes.csv' => ['Une ligne par ligne de chemin de fer présente à l\'ère, avec ses totaux.', 'One row per railway line present in the era, with totals.'],
        'lignes.geojson' => ['Mêmes lignes, géométrie MultiLineString réunissant les sections exportées.', 'Same lines, MultiLineString geometry built from the exported sections.'],
        'sections.csv' => ['Une ligne par section (portion de ligne dans un état homogène) à l\'ère.', 'One row per section (line portion in a uniform state) in the era.'],
        'sections.geojson' => ['Mêmes sections, géométrie LineString ou MultiLineString.', 'Same sections, LineString or MultiLineString geometry.'],
        'gares.csv' => ['Une ligne par gare ou halte présente à l\'ère, avec sa position (lat, lon, WGS 84).', 'One row per station or halt present in the era, with its position (lat, lon, WGS 84).'],
        'gares.geojson' => ['Mêmes gares, géométrie Point.', 'Same stations, Point geometry.'],
        'evenements.csv' => ['Événements datés des lignes (ouvertures, fermetures, électrifications…), sans texte.', 'Dated line events (openings, closures, electrifications…), without text.'],
        'accidents.csv' => ['Accidents publiés par VFF : date, lieu, nature, bilan, sources. Aucun résumé.', 'Accidents published by VFF: date, place, kind, toll, sources. No summary.'],
        'materiel.csv' => ['Séries de matériel roulant : constructeur, exploitants, années, caractéristiques.', 'Rolling stock series: builder, operators, years, characteristics.'],
    ];

    /** Champs communs expliqués dans le LISEZMOI : champ => [français, anglais]. */
    public const FIELDS = [
        'era' => ['décennie de la carte (1830 à 2020) ; 2030 désigne l\'état du réseau au jour de l\'export', 'map decade (1830 to 2020); 2030 is the state of the network on the export day'],
        'status' => ['état à l\'ère (in_service, abandoned, dismantled, razed… ; pour les gares : active, tourist, no_commercial, declassified)', 'state in the era (in_service, abandoned, dismantled, razed…; stations: active, tourist, no_commercial, declassified)'],
        'pk_start_m, pk_end_m' => ['points kilométriques en mètres', 'kilometre posts in metres'],
        '*_on' => ['dates au format AAAA-MM-JJ ; *_precision indique si seul le mois ou l\'année est sûr', 'dates as YYYY-MM-DD; *_precision tells whether only the month or the year is known'],
        '*_source, sources' => ['fournisseur de la donnée (clé du registre des droits : osm, sncf, ign, wikidata, wikipedia, vff…)', 'provider of the value (key of the rights register: osm, sncf, ign, wikidata, wikipedia, vff…)'],
        'geometry_status' => ['original (tracé de la source de la section), replaced (tracé d\'une source libre à moins de 15 m), withheld (aucun tracé libre : géométrie absente)', 'original (trace of the section source), replaced (trace of an open source within 15 m), withheld (no open trace: no geometry)'],
    ];

    /** Éléments écartés ou remplacés : clé du registre de fabrication => [français, anglais]. */
    public const ITEMS = [
        'sections.geometry' => ['géométries de sections', 'section geometries'],
        'sections.gauge' => ['écartements de sections', 'section gauges'],
        'lines' => ['lignes créées d\'après un tracé non libre', 'lines created from a non-open trace'],
        'lines.name' => ['noms de lignes', 'line names'],
        'stations.name' => ['noms de gares', 'station names'],
        'stations.geometry' => ['positions de gares', 'station positions'],
        'stations.opened_on' => ['dates d\'ouverture de gares', 'station opening dates'],
        'stations.closed_on' => ['dates de fermeture de gares', 'station closing dates'],
        'events' => ['événements datés', 'dated events'],
        'accidents' => ['accidents', 'accidents'],
        'accidents.location' => ['positions d\'accidents', 'accident positions'],
        'accidents.deaths' => ['nombres de morts', 'death tolls'],
        'accidents.injuries' => ['nombres de blessés', 'injury counts'],
        'accidents.kind' => ['natures d\'accidents', 'accident kinds'],
        'accidents.name' => ['noms d\'accidents', 'accident names'],
        'accidents.sources' => ['sources citées d\'accidents', 'cited accident sources'],
        'materials' => ['séries de matériel', 'rolling stock series'],
    ];

    public const REASONS = [
        OpenDataRights::NOT_OPEN => ['source sans licence ouverte écrite', 'source without a written open licence'],
        OpenDataRights::NON_COMMERCIAL => ['source réservée à un usage non commercial', 'source restricted to non-commercial use'],
        OpenDataRights::UNKNOWN_SOURCE => ['source absente du registre des droits', 'source missing from the rights register'],
        'snapped_to_track' => ['position recalée sur un tracé non libre, remplacée par la position de sa source', 'position snapped onto a non-open trace, replaced by its source position'],
    ];

    public function __construct(private readonly OpenDataRights $rights) {}

    /**
     * Écrit LISEZMOI et LICENCE, contrôle les droits, construit l'archive et rend l'entrée du catalogue.
     *
     * @param  array{id: string, kind: string, era: ?int, title_fr: string, title_en: string}  $meta
     * @return array<string, mixed>
     *
     * @throws OpenDataLicenceViolation
     */
    public function finish(string $root, array $meta, OpenDataLedger $ledger, string $date): array
    {
        $id = $meta['id'];
        $dir = "{$root}/{$id}";
        $providers = $ledger->providers();
        // Contrôle final : aucune source non autorisée ne sort, sinon rien n'est publié pour ce jeu.
        $this->rights->assertAllowed($id, $providers);
        $credits = $this->rights->attribution($providers);

        $this->write("{$dir}/".self::README, $this->readme($meta, $ledger, $credits, $date));
        $this->write("{$dir}/".self::LICENCE_FILE, $this->licence($meta, $credits, $date));

        $names = array_keys($ledger->rows());
        $names[] = self::README;
        $names[] = self::LICENCE_FILE;
        sort($names);
        $files = [];
        foreach ($names as $name) {
            $path = "{$dir}/{$name}";
            $files[] = ['name' => $name, 'bytes' => (int) filesize($path), 'rows' => $ledger->rows()[$name] ?? null, 'sha256' => (string) hash_file('sha256', $path)];
        }
        $archive = $this->zip($root, $id, $names, $date);

        return [
            'id' => $id,
            'kind' => $meta['kind'],
            'era' => $meta['era'],
            'generated_on' => $date,
            'licence' => OpenDataRights::LICENCE,
            'licence_url' => OpenDataRights::LICENCE_URL,
            'commercial_reuse' => $this->rights->commercial(),
            'archive' => $archive,
            'files' => $files,
            'credits' => $credits,
            'withheld' => $ledger->withheld(),
            'removed' => $ledger->total(OpenDataLedger::REMOVED),
            'replaced' => $ledger->total(OpenDataLedger::REPLACED),
        ];
    }

    /**
     * @param  array{id: string, kind: string, era: ?int, title_fr: string, title_en: string}  $meta
     * @param  list<array{key: string, name: string, licence: string, url: ?string}>  $credits
     */
    public function readme(array $meta, OpenDataLedger $ledger, array $credits, string $date): string
    {
        $t = [];
        $t[] = "VFF - Voies Ferrées de France : {$meta['title_fr']}";
        $t[] = str_repeat('=', mb_strlen(end($t)));
        $t[] = '';
        $t[] = "Jeu : {$meta['id']}. Données extraites le {$date} de l'encyclopédie VFF ({$this->site()}).";
        $t[] = 'Licence : '.OpenDataRights::LICENCE_NAME.' ('.OpenDataRights::LICENCE_URL.'), réutilisation commerciale permise.';
        $t[] = 'Attribution demandée : « VFF - Voies Ferrées de France, '.$this->site().' » et les crédits du fichier LICENCE.txt.';
        $t[] = 'Partage à l\'identique : une base dérivée publiée doit l\'être sous ODbL 1.0.';
        $t[] = '';
        $t[] = 'Fichiers';
        $t[] = '========';
        foreach ($ledger->rows() as $file => $rows) {
            $t[] = "- {$file} ({$rows} ".($rows > 1 ? 'éléments' : 'élément').') : '.(self::FILES[$file][0] ?? '');
        }
        $t[] = 'CSV : UTF-8, virgule, guillemets doubles, en-tête en première ligne. GeoJSON : RFC 7946, WGS 84 (EPSG:4326).';
        $t[] = 'Un texte qui commence par =, +, - ou @ est précédé d\'une apostrophe pour qu\'un tableur ne le lise pas comme une formule.';
        $t[] = '';
        $t[] = 'Champs';
        $t[] = '======';
        foreach (self::FIELDS as $field => [$fr]) {
            $t[] = "- {$field} : {$fr}";
        }
        $t[] = '';
        $t[] = 'Provenance et licences';
        $t[] = '======================';
        $t[] = 'Chaque donnée garde la clé de son fournisseur. N\'entrent que les sources à licence ouverte écrite et vérifiée,';
        $t[] = 'compatible avec un usage commercial (registre des droits de VFF). Les textes (descriptions, résumés, citations)';
        $t[] = 'ne sont pas repris. Ce qui vient d\'une autre source est retiré, ou remplacé par la meilleure donnée libre disponible :';
        $t = [...$t, ...$this->withheldLines($ledger, 0)];
        $t[] = '';
        $t[] = 'Sources citées : '.implode(', ', array_map(fn (array $c): string => $c['name'], $credits)).'. Détail dans LICENCE.txt.';
        $t[] = '';
        $t[] = '';
        $t[] = "VFF - French railway lines: {$meta['title_en']}";
        $t[] = str_repeat('=', mb_strlen(end($t)));
        $t[] = '';
        $t[] = "Dataset {$meta['id']}, extracted on {$date} from the VFF encyclopedia ({$this->site()}).";
        $t[] = 'Licence: '.OpenDataRights::LICENCE_NAME.' ('.OpenDataRights::LICENCE_URL.'), commercial reuse allowed.';
        $t[] = 'Attribution: "VFF - Voies Ferrées de France, '.$this->site().'" and the credits listed in LICENCE.txt.';
        $t[] = 'Share-alike: a derived database made public must be released under ODbL 1.0.';
        $t[] = '';
        foreach ($ledger->rows() as $file => $rows) {
            $t[] = "- {$file} ({$rows}): ".(self::FILES[$file][1] ?? '');
        }
        $t[] = 'CSV: UTF-8, comma, double quotes, header row. GeoJSON: RFC 7946, WGS 84 (EPSG:4326).';
        $t[] = '';
        foreach (self::FIELDS as $field => [, $en]) {
            $t[] = "- {$field}: {$en}";
        }
        $t[] = '';
        $t[] = 'Only sources with a written, checked open licence that allows commercial use are included. Texts are not included.';
        $t[] = 'Anything from another source was removed or replaced by the best open value available:';
        $t = [...$t, ...$this->withheldLines($ledger, 1)];
        $t[] = '';

        return implode("\n", $t)."\n";
    }

    /**
     * @param  array{id: string, kind: string, era: ?int, title_fr: string, title_en: string}  $meta
     * @param  list<array{key: string, name: string, licence: string, url: ?string}>  $credits
     */
    public function licence(array $meta, array $credits, string $date): string
    {
        $t = [];
        $t[] = "VFF - Voies Ferrées de France, jeu {$meta['id']} ({$date})";
        $t[] = '';
        $t[] = 'Ce jeu de données est mis à disposition sous la licence '.OpenDataRights::LICENCE_NAME.'.';
        $t[] = 'This dataset is made available under the '.OpenDataRights::LICENCE_NAME.'.';
        $t[] = OpenDataRights::LICENCE_URL;
        $t[] = '';
        $t[] = 'Producteur / Producer : UnxWares SAS pour VFF - Voies Ferrées de France, '.$this->site();
        $t[] = '';
        $t[] = 'Crédits des sources / Source credits';
        $t[] = '====================================';
        foreach ($credits as $c) {
            $t[] = "- {$c['name']} : {$c['licence']}".($c['url'] !== null ? " ({$c['url']})" : '');
        }
        $t[] = '';
        $t[] = 'Mentions à reprendre / Required notices :';
        foreach ($this->notices($credits) as $notice) {
            $t[] = "- {$notice}";
        }
        $t[] = '';
        $t[] = 'Texte officiel de la licence / Official licence text';
        $t[] = '====================================================';
        $t[] = '';
        $text = @file_get_contents(resource_path('content/open-data/odbl-1.0.txt'));
        $t[] = $text !== false ? rtrim($text) : OpenDataRights::LICENCE_URL;

        return implode("\n", $t)."\n";
    }

    /**
     * Mentions exigées par les licences des fournisseurs cités.
     *
     * @param  list<array{key: string, name: string, licence: string, url: ?string}>  $credits
     * @return list<string>
     */
    private function notices(array $credits): array
    {
        $known = [
            'osm' => '© contributeurs OpenStreetMap / OpenStreetMap contributors, ODbL 1.0, https://www.openstreetmap.org/copyright',
            'sncf' => 'SNCF Réseau, SNCF Gares & Connexions, SNCF Voyageurs (SNCF Open Data), ODbL 1.0',
            'ign' => 'IGN - BD TOPO, BD ORTHO historique, RGE ALTI, Licence Ouverte 2.0 (Etalab)',
            'wikidata' => 'Wikidata, CC0 1.0',
            'ohm' => 'OpenHistoricalMap, CC0 1.0',
            'wikipedia' => 'Wikipédia (faits relevés, chaque lien pointe vers la révision lue), textes sous CC BY-SA 4.0',
        ];
        $out = [];
        foreach ($credits as $c) {
            $out[] = $known[$c['key']] ?? "{$c['name']}, {$c['licence']}";
        }
        $out[] = 'VFF - Voies Ferrées de France, '.$this->site().', ODbL 1.0';

        return $out;
    }

    /** @return list<string> */
    private function withheldLines(OpenDataLedger $ledger, int $lang): array
    {
        $out = [];
        foreach ($ledger->withheld() as $item => $actions) {
            foreach ($actions as $action => $reasons) {
                foreach ($reasons as $reason => $n) {
                    $label = self::ITEMS[$item][$lang] ?? (str_starts_with($item, 'materials.')
                        ? ($lang === 0 ? 'champ ' : 'field ').substr($item, 10).($lang === 0 ? ' du matériel' : ' of rolling stock')
                        : $item);
                    $verb = $action === OpenDataLedger::REPLACED ? ['remplacé(s)', 'replaced'][$lang] : ['retiré(s)', 'removed'][$lang];
                    $why = self::REASONS[$reason][$lang] ?? $reason;
                    $out[] = "  - {$label} : {$n} {$verb} ({$why})";
                }
            }
        }

        return $out === [] ? [$lang === 0 ? '  - rien n\'a été retiré ni remplacé.' : '  - nothing was removed or replaced.'] : $out;
    }

    /**
     * Archive ZIP déterministe du jeu : fichiers triés, date de modification fixée au jour du jeu.
     *
     * @param  list<string>  $names
     * @return array{name: string, bytes: int, sha256: string}
     */
    private function zip(string $root, string $id, array $names, string $date): array
    {
        $final = "{$root}/{$id}.zip";
        $tmp = "{$root}/.{$id}.zip.tmp";
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Impossible de créer {$tmp}.");
        }
        $mtime = (int) strtotime("{$date} 00:00:00 UTC");
        foreach ($names as $name) {
            $entry = "{$id}/{$name}";
            $zip->addFile("{$root}/{$id}/{$name}", $entry);
            $zip->setMtimeName($entry, $mtime);
            $zip->setCompressionName($entry, ZipArchive::CM_DEFLATE);
        }
        $zip->close();
        if (! rename($tmp, $final)) {
            throw new RuntimeException("Impossible de remplacer {$final}.");
        }

        return ['name' => "{$id}.zip", 'bytes' => (int) filesize($final), 'sha256' => (string) hash_file('sha256', $final)];
    }

    private function write(string $path, string $content): void
    {
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException("Impossible d'écrire {$path}.");
        }
    }

    private function site(): string
    {
        return self::SITE;
    }
}
