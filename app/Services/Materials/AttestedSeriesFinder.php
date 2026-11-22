<?php

namespace App\Services\Materials;

use Illuminate\Support\Facades\DB;

/**
 * Séries que Wikidata ne rattache pas à la France (ICE, AVE, locomotives belges, suisses ou allemandes)
 * mais dont une source atteste la circulation sur une ligne française :
 *
 *   1. un article Wikipédia d'une ligne de VFF cite l'article d'une série dans une section utile
 *      (« Matériel roulant », « Exploitation »…), avec les mêmes garde-fous que les liens du reste de
 *      l'import (futur, conditionnel, projet et négation écartés) ;
 *   2. Wikidata déclare la série comme « véhicule habituellement utilisé » (P3438) d'une ligne
 *      française.
 *
 * Seules comptent les séries au sens de Wikidata (instances d'une sous-classe de « classe de matériel
 * roulant ferroviaire ») : une gare, une ville ou une compagnie citée dans la même section ne passe pas.
 * Le nom rapproché ne suffit jamais : seul un lien vers l'article, ou un élément Wikidata, est retenu.
 */
final class AttestedSeriesFinder
{
    public function __construct(
        private readonly WikidataMaterialSource $wikidata,
        private readonly WikipediaArticleSource $wikipedia,
    ) {}

    /**
     * Séries attestées, hors de celles que Wikidata rattache déjà à la France.
     *
     * @param  list<string>  $franceQids  séries rattachées à la France (`candidates()`)
     * @return array<string, array{source: string, ref: string}> attestation (la première trouvée) par identifiant Wikidata
     */
    public function find(array $franceQids, bool $refresh = false): array
    {
        $known = array_flip($franceQids);
        $found = [];

        // 1. Liens posés dans les articles des lignes.
        $linesByTitle = [];
        foreach (DB::table('lines')->whereNotNull('wikipedia_title')->orderBy('id')->pluck('wikipedia_title') as $title) {
            $linesByTitle[WikipediaMaterialSections::normalizeTitle((string) $title)] = true;
        }
        $pages = $this->wikipedia->sections('wikipedia_lines', array_keys($linesByTitle), WikipediaMaterialSections::LINE_SECTIONS, $refresh);
        $citedBy = [];
        foreach ($pages as $page) {
            if ($page === null) {
                continue;
            }
            foreach ($page['sections'] as $section) {
                if (WikipediaMaterialSections::isDoubtfulSection($section['title'])) {
                    continue;
                }
                foreach (WikipediaMaterialSections::links($section['text']) as $link) {
                    if (! isset($linesByTitle[$link['target']])) {
                        $citedBy[$link['target']] ??= $page['title'];
                    }
                }
            }
        }
        $qidByTitle = $this->wikipedia->wikidataIds('wikipedia_qids', array_keys($citedBy), $refresh);
        $viaArticle = [];
        foreach ($citedBy as $target => $article) {
            $qid = $qidByTitle[$target] ?? null;
            if ($qid !== null && ! isset($known[$qid])) {
                $viaArticle[$qid] ??= ['source' => 'wikipedia', 'ref' => $article];
            }
        }

        // 2. Véhicule habituellement utilisé (P3438) d'une ligne française.
        $viaWikidata = [];
        foreach ($this->wikidata->vehicleUsage($refresh) as $usage) {
            if (! isset($known[$usage['material']])) {
                $viaWikidata[$usage['material']] ??= ['source' => 'wikidata', 'ref' => $usage['subject'].'#P3438'];
            }
        }

        $candidates = $viaWikidata + $viaArticle;
        $series = $this->wikidata->seriesAmong(array_keys($candidates), $refresh);
        foreach ($candidates as $qid => $attestation) {
            if ($series[$qid] ?? false) {
                $found[$qid] = $attestation;
            }
        }
        ksort($found, SORT_NATURAL);

        return $found;
    }
}
