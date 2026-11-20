<?php

namespace App\Models;

use App\Console\Commands\BuildLineGeometry;
use Clickbar\Magellan\Data\Geometries\MultiLineString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Géométrie de ligne fusionnée et continue par ère (table dérivée).
 *
 * @see BuildLineGeometry
 */
class LineGeometry extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'geom' => MultiLineString::class,
        'length_m' => 'integer',
        'segment_count' => 'integer',
        'part_count' => 'integer',
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
