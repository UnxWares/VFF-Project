<?php

namespace App\Models;

use App\Enums\AccidentReferenceAccess;
use App\Enums\AccidentReferenceKind;
use App\Enums\AccidentReferenceReliability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Document consulté pour le contexte d'un accident (vff:enrich-accidents) : fournisseur (clé du registre des
 * droits), nature, fiabilité, titre, éditeur, date du document, page ou numéro, lien, accès et date de
 * consultation ; pour un article de presse, les faits qu'il dit de l'accident (relus par vff:verify-accidents).
 *
 * @property int $id
 * @property int $accident_id
 * @property string $key
 * @property int $position
 * @property string $provider
 * @property AccidentReferenceKind $kind
 * @property AccidentReferenceReliability $reliability
 * @property string $title
 * @property string|null $publisher
 * @property string|null $author
 * @property string|null $published
 * @property string|null $locator
 * @property string $url
 * @property AccidentReferenceAccess $access
 * @property Carbon $consulted_on
 * @property string|null $note
 * @property array<string, mixed>|null $facts faits lus dans un article de presse (date ou date_relative, commune_insee, deaths, deaths_provisional)
 */
class AccidentReference extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'kind' => AccidentReferenceKind::class,
            'reliability' => AccidentReferenceReliability::class,
            'access' => AccidentReferenceAccess::class,
            'consulted_on' => 'date',
            'position' => 'integer',
            'facts' => 'array',
        ];
    }

    /** @return BelongsTo<Accident, $this> */
    public function accident(): BelongsTo
    {
        return $this->belongsTo(Accident::class);
    }
}
