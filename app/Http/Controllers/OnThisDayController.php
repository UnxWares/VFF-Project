<?php

namespace App\Http\Controllers;

use App\Services\OnThisDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page « Ce jour-là » : /on-this-day (aujourd'hui) et /on-this-day/{mm-dd}. Les faits viennent de OnThisDay
 * (mêmes données que `GET /api/v1/on-this-day`). `?accidents=0` masque les accidents. Le formulaire
 * `?month=11&day=23` (sans JavaScript) renvoie vers l'adresse du jour.
 */
class OnThisDayController extends Controller
{
    public function index(Request $request, OnThisDay $service, ?string $monthDay = null): Response|RedirectResponse
    {
        $month = filter_var($request->query('month'), FILTER_VALIDATE_INT);
        $dayOfMonth = filter_var($request->query('day'), FILTER_VALIDATE_INT);
        if ($monthDay === null && is_int($month) && is_int($dayOfMonth) && checkdate($month, $dayOfMonth, 2024)) {
            return redirect()->route('network.on-this-day', ['monthDay' => OnThisDay::key($month, $dayOfMonth)]
                + ($request->query('accidents') === '0' ? ['accidents' => 0] : []));
        }
        $today = now()->format('m-d');
        $parsed = OnThisDay::parse($monthDay ?? $today);
        abort_if($parsed === null, 404);
        $withAccidents = $request->query('accidents') !== '0';
        $types = $withAccidents ? [] : array_values(array_diff(OnThisDay::KINDS, ['accident']));
        $data = $service->day($parsed[0], $parsed[1], app()->getLocale(), $types);
        $coverage = $service->coverage();
        $days = OnThisDay::allDays();
        $i = (int) array_search($data['day'], $days, true);
        $accidents = $withAccidents ? count(array_filter($data['facts'], fn (array $f): bool => $f['kind'] === 'accident'))
            : count(array_filter($service->day($parsed[0], $parsed[1], app()->getLocale(), ['accident'])['facts']));

        return Inertia::render('Network/OnThisDay', [
            'day' => [
                'key' => $data['day'], 'month' => $parsed[0], 'day' => $parsed[1],
                'is_today' => $data['day'] === $today,
                'previous' => $days[($i + count($days) - 1) % count($days)],
                'next' => $days[($i + 1) % count($days)],
            ],
            'facts' => $data['facts'],
            'accidents' => ['shown' => $withAccidents, 'count' => $accidents],
            'neighbours' => $service->neighbours($data['day']),
            'coverage' => ['days' => $coverage['days'], 'days_with_facts' => $coverage['days_with_facts'], 'facts' => $coverage['facts']],
            'today' => $today,
        ]);
    }
}
