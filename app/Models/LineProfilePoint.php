<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un point du profil en long (table `line_profile_points`) : altitude du référentiel de la ligne
 * à un PK. Écrit par App\Services\Profiles\LineProfileBuilder ; lu par App\Services\Profiles\LineProfiles.
 * Clé primaire composée (line_profile_id, seq) : lecture seule par Eloquent, l'écriture est groupée en SQL.
 *
 * @property int $line_profile_id
 * @property int $line_id
 * @property int $seq
 * @property int $distance_m
 * @property int|null $pk_m
 * @property float|null $altitude_m
 * @property string|null $source bdtopo | rge_alti | rge_track | null
 * @property bool $interpolated
 * @property string|null $structure tunnel | bridge | null
 * @property int $piece
 * @property int|null $branch
 */
class LineProfilePoint extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'seq';

    protected function casts(): array
    {
        return [
            'seq' => 'integer',
            'distance_m' => 'integer',
            'pk_m' => 'integer',
            'altitude_m' => 'float',
            'interpolated' => 'boolean',
            'piece' => 'integer',
            'branch' => 'integer',
        ];
    }

    /** @return BelongsTo<LineProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(LineProfile::class, 'line_profile_id');
    }
}
