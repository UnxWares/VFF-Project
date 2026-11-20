<?php

namespace App\Models;

use App\Enums\LineEventType;
use Database\Factories\LineEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LineEvent extends Model
{
    /** @use HasFactory<LineEventFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => LineEventType::class,
        'event_date' => 'date',
    ];

    /** @return BelongsTo<Line, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /** @return MorphMany<Media, $this> */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
