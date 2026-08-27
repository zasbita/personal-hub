<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FutsalService
{
    /**
     * Upcoming Timnas Futsal matches — scraped from Wikipedia Results and fixtures.
     * Cached 3h like football/mlbb.
     *
     * @return array<int, array{id:string,date:string,home:string,away:string,league:string}>
     */
    public function getUpcomingMatches(): array
    {
        return Cache::remember('futsal.upcoming', now()->addHours(3), function () {
            $raw = $this->fetchFromWikipedia();

            return array_values(array_column($raw, null, 'id'));
        });
    }

    /** For futsal timnas static — only Indonesia */
    public function searchTeams(string $query): array
    {
        return Cache::remember('futsal.teams.'.strtolower($query), now()->addDay(), function () use ($query) {
            $q = strtolower(trim($query));
            $map = ['indonesia' => 'Indonesia', 'timnas' => 'Indonesia', 'garuda' => 'Indonesia'];
            if (isset($map[$q])) {
                return ['Indonesia'];
            }

            return str_contains('indonesia', $q) ? ['Indonesia'] : [];
        });
    }

    /**
     * Scrape en.wikipedia.org/wiki/Indonesia_national_futsal_team → Results and fixtures
     * Pattern: "Indonesia v Cambodia" + "5 September 2025" + "13:30 UTC+8"
     *
     * @return array<int, array{id:string,date:string,home:string,away:string,league:string}>
     */
    private function fetchFromWikipedia(): array
    {
        $url = config('services.futsal.url', 'https://en.wikipedia.org/wiki/Indonesia_national_futsal_team');
        $html = Http::timeout(15)->get($url)->body();
        if (! $html) {
            return [];
        }
        $text = strip_tags($html);
        $text = html_entity_decode($text);
        // Future fixtures are under 2026 section — match "Indonesia v Thailand" with date nearby
        // Simplest: find all "Indonesia v <Team>" and date+time nearby
        preg_match_all('/Indonesia\s+v\s+([A-Za-z ]+?)\s+(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})\s+.*?(\d{1,2}:\d{2})\s*UTC\+(\d+)/is', $text, $m, PREG_SET_ORDER);
        $months = ['january' => '01', 'february' => '02', 'march' => '03', 'april' => '04', 'may' => '05', 'june' => '06', 'july' => '07', 'august' => '08', 'september' => '09', 'october' => '10', 'november' => '11', 'december' => '12',
            'januari' => '01', 'februari' => '02', 'maret' => '03', 'april' => '04', 'mei' => '05', 'juni' => '06', 'juli' => '07', 'agustus' => '08', 'september' => '09', 'oktober' => '10', 'november' => '11', 'desember' => '12'];
        $out = [];
        $now = new \DateTimeImmutable;
        foreach ($m as $hit) {
            $away = trim($hit[1]);
            // Heuristic: next token is likely competition header not team — skip if too long (>20 chars) or contains digits
            if (strlen($away) > 20 || preg_match('/\d/', $away)) {
                continue;
            }
            $day = str_pad($hit[2], 2, '0', STR_PAD_LEFT);
            $monKey = strtolower($hit[3]);
            $mon = $months[$monKey] ?? null;
            if (! $mon) {
                continue;
            }
            $year = $hit[4];
            $time = $hit[5];
            $offset = (int) $hit[6];
            $tz = $offset === 7 ? 'Asia/Jakarta' : ($offset === 8 ? 'Asia/Shanghai' : 'UTC');
            if (! str_starts_with($tz, 'Asia')) {
                $tz = sprintf('Etc/GMT%+d', -$offset);
            }
            try {
                $dtLocal = new \DateTimeImmutable("{$year}-{$mon}-{$day} {$time}:00", new \DateTimeZone($tz));
                $dtUtc = $dtLocal->setTimezone(new \DateTimeZone('UTC'));
            } catch (\Throwable) {
                continue;
            }
            // Only future (allow yesterday for timezone skew)
            if ($dtUtc < $now->modify('-1 day')) {
                continue;
            }
            $id = strtolower('indonesia-'.preg_replace('/\s+/', '-', $away)."-{$year}{$mon}{$day}".str_replace(':', '', $time));
            $out[] = ['id' => $id, 'date' => $dtUtc->format('Y-m-d\TH:i:sP'), 'home' => 'Indonesia', 'away' => $away, 'league' => 'AFC/ASEAN Futsal'];
        }
        // Also match "X v Indonesia" reversed
        preg_match_all('/([A-Za-z ]+?)\s+v\s+Indonesia\s+(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})\s+.*?(\d{1,2}:\d{2})\s*UTC\+(\d+)/is', $text, $m2, PREG_SET_ORDER);
        foreach ($m2 as $hit) {
            $away = trim($hit[1]);
            if (strlen($away) > 20 || preg_match('/\d/', $away)) {
                continue;
            }
            $day = str_pad($hit[2], 2, '0', STR_PAD_LEFT);
            $monKey = strtolower($hit[3]);
            $mon = $months[$monKey] ?? null;
            if (! $mon) {
                continue;
            }
            $year = $hit[4];
            $time = $hit[5];
            $offset = (int) $hit[6];
            $tz = $offset === 7 ? 'Asia/Jakarta' : ($offset === 8 ? 'Asia/Shanghai' : 'UTC');
            if (! str_starts_with($tz, 'Asia')) {
                $tz = sprintf('Etc/GMT%+d', -$offset);
            }
            try {
                $dtLocal = new \DateTimeImmutable("{$year}-{$mon}-{$day} {$time}:00", new \DateTimeZone($tz));
                $dtUtc = $dtLocal->setTimezone(new \DateTimeZone('UTC'));
            } catch (\Throwable) {
                continue;
            }
            if ($dtUtc < $now->modify('-1 day')) {
                continue;
            }
            $id = strtolower(preg_replace('/\s+/', '-', $away)."-indonesia-{$year}{$mon}{$day}".str_replace(':', '', $time));
            $out[] = ['id' => $id, 'date' => $dtUtc->format('Y-m-d\TH:i:sP'), 'home' => $away, 'away' => 'Indonesia', 'league' => 'AFC/ASEAN Futsal'];
        }

        return array_values(array_filter($out, fn ($r) => $r['date'] !== ''));
    }
}
