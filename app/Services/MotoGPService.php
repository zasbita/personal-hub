<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MotoGPService
{
    private const API = 'https://api.motogp.pulselive.com/motogp/v1';

    /** sport_type => category acronym used by the MotoGP API */
    private const CLASSES = ['motogp' => 'MGP', 'moto2' => 'MT2', 'moto3' => 'MT3'];

    private ?array $events = null;

    /**
     * Upcoming races of the current season, Ergast-shaped for backwards compatibility.
     */
    public function getCurrentSeasonRaces(string $class = 'motogp'): array
    {
        $acronym = self::CLASSES[strtolower($class)] ?? null;
        if (!$acronym) throw new \InvalidArgumentException("Unknown class {$class}");

        $now = new \DateTimeImmutable();
        $races = [];
        foreach ($this->events() as $e) {
            if (($e['kind'] ?? '') !== 'GP') continue; // skip tests/shows
            foreach ($e['broadcasts'] ?? [] as $b) {
                if (($b['kind'] ?? '') !== 'RACE' || ($b['shortname'] ?? '') !== 'RAC') continue;
                if (($b['category']['acronym'] ?? '') !== $acronym) continue;
                $dt = new \DateTimeImmutable($b['date_start']);
                if ($dt <= $now) continue;
                $races[] = [
                    'round' => (string) ($e['sequence'] ?? 0),
                    'raceName' => trim($e['name'] ?? ''),
                    'date' => $dt->format('Y-m-d'),
                    'time' => $dt->format('H:i:sP'),
                    'Circuit' => [
                        'circuitName' => $e['circuit']['name'] ?? '',
                        'Location' => [
                            'locality' => ($e['circuit']['city'] ?? '') ?: ($e['circuit']['region'] ?? ''),
                            'country' => $e['circuit']['country'] ?? '',
                        ],
                    ],
                ];
            }
        }
        usort($races, fn($a, $b) => ($a['date'] . $a['time']) <=> ($b['date'] . $b['time']));
        return $races;
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

    /** Every event of the current season, with its session schedule. Fetched once per instance. */
    private function events(): array
    {
        if ($this->events !== null) return $this->events;

        $r = Http::timeout(15)->get(self::API . '/results/seasons');
        if ($r->failed()) throw new \RuntimeException('Failed to fetch MotoGP seasons');
        $year = collect($r->json())->firstWhere('current', true)['year'] ?? (int) date('Y');

        $r = Http::timeout(15)->get(self::API . '/events', ['seasonYear' => $year, 'isFinished' => 'false']);
        if ($r->failed()) throw new \RuntimeException("Failed to fetch MotoGP {$year} events");

        return $this->events = $r->json() ?? [];
    }
}
