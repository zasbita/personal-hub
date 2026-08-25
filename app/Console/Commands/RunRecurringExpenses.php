<?php

namespace App\Console\Commands;

use App\Services\SheetsService;
use App\Services\SupabaseService;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RunRecurringExpenses extends Command
{
    protected $signature = 'expenses:recurring';

    protected $description = 'Append recurring expenses due today to Sheets';

    public function handle(): int
    {
        $lock = Cache::lock('recurring:'.now()->format('Y-m-d'), 3600);
        if (! $lock->get()) {
            $this->info('Already ran today');

            return 0;
        }

        try {
            $s = new SupabaseService;
            $all = $s->select('recurring_expenses', ['select' => '*']);
            if (empty($all)) {
                $this->info('No recurring expenses');

                return 0;
            }

            $today = (int) now()->timezone(config('app.display_timezone', 'Asia/Jakarta'))->day;
            $lastDay = (int) now()->timezone(config('app.display_timezone', 'Asia/Jakarta'))->endOfMonth()->day;

            $due = array_filter($all, function ($r) use ($today, $lastDay) {
                $d = (int) $r['day_of_month'];

                return $d === $today || ($d === 31 && $today === $lastDay && $d > $lastDay) || ($d > $lastDay && $today === $lastDay);
            });

            if (empty($due)) {
                $this->info('Nothing due today');

                return 0;
            }

            $sheets = new SheetsService;
            $tg = new TelegramService;
            $owner = (int) config('services.telegram.owner_id', 0);

            foreach ($due as $r) {
                $sheets->appendExpense((float) $r['amount'], $r['description'], $r['category'] ?? 'General');
                $this->info("Appended: {$r['description']} Rp {$r['amount']}");
                if ($owner) {
                    try {
                        $tg->sendMessage($owner, '🔁 *Recurring:* Rp '.number_format($r['amount'], 0, ',', '.')." - {$r['description']} (".($r['category'] ?? 'General').')');
                    } catch (\Throwable $e) {
                        // ignore telegram failure
                    }
                }
            }

            return 0;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return 1;
        } finally {
            // lock auto-expires, keep for day
        }
    }
}
