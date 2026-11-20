<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Fiche d'un événement de ligne : la liste, plus la citation de la source et le détail de la portion.
 */
class LineEventDetailResource extends LineEventResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'source_quote' => $this->source_quote,
            'section_pk_start_m' => $this->section_pk_start_m,
            'section_pk_end_m' => $this->section_pk_end_m,
            'section_length_m' => $this->section_length_m,
            'section_resolution' => $this->section_resolution,
            'reuse' => $this->reuse,
            'heritage_kind' => $this->heritage_kind,
        ];
    }
}
