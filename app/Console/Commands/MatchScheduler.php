<?php

namespace App\Console\Commands;

use App\Services\FootballService;
use App\Services\MobileLegendService;
use App\Services\MotoGPService;
use App\Services\NameMatcher;
use App\Services\SportPrefsService;
use App\Services\SupabaseService;
use App\Services\TelegramService;
use App\Services\VolleyballService;
use App\Support\MatchHelper;
use Illuminate\Console\Command;

class MatchScheduler extends Command
{
    protected $signature = 'bot:schedule';

    protected $description = 'Store upcoming matches 1 day before kickoff (H-1) without notification';

    public function handle(): int
    {
        $tg = new TelegramService;

        try {
            $s = new SupabaseService;
            $prefs = (new SportPrefsService($s))->getActivePreferences();
            if (empty($prefs)) {
                $this->info('No active preferences');

                return 0;
            }
            $this->info(count($prefs).' active preferences');

            $by = fn (array $types) => array_values(array_filter($prefs, fn ($p) => in_array($p['sport_type'], $types, true)));
            $fp = $by(['football']);
            $vp = $by(['volly']);
            $mp = $by(['motogp', 'moto2', 'moto3', 'baggers']);
            $lp = $by(['mobilelegend']);

            if ($fp) {
                $this->scheduleTeamSport($s, $fp, 'football', fn () => (new FootballService)->getUpcomingFixtures());
            }
            if ($vp) {
                $this->scheduleTeamSport($s, $vp, 'volly', fn () => (new VolleyballService)->getUpcomingGames());
            }
            if ($mp) {
                $this->scheduleMotoGP($s, $mp);
            }
            if ($lp) {
                $this->scheduleTeamSport($s, $lp, 'mobilelegend', fn () => (new MobileLegendService)->getUpcomingMatches());
            }

            $this->info('Done');
        } catch (\Throwable $e) {
            $this->error("Match Scheduler Error: {$e->getMessage()}");
            $ownerId = (int) config('services.telegram.owner_id', 0);
            if ($ownerId) {
                $tg->sendMessage($ownerId, "❌ *Match Scheduler Error*\n`".$e->getMessage().'`');
            }

            return 1;
        }

        return 0;
    }

    /** @param callable():array $fetch */
    private function scheduleTeamSport(SupabaseService $s, array $prefs, string $sport, callable $fetch): void
    {
        try {
            $soon = array_filter($fetch(), fn ($m) => MatchHelper::isOneDayAway($m['date'] ?? ''));
            if (empty($soon)) {
                $this->info("No {$sport} match H-1");

                return;
            }
            // dedup same api id returned for today+tomorrow
            $soon = array_values(array_column($soon, null, 'id'));

            $inserted = 0;
            $skipped = 0;
            foreach ($prefs as $p) {
                foreach ($soon as $m) {
                    if (! NameMatcher::matches($m['home'], $p['entity_name']) && ! NameMatcher::matches($m['away'], $p['entity_name'])) {
                        continue;
                    }
                    $sid = MatchHelper::sourceId($m['id'], $p['user_id']);
                    $ex = $s->select('match_schedule', ['select' => 'id', 'source_id' => "eq.{$sid}", 'sport_type' => "eq.{$sport}"]);
                    if ($ex) {
                        $skipped++;

                        continue;
                    }
                    $s->insert('match_schedule', [
                        'source_id' => $sid,
                        'sport_type' => $sport,
                        'competition' => $m['league'],
                        'home_team' => $m['home'],
                        'away_team' => $m['away'],
                        'match_time' => $m['date'],
                        'status' => 'NS',
                        'notified' => false,
                    ]);
                    $inserted++;
                    $this->info("Scheduled {$p['user_id']}: {$m['home']} vs {$m['away']} @ {$m['date']}");
                }
            }
            $this->info("{$sport} H-1: {$inserted} inserted, {$skipped} skipped (already exists)");
        } catch (\Throwable $e) {
            $this->error("{$sport} schedule error: {$e->getMessage()}");
            $ownerId = (int) config('services.telegram.owner_id', 0);
            if ($ownerId) {
                (new TelegramService)->sendMessage($ownerId, "❌ *{$sport} schedule error*\n`{$e->getMessage()}`");
            }
        }
    }

    private function scheduleMotoGP(SupabaseService $s, array $prefs): void
    {
        try {
            $moto = new MotoGPService;
            $all = [];
            foreach (['motogp', 'moto2', 'moto3', 'baggers'] as $c) {
                foreach ($moto->getCurrentSeasonRaces($c) as $r) {
                    $all[] = array_merge($r, ['classification' => $c]);
                }
            }
            $all = array_filter($all, fn ($r) => MatchHelper::isOneDayAway($r['date'].'T'.($r['time'] ?? '00:00:00')));
            if (empty($all)) {
                $this->info('No MotoGP race H-1');

                return;
            }
            $inserted = 0;
            $skipped = 0;
            foreach ($prefs as $p) {
                foreach ($all as $r) {
                    if (! $moto->matchesRace($r['raceName'], $p['entity_name'])) {
                        continue;
                    }
                    $sid = MatchHelper::sourceId("{$r['classification']}-{$r['round']}-{$r['session']}", $p['user_id']);
                    $ex = $s->select('match_schedule', ['select' => 'id', 'source_id' => "eq.{$sid}", 'sport_type' => "eq.{$r['classification']}"]);
                    if ($ex) {
                        $skipped++;

                        continue;
                    }
                    $time = $r['time'] ?? '00:00:00';
                    $loc = "{$r['Circuit']['Location']['locality']}, {$r['Circuit']['Location']['country']}";
                    $s->insert('match_schedule', [
                        'source_id' => $sid,
                        'sport_type' => $r['classification'],
                        'competition' => $r['raceName'],
                        'home_team' => $r['Circuit']['circuitName'],
                        'away_team' => $loc,
                        'match_time' => "{$r['date']}T{$time}",
                        'status' => 'scheduled',
                        'notified' => false,
                    ]);
                    $inserted++;
                    $this->info("Scheduled {$p['user_id']}: {$r['raceName']} ({$r['classification']})");
                }
            }
            $this->info("MotoGP H-1: {$inserted} inserted, {$skipped} skipped");
        } catch (\Throwable $e) {
            $this->warn("MotoGP schedule skipped: {$e->getMessage()}");
        }
    }
}
