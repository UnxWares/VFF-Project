<?php

// Historique de la signalisation (vff:import-signalling-history) : frise de la fiche ligne.
// Faits relevés dans les articles de ligne de Wikipédia (CC BY-SA 4.0).
return [
    'title' => 'Signalisation au fil du temps',
    'intro' => 'Dates de mise en service relevées dans l’article Wikipédia de la ligne. Le relevé est partiel : un système absent de la liste a pu être mis en service sans que l’article en donne la date.',
    'system' => [
        'BM' => 'BM, block manuel',
        'CT' => 'Cantonnement téléphonique',
        'TVM' => 'TVM',
        'ETCS' => 'ETCS',
    ],
    'section' => [
        'whole_line' => 'Sur toute la ligne',
        'unspecified' => 'Portion non précisée',
        'between' => 'De :from à :to',
        'from' => 'À partir de :from',
        'to' => 'Jusqu’à :to',
        'pk' => ':pk :from à :to',
    ],
    'source' => [
        'link' => 'Wikipédia',
        'hint' => 'article « :title », nouvel onglet',
        'note_one' => 'Faits relevés dans l’article « :title » de Wikipédia (licence CC BY-SA 4.0). Le jour ou le mois n’apparaît que si l’article le donne.',
        'note_other' => 'Faits relevés dans les articles de Wikipédia « :title » (licence CC BY-SA 4.0). Le jour ou le mois n’apparaît que si l’article le donne.',
    ],
];
