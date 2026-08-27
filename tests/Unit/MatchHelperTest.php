<?php

namespace Tests\Unit;

use App\Support\MatchHelper;
use PHPUnit\Framework\TestCase;

class MatchHelperTest extends TestCase
{
    public function test_is_one_day_away_window(): void
    {
        $now = new \DateTimeImmutable('2026-08-27 10:00:00 UTC');
        $this->assertTrue(MatchHelper::isOneDayAway('2026-08-28 11:00:00 UTC', $now)); // 25h
        $this->assertFalse(MatchHelper::isOneDayAway('2026-08-27 10:30:00 UTC', $now)); // 0.5h - not H-1
        $this->assertFalse(MatchHelper::isOneDayAway('2026-08-27 15:00:00 UTC', $now)); // 5h
        $this->assertFalse(MatchHelper::isOneDayAway('2026-08-29 10:00:00 UTC', $now)); // 48h
        $this->assertFalse(MatchHelper::isOneDayAway('', $now));
        $this->assertFalse(MatchHelper::isOneDayAway('invalid', $now));
    }

    public function test_starts_soon(): void
    {
        $now = new \DateTimeImmutable;
        $soon = $now->modify('+30 minutes')->format(\DateTimeInterface::ATOM);
        $late = $now->modify('+5 hours')->format(\DateTimeInterface::ATOM);
        $this->assertTrue(MatchHelper::startsSoon($soon));
        $this->assertFalse(MatchHelper::startsSoon($late));
    }

    public function test_source_id_roundtrip(): void
    {
        $sid = MatchHelper::sourceId('55', 7);
        $this->assertSame('55:u7', $sid);
        [$id, $uid] = MatchHelper::splitSourceId($sid);
        $this->assertSame('55', $id);
        $this->assertSame(7, $uid);
    }
}
