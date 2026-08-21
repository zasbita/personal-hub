<?php

namespace App\Console\Commands;

use App\Services\{FootballService, MotoGPService, NameMatcher, SportPrefsService, SupabaseService, TelegramService, VolleyballService};
use Illuminate\Console\Command;

class MatchNotifier extends Command
{
    protected $signature = 'bot:notify';
    protected $description = 'Check for live/upcoming matches and send notifications';

    /** How long before kickoff/race start the notification goes out. */
    private const NOTIFY_WINDOW = '+1 hour';

    public function handle(): int
    {
        try {
            $s = new SupabaseService();
            $tg = new TelegramService();
            $prefs = (new SportPrefsService($s))->getActivePreferences();
            if (empty($prefs)) { $this->info('No active preferences'); return 0; }
            $this->info(count($prefs) . ' active preferences');

            $by = fn(array $types) => array_values(array_filter($prefs, fn($p) => in_array($p['sport_type'], $types, true)));
            $fp = $by(['football']);
            $vp = $by(['volly']);
            $mp = $by(['motogp', 'moto2', 'moto3']);
            if ($fp) $this->notifyTeamSport($s, $tg, $fp, 'football', '⚽', fn() => (new FootballService())->getUpcomingFixtures());
            if ($vp) $this->notifyTeamSport($s, $tg, $vp, 'volly', '🏐', fn() => (new VolleyballService())->getUpcomingGames());
            if ($mp) $this->notifyMotoGP($s, $tg, $mp);
            $this->info('Done');
        } catch (\Throwable $e) {
            $this->error("Match Notifier Error: {$e->getMessage()}");
            $ownerId = (int) config('services.telegram.owner_id', 0);
            if ($ownerId) {
                $tg->sendMessage($ownerId, "❌ *Match Notifier Error*\n`" . $e->getMessage() . "`");
            }
            return 1;
        }
        return 0;
    }

    /**
     * Football and volleyball share the same shape: a list of upcoming matches
     * with home/away/league, matched against followed team names.
     * @param callable():array $fetch
     */
    private function notifyTeamSport(SupabaseService $s, TelegramService $tg, array $prefs, string $sport, string $emoji, callable $fetch): void
    {
        try {
            $soon = array_filter($fetch(), fn($m) => $this->startsSoon($m['date']));
            if (empty($soon)) { $this->info("No {$sport} match starting soon"); return; }
            foreach ($prefs as $p) {
                foreach ($soon as $m) {
                    if (!NameMatcher::matches($m['home'], $p['entity_name']) && !NameMatcher::matches($m['away'], $p['entity_name'])) continue;
                    $ex = $s->select('match_schedule', ['select' => 'id', 'source_id' => "eq.{$m['id']}", 'sport_type' => "eq.{$sport}"]);
                    if ($ex) continue;
                    $tg->sendMessage((int) $p['user_id'], "{$emoji} *1 jam lagi!*\n{$m['home']} vs {$m['away']}\n🏆 {$m['league']}\n⏱️ " . date('d/m/Y H:i', strtotime($m['date'])));
                    $s->insert('match_schedule', ['source_id' => $m['id'], 'sport_type' => $sport, 'competition' => $m['league'], 'home_team' => $m['home'], 'away_team' => $m['away'], 'match_time' => $m['date'], 'status' => 'NS', 'notified' => true]);
                    $this->info("Notified {$p['user_id']}: {$m['home']} vs {$m['away']}");
                }
            }
        } catch (\Throwable $e) {
            $this->error("{$sport} error: {$e->getMessage()}");
            $ownerId = (int) config('services.telegram.owner_id', 0);
            if ($ownerId) $tg->sendMessage($ownerId, "❌ *{$sport} error*\n`{$e->getMessage()}`");
        }
    }

    private function notifyMotoGP(SupabaseService $s, TelegramService $tg, array $prefs): void
    {
        try {
            $moto = new MotoGPService();
            $all = [];
            foreach (['motogp', 'moto2', 'moto3'] as $c) { foreach ($moto->getCurrentSeasonRaces($c) as $r) { $all[] = array_merge($r, ['classification' => $c]); } }
            $all = array_filter($all, fn($r) => $this->startsSoon($r['date'] . 'T' . ($r['time'] ?? '00:00:00')));
            if (empty($all)) { $this->info('No MotoGP race starting soon'); return; }
            foreach ($prefs as $p) {
                foreach ($all as $r) {
                    if ($moto->matchesRace($r['raceName'], $p['entity_name'])) {
                        $sid = "{$r['classification']}-{$r['round']}";
                        $ex = $s->select('match_schedule', ['select' => 'id', 'source_id' => "eq.{$sid}", 'sport_type' => "eq.{$r['classification']}"]);
                        if (empty($ex)) {
                            $tg->sendMessage((int) $p['user_id'], "🏍️ *" . strtoupper($r['classification']) . " 1 jam lagi!*\n\n" . $moto->formatRaceInfo($r));
                            $time = $r['time'] ?? '00:00:00';
                            $loc = "{$r['Circuit']['Location']['locality']}, {$r['Circuit']['Location']['country']}";
                            $s->insert('match_schedule', ['source_id' => $sid, 'sport_type' => $r['classification'], 'competition' => $r['raceName'], 'home_team' => $r['Circuit']['circuitName'], 'away_team' => $loc, 'match_time' => "{$r['date']}T{$time}", 'status' => 'scheduled', 'notified' => true]);
                            $this->info("Notified {$p['user_id']}: {$r['raceName']}");
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->warn("MotoGP skipped: {$e->getMessage()}");
            return;
        }
    }

    /** True when $iso starts between now and NOTIFY_WINDOW from now. */
    private function startsSoon(string $iso): bool
    {
        if ($iso === '') return false;
        $dt = new \DateTimeImmutable($iso);
        $now = new \DateTimeImmutable();
        return $dt > $now && $dt <= $now->modify(self::NOTIFY_WINDOW);
    }
}
