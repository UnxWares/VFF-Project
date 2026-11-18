<?php

namespace App\Services;

/**
 * Parse les articles Wikipédia FR des RÉSEAUX secondaires (« Chemins de fer
 * départementaux de la Somme », « Tramways de l'Ain », « Réseau breton »…) et
 * des lignes d'intérêt local isolées (« Tramway d'Annecy à Thônes ») en
 * « fiches » datées : une fiche = un itinéraire (lieux A - … - B, longueur
 * éventuelle) + ses événements (ouverture, fermeture aux voyageurs / aux
 * marchandises / totale, déclassement, dépose), chacun avec sa date, sa
 * précision et une courte citation.
 *
 * Formes reconnues (les plus fréquentes dans ces articles) :
 *   - tableaux : colonnes « Ligne / Section », « Date d'ouverture »,
 *     « Fermeture aux voyageurs », « … aux marchandises », « Déclassement »,
 *     « Longueur » (rowspan / colspan, clés de tri masquées retirées) ;
 *   - listes : « * Saint-Laurent - Saint-Trivier, 30 km, ouverte en 1897,
 *     fermée en 1936 », « : Corbigny - Alligny, (62 km), ouverture en 1901,
 *     fermeture le 5 mars 1939 », « * La Mi-Forêt - Sautoger (1905 - 1937) »,
 *     sous-puces = sections de la ligne de la puce parente ;
 *   - titres de sections qui nomment une ligne (« === Ligne Abbeville -
 *     Dompierre === ») : la prose de la section la concerne ;
 *   - prose : « La ligne de Saint-Brieuc à Moncontour a été ouverte le 1er mai
 *     1905 et fermée aux voyageurs le 1er avril 1937 », « ouverte le 15
 *     novembre 1888 de Doullens à Beauval, le 14 février 1889 de Beauval à
 *     Beauquesne » (une fiche par portion citée) ;
 *   - infobox : dates de l'article d'une ligne isolée, ou dates de début /
 *     fin du réseau (fermeture « au plus tard »).
 *
 * Prudence : un événement n'est rattaché à une fiche que si sa phrase la
 * désigne (portion citée, sujet « la ligne » dans la section de la ligne…).
 * Les décisions (« projet de fermeture », « décide la fermeture »), les
 * concessions, déclarations d'utilité publique et travaux ne sont pas des
 * événements. Ne touche pas la base.
 */
final class SecondaryNetworkParser
{
    public const TYPE_LABELS = [
        'opening' => 'Ouverture', 'opening_passenger' => 'Ouverture aux voyageurs', 'opening_freight' => 'Ouverture aux marchandises',
        'reopening' => 'Réouverture', 'reopening_passenger' => 'Réouverture aux voyageurs', 'reopening_freight' => 'Réouverture aux marchandises',
        'closure' => 'Fermeture (tout trafic)', 'closure_passenger' => 'Fermeture aux voyageurs', 'closure_freight' => 'Fermeture aux marchandises',
        'declassification' => 'Déclassement', 'dismantling' => 'Dépose des voies',
    ];

    /** Sections sans intérêt chronologique pour les lignes. */
    private const SKIP_SECTIONS = '/^(notes?\b|références|sources?\b|bibliographie|voir aussi|liens? externes?|articles? connexes|annexes?|galerie|photos?\b|ouvrages\b|matériel|locomotives?|autorails?|automotrices?|voitures?|wagons?|motrices?|draisines?|parc\b|personnel|dépôts?\b|ateliers?|vestiges|tarifs?|horaires?|exploitants?|compagnies? exploitantes?|matériel préservé|préservation|accidents?|vie sociale|culture|filmographie|littérature|ouvrages? d.art|énergie|alimentation|signalisation|tracé dans)/iu';

    private const MONTHS = 'janvier|février|fevrier|mars|avril|mai|juin|juillet|août|aout|septembre|octobre|novembre|décembre|decembre';

    /** Toponyme : mots capitalisés reliés par des particules (« Saint-Laurent-sur-Saône », « La Roche-sur-Yon », « Pont-de-Frans »). */
    private const PLACE = '(?:(?:La|Le|Les|L[\'’])\s?)?\p{Lu}[\p{L}\p{M}\'’.\-]*(?:[\s\-](?:(?:sur|sous|lès|lez|les|le|la|de|du|des|en|aux|au|d[\'’]|l[\'’])[\s\-]?)*\p{Lu}[\p{L}\p{M}\'’.\-]*)*';

    /** Sujets qui désignent la ligne de la section courante. */
    private const LINE_SUBJECT = '/\b(la ligne|cette ligne|ces lignes|les lignes|elle|elles|le tramway|ce tramway|la voie|le chemin de fer|ce chemin de fer|l[\'’]exploitation|le trafic|le service|les trains|la desserte|les circulations|ouverture de la ligne|fermeture de la ligne)\b/iu';

    /** Sujets qui désignent AUTRE CHOSE que la ligne (gare, compagnie, réseau, matériel…). */
    private const OTHER_SUBJECT = '/\b(la gare|une gare|les gares|le dépôt|l[\'’]atelier|la compagnie|la société|le réseau|les réseaux|le conseil|le département|l[\'’]autorail|les autorails|la locomotive|les locomotives|le matériel|la halle|le pont|le viaduc|le tunnel|la route|le musée|l[\'’]association|le funiculaire|le bus|les autobus|la concession|la station)\b/iu';

    /** Mots qui annoncent une portion (une phrase qui en cite une sans qu'on la comprenne n'est jamais appliquée à toute la ligne). */
    private const SECTION_WORDS = '/\b(sections?|tronçons?|portions?|parties?|antennes?|branches?|embranchements?|prolongements?|prolongée?s?|jusqu[\'’]à|entre \p{Lu}|de \p{Lu}[\p{L}\-]+ à \p{Lu})/u';

    /** Événements d'ensemble du réseau (« toutes les lignes … ») cités par l'article en cours. */
    private array $networkStatements = [];

    /**
     * Événements de la prose générale qui désignent une ligne par son seul terminus (« En 1938, la
     * ligne de Sochaux disparaît »), rattachés en fin d'article à la fiche qui s'y termine.
     *
     * @var list<array{label: string, event: array, sentence: string, mode: string}>
     */
    private array $pendingTermini = [];

    public function __construct(private WikipediaLineParser $wp) {}

    /**
     * @return array{infobox: array<string, string>, single: ?array, records: list<array>, network: array{start: ?array, end: ?array, events: list<array>, last_start: ?array, spread: int, last_closure: ?array, closure_spread: int, statement: ?array, statements: list<array>}}
     */
    public function parse(string $wikitext, string $title): array
    {
        $this->networkStatements = [];
        $this->pendingTermini = [];
        $w = $this->prepare($wikitext);
        $infobox = $this->wp->parseInfobox($w);
        $w = $this->stripLeadingInfobox($w);
        // Introduction (avant le premier titre de section) : période d'exploitation du réseau.
        $lead = preg_split('/\n={2,6}[^=\n]/u', $w, 2)[0];
        $period = $this->leadPeriod($lead);

        $records = [];
        $root = null;
        $single = $this->singleLinePlaces($title);
        // Article d'UNE ligne dont le titre ne donne pas l'itinéraire (« Ligne de tramway 2 (ELRT) ») :
        // celui de l'introduction (« la ligne reliait [[Lille]] à [[Leers]] »).
        // Seulement pour une ligne NUMÉROTÉE ou LETTRÉE (« Ligne 5 », « Ligne de tramway R (ELRT) ») : l'article
        // d'une ligne nommée (« Chemin de fer de la Mure ») traite souvent aussi de prolongements jamais faits.
        if (! $single && preg_match('/\{\{\s*Infobox\s+Ligne/iu', $wikitext)
            && preg_match('/^(?:Ancienne\s+)?(?:Ligne|Tramway)(?:\s+de\s+tramway)?\s+(?:n°\s*)?(?:\d+[A-Za-z]?|[A-Z](?:bis)?)\b/u', $title)) {
            $single = $this->leadRoute($lead);
        }
        if ($single) {
            $records[] = $this->record(0, null, 'article', '', $single, null, [], $title);
            $root = 0;
        }

        $lines = explode("\n", $w);
        $n = count($lines);
        $headings = [];          // niveau => [titre, fiche|null, ignorer]
        $context = $root;
        $skip = false;
        $listStack = [];         // profondeur => fiche
        $term = null;            // fiche ouverte par « ; Ligne … »
        $paragraph = '';
        $this->listBase = null;
        $this->listDate = null;
        $this->itemBases = [];
        $flush = function () use (&$paragraph, &$records, &$context, &$term, &$skip, &$headings) {
            if (! $skip && trim($paragraph) !== '') {
                $this->prose($paragraph, $term ?? $context, $records, $this->headingText($headings));
                // Dernière phrase du paragraphe = introduction d'une éventuelle liste qui suit.
                [$pl] = $this->render($paragraph);
                $sent = $this->sentences($pl);
                $intro = (string) end($sent);
                $this->listBase = $this->baseOf($intro);
                // « Le 1er décembre 1934, sont supprimées les lignes suivantes : » : la date vaut pour la liste.
                $d = $this->dates($intro);
                $this->listDate = $this->listBase && $this->listBase !== 'opening-implicit' && count($d) === 1 && preg_match('/(suivantes?|ci-dessous|ci-après)\s*:?\s*$|:\s*$/u', $intro) ? $d[0] : null;
            }
            $paragraph = '';
        };

        for ($i = 0; $i < $n; $i++) {
            $line = rtrim($lines[$i]);
            $t = trim($line);
            if ($t === '') {
                $flush();
                $listStack = [];
                $this->itemBases = [];

                continue;
            }
            if (preg_match('/^(={2,6})\s*(.+?)\s*\1\s*$/u', $t, $m)) {
                $flush();
                $level = strlen($m[1]);
                foreach (array_keys($headings) as $lv) {
                    if ($lv >= $level) {
                        unset($headings[$lv]);
                    }
                }
                [$htext] = $this->render($m[2]);
                $hskip = (bool) preg_match(self::SKIP_SECTIONS, trim($htext));
                $parentCtx = $root;
                foreach ($headings as $h) {
                    if ($h[1] !== null) {
                        $parentCtx = $h[1];
                    }
                    $hskip = $hskip || $h[2];
                }
                $rec = null;
                if (! $hskip) {
                    $places = $this->headingRoute($m[2]);
                    if ($places) {
                        $records[] = $this->record(count($records), $parentCtx, 'titre', $htext, $places, $this->km($htext), [], $htext);
                        $rec = count($records) - 1;
                    }
                }
                $headings[$level] = [$htext, $rec, $hskip];
                $this->listBase = $this->baseOf($htext);
                $this->listDate = null;
                ksort($headings);
                $context = $rec ?? $parentCtx;
                $skip = $hskip;
                $term = null;
                $listStack = [];
                $this->itemBases = [];

                continue;
            }
            if (str_starts_with($t, '{|')) {
                $flush();
                $table = [];
                $depth = 0;
                for (; $i < $n; $i++) {
                    $tl = trim($lines[$i]);
                    if (str_starts_with($tl, '{|')) {
                        $depth++;
                    }
                    $table[] = $lines[$i];
                    if (str_starts_with($tl, '|}')) {
                        $depth--;
                        if ($depth <= 0) {
                            break;
                        }
                    }
                }
                if (! $skip) {
                    $this->table($table, $term ?? $context, $records, $this->headingText($headings));
                }

                continue;
            }
            if ($skip) {
                continue;
            }
            if (preg_match('/^([*#:]+)\s*(.*)$/u', $t, $m)) {
                $flush();
                $depth = strlen($m[1]);
                $parent = null;
                for ($d = $depth - 1; $d >= 1; $d--) {
                    if (isset($listStack[$d])) {
                        $parent = $listStack[$d];
                        break;
                    }
                }
                foreach (array_keys($listStack) as $d) {
                    if ($d >= $depth) {
                        unset($listStack[$d]);
                    }
                }
                // « * Sablonnières à Bray-sur-Seine (88 km), ouverte : » puis « ** de Sablonnières à Saint-Siméon
                // le 23 novembre 1903 » : le verbe de la puce parente vaut pour ses sous-puces.
                $base = null;
                foreach ($this->itemBases as $d => $b) {
                    if ($d >= $depth) {
                        unset($this->itemBases[$d]);
                    } else {
                        $base = $b;
                    }
                }
                $saved = $this->listBase;
                $this->listBase = $base ?? $this->listBase;
                $rec = $this->listItem($m[2], $parent ?? $term ?? $context, $records, $this->headingText($headings), $parent !== null);
                $this->listBase = $saved;
                if ($rec !== null) {
                    $listStack[$depth] = $rec;
                }
                [$itemPlain] = $this->render($m[2]);
                if (preg_match('/:\s*$/u', trim($itemPlain)) && ! $this->dates($itemPlain) && ($b = $this->baseOf($itemPlain)) && $b !== 'opening-implicit') {
                    $this->itemBases[$depth] = $b;
                }

                continue;
            }
            if (str_starts_with($t, ';')) {
                $flush();
                $listStack = [];
                $body = ltrim($t, '; ');
                $places = $this->routeHead($body)['places'] ?? null;
                if ($places) {
                    [$plain] = $this->render($body);
                    $records[] = $this->record(count($records), $context, 'titre', $this->headingText($headings), $places, $this->km($plain), [], $plain);
                    $term = count($records) - 1;
                    $this->attachEvents($records[$term], $this->extractEvents($this->routeHead($body)['tail'] ?? '', 'tete', false, $this->listBase), $plain);
                } else {
                    $term = null;
                    $this->prose($body, $context, $records, $this->headingText($headings));
                }

                continue;
            }
            if (preg_match('/^(\{\{|\}\}|\||!|<)/u', $t)) {
                // Modèle multiligne (schéma de ligne, boîte déroulante…) ou reste de tableau.
                continue;
            }
            $paragraph .= ' '.$t;
        }
        $flush();
        $this->resolveTermini($records);

        // Fiches sans événement ni enfant daté : inutiles.
        $network = $this->networkDates($infobox, $single !== null, $period);
        if ($single && $network['events']) {
            // Infobox d'une ligne isolée : ses dates sont celles de la ligne (repli de faible rang).
            $records[0]['events'] = array_merge($records[0]['events'], $network['events']);
        }

        // Dernière fermeture TOTALE citée par l'article : le réseau décrit était fermé au plus tard à cette date.
        $last = null;
        $firstClosure = null;
        foreach ($records as $r) {
            foreach ($r['events'] as $e) {
                if (in_array($e['type'], ['closure', 'declassification', 'dismantling'], true) && ($firstClosure === null || $e['date'] < $firstClosure)) {
                    $firstClosure = $e['date'];
                }
                if (in_array($e['type'], ['closure', 'declassification', 'dismantling'], true) && ($last === null || $e['date'] > $last['date'])) {
                    $last = ['date' => $e['date'], 'year' => (int) substr($e['date'], 0, 4), 'precision' => $e['precision'],
                        'field' => 'dernière fermeture citée', 'value' => $e['quote'] ?? ''];
                }
            }
        }
        $network['last_closure'] = $last;
        $network['closure_spread'] = $last && $firstClosure ? $last['year'] - (int) substr($firstClosure, 0, 4) : 0;
        // Fin d'ensemble (la plus tardive des fermetures totales d'ensemble) et événements d'ensemble.
        $network['statement'] = null;
        foreach ($this->networkStatements as $c) {
            if (in_array($c['type'], ['closure', 'declassification', 'dismantling'], true)
                && ($network['statement'] === null || $c['date'] > $network['statement']['date'])) {
                $network['statement'] = $c;
            }
        }
        // Ouverture « de toutes les lignes » contredite par l'infobox (dernière mise en service bien plus tardive) : écartée.
        $network['statements'] = array_values(array_filter($this->networkStatements, fn ($st) => $st['type'] !== 'opening'
            || ! ($network['last_start'] ?? null) || $network['last_start']['year'] <= $st['year'] + 3));

        return ['infobox' => $infobox, 'single' => $single, 'records' => $records, 'network' => $network];
    }

    // ------------------------------------------------------------------
    // Fiches
    // ------------------------------------------------------------------

    private function record(int $id, ?int $parent, string $origin, string $heading, array $places, ?float $km, array $events, string $quote): array
    {
        return ['id' => $id, 'parent' => $parent, 'origin' => $origin, 'heading' => $heading, 'places' => $places,
            'km' => $km, 'events' => $events, 'quote' => $this->quote($quote)];
    }

    private function attachEvents(array &$record, array $events, string $quote): void
    {
        foreach ($events as $e) {
            $e['quote'] = $this->quote($e['quote'] ?? $quote);
            $e['origin'] = $e['origin'] ?? $record['origin'];
            $record['events'][] = $e;
        }
    }

    private function quote(string $s): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s));

        return mb_strlen($s) > 300 ? rtrim(mb_substr($s, 0, 297)).'…' : $s;
    }

    /** Puce ou définition « : » : itinéraire en tête puis dates, sinon événements de la fiche parente. */
    /** Verbe implicite des listes de dates (« ouverture » / « fermeture » annoncé par l'introduction ou le titre). */
    private ?string $listBase = null;

    /** Date de l'introduction d'une liste (« Le 1er décembre 1934, sont supprimées les lignes suivantes : »). */
    private ?array $listDate = null;

    /** @var array<int, string> profondeur de puce → verbe donné à ses sous-puces (« …, ouverte : ») */
    private array $itemBases = [];

    private function baseOf(string $text): ?string
    {
        $lc = mb_strtolower($text);
        if (preg_match('/\b(la plupart|certaines|plusieurs|quelques)\b/u', $lc)) {
            return null;
        }
        $o = (bool) preg_match('/\b(ouverture|ouvertures|ouverte?s?|mises? en service|inaugur\w+)\b/u', $lc);
        // « Le 1er décembre 1934, sont supprimées les lignes suivantes : », « L'exploitation ferroviaire
        // cesse sur les lignes restantes aux dates suivantes : », « Les lignes disparaissent… ».
        $c = (bool) preg_match('/\b(fermeture|fermetures|fermée?s?|suppression|supprimée?s?|déclass\w+|cesse|cessent|cessa|cessèrent|cessation|disparai\w+|disparaî\w+|disparurent|fin de l[\'’]exploitation)\b/u', $lc);
        // « Le premier réseau … Il comprend les lignes suivantes : » puis « *Tremblois-lès-Rocroi - Rocroi :
        // novembre 1895 (12km) » : liste des lignes du réseau, la date seule d'une puce est sa mise en
        // service (verbe implicite : confiance faible).
        // De même : « La CFC réalisa les prolongements suivants : », « Par la suite, les sections suivantes
        // ont été ajoutées : », « Le second réseau : ».
        if (! $o && ! $c && ! preg_match('/\b(concession|conc[éèe]d\w*|utilité publique|décret|projet\w*|construction|électrifi\w+|déclarée?s?|gares?|stations?|arrêts?|matériel|autobus|autocars?|remplac\w+|transform\w+|converti\w*|reprises?|repris|rachet\w+|racheté\w*|cédée?s?|rétrocédée?s?|affermée?s?|transférée?s?|intégrée?s?|incorporée?s?|exploitée?s? par)\b/u', $lc)
            && ((preg_match('/\b(lignes?|sections?|prolongements?|extensions?|antennes?|embranchements?)\b/u', $lc)
                    && preg_match('/\b(comprend|comprenait|comprennent|comprenaient|se compos\w+|compos(?:é|ée|és|ées) de|constitu\w+ de|formé\w* de|suivant(?:e|es|s)?|ajouté\w*|réalis\w+)\b/u', $lc))
                || preg_match('/^(?:le |la )?(?:premier|second|seconde|deuxième|troisième|nouveau|nouvel)\s+réseau\s*:?\s*$/u', trim($lc)))) {
            return 'opening-implicit';
        }

        return $o && ! $c ? 'opening' : ($c && ! $o ? 'closure' : null);
    }

    private function listItem(string $raw, ?int $context, array &$records, string $heading, bool $nested): ?int
    {
        $head = $this->routeHead($raw);
        [$plain] = $this->render($raw);
        if ($head) {
            $events = $this->extractEvents($head['tail'], 'tete', false, $this->listBase);
            if (! $events && $this->listDate && $this->listBase && ! $this->dates($head['tail'])) {
                $events[] = $this->event($this->listBase, $this->listDate, 'medium', false, 0, 0, 0) + ['quote' => 'Liste introduite par une date ('.$this->listDate['date'].') : '.$plain];
            }
            $records[] = $this->record(count($records), $context, 'liste', $heading, $head['places'], $this->km($plain), [], $plain);
            $id = count($records) - 1;
            $this->attachEvents($records[$id], $events, $plain);

            return $id;
        }
        // Puce de chronologie (« * 9 mars 1905 : ouverture de la ligne entre X et Y ») :
        // même traitement que la prose, en mode liste.
        $this->prose($raw, $context, $records, $heading, 'liste');

        return null;
    }

    /**
     * Prose : phrase par phrase. Un événement va à la portion citée dans sa
     * proposition (nouvelle fiche), sinon à la portion SUJET de la phrase
     * (« La ligne de X à Y a été ouverte … et fermée … »), sinon à la fiche
     * de contexte si la phrase parle bien de la ligne.
     */
    private function prose(string $raw, ?int $context, array &$records, string $heading, string $mode = 'texte'): void
    {
        [$plain, $links] = $this->render($raw);
        foreach ($this->sentences($plain) as $sentence) {
            if (! preg_match('/\b(1[89]\d\d|20[0-2]\d)\b/u', $sentence)) {
                continue;
            }
            $sections = $this->sectionPhrases($sentence, $links, $mode);
            $events = $this->extractEvents($sentence, $mode, true);
            if (! $events) {
                continue;
            }
            // « Exploitation : mai 1899 - juillet 1934 » / « (1905 - 1937) » sans verbe : seulement en contexte de ligne.
            $firstKw = min(array_map(fn ($e) => $e['pos'], $events));
            $firstClause = null;
            foreach ($events as $e) {
                if ($e['pos'] === $firstKw) {
                    $firstClause = $e['clause'];
                }
            }
            $subject = null;
            foreach ($sections as $s) {
                $before = mb_substr($sentence, max(0, $s['pos'] - 14), min(14, $s['pos']));
                if ($s['pos'] < $firstKw && $s['lead'] && ($s['pos'] < 40 || $s['pos'] >= $firstClause[0])
                    && ! preg_match('/\b(sur|de|à|avec|vers|par|depuis|jusqu[\'’]à|dans|pour|que|en|au|aux|du|des)\s+(?:la|le|les|l[\'’]|une|un|cette)?\s*$/u', $before)) {
                    $subject = $s;
                    break;
                }
            }
            foreach ($events as $e) {
                $own = null;
                foreach ($sections as $s) {
                    if ($s['pos'] >= $e['clause'][0] && $s['pos'] < $e['clause'][1] && $s !== $subject) {
                        $own = $s;
                        break;
                    }
                }
                $sec = $own ?? $subject;
                if ($sec) {
                    $rid = $this->findOrCreate($records, $sec['places'], $context, $mode === 'liste' ? 'liste' : 'texte', $heading, $sentence);
                    $this->attachEvents($records[$rid], [$e], $sentence);

                    continue;
                }
                if ($context === null) {
                    // Phrase sur le réseau en général : seul un événement d'ENSEMBLE est retenu
                    // (« Ces lignes ferment le 31 décembre 1950 », « Toutes les lignes fermeront entre
                    // 1939 et 1951 », « L'ensemble des lignes a été ouvert en 1904 »). Une plage donne
                    // sa borne haute (ouvertes au plus tard, fermées au plus tard).
                    $head = mb_substr($sentence, 0, max($e['clause'][1], 1));
                    $all = '/\b(toutes les lignes|l[\'’]ensemble (?:du réseau|des lignes)|la totalité (?:du réseau|des lignes))\b/iu';
                    $these = '/\b(ces lignes|toutes ces lignes|l[\'’]ensemble de ces lignes|le réseau|ce réseau)\b/iu';
                    $isClosure = in_array($e['type'], ['closure', 'closure_passenger', 'closure_freight', 'declassification', 'dismantling'], true);
                    if (! preg_match('/\b(sauf|excepté|à l[\'’]exception|hormis|sections?|tronçons?|la plupart|certaines|plusieurs|premières?|dernières?|moins|suivantes|ci-dessous|ci-après|suburbain|urbain)\b|:\s*$/iu', $sentence)
                        && (($isClosure && (preg_match($all, $head) || preg_match($these, $head)))
                            || ($e['type'] === 'opening' && (preg_match($all, $head) || preg_match('/\b(ces lignes|toutes ces lignes)\b/iu', $head))))) {
                        $date = $e['range'] && ($r = $this->range(mb_substr($sentence, $e['clause'][0], $e['clause'][1] - $e['clause'][0]))) ? $r[1] : $e;
                        $this->networkStatements[] = ['type' => $e['type'], 'date' => $date['date'], 'year' => (int) substr($date['date'], 0, 4),
                            'precision' => $date['precision'], 'range' => $e['range'], 'field' => 'phrase d\'ensemble', 'value' => $this->quote($sentence)];

                        continue;
                    }
                    // « (fermeture de la ligne de Réchésy) », « En 1938, la ligne de Sochaux disparaît » :
                    // ligne désignée par son terminus, résolue en fin d'article (fiche unique qui s'y termine).
                    $terminus = $this->terminusMention(mb_substr($sentence, $e['clause'][0], $e['clause'][1] - $e['clause'][0]));
                    if ($terminus !== null && ! $e['range']) {
                        $this->pendingTermini[] = ['label' => $terminus, 'event' => $e, 'sentence' => $sentence, 'mode' => $mode];
                    }

                    continue;
                }
                // Portion citée mais non comprise, ou sujet étranger à la ligne : on s'abstient.
                $clauseText = mb_substr($sentence, 0, $e['clause'][1]);
                if (preg_match(self::SECTION_WORDS, $sentence) || preg_match(self::OTHER_SUBJECT, $clauseText)) {
                    continue;
                }
                if ($mode === 'texte' && ! preg_match(self::LINE_SUBJECT, $sentence) && ! $e['range']) {
                    continue;
                }
                $this->attachEvents($records[$context], [$e], $sentence);
            }
            // « … voit la fermeture de l'embranchement d'Offoy à Ercheu, et de la ligne d'Albert à Doullens » :
            // une proposition sans verbe ni date qui ajoute une portion reprend l'événement précédent ;
            // de même un pronom : « est ouverte la ligne de Parthenay à Saint-Laurs et celle de Bressuire
            // à Montreuil-Bellay ».
            $clauses = $this->clauses($sentence);
            foreach ($clauses as $ci => [$c, $cs, $ce]) {
                if ($ci === 0 || ! preg_match('/^(?:et\s+|ainsi\s+que\s+|puis\s+)?(?:de\s+|du\s+|des\s+|d[\'’])?(?:la\s+|l[\'’]|les\s+)?(?:lignes?|sections?|tronçons?|embranchements?|antennes?|celles?\s+(?=de\s|d[\'’]|du\s|des\s|entre\s))\b/u', trim($c))
                    || $this->dates($c) || $this->keywords($c)) {
                    continue;
                }
                [, $ps, $pe] = $clauses[$ci - 1];
                $prev = array_values(array_filter($events, fn ($e) => $e['clause'][0] === $ps));
                foreach ($sections as $sec) {
                    if ($prev && $sec['pos'] >= $cs && $sec['pos'] < $ce) {
                        $rid = $this->findOrCreate($records, $sec['places'], $context, $mode === 'liste' ? 'liste' : 'texte', $heading, $sentence);
                        $this->attachEvents($records[$rid], $prev, $sentence);
                        break;
                    }
                }
            }
        }
    }

    /**
     * Ligne désignée par son seul terminus dans une proposition (« la ligne de Réchésy », « la ligne
     * de Sochaux disparaît », « l'embranchement d'Étueffont ») ; null si aucune, plusieurs, ou si la
     * mention est un itinéraire complet (« la ligne de Belfort à Delle ») ou une exception (« sauf la
     * ligne de Vergt »).
     */
    public function terminusMention(string $clause): ?string
    {
        if (! preg_match_all('/(?<![\p{L}\'’])(?:ligne|embranchement|antenne|branche)\s+(de\s+|d[\'’]\s*|du\s+|des\s+)('.self::PLACE.')/u', $clause, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $found = [];
        foreach ($mm as $m) {
            $label = rtrim(trim($m[2][0]), '.');
            $after = substr($clause, $m[2][1] + strlen($m[2][0]), 16);
            $before = substr($clause, max(0, $m[0][1] - 40), min(40, $m[0][1]));
            if (preg_match('/^\s*(?:[-–—]|à\s|au\s|aux\s|et\s+(?:\p{Lu}|de\s|d[\'’]|du\s)|jusqu|vers\s|par\s)/u', $after)
                || preg_match('/\b(sauf|excepté|exceptée|à l[\'’]exception d[eu\'’]|hormis|hors)\s+(?:\p{L}+\s+){0,3}$/u', $before)
                || $this->badPlace($label)) {
                continue;
            }
            $found[] = trim($m[1][0]) === 'du' && ! preg_match('/^(Le|La|Les)\s/u', $label) ? 'Le '.$label : $label;
        }
        $found = array_values(array_unique($found));

        return count($found) === 1 ? $found[0] : null;
    }

    /** Événements « la ligne de X » : à la fiche (unique) de l'article qui commence ou se termine à X. */
    private function resolveTermini(array &$records): void
    {
        $same = fn (string $x, string $y) => $x === $y || (strlen($x) >= 5 && strlen($y) >= 5 && (str_starts_with($x.' ', $y.' ') || str_starts_with($y.' ', $x.' ')));
        foreach ($this->pendingTermini as $pt) {
            $key = PlaceNames::normalize($pt['label']);
            $hits = [];
            foreach ($records as $r) {
                if ($r['origin'] === 'article' || count($r['places']) < 2) {
                    continue;
                }
                $a = PlaceNames::normalize($r['places'][0]['label']);
                $b = PlaceNames::normalize($r['places'][count($r['places']) - 1]['label']);
                if ($same($a, $key) || $same($b, $key)) {
                    $route = [$a, $b];
                    sort($route);
                    $hits[implode('|', $route)] ??= $r['id'];
                }
            }
            if (count($hits) === 1) {
                $this->attachEvents($records[reset($hits)], [$pt['event'] + ['origin' => $pt['mode'] === 'liste' ? 'liste' : 'texte']], $pt['sentence']);
            }
        }
        $this->pendingTermini = [];
    }

    /**
     * Période d'exploitation du RÉSEAU (ou de la ligne) annoncée par l'introduction de l'article :
     * « Les Tramways de Tarn-et-Garonne sont un ancien réseau … qui ont fonctionné dans le département
     * entre 1913 et 1933 », « Le tramway … a été exploité de 1900 à 1935 ». Jamais la période d'un
     * exploitant (« exploité par la compagnie X de 1926 à 1949 ») ni d'une concession.
     *
     * @return array{start: array, end: array, value: string}|null
     */
    public function leadPeriod(string $lead): ?array
    {
        [$plain] = $this->render($lead);
        foreach ($this->sentences($plain) as $s) {
            if (! preg_match('/\b(?:réseau|réseaux|tramways?|lignes?|chemins? de fer|voies? ferrées?|tacot)\b/iu', $s)
                || ! preg_match('/\b(fonctionn\w+|circul\w+|(?:été |est |était |étaient |sont |fut |furent )?exploitée?s?|en service|en exploitation|en activité)\b/iu', $s, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            $tail = substr($s, $m[0][1], 160);
            if (preg_match('/\b(concession|conc[ée]d\w*|affermage|affermée?s?|projet|construction|construite?s?|électrifi\w+|autorails?|autobus|autocars?)\b/iu', $tail)
                || preg_match('/\bpar\s+(?:la |le |les |l[\'’])?(?:compagnie|société|régie|exploitant|CFD|SE|CFTA|SNCF|département)\b/iu', $tail)) {
                continue;
            }
            $range = $this->range($tail);
            if ($range && $range[1]['year'] - $range[0]['year'] <= 120) {
                return ['start' => $range[0], 'end' => $range[1], 'value' => $this->quote($s)];
            }
        }

        return null;
    }

    private function findOrCreate(array &$records, array $places, ?int $parent, string $origin, string $heading, string $quote): int
    {
        $ends = fn (array $ps) => [PlaceNames::normalize($ps[0]['label']), PlaceNames::normalize($ps[count($ps) - 1]['label'])];
        if ($parent !== null) {
            [$a, $b] = $ends($places);
            [$c, $d] = $ends($records[$parent]['places']);
            $same = fn ($x, $y) => $x === $y || (strlen($x) >= 4 && strlen($y) >= 4 && (str_starts_with($x.' ', $y.' ') || str_starts_with($y.' ', $x.' ')));
            if (($same($a, $c) && $same($b, $d)) || ($same($a, $d) && $same($b, $c))) {
                return $parent; // « La ligne de X à Y … » dans la section « Ligne X - Y »
            }
        }
        $key = implode('|', array_map(fn ($p) => PlaceNames::normalize($p['label']), $places));
        foreach ($records as $r) {
            if ($r['parent'] === $parent && implode('|', array_map(fn ($p) => PlaceNames::normalize($p['label']), $r['places'])) === $key) {
                return $r['id'];
            }
        }
        $records[] = $this->record(count($records), $parent, $origin, $heading, $places, null, [], $quote);

        return count($records) - 1;
    }

    // ------------------------------------------------------------------
    // Tableaux
    // ------------------------------------------------------------------

    private function table(array $lines, ?int $context, array &$records, string $heading): void
    {
        $rows = $this->tableRows($lines);
        if (count($rows) < 2) {
            return;
        }
        // En-tête : premières lignes entièrement en « ! » (plusieurs niveaux fusionnés).
        $header = [];
        $start = 0;
        while ($start < count($rows) && $rows[$start]['header']) {
            foreach ($rows[$start]['cells'] as $k => $c) {
                [$txt] = $this->render($c);
                $header[$k] = trim(($header[$k] ?? '').' '.$txt);
            }
            $start++;
        }
        if (! $header) {
            return;
        }
        $cols = [];
        foreach ($header as $k => $h) {
            $cols[$k] = $this->columnType($h);
        }
        $routeCol = array_search('route', $cols, true);
        $dateCols = array_filter($cols, fn ($c) => ! in_array($c, ['route', 'km', 'ignore'], true));
        if (! $dateCols) {
            return;
        }
        for ($r = $start; $r < count($rows); $r++) {
            $cells = $rows[$r]['cells'];
            $rc = $routeCol;
            if ($rc === false) {
                // Pas de colonne « ligne » reconnue : la première cellule qui ressemble à un itinéraire.
                foreach ($cells as $k => $c) {
                    if (! isset($dateCols[$k]) && $this->routeHead($c, true)) {
                        $rc = $k;
                        break;
                    }
                }
            }
            if ($rc === false || ! isset($cells[$rc])) {
                continue;
            }
            $head = $this->routeHead($cells[$rc], true);
            if (! $head) {
                continue;
            }
            $plainCells = [];
            foreach ($cells as $k => $c) {
                [$plainCells[$k]] = $this->render($c);
            }
            $quote = implode(' | ', array_filter(array_map(fn ($k) => trim(($header[$k] ?? '').' : '.$plainCells[$k], ' :'), array_keys($plainCells)), fn ($s) => $s !== ''));
            $km = null;
            foreach ($cols as $k => $c) {
                if ($c === 'km' && isset($plainCells[$k]) && preg_match('/(\d+(?:[.,]\d+)?)/u', $plainCells[$k], $m)) {
                    $km = (float) str_replace(',', '.', $m[1]);
                }
            }
            $km ??= $this->km($plainCells[$rc]);
            $events = [];
            foreach ($dateCols as $k => $type) {
                $cell = trim($plainCells[$k] ?? '');
                if ($cell === '' || ! preg_match('/\b(1[89]\d\d|20[0-2]\d)\b/u', $cell)) {
                    continue;
                }
                $events = array_merge($events, $this->cellEvents($cell, $type, ($header[$k] ?? '').' : '.$cell));
            }
            if (! $events && ! $head['tail']) {
                continue;
            }
            $events = array_merge($events, $this->extractEvents($head['tail'], 'tete'));
            $records[] = $this->record(count($records), $context, 'tableau', $heading, $head['places'], $km, [], $quote);
            $this->attachEvents($records[count($records) - 1], $events, $quote);
        }
    }

    /** Événements d'une cellule de date, typée par son en-tête (la cellule peut préciser « voyageurs » / « marchandises »). */
    private function cellEvents(string $cell, string $type, string $quote): array
    {
        if ($type === 'range') {
            return array_map(fn ($e) => $e + ['quote' => $quote], $this->extractEvents($cell, 'tete'));
        }
        // La cellule contient ses propres mots-clés (« 1939 (voyageurs), 1948 (marchandises) ») : on les suit.
        $family = fn (string $t) => preg_replace('/_(passenger|freight)$/', '', $t);
        $own = array_values(array_filter($this->extractEvents($cell, 'tete'), fn ($e) => $family($e['type']) === $family($type)));
        if ($own) {
            // Événement sans qualificatif (« 1901-1903 ») dans une colonne « … aux voyageurs » : l'en-tête le qualifie.
            return array_map(fn ($e) => ['type' => $e['type'] === $family($type) ? $type : $e['type']] + $e + ['quote' => $quote], $own);
        }
        $out = [];
        $dates = $this->dates($cell);
        if (! $dates) {
            return [];
        }
        // « 1938 (voyageurs), 1948 (marchandises) » : une date par trafic, sans verbe (extractEvents n'y voit rien).
        if (count($dates) > 1 && in_array($family($type), ['opening', 'closure', 'reopening'], true)) {
            $byTraffic = [];
            foreach ($dates as $i => $d) {
                $segment = substr($cell, $d['pos'], ($dates[$i + 1]['pos'] ?? strlen($cell)) - $d['pos']);
                $q = array_values(array_intersect($this->qualifiers($segment), ['P', 'F']));
                if (count($q) !== 1) {
                    $byTraffic = [];
                    break;
                }
                $byTraffic[] = ['type' => $this->typeFor($family($type), $q), 'date' => $d['date'], 'precision' => $d['precision'],
                    'confidence' => $d['precision'] === 'year' ? 'medium' : 'high', 'range' => false, 'pos' => $d['pos'],
                    'clause' => [0, mb_strlen($cell)], 'quote' => $quote];
            }
            if ($byTraffic) {
                return $byTraffic;
            }
        }
        // Plage dans une colonne d'ouverture : première date ; de fermeture : dernière.
        $d = str_starts_with($type, 'opening') || str_starts_with($type, 'reopening') ? $dates[0] : $dates[count($dates) - 1];
        // Qualificatif dans la cellule (« 1939 aux voyageurs »).
        if (in_array($type, ['closure', 'opening'], true)) {
            $q = $this->qualifiers($cell);
            $type = $this->typeFor($type, $q);
        }
        $out[] = ['type' => $type, 'date' => $d['date'], 'precision' => $d['precision'], 'confidence' => count($dates) > 1 ? 'low' : ($d['precision'] === 'year' ? 'medium' : 'high'),
            'range' => count($dates) > 1, 'pos' => 0, 'clause' => [0, mb_strlen($cell)], 'quote' => $quote];

        return $out;
    }

    private function columnType(string $h): string
    {
        $h = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $h)));
        if ($h === '') {
            return 'ignore';
        }
        if (preg_match('/concession|utilité publique|\bd\.?u\.?p\b|décret|loi\b|arrêté|convention|numéro|n°|type|remarque|note|observation|exploitant|compagnie|écartement|traction|gares?\b|illustration|état|vitesse|propriétaire|constructeur/u', $h)) {
            return 'ignore';
        }
        if (preg_match('/longueur|\bkm\b|kilom|distance/u', $h) && ! preg_match('/ouvert|ferm|service/u', $h)) {
            return 'km';
        }
        $p = (bool) preg_match('/voyageurs?|passagers?/u', $h);
        $f = (bool) preg_match('/marchandises?|fret|\bpv\b/u', $h);
        if (preg_match('/réouverture|remise en service/u', $h)) {
            return $p && ! $f ? 'reopening_passenger' : ($f && ! $p ? 'reopening_freight' : 'reopening');
        }
        if (preg_match('/ouvert|mise en service|mise en exploitation|inaugur|mise en circulation/u', $h) && ! preg_match('/ferm/u', $h)) {
            return $p && ! $f ? 'opening_passenger' : ($f && ! $p ? 'opening_freight' : 'opening');
        }
        if (preg_match('/déclass/u', $h) && ! preg_match('/ferm/u', $h)) {
            return 'declassification';
        }
        if (preg_match('/dépose|déferr/u', $h)) {
            return 'dismantling';
        }
        if (preg_match('/ferm|suppression|fin d.exploitation|fin du service|arrêt de l.exploitation|déclass/u', $h)) {
            return $p && ! $f ? 'closure_passenger' : ($f && ! $p ? 'closure_freight' : 'closure');
        }
        if (preg_match('/période|exploitation|en service|dates?\b|années/u', $h)) {
            return 'range';
        }
        if (preg_match('/ligne|section|tronçon|parcours|itinéraire|relation|trajet|^nom\b|origine|liaison|desserte/u', $h)) {
            return 'route';
        }

        return 'ignore';
    }

    /**
     * Lignes de tableau wiki → cellules (texte brut wiki), rowspan / colspan dépliés.
     *
     * @return list<array{header: bool, cells: array<int, string>}>
     */
    private function tableRows(array $lines): array
    {
        $rows = [];
        $cur = null;
        $carry = []; // colonne => [restant, contenu]
        $push = function () use (&$rows, &$cur, &$carry) {
            if ($cur === null || ! $cur['raw']) {
                $cur = null;

                return;
            }
            $cells = [];
            $col = 0;
            $header = true;
            foreach ($cur['raw'] as [$isHead, $cell]) {
                while (isset($carry[$col]) && $carry[$col][0] > 0) {
                    $cells[$col] = $carry[$col][1];
                    $carry[$col][0]--;
                    $col++;
                }
                [$attr, $content] = $this->splitCellAttributes($cell);
                $span = preg_match('/colspan\s*=\s*"?(\d+)/iu', $attr, $m) ? max(1, min(12, (int) $m[1])) : 1;
                $rowspan = preg_match('/rowspan\s*=\s*"?(\d+)/iu', $attr, $m) ? max(1, min(60, (int) $m[1])) : 1;
                for ($s = 0; $s < $span; $s++) {
                    $cells[$col] = $content;
                    if ($rowspan > 1) {
                        $carry[$col] = [$rowspan - 1, $content];
                    }
                    $col++;
                }
                $header = $header && $isHead;
            }
            while (isset($carry[$col]) && $carry[$col][0] > 0) {
                $cells[$col] = $carry[$col][1];
                $carry[$col][0]--;
                $col++;
            }
            // Ligne d'en-tête de ligne (« ! scope=row | Ligne 1 ») + cellules : pas un en-tête de tableau.
            $rows[] = ['header' => $header, 'cells' => $cells];
            $cur = null;
        };
        foreach ($lines as $l) {
            $t = trim($l);
            if (str_starts_with($t, '{|') || str_starts_with($t, '|+')) {
                continue;
            }
            if (str_starts_with($t, '|}')) {
                break;
            }
            if (str_starts_with($t, '|-')) {
                $push();
                $cur = ['raw' => []];

                continue;
            }
            $cur ??= ['raw' => []];
            if (str_starts_with($t, '!') || str_starts_with($t, '|')) {
                $isHead = $t[0] === '!';
                $body = substr($t, 1);
                // « || 30 » / « !! Titre » en début de ligne : simple séparateur de cellule.
                if ($body !== '' && $body[0] === $t[0]) {
                    $body = substr($body, 1);
                }
                foreach ($this->splitCells($body, $isHead) as $c) {
                    $cur['raw'][] = [$isHead, $c];
                }
            } elseif ($cur['raw']) {
                $last = count($cur['raw']) - 1;
                $cur['raw'][$last][1] .= "\n".$l;
            }
        }
        $push();

        return $rows;
    }

    /** Sépare « a || b || c » (ou « !! ») hors liens et modèles. */
    private function splitCells(string $body, bool $isHead): array
    {
        $out = [];
        $cur = '';
        $depthT = 0;
        $depthL = 0;
        $len = strlen($body);
        for ($i = 0; $i < $len; $i++) {
            $two = substr($body, $i, 2);
            if ($two === '{{') {
                $depthT++;
                $cur .= $two;
                $i++;

                continue;
            }
            if ($two === '}}') {
                $depthT = max(0, $depthT - 1);
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
                $depthL = max(0, $depthL - 1);
                $cur .= $two;
                $i++;

                continue;
            }
            if ($depthT === 0 && $depthL === 0 && ($two === '||' || ($isHead && $two === '!!'))) {
                $out[] = $cur;
                $cur = '';
                $i++;

                continue;
            }
            $cur .= $body[$i];
        }
        $out[] = $cur;

        return $out;
    }

    /** « style="…" | contenu » → [attributs, contenu]. */
    private function splitCellAttributes(string $cell): array
    {
        $depthT = 0;
        $depthL = 0;
        $len = strlen($cell);
        for ($i = 0; $i < $len; $i++) {
            $two = substr($cell, $i, 2);
            if ($two === '{{') {
                $depthT++;
                $i++;

                continue;
            }
            if ($two === '}}') {
                $depthT = max(0, $depthT - 1);
                $i++;

                continue;
            }
            if ($two === '[[') {
                $depthL++;
                $i++;

                continue;
            }
            if ($two === ']]') {
                $depthL = max(0, $depthL - 1);
                $i++;

                continue;
            }
            if ($cell[$i] === '|' && $depthT === 0 && $depthL === 0) {
                $attr = substr($cell, 0, $i);
                if (preg_match('/^\s*(?:[\w-]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s|]+)\s*)+$/u', $attr) || preg_match('/^\s*[a-z-]+\s*$/u', $attr)) {
                    return [$attr, substr($cell, $i + 1)];
                }
                break;
            }
        }

        return ['', $cell];
    }

    // ------------------------------------------------------------------
    // Itinéraires
    // ------------------------------------------------------------------

    /** Lieux d'une ligne isolée d'après le titre de son article, sinon null. */
    public function singleLinePlaces(string $title): ?array
    {
        $t = trim(preg_replace('/\s*\((?:compagnie|ligne|tramway|chemin de fer|ancienne ligne)[^)]*\)\s*$/iu', '', $title));
        $prefix = '/^(?:(?:Compagnie|Société(?: anonyme)?)\s+(?:du|des|de la)\s+)?(?:Ancienne\s+)?(?:Ligne(?:\s+secondaire|\s+de\s+tramway)?|Chemins?\s+de\s+fer(?:\s+(?:d[\'’]intérêt\s+local|industriel|routier|sur\s+route|à\s+voie\s+étroite))?|Tramways?(?:\s+à\s+vapeur|\s+électrique)?|Voie\s+ferrée|Embranchement|Antenne|Tacot)\s+';
        if (preg_match($prefix.'(de\s+la\s+|de\s+l[\'’]|des\s+|du\s+|de\s+|d[\'’])(.+?)\s+(?:à|au|aux)\s+(.+)$/iu', $t, $m)) {
            [$from] = $this->render($m[2]);
            [$to] = $this->render($m[3]);
            $from = match (mb_strtolower(trim($m[1]))) {
                'de la' => 'La '.$from, 'du' => 'Le '.$from, 'des' => 'Les '.$from,
                "de l'", 'de l’' => "L'".$from, default => $from,
            };
            $to = trim(preg_replace('/\s+(?:et\s+(?:extensions?|prolongements?)|via\s+.+|par\s+.+|\(.*\))$/iu', '', $to));
            $parts = preg_split('/\s+(?:et|à)\s+/u', $to);
            $places = [['label' => trim($from), 'target' => null]];
            foreach ($parts as $p) {
                // Article détaché seulement (« la Mure », « l'Argentière ») : « Lalevade », « Laval », « Lens » restent entiers.
                $p = trim(preg_replace('/^(?:(?:la|le|les)\s+|l[\'’]\s*)/iu', '', $p));
                if ($p !== '' && ! preg_match('/^(?:frontière|mer|la mer|sommet|extensions?)/iu', $p)) {
                    $places[] = ['label' => $this->restoreArticleAfter($t, $p), 'target' => null];
                }
            }

            return count($places) >= 2 ? $places : null;
        }
        if (preg_match($prefix.'(.+)$/iu', $t, $m) && preg_match('/\s[-–—]\s|–|—/u', $m[1])) {
            $places = [];
            foreach (preg_split('/\s*[-–—]\s+|\s+[-–—]\s*|–|—/u', $m[1]) as $p) {
                $p = trim($p);
                if ($p !== '') {
                    $places[] = ['label' => $p, 'target' => null];
                }
            }

            return count($places) >= 2 ? $places : null;
        }

        return null;
    }

    /**
     * Itinéraire d'une ligne annoncé par l'introduction de son article : « la ligne reliait
     * [[Lille]] à [[Leers]] », « Elle relie la gare de Saint-Gervais au Nid d'Aigle ». Lieux avec
     * la cible de leur lien, sinon null.
     */
    public function leadRoute(string $lead): ?array
    {
        [$plain, $links] = $this->render($lead);
        $P = self::PLACE;
        if (! preg_match('/\b(?:relie|reliait|reliant|relient|reliaient|joint|joignait|joignant)\s+(?:la (?:ville|gare|commune) (?:de |d[\'’])|les (?:villes|gares|communes) (?:de |d[\'’]))?('.$P.')\s+(à|au|aux)\s+(?:la (?:ville|gare|commune) (?:de |d[\'’]))?('.$P.')/u', $plain, $m)) {
            return null;
        }
        $a = rtrim(trim($m[1]), '.');
        $b = rtrim(trim($m[3]), '.');
        if ($this->badPlace($a) || $this->badPlace($b)) {
            return null;
        }
        $b = match ($m[2]) {
            'au' => preg_match('/^(Le|La|Les)\s/u', $b) ? $b : 'Le '.$b,
            'aux' => preg_match('/^(Le|La|Les)\s/u', $b) ? $b : 'Les '.$b,
            default => $b,
        };

        return [['label' => $a, 'target' => $links[PlaceNames::normalize($a)] ?? null], ['label' => $b, 'target' => $links[PlaceNames::normalize(preg_replace('/^(?:Le|Les)\s/u', '', $b))] ?? $links[PlaceNames::normalize($b)] ?? null]];
    }

    private function articleBack(string $title, string $from): string
    {
        if (preg_match('/\s(du)\s/u', $title) && ! preg_match('/^(Le|La|Les)\s/u', $from) && preg_match('/\sdu\s'.preg_quote($from, '/').'/u', $title)) {
            return 'Le '.$from;
        }

        return $from;
    }

    private function restoreArticleAfter(string $title, string $to): string
    {
        if (preg_match('/\sau\s'.preg_quote($to, '/').'/u', $title)) {
            return 'Le '.$to;
        }
        if (preg_match('/\saux\s'.preg_quote($to, '/').'/u', $title)) {
            return 'Les '.$to;
        }

        return $to;
    }

    /** Titre de section qui nomme une ligne : « Ligne Abbeville - Dompierre », « Ligne d'Albert à Doullens », « Le Mans - Mamers ». */
    private function headingRoute(string $raw): ?array
    {
        [$plain, $links] = $this->render($raw);
        $plain = trim($plain);
        if (preg_match('/^(?:Ligne|Lignes|Embranchement|Antenne|Section|Tronçon|Prolongement|Tramway|Chemin de fer)\b/iu', $plain)
            || preg_match('/^'.self::PLACE.'\s*[-–—]\s*'.self::PLACE.'/u', $plain)) {
            $head = $this->routeHead($raw);

            return $head['places'] ?? null;
        }

        return null;
    }

    /**
     * Itinéraire en tête d'un texte (« [[A]] - [[B]] - C, 30 km, ouverte … »)
     * → lieux + reste du texte. $strict : la cellule ne doit contenir QUE l'itinéraire.
     *
     * @return array{places: list<array{label: string, target: ?string}>, tail: string}|null
     */
    public function routeHead(string $raw, bool $strict = false): ?array
    {
        [$plain, $links] = $this->render($raw);
        $plain = trim(preg_replace('/\s+/u', ' ', $plain));
        $plain = preg_replace('/^(?:\d+[.)°]\s*|[A-Z]\s*:\s*|\(\d+\)\s*)/u', '', $plain);
        // Préfixes : « Ligne n° 3 : », « Ligne 1 : », « Embranchement de », « Antenne de »…
        $plain = preg_replace('/^(?:(?:la|les)\s+)?(?:lignes?|tramways?|chemin de fer)\s*(?:n°\s*)?(?:\d+|[A-Z]\b)?\s*[:.\-–]\s*/iu', '', $plain);
        $plain = preg_replace('/^(?:(?:la|les|l[\'’])\s*)?(?:lignes?|embranchements?|antennes?|sections?|tronçons?|prolongements?|raccordements?|tramways?|chemin de fer|liaison)\s+(?=(?:de |d[\'’]|du |des |entre |\p{Lu}))/iu', '', $plain);
        // Fin de l'itinéraire : première virgule / deux-points / parenthèse / point-virgule.
        if (! preg_match('/^(.+?)(?:\s*[,;:(]|\s+(?:ouvert|mise?s? en service|inaugur|fermé|longue|de \d|d[\'’]une|soit|est |a été|fut |sur \d|le \d|en (?:1[89]|20)\d\d|(?:1er|[12]?\d|3[01]) (?:'.self::MONTHS.')\b|(?:'.self::MONTHS.') (?:1[89]|20)\d\d|(?:1[89]|20)\d\d\b)|$)(.*)$/su', $plain, $m)) {
            return null;
        }
        $route = trim($m[1]);
        $tail = trim(mb_substr($plain, mb_strlen($m[1])));
        if ($strict && preg_match('/\b(ouvert|ferm|mise en service)\w*/iu', $route)) {
            return null;
        }
        $places = null;
        if (preg_match('/^(?:de\s+la\s+|de\s+l[\'’]|de\s+|d[\'’]|du\s+|des\s+)(.+?)\s+(?:à|au|aux)\s+(.+?)(?:\s+(?:par|via)\s+(.+))?$/u', $route, $mm)) {
            $places = [$mm[1]];
            if (! empty($mm[3])) {
                foreach (preg_split('/\s*,\s*|\s+et\s+/u', $mm[3]) as $via) {
                    $places[] = $via;
                }
            }
            $places[] = $mm[2];
            if (str_starts_with($route, 'du ')) {
                $places[0] = 'Le '.$places[0];
            }
        } elseif (preg_match('/^entre\s+(.+?)\s+et\s+(.+)$/u', $route, $mm)) {
            $places = [$mm[1], $mm[2]];
        } elseif (preg_match('/\s[-–—]\s|–|—|\s-|-\s/u', $route)) {
            $places = preg_split('/\s+[-–—]\s+|\s*[–—]\s*|\s-(?=\p{Lu})|(?<=\p{Ll})-\s/u', $route);
        }
        if (! $places || count($places) < 2) {
            return null;
        }
        $out = [];
        foreach ($places as $p) {
            $p = trim($p, " \t.,;:'\"«»");
            $p = trim(preg_replace('/\s*\(.*?\)\s*/u', ' ', $p));
            $p = preg_replace('/^(?:gare\s+(?:de\s+|d[\'’]|du\s+|des\s+)|la gare\s+(?:de\s+|d[\'’]))/iu', '', $p);
            if ($p === '' || mb_strlen($p) > 60 || ! preg_match('/^'.self::PLACE.'$/u', $p)
                || preg_match('/\b(km|lignes?|sections?|ouvert|fermé|kilomètres?)\b|\d{3,}/iu', $p)) {
                return null;
            }
            $out[] = ['label' => $p, 'target' => $links[PlaceNames::normalize($p)] ?? null];
        }

        return ['places' => $out, 'tail' => $tail];
    }

    /**
     * Portions citées dans une phrase (« la ligne de X à Y », « de X à Y »,
     * « entre X et Y », « section X - Y ») avec leur position.
     *
     * @return list<array{pos: int, places: list<array{label: string, target: ?string}>, lead: bool}>
     */
    private function sectionPhrases(string $s, array $links, string $mode = 'texte'): array
    {
        $P = self::PLACE;
        $out = [];
        $patterns = [
            // « la ligne de X à Y », « le tronçon de X à Y » : sujet possible de la phrase.
            ['/\b(?:ligne|lignes|section|sections|tronçon|tronçons|partie|portion|embranchement|antenne|prolongement|raccordement)\s+(?:de\s+|d[\'’]\s*|du\s+|des\s+|entre\s+)('.$P.')\s+(?:à|au|aux|jusqu[\'’]à|et)\s+('.$P.')/u', true],
            ['/\b(?:lignes?|sections?|tronçons?|liaisons?)\s+('.$P.')\s*[-–—]\s*('.$P.')/u', true],
            ['/(?<![\p{L}\'’])(?:de|d[\'’]|du|depuis)\s*('.$P.')\s+(?:à|au|aux|jusqu[\'’]à)\s+('.$P.')/u', false],
            ['/\bentre\s+('.$P.')\s+et\s+('.$P.')/u', false],
        ];
        if ($mode === 'liste') {
            // Listes : « Plouha - Paimpol (1924 - 1956) » sans le mot « ligne ».
            $patterns[] = ['/(?<![\p{L}\-])('.$P.')\s+[-–—]\s+('.$P.')/u', false];
        }
        $taken = [];
        foreach ($patterns as [$rx, $lead]) {
            if (! preg_match_all($rx, $s, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($mm as $m) {
                $pos = mb_strlen(substr($s, 0, $m[0][1]));
                // « … sont fermées sauf celle de Vergt à Bergerac » : portion EXCLUE de l'événement.
                if (preg_match('/\b(sauf|excepté|exceptée|à l[\'’]exception d[eu\'’]|hormis|hors)\s+(?:\p{L}+\s+){0,3}$/u', mb_substr($s, max(0, $pos - 40), min(40, $pos)))) {
                    continue;
                }
                $a = rtrim(trim($m[1][0]), '.');
                $b = rtrim(trim($m[2][0]), '.');
                if (isset($taken[$m[1][1]]) || $this->badPlace($a) || $this->badPlace($b)) {
                    continue;
                }
                $taken[$m[1][1]] = true;
                if (str_contains(mb_substr($m[0][0], 0, 30), ' du ') && ! preg_match('/^(Le|La|Les)\s/u', $a)) {
                    $a = 'Le '.$a;
                }
                $out[] = ['pos' => $pos, 'lead' => $lead, 'places' => [
                    ['label' => $a, 'target' => $links[PlaceNames::normalize($a)] ?? null],
                    ['label' => $b, 'target' => $links[PlaceNames::normalize($b)] ?? null],
                ]];
            }
        }
        usort($out, fn ($x, $y) => $x['pos'] <=> $y['pos']);

        return $out;
    }

    private function badPlace(string $p): bool
    {
        return mb_strlen($p) < 2 || mb_strlen($p) > 60
            || (bool) preg_match('/^(Il|Elle|Ils|Elles|Le|La|Les|Ce|Cette|En|Au|Aux|Un|Une|Son|Sa|Ses|Leur|Puis|Mais|Après|Avant|Depuis|Lors|SNCF|PLM|PO|État|Etat|Compagnie|Société|Conseil|Ligne|Section|Paris-Orléans|France|Midi|Nord|Est|Ouest)$/u', $p)
            || (bool) preg_match('/\b(Guerre|Mondiale|Compagnie|Société|Chemins?|Conseil|Département|Ministère|République|Empire|Assemblée)\b/u', $p)
            || (bool) preg_match('/^(?:'.self::MONTHS.')\b/iu', $p);
    }

    // ------------------------------------------------------------------
    // Événements
    // ------------------------------------------------------------------

    /**
     * Événements datés d'un fragment (queue de puce, cellule, phrase).
     *
     * @param  'tete'|'liste'|'texte'  $mode  tete : queue d'un titre de fiche (« ; A - B … ») ou cellule de tableau
     * @return list<array{type: string, date: string, precision: string, confidence: string, range: bool, pos: int, clause: array{0: int, 1: int}}>
     */
    public function extractEvents(string $text, string $mode, bool $plain = false, ?string $defaultBase = null): array
    {
        if (! $plain) {
            [$text] = $this->render($text);
        }
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '' || ! preg_match('/\b(1[89]\d\d|20[0-2]\d)\b/u', $text)) {
            return [];
        }
        $events = [];
        $prevBase = null;
        $prevQuals = [];
        $prevIdx = -10;
        $prevText = '';
        $leadDate = null;
        $clauses = $this->clauses($text);
        foreach ($clauses as $ci => [$c, $start, $end]) {
            $prevClause = $prevText;
            $context = $prevText.' '.$c;
            // Proposition précédente réduite à une date (« Le 31 décembre 1949 », « En 1936 ») ?
            $leadDate = null;
            if ($prevText !== '' && mb_strlen($prevText) < 45 && preg_match('/^(?:le|en|dès|à partir du|au|puis le|et le)\s/iu', trim($prevText))
                && ! $this->keywords($prevText) && ($pd = $this->dates($prevText))) {
                $leadDate = end($pd);
            }
            $prevText = $c;
            $kws = $this->keywords($c);
            $range = $this->range($c);
            $dates = $this->dates($c);
            if ($range) {
                // Les dates de la plage ne comptent qu'une fois.
                $dates = array_values(array_filter($dates, fn ($d) => $d['year'] !== $range[0]['year'] && $d['year'] !== $range[1]['year']));
            }
            if ($this->excluded($c, $kws)) {
                $prevBase = null;

                continue;
            }
            if (! $kws && ($range || $dates) && $prevBase !== null && $ci === $prevIdx + 1
                && ($this->qualifiers($c) || $mode !== 'texte' || preg_match('/^(?:puis\s+|et\s+)?(?:le|en|dès|à partir du|au)\s/u', trim($c)))
                && ! preg_match(self::OTHER_SUBJECT, $c) && mb_strlen($c) < 120) {
                // « … aux voyageurs en 1939, aux marchandises en 1948 » : le verbe précédent vaut encore.
                $q = $this->qualifiers($c);
                $kws = [['base' => $prevBase, 'quals' => $q ?: $prevQuals, 'pos' => 0, 'inherited' => true]];
            }
            if (! $kws && ! $range && $dates && preg_match('/^\s*jusqu[\'’](?:au|en|à)\b/u', $c)
                && preg_match('/\b(subsist\w+|fonctionn\w+|circul\w+|maintenue?s?|reste(?:nt)? (?:en service|exploitée?s?|ouverte?s?))\b/u', mb_strtolower($prevClause))
                && ! $this->keywords($prevClause) && ! preg_match(self::OTHER_SUBJECT, $prevClause)) {
                // « Seule subsiste alors la ligne Belfort - Étueffont-Haut, jusqu'au 4 juillet 1948 » : la
                // survie annoncée par la proposition précédente s'arrête à cette date.
                $events[] = $this->event('closure', $dates[0], $dates[0]['precision'] === 'year' ? 'medium' : 'high', false, $start, $end, $start);

                continue;
            }
            if (! $kws) {
                // Période sans verbe : en tête de fiche (« A - B (1905 - 1937) »), entre parenthèses dans une
                // puce, ou « exploitée de 1900 à 1936 » ; jamais pour une période d'exploitant ou de concession.
                $rangeOk = match ($mode) {
                    'tete' => true,
                    'liste' => (bool) preg_match('/\(\s*(?:de\s+)?(?:\d{1,2}(?:er)?\s+)?(?:\p{L}+\s+)?(?:1[89]\d\d|20[0-2]\d)\s*[-–—]\s*(?:\d{1,2}(?:er)?\s+)?(?:\p{L}+\s+)?(?:1[89]\d\d|20[0-2]\d)\s*\)/u', $c),
                    default => (bool) preg_match('/^\s*(?:période d[\'’])?exploitation\s*$/iu', $prevClause)
                        || (bool) preg_match('/\b(?:exploitée?s?|en service|circul\w+)\s+(?:de|du|entre|depuis)\s+(?:\d|'.self::MONTHS.')/iu', $context),
                };
                // Liste de dates d'ouverture (« : Carcassonne - Caunes : 10 mars 1901 » sous « Les lignes sont
                // ouvertes aux dates suivantes ») : une date seule prend le verbe de l'introduction.
                if (! $range && $defaultBase !== null && $mode === 'tete' && count($dates) === 1 && count($clauses) <= 3
                    && ! preg_match('/\b(compagnie|société|concession|conc[éèe]d\w*|décret|utilité publique|convention)\b/iu', $text)) {
                    $implicit = $defaultBase === 'opening-implicit';
                    $events[] = $this->event($implicit ? 'opening' : $defaultBase, $dates[0], $implicit ? 'low' : 'medium', false, $start, $end, $start);

                    continue;
                }
                // En tête de fiche ou de puce, seule la proposition de la période compte (« Bussy - Ercheu, 13 km
                // (1897 - 1953), exploitée à l'origine par la Société… ») ; dans un texte, toute la phrase.
                if ($range && $rangeOk && ! preg_match('/\b(compagnie|société|régie|SNCF|CFTA|exploitant|concession|conc[ée]d\w*|affermage|convention|propriété|confiée?)\b/iu', $mode === 'texte' ? $text : $c)) {
                    // « (1905 - 1937) », « Exploitation : mai 1899 - juillet 1934 » : période d'exploitation.
                    [$a, $b] = $range;
                    if ($b['date'] > $a['date']) {
                        $events[] = $this->event('opening', $a, 'low', true, $start, $end, $start);
                        $events[] = $this->event('closure', $b, 'low', true, $start, $end, $start);
                    }
                }

                continue;
            }
            // « Le 31 décembre 1949, la ligne Albert - Ham ferme également » : date en tête de phrase.
            if (! $dates && ! $range && $leadDate !== null) {
                $dates = [$leadDate + ['pos' => 0]];
                $kws = array_map(fn ($k) => ['pos' => 0] + $k, $kws);
            }
            foreach ($kws as $k) {
                $type = $this->typeFor($k['base'], $k['quals']);
                if ($range) {
                    $d = str_starts_with($k['base'], 'opening') || $k['base'] === 'reopening' ? $range[0] : $range[1];
                    $events[] = $this->event($type, $d, 'low', true, $start + $k['pos'], $end, $start);

                    continue;
                }
                $d = $this->nearest($dates, $k['pos'], $k['after'] ?? false);
                if ($d) {
                    $events[] = $this->event($type, $d, $d['precision'] === 'year' ? 'medium' : 'high', false, $start + $k['pos'], $end, $start);
                }
            }
            $last = end($kws);
            $prevBase = $last['base'];
            $prevQuals = $last['quals'];
            $prevIdx = $ci;
        }

        // Une même (type, date) une seule fois.
        $seen = [];
        $out = [];
        foreach ($events as $e) {
            $k = $e['type'].'|'.$e['date'];
            if (! isset($seen[$k])) {
                $seen[$k] = true;
                $out[] = $e;
            }
        }

        return $out;
    }

    private function event(string $type, array $d, string $confidence, bool $range, int $pos, int $end, int $start): array
    {
        return ['type' => $type, 'date' => $d['date'], 'precision' => $d['precision'], 'confidence' => $confidence,
            'range' => $range, 'pos' => $pos, 'clause' => [$start, $end]];
    }

    /**
     * Propositions avec leurs bornes (en caractères) : découpe sur ; , « puis » « et » « mais ».
     *
     * @return list<array{0: string, 1: int, 2: int}>
     */
    private function clauses(string $s): array
    {
        // « entre X et Y » : le « et » ne sépare pas deux propositions (même longueur pour garder les positions).
        $s = preg_replace_callback('/\bentre\s+[^,;:]{1,70}?\s+et\s+/u', fn ($m) => preg_replace('/\s+et\s+$/u', ' §§ ', $m[0]), $s);
        $parts = preg_split('/(\s*;\s*|,\s+|\s+puis\s+|\s+et\s+(?!(?:aux?|à la|à l[\'’])\s+(?:voyageurs|marchandises|fret))|\s+mais\s+|\s+tandis\s+que\s+|:\s+)/u', $s, -1, PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $out = [];
        foreach ($parts as [$p, $off]) {
            if (preg_match('/^(\s*;\s*|,\s+|\s+puis\s+|\s+et\s+|\s+mais\s+|\s+tandis\s+que\s+|:\s+)$/u', $p)) {
                continue;
            }
            $start = mb_strlen(substr($s, 0, $off));
            $out[] = [str_replace(' §§ ', ' et ', $p), $start, $start + mb_strlen($p)];
        }

        return $out;
    }

    /** Mots-clés d'événements d'une proposition : base + qualificatifs (P voyageurs, F marchandises, A tout trafic). */
    private function keywords(string $c): array
    {
        $lc = mb_strtolower($c);
        $found = [];
        $rx = [
            'reopening' => '/\b(réouvert\w*|rouvert\w*|réouverture|remise? en service|rétabli\w*)\b/u',
            'opening' => '/\b(ouverture|ouverte?s?|ouvre|ouvrent|ouvrit|ouvrirent|s[\'’]ouvre|mise?s? en (?:service|exploitation|circulation)|livrée?s? à (?:l[\'’]exploitation|la circulation)|inaugur\w+|entrée? en service|premier train)\b/u',
            'closure' => '/\b(fermeture|fermée?s?|ferma|fermèrent|ferment|fermeront|fermera|(?<!la |une |sa |leur |grande |petite |cette |sa |une |des |les )ferme(?! de\b| du\b| des\b)|cess\w+ toute activité|supprimée?s?|suppression|cessation|cess\w+ (?:d[\'’]être exploitée|toute exploitation|l[\'’]exploitation)|fin de l[\'’]exploitation|fin d[\'’]exploitation|dernier train|dernière circulation|dernières circulations|arrêt de l[\'’]exploitation|n[\'’]est plus exploitée|remplacée? par (?:des |un service d[\'’])?(?:autocars?|cars|autobus|bus|services? routiers?)|(?:fonctionn\w+|circul\w+|subsist\w+|maintenue?s?|reste(?:nt)? en service) jusqu[\'’](?:en|au|à)'
                // « En 1938, la ligne de Sochaux disparaît à son tour », « L'exploitation ferroviaire cesse ».
                .'|disparaît|disparait|disparaissent|disparut|disparurent|(?:a|ont) disparu'
                .'|(?:l[\'’]exploitation|le trafic|le service|les circulations)(?:\s+(?:ferroviaire|commerciale|voyageurs|marchandises))?\s+(?:cesse|cessent|cessa|cessèrent|prend fin|prennent fin|prit fin))\b/u',
            'declassification' => '/\b(déclass\w+)\b/u',
            'dismantling' => '/\b(déposée?s?|dépose|déferrée?s?|déferrement|démontée?s?|démontage|arrachée?s?)\b/u',
        ];
        foreach ($rx as $base => $r) {
            if (preg_match_all($r, $lc, $mm, PREG_OFFSET_CAPTURE)) {
                foreach ($mm[1] as $m) {
                    $pos = mb_strlen(substr($lc, 0, $m[1]));
                    $word = $m[0];
                    if ($base === 'opening' && preg_match('/^(réouvert|rouvert)/u', $word)) {
                        continue;
                    }
                    $after = mb_substr($lc, $pos + mb_strlen($word), 40);
                    $before = mb_substr($lc, max(0, $pos - 35), min(35, $pos));
                    // Ouverture d'autre chose : chantier, enquête, gare, compagnie…
                    if (in_array($base, ['opening', 'closure', 'reopening'], true)
                        && preg_match('/^\s*(?:de |d[\'’]|du |des |à |au |aux )?(?:la |l[\'’]|les |un |une |son |sa )?(?:chantier|travaux|enquête|souscription|crédit|concours|plis|négociations?|compagnie|société|gare|halte|station|dépôt|atelier|bureau|guichet|usine|mine|carrière|route|routière|ligne d[\'’]autocars?|service d[\'’]autocars?|autocars?|autobus|boucle|frontière|école|musée)\b/u', $after)) {
                        continue;
                    }
                    if ($base === 'dismantling' && preg_match('/^\s*(?:un |une |le |la |les |des )?(?:projet|dossier|brevet|plainte|demande|bilan|candidature|statuts)/u', $after)) {
                        continue;
                    }
                    // Autre objet que la ligne juste avant le mot-clé (matériel, voie verte, gare…).
                    if (preg_match('/\b(automotrices?|autorails?|locomotives?|voitures?|wagons?|matériel|électrification|signalisation|voie verte|piste cyclable|véloroute|vélorail|sentier|musée|exposition|gare|halte|dépôt|atelier|usine|mine|carrière|autocars?|autobus|ligne d[\'’]autobus|service routier)\b[^,;]{0,40}$/u', $before)
                        || preg_match('/^\s*(?:de |d[\'’]|du |des )?(?:la |l[\'’]|les |une |un )?(?:voie verte|piste cyclable|véloroute|vélorail|sentier|musée)/u', $after)) {
                        continue;
                    }
                    // Décision, menace, projet : pas un événement réalisé.
                    if (preg_match('/(projet|menac\w*|envisag\w*|demand\w*|décid\w*|décision|vot\w*|propos\w*|prévo\w*|prévu\w*|évoqu\w*|risque|souhait\w*|réclam\w*|annonc\w*|programm\w*|autoris\w*|sollicit\w*|refus\w*|s[\'’]oppos\w*|contre)\s+(?:de\s+|la\s+|l[\'’]|d[\'’]|sa\s+|son\s+|leur\s+|à\s+la\s+|une\s+|un\s+)*$/u', $before)) {
                        continue;
                    }
                    // « après la fermeture », « lors de l'ouverture » : repère pour un AUTRE événement.
                    if (preg_match('/\b(après|lors de|depuis|avant|dès|à l[\'’]occasion de|en vue de|en attendant)\s+(?:la |l[\'’]|sa |son |leur |cette )?$/u', $before)) {
                        continue;
                    }
                    // « jusqu'à sa fermeture le 6 février 1989 », « du 31 décembre 1972 à sa fermeture » : seule une date APRÈS vaut.
                    $afterOnly = (bool) preg_match('/\b(?:jusqu[\'’])?à\s+(?:la |l[\'’]|sa |son |leur )$/u', $before);
                    $found[] = ['base' => $base, 'pos' => $pos, 'word' => $word, 'after' => $afterOnly];
                }
            }
        }
        if (! $found) {
            return [];
        }
        usort($found, fn ($a, $b) => $a['pos'] <=> $b['pos']);
        // « fermée puis déclassée » sont séparées par la découpe ; dans une même proposition, deux mots
        // de même base ne comptent qu'une fois.
        $out = [];
        $quals = $this->qualifiers($c);
        foreach ($found as $f) {
            foreach ($out as $o) {
                if ($o['base'] === $f['base']) {
                    continue 2;
                }
            }
            $q = $quals;
            if (str_starts_with($f['word'], 'remplac')) {
                $q = ['P'];
            }
            $out[] = ['base' => $f['base'], 'quals' => $q, 'pos' => $f['pos'], 'after' => $f['after']];
        }

        return $out;
    }

    /** @return list<string> P (voyageurs), F (marchandises), A (tout trafic) */
    public function qualifiers(string $c): array
    {
        // Sensible à la casse : « Le Fret » (lieu) n'est pas le fret.
        $q = [];
        if (preg_match('/\b([Vv]oyageurs?|[Pp]assagers?)\b/u', $c)) {
            $q[] = 'P';
        }
        if (preg_match('/\b([Mm]archandises?|fret|petite vitesse)\b/u', $c)) {
            $q[] = 'F';
        }
        if (preg_match('/\b(tout[\s-]trafic|tous trafics|à tout[\s-]trafic|totale(?:ment)?|complètement|entièrement)\b/iu', $c)) {
            $q[] = 'A';
        }

        return $q;
    }

    public function typeFor(string $base, array $quals): string
    {
        $p = in_array('P', $quals, true);
        $f = in_array('F', $quals, true);
        if (in_array($base, ['opening', 'closure', 'reopening'], true)) {
            if ($p && ! $f) {
                return $base === 'closure' ? 'closure_passenger' : $base.'_passenger';
            }
            if ($f && ! $p) {
                return $base === 'closure' ? 'closure_freight' : $base.'_freight';
            }
        }

        return $base;
    }

    /** Proposition qui ne décrit pas un événement de la ligne (concession, DUP, travaux, matériel…). */
    private function excluded(string $c, array $kws): bool
    {
        $lc = mb_strtolower($c);
        if (preg_match('/\b(concession|conc[ée]d\w*|utilité publique|\bd\.?u\.?p\b|décret|convention|adjudic\w+|déclarée?s? d[\'’]utilité|électrifi\w+|autorails?|locomotives?|automotrices?|construite?s?|commandée?s?|livrées? par|achetée?s?|vendue?s?|rachetée?s?|affermage|rétrocéd\w+|fusion|créée?s?|fondée?s?|dissoute?s?|faillite|liquidation)\b/u', $lc)
            && ! array_filter($kws, fn ($k) => in_array($k['base'], ['opening', 'closure'], true) && ! preg_match('/concession|utilité/u', mb_substr($lc, $k['pos'], 30)))) {
            return true;
        }

        return false;
    }

    /**
     * Plage « 1905 - 1937 », « 1897-1936 », « de 1897 à 1936 », « mai 1899 - juillet 1934 ».
     *
     * @return array{0: array, 1: array}|null
     */
    private function range(string $c): ?array
    {
        // « (8 avril 1900 - fin 1937) » : « fin », « début » précèdent l'année.
        $D = '(?:(?:fin|début|mi-|courant)\s*)?(?:(?:1er|[12]?\d|3[01])\s+)?(?:(?:'.self::MONTHS.')\s+)?(?:1[89]\d\d|20[0-2]\d)';
        if (! preg_match('/(?<![\d\/])(?:de\s+|entre\s+)?('.$D.')\s*(?:[-–—]|\bà\b|\bau\b|\bet\b|\bjusqu[\'’](?:à|en)\b)\s*('.$D.')(?![\d\/])/iu', $c, $m)) {
            return null;
        }
        $a = $this->dates($m[1]);
        $b = $this->dates($m[2]);
        if (! $a || ! $b || $b[0]['date'] <= $a[0]['date']) {
            return null;
        }

        return [$a[0], $b[0]];
    }

    /** @return list<array{pos: int, date: string, year: int, precision: string}> */
    public function dates(string $s): array
    {
        // « au début de l'année 1939 » : une année, pas une décennie (même longueur : positions conservées).
        $s = preg_replace_callback('/\bannée(?=\s+(?:1[89]\d\d|20[0-2]\d)\b)/u', fn ($m) => str_repeat(' ', mb_strlen($m[0])), $s);
        // « 02 mai 1908 » (infobox, {{date|02|05|1908}}) : « 2 mai 1908 », même longueur.
        $s = preg_replace('/\b0([1-9])(?=\s+(?:'.self::MONTHS.')\b)/iu', ' $1', $s);

        return array_values(array_filter($this->wp->findDates($s), fn ($d) => $d['precision'] !== 'decade' && $d['year'] >= 1830 && $d['year'] <= (int) date('Y')));
    }

    /** Date la plus proche APRÈS le mot-clé (préférée), sinon avant. */
    private function nearest(array $dates, int $pos, bool $afterOnly = false): ?array
    {
        $best = null;
        foreach ($dates as $d) {
            if ($afterOnly && $d['pos'] < $pos) {
                continue;
            }
            $dist = $d['pos'] >= $pos ? $d['pos'] - $pos : ($pos - $d['pos']) * 1.5;
            if ($dist > 90) {
                continue;
            }
            if ($best === null || $dist < $best[0]) {
                $best = [$dist, $d];
            }
        }

        return $best[1] ?? null;
    }

    // ------------------------------------------------------------------
    // Infobox
    // ------------------------------------------------------------------

    /**
     * Dates de l'infobox : ouverture (première mise en service), fermeture
     * (dernière date de fermeture / de disparition).
     *
     * À défaut d'infobox, la période annoncée par l'introduction (« … qui ont fonctionné entre 1913
     * et 1933 ») en tient lieu (champ « introduction »).
     *
     * @return array{start: ?array, end: ?array, events: list<array>, last_start: ?array, spread: int, period: ?array}
     */
    private function networkDates(array $infobox, bool $single, ?array $period = null): array
    {
        $start = null;
        $lastStart = null;
        $end = null;
        $firstEnd = null;
        $closed = null;
        $successor = '';
        foreach ($infobox as $k => $v) {
            if (preg_match('/^(successeur|repreneur|remplacé par)$/u', mb_strtolower(trim($k)))) {
                $successor = trim($this->render((string) $v)[0]);
            }
        }
        foreach ($infobox as $k => $v) {
            $k = mb_strtolower(trim($k));
            // Valeur rendue d'abord ({{date|02|05|1908}} → « 2 mai 1908 »).
            $dates = $this->dates($this->render((string) $v)[0]);
            if (! $dates) {
                continue;
            }
            if (preg_match('/^(mise en service\d?|date d[\'’]ouverture|ouverture|inauguration|début|date de mise en service|mise en service \d)$/u', $k)) {
                foreach ($dates as $d) {
                    if ($start === null || $d['date'] < $start['date']) {
                        $start = $d + ['field' => $k, 'value' => $v];
                    }
                    if ($lastStart === null || $d['date'] > $lastStart['date']) {
                        $lastStart = $d;
                    }
                }
            }
            // Fin du TRAFIC : champs de fermeture, « fin » d'un réseau ; « disparition » / « dissolution »
            // seulement sans successeur (sinon fin de la COMPAGNIE reprise : Bouches-du-Rhône 1881, Dombes 1897).
            if (preg_match('/^(fermeture\d?|date de fermeture|fermeture définitive|fermeture au trafic|suppression|fin)$/u', $k)
                || (preg_match('/^(date de disparition|disparition|dissolution)$/u', $k) && $successor === '')) {
                foreach ($dates as $d) {
                    if ($closed === null || $d['date'] > $closed['date']) {
                        $closed = $d + ['field' => $k, 'value' => $v];
                    }
                }
            }
            if (preg_match('/^(fermeture\d?|date de fermeture|fermeture définitive|fermeture au trafic|suppression)$/u', $k)) {
                // Fin du TRAFIC (≠ fin de la compagnie : dissolution, absorption, rachat).
                foreach ($dates as $d) {
                    if ($closed === null || $d['date'] > $closed['date']) {
                        $closed = $d + ['field' => $k, 'value' => $v];
                    }
                }
            }
            if (preg_match('/^(fermeture\d?|date de fermeture|fin|date de disparition|disparition|fermeture définitive|fermeture au trafic|suppression|dissolution)$/u', $k)) {
                foreach ($dates as $d) {
                    if ($end === null || $d['date'] > $end['date']) {
                        $end = $d + ['field' => $k, 'value' => $v];
                    }
                    if ($firstEnd === null || $d['date'] < $firstEnd['date']) {
                        $firstEnd = $d;
                    }
                }
            }
        }
        if ($period) {
            if (! $start) {
                $start = $period['start'] + ['field' => 'introduction', 'value' => $period['value']];
                $lastStart ??= $period['start'];
            }
            if (! $end) {
                $end = $period['end'] + ['field' => 'introduction', 'value' => $period['value']];
                $firstEnd ??= $period['end'];
            }
            $closed ??= $period['end'] + ['field' => 'introduction', 'value' => $period['value']];
        }
        $events = [];
        if ($single) {
            if ($start) {
                $events[] = ['type' => 'opening', 'date' => $start['date'], 'precision' => $start['precision'], 'confidence' => 'low', 'range' => false,
                    'pos' => 0, 'clause' => [0, 0], 'origin' => 'infobox', 'quote' => self::fieldQuote($start)];
            }
            if ($end && (! $start || $end['date'] > $start['date'])) {
                $events[] = ['type' => 'closure', 'date' => $end['date'], 'precision' => $end['precision'], 'confidence' => 'low', 'range' => false,
                    'pos' => 0, 'clause' => [0, 0], 'origin' => 'infobox', 'quote' => self::fieldQuote($end)];
            }
        }

        return ['start' => $start, 'end' => $end, 'events' => $events, 'last_start' => $lastStart,
            'spread' => $end && $firstEnd ? $end['year'] - $firstEnd['year'] : 0, 'period' => $period, 'closed' => $closed];
    }

    /** Citation d'une borne de réseau : champ d'infobox, ou phrase de l'introduction. */
    public static function fieldQuote(array $d): string
    {
        return $d['field'] === 'introduction' ? 'Introduction de l\'article : '.$d['value'] : 'Infobox : '.$d['field'].' = '.$d['value'];
    }

    // ------------------------------------------------------------------
    // Wikitexte → texte
    // ------------------------------------------------------------------

    /** Nettoyage global : commentaires, références, fichiers, clés de tri masquées, galeries. */
    private function prepare(string $w): string
    {
        $w = preg_replace('/<!--.*?-->/su', '', $w);
        $w = preg_replace('/<ref[^>]*\/>/iu', '', $w);
        $w = preg_replace('/<ref[^>]*>.*?<\/ref\s*>/isu', '', $w);
        $w = preg_replace('/<(gallery|timeline|math|imagemap|mapframe|graph|syntaxhighlight)[^>]*>.*?<\/\1\s*>/isu', '', $w);
        $w = preg_replace('/<span[^>]*display\s*:\s*none[^>]*>.*?<\/span>/isu', '', $w);
        $w = preg_replace('/\{\{\s*(?:sfn|harvsp|harv|sfrac|refnec|référence nécessaire|,|référence souhaitée|lien web|article|ouvrage)\b(?:[^{}]|\{\{[^{}]*\}\})*\}\}/iu', '', $w);

        return $this->stripFileLinks($w);
    }

    private function stripLeadingInfobox(string $w): string
    {
        if (! preg_match('/\{\{\s*Infobox/iu', $w, $m, PREG_OFFSET_CAPTURE)) {
            return $w;
        }
        $start = $m[0][1];
        $depth = 0;
        $len = strlen($w);
        for ($i = $start; $i < $len - 1; $i++) {
            $two = $w[$i].$w[$i + 1];
            if ($two === '{{') {
                $depth++;
                $i++;
            } elseif ($two === '}}') {
                $depth--;
                $i++;
                if ($depth === 0) {
                    return substr($w, 0, $start).substr($w, $i + 1);
                }
            }
        }

        return $w;
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

    /**
     * Fragment wiki → [texte brut, liens (libellé normalisé → cible)].
     *
     * @return array{0: string, 1: array<string, string>}
     */
    public function render(string $s): array
    {
        for ($i = 0; $i < 10 && str_contains($s, '{{'); $i++) {
            $new = preg_replace_callback('/\{\{((?:[^{}]|\{(?!\{)|\}(?!\}))*)\}\}/u', fn ($m) => $this->template($m[1]), $s);
            if ($new === null || $new === $s) {
                break;
            }
            $s = $new;
        }
        $links = [];
        $s = preg_replace('/\[(?:https?:)?\/\/[^\s\]]+\s+([^\]]*)\]/u', '$1', $s);
        $s = preg_replace('/\[(?:https?:)?\/\/[^\s\]]+\]/u', '', $s);
        $s = preg_replace_callback('/\[\[([^\[\]|]*)(?:\|([^\[\]]*))?\]\]/u', function ($m) use (&$links) {
            $target = trim(str_replace('_', ' ', $m[1]));
            if (preg_match('/^:?(Catégorie|Category|Portail|Projet|Modèle|Aide|Wikipédia|[a-z]{2,3}(-[a-z]+)?):/iu', $target)) {
                return '';
            }
            $label = isset($m[2]) && trim($m[2]) !== '' ? trim($m[2]) : preg_replace('/#.*$/u', '', $target);
            // « [[1891 dans les chemins de fer|1891]] » : simple date.
            if (! preg_match('/^\d{1,4}\b|^(?:'.self::MONTHS.')\b|dans les chemins de fer|en France$/iu', $target)) {
                $links[PlaceNames::normalize($label)] = $target;
            }

            return $label;
        }, $s);
        $s = str_replace(['[[', ']]'], '', $s);
        $s = preg_replace("/'{2,}/u", '', $s);
        $s = preg_replace('/<br\s*\/?>/iu', ' ', $s);
        $s = preg_replace('/<\/?[a-z][^>]*>/iu', '', $s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = str_replace(["\u{00A0}", "\u{202F}", "\u{2009}", "\u{2007}"], ' ', $s);
        $s = preg_replace('/[ \t]+/u', ' ', $s);

        return [trim($s), $links];
    }

    private function template(string $inner): string
    {
        $parts = array_map('trim', explode('|', $inner));
        $name = mb_strtolower(trim(str_replace('_', ' ', array_shift($parts))));
        $pos = [];
        $named = [];
        foreach ($parts as $p) {
            if (preg_match('/^([\p{L}\d _-]{1,25})=(.*)$/su', $p, $m) && ! preg_match('/^\d+$/', trim($m[1]))) {
                $named[mb_strtolower(trim($m[1]))] = trim($m[2]);
            } else {
                $pos[] = $p;
            }
        }
        if (in_array($name, ['date', 'date-', 'date sport', 'date-1', 'date républicaine', 'date julienne'], true)) {
            return $this->renderDate($pos);
        }

        return match (true) {
            $name === '1er' => '1er',
            in_array($name, ['er', 'e', 're', 'ème', 'eme', 'ers', 'es', 'res'], true) => $name,
            in_array($name, ['unité', 'nombre', 'nb'], true) => implode(' ', array_slice($pos, 0, 2)),
            in_array($name, ['nobr', 'nowrap', 'centrer', 'souligner', 'abréviation', 'abr', 'sp', 'petit', 'small', 'gras', 'italique',
                'guil', 'guillemets', 'noir', 'couleur', 'citation', 'm', 'nom', 'formatnum', 'num', 'ill', 'pas clair', 'clair'], true) => $pos[0] ?? '',
            in_array($name, ['tri', 'sort', 'trié', 'sortname'], true) => $pos[count($pos) - 1] ?? '',
            in_array($name, ['lang', 'langue'], true) => $pos[1] ?? ($pos[0] ?? ''),
            $name === 'lien' => $named['texte'] ?? $named['fr'] ?? ($pos[0] ?? ''),
            in_array($name, ['st', 'st.'], true) => 'St',
            in_array($name, ['ste', 'ste.'], true) => 'Ste',
            in_array($name, ['numéro', 'n°', 'nº', 'no'], true) => 'n° '.($pos[0] ?? ''),
            default => '',
        };
    }

    private function renderDate(array $pos): string
    {
        $pos = array_values(array_filter($pos, fn ($p) => $p !== ''));
        if (! $pos) {
            return '';
        }
        if (preg_match('/\s/u', $pos[0])) {
            return $pos[0];
        }
        $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $out = [];
        foreach (array_slice($pos, 0, 3) as $p) {
            if (! preg_match('/^(\d{1,4}|1er|'.self::MONTHS.')$/iu', $p)) {
                break;
            }
            $out[] = $p;
        }
        if (count($out) === 3 && ctype_digit($out[1]) && (int) $out[1] >= 1 && (int) $out[1] <= 12) {
            $out[1] = $months[(int) $out[1] - 1];
        }
        // {{date|02|05|1908}} : « 2 mai 1908 » (un jour à zéro initial ne serait pas lu).
        if (count($out) === 3 && ctype_digit($out[0]) && (int) $out[0] >= 1 && (int) $out[0] <= 31) {
            $out[0] = (string) (int) $out[0];
        }

        return implode(' ', $out);
    }

    /** Phrases d'un paragraphe (coupure sur « . » suivi d'une majuscule, hors abréviations). */
    private function sentences(string $s): array
    {
        $s = preg_replace('/\b(St|Ste|MM|M|Mme|Cie|cf|env|p|vol|n°|av|J\.-C)\.\s/u', '$1§ ', $s);
        $parts = preg_split('/(?<=[.!?])\s+(?=[\p{Lu}«])/u', $s);

        return array_map(fn ($p) => str_replace('§', '.', trim($p)), array_filter($parts, fn ($p) => trim($p) !== ''));
    }

    private function km(string $s): ?float
    {
        return preg_match('/(\d+(?:[.,]\d+)?)\s*km\b/u', $s, $m) ? (float) str_replace(',', '.', $m[1]) : null;
    }

    private function headingText(array $headings): string
    {
        return implode(' > ', array_map(fn ($h) => $h[0], $headings));
    }
}
