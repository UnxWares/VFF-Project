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
 * consultation.
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
        ];
    }

    /** @return BelongsTo<Accident, $this> */
    public function accident(): BelongsTo
    {
        return $this->belongsTo(Accident::class);
    }
}
