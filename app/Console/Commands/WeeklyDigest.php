<?php

namespace App\Console\Commands;

use App\Services\ExpenseSummary;
use App\Services\SheetsService;
use App\Services\SupabaseService;
use App\Services\TelegramService;
use App\Services\VehicleService;
use Illuminate\Console\Command;

/**
 * The Monday morning report: spending, budget, and the service reminder in one
 * message. A report you have to ask for is a report you read too late — and one
 * scheduled message needs no "have I already nagged about this?" bookkeeping.
 */
class WeeklyDigest extends Command
{
    protected $signature = 'bot:digest {--days=7}';

    protected $description = 'Send the weekly expense, budget and service digest to the owner';

    /** Remaining KM below which the service reminder is worth sending unprompted. */
    private const SERVICE_WARN_KM = 300;

    public function handle(): int
    {
        $owner = (int) config('services.telegram.owner_id', 0);
        if (! $owner) {
            $this->error('OWNER_ID not configured');

            return 1;
        }

        $days = max(1, (int) $this->option('days'));
        $tg = new TelegramService;
        $parts = array_filter([$this->expenses($days), $this->service($owner)]);
        if (empty($parts)) {
            $this->info('Nothing to report');

            return 0;
        }

        $tg->sendMessage($owner, implode("\n\n", $parts));
        $this->info('Digest sent');

        return 0;
    }

    private function expenses(int $days): string
    {
        try {
            $s = new SheetsService;
            $r = $s->getRecentExpenses($days);
            if (empty($r['items'])) {
                return "📅 *Laporan Mingguan*\nBelum ada pengeluaran {$days} hari terakhir.";
            }
            $out = ExpenseSummary::format($r, $days, 'Laporan Mingguan');
            $budget = ExpenseSummary::budgetLine($s->spentThisMonth());

            return $budget ? "{$out}\n{$budget}" : $out;
        } catch (\Throwable $e) {
            $this->error("Expenses failed: {$e->getMessage()}");

            return '';
        }
    }

    private function service(int $owner): string
    {
        try {
            $v = (new VehicleService(new SupabaseService))->getServiceStatus($owner);
            if (! $v) {
                return '';
            }
            $left = $v['remainingKm'];
            if ($left > self::SERVICE_WARN_KM) {
                return '';
            } // nothing useful to say yet
            $head = $left <= 0
                ? '🚨 *Servis sudah lewat '.number_format(abs($left)).' KM!*'
                : '⚠️ *Servis dalam '.number_format($left).' KM*';

            return "{$head}\n📍 Odometer: ".number_format($v['lastKm']).' KM · 🎯 Target: '.number_format($v['nextServiceKm']).' KM';
        } catch (\Throwable $e) {
            $this->error("Service check failed: {$e->getMessage()}");

            return '';
        }
    }
}
