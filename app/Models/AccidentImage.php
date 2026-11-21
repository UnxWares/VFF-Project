<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Image d'archives d'un accident (gravure, photo du lieu, monument), toujours de Wikimedia Commons : auteur,
 * licence et date relus sur Commons par vff:enrich-accidents, légende rédigée par VFF.
 *
 * @property int $id
 * @property int $accident_id
 * @property int $position
 * @property string $file
 * @property string $page_url
 * @property string $image_url
 * @property string|null $thumb_url
 * @property int|null $width
 * @property int|null $height
 * @property string $author
 * @property string $licence
 * @property string|null $licence_url
 * @property string|null $date
 * @property array<string, string> $caption
 * @property Carbon $retrieved_at
 */
class AccidentImage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'caption' => 'array',
            'retrieved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Accident, $this> */
    public function accident(): BelongsTo
    {
        return $this->belongsTo(Accident::class);
    }
}
