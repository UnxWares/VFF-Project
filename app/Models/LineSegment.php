<?php

namespace App\Models;

use App\Enums\SegmentStatus;
use Clickbar\Magellan\Data\Geometries\LineString;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LineSegment extends Model
{
    /** @use HasFactory<\Database\Factories\LineSegmentFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'geom' => LineString::class,
        'status' => SegmentStatus::class,
        'electrified' => 'boolean',
        'length_meters' => 'integer',
        'max_speed_kmh' => 'integer',
        'voltage_v' => 'integer',
        'gauge_mm' => 'integer',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function era(): BelongsTo
    {
        return $this->belongsTo(Era::class);
    }
}
