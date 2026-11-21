<?php

// Application web installable (resources/js/pwa) : bandeau de mise à jour et page hors ligne.
return [
    'update_available' => 'Une nouvelle version du site est disponible.',
    'update_reload' => 'Recharger',
    'update_later' => 'Plus tard',

    'offline_document_title' => 'Hors ligne - Voies Ferrées de France',
    'offline_title' => 'Vous êtes hors ligne',
    'offline_text' => 'Cette page n’a pas été enregistrée sur cet appareil : elle n’est lisible qu’avec une connexion. Vérifiez votre réseau, puis réessayez.',
    'offline_retry' => 'Réessayer',

    // Copie d'une page servie sans réseau (Components/OfflineCopyNotice.svelte) ; :date = date et heure de la copie.
    'offline_copy' => 'Vous êtes hors ligne. Cette page est la copie enregistrée sur cet appareil le :date ; elle a pu changer depuis.',
    'offline_map_partial' => 'La carte n’a pas de fond de plan et ne montre que les tracés et les gares déjà affichés sur cet appareil.',
    'offline_map_basemap' => 'La carte ne montre que ce qui a déjà été affiché sur cet appareil : les tracés, les gares et le fond de plan des zones déjà vues.',
];
