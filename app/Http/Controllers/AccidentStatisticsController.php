<?php

namespace App\Http\Controllers;

use App\Enums\AccidentKind;
use App\Services\Accidents\AccidentStatistics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page /accidents/statistics, « Les accidents en chiffres » : mêmes agrégats que
 * `GET /api/v1/accidents/statistics` (AccidentStatistics, en cache). Les filtres sont dans l'adresse,
 * donc partageables ; une valeur invalide est ignorée, jamais une erreur.
 */
class AccidentStatisticsController extends Controller
{
    public function index(Request $request, AccidentStatistics $statistics): Response
    {
        $from = $this->year($request->query('from'));
        $to = $this->year($request->query('to'));
        if ($from !== null && $to !== null && $to < $from) {
            [$from, $to] = [$to, $from];
        }
        $kind = $request->query('kind');
        $review = $request->query('review');
        $filters = [
            'from' => $from,
            'to' => $to,
            'kind' => is_string($kind) ? AccidentKind::tryFrom($kind)?->value : null,
            'review' => $review === 'ok' ? 'ok' : null,
        ];

        return Inertia::render('Accidents/Statistics', [
            'statistics' => $statistics->get($filters),
            'filters' => $filters,
            'unfiltered_total' => $statistics->get([])['totals']['accidents'],
        ]);
    }

    private function year(mixed $value): ?int
    {
        return is_string($value) && preg_match('/^\d{4}$/', $value) && (int) $value >= 1800 && (int) $value <= 2100 ? (int) $value : null;
    }
}
