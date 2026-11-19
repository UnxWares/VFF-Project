<?php

namespace App\Services\Accidents;

/**
 * Un accident en France cité par une liste de Wikipédia en anglais ou en allemand : sa date, les lieux que
 * l'entrée nomme (déjà filtrés : seuls comptent ceux qu'elle introduit comme lieu de l'accident), et si
 * l'entrée cite elle-même une référence (livre, presse) qui n'est pas Wikipédia. Aucun texte n'est gardé.
 */
final readonly class AccidentEditionEntry
{
    /**
     * @param  'en'|'de'  $lang  édition de Wikipédia
     * @param  list<array{name: string, qualifier: ?string}>  $places  noms de lieux ; qualificatif = département ou précision (« Ardèche »)
     * @param  list<string>  $references  références citées par l'entrée, en clair court (« Semmens 1994 », « sudouest.fr »)
     */
    public function __construct(
        public string $lang,
        public string $date,
        public array $places,
        public bool $referenced,
        public array $references,
        public string $title,
        public int $revision,
        public ?string $anchor,
    ) {}

    /** Permalien de la révision lue, avec l'ancre de la section. */
    public function url(): string
    {
        return "https://{$this->lang}.wikipedia.org/w/index.php?title=".rawurlencode(str_replace(' ', '_', $this->title)).'&oldid='.$this->revision
            .($this->anchor !== null ? '#'.rawurlencode($this->anchor) : '');
    }
}
