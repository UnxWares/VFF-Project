<?php

use App\Broadcasting\ContributorChannel;
use App\Broadcasting\ReviewQueueChannel;
use App\Http\Middleware\EnsureRealtimeEnabled;
use Illuminate\Support\Facades\Broadcast;

/*
| Canaux privés du temps réel (docs/API.md, section « Temps réel »), autorisés par /broadcasting/auth
| pour un membre connecté au site. Le canal public `contributions` n'a pas besoin d'autorisation.
| Rien n'est diffusé tant que le temps réel est désactivé (App\Support\Realtime). Pas de canal
| `App.Models.User.{id}` du modèle de Laravel : rien n'y était diffusé, et il admettait un numéro
| suivi de lettres (`1abc`).
*/

// Identification de la connexion auprès de Reverb (`/broadcasting/user-auth`) : il peut alors couper les
// connexions d'un compte suspendu ou rétrogradé (App\Services\Realtime\RealtimeRevocation). Seul le
// numéro du compte est signé.
Broadcast::userRoutes(['middleware' => ['web', EnsureRealtimeEnabled::class, 'auth']]);
Broadcast::resolveAuthenticatedUserUsing(fn ($request): ?array => $request->user() === null ? null : ['id' => (string) $request->user()->getAuthIdentifier()]);

Broadcast::channel(ReviewQueueChannel::NAME, ReviewQueueChannel::class);
Broadcast::channel(ContributorChannel::PATTERN, ContributorChannel::class);
