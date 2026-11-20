<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Suspension d'un compte par un administrateur. Le motif est obligatoire : il est communiqué au
 * membre (CGU, « Validation et modération »). La date de fin est facultative ; sans elle, la
 * suspension dure jusqu'à sa levée par un administrateur.
 */
class SuspendAccountRequest extends FormRequest
{
    public const REASON_MIN = 10;

    public const REASON_MAX = 2000;

    public function authorize(): Response
    {
        $account = $this->route('user');

        return $this->user() !== null && $account instanceof User
            ? Gate::forUser($this->user())->inspect('suspend', $account)
            : Response::denyAsNotFound();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:'.self::REASON_MIN, 'max:'.self::REASON_MAX],
            'until' => ['nullable', 'date_format:Y-m-d', 'after:today'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => __('moderation.errors.reason_required', ['min' => self::REASON_MIN]),
            'reason.min' => __('moderation.errors.reason_required', ['min' => self::REASON_MIN]),
            'reason.max' => __('moderation.errors.reason_max', ['max' => self::REASON_MAX]),
            'until.date_format' => __('moderation.errors.until_format'),
            'until.after' => __('moderation.errors.until_future'),
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }

    /** Dernier jour de la suspension, à la fin de la journée (heure de Paris), ou null. */
    public function until(): ?Carbon
    {
        $date = $this->validated('until');

        return filled($date) ? Carbon::createFromFormat('Y-m-d', (string) $date)->endOfDay() : null;
    }
}
