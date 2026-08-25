<?php

namespace App\Services;

class ExpenseSummary
{
    /** Above this many entries the list is dropped: Telegram cuts a message at 4096 chars. */
    private const MAX_ITEMS = 15;

    /**
     * Render the summary Telegram sends, shared by /summary and the weekly digest.
     *
     * @param  array{total: float, items: array, byCategory: array<string, float>}  $recent
     */
    public static function format(array $recent, int $days, ?string $title = null): string
    {
        $m = '📅 *'.($title ?? "Ringkasan {$days} Hari")."*\n\n";
        if (count($recent['items']) <= self::MAX_ITEMS) {
            foreach ($recent['items'] as $i => $it) {
                $m .= ($i + 1).". *{$it['date']}* - {$it['description']}: *Rp ".self::rupiah($it['amount'])."*\n";
            }
        } else {
            $m .= count($recent['items'])." transaksi, terlalu banyak untuk dirinci satu-satu.\n";
        }
        $m .= "\n📁 *Per Kategori*\n";
        foreach ($recent['byCategory'] as $cat => $sum) {
            $m .= "{$cat}: *Rp ".self::rupiah($sum)."*\n";
        }

        return $m."\n💰 *Total: Rp ".self::rupiah($recent['total']).'*';
    }

    /**
     * How the month is tracking against MONTHLY_BUDGET, or '' when no budget is set.
     * Sent with every logged expense: a budget you only see when you ask is a budget
     * you find out about too late.
     */
    public static function budgetLine(float $spentThisMonth): string
    {
        $budget = (float) config('services.budget.monthly', 0);
        if ($budget <= 0) {
            return '';
        }
        $pct = (int) round($spentThisMonth / $budget * 100);
        $icon = $pct >= 100 ? '🚨' : ($pct >= 80 ? '⚠️' : '📊');
        $left = $budget - $spentThisMonth;
        $tail = $left >= 0 ? 'sisa Rp '.self::rupiah($left) : 'lewat Rp '.self::rupiah(-$left);

        return "{$icon} Bulan ini: Rp ".self::rupiah($spentThisMonth).' / '.self::rupiah($budget)." ({$pct}%, {$tail})";
    }

    public static function rupiah(float $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }
}
