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
                    ['kind' => 'RACE', 'shortname' => 'SPR', 'category' => ['acronym' => 'MGP'], 'date_start' => '2099-08-29T15:00:00+0200'], // sprint, not the GP
                    ['kind' => 'RACE', 'shortname' => 'RAC', 'category' => ['acronym' => 'MT2'], 'date_start' => '2099-08-30T12:15:00+0200'],
                    ['kind' => 'RACE', 'shortname' => 'RAC', 'category' => ['acronym' => 'MGP'], 'date_start' => '2099-08-30T14:00:00+0200'],
                    ['kind' => 'QUALIFYING', 'shortname' => 'Q2', 'category' => ['acronym' => 'MGP'], 'date_start' => '2099-08-29T11:15:00+0200'],
                ]],
                ['kind' => 'GP', 'name' => 'PAST GP', 'sequence' => 1, 'circuit' => ['name' => 'Old', 'city' => 'Old', 'country' => 'Spain'], 'broadcasts' => [
                    ['kind' => 'RACE', 'shortname' => 'RAC', 'category' => ['acronym' => 'MGP'], 'date_start' => '2000-05-01T14:00:00+0200'],
                ]],
            ]),
        ]);

        $races = (new MotoGPService())->getCurrentSeasonRaces('motogp');

        $this->assertCount(1, $races);
        $this->assertSame('GRAND PRIX OF ARAGON', $races[0]['raceName']);
        $this->assertSame('14', $races[0]['round']);
        $this->assertSame('2099-08-30', $races[0]['date']);
        $this->assertSame('14:00:00+02:00', $races[0]['time']);
        $this->assertSame('AR', $races[0]['Circuit']['Location']['locality']);
        $this->assertStringContainsString('30/08/2099 14:00', (new MotoGPService())->formatRaceInfo($races[0]));

        $moto2 = (new MotoGPService())->getCurrentSeasonRaces('moto2');
        $this->assertSame('12:15:00+02:00', $moto2[0]['time']);
    }

    public function test_unknown_class_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new MotoGPService())->getCurrentSeasonRaces('formula1');
    }
}
