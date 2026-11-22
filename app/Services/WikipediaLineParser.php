<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Parse les articles Wikipédia FR « Ligne de X à Y » : infobox + TOUS les
 * événements datés, AVEC LA PORTION de ligne concernée.
 *
 * Pourquoi la portion : la carte par décennie doit savoir QUELLE partie d'une
 * ligne ferme (« 18 avril 1939 : fermeture du tronçon de Verneuil-l'Étang à
 * Marles-en-Brie au trafic des voyageurs »), pas seulement qu'« elle ferme ».
 *
 * Sources d'événements dans l'article, par ordre de fiabilité :
 *   1. tableaux chronologiques (colonnes Section / Date d'ouverture / Date de
 *      fermeture aux voyageurs / aux marchandises / déclassement…) ;
 *   2. listes à puces (« Chronologie », « Dates de déclassement »…) ;
 *   3. texte des sections historiques (phrases avec sujet « la ligne » /
 *      « la section de X à Y » + verbe + date) ;
 *   4. infobox (mise en service, électrification, fermeture) - seulement en
 *      repli, car elle ne dit ni la portion ni la nature du trafic.
 *
 * Typage (cf. App\Enums\LineEventType) : « fermeture aux voyageurs » =
 * closure_passenger, « aux marchandises » = closure_freight, « fermeture »
 * sans précision ou « tout trafic » = closure. Idem ouverture / réouverture.
 *
 * Ne touche pas la base : retourne des tableaux.
 */
class WikipediaLineParser
{
    /** Proposition en cours d'analyse (casse d'origine, pour badObject()). */
    private string $currentClause = '';

    /** Portion nommée par le titre de section courant (« Section de X à Y »). */
    private ?array $headingSection = null;

    /** Titre de l'article analysé (« LGV Rhin-Rhône ») : une LGV citée sous un autre nom est une autre ligne. */
    private ?string $articleTitle = null;

    /** Mots d'une électrification (« électrifiée », « mise sous tension », « 25 kV », « caténaire »). */
    private const ELECTRIFICATION_RX = '/électrifi\w*|sous tension|caténaire|\b\d+(?:[,.]\d+)?\s*kv\b|\bvolts?\b|courant (?:continu|alternatif|monophasé)|traction électrique/u';

    /**
     * Services, matériels et équipements qui « ouvrent » ou sont « mis en service » sans être la
     * ligne : un train, une relation, des aller-retours, un évitement, un poste, un ouvrage.
     */
    private const SERVICE_RX = '/\b(?:trains?|tgv|ter|intercités|aqualys|interloire|téoz|corail|ouigo|talgo|thalys|eurostar|relations?|navettes?|omnibus|express|autorails?|liaisons?|aller-retours?|allers-retours|rotations?|missions?|évitements?|postes?|block|bal|bapr|bmu|signalisation|ouvrages?)\b/u';

    /** Nom d'une portion de ligne : son ouverture est bien celle de la ligne. */
    private const LINE_NOUN_RX = '/\b(?:la ligne|cette ligne|section|tronçon|portion|partie|antenne|embranchement|branche|prolongement de la ligne)\b/u';

    private const API_URL = 'https://fr.wikipedia.org/w/api.php';

    /**
     * Itinéraire dont la ligne n'est qu'une partie (« la grande artère historique entre Paris et
     * Bruxelles », « l'axe de Paris-Nord à Laon », « dans l'optique de créer un itinéraire de Gray à
     * Chalon-sur-Saône ») : la portion qui suit n'est pas une section de la ligne. « Le nouvel
     * itinéraire de Saint-Denis à Creil … est mis en service » reste une portion (sujet de l'événement).
     */
    public const ITINERARY_RX = '/(?:\b(?:artère|axe|radiale|magistrale|corridor)s?|\b(?:partie\s+d\S*|créer|compléter|former|constituer|achever)\s+(?:\S+\s+){0,3}?(?:itinéraire|relation|liaison)s?)(?:\s+[\p{L}-]+){0,2}\s*$/u';

    /**
     * Pays étrangers : un événement situé « au Japon », « en Belgique »… ne concerne pas la ligne
     * française de l'article (« inspirée par le précédent du Shinkansen Tōkaidō, ouvert au Japon en
     * 1964 entre Tokyo et Osaka »), sauf si sa portion part d'une extrémité de la ligne.
     */
    public const FOREIGN_COUNTRY_RX = '/\b(?:au|en|aux)\s+(?:japon|chine|corée(?:\s+du\s+sud)?|taïwan|inde|russie|états-unis|canada|royaume-uni|angleterre|écosse|irlande|allemagne|belgique|suisse|italie|espagne|portugal|pays-bas|luxembourg|autriche|danemark|suède|norvège|finlande|pologne|tchéquie|hongrie|turquie|maroc|algérie|tunisie|argentine|brésil|chili|australie)\b/u';

    /** Comparaison avec un autre réseau (« inspirée par le précédent du … », « sur le modèle du … »). */
    /** « … et une ligne de X à Y est … », « … et le projet … » : proposition coordonnée à un autre sujet. */
    private const NEW_SUBJECT_RX = '/\bet\s+(?:une?|la|le|les|des|cette|ce|ces)\s+(?:nouvelles?\s+|autres?\s+)?(?:lignes?|sections?|tronçons?|projets?|liaisons?|embranchements?|antennes?|compagnies?)\b/u';

    public const COMPARISON_RX = '/\b(?:le\s+précédent\s+d|inspirée?s?\s+(?:par|de|du|des)\s|à\s+l[\'’]image\s+d|sur\s+le\s+modèle\s+d)/u';

    private const USER_AGENT = 'VFF-Bot/1.0 (+https://vff-project.org)';

    public const MONTHS = [
        'janvier' => 1, 'février' => 2, 'fevrier' => 2, 'mars' => 3, 'avril' => 4,
        'mai' => 5, 'juin' => 6, 'juillet' => 7, 'août' => 8, 'aout' => 8,
        'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'décembre' => 12, 'decembre' => 12,
    ];

    public const TYPE_LABELS = [
        'opening' => 'Ouverture', 'opening_passenger' => 'Ouverture aux voyageurs',
        'opening_freight' => 'Ouverture aux marchandises', 'closure' => 'Fermeture (tout trafic)',
        'closure_passenger' => 'Fermeture aux voyageurs', 'closure_freight' => 'Fermeture aux marchandises',
        'reopening' => 'Réouverture', 'reopening_passenger' => 'Réouverture aux voyageurs',
        'reopening_freight' => 'Réouverture aux marchandises', 'dismantling' => 'Dépose des voies',
        'declassification' => 'Déclassement', 'electrification' => 'Électrification',
        'concession' => 'Concession', 'concession_end' => 'Fin de concession',
        'modernization' => 'Modernisation', 'accident' => 'Accident', 'other' => 'Événement',
    ];

    /** Formulations qui désignent explicitement TOUTE la ligne. */
    public const EXPLICIT_WHOLE_LINE = '/toute la ligne|l[\'’]ensemble de la ligne|la totalité|intégralité|entièrement|en totalité|la ligne entière|tout le parcours|de bout en bout|(?:la|cette) ligne (?:est|a été|fut|sera) (?:\p{L}+ )?(?:définitivement|finalement|totalement|complètement|officiellement)/u';

    /**
     * Libellé des événements « other » qui remplacent une fausse fermeture aux voyageurs :
     * fin de la desserte omnibus, des trains de voyageurs subsistant (trains directs, en
     * saison, en fin de semaine). Règle métier : une desserte saisonnière ou partielle reste
     * du voyageur, la portion n'est pas « fret seul ».
     */
    public const SERVICE_REDUCTION_LABEL = 'Réduction de la desserte voyageurs';

    /**
     * Équipements (signalisation, contrôle de vitesse, radio, commande, postes) : leur « mise en
     * service » n'est jamais une ouverture de la ligne (752000 : « mise en service technique de
     * l'ETCS sur la ligne » le 12/11/2024 lue comme ouverture). Alternatives d'expression régulière,
     * en minuscules, sans groupe capturant.
     */
    public const EQUIPMENT_RX = 'bal|bapr|bmu[\w-]*|block|signalisation|capi|prs|pai|poste|kvb|tvm|etcs|ertms|gsm-r|ipcs|commande centralisée|cantonnement|nexteo|sacem';

    /**
     * Sections sans intérêt chronologique pour la LIGNE (bibliographie, galeries, matériel
     * roulant…). Une sous-section d'une section écartée l'est aussi (cf. parseChronology) :
     * « == Matériel roulant == / === Locomotives compound === / *22-2 … mise en service sur
     * la TPT 1929 » parle d'une locomotive, pas d'une ouverture de la ligne (3905 Pithiviers).
     */
    private const SKIP_SECTIONS = '/^(notes?\b|références|sources?\b|bibliographie|voir aussi|liens? externes?|articles? connexes|annexes?|galerie|filmographie|.*cinéma|.*culture|.*littérature|photos?\b|ouvrages|liste des gares|gares? (de la ligne|desservies)|gares?(?: et haltes?)?\s*$|haltes?\s*$|ouvrages d.art|matériel roulant|matériels?(?:\s+(?:moteurs?|remorqués?|utilisés?|ferroviaires?))?\s*$|vitesses? limites?|profil)/iu';

    /** Noms capitalisés qui ne sont pas des lieux. */
    private const NOT_PLACES = [
        'il', 'elle', 'ils', 'elles', 'la', 'le', 'les', 'un', 'une', 'en', 'ce', 'cette', 'ces', 'son', 'sa',
        'sncf', 'rff', 'ratp', 'plm', 'po', 'état', 'etat', 'compagnie', 'société', 'ministère', 'conseil',
        'région', 'département', 'france', 'cette section', 'ligne', 'section', 'tronçon', 'après', 'avant',
        'depuis', 'pendant', 'lors', 'puis', 'ter', 'tgv', 'intercités', 'fret', 'voyageurs', 'nord', 'sud',
        'est', 'ouest', 'midi', 'réseau', 'pk', 'cfd', 'seconde guerre mondiale', 'première guerre mondiale',
        'guerre', 'libération', 'occupation', 'allemands', 'alliés', 'conseil général', 'administration',
    ];

    // ------------------------------------------------------------------
    // API historique (vff:enrich-line-wikipedia)
    // ------------------------------------------------------------------

    /**
     * Cherche puis parse un article Wikipédia pour une ligne.
     */
    public function fetchAndParse(string $lineName, ?string $lineCode = null): array
    {
        $title = $this->findArticleTitle($lineName, $lineCode);
        if (! $title) {
            return ['found' => false];
        }

        $wikitext = $this->fetchWikitext($title);
        if (! $wikitext) {
            return ['found' => false];
        }

        $metadata = $this->parseInfobox($wikitext);

        return [
            'found' => true,
            'title' => $title,
            'url' => 'https://fr.wikipedia.org/wiki/'.str_replace(' ', '_', $title),
            'metadata' => $metadata,
            'events' => $this->parseEvents($wikitext, $title),
            'companies' => $this->parseCompanies($wikitext, $metadata),
            'regions' => $this->parseRegions($metadata),
            'departements' => $this->parseDepartements($metadata),
            'wikitext' => $wikitext,
        ];
    }

    private function findArticleTitle(string $lineName, ?string $lineCode = null): ?string
    {
        $candidates = [$lineName];
        $normalized = strtr($lineName, ['–' => '-', '—' => '-', '’' => "'"]);
        if ($normalized !== $lineName) {
            $candidates[] = $normalized;
        }
        if (preg_match('/^([^–\-]+?)[–\-]\s*(.+)$/u', $lineName, $m) && ! str_starts_with($lineName, 'Ligne ')) {
            $candidates[] = 'Ligne de '.trim($m[1]).' à '.trim($m[2]);
        }
        if (str_contains($lineName, ' (LGV)')) {
            $candidates[] = str_replace(' (LGV)', '', $lineName);
            $candidates[] = preg_replace('/^Ligne (?:d[e\']\s*)/', 'LGV ', str_replace(' (LGV)', '', $lineName));
        }
        foreach (array_values(array_unique(array_filter($candidates))) as $c) {
            $r = Http::withUserAgent(self::USER_AGENT)->timeout(20)->get(self::API_URL, [
                'action' => 'opensearch', 'search' => $c, 'limit' => 5, 'format' => 'json', 'profile' => 'fuzzy', 'maxlag' => 5,
            ])->json();
            foreach ($r[1] ?? [] as $title) {
                if (preg_match('/^(Ligne |LGV )/u', $title) && ! str_contains($title, 'homonymie')) {
                    return $title;
                }
            }
        }

        return null;
    }

    private function fetchWikitext(string $title): ?string
    {
        $r = Http::withUserAgent(self::USER_AGENT)->timeout(30)->get(self::API_URL, [
            'action' => 'parse', 'page' => $title, 'format' => 'json', 'prop' => 'wikitext', 'redirects' => 1, 'maxlag' => 5,
        ])->json();

        return $r['parse']['wikitext']['*'] ?? null;
    }

    /**
     * Parse `{{Infobox Ligne ferroviaire | param = valeur | …}}`.
     */
    public function parseInfobox(string $wikitext): array
    {
        if (! preg_match('/\{\{\s*Infobox[^|]*\|/iu', $wikitext, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $wikitext = preg_replace('/<!--.*?-->/su', '', $wikitext);
        preg_match('/\{\{\s*Infobox[^|]*\|/iu', $wikitext, $m, PREG_OFFSET_CAPTURE);
        // Extrait le bloc équilibré de l'infobox.
        $start = $m[0][1] + strlen($m[0][0]);
        $depth = 1;
        $len = strlen($wikitext);
        $i = $start;
        for (; $i < $len - 1 && $depth > 0; $i++) {
            $two = $wikitext[$i].$wikitext[$i + 1];
            if ($two === '{{') {
                $depth++;
                $i++;
            } elseif ($two === '}}') {
                $depth--;
                $i++;
            }
        }
        $inner = substr($wikitext, $start, max(0, $i - $start - 2));

        // `|` séparateurs seulement hors templates `{{…}}` et liens `[[…]]`.
        $params = [];
        $tplDepth = 0;
        $linkDepth = 0;
        $current = '';
        $key = null;
        $chars = mb_str_split($inner);
        $count = count($chars);
        for ($i = 0; $i < $count; $i++) {
            $ch = $chars[$i];
            $next = $chars[$i + 1] ?? '';
            if ($ch === '{' && $next === '{') {
                $tplDepth++;
                $current .= $ch.$next;
                $i++;

                continue;
            }
            if ($ch === '}' && $next === '}') {
                $tplDepth--;
                $current .= $ch.$next;
                $i++;

                continue;
            }
            if ($ch === '[' && $next === '[') {
                $linkDepth++;
                $current .= $ch.$next;
                $i++;

                continue;
            }
            if ($ch === ']' && $next === ']') {
                $linkDepth--;
                $current .= $ch.$next;
                $i++;

                continue;
            }
            if ($ch === '|' && $tplDepth === 0 && $linkDepth === 0) {
                if ($key !== null) {
                    $params[trim($key)] = trim($current);
                }
                $key = null;
                $current = '';

                continue;
            }
            if ($ch === '=' && $key === null && $tplDepth === 0 && $linkDepth === 0) {
                $key = $current;
                $current = '';

                continue;
            }
            $current .= $ch;
        }
        if ($key !== null) {
            $params[trim($key)] = trim($current);
        }

        foreach ($params as $k => $v) {
            $params[$k] = $k === 'schéma' || $k === 'schéma2' ? '' : $this->cleanInline($v);
        }

        return $params;
    }

    /**
     * Événements datés de l'article (format riche, cf. parseChronology()).
     * Les clés historiques `type`, `year`, `date`, `description`,
     * `confidence`, `source_quote` restent présentes.
     */
    public function parseEvents(string $wikitext, ?string $title = null): array
    {
        return $this->parseChronology($wikitext, $title);
    }

    // ------------------------------------------------------------------
    // Chronologie par portion
    // ------------------------------------------------------------------

    /**
     * Extrait tous les événements datés avec leur portion.
     *
     * @return list<array{type: string, date: string, year: int, precision: string,
     *     section_from: ?string, section_to: ?string, pk_start_m: ?int, pk_end_m: ?int,
     *     portion_unknown: bool, origin: string, heading: string, description: string,
     *     confidence: string, source_quote: string}>
     */
    public function parseChronology(string $wikitext, ?string $title = null): array
    {
        $infobox = $this->parseInfobox($wikitext);
        $ends = $this->lineEnds($title, $infobox);
        $text = $this->toPlainText($wikitext, $title);
        $this->articleTitle = $title;

        $events = [];
        $lines = explode("\n", $text);
        $headings = [];
        $headingSections = [];
        $this->headingSection = null;
        $skip = false;
        $bulletCtx = [];
        $lastSection = null;
        $n = count($lines);
        for ($i = 0; $i < $n; $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^(={2,6})\s*(.+?)\s*=+$/u', $line, $m)) {
                $level = strlen($m[1]);
                $headings = array_slice($headings, 0, max(0, $level - 2));
                $headings[] = trim($m[2]);
                // Écartée si elle-même ou l'une de ses sections parentes l'est.
                $skip = (bool) array_filter($headings, fn ($h) => preg_match(self::SKIP_SECTIONS, $h));
                $bulletCtx = [];
                $lastSection = null;
                // Titre de section qui nomme une portion : contexte des événements qu'il contient.
                // « Fermeture de la section Mézidon - Dives » nomme aussi la portion de ce qu'il raconte.
                $hs = preg_match('/^(?:(?:fermeture|déclassement|dépose|désaffectation|neutralisation)s?\s+(?:de\s+la\s+|du\s+|des\s+|de\s+l[\'’])?)?(?:la |le )?(?:section|tronçon|partie|portion|antenne|branche|ligne)s?\b/iu', trim($m[2])) ? $this->findSection(trim($m[2]), $ends) : null;
                $headingSections[$level] = $hs && ! $hs['other_line'] && ! $hs['whole_line'] && ($hs['from'] || $hs['pk_start'] !== null) ? $hs : null;
                foreach (array_keys($headingSections) as $lv) {
                    if ($lv > $level) {
                        unset($headingSections[$lv]);
                    }
                }
                $this->headingSection = null;
                foreach ($headingSections as $hsv) {
                    if ($hsv) {
                        $this->headingSection = $hsv;
                    }
                }

                continue;
            }
            $heading = implode(' > ', $headings);

            if (str_starts_with($line, '{|')) {
                $table = [];
                $depth = 0;
                for (; $i < $n; $i++) {
                    $tl = trim($lines[$i]);
                    if (str_starts_with($tl, '{|')) {
                        $depth++;
                    }
                    if (str_starts_with($tl, '|}')) {
                        $depth--;
                        if ($depth <= 0) {
                            break;
                        }
                    }
                    $table[] = $lines[$i];
                }
                if (! $skip) {
                    array_push($events, ...$this->parseTable($table, $heading, $ends));
                }

                continue;
            }
            if ($skip) {
                continue;
            }

            if (preg_match('/^([*#]+)[:*#]*\s*(.*)$/u', $line, $m)) {
                $depth = strlen($m[1]);
                $parent = $bulletCtx[$depth - 1] ?? null;
                $bulletCtx = array_filter($bulletCtx, fn ($d) => $d < $depth, ARRAY_FILTER_USE_KEY);
                // Une « puce » de plusieurs phrases est du texte : même prudence que la prose.
                $isProse = mb_strlen($m[2]) > 220 && ! preg_match('/^.{0,25}\b(1[89]\d\d|20[0-3]\d)\b/u', $m[2]);
                $res = $this->analyze($m[2], $isProse ? 'prose' : 'bullet', $heading, $ends, $parent);
                array_push($events, ...$res['events']);
                $bulletCtx[$depth] = [
                    'section' => $res['section'] ?? ($parent['section'] ?? null),
                    'date' => $res['events'] ? null : ($res['date'] ?? ($parent['date'] ?? null)),
                    'list_type' => $parent['list_type'] ?? null,
                ];

                continue;
            }
            if (str_starts_with($line, ';')) {
                // Terme de liste de définitions (« ; 1938 » ou « ; Section de X à Y »).
                $res = $this->analyze(ltrim($line, '; '), 'bullet', $heading, $ends, null);
                array_push($events, ...$res['events']);
                $bulletCtx = [0 => ['section' => $res['section'] ?? null, 'date' => $res['events'] ? null : ($res['date'] ?? null)]];

                continue;
            }
            if (preg_match('/^[|!]/u', $line)) {
                continue;
            } // reste de tableau mal formé

            $bulletCtx = [];
            $res = $this->analyze(ltrim($line, ': '), 'prose', $heading, $ends, ['section' => $lastSection, 'date' => null]);
            array_push($events, ...$res['events']);
            $lastSection = $res['section'] ?? $lastSection;
            // « Le trafic des marchandises a été supprimé en cinq étapes : » puis une puce par portion
            // (« de Grand - Avranville à Sionne - Midrevaux, le 3 mars 1969 ») : des fermetures de ce type.
            if (($listType = $this->listLeadType($line)) !== null) {
                $bulletCtx = [0 => ['section' => null, 'date' => null, 'list_type' => $listType]];
            }
        }

        // Les événements des lectures ajoutées (étapes, date commune, verbe au présent) ne masquent pas le
        // repli de l'infobox : ses autres dates restent (Cavaillon - Apt « ouvre en 1877 » n'efface pas
        // « mise en service2 = 1890 ») ; une même année en double est retirée par dedupe().
        $shadowing = array_filter($events, fn ($e) => ! isset($e[self::ADDED_READING]));
        $events = array_map(function ($e) {
            unset($e[self::ADDED_READING]);

            return $e;
        }, $events);
        $events = array_merge($events, $this->infoboxEvents($infobox, $shadowing));

        return $this->dedupe($events);
    }

    /**
     * Gares du schéma de ligne ({{BSnbis|icônes…|PK|[[Gare de X|X]]|…}}) avec
     * leur PK : seule source « nom ↔ PK » sur bien des sections déclassées.
     *
     * @return list<array{name: string, pk_m: int, icon: string}>
     */
    public function parseRouteDiagram(string $wikitext): array
    {
        $out = [];
        $wikitext = preg_replace('/<!--.*?-->/su', '', $wikitext);
        if (! preg_match_all('/\{\{\s*BS(\d*)(?:e?bis|-?)\s*\|/u', $wikitext, $mm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return [];
        }
        $length = RouteDiagramStations::lineLengthM($wikitext);
        foreach ($mm as $m) {
            $n = $m[1][0] === '' ? 1 : (int) $m[1][0];
            $params = $this->templateParams($wikitext, $m[0][1] + strlen($m[0][0]));
            $pos = array_values(array_filter($params, fn ($p) => ! preg_match('/^\s*[A-Za-z]\w*\s*=/u', $p)));
            if (count($pos) < $n + 2) {
                continue;
            }
            $icons = implode(' ', array_slice($pos, 0, $n));
            if (! preg_match('/(?:^|[\s\-])(?:ex|u|ut|t|e|d|l|m|v|h|um|umv|x|ux)*[KT]?(BHF|HST)(?:[a-z@+\-]|$|\s)/u', $icons, $im)) {
                continue;
            }
            $km = trim($pos[$n]);
            // {{BSkm|102,468|0,000}} : PK sur la ligne d'origine puis sur celle-ci → le dernier, sauf
            // au terminus qui donne le PK sur cette ligne puis sur l'autre (RouteDiagramStations::bskmChoice).
            if (preg_match('/\{\{\s*BSkm\s*\|([^}]*)\}\}/u', $km, $k)) {
                $values = array_map(fn ($v) => trim(preg_replace("/'{2,}|<[^>]+>/u", '', $v)), explode('|', $k[1]));
                $parsed = [];
                foreach ($values as $v) {
                    if (preg_match('/^-?\d{1,4}(?:[,.]\d{1,3})?$/u', $v)) {
                        $parsed[(int) RouteDiagramStations::pk($v)] = $v;
                    }
                }
                $km = $parsed === [] ? (string) end($values) : $parsed[RouteDiagramStations::bskmChoice(array_keys($parsed), $length)];
            }
            $km = trim(preg_replace("/'{2,}|<[^>]+>/u", '', $km));
            if (! preg_match('/^(-?\d{1,4})(?:[,.](\d{1,3}))?$/u', $km, $k)) {
                continue;
            }
            $pk = (int) $k[1] * 1000 + (isset($k[2]) ? (int) str_pad($k[2], 3, '0') : 0);
            $name = $this->cleanInline($pos[$n + 1]);
            $name = trim(preg_replace('/\s*\(.*?\)\s*$/u', '', $name));
            if ($name === '' || mb_strlen($name) > 120 || preg_match('/^(vers|ligne|bif|tunnel|viaduc|pont)\b/iu', $name)) {
                continue;
            }
            $out[] = ['name' => $name, 'pk_m' => $pk, 'icon' => mb_substr(trim($icons), 0, 60)];
        }

        return $out;
    }

    /** Paramètres de premier niveau d'un modèle commençant à $start (après « {{nom| »). */
    private function templateParams(string $s, int $start): array
    {
        $params = [];
        $cur = '';
        $depthT = 0;
        $depthL = 0;
        $len = strlen($s);
        for ($i = $start; $i < $len; $i++) {
            $two = substr($s, $i, 2);
            if ($two === '{{') {
                $depthT++;
                $cur .= $two;
                $i++;

                continue;
            }
            if ($two === '[[') {
                $depthL++;
                $cur .= $two;
                $i++;

                continue;
            }
            if ($two === ']]') {
                $depthL--;
                $cur .= $two;
                $i++;

                continue;
            }
            if ($two === '}}') {
                if ($depthT === 0) {
                    $params[] = $cur;
                    break;
                }
                $depthT--;
                $cur .= $two;
                $i++;

                continue;
            }
            if ($s[$i] === '|' && $depthT === 0 && $depthL === 0) {
                $params[] = $cur;
                $cur = '';

                continue;
            }
            if ($s[$i] === "\n" && $depthT === 0 && $depthL === 0 && strlen($cur) > 400) {
                break;
            }
            $cur .= $s[$i];
        }

        return $params;
    }

    /**
     * Extrémités de la ligne d'après le titre (« Ligne du Mans à Angers » →
     * [Le Mans, Angers]) ou l'infobox (origine / destination).
     *
     * @return list<string>
     */
    public function lineEnds(?string $title, array $infobox = []): array
    {
        $ends = [];
        if ($title && preg_match('/^(?:Ligne|Chemin de fer|Tramway|Raccordement)\s+(de la |de l[\'’]|des |du |de |d[\'’])(.+?)\s+(?:à|au|aux)\s+(.+?)(?:\s*\(.*\))?$/u', $title, $m)) {
            $ends[] = $this->restoreArticle($m[1], $m[2]);
            $ends[] = preg_replace('/^(?:la |le |les |l[\'’])/u', '', $m[3]);
            if (preg_match('/\s(au|aux)\s/u', $title, $mm)) {
                $ends[1] = ($mm[1] === 'au' ? 'Le ' : 'Les ').$ends[1];
            }
        }
        if (! $ends) {
            foreach (['origine', 'destination'] as $k) {
                if (! empty($infobox[$k])) {
                    $ends[] = $infobox[$k];
                }
            }
        }

        return array_values(array_unique(array_filter($ends)));
    }

    private function restoreArticle(string $prep, string $name): string
    {
        return match (trim($prep)) {
            'du' => 'Le '.$name,
            'des' => 'Les '.$name,
            'de la' => 'La '.$name,
            'de l\'', 'de l’' => 'L\''.$name,
            default => $name,
        };
    }

    // ------------------------------------------------------------------
    // Nettoyage du wikitexte
    // ------------------------------------------------------------------

    /**
     * Wikitexte → texte brut ligne à ligne (titres, puces et tableaux
     * conservés). Les liens vers D'AUTRES articles de lignes sont entourés de
     * ⟦…⟧ pour ne pas attribuer leurs dates à la ligne courante.
     */
    public function toPlainText(string $wikitext, ?string $selfTitle = null): string
    {
        $s = preg_replace('/<!--.*?-->/su', '', $wikitext);
        $s = preg_replace('/<ref[^>]*\/>/iu', '', $s);
        $s = preg_replace('/<ref[^>]*>.*?<\/ref\s*>/isu', '', $s);
        $s = preg_replace('/<(gallery|timeline|math|syntaxhighlight|imagemap|mapframe|graph)[^>]*>.*?<\/\1\s*>/isu', '', $s);
        $s = preg_replace('/<references[^>]*\/?>(.*?<\/references>)?/isu', '', $s);
        $s = $this->stripFileLinks($s);

        for ($i = 0; $i < 15 && str_contains($s, '{{'); $i++) {
            $new = preg_replace_callback('/\{\{((?:[^{}]|\{(?!\{)|\}(?!\}))*)\}\}/u', fn ($m) => $this->renderTemplate($m[1]), $s);
            if ($new === null || $new === $s) {
                break;
            }
            $s = $new;
        }

        $s = preg_replace('/\[(?:https?:)?\/\/[^\s\]]+\s+([^\]]*)\]/u', '$1', $s);
        $s = preg_replace('/\[(?:https?:)?\/\/[^\s\]]+\]/u', '', $s);

        $self = $selfTitle ? $this->norm($selfTitle) : null;
        $s = preg_replace_callback('/\[\[([^\[\]|]*)(?:\|([^\[\]]*))?\]\]/u', function ($m) use ($self) {
            $target = trim(str_replace('_', ' ', $m[1]));
            if (preg_match('/^:?(Catégorie|Category|Portail|Projet|Modèle|Aide|Wikipédia|[a-z]{2,3}(-[a-z]+)?):/iu', $target)) {
                return '';
            }
            $label = isset($m[2]) && trim($m[2]) !== '' ? $m[2] : preg_replace('/#.*$/u', '', $target);
            if ($self !== null && preg_match('/^(Ligne|LGV|Chemin de fer|Tramway|Ligne [A-Z0-9]|RER|Transilien|Métro)\b/iu', $target)
                && $this->norm($target) !== $self) {
                return '⟦'.$label.'⟧';
            }

            return $label;
        }, $s);

        $s = str_replace(['[[', ']]'], '', $s); // liens mal formés dans la source
        $s = preg_replace("/'{2,}/u", '', $s);
        $s = preg_replace('/<br\s*\/?>/iu', ' ', $s);
        $s = preg_replace('/<\/?[a-z][^>]*>/iu', '', $s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = str_replace(["\u{00A0}", "\u{202F}", "\u{2009}", "\u{2007}"], ' ', $s);
        $s = preg_replace('/[ \t]+/u', ' ', $s);

        return $s;
    }

    /** Version « une ligne » pour les valeurs d'infobox. */
    private function cleanInline(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', $this->toPlainText($s)));
    }

    private function stripFileLinks(string $s): string
    {
        $out = '';
        $pos = 0;
        while (preg_match('/\[\[\s*(?:Fichier|File|Image|Média|Media)\s*:/iu', $s, $m, PREG_OFFSET_CAPTURE, $pos)) {
            $start = $m[0][1];
            $out .= substr($s, $pos, $start - $pos);
            $depth = 0;
            $len = strlen($s);
            $i = $start;
            for (; $i < $len - 1; $i++) {
                $two = $s[$i].$s[$i + 1];
                if ($two === '[[') {
                    $depth++;
                    $i++;
                } elseif ($two === ']]') {
                    $depth--;
                    $i++;
                    if ($depth === 0) {
                        break;
                    }
                }
            }
            $pos = $i + 1;
        }

        return $out.substr($s, $pos);
    }

    private function renderTemplate(string $inner): string
    {
        // Les barres d'un lien [[cible|texte]] ne séparent pas les paramètres ({{1er|juin [[1969 dans les chemins de fer|1969]]}}).
        $parts = array_map('trim', preg_split('/\|(?![^\[]*\]\])/u', $inner) ?: [$inner]);
        $rawName = array_shift($parts);
        if (str_contains($rawName, ':') && ! preg_match('/^(st|ste)\.?$/iu', $rawName)) {
            [$fn, $arg] = explode(':', $rawName, 2);

            return in_array(mb_strtolower(trim($fn)), ['formatnum', 'lc', 'uc', 'ucfirst', 'lcfirst'], true) ? trim($arg) : '';
        }
        $name = mb_strtolower(trim(str_replace('_', ' ', $rawName)));
        $pos = [];
        $named = [];
        foreach ($parts as $p) {
            if (preg_match('/^([\p{L}\d _-]{1,25})=(.*)$/su', $p, $m) && ! preg_match('/^\d+$/', trim($m[1]))) {
                $named[mb_strtolower(trim($m[1]))] = trim($m[2]);
            } else {
                $pos[] = $p;
            }
        }

        return match (true) {
            in_array($name, ['date', 'date-', 'date+', 'date sport', 'date-1', 'date républicaine', 'date julienne'], true) => $this->renderDate($parts),
            // {{1er|juin 1969}}, {{1er|mai|1900}}, {{1er|trimestre}} : le 1er suivi de ses arguments.
            $name === '1er' => trim('1er '.implode(' ', array_filter($pos, fn ($p) => $p !== ''))),
            in_array($name, ['er', 'e', 're', 'ème', 'eme', 'ers', 'es', 'res', 'ex'], true) => $name,
            in_array($name, ['unité', 'nombre', 'nb', 'num', 'formatnum', 'nobr', 'nowrap', 'centrer', 'souligner',
                'citation', 'citation bloc', 'refnec', 'référence nécessaire', 'référence souhaitée', 'refsou',
                'abréviation', 'abr', 'sp', 'petit', 'small', 'gras', 'italique', 'guil', 'guillemets', 'noir', 'couleur',
                'ill', 'sous-titre', 'm', 'nom', 'pas clair', 'précision nécessaire', 'non neutre', 'clair'], true) => $name === 'unité' || $name === 'nombre' ? implode(' ', array_slice($pos, 0, 2)) : ($pos[0] ?? ''),
            in_array($name, ['numéro', 'n°', 'nº', 'no', 'numéros', 'n°s', 'nos'], true) => 'n° '.($pos[0] ?? ''),
            in_array($name, ['s-', 's', 'siècle', 'sp-', 's2-', 'xs'], true) => ($pos[0] ?? '').'e siècle',
            in_array($name, ['lang', 'langue'], true) => $pos[1] ?? ($pos[0] ?? ''),
            $name === 'lien' => $named['texte'] ?? $named['fr'] ?? ($pos[0] ?? ''),
            // {{lnobr|LGV 1}} : lien insécable. Rendu en lien (puis ⟦…⟧ s'il vise une autre ligne) : rendu vide,
            // il effaçait le sujet (« La {{lnobr|LGV 1}} n'est ouverte en totalité jusqu'à Bruxelles que le
            // 14 décembre 1997 » devenait « La n'est ouverte… », une ouverture de la LGV Nord).
            in_array($name, ['lnobr', 'lien insécable'], true) => ($pos[0] ?? '') === '' ? '' : '[['.$pos[0].(isset($pos[1]) && $pos[1] !== '' ? '|'.$pos[1] : '').']]',
            in_array($name, ['st.', 'st'], true) => 'St',
            in_array($name, ['ste.', 'ste'], true) => 'Ste',
            $name === 'heure' => ($pos[0] ?? '').' h '.($pos[1] ?? ''),
            default => '',
        };
    }

    /**
     * Gabarits {{Date}}, {{Date-}}, {{Date+}} : « {{Date-|20/7/1862||}} », « {{date|1er|juin|1980|dans les
     * chemins de fer}} », « {{Date|3=2014}} », « {{date||mai|2006}} », « {{Date|1909 07 24}} »...
     * Rendus en « 20 juillet 1862 », « 1er juin 1980 », « 2014 », « mai 2006 », « 24 juillet 1909 » : le
     * lecteur de dates (findDates) ne lit que ces écritures. Les arguments après la date (« dans les
     * chemins de fer », « en France ») sont ignorés.
     *
     * @param  list<string>  $parts  paramètres bruts (sans le nom du gabarit), « 3=2014 » compris
     */
    private function renderDate(array $parts): string
    {
        $slots = [];
        $n = 0;
        foreach ($parts as $p) {
            if (preg_match('/^([1-3])\s*=\s*(.*)$/su', $p, $m)) {
                $slots[(int) $m[1]] = trim($m[2]);
            } elseif (! preg_match('/^[\p{L}\d _-]{1,25}=/u', $p)) {
                $slots[++$n] = $p;
            }
        }
        ksort($slots);
        $words = [];
        foreach (array_slice($slots, 0, 4, true) as $i => $v) {
            if ($i > 3 && $words) {
                break;
            }
            $v = trim(preg_replace('/^le\s+/iu', '', trim($v)));
            if ($v === '') {
                continue;
            }
            $got = $this->leadingDateWords($v);
            $all = count(preg_split('/[\s\/.-]+/u', $v, -1, PREG_SPLIT_NO_EMPTY));
            if ($words === [] && count($got) < $all && preg_match('/\s/u', $v)) {
                // « automne 2017 », « début mars 1950 », « 1er trimestre 2014 » : écriture libre, rendue telle quelle.
                return rtrim($v, ' -');
            }
            if ($got === []) {
                break;
            }
            array_push($words, ...$got);
            if (count($got) < $all) {
                break; // « 1864 dans les chemins de fer » : le reste n'est pas la date
            }
        }

        return $this->normalizeDateWords($words);
    }

    /**
     * Mots du début d'un argument qui appartiennent à une date : nombres, « 1er », mois, « le ».
     *
     * @return list<string>
     */
    private function leadingDateWords(string $v): array
    {
        $out = [];
        foreach (preg_split('/[\s\/.-]+/u', mb_strtolower($v), -1, PREG_SPLIT_NO_EMPTY) as $w) {
            if (preg_match('/^(\d{1,4}|1er)$/u', $w) || isset(self::MONTHS[$w])) {
                $out[] = $w;
            } elseif ($w === 'le' && $out === []) {
                continue;
            } else {
                break;
            }
        }

        return $out;
    }

    /** @param  list<string>  $w */
    private function normalizeDateWords(array $w): string
    {
        $isDay = fn (string $x): bool => $x === '1er' || (ctype_digit($x) && strlen($x) <= 2 && (int) $x >= 1 && (int) $x <= 31);
        $isNumMonth = fn (string $x): bool => ctype_digit($x) && strlen($x) <= 2 && (int) $x >= 1 && (int) $x <= 12;
        $isYear = fn (string $x): bool => ctype_digit($x) && strlen($x) === 4;
        $monthName = fn (string $x): string => isset(self::MONTHS[$x]) ? $x : (string) array_search((int) $x, self::MONTHS, true);

        if (count($w) >= 3) {
            [$a, $b, $c] = $w;
            if ($isDay($a) && (isset(self::MONTHS[$b]) || $isNumMonth($b)) && $isYear($c)) {
                return $a.' '.$monthName($b).' '.$c; // {{Date|1er|juin|1980}}, {{Date|28|08|2022}}, « 01/12/2023 »
            }
            if ($isYear($a) && $isNumMonth($b) && $isDay($c)) {
                return $c.' '.$monthName($b).' '.$a; // « 1909 07 24 », « 1934-09-21 »
            }
        }
        if (count($w) === 2) {
            [$a, $b] = $w;
            if ((isset(self::MONTHS[$a]) || $isNumMonth($a)) && $isYear($b)) {
                return $monthName($a).' '.$b; // {{Date||11|2024}}, {{date|mai 2006}}
            }
            if ($isYear($a) && ($isNumMonth($b) || isset(self::MONTHS[$b]))) {
                return $monthName($b).' '.$a; // « 1914 05 », « 2018-09 »
            }
        }

        return implode(' ', $w);
    }

    public function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
            'œ' => 'oe', 'æ' => 'ae', 'ÿ' => 'y', '’' => "'"]);
        $s = preg_replace('/\bsainte\b/u', 'ste', $s);
        $s = preg_replace('/\bsaint\b/u', 'st', $s);
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s);

        return trim($s);
    }

    // ------------------------------------------------------------------
    // Analyse d'un paragraphe / d'une puce
    // ------------------------------------------------------------------

    /**
     * @param  'bullet'|'prose'  $mode
     * @param  array{section?: ?array, date?: ?array, list_type?: ?string}|null  $ctx  contexte hérité (puce parente, phrase précédente)
     * @return array{events: list<array>, section: ?array, date: ?array}
     */
    private function analyze(string $text, string $mode, string $heading, array $ends, ?array $ctx): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        $events = [];
        $lastSection = $ctx['section'] ?? null;
        $firstSection = null;
        $paraSection = null;
        if ($text === '' || ! preg_match('/\d{4}|PK|déclass|ferm|ouvert/iu', $text)) {
            $sec = $text !== '' ? $this->findSection($text, $ends) : null;

            return ['events' => [], 'section' => $sec && ! $sec['other_line'] ? $sec : null, 'date' => null];
        }

        // Date de puce : « 12 mai 1938 : … » ou date unique de la puce.
        $bulletDate = null;
        if ($mode === 'bullet') {
            $all = array_values(array_filter($this->findDates($text), fn ($d) => $d['precision'] !== 'decade'));
            if ($all && ($all[0]['pos'] < 25 || count($all) === 1)) {
                $bulletDate = $all[0];
            }
            $bulletDate ??= $ctx['date'] ?? null;
        }
        $headingType = $this->headingDefaultType($heading);

        $sentences = $mode === 'prose'
            ? preg_split('/(?<=[.!?»])\s+(?=[\p{Lu}«⟦])/u', $text)
            : [$text];

        $paraSubjectLine = false;
        // Fermetures sans date de la phrase précédente (« … une partie de son réseau est fermée au
        // trafic voyageur. C'est le cas de la section X - Y à partir du 1 mars 1938 »).
        $undatedClosures = [];
        // Dernière date du paragraphe (« … cesse à ce moment »).
        $paraLastDate = null;
        foreach ($sentences as $si => $sentence) {
            $prevTypes = [];
            $prevAccepted = false;
            $sentenceSection = null;
            $lcSentence = mb_strtolower($sentence);
            // Phrase suivante du même paragraphe (« … est supprimée. Il ne reste plus que les trains directs … »).
            $nextLc = mb_strtolower($sentences[$si + 1] ?? '');
            if ($undatedClosures && preg_match('/^c[\'’]est (?:notamment |également |aussi |ainsi |alors )?le cas (?:de|du|des|d[\'’])/u', $lcSentence)) {
                $prevTypes = $undatedClosures;
            }
            $sentenceUndated = [];
            // Réaffectation de l'emprise (voie verte, vélorail…) : pas d'ouverture ferroviaire.
            $greenway = (bool) preg_match('/piétons|cycl|voie verte|promenade|coulée verte|randonn|vélo|draisine|vélorail|musée/u', $lcSentence);
            // « Les sections de A à B et de B à C sont fermées respectivement le D1 et le D2 » : une
            // proposition par portion, dans l'ordre (cf. respectiveStages()).
            $respective = $this->respectiveStages($sentence, $ends);
            $clauses = $respective ?? $this->splitClauses($sentence);
            // Texte de la phrase hors propositions qui portent leur propre ÉVÉNEMENT et leur propre
            // portion : cette portion est extraite, elle ne rend pas « inconnue » celle d'une autre
            // proposition (cf. makeEvent : « La ligne est fermée au trafic voyageurs le 9 janvier
            // 1939, au trafic marchandises sur la section de X à Y en 1940 »). Porte un événement :
            // un mot-clé, un trafic en tête (« au trafic marchandises de X à Y le … ») ou une suite
            // d'énumération (« le 18 mai 1952 pour le tronçon de X à Y », « d'Ambrières à Mayenne en
            // mars 1987 »). Une portion citée sans événement reste un doute (« Du côté suisse la ligne
            // fut démontée entre 1942 et 1943, alors que le trafic marchandise perdura entre
            // Pontarlier et … »).
            $unsectioned = implode(' ', array_filter($clauses, fn ($c) => $this->findSection($c, $ends) === null
                || ! ($this->detectTypes($c, $mode)
                    || preg_match('/^(?:et\s+)?(?:au|aux|des)\s+(?:(?:trafic|service)\s+(?:des\s+)?)?(?:marchandises|voyageurs|fret)\b/u', mb_strtolower($c))
                    || preg_match('/^(?:et\s+)?(?:(?:le|du)\s+(?:1er|\d{1,2})\s+\p{L}+\s+\d{4}|en\s+\d{4}|(?:de|d[\'’])\s*\p{Lu}|entre\s+\p{Lu})/u', $c))));
            // Étapes énumérées dans une même proposition (« Troyes - Bar-sur-Seine le 20 juillet 1862,
            // Bar-sur-Seine - Châtillon-sur-Seine le 19 octobre 1868 ») : une proposition par étape,
            // qui hérite du verbe de la première (cf. stagedItems()).
            $staged = $respective !== null
                ? array_map(fn ($c, $i) => ['text' => $c, 'added' => $i > 0], $clauses, array_keys($clauses))
                : $this->splitStagedClauses($clauses, $ends);
            $clauses = array_column($staged, 'text');
            foreach ($clauses as $ci => $clause) {
                // Événement lu grâce aux étapes, à la date commune ou au verbe au présent (cf. ADDED_READING).
                $added = $staged[$ci]['added'];
                $dates = $this->findDates($clause);
                $section = $this->findSection($clause, $ends);
                $lc = mb_strtolower($clause);

                if ($section && $section['other_line']) {
                    // La phrase parle d'une AUTRE ligne : on l'ignore.
                    $prevTypes = [];

                    continue;
                }
                if ($section && $section['only_to'] && ($sentenceSection['to'] ?? $lastSection['to'] ?? null)) {
                    // « … puis prolongée jusqu'à Y » : départ = arrivée précédente.
                    $section['from'] = $sentenceSection['to'] ?? $lastSection['to'];
                }
                if (! $section && preg_match('/\b(cette (section|partie|portion|antenne)|ce (tronçon|prolongement)|celle-ci|celui-ci)\b/u', $lc)) {
                    $section = $sentenceSection ?? $lastSection;
                }
                $pronoun = $mode === 'prose' && (bool) preg_match('/^(?:[^,]{0,40},\s*)?(elle|il|celle-ci|celui-ci) /u', $lc);
                if (! $section && $pronoun && $paraSection) {
                    $section = $paraSection; // « Elle est ouverte le … » après « La section de X à Y … »
                }
                $types = $this->detectTypes($clause, $mode);
                $inherited = false;
                if (! $types && $prevTypes && $dates && ($section || $this->trafficQualifier($lc) !== null || preg_match('/\b(celle|celui|ceux|celles)\b/u', $lc))) {
                    // Structure parallèle : « …, celle de X à Y le DATE », « … et des marchandises en 1969 ».
                    foreach ($prevTypes as $t) {
                        $types[] = ['type' => $this->requalify($t['type'], $lc), 'pos' => 0];
                    }
                    $inherited = $prevAccepted;
                }
                if (! $types && $mode === 'bullet' && ($listType = $ctx['list_type'] ?? null) && ($dates || $bulletDate) && $section && ! $section['other_line']
                    && ! $greenway && ! ($listType === 'closure' && in_array($headingType, ['declassification', 'dismantling'], true))) {
                    // Puces d'une liste annoncée par « Le trafic des marchandises a été supprimé en cinq étapes : »
                    // (plus précise que le titre, sauf « fermée » sous un titre « Déclassement »).
                    $types = [['type' => $listType, 'pos' => 0]];
                }
                if (! $types && $mode === 'bullet' && $headingType && ($dates || $bulletDate) && $section && ! $section['other_line']) {
                    $types = [['type' => $headingType, 'pos' => 0]];
                }
                if ($section && ! $section['whole_line'] && ! $section['only_to']) {
                    $sentenceSection = $section;
                    $firstSection ??= $section;
                }
                if ($section && ! $section['only_to']) {
                    $paraSection = $section['whole_line'] ? null : $section;
                }
                if (! $types) {
                    continue;
                }
                $prevTypes = $types;
                $isClosure = (bool) array_filter($types, fn ($t) => str_starts_with($t['type'], 'closure'));
                if ($isClosure && $section && $section['whole_line'] && $this->headingSection && preg_match('/\bdont (?:celle|la ligne|cette ligne)\b/u', $lc)) {
                    // « … sur la plupart des lignes de l'Oise dont celle d'Ormoy-Villers à Boves » sous le titre
                    // « Section d'Ormoy-Villers à Estrées-Saint-Denis » : la ligne est citée par son nom, la portion par le titre.
                    $section = $this->headingSection;
                }
                // (« Comme de nombreuses lignes secondaires, elle est fermée … » : la ligne elle-même, comparée.)
                if ($isClosure && ! $section && preg_match('/(?<!comme )\b(?:sur|de) (?:la plupart des|de nombreuses|nombreuses|plusieurs|toutes les) lignes\b/u', $lc)
                    && ! preg_match('/\bdont (?:celle|la ligne|cette ligne)\b/u', $lc)) {
                    continue; // « suppression du service voyageurs sur la plupart des lignes de l'Oise » : mesure générale
                }
                if ($isClosure && $mode === 'prose' && ! $section && preg_match('/^Comme\s+\p{Lu}[^,]{0,40},\s*(?:elle|il)\s/u', $clause)) {
                    continue; // « Comme Viry, elle est fermée au trafic voyageurs depuis 1996 » : une gare, pas la ligne
                }
                if ($isClosure && $section) {
                    $section = $this->withoutKeptSection($clause, $section, $ends);
                }
                if (! $section && $sentenceSection && ! preg_match('/\bla ligne\b/u', $lc)) {
                    $section = $sentenceSection; // hérite de la proposition précédente de la même phrase
                }
                if (! $section && $this->headingSection && ! preg_match(self::EXPLICIT_WHOLE_LINE, $lc)) {
                    $section = $this->headingSection; // « == Section de X à Y == » : la portion du titre
                }
                if (! $dates && $bulletDate) {
                    $dates = [$bulletDate];
                }
                if (! $dates && $isClosure && $paraLastDate && $section
                    && preg_match('/\b(?:à ce moment|à cette (?:même )?date|à la même date|au même moment|le même jour|ce même jour|ce jour-là)\b/u', $lc, $same, PREG_OFFSET_CAPTURE)) {
                    // « Le service voyageurs entre X et Y … cesse à ce moment » : date de la phrase qui précède.
                    $dates = [['pos' => mb_strlen(substr($lc, 0, $same[0][1]))] + $paraLastDate];
                }
                if (! $dates && $section && ! $section['whole_line'] && ! $section['only_to'] && ($shared = $this->sharedStageDate($clauses, $ci, $ends, $mode)) !== null) {
                    // « Le trafic marchandises cesse entre Villars et Poinson et entre Mirebeau-sur-Bèze et
                    // Champagne-sur-Vingeanne le 30 mai 1975 » : une date pour les deux portions.
                    $dates = [['pos' => mb_strlen($clause)] + $shared];
                    $added = true;
                }
                if (! $dates) {
                    if ($isClosure) {
                        $sentenceUndated = array_values(array_filter($types, fn ($t) => str_starts_with($t['type'], 'closure')));
                    }

                    continue;
                }

                if ($this->isUncertain($lc)) {
                    continue;
                }
                if ($this->isOtherInfrastructure($clause, $types, $section !== null)) {
                    continue;
                }
                if ($greenway) {
                    $types = array_values(array_filter($types, fn ($t) => ! preg_match('/^(opening|reopening)/', $t['type'])));
                    if (! $types) {
                        continue;
                    }
                }
                if ($mode === 'prose' && ! $section && ! $inherited && ! ($pronoun && $paraSubjectLine)
                    && ! preg_match('/\b(la|cette|toute la|l.ensemble de la) ligne\b|\bligne (est|fut|a été)\b|\bses (voies|rails)\b/u', $lc)) {
                    continue; // texte libre sans sujet identifiable : trop ambigu
                }
                if ($mode === 'prose' && str_contains($clause, '⟦') && ! $section) {
                    continue;
                }

                $prevAccepted = true;
                foreach ($types as $t) {
                    if (in_array($t['type'], ['closure', 'closure_passenger'], true) && $this->isServiceReduction($t, $lc, $nextLc)) {
                        // Desserte voyageurs réduite, pas supprimée : ce n'est pas une fermeture.
                        $t = ['type' => 'other', 'pos' => $t['pos'], 'label' => self::SERVICE_REDUCTION_LABEL];
                    }
                    if ($mode === 'prose' && in_array($t['type'], ['concession', 'concession_end', 'other'], true) && ! isset($t['label'])) {
                        continue;
                    }
                    if ($mode === 'prose' && ($t['label'] ?? '') === "Déclaration d'utilité publique") {
                        continue;
                    }
                    $date = $this->nearestDate($this->datesOfKeyword($dates, $t['pos'], array_column($types, 'pos'), $lc), $t['pos'], $mode === 'prose' ? 170 : null);
                    if (! $date || $date['year'] < 1820 || $date['date'] > date('Y-m-d', strtotime('+1 month'))) {
                        continue;
                    }
                    // Section « Projets », « Perspectives », « Avenir » : une (ré)ouverture datée de la
                    // seule année y est un projet, un débat ou un calendrier (« le député s'est exprimé en
                    // 2005 sur la réouverture »). Une réouverture réelle y est datée au jour près
                    // (« rouverte le 26 juin 2016 » sous « Projet de réouverture entre Oloron et Bedous »).
                    if ($this->isProjectHeading($heading) && preg_match('/^(opening|reopening)/', $t['type']) && $date['precision'] !== 'day') {
                        continue;
                    }
                    // Titre « Menaces de fermeture » : une fermeture datée de la seule année y est une échéance redoutée.
                    if (str_starts_with($t['type'], 'closure') && preg_match('/\bmenac/iu', $heading) && $date['precision'] !== 'day') {
                        continue;
                    }
                    // Électrification, service, équipement ou autre ligne « mis en service » : pas une ouverture de la ligne.
                    if (str_starts_with($t['type'], 'opening') && $this->openingOfSomethingElse($clause, $sentence, $heading, $t['pos'])) {
                        continue;
                    }
                    // Événement d'une autre ligne ou d'un autre pays cité dans l'article (comparaison, contexte).
                    if ($this->aboutAnotherSubject($clause, $sentence, $t['pos'], $section, $ends)) {
                        continue;
                    }
                    // « Ouverte à partir de 1847 » : début d'une ouverture par étapes, pas l'ouverture de toute la ligne.
                    if (preg_match('/^(opening|reopening)/', $t['type']) && $section === null
                        && preg_match('/\b(?:à partir de|dès)\s+(?:l[\'’]année\s+|l[\'’]an\s+)?$/u', mb_substr($lc, max(0, $date['pos'] - 25), min(25, $date['pos'])))) {
                        continue;
                    }
                    // Propositions recomposées de « respectivement » : la citation reste la phrase de l'article.
                    $event = $this->makeEvent($t['type'], $date, $section, $mode, $heading, $respective !== null ? $sentence : $clause, $sentence,
                        ($t['label'] ?? null) === self::SERVICE_REDUCTION_LABEL ? $t['label'] : null, $unsectioned);
                    if ($added || preg_match(self::PRESENT_VERB_RX, mb_substr($lc, $t['pos'], 8))) {
                        $event[self::ADDED_READING] = true;
                    }
                    $events[] = $event;
                }
            }
            $undatedClosures = $sentenceUndated;
            $sentenceDates = array_values(array_filter($this->findDates($sentence), fn ($d) => $d['precision'] !== 'decade'));
            if ($sentenceDates) {
                $paraLastDate = end($sentenceDates);
            }
            $lastSection = $sentenceSection ?? $lastSection;
            if ($sentenceSection) {
                $paraSubjectLine = false;
            } elseif (preg_match('/^(?:[^,]{0,40},\s*)?(la|cette) (?:ligne|transversale|antenne)\b/u', $lcSentence)) {
                $paraSubjectLine = true;
                $paraSection = null;
            }
        }

        return ['events' => $events, 'section' => $firstSection ?? null, 'date' => $bulletDate];
    }

    /**
     * Découpe une phrase en propositions (« …, puis … », « … ; … »,
     * « … et celle de … »), sans couper « entre X et Y ».
     * Un trafic qui ouvre une nouvelle énumération après une virgule (« fermée en totalité
     * aux voyageurs le 31 mai 1970, au trafic marchandises de Céaucé à Ambrières le 31 mai
     * 1970, d'Ambrières à Mayenne en mars 1987 ») est une proposition à part : les sections
     * qui suivent héritent du verbe ET de ce trafic (fermetures au fret, pas aux voyageurs).
     */
    private function splitClauses(string $sentence): array
    {
        $parts = preg_split('/(\s*;\s*|,?\s+puis\s+|,\s+(?=(?:et\s+)?(?:celle|celui|ceux|celles)\b)|,\s+(?=(?:avant|alors|tandis)\b)|,\s+(?=(?:assurant|permettant|reliant|desservant|rejoignant|empruntant|longeant|traversant|franchissant)\b)|\s+(?:en raison d[eu]s?|à cause d[eu]s?|afin de|pour permettre|du fait d[eu]s?|faute d[eu]s?|grâce à|à la suite d[eu]s?|suite à)\s+|\s+:\s+|\s+et\s+(?=d[e’\']\s|d[’\'])|\s+et\s+(?=(?:des|aux|au)\s+(?:marchandises|voyageurs|fret)\b)|,\s+(?=(?:aux|au)\s+(?:(?:trafic|service)\s+(?:des\s+)?)?(?:marchandises|voyageurs|fret)\b)|\s+et\s+enfin\s+|,\s+(?=(?:de|d[’\'])\s*\p{Lu}[^,;]{2,60}\s(?:à|au)\s+\p{Lu})|,\s+mais\s+(?=(?:la\s+|une\s+|sa\s+)?(?:fermeture|fermée?s?)\b)|\s+(?:tandis|alors)\s+que\s+|\s+et\s+(?=(?:le|la|les|celle|celui|en|au|du|de\s+la|l[\'’])\s|l[\'’]))/u', $sentence, -1, PREG_SPLIT_DELIM_CAPTURE);
        $clauses = [];
        $current = '';
        for ($i = 0; $i < count($parts); $i++) {
            if ($i % 2 === 1) {
                // délimiteur : ne pas couper un « entre X et Y » ouvert
                $isEt = (bool) preg_match('/^\s+et\s+$/u', $parts[$i]);
                if ($isEt && preg_match('/\bentre\s+(?!.*\set\s)[^,;]*$/u', $current)) {
                    $current .= $parts[$i];

                    continue;
                }
                // « fermée aux voyageurs et aux marchandises en 1950 », « fermée au service des
                // voyageurs et au service des marchandises le 1er mars 1950 » : deux trafics, UNE
                // date (celle de la fin de la phrase). Coupée, la première moitié restait sans date
                // et la seconde sans sujet : la fermeture était perdue. (Fermetures seulement : « devient
                // mixte, ouverte aux marchandises et aux voyageurs » garde sa règle d'ouverture.)
                if ($isEt && ! preg_match('/\b(?:1[89]\d\d|20[0-3]\d)\b/u', $current) && preg_match('/\b(?:fermée?s?|fermeture|supprimée?s?|suppression)\b/u', mb_strtolower($current))
                    && preg_match('/^(?:des|aux|au)\s+(?:(?:trafic|service)\s+(?:des\s+)?)?(?:marchandises|voyageurs|fret)\b/u', mb_strtolower($parts[$i + 1] ?? ''))) {
                    $current .= $parts[$i];

                    continue;
                }
                $clauses[] = $current;
                $current = '';

                continue;
            }
            $current .= $parts[$i];
        }
        $clauses[] = $current;

        return array_values(array_filter(array_map('trim', $clauses), fn ($c) => $c !== ''));
    }

    /**
     * Verbe au présent (« La ligne ferme au service voyageurs le 2 mars 1969 », « Elle ouvre au trafic des
     * voyageurs le 18 octobre 1886 », « ouvre en 1877 ») : retenu seulement suivi de sa date complète,
     * au plus après le trafic. Sans elle, « ferme » et « ouvre » sont trop vagues (« ouvre la voie à »,
     * « s'ouvre », « ferme le 24 août et ne rouvre … le 1 février 1915 », date d'une parenthèse voisine).
     */
    private const PRESENT_VERB_RX = '/^(?:ferme(?:nt)?|ouvr(?:e|ent))\b/u';

    private const PRESENT_VERB_DATE = '(?=\s+(?:(?:au|aux)\s+(?:(?:service|trafic)\s+(?:des\s+)?)?(?:voyageurs|marchandises|fret)\s+)?(?:(?:le|dès le|à partir du)\s+(?:1er|\d{1,2})\s+\p{L}+\s+(?:1[89]\d\d|20[0-3]\d)|en\s+(?:\p{L}+\s+)?(?:1[89]\d\d|20[0-3]\d))\b)';

    /** Clé interne (retirée avant le retour de parseChronology()) des événements des lectures ajoutées. */
    private const ADDED_READING = "\0added";

    /** Rang d'une étape (« le second le 23 juillet 1959 jusqu'à … »). */
    private const STAGE_ORDINAL = '(?:premier|première|second|seconde|deuxième|troisième|quatrième|cinquième|dernier|dernière|suivant|suivante)';

    /** Séparateur entre deux étapes d'une énumération (« , », « et », « , et enfin », « ; », « puis »). */
    private const STAGE_SEP = '(?:,\s+(?:et\s+|puis\s+)?(?:enfin\s+)?|\s+et\s+(?:enfin\s+)?|\s+puis\s+|\s*;\s*)';

    /**
     * Découpe les propositions qui énumèrent des étapes (cf. stagedItems()) et sépare « entre X et Y
     * et entre Z et T » en deux propositions (la date commune est retrouvée par sharedStageDate()).
     *
     * Le premier morceau d'une proposition coupée garde la lecture d'avant (même portion, même date) ;
     * les suivants sont des lectures ajoutées (`added`, cf. ADDED_READING).
     *
     * @param  list<string>  $clauses
     * @param  list<string>  $ends
     * @return list<array{text: string, added: bool}>
     */
    private function splitStagedClauses(array $clauses, array $ends): array
    {
        $out = [];
        foreach ($clauses as $clause) {
            $parts = [];
            foreach ($this->stagedItems($clause, $ends) ?? [$clause] as $item) {
                array_push($parts, ...$this->splitBetweenPairs($item, $ends));
            }
            foreach ($parts as $i => $part) {
                $out[] = ['text' => $part, 'added' => $i > 0];
            }
        }

        return $out;
    }

    /**
     * Étapes d'une proposition qui porte plusieurs dates, chacune avec sa portion :
     *   - portion puis date : « Troyes - Bar-sur-Seine le 20 juillet 1862, Bar-sur-Seine -
     *     Châtillon-sur-Seine le 19 octobre 1868, Châtillon-sur-Seine - Is-sur-Tille le 9 décembre 1882 »,
     *     « entre Sargé et Montoire le 2 janvier 1893, entre Montoire et Château-Renault le 15 octobre
     *     1893 et entre Château-Renault et Vouvray le 29 juillet 1894 » ;
     *   - date puis portion : « fermée au trafic voyageurs le 15 mai 1939 de Pithiviers à
     *     Beaune-la-Rolande, le 4 novembre 1969 d'Étampes à Pithiviers ».
     * Chaque étape devient une proposition, qui hérite du verbe de la première (structure parallèle
     * d'analyze()). Sans cela, une seule portion et une seule date étaient lues par mot-clé.
     *
     * Règle stricte : chaque morceau porte exactement une date et une portion (partielle, de cette
     * ligne), du même côté de la date pour tous, et rien d'autre ne cite de portion hors des morceaux.
     * Sinon (une date sans portion, une portion de trop, un autre trafic énuméré au milieu), la
     * proposition reste entière : mieux vaut une étape de moins qu'une portion datée à tort.
     *
     * @param  list<string>  $ends
     * @return list<string>|null
     */
    private function stagedItems(string $clause, array $ends): ?array
    {
        $all = $this->findDates($clause);
        if (count($all) < 2 || array_filter($all, fn ($d) => $d['precision'] === 'decade')) {
            return null;
        }
        $n = count($all);
        $spans = [];
        foreach ($all as $d) {
            if (! preg_match('/^(?:(?:1er|\d{1,2})\s*(?:er)?\s+)?(?:\p{L}+\s+)?\d{4}/u', mb_substr($clause, $d['pos']), $m)) {
                return null;
            }
            $spans[] = [$d['pos'], $d['pos'] + mb_strlen($m[0])];
        }
        $len = mb_strlen($clause);

        // Portion puis date : coupe juste après chaque date (sauf la dernière), sur un séparateur.
        $cutsAfter = [];
        for ($i = 0; $i < $n - 1; $i++) {
            $rest = mb_substr($clause, $spans[$i][1], $spans[$i + 1][0] - $spans[$i][1]);
            if (! preg_match('/^(\s*\([^()]{0,80}\))?('.self::STAGE_SEP.')/u', $rest, $m)) {
                $cutsAfter = null;
                break;
            }
            $start = $spans[$i][1] + mb_strlen($m[1]);
            $cutsAfter[] = [$start, $start + mb_strlen($m[2])];
        }
        // Date puis portion : coupe juste avant chaque date (sauf la première), sur un séparateur suivi
        // au plus d'un « le », « en », « depuis le »...
        $cutsBefore = [];
        for ($i = 1; $i < $n; $i++) {
            $head = mb_substr($clause, $spans[$i - 1][1], $spans[$i][0] - $spans[$i - 1][1]);
            // « , le second le 23 juillet 1959 jusqu'à Don - Sainghin », « , le 04 novembre 1969 d'Étampes à Pithiviers ».
            if (! preg_match('/('.self::STAGE_SEP.')((?:(?:le|la)\s+'.self::STAGE_ORDINAL.'\s+)?(?:(?:depuis|dès|à partir d[ue]|à compter d[ue])\s+)?(?:le|les|en|au|du)\s+(?:0\d\s+)?)?$/u', $head, $m, PREG_OFFSET_CAPTURE)) {
                $cutsBefore = null;
                break;
            }
            $start = $spans[$i - 1][1] + mb_strlen(substr($head, 0, $m[1][1]));
            $cutsBefore[] = [$start, $start + mb_strlen($m[1][0])];
        }

        $found = null;
        foreach (['after' => $cutsAfter, 'before' => $cutsBefore] as $order => $cuts) {
            if ($cuts === null) {
                continue;
            }
            $items = [];
            $from = 0;
            foreach ([...$cuts, [$len, $len]] as $i => [$cutStart, $cutEnd]) {
                $text = mb_substr($clause, $from, $cutStart - $from);
                $dateAt = $spans[$i][0] - $from;
                $sec = $this->findSection($text, $ends);
                if (! $sec || $sec['other_line'] || $sec['whole_line'] || ($sec['only_to'] && $i === 0) || $sec['quote'] === '') {
                    continue 2;
                }
                $secAt = mb_strpos($text, $sec['quote']);
                if ($secAt === false) {
                    continue 2;
                }
                // La portion du bon côté de la date, et aucune autre de l'autre côté.
                $other = $order === 'after' ? mb_substr($text, $dateAt) : mb_substr($text, 0, $dateAt);
                if (($order === 'after' ? $secAt >= $dateAt : $secAt < $dateAt) || $this->findSection($other, $ends) !== null) {
                    continue 2;
                }
                if ($i > 0 && ! $this->isBareStage($text, $sec['quote'], $secAt, $dateAt, $spans[$i][1] - $spans[$i][0]) && ! $this->detectTypes($text, 'prose')) {
                    // « La portion A - B ayant été fermée en 1990, seule la portion C - D est exploitée en 2005 » :
                    // un autre verbe, sans mot-clé à lui. Ce n'est pas une étape de la même énumération.
                    continue 2;
                }
                $items[] = trim($text);
                $from = $cutEnd;
            }
            if ($found !== null) {
                return null; // les deux lectures tiennent : ambigu
            }
            $found = $items;
        }

        return $found;
    }

    /**
     * « Les sections de Chaussin à Pleure et de Pleure à Bletterans sont fermées respectivement le 1 mars
     * 1969 et le 2 juin 1971 » : autant de portions avant « respectivement » que de dates après, appariées
     * dans l'ordre. Rend une proposition par portion : la première garde le verbe (« Les sections de
     * Chaussin à Pleure et de Pleure à Bletterans sont fermées le 1 mars 1969 », où findSection() lit la
     * première portion), les suivantes (« de Pleure à Bletterans le 2 juin 1971 ») en héritent.
     * Nombres différents, date avant « respectivement », portion après, ou rien d'autre que des dates et
     * des mots de liaison après : null, la phrase suit la lecture ordinaire.
     *
     * @param  list<string>  $ends
     * @return list<string>|null
     */
    private function respectiveStages(string $sentence, array $ends): ?array
    {
        if (! preg_match('/^(?<pre>.*?)[\s,]*\brespectivement\b[\s,]*(?<post>.*)$/u', $sentence, $m)) {
            return null;
        }
        $pre = $m['pre'];
        $post = $m['post'];
        $dates = $this->findDates($post);
        if (count($dates) < 2 || $this->findDates($pre) || $this->findSection($post, $ends) !== null
            || array_filter($dates, fn ($d) => $d['precision'] === 'decade') || ! $this->detectTypes($pre, 'prose')) {
            return null;
        }
        // Les dates : seules, séparées par « , », « et », « puis », précédées au plus de « le », « en »...
        $texts = [];
        $from = 0;
        foreach ($dates as $d) {
            $gap = mb_substr($post, $from, $d['pos'] - $from);
            if (! preg_match('/^(?:\s*(?:,|et|puis|le|les|en|au|du|dès|depuis|à partir d[ue]))*\s*$/u', $gap)
                || ! preg_match('/^(?:(?:1er|\d{1,2})\s*(?:er)?\s+)?(?:\p{L}+\s+)?\d{4}/u', mb_substr($post, $d['pos']), $dm)) {
                return null;
            }
            $texts[] = trim($gap.' '.$dm[0], ' ,');
            $from = $d['pos'] + mb_strlen($dm[0]);
        }
        // Les portions, dans l'ordre du texte.
        $quotes = [];
        $rest = $pre;
        while (($sec = $this->findSection($rest, $ends)) !== null && $sec['quote'] !== '' && ($at = mb_strpos($rest, $sec['quote'])) !== false) {
            if ($sec['other_line'] || $sec['whole_line'] || $sec['only_to']) {
                return null;
            }
            $quotes[] = $sec['quote'];
            $rest = mb_substr($rest, $at + mb_strlen($sec['quote']));
        }
        if (count($quotes) !== count($texts)) {
            return null;
        }
        $clauses = [trim($pre).' '.$texts[0]];
        for ($i = 1; $i < count($quotes); $i++) {
            $clauses[] = $quotes[$i].' '.$texts[$i];
        }

        return $clauses;
    }

    /**
     * Étape « nue » d'une énumération : sa portion, sa date et des mots de liaison seulement (« le 8 avril
     * 1955 entre La Châtaigneraie et Fontenay-le-Comte », « Bar-sur-Seine - Châtillon-sur-Seine le 19
     * octobre 1868 », « suivi par celui de Port-de-Piles au Blanc, le 27 juin 1940 », « prolongé jusqu'à
     * Grenoble le 1 juillet 1858 »). Ce qui suit la
     * dernière des deux (portion ou date) est la fin de la phrase et n'est pas lu ; une parenthèse non plus.
     */
    private function isBareStage(string $text, string $quote, int $secAt, int $dateAt, int $dateLen): bool
    {
        $secEnd = $secAt + mb_strlen($quote);
        $core = $secAt < $dateAt
            ? mb_substr($text, 0, $secAt).' '.mb_substr($text, $secEnd, $dateAt - $secEnd)
            : mb_substr($text, 0, $dateAt).' '.mb_substr($text, $dateAt + $dateLen, max(0, $secAt - $dateAt - $dateLen));
        $core = (string) preg_replace('/\([^()]*\)/u', ' ', mb_strtolower($core));
        $words = preg_split('/[^\p{L}]+/u', $core, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return ! array_diff($words, ['le', 'la', 'les', 'l', 'en', 'au', 'aux', 'du', 'de', 'd', 'des', 'depuis', 'dès', 'à', 'partir',
            'compter', 'pour', 'sur', 'puis', 'et', 'enfin', 'ensuite', 'section', 'sections', 'tronçon', 'tronçons', 'partie',
            'portion', 'ligne', 'celle', 'celui', 'ceux', 'celles', 'cette', 'ce', 'par', 'suivi', 'suivie', 'suivis', 'suivies',
            'prolongé', 'prolongée', 'prolongés', 'prolongées', 'premier', 'première', 'second', 'seconde', 'deuxième', 'troisième',
            'quatrième', 'cinquième', 'dernier', 'dernière', 'suivant', 'suivante']);
    }

    /**
     * « … cesse entre Villars et Poinson et entre Mirebeau-sur-Bèze et Champagne-sur-Vingeanne le 30 mai
     * 1975 » : deux portions « entre X et Y » coordonnées, coupées en deux propositions.
     *
     * @param  list<string>  $ends
     * @return list<string>
     */
    private function splitBetweenPairs(string $clause, array $ends): array
    {
        if (! preg_match('/^(.*?\bentre\s+[^,;]+?\s+et\s+[^,;]+?)(?:,\s+|\s+)et\s+(entre\s+\p{Lu}.*)$/u', $clause, $m)) {
            return [$clause];
        }
        if (substr_count($m[1], '(') !== substr_count($m[1], ')')) {
            return [$clause]; // « (notamment entre X et Y et entre Z et T) » : un exemple entre parenthèses, pas l'objet du verbe
        }
        if ($this->findDates($m[1]) || ! $this->detectTypes($m[1], 'prose')) {
            // Date avant les portions (« le 22 avril 1959 entre A et B et entre C et D ») : elle n'est pas
            // commune à coup sûr (sharedStageDate() ne lit que la date finale), la proposition reste entière.
            // « Le service voyageurs entre A et B … et entre E et F cesse » : le verbe suit, il vaut pour toutes.
            return [$clause];
        }
        $a = $this->findSection($m[1], $ends);
        $b = $this->findSection($m[2], $ends);
        if (! $a || ! $b || $a['other_line'] || $b['other_line'] || $a['whole_line'] || $b['whole_line']) {
            return [$clause];
        }

        return [$m[1], ...$this->splitBetweenPairs($m[2], $ends)];
    }

    /**
     * Date commune d'une énumération de portions sans date propre (« fermée de X à Y et de Z à T le
     * 1er mars 1950 », « entre X et Y et entre Z et T le 30 mai 1975 ») : celle de la dernière portion
     * énumérée. Chaque proposition suivante doit n'être qu'une portion (« de Z à T », « entre Z et T »),
     * sans mot-clé à elle ; la dernière porte une seule date, placée après sa portion, et rien après.
     *
     * @param  list<string>  $clauses
     * @param  list<string>  $ends
     * @return array{date: string, year: int, precision: string}|null
     */
    private function sharedStageDate(array $clauses, int $index, array $ends, string $mode): ?array
    {
        for ($j = $index + 1; $j < count($clauses); $j++) {
            $c = $clauses[$j];
            if (! preg_match('/^(?:et\s+)?(?:entre|de|du|des|d[\'’])\s*\p{Lu}/u', $c) || $this->detectTypes($c, $mode)) {
                return null;
            }
            $sec = $this->findSection($c, $ends);
            if (! $sec || $sec['other_line'] || $sec['whole_line'] || $sec['only_to'] || $sec['quote'] === '') {
                return null;
            }
            $dates = $this->findDates($c);
            if (! $dates) {
                continue;
            }
            $at = mb_strpos($c, $sec['quote']);
            if (count($dates) !== 1 || $dates[0]['precision'] === 'decade' || $at === false || $dates[0]['pos'] < $at + mb_strlen($sec['quote'])
                || ! preg_match('/^(?:(?:1er|\d{1,2})\s*(?:er)?\s+)?(?:\p{L}+\s+)?\d{4}\s*(?:\([^()]{0,80}\))?\s*[.,;]?\s*$/u', mb_substr($c, $dates[0]['pos']))) {
                return null;
            }

            return ['date' => $dates[0]['date'], 'year' => $dates[0]['year'], 'precision' => $dates[0]['precision']];
        }

        return null;
    }

    /**
     * @return list<array{pos: int, date: string, year: int, precision: string}>
     */
    public function findDates(string $s): array
    {
        $months = implode('|', array_keys(self::MONTHS));
        $found = [];
        $mask = $s;
        if (preg_match_all('/\b(1er|[12]?\d|3[01])\s*(?:er)?\s+('.$months.')\s+(1[89]\d\d|20[0-3]\d)\b/iu', $s, $mm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                $day = $m[1][0] === '1er' ? 1 : (int) $m[1][0];
                $month = self::MONTHS[mb_strtolower($m[2][0])];
                $year = (int) $m[3][0];
                if (! checkdate($month, $day, $year)) {
                    continue;
                }
                $found[] = ['pos' => $m[0][1], 'date' => sprintf('%04d-%02d-%02d', $year, $month, $day), 'year' => $year, 'precision' => 'day'];
                $mask = substr_replace($mask, str_repeat('#', strlen($m[0][0])), $m[0][1], strlen($m[0][0]));
            }
        }
        if (preg_match_all('/\b('.$months.')\s+(1[89]\d\d|20[0-3]\d)\b/iu', $mask, $mm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                $year = (int) $m[2][0];
                $found[] = ['pos' => $m[0][1], 'date' => sprintf('%04d-%02d-01', $year, self::MONTHS[mb_strtolower($m[1][0])]), 'year' => $year, 'precision' => 'month'];
                $mask = substr_replace($mask, str_repeat('#', strlen($m[0][0])), $m[0][1], strlen($m[0][0]));
            }
        }
        if (preg_match_all('/(?<!\d)(?<!\d[,.])(?<![\/#-])(?<!PK )(?<!n° )(1[89]\d\d|20[0-3]\d)(?!\d|[,.]\d|\/|\s*(?:km|m\b|mm|v\b|volts|kv|hab|voyageurs|tonnes|t\b|€|euros|f\b|francs|places|mètres|trains|kw|ch\b))/iu', $mask, $mm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                // « années 1930 », « 1938-1939 » (plage) : on garde la première année.
                $before = substr($mask, max(0, $m[0][1] - 8), min(8, $m[0][1]));
                if (preg_match('/années?\s*$/iu', $before, $word, PREG_OFFSET_CAPTURE)) {
                    // « au cours des années 1970 » : décennie, inutilisable comme date
                    // mais elle « capte » le mot-clé voisin (cf. nearestDate).
                    $found[] = ['pos' => $m[0][1] - strlen($before) + $word[0][1], 'date' => sprintf('%04d-01-01', (int) $m[1][0]), 'year' => (int) $m[1][0], 'precision' => 'decade'];

                    continue;
                }
                $year = (int) $m[1][0];
                $found[] = ['pos' => $m[0][1], 'date' => sprintf('%04d-01-01', $year), 'year' => $year, 'precision' => 'year'];
            }
        }
        foreach ($found as &$f) {
            $f['pos'] = mb_strlen(substr($s, 0, $f['pos'])); // octets → caractères
        }
        unset($f);
        usort($found, fn ($a, $b) => $a['pos'] <=> $b['pos']);

        return $found;
    }

    /**
     * Dates candidates d'un mot-clé quand la proposition en porte plusieurs, chacun suivi de
     * sa date (« La ligne a été ouverte le 4 août 1870 et fermée au service des voyageurs le
     * 6 mars 1939 », « mise en service en 1879 et fut fermée au trafic voyageurs en 1990 ») :
     * une date placée entre le mot-clé précédent et celui-ci appartient au précédent. Elle
     * n'est pas offerte à ce mot-clé s'il a une date après lui ; sans cela, la date la plus
     * proche (celle de l'ouverture, juste avant « fermée ») datait la fermeture du jour de
     * l'ouverture (333000, 517000, 578000 : fermée aux voyageurs le jour de sa mise en service).
     *
     * Proposition coordonnée à un AUTRE sujet (NEW_SUBJECT_RX) entre ce mot-clé et le suivant : les
     * dates qui suivent le mot-clé suivant appartiennent à cet autre sujet (« elle est déclassée et une
     * ligne de Brioude à Saint-Flour est déclarée d'utilité publique par une loi le 7 juillet 1905 » :
     * le projet antérieur est déclassé, pas la ligne, et pas le 07/07/1905).
     *
     * @param  list<array{pos: int}>  $dates
     * @param  list<int>  $keywords  positions des mots-clés de la proposition
     * @param  string  $lc  la proposition en minuscules (positions en caractères)
     * @return list<array{pos: int}>
     */
    private function datesOfKeyword(array $dates, int $pos, array $keywords, string $lc = ''): array
    {
        $following = array_filter($keywords, fn ($k) => $k > $pos);
        if ($following && $lc !== '' && preg_match(self::NEW_SUBJECT_RX, mb_substr($lc, $pos, min($following) - $pos))) {
            $next = min($following);
            $dates = array_values(array_filter($dates, fn ($d) => $d['pos'] < $next));
        }
        $previous = array_filter($keywords, fn ($k) => $k < $pos);
        if (! $previous || ! array_filter($dates, fn ($d) => $d['pos'] > $pos)) {
            return $dates;
        }
        $previous = max($previous);

        return array_values(array_filter($dates, fn ($d) => $d['pos'] < $previous || $d['pos'] > $pos));
    }

    private function nearestDate(array $dates, int $pos, ?int $maxDist = null): ?array
    {
        $best = null;
        foreach ($dates as $d) {
            // Une date APRÈS le mot-clé (« fermée le 12 mai 1938 ») est légèrement préférée.
            $dist = $d['pos'] >= $pos ? $d['pos'] - $pos : ($pos - $d['pos']) * 1.3;
            if ($d['precision'] === 'decade') {
                $dist *= 0.3;
            }
            if ($best === null || $dist < $best[0]) {
                $best = [$dist, $d];
            }
        }
        if ($best && $maxDist !== null && $best[0] > $maxDist) {
            return null;
        } // date trop éloignée du verbe
        if ($best && $best[1]['precision'] === 'decade') {
            return null;
        }

        return $best[1] ?? null;
    }

    /**
     * Repère les mots-clés d'événements dans une proposition.
     *
     * @return list<array{type: string, pos: int, label?: string}>
     */
    private function detectTypes(string $clause, string $mode): array
    {
        $lc = mb_strtolower($clause);
        $types = [];
        // Positions en CARACTÈRES (les fenêtres sont découpées avec mb_substr).
        $hit = function (string $rx) use ($lc): ?int {
            return preg_match($rx, $lc, $m, PREG_OFFSET_CAPTURE) ? mb_strlen(substr($lc, 0, $m[0][1])) : null;
        };
        $before = fn (int $pos, int $n = 25) => mb_substr($lc, max(0, $pos - $n), min($n, $pos));
        // « après la fermeture… », « lors de l'ouverture… » : repère temporel,
        // la date de la phrase porte sur autre chose.
        $anchor = fn (int $pos) => (bool) preg_match('/(après|lors de|depuis|avant|jusqu[\'’]à|en attendant|à l[\'’]occasion de|dès)\s+(la |l[\'’]|sa |son |leur )?$/u', $before($pos, 30));
        $negated = fn (int $pos) => (bool) preg_match('/\bnon\s*-?\s*$|\bjamais\s+(été\s+)?$|\bpas\s+(été\s+)?$/u', $before($pos, 14));
        // « s'est exprimé sur la réouverture », « engagé pour la réouverture », « dossier de
        // réouverture », « dans la perspective d'une réouverture » : sujet de débat, pas un fait.
        $topic = fn (int $pos) => (bool) preg_match('/\b(?:sur|pour|en faveur d[eu]|contre|dossiers? d[eu]|projets? d[eu]|perspective d[\'’]une?|études? d[eu]|en vue d[eu]|cadre d[eu])\s+(?:la |l[\'’]|une |sa |son |leur )?$/u', $before($pos, 30));
        // « 4 engins AMG 800 sont remis en service » : le matériel, pas la ligne.
        $material = fn (int $pos) => (bool) preg_match('/\b(?:engins?|autorails?|automotrices?|rames?|locomotives?|locotracteurs?|voitures?|wagons?|matériels?|draisines?)\b[^.;,]{0,30}$/u', $before($pos, 40));
        // « la ⟦ligne de la grande ceinture de Paris⟧ dont le tronçon occidental ouvre le 4 septembre 1882 » : le
        // verbe au présent a pour sujet une autre ligne (lien vers son article) citée juste avant.
        $linkedSubject = fn (int $pos) => preg_match(self::PRESENT_VERB_RX, mb_substr($lc, $pos, 8))
            && preg_match('/⟦[^⟦⟧]*⟧[^.;⟦⟧]{0,60}$/u', $before($pos, 140));
        $this->currentClause = $clause;

        // Dépose : exige un contexte voie / ligne (« plainte déposée » n'en est pas une).
        if (($p = $hit('/\b(dépos(?:e|ée|ées|é|és)|démont(?:age|ée?s?)|démantèlement|démantelée?s?|arrach(?:age|ée?s?)|ferraill\w*|enlèvement des (?:voies|rails))\b/u')) !== null
            && preg_match('/\b(voies?|rails?|ligne|section|tronçon|partie|portion|emprise|infrastructure)\b/u', $lc)
            && ! preg_match('/^dépose d[\'’]un (pont|ouvrage|tablier)/u', mb_substr($lc, $p, 30)) && ! $negated($p)
            // Verbe « déposer » (« la Compagnie … qui dépose une concession pour la création d'une ligne ») :
            // une démarche administrative, pas la dépose des voies (Saintes - Royan « dépose » du 09/05/1867).
            && ! preg_match('/^dépos\w*\s+(?:une?|des|sa|son|ses|leur|leurs|le|la|les)\s+(?:demande|concession|dossier|projet|requête|recours|plainte|brevet|candidature|offre|proposition|amendement|bilan|statuts?|avant-projet)/u', mb_substr($lc, $p, 60))
            && ! preg_match('/\b(?:qui|il|elle|on|ils|elles)\s+$/u', $before($p, 12))) {
            $types[] = ['type' => 'dismantling', 'pos' => $p];
        }
        if (($p = $hit('/\b(déclass(?:ement|ée?s?)|retranch(?:ement|ée?s?)|radiée? du|rayée?s? de la nomenclature)/u')) !== null && ! $negated($p)
            && ! preg_match('/^\S+(?:\s+\S+){0,3}\s+(?:sont|est)\s+(?:les\s+)?suivant|ci-dessous|ci-après/u', mb_substr($lc, $p, 60))) {
            $types[] = ['type' => 'declassification', 'pos' => $p];
        }
        if (($p = $hit('/(?<!dés)(?<!dé)\b(électrifi(?:cation|ée?s?)|mise sous tension)/u')) !== null && ! str_contains($lc, 'désélectrif')
            && ! $negated($p) && ! ($mode === 'prose' && $anchor($p))) {
            $types[] = ['type' => 'electrification', 'pos' => $p];
        }
        if (($p = $hit('/\bdésélectrifi\w*/u')) !== null) {
            $types[] = ['type' => 'other', 'pos' => $p, 'label' => 'Désélectrification'];
        }
        $reopen = $hit('/\b(réouverture|ré-ouverture|rouverte?s?|réouverte?s?|remise?s? en (?:service|exploitation|circulation)|reprise du (?:trafic|service))\b/u');
        if ($reopen !== null && ($negated($reopen) || ($mode === 'prose' && $anchor($reopen)) || $topic($reopen) || $material($reopen))) {
            $reopen = null;
        }
        if ($reopen !== null) {
            $types[] = ['type' => $this->qualify('reopening', $lc, $reopen), 'pos' => $reopen];
        }
        $close = $hit('/\b(fermeture|fermée?s?|ferme(?:nt)?'.self::PRESENT_VERB_DATE.'|neutralis(?:ation|ée?s?)|suppression (?:du|des) (?:service|trafic)(?:\s+(?:des\s+)?(?:voyageurs|marchandises|fret))?|(?<=service |trafic |voyageurs |marchandises |fret )(?:(?:est|a été|fut|sont|ont été)\s+)?supprimée?s?|cess(?:e|ent|ation)|abandon(?:née?s?)?|dernier train|derniers trains|désaffect(?:ation|ée?s?)|(?:transf[ée]r(?:é|ée|ement)|report(?:é|ée)|basculée?) sur (?:la )?route|remplacée? par (?:des |un service d[\'’])?(?:auto)?cars?|fin de l[\'’]exploitation|plus (?:aucun|de) (?:trafic|train|service|circulation)|n[\'’](?:est|était) plus (?:exploitée?|utilisée?|parcourue?|circulée?|desservie?)|plus exploitée?|renon(?:ce|cent|ça)(?=[^.;]{0,40}\b(?:au|à)\s+(?:trafic|service|l[\'’]exploitation)))\b/u');
        if ($close !== null && preg_match('/^\S+\s+depuis\s+(?!le\b|l[\'’]|\d|1er|janvier|février|mars|avril|mai|juin|juillet|août|septembre|octobre|novembre|décembre)/u', mb_substr($lc, $close, 40))) {
            $close = null; // « section fermée depuis plusieurs années » : état, pas événement
        }
        if ($close !== null && preg_match('/^abandon/u', mb_substr($lc, $close, 8)) && ! preg_match('/\b(ligne|section|tronçon|voie|exploitation)\b/u', $lc)) {
            $close = null; // « téléphérique abandonné »
        }
        if ($close !== null && preg_match('/travaux|chantier|temporaire|provisoire|momentané|interruption|pour une durée|pendant (?:toute )?la durée|pendant \d|pendant (?:plusieurs|quelques|de (?:nombreux|longs)|deux|trois|quatre|cinq|six|huit|dix|quinze)\s+(?:jours?|semaines?|mois|heures?|nuits?)|le temps de|intempéries|crue|éboulement|glissement de terrain|tempête|inondation|rénovation|régénération|pour cause de|saison|période|hivern|estiv|automn|déraill\w*|accident|collision|\bdu \d{1,2}(?:er)? \p{L}+ (?:\d{4} )?au \d/u', $lc)) {
            $close = null; // fermeture temporaire (travaux, aléa, déraillement « pendant plusieurs jours ») : la ligne reste ouverte sur la décennie
        }
        if ($close !== null && preg_match('/\b(?:risques? (?:de|d[\'’]une?)\s+(?:fermeture|réduction|suppression|disparition|neutralisation)|menacée?s?|menaces?|prévue?s?|envisagée?s?|envisag(?:e|ent|eait|eaient)|programmée?s?|devrai(?:t|ent)|pourrai(?:t|ent)|à l[\'’]horizon|d[\'’]ici (?:à |la fin de )?\d{4}|crain(?:t|ent|te|tes)|redout\w*)\b/u', $lc)
            && ! preg_match('/\b(?:effective|effectivement|a finalement lieu|intervient le|est prononcée)\b/u', $lc)) {
            $close = null; // « un risque … et une fermeture pour 2025 », « la CGT craint une fermeture » : prévision, menace, pas un fait
        }
        if ($close !== null && preg_match('/^\S+(?:\s+\S+){0,4}?\s+pour\s+(?:l[\'’]année\s+|la fin\s+(?:de\s+)?|fin\s+)?\d{4}\b/u', mb_substr($lc, $close, 70))) {
            $close = null; // « une fermeture pour 2025 » : échéance annoncée
        }
        if ($close !== null && preg_match('/\b(?:documentaire|film|reportage|livre|roman|émission|album|chanson|bande dessinée)\b/u', $lc)) {
            $close = null; // « la fermeture de la ligne fait l'objet du documentaire Voyage à Bessèges » : mention, pas un événement
        }
        if ($close !== null && ($negated($close) || ($mode === 'prose' && $anchor($close)) || $linkedSubject($close))) {
            $close = null;
        }
        if ($close !== null && preg_match('/^\S+(?:\s+\S+){0,3}\s+que\s+(?:par|pour|au|aux)\b/u', mb_substr($lc, $close, 60))) {
            $close = null; // « n'est plus utilisée que par des trains de marchandises » : restriction, pas fermeture
        }
        if ($close !== null && preg_match('/^(fermeture|abandon)/u', mb_substr($lc, $close, 10))) {
            $kwc = preg_match('/^\S+/u', mb_substr($lc, $close, 20), $kk) ? $kk[0] : '';
            if ($this->badObject($lc, $close, $kwc)) {
                $close = null;
            } // « fermeture des mines », « de 6 700 km de lignes »
        }
        if ($close !== null && preg_match('/^derniers? trains? (?:(?:assurée?s?|tractée?s?|remorquée?s?|effectuée?s?) )?(?:avec|par|en|à) (?:une |des |la )?(?:locomotives? |traction |machines? )?(?:à )?vapeur/u', mb_substr($lc, $close, 90))) {
            $close = null; // « dernier train assuré avec une locomotive à vapeur » : fin de la vapeur, pas de la ligne
        }
        if ($close !== null && preg_match('/^plus (?:aucun|de) trains?\b[^.;]{0,80}\bde bout en bout\b/u', mb_substr($lc, $close, 130))) {
            $close = null; // « plus aucun train ne parcourt la ligne de bout en bout » : fin des trains directs
        }
        if ($close !== null && (preg_match('/\b(?:sur|contre|en faveur d[eu]|projets? d[eu])\s+(?:la |l[\'’]|une |sa |son |leur )?$/u', $before($close, 30))
            || preg_match('/\b(?:délibèr\w*|apprend\w*)\b/u', $before($close, 90)))) {
            $close = null; // « le conseil municipal délibère sur la suppression du service » : débat, pas un fait
        }
        // « La ligne ferme au trafic voyageurs le 15 mai 1938, à l'exception d'un train … qui perdure jusqu'en
        // 1953 » : le trafic qui survit dit la vraie fin ; le verbe au présent ne la masque pas.
        $present = $close !== null && preg_match(self::PRESENT_VERB_RX, mb_substr($lc, $close, 8));
        if (($close === null || $present) && ($p = $hit('/\b(?:perdur\w*|subsist\w*|se maint(?:ient|iennent|int|inrent|enait|enaient)|continu(?:e|ent|a|èrent|ait|aient)|demeur(?:e|ent|a|èrent|ait|aient))\b(?=[^.;]{0,140}\bjusqu[\'’](?:au|à|en)\s+(?:(?:1er|\d{1,2})\s+\p{L}+\s+)?(?:1[89]\d\d|20[0-3]\d)\b)/u')) !== null
            && ($q = $this->trafficQualifier($lc)) !== null && ! $negated($p)) {
            // Un trafic qui SURVIT jusqu'à une date (« alors que le trafic marchandise perdura entre
            // Pontarlier et les Hôpitaux-Neufs jusqu'au 3 novembre 1969 », 876000) : la date est la
            // FIN de ce trafic. La proposition porte son propre événement : elle n'hérite pas de celui
            // de la proposition précédente (« la ligne fut démontée entre 1942 et 1943 »), qui en
            // faisait une fausse dépose de la portion.
            $types[] = ['type' => match ($q) {
                'passenger' => 'closure_passenger', 'freight' => 'closure_freight', default => 'closure'
            }, 'pos' => $p];
            $close = null;
        }
        if ($close !== null && ! preg_match('/\b(ouverte|rouverte|maintenue|conservée) (?:au|aux)\b.*\bjusqu/u', $lc)) {
            $type = $this->qualify('closure', $lc, $close);
            if ($type === 'closure' && preg_match('/route|cars?\b|autocars?|dernier train/u', mb_substr($lc, $close, 60))) {
                $type = 'closure_passenger'; // report sur route = service voyageurs
            }
            if ($type === 'closure' && preg_match('/^(?:[^,]{0,40},\s*)?(?:le|la) (?:service|trafic|desserte) (?:des |de |aux )?(voyageurs|marchandises|fret)\b(?!\s+et\s+(?:des\s+|de\s+|aux\s+)?(?:voyageurs|marchandises|fret))/u', $lc, $sm)) {
                // « Le service voyageurs entre X et Y, entre Z et T … cesse à ce moment » : le sujet dit le trafic,
                // sauf si un autre sujet s'intercale (« le service voyageurs est interrompu en 1939, la ligne est fermée en 1951 »).
                $between = mb_substr($lc, mb_strlen($sm[0]), max(0, $close - mb_strlen($sm[0])));
                if (! preg_match('/\b(?:la|cette) ligne\b|\b(?:elle|il)\b/u', $between)) {
                    $type = $sm[1] === 'voyageurs' ? 'closure_passenger' : 'closure_freight';
                }
            }
            $t = ['type' => $type, 'pos' => $close];
            // Desserte omnibus seule (« la desserte omnibus est supprimée », « fermeture au service des
            // voyageurs omnibus ») : d'autres trains de voyageurs peuvent subsister, cf. analyze().
            $around = mb_substr($lc, max(0, $close - 60), 140);
            if ($type !== 'closure_freight' && preg_match('/\b(?:desserte|service|trafic|trains?|circulations?|relations?|voyageurs|autorails?)\s+(?:des\s+voyageurs\s+|de\s+voyageurs\s+)?omnibus\b|\bles omnibus\b/u', $around, $om)) {
                // « desserte / service omnibus » : réduction ; « trains / autorails omnibus » : seulement si
                // d'autres trains de voyageurs subsistent (sinon c'était toute la desserte).
                $t['partial'] = preg_match('/^(?:desserte|service|trafic|voyageurs)/u', $om[0]) ? 'service' : 'signal';
            }
            $types[] = $t;
        }
        $hasConcession = (bool) preg_match('/\b(concédée?s?|concession|utilité publique)\b/u', $lc);
        // « Les premiers trains commencent à circuler entre Ajaccio et Bocognano le 1er décembre 1888 ».
        if (preg_match('/\b(ouverture|ouverte?s?|(?<!s[\'’])ouvr(?:e|ent)'.self::PRESENT_VERB_DATE.'|mise?s? en (?:service|exploitation)|inaugur(?:ation|ée?s?)|livrée?s? à l[\'’]exploitation|prolongement|prolongée?s?|livrée?s? (?:au trafic|à la circulation)|premiers trains (?:de voyageurs |réguliers |commerciaux )?(?:commencent à |commencèrent à )?circul\w*)\b/u', $lc, $om, PREG_OFFSET_CAPTURE)) {
            $open = mb_strlen(substr($lc, 0, $om[0][1]));
            $kw = $om[1][0];
        } else {
            $open = null;
            $kw = '';
        }
        if ($open !== null && str_starts_with($kw, 'prolong') && ($hasConcession
            || (! preg_match('/\b(ligne|section|tronçon|voie|jusqu|vers)\b/u', $lc) && ! preg_match('/\b(de|d[\'’]|à)\s*\p{Lu}/u', $clause))
            || preg_match('/\b(service|trains?|desserte|relations?|ter|tgv|missions?|navettes?)\b[^.;]{0,25}$/u', $before($open, 40)))) {
            $open = null; // « le prolongement … est concédé », « le service est prolongé »
        }
        if ($open !== null && ($anchor($open) || $negated($open) || $linkedSubject($open))) {
            $open = null; // « jusqu'à la mise en service du RER », « depuis l'ouverture »
        }
        if ($open !== null && ($topic($open) || (str_starts_with($kw, 'mis') && $material($open)))) {
            $open = null; // « pour l'ouverture de la ligne », « les autorails sont mis en service »
        }
        if ($open !== null && str_starts_with($kw, 'mis')
            && preg_match('/^\s+(?:sur|entre)\s+(?:la|cette|toute la)\s+(?:ligne|relation)\b/u', mb_substr($lc, $open + mb_strlen($kw), 30))) {
            $open = null; // « Ils sont mis en service sur la ligne Nice-Digne en 1935 » : du matériel posé SUR la ligne
        }
        if ($open !== null && preg_match('/(restée?s?|reste|restent|demeurée?s?|demeure|maintenue?s?|toujours|encore|restant|demeurant)(?:\s+(?:cependant|toutefois|néanmoins|pourtant|donc|alors|encore|toujours|officiellement|administrativement|juridiquement))*\s+$/u', $before($open, 45))) {
            $open = null; // « restée ouverte aux marchandises », « restant cependant ouvert administrativement » : état
        }
        if ($open !== null && preg_match('/^ouverte?s?\s+(?:administrativement|juridiquement)\b/u', mb_substr($lc, $open, 40))) {
            $open = null; // « ouvert administrativement » : état juridique, pas une ouverture au trafic
        }
        if ($open !== null && preg_match('/\b(?:était|étaient|est restée?|sont restées?)\s+(?:encore\s+|toujours\s+)?ouverte?s?\b[^.;]*\bjusqu/u', $lc)) {
            $open = null; // « La ligne était ouverte au trafic voyageurs … jusqu'au 6 juillet 2012 » : état, la date est une fin
        }
        if ($open !== null && preg_match('/piétons|cycl|voie verte|promenade|coulée verte|randonn|vélo|musée|draisine|vélorail/u', $lc)) {
            $open = null; // réaffectation de l'emprise, pas une ouverture ferroviaire
        }
        if ($open !== null && str_starts_with($kw, 'mis') && preg_match('/^mise?s? en (?:service|exploitation) techniques?\b/u', mb_substr($lc, $open, 40))) {
            $open = null; // « mise en service technique » : essais et équipements, pas l'ouverture commerciale
        }
        if ($open !== null && str_starts_with($kw, 'mis') && preg_match('/\b('.self::EQUIPMENT_RX.'|électrification|caténaire|tram-train|tgv|ter|rer|desserte|service)\b[^.;]{0,100}$/u', $before($open, 120), $eq)) {
            if (preg_match('/^(?:'.self::EQUIPMENT_RX.')$/u', $eq[1])) {
                $types[] = ['type' => 'modernization', 'pos' => $open];
            }
            $open = null; // « le BAL est mis en service », « l'électrification … est mise en service »
        }
        if ($open !== null && preg_match('/(deuxième|seconde|troisième|quatrième|nouvelle|nouvelles|double) voies?\s+(?:a|ont|est|sont)?\s*(?:été\s+)?$/u', $before($open, 40))) {
            $types[] = ['type' => 'modernization', 'pos' => $open];
            $open = null;
        }
        if ($open !== null && $reopen === null) {
            $window = mb_substr($lc, $open, 90);
            if (preg_match('/\b('.self::EQUIPMENT_RX.'|double voie|doublement|tram-train|tgv|ter|rer|métro|tramway|tram|autorails?|automotrices?|matériel|rames?|locomotives?|desserte|cadencement|service annuel|bus|autocars?|navettes?|gare|halte|arrêt|passage|pont|viaduc|tunnel|raccordement|électrification|plateforme|terminal|téléphérique)\b/u', $window, $mw)
                && ! preg_match('/\b(ligne|section|tronçon)\b/u', mb_substr($window, 0, max(0, mb_strpos($window, $mw[1]))))) {
                if (preg_match('/\b(double voie|doublement|'.self::EQUIPMENT_RX.')\b/u', $window)) {
                    $types[] = ['type' => 'modernization', 'pos' => $open];
                }
            } elseif (! str_contains(mb_substr($lc, max(0, $open - 10), 30), 'ouvert au public') && ! $this->badObject($lc, $open, $kw)) {
                $types[] = ['type' => $this->qualify('opening', $lc, $open), 'pos' => $open];
            }
        }
        if (($p = $hit('/\b(concédée?s?|concession|rétrocédée?s?|rétrocession|affermée?s?)\b/u')) !== null) {
            $end = preg_match('/\b(fin de (?:la )?concession|rachat|rachetée?s?|nationalis|déchéance|reprise par l[\'’]état|incorporée?s? au réseau de l[\'’]état)/u', $lc);
            $types[] = ['type' => $end ? 'concession_end' : 'concession', 'pos' => $p];
        }
        if (($p = $hit('/\b(déclarée? d[\'’]utilité publique|déclaration d[\'’]utilité publique|\bdup\b)/u')) !== null) {
            $types[] = ['type' => 'other', 'pos' => $p, 'label' => "Déclaration d'utilité publique"];
        }
        if (($p = $hit('/\b(mise à double voie|doublement|mise à voie unique|banalisation|block automatique|modernisation|rénovation complète|renouvellement (?:complet )?de la voie)\b/u')) !== null) {
            if (! in_array('modernization', array_column($types, 'type'), true)) {
                $types[] = ['type' => 'modernization', 'pos' => $p];
            }
        }
        if ($mode === 'bullet' && ($p = $hit('/\b(accident|déraillement|collision|catastrophe|tamponnement)\b/u')) !== null) {
            $types[] = ['type' => 'accident', 'pos' => $p];
        }

        return $types;
    }

    /**
     * Une « ouverture » qui ne porte pas sur la ligne : électrification (titre de section, mot
     * d'électrification avant le mot-clé : « électrifiée … entre Laval et Rennes (mise en service
     * le 1 juillet 1965) »), ouverture temporaire (détournement à titre exceptionnel), service ou
     * équipement sujet du verbe (« un train baptisé Le Cévenol fut créé … et prolongé », « les
     * Aqualys, mis en service en 1999 », « évitements … mis en service »), service objet du verbe
     * (« mise en service de trains directs », « avec deux rotations par jour »), autre ligne
     * (« mise en service de la LGV Atlantique » dans un autre article que le sien).
     */
    private function openingOfSomethingElse(string $clause, string $sentence, string $heading, int $pos): bool
    {
        $lc = mb_strtolower($clause);
        $ls = mb_strtolower($sentence);
        $at = mb_strpos($ls, $lc);
        $kw = ($at === false ? 0 : $at) + $pos; // proposition héritée (pos 0) : son début
        $before = mb_substr($ls, 0, $kw);
        $after = mb_substr($ls, $kw, 120);

        // 1. Électrification : titre de section, mot d'électrification avant le mot-clé sans nouveau
        //    sujet « ligne » entre les deux, ou mot-clé qui porte sur l'électrification.
        if (preg_match('/électrif|traction électrique|signalisation/u', mb_strtolower($heading))) {
            return true; // titres « Électrification », « Signalisation » : mises en service d'équipements
        }
        if (preg_match_all(self::ELECTRIFICATION_RX, $before, $em, PREG_OFFSET_CAPTURE)) {
            $tail = substr($before, end($em[0])[1]);
            if (! preg_match('/\b(?:la ligne|cette ligne|la section|le tronçon|celle-ci|(?:elle|il) (?:est|a|fut|avait))\b/u', $tail)) {
                return true;
            }
        }
        if (preg_match('/^\S+(?:\s+\S+){0,3}?\s+(?:de\s+l[\'’]électrification|de\s+la\s+traction\s+électrique|du\s+courant)\b/u', $after)) {
            return true;
        }
        // 2. Ouverture temporaire (itinéraire de détournement).
        if (preg_match('/à titre exceptionnel|exceptionnellement|provisoirement|temporairement|\b(?:trains?|circulations?|relations?)\b[^.;]{0,60}\bdétournée?s?\b/u', $lc)) {
            return true;
        }
        // 3. Service ou équipement sujet : le dernier nommé avant le mot-clé, sans nom de portion de
        //    ligne entre les deux ni juste après (« le premier train inaugure la ligne » reste une
        //    ouverture), dans une proposition dont la ligne n'est pas le sujet (« la section …, parcourue
        //    par des trains de marchandises, est ouverte aux voyageurs »).
        $lineSubject = (bool) preg_match('/^(?:[^,]{0,40},\s*)?(?:(?:la|cette|le|ce) (?:ligne|section|tronçon|portion|partie|antenne|embranchement|branche|prolongement)\b|(?:elle|il|celle-ci|celui-ci) (?:est|a|fut|sera|sont|ont|avait)\b)/u', $lc);
        if (! $lineSubject && preg_match_all(self::SERVICE_RX, $before, $sm, PREG_OFFSET_CAPTURE)) {
            $last = end($sm[0]);
            $from = mb_strlen(substr($before, 0, $last[1]));
            $between = mb_substr($before, $from);
            $lead = mb_substr($before, max(0, $from - 12), min(12, $from));
            if (! preg_match(self::LINE_NOUN_RX, $between) && ! preg_match(self::LINE_NOUN_RX, mb_substr($after, 0, 40))
                && ! preg_match('/\bpremi(?:er|ère)s?\s+$/u', $lead)) {
                return true;
            }
        }
        // 4. Service objet : « mise en service de trains directs », « … avec deux rotations par jour ».
        if (preg_match('/^\S+(?:\s+\S+){0,3}?\s+(?:de|des|du|d[\'’])\s*(?:la\s+|l[\'’])?(?:trains?|tgv|ter|relations?|dessertes?|services?|liaisons?|navettes?|rames?|autorails?|postes?|'.self::EQUIPMENT_RX.')\b/u', $after)
            || preg_match('/\b(?:rotations?|aller-retours?|allers-retours|trains?|circulations?)\s+(?:par|chaque)\s+(?:jour|semaine)\b/u', mb_substr($after, 0, 80))) {
            return true;
        }
        // 5. Autre ligne : lien vers l'article d'une autre ligne juste après le mot-clé, ou LGV objet
        //    (« mise en service de la première section de la LGV Est ») ou sujet (« la LGV Rhône-Alpes
        //    est mise en service ») sous un autre nom que celui de l'article.
        if (preg_match('/^[^.;⟦]{0,80}⟦/u', $after)) {
            return true;
        }
        $origAfter = mb_substr($sentence, $kw, 160);
        $origBefore = mb_substr($sentence, max(0, $kw - 80), min(80, $kw));
        $lgvName = '(?:LGV|lgv|[Ll]igne à grande vitesse)(?:\s+(?:de\s+la\s+|du\s+|d[\'’])?(\p{Lu}[\p{L}\'’-]*(?:\s+(?:\p{Lu}[\p{L}\'’-]*|européenne))*))?';
        if (preg_match('/^\S+(?:\s+\S+){0,8}?\s+'.$lgvName.'/u', $origAfter, $lgv)
            || preg_match('/'.$lgvName.'[^.;,]{0,40}$/u', $origBefore, $lgv)) {
            $title = (string) $this->articleTitle;
            if (! preg_match('/^(?:LGV|Ligne à grande vitesse)\b/iu', $title)) {
                return true; // une LGV dans l'article d'une ligne classique
            }
            $own = $this->norm(preg_replace('/^(?:LGV|Ligne à grande vitesse)\s+/iu', '', $title));
            if (($lgv[1] ?? '') !== '' && ! str_starts_with($own, $this->norm($lgv[1]))) {
                return true; // une autre LGV que celle de l'article
            }
        }

        return false;
    }

    /**
     * L'événement concerne un AUTRE sujet que la ligne de l'article (contrôle qualité du 2026-10-20 :
     * « Ouverture de Tokyo à Osaka (1964) » dans la chronique de la LGV Sud-Est) :
     *   1. mot-clé entre parenthèses juste après le lien d'une autre ligne : « la ⟦ligne du
     *      Haut-Bugey⟧ (rouverte après modernisation le 12 décembre 2010) » ;
     *   2. sujet de la proposition = lien vers une autre ligne : « La ⟦LGV 1⟧ n'est ouverte en
     *      totalité jusqu'à Bruxelles que le 14 décembre 1997 » (article LGV Nord) ;
     *   2 bis. sujet = « la ligne X - Y » d'une autre ligne (ni X ni Y ne sont des extrémités) ;
     *   3. aparté entre parenthèses avec sa propre portion, qui ne part d'aucune extrémité de la
     *      ligne : « (électrification entre Reims et Mohon) » ;
     *   4. portion qui ne part d'aucune extrémité de la ligne, dans une proposition située à
     *      l'étranger (« ouvert au Japon en 1964 entre Tokyo et Osaka ») ou dans une comparaison
     *      (« inspirée par le précédent du Shinkansen Tōkaidō, ouvert … »).
     *
     * @param  array<string, mixed>|null  $section
     * @param  list<string>  $ends
     */
    public function aboutAnotherSubject(string $clause, string $sentence, int $pos, ?array $section, array $ends): bool
    {
        $lc = mb_strtolower($clause);
        $ls = mb_strtolower($sentence);
        $at = mb_strpos($ls, $lc);
        $kw = ($at === false ? 0 : $at) + $pos;
        if (preg_match('/⟦[^⟧]*⟧\s*\([^()]*$/u', mb_substr($sentence, 0, $kw))) {
            return true;
        }
        $clauseBefore = mb_substr($lc, 0, $pos);
        if (preg_match('/^(?:[^,⟦]{0,40},\s*)?(?:la|le|les|l[\'’]|cette|ce)\s*⟦[^⟧]+⟧/u', $clauseBefore)
            && ! preg_match('/⟧.*\b(?:la ligne|cette ligne|celle-ci|elle)\b/u', $clauseBefore)) {
            return true;
        }
        // « En février 1859, la ligne Le Mans - Argentan est prolongée … » : une autre ligne, nommée sans lien.
        $name = $this->nameRx(false);
        if (preg_match('/^(?:[^,]{0,40},\s*)?(?:la|cette)\s+ligne\s+(?:de\s+|d[\'’]|du\s+)?(?<a>'.$name.')\s*(?:-|–|à|au|aux)\s*(?<b>'.$name.')/u', mb_substr($clause, 0, $pos), $m)
            && $ends && ! $this->matchesEnd($m['a'], $ends) && ! $this->matchesEnd($m['b'], $ends)) {
            return true;
        }
        $anchored = $section !== null && ! ($section['other_line'] ?? false) && (($section['whole_line'] ?? false)
            || ($section['from'] !== null && $this->matchesEnd((string) $section['from'], $ends))
            || ($section['to'] !== null && $this->matchesEnd((string) $section['to'], $ends)));
        if ($anchored) {
            return false;
        }
        // Aparté qui commence par son propre événement, après une phrase déjà datée : « gare d'Amagne -
        // Lucquy le 8 décembre 1964 (électrification entre Reims et Mohon) », « jusqu'en 1884 (ouverture
        // de la ligne Toul - Culmont) ». Pas « (avec une mise en service provisoire de X à Y le …) ».
        $head = mb_substr($sentence, 0, $kw);
        $open = mb_strrpos($head, '(');
        if ($open !== false && ! str_contains(mb_substr($head, $open), ')') && mb_strlen(trim(mb_substr($head, $open + 1))) <= 3
            && $this->findDates(mb_substr($sentence, 0, $open)) !== []
            // « de Courty à Pont-de-Dore le 15 mai 1872 (mise en service de la ligne de Clermont-Ferrand …) » :
            // la proposition principale a sa propre portion, l'aparté la commente.
            && $this->findSection(mb_substr($sentence, 0, $open), $ends) === null) {
            $aside = mb_substr($sentence, $open);
            $aside = mb_substr($aside, 0, (mb_strpos($aside, ')') ?: mb_strlen($aside)));
            $ownSection = $section !== null && ($section['quote'] ?? '') !== '' && str_contains($aside, (string) $section['quote']);
            if ($ownSection || preg_match('/\bligne\s+(?:de\s+|d[\'’]|du\s+)?\p{Lu}|⟦/u', $aside)) {
                return true;
            }
        }

        return (bool) preg_match(self::FOREIGN_COUNTRY_RX, mb_substr($lc, max(0, $pos - 80), 160))
            || (bool) preg_match(self::COMPARISON_RX, mb_substr($ls, 0, $kw));
    }

    /**
     * « mise en service des rames Téoz », « inauguration de l'électrification » :
     * le mot-clé ne porte pas sur la ligne. Un nom propre (lieu) ou un nom de
     * portion (ligne, section, tronçon…) après « de » est accepté.
     */
    private function badObject(string $lc, int $pos, string $kw): bool
    {
        $after = mb_substr($lc, $pos + mb_strlen($kw), 50);
        if (preg_match('/^\s*(?:du|de la|de l[\'’]|des|d[\'’]|de)\s*\d/u', $after)) {
            return true; // « fermeture de 6 700 km de lignes »
        }
        if (! preg_match('/^\s*(?:commerciale?s?\s+|complète\s+|officielle\s+|totale\s+|définitive\s+|administrative\s+)?(?:du|de la|de l[\'’]|des|d[\'’]|de)\s*([\p{L}-]+)/u', $after, $m)) {
            return false;
        }
        $w = $m[1];
        $ok = ['ligne', 'lignes', 'section', 'sections', 'tronçon', 'tronçons', 'portion', 'portions', 'partie', 'prolongement',
            'antenne', 'voie', 'trafic', 'service', 'totalité', 'ensemble', 'parcours', 'itinéraire', 'reste', 'dernier',
            'dernière', 'premier', 'première', 'premiers', 'premières', 'second', 'seconde', 'deuxième', 'troisième', 'nouvelle', 'nouveau',
            'embranchement', 'branche', 'circulation', 'exploitation', 'la', 'le', 'les', 'l'];
        if (in_array($w, $ok, true)) {
            // « des premières BB 16500 » : matériel, pas la ligne
            return (bool) preg_match('/^\s*(?:du|de la|de l[\'’]|des|d[\'’]|de)\s*(?:premi\w+|nouve\w+|dernière?s?)\s+(?!section|tronçon|partie|portion|ligne|voie)\w/u', $after);
        }
        if (in_array($w, ['lgv', 'rer', 'tgv', 'ter', 'tram', 'tramway', 'métro', 'bb', 'cc', 'z', 'x', 'ice', 'rames', 'rame', 'locomotives', 'autorails', 'automotrices', 'électrification', 'gare', 'gares', 'halte', 'haltes'], true)) {
            return true;
        }
        // Mot en minuscules dans le texte d'origine = nom commun → objet étranger à la ligne.
        $origAfter = mb_substr($this->currentClause, $pos + mb_strlen($kw), 50);

        return (bool) preg_match('/^\s*(?:commerciale?s?\s+|complète\s+|officielle\s+|totale\s+)?(?:du|de la|de l[\'’]|des|d[\'’]|de)\s*\p{Ll}/u', $origAfter);
    }

    /**
     * Voyageurs / marchandises / tout trafic, d'après les mots qui suivent
     * (ou précèdent de peu) le mot-clé.
     *
     * Mot-clé suivi de sa propre date : le qualificatif d'un AUTRE événement de la phrase ne
     * déteint pas sur lui. Après sa date, la fenêtre s'arrête avant la date ou le mot-clé
     * d'événement suivant, et même avant la proposition (« , … », « et … ») qui les porte :
     *   - « La ligne a été ouverte le 4 août 1870 et fermée au service des voyageurs le 6 mars
     *     1939 » : ouverture à tout trafic, pas aux seuls voyageurs (sinon, après la fermeture
     *     aux voyageurs, la portion restait sans aucun trafic au lieu de « fret ») ;
     *   - « ouverte entre Chartres et Dreux en 1873, réservée au fret depuis 1971 » : ouverture
     *     à tout trafic (le fret seul vient en 1971) ;
     *   - mais « Ouverte en 1865 par la Compagnie des mines de Béthune, la ligne est
     *     exclusivement affectée au trafic des marchandises » (rien d'autre n'est daté) :
     *     ouverture au fret.
     */
    private function qualify(string $base, string $lc, int $pos): string
    {
        // D'abord le groupe qui suit le mot-clé jusqu'à sa date (« fermée au service
        // des voyageurs en 1945 et des marchandises en 1969 » → voyageurs).
        $after = mb_substr($lc, $pos, 140);
        $start = max(0, $pos - 45);
        $window = mb_substr($lc, $start, 140);
        if (preg_match('/^(.*?)\b(?:en |le |dès le |à partir du |au )?(?:1er |\d{1,2} )?(?:janvier|février|mars|avril|mai|juin|juillet|août|septembre|octobre|novembre|décembre)?\s*(?:1[89]\d\d|20[0-3]\d)\b/u', $after, $mm)) {
            $own = $pos + mb_strlen($mm[0]);
            $rest = mb_substr($lc, $own, max(0, $pos + 140 - $own));
            if (preg_match('/\b(?:1er|\d{1,2}\s+\p{L}+\s+)?(?:1[89]\d\d|20[0-3]\d)\b|\b(?:ouvert|rouvert|réouverture|fermé|fermeture|mise?s? en service|inauguré|déclass|neutralis|supprimé|suppression|désaffect|dépos)/u', $rest, $stop, PREG_OFFSET_CAPTURE)) {
                $rest = substr($rest, 0, $stop[0][1]);
                $parts = preg_split('/\s+(?:et|puis|mais)\s+|[,;:]/u', $rest);
                $rest = count($parts) > 1 ? implode(' ', array_slice($parts, 0, -1)) : $rest;
            }
            $window = mb_substr($lc, $start, $own - $start).$rest;
        }
        if ($mm && ($near = $this->trafficQualifier($mm[1])) !== null) {
            $q = $near;
        } else {
            $q = $this->trafficQualifier($window, true);
        }

        return match ($q) {
            'passenger' => $base.'_passenger',
            'freight' => $base.'_freight',
            default => $base,
        };
    }

    private function requalify(string $type, string $lc): string
    {
        $base = preg_replace('/_(passenger|freight)$/', '', $type);
        if (! in_array($base, ['opening', 'closure', 'reopening'], true)) {
            return $type;
        }
        $q = $this->trafficQualifier($lc);

        return $q === null ? $type : match ($q) {
            'passenger' => $base.'_passenger',
            'freight' => $base.'_freight',
            default => $base,
        };
    }

    /** @return 'passenger'|'freight'|'all'|null */
    private function trafficQualifier(string $lc, bool $window = false): ?string
    {
        if (preg_match('/tout trafic|tous trafics|tout service|à tout|totalement|définitivement tout|tous les trafics/u', $lc)) {
            return 'all';
        }
        $pax = (bool) preg_match('/voyageurs?|passagers?|service des personnes/u', $lc);
        $fret = (bool) preg_match('/marchandises?|\bfret\b|wagons? isolés|trafic de messagerie/u', $lc);
        if ($pax && $fret) {
            // « fermée aux voyageurs, le fret étant maintenu » ≠ tout trafic.
            if (preg_match('/(marchandises?|fret)[^.;]{0,40}\b(maintenu|subsist|continu|conserv|seul|restant|reste|jusqu)/u', $lc)
                || preg_match('/\b(maintenu|conserv|restant|reste)\w*[^.;]{0,25}(marchandises?|fret)/u', $lc)) {
                return 'passenger';
            }
            if (preg_match('/(voyageurs?)[^.;]{0,40}\b(maintenu|subsist|continu|conserv|seul|restant|reste)/u', $lc)) {
                return 'freight';
            }

            return 'all';
        }

        return $pax ? 'passenger' : ($fret ? 'freight' : null);
    }

    /**
     * Fermeture aux voyageurs qui n'en est pas une : seule une PARTIE de la desserte voyageurs
     * disparaît. Cas reconnus :
     *   - la desserte ou le service OMNIBUS est supprimé (« la desserte omnibus entre Trouville -
     *     Deauville et Dives-Cabourg est supprimée et remplacée par des cars », « fermeture de la
     *     ligne au service des voyageurs omnibus ») : les trains directs ou saisonniers restent ;
     *   - des trains de voyageurs SUBSISTENT, dits juste après le mot-clé ou en tête de la phrase
     *     suivante (« Il ne reste plus que les trains directs Paris - Dives-Cabourg qui circulent
     *     en saison », « La seule circulation d'un train de voyageurs porte ensuite sur l'autorail
     *     express »), ou une exception saisonnière (« sauf l'été », « hormis les trains directs »).
     * Des trains spéciaux, touristiques, de pèlerinage ou de marchandises ne comptent pas.
     *
     * @param  array{type: string, pos: int, partial?: string}  $t
     */
    private function isServiceReduction(array $t, string $lc, string $nextLc): bool
    {
        if (($t['partial'] ?? null) === 'service') {
            return true;
        }
        $after = mb_substr($lc, $t['pos']);
        if (preg_match('/\b(?:sauf|excepté|hormis|à l[\'’]exception d[eu]s?)\s+[^.;]{0,40}\b(?:l[\'’]été|en été|en saison|saisonni\w+|estiva\w+|fins? de semaine|week-ends?|trains? (?:directs?|express|rapides?|saisonniers?))\b/u', $after)) {
            return true;
        }

        return $this->passengerServiceSurvives($after) || $this->passengerServiceSurvives(mb_substr($nextLc, 0, 300), true);
    }

    /**
     * Des trains de voyageurs réguliers subsistent (« il ne reste plus que les trains directs… »,
     * « seuls circulent encore les express », « la seule circulation d'un train de voyageurs… »).
     * $atStart : la mention doit ouvrir la phrase (phrase suivante d'une fermeture).
     */
    private function passengerServiceSurvives(string $lc, bool $atStart = false): bool
    {
        $rx = '/\b(?:il ne (?:reste|subsiste) (?:plus |alors |désormais |dès lors |donc )?que|ne (?:circulent?|subsistent?|restent?) (?:plus |alors |désormais )?que|seule?s? (?:subsist\w*|rest\w*|circul\w*|demeur\w*)'
            .'|(?:la |sa )?seule circulation d[\'’]un train de voyageurs|(?:sont|est|restent|reste|demeurent?) maintenue?s?|continuent? (?:de|à) circuler'
            .'|subsist(?:e|ent) (?:seulement |uniquement |encore )?(?:les|des|un|une|deux|trois|quelques))\b/u';
        if (! preg_match($rx, $lc, $m, PREG_OFFSET_CAPTURE)) {
            return false;
        }
        $at = mb_strlen(substr($lc, 0, $m[0][1]));
        if ($atStart && $at > 40) {
            return false;
        }
        $end = $at + mb_strlen($m[0][0]);
        $object = mb_substr($lc, $end, 160);
        $window = mb_substr($lc, max(0, $at - 60), min(60, $at)).$m[0][0].$object;
        if (preg_match('/^\s*(?:la |le |l[\'’]|les )?(?:sections?|tronçons?|parties?|portions?|lignes?|gares?|haltes?)\b/u', $object)) {
            return false; // « il ne reste plus que la section X - Y » : une autre portion
        }
        if (preg_match('/\b(?:marchandises|fret|wagons?|spéciaux|spéciales|touristiques?|pèlerinages?|militaires?|de travaux|de service|vélorails?|draisines?)\b/u', $window)) {
            return false;
        }

        return (bool) preg_match('/\b(?:trains?|autorails?|express|rapides?|directs?|turbotrains?|rtg|etg|rgp|relations?|circulations?|navettes?|voyageurs)\b/u', $window);
    }

    /**
     * Portion restée en service exclue de la fermeture : « le trafic voyageurs entre Montluçon
     * et Moulins est reporté sur la route, la section entre Montluçon et Commentry étant
     * toujours utilisée pour les liaisons Intercités » → fermeture de Commentry à Moulins.
     *
     * @param  array{from: ?string, to: ?string, whole_line: bool}  $section
     */
    private function withoutKeptSection(string $clause, array $section, array $ends): array
    {
        if (! preg_match('/,\s*(?:la|le|l[\'’])\s*(?:section|tronçon|partie|portion)\s+(?:entre|de|d[\'’]|du)\s*.{2,80}?\s(?:étant|restant|demeurant)\s+(?:toujours\s+|encore\s+)?(?:utilisée?|desservie?|ouverte?|exploitée?|parcourue?|empruntée?|en service)\b/u', $clause, $m, PREG_OFFSET_CAPTURE)) {
            return $section;
        }
        $kept = $this->findSection(substr($clause, $m[0][1] + 1), $ends);
        [$a, $b] = $section['whole_line'] ? [$ends[0] ?? null, $ends[1] ?? null] : [$section['from'] ?? null, $section['to'] ?? null];
        if (! $kept || ! $kept['from'] || ! $kept['to'] || ! $a || ! $b) {
            return $section;
        }
        $same = fn (string $x, string $y) => $this->matchesEnd($x, [$y]);
        $rest = match (true) {
            $same($kept['from'], $a) => [$kept['to'], $b],
            $same($kept['to'], $a) => [$kept['from'], $b],
            $same($kept['from'], $b) => [$a, $kept['to']],
            $same($kept['to'], $b) => [$a, $kept['from']],
            default => null,
        };
        if ($rest === null || $this->norm($rest[0]) === $this->norm($rest[1])) {
            return $section;
        }

        return ['from' => $rest[0], 'to' => $rest[1], 'pk_start' => null, 'pk_end' => null, 'whole_line' => false,
            'other_line' => false, 'only_to' => false, 'quote' => $kept['quote']];
    }

    /**
     * Type des puces annoncées par une phrase « … : » de fermeture (« Le trafic des marchandises
     * a été supprimé en cinq étapes : », « La ligne a été fermée au trafic des voyageurs en 1939
     * et au trafic des marchandises en plusieurs étapes : ») : la nature de trafic est celle de la
     * DERNIÈRE proposition, celle que la liste détaille.
     */
    private function listLeadType(string $line): ?string
    {
        $line = trim($line);
        if (! str_ends_with($line, ':')) {
            return null;
        }
        $lc = mb_strtolower($line);
        if ($this->isUncertain($lc)) {
            return null;
        }
        $types = array_values(array_filter($this->detectTypes($line, 'prose'),
            fn ($t) => str_starts_with($t['type'], 'closure') || $t['type'] === 'declassification'));
        if (! $types) {
            return null;
        }
        usort($types, fn ($x, $y) => $x['pos'] <=> $y['pos']);
        $last = end($types)['type'];
        if ($last === 'declassification') {
            return 'declassification';
        }
        $dates = $this->findDates($line);
        $tail = $dates ? mb_substr($lc, end($dates)['pos']) : $lc;

        return match ($this->trafficQualifier($tail)) {
            'passenger' => 'closure_passenger',
            'freight' => 'closure_freight',
            'all' => 'closure',
            default => $dates ? null : $last,
        };
    }

    private function headingDefaultType(string $heading): ?string
    {
        $h = mb_strtolower($heading);

        return match (true) {
            (bool) preg_match('/déclass/u', $h) => 'declassification',
            (bool) preg_match('/dépose/u', $h) => 'dismantling',
            (bool) preg_match('/électrif/u', $h) => 'electrification',
            (bool) preg_match('/réouverture/u', $h) => 'reopening',
            (bool) preg_match('/fermeture.*voyageurs/u', $h) => 'closure_passenger',
            (bool) preg_match('/fermeture.*marchandises/u', $h) => 'closure_freight',
            (bool) preg_match('/fermeture/u', $h) => 'closure',
            (bool) preg_match('/ouverture|mise en service/u', $h) => 'opening',
            default => null,
        };
    }

    /** Dernier niveau du titre de section consacré aux projets ou à l'avenir de la ligne. */
    public function isProjectHeading(string $heading): bool
    {
        $last = trim((string) preg_replace('/^.*>\s*/u', '', $heading));

        // « Le projet », « Genèse du projet », « Premiers projets » : histoire de la construction.
        return (bool) preg_match('/^(?:projets?|les projets|perspectives?|l[\'’]avenir|avenir|quel avenir|devenir|calendrier prévisionnel|potentiel|avantages?)\b/iu', $last);
    }

    private function isUncertain(string $lc): bool
    {
        return (bool) preg_match('/\b(projet\w*|prévue?s?|envisag\w*|devr(?:ait|aient|a)|pourr(?:ait|aient|a)|aurai(?:t|ent)|devai(?:t|ent)|à l[\'’]horizon|étudiée?s?|propos\w*|menac\w*|souhait\w*|réclam\w*|demand\w*|annonc\w*|espér\w*|candidat\w*|hypoth\w*|éventuel\w*|programmée?s?|planifiée?s?|serait|seraient|sera|seront|si\s|essais?|concertation|préalable|enquête publique|débat|études?|annulée?s?|annulation|marche à blanc|à terme|au-delà de|en cours|l[\'’]année suivante|l[\'’]année précédente|plus tard|auparavant|évoqu\w*|envisag\w*|menacée?|coûts?|estim\w*|chiffr\w*|financ\w*|budgets?|mandat\w*|millions? d[\'’]euros|m€|subvention\w*|convention\w*|militent|association|pétition|collectif|réclam\w*|avantages?)/u', $lc);
    }

    /** Ouverture d'une gare, d'un tunnel… : pas un événement de la LIGNE. */
    private function isOtherInfrastructure(string $clause, array $types, bool $hasSection = false): bool
    {
        $first = min(array_column($types, 'pos'));
        // « gare de Boissy-Saint-Léger » est un NOM DE LIEU, pas le sujet : on le masque.
        $masked = preg_replace('/\b(?i:gares?|haltes?|stations?|bifurcations?)\s+(?:de la |de l[\'’]|du |des |de |d[\'’])\s*\p{Lu}/u', 'lieu X', $clause);
        $lc = mb_strtolower($masked);
        $before = mb_substr($lc, 0, $first + 30);
        if ($hasSection) {
            // Portion citée : on n'écarte que si un équipement est le sujet le
            // plus proche (« … et des gares intermédiaires sont fermées »).
            $lastLine = preg_match_all('/\b(ligne|section|tronçon|portion|partie|voie)\b/u', $before, $ml, PREG_OFFSET_CAPTURE) ? end($ml[0])[1] : -1;
            $lastInfra = preg_match_all('/\b(gares?|haltes?|arrêts?|stations?|dépôts?|ateliers?|bâtiments?|postes?|usines?|mines?|embranchements?|raccordements?|quais?|guichets?)\b/u', $before, $mi, PREG_OFFSET_CAPTURE) ? end($mi[0])[1] : -1;

            return $lastInfra > $lastLine;
        }
        // Le sujet le plus proche du mot-clé l'emporte : « les stations ouvertes »
        // après « sur la ligne radiale » parle des stations.
        $lastLine = preg_match_all('/\b(la|cette|toute la) ligne\b/u', $before, $ml, PREG_OFFSET_CAPTURE) ? end($ml[0])[1] : -1;
        $infraRx = '/\b(gares?|haltes?|arrêts?|stations?|dépôts?|ateliers?|bâtiments?|postes?|tunnels?|viaducs?|ponts?|usines?|embranchements?|raccordements?|bretelles?|ite|voies? de service|faisceaux?|guichets?|buffets?|triage|quais?|lignes? de tramway|tramways?|métro|bus|autobus|plateformes?|terminal|téléphérique|funiculaire|parkings?|musée|chantiers?)\b/u';
        $lastInfra = preg_match_all($infraRx, $before, $mi, PREG_OFFSET_CAPTURE) ? end($mi[0])[1] : -1;
        if ($lastInfra >= 0 && $lastLine >= 0) {
            return $lastInfra > $lastLine;
        }

        return (bool) preg_match('/\b(gares?|haltes?|arrêts?|stations?|dépôts?|ateliers?|bâtiments?|postes?|tunnels?|viaducs?|ponts?|usines?|embranchements?|raccordements?|bretelles?|ite|voies? de service|faisceaux?|guichets?|buffets?|triage|quais?|lignes? de tramway|tramways?|métro|bus|autobus|plateformes?|terminal|téléphérique|funiculaire|parkings?|musée|chantiers?)\b/u', $before)
            && ! preg_match('/\bla ligne\b|\btoute la ligne\b/u', $before);
    }

    // ------------------------------------------------------------------
    // Portions : PK, « de X à Y », « entre X et Y », « X - Y »
    // ------------------------------------------------------------------

    private function nameRx(bool $allowDash = true): string
    {
        $w = '[\p{Lu}][\p{Ll}\p{Lu}’\'.]*';
        $ph = '(?:de|du|des|la|le|les|lès|lez|sur|sous|en|aux|au|et|à|d[’\']|l[’\'])';
        $ps = '(?:de|du|des|la|le|les|sur|sous|en|d[’\']|l[’\'])';
        $dash = $allowDash ? '|\s[-–]\s' : '';

        return "{$w}(?:(?:-{$dash})(?:{$ph}-?)*{$w}|\s(?:{$ps}\s?)*{$w}|[’'](?=\p{Lu}){$w})*";
    }

    /**
     * @return array{from: ?string, to: ?string, pk_start: ?int, pk_end: ?int, whole_line: bool, other_line: bool, only_to: bool, quote: string}|null
     */
    public function findSection(string $clause, array $ends): ?array
    {
        $res = ['from' => null, 'to' => null, 'pk_start' => null, 'pk_end' => null, 'whole_line' => false, 'other_line' => false, 'only_to' => false];

        if (preg_match('/\bPK\s*(\d{1,3}(?:[,.]\d{1,3})?)\s*(?:à|au|et|-|–|jusqu[’\']au)\s*(?:(?:au\s+)?(?:PK|pk)\s*)?(\d{1,3}(?:[,.]\d{1,3})?)\b/iu', $clause, $m)) {
            $a = (int) round((float) str_replace(',', '.', $m[1]) * 1000);
            $b = (int) round((float) str_replace(',', '.', $m[2]) * 1000);
            if ($a !== $b) {
                $res['pk_start'] = min($a, $b);
                $res['pk_end'] = max($a, $b);
            }
        }

        $name = $this->nameRx();
        $nameNoDash = $this->nameRx(false);
        $art = '(?:la |le |les |l[’\'])?';
        $poi = '(?:(?:l[’\']emplacement\s+de\s+(?:la\s+|l[’\'])?)?(?:ancienne\s+)?(?:gare|halte|station|bifurcation|bif\.|limite|frontière|sortie|entrée|jonction|terminus)s?\s+(?:de\s+la\s+|de\s+l[’\']|de\s+|d[’\']|du\s+|des\s+))?';
        $patterns = [
            // de X à Y / du X au Y
            '/(?<![\p{L}])(?:(?<prep>(?i:de|depuis|du|des))\s+|(?i:d[’\']))'.$art.$poi.'(?<a>'.$name.')\s+(?<prep2>à|a|jusqu[’\']à|jusqu[’\']au|jusqu[’\']aux|au|aux|vers)\s+'.$art.'(?:'.$poi.'|(?:celle|celui)\s+(?:de\s+|d[’\']))?(?<b>'.$name.')/u',
            // « Paris-Bastille à Bel-Air (PK …) » en tête de puce / cellule
            '/^(?<a>'.$name.')\s+(?<prep2>à|au|aux)\s+'.$art.'(?<b>'.$name.')/u',
            // entre X et Y
            '/\b(?i:entre)\s+'.$art.'(?:les\s+)?'.$poi.'(?<a>'.$name.')\s+et\s+'.$art.'(?:'.$poi.'|(?:celle|celui)\s+(?:de\s+|d[’\']))?(?<b>'.$name.')/u',
            // section X - Y
            '/^(?<a>'.$nameNoDash.')\s+[–—-]\s+(?<b>'.$nameNoDash.')(?=\s*(?:[,:;(]|$|\s(?:le|en|:)\s))/u',
            '/(?:section|tronçon|partie|portion|ligne|relation|parcours|liaison|ouverture|fermeture|service|déclassement|dépose|électrification|réouverture|antenne|itinéraire)s?\s+(?:de\s+la\s+ligne\s+|de\s+la\s+section\s+|du\s+tronçon\s+)?(?<a>'.$nameNoDash.')(?:\s*[–—]\s*|\s+-\s+)(?<b>'.$nameNoDash.')/u',
        ];

        // Toutes les correspondances, dans l'ordre du texte : la première
        // portion valide l'emporte ; une mention d'une AUTRE ligne (lien ⟦…⟧)
        // n'est retenue qu'à défaut.
        $cands = [];
        foreach ($patterns as $pi => $rx) {
            $offset = 0;
            while (preg_match($rx, $clause, $m, PREG_OFFSET_CAPTURE, $offset)) {
                $offset = $m[0][1] + 1;
                $cands[] = [$m[0][1], $pi, $m];
            }
        }
        usort($cands, fn ($x, $y) => [$x[0], $x[1]] <=> [$y[0], $y[1]]);
        $sawOther = null;
        foreach ($cands as [, , $m]) {

            $a = $this->cleanPlace($m['a'][0], $m['prep'][0] ?? null);
            $b = $this->cleanPlace($m['b'][0], $m['prep2'][0] ?? null);
            if (! $a || ! $b || $this->norm($a) === $this->norm($b)) {
                continue;
            }
            $inOther = $this->insideOtherLine($clause, $m[0][1]);
            $before = mb_strtolower(substr($clause, max(0, $m[0][1] - 40), min(40, $m[0][1])));
            if (preg_match('/(chemins? de fer|compagnie[^,;]*|société[^,;]*|route|canal|tramway)\s*$/u', $before)) {
                continue; // « Compagnie du chemin de fer de Paris à Strasbourg »
            }
            if (preg_match('/\b(?:autoroute|route nationale|rocade|l[\'’]a\s?\d{1,3}|rn\s?\d{1,3})(?:\s+[\p{L}-]+){0,2}\s*$/u', $before)) {
                continue; // « la construction de l'A85 entre Corzé et Bourgueil » : la route, pas la ligne
            }
            if (preg_match(self::ITINERARY_RX, $before)) {
                continue; // « une partie de la grande artère historique entre Paris et Bruxelles » : l'itinéraire, pas la ligne
            }
            if ($inOther) {
                $sawOther ??= $m[0][0];

                continue;
            }
            $isLine = isset($m['prep']) && $m['prep'][1] >= 0 && (bool) preg_match('/\blignes?\s*$/u', $before);
            if (preg_match('/\(\s*$/u', $before) && preg_match('/^lignes?\s/iu', $m[0][0])) {
                continue; // « la bifurcation de Stains (ligne Paris - Creil) » : la ligne qui passe là, pas une portion
            }
            $matchA = $this->matchesEnd($a, $ends);
            $matchB = $this->matchesEnd($b, $ends);
            if ($matchA && $matchB) {
                $res['whole_line'] = true;

                return $res + ['quote' => $m[0][0]];
            }
            if ($isLine && ! $matchA && ! $matchB && $ends) {
                continue; // « ligne de X à Y » d'une autre ligne non liée : on cherche plus loin
            }
            $res['from'] = $a;
            $res['to'] = $b;

            return $res + ['quote' => $m[0][0]];

        }
        if ($sawOther !== null && $res['pk_start'] === null) {
            $res['other_line'] = true;

            return $res + ['quote' => $sawOther];
        }

        // « … de Ponte-Leccia à Palasca, et le 15 novembre 1890 de cette gare jusqu'au terminus
        // de Calvi » : départ = arrivée de la portion précédente (cf. only_to dans analyze()).
        if (preg_match('/\b(?:de|depuis)\s+(?:cette\s+(?:dernière\s+)?(?:gare|station|halte|ville|localité)|celle-ci|ce\s+point|là)\s+(?:jusqu[’\'](?:à|au|aux)|à|au|aux|vers)\s+'.$art.$poi.'(?<b>'.$name.')/u', $clause, $m, PREG_OFFSET_CAPTURE)) {
            $b = $this->cleanPlace($m['b'][0], null);
            if ($b && ! $this->insideOtherLine($clause, $m[0][1])) {
                $res['to'] = $b;
                $res['only_to'] = true;

                return $res + ['quote' => $m[0][0]];
            }
        }

        // « prolongée jusqu'à Y » / « prolongement jusqu'à Y »
        if (preg_match('/\bjusqu[’\'](?:à|au|aux)\s+'.$art.$poi.'(?<b>'.$name.')/u', $clause, $m, PREG_OFFSET_CAPTURE)
            && preg_match('/prolong|ouverte?|mise en service|achev/u', mb_strtolower($clause))) {
            $b = $this->cleanPlace($m['b'][0], null);
            if ($b && ! $this->insideOtherLine($clause, $m[0][1])) {
                $res['to'] = $b;
                $res['only_to'] = true;

                return $res + ['quote' => $m[0][0]];
            }
        }

        if ($res['pk_start'] !== null) {
            // « Section à Yèbles (PK 46,677 à 46,735) » : lieu unique indicatif.
            if (preg_match('/\b(?:à|de)\s+(?<a>'.$name.')/u', $clause, $m)) {
                $res['from'] = $this->cleanPlace($m['a'], null);
            }

            return $res + ['quote' => ''];
        }

        if (preg_match('/⟦[^⟧]*⟧/u', $clause) && preg_match('/^(?:[^⟦]{0,60})(fermeture|ouverture|déclassement|dépose|mise en service|électrification)\s+(?:de\s+la\s+|du\s+|des\s+)?⟦/u', mb_strtolower($clause))) {
            $res['other_line'] = true;

            return $res + ['quote' => ''];
        }

        return null;
    }

    private function insideOtherLine(string $s, int $offset): bool
    {
        $before = substr($s, 0, $offset);

        return substr_count($before, '⟦') > substr_count($before, '⟧');
    }

    private function cleanPlace(string $name, ?string $prep): ?string
    {
        // « … entre Vivario et Vizzavona. La longueur totale … » : fin de phrase, pas un nom composé.
        $name = (string) preg_replace('/\.\s+(?:La|Le|Les|L[’\']|Il|Elle|Ils|Elles|Ce|Cette|Ces|En|Un|Une|On)\b.*$/u', '', $name);
        $name = trim(str_replace('—', '-', $name), " \t-–’'.");
        // « Section de Rochy-Condé » (tête de puce ou titre « Section de X à Y ») : le lieu est Rochy-Condé.
        if (preg_match('/^(?:Gare|Halte|Station|Bifurcation|Section|Tronçon|Portion|Partie)\s+(de la |de l[’\']|de |d[’\']|du |des )(.+)$/u', $name, $gm)) {
            $name = $gm[1] === 'du ' ? 'Le '.$gm[2] : ($gm[1] === 'des ' ? 'Les '.$gm[2] : $gm[2]);
        }
        $name = (string) preg_replace('/^(?:Section|Tronçon)\s+(?=\p{Lu})/u', '', $name);
        // « Celui de Gisors-Boisgeloup à Gasny ferme … » (le tronçon) : le lieu est Gisors-Boisgeloup.
        $name = (string) preg_replace('/^(?:Celui|Celle|Ceux|Celles)\s+(?:de\s+|d[’\'])(?=\p{Lu})/u', '', $name);
        $name = preg_replace('/\s+(?:de|du|des|la|le|les|sur|sous|en|et|à|d[’\']|l[’\'])$/u', '', $name);
        if ($name === '' || mb_strlen($name) < 2) {
            return null;
        }
        $first = mb_strtolower(preg_split('/[\s-]/u', $name)[0]);
        if (in_array(mb_strtolower($name), self::NOT_PLACES, true) || in_array($first, ['compagnie', 'société', 'ministère', 'conseil', 'sncf', 'rff', 'ratp', 'état', 'réseau', 'cfd'], true)) {
            return null;
        }
        if (array_key_exists(mb_strtolower($name), self::MONTHS)) {
            return null;
        }
        if (preg_match('/^(LGV|Ligne|RER|TGV|TER|Transilien|Intercités|Train|Autorail|Corail|Téoz|BB|CC|Paris-Orléans|Compagnie)\b/u', $name)) {
            return null;
        }
        // « du Mans » → Le Mans, « aux Aubrais » → Les Aubrais
        $prep = $prep !== null ? mb_strtolower(trim($prep)) : null;
        if (in_array($prep, ['du', 'au'], true) && ! preg_match('/^(Le|La|L[’\']|Les)\b/u', $name)) {
            $name = 'Le '.$name;
        }
        if (in_array($prep, ['des', 'aux', 'jusqu\'aux', 'jusqu’aux'], true) && ! preg_match('/^(Le|La|Les)\b/u', $name)) {
            $name = 'Les '.$name;
        }
        if (in_array($prep, ['jusqu\'au', 'jusqu’au'], true) && ! preg_match('/^(Le|La|L[’\']|Les)\b/u', $name)) {
            $name = 'Le '.$name;
        }

        return mb_substr($name, 0, 120);
    }

    private function matchesEnd(string $place, array $ends): bool
    {
        $p = $this->norm($place);
        foreach ($ends as $e) {
            $e = $this->norm($e);
            if ($e === '' || $p === '') {
                continue;
            }
            if ($p === $e || str_starts_with($p, $e.' ') || str_starts_with($e, $p.' ')) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Tableaux chronologiques
    // ------------------------------------------------------------------

    private function parseTable(array $lines, string $heading, array $ends): array
    {
        $rows = [];
        $row = null;
        foreach ($lines as $raw) {
            $l = trim($raw);
            if ($l === '' || str_starts_with($l, '{|') || str_starts_with($l, '|+')) {
                continue;
            }
            if (str_starts_with($l, '|-')) {
                if ($row) {
                    $rows[] = $row;
                }
                $row = [];

                continue;
            }
            $row ??= [];
            if ($l[0] === '!' || $l[0] === '|') {
                $isHeader = $l[0] === '!';
                $cells = preg_split($isHeader ? '/\s*(?:!!|\|\|)\s*/u' : '/\s*\|\|\s*/u', substr($l, 1));
                foreach ($cells as $c) {
                    $attrs = '';
                    if (preg_match('/^([^|]*=[^|]*)\|(?!\|)(.*)$/su', $c, $m)) {
                        $attrs = $m[1];
                        $c = $m[2];
                    }
                    $span = preg_match('/rowspan\s*=\s*"?(\d+)/iu', $attrs, $sm) ? (int) $sm[1] : 1;
                    $row[] = ['text' => trim($c), 'header' => $isHeader, 'rowspan' => $span];
                }
            } elseif ($row) {
                $row[count($row) - 1]['text'] .= ' '.$l; // suite de cellule multi-ligne
            }
        }
        if ($row) {
            $rows[] = $row;
        }
        if (count($rows) < 2) {
            return [];
        }

        // Ligne d'en-tête = première ligne majoritairement en « ! ».
        $headerIdx = null;
        foreach ($rows as $i => $r) {
            if (count(array_filter($r, fn ($c) => $c['header'])) >= max(2, count($r) / 2)) {
                $headerIdx = $i;
                break;
            }
        }
        if ($headerIdx === null) {
            return [];
        }
        $headers = array_map(fn ($c) => mb_strtolower($c['text']), $rows[$headerIdx]);

        $roles = [];
        foreach ($headers as $ci => $h) {
            $roles[$ci] = match (true) {
                (bool) preg_match('/^(date|année|dates)$/u', $h) => 'date',
                (bool) preg_match('/événement|évènement|description|faits?/u', $h) => 'event',
                (bool) preg_match('/pk.*(début|origine|départ)|^pk de$/u', $h) => 'pk_start',
                (bool) preg_match('/pk.*(fin|arrivée)|^pk à$/u', $h) => 'pk_end',
                (bool) preg_match('/longueur|km|distance|durée/u', $h) && ! preg_match('/date/u', $h) => null,
                (bool) preg_match('/^(de|origine|départ|début)$/u', $h) => 'from',
                (bool) preg_match('/^(à|a|destination|arrivée|fin|extrémité)$/u', $h) => 'to',
                (bool) preg_match('/section|tronçon|parcours|relation|portion/u', $h) && ! preg_match('/date/u', $h) => 'section',
                default => $this->headerType($h),
            };
        }
        $typeCols = array_filter($roles, fn ($r) => $r && str_starts_with($r, 'type:'));
        $events = [];
        $carry = [];
        foreach (array_slice($rows, $headerIdx + 1, null, true) as $r) {
            // Applique les rowspan hérités.
            $cells = [];
            $src = $r;
            for ($ci = 0, $k = 0; $ci < count($headers); $ci++) {
                if (isset($carry[$ci]) && $carry[$ci]['left'] > 0) {
                    $cells[$ci] = $carry[$ci]['text'];
                    $carry[$ci]['left']--;

                    continue;
                }
                $c = $src[$k++] ?? null;
                if ($c === null) {
                    break;
                }
                $cells[$ci] = $c['text'];
                if ($c['rowspan'] > 1) {
                    $carry[$ci] = ['text' => $c['text'], 'left' => $c['rowspan'] - 1];
                }
            }
            if (! $cells) {
                continue;
            }

            $from = $to = null;
            $pkStart = $pkEnd = null;
            foreach ($roles as $ci => $role) {
                $v = trim($cells[$ci] ?? '');
                if ($v === '') {
                    continue;
                }
                if ($role === 'section') {
                    [$from, $to] = $this->splitSectionCell($v, $ends);
                } elseif ($role === 'from') {
                    $from = $this->cleanPlace($v, null);
                } elseif ($role === 'to') {
                    $to = $this->cleanPlace($v, null);
                } elseif ($role === 'pk_start' && preg_match('/(\d{1,3}(?:[,.]\d{1,3})?)/u', $v, $m)) {
                    $pkStart = (int) round((float) str_replace(',', '.', $m[1]) * 1000);
                } elseif ($role === 'pk_end' && preg_match('/(\d{1,3}(?:[,.]\d{1,3})?)/u', $v, $m)) {
                    $pkEnd = (int) round((float) str_replace(',', '.', $m[1]) * 1000);
                }
            }
            $section = ($from || $to || $pkStart !== null) ? [
                'from' => $from, 'to' => $to,
                'pk_start' => $pkStart !== null && $pkEnd !== null ? min($pkStart, $pkEnd) : null,
                'pk_end' => $pkStart !== null && $pkEnd !== null ? max($pkStart, $pkEnd) : null,
                'whole_line' => $from && $to && $this->matchesEnd($from, $ends) && $this->matchesEnd($to, $ends),
                'other_line' => false, 'only_to' => false, 'quote' => '',
            ] : null;
            if ($section && $section['whole_line']) {
                $section = null;
            }
            $rowText = implode(' | ', array_filter($cells));

            if ($typeCols) {
                foreach ($typeCols as $ci => $role) {
                    $v = $cells[$ci] ?? '';
                    foreach ($this->findDates($v) as $d) {
                        if ($d['precision'] === 'decade' || $d['year'] < 1820 || $d['date'] > date('Y-m-d')) {
                            continue;
                        }
                        $events[] = $this->makeEvent(substr($role, 5), $d, $section, 'tableau', $heading, $rowText, $rowText);
                    }
                }
            } elseif (in_array('date', $roles, true) && in_array('event', $roles, true)) {
                $dateCi = array_search('date', $roles, true);
                $evtCi = array_search('event', $roles, true);
                $res = $this->analyze(($cells[$dateCi] ?? '').' : '.($cells[$evtCi] ?? ''), 'bullet', $heading, $ends, ['section' => $section, 'date' => null]);
                array_push($events, ...array_map(fn ($e) => ['origin' => 'tableau'] + $e, $res['events']));
            }
        }

        return $events;
    }

    /** En-tête de colonne de tableau → « type:<événement> » ou null. */
    private function headerType(string $h): ?string
    {
        if (! preg_match('/date|année|ouverture|fermeture|mise en service|déclass|dépose|électrif|concession|utilité publique|dup\b/u', $h)) {
            return null;
        }
        $q = $this->trafficQualifier($h);
        $suffix = match ($q) {
            'passenger' => '_passenger', 'freight' => '_freight', default => ''
        };

        return match (true) {
            (bool) preg_match('/réouverture/u', $h) => 'type:reopening'.$suffix,
            (bool) preg_match('/fermeture/u', $h) => 'type:closure'.$suffix,
            (bool) preg_match('/ouverture|mise en service|inauguration/u', $h) => 'type:opening'.$suffix,
            (bool) preg_match('/déclass/u', $h) => 'type:declassification',
            (bool) preg_match('/dépose/u', $h) => 'type:dismantling',
            (bool) preg_match('/électrif/u', $h) => 'type:electrification',
            (bool) preg_match('/concession/u', $h) => 'type:concession',
            (bool) preg_match('/utilité publique|dup\b/u', $h) => 'type:other',
            default => null,
        };
    }

    /** « Morcenx - Saint-Martin-d'Oney » → [Morcenx, Saint-Martin-d'Oney]. */
    private function splitSectionCell(string $v, array $ends): array
    {
        $v = trim(preg_replace('/\(.*?\)/u', '', $v));
        if (preg_match('/^(?:de\s+|d[’\'])?(.+?)\s+(?:à|au)\s+(.+)$/u', $v, $m)
            || preg_match('/^(.+?)\s*[–—]\s*(.+)$/u', $v, $m)
            || preg_match('/^(.+?)\s+-\s+(.+)$/u', $v, $m)
            || preg_match('/^entre\s+(.+?)\s+et\s+(.+)$/u', $v, $m)) {
            return [$this->cleanPlace($m[1], null), $this->cleanPlace($m[2], null)];
        }

        return [null, null];
    }

    // ------------------------------------------------------------------
    // Infobox (repli) et finalisation
    // ------------------------------------------------------------------

    /**
     * Événements tirés de l'infobox, seulement pour les familles que le
     * corps de l'article ne documente pas (l'infobox ne donne ni la portion
     * ni la nature du trafic).
     */
    private function infoboxEvents(array $infobox, array $bodyEvents): array
    {
        $families = [];
        foreach ($bodyEvents as $e) {
            $families[preg_replace('/_(passenger|freight)$/', '', $e['type'])] = true;
        }
        $hasClosureLike = isset($families['closure']) || isset($families['declassification']) || isset($families['dismantling']);
        $events = [];

        $groups = [
            'mise en service' => ['opening', isset($families['opening'])],
            'électrification' => ['electrification', isset($families['electrification'])],
            'fermeture' => ['closure', $hasClosureLike],
            'désélectrification' => ['other', isset($families['other'])],
        ];
        foreach ($groups as $key => [$type, $skip]) {
            if ($skip) {
                continue;
            }
            $dates = [];
            $partial = false;
            $raw = [];
            foreach (['', '2', '3', '4'] as $n) {
                $v = trim($infobox[$key.$n] ?? $infobox[str_replace(' ', '_', $key).$n] ?? '');
                if ($v === '') {
                    continue;
                }
                $raw[] = "{$key}{$n} = {$v}";
                if (preg_match('/partiel|partie|section/iu', $v)) {
                    $partial = true;
                }
                foreach ($this->findDates($v) as $d) {
                    if ($d['precision'] !== 'decade') {
                        $dates[] = $d;
                    }
                }
            }
            if (! $dates) {
                continue;
            }
            usort($dates, fn ($a, $b) => strcmp($a['date'], $b['date']));
            $quote = 'Infobox : '.implode(' ; ', $raw);
            $last = count($dates) - 1;
            foreach ($dates as $i => $d) {
                // Plusieurs dates d'ouverture = ouverture par étapes : seule la
                // dernière vaut pour toute la ligne, les premières portent sur
                // une portion inconnue.
                $unknown = $partial || ($type === 'opening' && $i < $last) || ($type === 'electrification' && count($dates) > 1 && $i < $last);
                $e = $this->makeEvent($type, $d, null, 'infobox', 'Infobox', $quote, $quote);
                $e['portion_unknown'] = $unknown;
                $e['confidence'] = $type === 'closure' ? 'low' : 'medium';
                if ($type === 'closure') {
                    $e['description'] = 'Fermeture (infobox Wikipédia : nature du trafic non précisée'.($unknown ? ', portion non précisée' : '').')';
                } elseif ($type === 'other') {
                    $e['description'] = 'Désélectrification (infobox Wikipédia)';
                } elseif ($unknown) {
                    $e['description'] = self::TYPE_LABELS[$type].' d\'une portion non précisée (infobox Wikipédia)';
                }
                $events[] = $e;
            }
        }

        // Concessions : concessionnaireN + dateconcN (+ finconcN).
        foreach (array_merge(range(1, 9), ['act']) as $n) {
            $company = $infobox["concessionnaire{$n}"] ?? '';
            if ($company === '' || preg_match('/déclass|fermée|aucun|néant|sans objet|^-$|inconnu/iu', $company)) {
                continue;
            }
            foreach (["dateconc{$n}" => 'concession', "finconc{$n}" => 'concession_end'] as $k => $type) {
                if (empty($infobox[$k]) || isset($families[$type])) {
                    continue;
                }
                $d = array_values(array_filter($this->findDates($infobox[$k]), fn ($x) => $x['precision'] !== 'decade'))[0] ?? null;
                if (! $d) {
                    continue;
                }
                $quote = "Infobox : concessionnaire{$n} = {$company} ; {$k} = {$infobox[$k]}";
                $e = $this->makeEvent($type, $d, null, 'infobox', 'Infobox', $quote, $quote);
                $e['description'] = ($type === 'concession' ? 'Concession à ' : 'Fin de concession de ').LineChronicle::dashes(mb_substr($company, 0, 120)).' (infobox Wikipédia)';
                $e['confidence'] = 'medium';
                $events[] = $e;
            }
        }

        return $events;
    }

    /**
     * @param  ?string  $portionText  texte où chercher une portion citée mais non extraite
     *                                (défaut : la phrase ; cf. analyze(), hors propositions à portion propre)
     */
    private function makeEvent(string $type, array $date, ?array $section, string $origin, string $heading, string $clause, string $sentence, ?string $label = null, ?string $portionText = null): array
    {
        $origin = match ($origin) {
            'bullet' => 'liste',
            'prose' => 'texte',
            default => $origin,
        };
        $from = $section['from'] ?? null;
        $to = $section['to'] ?? null;
        $pkA = $section['pk_start'] ?? null;
        $pkB = $section['pk_end'] ?? null;
        $where = match (true) {
            $from && $to => (preg_match('/^[AEIOUYÉÈÊÂÎÔ]/u', $from) ? " d'" : ' de ')."{$from} à {$to}",
            $from !== null && $pkA === null => " (à {$from})",
            // « de toute la ligne » seulement si la source le dit (extrémités de la
            // ligne citées) : le résolveur s'en sert comme mention explicite.
            ($section['whole_line'] ?? false) => ' de toute la ligne',
            default => '',
        };
        if ($pkA !== null) {
            $where .= sprintf(' (PK %s à %s)', number_format($pkA / 1000, 3, ',', ''), number_format($pkB / 1000, 3, ',', ''));
        }
        $label ??= self::TYPE_LABELS[$type] ?? 'Événement';
        // Règle : un événement dont la phrase cite une portion (section, tronçon,
        // gare…) sans qu'on ait pu l'extraire ne doit JAMAIS valoir pour toute la
        // ligne ; idem pour une réouverture sans portion, sauf mention explicite.
        // Le côté d'une frontière (« Du côté suisse la ligne fut démontée », 876000) ou un
        // territoire étranger est une portion citée.
        $lcs = mb_strtolower($sentence);
        $explicitWhole = (bool) preg_match(self::EXPLICIT_WHOLE_LINE, $lcs);
        $portionCited = $section === null && $origin !== 'infobox' && ! $explicitWhole
            && (bool) preg_match('/\b(sections?|tronçons?|portions?|parties?|antennes?|branches?|gares?|haltes?|prolongement)\b|\bentre \p{Lu}|\bjusqu[\'’]/u', $portionText ?? $sentence)
            || $section === null && $origin !== 'infobox' && ! $explicitWhole
            && (bool) preg_match('/\bcôtés?\s+(?:suisse|français|belge|allemand|italien|espagnol|luxembourgeois|monégasque|andorran)\b|\b(?:en|sur le)\s+territoire\s+(?:suisse|belge|allemand|italien|espagnol|luxembourgeois|monégasque|andorran)\b/u', mb_strtolower($clause));
        $suspectReopening = $section === null && str_starts_with($type, 'reopening') && ! $explicitWhole;
        // Pas de tiret long dans les textes affichés (consigne éditoriale).
        $quote = trim(str_replace('—', '-', preg_replace('/[⟦⟧]/u', '', mb_strlen($clause) >= 40 ? $clause : $sentence)));
        $confidence = match ($origin) {
            'tableau', 'liste' => $date['precision'] === 'year' ? 'medium' : 'high',
            'texte' => $date['precision'] === 'year' ? 'low' : 'medium',
            default => 'medium',
        };
        if ($suspectReopening || $portionCited) {
            $confidence = 'low';
        }

        return [
            'type' => $type,
            'date' => $date['date'],
            'year' => $date['year'],
            'precision' => $date['precision'],
            'section_from' => $from ? mb_substr($from, 0, 255) : null,
            'section_to' => $to ? mb_substr($to, 0, 255) : null,
            'pk_start_m' => $pkA,
            'pk_end_m' => $pkB,
            'portion_unknown' => $portionCited || $suspectReopening
                || ($section !== null && $section['only_to'] && ! $from)
                || ($section === null && (bool) preg_match('/partiellement|en partie|\bune partie\b|certaines sections|plusieurs sections|par tronçons|suivant les tronçons|par étapes|progressivement|\bpk\s*\d/u', mb_strtolower($clause))),
            'origin' => $origin,
            'heading' => $heading,
            'description' => str_replace('—', '-', $label.$where.' (Wikipédia, '.$origin.($heading !== '' ? ', « '.mb_substr($heading, 0, 80).' »' : ', introduction').')'),
            'confidence' => $confidence,
            'source_quote' => mb_strlen($quote) > 300 ? mb_substr($quote, 0, 297).'…' : $quote,
        ];
    }

    /**
     * Supprime les doublons : même type + même date + même portion → on garde
     * l'origine la plus structurée ; une date à l'année du texte libre est
     * écartée si un tableau / une liste donne la date exacte du même type.
     */
    private function dedupe(array $events): array
    {
        $rank = ['tableau' => 4, 'liste' => 3, 'texte' => 2, 'infobox' => 1];
        usort($events, fn ($a, $b) => ($rank[$b['origin']] ?? 0) <=> ($rank[$a['origin']] ?? 0)
            ?: ['day' => 3, 'month' => 2, 'year' => 1][$b['precision']] <=> ['day' => 3, 'month' => 2, 'year' => 1][$a['precision']]);
        $kept = [];
        foreach ($events as $e) {
            $sec = $this->norm((string) $e['section_from']).'|'.$this->norm((string) $e['section_to']).'|'.$e['pk_start_m'].'|'.$e['pk_end_m'];
            $dup = false;
            foreach ($kept as $k) {
                if ($k['type'] !== $e['type']) {
                    continue;
                }
                if ($e['origin'] === 'texte' && in_array($k['origin'], ['tableau', 'liste'], true) && $k['date'] === $e['date']) {
                    $dup = true; // même fait, nommé un peu différemment dans le texte
                    break;
                }
                $kSec = $this->norm((string) $k['section_from']).'|'.$this->norm((string) $k['section_to']).'|'.$k['pk_start_m'].'|'.$k['pk_end_m'];
                $sameDate = $k['date'] === $e['date'];
                $coarser = $e['precision'] !== 'day' && $k['year'] === $e['year']
                    && ($e['precision'] === 'year' || substr($k['date'], 0, 7) === substr($e['date'], 0, 7));
                if (($sameDate || $coarser) && ($kSec === $sec || ($e['origin'] !== 'tableau' && $e['origin'] !== 'liste' && $e['section_from'] === null && $e['pk_start_m'] === null))) {
                    $dup = true;
                    break;
                }
                // Même type, même date, même portion décrite dans un autre ordre.
                if ($sameDate && $this->norm((string) $k['section_from']) === $this->norm((string) $e['section_to'])
                    && $this->norm((string) $k['section_to']) === $this->norm((string) $e['section_from']) && $e['section_from']) {
                    $dup = true;
                    break;
                }
            }
            if (! $dup) {
                $kept[] = $e;
            }
        }
        usort($kept, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $kept;
    }

    // ------------------------------------------------------------------
    // Métadonnées (inchangé)
    // ------------------------------------------------------------------

    public function parseCompanies(string $wikitext, array $infobox): array
    {
        $companies = [];
        for ($n = 1; $n <= 9; $n++) {
            $compKey = "concessionnaire{$n}";
            if (! isset($infobox[$compKey]) || $infobox[$compKey] === '') {
                continue;
            }
            $startDates = isset($infobox["dateconc{$n}"]) ? $this->findDates($infobox["dateconc{$n}"]) : [];
            $endDates = isset($infobox["finconc{$n}"]) ? $this->findDates($infobox["finconc{$n}"]) : [];
            $companies[] = [
                'operator' => $infobox[$compKey],
                'from_year' => $startDates[0]['year'] ?? null,
                'to_year' => $endDates[0]['year'] ?? null,
            ];
        }
        if (isset($infobox['concessionnaireact']) && $infobox['concessionnaireact'] !== '') {
            $startDates = isset($infobox['dateconcact']) ? $this->findDates($infobox['dateconcact']) : [];
            $companies[] = ['operator' => $infobox['concessionnaireact'], 'from_year' => $startDates[0]['year'] ?? null, 'to_year' => null];
        }
        if (! $companies) {
            foreach (['concédée à', 'concessionnaire', 'exploitée par', 'exploitant'] as $key) {
                if (! isset($infobox[$key])) {
                    continue;
                }
                foreach (preg_split('/[\n,;]/', $infobox[$key]) as $line) {
                    $line = trim($line);
                    if ($line) {
                        $companies[] = ['operator' => $line, 'from_year' => null, 'to_year' => null];
                    }
                }
            }
        }

        return $companies;
    }

    public function parseRegions(array $infobox): array
    {
        return isset($infobox['régions']) ? $this->splitList($infobox['régions']) : [];
    }

    public function parseDepartements(array $infobox): array
    {
        return isset($infobox['départements']) ? $this->splitList($infobox['départements']) : [];
    }

    private function splitList(string $s): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;\n]| - /', $s)), fn ($x) => $x !== ''));
    }
}
