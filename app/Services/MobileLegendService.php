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
        $unique = array_values(array_unique(array_filter($teams)));
        if (! empty($unique)) {
            return $unique;
        }

        // ponytail: static fallback 9 MPL ID S18 teams — so /follow suggestion works even when MPL_API_URL empty / scrape fails
        return ['ONIC', 'EVOS', 'RRQ', 'BTR', 'TLID', 'AE', 'GEEK', 'DEWA', 'NAVI'];
    }

    /**
     * @return array<int, array{id:string,date:string,home:string,away:string,league:string}>
     */
    private function fetchFromApi(): array
    {
        $url = config('services.mpl.url', '');
        $key = config('services.mpl.key', '');
        if (empty($url)) {
            return [];
        }
        // id-mpl.com scrape branch — official MPL ID schedule page (no key, cache 3h)
        if (str_contains($url, 'id-mpl.com')) {
            return $this->fetchFromIdMpl($url);
        }
        $headers = $key ? ['x-api-key' => $key] : [];
        $out = [];
        foreach ([now()->format('Y-m-d'), now()->addDay()->format('Y-m-d')] as $date) {
            $r = Http::withHeaders($headers)->timeout(15)->get(rtrim($url, '/').'/matches', ['date' => $date]);
            if ($r->failed()) {
                throw new \RuntimeException('MLBB API Error: '.$r->body());
            }
            $j = $r->json();
            foreach ($j['data'] ?? $j['matches'] ?? $j['response'] ?? [] as $m) {
                $status = strtolower($m['status'] ?? $m['state'] ?? 'ns');
                if (! in_array($status, ['ns', 'scheduled', 'upcoming'], true) && ($status !== '')) {
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

    /**
     * Scrape https://id-mpl.com/schedule → [{id,date,home,away,league}]
     * Pattern: "BTR\nVS\nNAVI\n28 Agt · 15:00" (WIB → UTC)
     */
    private function fetchFromIdMpl(string $baseUrl): array
    {
        $html = Http::timeout(15)->get(rtrim($baseUrl, '/').'/schedule')->body();
        if (! $html) {
            return [];
        }
        // Normalize whitespace
        $text = strip_tags($html);
        $text = html_entity_decode($text);
        // Match team VS team + date "28 Agt · 15:00"
        preg_match_all('/([A-Z]{2,6})\s+VS\s+([A-Z]{2,6})\s+(\d{1,2})\s+(\w{3,4})\s*[·\.]\s*(\d{1,2}:\d{2})/u', $text, $m, PREG_SET_ORDER);
        $months = ['jan' => '01', 'feb' => '02', 'mar' => '03', 'apr' => '04', 'mei' => '05', 'jun' => '06', 'jul' => '07', 'agt' => '08', 'sep' => '09', 'okt' => '10', 'nov' => '11', 'des' => '12'];
        $year = (int) now()->format('Y');
        $out = [];
        foreach ($m as $hit) {
            $home = trim($hit[1]);
            $away = trim($hit[2]);
            $day = str_pad($hit[3], 2, '0', STR_PAD_LEFT);
            $monKey = strtolower($hit[4]);
            $mon = $months[$monKey] ?? null;
            if (! $mon) {
                continue;
            }
            $time = $hit[5];
            // WIB (UTC+7) → UTC
            try {
                $dtWib = new \DateTimeImmutable("{$year}-{$mon}-{$day} {$time}:00", new \DateTimeZone('Asia/Jakarta'));
                $dtUtc = $dtWib->setTimezone(new \DateTimeZone('UTC'));
            } catch (\Throwable) {
                continue;
            }
            $id = strtolower("{$home}-{$away}-{$year}{$mon}{$day}".str_replace(':', '', $time));
            $out[] = ['id' => $id, 'date' => $dtUtc->format('Y-m-d\TH:i:sP'), 'home' => $home, 'away' => $away, 'league' => 'MPL ID S18'];
        }

        return array_values(array_filter($out, fn ($r) => $r['date'] !== ''));
    }
}
