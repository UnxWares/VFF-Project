<?php

namespace App\Http\Controllers;

use App\Enums\AccountDeletionReason;
use App\Http\Requests\DeleteAccountRequest;
use App\Services\Account\AccountDataExport;
use App\Services\Account\AccountDeletion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Droits du titulaire d'un compte (politique de confidentialité, « Vos droits ») : télécharger
 * ses données et supprimer son compte. La section « Mes données » du profil appelle ces routes.
 */
class AccountController extends Controller
{
    /** Droit d'accès et de portabilité : fichier JSON téléchargé. */
    public function export(Request $request, AccountDataExport $export): JsonResponse
    {
        $filename = 'vff-account-data-'.now()->format('Y-m-d').'.json';

        return response()
            ->json($export->for($request->user()), 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"')
            ->header('Cache-Control', 'no-store, private');
    }

    /** Suppression du compte, après confirmation du mot de passe (DeleteAccountRequest). */
    public function destroy(DeleteAccountRequest $request, AccountDeletion $deletion): RedirectResponse
    {
        $user = $request->user();

        // Déconnexion AVANT la suppression : la déconnexion renouvelle le jeton « se souvenir de
        // moi » et enregistre le compte, ce qui le recréerait s'il était déjà supprimé.
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $deletion->delete($user, AccountDeletionReason::Owner);

        return redirect()->route('home')->with('success', __('account.flash.deleted'));
    }
}
