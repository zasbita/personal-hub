<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SheetsService
{
    private string $sheetId;
    private string $saEmail;
    private string $privateKey;

    public function __construct()
    {
        $this->sheetId = config('services.google.sheet_id', '');
        $this->saEmail = config('services.google.service_account_email', '');
        $this->privateKey = str_replace('\\n', "\n", config('services.google.private_key', ''));
    }

    private function getToken(): string
    {
        $now = time();
        $h = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $c = base64_encode(json_encode(['iss' => $this->saEmail, 'scope' => 'https://www.googleapis.com/auth/spreadsheets', 'aud' => 'https://oauth2.googleapis.com/token', 'exp' => $now + 3600, 'iat' => $now]));
        $input = "{$h}.{$c}";
        $sig = '';
        openssl_sign($input, $sig, $this->privateKey, OPENSSL_ALGO_SHA256);
        $jwt = "{$input}." . rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
        $r = Http::asForm()->post('https://oauth2.googleapis.com/token', ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]);
        $d = $r->json();
        if (!isset($d['access_token'])) throw new \RuntimeException('Google auth failed');
        return $d['access_token'];
    }

    private function sheetsGet(string $path): array
    {
        $r = Http::withToken($this->getToken())->get("https://sheets.googleapis.com/v4{$path}");
        if ($r->failed()) throw new \RuntimeException("Sheets GET failed: {$r->body()}");
        return $r->json();
    }

    private function sheetsSend(string $method, string $path, array $body = []): array
    {
        $r = Http::withToken($this->getToken())->withHeaders(['Content-Type' => 'application/json'])->send($method, "https://sheets.googleapis.com/v4{$path}", $body);
        if ($r->failed()) throw new \RuntimeException("Sheets {$method} failed: {$r->body()}");
        return $r->json();
    }

    public function listExpenses(): array
    {
        $d = $this->sheetsGet("/spreadsheets/{$this->sheetId}/values/Sheet1!A:E");
        $rows = $d['values'] ?? [];
        $out = [];
        foreach (array_slice($rows, 1) as $i => $r) {
            if (empty($r[4])) continue;
            $out[] = ['row' => $i + 2, 'date' => $r[0] ?? '', 'amount' => (float) ($r[1] ?? 0), 'description' => $r[2] ?? '', 'category' => $r[3] ?? 'General', 'id' => $r[4] ?? ''];
        }
        return $out;
    }

    public function findExpenseById(string $id): ?array
    {
        foreach ($this->listExpenses() as $e) { if ($e['id'] === $id) return $e; }
        return null;
    }

    public function appendExpense(float $amount, string $desc, string $cat): array
    {
        return $this->sheetsSend('POST', "/spreadsheets/{$this->sheetId}/values/A:E:append?valueInputOption=USER_ENTERED", ['values' => [[date('Y-m-d'), $amount, $desc, $cat, uuid_create()]]]);
    }

    public function getWeeklyExpenses(): array
    {
        $d = $this->sheetsGet("/spreadsheets/{$this->sheetId}/values/A:D");
        $rows = $d['values'] ?? [];
        if (empty($rows)) return ['total' => 0, 'items' => []];
        $ago = new \DateTime('-7 days');
        $now = new \DateTime();
        $total = 0;
        $items = [];
        $start = is_numeric($rows[0][1] ?? '') ? 0 : 1;
        for ($i = $start; $i < count($rows); $i++) {
            $r = $rows[$i];
            if (count($r) < 3) continue;
            $amt = (float) $r[1];
            if (is_nan($amt)) continue;
            $dt = new \DateTime($r[0]);
            if ($dt >= $ago && $dt <= $now) { $total += $amt; $items[] = ['date' => $r[0], 'amount' => $amt, 'description' => $r[2], 'category' => $r[3] ?? 'General']; }
        }
        return ['total' => $total, 'items' => $items];
    }

    public function updateExpenseRow(int $row, array $vals): void
    {
        $this->sheetsSend('PUT', "/spreadsheets/{$this->sheetId}/values/Sheet1!A{$row}:E{$row}?valueInputOption=USER_ENTERED", ['values' => [$vals]]);
    }

    public function deleteExpenseRow(int $row): void
    {
        $this->sheetsSend('POST', "/spreadsheets/{$this->sheetId}:batchUpdate", ['requests' => [['deleteDimension' => ['range' => ['sheetId' => 0, 'dimension' => 'ROWS', 'startIndex' => $row - 1, 'endIndex' => $row]]]]]);
    }
}
