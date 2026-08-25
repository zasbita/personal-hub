<?php

namespace Tests\Unit;

use App\Services\MotoGPService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MotoGPServiceTest extends TestCase
{
    public function test_picks_race_sessions_of_the_requested_class(): void
    {
        Http::fake([
            '*/results/seasons' => Http::response([['year' => 2025, 'current' => false], ['year' => 2026, 'current' => true]]),
            '*/events*' => Http::response([
                // a test event must be ignored
                ['kind' => 'TEST', 'name' => 'VALENCIA TEST', 'sequence' => 1, 'circuit' => ['name' => 'Ricardo Tormo', 'city' => 'Cheste', 'country' => 'Spain'], 'broadcasts' => [
                    ['kind' => 'RACE', 'shortname' => 'RAC', 'category' => ['acronym' => 'MGP'], 'date_start' => '2099-01-01T10:00:00+0100'],
                ]],
                ['kind' => 'GP', 'name' => 'GRAND PRIX OF ARAGON', 'sequence' => 14, 'circuit' => ['name' => 'MotorLand Aragón', 'city' => '', 'region' => 'AR', 'country' => 'Spain'], 'broadcasts' => [
                    ['kind' => 'RACE', 'shortname' => 'SPR', 'category' => ['acronym' => 'MGP'], 'date_start' => '2099-08-29T15:00:00+0200', 'name' => 'Tissot Sprint'],
                    ['kind' => 'RACE', 'shortname' => 'RAC', 'category' => ['acronym' => 'MT2'], 'date_start' => '2099-08-30T12:15:00+0200'],
                    ['kind' => 'RACE', 'shortname' => 'RAC', 'category' => ['acronym' => 'MGP'], 'date_start' => '2099-08-30T14:00:00+0200', 'name' => 'Grand Prix'],
                    ['kind' => 'QUALIFYING', 'shortname' => 'Q2', 'category' => ['acronym' => 'MGP'], 'date_start' => '2099-08-29T11:15:00+0200'],
                ]],
                ['kind' => 'GP', 'name' => 'PAST GP', 'sequence' => 1, 'circuit' => ['name' => 'Old', 'city' => 'Old', 'country' => 'Spain'], 'broadcasts' => [
                    ['kind' => 'RACE', 'shortname' => 'RAC', 'category' => ['acronym' => 'MGP'], 'date_start' => '2000-05-01T14:00:00+0200'],
                ]],
            ]),
        ]);

        $races = (new MotoGPService)->getCurrentSeasonRaces('motogp');

        $this->assertSame(['SPR', 'RAC'], array_column($races, 'session')); // sprint then Grand Prix, in start order
        $this->assertSame('GRAND PRIX OF ARAGON', $races[1]['raceName']);
        $this->assertSame('14', $races[1]['round']);
        $this->assertSame('2099-08-30', $races[1]['date']);
        $this->assertSame('14:00:00+02:00', $races[1]['time']);
        $this->assertSame('AR', $races[1]['Circuit']['Location']['locality']);
        // 14:00 CEST is 19:00 in Jakarta
        $this->assertStringContainsString('30/08/2099 19:00 WIB', (new MotoGPService)->formatRaceInfo($races[1]));

        $moto2 = (new MotoGPService)->getCurrentSeasonRaces('moto2');
        $this->assertSame('12:15:00+02:00', $moto2[0]['time']);
    }

    public function test_unknown_class_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new MotoGPService)->getCurrentSeasonRaces('formula1');
    }

    public function test_a_followed_race_matches_only_its_own_round(): void
    {
        $moto = new MotoGPService;

        $this->assertTrue($moto->matchesRace('GRAND PRIX OF ARAGON', 'Aragon'));
        $this->assertTrue($moto->matchesRace('ARAGON GP', 'Aragon Grand Prix'));
        $this->assertTrue($moto->matchesRace("GRAN PREMIO D'ITALIA", 'Italia'));

        // Every round shares the "grand prix" boilerplate; it must not match on it.
        $this->assertFalse($moto->matchesRace('GRAND PRIX OF JAPAN', 'Aragon Grand Prix'));
        $this->assertFalse($moto->matchesRace("GRAN PREMIO D'ITALIA", "Gran Premio d'España"));
        $this->assertFalse($moto->matchesRace('GRAND PRIX OF ARAGON', ''));
    }
}
