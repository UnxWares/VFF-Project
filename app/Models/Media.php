<?php

namespace App\Models;

use App\Enums\MediaType;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends Model
{
    /** @use HasFactory<\Database\Factories\MediaFactory> */
    use HasFactory;

    protected $table = 'media';

    protected $guarded = ['id'];

    protected $casts = [
        'type' => MediaType::class,
        'location' => Point::class,
        'exif_data' => 'array',
        'captured_at' => 'date',
        'file_size' => 'integer',
    ];

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }
}
