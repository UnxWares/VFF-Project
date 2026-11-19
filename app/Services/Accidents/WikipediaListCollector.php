<?php

namespace App\Services\Accidents;

use App\Enums\AccidentDatePrecision;
use App\Enums\AccidentKind;
use Illuminate\Support\Str;

/**
 * Accidents des pages « Liste d'accidents ferroviaires en France » de Wikipédia en français
 * (complément des éléments Wikidata et du bilan du BEA-TT : des centaines d'accidents anciens
 * sans article ni élément).
 *
 *   1. page d'accueil de la liste : titres des pages par décennie ou par année ;
 *   2. texte de chaque page, avec son numéro de révision ;
 *   3. faits de chaque puce (WikipediaListReader) : date, lieu cité, lignes citées, bilan, nature.
 *
 * Le lieu n'est connu qu'à la commune près : la fiche reste « à vérifier » (AccidentLocator). Le
 * lien de la source est le permalien de la révision lue, avec l'ancre de la section de la liste.
 * Aucun texte de la liste n'est repris ni conservé en base.
 */
final class WikipediaListCollector
{
    public const HOME = "Liste d'accidents ferroviaires en France";

    /** Page de l'année 1900, absente de l'accueil de la liste. */
    private const EXTRA_PAGES = ["Liste d'accidents ferroviaires en France en 1900"];

    public const LICENCE = 'CC BY-SA 4.0';

    public const LICENCE_URL = 'https://creativecommons.org/licenses/by-sa/4.0/deed.fr';

    public function __construct(private readonly WikipediaListReader $reader) {}

    /** @return list<AccidentCandidate> */
    public function collect(AccidentSourceClient $client): array
    {
        $home = $client->articleTexts('fr', [self::HOME])[self::HOME] ?? null;
        if ($home === null) {
            return [];
        }
        $titles = [...$this->pageTitles($home['content']), ...self::EXTRA_PAGES];
        $pages = $client->articleTexts('fr', array_values(array_unique($titles)));

        $out = [];
        $used = [];
        foreach ($titles as $title) {
            if (! isset($pages[$title])) {
                continue;
            }
            $page = $pages[$title];
            foreach ($this->reader->read($page['content']) as $entry) {
                $out[] = $this->candidate($entry, $page, $used);
            }
        }

        return $out;
    }

    /**
     * Titres des pages de décennie ou d'année liés depuis l'accueil de la liste.
     *
     * @return list<string>
     */
    public function pageTitles(string $homeWikitext): array
    {
        preg_match_all("/\\[\\[(Liste d(?:'|es )accidents ferroviaires en France (?:dans les années|en) \\d{4}s?)\\|/u", $homeWikitext, $m);

        return array_values(array_unique($m[1]));
    }

    /**
     * @param  array{date: string, precision: AccidentDatePrecision, anchor: ?string, places: list<array{0: string, 1: ?string}>, station: ?string, line_articles: list<string>, deaths: ?int, injuries: ?int, kind: ?AccidentKind, urban: bool}  $entry
     * @param  array{title: string, revid: int, content: string}  $page
     * @param  array<string, true>  $used  identifiants déjà pris (deux puces du même jour au même endroit)
     */
    private function candidate(array $entry, array $page, array &$used): AccidentCandidate
    {
        $place = $entry['places'][0][0] ?? $entry['station'];
        $base = 'fr:'.$entry['date'].'-'.Str::slug(Str::limit((string) $place, 40, ''));
        // Deux puces du même jour au même endroit se distinguent par leur rang.
        $key = $base;
        for ($i = 2; isset($used[$key]); $i++) {
            $key = "{$base}-{$i}";
        }
        $used[$key] = true;

        $c = new AccidentCandidate('wikipedia-list:'.$key);
        $c->date = $entry['date'];
        $c->precision = $entry['precision'];
        $c->dateSource = 'wikipedia';
        $c->kind = $entry['kind'];
        $c->kindSource = $entry['kind'] !== null ? 'wikipedia' : null;
        $c->urbanTransit = $entry['urban'];
        // La liste porte sur la France : un lieu introuvable reste « à vérifier » au lieu d'écarter la fiche.
        $c->declaredInFrance = true;
        $c->stopAtAmbiguousPlace = true;
        $c->note('place_from_list');
        if ($entry['places'] !== []) {
            [$c->communeName, $c->departmentCode] = $entry['places'][0];
            $c->placeHints = array_slice($entry['places'], 1);
        }
        $c->stationHint = $entry['station'];
        $c->lineArticles = $entry['line_articles'];

        $permalink = 'https://fr.wikipedia.org/w/index.php?title='.rawurlencode(str_replace(' ', '_', $page['title'])).'&oldid='.$page['revid'];
        $url = $permalink.($entry['anchor'] !== null ? '#'.rawurlencode($entry['anchor']) : '');
        if ($entry['deaths'] !== null) {
            $c->deaths['wikipedia'] = $entry['deaths'];
            $c->tollUrls['wikipedia'] = $url;
        }
        if ($entry['injuries'] !== null) {
            $c->injuries['wikipedia'] = $entry['injuries'];
        }
        $c->addSource(new AccidentSourceRef(
            provider: 'wikipedia',
            externalId: $key,
            role: 'list',
            title: $page['title'],
            url: $url,
            licence: self::LICENCE,
            licenceUrl: self::LICENCE_URL,
            revision: $page['revid'],
            facts: array_filter([
                'date' => $entry['date'], 'deaths' => $entry['deaths'], 'injuries' => $entry['injuries'],
                'place' => $place, 'department' => $entry['places'][0][1] ?? null,
                'line_articles' => $entry['line_articles'],
            ], fn ($v) => $v !== null && $v !== []),
        ));

        return $c;
    }
}
