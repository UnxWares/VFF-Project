<?php

namespace App\Services\Materials;

use App\Services\WikipediaLineParser;
use Illuminate\Support\Facades\DB;

/**
 * Lignes parcourues par une série d'après les relations que cite l'article Wikipédia de la série, quand
 * l'article ne renvoie pas directement vers l'article d'une ligne de VFF :
 *
 *   1. itinéraire : lien vers un article qui décrit une relation enchaînant plusieurs lignes (« [[Ligne de
 *      Paris à Granville]] ») ; les lignes viennent de database/data/wikipedia_itineraries.php. Rien n'est
 *      rattaché quand la phrase nomme aussi un arrêt intermédiaire (« … avec la gare de Dreux ») : elle ne
 *      parle que d'une partie du trajet ;
 *   2. relation entre gares : « Paris - Dreux - Argentan - Granville », « [[Gare de Dreux|Dreux]], tiret demi-cadratin, [[Gare de
 *      Granville|Granville]] », « relations entre Caen et Rennes ». Chaque paire d'arrêts consécutifs est
 *      reliée par le plus court chemin du graphe de l'époque (RelationRouter), seulement s'il est sans
 *      ambiguïté ; sinon rien n'est rattaché pour cette paire, et elle est comptée.
 *
 *   3. relation écrite d'un seul mot à tiret collé après « relation », « ligne », « liaison »… (« sur la ligne
 *      Paris-Granville ») : lue comme « Paris - Granville » quand le nom entier n'est pas une gare et qu'une
 *      seule coupure au tiret donne deux gares connues (« Saint-Lô », « Paris-Est » restent entiers). Un nom
 *      tiré du libellé d'un lien vers un autre article (« [[Ligne de Saint-Denis à Dieppe|Paris-Dieppe]] »),
 *      un nom de ligne « de X à Y » (« la ligne de Nantes-Orléans à Saintes ») et une ligne citée pour ses
 *      travaux (« l'électrification de la ligne Paris-Le Mans ») ne sont pas relus ainsi.
 *
 * Libellé (`route.label`) : les arrêts tels que la phrase les nomme quand elle désigne une ville (« Paris »
 * plutôt que la gare choisie par le plus court chemin), sinon le nom de la gare en base rendu lisible
 * (RelationRouter::readable : « Saint-Malo », sans « (IE) » ni « n° 2 ») ; `from`, `to` et `stops` gardent
 * l'identifiant et le nom en base.
 *
 * Époque : la première année citée par la phrase si elle tombe dans la vie de la série, sinon le milieu de
 * sa période de service, sinon l'époque actuelle. Preuve de confiance moyenne (comme un lien d'article de
 * série), avec la phrase citée, l'itinéraire (`route`) et la période quand la phrase en donne une
 * (`period`, reprise par MaterialPeriodCollector). Les règles d'écartement et d'électrification
 * s'appliquent ensuite comme aux autres liens (LineMaterialLinker).
 *
 * Sections lues : celles des services (MATERIAL_SECTIONS, et RELATION_SECTIONS pour les articles qui n'en
 * ont pas). Une section de projets est écartée, sauf si son titre annonce aussi des dessertes réalisées
 * (« Dessertes réalisées ou prévues ») : ses phrases au futur ou au conditionnel restent écartées une à une.
 */
final class MaterialRelations
{
    /** Cible fictive qui marque la relation dans la phrase donnée à MaterialPeriodExtractor. */
    private const RELATION_TARGET = 'VFF relation';

    /** Nom de lieu en clair : mots à capitale, reliés par un tiret ou un article (« Saint-Lô », « Bourg-en-Bresse »). */
    private const NAME = '\p{Lu}[\p{L}’\'.]*(?:[- ](?:(?:sur|sous|en|lès|lez|les|le|la|de|du|des|aux|au|d’|d\'|l’|l\')[- ]?)?\p{Lu}[\p{L}’\'.]*)*';

    /** Gare liée (marque posée à la place du lien) ou nom en clair. */
    private const PLACE = '(?:\x{E010}\d+\x{E011}|'.self::NAME.')';

    /** Séparateur d'arrêts : tiret entouré d'espaces, tiret demi-cadratin ou cadratin ; entre deux liens, « - » collé ou « à ». */
    private const SEPARATOR = '(?:\s*[–—]\s*|\s+-\s+|(?<=\x{E011})\s*-\s*(?=\x{E010})|(?<=\x{E011})\s+à\s+(?=\x{E010}))';

    /** Mot qui annonce une relation écrite d'un seul tenant (« la ligne Paris-Granville »). */
    private const RELATION_WORD = '(?i:relations?|liaisons?|lignes?|trains?|dessertes?|services?|trajets?|axes?|rapides?|express|omnibus)';

    /** Type de service cité par la phrase ou le titre de section, dans sa graphie courante. */
    private const SERVICES = [
        'TER' => '/\bTER\b/u', 'Intercités' => '/\bIntercit[ée]s?\b|\bTéoz\b/iu', 'TGV' => '/\bTGV\b/u',
        'grandes lignes' => '/\bgrandes?\s+lignes?\b/iu', 'express' => '/\bexpress\b|\brapides?\b|\bdirects?\b/iu',
        'omnibus' => '/\bomnibus\b/iu', 'fret' => '/\bfret\b|\bmarchandises\b|\bmessageries?\b/iu',
    ];

    /** @var array<string, int> */
    private array $stats = ['itineraries' => 0, 'itineraries_partial' => 0, 'pairs' => 0, 'pairs_ambiguous' => 0, 'pairs_without_path' => 0, 'stops_unresolved' => 0];

    /**
     * @param  array<string, array{lines: list<int>, stops: list<string>, source: string}>  $itineraries  titre normalisé → lignes de VFF
     */
    public function __construct(
        private readonly RelationRouter $router,
        private readonly MaterialPeriodExtractor $extractor,
        private readonly array $itineraries,
    ) {}

    /**
     * Itinéraires du fichier de données, codes de ligne remplacés par les identifiants (un code absent de la
     * base est ignoré).
     *
     * @param  array<string, array{stops: list<string>, lines: list<string>, source: string}>|null  $data
     */
    public static function fromDatabase(?array $data = null): self
    {
        $data ??= require database_path('data/wikipedia_itineraries.php');
        $codes = [];
        foreach ($data as $itinerary) {
            array_push($codes, ...$itinerary['lines']);
        }
        $ids = [];
        foreach (DB::table('lines')->whereIn('code', array_unique($codes))->get(['id', 'code']) as $row) {
            $ids[(string) $row->code][] = (int) $row->id;
        }
        $itineraries = [];
        foreach ($data as $title => $itinerary) {
            $lines = [];
            foreach ($itinerary['lines'] as $code) {
                array_push($lines, ...$ids[$code] ?? []);
            }
            $itineraries[WikipediaMaterialSections::normalizeTitle($title)] = ['lines' => $lines, 'stops' => $itinerary['stops'], 'source' => $itinerary['source']];
        }

        return new self(new RelationRouter, new MaterialPeriodExtractor(new WikipediaLineParser), $itineraries);
    }

    /**
     * Nombre d'itinéraires et de paires d'arrêts lus, et refus (itinéraire partiel, paire ambiguë ou sans
     * chemin, arrêt introuvable), depuis la création.
     *
     * @return array<string, int>
     */
    public function stats(): array
    {
        return $this->stats;
    }

    /**
     * Lignes attestées par les relations d'un article de série, avec leur preuve.
     *
     * @param  array{title: string, revid: int, sections: list<array{title: string, level: int, text: string}>}  $page
     * @param  array{fast: bool, from: ?int, to: ?int, electric?: bool}  $material  grande vitesse, années de service, traction électrique seule
     * @return list<array{line_id: int, evidence: array<string, mixed>}>
     */
    public function links(array $page, array $material, ?\DateTimeInterface $today = null): array
    {
        $out = [];
        foreach ($page['sections'] as $section) {
            if (WikipediaMaterialSections::isDoubtfulSection($section['title']) && preg_match('/\bréalis/iu', $section['title']) !== 1) {
                continue;
            }
            foreach (WikipediaMaterialSections::units(WikipediaMaterialSections::stripNoise($section['text'])) as $unit) {
                $sentence = ltrim($unit['sentence']);
                if (preg_match('/^(?:[|!]|\{\||\[\[\s*(?:fichier|file|image)\s*:)/iu', $sentence) === 1) {
                    continue;
                }
                array_push($out, ...$this->itineraryLinks($page, $section['title'], $sentence, $today));
                array_push($out, ...$this->relationLinks($page, $section['title'], $sentence, $material, $today));
            }
        }

        return $out;
    }

    /**
     * @param  array{title: string, revid: int}  $page
     * @return list<array{line_id: int, evidence: array<string, mixed>}>
     */
    private function itineraryLinks(array $page, string $section, string $sentence, ?\DateTimeInterface $today): array
    {
        $out = [];
        foreach (WikipediaMaterialSections::links($sentence) as $link) {
            $itinerary = $this->itineraries[$link['target']] ?? null;
            if ($itinerary === null) {
                continue;
            }
            $this->stats['itineraries']++;
            $text = RelationRouter::key($link['quote']);
            foreach (array_slice($itinerary['stops'], 1, -1) as $stop) {
                if (preg_match('/(?<![a-z])'.preg_quote(RelationRouter::key($stop), '/').'(?![a-z])/u', $text) === 1) {
                    $this->stats['itineraries_partial']++;

                    continue 2;
                }
            }
            $target = $link['target'];
            $period = $this->period($sentence, fn (string $t): bool => $t === $target, $today);
            [$first, $last] = [$itinerary['stops'][0], $itinerary['stops'][count($itinerary['stops']) - 1]];
            $ends = [$this->endpoint($first), $this->endpoint($last)];
            $route = [
                'kind' => 'itinerary', 'title' => $target, 'source' => $itinerary['source'],
                'label' => RelationRouter::readable($first).' - '.RelationRouter::readable($last), 'from' => $ends[0], 'to' => $ends[1],
                'service' => self::service($link['quote'], $section),
            ];
            $evidence = $this->evidence($page, $section, $link['quote'], $link['doubt'], array_filter($route, fn ($v): bool => $v !== null), $period);
            foreach ($itinerary['lines'] as $lineId) {
                $out[] = ['line_id' => $lineId, 'evidence' => $evidence];
            }
        }

        return $out;
    }

    /**
     * @param  array{title: string, revid: int}  $page
     * @param  array{fast: bool, from: ?int, to: ?int, electric?: bool}  $material
     * @return list<array{line_id: int, evidence: array<string, mixed>}>
     */
    private function relationLinks(array $page, string $section, string $sentence, array $material, ?\DateTimeInterface $today): array
    {
        // Gares liées remplacées par une marque (le libellé peut contenir un tiret : « Trouville - Deauville ») ;
        // les autres liens deviennent leur texte.
        $places = [];
        $marked = (string) preg_replace_callback('/\[\[([^\[\]|#]+)(?:#[^\[\]|]*)?(?:\|([^\[\]]*))?\]\]/u', function (array $m) use (&$places): string {
            $target = WikipediaMaterialSections::normalizeTitle($m[1]);
            $label = ($m[2] ?? '') !== '' ? $m[2] : $m[1];
            $name = preg_match('/^gare\s+(?:de\s+la\s+|de\s+l[’\']|de\s+|d[’\']|du\s+|des\s+)(.+)$/iu', $target, $g) === 1 ? $g[1] : null;
            if ($name === null && ! str_contains($target, ':') && $this->router->isStationName($target)) {
                $name = $target;
            }
            if ($name === null) {
                return $m[0];
            }
            $places[] = ['name' => $name, 'label' => $label];

            return "\u{E010}".(count($places) - 1)."\u{E011}";
        }, $sentence);
        $text = WikipediaMaterialSections::plain($marked);
        $readable = fn (string $s): string => (string) preg_replace_callback('/\x{E010}(\d+)\x{E011}/u', fn (array $m): string => $places[(int) $m[1]]['label'] ?? '', $s);
        $quote = $readable($text);
        if (WikipediaMaterialSections::isProspective($quote)) {
            return [];
        }

        $chains = [];
        if (preg_match_all('/(?<![\p{L}\x{E011}])'.self::PLACE.'(?:'.self::SEPARATOR.self::PLACE.')+/u', $text, $found, PREG_OFFSET_CAPTURE)) {
            foreach ($found[0] as [$chain, $offset]) {
                $chains[] = [$chain, $offset, preg_split('/'.self::SEPARATOR.'/u', $chain) ?: []];
            }
        }
        $between = '/\b(?i:relations?|liaisons?|lignes?|trains?|dessertes?|services?|trajets?)\b[^.;:]{0,40}?\bentre\s+('.self::PLACE.')\s+et\s+('.self::PLACE.')/u';
        if (preg_match_all($between, $text, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($found as $m) {
                $chains[] = [substr($text, $m[1][1], $m[2][1] + strlen($m[2][0]) - $m[1][1]), $m[1][1], [$m[1][0], $m[2][0]]];
            }
        }

        $out = [];
        $year = $this->year($quote, $material);
        // Année antérieure à la première ère : graphe de l'époque actuelle.
        $eraYear = $this->router->eraYear($year) ?? $this->router->eraYear((int) date('Y'));
        if ($eraYear === null) {
            return [];
        }
        array_push($chains, ...$this->joinedChains($text, $sentence, $chains, $eraYear));
        foreach ($chains as [$chain, $offset, $pieces]) {
            $stops = [];
            $written = [];
            foreach ($pieces as $piece) {
                [$ids, $name] = $this->stop($piece, $places, $eraYear);
                if ($ids === []) {
                    $this->stats['stops_unresolved']++;

                    continue 2;
                }
                $stops[] = $ids;
                $written[] = $name;
            }
            $lines = [];
            $chosen = [];
            for ($k = 0; $k + 1 < count($stops); $k++) {
                $this->stats['pairs']++;
                $route = $this->router->route($stops[$k], $stops[$k + 1], $eraYear, $material['fast'], $material['electric'] ?? false);
                if ($route['status'] === 'ambiguous') {
                    $this->stats['pairs_ambiguous']++;
                } elseif ($route['status'] === 'no_path') {
                    $this->stats['pairs_without_path']++;
                }
                if ($route['status'] === 'ok') {
                    array_push($lines, ...$route['lines']);
                    $chosen[$k] = $route['from'];
                    $chosen[$k + 1] = $route['to'];
                }
            }
            if ($lines === []) {
                continue;
            }
            // Arrêts : la gare retenue par le plus court chemin (celle d'une ville à plusieurs gares, « Paris ») ;
            // libellé : le nom écrit quand il désigne une ville ou un début de nom, sinon le nom en base lisible.
            $named = [];
            $shown = [];
            foreach ($stops as $k => $ids) {
                $id = $chosen[$k] ?? (count($ids) === 1 ? $ids[0] : null);
                $named[] = $id !== null ? ['id' => $id, 'name' => $this->router->stationName($id)] : ['id' => null, 'name' => $readable(trim($pieces[$k]))];
                $name = $id !== null ? $this->router->stationName($id) : $named[$k]['name'];
                $shown[] = RelationRouter::readable(RelationRouter::key($name) === RelationRouter::key($written[$k]) ? $name : $written[$k]);
            }
            $before = $readable(substr($text, 0, $offset));
            $label = $readable($chain);
            $after = $readable(substr($text, $offset + strlen($chain)));
            // Doute lu dans la partie de phrase de la relation, sinon n'importe où dans la phrase : dans une
            // liste, « Dans des cas exceptionnels, … sur la desserte Paris-Est - Meaux » porte sur la relation.
            $doubt = WikipediaMaterialSections::doubt($before, $label, $after) ?? self::doubtAnywhere($quote);
            $period = $this->period($before.'[['.self::RELATION_TARGET.'|'.$label.']]'.$after, fn (string $t): bool => $t === self::RELATION_TARGET, $today);
            $evidence = $this->evidence($page, $section, mb_strimwidth($quote, 0, WikipediaMaterialSections::QUOTE_WIDTH, '…'), $doubt, array_filter([
                'kind' => 'relation', 'label' => implode(' - ', $shown), 'from' => $named[0], 'to' => $named[count($named) - 1],
                'stops' => $named, 'service' => self::service($quote, $section), 'era_year' => $eraYear,
            ], fn ($v): bool => $v !== null), $period);
            foreach (array_unique($lines) as $lineId) {
                $out[] = ['line_id' => $lineId, 'evidence' => $evidence];
            }
        }

        return $out;
    }

    /** Première famille de doute (WikipediaMaterialSections::UNCERTAIN) citée dans la phrase, ou null. */
    private static function doubtAnywhere(string $quote): ?string
    {
        foreach (WikipediaMaterialSections::UNCERTAIN as $family => $words) {
            if (preg_match('/\b(?:'.$words.')\b/iu', $quote) === 1) {
                return $family;
            }
        }

        return null;
    }

    /**
     * Gare d'extrémité d'un itinéraire, identifiée sur le graphe de l'époque actuelle si son nom est connu.
     *
     * @return array{id: ?int, name: string}
     */
    private function endpoint(string $name): array
    {
        $era = $this->router->eraYear((int) date('Y'));
        $ids = $era === null ? [] : $this->router->resolve($name, $era);

        return count($ids) === 1 ? ['id' => $ids[0], 'name' => $this->router->stationName($ids[0])] : ['id' => null, 'name' => $name];
    }

    /** Type de service cité par la phrase, sinon par le titre de section (« TER Normandie », « Intercités »), ou null. */
    public static function service(string $quote, string $section): ?string
    {
        foreach ([$quote, $section] as $text) {
            foreach (self::SERVICES as $service => $pattern) {
                if (preg_match($pattern, $text) === 1) {
                    return $service;
                }
            }
        }

        return null;
    }

    /**
     * Gares d'un arrêt de la relation : gare liée, sinon nom en clair, sans ses premiers mots au besoin
     * (« Relation Argentan » → « Argentan »), avec le nom qui les a désignées.
     *
     * @param  list<array{name: string, label: string}>  $places
     * @return array{0: list<int>, 1: string}
     */
    private function stop(string $piece, array $places, int $eraYear): array
    {
        $piece = trim($piece);
        if (preg_match('/^\x{E010}(\d+)\x{E011}$/u', $piece, $m) === 1) {
            $name = $places[(int) $m[1]]['name'];

            return [$this->router->resolve($name, $eraYear), $name];
        }
        $words = preg_split('/\s+/u', $piece) ?: [];
        for ($i = 0; $i < count($words); $i++) {
            $name = implode(' ', array_slice($words, $i));
            $ids = $this->router->resolve($name, $eraYear);
            if ($ids !== []) {
                return [$ids, $name];
            }
        }

        return [[], $piece];
    }

    /**
     * Relations écrites d'un seul tenant après un mot comme « ligne » ou « relation » (« la ligne
     * Paris-Granville ») : coupées au seul tiret qui sépare deux gares connues, quand le nom entier n'en est
     * pas une. Les chaînes déjà lues et les libellés de liens vers d'autres articles sont laissés de côté.
     *
     * @param  list<array{0: string, 1: int, 2: list<string>}>  $chains  chaînes déjà trouvées
     * @return list<array{0: string, 1: int, 2: list<string>}>
     */
    private function joinedChains(string $text, string $sentence, array $chains, int $eraYear): array
    {
        if (preg_match_all('/\b'.self::RELATION_WORD.'\s+(?:(?:de|du|des|entre)\s+|d[’\']\s*)?('.self::NAME.')/u', $text, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) < 1) {
            return [];
        }
        $out = [];
        foreach ($found as $m) {
            [$name, $offset] = $m[1];
            $name = rtrim($name, '.');
            // Nom de ligne « de X à Y » (« la ligne de Nantes-Orléans à Saintes »), libellé d'un lien, ou ligne
            // citée pour ses travaux (« l'électrification de la ligne Paris-Le Mans ») : pas une relation.
            $before = substr($text, max(0, $m[0][1] - 60), min(60, $m[0][1]));
            if (! str_contains($name, '-') || preg_match('/^\s+(?:à|au|aux)\s/u', substr($text, $offset + strlen($name))) === 1
                || preg_match('/\[\[[^\]]*\|\s*'.preg_quote($name, '/').'\s*\]\]/u', $sentence) === 1
                || preg_match('/(?:électrifi\w*|mise\s+sous\s+tension|travaux|construction|ouverture|fermeture|doublement)\b[^.;]*$/iu', $before) === 1) {
                continue;
            }
            foreach ($chains as [$chain, $start]) {
                if ($offset >= $start && $offset < $start + strlen($chain)) {
                    continue 2;
                }
            }
            if ($this->router->resolve($name, $eraYear) !== []) {
                continue;
            }
            $parts = explode('-', $name);
            $splits = [];
            for ($k = 1; $k < count($parts); $k++) {
                [$a, $b] = [implode('-', array_slice($parts, 0, $k)), implode('-', array_slice($parts, $k))];
                if ($this->router->resolve($a, $eraYear) !== [] && $this->router->resolve($b, $eraYear) !== []) {
                    $splits[] = [$a, $b];
                }
            }
            if (count($splits) === 1) {
                $out[] = [$name, $offset, $splits[0]];
            }
        }

        return $out;
    }

    /**
     * Année de l'époque à lire : première année de la phrase si elle tombe dans la vie de la série, sinon le
     * milieu de la période de service, sinon l'année en cours.
     *
     * @param  array{fast: bool, from: ?int, to: ?int}  $material
     */
    private function year(string $quote, array $material): int
    {
        $now = (int) date('Y');
        $from = $material['from'];
        $to = min($material['to'] ?? $now, $now);
        if (preg_match_all('/(?<![\d\p{L}])(18[3-9]\d|19\d\d|20[0-4]\d)(?!\d)/u', $quote, $m)) {
            foreach ($m[1] as $year) {
                if (($from === null || (int) $year >= $from) && (int) $year <= $to) {
                    return (int) $year;
                }
            }
        }

        return $from === null ? $now : intdiv($from + $to, 2);
    }

    /**
     * Période donnée par la phrase pour la relation (ou l'itinéraire), ou null.
     *
     * @param  callable(string): bool  $isTarget
     * @return array{from: ?array{date: string, precision: string}, to: ?array{date: string, precision: string}, confidence: string, excerpt: string}|null
     */
    private function period(string $wikitext, callable $isTarget, ?\DateTimeInterface $today): ?array
    {
        $facts = $this->extractor->extract($wikitext, fn (string $t): array => $isTarget($t) ? [0] : [], fn (): bool => false, MaterialPeriodExtractor::MODE_MATERIAL_ARTICLE, $today);
        foreach ($facts as $fact) {
            if ($fact['from'] !== null || $fact['to'] !== null) {
                return ['from' => $fact['from'], 'to' => $fact['to'], 'confidence' => $fact['confidence'], 'excerpt' => $fact['excerpt']];
            }
        }

        return null;
    }

    /**
     * @param  array{title: string, revid: int}  $page
     * @param  array<string, mixed>  $route
     * @param  array<string, mixed>|null  $period
     * @return array<string, mixed>
     */
    private function evidence(array $page, string $section, string $quote, ?string $doubt, array $route, ?array $period): array
    {
        return array_filter([
            'kind' => LineMaterialLinker::KIND_MATERIAL_ARTICLE,
            'article' => $page['title'],
            'revid' => $page['revid'],
            'section' => $section,
            'quote' => $quote,
            'url' => 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $page['title'])).'&oldid='.$page['revid'],
            'route' => $route,
            'period' => $period,
            'doubt' => $doubt ?? WikipediaMaterialSections::uncertainSection($section),
        ], fn ($v): bool => $v !== null);
    }
}
