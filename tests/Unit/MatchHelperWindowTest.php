<?php

namespace Tests\Unit;

use App\Support\MatchHelper;
use PHPUnit\Framework\TestCase;

class MatchHelperWindowTest extends TestCase
{
    public function test_is_next_24h(): void
    {
        $now = new \DateTimeImmutable('2026-08-27 10:00:00 UTC');
        $this->assertTrue(MatchHelper::isNext24Hours('2026-08-27 15:00:00 UTC', $now)); // 5h
        $this->assertTrue(MatchHelper::isNext24Hours('2026-08-28 09:59:00 UTC', $now)); // 23:59
        $this->assertFalse(MatchHelper::isNext24Hours('2026-08-28 10:00:01 UTC', $now)); // >24h exactly
        $this->assertFalse(MatchHelper::isNext24Hours('2026-08-27 10:00:00 UTC', $now)); // now = not >
        $this->assertFalse(MatchHelper::isNext24Hours('', $now));
        $this->assertFalse(MatchHelper::isNext24Hours('invalid', $now));
    }
}
