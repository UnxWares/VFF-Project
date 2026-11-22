<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Notice d'inventaire d'un fonds d'archives rattachée à une ligne (vff:import-sncf-archives) : métadonnées
 * du jeu de données « Archives SNCF » (ODbL) et lien vers la notice sur archives.sncf.com. Ni description,
 * ni image, ni document numérisé ne sont gardés.
 *
 * @property int $id
 * @property int $line_id
 * @property string $source
 * @property ?string $reference
 * @property string $title
 * @property string $doc_type
 * @property ?string $theme
 * @property ?string $sub_theme
 * @property ?string $holder
 * @property ?string $box
 * @property ?string $rights
 * @property ?string $date_label
 * @property ?int $year
 * @property ?int $year_to
 * @property ?Carbon $exact_date
 * @property string $date_precision
 * @property string $url
 * @property int $score
 * @property list<array{code: string, points: int}> $score_reasons
 * @property list<string> $matched_places
 * @property Carbon $fetched_at
 */
class LineArchiveRecord extends Model
{
    /** Fonds connus : Archives du groupe SNCF (jeu de données SARDO). */
    public const SOURCES = ['sncf-archives'];

    /** Types de notices : dossier d'archives, image fixe (photographie, affiche), film. */
    public const TYPES = ['document', 'image', 'video'];

    /** Précisions de date : jour, mois, année, intervalle (year à year_to), inconnue. */
    public const DATE_PRECISIONS = ['day', 'month', 'year', 'range', 'unknown'];

    protected $guarded = ['id'];

    protected $casts = [
        'score_reasons' => 'array',
        'matched_places' => 'array',
        'year' => 'integer',
        'year_to' => 'integer',
        'score' => 'integer',
        'exact_date' => 'date',
        'fetched_at' => 'datetime',
    ];

    /** @return BelongsTo<Line, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }
}
