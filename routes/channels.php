<?php

use App\Broadcasting\ContributorChannel;
use App\Broadcasting\ReviewQueueChannel;
use Illuminate\Support\Facades\Broadcast;

/*
| Canaux privés du temps réel (docs/API.md, section « Temps réel »), autorisés par /broadcasting/auth
| pour un membre connecté au site. Le canal public `contributions` n'a pas besoin d'autorisation.
| Rien n'est diffusé tant que le temps réel est désactivé (App\Support\Realtime).
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel(ReviewQueueChannel::NAME, ReviewQueueChannel::class);
Broadcast::channel(ContributorChannel::PATTERN, ContributorChannel::class);
