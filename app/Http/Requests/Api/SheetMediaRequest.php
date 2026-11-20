<?php

namespace App\Http\Requests\Api;

use App\Services\Api\MediaGallery;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pagination, ordre et filtres de la galerie d'une fiche : `GET /api/v1/lines/{line}/media`,
 * `/stations/{station}/media` et `/materials/{material}/media` (API publique, anonyme). Les
 * paramètres sont documentés ici une seule fois pour les trois points d'accès.
 */
#[QueryParameter('page', 'Numéro de page, à partir de 1.', type: 'int', default: 1, example: 1)]
#[QueryParameter('per_page', 'Nombre de médias par page (1 à 60).', type: 'int', default: 24, example: 24)]
#[QueryParameter('sort', "Ordre : `oldest` (époque représentée la plus ancienne d'abord), `newest` (la plus récente d'abord), `featured` (médias mis en avant d'abord). Les médias non datés viennent en dernier.", type: 'string', default: 'oldest', example: 'oldest')]
#[QueryParameter('type', 'Type de média : `photo`, `postcard` (carte postale), `map` (carte), `plan`, `document`.', type: 'string', example: 'postcard')]
#[QueryParameter('period', 'Époque représentée : `pre1900` (avant 1900), `1900_1944`, `1945_1969`, `1970_1999`, `since2000` (depuis 2000), `undated` (non daté). Bornes incluses.', type: 'string', example: '1900_1944')]
class SheetMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return MediaGallery::rules();
    }
}
