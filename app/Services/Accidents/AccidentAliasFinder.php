<?php

namespace App\Services\Accidents;

use App\Enums\AccidentKind;
use App\Enums\AccidentLocationPrecision;
use App\Enums\AccidentReviewStatus;
use App\Models\Accident;
use Illuminate\Support\Facades\DB;

/**
 * Doublons : deux fiches qui décrivent le même accident (deux éléments Wikidata pour un seul accident,
 * comme Le Val-Doré et Conches-en-Ouche le 24/10/1933).
 *
 * Même jour (date au jour des deux côtés), même commune ou moins de 10 km, natures compatibles (identiques,
 * ou l'une « autre »). Deux fiches qui ont chacune une description propre (élément Wikidata ou article de
 * Wikipédia) sont fusionnées : la mieux établie est publiée, l'autre devient son alias (gardée avec ses
 * sources, jamais publiée). Les autres paires, où une puce de liste est en jeu, sont seulement signalées.
 */
final class AccidentAliasFinder
{
    /** Distance au-delà de laquelle deux fiches du même jour ne sont plus le même accident. */
    private const SAME_EVENT_MAX_M = 10000;

    /**
     * Une entrée par paire de fiches du même accident probable : `alias` renseigné quand elles sont
     * fusionnées (`keep` reste publiée), nul quand le doublon n'est que possible (`other` est alors la
     * deuxième fiche de la paire).
     *
     * @return list<array{keep: Accident, alias: ?Accident, other: Accident, distance_m: ?int}>
     */
    public function pairs(): array
    {
        $rows = DB::select(
            'SELECT a.id AS a_id, b.id AS b_id,
                    CASE WHEN a.geom IS NOT NULL AND b.geom IS NOT NULL THEN ST_Distance(a.geom::geography, b.geom::geography) END AS distance_m
             FROM accidents a JOIN accidents b ON b.id > a.id AND b.occurred_on = a.occurred_on
             WHERE a.alias_of_id IS NULL AND b.alias_of_id IS NULL
               AND a.date_precision = \'day\' AND b.date_precision = \'day\'
               AND (a.commune_insee = b.commune_insee
                    OR (a.geom IS NOT NULL AND b.geom IS NOT NULL AND ST_DWithin(a.geom::geography, b.geom::geography, ?)))
             ORDER BY a.id, b.id',
            [self::SAME_EVENT_MAX_M],
        );
        $out = [];
        foreach ($rows as $r) {
            $a = Accident::query()->withoutGlobalScopes()->with('sources')->findOrFail($r->a_id);
            $b = Accident::query()->withoutGlobalScopes()->with('sources')->findOrFail($r->b_id);
            if (! $this->compatible($a->kind, $b->kind)) {
                continue;
            }
            $distance = $r->distance_m !== null ? (int) round((float) $r->distance_m) : null;
            if ($this->described($a) && $this->described($b)) {
                [$keep, $alias] = $this->better($a, $b) ? [$a, $b] : [$b, $a];
                $out[] = ['keep' => $keep, 'alias' => $alias, 'other' => $alias, 'distance_m' => $distance];
            } else {
                $out[] = ['keep' => $a, 'alias' => null, 'other' => $b, 'distance_m' => $distance];
            }
        }

        return $out;
    }

    /** Natures identiques, ou l'une est « autre » (nature non lue). */
    private function compatible(AccidentKind $a, AccidentKind $b): bool
    {
        return $a === $b || $a === AccidentKind::Other || $b === AccidentKind::Other;
    }

    /** Fiche qui a sa propre description : élément Wikidata ou article de Wikipédia. */
    private function described(Accident $a): bool
    {
        return $a->sources->contains(fn ($s) => in_array($s->role, ['data', 'article'], true));
    }

    /** La fiche $a est-elle mieux établie que $b ? « ok », puis plus de sources, puis lieu le plus précis, puis la plus ancienne. */
    private function better(Accident $a, Accident $b): bool
    {
        $score = fn (Accident $x): array => [
            $x->review_status === AccidentReviewStatus::Ok ? 1 : 0,
            $x->sources->count(),
            $this->precisionRank($x->location_precision),
            -$x->id,
        ];

        return $score($a) > $score($b);
    }

    private function precisionRank(?AccidentLocationPrecision $p): int
    {
        return match ($p) {
            AccidentLocationPrecision::Exact => 4,
            AccidentLocationPrecision::LevelCrossing => 3,
            AccidentLocationPrecision::Station => 2,
            AccidentLocationPrecision::Commune => 1,
            null => 0,
        };
    }
}
