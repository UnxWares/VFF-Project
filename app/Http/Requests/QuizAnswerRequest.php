<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

/** Réponse à une question du quiz : la question (QuizQuestionRequest) et la clé de la proposition choisie. */
class QuizAnswerRequest extends QuizQuestionRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return parent::rules() + [
            'choice' => ['required', 'string', 'max:255'],
        ];
    }

    public function choice(): string
    {
        return $this->string('choice')->toString();
    }
}
