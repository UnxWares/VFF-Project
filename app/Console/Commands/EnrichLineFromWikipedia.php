<?php

namespace App\Console\Commands;

use App\Services\WikipediaLineParser;
use App\Support\Lgv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Enrichit les lignes depuis Wikipedia FR : metadata techniques + events
 * historiques + compagnies exploitantes + traversées (régions/départements).
 *
 * Stocke aussi la source dans `line_sources` pour audit.
 *
 * Usage :
 *   php artisan vff:enrich-line-wikipedia                 # toutes
 *   php artisan vff:enrich-line-wikipedia --slug=rfn-405000  # une seule
 */
class EnrichLineFromWikipedia extends Command
{
    protected $signature = 'vff:enrich-line-wikipedia
                            {--slug= : slug d\'une ligne précise}
                            {--limit= : limite le nombre de lignes}
                            {--sleep=200 : délai entre 2 requêtes Wikipedia (ms)}';

    protected $description = 'Enrichit les lignes depuis Wikipedia FR.';

    public function __construct(protected WikipediaLineParser $parser)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $q = DB::table('lines')->whereNotNull('name');
        if ($slug = $this->option('slug')) {
            $q->where('slug', $slug);
        } else {
            // Skip les lignes au nom anonyme (Ligne XXXXXX), regex POSIX PostgreSQL
            $q->whereRaw("name !~ '^Ligne [0-9]+$'");
        }
        if ($limit = $this->option('limit')) {
            $q->limit((int) $limit);
        }

        $lines = $q->orderBy('id')->get(['id', 'slug', 'code', 'name']);
        $this->info("Enrichissement Wikipedia : {$lines->count()} lignes");

        $sleepMs = (int) $this->option('sleep');
        $bar = $this->output->createProgressBar($lines->count());
        $found = 0;
        $notFound = 0;
        $eventsTotal = 0;

        foreach ($lines as $line) {
            $r = $this->parser->fetchAndParse($line->name, $line->code);
            usleep($sleepMs * 1000);

            if (! $r['found']) {
                $notFound++;
                $bar->advance();

                continue;
            }

            $this->enrichLine($line, $r, $eventsTotal);
            $found++;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $this->info("✓ {$found} lignes enrichies, {$notFound} non trouvées ({$eventsTotal} événements repérés : lancer vff:import-wikipedia-chronology pour les importer)");

        return self::SUCCESS;
    }

    /**
     * Classe le champ `trafic` Wikipédia (texte libre) en passenger/freight/mixed.
     * Toute marque voyageurs l'emporte (protège LGV/raccordements). Retourne null
     * si vide, « aucun », voie verte, etc. (pas un trafic commercial).
     */
    private function classifyTrafic(string $raw): ?string
    {
        $t = mb_strtolower(strip_tags($raw));
        if ($t === '' || preg_match('/aucun|voie verte|déposée|désaffect|non exploit|travaux/i', $t)) {
            return null;
        }
        $pax = (bool) preg_match('/voyageur|tgv|ter\b|intercité|ouigo|transilien|\brer\b|eurostar|lyria|ice|omnibus|fret.{0,3}voyageur|tram/i', $t);
        $fret = (bool) preg_match('/fret|marchandise|pondéreux/i', $t);

        return match (true) {
            $pax && $fret => 'mixed',
            $pax => 'passenger',
            $fret => 'freight',
            default => null,
        };
    }

    private function enrichLine(object $line, array $r, int &$eventsTotal): void
    {
        $meta = $r['metadata'];

        // === Lines metadata ===
        $update = [
            'wikipedia_title' => $r['title'],
            'encyclopedia_updated_at' => now(),
        ];
        if (isset($meta['longueur']) && is_numeric($meta['longueur'])) {
            $update['length_official_m'] = (int) ((float) $meta['longueur'] * 1000);
        }
        if (isset($meta['écartement']) && is_numeric(preg_replace('/[^\d]/', '', $meta['écartement']))) {
            $update['gauge_mm_default'] = (int) preg_replace('/[^\d]/', '', $meta['écartement']);
        }
        if (isset($meta['nombre de voies act'])) {
            $nv = $meta['nombre de voies act'];
            if (preg_match('/(\d)/', $nv, $m)) {
                $update['nb_voies_default'] = (int) $m[1];
            }
        }
        if (isset($meta['vitesse'])) {
            if (preg_match('/(\d+)/', $meta['vitesse'], $m)) {
                $update['max_speed_kmh_official'] = (int) $m[1];
            }
        }
        if ($r['companies']) {
            $update['historical_operators'] = json_encode($r['companies']);
        }
        if ($r['regions']) {
            $update['regions'] = json_encode($r['regions']);
        }
        if ($r['departements']) {
            $update['departements'] = json_encode($r['departements']);
        }

        // Année de concession initiale (1ère compagnie)
        foreach ($r['companies'] as $c) {
            if ($c['from_year']) {
                $update['concession_year'] = $c['from_year'];
                break;
            }
        }

        DB::table('lines')->where('id', $line->id)->update($update);

        // === Trafic Wikipedia → affine UNIQUEMENT les segments restés 'unknown' ===
        // (la donnée SNCF reste prioritaire ; on ne comble que ce qu'elle ne dit pas).
        if (isset($meta['trafic']) && ($cls = $this->classifyTrafic($meta['trafic']))) {
            // Règle « si y'a du passager, y'a du fret » : voyageurs seuls réservé aux LGV.
            // LGV selon la définition unique (drapeau, nom, catégorie SNCF) : le drapeau
            // is_lgv seul vaut false tant que vff:classify-lines n'a pas tourné.
            $isLgv = (bool) DB::selectOne('SELECT '.Lgv::sql('l', null).' AS lgv FROM lines l WHERE l.id = ?', [$line->id])->lgv;
            if ($cls === 'passenger' && ! $isLgv) {
                $cls = 'mixed';
            }
            DB::update(
                "UPDATE line_segments SET traffic_type=?, updated_at=NOW()
                 WHERE line_id=? AND era_id=(SELECT id FROM eras WHERE is_current=true)
                   AND status IN ('in_service','single_track') AND traffic_type='unknown'",
                [$cls, $line->id],
            );
            DB::update('UPDATE lines SET type=COALESCE(type,?) WHERE id=?', [
                match ($cls) {
                    'passenger' => 'passenger', 'freight' => 'freight', 'mixed' => 'mixed', default => null
                },
                $line->id,
            ]);
        }

        // === Events ===
        // Les événements datés (par portion, typés voyageurs / marchandises / tout
        // trafic, avec empreinte idempotente) sont importés par
        // vff:import-wikipedia-chronology (source = 'wikipedia'). On n'en écrit
        // plus ici pour éviter des doublons au format historique.
        $eventsTotal += count($r['events']);

        // === line_sources ===
        $fields = [];
        foreach (['length_official_m', 'gauge_mm_default', 'nb_voies_default',
            'max_speed_kmh_official', 'historical_operators', 'regions',
            'departements', 'concession_year'] as $f) {
            if (isset($update[$f])) {
                $fields[] = $f;
            }
        }
        DB::table('line_sources')->updateOrInsert(
            ['line_id' => $line->id, 'source_type' => 'wikipedia'],
            [
                'source_url' => $r['url'],
                'fetched_at' => now(),
                'fields_provided' => json_encode($fields),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
