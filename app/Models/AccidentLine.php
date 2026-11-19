<?php

namespace App\Models;

use App\Enums\AccidentLinkConfidence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rattachement d'un accident à une ligne VFF : ligne principale ou secondaire (jonction), ère du
 * tracé utilisé, distance, PK estimé, portion entre deux gares, méthode et confiance.
 *
 * @property int $id
 * @property int $accident_id
 * @property int $line_id
 * @property bool $is_primary
 * @property int|null $era_year
 * @property int|null $distance_m
 * @property int|null $pk_m
 * @property int|null $section_from_station_id
 * @property int|null $section_to_station_id
 * @property string $method
 * @property AccidentLinkConfidence $confidence
 * @property float $score
 */
class AccidentLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'era_year' => 'integer',
            'distance_m' => 'integer',
            'pk_m' => 'integer',
            'confidence' => AccidentLinkConfidence::class,
            'score' => 'float',
        ];
    }

    /** @return BelongsTo<Accident, $this> */
    public function accident(): BelongsTo
    {
        return $this->belongsTo(Accident::class);
    }

    /** @return BelongsTo<Line, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /** @return BelongsTo<Station, $this> */
    public function sectionFrom(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'section_from_station_id');
    }

    /** @return BelongsTo<Station, $this> */
    public function sectionTo(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'section_to_station_id');
    }
}
