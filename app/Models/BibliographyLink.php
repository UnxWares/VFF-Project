<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rattachement d'une référence à une ligne ou à une gare (exactement l'une des deux), avec son fondement :
 * `title`, `title_alias`, `article_bibliography`, `article_reference` ou `wikidata` (App\Services\Bibliography\BibliographyMatcher).
 *
 * @property int $id
 * @property int $entry_id
 * @property ?int $line_id
 * @property ?int $station_id
 * @property string $provider
 * @property string $basis
 * @property ?int $score
 * @property list<array{code: string, points: int}>|null $reasons
 * @property ?string $origin_url
 */
class BibliographyLink extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'reasons' => 'array',
        'score' => 'integer',
    ];

    /** @return BelongsTo<BibliographyEntry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(BibliographyEntry::class, 'entry_id');
    }
}
