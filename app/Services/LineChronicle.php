<?php

namespace App\Services;

use App\Http\Resources\MediaResource;
use App\Models\LineEvent;
use App\Models\Media;
use App\Support\EventText;
use App\Support\FrenchGrammar;
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
 *
 * Présentation (chantier « UI unifiée », 2026-11) :
 *   - noms de lieux écrits comme dans la liste des gares de la ligne (« Saillat-Chassenon »,
 *     pas « Saillat - Chassenon » ; une coquille de la source à une lettre près, « Thenellières »
 *     pour « Thennelières », rejoint la gare et donc la même portion) ;
 *   - portions rangées le long de la ligne (PK de leurs gares), orientées dans le sens des PK,
 *     chacune avec un titre rédigé dans la langue de la page (`title` : « D’Ambert à Darsac »,
 *     « Du Puley à Genouilly ») et une longueur cohérente avec la liste des gares (écart de PK
 *     de ses deux gares quand ils sont connus ; toute la ligne : la longueur de l'en-tête) ;
 *   - événement : `section_text` (« d’Ambert à Arlanc », « près d’Évry »), description sans la
 *     parenthèse « correction sourcée » (raison dans `correction`), citation rédigée
 *     (`quote_kind` : data ou citation) ; fait d'une autre ligne gardé pour mémoire : statut
 *     `other_line` ;
 *   - `statuses` : statuts présents dans la chronique (légende sans entrée inutile).
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
     * @param  array<string, int>  $pkByPlace  placeKey d'une gare de la ligne → son PK (rangement, longueur des portions)
     * @param  ?int  $lineLengthM  longueur de la ligne (portion « toute la ligne »)
     * @param  ?string  $locale  langue des titres de portion (null : pas de titre)
     * @return array{events: list<array>, groups: list<array>, notes: list<array>, conflicts: list<array>, statuses: list<string>}
     */
    public static function build(array $events, array $used, array $pkByPlace = [], ?int $lineLengthM = null, ?string $locale = null): array
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
                ($e['other_line'] ?? false) => 'other_line',
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
        // Longueur et sens d'une portion nommée : PK de ses deux gares quand ils sont connus (même
        // mesure que la liste des gares ; la longueur résolue de l'événement peut venir d'un autre
        // tronçon de même nom), sinon celle de l'événement. Toute la ligne : longueur de l'en-tête.
        $order = array_flip(array_keys($groups));
        foreach ($groups as $k => &$g) {
            $g['pk_min'] = null;
            if ($k === 'whole' && $lineLengthM !== null) {
                $g['length_m'] = $lineLengthM;
            }
            $pa = $g['from'] !== null ? ($pkByPlace[self::placeKey($g['from'])] ?? null) : null;
            $pb = $g['to'] !== null ? ($pkByPlace[self::placeKey($g['to'])] ?? null) : null;
            if ($pa !== null && $pb !== null && self::comparable($k)) {
                if ($pa > $pb) {
                    [$g['from'], $g['to'], $pa, $pb] = [$g['to'], $g['from'], $pb, $pa];
                }
                $g['length_m'] = $pb - $pa;
                $g['pk_min'] = $pa;
            } elseif ($pa !== null || $pb !== null) {
                $g['pk_min'] = $pa ?? $pb;
            }
            // Lieux sans gare de la ligne (portions OpenHistoricalMap d'une LGV) : position de leurs
            // événements le long de la ligne (PK de la source, sinon de la gare la plus proche).
            if ($g['pk_min'] === null) {
                $hints = array_filter(array_map(fn ($id) => $out[$id]['pk_hint'] ?? null, $g['event_ids']), fn ($v) => $v !== null);
                $g['pk_min'] = $hints === [] ? null : min($hints);
            }
        }
        unset($g);
        // Toute la ligne d'abord, puis les portions nommées le long de la ligne (PK), puis celles
        // désignées par un seul lieu, puis le non localisé ; sans PK : ordre du premier événement.
        uksort($groups, fn ($a, $b) => [self::groupRank($a), $groups[$a]['pk_min'] === null ? 1 : 0, $groups[$a]['pk_min'] ?? 0, $order[$a]]
            <=> [self::groupRank($b), $groups[$b]['pk_min'] === null ? 1 : 0, $groups[$b]['pk_min'] ?? 0, $order[$b]]);
        $groups = array_map(function (array $g) use ($locale) {
            unset($g['pk_min']);

            return $g + ['title' => $locale === null ? null : self::groupTitle($g, $locale)];
        }, $groups);

        $statuses = array_values(array_unique(array_column($out, 'status')));
        usort($statuses, fn ($a, $b) => array_search($a, self::STATUSES, true) <=> array_search($b, self::STATUSES, true));

        return [
            'events' => array_values($out),
            'groups' => array_values($groups),
            'notes' => array_values($notes),
            'conflicts' => $conflicts,
            'statuses' => $statuses,
        ];
    }

    /** Statuts d'un événement, dans l'ordre de la légende. */
    public const STATUSES = ['retained', 'agrees', 'conflict', 'unused', 'ignored', 'unlocated', 'context', 'other_line'];

    /**
     * Titre d'une portion dans une langue : « Toute la ligne », « D’Ambert à Darsac », « Du Puley à
     * Genouilly », « Près d’Évry » (élisions et contractions du français, cf. FrenchGrammar).
     *
     * @param  array{key: string, from: ?string, to: ?string}  $g
     */
    public static function groupTitle(array $g, string $locale): string
    {
        $t = fn (string $key, array $p = []) => (string) trans('lines.'.$key, $p, $locale);

        return match (true) {
            $g['key'] === 'whole' => $t('chronicle.whole_line'),
            $g['key'] === 'traced' => $t('chronicle.traced'),
            $g['key'] === 'unspecified' => $t('chronicle.unspecified'),
            default => self::placeText($g['from'], $g['to'], $locale, true) ?? $t('chronicle.unspecified'),
        };
    }

    /**
     * Portion d'un événement en toutes lettres : « de Troyes à Brienne-le-Château », « d’Ambert à
     * Arlanc », « près d’Évry » (titre : capitale initiale) ; null sans lieu.
     */
    public static function placeText(?string $from, ?string $to, string $locale, bool $capital = false): ?string
    {
        $from = $from !== null && trim($from) !== '' ? trim($from) : null;
        $to = $to !== null && trim($to) !== '' ? trim($to) : null;
        if ($from === null && $to === null) {
            return null;
        }
        if ($from !== null && $to !== null && self::placeKey($from) !== self::placeKey($to)) {
            return $locale === 'fr' ? FrenchGrammar::between($from, $to, $capital)
                : (string) trans('lines.event_section.'.($capital ? 'between' : 'between_inline'), ['from' => $from, 'to' => $to], $locale);
        }
        $place = $from ?? $to;

        return $locale === 'fr' ? FrenchGrammar::near((string) $place, $capital)
            : (string) trans('lines.event_section.'.($capital ? 'near' : 'near_inline'), ['place' => $place], $locale);
    }

    /**
     * Nom de lieu d'une source écrit comme la gare de la ligne qu'il désigne : même clé
     * (placeKey : casse, accents, tirets, « St- / Saint- »), ou coquille d'une lettre (deux pour
     * un nom de dix lettres et plus) quand une seule gare est aussi proche. Sinon inchangé.
     *
     * @param  array<string, string>  $stations  placeKey → nom de la gare
     */
    public static function stationName(?string $place, array $stations): ?string
    {
        $key = self::placeKey($place);
        if ($key === null) {
            return $place;
        }
        if (isset($stations[$key])) {
            return $stations[$key];
        }
        $len = strlen($key);
        if ($len < 6) {
            return $place;
        }
        $max = $len >= 10 ? 2 : 1;
        $hits = [];
        foreach ($stations as $k => $name) {
            if (abs(strlen($k) - $len) <= $max && levenshtein($k, $key) <= $max) {
                $hits[] = $name;
            }
        }

        return count(array_unique($hits)) === 1 ? $hits[0] : $place;
    }

    /**
     * Fait d'une AUTRE ligne gardé dans la chronique pour mémoire (correction sourcée qui le
     * neutralise : « Ouverture d'une autre ligne de l'itinéraire… », « Section de la ligne 433000 »).
     */
    public static function isOtherLine(string $type, ?string $description, ?string $lineCode): bool
    {
        if ($type !== 'other' || $description === null) {
            return false;
        }
        if (preg_match('/\bautres? lignes?\b/u', $description)) {
            return true;
        }

        return preg_match('/^Section de la ligne (\d{3,6})\b/u', $description, $m) === 1
            && ltrim($m[1], '0') !== ltrim((string) $lineCode, '0');
    }

    /** Portion désignée sans ambiguïté : toute la ligne, ou deux extrémités nommées. */
    public static function comparable(string $portionKey): bool
    {
        return $portionKey === 'whole' || str_contains($portionKey, '|');
    }

    private static function groupRank(string $key): int
    {
        return match (true) {
            $key === 'whole' => 0,
            $key === 'traced' => 3,
            $key === 'unspecified' => 4,
            str_contains($key, '|') => 1,
            default => 2, // un seul lieu (« près de X »)
        };
    }

    /** Même date, à la précision commune (une année seule s'accorde avec un jour de cette année). */
    public static function sameDate(array $a, array $b): bool
    {
        $prec = in_array('year', [$a['precision'], $b['precision']], true) ? 4
            : (in_array('month', [$a['precision'], $b['precision']], true) ? 7 : 10);

        return substr($a['date'], 0, $prec) === substr($b['date'], 0, $prec);
    }

    /** Champs d'une image d'événement gardés dans la chronique (crédits complets, rien de plus). */
    public const EVENT_MEDIA_FIELDS = [
        'id', 'provider', 'provider_label', 'type', 'title', 'caption', 'thumb_url', 'display_url', 'source_url',
        'width', 'height', 'author', 'attribution', 'license', 'license_url', 'rights_status', 'contribution_id',
    ];

    /**
     * Images publiées des événements d'une ligne, en une requête, rangées par événement (dans l'ordre
     * de leur publication). Une image en attente de validation n'est pas dans `media`.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public static function eventMedia(int $lineId): array
    {
        $out = [];
        $rows = Media::query()
            ->where('mediable_type', LineEvent::class)
            ->whereIn('mediable_id', LineEvent::query()->select('id')->where('line_id', $lineId))
            ->displayable()
            ->orderBy('id')
            ->get();
        foreach ($rows as $m) {
            $out[(int) $m->mediable_id][] = array_intersect_key(MediaResource::make($m)->resolve(), array_flip(self::EVENT_MEDIA_FIELDS));
        }

        return $out;
    }

    /**
     * Lecture en base : événements complets de la ligne, portée (LinePhases::events), images publiées
     * de chaque événement (`media`).
     *
     * @param  list<int>  $used
     * @param  list<array{name: string, pk_m: ?int}>  $stations  gares de la ligne (LineStations) : noms et PK
     * @param  ?int  $lineLengthM  longueur de la ligne affichée dans l'en-tête (portion « toute la ligne »)
     */
    public static function forLine(object $line, array $used, array $stations = [], ?int $lineLengthM = null, ?string $kept = null): array
    {
        // Condition SQL facultative sur line_events (hors du site : RedistributableDates::lineEventKept).
        $kept ??= 'TRUE';
        $locale = app()->getLocale();
        $names = [];
        $pkByPlace = [];
        foreach ($stations as $st) {
            if (($k = self::placeKey($st['name'])) !== null) {
                $names[$k] ??= $st['name'];
                if (($st['pk_m'] ?? null) !== null) {
                    $pkByPlace[$k] ??= (int) $st['pk_m'];
                }
            }
        }
        $scopes = LinePhases::events($line);
        // Position approchée de chaque événement localisé le long de la ligne (rangement des portions) :
        // PK de la source, sinon PK de la gare de la ligne la plus proche de sa portion.
        $pkHints = [];
        // Point de la portion calculé une fois par événement (CTE matérialisée) : dans le tri, il était
        // recalculé pour chaque gare candidate (100 ms sur la LGV Sud-Est, 20 ms ainsi).
        foreach (DB::select('WITH e AS MATERIALIZED (
                    SELECT id, line_id, section_pk_start_m, ST_PointOnSurface(section_geom) AS p FROM line_events
                    WHERE line_id = ? AND (section_pk_start_m IS NOT NULL OR section_geom IS NOT NULL))
                SELECT e.id, COALESCE(e.section_pk_start_m, (
                    SELECT sl.pk_m FROM station_lines sl JOIN stations s ON s.id = sl.station_id
                    WHERE sl.line_id = e.line_id AND sl.pk_m IS NOT NULL
                    ORDER BY s.geom <-> e.p LIMIT 1)) AS pk
                FROM e', [$line->id]) as $h) {
            if ($h->pk !== null) {
                $pkHints[(int) $h->id] = (int) $h->pk;
            }
        }
        $media = self::eventMedia((int) $line->id);
        $events = [];
        foreach (DB::select('SELECT id, type, event_date, date_precision, description, source, source_url, source_quote, confidence,
                    section_from, section_to, section_length_m, heritage_kind, reuse
                FROM line_events WHERE line_id = ? AND '.$kept.' ORDER BY event_date, id', [$line->id]) as $r) {
            // Charte : pas de tiret long à l'affichage. Raison d'une correction sourcée à part.
            [$raw, $correction] = $r->description === null ? [null, null] : EventText::splitCorrection(self::dashes($r->description));
            // Textes générés dans la langue de la page (App\Support\EventText) ; *_lang = fr quand tout
            // ou partie reste une citation en français. Lieux écrits comme les gares de la ligne.
            $d = EventText::describe($r->source, $raw, $locale, $r->section_from, $r->section_to);
            $from = self::stationName($r->section_from, $names);
            $to = self::stationName($r->section_to, $names);
            $q = EventText::quoteDetails($r->source, $r->source_quote === null ? null : self::dashes($r->source_quote), $locale);
            if ($q['kind'] === 'citation' && $q['text'] !== null) {
                $q['text'] = Str::limit($q['text'], 400);
            }
            $events[] = [
                'id' => (int) $r->id,
                'type' => $r->type,
                'date' => substr((string) $r->event_date, 0, 10),
                'precision' => in_array($r->date_precision, ['day', 'month', 'year'], true) ? $r->date_precision : 'day',
                // Clé vide sans lieu (renamePlaces l'ignore) : une clé null est dépréciée en PHP 8.5.
                'description' => self::renamePlaces($d['text'], [(string) $r->section_from => $from, (string) $r->section_to => $to]),
                'description_lang' => $d['lang'],
                'correction' => $correction,
                'correction_lang' => $correction !== null && $locale !== 'fr' ? 'fr' : null,
                'quote' => $q['text'],
                'quote_lang' => $q['lang'],
                'quote_kind' => $q['kind'],
                'source' => $r->source,
                'source_url' => $r->source_url,
                'confidence' => $r->confidence,
                'section_from' => $from,
                'section_to' => $to,
                'section_text' => self::placeText($from, $to, $locale),
                'section_length_m' => $r->section_length_m === null ? null : (int) $r->section_length_m,
                'heritage_kind' => $r->heritage_kind,
                'reuse' => $r->reuse,
                'scope' => $scopes[(int) $r->id]['scope'] ?? 'unlocated',
                'other_line' => self::isOtherLine((string) $r->type, $raw, $line->code ?? null),
                'pk_hint' => $pkHints[(int) $r->id] ?? null,
                'media' => $media[(int) $r->id] ?? [],
            ];
        }

        return self::build($events, $used, $pkByPlace, $lineLengthM, $locale);
    }

    /**
     * Noms de lieux d'un texte généré remplacés par leur forme de la liste des gares (seulement
     * les noms exacts de la portion de l'événement, hors citation « … »).
     *
     * @param  array<?string, ?string>  $map  nom de la source → nom affiché
     */
    public static function renamePlaces(?string $text, array $map): ?string
    {
        if ($text === null) {
            return null;
        }
        $map = array_filter($map, fn ($to, $from) => $from !== '' && $to !== null && $to !== $from, ARRAY_FILTER_USE_BOTH);
        if ($map === []) {
            return $text;
        }
        $parts = preg_split('/([«“„][^»”“]*[»”“])/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                $parts[$i] = strtr($part, $map);
            }
        }

        return implode('', $parts);
    }

    /** Tirets longs et cadratins remplacés par un tiret simple (charte VFF). */
    public static function dashes(string $text): string
    {
        return (string) preg_replace(['/\s*[—―]\s*/u', '/\s+–\s+/u', '/--/'], [' - ', ' - ', '-'], $text);
    }
}
