<?php

namespace App\Services\Accidents;

/**
 * Accidents de Wikidata situés en France, complétés par leur article de Wikipédia en français.
 *
 *   1. éléments candidats : instances d'« accident ferroviaire » (Q1078765) et de ses
 *      sous-classes déclarés en France (P17) ou dont les coordonnées tombent dans l'emprise de la
 *      métropole (le contour des communes tranche ensuite), plus les éléments des articles des
 *      catégories « Accident ferroviaire en France » et « Attentat ferroviaire en France » ;
 *   2. lecture des éléments (WikidataAccidentReader) et des éléments qu'ils citent ;
 *   3. faits de l'article de Wikipédia (WikipediaAccidentReader) : recoupement de la date et du
 *      bilan, coordonnées manquantes, nature, train, lignes citées.
 */
final class WikidataAccidentCollector
{
    public const CATEGORIES = [
        'Catégorie:Accident ferroviaire en France',
        'Catégorie:Attentat ferroviaire en France',
    ];

    public const WIKIPEDIA_LICENCE = 'CC BY-SA 4.0';

    public const WIKIPEDIA_LICENCE_URL = 'https://creativecommons.org/licenses/by-sa/4.0/deed.fr';

    /** Emprise de la France métropolitaine et de la Corse (premier tri, avant les contours). */
    private const BBOX = ['lat' => [41.3, 51.2], 'lng' => [-5.3, 9.7]];

    private const SPARQL = <<<'SPARQL'
        SELECT ?i (SAMPLE(?country) AS ?c) (SAMPLE(?coord) AS ?co) WHERE {
          ?t wdt:P279* wd:Q1078765 . ?i wdt:P31 ?t .
          OPTIONAL { ?i wdt:P17 ?country } OPTIONAL { ?i wdt:P625 ?coord }
        } GROUP BY ?i
        SPARQL;

    /** @var array<string, int> éléments écartés, par motif */
    public array $rejected = [];

    public function __construct(
        private readonly WikidataAccidentReader $reader,
        private readonly WikipediaAccidentReader $wikipedia,
        private readonly AccidentKindClassifier $classifier,
        private readonly BeaTtTitleReader $titles,
    ) {}

    /** @return list<AccidentCandidate> */
    public function collect(AccidentSourceClient $client): array
    {
        $ids = $this->candidateIds($client);
        $entities = $client->entities($ids);
        $relatedIds = [];
        foreach ($entities as $entity) {
            array_push($relatedIds, ...$this->reader->relatedIds($entity));
        }
        $related = $client->entities(array_values(array_unique($relatedIds)), withSitelinks: false);

        $out = [];
        foreach ($entities as $entity) {
            $c = $this->reader->read($entity, $related);
            if (is_string($c)) {
                $this->rejected[$c] = ($this->rejected[$c] ?? 0) + 1;

                continue;
            }
            $out[] = $c;
        }
        $this->enrichFromWikipedia($client, $out);

        return $out;
    }

    /**
     * Éléments candidats : requête SPARQL triée sur le pays ou l'emprise, puis catégories.
     *
     * @return list<string>
     */
    public function candidateIds(AccidentSourceClient $client): array
    {
        $ids = [];
        foreach ($client->sparql(self::SPARQL, 'accidents-ferroviaires') as $row) {
            $id = basename($row['i']['value'] ?? '');
            $country = basename($row['c']['value'] ?? '');
            $inBox = false;
            if (preg_match('/^Point\(([-\d.eE]+) ([-\d.eE]+)\)$/', $row['co']['value'] ?? '', $m)) {
                [$lng, $lat] = [(float) $m[1], (float) $m[2]];
                $inBox = $lat >= self::BBOX['lat'][0] && $lat <= self::BBOX['lat'][1]
                    && $lng >= self::BBOX['lng'][0] && $lng <= self::BBOX['lng'][1];
            }
            if (in_array($country, WikidataAccidentReader::FRANCE, true) || $inBox) {
                $ids[$id] = true;
            }
        }
        foreach (self::CATEGORIES as $category) {
            foreach ($client->categoryItems('fr', $category) as $qid) {
                $ids[$qid] = true;
            }
        }
        $ids = array_keys($ids);
        sort($ids, SORT_NATURAL);

        return $ids;
    }

    /** @param list<AccidentCandidate> $candidates */
    private function enrichFromWikipedia(AccidentSourceClient $client, array $candidates): void
    {
        $titles = [];
        foreach ($candidates as $c) {
            if (isset($c->wikipedia['fr'])) {
                $titles[] = $c->wikipedia['fr']['title'];
            }
        }
        $leads = $client->articleLeads('fr', $titles);
        foreach ($candidates as $c) {
            $title = $c->wikipedia['fr']['title'] ?? null;
            if ($title !== null && isset($leads[$title])) {
                $this->applyArticle($c, $leads[$title]);
            }
            if ($c->kind === null) {
                // Dernier recours : le libellé (« Collision ferroviaire de Charenton »).
                $c->kind = $this->classifier->fromFrenchText($c->labels['fr'] ?? null);
                $c->kindSource = $c->kind !== null ? 'label' : null;
            }
        }
    }

    /**
     * Faits d'un article : source citée (permalien de la révision lue), recoupement.
     *
     * @param  array{title: string, revid: int, content: string}  $lead
     */
    public function applyArticle(AccidentCandidate $c, array $lead): void
    {
        $facts = $this->wikipedia->read($lead['content']);
        $permalink = 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $lead['title'])).'&oldid='.$lead['revid'];
        $c->wikipedia['fr']['revid'] = $lead['revid'];

        if ($facts['date'] !== null && $facts['precision'] !== null) {
            if ($c->precision === null || $facts['precision']->rank() > $c->precision->rank()) {
                if ($c->date !== null && ! str_starts_with($facts['date'], substr($c->date, 0, 4))) {
                    $c->note('date_mismatch', "Wikidata {$c->date}, Wikipédia {$facts['date']}");
                }
                $c->date = $facts['date'];
                $c->precision = $facts['precision'];
                $c->dateSource = 'wikipedia';
            } elseif ($c->date !== null && $facts['precision'] === $c->precision && $facts['date'] !== $c->date) {
                $c->note('date_mismatch', "Wikidata {$c->date}, Wikipédia {$facts['date']}");
            }
        }
        if ($facts['deaths'] !== null) {
            $c->deaths['wikipedia'] = $facts['deaths'];
            $c->tollUrls['wikipedia'] = $permalink;
        }
        if ($facts['injuries'] !== null) {
            $c->injuries['wikipedia'] = $facts['injuries'];
        }
        if ($facts['deaths_approx'] || $facts['injuries_approx']) {
            $c->note('toll_approximate', 'Wikipédia');
        }
        if (($c->lat === null || $c->lng === null) && $facts['lat'] !== null && $facts['lng'] !== null) {
            $c->lat = $facts['lat'];
            $c->lng = $facts['lng'];
            $c->locationSource = 'wikipedia';
        }
        if ($c->kind === null && ($kind = $this->classifier->fromFrenchText($facts['type'])) !== null) {
            $c->kind = $kind;
            $c->kindSource = 'wikipedia';
        }
        foreach ($facts['trains'] as $train) {
            $trains = $this->titles->trains($train);
            foreach ($trains !== [] ? $trains : [['label' => null, 'category' => null, 'number' => null, 'material' => $train, 'operator' => null]] as $t) {
                $c->addTrain($t, 'wikipedia');
            }
        }
        foreach ($facts['operators'] as $operator) {
            if (! in_array($operator, $c->operators, true)) {
                $c->operators[] = $operator;
            }
        }
        $c->time ??= $facts['time'];
        // Sans coordonnées : lieu cité par l'infobox (gare ou commune) et département.
        if ($c->lat === null && $c->communeInsee === null) {
            $c->departmentCode ??= $facts['department'];
            foreach ($facts['site_links'] as $link) {
                if (preg_match("/^Gare (?:de |d['’]|du |des )(.+)$/u", $link, $m)) {
                    $c->stationHint ??= trim(preg_replace('/\s*\(.*\)$/u', '', $m[1]) ?? $m[1]);
                    array_unshift($c->placeHints, [$c->stationHint, $facts['department']]);
                } else {
                    array_unshift($c->placeHints, [trim(preg_replace('/\s*\(.*\)$/u', '', $link) ?? $link), $facts['department']]);
                }
            }
        }
        $c->lineArticles = array_values(array_unique([...$c->lineArticles, ...$facts['line_articles']]));

        $c->addSource(new AccidentSourceRef(
            provider: 'wikipedia',
            externalId: 'fr:'.$lead['title'],
            role: 'article',
            title: $lead['title'],
            url: $permalink,
            licence: self::WIKIPEDIA_LICENCE,
            licenceUrl: self::WIKIPEDIA_LICENCE_URL,
            revision: $lead['revid'],
            facts: array_filter([
                'date' => $facts['date'], 'date_precision' => $facts['precision']?->value,
                'deaths' => $facts['deaths'], 'injuries' => $facts['injuries'],
                'lat' => $facts['lat'], 'lng' => $facts['lng'], 'type' => $facts['type'],
                'trains' => $facts['trains'], 'operators' => $facts['operators'], 'time' => $facts['time'],
                'site' => $facts['site_links'], 'department' => $facts['department'],
                'line_articles' => $facts['line_articles'],
            ], fn ($v) => $v !== null && $v !== []),
        ));
    }
}
