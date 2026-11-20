<?php

namespace App\Http\Requests;

use App\Enums\QuizGame;
use App\Enums\QuizLevel;
use App\Services\Quiz\QuizSeries;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Désignation d'une question du quiz : jeu, niveau, graine de la série et numéro de la question
 * (0 à 9). Lue dans l'adresse ; la réponse JSON dit l'erreur (422) au lieu de rediriger.
 */
class QuizQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'game' => ['required', Rule::enum(QuizGame::class)],
            'level' => ['required', Rule::enum(QuizLevel::class)],
            'seed' => ['required', 'string', 'regex:'.QuizSeries::SEED_PATTERN],
            'q' => ['required', 'integer', 'min:0', 'max:'.(QuizSeries::LENGTH - 1)],
        ];
    }

    public function game(): QuizGame
    {
        return QuizGame::from($this->string('game')->toString());
    }

    public function level(): QuizLevel
    {
        return QuizLevel::from($this->string('level')->toString());
    }

    public function seed(): string
    {
        return $this->string('seed')->toString();
    }

    public function index(): int
    {
        return $this->integer('q');
    }
}
