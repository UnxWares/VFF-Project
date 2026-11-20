<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Source consultée pour un accident : fournisseur (clé de database/data/source_rights.php),
 * lien, titre, licence, révision lue et faits relevés (pour le recoupement).
 *
 * @property int $id
 * @property int $accident_id
 * @property string $provider
 * @property string|null $external_id
 * @property string $role
 * @property string $title
 * @property string $url
 * @property string $licence
 * @property string|null $licence_url
 * @property int|null $revision
 * @property array<string, mixed>|null $facts
 * @property Carbon $retrieved_at
 */
class AccidentSource extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'facts' => 'array',
            'revision' => 'integer',
            'retrieved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Accident, $this> */
    public function accident(): BelongsTo
    {
        return $this->belongsTo(Accident::class);
    }
}
