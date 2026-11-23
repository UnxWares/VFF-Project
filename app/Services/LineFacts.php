<?php

namespace App\Services;

use App\Services\Sncf\LineInfrastructure;
use App\Support\FrenchDepartments;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bloc « Fiche » de la fiche ligne : chaque valeur dit à quoi elle se rapporte (contrôle qualité du
 * 2026-10-20 : sur la 752000, ligne de 711 km de Combs-la-Ville à Marseille, la fiche donnait la
 * longueur, la vitesse et les régions de l'élément Wikidata « LGV Sud-Est », c'est-à-dire de la seule
 * LN1 : 409 km, 300 km/h, 3 régions, alors que l'en-tête disait 320 km/h).
 *
 *   - Départements et régions : calculés sur le tracé de la LIGNE ENTIÈRE (toutes ères), par les
 *     contours des communes traversées (au moins DEPARTMENT_MIN_M dans le département).
 *   - Vitesse maximale : celle de l'en-tête (tronçons de l'ère affichée, vitesses SNCF Réseau),
 *     avec son ère ; la valeur Wikidata n'est montrée que si elle diffère, rapportée à son élément.
 *   - Longueur, vitesse, écartement… de Wikidata : rapportés à l'élément (son article) quand sa
 *     longueur s'écarte de plus de PART_RATIO de celle de la ligne (élément qui ne décrit qu'une
 *     partie de la ligne, ou un ensemble plus grand).
 *
 * Chaque fait : key, valeur (km, kmh, mm, n, year, list), scope (line | item), source (vff | sncf |
 * wikidata), item (nom de l'élément décrit, si scope = item), as_of (année de l'ère, si datée).
 */
final class LineFacts
{
    /** Écart relatif de longueur au-delà duquel l'élément Wikidata ne décrit pas la ligne entière. */
    public const PART_RATIO = 0.1;

    /** Longueur minimale (m) dans un département pour le compter comme traversé. */
    public const DEPARTMENT_MIN_M = 200;

    /**
     * @param  object  $encyclopedia  colonnes encyclopédiques de lines (Wikidata)
     * @param  array<string, mixed>  $stats  en-tête (length_km, length_greatest_km, max_speed_kmh)
     * @return list<array<string, mixed>>
     */
    public static function build(object $line, object $encyclopedia, array $stats, ?int $eraYear, bool $eraIsCurrent): array
    {
        $item = trim((string) ($encyclopedia->wikipedia_title ?? '')) ?: null;
        $lineKm = (float) ($stats['length_greatest_km'] ?? $stats['length_km'] ?? 0);
        $wdKm = $encyclopedia->length_official_m ? $encyclopedia->length_official_m / 1000 : null;
        $partial = $wdKm !== null && $lineKm > 0 && abs($wdKm - $lineKm) / $lineKm > self::PART_RATIO;
        // L'élément Wikidata porte le nom de la ligne elle-même (« Ligne de Saint-Germain-des-Fossés à
        // Darsac » pour la ligne de St-Germain-des-Fossés à Darsac) : il la décrit, sa longueur diffère
        // seulement de celle du tracé (« 160 km d'après Wikidata ; tracé de VFF : 145,6 km »), au lieu de
        // la phrase contradictoire « Ligne de … seulement, pas toute la ligne ».
        $sameLine = $item !== null && LineSheet::nameKey($item) === LineSheet::nameKey((string) ($line->name ?? ''));
        $differs = $partial && $sameLine;
        $partial = $partial && ! $sameLine;
        $wd = fn (array $f) => $f + ['source' => 'wikidata', 'scope' => $partial ? 'item' : 'line', 'item' => $partial ? $item : null];

        $facts = [];
        if ($wdKm !== null) {
            $facts[] = $wd(['key' => 'length', 'km' => round($wdKm, 1)] + ($differs ? ['line_km' => round($lineKm, 1)] : []));
        }
        // Écartement : celui des tronçons de la ligne (le plus fréquent), sinon celui de l'élément.
        if (! empty($stats['gauge_mm'])) {
            $facts[] = ['key' => 'gauge', 'mm' => (int) $stats['gauge_mm'], 'source' => 'vff', 'scope' => 'line', 'item' => null];
        } elseif ($encyclopedia->gauge_mm_default) {
            $facts[] = $wd(['key' => 'gauge', 'mm' => (int) $encyclopedia->gauge_mm_default]);
        }
        // Nombre de voies : régime d'exploitation de SNCF Réseau (aujourd'hui) quand il l'établit et ne contredit pas
        // la fiche ; sinon le nombre de la fiche, avec sa source.
        $known = $encyclopedia->nb_voies_default ? (int) $encyclopedia->nb_voies_default : null;
        if ($tracks = app(LineInfrastructure::class)->trackFact((int) $line->id, $known)) {
            $facts[] = $tracks;
        } elseif ($known !== null) {
            $facts[] = $wd(['key' => 'tracks', 'n' => $known]);
        }
        $speed = $stats['max_speed_kmh'] ?? null;
        if ($speed) {
            $facts[] = ['key' => 'max_speed', 'kmh' => (int) $speed, 'source' => 'sncf', 'scope' => 'line', 'item' => null,
                'as_of' => $eraIsCurrent ? 'today' : $eraYear,
                // Vitesse atteinte sur une petite part de la ligne seulement (moins de la moitié de ce qui est
                // en service) : la longueur concernée est dite (« 160 km/h sur 0,6 km »).
                'km' => isset($stats['max_speed_km'], $stats['active_length_km']) && (float) $stats['active_length_km'] > 0
                    && (float) $stats['max_speed_km'] < (float) $stats['active_length_km'] / 2 ? (float) $stats['max_speed_km'] : null];
        }
        if ($encyclopedia->max_speed_kmh_official && (int) $encyclopedia->max_speed_kmh_official !== (int) $speed) {
            $facts[] = $wd(['key' => 'max_speed', 'kmh' => (int) $encyclopedia->max_speed_kmh_official]);
        }
        if ($encyclopedia->concession_year) {
            $facts[] = $wd(['key' => 'concession', 'year' => (int) $encyclopedia->concession_year]);
        }
        $departments = self::departments((int) $line->id);
        if ($departments !== []) {
            $regions = array_values(array_unique(array_column($departments, 'region')));
            usort($regions, fn ($a, $b) => strcmp(Str::ascii($a), Str::ascii($b)));
            usort($departments, fn ($a, $b) => strcmp(Str::ascii($a['name']), Str::ascii($b['name'])));
            $facts[] = ['key' => 'regions', 'list' => $regions, 'source' => 'vff', 'scope' => 'line', 'item' => null];
            $facts[] = ['key' => 'departements', 'list' => array_map(fn ($d) => ['code' => $d['code'], 'name' => $d['name']], $departments),
                'source' => 'vff', 'scope' => 'line', 'item' => null];
        } else {
            // Pas de tracé : régions et départements de l'élément Wikidata, rapportés à lui.
            $decode = fn ($v) => is_string($v) ? (json_decode($v, true) ?: []) : [];
            if ($r = $decode($encyclopedia->regions ?? null)) {
                $facts[] = $wd(['key' => 'regions', 'list' => array_map(fn ($x) => $x['name'] ?? $x, $r)]);
            }
            if ($d = $decode($encyclopedia->departements ?? null)) {
                $facts[] = $wd(['key' => 'departements', 'list' => array_map(fn ($x) => is_array($x) ? $x : ['code' => null, 'name' => $x], $d)]);
            }
        }

        return array_map(fn (array $f) => self::localized($f, app()->getLocale()), $facts);
    }

    /** Clés des libellés (lang/{fr,en,de}/lines.php, facts.*). */
    private const LABELS = ['length' => 'length_official', 'gauge' => 'gauge', 'tracks' => 'tracks', 'max_speed' => 'max_speed',
        'concession' => 'concession', 'regions' => 'regions', 'departements' => 'departements'];

    /**
     * Fait avec son libellé et sa précision rédigés dans la langue de la page (`label`, `note`) :
     * une valeur d'un élément Wikidata qui ne décrit qu'une partie de la ligne porte son nom dans le
     * libellé (« Longueur officielle (LGV Sud-Est) »), jamais seulement dans une note en petit.
     *
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    public static function localized(array $f, string $locale): array
    {
        $num = fn (float $n) => (new \NumberFormatter($locale === 'en' ? 'en_GB' : $locale, \NumberFormatter::DECIMAL))->format($n);
        $g = fn (string $key, array $p = []) => (string) trans('generated.facts.'.$key, $p, $locale);
        $label = (string) trans('lines.facts.'.(self::LABELS[$f['key']] ?? $f['key']), [], $locale);
        if ($f['scope'] === 'item' && $f['item'] !== null) {
            $label = $g('label_item', ['label' => $label, 'item' => $f['item']]);
        }
        $note = match (true) {
            $f['scope'] === 'item' => $g('note_item'),
            $f['source'] === 'sncf' && $f['key'] === 'tracks' && ($f['km'] ?? null) === null => $g('note_sncf_tracks'),
            $f['source'] === 'sncf' && ($f['km'] ?? null) !== null => $g($f['as_of'] === 'today' ? 'note_sncf_today_part' : 'note_sncf_era_part',
                ['km' => $num((float) $f['km']), 'year' => (string) $f['as_of']]),
            $f['source'] === 'sncf' => $g($f['as_of'] === 'today' ? 'note_sncf_today' : 'note_sncf_era', ['year' => (string) $f['as_of']]),
            $f['source'] === 'vff' => $g('note_vff_line'),
            isset($f['line_km']) => $g('note_wikidata_differs', ['km' => $num((float) $f['line_km'])]),
            default => $g('note_wikidata_line'),
        };

        return $f + ['label' => $label, 'note' => $note];
    }

    /**
     * Départements traversés par la ligne entière (tous les tracés de toutes les ères), avec leur
     * région, du plus long parcours au plus court. Mis en cache (recalcul si les tronçons changent).
     *
     * @return list<array{code: string, name: string, region: string, m: int}>
     */
    public static function departments(int $lineId): array
    {
        $sig = DB::selectOne('SELECT COUNT(*) AS n, MAX(updated_at) AS t FROM line_segments WHERE line_id = ?', [$lineId]);
        if (! $sig || (int) $sig->n === 0) {
            return [];
        }

        return Cache::remember("line-facts:departments:{$lineId}:{$sig->n}:{$sig->t}", now()->addDays(7), function () use ($lineId) {
            $out = [];
            // Un contour de commune invalide (anneau qui se recoupe : 18 sur 35 014 à l'import) faisait
            // échouer ST_Intersection (GEOS TopologyException, erreur 500 sur la fiche de 750000) : il est
            // réparé à la volée ; les contours valides sont pris tels quels, leur résultat ne change pas.
            // Tracé par tracé (chaque tracé distinct de toutes les ères), et non la ligne fusionnée : même somme de
            // longueurs, mais l'index des contours ne retient que les communes proches de chaque tracé, et chaque
            // intersection porte sur un tracé court (Paris - Marseille : 0,45 s au lieu de 2,7 s, mêmes mètres).
            foreach (DB::select(<<<'SQL'
                WITH seg AS (SELECT DISTINCT geom FROM line_segments WHERE line_id = ?),
                     c AS (SELECT cc.departement, seg.geom AS g,
                                  CASE WHEN ST_IsValid(cc.geom) THEN cc.geom ELSE ST_MakeValid(cc.geom) END AS geom
                           FROM seg JOIN commune_contours cc
                             ON cc.departement IS NOT NULL AND ST_Intersects(cc.geom, seg.geom))
                SELECT departement AS code, ROUND(SUM(ST_Length(ST_Intersection(geom, g)::geography)))::int AS m
                FROM c
                GROUP BY departement
                HAVING SUM(ST_Length(ST_Intersection(geom, g)::geography)) >= ?
                ORDER BY 2 DESC
            SQL, [$lineId, self::DEPARTMENT_MIN_M]) as $r) {
                if ($d = FrenchDepartments::find((string) $r->code)) {
                    $out[] = ['code' => (string) $r->code, 'name' => $d[0], 'region' => $d[1], 'm' => (int) $r->m];
                }
            }

            return $out;
        });
    }
}
