<?php

namespace Tests\Unit;

use App\Services\ExpenseParser;
use Tests\TestCase;

class ExpenseParserTest extends TestCase
{
    public function test_a_command_and_bare_chat_parse_the_same_way(): void
    {
        $expected = ['amount' => 50000.0, 'description' => 'makan siang', 'category' => 'General'];

        $this->assertSame($expected, ExpenseParser::parse('/log 50k makan siang'));
        $this->assertSame($expected, ExpenseParser::parse('/catat 50k makan siang'));
        $this->assertSame($expected, ExpenseParser::parse('50k makan siang'));
        $this->assertSame($expected, ExpenseParser::parse('50rb makan siang'));
        $this->assertSame($expected, ExpenseParser::parse('50.000 makan siang'));
    }

    public function test_a_trailing_hashtag_becomes_the_category(): void
    {
        $p = ExpenseParser::parse('25k kopi #jajan');

        $this->assertSame(25000.0, $p['amount']);
        $this->assertSame('kopi', $p['description']);
        $this->assertSame('Jajan', $p['category']);
    }

    public function test_text_that_is_not_an_amount_is_rejected(): void
    {
        $this->assertNull(ExpenseParser::parse('halo bot'));
        $this->assertNull(ExpenseParser::parse('/log'));
        $this->assertNull(ExpenseParser::parse('50k'));       // no description
        $this->assertNull(ExpenseParser::parse('0 gratis'));
        $this->assertNull(ExpenseParser::parse('999999999999 rumah'));
    }

    public function test_a_floor_keeps_stray_chat_out_of_the_sheet(): void
    {
        // What bare chat looks like when a sentence happens to start with a number.
        $this->assertNull(ExpenseParser::parse('2 hari lagi libur', 1000));
        $this->assertNull(ExpenseParser::parse('3 orang ikut', 1000));

        // Real amounts still land, and /log has no floor at all.
        $this->assertSame(50000.0, ExpenseParser::parse('50k makan siang', 1000)['amount']);
        $this->assertSame(2000.0, ExpenseParser::parse('2k parkir', 1000)['amount']);
        $this->assertSame(2.0, ExpenseParser::parse('/log 2 receh')['amount']);
    }
}
