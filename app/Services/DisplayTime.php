<?php

namespace App\Services;

class DisplayTime
{
    /** Format an ISO timestamp in the timezone the notifications are read in. */
    public static function format(string $iso): string
    {
        return (new \DateTimeImmutable($iso))
            ->setTimezone(new \DateTimeZone(config('app.display_timezone', 'Asia/Jakarta')))
            ->format('d/m/Y H:i T');
    }
}
