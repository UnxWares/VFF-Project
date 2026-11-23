<?php

/*
| Textes légaux : version en vigueur de chacun, sous la forme de sa date (AAAA-MM-JJ), affichée en
| haut de la page publique (« Dernière mise à jour ») et enregistrée avec l'acceptation des CGU à
| l'inscription (users.terms_version). À changer en même temps que le texte publié
| (lang/{fr,en,de}/use_conditions.php ou privacy.php), puis prévenir les membres d'un changement
| important : php artisan vff:notify-legal-change.
*/
return [
    'versions' => [
        'use-conditions' => '2026-11-20',
        'privacy' => '2026-11-23',
    ],
];
