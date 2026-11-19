<?php

namespace App\Services\Accidents;

use App\Enums\AccidentDatePrecision;
use App\Enums\AccidentLocationPrecision;

/**
 * Lecture d'un élément Wikidata d'accident (CC0) : date et précision (P585, à défaut P580),
 * coordonnées (P625), commune (P131 puis code INSEE P374 de l'élément visé), pays (P17), bilan
 * (P1120 morts, P1339 blessés, avec le lien de référence P854), exploitant (P137), véhicules
 * (P121, P1876), ligne (P81), libellés fr/en/de et articles de Wikipédia liés.
 */
final class WikidataAccidentReader
{
    public const LICENCE = 'CC0 1.0';

    public const LICENCE_URL = 'https://creativecommons.org/publicdomain/zero/1.0/';

    /**
     * France, France métropolitaine et régimes successifs (P17 des accidents anciens) : royaume,
     * monarchie de Juillet, IIe République, Second Empire, IIIe République, régime de Vichy,
     * IVe République.
     */
    public const FRANCE = ['Q142', 'Q212429', 'Q70972', 'Q58202', 'Q58326', 'Q71092', 'Q70802', 'Q69808', 'Q69829'];

    /** Classes des pages de liste, jamais un accident. */
    private const NOT_EVENTS = ['Q13406463'];

    public function __construct(private readonly AccidentKindClassifier $classifier) {}

    /**
     * @param  array<string, mixed>  $entity  réponse wbgetentities d'un élément
     * @param  array<string, array<string, mixed>>  $related  éléments cités (communes, véhicules, exploitants)
     * @return AccidentCandidate|string candidat, ou motif du rejet
     */
    public function read(array $entity, array $related): AccidentCandidate|string
    {
        $id = (string) ($entity['id'] ?? '');
        $claims = $entity['claims'] ?? [];
        $classes = $this->itemValues($claims['P31'] ?? []);
        if (array_intersect($classes, self::NOT_EVENTS) !== []) {
            return 'not_event';
        }
        [$date, $precision, $dateNote] = $this->date($claims['P585'] ?? $claims['P580'] ?? []);
        if ($date === null || $precision === null) {
            return 'no_date';
        }

        $c = new AccidentCandidate("wikidata:{$id}");
        $c->wikidataId = $id;
        $c->date = $date;
        $c->precision = $precision;
        $c->dateSource = 'wikidata';
        if ($dateNote !== null) {
            $c->note('date_ambiguous', $dateNote);
        }
        foreach (['fr', 'en', 'de'] as $lang) {
            if (isset($entity['labels'][$lang]['value'])) {
                $c->labels[$lang] = $this->label((string) $entity['labels'][$lang]['value'], $lang);
            }
        }
        $c->kind = $this->classifier->fromWikidataClasses($classes);
        $c->kindSource = $c->kind !== null ? 'wikidata' : null;
        $c->urbanTransit = array_intersect($classes, AccidentKindClassifier::URBAN_CLASSES) !== []
            || (bool) preg_match('/\b(?:métro|tramway)\b/iu', $c->labels['fr'] ?? '');

        $coord = $this->best($claims['P625'] ?? []);
        if ($coord !== null && isset($coord['mainsnak']['datavalue']['value']['latitude'])) {
            $c->lat = (float) $coord['mainsnak']['datavalue']['value']['latitude'];
            $c->lng = (float) $coord['mainsnak']['datavalue']['value']['longitude'];
        }
        $countries = $this->itemValues($claims['P17'] ?? []);
        $c->declaredInFrance = array_intersect($countries, self::FRANCE) !== [];
        $c->declaredElsewhere = $countries !== [] && ! $c->declaredInFrance;
        foreach ([...$this->itemValues($claims['P276'] ?? []), ...$this->itemValues($claims['P131'] ?? [])] as $place) {
            $insee = $this->stringValue($related[$place]['claims']['P374'] ?? []);
            if ($c->communeInsee === null && $insee !== null && preg_match('/^(?:\d{5}|2[AB]\d{3})$/', $insee)) {
                $c->communeInsee = $insee;
                $c->communeName = $this->labelOf($related[$place] ?? []);
            }
            // Lieu cité (gare, lieu-dit) qui a ses coordonnées : position de ce lieu.
            $placeCoord = $this->best($related[$place]['claims']['P625'] ?? [])['mainsnak']['datavalue']['value'] ?? null;
            if ($c->lat === null && $insee === null && isset($placeCoord['latitude'], $placeCoord['longitude'])) {
                $c->lat = (float) $placeCoord['latitude'];
                $c->lng = (float) $placeCoord['longitude'];
                $c->locationPrecision = AccidentLocationPrecision::Station;
                $c->locationSource = 'wikidata';
            }
        }
        if ($c->communeInsee === null) {
            foreach ($this->placesFromLabels($c->labels) as $hint) {
                $c->placeHints[] = [$hint, null];
            }
        }

        foreach (['P1120' => 'deaths', 'P1339' => 'injuries'] as $prop => $field) {
            [$n, $url, $ambiguous] = $this->quantity($claims[$prop] ?? []);
            // Plusieurs valeurs au même rang (52 et 200 morts à Meudon) : aucune n'est retenue,
            // le bilan vient d'une autre source et la fiche est à vérifier.
            if ($ambiguous !== null) {
                $c->note('toll_ambiguous', "Wikidata {$prop} : {$ambiguous}");
            } elseif ($n !== null) {
                $c->{$field}['wikidata'] = $n;
                if ($url !== null && $field === 'deaths') {
                    $c->tollUrls['wikidata'] = $url;
                }
            }
        }
        foreach ($this->itemValues($claims['P137'] ?? []) as $op) {
            if (($label = $this->labelOf($related[$op] ?? [])) !== null) {
                $c->operators[] = $label;
            }
        }
        foreach ([...$this->itemValues($claims['P121'] ?? []), ...$this->itemValues($claims['P1876'] ?? [])] as $vehicle) {
            if (($label = $this->labelOf($related[$vehicle] ?? [])) !== null) {
                $c->addTrain(['label' => null, 'category' => null, 'number' => null, 'material' => $label, 'operator' => null], 'wikidata');
            }
        }
        $c->lineItems = $this->itemValues($claims['P81'] ?? []);

        foreach (['fr', 'en', 'de'] as $lang) {
            $title = $entity['sitelinks']["{$lang}wiki"]['title'] ?? null;
            if (is_string($title)) {
                $c->wikipedia[$lang] = [
                    'title' => $title,
                    'url' => "https://{$lang}.wikipedia.org/wiki/".str_replace(' ', '_', $title),
                    'revid' => null,
                ];
            }
        }

        $c->addSource(new AccidentSourceRef(
            provider: 'wikidata',
            externalId: $id,
            role: 'data',
            title: $c->labels['fr'] ?? $c->labels['en'] ?? $id,
            url: "https://www.wikidata.org/wiki/{$id}",
            licence: self::LICENCE,
            licenceUrl: self::LICENCE_URL,
            revision: isset($entity['lastrevid']) ? (int) $entity['lastrevid'] : null,
            facts: array_filter([
                'date' => $date, 'date_precision' => $precision->value,
                'deaths' => $c->deaths['wikidata'] ?? null, 'injuries' => $c->injuries['wikidata'] ?? null,
                'lat' => $c->lat, 'lng' => $c->lng, 'commune_insee' => $c->communeInsee,
                'classes' => $classes,
            ], fn ($v) => $v !== null && $v !== []),
        ));

        return $c;
    }

    /**
     * Identifiants des éléments cités par l'élément (communes, lieux, exploitants, véhicules,
     * lignes) : à charger avant read().
     *
     * @param  array<string, mixed>  $entity
     * @return list<string>
     */
    public function relatedIds(array $entity): array
    {
        $ids = [];
        foreach (['P131', 'P276', 'P137', 'P121', 'P1876'] as $prop) {
            array_push($ids, ...$this->itemValues($entity['claims'][$prop] ?? []));
        }

        return array_values(array_unique($ids));
    }

    /**
     * Lieux nommés par les libellés : « accident ferroviaire de Sarry », « Eisenbahnunfall von
     * Revigny-sur-Ornain », « 1911 Müllheim rail accident ». Simples indices, vérifiés ensuite
     * dans la table des communes.
     *
     * @param  array<string, string>  $labels
     * @return list<string>
     */
    public function placesFromLabels(array $labels): array
    {
        $out = [];
        $patterns = [
            'fr' => "/\\b(?:de|d['’]|du|à)\\s*((?:la |le |les |l['’])?\\p{Lu}[^()]*?)(?:\\s+(?:le|du|en)\\s+\\d.*)?$/u",
            'de' => '/\\b(?:von|bei|in)\\s+(\\p{Lu}[^()]*?)(?:\\s+\\(\\d.*)?$/u',
            'en' => '/^(?:\\d{4}\\s+)?(\\p{Lu}[^()]*?)\\s+(?:rail|train|railway)\\s+(?:accident|crash|disaster|collision)/u',
        ];
        foreach ($patterns as $lang => $pattern) {
            if (isset($labels[$lang]) && preg_match($pattern, $labels[$lang], $m)) {
                $out[] = trim($m[1]);
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Libellé tel qu'affiché : Wikidata écrit les noms d'événements en minuscule initiale en
     * français (« accident ferroviaire de … ») ; la fiche les affiche comme un titre.
     */
    private function label(string $label, string $lang): string
    {
        return $lang === 'fr' || $lang === 'en' ? mb_strtoupper(mb_substr($label, 0, 1)).mb_substr($label, 1) : $label;
    }

    /**
     * Date d'une déclaration temporelle : rang « préféré » d'abord, puis la plus précise.
     * Plusieurs valeurs contradictoires au même rang sont signalées.
     *
     * @param  list<array<string, mixed>>  $claims
     * @return array{0: ?string, 1: ?AccidentDatePrecision, 2: ?string}
     */
    public function date(array $claims): array
    {
        $values = [];
        foreach ($this->ranked($claims) as $claim) {
            $v = $claim['mainsnak']['datavalue']['value'] ?? null;
            if (! is_array($v) || ! isset($v['time'], $v['precision'])) {
                continue;
            }
            $precision = AccidentDatePrecision::fromWikidata((int) $v['precision']);
            if ($precision === null || ! preg_match('/^\+(\d{4})-(\d{2})-(\d{2})/', (string) $v['time'], $m)) {
                continue;
            }
            $date = match ($precision) {
                AccidentDatePrecision::Day => "{$m[1]}-{$m[2]}-{$m[3]}",
                AccidentDatePrecision::Month => "{$m[1]}-{$m[2]}-01",
                AccidentDatePrecision::Year => "{$m[1]}-01-01",
            };
            $values[] = [$date, $precision, $claim['rank'] ?? 'normal'];
        }
        if ($values === []) {
            return [null, null, null];
        }
        $topRank = $values[0][2];
        $same = array_values(array_filter($values, fn ($v) => $v[2] === $topRank));
        usort($same, fn ($a, $b) => $b[1]->rank() <=> $a[1]->rank());
        $distinct = array_unique(array_map(fn ($v) => $v[0], $same));
        $note = count($distinct) > 1 ? 'dates citées : '.implode(', ', $distinct) : null;

        return [$same[0][0], $same[0][1], $note];
    }

    /**
     * Quantité (bilan) : rang « préféré » d'abord ; plusieurs valeurs au même rang sont rendues
     * dans le troisième élément (la plus grande en premier élément, pour information). Lien de la
     * première référence (P854) quand il existe.
     *
     * @param  list<array<string, mixed>>  $claims
     * @return array{0: ?int, 1: ?string, 2: ?string}
     */
    public function quantity(array $claims): array
    {
        $values = [];
        $url = null;
        $topRank = null;
        foreach ($this->ranked($claims) as $claim) {
            $amount = $claim['mainsnak']['datavalue']['value']['amount'] ?? null;
            if (! is_string($amount) || ! is_numeric($amount)) {
                continue;
            }
            $topRank ??= $claim['rank'] ?? 'normal';
            if (($claim['rank'] ?? 'normal') !== $topRank) {
                continue;
            }
            $values[] = (int) round((float) $amount);
            $url ??= $claim['references'][0]['snaks']['P854'][0]['datavalue']['value'] ?? null;
        }
        if ($values === []) {
            return [null, null, null];
        }
        $distinct = array_values(array_unique($values));

        return [max($values), is_string($url) ? $url : null, count($distinct) > 1 ? implode(', ', $distinct) : null];
    }

    /**
     * Déclarations hors rang « déconseillé », les préférées d'abord.
     *
     * @param  list<array<string, mixed>>  $claims
     * @return list<array<string, mixed>>
     */
    private function ranked(array $claims): array
    {
        $kept = array_values(array_filter($claims, fn ($c) => ($c['rank'] ?? 'normal') !== 'deprecated'));
        usort($kept, fn ($a, $b) => (($b['rank'] ?? '') === 'preferred') <=> (($a['rank'] ?? '') === 'preferred'));

        return $kept;
    }

    /**
     * @param  list<array<string, mixed>>  $claims
     * @return array<string, mixed>|null
     */
    private function best(array $claims): ?array
    {
        return $this->ranked($claims)[0] ?? null;
    }

    /**
     * @param  list<array<string, mixed>>  $claims
     * @return list<string>
     */
    private function itemValues(array $claims): array
    {
        $out = [];
        foreach ($this->ranked($claims) as $claim) {
            $id = $claim['mainsnak']['datavalue']['value']['id'] ?? null;
            if (is_string($id)) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    /** @param list<array<string, mixed>> $claims */
    private function stringValue(array $claims): ?string
    {
        $v = $this->best($claims)['mainsnak']['datavalue']['value'] ?? null;

        return is_string($v) ? $v : null;
    }

    /** @param array<string, mixed> $entity */
    private function labelOf(array $entity): ?string
    {
        $label = $entity['labels']['fr']['value'] ?? $entity['labels']['en']['value'] ?? null;

        return is_string($label) ? $label : null;
    }
}
