<?php

namespace Tests\Unit;

use App\Services\SheetsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SheetsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // A pattern that stops matching must fail the test, not quietly call Google.
        Http::preventStrayRequests();
    }

    public function test_recent_expenses_are_windowed_and_grouped_by_category(): void
    {
        $this->fakeSheet('A:D', [
            ['Date', 'Amount', 'Description', 'Category'],
            [now()->subDays(2)->format('Y-m-d'), '50000', 'Makan siang', 'General'],
            [now()->subDays(3)->format('Y-m-d'), '25000', 'Kopi', 'Jajan'],
            [now()->subDays(4)->format('Y-m-d'), '10000', 'Parkir', 'General'],
            [now()->subDays(40)->format('Y-m-d'), '999000', 'Bulan lalu', 'General'],
            [now()->subDay()->format('Y-m-d'), 'catatan', 'Bukan angka', 'General'],
        ]);

        $r = (new SheetsService)->getRecentExpenses(7);

        $this->assertSame(85000.0, $r['total']);
        $this->assertCount(3, $r['items']);
        // Biggest category first, so the summary leads with where the money went.
        $this->assertSame(['General' => 60000.0, 'Jajan' => 25000.0], $r['byCategory']);
    }

    public function test_a_wider_window_reaches_further_back(): void
    {
        $this->fakeSheet('A:D', [
            ['Date', 'Amount', 'Description', 'Category'],
            [now()->subDays(2)->format('Y-m-d'), '50000', 'Makan siang', 'General'],
            [now()->subDays(40)->format('Y-m-d'), '999000', 'Bulan lalu', 'General'],
        ]);

        $this->assertSame(50000.0, (new SheetsService)->getRecentExpenses(7)['total']);
        $this->assertSame(1049000.0, (new SheetsService)->getRecentExpenses(60)['total']);
    }

    public function test_the_last_expense_is_the_bottom_row_of_the_sheet(): void
    {
        $this->fakeSheet('A:E', [
            ['Date', 'Amount', 'Description', 'Category', 'ID'],
            ['2026-08-20', '50000', 'Makan siang', 'General', 'id-1'],
            ['2026-08-21', '25000', 'Kopi', 'Jajan', 'id-2'],
        ]);

        $last = (new SheetsService)->lastExpense();

        $this->assertSame('id-2', $last['id']);
        $this->assertSame(3, $last['row']); // header is row 1
    }

    public function test_no_expense_to_undo_on_an_empty_sheet(): void
    {
        $this->fakeSheet('A:E', [['Date', 'Amount', 'Description', 'Category', 'ID']]);

        $this->assertNull((new SheetsService)->lastExpense());
    }

    public function test_writes_actually_carry_a_body(): void
    {
        $this->fakeSheet('A:E', []);
        $s = new SheetsService;

        $s->appendExpense(50000, 'Makan siang', 'General');
        $s->updateExpenseRow(4, ['2026-08-24', 25000, 'Kopi', 'Jajan', 'id-2']);
        $s->deleteExpenseRow(4);

        $bodies = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'sheets.googleapis.com') && $pair[0]->method() !== 'GET')
            ->map(fn ($pair) => $pair[0]->data())
            ->values()->all();

        $this->assertSame('Makan siang', $bodies[0]['values'][0][2]);
        $this->assertSame('Kopi', $bodies[1]['values'][0][2]);
        $this->assertSame(3, $bodies[2]['requests'][0]['deleteDimension']['range']['startIndex']);
    }

    public function test_every_range_names_the_same_tab(): void
    {
        $this->fakeSheet('A:E', [['Date', 'Amount', 'Description', 'Category', 'ID']]);
        $s = new SheetsService;

        $s->listExpenses();
        $s->getRecentExpenses(7);
        $s->appendExpense(1000, 'Kopi', 'Jajan');
        $s->updateExpenseRow(4, ['2026-08-24', 1000, 'Kopi', 'Jajan', 'id']);

        $ranges = collect(Http::recorded())
            ->map(fn ($pair) => urldecode($pair[0]->url()))
            ->filter(fn ($url) => str_contains($url, '/values/'))
            ->values()->all();

        $this->assertCount(4, $ranges);
        foreach ($ranges as $url) {
            $this->assertStringContainsString('/values/Sheet1!', $url, "range without a tab name: {$url}");
        }
    }

    public function test_a_delete_targets_the_tab_by_name_not_a_hardcoded_zero(): void
    {
        $this->fakeSheet('A:E', [], tabId: 42);

        (new SheetsService)->deleteExpenseRow(4);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'batchUpdate')) {
                return false;
            }

            return $request['requests'][0]['deleteDimension']['range']['sheetId'] === 42;
        });
    }

    private function fakeSheet(string $range, array $values, int $tabId = 0): void
    {
        Http::fake([
            '*oauth2.googleapis.com/token' => Http::response(['access_token' => 'a-token']),
            '*sheets.googleapis.com/*' => function ($request) use ($range, $values, $tabId) {
                $url = urldecode($request->url());
                if (str_contains($url, 'fields=sheets')) {
                    return Http::response(['sheets' => [['properties' => ['sheetId' => $tabId, 'title' => 'Sheet1']]]]);
                }

                return Http::response(str_contains($url, $range) ? ['values' => $values] : ['values' => []]);
            },
        ]);
    }
}
