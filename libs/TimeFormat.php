<?php

declare(strict_types=1);

// Time as in the tiles: "Today 18:08" on the same calendar day, otherwise "15.10. 18:08"
class TimeFormat
{
    public static function short(int $time, int $now, string $today): string
    {
        if (date('Y-m-d', $time) === date('Y-m-d', $now)) {
            return $today . ' ' . date('H:i', $time);
        }
        return date('d.m. H:i', $time);
    }
}
