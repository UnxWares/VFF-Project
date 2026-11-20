<?php

namespace App\Services\Contributions;

use App\Enums\ContentLicense;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Source obligatoire d'une proposition sourcée (événement daté, matériel sur une ligne, série de
 * matériel) : nature, titre ou référence, adresse (sauf pour un ouvrage papier), licence pour un
 * document numérisé. Mêmes règles et même forme dans la charge utile pour tous ces types, sur le site
 * comme dans la mutation GraphQL.
 */
final class SourceCitation
{
    /** Nature de la source : page web, document numérisé (avec sa licence), ouvrage ou archive papier. */
    public const KINDS = ['web', 'document', 'print'];

    /**
     * Règles des champs `source_*`. Une licence n'est lue que pour un document numérisé (exclude_unless).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rules(): array
    {
        return [
            'source_kind' => ['required', 'string', Rule::in(self::KINDS)],
            'source_title' => ['required', 'string', 'min:3', 'max:300'],
            'source_url' => ['nullable', 'required_unless:source_kind,print', 'url:http,https', 'max:500'],
            'source_license' => ['exclude_unless:source_kind,document', 'required', 'string', Rule::enum(ContentLicense::class)],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'source_title.required' => __('proposals.event.errors.source_title'),
            'source_title.min' => __('proposals.event.errors.source_title'),
            'source_url.required_unless' => __('proposals.event.errors.source_url'),
            'source_url.url' => __('editor.errors.bad_url'),
            'source_license.required' => __('proposals.event.errors.source_license'),
        ];
    }

    /**
     * Source de la charge utile, d'après une saisie validée par rules().
     *
     * @param  array<string, mixed>  $data
     * @return array{kind: string, title: ?string, url: ?string, license: ?string}
     */
    public static function fromInput(array $data): array
    {
        return [
            'kind' => (string) $data['source_kind'],
            'title' => self::clean($data['source_title'] ?? null),
            'url' => self::clean($data['source_url'] ?? null),
            'license' => isset($data['source_license']) ? (string) $data['source_license'] : null,
        ];
    }

    /**
     * Référence publiée de la source : son titre et, pour un document, sa licence
     * (« Rapport 1938 (licence : Public domain) »).
     *
     * @param  array<string, mixed>  $source  source de la charge utile (fromInput)
     */
    public static function reference(array $source): ?string
    {
        $title = $source['title'] ?? null;
        if (! is_string($title) || $title === '') {
            return null;
        }
        $license = ContentLicense::tryFrom((string) ($source['license'] ?? ''));

        return $license !== null ? "{$title} (licence : {$license->label()})" : $title;
    }

    /** Texte d'une ligne : espaces de début et de fin retirés, blancs intérieurs réduits à un seul. */
    public static function clean(mixed $value): ?string
    {
        $text = is_string($value) ? trim((string) preg_replace('/\s+/u', ' ', $value)) : '';

        return $text === '' ? null : $text;
    }

    /** Texte libre sur plusieurs lignes : seulement les blancs de début et de fin retirés. */
    public static function trimmed(mixed $value): ?string
    {
        $text = is_string($value) ? trim($value) : '';

        return $text === '' ? null : $text;
    }
}
