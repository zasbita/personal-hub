<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FootballService
{
    private string $apiKey;
    public function __construct() { $this->apiKey = config('services.football.api_key', ''); }

    private function fetch(string $endpoint, array $params = []): array
    {
        if (empty($this->apiKey)) throw new \RuntimeException('Football API Key not configured');
        $r = Http::withHeaders(['x-apisports-key' => $this->apiKey])->get("https://v3.football.api-sports.io{$endpoint}?" . http_build_query($params));
        if ($r->failed()) throw new \RuntimeException("API Football Error: {$r->body()}");
        return $r->json();
    }

    public function getLiveFixtures(): array { return $this->fetch('/fixtures', ['live' => 'all']); }

    public function getUpcomingFixtures(int $hours = 24): array
    {
        $now = new \DateTime();
        $future = new \DateTime("+{$hours} hours");
        return $this->fetch('/fixtures', ['from' => $now->format('Y-m-d'), 'to' => $future->format('Y-m-d'), 'status' => 'NS,PST,LIVE']);
    }
}
