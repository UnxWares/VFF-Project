<?php

namespace App\Services\Accidents;

/**
 * Listes d'accidents des Wikipédia en anglais et en allemand (accidents en France seulement) : une seconde
 * lecture, écrite par d'autres contributeurs et citant d'autres références (livres, presse), de la date et du
 * lieu des accidents que la liste de Wikipédia en français décrit déjà. Elle ne crée aucune fiche : elle sert
 * à recouper (AccidentImporter::attachEditions).
 *
 *   - anglais : « List of rail accidents (… ) » par décennie et « List of rail accidents in France » ;
 *   - allemand : « Liste schwerer Unfälle im Schienenverkehr ».
 *
 * Le lien de la source est le permalien de la révision lue, avec l'ancre de la section. Aucun texte n'est gardé.
 */
final class WikipediaEditionCollector
{
    public const LICENCE = 'CC BY-SA 4.0';

    public const LICENCE_URL = 'https://creativecommons.org/licenses/by-sa/4.0/';

    /** @var list<string> pages « List of rail accidents (…) » de Wikipédia en anglais (le tiret demi-cadratin des titres est écrit en séquence d'échappement) */
    public const EN_DECADES = [
        'List of rail accidents (before 1880)', "List of rail accidents (1880\u{2013}1889)", "List of rail accidents (1890\u{2013}1899)",
        "List of rail accidents (1900\u{2013}1909)", "List of rail accidents (1910\u{2013}1919)", "List of rail accidents (1920\u{2013}1929)",
        "List of rail accidents (1930\u{2013}1939)", "List of rail accidents (1940\u{2013}1949)", "List of rail accidents (1950\u{2013}1959)",
        "List of rail accidents (1960\u{2013}1969)", "List of rail accidents (1970\u{2013}1979)", "List of rail accidents (1980\u{2013}1989)",
        "List of rail accidents (1990\u{2013}1999)", "List of rail accidents (2000\u{2013}2009)", "List of rail accidents (2010\u{2013}2019)",
        "List of rail accidents (2020\u{2013}present)",
    ];

    public const EN_FRANCE = 'List of rail accidents in France';

    public const DE_LIST = 'Liste schwerer Unfälle im Schienenverkehr';

    /** Faux si une page demandée manquait (hors ligne) : les sources absentes ne sont alors pas retirées. */
    public bool $complete = true;

    public function __construct(private readonly WikipediaEditionListReader $reader) {}

    /** @return list<AccidentEditionEntry> */
    public function collect(AccidentSourceClient $client): array
    {
        $en = $client->articleTexts('en', [...self::EN_DECADES, self::EN_FRANCE]);
        $de = $client->articleTexts('de', [self::DE_LIST]);
        $this->complete = count($en) === count(self::EN_DECADES) + 1 && count($de) === 1;

        $out = [];
        foreach (self::EN_DECADES as $title) {
            if (isset($en[$title])) {
                $out = [...$out, ...$this->entries('en', $en[$title], $this->reader->readEnglish($en[$title]['content']))];
            }
        }
        if (isset($en[self::EN_FRANCE])) {
            $out = [...$out, ...$this->entries('en', $en[self::EN_FRANCE], $this->reader->readEnglishTable($en[self::EN_FRANCE]['content']))];
        }
        if (isset($de[self::DE_LIST])) {
            $out = [...$out, ...$this->entries('de', $de[self::DE_LIST], $this->reader->readGerman($de[self::DE_LIST]['content']))];
        }

        return $out;
    }

    /**
     * @param  'en'|'de'  $lang
     * @param  array{title: string, revid: int, content: string}  $page
     * @param  list<array{date: string, places: list<array{name: string, qualifier: ?string}>, referenced: bool, references: list<string>, anchor: ?string}>  $read
     * @return list<AccidentEditionEntry>
     */
    private function entries(string $lang, array $page, array $read): array
    {
        return array_map(fn (array $e): AccidentEditionEntry => new AccidentEditionEntry(
            $lang, $e['date'], $e['places'], $e['referenced'], $e['references'], $page['title'], $page['revid'], $e['anchor'],
        ), $read);
    }
}
