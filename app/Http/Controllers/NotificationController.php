<?php

namespace App\Http\Controllers;

use App\Models\DatabaseNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Notifications du membre connecté (page /notifications) : liste paginée, ouverture d'une
 * notification (marquée comme lue, puis redirection vers la contribution) et « tout marquer
 * comme lu ». Le nombre non lu du menu du compte est partagé par HandleInertiaRequests.
 */
class NotificationController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $notifications = $request->user()->notifications()->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications->through(fn (DatabaseNotification $n): array => [
                'id' => $n->id,
                'kind' => $n->data['kind'] ?? null,
                'title' => (string) ($n->data['title'] ?? ''),
                'body' => (string) ($n->data['body'] ?? ''),
                'reason' => $n->data['reason'] ?? null,
                'has_link' => filled($n->data['url'] ?? null),
                // ISO 8601 : le navigateur formate la date dans la langue de la page.
                'created_at' => $n->created_at?->toIso8601String(),
                'read' => $n->read_at !== null,
            ]),
        ]);
    }

    /** Ouvre une notification : elle devient lue, puis on suit son lien (sinon retour à la liste). */
    public function open(Request $request, string $notification): RedirectResponse
    {
        // Identifiant UUID vérifié ici plutôt que par la route : un visiteur est d'abord renvoyé
        // vers la connexion (middleware auth), quel que soit l'identifiant demandé.
        abort_unless(Str::isUuid($notification), 404);
        $found = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $found->markAsRead();

        $url = $found->data['url'] ?? null;

        // Lien relatif écrit par l'application (ContributionNotification::path) : jamais une
        // adresse externe, même si la donnée était altérée.
        return is_string($url) && self::isInternalPath($url)
            ? redirect($url)
            : redirect()->route('notifications.index');
    }

    /**
     * Chemin interne du site : commence par une seule barre, sans barre inverse (les navigateurs
     * lisent « /\hôte » comme « //hôte »), sans caractère de contrôle, et sans protocole ni hôte
     * selon parse_url.
     */
    private static function isInternalPath(string $url): bool
    {
        if (! str_starts_with($url, '/') || str_starts_with($url, '//') || str_contains($url, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return false;
        }
        $parts = parse_url($url);

        return $parts !== false && ! isset($parts['scheme']) && ! isset($parts['host']);
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', __('notifications.flash.all_read'));
    }
}
