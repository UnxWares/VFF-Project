<?php

namespace App\Services\Quiz;

use App\Enums\QuizGame;
use App\Enums\QuizLevel;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Série de questions du quiz (/quiz), tirée d'une graine.
 *
 * Le tirage ne dépend que du jeu, du niveau, de la graine et du vivier. Chaque sujet a son rang propre
 * (hachage de la graine et de sa clé) et son propre générateur pour les propositions : un sujet entré
 * dans le vivier ou sorti de lui ne déplace pas les autres questions, et une adresse partagée redonne la
 * même série tant que ses dix questions n'ont pas changé.
 *
 * Rien n'est gardé côté serveur ; pour vérifier une réponse, la série est tirée de nouveau (le vivier est
 * en cache, le tirage ne lit pas la base). L'adresse porte donc aussi la version de la série (version() :
 * empreinte des sujets et des propositions). Si la base a changé au point de modifier la série, la
 * version ne correspond plus : la page le dit et propose une nouvelle série, et une réponse n'est jamais
 * jugée sur une autre question que celle qui a été affichée (QuizController).
 *
 * La page reçoit les questions sans la réponse (questions()) ; la réponse sort seulement par reveal(),
 * appelée après le choix du joueur.
 */
final class QuizSeries
{
    /** Questions par série. */
    public const LENGTH = 10;

    /** Propositions par question, la bonne comprise. */
    public const OPTIONS = 4;

    /** Niveau facile : les trois autres propositions sont tirées parmi les N plus plausibles. */
    public const EASY_SPREAD = 12;

    /** Forme d'une graine lue dans l'adresse. */
    public const SEED_PATTERN = '/^[a-z0-9]{4,16}$/';

    /** Forme de la version d'une série (version()) lue dans l'adresse. */
    public const VERSION_PATTERN = '/^[a-f0-9]{8}$/';

    public function __construct(
        private readonly LineQuiz $lines,
        private readonly MaterialQuiz $materials,
    ) {}

    /** Nouvelle graine (8 caractères hexadécimaux). */
    public static function newSeed(): string
    {
        return bin2hex(random_bytes(4));
    }

    public static function validSeed(string $seed): bool
    {
        return preg_match(self::SEED_PATTERN, $seed) === 1;
    }

    /**
     * Version d'une série : empreinte courte de ses sujets et de ses propositions, dans l'ordre. Deux tirages
     * de même version posent exactement les mêmes questions avec les mêmes réponses.
     *
     * @param  list<QuizPick>  $picks
     */
    public static function version(array $picks): string
    {
        $shape = array_map(fn (QuizPick $p): array => [(string) $p->subject['key'], $p->optionKeys()], $picks);

        return substr(hash('sha256', (string) json_encode($shape)), 0, 8);
    }

    public function source(QuizGame $game): QuizSource
    {
        return match ($game) {
            QuizGame::Lines => $this->lines,
            QuizGame::Materials => $this->materials,
        };
    }

    /** Le jeu a-t-il assez de sujets pour une question à quatre propositions ? */
    public function available(QuizGame $game): bool
    {
        return count($this->source($game)->pool()) >= self::OPTIONS;
    }

    /**
     * Tirage de la série : sujets distincts, chacun avec trois autres propositions de libellés distincts.
     *
     * @return list<QuizPick>
     */
    public function picks(QuizGame $game, QuizLevel $level, string $seed): array
    {
        $source = $this->source($game);
        $pool = $source->pool();
        if (count($pool) < self::OPTIONS) {
            return [];
        }
        $prefix = "{$game->value}|{$level->value}|{$seed}|";
        // Rang de chaque sujet : il ne dépend que de la graine et de la clé du sujet, pas du reste du vivier.
        $ranked = array_map(fn (array $s): array => [hash('xxh128', $prefix.$s['key']), $s], $pool);
        usort($ranked, fn (array $a, array $b): int => [$a[0], $a[1]['id']] <=> [$b[0], $b[1]['id']]);

        $picks = [];
        foreach (array_column($ranked, 1) as $subject) {
            if (count($picks) === self::LENGTH) {
                break;
            }
            $others = $this->distinctLabels($subject, $source->candidates($subject, $pool, $level));
            if (count($others) < self::OPTIONS - 1) {
                continue;
            }
            // Générateur propre au sujet : ses propositions ne dépendent pas des questions précédentes.
            $rng = new Randomizer(new Xoshiro256StarStar(hash('sha256', $prefix.$subject['key'], true)));
            $chosen = match ($level) {
                QuizLevel::Hard => array_slice($others, 0, self::OPTIONS - 1),
                QuizLevel::Easy => $this->sample($rng, array_slice($others, 0, self::EASY_SPREAD), self::OPTIONS - 1),
            };
            $picks[] = new QuizPick(count($picks), $subject, $rng->shuffleArray([$subject, ...$chosen]));
        }

        return $picks;
    }

    /**
     * Questions d'un tirage (picks()), telles que la page les reçoit (sans la réponse).
     *
     * @param  list<QuizPick>  $picks
     * @return list<array<string, mixed>>
     */
    public function questions(QuizGame $game, QuizLevel $level, string $seed, array $picks): array
    {
        $source = $this->source($game);
        $version = self::version($picks);

        return array_map(function (QuizPick $pick) use ($source, $game, $level, $seed, $version): array {
            $question = ['index' => $pick->index, 'options' => $pick->publicOptions()] + $source->question($pick);
            if (isset($question['photo']) && is_array($question['photo'])) {
                // L'adresse de l'image Commons contient souvent le nom de la série : la question donne celle
                // d'une redirection du site, pour que le nom ne figure ni dans la page ni dans ses données.
                // Le navigateur suit la redirection : l'adresse finale reste visible dans ses outils.
                $question['photo']['url'] = route('quiz.photo', ['game' => $game->value, 'level' => $level->value, 'seed' => $seed, 'v' => $version, 'q' => $pick->index], false);
            }

            return $question;
        }, $picks);
    }

    /** Question n° `$index` de la série, ou null si elle n'existe pas. */
    public function pick(QuizGame $game, QuizLevel $level, string $seed, int $index): ?QuizPick
    {
        return $this->picks($game, $level, $seed)[$index] ?? null;
    }

    /**
     * Réponse à une question, après le choix du joueur : null si la proposition choisie n'est pas
     * l'une des quatre de la question.
     *
     * @return array<string, mixed>|null
     */
    public function reveal(QuizGame $game, QuizPick $pick, string $choice): ?array
    {
        if (! in_array($choice, $pick->optionKeys(), true)) {
            return null;
        }

        return ['correct' => $choice === (string) $pick->subject['key'], 'choice' => $choice]
            + $this->source($game)->reveal($pick->subject);
    }

    /**
     * Candidats dont le libellé diffère de celui du sujet et des précédents (deux séries ou deux lignes
     * de même nom ne peuvent pas être proposées ensemble).
     *
     * @param  array<string, mixed>  $subject
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    private function distinctLabels(array $subject, array $candidates): array
    {
        $seen = [self::labelKey((string) $subject['label']) => true];
        $out = [];
        foreach ($candidates as $c) {
            $k = self::labelKey((string) $c['label']);
            if (! isset($seen[$k]) && $c['id'] !== $subject['id']) {
                $seen[$k] = true;
                $out[] = $c;
            }
        }

        return $out;
    }

    private static function labelKey(string $label): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($label)) ?? $label);
    }

    /**
     * `$n` éléments distincts tirés au hasard, dans l'ordre de la liste.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function sample(Randomizer $rng, array $items, int $n): array
    {
        $keys = (array) $rng->pickArrayKeys($items, $n);

        return array_map(fn (int $k): array => $items[$k], $keys);
    }
}
