<?php

namespace App\Http\Controllers;

use App\Enums\MediaType;
use App\Models\Media;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CreditsController extends Controller
{
    public function tech(): Response
    {
        return Inertia::render('Credits/Tech');
    }

    public function media(): Response
    {
        $byLicense = Media::query()
            ->select('license', DB::raw('COUNT(*) as count'))
            ->groupBy('license')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => ['license' => $r->license, 'count' => $r->count])
            ->all();

        $byAuthor = Media::query()
            ->select('author', DB::raw('COUNT(*) as count'))
            ->whereNotNull('author')
            ->groupBy('author')
            ->orderByDesc('count')
            ->limit(30)
            ->get()
            ->map(fn ($r) => ['author' => $r->author, 'count' => $r->count])
            ->all();

        return Inertia::render('Credits/Media', [
            'stats' => [
                'total' => Media::count(),
                'photo_count' => Media::where('type', MediaType::Photo->value)->count(),
                'plan_count' => Media::where('type', MediaType::Plan->value)->count(),
                'document_count' => Media::where('type', MediaType::Document->value)->count(),
            ],
            'byLicense' => $byLicense,
            'byAuthor' => $byAuthor,
        ]);
    }
}
