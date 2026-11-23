<?php

namespace Database\Seeders;

use App\Models\Era;
use App\Services\Companies\CompanyFigures;
use App\Services\Departments\DepartmentFigures;
use App\Support\DatabaseGuard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Jeu de données minimal et déterministe de la suite de fumée Playwright (tests/e2e, job `e2e` de la CI).
 *
 * Sur une base vide migrée : les ères, la ligne d'Andelot-en-Montagne à La Cluse (rfn-878000) avec trois
 * gares (dont `andelot`), ses portions, ses arrêts et ses états de gare par ère, un accident
 * (`1890-09-16-andelot-en-montagne`) rattaché à la ligne et à la gare, et une seconde ligne pour que la
 * liste ne soit pas réduite à une fiche. Ce sont des faits publics (noms, communes, dates) : aucune donnée
 * personnelle, aucun compte. L'article du journal vient des fichiers versionnés `resources/content/journal`.
 * La commune d'Andelot-en-Montagne (39009, contour simplifié autour de la gare) porte la fiche de commune ; les
 * fiches du département 39 et des compagnies sont calculées et rangées dans le cache, comme le font
 * vff:compute-department-figures et vff:compute-company-figures, pour que leurs pages répondent.
 *
 * Idempotent : relancer le seeder ne duplique rien (clés naturelles : slug, ère + ligne + gare).
 * Garde-fou : refuse toute base dont le nom ne finit pas par `_test` ou `_test_<n>` (processus d'une suite en
 * parallèle) : jamais la base de développement `vff` (App\Support\DatabaseGuard).
 */
class E2eSeeder extends Seeder
{
    public const string LINE = 'rfn-878000';

    public const string STATION = 'andelot';

    public const string ACCIDENT = '1890-09-16-andelot-en-montagne';

    public const string COMMUNE = '39009';

    public const string DEPARTMENT = '39';

    /** Gare du jeu dont l'ancienne adresse (fiche fusionnée) renvoie vers `andelot` (301). */
    public const string MERGED_STATION_OLD_SLUG = 'osm-4242424242-andelot';

    /** Ligne au slug neutre dont l'ancienne adresse « archeo-… » (database/data/line_slug_redirects.php) renvoie ici (301). */
    public const string NEUTRAL_LINE = 'antenne-de-la-flotte';

    /** Gare fermée dont la date de fermeture est un intervalle (« entre 1951 et 1960 »), près de la commune du jeu. */
    public const string CLOSED_RANGE_STATION = 'andelot-le-haut';

    /** Première ère où la ligne figure (ouverture de la ligne en 1862 à 1883). */
    private const int FIRST_ERA = 1870;

    public function run(): void
    {
        $database = (string) DB::connection()->getDatabaseName();
        // Même règle que les tests et les commandes qui vident la base : `_test`, ou `_test_<n>` d'un processus
        // de la suite en parallèle (sinon E2eSeederTest échouait sous --parallel).
        if (! DatabaseGuard::isDisposable($database)) {
            throw new \RuntimeException("E2eSeeder refuse la base « {$database} » : seule une base dont le nom finit par _test (ou _test_<n>) est acceptée.");
        }

        $this->call([EraSeeder::class, HistoricalErasSeeder::class]);

        DB::transaction(function (): void {
            $lineId = $this->line(self::LINE, "Ligne d'Andelot-en-Montagne à La Cluse", '878000', 'mixed', 'principal');
            $otherId = $this->line('rfn-830000', 'Ligne de Paris-Lyon à Marseille-Saint-Charles (ligne de démonstration)', '830000', 'mixed', 'principal');

            $stations = [
                ['slug' => self::STATION, 'name' => 'Andelot', 'city' => 'Andelot-en-Montagne', 'dep' => '39', 'lon' => 5.9236614, 'lat' => 46.8593999, 'opened' => '1862-01-01', 'pk' => 0],
                ['slug' => 'champagnole', 'name' => 'Champagnole', 'city' => 'Champagnole', 'dep' => '39', 'lon' => 5.9086, 'lat' => 46.7457, 'opened' => '1864-01-01', 'pk' => 13723],
                ['slug' => 'la-cluse', 'name' => 'La Cluse', 'city' => 'Les Neyrolles', 'dep' => '01', 'lon' => 5.6603, 'lat' => 46.2085, 'opened' => '1882-01-01', 'pk' => 123000],
            ];
            $stationIds = [];
            foreach ($stations as $s) {
                $stationIds[$s['slug']] = $this->station($s);
            }

            $path = 'LINESTRING(5.9236614 46.8593999, 5.9086 46.7457, 5.7800 46.4300, 5.6603 46.2085)';
            foreach (Era::query()->where('year', '>=', self::FIRST_ERA)->orderBy('year')->get() as $era) {
                $this->segment($lineId, $era->id, $path);
                $position = 0;
                foreach ($stations as $s) {
                    $this->stop($lineId, $era->id, $stationIds[$s['slug']], $position++, $s['pk']);
                    $this->stationEra($stationIds[$s['slug']], $era->id);
                }
            }

            $this->segment($otherId, Era::query()->where('year', 2030)->value('id'), 'LINESTRING(2.3730 48.8443, 4.8357 45.7606, 5.3800 43.3030)');

            $this->accident($stationIds[self::STATION], $lineId);
            $this->commune();
            $this->redirects($stationIds[self::STATION]);
            $this->closedRangeStation();
            $this->doubtfulMaterials($lineId);
            $this->quizFacts($lineId, $stationIds[self::STATION]);
        });

        // Fiches lues dans le cache seulement (pages /departments/39-jura et /companies/…).
        app(DepartmentFigures::class)->refresh([self::DEPARTMENT]);
        app(CompanyFigures::class)->refresh();
    }

    /**
     * Anciennes adresses : une fiche de gare fusionnée dans `andelot` (table station_redirects) et une ligne au
     * slug neutre dont l'ancien slug « archeo-… » vit dans le fichier versionné des redirections de lignes.
     */
    private function redirects(int $stationId): void
    {
        $now = now();
        DB::table('station_redirects')->updateOrInsert(['old_slug' => self::MERGED_STATION_OLD_SLUG], [
            'station_id' => $stationId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $neutral = $this->line(self::NEUTRAL_LINE, 'Antenne de La Flotte (ligne de démonstration)', '878999', 'mixed', 'secondaire');
        $this->segment($neutral, (int) Era::query()->where('year', 2030)->value('id'), 'LINESTRING(5.9236614 46.8593999, 5.9300 46.8700)');
    }

    /**
     * Gare fermée dont la fermeture n'est connue que par un intervalle (précision `range` : fermée après 1950, au plus
     * tard en 1960), à moins d'un kilomètre de la gare d'Andelot : annuaire des gares et « Autour de moi ».
     */
    private function closedRangeStation(): void
    {
        $id = $this->station(['slug' => self::CLOSED_RANGE_STATION, 'name' => 'Andelot-le-Haut', 'city' => 'Andelot-en-Montagne', 'dep' => '39', 'lon' => 5.9100, 'lat' => 46.8650, 'opened' => '1862-01-01', 'pk' => 0]);
        DB::table('stations')->where('id', $id)->update([
            'status' => 'declassified',
            'lifecycle' => 'disused',
            'closed_at' => '1960-12-31',
            'closed_precision' => 'range',
            'provenance' => json_encode(['opened_at' => 'wikipedia', 'closed_at' => 'vff:eras-range', 'closed_after' => 1950]),
        ]);
    }

    /**
     * Séries citées sur la ligne sans service attesté (liens de fiabilité faible dont chaque preuve nomme un doute) :
     * la fiche de ligne les range dans le groupe replié « Cités, sans service attesté ».
     */
    private function doubtfulMaterials(int $lineId): void
    {
        $now = now();
        foreach ([['bb-9999-essais', 'BB 9999', 'trial'], ['z-9999-commande', 'Z 9999', 'order']] as [$slug, $name, $doubt]) {
            DB::table('materials')->updateOrInsert(['slug' => $slug], [
                'name' => $name,
                'type' => 'locomotive_electrique',
                'in_service_from' => 1980,
                'scope' => 'france',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $materialId = (int) DB::table('materials')->where('slug', $slug)->value('id');
            DB::table('line_materials')->updateOrInsert(['line_id' => $lineId, 'material_id' => $materialId, 'era_id' => null], [
                'source' => 'wikipedia',
                'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_demonstration&oldid=1',
                'confidence' => 'low',
                'evidence' => json_encode([['kind' => 'wikipedia_line_article', 'doubt' => $doubt]]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Un sujet de chaque famille du jeu « Culture du rail » tirée des données du jour (App\Services\Quiz\DailyFactPool) :
     * ouverture d'une grande gare corrigée au jour, système de cabine d'une LGV, série de matériel citée sur une ligne
     * (avec cinq séries du même type sans lien, pour les mauvaises réponses) et année d'un accident relu de plus de
     * 25 ans. Quatre sujets suffisent à ouvrir le jeu (QuizSeries::OPTIONS).
     */
    private function quizFacts(int $lineId, int $stationId): void
    {
        $now = now();

        DB::table('station_attendances')->updateOrInsert(['station_id' => $stationId, 'year' => 2024, 'series' => '2015-2024'], [
            'passengers' => 1_500_000, 'footfall' => 1_500_000, 'uic_code' => '87000001', 'match_method' => 'uic',
            'source' => 'sncf', 'dataset' => 'frequentation-gares-archive', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('station_events')->updateOrInsert(['station_id' => $stationId, 'type' => 'opening', 'event_date' => '1862-03-04', 'source' => 'correction'], [
            'date_precision' => 'day', 'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Gare_d%27Andelot&oldid=1',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $lgv = $this->line('rfn-e2e-lgv', 'LGV de démonstration', '999000', 'passenger', 'principal');
        DB::table('lines')->where('id', $lgv)->update(['is_lgv' => true]);
        DB::table('line_signalling_events')->updateOrInsert(['fingerprint' => sha1('e2e-lgv-tvm-430')], [
            'line_id' => $lgv, 'system' => 'TVM-430', 'role' => 'cab', 'event_date' => '1993-05-23', 'date_precision' => 'day',
            'scope' => 'whole_line', 'confidence' => 'high', 'origin' => 'document', 'source' => 'manual', 'source_title' => 'Bilan LOTI de démonstration',
            'source_revision' => 1, 'source_url' => 'https://example.org/loti-demonstration.pdf', 'source_excerpt' => 'Extrait de démonstration',
            'verification' => 'official', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $cited = null;
        foreach ([['bb-9101', 'BB 9101'], ['bb-9102', 'BB 9102'], ['bb-9103', 'BB 9103'], ['bb-9104', 'BB 9104'], ['bb-9105', 'BB 9105'], ['bb-9106', 'BB 9106']] as $i => [$slug, $name]) {
            DB::table('materials')->updateOrInsert(['slug' => $slug], [
                'name' => $name, 'type' => 'locomotive_diesel', 'in_service_from' => 1960 + $i * 5, 'scope' => 'france',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $cited ??= (int) DB::table('materials')->where('slug', $slug)->value('id');
        }
        DB::table('line_materials')->updateOrInsert(['line_id' => $lineId, 'material_id' => $cited, 'era_id' => null], [
            'source' => 'wikipedia', 'source_url' => 'https://fr.wikipedia.org/w/index.php?title=Ligne_de_demonstration&oldid=2',
            'confidence' => 'high', 'evidence' => json_encode([['kind' => 'wikipedia_line_article']]),
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $accidentId = (int) DB::table('accidents')->where('slug', self::ACCIDENT)->value('id');
        DB::table('accident_sources')->updateOrInsert(['accident_id' => $accidentId, 'url' => 'https://fr.wikipedia.org/wiki/Collision_d%27Andelot-en-Montagne'], [
            'provider' => 'wikipedia', 'external_id' => 'Collision d\'Andelot-en-Montagne', 'role' => 'article', 'title' => 'Collision d\'Andelot-en-Montagne',
            'licence' => 'CC BY-SA 4.0', 'licence_url' => 'https://creativecommons.org/licenses/by-sa/4.0/', 'retrieved_at' => $now,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    /** Commune d'Andelot-en-Montagne : contour carré simplifié qui contient la gare `andelot`. */
    private function commune(): void
    {
        $now = now();
        DB::table('communes')->updateOrInsert(['code_insee' => self::COMMUNE, 'type' => 'commune-actuelle'], [
            'nom' => 'Andelot-en-Montagne',
            'nom_norm' => 'andelot en montagne',
            'departement' => self::DEPARTMENT,
            'geom' => DB::raw('ST_SetSRID(ST_MakePoint(5.92, 46.86), 4326)'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('commune_contours')->updateOrInsert(['code_insee' => self::COMMUNE], [
            'nom' => 'Andelot-en-Montagne',
            'departement' => self::DEPARTMENT,
            'geom' => DB::raw("ST_GeomFromText('MULTIPOLYGON(((5.89 46.84, 5.95 46.84, 5.95 46.88, 5.89 46.88, 5.89 46.84)))', 4326)"),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function line(string $slug, string $name, string $code, string $type, string $network): int
    {
        $now = now();
        DB::table('lines')->updateOrInsert(['slug' => $slug], [
            'name' => $name,
            'code' => $code,
            'type' => $type,
            'network' => $network,
            'description' => "{$name} est une ligne de chemin de fer à voie unique du Jura français. Jeu de données de test : texte court et sans valeur encyclopédique.",
            'length_official_m' => 123000,
            'gauge_mm_default' => 1435,
            'max_speed_kmh' => 110,
            'electrified' => false,
            'regions' => json_encode(['Bourgogne-Franche-Comté']),
            'departements' => json_encode(['Jura']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::table('lines')->where('slug', $slug)->value('id');
    }

    /** @param array{slug: string, name: string, city: string, dep: string, lon: float, lat: float, opened: string, pk: int} $s */
    private function station(array $s): int
    {
        $now = now();
        DB::table('stations')->updateOrInsert(['slug' => $s['slug']], [
            'name' => $s['name'],
            'type' => 'gare',
            'geom' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)', $s['lon'], $s['lat'])),
            'city' => $s['city'],
            'department_code' => $s['dep'],
            'opened_at' => $s['opened'],
            'status' => 'active',
            'lifecycle' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::table('stations')->where('slug', $s['slug'])->value('id');
    }

    private function segment(int $lineId, int $eraId, string $wkt): void
    {
        $now = now();
        DB::table('line_segments')->updateOrInsert(['line_id' => $lineId, 'era_id' => $eraId], [
            'geom' => DB::raw(sprintf("ST_SetSRID(ST_GeomFromText('%s'), 4326)", $wkt)),
            'status' => 'in_service',
            'max_speed_kmh' => 110,
            'electrified' => false,
            'gauge_mm' => 1435,
            'traffic_type' => 'mixed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function stop(int $lineId, int $eraId, int $stationId, int $position, int $pk): void
    {
        $now = now();
        DB::table('line_stops')->updateOrInsert(['line_id' => $lineId, 'era_id' => $eraId, 'station_id' => $stationId], [
            'position' => $position,
            'pk_m' => $pk,
            'effective_status' => 'active',
            'is_terminal' => $position === 0 || $pk === 123000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function stationEra(int $stationId, int $eraId): void
    {
        $now = now();
        DB::table('station_eras')->updateOrInsert(['station_id' => $stationId, 'era_id' => $eraId], [
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function accident(int $stationId, int $lineId): void
    {
        $now = now();
        DB::table('accidents')->updateOrInsert(['slug' => self::ACCIDENT], [
            'occurred_on' => '1890-09-16',
            'date_precision' => 'day',
            'kind' => 'collision',
            'name' => json_encode([
                'fr' => 'Collision à Andelot-en-Montagne',
                'en' => 'Collision at Andelot-en-Montagne',
                'de' => 'Zusammenstoß in Andelot-en-Montagne',
            ]),
            'commune_insee' => '39009',
            'commune_name' => 'Andelot-en-Montagne',
            'department_code' => '39',
            'geom' => DB::raw('ST_SetSRID(ST_MakePoint(5.9236614, 46.8593999), 4326)'),
            'location_precision' => 'station',
            'location_source' => 'station',
            'nearest_station_id' => $stationId,
            'nearest_station_distance_m' => 0,
            'deaths' => 2,
            'injuries' => 9,
            'toll_source' => 'wikipedia',
            'summary' => json_encode([
                'fr' => 'Le 16 septembre 1890, une collision ferroviaire a lieu à Andelot-en-Montagne (Jura). Bilan : 2 morts et 9 blessés.',
                'en' => 'On 16 September 1890, a railway collision occurred at Andelot-en-Montagne (Jura). Toll: 2 dead and 9 injured.',
                'de' => 'Am 16. September 1890 ereignete sich in Andelot-en-Montagne (Jura) ein Zusammenstoß im Bahnverkehr. Bilanz: 2 Tote und 9 Verletzte.',
            ]),
            'review_status' => 'ok',
            'provenance' => json_encode(['date' => 'wikipedia', 'kind' => 'wikipedia', 'name' => 'vff', 'deaths' => 'wikipedia', 'location' => 'station']),
            'imported_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $accidentId = (int) DB::table('accidents')->where('slug', self::ACCIDENT)->value('id');

        DB::table('accident_lines')->updateOrInsert(['accident_id' => $accidentId, 'line_id' => $lineId], [
            'is_primary' => true,
            'era_year' => 1890,
            'distance_m' => 31,
            'pk_m' => 0,
            'method' => 'proximity+wikipedia',
            'confidence' => 'high',
            'score' => 0.987,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
