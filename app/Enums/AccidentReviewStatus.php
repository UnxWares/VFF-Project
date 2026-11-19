<?php

namespace App\Enums;

/** État de relecture d'une fiche d'accident importée (accidents.review_status). */
enum AccidentReviewStatus: string
{
    /**
     * Date et lieu confirmés : par une source primaire fiable (enquête du BEA-TT) ou par deux sources
     * indépendantes qui s'accordent (accidents.verification). La précision du lieu reste celle de la
     * source ; le rattachement à une ligne porte sa propre confiance.
     */
    case Ok = 'ok';

    /** Cas douteux : voir accidents.review_notes (bilans divergents, date ou lieu qu'une seule source donne, lieu douteux…). */
    case ToReview = 'to_review';
}
