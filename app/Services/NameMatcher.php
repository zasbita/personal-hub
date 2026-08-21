<?php

namespace App\Services;

class NameMatcher
{
    /** Indonesian names for teams the sports APIs report in English. */
    private const ALIASES = [
        'spanyol' => 'spain',
        'inggris' => 'england',
        'jerman' => 'germany',
        'belanda' => 'netherlands',
        'prancis' => 'france',
        'perancis' => 'france',
        'italia' => 'italy',
        'jepang' => 'japan',
        'korea selatan' => 'south korea',
        'amerika' => 'usa',
        'swiss' => 'switzerland',
        'mesir' => 'egypt',
        'turki' => 'turkey',
        'polandia' => 'poland',
        'rusia' => 'russia',
        'yunani' => 'greece',
        'tiongkok' => 'china',
    ];

    /**
     * Loose match of a followed entity against a name coming from an API.
     * Substring so national women's sides ("Indonesia W") match "Indonesia".
     */
    public static function matches(string $apiName, string $search): bool
    {
        $a = strtolower(trim($apiName));
        $s = strtolower(trim($search, " \t\n\r\0\x0B\"'"));
        if ($s === '') return false;
        $s = self::ALIASES[$s] ?? $s;
        return str_contains($a, $s);
    }
}
