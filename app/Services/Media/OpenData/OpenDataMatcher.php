<?php

namespace App\Services\Media\OpenData;

use Illuminate\Support\Facades\DB;

/**
 * Rattache une vue ferroviaire d'un jeu ouvert (OpenDataNormalizer) à une gare ou à une ligne de VFF, avec une
 * méthode et une confiance écrites dans `provider_meta`. Règles prudentes : jamais de rattachement sur la seule
 * proximité d'un point qui peut être le centre de la commune ; un doute reste hors base (motif rendu).
 *
 * Une vue n'est rattachée à une gare que si son titre, sa légende ou le thème du jeu parlent d'une gare ou d'une halte
 * (hors noms de rues : « rue de la Gare » ne dit rien de la gare). Gare, dans l'ordre :
 *  1. `nom` : tous les mots du nom de la gare (ou d'un nom alternatif) se lisent dans le texte ; `nom-commune` : ils se
 *     lisent dans le texte et le nom de la commune réunis (« La Gare » à Clamart). Les gares cherchées sont celles de la
 *     commune et, quand le jeu donne un point, celles qui en sont proches (La Garenne-Colombes est à Colombes). La gare qui
 *     a le plus de mots l'emporte ; une égalité se départage par le point précis, sinon reste sans rattachement ;
 *  2. `point` : le point précis de la vue est à 300 m au plus d'une gare de la commune et à 150 m de plus que la suivante ;
 *  3. `commune-unique` : une seule gare ou halte dans la commune.
 * Avec un point précis, une gare à plus de 1,5 km (nom) ou 350 m (commune) est refusée.
 * Ligne : seulement pour les jeux au point précis (`point_precise`), vue de viaduc, tunnel, passage à niveau, train ou chemin de
 * fer, ou de tramway ou funiculaire (alors seulement une ligne dont le nom porte ce mode) : les lignes dont un tracé passe à 100 m au plus du point, trois au plus.
 */
class OpenDataMatcher
{
    public const STATION_POINT_M = 300;

    public const COMMUNE_RADIUS_M = 1500;

    /** Rayon autour du centre de la commune (points d'Albert-Kahn) où chercher une gare qui porte son nom. */
    public const CENTROID_RADIUS_M = 4000;

    /** Écart maximal du point précis d'une vue à la gare nommée dans son titre. */
    public const NAMED_MAX_M = 1500;

    /** Écart maximal pour une gare retenue par le nom de la commune ou parce qu'elle est seule. */
    public const COMMUNE_NAMED_MAX_M = 350;

    public const LINE_POINT_M = 100;

    /** @var array<int, array{id: int, name: string, key: string, tokens: list<string>, alts: list<list<string>>, city: string, type: string, status: string, lat: float, lng: float}> */
    private array $stations = [];

    /** @var array<string, list<int>> clé de commune => index dans */
    private array $byCommune = [];

    public function __construct(bool $load = true)
    {
        if ($load) {
            $this->load();
        }
    }

    private function load(): void
    {
        $rows = DB::select('SELECT id, name, alt_names, city, type, status, ST_Y(geom) AS lat, ST_X(geom) AS lng FROM stations');
        foreach ($rows as $r) {
            $alts = [];
            foreach ((array) json_decode((string) ($r->alt_names ?? '[]'), true) as $alt) {
                $tokens = OpenDataText::tokens(is_array($alt) ? ($alt['name'] ?? '') : (string) $alt);
                if ($tokens !== []) {
                    $alts[] = $tokens;
                }
            }
            $this->add((int) $r->id, (string) $r->name, (string) $r->city, (string) $r->type, (string) $r->status, (float) $r->lat, (float) $r->lng, $alts);
        }
    }

    /**
     * @param  list<list<string>>  $alts
     */
    public function add(int $id, string $name, string $city, string $type, string $status, float $lat, float $lng, array $alts = []): void
    {
        $index = count($this->stations);
        $this->stations[$index] = [
            'id' => $id, 'name' => $name, 'key' => self::communeKey($city), 'tokens' => OpenDataText::tokens($name),
            'alts' => $alts, 'city' => $city, 'type' => $type, 'status' => $status, 'lat' => $lat, 'lng' => $lng,
        ];
        $this->byCommune[self::communeKey($city)][] = $index;
    }

    /** « Paris 15e Arrondissement » et « Paris » donnent la même clé. */
    public static function communeKey(?string $city): string
    {
        $city = (string) preg_replace('/\s+\d+\s*(?:er|e|ème)?\s+arrondissement\s*$/iu', '', (string) $city);

        return OpenDataText::fold($city);
    }

    /**
     * @param  array<string, mixed>  $candidate  sortie de OpenDataNormalizer
     * @param  array<string, mixed>  $dataset
     * @return array{kind: string, id: int, name: string, method: string, distance_m: ?int, confidence: string, also?: list<int>}|array{kind: null, reason: string}
     */
    public function match(array $candidate, array $dataset): array
    {
        // Fonds qui mêlent tramways et chemins de fer (Pireneas) : « Tramway de la Gare » ne montre pas la gare, une vue de tramway
        // reste sans rattachement (VFF ne décrit pas les tramways urbains), sauf si le titre parle aussi d'un chemin de fer ou de la SNCF.
        if (! empty($dataset['tram_apart']) && preg_match('/\b(?:tramways?|tram|funiculaires?|ficelle)\b/iu', (string) $candidate['title']) === 1
            && preg_match('/\b(?:chemins? de fer|SNCF|P\.?O\.?|Midi)\b/u', (string) $candidate['title']) !== 1) {
            return ['kind' => null, 'reason' => 'tramway'];
        }
        // « Vue prise de la gare », « sentier au-dessus de la gare » : la gare est le point de vue ou un repère, pas le sujet.
        if (! empty($dataset['viewpoint_apart']) && preg_match('/\b(?:vues?|vus?|prises?|depuis)\b[^,;:]*\b(?:de|du|d\'|depuis)\s+(?:la\s+|l\')?gare\b|\b(?:au[- ]dessus|en face|pr[èe]s|au[- ]del[àa]|en dessous)\s+(?:de\s+la\s+|de\s+l\'|du\s+)gare\b|\bsentiers?\b/iu', (string) $candidate['title']) === 1) {
            return ['kind' => null, 'reason' => 'point_de_vue'];
        }
        $candidate['point_precise'] = ! empty($dataset['point_precise']) && $candidate['lat'] !== null && $candidate['lng'] !== null;
        $alias = $dataset['commune_aliases'][OpenDataText::fold((string) $candidate['commune'])] ?? null;
        $candidate['pool_commune'] = $alias ?? $candidate['commune'];
        $candidate['station_aliases'] = $dataset['station_aliases'] ?? [];
        $candidate['title_aliases'] = $dataset['title_aliases'] ?? [];
        $station = $this->matchStation($candidate);
        if ($station['kind'] !== null) {
            return $station;
        }
        $reason = $station['reason'];

        // Une vue qui parle d'une gare ne passe jamais à la ligne : un doute sur la gare reste un doute.
        if ($reason === 'sans_mot_gare' && $candidate['point_precise']
            && preg_match('/\b(viaducs?|tunnels?|chemins? de fer|trains?|voies? ferr[ée]es?|passages? [àa] niveau|tramways?|funiculaires?|ficelle)\b/iu', OpenDataText::withoutStreets($candidate['title'].' '.$candidate['themes'])) === 1) {
            // Un funiculaire ou un tramway ne s'attache qu'à une ligne du même mode (par son nom) ; toute autre vue, à une ligne qui n'en est pas.
            $mode = preg_match('/\bfuniculaires?|ficelle\b/iu', (string) $candidate['title']) === 1 ? 'funiculaire' : (preg_match('/\btramways?\b/iu', (string) $candidate['title']) === 1 ? 'tram' : null);
            $lines = array_values(array_filter(
                $this->linesNear((float) $candidate['lat'], (float) $candidate['lng']),
                fn (array $l): bool => $mode !== null
                    ? str_contains(OpenDataText::fold($l['name']), $mode)
                    : preg_match('/funiculaire|tram/', OpenDataText::fold($l['name'])) !== 1,
            ));
            if ($lines !== [] && count($lines) <= 3) {
                $first = $lines[0];

                return ['kind' => 'line', 'id' => $first['id'], 'name' => $first['name'], 'method' => 'ligne-point', 'distance_m' => $first['distance_m'], 'confidence' => 'moyenne',
                    'also' => array_slice(array_column($lines, 'id'), 1)];
            }
            $reason = $lines === [] ? $reason : 'lignes_trop_nombreuses';
        }

        // Fonds sans point ni commune fiable (École des ponts, Getty, Numistral) : la gare ou la ligne nommée dans le titre.
        if (! empty($dataset['title_match'])) {
            $named = $this->matchByTitle($candidate);
            if ($named['kind'] !== null) {
                return $named;
            }
            $reason = $named['reason'];
        }

        return ['kind' => null, 'reason' => $reason];
    }

    /** @var list<array{id: int, name: string, a: list<string>, b: list<string>}>|null */
    private ?array $lineEnds = null;

    /**
     * Rattachement par le titre seul. Gare : « Gare de X » (ou « halte », « station ») dont le nom, ou le nom suivi de ce qui
     * est entre parenthèses (« Gare de Lyon (Perrache) »), est celui d'une seule gare de VFF (mots égaux, rien d'approché).
     * Ligne : « Ligne de A à B », « Chemin de fer de A à B », « Embranchement de A à B » dont A et B sont les deux
     * extrémités d'une seule ligne de VFF (mots égaux d'abord ; à défaut, A et B débutent les extrémités, puis s'y lisent, et une
     * seule ligne convient), dans un sens ou dans l'autre. Un doute reste sans rattachement.
     *
     * @param  array<string, mixed>  $c
     * @return array{kind: string, id: int, name: string, method: string, distance_m: ?int, confidence: string, city?: string, status?: string}|array{kind: null, reason: string}
     */
    public function matchByTitle(array $c): array
    {
        $title = (string) ($c['title'] ?? '');
        $reason = 'sans_nom_dans_le_titre';
        // Noms que le fonds donne à une gare sans la nommer comme VFF (« Strasbourg - La Gare Centrale » est Strasbourg-Ville) : saisis à la main.
        foreach ((array) ($c['title_aliases'] ?? []) as $pattern => $stationName) {
            if (preg_match($pattern, $title) === 1) {
                $tokens = OpenDataText::tokens($stationName);
                $found = array_keys(array_filter($this->stations, fn (array $s): bool => $s['tokens'] === $tokens));
                if (count($found) === 1) {
                    return $this->hit($found[0], 'alias-titre', $c, 'moyenne', null);
                }
            }
        }
        $subject = OpenDataText::withoutStreets($title, true);
        // « Station du chemin de fer à Thann » : le nom de la gare vient après « chemin de fer à » (sauf point de vue : « vis à vis de la station »).
        $viaRailway = preg_match('/\b(?:prise?s?|vis [àa] vis|depuis)\b/iu', $title) !== 1
            && preg_match('/\b(?i:gare|station|halte)\b[^.;:()]*?\b(?i:chemin de fer)\s+(?:à|a)\s+(\p{Lu}[\p{L}\'’-]+(?:[ -]\p{Lu}[\p{L}\'’-]+)*)/u', $subject, $at) === 1;
        if ($viaRailway) {
            $m = [$at[0], $at[1]];
            $found = true;
        } else {
            $found = preg_match('/\b(?:gare|halte|station)\s+(?:du |de la |de l\'|des |de |d\')?\s*([^.,;:()]+)(?:\(([^)]+)\))?/iu', $subject, $m, PREG_OFFSET_CAPTURE) === 1;
        }
        // Dans un titre sur Paris ou son métropolitain, « la gare d'Orléans » est celle de Paris-Austerlitz, pas Orléans.
        if ($found && preg_match('/\b(?:m[ée]tropolitain|Paris)\b/iu', $title) === 1) {
            return ['kind' => null, 'reason' => 'gare_de_paris'];
        }
        // Une gare citée entre parenthèses ou comme point de vue (« prise des combles de la gare d'Orléans ») n'est pas le sujet de la vue.
        if ($found && ! $viaRailway) {
            $before = substr($subject, 0, (int) $m[0][1]);
            if (substr_count($before, '(') > substr_count($before, ')') || preg_match('/\b(?:prise?s?|depuis|du haut|des combles|vue d(?:e|u|es) )\s+(?:\S+\s+){0,3}$/iu', $before) === 1) {
                $found = false;
            }
            $m = array_map(fn (array $g): string => $g[0], $m);
        }
        if ($found) {
            // Le nom, puis le nom suivi de ce qui est entre parenthèses (« Gare de Lyon (Perrache) ») ; jamais la parenthèse seule (« (marchandises) »).
            $variants = array_filter([OpenDataText::tokens($m[1]), isset($m[2]) ? OpenDataText::tokens($m[1].' '.$m[2]) : null]);
            foreach ($variants as $tokens) {
                $found = [];
                foreach ($this->stations as $i => $s) {
                    foreach (array_merge([$s['tokens']], $s['alts']) as $names) {
                        if ($names !== [] && $names === $tokens) {
                            $found[$i] = true;
                        }
                    }
                }
                if (count($found) === 1) {
                    $i = array_key_first($found);

                    return $this->hit($i, 'nom-titre', $c, 'moyenne', null);
                }
                // « Gare de Nice » : la gare principale d'une ville se nomme « Nice-Ville » dans VFF, à condition qu'elle soit seule.
                if ($found === []) {
                    $main = [];
                    foreach ($this->stations as $i => $s) {
                        if ($s['tokens'] === array_merge($tokens, ['ville'])) {
                            $main[] = $i;
                        }
                    }
                    if (count($main) === 1) {
                        return $this->hit($main[0], 'nom-titre-ville', $c, 'basse', null);
                    }
                }
                if (count($found) > 1) {
                    $reason = 'noms_ambigus';
                }
            }
            if ($reason === 'sans_nom_dans_le_titre') {
                $reason = 'gare_inconnue';
            }
            if (preg_match('/^\s*gares?\b/iu', $title) === 1) {
                return ['kind' => null, 'reason' => $reason];
            }
        }

        if (preg_match('/\b(?:ligne|chemins? de fer|embranchement|section)\s+(?:du |de la |de l\'|des |de |d\')?\s*([^.,;:()]+?)\s+(?:à|a)\s+([^.,;:()]+?)\s*(?:[.,;:(]|$)/iu', $title, $m) === 1) {
            $a = OpenDataText::tokens($m[1]);
            $b = OpenDataText::tokens($m[2]);
            if ($a !== [] && $b !== []) {
                $exact = [];
                $prefix = [];
                $inside = [];
                // Une ligne à grande vitesse ne porte pas les vues d'avant : « Paris à Strasbourg » de 1852 n'est pas la LGV Est.
                $fast = preg_match('/\b(?:lgv|tgv|grande vitesse)\b/iu', $title) === 1;
                foreach ($this->lineEnds() as $l) {
                    if (! $fast && preg_match('/\blgv\b/iu', $l['name']) === 1) {
                        continue;
                    }
                    foreach ([[$l['a'], $l['b']], [$l['b'], $l['a']]] as [$x, $y]) {
                        if ($x === $a && $y === $b) {
                            $exact[$l['id']] = $l;
                        } elseif (array_slice($x, 0, count($a)) === $a && array_slice($y, 0, count($b)) === $b) {
                            $prefix[$l['id']] = $l;
                        } elseif (self::contains($x, $a) && self::contains($y, $b)) {
                            // « Cahors à Libos » pour une ligne de VFF « Cahors à Monsempron-Libos ».
                            $inside[$l['id']] = $l;
                        }
                    }
                }
                $tier = $exact !== [] ? 'ligne-titre' : ($prefix !== [] ? 'ligne-titre-debut' : 'ligne-titre-contenu');
                $pool = $exact !== [] ? $exact : ($prefix !== [] ? $prefix : $inside);
                if (count($pool) === 1) {
                    $l = reset($pool);

                    return ['kind' => 'line', 'id' => $l['id'], 'name' => $l['name'], 'method' => $tier, 'distance_m' => null, 'confidence' => $exact !== [] ? 'moyenne' : 'basse'];
                }

                if ($pool === []) {
                    // « Pau à Oloron » : aucune ligne de VFF ne porte ces deux noms pour extrémités, mais une seule dessert les gares de Pau et d'Oloron.
                    $served = $this->lineServingBoth($a, $b, $fast);
                    if ($served !== null) {
                        return ['kind' => 'line', 'id' => $served['id'], 'name' => $served['name'], 'method' => 'ligne-titre-gares', 'distance_m' => null, 'confidence' => 'basse'];
                    }
                }

                return ['kind' => null, 'reason' => $pool === [] ? 'ligne_inconnue' : 'lignes_multiples'];
            }
        }

        return ['kind' => null, 'reason' => $reason];
    }

    /** @var array<int, string> id de ligne => nom */
    private array $lineNames = [];

    /** @var array<int, list<int>>|null id de gare => lignes qui la desservent */
    private ?array $serving = null;

    /**
     * La seule ligne qui dessert une gare nommée A et une gare nommée B (noms égaux, à défaut débutés par A ou B), sinon rien.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return array{id: int, name: string}|null
     */
    private function lineServingBoth(array $a, array $b, bool $fast): ?array
    {
        $this->lineEnds();
        $serving = $this->serving();
        $linesOf = function (array $tokens) use ($serving): array {
            $exact = [];
            $prefix = [];
            foreach ($this->stations as $s) {
                if ($s['tokens'] === $tokens) {
                    $exact[] = $s['id'];
                } elseif (array_slice($s['tokens'], 0, count($tokens)) === $tokens) {
                    $prefix[] = $s['id'];
                }
            }
            // Les gares retenues doivent être d'une seule ville (« Saint-Étienne » débute les noms de dizaines de gares de communes différentes).
            $ids = $exact !== [] ? $exact : $prefix;
            $cities = [];
            foreach ($this->stations as $s) {
                if (in_array($s['id'], $ids, true)) {
                    $cities[$s['key']] = true;
                }
            }
            $lines = [];
            if (count($cities) === 1) {
                foreach ($ids as $id) {
                    foreach ($serving[$id] ?? [] as $lineId) {
                        $lines[$lineId] = true;
                    }
                }
            }

            return $lines;
        };
        $common = array_keys(array_intersect_key($linesOf($a), $linesOf($b)));
        $common = array_values(array_filter($common, fn (int $id): bool => $fast || preg_match('/\blgv\b/iu', $this->lineNames[$id] ?? '') !== 1));
        if (count($common) !== 1 || ! isset($this->lineNames[$common[0]])) {
            return null;
        }

        return ['id' => $common[0], 'name' => $this->lineNames[$common[0]]];
    }

    /** @return array<int, list<int>> */
    private function serving(): array
    {
        if ($this->serving === null) {
            $this->serving = [];
            foreach (DB::select('SELECT station_id, line_id FROM station_lines') as $r) {
                $this->serving[(int) $r->station_id][] = (int) $r->line_id;
            }
        }

        return $this->serving;
    }

    /** Gare desservie par une ligne (pour les essais sans base). */
    public function addServing(int $stationId, int $lineId): void
    {
        $this->serving ??= [];
        $this->serving[$stationId][] = $lineId;
    }

    /**
     * Les jetons `$needle` se suivent dans `$haystack`.
     *
     * @param  list<string>  $haystack
     * @param  list<string>  $needle
     */
    private static function contains(array $haystack, array $needle): bool
    {
        for ($i = 0, $n = count($needle); $i + $n <= count($haystack); $i++) {
            if (array_slice($haystack, $i, $n) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Les deux extrémités (jetons) de chaque ligne nommée « Ligne de A à B ».
     *
     * @return list<array{id: int, name: string, a: list<string>, b: list<string>}>
     */
    private function lineEnds(): array
    {
        if ($this->lineEnds === null) {
            $this->lineEnds = [];
            foreach (DB::select("SELECT id, name FROM lines WHERE name ~* '^(ligne|embranchement|section) (du |de la |de l.|des |de |d.)?.+ à .+'") as $r) {
                $this->lineNames[(int) $r->id] = (string) $r->name;
                if (preg_match('/^(?:ligne|embranchement|section)\s+(?:du |de la |de l\'|des |de |d\')?\s*(.+?)\s+à\s+(.+)$/iu', (string) $r->name, $m) === 1) {
                    $this->lineEnds[] = ['id' => (int) $r->id, 'name' => (string) $r->name, 'a' => OpenDataText::tokens($m[1]), 'b' => OpenDataText::tokens($m[2])];
                }
            }
        }

        return $this->lineEnds;
    }

    /**
     * Lignes de test sans base : « Ligne de A à B ».
     */
    public function addLine(int $id, string $name): void
    {
        $this->lineEnds ??= [];
        $this->lineNames[$id] = $name;
        if (preg_match('/^(?:ligne|embranchement|section)\s+(?:du |de la |de l\'|des |de |d\')?\s*(.+?)\s+à\s+(.+)$/iu', $name, $m) === 1) {
            $this->lineEnds[] = ['id' => $id, 'name' => $name, 'a' => OpenDataText::tokens($m[1]), 'b' => OpenDataText::tokens($m[2])];
        }
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array{kind: string, id: int, name: string, method: string, distance_m: ?int, confidence: string}|array{kind: null, reason: string}
     */
    public function matchStation(array $c): array
    {
        $precise = (bool) ($c['point_precise'] ?? false);
        $hasPoint = $c['lat'] !== null && $c['lng'] !== null;
        $communeKey = ($c['pool_commune'] ?? $c['commune']) !== null ? self::communeKey((string) ($c['pool_commune'] ?? $c['commune'])) : '';
        $byCity = $communeKey !== '' ? ($this->byCommune[$communeKey] ?? []) : [];
        // Les gares d'une commune voisine portent parfois le nom de la commune (La Garenne-Colombes est à Colombes) : le point les ajoute.
        $pool = $byCity;
        if ($hasPoint) {
            $pool = array_values(array_unique(array_merge($byCity, $this->near((float) $c['lat'], (float) $c['lng'], $precise ? self::COMMUNE_RADIUS_M : self::CENTROID_RADIUS_M))));
        }
        if ($pool === []) {
            return ['kind' => null, 'reason' => $communeKey === '' ? 'sans_commune' : 'commune_sans_gare'];
        }

        $raw = implode(' ', array_filter([$c['title'], $c['title_alt'] ?? null, $c['station_hint'] ?? null, $c['caption'] ?? null]));
        // « Gare du funiculaire » (la gare d'un autre mode) et « Café de la Gare » ou « Hôtel Terminus » (un commerce) : le thème « Gare » du jeu n'y change rien.
        if (preg_match('/\bgares?\s+(?:du|de la|des)\s+(?:funiculaire|tramways?|bateaux?)\b|\b(?:cafe|restaurant|hotel|brasserie|auberge|buvette|epicerie|pharmacie|librairie)(?: restaurant)?(?: [a-z0-9]+){0,3}? (?:de la|du|de l) gare\b|\bstations? (?:du|des|de la) (?:tramways?|funiculaire|bateaux?|metro|autobus)\b|\bhotel terminus\b/', OpenDataText::fold($raw)) === 1) {
            return ['kind' => null, 'reason' => 'sans_mot_gare'];
        }
        $stripped = OpenDataText::withoutStreets($raw, true);
        $own = OpenDataText::fold($stripped);
        // Quand le titre ne cite la gare que dans le nom d'une rue ou d'un commerce, les thèmes du jeu (« Gare ») ne suffisent pas.
        $nameOnly = trim($stripped) !== trim($raw) && preg_match('/\\b(gares?|halte)\\b/', $own) !== 1;
        // Une vue qui ne parle ni de gare ni de halte n'est pas rattachée à une gare (viaduc, tramway…) ; « station » ne compte pas
        // quand il s'agit d'un tramway, d'un funiculaire ou d'un bateau.
        $says = preg_match('/\b(gares?|halte)\b/', $own) === 1
            || (! $nameOnly && preg_match('/\bgares?\b/', OpenDataText::fold((string) ($c['themes'] ?? '').' '.(string) ($c['subjects'] ?? ''))) === 1)
            || (preg_match('/\bstation\b/', $own) === 1 && preg_match('/\b(tramways?|funiculaires?|metro|electrique|bateaux?|autobus|taxis?|service)\b/', $own) !== 1);
        if (! $says) {
            return ['kind' => null, 'reason' => 'sans_mot_gare'];
        }
        $ownWords = array_flip(explode(' ', $own));
        $fullWords = $ownWords + array_flip(explode(' ', OpenDataText::fold((string) $c['commune'])));

        // 1. Le nom de la gare se lit dans le texte (`nom`), ou dans le texte et le nom de la commune (`nom-commune`).
        $best = [];
        $bestScore = 0;
        foreach ($pool as $i) {
            $s = $this->stations[$i];
            $score = 0;
            $viaText = false;
            $alts = $s['alts'];
            foreach ($c['station_aliases'][$s['name']] ?? [] as $aliasName) {
                $alts[] = OpenDataText::tokens($aliasName);
            }
            foreach (array_merge([$s['tokens']], $alts) as $tokens) {
                if ($tokens === [] || (count($tokens) === 1 && mb_strlen($tokens[0]) < 3)) {
                    continue;
                }
                $inOwn = count(array_diff($tokens, array_keys($ownWords))) === 0;
                $inFull = count(array_diff($tokens, array_keys($fullWords))) === 0;
                if (($inOwn || $inFull) && count($tokens) > $score) {
                    $score = count($tokens);
                    $viaText = $inOwn;
                }
            }
            if ($score > $bestScore) {
                $best = [$i => $viaText];
                $bestScore = $score;
            } elseif ($score === $bestScore && $score > 0) {
                $best[$i] = $viaText;
            }
        }
        if ($bestScore > 0) {
            $pick = null;
            if (count($best) === 1) {
                $pick = array_key_first($best);
            } elseif ($precise) {
                // Plusieurs gares portent ce nom : la plus proche du point de la vue, si elle est nettement la plus proche.
                $pick = $this->clearlyNearest(array_keys($best), (float) $c['lat'], (float) $c['lng'], 400, 150);
            }
            if ($pick === null) {
                return ['kind' => null, 'reason' => 'noms_ambigus'];
            }
            $method = $best[$pick] ? 'nom' : 'nom-commune';
            $limit = $best[$pick] ? self::NAMED_MAX_M : self::COMMUNE_NAMED_MAX_M;
            $hit = $this->hit($pick, $method, $c, $best[$pick] ? 'haute' : 'moyenne');
            if ($precise && $hit['distance_m'] !== null && $hit['distance_m'] > $limit) {
                return ['kind' => null, 'reason' => 'gare_trop_loin'];
            }

            return $hit;
        }

        // 2. Gare non nommée : le point précis désigne une seule gare de la commune, ou la commune n'en a qu'une.
        $stops = array_values(array_filter($byCity, fn (int $i): bool => in_array($this->stations[$i]['type'], ['gare', 'halte'], true)));
        if ($precise) {
            $near = $this->clearlyNearest($stops, (float) $c['lat'], (float) $c['lng'], self::STATION_POINT_M, 150);
            if ($near !== null) {
                return $this->hit($near, 'point', $c, 'moyenne');
            }
        }
        if (count($stops) === 1) {
            $hit = $this->hit($stops[0], 'commune-unique', $c, 'moyenne');
            if ($precise && $hit['distance_m'] !== null && $hit['distance_m'] > self::COMMUNE_NAMED_MAX_M) {
                return ['kind' => null, 'reason' => 'gare_trop_loin'];
            }

            return $hit;
        }

        return ['kind' => null, 'reason' => 'gares_multiples'];
    }

    /**
     * Index de la gare la plus proche du point si elle est à `$within` mètres au plus et que la suivante est au moins
     * `$margin` mètres plus loin.
     *
     * @param  list<int>  $indexes
     */
    private function clearlyNearest(array $indexes, float $lat, float $lng, int $within, int $margin): ?int
    {
        $d = [];
        foreach ($indexes as $i) {
            $d[$i] = self::distance($lat, $lng, $this->stations[$i]['lat'], $this->stations[$i]['lng']);
        }
        asort($d);
        $keys = array_keys($d);
        if ($keys === [] || $d[$keys[0]] > $within) {
            return null;
        }

        return count($keys) === 1 || $margin <= $d[$keys[1]] - $d[$keys[0]] ? $keys[0] : null;
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array{kind: string, id: int, name: string, method: string, distance_m: ?int, confidence: string, city: string, status: string}
     */
    private function hit(int $index, string $method, array $c, string $confidence, ?int $distance = null): array
    {
        $s = $this->stations[$index];
        if ($distance === null && ! empty($c['point_precise'])) {
            $distance = (int) round(self::distance((float) $c['lat'], (float) $c['lng'], $s['lat'], $s['lng']));
        }

        return ['kind' => 'station', 'id' => $s['id'], 'name' => $s['name'], 'method' => $method, 'distance_m' => $distance, 'confidence' => $confidence, 'city' => $s['city'], 'status' => $s['status']];
    }

    /** @return list<int> index des gares à moins de $radius mètres */
    private function near(float $lat, float $lng, float $radius): array
    {
        $out = [];
        foreach ($this->stations as $i => $s) {
            if (abs($s['lat'] - $lat) <= $radius / 111000 && self::distance($lat, $lng, $s['lat'], $s['lng']) <= $radius) {
                $out[] = $i;
            }
        }

        return $out;
    }

    /**
     * Lignes dont un tracé passe à LINE_POINT_M au plus du point.
     *
     * @return list<array{id: int, name: string, distance_m: int}>
     */
    public function linesNear(float $lat, float $lng): array
    {
        $rows = DB::select(
            'SELECT l.id, l.name, MIN(ST_Distance(p.geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography)) AS d
             FROM line_reference_parts p JOIN lines l ON l.id = p.line_id
             WHERE p.geom && ST_Expand(ST_SetSRID(ST_MakePoint(?, ?), 4326), 0.0025)
               AND ST_DWithin(p.geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)
             GROUP BY l.id, l.name ORDER BY d, l.id LIMIT 6',
            [$lng, $lat, $lng, $lat, $lng, $lat, self::LINE_POINT_M],
        );

        return array_map(fn ($r): array => ['id' => (int) $r->id, 'name' => (string) $r->name, 'distance_m' => (int) round((float) $r->d)], $rows);
    }

    /** Distance en mètres (haversine). */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371008.8;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $a = sin(($p2 - $p1) / 2) ** 2 + cos($p1) * cos($p2) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }
}
