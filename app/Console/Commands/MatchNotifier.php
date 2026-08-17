<?php

namespace App\Console\Commands;

use App\Services\{FootballService, MotoGPService, SportPrefsService, SupabaseService, TelegramService};
use Illuminate\Console\Command;

class MatchNotifier extends Command
{
    protected $signature = 'bot:notify';
    protected $description = 'Check for live/upcoming matches and send notifications';

    public function handle(): int
    {
        $s = new SupabaseService();
        $tg = new TelegramService();
        $prefs = (new SportPrefsService($s))->getActivePreferences();
        if (empty($prefs)) { $this->info('No active preferences'); return 0; }
        $this->info(count($prefs) . ' active preferences');

        $fp = array_values(array_filter($prefs, fn($p) => $p['sport_type'] === 'football'));
        $mp = array_values(array_filter($prefs, fn($p) => in_array($p['sport_type'], ['motogp', 'moto2', 'moto3'])));
        if ($fp) $this->notifyFootball($s, $tg, $fp);
        if ($mp) $this->notifyMotoGP($s, $tg, $mp);
        $this->info('Done');
        return 0;
    }

    private function notifyFootball(SupabaseService $s, TelegramService $tg, array $prefs): void
    {
        try {
            $live = (new FootballService())->getLiveFixtures();
            if (empty($live['response'])) { $this->info('No live football'); return; }
            foreach ($prefs as $p) {
                foreach ($live['response'] as $f) {
                    if (strtolower($f['teams']['home']['name']) === strtolower($p['entity_name']) || strtolower($f['teams']['away']['name']) === strtolower($p['entity_name'])) {
                        $sid = (string) $f['fixture']['id'];
                        $ex = $s->select('match_schedule', ['select' => 'id', 'source_id' => "eq.{$sid}", 'sport_type' => 'eq.football']);
                        if (empty($ex)) {
                            $h = $f['teams']['home']['name'];
                            $a = $f['teams']['away']['name'];
                            $tg->sendMessage((int) $p['user_id'], "🔔 *Live!*\n{$h} vs {$a}\n⏱️ " . date('d/m/Y H:i', strtotime($f['fixture']['date'])));
                            $s->insert('match_schedule', ['source_id' => $sid, 'sport_type' => 'football', 'competition' => $f['league']['name'], 'home_team' => $h, 'away_team' => $a, 'match_time' => $f['fixture']['date'], 'status' => $f['fixture']['status']['short'] ?? 'live', 'notified' => true]);
                            $this->info("Notified {$p['user_id']}: {$h} vs {$a}");
                        }
                    }
                }
            }
        } catch (\Exception $e) { $this->error("Football error: {$e->getMessage()}"); }
    }

    private function notifyMotoGP(SupabaseService $s, TelegramService $tg, array $prefs): void
    {
        try {
            $moto = new MotoGPService();
            $all = [];
            foreach (['motogp', 'moto2', 'moto3'] as $c) { foreach ($moto->getCurrentSeasonRaces($c) as $r) { $all[] = array_merge($r, ['classification' => $c]); } }
            if (empty($all)) { $this->info('No upcoming MotoGP'); return; }
            foreach ($prefs as $p) {
                foreach ($all as $r) {
                    if ($moto->matchesRace($r['raceName'], $p['entity_name'])) {
                        $sid = "{$r['classification']}-{$r['round']}";
                        $ex = $s->select('match_schedule', ['select' => 'id', 'source_id' => "eq.{$sid}", 'sport_type' => "eq.{$r['classification']}"]);
                        if (empty($ex)) {
                            $tg->sendMessage((int) $p['user_id'], "🏍️ *" . strtoupper($r['classification']) . "!*\n\n" . $moto->formatRaceInfo($r));
                            $time = $r['time'] ?? '00:00:00';
                            $loc = "{$r['Circuit']['Location']['locality']}, {$r['Circuit']['Location']['country']}";
                            $s->insert('match_schedule', ['source_id' => $sid, 'sport_type' => $r['classification'], 'competition' => $r['raceName'], 'home_team' => $r['Circuit']['circuitName'], 'away_team' => $loc, 'match_time' => "{$r['date']}T{$time}", 'status' => 'scheduled', 'notified' => true]);
                            $this->info("Notified {$p['user_id']}: {$r['raceName']}");
                        }
                    }
                }
            }
        } catch (\Exception $e) { $this->error("MotoGP error: {$e->getMessage()}"); }
    }
}
