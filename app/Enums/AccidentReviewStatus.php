<?php

namespace App\Enums;

/** État de relecture d'une fiche d'accident importée (accidents.review_status). */
enum AccidentReviewStatus: string
{
    /** Sources concordantes, position et ligne établies. */
    case Ok = 'ok';

    /** Cas douteux : voir accidents.review_notes (bilans divergents, lieu vague, ligne incertaine…). */
    case ToReview = 'to_review';
}
