<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SheetsService
{
    /**
     * The one tab every read and write targets. Ranges used to be written three
     * ways — "Sheet1!A:E", a bare "A:D" (which means whichever tab is first), and
     * a hardcoded numeric sheetId of 0 for deletes. Renaming or reordering tabs
     * would have sent reads and writes to different places.
     */
    private const TAB = 'Sheet1';

    private string $sheetId;

    private string $saEmail;

    private string $privateKey;

    public function __construct()
    {
        $this->sheetId = config('services.google.sheet_id', '');
        $this->saEmail = config('services.google.service_account_email', '');
        $this->privateKey = str_replace('\\n', "\n", config('services.google.private_key', ''));
    }

    /**
     * Google access token, cached just under its hour of life. Every Sheets call
     * used to mint its own, so a single read cost two round trips to Google.
     */
    private function getToken(): string
    {
        return Cache::remember('sheets.token', 3300, function () {
            $now = time();
            $h = self::b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $c = self::b64url(json_encode(['iss' => $this->saEmail, 'scope' => 'https://www.googleapis.com/auth/spreadsheets', 'aud' => 'https://oauth2.googleapis.com/token', 'exp' => $now + 3600, 'iat' => $now]));
            $input = "{$h}.{$c}";
            $sig = '';
            openssl_sign($input, $sig, $this->privateKey, OPENSSL_ALGO_SHA256);
            $jwt = "{$input}.".self::b64url($sig);
            $r = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]);
            $d = $r->json();
            if (! isset($d['access_token'])) {
                throw new \RuntimeException("Google auth failed: {$r->body()}");
            }

            return $d['access_token'];
        });
    }

    /**
     * JWT segments must use the URL-safe alphabet. Plain base64 emits '+', '/'
     * and padding, which Google rejects — and whether it does depends on the
     * iat/exp bytes of the moment, so it failed only some of the time.
     */
    private static function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function sheetsGet(string $path): array
    {
        $r = Http::withToken($this->getToken())->timeout(15)->get("https://sheets.googleapis.com/v4{$path}");
        if ($r->failed()) {
            throw new \RuntimeException("Sheets GET failed: {$r->body()}");
        }

        return $r->json();
    }

    private function sheetsSend(string $method, string $path, array $body = []): array
    {
        // send() takes Guzzle options, not a payload — passing the payload straight
        // in sent an empty body, so every append, edit and delete was a no-op.
        $r = Http::withToken($this->getToken())->timeout(15)->send($method, "https://sheets.googleapis.com/v4{$path}", ['json' => $body]);
        if ($r->failed()) {
            throw new \RuntimeException("Sheets {$method} failed: {$r->body()}");
        }

        return $r->json();
    }

    public function listExpenses(): array
    {
        $d = $this->sheetsGet("/spreadsheets/{$this->sheetId}/values/".self::range('A:E'));
        $rows = $d['values'] ?? [];
        $out = [];
        foreach (array_slice($rows, 1) as $i => $r) {
            if (empty($r[4])) {
                continue;
            }
            $out[] = ['row' => $i + 2, 'date' => $r[0] ?? '', 'amount' => (float) ($r[1] ?? 0), 'description' => $r[2] ?? '', 'category' => $r[3] ?? 'General', 'id' => $r[4] ?? ''];
        }

        return $out;
    }

    public function findExpenseById(string $id): ?array
    {
        foreach ($this->listExpenses() as $e) {
            if ($e['id'] === $id) {
                return $e;
            }
        }

        return null;
    }

    public function appendExpense(float $amount, string $desc, string $cat): array
    {
        return $this->sheetsSend('POST', "/spreadsheets/{$this->sheetId}/values/".self::range('A:E').':append?valueInputOption=USER_ENTERED', ['values' => [[date('Y-m-d'), $amount, $desc, $cat, uuid_create()]]]);
    }

    /**
     * Spending over the last $days, oldest first, with per-category subtotals.
     *
     * @return array{total: float, items: array<int, array{date: string, amount: float, description: string, category: string}>, byCategory: array<string, float>}
     */
    public function getRecentExpenses(int $days = 7): array
    {
        return $this->getExpensesSince(new \DateTime("-{$days} days"));
    }

    /** Same shape as getRecentExpenses, from an explicit starting point. */
    public function getExpensesSince(\DateTimeInterface $since): array
    {
        $d = $this->sheetsGet("/spreadsheets/{$this->sheetId}/values/".self::range('A:D'));
        $rows = $d['values'] ?? [];
        if (empty($rows)) {
            return ['total' => 0, 'items' => [], 'byCategory' => []];
        }
        $ago = $since;
        $now = new \DateTime;
        $total = 0;
        $items = [];
        $byCategory = [];
        $start = is_numeric($rows[0][1] ?? '') ? 0 : 1;
        for ($i = $start; $i < count($rows); $i++) {
            $r = $rows[$i];
            if (count($r) < 3) {
                continue;
            }
            if (! is_numeric($r[1])) {
                continue;
            } // a stray note in the amount column
            $amt = (float) $r[1];
            $dt = new \DateTime($r[0]);
            if ($dt < $ago || $dt > $now) {
                continue;
            }
            $cat = ($r[3] ?? '') ?: 'General';
            $total += $amt;
            $items[] = ['date' => $r[0], 'amount' => $amt, 'description' => $r[2], 'category' => $cat];
            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + $amt;
        }
        arsort($byCategory);

        return ['total' => $total, 'items' => $items, 'byCategory' => $byCategory];
    }

    /** Total spent since the 1st of this month, for the budget line. */
    public function spentThisMonth(): float
    {
        return $this->getExpensesSince(new \DateTime('first day of this month midnight'))['total'];
    }

    /** The most recently appended expense, or null when the sheet is empty. */
    public function lastExpense(): ?array
    {
        $all = $this->listExpenses();

        return $all ? $all[count($all) - 1] : null;
    }

    public function updateExpenseRow(int $row, array $vals): void
    {
        $this->sheetsSend('PUT', "/spreadsheets/{$this->sheetId}/values/".self::range("A{$row}:E{$row}").'?valueInputOption=USER_ENTERED', ['values' => [$vals]]);
    }

    public function deleteExpenseRow(int $row): void
    {
        $this->sheetsSend('POST', "/spreadsheets/{$this->sheetId}:batchUpdate", ['requests' => [['deleteDimension' => ['range' => ['sheetId' => $this->tabId(), 'dimension' => 'ROWS', 'startIndex' => $row - 1, 'endIndex' => $row]]]]]);
    }

    /** An A1 range on TAB, so no caller has to remember the tab name. */
    private static function range(string $a1): string
    {
        return self::TAB.'!'.$a1;
    }

    /**
     * The numeric id batchUpdate wants for TAB. Cached: tab ids only change when
     * someone adds or removes a tab, and this would otherwise cost a round trip
     * on every delete.
     */
    private function tabId(): int
    {
        return Cache::remember('sheets.tabid.'.self::TAB, 3600, function () {
            $d = $this->sheetsGet("/spreadsheets/{$this->sheetId}?fields=sheets(properties(sheetId,title))");
            foreach ($d['sheets'] ?? [] as $tab) {
                if (($tab['properties']['title'] ?? '') === self::TAB) {
                    return (int) $tab['properties']['sheetId'];
                }
            }
            throw new \RuntimeException('Sheets tab '.self::TAB.' not found');
        });
    }
}
