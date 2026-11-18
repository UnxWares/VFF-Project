<?php

namespace App\Services;

use App\Support\EventText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Chronique d'une ligne pour sa fiche : tous ses événements (rien n'est caché),
 * regroupés par portion, chacun avec sa note de source, et les désaccords entre
 * sources signalés.
 *
 *   - portion : « De A à B » (places normalisées : l'ordre et « St- / Saint- » ne
 *     comptent pas), « toute la ligne », ou « portion non précisée » ;
 *   - famille : ouverture, réouverture, fin du trafic voyageurs, fin du fret,
 *     fermeture totale, dépose, patrimoine, électrification, autre ;
 *   - statut : retained (date une phase de la ligne), agrees (même fait, même date
 *     qu'un événement retenu), conflict (même fait, autre date), unused (aucun
 *     changement de la carte ne lui correspond), ignored (antérieur à la grande vitesse
 *     sur une LGV), unlocated (portion citée mais non localisée) ;
 *   - notes : une par source distincte (source + lien), numérotées dans l'ordre
 *     d'apparition.
 */
final class LineChronicle
{
    /** Familles d'événements (un même fait rapporté par plusieurs sources). */
    public const FAMILIES = [
        'opening' => 'opening', 'opening_passenger' => 'opening', 'opening_freight' => 'opening',
        'reopening' => 'reopening', 'reopening_passenger' => 'reopening', 'reopening_freight' => 'reopening',
        'closure_passenger' => 'closure_passenger',
        'closure_freight' => 'closure_freight',
        'closure' => 'closure', 'declassification' => 'declassification',
        'dismantling' => 'dismantling', 'conversion' => 'dismantling',
        'heritage_start' => 'heritage', 'heritage_end' => 'heritage_end',
        'electrification' => 'electrification',
    ];

    /** Familles qui changent l'état de la carte (les autres sont du contexte : concession, accident…). */
    public const STATE_FAMILIES = ['opening', 'reopening', 'closure_passenger', 'closure_freight', 'closure', 'declassification', 'dismantling', 'heritage', 'heritage_end'];

    /** Sources dont chaque lien désigne un objet (chemin cartographique) : une seule note. */
    public const GROUPED_SOURCES = ['ohm', 'osm'];

    public static function family(string $type): string
    {
        return self::FAMILIES[$type] ?? 'other';
    }

    /** Clé de lieu insensible à la casse, aux accents, aux tirets et à « St- / Saint- ». */
    public static function placeKey(?string $place): ?string
    {
        if ($place === null || trim($place) === '') {
            return null;
        }
        $k = Str::lower(Str::ascii($place));
        $k = preg_replace(['/\bst\b\.?/', '/\bste\b\.?/'], ['saint', 'sainte'], $k);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', (string) $k));
    }

    /**
     * Clé de portion d'un événement : « whole », « unspecified », ou les lieux triés.
     *
     * @param  array{scope: string, section_from: ?string, section_to: ?string}  $e
     */
    public static function portionKey(array $e): string
    {
        if ($e['scope'] === 'whole') {
            return 'whole';
        }
        $places = array_values(array_unique(array_filter([self::placeKey($e['section_from']), self::placeKey($e['section_to'])])));
        if ($places === []) {
            return $e['scope'] === 'localized' ? 'traced' : 'unspecified';
        }
        sort($places);

        return implode('|', $places);
    }

    /**
     * Calcul pur.
     *
     * @param  list<array<string, mixed>>  $events  événements (id, type, date, precision, source, source_url,
     *                                              confidence, scope, section_from, section_to…), par date
     * @param  list<int>  $used  événements qui datent une phase (LinePhases)
     * @return array{events: list<array>, groups: list<array>, notes: list<array>, conflicts: list<array>}
     */
    public static function build(array $events, array $used): array
    {
        $used = array_flip($used);
        $notes = [];
        $out = [];
        foreach ($events as $e) {
            // Une note par source et par lien ; les objets cartographiques (un lien par chemin
            // OpenHistoricalMap / OpenStreetMap) sont regroupés en une seule note à plusieurs liens.
            $grouped = in_array($e['source'] ?? null, self::GROUPED_SOURCES, true);
            $noteKey = ($e['source'] ?? '?').'|'.($grouped ? '*' : ($e['source_url'] ?? ''));
            $notes[$noteKey] ??= ['n' => count($notes) + 1, 'source' => $e['source'] ?? null, 'url' => $grouped ? null : ($e['source_url'] ?? null), 'urls' => []];
            if ($grouped && ($e['source_url'] ?? null) && ! in_array($e['source_url'], $notes[$noteKey]['urls'], true)) {
                $notes[$noteKey]['urls'][] = $e['source_url'];
            }
            $e['family'] = self::family($e['type']);
            $e['portion_key'] = self::portionKey($e);
            $e['note'] = $notes[$noteKey]['n'];
            $e['status'] = match (true) {
                isset($used[$e['id']]) => 'retained',
                $e['scope'] === 'ignored' => 'ignored',
                $e['scope'] === 'unlocated' => 'unlocated',
                in_array($e['family'], self::STATE_FAMILIES, true) => 'unused',
                default => 'context',
            };
            $e['agrees_with'] = [];
            $e['conflicts_with'] = [];
            $out[$e['id']] = $e;
        }

        // Accords et désaccords : même portion (ou toute la ligne), même famille.
        $conflicts = [];
        $byFact = [];
        foreach ($out as $e) {
            // Seules une portion nommée par ses deux extrémités ou toute la ligne désignent à coup
            // sûr le même tronçon : « près de X » ou une portion sans nom peuvent être deux
            // tronçons différents (l'un finit à X, l'autre en part), rien à comparer.
            if (in_array($e['family'], self::STATE_FAMILIES, true) && $e['status'] !== 'ignored' && self::comparable($e['portion_key'])) {
                $byFact[$e['portion_key'].'#'.$e['family']][] = $e['id'];
            }
        }
        foreach ($byFact as $fact => $ids) {
            if (count($ids) < 2) {
                continue;
            }
            $years = [];
            foreach ($ids as $a) {
                foreach ($ids as $b) {
                    if ($a >= $b) {
                        continue;
                    }
                    if (self::sameDate($out[$a], $out[$b])) {
                        $out[$a]['agrees_with'][] = $b;
                        $out[$b]['agrees_with'][] = $a;
                    } else {
                        $out[$a]['conflicts_with'][] = $b;
                        $out[$b]['conflicts_with'][] = $a;
                    }
                }
                $years[substr($out[$a]['date'], 0, 4)] = true;
            }
            if (count($years) > 1) {
                [$portion, $family] = explode('#', $fact, 2);
                $conflicts[] = ['portion_key' => $portion, 'family' => $family, 'event_ids' => $ids];
            }
        }
        // Fermeture totale datée AVANT la fin d'un des services sur la même portion :
        // les sources se contredisent (« fermée en 1940 » mais « fermée au fret en 1954 »).
        foreach ($out as $c) {
            if ($c['family'] !== 'closure' || $c['status'] === 'ignored' || ! self::comparable($c['portion_key'])) {
                continue;
            }
            foreach ($out as $s) {
                if (in_array($s['family'], ['closure_passenger', 'closure_freight'], true) && $s['portion_key'] === $c['portion_key']
                    && substr($s['date'], 0, 4) > substr($c['date'], 0, 4)) {
                    $out[$c['id']]['conflicts_with'][] = $s['id'];
                    $out[$s['id']]['conflicts_with'][] = $c['id'];
                    $conflicts[] = ['portion_key' => $c['portion_key'], 'family' => 'closure_order', 'event_ids' => [$c['id'], $s['id']]];
                }
            }
        }
        foreach ($out as &$e) {
            $e['agrees_with'] = array_values(array_unique($e['agrees_with']));
            $e['conflicts_with'] = array_values(array_unique($e['conflicts_with']));
            if ($e['status'] === 'unused' && $e['agrees_with'] && array_filter($e['agrees_with'], fn ($id) => isset($used[$id]))) {
                $e['status'] = 'agrees';
            } elseif ($e['status'] === 'unused' && $e['conflicts_with']) {
                $e['status'] = 'conflict';
            }
        }
        unset($e);

        // Portions, dans l'ordre de leur premier événement.
        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];
        foreach ($out as $e) {
            $k = $e['portion_key'];
            if (! isset($groups[$k])) {
                $groups[$k] = ['key' => $k, 'from' => null, 'to' => null, 'length_m' => null, 'event_ids' => []];
            }
            $groups[$k]['from'] ??= $e['section_from'] ?? null;
            $groups[$k]['to'] ??= $e['section_to'] ?? null;
            $groups[$k]['length_m'] ??= $e['section_length_m'] ?? null;
            $groups[$k]['event_ids'][] = $e['id'];
        }
        // Toute la ligne d'abord, puis les portions nommées, puis le non localisé.
        uksort($groups, fn ($a, $b) => [self::groupRank($a), array_search($a, array_keys($groups), true)]
            <=> [self::groupRank($b), array_search($b, array_keys($groups), true)]);

        return [
            'events' => array_values($out),
            'groups' => array_values($groups),
            'notes' => array_values($notes),
            'conflicts' => $conflicts,
        ];
    }

    /** Portion désignée sans ambiguïté : toute la ligne, ou deux extrémités nommées. */
    public static function comparable(string $portionKey): bool
    {
        return $portionKey === 'whole' || str_contains($portionKey, '|');
    }

    private static function groupRank(string $key): int
    {
        return match ($key) {
            'whole' => 0,
            'traced' => 2,
            'unspecified' => 3,
            default => 1,
        };
    }

    /** Même date, à la précision commune (une année seule s'accorde avec un jour de cette année). */
    public static function sameDate(array $a, array $b): bool
    {
        $prec = in_array('year', [$a['precision'], $b['precision']], true) ? 4
            : (in_array('month', [$a['precision'], $b['precision']], true) ? 7 : 10);

        return substr($a['date'], 0, $prec) === substr($b['date'], 0, $prec);
    }

    /**
     * Lecture en base : événements complets de la ligne, portée (LinePhases::events).
     *
     * @param  list<int>  $used
     */
    public static function forLine(object $line, array $used): array
    {
        $scopes = LinePhases::events($line);
        $events = [];
        foreach (DB::select('SELECT id, type, event_date, date_precision, description, source, source_url, source_quote, confidence,
                    section_from, section_to, section_length_m, heritage_kind, reuse
                FROM line_events WHERE line_id = ? ORDER BY event_date, id', [$line->id]) as $r) {
            $events[] = [
                'id' => (int) $r->id,
                'type' => $r->type,
                'date' => substr((string) $r->event_date, 0, 10),
                'precision' => in_array($r->date_precision, ['day', 'month', 'year'], true) ? $r->date_precision : 'day',
                // Charte : pas de tiret long à l'affichage. Textes générés dans la langue de la page
                // (App\Support\EventText) ; *_lang = fr quand tout ou partie reste une citation en français.
                'description' => ($d = EventText::describe($r->source, $r->description === null ? null : self::dashes($r->description),
                    app()->getLocale(), $r->section_from, $r->section_to))['text'],
                'description_lang' => $d['lang'],
                'quote' => ($q = EventText::quote($r->source, $r->source_quote === null ? null : Str::limit(self::dashes($r->source_quote), 400),
                    app()->getLocale()))['text'],
                'quote_lang' => $q['lang'],
                'source' => $r->source,
                'source_url' => $r->source_url,
                'confidence' => $r->confidence,
                'section_from' => $r->section_from,
                'section_to' => $r->section_to,
                'section_length_m' => $r->section_length_m === null ? null : (int) $r->section_length_m,
                'heritage_kind' => $r->heritage_kind,
                'reuse' => $r->reuse,
                'scope' => $scopes[(int) $r->id]['scope'] ?? 'unlocated',
            ];
        }

        return self::build($events, $used);
    }

    /** Tirets longs et cadratins remplacés par un tiret simple (charte VFF). */
    public static function dashes(string $text): string
    {
        return (string) preg_replace(['/\s*[—―]\s*/u', '/\s+–\s+/u', '/--/'], [' - ', ' - ', '-'], $text);
    }
}
