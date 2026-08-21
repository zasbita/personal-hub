<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class VolleyballService
{
    private const API = 'https://v1.volleyball.api-sports.io';

    /**
     * Not-yet-started games for today and tomorrow.
     * Cached because the API-Sports free plan allows 100 requests/day while
     * bot:notify runs every 15 minutes.
     */
    public function getUpcomingGames(): array
    {
        return Cache::remember('volleyball.upcoming', now()->addHours(3), function () {
            $all = array_merge(
                $this->fetch(now()->format('Y-m-d')),
                $this->fetch(now()->addDay()->format('Y-m-d')),
            );
            return array_values(array_column($all, null, 'id')); // a game can be listed under both dates
        });
    }

    /** Team names matching $query, for validating what a user follows. */
    public function searchTeams(string $query): array
    {
        return Cache::remember('volleyball.teams.' . strtolower($query), now()->addDay(), function () use ($query) {
            $j = $this->request('/teams', ['search' => $query]);
            return array_map(fn($t) => $t['name'], $j['response'] ?? []);
        });
    }

    private function request(string $endpoint, array $params): array
    {
        $key = config('services.api_sports.key', '');
        if (empty($key)) throw new \RuntimeException('API-Sports key not configured');
        $r = Http::withHeaders(['x-apisports-key' => $key])->timeout(15)->get(self::API . $endpoint, $params);
        if ($r->failed()) throw new \RuntimeException("API Volleyball Error: {$r->body()}");
        $j = $r->json();
        if (!empty($j['errors'])) throw new \RuntimeException('API Volleyball Error: ' . json_encode($j['errors']));
        return $j;
    }

    private function fetch(string $date): array
    {
        $j = $this->request('/games', ['date' => $date]);
        $out = [];
        foreach ($j['response'] ?? [] as $g) {
            if (($g['status']['short'] ?? '') !== 'NS') continue;
            $out[] = [
                'id' => (string) $g['id'],
                'date' => $g['date'],
                'home' => $g['teams']['home']['name'],
                'away' => $g['teams']['away']['name'],
                'league' => $g['league']['name'],
            ];
        }
        return $out;
    }
}
