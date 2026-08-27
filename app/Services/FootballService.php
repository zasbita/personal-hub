<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FootballService
{
    private const API = 'https://v3.football.api-sports.io';

    /**
     * Not-yet-kicked-off fixtures for the next 7 days, trimmed to the fields
     * the notifier uses. Cached because the API-Sports free plan allows 100
     * requests/day while bot:notify runs every 15 minutes.
     */
    public function getUpcomingFixtures(): array
    {
        return Cache::remember('football.upcoming', now()->addHours(3), function () {
            $all = [];
            for ($i = 0; $i < 7; $i++) {
                try {
                    $all = array_merge($all, $this->fetch(now()->addDays($i)->format('Y-m-d')));
                } catch (\RuntimeException $e) {
                    // Free plan 403 for date outside 26–28 Agt window → skip that date (ponytail: 7d window best-effort within quota)
                    if (str_contains($e->getMessage(), 'Free plans do not have access')) {
                        continue;
                    }

                    throw $e;
                }
            }

            return array_values(array_column($all, null, 'id')); // a game can be listed under both dates
        });
    }

    /** Team names matching $query, for validating what a user follows. */
    public function searchTeams(string $query): array
    {
        return Cache::remember('football.teams.'.strtolower($query), now()->addDay(), function () use ($query) {
            $j = $this->request('/teams', ['search' => $query]);

            return array_map(fn ($t) => $t['team']['name'], $j['response'] ?? []);
        });
    }

    /**
     * Final score of one fixture, or null while it is not over yet. Not cached:
     * a null means "ask again next run", and once it is final the row is marked
     * reported so nothing asks twice.
     */
    public function getResult(string $id): ?array
    {
        $f = $this->request('/fixtures', ['id' => $id])['response'][0] ?? null;
        if (! $f || ! in_array($f['fixture']['status']['short'] ?? '', ['FT', 'AET', 'PEN'], true)) {
            return null;
        }

        return ['home' => (int) ($f['goals']['home'] ?? 0), 'away' => (int) ($f['goals']['away'] ?? 0)];
    }

    private function request(string $endpoint, array $params): array
    {
        $key = config('services.football.api_key', '');
        if (empty($key)) {
            throw new \RuntimeException('Football API Key not configured');
        }
        $r = Http::withHeaders(['x-apisports-key' => $key])->timeout(15)->get(self::API.$endpoint, $params);
        if ($r->failed()) {
            throw new \RuntimeException("API Football Error: {$r->body()}");
        }
        $j = $r->json();
        if (! empty($j['errors'])) {
            throw new \RuntimeException('API Football Error: '.json_encode($j['errors']));
        }

        return $j;
    }

    private function fetch(string $date): array
    {
        // from/to need a league or team, so query one date at a time
        $j = $this->request('/fixtures', ['date' => $date]);
        $out = [];
        foreach ($j['response'] ?? [] as $f) {
            if (($f['fixture']['status']['short'] ?? '') !== 'NS') {
                continue;
            }
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
