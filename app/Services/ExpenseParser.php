<?php

namespace App\Services;

class ExpenseParser
{
    /**
     * @param float $minAmount Floor below which the text is not treated as money.
     *   Plain chat is parsed as an expense too, and "2 hari lagi libur" reads as
     *   Rp 2 — so that path asks for a believable amount. /log stays exact.
     */
    public static function parse(string $text, float $minAmount = 0): ?array
    {
        $cleanText = preg_replace('#^/(log|catat)\s+#i', '', trim($text));
        if (empty($cleanText) || !preg_match('/^([\d.,\s]+(?:k|rb)?)\s+(.+)$/i', $cleanText, $m)) {
            return null;
        }
        $amount = self::normalizeAmount(trim($m[1]));
        if ($amount === null || $amount <= 0 || $amount < $minAmount || $amount > 100000000) return null;

        $desc = trim($m[2]);
        $cat = 'General';
        if (preg_match('/#(\w+)\s*$/', $desc, $hm)) {
            $cat = ucfirst(strtolower($hm[1]));
            $desc = trim(preg_replace('/#\w+\s*$/', '', $desc));
        }
        return ['amount' => $amount, 'description' => $desc, 'category' => $cat];
    }

    private static function normalizeAmount(string $raw): ?float
    {
        $clean = strtolower(trim(str_replace(',', '', $raw)));
        if (preg_match('/(k|rb)$/', $clean)) {
            $num = trim(preg_replace('/(k|rb)$/', '', $clean));
            if (preg_match('/[^\d.]/', $num)) return null;
            return ((float) $num) * 1000;
        }
        return (float) str_replace('.', '', $clean);
    }
}
