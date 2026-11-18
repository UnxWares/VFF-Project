<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Inertia\Inertia;
use Inertia\Response;

class MaterialController extends Controller
{
    public function index(): Response
    {
        $materials = Material::query()
            ->orderBy('in_service_from')
            ->orderBy('name')
            ->get(['slug', 'name', 'type', 'builder', 'in_service_from', 'in_service_to']);

        $byType = $materials
            ->groupBy(fn ($m) => $m->type)
            ->map(fn ($g, $type) => [
                'type' => $type,
                'items' => $g->map(fn ($m) => [
                    'slug' => $m->slug,
                    'name' => $m->name,
                    'builder' => $m->builder,
                    'in_service_from' => $m->in_service_from,
                    'in_service_to' => $m->in_service_to,
                ])->values(),
            ])
            ->values();

        return Inertia::render('Materials/Index', [
            'groups' => $byType,
            'total' => $materials->count(),
        ]);
    }

    public function show(Material $material): Response
    {
        $material->load(['lines' => fn ($q) => $q->orderBy('name')->limit(20)]);

        return Inertia::render('Materials/Show', [
            'material' => [
                'slug' => $material->slug,
                'name' => $material->name,
                'type' => $material->type,
                'builder' => $material->builder,
                'in_service_from' => $material->in_service_from,
                'in_service_to' => $material->in_service_to,
                'description' => $material->description ?? null,
            ],
            'lines' => $material->lines->map(fn ($l) => [
                'slug' => $l->slug,
                'name' => $l->name,
                'code' => $l->code,
            ]),
        ]);
    }
}
