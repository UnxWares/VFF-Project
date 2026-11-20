<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Donne un vrai nom à chaque ligne RFN encore anonyme (« Ligne 830000 »).
 *
 * Cascade, de la source la plus fiable à la moins fiable :
 *   1. SNCF Réseau `mode-de-cantonnement-des-lignes` (lib_ligne officiel),
 *      via vff:backfill-line-names-sncf ;
 *   2. Wikidata (CC0) : items « ligne de chemin de fer » portant le code
 *      à 6 chiffres (P1671), libellé français ;
 *   3. nom DÉRIVÉ des gares SNCF de la ligne (liste-des-gares) : gares de
 *      plus petit et plus grand PK → « Ligne de X à Y » (« Raccordement de
 *      X à Y » pour un raccordement) ;
 *   4. à défaut, gare SNCF la plus proche du tracé : « Raccordement près de X ».
 *
 * La provenance du nom est notée dans lines.alt_names->name_source
 * (sncf | wikidata | derived_stations | derived_nearest) pour la curation.
 */
class NameLines extends Command
{
    protected $signature = 'vff:name-lines {--skip-sncf : ne pas relancer l\'étape SNCF}';

    protected $description = 'Nomme toutes les lignes anonymes (SNCF, Wikidata, puis dérivé des gares).';

    private const ANONYMOUS = "(name ~ '^Ligne [0-9 ]+$' OR name ~ '^[0-9 ]+$')";

    public function handle(): int
    {
        if (! $this->option('skip-sncf')) {
            $this->call('vff:backfill-line-names-sncf');
            DB::statement("UPDATE lines SET alt_names = COALESCE(alt_names, '{}'::jsonb) || '{\"name_source\": \"sncf\"}'
                WHERE code IS NOT NULL AND NOT ".self::ANONYMOUS." AND (alt_names->>'name_source') IS NULL");
        }

        $this->info('→ Wikidata (P1671, lignes de chemin de fer)…');
        $byCode = $this->wikidataNames();
        $wd = 0;
        foreach (DB::select('SELECT id, code FROM lines WHERE code IS NOT NULL AND '.self::ANONYMOUS) as $line) {
            if ($name = $byCode[$this->normalizeCode($line->code)] ?? null) {
                $this->rename($line->id, $name, 'wikidata');
                $wd++;
            }
        }
        $this->line("  {$wd} lignes nommées depuis Wikidata");

        $this->info('→ Noms dérivés des gares SNCF de la ligne…');
        $derived = 0;
        foreach (DB::select('SELECT id, code FROM lines WHERE code IS NOT NULL AND '.self::ANONYMOUS) as $line) {
            $ends = DB::select(
                'SELECT DISTINCT ON (pos) pos, libelle FROM (
                    SELECT libelle, pk_m, CASE WHEN pk_m = MIN(pk_m) OVER () THEN 0 ELSE 1 END AS pos,
                           MIN(pk_m) OVER () AS mn, MAX(pk_m) OVER () AS mx
                    FROM sncf_gares WHERE code_ligne = ? AND pk_m IS NOT NULL AND libelle IS NOT NULL
                 ) g WHERE pk_m = mn OR pk_m = mx ORDER BY pos, libelle',
                [$line->code],
            );
            $prefix = $this->isConnection($line->code) ? 'Raccordement' : 'Ligne';
            if (count($ends) === 2 && $ends[0]->libelle !== $ends[1]->libelle) {
                $this->rename($line->id, "{$prefix} de {$this->title($ends[0]->libelle)} à {$this->title($ends[1]->libelle)}", 'derived_stations');
                $derived++;
            }
        }
        $this->line("  {$derived} lignes nommées d'après leurs gares extrêmes");

        $this->info('→ Gare la plus proche du tracé…');
        $nearest = 0;
        foreach (DB::select('SELECT id, code FROM lines WHERE code IS NOT NULL AND '.self::ANONYMOUS) as $line) {
            $row = DB::selectOne(
                'SELECT g.libelle FROM sncf_line_geometries s, sncf_gares g
                 WHERE s.code_ligne = ? AND g.lat IS NOT NULL
                 ORDER BY ST_SetSRID(ST_MakePoint(g.lon, g.lat), 4326) <-> ST_LineInterpolatePoint(s.geom, 0.5)
                 LIMIT 1',
                [$line->code],
            );
            if ($row) {
                $prefix = $this->isConnection($line->code) ? 'Raccordement' : 'Ligne';
                $this->rename($line->id, "{$prefix} près de {$this->title($row->libelle)}", 'derived_nearest');
                $nearest++;
            }
        }
        $this->line("  {$nearest} lignes nommées d'après la gare la plus proche");

        $left = DB::selectOne('SELECT COUNT(*) n FROM lines WHERE '.self::ANONYMOUS)->n;
        $this->info("✓ Lignes encore anonymes : {$left}");

        return self::SUCCESS;
    }

    /** @return array<string, string> code normalisé (6 chiffres) → libellé */
    private function wikidataNames(): array
    {
        $query = <<<'SPARQL'
            SELECT ?code ?label WHERE {
              ?item wdt:P1671 ?code ; wdt:P17 wd:Q142 ; wdt:P31/wdt:P279* wd:Q728937 .
              ?item rdfs:label ?label FILTER(lang(?label) = "fr")
              FILTER(REGEX(?code, "^[0-9]{3} ?[0-9]{3}$"))
            }
            SPARQL;

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'VFF-Bot/1.0 (+https://vff-project.org)',
                'Accept' => 'application/sparql-results+json',
            ])->timeout(120)->get('https://query.wikidata.org/sparql', ['query' => $query]);
        } catch (ConnectionException $e) {
            $response = null;
        }
        // Wikidata indisponible : la cascade continue (noms dérivés des gares SNCF).
        if ($response === null || ! $response->successful()) {
            $this->warn('  Wikidata indisponible ('.($response ? "HTTP {$response->status()}" : 'connexion impossible').') : étape ignorée.');

            return [];
        }
        $rows = $response->json('results.bindings') ?? [];

        $byCode = [];
        foreach ($rows as $r) {
            $code = $this->normalizeCode($r['code']['value']);
            $label = $this->ucfirst($r['label']['value']);
            // En cas de doublon, préférer un libellé « Ligne de … à … ».
            if (! isset($byCode[$code]) || (str_starts_with($label, 'Ligne de') && ! str_starts_with($byCode[$code], 'Ligne de'))) {
                $byCode[$code] = $label;
            }
        }

        return $byCode;
    }

    private function isConnection(string $code): bool
    {
        return DB::table('sncf_line_types')->where('code_ligne', $code)->where('type_ligne', 'Rac')->exists();
    }

    private function rename(int $id, string $name, string $source): void
    {
        DB::update(
            "UPDATE lines SET name = ?, alt_names = COALESCE(alt_names, '{}'::jsonb) || jsonb_build_object('name_source', ?::text), updated_at = NOW() WHERE id = ?",
            [$name, $source, $id],
        );
    }

    private function normalizeCode(string $code): string
    {
        return str_pad(preg_replace('/\s+/', '', $code), 6, '0', STR_PAD_LEFT);
    }

    /** « PARIS-BASTILLE » → « Paris-Bastille » (les libellés SNCF sont parfois en capitales). */
    private function title(string $s): string
    {
        return mb_strtoupper($s) === $s ? mb_convert_case(mb_strtolower($s), MB_CASE_TITLE) : $s;
    }

    private function ucfirst(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)).mb_substr($s, 1);
    }
}
