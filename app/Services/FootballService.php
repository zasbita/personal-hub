<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FootballService
{
    private const API = 'https://v3.football.api-sports.io';

    /**
     * Not-yet-kicked-off fixtures for today and tomorrow, trimmed to the fields
     * the notifier uses. Cached because the API-Sports free plan allows 100
     * requests/day while bot:notify runs every 15 minutes.
     */
    public function getUpcomingFixtures(): array
    {
        return Cache::remember('football.upcoming', now()->addHours(6), function () {
            return array_merge(
                $this->fetch(now()->format('Y-m-d')),
                $this->fetch(now()->addDay()->format('Y-m-d')),
            );
        });
    }

    private function fetch(string $date): array
    {
        $key = config('services.football.api_key', '');
        if (empty($key)) throw new \RuntimeException('Football API Key not configured');
        // from/to need a league or team, so query one date at a time
        $r = Http::withHeaders(['x-apisports-key' => $key])->timeout(15)->get(self::API . '/fixtures', ['date' => $date]);
        if ($r->failed()) throw new \RuntimeException("API Football Error: {$r->body()}");
        $j = $r->json();
        if (!empty($j['errors'])) throw new \RuntimeException('API Football Error: ' . json_encode($j['errors']));
        $out = [];
        foreach ($j['response'] ?? [] as $f) {
            if (($f['fixture']['status']['short'] ?? '') !== 'NS') continue;
            $out[] = [
                'id' => (string) $f['fixture']['id'],
                'date' => $f['fixture']['date'],
                'home' => $f['teams']['home']['name'],
                'away' => $f['teams']['away']['name'],
                'league' => $f['league']['name'],
            ];
        }
        return $out;
    }
}
