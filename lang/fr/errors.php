<?php

// Pages d'erreur (resources/js/Pages/Error.svelte, resources/views/errors/minimal.blade.php).
return [
    'eyebrow' => 'Erreur :code',
    'back' => 'Revenir en arrière',
    'home' => 'Retour à l’accueil',
    'reload' => 'Recharger la page',
    'login' => 'Se connecter',
    'explore' => 'Pour reprendre votre visite',
    'map' => 'Ouvrir la carte',
    'lines' => 'Parcourir les lignes',
    'stations' => 'Parcourir les gares',
    'retry_after' => 'Vous pouvez réessayer dans :seconds secondes.',

    '403' => [
        'title' => 'Accès refusé',
        'text' => 'Vous n’avez pas l’autorisation d’ouvrir cette page. Si vous pensez qu’il s’agit d’une erreur, connectez-vous avec le bon compte ou écrivez-nous.',
    ],
    '404' => [
        'title' => 'Page introuvable',
        'text' => 'L’adresse demandée n’existe pas, ou la page a changé de place. Vérifiez l’adresse, ou reprenez depuis l’accueil, la carte ou la liste des lignes.',
    ],
    '405' => [
        'title' => 'Demande non acceptée',
        'text' => 'Cette adresse ne répond pas à ce type de demande. Revenez à la page précédente ou repartez de l’accueil.',
    ],
    '419' => [
        'title' => 'Session expirée',
        'text' => 'La page est restée ouverte trop longtemps et votre session a expiré. Rechargez la page, puis recommencez : ce que vous aviez saisi n’a pas été enregistré.',
    ],
    '429' => [
        'title' => 'Trop de requêtes',
        'text' => 'Vous avez envoyé trop de demandes en peu de temps. Patientez un moment avant de réessayer.',
    ],
    '500' => [
        'title' => 'Erreur du serveur',
        'text' => 'Un problème est survenu de notre côté et il a été enregistré. Réessayez dans quelques instants ; s’il persiste, signalez-le-nous sur Discord ou GitHub.',
    ],
    '503' => [
        'title' => 'Service momentanément indisponible',
        'text' => 'Le site est en maintenance ou très sollicité. Réessayez dans quelques minutes.',
    ],
    'default' => [
        'title' => 'Une erreur est survenue',
        'text' => 'La page n’a pas pu s’afficher. Réessayez, ou repartez de l’accueil.',
    ],
];
