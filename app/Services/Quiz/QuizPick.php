<?php

namespace App\Services\Quiz;

/**
 * Une question tirée : le sujet à reconnaître et les quatre propositions dans l'ordre affiché
 * (le sujet en fait partie). Reste côté serveur : seule QuizSource::question() en sort, sans le sujet.
 */
final readonly class QuizPick
{
    /**
     * @param  array<string, mixed>  $subject  élément du vivier (`id`, `key`, `label` et les champs propres au jeu)
     * @param  list<array<string, mixed>>  $options  propositions, sujet compris
     */
    public function __construct(
        public int $index,
        public array $subject,
        public array $options,
    ) {}

    /**
     * Clés des propositions, dans l'ordre affiché.
     *
     * @return list<string>
     */
    public function optionKeys(): array
    {
        return array_map(fn (array $o): string => (string) $o['key'], $this->options);
    }

    /**
     * Propositions telles que la page les reçoit : clé et libellé seulement.
     *
     * @return list<array{key: string, label: string}>
     */
    public function publicOptions(): array
    {
        return array_map(fn (array $o): array => ['key' => (string) $o['key'], 'label' => QuizLabel::of($o)], $this->options);
    }
}
