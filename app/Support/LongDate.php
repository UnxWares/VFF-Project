<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Date longue dans la langue courante : « 19 décembre 2026 », « 19 December 2026 » (anglais
 * britannique), « 19. Dezember 2026 ».
 */
final class LongDate
{
    public static function format(DateTimeInterface $date): string
    {
        $locale = app()->getLocale();

        return Carbon::instance($date)->locale($locale)->isoFormat($locale === 'de' ? 'D. MMMM YYYY' : 'D MMMM YYYY');
    }
}
