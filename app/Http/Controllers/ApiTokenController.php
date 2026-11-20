<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApiTokenRequest;
use App\Services\Api\ApiTokenRefused;
use App\Services\Api\ApiTokens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Jetons d'API personnels, depuis le profil (section « Jetons d'accès à l'API »). Le secret du
 * jeton créé passe une seule fois par la session (donnée flash `api_token_created`), lue par
 * ProfileController::show puis oubliée.
 */
class ApiTokenController extends Controller
{
    public const FLASH = 'api_token_created';

    public function __construct(private readonly ApiTokens $tokens) {}

    public function store(StoreApiTokenRequest $request): RedirectResponse
    {
        try {
            $token = $this->tokens->create($request->user(), $request->validated('token_name'), $request->wantsContribute());
        } catch (ApiTokenRefused $refused) {
            return back()->withErrors(['token_name' => $refused->getMessage()]);
        }

        return back()
            ->with('success', __('api_tokens.flash.created'))
            ->with(self::FLASH, ['id' => (int) $token->accessToken->id, 'plain' => $token->plainTextToken]);
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        abort_unless($this->tokens->revoke($request->user(), $token), 404);

        return back()->with('success', __('api_tokens.flash.revoked'));
    }
}
