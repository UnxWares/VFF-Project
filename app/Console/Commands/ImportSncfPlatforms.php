<?php

namespace App\Console\Commands;

use App\Services\PlaceNames;
use App\Services\Sncf\SncfOpenDataCache;
use App\Services\Sncf\StationPlatforms;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importe les quais des gares de voyageurs du jeu SNCF Open Data « Liste des quais » (SNCF Réseau, ODbL)
 * dans `station_platforms` : un enregistrement par voie bordée, avec sa longueur et ses PK, rattaché à une
 * gare de VFF. Le jeu ne donne pas de code UIC : la gare est cherchée d'abord dans le référentiel des gares
 * SNCF (`sncf_gares`, même nom et même ligne, puis même nom seul) pour son code UIC, puis dans `stations`
 * par ce code ; à défaut, la gare de VFF de même nom (normalisé) la plus proche du milieu du quai, à
 * 1,5 km au plus. Un enregistrement sans gare rattachée est gardé (`station_id` nul) pour l'audit.
 *
 * Écriture : toutes les lignes `source = 'sncf'` de la table sont remplacées dans une transaction ; aucune
 * autre table n'est touchée. --dry-run mesure seulement (rattachements, gares sans quai, contradictions).
 * Le jeu est gardé dans storage/app/sources/sncf-quais/ (dossier `.nobackup`), licence et éditeur
 * revérifiés à chaque téléchargement (SncfOpenDataCache).
 *
 *   php artisan vff:import-sncf-platforms --dry-run --csv=/chemin/rapport.csv
 *   php artisan vff:import-sncf-platforms
 */
class ImportSncfPlatforms extends Command
{
    protected $signature = 'vff:import-sncf-platforms
        {--dry-run : rapport seulement, aucune écriture en base}
        {--refresh : télécharge de nouveau le jeu au lieu du cache local}
        {--cache-dir= : dossier du cache (défaut : storage/app/sources/sncf-quais)}
        {--csv= : rapport détaillé par gare SNCF, au format CSV}';

    protected $description = 'Importe les quais des gares (longueur, nombre) du jeu SNCF Réseau « Liste des quais » (ODbL)';

    /** Distance (m) au-delà de laquelle une gare de même nom n'est pas celle du quai. */
    public const NAME_MAX_DISTANCE_M = 1500;

    /** Écart (m) entre une gare rattachée par son code UIC et le milieu de son quai, signalé au-delà. */
    public const UIC_FAR_M = 2000;

    /** Distance (m) au plus entre les quais d'une fiche fermée et une gare ouverte dont elle partage les quais. */
    public const SAME_SITE_M = 250;

    /** Statuts de gare de VFF qui contredisent l'existence de quais en service (« excluded » : fiche écartée, nature `excluded`). */
    private const CLOSED_STATUSES = ['declassified', 'gone', 'planned', 'excluded'];

    public function handle(SncfOpenDataCache $cache): int
    {
        $dry = (bool) $this->option('dry-run');
        try {
            $data = $cache->load(StationPlatforms::DATASET, StationPlatforms::FIELDS,
                (string) ($this->option('cache-dir') ?: storage_path('app/sources/sncf-quais')), (bool) $this->option('refresh'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info(sprintf('%d enregistrements (jeu modifié le %s, licence %s).', count($data['records']), $data['modified'] ?? '?', $data['license']));

        [$stations, $byUic, $byName] = $this->stations();
        [$sncfByNameLine, $sncfByName] = $this->sncfGares();

        // Regroupement par gare SNCF et ligne : un seul rattachement par groupe, au milieu de ses quais.
        $groups = [];
        $implausible = ['pk' => 0, 'coords' => 0];
        foreach ($data['records'] as $r) {
            $name = trim((string) ($r['lib_gare'] ?? ''));
            $line = trim((string) ($r['code_ligne'] ?? ''));
            $label = trim((string) ($r['libelle'] ?? ''));
            if ($line === '' || $label === '') {
                continue;
            }
            $length = is_numeric($r['longueur'] ?? null) ? (int) round((float) $r['longueur']) : null;
            $pkStart = StationPlatforms::pkMetres($r['pkd'] ?? null);
            $pkEnd = StationPlatforms::pkMetres($r['pkf'] ?? null);
            $span = is_numeric($r['y_d_wgs84'] ?? null) && is_numeric($r['x_d_wgs84'] ?? null) && is_numeric($r['y_f_wgs84'] ?? null) && is_numeric($r['x_f_wgs84'] ?? null)
                ? self::distance((float) $r['y_d_wgs84'], (float) $r['x_d_wgs84'], (float) $r['y_f_wgs84'], (float) $r['x_f_wgs84']) : null;
            // Enregistrement dont une extrémité est fausse (PK « 010+299 » pour un quai de 425 m, bout du quai à 8 km) :
            // la mesure fausse est écartée (la longueur du jeu reste), sans quoi le quai « s'éloigne » de sa gare.
            if ($pkStart !== null && $pkEnd !== null && ! StationPlatforms::plausibleExtent($length, abs($pkEnd - $pkStart))) {
                [$pkStart, $pkEnd] = [null, null];
                $implausible['pk']++;
            }
            $coordsOk = $span === null || StationPlatforms::plausibleExtent($length, $span);
            $coordsOk || $implausible['coords']++;
            $lat = $coordsOk ? self::mid($r['y_d_wgs84'] ?? null, $r['y_f_wgs84'] ?? null) : null;
            $lon = $coordsOk ? self::mid($r['x_d_wgs84'] ?? null, $r['x_f_wgs84'] ?? null) : null;
            $groups[$name.'|'.$line]['name'] = $name;
            $groups[$name.'|'.$line]['line'] = $line;
            $groups[$name.'|'.$line]['rows'][] = [
                'label' => mb_substr($label, 0, 100), 'code_ligne' => $line,
                'code_voie' => ($v = trim((string) ($r['code_voie'] ?? ''))) !== '' ? mb_substr($v, 0, 40) : null,
                'rg_troncon' => is_numeric($r['rg_troncon'] ?? null) ? (int) $r['rg_troncon'] : null,
                'length_m' => $length,
                'pk_start_m' => $pkStart,
                'pk_end_m' => $pkEnd,
                'lat' => $lat, 'lon' => $lon,
            ];
        }

        $count = ['uic' => 0, 'name' => 0, 'site' => 0, 'none' => 0, 'no_name' => 0];
        $matchedStations = ['uic' => [], 'name' => [], 'site' => []];
        $contradictions = ['closed' => [], 'uic_far' => [], 'length_pk' => 0, 'two_stations' => []];
        $insert = [];
        $csv = [];
        $platformCount = 0;
        $sncfToStation = [];
        $now = now();
        $resolved = [];
        foreach ($groups as $key => $g) {
            $lat = self::avg(array_column($g['rows'], 'lat'));
            $lon = self::avg(array_column($g['rows'], 'lon'));
            $resolved[$key] = ['lat' => $lat, 'lon' => $lon, 'platforms' => StationPlatforms::group($g['rows']),
                'match' => $this->match($g['name'], $g['line'], $lat, $lon, $stations, $byUic, $byName, $sncfByNameLine, $sncfByName)];
        }
        $sameSite = $this->sameSite($groups, $resolved, $stations);
        foreach ($groups as $key => $g) {
            [$sid, $method, $distance] = $sameSite[$key] ?? $resolved[$key]['match'];
            $platforms = $resolved[$key]['platforms'];
            $platformCount += count($platforms);
            foreach ($platforms as $p) {
                $extent = $p['pk_start_m'] !== null ? $p['pk_end_m'] - $p['pk_start_m'] : null;
                if ($p['length_m'] !== null && $extent !== null && abs($extent - $p['length_m']) > max(30, 0.2 * $p['length_m'])) {
                    $contradictions['length_pk']++;
                }
            }
            $note = '';
            if ($g['name'] === '') {
                $count['no_name']++;
                $note = 'gare non nommée dans le jeu';
            } elseif ($sid === null) {
                $count['none']++;
                $note = 'aucune gare VFF';
            } else {
                $count[$method]++;
                $matchedStations[$method][$sid] = true;
                $s = $stations[$sid];
                if (in_array($s['status'], self::CLOSED_STATUSES, true)) {
                    $contradictions['closed'][$sid] = $s['slug'].' ('.$s['status'].')';
                    $note = 'gare VFF au statut '.$s['status'];
                }
                if ($method === 'uic' && $distance !== null && $distance > self::UIC_FAR_M) {
                    $contradictions['uic_far'][] = $g['name'].' / '.$s['slug'].' : '.$distance.' m';
                    $note = trim($note.' ; gare VFF à '.$distance.' m', ' ;');
                }
                if ($method !== 'site') {
                    $sncfToStation[PlaceNames::normalize($g['name'])][$sid] = $s['slug'];
                }
            }
            $summary = StationPlatforms::summary($platforms);
            $csv[] = [$g['name'], $g['line'], $summary['count'], $summary['longest_m'] ?? '', $sid !== null ? $stations[$sid]['slug'] : '',
                $method ?? '', $distance ?? '', $note];
            foreach ($g['rows'] as $row) {
                $insert[] = $row + ['station_id' => $sid, 'sncf_station_name' => $g['name'] !== '' ? mb_substr($g['name'], 0, 255) : null,
                    'match_method' => $method, 'source' => StationPlatforms::SOURCE, 'dataset_modified' => $data['modified'],
                    'fetched_at' => $data['fetched_at'] ?? $now, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        foreach ($sncfToStation as $name => $ids) {
            if (count($ids) > 1) {
                $contradictions['two_stations'][] = $name.' : '.implode(', ', $ids);
            }
        }

        $matched = array_keys($matchedStations['uic'] + $matchedStations['name'] + $matchedStations['site']);
        $activeWithout = collect($stations)->filter(fn (array $s, int $id) => $s['status'] === 'active' && $s['uic_code'] !== null
            && ! in_array($id, $matched, true))->count();
        $sncfGares = count(array_unique(array_filter(array_column($groups, 'name'))));

        $this->line('');
        $this->line(sprintf('Quais (enregistrements regroupés) : %d ; gares SNCF nommées : %d ; groupes gare et ligne : %d', $platformCount, $sncfGares, count($groups)));
        $this->line(sprintf('Rattachés par code UIC : %d groupes, %d gares VFF', $count['uic'], count($matchedStations['uic'])));
        $this->line(sprintf('Rattachés par nom et distance (%d m au plus) : %d groupes, %d gares VFF', self::NAME_MAX_DISTANCE_M, $count['name'], count($matchedStations['name'])));
        $this->line(sprintf('Rattachés au même site qu\'une gare ouverte (quais d\'une fiche fermée, même ligne et mêmes PK) : %d groupes, %d gares VFF', $count['site'], count($matchedStations['site'])));
        $this->line(sprintf('Enregistrements à extrémité fausse écartée (plus de 3 fois la longueur) : %d PK, %d coordonnées', $implausible['pk'], $implausible['coords']));
        $this->line(sprintf('Non rattachés : %d groupes (gare non nommée dans le jeu : %d)', $count['none'] + $count['no_name'], $count['no_name']));
        $this->line(sprintf('Gares VFF touchées : %d', count($matched)));
        $this->line(sprintf('Gares VFF ouvertes aux voyageurs (statut active, code UIC connu) sans quai connu : %d', $activeWithout));
        $this->line(sprintf('Contradictions : %d gares VFF fermées, disparues ou projetées avec des quais ; %d rattachements UIC à plus de %d m ; %d quais dont la longueur s\'écarte de l\'étendue des PK (plus de 20 %% et 30 m) ; %d noms SNCF rattachés à plusieurs gares VFF',
            count($contradictions['closed']), count($contradictions['uic_far']), self::UIC_FAR_M, $contradictions['length_pk'], count($contradictions['two_stations'])));
        foreach (array_slice(array_values($contradictions['closed']), 0, 15) as $c) {
            $this->line('  fermée : '.$c);
        }
        foreach (array_slice($contradictions['uic_far'], 0, 10) as $c) {
            $this->line('  éloignée : '.$c);
        }
        foreach (array_slice($contradictions['two_stations'], 0, 10) as $c) {
            $this->line('  plusieurs gares : '.$c);
        }

        if ($path = $this->option('csv')) {
            $h = fopen((string) $path, 'w');
            fputcsv($h, ['gare_sncf', 'code_ligne', 'quais', 'plus_long_m', 'gare_vff', 'rattachement', 'distance_m', 'note'], ',', '"', '');
            foreach ($csv as $row) {
                fputcsv($h, $row, ',', '"', '');
            }
            fclose($h);
            $this->line("Rapport : {$path}");
        }

        if ($dry) {
            $this->line('Passe à blanc : aucune écriture en base.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($insert): void {
            DB::table('station_platforms')->where('source', StationPlatforms::SOURCE)->delete();
            foreach (array_chunk($insert, 500) as $chunk) {
                DB::table('station_platforms')->insert($chunk);
            }
        });
        $this->info(sprintf('%d enregistrements écrits dans station_platforms.', count($insert)));

        return self::SUCCESS;
    }

    /**
     * Groupes de quais d'une fiche fermée qui sont en réalité ceux d'une gare ouverte voisine : le jeu SNCF donne parfois
     * aux mêmes quais un second libellé (« Direction Régionale Rhône-Alpes » pour Lyon-Perrache, « Grasse » pour
     * Marseille-St-Charles, « Paris-Tolbiac » pour Bibliothèque-François-Mitterrand), et la fiche VFF de ce libellé
     * (fermée, souvent née de la même erreur) recevrait des quais qui desservent aujourd'hui une autre gare.
     * Rattachement refait quand le groupe est sur la même ligne que la gare ouverte, que ses PK recoupent ceux d'un de
     * ses groupes (StationPlatforms::sameSite) et que ses quais sont à 250 m au plus de cette gare.
     *
     * @param  array<string, array<string, mixed>>  $groups
     * @param  array<string, array<string, mixed>>  $resolved
     * @param  array<int, array<string, mixed>>  $stations
     * @return array<string, array{0: int, 1: string, 2: ?int}>
     */
    private function sameSite(array $groups, array $resolved, array $stations): array
    {
        $byLine = [];
        foreach ($resolved as $key => $r) {
            $sid = $r['match'][0];
            if ($sid !== null && $stations[$sid]['status'] === 'active' && StationPlatforms::pkExtent($r['platforms']) !== null) {
                $byLine[$groups[$key]['line']][] = [$key, $sid];
            }
        }
        $out = [];
        foreach ($resolved as $key => $r) {
            $sid = $r['match'][0];
            $extent = StationPlatforms::pkExtent($r['platforms']);
            if ($sid === null || $extent === null || $r['lat'] === null || ! in_array($stations[$sid]['status'], self::CLOSED_STATUSES, true)) {
                continue;
            }
            $best = null;
            foreach ($byLine[$groups[$key]['line']] ?? [] as [$otherKey, $otherSid]) {
                if ($otherSid === $sid || ! StationPlatforms::sameSite($extent, StationPlatforms::pkExtent($resolved[$otherKey]['platforms']))) {
                    continue;
                }
                $d = self::distance($r['lat'], $r['lon'], $stations[$otherSid]['lat'], $stations[$otherSid]['lon']);
                if ($d !== null && $d <= self::SAME_SITE_M && ($best === null || $d < $best[1])) {
                    $best = [$otherSid, $d];
                }
            }
            if ($best !== null) {
                $out[$key] = [$best[0], 'site', $best[1]];
            }
        }

        return $out;
    }

    /**
     * Gare de VFF d'un groupe de quais : code UIC du référentiel SNCF, sinon même nom au plus près.
     *
     * @param  array<int, array<string, mixed>>  $stations
     * @param  array<string, int>  $byUic
     * @param  array<string, list<int>>  $byName
     * @param  array<string, string>  $sncfByNameLine
     * @param  array<string, list<string>>  $sncfByName
     * @return array{0: ?int, 1: ?string, 2: ?int} gare, méthode (uic | name | site), distance en mètres au milieu des quais
     */
    private function match(string $name, string $line, ?float $lat, ?float $lon, array $stations, array $byUic, array $byName,
        array $sncfByNameLine, array $sncfByName): array
    {
        if ($name === '') {
            return [null, null, null];
        }
        $key = PlaceNames::normalize($name);
        $uic = $sncfByNameLine[$key.'|'.$line] ?? (count($sncfByName[$key] ?? []) === 1 ? $sncfByName[$key][0] : null);
        $sid = $uic !== null ? ($byUic[$uic] ?? $byUic[substr($uic, 0, 7)] ?? null) : null;
        if ($sid !== null) {
            return [$sid, 'uic', self::distance($lat, $lon, $stations[$sid]['lat'], $stations[$sid]['lon'])];
        }
        $best = null;
        $bestD = null;
        foreach ($byName[$key] ?? [] as $id) {
            $d = self::distance($lat, $lon, $stations[$id]['lat'], $stations[$id]['lon']);
            if ($d !== null && $d <= self::NAME_MAX_DISTANCE_M && ($bestD === null || $d < $bestD)) {
                [$best, $bestD] = [$id, $d];
            }
        }

        return $best !== null ? [$best, 'name', $bestD] : [null, null, null];
    }

    /** @return array{0: array<int, array{slug: string, status: string, uic_code: ?string, lat: float, lon: float}>, 1: array<string, int>, 2: array<string, list<int>>} */
    private function stations(): array
    {
        $stations = [];
        $byUic = [];
        $byName = [];
        foreach (DB::select("SELECT id, slug, name, CASE WHEN provenance->>'kind' = 'excluded' THEN 'excluded' ELSE status END AS status, uic_code, ST_Y(geom) AS lat, ST_X(geom) AS lon FROM stations") as $s) {
            $stations[(int) $s->id] = ['slug' => (string) $s->slug, 'status' => (string) $s->status,
                'uic_code' => $s->uic_code !== null ? (string) $s->uic_code : null, 'lat' => (float) $s->lat, 'lon' => (float) $s->lon];
            if ($s->uic_code !== null && $s->uic_code !== '') {
                $byUic[(string) $s->uic_code] ??= (int) $s->id;
            }
            $byName[PlaceNames::normalize((string) $s->name)][] = (int) $s->id;
        }

        return [$stations, $byUic, $byName];
    }

    /** @return array{0: array<string, string>, 1: array<string, list<string>>} code UIC par nom et ligne, codes UIC par nom */
    private function sncfGares(): array
    {
        $byNameLine = [];
        $byName = [];
        foreach (DB::table('sncf_gares')->whereNotNull('code_uic')->whereNotNull('libelle')
            ->orderByDesc('voyageurs')->get(['code_uic', 'libelle', 'code_ligne']) as $g) {
            $key = PlaceNames::normalize((string) $g->libelle);
            $byNameLine[$key.'|'.$g->code_ligne] ??= (string) $g->code_uic;
            if (! in_array((string) $g->code_uic, $byName[$key] ?? [], true)) {
                $byName[$key][] = (string) $g->code_uic;
            }
        }

        return [$byNameLine, $byName];
    }

    private static function mid(mixed $a, mixed $b): ?float
    {
        return is_numeric($a) && is_numeric($b) ? ((float) $a + (float) $b) / 2 : (is_numeric($a) ? (float) $a : (is_numeric($b) ? (float) $b : null));
    }

    /** @param  list<?float>  $values */
    private static function avg(array $values): ?float
    {
        $values = array_filter($values, fn ($v) => $v !== null);

        return $values === [] ? null : array_sum($values) / count($values);
    }

    /** Distance (m) entre deux points WGS 84 (haversine), null si un point manque. */
    private static function distance(?float $lat1, ?float $lon1, float $lat2, float $lon2): ?int
    {
        if ($lat1 === null || $lon1 === null) {
            return null;
        }
        $p = M_PI / 180;
        $a = sin(($lat2 - $lat1) * $p / 2) ** 2 + cos($lat1 * $p) * cos($lat2 * $p) * sin(($lon2 - $lon1) * $p / 2) ** 2;

        return (int) round(2 * 6371000 * asin(min(1.0, sqrt($a))));
    }
}
