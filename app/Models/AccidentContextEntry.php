<?php

namespace App\Models;

use App\Enums\AccidentContextSection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Énoncé du contexte d'un accident, rédigé par VFF en fr, en et de, avec les clés des documents qui
 * l'appuient (accident_references.key) et, rarement, une courte citation exacte d'une source ouverte.
 *
 * @property int $id
 * @property int $accident_id
 * @property AccidentContextSection $section
 * @property int $position
 * @property array<string, string> $text
 * @property list<string> $reference_keys
 * @property array{text: string, lang: string, reference: string}|null $quote
 */
class AccidentContextEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'section' => AccidentContextSection::class,
            'position' => 'integer',
            'text' => 'array',
            'reference_keys' => 'array',
            'quote' => 'array',
        ];
    }

    /** Texte dans la langue demandée, en français à défaut. */
    public function textIn(string $locale): string
    {
        return $this->text[$locale] ?? $this->text['fr'] ?? '';
    }

    /** @return BelongsTo<Accident, $this> */
    public function accident(): BelongsTo
    {
        return $this->belongsTo(Accident::class);
    }
}
