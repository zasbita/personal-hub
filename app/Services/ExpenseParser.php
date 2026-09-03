<?php

namespace App\Services;

class ExpenseParser
{
    /**
     * @param  float  $minAmount  Floor below which the text is not treated as money.
     *                            Plain chat is parsed as an expense too, and "2 hari lagi libur" reads as
     *                            Rp 2 — so that path asks for a believable amount. /log stays exact.
     */
    public static function parse(string $text, float $minAmount = 0): ?array
    {
        $cleanText = preg_replace('#^/(log|catat)\s+#i', '', trim($text));
        if (empty($cleanText) || ! preg_match('/^([\d.,\s]+(?:k|rb)?)\s+(.+)$/i', $cleanText, $m)) {
            return null;
        }
        $amount = self::normalizeAmount(trim($m[1]));
        if ($amount === null || $amount <= 0 || $amount < $minAmount || $amount > 100000000) {
            return null;
        }

        $desc = trim($m[2]);
        $cat = 'General';
        $date = null;

        // Extract trailing #category and date in any order (e.g. "makan siang 2026-09-02 #Jajan" or "makan siang #Jajan 2026-09-02")
        for ($i = 0; $i < 3; $i++) {
            $changed = false;
            if (preg_match('/#(\w+)\s*$/', $desc, $hm)) {
                $cat = ucfirst(strtolower($hm[1]));
                $desc = trim(preg_replace('/#\w+\s*$/', '', $desc));
                $changed = true;
            }
            $extracted = self::extractTrailingDate($desc);
            if ($extracted !== null) {
                $date = $extracted['date'];
                $desc = $extracted['remaining'];
                $changed = true;
            }
            if (! $changed) {
                break;
            }
        }

        if ($desc === '') {
            return null;
        }

        return ['amount' => $amount, 'description' => $desc, 'category' => $cat, 'date' => $date ?? date('Y-m-d')];
    }

    private static function normalizeAmount(string $raw): ?float
    {
        $clean = strtolower(trim(str_replace(',', '', $raw)));
        if (preg_match('/(k|rb)$/', $clean)) {
            $num = trim(preg_replace('/(k|rb)$/', '', $clean));
            if (preg_match('/[^\d.]/', $num)) {
                return null;
            }

            return ((float) $num) * 1000;
        }

        return (float) str_replace('.', '', $clean);
    }

    /**
     * @return array{date: string, remaining: string}|null
     */
    private static function extractTrailingDate(string $desc): ?array
    {
        $lower = strtolower($desc);

        // Relative: "hari ini" / "today"
        if (preg_match('/\s+hari\s+ini\s*$/i', $desc, $m)) {
            $remaining = trim(substr($desc, 0, -strlen($m[0])));
            // ponytail: relative date, today
            return ['date' => date('Y-m-d'), 'remaining' => $remaining];
        }
        if (preg_match('/\s+today\s*$/i', $desc, $m)) {
            $remaining = trim(substr($desc, 0, -strlen($m[0])));

            return ['date' => date('Y-m-d'), 'remaining' => $remaining];
        }
        // "kemarin" / "yesterday" -> yesterday
        if (preg_match('/\s+kemarin\s*$/i', $desc, $m)) {
            $remaining = trim(substr($desc, 0, -strlen($m[0])));

            return ['date' => date('Y-m-d', strtotime('-1 day')), 'remaining' => $remaining];
        }
        if (preg_match('/\s+yesterday\s*$/i', $desc, $m)) {
            $remaining = trim(substr($desc, 0, -strlen($m[0])));

            return ['date' => date('Y-m-d', strtotime('-1 day')), 'remaining' => $remaining];
        }

        // Absolute: trailing token that looks like a date
        if (preg_match('/\s+(\d{4}[-\/]\d{1,2}[-\/]\d{1,2}|\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4})\s*$/', $desc, $m)) {
            $raw = $m[1];
            $parsed = self::parseDateString($raw);
            if ($parsed !== null) {
                $remaining = trim(substr($desc, 0, -strlen($m[0])));

                return ['date' => $parsed, 'remaining' => $remaining];
            }
        }

        return null;
    }

    private static function parseDateString(string $raw): ?string
    {
        $raw = trim($raw);
        // YYYY-MM-DD or YYYY/MM/DD
        if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $raw, $m)) {
            $y = (int) $m[1]; $mo = (int) $m[2]; $d = (int) $m[3];
            if (checkdate($mo, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }

            return null;
        }
        // DD-MM-YYYY or DD/MM/YYYY or DD-MM-YY etc
        if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{2,4})$/', $raw, $m)) {
            $d = (int) $m[1]; $mo = (int) $m[2]; $y = (int) $m[3];
            if ($y < 100) {
                $y += 2000; // 26 -> 2026, ponytail: Y2K cutoff 00-99
            }
            if (checkdate($mo, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
        }

        return null;
    }
}
