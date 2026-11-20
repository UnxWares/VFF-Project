<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Document d'archives rattaché à une ligne (vff:import-gallica) : métadonnées d'une notice du catalogue
 * général de la BnF qui décrit un document numérisé en accès libre dans Gallica. Aucune image ni texte
 * numérisé n'est gardé : le lien `gallica_url` mène au document chez la BnF.
 *
 * @property int $id
 * @property int $line_id
 * @property string $source
 * @property string $gallica_ark
 * @property ?string $catalogue_ark
 * @property string $title
 * @property string $doc_type
 * @property list<string> $authors
 * @property ?string $date_label
 * @property ?int $year
 * @property ?int $year_to
 * @property ?Carbon $exact_date
 * @property string $date_precision
 * @property ?string $shelfmark
 * @property int $score
 * @property list<array{code: string, points: int}> $score_reasons
 * @property list<string> $matched_places
 * @property ?Carbon $record_updated_on
 * @property Carbon $fetched_at
 * @property-read string $gallica_url
 * @property-read ?string $catalogue_url
 */
class LineArchiveDocument extends Model
{
    /** Types de documents retenus : texte imprimé, document cartographique, image fixe, manuscrit. */
    public const TYPES = ['monograph', 'map', 'image', 'manuscript'];

    /** Précisions de date : jour, mois, année, date approchée, intervalle (year à year_to), inconnue. */
    public const DATE_PRECISIONS = ['day', 'month', 'year', 'circa', 'range', 'unknown'];

    protected $guarded = ['id'];

    protected $casts = [
        'authors' => 'array',
        'score_reasons' => 'array',
        'matched_places' => 'array',
        'year' => 'integer',
        'year_to' => 'integer',
        'score' => 'integer',
        'exact_date' => 'date',
        'record_updated_on' => 'date',
        'fetched_at' => 'datetime',
    ];

    /** @return BelongsTo<Line, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /** Document dans Gallica (« https://gallica.bnf.fr/ark:/12148/bpt6k1192344q »). */
    public function getGallicaUrlAttribute(): string
    {
        return 'https://gallica.bnf.fr/'.$this->gallica_ark;
    }

    /** Notice du catalogue général de la BnF, nulle si l'ark de la notice manque. */
    public function getCatalogueUrlAttribute(): ?string
    {
        return $this->catalogue_ark === null ? null : 'https://catalogue.bnf.fr/'.$this->catalogue_ark;
    }
}
