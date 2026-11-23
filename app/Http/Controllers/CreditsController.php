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
        $byLicense = Media::query()->displayable()
            ->select('license', DB::raw('COUNT(*) as count'))
            ->groupBy('license')
            ->orderByDesc('count')
            ->get()
            ->map(fn (Media $r): array => ['license' => $r->license, 'count' => $r->getAttribute('count')])
            ->all();

        $byAuthor = Media::query()->displayable()
            ->select('author', DB::raw('COUNT(*) as count'))
            ->whereNotNull('author')
            ->groupBy('author')
            ->orderByDesc('count')
            ->limit(30)
            ->get()
            ->map(fn (Media $r): array => ['author' => $r->author, 'count' => $r->getAttribute('count')])
            ->all();

        return Inertia::render('Credits/Media', [
            'stats' => [
                'total' => Media::query()->displayable()->count(),
                'photo_count' => Media::query()->displayable()->where('type', MediaType::Photo->value)->count(),
                'plan_count' => Media::query()->displayable()->where('type', MediaType::Plan->value)->count(),
                'document_count' => Media::query()->displayable()->where('type', MediaType::Document->value)->count(),
            ],
            'byLicense' => $byLicense,
            'byAuthor' => $byAuthor,
        ]);
    }
}
