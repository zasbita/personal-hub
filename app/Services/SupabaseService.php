<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class SupabaseService
{
    /** Nothing here is worth waiting on forever — bot:notify runs on a 15 minute cron. */
    private const TIMEOUT = 15;

    private string $url;

    private string $key;

    public function __construct()
    {
        $this->url = config('services.supabase.url', '');
        $this->key = config('services.supabase.key', '');
    }

    private function headers(): array
    {
        return ['apikey' => $this->key, 'Authorization' => "Bearer {$this->key}", 'Content-Type' => 'application/json', 'Prefer' => 'return=representation'];
    }

    private function client(array $extraHeaders = []): PendingRequest
    {
        return Http::withHeaders(array_merge($this->headers(), $extraHeaders))->timeout(self::TIMEOUT);
    }

    public function select(string $table, array $params = []): array
    {
        $q = http_build_query($params);
        $r = $this->client()->get("{$this->url}/rest/v1/{$table}".($q ? "?{$q}" : ''));
        if ($r->failed()) {
            throw new \RuntimeException("Supabase select failed: {$r->body()}");
        }

        return $r->json();
    }

    public function selectSingle(string $table, array $params = []): ?array
    {
        $result = $this->select($table, array_merge($params, ['limit' => 1]));

        return $result[0] ?? null;
    }

    public function insert(string $table, array $data): array
    {
        $r = $this->client()->post("{$this->url}/rest/v1/{$table}", $data);
        if ($r->failed()) {
            throw new \RuntimeException("Supabase insert failed: {$r->body()}");
        }

        return $r->json();
    }

    public function upsert(string $table, array $data): array
    {
        $r = $this->client(['Prefer' => 'resolution=merge-duplicates,return=representation'])
            ->post("{$this->url}/rest/v1/{$table}", $data);
        if ($r->failed()) {
            throw new \RuntimeException("Supabase upsert failed: {$r->body()}");
        }

        return $r->json();
    }

    public function update(string $table, array $data, array $filters): array
    {
        $q = http_build_query($filters);
        $r = $this->client()->patch("{$this->url}/rest/v1/{$table}?{$q}", $data);
        if ($r->failed()) {
            throw new \RuntimeException("Supabase update failed: {$r->body()}");
        }

        return $r->json();
    }

    public function delete(string $table, array $filters): void
    {
        $q = http_build_query($filters);
        $r = $this->client()->delete("{$this->url}/rest/v1/{$table}?{$q}");
        if ($r->failed()) {
            throw new \RuntimeException("Supabase delete failed: {$r->body()}");
        }
    }
}
