<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Géocodage HORS LIGNE des toponymes cités par les articles Wikipédia de
 * réseaux secondaires (« Carhaix - Morlaix », « [[Cerdon (Ain)|Pont-de-Préau]] »),
 * à partir des tables VFF `communes` (communes actuelles, déléguées et associées)
 * et `stations` (gares consolidées). Aucun appel réseau.
 *
 * Pourquoi un géocodeur dédié : les noms des réseaux d'intérêt local sont
 * souvent abrégés (« Carhaix » pour Carhaix-Plouguer, « Jassans » pour
 * Jassans-Riottier) ou homonymes d'une commune d'un autre département
 * (« Saint-Martin »). On les résout donc :
 *   1. par la cible du lien wiki quand elle existe (« Cerdon (Ain) » → commune
 *      Cerdon dans l'Ain ; « Gare de La Brohinière » → gare) ;
 *   2. sinon par le libellé : gare puis commune de même nom normalisé, puis
 *      commune dont le nom COMMENCE par le libellé (« Carhaix-Plouguer ») ;
 *   3. en départageant les homonymes par le CONTEXTE de l'article (barycentre
 *      des toponymes non ambigus, départements cités dans le titre).
 * Garde-fou : deux candidats plausibles éloignés l'un de l'autre → null
 * (ambigu), jamais de choix arbitraire.
 */
final class SecondaryPlaceGeocoder
{
    /** Départements (nom tel qu'écrit dans les titres et qualificatifs d'articles) → code INSEE. */
    public const DEPARTEMENTS = [
        'Ain' => '01', 'Aisne' => '02', 'Allier' => '03', 'Alpes-de-Haute-Provence' => '04', 'Basses-Alpes' => '04',
        'Hautes-Alpes' => '05', 'Alpes-Maritimes' => '06', 'Ardèche' => '07', 'Ardennes' => '08', 'Ariège' => '09',
        'Aube' => '10', 'Aude' => '11', 'Aveyron' => '12', 'Bouches-du-Rhône' => '13', 'Calvados' => '14',
        'Cantal' => '15', 'Charente' => '16', 'Charente-Maritime' => '17', 'Charente-Inférieure' => '17', 'Cher' => '18',
        'Corrèze' => '19', 'Corse-du-Sud' => '2A', 'Haute-Corse' => '2B', "Côte-d'Or" => '21', "Côtes-d'Armor" => '22',
        'Côtes-du-Nord' => '22', 'Creuse' => '23', 'Dordogne' => '24', 'Doubs' => '25', 'Drôme' => '26', 'Eure' => '27',
        'Eure-et-Loir' => '28', 'Finistère' => '29', 'Gard' => '30', 'Haute-Garonne' => '31', 'Gers' => '32',
        'Gironde' => '33', 'Hérault' => '34', 'Ille-et-Vilaine' => '35', 'Indre' => '36', 'Indre-et-Loire' => '37',
        'Isère' => '38', 'Jura' => '39', 'Landes' => '40', 'Loir-et-Cher' => '41', 'Loire' => '42', 'Haute-Loire' => '43',
        'Loire-Atlantique' => '44', 'Loire-Inférieure' => '44', 'Loiret' => '45', 'Lot' => '46', 'Lot-et-Garonne' => '47',
        'Lozère' => '48', 'Maine-et-Loire' => '49', 'Manche' => '50', 'Marne' => '51', 'Haute-Marne' => '52',
        'Mayenne' => '53', 'Meurthe-et-Moselle' => '54', 'Meuse' => '55', 'Morbihan' => '56', 'Moselle' => '57',
        'Nièvre' => '58', 'Nord' => '59', 'Oise' => '60', 'Orne' => '61', 'Pas-de-Calais' => '62', 'Puy-de-Dôme' => '63',
        'Pyrénées-Atlantiques' => '64', 'Basses-Pyrénées' => '64', 'Hautes-Pyrénées' => '65', 'Pyrénées-Orientales' => '66',
        'Bas-Rhin' => '67', 'Haut-Rhin' => '68', 'Rhône' => '69', 'Haute-Saône' => '70', 'Saône-et-Loire' => '71',
        'Sarthe' => '72', 'Savoie' => '73', 'Haute-Savoie' => '74', 'Paris' => '75', 'Seine-Maritime' => '76',
        'Seine-Inférieure' => '76', 'Seine-et-Marne' => '77', 'Yvelines' => '78', 'Seine-et-Oise' => '78',
        'Deux-Sèvres' => '79', 'Somme' => '80', 'Tarn' => '81', 'Tarn-et-Garonne' => '82', 'Var' => '83',
        'Vaucluse' => '84', 'Vendée' => '85', 'Vienne' => '86', 'Haute-Vienne' => '87', 'Vosges' => '88',
        'Yonne' => '89', 'Territoire de Belfort' => '90', 'Essonne' => '91', 'Hauts-de-Seine' => '92',
        'Seine-Saint-Denis' => '93', 'Val-de-Marne' => '94', "Val-d'Oise" => '95', 'Seine' => '75',
    ];

    /** Mots de fin de libellé qui qualifient une GARE, pas un lieu (« Bourg-en-Bresse ESE », « Châteaulin-PO »). */
    private const STATION_SUFFIXES = '/[\s-]+(ville|gare|etat|état|po|ese|plm|sncf|nord|sud|est|ouest|centre|central|centrale|embranchement|transit|bourg|port|bifurcation|bif|halte|cfd|se|tramway|tram|voie metrique|vm|ancienne gare|place|eglise|église)$/iu';

    /** Libellés trop vagues pour être géocodés. */
    private const VAGUE = ['gare', 'la gare', 'ville', 'port', 'le port', 'la ville', 'bifurcation', 'embranchement', 'centre', 'la mer', 'mer', 'plage', 'frontiere', 'la frontiere'];

    /** Début de nom trop banal pour l'appariement par préfixe (« Saint », « Le »…). */
    private const PREFIX_STOP = ['saint', 'sainte', 'le', 'la', 'les', 'l', 'notre', 'mont', 'pont', 'port', 'val', 'bois', 'grand', 'petit', 'haut', 'bas', 'vieux', 'neuf', 'neuve', 'villeneuve', 'chateau', 'fort', 'la roche', 'roche'];

    /** @var array<string, list<array{0: float, 1: float, 2: ?string, 3: string, 4: int}>> nom normalisé → [lon, lat, dép, nom, population] */
    private array $communes = [];

    /** @var array<string, list<array{0: float, 1: float, 2: ?string, 3: string, 4: int}>> premier(s) mot(s) → communes */
    private array $prefix = [];

    /** @var array<string, list<array{0: float, 1: float, 2: ?string, 3: string}>> nom normalisé → gares */
    private array $stations = [];

    /** @var array<string, array{0: float, 1: float}> code département → barycentre des communes */
    private array $depCentroid = [];

    /** @var array<string, list<array{0: float, 1: float, 2: ?string, 3: string, 4: int}>> nom normalisé SANS espaces → communes (« plouezoch » → Plouezoc'h) */
    private array $compact = [];

    /**
     * Lieux-dits nommés par une extrémité de ligne VFF : « Ligne des Errues à Rougemont » situe
     * Les Errues au bout du tracé opposé à Rougemont(-le-Château). Ni commune ni gare.
     *
     * @var array<string, list<array{0: float, 1: float, 2: ?string, 3: string}>>
     */
    private array $endpoints = [];

    private bool $loaded = false;

    public function load(): void
    {
        if ($this->loaded) {
            return;
        }
        $sum = [];
        foreach (DB::select("SELECT nom, departement, type, COALESCE(population, 0) AS pop, ST_X(geom) AS lon, ST_Y(geom) AS lat FROM communes ORDER BY type = 'commune-actuelle' DESC") as $c) {
            $key = PlaceNames::normalize($c->nom);
            $row = [(float) $c->lon, (float) $c->lat, $c->departement, $c->nom, (int) $c->pop];
            // Une commune déléguée homonyme de sa commune nouvelle au même endroit : un seul candidat.
            $dup = false;
            foreach ($this->communes[$key] ?? [] as $o) {
                if ($o[2] === $row[2] && $this->meters($o, $row) < 3000) {
                    $dup = true;
                    break;
                }
            }
            if (! $dup) {
                $this->communes[$key][] = $row;
                $this->compact[str_replace(' ', '', $key)][] = $row;
            }
            if ($c->type === 'commune-actuelle' && $c->departement) {
                $sum[$c->departement][0] = ($sum[$c->departement][0] ?? 0) + $row[0];
                $sum[$c->departement][1] = ($sum[$c->departement][1] ?? 0) + $row[1];
                $sum[$c->departement][2] = ($sum[$c->departement][2] ?? 0) + 1;
            }
            // « Carhaix » → Carhaix-Plouguer, « Jassans » → Jassans-Riottier.
            $words = explode(' ', $key);
            if (count($words) >= 2) {
                foreach ([1, 2] as $n) {
                    if (count($words) <= $n) {
                        break;
                    }
                    $p = implode(' ', array_slice($words, 0, $n));
                    if (strlen($p) >= 4 && ! in_array($p, self::PREFIX_STOP, true) && ! in_array($words[0], self::PREFIX_STOP, true)) {
                        $this->prefix[$p][] = $row;
                    }
                }
            }
        }
        foreach ($sum as $dep => $s) {
            $this->depCentroid[$dep] = [$s[0] / $s[2], $s[1] / $s[2]];
        }
        // Noms principaux seulement : les noms alternatifs (« Gare de Lyon »…) sont trop bruités.
        foreach (DB::select('SELECT name, department_code, ST_X(geom) AS lon, ST_Y(geom) AS lat FROM stations') as $s) {
            $key = PlaceNames::normalize((string) $s->name);
            if ($key !== '') {
                $this->stations[$key][] = [(float) $s->lon, (float) $s->lat, $s->department_code, (string) $s->name];
            }
        }
        $this->loaded = true;
        $this->loadEndpoints();
    }

    /**
     * Index des lieux-dits d'extrémité de ligne (cf. $endpoints) : pour chaque ligne VFF
     * « Ligne de A à B » à tracé de référence, si A se géocode (commune, gare) près d'une
     * extrémité du tracé et que B ne se géocode près d'AUCUNE, B est situé à l'autre extrémité.
     */
    private function loadEndpoints(): void
    {
        $rows = DB::select(<<<'SQL'
            WITH d AS (SELECT r.line_id, (ST_Dump(ST_LineMerge(r.geom))).geom AS g FROM line_reference_geometries r),
                 p AS (SELECT line_id, ST_StartPoint(g) AS pt FROM d UNION ALL SELECT line_id, ST_EndPoint(g) FROM d),
                 e AS (SELECT DISTINCT ON (a.line_id) a.line_id, a.pt AS a, b.pt AS b
                       FROM p a JOIN p b ON a.line_id = b.line_id
                       ORDER BY a.line_id, ST_Distance(a.pt, b.pt) DESC)
            SELECT l.name, ST_X(e.a) AS ax, ST_Y(e.a) AS ay, ST_X(e.b) AS bx, ST_Y(e.b) AS by,
                   (SELECT c.departement FROM communes c ORDER BY c.geom <-> e.a LIMIT 1) AS adep,
                   (SELECT c.departement FROM communes c ORDER BY c.geom <-> e.b LIMIT 1) AS bdep
            FROM e JOIN lines l ON l.id = e.line_id
        SQL);
        foreach ($rows as $r) {
            $parsed = PlaceNames::parseLineName((string) $r->name);
            if (! $parsed || count($parsed['places']) < 2 || preg_match('/\b(près de|secteur|raccordement|bifurcation)\b/iu', (string) $r->name)) {
                continue;
            }
            $names = [$parsed['places'][0], $parsed['places'][count($parsed['places']) - 1]];
            $ends = [[(float) $r->ax, (float) $r->ay, $r->adep], [(float) $r->bx, (float) $r->by, $r->bdep]];
            if ($this->meters($ends[0], $ends[1]) < 1500) {
                continue;
            }
            // Extrémité de chaque nom d'après les communes et gares (≤ 6 km), -1 si aucune.
            $at = [];
            foreach ($names as $k => $name) {
                $at[$k] = -1;
                // Toutes les communes possibles, préfixes compris (« Mortagne-Port » : Mortagne-sur-Gironde,
                // pas seulement la commune homonyme Mortagne des Vosges).
                $cs = $this->candidates($name, null, false);
                foreach ($this->variants($name) as $v) {
                    foreach ($this->prefix[PlaceNames::normalize($v)] ?? [] as $p) {
                        $cs[] = ['lon' => $p[0], 'lat' => $p[1]];
                    }
                }
                foreach ($cs as $c) {
                    foreach ($ends as $ei => $e) {
                        if ($this->meters([$c['lon'], $c['lat']], $e) < 6000) {
                            $at[$k] = $at[$k] === -1 || $at[$k] === $ei ? $ei : -2; // -2 : les deux (ligne courte)
                        }
                    }
                }
            }
            foreach ([[0, 1], [1, 0]] as [$known, $other]) {
                if ($at[$known] >= 0 && $at[$other] === -1) {
                    $e = $ends[1 - $at[$known]];
                    $label = trim(preg_replace('/\s*\(.*?\)\s*/u', ' ', $names[$other]));
                    $row = [$e[0], $e[1], $e[2], $label];
                    foreach (array_unique([PlaceNames::normalize($label), PlaceNames::normalize(preg_replace('/^(?:La|Le|Les|L[\'’])\s*/u', '', $label))]) as $key) {
                        if (mb_strlen($key) >= 3) {
                            $this->endpoints[$key][] = $row;
                        }
                    }
                }
            }
        }
    }

    /** Barycentre des communes d'un département (contexte d'article). */
    public function departementCentroid(string $code): ?array
    {
        $this->load();

        return $this->depCentroid[$code] ?? null;
    }

    /**
     * Codes des départements nommés dans un texte (titre d'article, qualificatif de lien).
     *
     * @return list<string>
     */
    public static function departementsIn(string $text, bool $strict = false): array
    {
        $out = [];
        $names = array_keys(self::DEPARTEMENTS);
        usort($names, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a)); // « Haute-Vienne » avant « Vienne »
        $t = ' '.$text.' ';
        foreach ($names as $name) {
            // Mode strict (titres, catégories) : un nom simple (« Vienne », « Ain », « Nord ») n'est un
            // département que précédé de son article (« de la Vienne », « dans l'Ain », « du Nord ») ;
            // les noms composés (« Haute-Vienne », « Loir-et-Cher ») sont sans ambiguïté.
            $lead = $strict && ! preg_match('/[\s-]/u', $name)
                ? '(?<=\bla |\ble |\bles |\bl\'|\bl’|\bdu |\bdes |\ben )'
                : '(?<![\p{L}-])';
            $rx = '/'.$lead.preg_quote($name, '/').'(?![\p{L}-])/u';
            if (preg_match($rx, $t)) {
                $out[] = self::DEPARTEMENTS[$name];
                $t = preg_replace($rx, ' ', $t);
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Tous les candidats d'un toponyme (avant arbitrage par le contexte).
     *
     * @return list<array{lon: float, lat: float, dep: ?string, name: string, kind: string, rank: int}>
     */
    public function candidates(string $label, ?string $target = null, bool $withEndpoints = true): array
    {
        $this->load();
        $out = [];
        $add = function (array $rows, string $kind, int $rank, ?string $dep = null) use (&$out) {
            foreach ($rows as $r) {
                if ($dep !== null && $r[2] !== $dep) {
                    continue;
                }
                $out[] = ['lon' => $r[0], 'lat' => $r[1], 'dep' => $r[2], 'name' => $r[3], 'kind' => $kind, 'rank' => $rank];
            }
        };

        // 1. Cible du lien wiki : la plus fiable (commune qualifiée par son département, ou gare).
        if ($target !== null && $target !== '') {
            $t = trim(preg_replace('/#.*$/u', '', $target));
            if (preg_match('/^(?:Gare|Halte|Station|Arrêt)\s+(?:de\s+|d[\'’]\s*|du\s+|des\s+)(.+)$/iu', $t, $m)) {
                $name = trim(preg_replace('/\s*\(.*\)\s*$/u', '', $m[1]));
                $add($this->stations[$this->stationKey($name)] ?? [], 'gare (lien)', 0);
                $deps = preg_match('/\((.+)\)\s*$/u', $m[1], $q) ? self::departementsIn($q[1]) : [];
                $add($this->communes[PlaceNames::normalize($this->stationName($name))] ?? [], 'commune (lien gare)', 1, $deps[0] ?? null);
            } elseif (! preg_match('/^(Ligne|Chemin|Tramway|Réseau|Compagnie|Société|Viaduc|Pont|Tunnel|Canal|Fleuve|Rivière)\b/iu', $t)) {
                $qual = null;
                if (preg_match('/^(.+?)\s*\((.+)\)\s*$/u', $t, $m)) {
                    $t = $m[1];
                    $qual = self::departementsIn($m[2])[0] ?? null;
                }
                $add($this->communes[PlaceNames::normalize($t)] ?? [], 'commune (lien)', 0, $qual);
            }
            if ($out) {
                return $out;
            }
        }

        // 2. Libellé : gare puis commune homonyme, puis variantes sans qualificatif de gare ;
        // 3. avec les communes dont le nom COMMENCE par le libellé (« Carhaix » → Carhaix-Plouguer,
        //    « Vailly » → Vailly-sur-Aisne) : le contexte de l'article départagera.
        foreach ($this->variants($label) as $i => $v) {
            $add($this->stations[$this->stationKey($v)] ?? [], 'gare', 2 + $i);
            $add($this->communes[PlaceNames::normalize($v)] ?? [], 'commune', 2 + $i);
            if ($out) {
                break;
            }
        }
        foreach ($this->variants($label) as $v) {
            $before = count($out);
            $add($this->prefix[PlaceNames::normalize($v)] ?? [], 'commune (préfixe)', 6);
            if (count($out) > $before) {
                break;
            }
        }
        // 4. Repli : autre graphie (« Plouézoch » → Plouezoc'h) ou article (« Grand-Lucé » → Le Grand-Lucé).
        if (! $out) {
            foreach ($this->variants($label) as $v) {
                $add($this->compact[str_replace(' ', '', PlaceNames::normalize($v))] ?? [], 'commune (graphie)', 7);
                foreach (self::articleVariants($v) as $a) {
                    $add($this->stations[$this->stationKey($a)] ?? [], 'gare (article)', 7);
                    $add($this->communes[PlaceNames::normalize($a)] ?? [], 'commune (article)', 7);
                }
                if ($out) {
                    break;
                }
            }
        }
        // 5. Lieu-dit nommé par une extrémité de ligne VFF (« Les Errues », « Touvent ») : toujours
        //    proposé, le contexte de l'article départage d'avec les communes homonymes lointaines.
        if ($withEndpoints) {
            foreach ($this->variants($label) as $v) {
                $before = count($out);
                foreach (array_merge([$v], self::articleVariants($v)) as $a) {
                    $add($this->endpoints[PlaceNames::normalize($a)] ?? [], 'lieu-dit (extrémité de ligne)', 8);
                }
                if (count($out) > $before) {
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * Le même nom avec ou sans article initial : « Grand-Lucé » ↔ « Le Grand-Lucé »,
     * « Les Errues » → « Errues ».
     *
     * @return list<string>
     */
    public static function articleVariants(string $label): array
    {
        $label = trim($label);
        if (preg_match('/^(?:La|Le|Les)\s+(.+)$|^L[\'’]\s*(.+)$/u', $label, $m)) {
            return [trim($m[1] !== '' ? $m[1] : $m[2])];
        }
        if (! preg_match('/^\p{Lu}/u', $label)) {
            return [];
        }

        return preg_match('/^[AEIOUYÉÈÊÂÎÔ]/u', $label) ? ["L'".$label] : ['Le '.$label, 'La '.$label, 'Les '.$label];
    }

    /**
     * Toponyme → point, en départageant les homonymes par le contexte de
     * l'article. Null si inconnu ou ambigu.
     *
     * @param  array{lon: float, lat: float}|null  $ctx  barycentre de l'article
     * @param  list<string>  $deps  départements de l'article
     * @return array{lon: float, lat: float, dep: ?string, name: string, kind: string}|null
     */
    public function geocode(string $label, ?string $target, ?array $ctx, array $deps = []): ?array
    {
        $label = trim($label);
        if (mb_strlen($label) < 2 || in_array(PlaceNames::normalize($label), self::VAGUE, true)) {
            return null;
        }
        $cands = $this->candidates($label, $target);
        if (! $cands) {
            return null;
        }
        // Regroupe les candidats proches (gare + commune du même lieu).
        $groups = [];
        foreach ($cands as $c) {
            foreach ($groups as &$g) {
                if ($this->meters([$g[0]['lon'], $g[0]['lat']], [$c['lon'], $c['lat']]) < 6000) {
                    $g[] = $c;

                    continue 2;
                }
            }
            unset($g);
            $groups[] = [$c];
        }
        unset($g);
        if (count($groups) === 1) {
            $best = $groups[0][0];
            // Unique en France : seulement plausible s'il n'est pas à l'autre bout du pays.
            if ($ctx && $this->meters([$best['lon'], $best['lat']], [$ctx['lon'], $ctx['lat']]) > 150000) {
                return null;
            }

            return $best;
        }
        if (! $ctx && ! $deps) {
            return null; // homonymes sans contexte : ambigu
        }
        // Homonymes : celui du département de l'article, sinon le plus proche du barycentre.
        if ($deps) {
            $inDep = array_values(array_filter($groups, fn ($g) => in_array($g[0]['dep'], $deps, true)));
            if (count($inDep) === 1) {
                return $inDep[0][0];
            }
            if (count($inDep) > 1) {
                $groups = $inDep;
            }
        }
        if (! $ctx) {
            return null;
        }
        $scored = [];
        foreach ($groups as $g) {
            $scored[] = [$this->meters([$g[0]['lon'], $g[0]['lat']], [$ctx['lon'], $ctx['lat']]), $g[0]];
        }
        usort($scored, fn ($a, $b) => $a[0] <=> $b[0]);
        [$d1, $best] = $scored[0];
        $d2 = $scored[1][0];
        if ($d1 > 150000) {
            return null;
        }
        if ($d2 < max(30000, 2.5 * $d1)) {
            // Le plus proche est une COMMUNE de ce nom, les homonymes plus lointains ne sont que des gares
            // (« Grasse » : la commune des Alpes-Maritimes, pas un arrêt « Grasse » de Marseille) : la commune.
            $isCommune = fn (array $g) => (bool) array_filter($g, fn ($c) => in_array($c['kind'], ['commune', 'commune (lien)'], true));
            $nearest = null;
            foreach ($groups as $g) {
                if ($g[0] === $best) {
                    $nearest = $g;
                }
            }
            $others = array_filter($groups, fn ($g) => $g[0] !== $best);
            if (! $nearest || ! $isCommune($nearest) || array_filter($others, $isCommune)) {
                return null; // le second homonyme est presque aussi plausible : ambigu
            }
        }

        return $best;
    }

    /**
     * Variantes d'un libellé : tel quel, sans qualificatif de gare
     * (« Bourg-en-Bresse ESE », « Concarneau Ville », « Châteaulin-PO »).
     *
     * @return list<string>
     */
    public function variants(string $label): array
    {
        $label = trim(preg_replace('/\s*\(.*?\)\s*/u', ' ', $label));
        $out = [$label];
        $v = $label;
        for ($i = 0; $i < 2; $i++) {
            $n = trim(preg_replace(self::STATION_SUFFIXES, '', $v));
            if ($n === $v || mb_strlen($n) < 2) {
                break;
            }
            $out[] = $v = $n;
        }
        $n = trim(preg_replace('/^(?:gare|halte|station|arrêt|bifurcation|bif\.?)\s+(?:de\s+|d[\'’]\s*|du\s+|des\s+)?/iu', '', $label));
        if ($n !== $label && mb_strlen($n) >= 2) {
            $out[] = $n;
        }

        // « St-Jean » → « Saint-Jean » est géré par PlaceNames::normalize.
        return array_values(array_unique($out));
    }

    private function stationName(string $name): string
    {
        return trim(preg_replace(self::STATION_SUFFIXES, '', $name));
    }

    private function stationKey(string $name): string
    {
        $n = preg_replace('/^(?:gare|halte|station|arrêt)\s+(?:de\s+|d[\'’]\s*|du\s+|des\s+)?/iu', '', trim($name));

        return PlaceNames::normalize($n);
    }

    /** Distance approximative en mètres entre deux points [lon, lat]. */
    public function meters(array $a, array $b): float
    {
        $k = cos(deg2rad(($a[1] + $b[1]) / 2));
        $dx = ($a[0] - $b[0]) * 111320 * $k;
        $dy = ($a[1] - $b[1]) * 110540;

        return sqrt($dx * $dx + $dy * $dy);
    }
}
