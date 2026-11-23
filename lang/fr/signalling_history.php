<?php

// Historique de la signalisation (vff:import-signalling-history) : frise de la fiche ligne.
// Faits relevés dans les articles de ligne de Wikipédia (CC BY-SA 4.0).
return [
    'title' => 'Signalisation et protection au fil du temps',
    'intro' => 'Dates de mise en service relevées dans l’article Wikipédia de la ligne et dans d’autres documents (décisions et rapports de l’EPSF, bilans de SNCF Réseau et de l’État, presse). Le relevé est partiel : un système absent de la liste a pu être mis en service sans qu’aucun de ces documents n’en donne la date.',
    'system' => [
        'BM' => 'BM, block manuel',
        'CT' => 'Cantonnement téléphonique',
        'CC' => 'Commande centralisée (CCVU, CCR, PCD)',
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
    // Origine d'un fait qui ne vient pas d'une phrase de l'article.
    'origin' => [
        'opening' => 'Date d’ouverture de la ligne : une ligne à grande vitesse roule en signalisation en cabine dès son ouverture.',
        'infobox' => 'Date donnée par l’infobox de l’article.',
        'table' => 'Date donnée par un tableau de l’article.',
    ],
    'source' => [
        'link' => 'Wikipédia',
        'hint' => 'article « :title », nouvel onglet',
        'note_one' => 'Faits relevés dans l’article « :title » de Wikipédia (licence CC BY-SA 4.0). Le jour ou le mois n’apparaît que si l’article le donne.',
        'note_other' => 'Faits relevés dans les articles de Wikipédia « :title » (licence CC BY-SA 4.0). Le jour ou le mois n’apparaît que si l’article le donne.',
        'document_hint' => ':title, nouvel onglet',
        'note_documents' => 'Quand le lien porte le nom d’un éditeur, la date vient de ce document, confirmée par une source officielle ou par deux sources indépendantes. Le jour ou le mois n’apparaît que si ces documents le donnent.',
    ],
    'at' => [
        'intro' => 'Date choisie dans « :section » : :date. Chaque mise en service est située par rapport à cette date.',
        'change' => 'Choisir une autre date',
    ],
    'status' => [
        'in_force' => 'En vigueur à cette date',
        'replaced' => 'Remplacé par :system (:when)',
        'later' => 'Pas encore en service à cette date',
        'uncertain_year' => 'Date connue à l’année près : en service avant ou après cette date',
        'uncertain_month' => 'Date connue au mois près : en service avant ou après cette date',
    ],
    // Dates contredites par des documents (corrections sourcées), repliées sous la frise.
    'corrections' => [
        'summary' => 'Dates contredites par des documents (:count)',
        'intro' => 'Ces dates, lues dans un article ou un document, sont contredites par des documents plus sûrs. Elles ne sont ni dans la frise ni sur la carte.',
        'read_in' => 'Date lue dans',
        'original_document' => 'un document',
        'action' => [
            'neutralize' => 'Écartée',
            'replace' => 'Remplacée dans la frise par la date des documents',
        ],
        'reason' => 'Pourquoi :',
        'documents' => 'Contredite par :',
    ],
];
