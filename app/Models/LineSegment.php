<?php

namespace App\Models;

use App\Enums\SegmentStatus;
use Clickbar\Magellan\Data\Geometries\LineString;
use Database\Factories\LineSegmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LineSegment extends Model
{
    /** @use HasFactory<LineSegmentFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'geom' => LineString::class,
        'status' => SegmentStatus::class,
        'electrified' => 'boolean',
        'is_heritage' => 'boolean',
        'length_meters' => 'integer',
        'max_speed_kmh' => 'integer',
        'voltage_v' => 'integer',
        'gauge_mm' => 'integer',
    ];

    /** @return BelongsTo<Line, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /** @return BelongsTo<Era, $this> */
    public function era(): BelongsTo
    {
        return $this->belongsTo(Era::class);
    }
}
