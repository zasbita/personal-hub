<?php

namespace Tests\Unit;

use App\Services\NameMatcher;
use PHPUnit\Framework\TestCase;

class NameMatcherTest extends TestCase
{
    public function test_matching(): void
    {
        $this->assertTrue(NameMatcher::matches('Liverpool', 'liverpool'));
        $this->assertTrue(NameMatcher::matches('Spain', 'Spanyol'));            // Indonesian alias
        $this->assertTrue(NameMatcher::matches('Indonesia W', 'Indonesia'));    // women's side
        $this->assertTrue(NameMatcher::matches('Manchester United', '"Man"'));  // quoted pref value
        $this->assertFalse(NameMatcher::matches('Spain', 'Italia'));
        $this->assertFalse(NameMatcher::matches('Liverpool', ''));
    }
}
