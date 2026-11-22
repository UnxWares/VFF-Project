<?php

namespace App\Services\Accidents;

use App\Enums\AccidentDatePrecision;
use App\Enums\AccidentKind;
use App\Services\PlaceNames;
use App\Support\FrenchDepartments;

/**
 * Faits des pages « Liste d'accidents ferroviaires en France » de Wikipédia en français : une
 * puce par accident, qui commence par sa date. Seuls des FAITS sont gardés (date, communes et
 * gares citées, lignes citées, nombre de morts et de blessés, nature) ; aucun texte de la liste
 * n'est repris ni conservé (CC BY-SA 4.0), le résumé de VFF est rédigé à partir des faits.
 *
 * Le texte d'une puce est libre : tout ce qui n'est pas lu avec certitude reste inconnu. Un bilan
 * approximatif (« environ », « plus de », « une dizaine »), en fourchette (« de neuf à dix-huit
 * morts ») ou donné deux fois avec des valeurs différentes n'est jamais retenu.
 */
final class WikipediaListReader
{
    private const MONTHS = [
        'janvier' => 1, 'février' => 2, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6,
        'juillet' => 7, 'août' => 8, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11,
        'décembre' => 12, 'decembre' => 12,
    ];

    private const UNITS = [
        'un' => 1, 'une' => 1, 'deux' => 2, 'trois' => 3, 'quatre' => 4, 'cinq' => 5, 'six' => 6, 'sept' => 7,
        'huit' => 8, 'neuf' => 9, 'dix' => 10, 'onze' => 11, 'douze' => 12, 'treize' => 13, 'quatorze' => 14,
        'quinze' => 15, 'seize' => 16, 'vingt' => 20, 'vingts' => 20, 'trente' => 30, 'quarante' => 40,
        'cinquante' => 50, 'soixante' => 60,
    ];

    /** Mots qui rendent un nombre approximatif ou qui en font une fourchette. */
    private const APPROX = '/(?:environ|près de|pres de|plus de|moins de|au moins|une? (?:dizaine|vingtaine|trentaine|quarantaine|cinquantaine|centaine)|quelque|jusqu\'à|jusqu’à|entre|ou|à)\s*$/iu';

    /** Articles dont le titre ne désigne pas un lieu. */
    private const NOT_PLACE = '/^(?:Ligne |LGV |Compagnie |Société |Chemin de fer|Réseau |Chemins de fer|Gare |Station |Halte |Tramway|Métro|Train |Trains |TGV|TER|RER|Intercités|Transilien|Corail|Autorail|Locomotive|Automotrice|Rame |Wagon|Voiture|Camion|Poids|Passage à niveau|Tunnel|Viaduc|Pont |Catégorie:|Fichier:|Image:|[A-Z]{1,3} ?\d|[A-Z]{1,4}$|\d)/u';

    /**
     * Un train qui heurte une personne (texte normalisé : minuscules, sans accents) : la personne est l'objet
     * direct d'un verbe conjugué. Un participe présent (« la locomotive écrasant les voyageurs ») décrit les
     * suites d'une autre collision, pas un heurt de personne.
     */
    private const PERSON_STRUCK = '/\b(?:percut|heurt|fauch|happ|ecras|renvers|tamponn)(?!\w*ant\b)\w*\s+(?:(?:mortellement|fatalement|grievement|violemment)\s+)?(?:un |une |le |la |les |des |deux |trois |quatre |cinq |six |\d+ )?(?:jeunes? |vieux |vieille |autres? )?(?:pietons?|personnes?|hommes?|femmes?|dames?|enfants?|cyclistes?|ouvriers?|cheminots?|agents?|salaries?|promeneurs?|randonneurs?|passants?|adolescents?|voyageurs?|gamins?|fillettes?|garcons?|riverains?|techniciens?)\b/';

    /**
     * Un train ou un véhicule heurté, ou deux trains qui se rencontrent : collision, jamais un heurt de personne. Aussi
     * un train « tamponné », ou un train sujet d'un verbe de choc au passif (« un train de voyageurs [...] est percuté
     * par celui qui le suivait ») : les voyageurs cités ensuite sont ses victimes (Jussey, 28/08/1881).
     */
    private const VEHICLE_STRUCK = '/\b(?:(?:percut|heurt|tamponn|prend|prennent)\w*\s+(?:(?:de plein fouet|en echarpe|par l arriere)\s+)?(?:un |une |le |la |les |l |des )?(?:autre |second |deuxieme )?(?:train|express|rapide|convoi|omnibus|locomotive|machine|rame|autorail|wagon|tgv|ter|marchandises|camion|autocar|car|bus|tracteur|voiture)\w*|(?:train|express|rapide|convoi|omnibus|rame)s? tamponn\w*|(?:train|express|rapide|convoi|omnibus|rame)s?(?: \w+){0,15} (?:est|sont|fut|furent) (?:percut|heurt|tamponn|pris en echarpe)\w* par|se telescop\w*|en echarpe|collision)\b/';

    /** @var array<string, string> nom normalisé d'un département → code */
    private static array $departments = [];

    private readonly AccidentPlaceTable $placeTable;

    public function __construct(private readonly AccidentKindClassifier $classifier, ?AccidentPlaceTable $placeTable = null)
    {
        $this->placeTable = $placeTable ?? new AccidentPlaceTable;
    }

    /**
     * @return list<array{
     *   date: string, precision: AccidentDatePrecision, anchor: ?string,
     *   places: list<array{0: string, 1: ?string}>, station: ?string, place_basis: ?string, line_articles: list<string>,
     *   deaths: ?int, injuries: ?int, kind: ?AccidentKind, urban: bool
     * }>
     */
    public function read(string $wikitext): array
    {
        $entries = [];
        $anchor = null;
        foreach (preg_split('/\R/u', $wikitext) ?: [] as $line) {
            if (preg_match('/^==+\s*(.+?)\s*==+\s*$/u', $line, $h)) {
                $anchor = str_replace(' ', '_', trim(strip_tags(preg_replace('/\[\[(?:[^\]|]*\|)?([^\]]*)\]\]/u', '$1', $h[1]) ?? $h[1])));

                continue;
            }
            if (! str_starts_with($line, '*') || str_starts_with($line, '**')) {
                continue;
            }
            $parsed = $this->date(ltrim(substr($line, 1)));
            if ($parsed === null) {
                continue;
            }
            [$date, $rest] = $parsed;
            $entries[] = ['anchor' => $anchor] + $this->facts($date, $rest);
        }

        return $entries;
    }

    /**
     * Date en tête de puce : modèle {{date|8|mai|1842}}, {{Date-|14|11|2015}}, {{date|8 mai 1842}},
     * [[1941|28 décembre 1941]] ou date en clair, éventuellement précédée de « Le » ou « Nuit du 8 au ».
     *
     * @return array{0: string, 1: string}|null date AAAA-MM-JJ et suite de la ligne
     */
    public function date(string $line): ?array
    {
        $month = '(?:'.implode('|', array_keys(self::MONTHS)).')';
        $head = '(?:[Ll]e\s+)?(?:[Nn]uit du \d{1,2}(?:er)? au\s+)?';
        $day = '(\d{1,2}|1er)';
        $patterns = [
            // {{date|…}} avec ses arguments (le jour peut précéder le modèle : « 24 {{date||janvier|1977}} »).
            '/^'.$head.'(?:'.$day.'\s+)?\{\{[Dd]ate-?\|([^{}]*)\}\}/u' => 'template',
            '/^'.$head.'\[\[\d{4}\|'.$day.'\s+('.$month.')\s+(\d{4})\]\]/iu' => 'link',
            '/^'.$head.$day.'\s+('.$month.')\s+(\d{4})(?!\d)/iu' => 'plain',
        ];
        foreach ($patterns as $pattern => $kind) {
            if (! preg_match($pattern, $line, $m)) {
                continue;
            }
            $found = match ($kind) {
                'template' => $this->templateDate($m[1] !== '' ? $m[1] : null, $m[2]),
                default => $this->fromParts($m[1], $m[2], $m[3]),
            };

            return $found === null ? null : [$found, substr($line, strlen($m[0]))];
        }

        return null;
    }

    private function templateDate(?string $outerDay, string $args): ?string
    {
        $parts = array_map('trim', explode('|', $args));
        if (count($parts) === 1 && preg_match('/^(\d{1,2}|1er)\s+(\p{L}+)\s+(\d{4})$/u', $parts[0], $m)) {
            return $this->fromParts($m[1], $m[2], $m[3]);
        }
        if (count($parts) === 3) {
            [$d, $mo, $y] = $parts;

            return $this->fromParts($d !== '' ? $d : (string) $outerDay, $mo, $y);
        }

        return null;
    }

    private function fromParts(string $day, string $month, string $year): ?string
    {
        $d = (int) $day;
        $m = ctype_digit($month) ? (int) $month : (self::MONTHS[mb_strtolower($month)] ?? 0);
        $y = (int) $year;
        if ($d < 1 || $m < 1 || $y < 1800 || $y > 2100 || ! checkdate($m, $d, $y)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }

    /**
     * Faits d'une puce, d'après la suite de la ligne.
     *
     * @return array{date: string, precision: AccidentDatePrecision, places: list<array{0: string, 1: ?string}>, station: ?string, place_basis: ?string, line_articles: list<string>, deaths: ?int, injuries: ?int, kind: ?AccidentKind, urban: bool}
     */
    private function facts(string $date, string $rest): array
    {
        $text = $this->clean($rest);
        $text = ltrim($text, " \t:-–—");
        [$plain, $links] = $this->links($text);

        $lines = [];
        $station = null;
        $places = [];
        foreach ($links as $link) {
            $target = $link['target'];
            if (preg_match('/^(?:Ligne|LGV) /iu', $target)) {
                $lines[] = mb_strtoupper(mb_substr($target, 0, 1)).mb_substr(preg_replace('/\s*#.*$/u', '', $target) ?? $target, 1);
            } elseif (preg_match("/^Gare (?:de |d['’]|du |des |de la )(.+)$/iu", $target, $m)) {
                $name = trim(preg_replace('/\s*\(.*\)$/u', '', $m[1]) ?? $m[1]);
                $station ??= $name;
                $places[] = ['name' => $name, 'department' => null, 'rank' => $this->rank($link, $plain), 'at' => $link['at']];
            } elseif (mb_strtolower(mb_substr($target, 0, 1)) !== mb_substr($target, 0, 1)
                && ! preg_match(self::NOT_PLACE, $target) && $this->department($target) === null) {
                // Un lieu est un nom propre : un lien en minuscules (« serre-frein ») n'en est pas un.
                $dept = null;
                $name = $target;
                if (preg_match('/^(.+?)\s*\(([^)]+)\)$/u', $target, $m)) {
                    $name = $m[1];
                    $dept = $this->department($m[2]);
                }
                $dept ??= $link['parenthesis'] !== null ? $this->department($link['parenthesis']) : null;
                $places[] = ['name' => trim($name), 'department' => $dept, 'rank' => $this->rank($link, $plain), 'at' => $link['at']];
            }
        }
        // Lieu cité sans lien, en tête de puce : « Courtonne-La-Meurdrac (Calvados) : collision… ».
        if ($places === [] || ! array_any($places, fn ($p) => $p['at'] <= 2)) {
            $lead = $this->leadingPlace($plain);
            if ($lead !== null) {
                array_unshift($places, $lead);
            }
        }
        usort($places, fn ($a, $b) => [$a['rank'], $a['at']] <=> [$b['rank'], $b['at']]);
        // Table de correspondance de VFF (database/data/accident_places.php) : le lieu que le texte nomme sans
        // lien lisible. Elle prime sur la lecture automatique, qui s'y trompait (un nom de ligne, une ville du trajet).
        $mapped = $this->placeTable->find($date, $plain);
        if ($mapped !== null) {
            $places = [['name' => $mapped['name'], 'department' => $mapped['department'], 'rank' => 0, 'at' => 0]];
            $station = null;
        }

        $deaths = $this->count($plain, 'morts?|tués?|décès|victimes? mortelles?|personnes? (?:tuées?|décédées?)');
        $injuries = $this->count($plain, 'blessés?|personnes? blessées?');
        $normalized = PlaceNames::normalize($plain);

        return [
            'date' => $date,
            'precision' => AccidentDatePrecision::Day,
            'places' => array_map(fn ($p) => [$p['name'], $p['department']], $this->unique($places)),
            'station' => $station,
            'place_basis' => $mapped['basis'] ?? null,
            'line_articles' => array_values(array_unique($lines)),
            'deaths' => $deaths,
            'injuries' => $injuries,
            'kind' => $this->kind($plain, $normalized),
            'urban' => (bool) preg_match('/\b(?:metro|tramways?)\b/', $normalized) && ! str_contains($normalized, 'tram train'),
        ];
    }

    /**
     * Nature d'après le texte. Un passage à niveau cité fait un accident de passage à niveau, même
     * quand le texte décrit d'abord la collision ou le déraillement qui en résulte. Un train qui
     * heurte une personne (piéton, agent, ouvrier) hors passage à niveau est un heurt de personne.
     */
    private function kind(string $plain, string $normalized): ?AccidentKind
    {
        $kind = $this->classifier->fromFrenchText($plain) ?? $this->classifier->fromNarrative($plain);
        $crossing = (bool) preg_match('/\b(?:passages? a niveau|pn)\b/', $normalized);
        if (! $crossing && ! preg_match('/\b(?:deraill\w*|collision entre)\b/', $normalized)
            && ! preg_match(self::VEHICLE_STRUCK, $normalized) && preg_match(self::PERSON_STRUCK, $normalized)) {
            return AccidentKind::PersonStruck;
        }
        if ($crossing && in_array($kind, [null, AccidentKind::Collision, AccidentKind::Derailment], true)) {
            return AccidentKind::LevelCrossing;
        }

        return $kind;
    }

    /** Retire notes, modèles et commentaires, garde les liens. */
    private function clean(string $text): string
    {
        $text = preg_replace('/<ref[^>]*\/>|<ref[^>]*>.*?<\/ref>|<!--.*?-->/su', '', $text) ?? $text;
        for ($i = 0; $i < 4; $i++) {
            $text = preg_replace('/\{\{[^{}]*\}\}/u', '', $text, -1, $n) ?? $text;
            if ($n === 0) {
                break;
            }
        }

        return str_replace("''", '', $text);
    }

    /**
     * Texte sans liens (le libellé reste) et liste des liens : cible, position, parenthèse qui suit.
     *
     * @return array{0: string, 1: list<array{target: string, at: int, parenthesis: ?string, before: string}>}
     */
    private function links(string $text): array
    {
        $links = [];
        $plain = '';
        $pos = 0;
        while (preg_match('/\[\[([^\]|]+)(?:\|([^\]]*))?\]\]/u', $text, $m, PREG_OFFSET_CAPTURE, $pos)) {
            $plain .= substr($text, $pos, $m[0][1] - $pos);
            $at = mb_strlen($plain);
            $label = $m[2][0] ?? $m[1][0];
            $after = substr($text, $m[0][1] + strlen($m[0][0]), 80);
            $links[] = [
                'target' => trim(str_replace('_', ' ', $m[1][0])),
                'at' => $at,
                'parenthesis' => preg_match('/^\s*\(\s*(?:\[\[(?:[^\]|]*\|)?([^\]]*)\]\]|([^()\[\]]+))\s*\)/u', $after, $p) ? trim($p[1] !== '' ? $p[1] : $p[2]) : null,
                'before' => mb_substr($plain, max(0, $at - 24)),
            ];
            $plain .= $label;
            $pos = $m[0][1] + strlen($m[0][0]);
        }

        return [$plain.substr($text, $pos), $links];
    }

    /** Rang d'un lieu cité : 0 en tête ou après « à », 2 après « près de », « entre », « vers ». */
    private function rank(array $link, string $plain): int
    {
        if ($link['at'] <= 2) {
            return 0;
        }
        $before = $link['before'];
        if (preg_match('/(?:près d[eu’\']|pres d[eu’\']|entre|vers|proche d[eu’\']|après|avant|depuis|direction)\s*(?:la |le |les |l[’\'])?$/iu', $before)) {
            return 3;
        }
        if (preg_match('/(?:\bà|\bau|\ben|commune d[eu’\']|gare d[eu’\']|gare de la)\s*$/iu', $before)) {
            return 1;
        }

        return 2;
    }

    /**
     * @return array{name: string, department: ?string, rank: int, at: int}|null
     */
    private function leadingPlace(string $plain): ?array
    {
        $head = trim(strstr($plain, ':', true) ?: '');
        if ($head === '' || mb_strlen($head) > 60 || ! preg_match('/^\p{Lu}[\p{L}\' ’.-]*(?:\s*\(([^)]+)\))?$/u', $head, $m)) {
            return null;
        }
        $name = trim(preg_replace('/\s*\(.*\)$/u', '', $head) ?? $head);
        $dept = isset($m[1]) ? $this->department($m[1]) : null;
        if ($name === '' || ($dept === null && isset($m[1]))) {
            return null;
        }

        return ['name' => $name, 'department' => $dept, 'rank' => 0, 'at' => 0];
    }

    /**
     * @param  list<array{name: string, department: ?string, rank: int, at: int}>  $places
     * @return list<array{name: string, department: ?string, rank: int, at: int}>
     */
    private function unique(array $places): array
    {
        $seen = [];
        $out = [];
        foreach ($places as $p) {
            $key = PlaceNames::normalize($p['name']).'|'.($p['department'] ?? '');
            if (! isset($seen[$key]) && $p['name'] !== '') {
                $seen[$key] = true;
                $out[] = $p;
            }
        }

        return array_slice($out, 0, 4);
    }

    /** Code du département dont le nom est $name (« Oise », « Oise (département) »), sinon null. */
    public function department(string $name): ?string
    {
        if (self::$departments === []) {
            foreach (FrenchDepartments::ALL as $code => [$label]) {
                self::$departments[PlaceNames::normalize($label)] = (string) $code;
            }
        }
        $name = preg_replace('/\s*\((?:département|departement)\)\s*$/iu', '', $name) ?? $name;

        return self::$departments[PlaceNames::normalize($name)] ?? null;
    }

    /**
     * Nombre qui précède un des noms (« trois morts », « 17 tués »). Null si aucun, si le nombre est
     * approximatif ou en fourchette, ou si deux valeurs différentes sont données.
     */
    public function count(string $plain, string $nouns): ?int
    {
        $words = implode('|', [...array_keys(self::UNITS), 'cent', 'cents']);
        $number = '(\d+(?:[  ]\d{3})*|(?:'.$words.')(?:(?:[ -]+et)?[ -]+(?:'.$words.'))*)';
        $qualifier = '(?:(?:graves?|légers?|légèrement|grièvement|autres|nouveaux|dont|voyageurs|passagers|personnes|piétons|ouvriers|agents|employés|militaires|enfants|soldats)\s+)*';
        if (! preg_match_all('/(?<![\p{L}\d])'.$number.'\s+'.$qualifier.'(?:'.$nouns.')(?![\p{L}])/iu', $plain, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $values = [];
        foreach ($all as $m) {
            $before = substr($plain, 0, (int) $m[1][1]);
            if (preg_match(self::APPROX, $before)) {
                return null;
            }
            $value = $this->number($m[1][0]);
            if ($value === null) {
                return null;
            }
            $values[] = $value;
        }

        return count(array_unique($values)) === 1 ? $values[0] : null;
    }

    /** Nombre écrit en chiffres ou en lettres (jusqu'à plusieurs centaines), null si illisible. */
    public function number(string $text): ?int
    {
        $text = mb_strtolower(trim($text));
        if (preg_match('/^\d[\d  ]*$/u', $text)) {
            return (int) preg_replace('/\D/u', '', $text);
        }
        $total = 0;
        $current = 0;
        foreach (preg_split('/[\s-]+/u', $text) ?: [] as $word) {
            if ($word === 'et') {
                continue;
            }
            if ($word === 'cent' || $word === 'cents') {
                $current = max($current, 1) * 100;
                $total += $current;
                $current = 0;

                continue;
            }
            $value = self::UNITS[$word] ?? null;
            if ($value === null) {
                return null;
            }
            // « quatre-vingt » : 4 × 20.
            $current = ($value === 20 && $current === 4) ? 80 : $current + $value;
        }

        return $total + $current > 0 ? $total + $current : null;
    }
}
