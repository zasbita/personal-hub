<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MotoGPService
{
    public function getCurrentSeasonRaces(string $class = 'motogp'): array
    {
        $r = Http::get("https://ergast.com/api/moto/{$class}/current/races.json");
        if ($r->failed()) throw new \RuntimeException("Failed to fetch {$class} races");
        $d = $r->json();
        $races = $d['MRData']['RaceTable']['Races'] ?? [];
        $now = new \DateTime();
        return array_values(array_filter($races, fn($race) => new \DateTime($race['date'] . 'T' . ($race['time'] ?? '00:00:00')) > $now));
    }

    public function matchesRace(string $raceName, string $search): bool
    {
        $l = strtolower($raceName);
        $s = strtolower($search);
        return str_contains($l, $s) || str_contains($s, explode(' ', $l)[0] ?? '');
    }

    public function formatRaceInfo(array $race): string
    {
        $dt = new \DateTime($race['date'] . 'T' . ($race['time'] ?? '00:00:00'));
        return "🏍️ *{$race['raceName']}*\n🏁 {$race['Circuit']['circuitName']}\n📍 {$race['Circuit']['Location']['locality']}, {$race['Circuit']['Location']['country']}\n⏱️ " . $dt->format('d/m/Y H:i');
    }
}
