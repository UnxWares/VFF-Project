<?php

namespace App\Models;

use App\Enums\LineEventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LineEvent extends Model
{
    /** @use HasFactory<\Database\Factories\LineEventFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => LineEventType::class,
        'event_date' => 'date',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
