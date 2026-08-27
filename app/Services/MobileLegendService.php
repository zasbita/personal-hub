<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MobileLegendService
{
    /**
     * Upcoming MLBB matches trimmed to the shape the bot uses.
     * Cached like football/volleyball (3h) to respect quota and keep /jadwal snappy.
     *
     * @return array<int, array{id:string,date:string,home:string,away:string,league:string}>
     */
    public function getUpcomingMatches(): array
    {
        return Cache::remember('mlbb.upcoming', now()->addHours(3), function () {
            $raw = $this->fetchFromApi();

            // already filtered to NS in fetch, dedup by id like FootballService
            return array_values(array_column($raw, null, 'id'));
        });
    }

    /** Team names matching $query, for validating what a user follows. */
    public function searchTeams(string $query): array
    {
        return Cache::remember('mlbb.teams.'.strtolower($query), now()->addDay(), function () use ($query) {
            $teams = $this->allTeamNames();
            $q = strtolower($query);

            return array_values(array_filter($teams, fn ($t) => str_contains(strtolower($t), $q)));
        });
    }

    /** All distinct team names from upcoming matches (cached via getUpcomingMatches). */
    private function allTeamNames(): array
    {
        $teams = [];
        foreach ($this->getUpcomingMatches() as $m) {
            $teams[] = $m['home'];
            $teams[] = $m['away'];
        }

        return array_values(array_unique($teams));
    }

    /**
     * @return array<int, array{id:string,date:string,home:string,away:string,league:string}>
     */
    private function fetchFromApi(): array
    {
        $url = config('services.mpl.url', '');
        $key = config('services.mpl.key', '');
        if (empty($url)) {
            // No provider configured yet — treat as empty until wired (ponytail: no crash, no extra dep)
            return [];
        }
        $headers = $key ? ['x-api-key' => $key] : [];
        // provisional endpoint: GET {url}/matches?date=YYYY-MM-DD
        // tests fake via '*mpl*'
        $out = [];
        foreach ([now()->format('Y-m-d'), now()->addDay()->format('Y-m-d')] as $date) {
            $r = Http::withHeaders($headers)->timeout(15)->get(rtrim($url, '/').'/matches', ['date' => $date]);
            if ($r->failed()) {
                throw new \RuntimeException('MLBB API Error: '.$r->body());
            }
            $j = $r->json();
            foreach ($j['data'] ?? $j['matches'] ?? $j['response'] ?? [] as $m) {
                // normalize both provisional shapes: {id,date,home,away,league,status} or {match_id,match_date,team1,team2,tournament}
                $status = strtolower($m['status'] ?? $m['state'] ?? 'ns');
                if (! in_array($status, ['ns', 'scheduled', 'upcoming'], true) && ($status !== '')) {
                    // if provider uses different status naming, peek at raw if possible
                    if (isset($m['status']) && strtolower($m['status']) !== 'ns') {
                        continue;
                    }
                }
                $out[] = [
                    'id' => (string) ($m['id'] ?? $m['match_id'] ?? uniqid()),
                    'date' => $m['date'] ?? $m['match_date'] ?? $m['match_time'] ?? '',
                    'home' => $m['home'] ?? $m['team1'] ?? $m['home_team'] ?? '',
                    'away' => $m['away'] ?? $m['team2'] ?? $m['away_team'] ?? '',
                    'league' => $m['league'] ?? $m['tournament'] ?? $m['competition'] ?? 'MPL ID',
                ];
            }
        }

        return array_values(array_filter($out, fn ($m) => $m['date'] !== '' && $m['home'] !== ''));
    }
}
