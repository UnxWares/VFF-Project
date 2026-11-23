<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Une référence bibliographique (ouvrage ou article) qui traite de lignes ou de gares (vff:import-bibliography) :
 * seulement les données de la notice, jamais le contenu de l'ouvrage.
 *
 * @property int $id
 * @property string $dedup_key
 * @property string $kind book ou article
 * @property string $title
 * @property list<string>|null $authors
 * @property ?string $publisher
 * @property ?string $place
 * @property ?int $year
 * @property ?string $isbn ISBN-13 sans séparateur
 * @property ?string $journal
 * @property ?string $bnf_ark
 * @property string $provider bnf, wikipedia ou wikidata
 * @property ?string $url notice de la BnF, à défaut référence d'origine
 * @property ?Carbon $fetched_at
 */
class BibliographyEntry extends Model
{
    public const KINDS = ['book', 'article'];

    public const PROVIDERS = ['bnf', 'wikipedia', 'wikidata'];

    protected $guarded = ['id'];

    protected $casts = [
        'authors' => 'array',
        'year' => 'integer',
        'fetched_at' => 'datetime',
    ];

    /** @return HasMany<BibliographyLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(BibliographyLink::class, 'entry_id');
    }
}
