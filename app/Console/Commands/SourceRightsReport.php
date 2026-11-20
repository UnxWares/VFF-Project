<?php

namespace App\Console\Commands;

use App\Support\SourceRights;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Droits des sources (registre database/data/source_rights.php, cf. App\Support\SourceRights).
 * LECTURE SEULE : ne retire ni ne modifie aucune donnée.
 *
 *   php artisan vff:source-rights            # fournisseurs, statut, licence, preuve
 *   php artisan vff:source-rights --check    # toute provenance présente en base est déclarée (sinon code 1)
 *   php artisan vff:source-rights --impact   # ce que la carte perdrait sans les sources INCERTAINES
 *   php artisan vff:source-rights --impact --status=incertain --json=storage/app/reports/sources-incertaines.json
 *
 * Mesure --impact (sources du statut demandé, « incertain » par défaut) :
 *   - événements datés (line_events), y compris corrections manuelles et patrimoine qui citent ces sources,
 *     et lignes dont TOUS les événements en viennent ;
 *   - tracé de référence : km DÉFINIS par ces sources (la portion existe parce qu'elles la donnent,
 *     même recalée sur OSM ou BD TOPO) et km DESSINÉS par elles (aucune géométrie précise) ;
 *     lignes dont tout le tracé en vient ;
 *   - par ère : km en service définis / dessinés par ces sources (line_segments) ;
 *   - gares qui n'existent que par ces sources, noms de lignes qui en viennent.
 */
class SourceRightsReport extends Command
{
    protected $signature = 'vff:source-rights
        {--check : Vérifie que chaque provenance présente en base est déclarée dans le registre}
        {--impact : Mesure ce que la carte perdrait sans les sources du statut --status}
        {--status=incertain : Statut mesuré par --impact (incertain, interdit, sur)}
        {--json= : Écrit les mesures --impact dans ce fichier JSON}';

    protected $description = 'Droits des sources : registre, contrôle des provenances, mesure de dépendance (lecture seule).';

    /**
     * Provenances lues en base : clé du registre => requête SQL des valeurs distinctes. Chaque table du
     * registre (sources et composites) y a sa requête : SourceRightsCommandTest échoue sinon. Les tables
     * sans colonne de provenance (passages à niveau, signalisation actuelle) viennent toutes de SNCF
     * Réseau : leur requête donne la valeur que l'API sert pour elles (Provenance::for(…, 'sncf')) dès
     * qu'elles ont une ligne.
     */
    public const PROVENANCE_QUERIES = [
        'line_events' => 'SELECT DISTINCT source AS v FROM line_events WHERE source IS NOT NULL',
        'source_traces' => 'SELECT DISTINCT source AS v FROM source_traces',
        'line_reference_parts' => 'SELECT DISTINCT source AS v FROM line_reference_parts',
        'line_segments' => 'SELECT DISTINCT source AS v FROM line_segments WHERE source IS NOT NULL',
        'station_sources' => 'SELECT DISTINCT source AS v FROM station_sources',
        'source_stations' => 'SELECT DISTINCT source AS v FROM source_stations',
        'station_events' => 'SELECT DISTINCT source AS v FROM station_events',
        'line_sources' => 'SELECT DISTINCT source_type AS v FROM line_sources',
        'media' => 'SELECT DISTINCT provider AS v FROM media',
        'station_services' => "SELECT DISTINCT e->>'feed' AS v FROM station_services, jsonb_array_elements(evidence) AS e",
        'lines.name_source' => "SELECT DISTINCT alt_names->>'name_source' AS v FROM lines WHERE alt_names->>'name_source' IS NOT NULL",
        'materials' => 'SELECT DISTINCT source AS v FROM materials WHERE source IS NOT NULL',
        'materials.fields' => "SELECT DISTINCT p.value->>'source' AS v FROM materials, jsonb_each(COALESCE(provenance, '{}'::jsonb)) AS p",
        'materials.image' => "SELECT DISTINCT image->>'provider' AS v FROM materials WHERE image IS NOT NULL",
        'line_materials' => 'SELECT DISTINCT source AS v FROM line_materials WHERE source IS NOT NULL',
        'line_material_periods' => 'SELECT DISTINCT source AS v FROM line_material_periods',
        'accident_sources' => 'SELECT DISTINCT provider AS v FROM accident_sources',
        'line_profiles' => 'SELECT DISTINCT source AS v FROM line_profiles',
        'line_greenways' => 'SELECT DISTINCT source AS v FROM line_greenways',
        'sncf_level_crossings' => "SELECT DISTINCT 'sncf' AS v FROM sncf_level_crossings",
        'line_signalling' => "SELECT DISTINCT 'sncf' AS v FROM line_signalling",
        'line_signalling_events' => 'SELECT DISTINCT source AS v FROM line_signalling_events',
        'line_archive_documents' => 'SELECT DISTINCT source AS v FROM line_archive_documents',
    ];

    public function handle(): int
    {
        $rights = new SourceRights;

        if ($this->option('check')) {
            return $this->check($rights);
        }
        if ($this->option('impact')) {
            return $this->impact($rights, (string) $this->option('status'));
        }

        $this->table(['clé', 'source', 'statut', 'licence', 'preuve'], collect($rights->providers())->map(fn (array $p, string $k) => [
            $k, $p['nom'] ?? '', $p['statut'] ?? '?', mb_strimwidth((string) ($p['licence'] ?? ''), 0, 60, '…'), $p['preuve'][0] ?? '',
        ])->values()->all());

        return self::SUCCESS;
    }

    /** Registre cohérent, et toute valeur de provenance présente en base déclarée. */
    private function check(SourceRights $rights): int
    {
        $errors = self::registryErrors($rights);
        foreach (self::PROVENANCE_QUERIES as $table => $sql) {
            foreach (DB::select($sql) as $row) {
                if (! $rights->knows($table, (string) $row->v)) {
                    $errors[] = "{$table} : provenance « {$row->v} » absente du registre database/data/source_rights.php";
                }
            }
        }
        foreach ($errors as $e) {
            $this->error($e);
        }
        if ($errors) {
            return self::FAILURE;
        }
        $this->info('Registre des droits cohérent ; toutes les provenances présentes en base sont déclarées.');

        return self::SUCCESS;
    }

    /** @return list<string> incohérences du registre lui-même */
    public static function registryErrors(SourceRights $rights): array
    {
        $errors = [];
        foreach ($rights->providers() as $key => $p) {
            if (! in_array($p['statut'] ?? null, SourceRights::STATUSES, true)) {
                $errors[] = "fournisseur {$key} : statut « ".($p['statut'] ?? '').' » inconnu';
            }
            foreach (['nom', 'licence', 'obligations'] as $field) {
                if (trim((string) ($p[$field] ?? '')) === '') {
                    $errors[] = "fournisseur {$key} : champ {$field} vide";
                }
            }
            $proofs = $p['preuve'] ?? [];
            if (! is_array($proofs) || $proofs === [] || array_filter($proofs, fn ($u) => ! preg_match('#^https?://#', (string) $u))) {
                $errors[] = "fournisseur {$key} : preuve absente ou lien invalide";
            }
            // Une source n'est SÛRE que sur une licence écrite : jamais sur un accord oral.
            if (($p['statut'] ?? null) === SourceRights::SAFE && ($p['accord'] ?? null) === 'oral') {
                $errors[] = "fournisseur {$key} : SÛR sur la foi d'un accord oral";
            }
        }
        foreach ([$rights->sources(), $rights->composites()] as $maps) {
            foreach ($maps as $table => $map) {
                foreach ($map as $value => $provider) {
                    if ($rights->provider($provider) === null) {
                        $errors[] = "{$table}.{$value} : fournisseur « {$provider} » inconnu";
                    }
                }
            }
        }

        return $errors;
    }

    private function impact(SourceRights $rights, string $status): int
    {
        if (! in_array($status, SourceRights::STATUSES, true)) {
            $this->error("Statut inconnu : {$status}");

            return self::FAILURE;
        }
        $providers = $rights->providersWithStatus($status);
        $this->info("Sources « {$status} » : ".($providers ? implode(', ', $providers) : 'aucune'));
        $out = ['statut' => $status, 'fournisseurs' => $providers, 'mesure_le' => now()->toDateTimeString()];

        // 1. Événements datés.
        $ev = $rights->sqlCondition('line_events', $status);
        $out['evenements'] = [
            'total' => (int) DB::scalar('SELECT COUNT(*) FROM line_events'),
            'concernes' => (int) DB::scalar("SELECT COUNT(*) FROM line_events WHERE {$ev}"),
            'par_source' => collect(DB::select("SELECT source, COUNT(*) AS n FROM line_events WHERE {$ev} GROUP BY source ORDER BY n DESC"))
                ->mapWithKeys(fn ($r) => [$r->source => (int) $r->n])->all(),
            'lignes_sans_autre_date' => (int) DB::scalar("SELECT COUNT(*) FROM (SELECT line_id FROM line_events GROUP BY line_id
                HAVING BOOL_AND({$ev})) x"),
        ];

        // 2. Tracé de référence (définis = toute portion de la source ; dessinés = sans géométrie précise).
        $parts = $rights->sqlCondition('line_reference_parts', $status, 'p.source', null);
        $drawn = "({$parts} AND (p.matched_source IS NULL OR p.matched_source = 'connector'))";
        $r = DB::selectOne("SELECT COALESCE(SUM(length_m), 0) AS tot,
                COALESCE(SUM(length_m) FILTER (WHERE {$parts}), 0) AS def,
                COALESCE(SUM(length_m) FILTER (WHERE {$drawn}), 0) AS drawn
            FROM line_reference_parts p");
        $lines = DB::selectOne("SELECT COUNT(*) FILTER (WHERE all_def) AS def, COUNT(*) FILTER (WHERE all_drawn) AS drawn, COUNT(*) AS tot
            FROM (SELECT p.line_id, BOOL_AND({$parts}) AS all_def, BOOL_AND({$drawn}) AS all_drawn
                  FROM line_reference_parts p GROUP BY p.line_id) x");
        $out['trace_reference'] = [
            'km_total' => round($r->tot / 1000, 1), 'km_definis' => round($r->def / 1000, 1), 'km_dessines' => round($r->drawn / 1000, 1),
            'lignes_avec_trace' => (int) $lines->tot, 'lignes_definies_seulement_par_elles' => (int) $lines->def,
            'lignes_dessinees_seulement_par_elles' => (int) $lines->drawn,
        ];

        // 3. Par ère : km en service définis / dessinés (géométrie du tronçon dans le dessin non recalé, tampon 1 m).
        $seg = $rights->sqlCondition('line_segments', $status, 's.source', null);
        $rows = DB::select("WITH raw AS (
                SELECT p.line_id, ST_Buffer(ST_Union(ST_Transform(p.geom, 2154)), 1.0, 'quad_segs=2') AS g
                FROM line_reference_parts p WHERE {$drawn} GROUP BY p.line_id
            )
            SELECT e.year,
                   SUM(s.length_meters) FILTER (WHERE s.status = 'in_service') AS svc,
                   SUM(s.length_meters) FILTER (WHERE s.status = 'in_service' AND {$seg}) AS def,
                   SUM(CASE WHEN s.status = 'in_service' AND raw.g IS NOT NULL AND {$seg}
                            THEN s.length_meters * LEAST(1.0, ST_Length(ST_Intersection(ST_Transform(s.geom, 2154), raw.g))
                                 / GREATEST(ST_Length(ST_Transform(s.geom, 2154)), 0.001)) ELSE 0 END) AS drawn
            FROM line_segments s JOIN eras e ON e.id = s.era_id
            LEFT JOIN raw ON raw.line_id = s.line_id
            GROUP BY e.year ORDER BY e.year");
        $out['eres'] = collect($rows)->map(fn ($r) => [
            'ere' => (int) $r->year, 'km_en_service' => round(($r->svc ?? 0) / 1000), 'km_definis' => round(($r->def ?? 0) / 1000),
            'km_dessines' => round(($r->drawn ?? 0) / 1000),
        ])->all();

        // 4. Gares et noms.
        $st = $rights->sqlCondition('station_sources', $status, 'ss.source', null);
        $out['gares_seulement_par_elles'] = (int) DB::scalar("SELECT COUNT(*) FROM (SELECT ss.station_id FROM station_sources ss
            WHERE ss.station_id IS NOT NULL GROUP BY ss.station_id HAVING BOOL_AND({$st})) x");
        $names = $rights->sqlCondition('lines.name_source', $status, "alt_names->>'name_source'", null);
        $out['noms_de_ligne'] = (int) DB::scalar("SELECT COUNT(*) FROM lines WHERE {$names}");

        $this->table(['mesure', 'valeur'], [
            ['événements (sur '.$out['evenements']['total'].')', $out['evenements']['concernes']],
            ['lignes dont toutes les dates en viennent', $out['evenements']['lignes_sans_autre_date']],
            ['tracé de référence : km définis / km total', $out['trace_reference']['km_definis'].' / '.$out['trace_reference']['km_total']],
            ['tracé de référence : km dessinés (sans géométrie précise)', $out['trace_reference']['km_dessines']],
            ['lignes définies seulement par elles', $out['trace_reference']['lignes_definies_seulement_par_elles']],
            ['lignes dessinées seulement par elles (sans tracé sûr)', $out['trace_reference']['lignes_dessinees_seulement_par_elles']],
            ['gares qui n\'existent que par elles', $out['gares_seulement_par_elles']],
            ['noms de ligne qui en viennent', $out['noms_de_ligne']],
        ]);
        $this->table(['ère', 'km en service', 'définis par elles', 'dessinés par elles'],
            array_map(fn ($e) => [$e['ere'], $e['km_en_service'], $e['km_definis'], $e['km_dessines']], $out['eres']));

        if ($file = $this->option('json')) {
            file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
            $this->line("Mesures écrites dans {$file}");
        }

        return self::SUCCESS;
    }
}
