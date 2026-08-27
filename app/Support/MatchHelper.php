<?php

namespace App\Support;

class MatchHelper
{
    /** 1 hour window for notify, 20-30h for H-1 schedule — ponytail: tune here, not per caller */
    public const NOTIFY_WINDOW = '+1 hour';

    public const SCHEDULE_FROM_HOURS = 20;

    public const SCHEDULE_TO_HOURS = 30;

    public const NEXT_24H_HOURS = 24;

    public const NEXT_7D_HOURS = 168;

    public const NEXT_3D_HOURS = 72;

    public static function sourceId(string $matchId, int|string $userId): string
    {
        return "{$matchId}:u{$userId}";
    }

    /** @return array{0: string, 1: ?int} */
    public static function splitSourceId(string $sourceId): array
    {
        $at = strrpos($sourceId, ':u');

        return $at === false ? [$sourceId, null] : [substr($sourceId, 0, $at), (int) substr($sourceId, $at + 2)];
    }

    public static function startsSoon(string $iso): bool
    {
        return self::isInWindow($iso, new \DateTimeImmutable, (new \DateTimeImmutable)->modify(self::NOTIFY_WINDOW));
    }

    public static function isOneDayAway(string $iso, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable;

        return self::isInWindow($iso, $now->modify('+'.self::SCHEDULE_FROM_HOURS.' hours'), $now->modify('+'.self::SCHEDULE_TO_HOURS.' hours'));
    }

    public static function isNext24Hours(string $iso, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable;

        return self::isInWindow($iso, $now, $now->modify('+'.self::NEXT_24H_HOURS.' hours'));
    }

    public static function isNext7Days(string $iso, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable;

        return self::isInWindow($iso, $now, $now->modify('+'.self::NEXT_7D_HOURS.' hours'));
    }

    public static function isNext3Days(string $iso, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable;

        return self::isInWindow($iso, $now, $now->modify('+'.self::NEXT_3D_HOURS.' hours'));
    }

    public static function isInWindow(string $iso, \DateTimeImmutable $from, \DateTimeImmutable $to): bool
    {
        if ($iso === '') {
            return false;
        }
        try {
            $dt = new \DateTimeImmutable($iso);
        } catch (\Throwable) {
            return false;
        }

        return $dt > $from && $dt <= $to;
    }
}
