<?php

namespace App\Console\Commands;

use App\Services\DisplayTime;
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

class MatchNotifier extends Command
{
    protected $signature = 'bot:notify';

    protected $description = 'Check for live/upcoming matches and send notifications';

    /** @deprecated use MatchHelper::NOTIFY_WINDOW */
    private const NOTIFY_WINDOW = '+1 hour';

    public function handle(): int
    {
        // Built before the try block: the catch below reports through it.
        $tg = new TelegramService;
        try {
            $s = new SupabaseService;
            $this->reportResults($s, $tg);
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
                $this->notifyTeamSport($s, $tg, $fp, 'football', '⚽', fn () => (new FootballService)->getUpcomingFixtures());
            }
            if ($vp) {
                $this->notifyTeamSport($s, $tg, $vp, 'volly', '🏐', fn () => (new VolleyballService)->getUpcomingGames());
            }
            if ($mp) {
                $this->notifyMotoGP($s, $tg, $mp);
            }
            if ($lp) {
                $this->notifyTeamSport($s, $tg, $lp, 'mobilelegend', '🎮', fn () => (new MobileLegendService)->getUpcomingMatches());
            }
            $this->info('Done');
        } catch (\Throwable $e) {
            $this->error("Match Notifier Error: {$e->getMessage()}");
            $ownerId = (int) config('services.telegram.owner_id', 0);
            if ($ownerId) {
                $tg->sendMessage($ownerId, "❌ *Match Notifier Error*\n`".$e->getMessage().'`');
            }

            return 1;
        }

        return 0;
    }

    /**
     * Football and volleyball share the same shape: a list of upcoming matches
     * with home/away/league, matched against followed team names.
     *
     * @param  callable():array  $fetch
     */
    private function notifyTeamSport(SupabaseService $s, TelegramService $tg, array $prefs, string $sport, string $emoji, callable $fetch): void
    {
        try {
            $soon = array_filter($fetch(), fn ($m) => $this->startsSoon($m['date']));
            if (empty($soon)) {
                $this->info("No {$sport} match starting soon");

                return;
            }
            $sent = 0;
            foreach ($prefs as $p) {
                foreach ($soon as $m) {
                    if (! NameMatcher::matches($m['home'], $p['entity_name']) && ! NameMatcher::matches($m['away'], $p['entity_name'])) {
                        continue;
                    }
                    $sid = MatchHelper::sourceId($m['id'], $p['user_id']);
                    $ex = $s->select('match_schedule', ['select' => 'id,notified', 'source_id' => "eq.{$sid}", 'sport_type' => "eq.{$sport}"]);
                    if ($ex) {
                        // H-1 row exists: if notified missing (legacy) or true -> skip; if false -> send and mark
                        $notified = $ex[0]['notified'] ?? true;
                        if ((bool) $notified) {
                            continue;
                        }
                        $tg->sendMessage((int) $p['user_id'], "{$emoji} *1 jam lagi!*\n{$m['home']} vs {$m['away']}\n🏆 {$m['league']}\n⏱️ ".DisplayTime::format($m['date']));
                        $s->update('match_schedule', ['notified' => true], ['id' => "eq.{$ex[0]['id']}"]);
                        $this->info("Notified {$p['user_id']}: {$m['home']} vs {$m['away']} (updated H-1 row)");
                        $sent++;

                        continue;
                    }
                    $tg->sendMessage((int) $p['user_id'], "{$emoji} *1 jam lagi!*\n{$m['home']} vs {$m['away']}\n🏆 {$m['league']}\n⏱️ ".DisplayTime::format($m['date']));
                    $s->insert('match_schedule', ['source_id' => $sid, 'sport_type' => $sport, 'competition' => $m['league'], 'home_team' => $m['home'], 'away_team' => $m['away'], 'match_time' => $m['date'], 'status' => 'NS', 'notified' => true]);
                    $this->info("Notified {$p['user_id']}: {$m['home']} vs {$m['away']}");
                    $sent++;
                }
            }
            // Silence here used to be ambiguous: nothing upcoming, or upcoming but
            // nobody follows the teams playing. Say which.
            if ($sent === 0) {
                $this->info(count($soon)." {$sport} match(es) starting soon, none followed");
            }
        } catch (\Throwable $e) {
            $this->error("{$sport} error: {$e->getMessage()}");
            $ownerId = (int) config('services.telegram.owner_id', 0);
            if ($ownerId) {
                $tg->sendMessage($ownerId, "❌ *{$sport} error*\n`{$e->getMessage()}`");
            }
        }
    }

    private function notifyMotoGP(SupabaseService $s, TelegramService $tg, array $prefs): void
    {
        try {
            $moto = new MotoGPService;
            $all = [];
            foreach (['motogp', 'moto2', 'moto3', 'baggers'] as $c) {
                foreach ($moto->getCurrentSeasonRaces($c) as $r) {
                    $all[] = array_merge($r, ['classification' => $c]);
                }
            }
            $all = array_filter($all, fn ($r) => $this->startsSoon($r['date'].'T'.($r['time'] ?? '00:00:00')));
            if (empty($all)) {
                $this->info('No MotoGP race starting soon');

                return;
            }
            foreach ($prefs as $p) {
                foreach ($all as $r) {
                    if ($moto->matchesRace($r['raceName'], $p['entity_name'])) {
                        $sid = MatchHelper::sourceId("{$r['classification']}-{$r['round']}-{$r['session']}", $p['user_id']);
                        $ex = $s->select('match_schedule', ['select' => 'id,notified', 'source_id' => "eq.{$sid}", 'sport_type' => "eq.{$r['classification']}"]);
                        if (! empty($ex)) {
                            $notified = $ex[0]['notified'] ?? true;
                            if ((bool) $notified) {
                                continue;
                            }
                            $tg->sendMessage((int) $p['user_id'], '🏍️ *'.strtoupper($r['classification'])." 1 jam lagi!*\n\n".$moto->formatRaceInfo($r));
                            $s->update('match_schedule', ['notified' => true], ['id' => "eq.{$ex[0]['id']}"]);
                            $this->info("Notified {$p['user_id']}: {$r['raceName']} (updated H-1 row)");

                            continue;
                        }
                        $tg->sendMessage((int) $p['user_id'], '🏍️ *'.strtoupper($r['classification'])." 1 jam lagi!*\n\n".$moto->formatRaceInfo($r));
                        $time = $r['time'] ?? '00:00:00';
                        $loc = "{$r['Circuit']['Location']['locality']}, {$r['Circuit']['Location']['country']}";
                        $s->insert('match_schedule', ['source_id' => $sid, 'sport_type' => $r['classification'], 'competition' => $r['raceName'], 'home_team' => $r['Circuit']['circuitName'], 'away_team' => $loc, 'match_time' => "{$r['date']}T{$time}", 'status' => 'scheduled', 'notified' => true]);
                        $this->info("Notified {$p['user_id']}: {$r['raceName']}");
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->warn("MotoGP skipped: {$e->getMessage()}");

            return;
        }
    }

    /**
     * Send the final score of matches that were announced earlier and have since
     * finished, then mark the row so it is only reported once.
     * ponytail: football and volleyball only — MotoGP results live on a different
     * endpoint with a different shape; add a MotoGPService::getResult if wanted.
     */
    private function reportResults(SupabaseService $s, TelegramService $tg): void
    {
        try {
            $from = (new \DateTimeImmutable('-6 hours'))->format(\DateTimeInterface::ATOM);
            $to = (new \DateTimeImmutable('-2 hours'))->format(\DateTimeInterface::ATOM);
            // Before kickoff + 2h nothing is final, and after 6h it is stale news.
            $rows = $s->select('match_schedule', ['select' => 'id,source_id,sport_type,home_team,away_team,competition', 'status' => 'eq.NS', 'and' => "(match_time.gte.{$from},match_time.lte.{$to})"]);
            foreach ($rows as $row) {
                if (! in_array($row['sport_type'], ['football', 'volly'], true)) {
                    continue;
                }
                [$apiId, $userId] = self::splitSourceId($row['source_id']);
                if ($userId === null) {
                    continue;
                } // row predates the per-user key: no recipient to read off it
                $score = $row['sport_type'] === 'football'
                    ? (new FootballService)->getResult($apiId)
                    : (new VolleyballService)->getResult($apiId);
                if (! $score) {
                    continue;
                }
                $emoji = $row['sport_type'] === 'football' ? '⚽' : '🏐';
                $tg->sendMessage($userId, "{$emoji} *Selesai*\n{$row['home_team']} *{$score['home']} - {$score['away']}* {$row['away_team']}\n🏆 {$row['competition']}");
                $s->update('match_schedule', ['status' => 'FT'], ['id' => "eq.{$row['id']}"]);
                $this->info("Result sent to {$userId}: {$row['home_team']} {$score['home']}-{$score['away']} {$row['away_team']}");
            }
        } catch (\Throwable $e) {
            $this->error("Results error: {$e->getMessage()}");
        }
    }

    /**
     * Split a source_id back into the API id and the recipient.
     *
     * @return array{0: string, 1: ?int}
     */
    private static function splitSourceId(string $sourceId): array
    {
        return MatchHelper::splitSourceId($sourceId);
    }

    /**
     * The "already notified" key. It carries the recipient because the row is a
     * per-user receipt: keyed on the match alone, the first follower's row
     * silences everyone else following the same match.
     */
    private static function sourceId(string $matchId, int|string $userId): string
    {
        return MatchHelper::sourceId($matchId, $userId);
    }

    /** True when $iso starts between now and NOTIFY_WINDOW from now. */
    private function startsSoon(string $iso): bool
    {
        return MatchHelper::startsSoon($iso);
    }
}
