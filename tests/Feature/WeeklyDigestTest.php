<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeeklyDigestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.telegram.owner_id' => 811031481]);
    }

    public function test_the_digest_carries_spending_budget_and_a_due_service(): void
    {
        config(['services.budget.monthly' => 3000000]);
        $this->fake(sheetRows: [
            ['Date', 'Amount', 'Description', 'Category'],
            [now()->subDays(2)->format('Y-m-d'), '50000', 'Makan siang', 'General'],
            [now()->subDays(3)->format('Y-m-d'), '25000', 'Kopi', 'Jajan'],
        ], vehicle: [['last_km' => 12800, 'next_service_km' => 13000]]);

        $this->artisan('bot:digest')->assertExitCode(0);

        $text = $this->telegramText();
        $this->assertStringContainsString('Laporan Mingguan', $text);
        $this->assertStringContainsString('Makan siang', $text);
        $this->assertStringContainsString('Jajan: *Rp 25.000*', $text);
        $this->assertStringContainsString('Total: Rp 75.000', $text);
        $this->assertStringContainsString('Servis dalam 200 KM', $text);
    }

    public function test_a_service_that_is_not_due_is_left_out(): void
    {
        $this->fake(sheetRows: [
            ['Date', 'Amount', 'Description', 'Category'],
            [now()->subDay()->format('Y-m-d'), '50000', 'Makan siang', 'General'],
        ], vehicle: [['last_km' => 10000, 'next_service_km' => 12000]]);

        $this->artisan('bot:digest')->assertExitCode(0);

        $this->assertStringNotContainsString('Servis', $this->telegramText());
    }

    public function test_the_budget_line_is_skipped_when_no_budget_is_set(): void
    {
        config(['services.budget.monthly' => 0]);
        $this->fake(sheetRows: [
            ['Date', 'Amount', 'Description', 'Category'],
            [now()->subDay()->format('Y-m-d'), '50000', 'Makan siang', 'General'],
        ], vehicle: []);

        $this->artisan('bot:digest')->assertExitCode(0);

        $this->assertStringNotContainsString('Bulan ini', $this->telegramText());
    }

    public function test_an_overdue_service_is_shouted_about(): void
    {
        config(['services.budget.monthly' => 0]);
        $this->fake(sheetRows: [['Date', 'Amount', 'Description', 'Category']], vehicle: [['last_km' => 13500, 'next_service_km' => 13000]]);

        $this->artisan('bot:digest')->assertExitCode(0);

        $text = $this->telegramText();
        $this->assertStringContainsString('Servis sudah lewat 500 KM', $text);
        $this->assertStringContainsString('Belum ada pengeluaran', $text);
    }

    private function fake(array $sheetRows, array $vehicle): void
    {
        Http::fake([
            '*oauth2.googleapis.com/token' => Http::response(['access_token' => 'a-token']),
            '*/values/*' => Http::response(['values' => $sheetRows]),
            '*/rest/v1/vehicle_service*' => Http::response($vehicle),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
    }

    private function telegramText(): string
    {
        foreach (Http::recorded() as $pair) {
            if (str_contains($pair[0]->url(), 'api.telegram.org')) return $pair[0]['text'];
        }
        return '';
    }
}
