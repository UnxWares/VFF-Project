<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Noms historiques des lignes, chacun avec sa source, dans `lines.alt_names.names`, et
 * sections nommées dans `line_sections`. Hors ligne : aucune requête réseau.
 *
 * Sources :
 *   1. Wikidata (cache de vff:import-wikidata-lines, storage/app/sources/wikidata/
 *      entities.json) pour les lignes rattachées à un item (alt_names.wikidata) : libellé
 *      français, titre de l'article Wikipédia, alias français, nom officiel P1448 avec ses
 *      dates (P580 / P582) ;
 *   2. database/data/lignes_noms_historiques.php : noms et sections curés à la main
 *      d'après Wikipédia (LGV Sud-Est = LGV Paris - Lyon = LN1, LGV Rhône-Alpes = LN4…).
 *
 * Pourquoi : les noms de la LGV Sud-Est avaient disparu de sa fiche. Aucune commande ne les
 * avait écrasés : ils vivaient dans `line_sections`, remplie par un seeder hors pipeline
 * (Database\Seeders\LineSectionsSeeder), jamais rejoué depuis la recréation de la base ;
 * et la fiche lisait `alt_names` comme une liste alors que c'est devenu un objet de
 * métadonnées. Cette commande, étape de vff:rebuild-history, les rend durables.
 *
 * Préservation : seule la clé `names` d'alt_names est réécrite (opérateur jsonb ||), les
 * autres clés (wikidata, name_source, lignes_oubliees, merged…) sont intactes ; les
 * sections sont mises à jour par (ligne, nom), jamais supprimées.
 */
class ImportLineNames extends Command
{
    protected $signature = 'vff:import-line-names
        {--line= : code RFN d\'une seule ligne}
        {--entities= : cache Wikidata (défaut : storage/app/sources/wikidata/entities.json)}
        {--data= : fichier des noms curés (défaut : database/data/lignes_noms_historiques.php)}
        {--dry-run : affiche sans écrire}';

    protected $description = 'Noms historiques des lignes (Wikidata en cache + noms curés) et sections nommées, avec leurs sources.';

    public function handle(): int
    {
        $entitiesPath = $this->option('entities') ?: storage_path('app/sources/wikidata/entities.json');
        $dataPath = $this->option('data') ?: database_path('data/lignes_noms_historiques.php');
        $curated = is_file($dataPath) ? require $dataPath : [];
        $entities = is_file($entitiesPath) ? (json_decode((string) file_get_contents($entitiesPath), true) ?: []) : [];
        if ($entities === []) {
            $this->warn("Cache Wikidata absent ou vide ({$entitiesPath}) : seuls les noms curés sont importés.");
        }

        $query = DB::table('lines')->select('id', 'code', 'name', 'alt_names');
        if ($code = $this->option('line')) {
            $query->where('code', $code);
        }
        $dry = (bool) $this->option('dry-run');
        $named = 0;
        $names = 0;
        $sections = 0;

        foreach ($query->orderBy('id')->cursor() as $line) {
            $alt = json_decode((string) $line->alt_names, true);
            $alt = is_array($alt) && ! array_is_list($alt) ? $alt : [];
            $list = [];
            $qid = $alt['wikidata'] ?? null;
            if (is_string($qid) && isset($entities[$qid]) && is_array($entities[$qid])) {
                $list = self::wikidataNames($entities[$qid], $qid);
            }
            foreach ($curated[$line->code ?? '']['names'] ?? [] as $n) {
                $list[] = ['name' => $n['name'], 'kind' => 'name', 'source' => 'wikipedia', 'source_url' => $n['source_url'] ?? null,
                    'from' => $n['from'] ?? null, 'to' => $n['to'] ?? null];
            }
            $list = self::unique($list, $line->name);

            if ($list !== ($alt['names'] ?? null) && ($list !== [] || isset($alt['names']))) {
                $named++;
                $names += count($list);
                if (! $dry) {
                    DB::update("UPDATE lines SET alt_names = COALESCE(CASE WHEN jsonb_typeof(alt_names) = 'object' THEN alt_names END, '{}'::jsonb)
                            || jsonb_build_object('names', CAST(? AS jsonb)), updated_at = NOW() WHERE id = ?",
                        [json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $line->id]);
                }
            }

            foreach ($curated[$line->code ?? '']['sections'] ?? [] as $s) {
                $sections++;
                if ($dry) {
                    continue;
                }
                $row = [
                    'alt_names' => json_encode(array_values($s['alt_names'] ?? []), JSON_UNESCAPED_UNICODE),
                    'pk_start_m' => (int) $s['pk_start_m'],
                    'pk_end_m' => (int) $s['pk_end_m'],
                    'opening_year_from' => $s['opening_year_from'] ?? null,
                    'opening_year_to' => $s['opening_year_to'] ?? null,
                    'description' => $s['description'] ?? null,
                    'source_url' => $s['source_url'] ?? null,
                    'updated_at' => now(),
                ];
                $existing = DB::table('line_sections')->where('line_id', $line->id)->where('name', $s['name'])->value('id');
                $existing
                    ? DB::table('line_sections')->where('id', $existing)->update($row)
                    : DB::table('line_sections')->insert($row + ['line_id' => $line->id, 'name' => $s['name'], 'created_at' => now()]);
            }
        }

        $this->info(($dry ? '[simulation] ' : '')."{$names} nom(s) sur {$named} ligne(s) mises à jour ; {$sections} section(s) nommée(s).");

        return self::SUCCESS;
    }

    /**
     * Noms français d'un item Wikidata : libellé, titre de l'article, alias, nom officiel
     * (P1448, avec ses dates de début et de fin).
     *
     * @return list<array{name: string, kind: string, source: string, source_url: string, from: ?int, to: ?int}>
     */
    public static function wikidataNames(array $entity, string $qid): array
    {
        $url = "https://www.wikidata.org/wiki/{$qid}";
        $out = [];
        $add = function (?string $name, ?int $from = null, ?int $to = null, string $prop = '') use (&$out, $url) {
            if (is_string($name) && trim($name) !== '') {
                $out[] = ['name' => trim($name), 'kind' => 'name', 'source' => 'wikidata', 'source_url' => $url.$prop, 'from' => $from, 'to' => $to];
            }
        };
        foreach (is_array($entity['claims']['P1448'] ?? null) ? $entity['claims']['P1448'] : [] as $st) {
            $v = $st['mainsnak']['datavalue']['value'] ?? null;
            if (! is_array($v) || ($v['language'] ?? null) !== 'fr' || ($st['rank'] ?? 'normal') === 'deprecated') {
                continue;
            }
            $add($v['text'] ?? null, self::qualifierYear($st, 'P580'), self::qualifierYear($st, 'P582'), '#P1448');
        }
        $add($entity['labels']['fr']['value'] ?? null);
        $add($entity['sitelinks']['frwiki']['title'] ?? null);
        foreach (is_array($entity['aliases']['fr'] ?? null) ? $entity['aliases']['fr'] : [] as $a) {
            $add(is_array($a) ? ($a['value'] ?? null) : null);
        }

        return $out;
    }

    private static function qualifierYear(array $statement, string $prop): ?int
    {
        $time = $statement['qualifiers'][$prop][0]['datavalue']['value']['time'] ?? null;

        return is_string($time) && preg_match('/^[+-](\d{4})/', $time, $m) ? (int) $m[1] : null;
    }

    /**
     * Sans doublon (casse, accents, ponctuation et « St / Saint » ignorés) ni le nom
     * principal ; à nom égal, l'entrée la plus datée l'emporte.
     *
     * @param  list<array<string, mixed>>  $names
     * @return list<array<string, mixed>>
     */
    public static function unique(array $names, string $lineName): array
    {
        $key = fn (string $n) => preg_replace(['/\bste\b/', '/\bst\b/', '/[^a-z0-9]+/'], ['sainte', 'saint', ''], Str::lower(Str::ascii($n)));
        $main = $key($lineName);
        $out = [];
        foreach ($names as $n) {
            $n['name'] = trim((string) preg_replace(['/\s*[—–]\s*/u', '/--/'], [' - ', '-'], $n['name']));
            $k = $key($n['name']);
            if ($k === '' || $k === $main) {
                continue;
            }
            $dated = fn ($x) => ($x['from'] ?? null) !== null || ($x['to'] ?? null) !== null;
            if (! isset($out[$k]) || (! $dated($out[$k]) && $dated($n))) {
                $out[$k] = $n;
            }
        }

        return array_values($out);
    }
}
