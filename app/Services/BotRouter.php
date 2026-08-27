<?php

namespace App\Services;

use App\Support\MatchHelper;

/**
 * Everything the bot does with one incoming Telegram update.
 *
 * This lives outside the console command because the same handling is reached
 * two ways: the webhook route in production, and `bot:listen` polling in local
 * development. A Telegram token allows only one of those at a time.
 */
class BotRouter
{
    /** Days a `/summary 30` may ask for. A year of rows is already more than Telegram will show. */
    private const SUMMARY_MAX_DAYS = 365;

    /** Plain chat must name a believable amount before it counts as an expense. */
    private const IMPLICIT_MIN_AMOUNT = 1000;

    /** The command list Telegram shows in its own "/" menu. */
    public const MENU = [
        'log' => 'Catat pengeluaran — /log 50k makan siang',
        'undo' => 'Hapus pengeluaran terakhir',
        'summary' => 'Ringkasan pengeluaran — /summary atau /summary 30',
        'update_km' => 'Update odometer motor — /update_km 12500',
        'check_service' => 'Sisa KM sampai servis berikutnya',
        'follow' => 'Pantau tim atau balapan — /follow volly Indonesia',
        'unfollow' => 'Berhenti memantau — /unfollow volly Indonesia',
        'myteams' => 'Daftar yang sedang dipantau',
        'schedule' => 'Check schedule next 3 days — /schedule',
    ];

    private const WELCOME = "👋 *Serene Darwin*\n"
        ."Asisten pribadi buat catat duit, motor, dan jadwal pertandingan.\n\n"
        ."💰 *Keuangan*\n"
        ."`50k makan siang` — catat langsung, tanpa perintah\n"
        ."`25k kopi #Jajan` — pakai kategori\n"
        ."`/undo` — hapus catatan terakhir\n"
        ."`/summary` — ringkasan 7 hari\n"
        ."`/summary 30` — ganti jumlah hari\n\n"
        ."🏍️ *Motor*\n"
        ."`/update_km 12500` — update odometer\n"
        ."`/check_service` — sisa KM ke servis\n\n"
        ."🏆 *Olahraga*\n"
        ."`/follow volly Indonesia` — pantau tim\n"
        ."`/unfollow volly Indonesia` — berhenti pantau\n"
        ."`/myteams` — daftar pantauan\n"
        ."`/schedule` — check schedule next 3 days\n\n"
        ."_Notifikasi 1 jam sebelum pertandingan, skor akhir setelah selesai,_\n"
        .'_laporan mingguan tiap Senin pagi._';

    private TelegramService $tg;

    private int $ownerId;

    public function __construct(TelegramService $tg, int $ownerId)
    {
        $this->tg = $tg;
        $this->ownerId = $ownerId;
    }

    public function handle(array $update): void
    {
        $tg = $this->tg;
        $msg = $update['message'] ?? null;
        if (! $msg || ! isset($msg['from'])) {
            return;
        }
        $uid = (int) $msg['from']['id'];
        $cid = (int) $msg['chat']['id'];
        $text = trim($msg['text'] ?? '');

        // Photo with caption → treat caption as expense (ponytail: caption OCR stub, real Vision API when key exists)
        if (isset($msg['photo']) && is_array($msg['photo'])) {
            $caption = trim($msg['caption'] ?? '');
            if ($caption !== '') {
                $this->handleExpense($cid, $caption, $tg, true);

                return;
            }
            $tg->sendMessage($cid, "📸 *Foto diterima.* Tambahkan caption mis. `50k bensin #Transport` untuk catat otomatis.\n_OCR penuh butuh VISION_API_KEY — caption sudah bisa._");

            return;
        }

        if ($uid !== $this->ownerId) {
            $tg->sendMessage($cid, "❌ *Unauthorized.* Your ID: `{$uid}`");

            return;
        }

        if ($text === '/start' || $text === '/help') {
            $tg->sendMessage($cid, self::WELCOME);

            return;
        }
        if (str_starts_with($text, '/summary')) {
            $this->handleSummary($cid, $text, $tg);

            return;
        }
        if (str_starts_with($text, '/update_km')) {
            $this->handleKm($uid, $msg['from']['username'] ?? null, $cid, $text, $tg);

            return;
        }
        if ($text === '/check_service') {
            $this->handleService($uid, $cid, $tg);

            return;
        }
        if (str_starts_with($text, '/log') || str_starts_with($text, '/catat')) {
            $this->handleExpense($cid, $text, $tg);

            return;
        }
        if ($text === '/undo') {
            $this->handleUndo($cid, $tg);

            return;
        }
        if (str_starts_with($text, '/follow')) {
            $this->handleFollow($uid, $cid, $text, $tg);

            return;
        }
        if (str_starts_with($text, '/unfollow')) {
            $this->handleUnfollow($uid, $cid, $text, $tg);

            return;
        }
        if ($text === '/myteams') {
            $this->handleMyTeams($uid, $cid, $tg);

            return;
        }
        if ($text === '/jadwal' || $text === '/schedule' || $text === '/next') {
            $this->handleJadwal($uid, $cid, $tg);

            return;
        }
        if (str_starts_with($text, '/')) {
            $tg->sendMessage($cid, '❓ Perintah tidak dikenal. Lihat `/help`.');

            return;
        }
        // Plain text is an expense: typing /log before every note is friction.
        $this->handleExpense($cid, $text, $tg, true);
    }

    private function handleSummary(int $cid, string $text, TelegramService $tg): void
    {
        preg_match('/^\/summary\s+(\d+)/', $text, $dm);
        $days = min(max((int) ($dm[1] ?? 7), 1), self::SUMMARY_MAX_DAYS);
        try {
            $r = (new SheetsService)->getRecentExpenses($days);
            if (empty($r['items'])) {
                $tg->sendMessage($cid, "📅 Belum ada pengeluaran {$days} hari terakhir.");

                return;
            }
            $tg->sendMessage($cid, ExpenseSummary::format($r, $days));
        } catch (\Exception $e) {
            $tg->sendMessage($cid, '❌ Error mengambil data.');
        }
    }

    private function handleUndo(int $cid, TelegramService $tg): void
    {
        try {
            $s = new SheetsService;
            $last = $s->lastExpense();
            if (! $last) {
                $tg->sendMessage($cid, '📭 Belum ada pengeluaran untuk dihapus.');

                return;
            }
            $s->deleteExpenseRow($last['row']);
            $tg->sendMessage($cid, '🗑️ *Dihapus:* Rp '.ExpenseSummary::rupiah($last['amount'])." - {$last['description']}");
        } catch (\Exception $e) {
            $tg->sendMessage($cid, '❌ Error hapus pengeluaran.');
        }
    }

    private function handleKm(int $uid, ?string $un, int $cid, string $text, TelegramService $tg): void
    {
        if (! preg_match('/\/update_km\s+(\d+)/', $text, $m)) {
            $tg->sendMessage($cid, '⚠️ Format: `/update_km [angka]`');

            return;
        }
        try {
            $r = (new VehicleService(new SupabaseService))->updateOdometer($uid, $un, (int) $m[1]);
            $tg->sendMessage($cid, "🏍️ *Updated!*\n📍 KM: *".number_format($r['lastKm'])."*\n🔧 Servis: *".number_format($r['nextServiceKm']).' KM*');
        } catch (\Exception $e) {
            $tg->sendMessage($cid, '❌ Error update odometer.');
        }
    }

    private function handleService(int $uid, int $cid, TelegramService $tg): void
    {
        try {
            $r = (new VehicleService(new SupabaseService))->getServiceStatus($uid);
            if (! $r) {
                $tg->sendMessage($cid, '⚠️ Belum ada data. Gunakan `/update_km` dulu.');

                return;
            }
            $s = $r['remainingKm'] <= 0 ? '🚨 *SERVIS SEKARANG!* Lewat '.number_format(abs($r['remainingKm'])).' KM' : ($r['remainingKm'] <= 200 ? '⚠️ Tinggal *'.number_format($r['remainingKm']).' KM*' : '✅ Aman. Sisa *'.number_format($r['remainingKm']).' KM*');
            $tg->sendMessage($cid, "🔧 *Status Servis*\n📍 Terakhir: *".number_format($r['lastKm'])." KM*\n🎯 Target: *".number_format($r['nextServiceKm'])." KM*\n\n{$s}");
        } catch (\Exception $e) {
            $tg->sendMessage($cid, '❌ Error cek servis.');
        }
    }

    private function handleExpense(int $cid, string $text, TelegramService $tg, bool $implicit = false): void
    {
        $p = ExpenseParser::parse($text, $implicit ? self::IMPLICIT_MIN_AMOUNT : 0);
        if (! $p) {
            $tg->sendMessage($cid, '⚠️ Format: `/log 50k makan siang`');

            return;
        }
        try {
            $s = new SheetsService;
            // Duplicate guard: same amount+description as last row today → warn but still save (ponytail: one read, no lock)
            $dup = false;
            try {
                $last = $s->lastExpense();
                if ($last && (float) $last['amount'] === (float) $p['amount'] && strtolower(trim($last['description'])) === strtolower(trim($p['description'])) && ($last['date'] ?? '') === date('Y-m-d')) {
                    $dup = true;
                }
            } catch (\Throwable $e) {
                // ignore check failure
            }
            $s->appendExpense($p['amount'], $p['description'], $p['category']);
            $reply = '✅ *Rp '.ExpenseSummary::rupiah($p['amount'])."* untuk *{$p['description']}*\n📁 {$p['category']}";
            if ($dup) {
                $reply = "⚠️ *Duplikat?* Mirip transaksi terakhir hari ini.\nKetik `/undo` jika salah.\n\n".$reply;
            }
            // Category budget alert — ponytail: one extra Sheets read + one Supabase read, best-effort
            $catAlert = '';
            try {
                $budgets = (new SupabaseService)->select('category_budgets', ['select' => 'monthly_limit', 'user_id' => 'eq.'.config('services.telegram.owner_id'), 'category' => 'eq.'.$p['category']]);
                if (! empty($budgets) && isset($budgets[0]['monthly_limit'])) {
                    $limit = (float) $budgets[0]['monthly_limit'];
                    $byCat = $s->getExpensesSince(now()->startOfMonth())['byCategory'] ?? [];
                    $spentCat = (float) ($byCat[$p['category']] ?? 0) + (float) $p['amount']; // include just-saved row if sheets lag
                    // Use actual byCat if it already includes the new row (Sheets read after append may lag, so add)
                    if ($limit > 0) {
                        $pct = (int) round($spentCat / $limit * 100);
                        if ($pct >= 100) {
                            $catAlert = "\n🚨 *Budget {$p['category']} lewat!* {$pct}% (Rp ".ExpenseSummary::rupiah($spentCat).' / '.ExpenseSummary::rupiah($limit).')';
                        } elseif ($pct >= 80) {
                            $catAlert = "\n⚠️ *Budget {$p['category']} 80%* {$pct}% (Rp ".ExpenseSummary::rupiah($spentCat).' / '.ExpenseSummary::rupiah($limit).')';
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
            $budget = ExpenseSummary::budgetLine($s->spentThisMonth());
            $extra = trim($catAlert."\n\n".$budget);
            $tg->sendMessage($cid, $extra ? "{$reply}\n\n{$extra}" : $reply);
        } catch (\Exception $e) {
            $tg->sendMessage($cid, '❌ Error simpan ke Sheets.');
        }
    }

    private function handleFollow(int $uid, int $cid, string $text, TelegramService $tg): void
    {
        $p = explode(' ', $text);
        if (count($p) < 3) {
            $tg->sendMessage($cid, '⚠️ Format: `/follow [sport] [team]`');

            return;
        }
        $sport = SportPrefsService::normalizeSport(strtolower($p[1]));
        // futsal alias timnas/garuda normalization happens in resolveEntity, keep sport as futsal here
        if (! in_array($sport, SportPrefsService::SPORTS, true)) {
            $tg->sendMessage($cid, "⚠️ Sport *{$p[1]}* belum didukung.\nPilihan: `".implode('`, `', SportPrefsService::SPORTS).'`');

            return;
        }
        $wanted = implode(' ', array_slice($p, 2));
        try {
            $name = $this->resolveEntity($sport, $wanted, $cid, $tg);
            if ($name === null) {
                return;
            }
            (new SportPrefsService(new SupabaseService))->addPreference($uid, $sport, strtolower($name), $name);
            $tg->sendMessage($cid, "✅ Memantau *{$name}* di *{$sport}*");
        } catch (\Exception $e) {
            $tg->sendMessage($cid, '❌ Error simpan preferensi.');
        }
    }

    /**
     * Confirm the followed entity actually exists in the API, so a preference
     * cannot be stored that the notifier will never match. Returns the
     * canonical name, or null after replying with suggestions.
     */
    private function resolveEntity(string $sport, string $wanted, int $cid, TelegramService $tg): ?string
    {
        try {
            if (in_array($sport, ['motogp', 'moto2', 'moto3', 'baggers'], true)) {
                $moto = new MotoGPService;
                $races = $moto->upcomingRaceNames();
                foreach ($races as $r) {
                    if ($moto->matchesRace($r, $wanted)) {
                        return $wanted;
                    }
                }
                $tg->sendMessage($cid, "⚠️ *{$wanted}* tidak cocok dengan sisa balapan musim ini.\nContoh: `".implode('`, `', array_slice($races, 0, 4)).'`');

                return null;
            }
            if ($sport === 'mobilelegend') {
                $ml = new MobileLegendService;
                $options = $ml->searchTeams($wanted);
                foreach ($options as $o) {
                    if (strcasecmp($o, $wanted) === 0) {
                        return $o;
                    }
                }
                if (empty($options)) {
                    $tg->sendMessage($cid, "⚠️ Tim *{$wanted}* tidak ditemukan di data mobilelegend.");

                    return null;
                }
                $tg->sendMessage($cid, "⚠️ Tim *{$wanted}* tidak persis ada. Maksudmu:\n`".implode("`\n`", array_slice($options, 0, 6)).'`');

                return null;
            }
            if ($sport === 'futsal') {
                $low = strtolower(trim($wanted));
                if (in_array($low, ['indonesia', 'timnas', 'garuda'], true)) {
                    return 'Indonesia';
                }
                $futsal = new FutsalService;
                $options = $futsal->searchTeams($wanted);
                foreach ($options as $o) {
                    if (strcasecmp($o, $wanted) === 0) {
                        return $o;
                    }
                }
                if (empty($options)) {
                    $tg->sendMessage($cid, '⚠️ Hanya *Indonesia* yang didukung untuk futsal saat ini. Coba `/follow futsal Indonesia`.');

                    return null;
                }
                $tg->sendMessage($cid, "⚠️ Tim *{$wanted}* tidak persis ada. Maksudmu:\n`".implode("`\n`", array_slice($options, 0, 6)).'`');

                return null;
            }

            $options = $sport === 'football'
                ? (new FootballService)->searchTeams($wanted)
                : (new VolleyballService)->searchTeams($wanted);
            foreach ($options as $o) {
                if (strcasecmp($o, $wanted) === 0) {
                    return $o;
                }
            }
            if (empty($options)) {
                $tg->sendMessage($cid, "⚠️ Tim *{$wanted}* tidak ditemukan di data {$sport}.");

                return null;
            }
            $tg->sendMessage($cid, "⚠️ Tim *{$wanted}* tidak persis ada. Maksudmu:\n`".implode("`\n`", array_slice($options, 0, 6)).'`');

            return null;
        } catch (\Throwable $e) {
            $tg->sendMessage($cid, "❌ Gagal cek nama: {$e->getMessage()}");

            return null;
        }
    }

    private function handleUnfollow(int $uid, int $cid, string $text, TelegramService $tg): void
    {
        $p = explode(' ', $text);
        if (count($p) < 3) {
            $tg->sendMessage($cid, '⚠️ Format: `/unfollow [sport] [team]`');

            return;
        }
        try {
            $sport = SportPrefsService::normalizeSport(strtolower($p[1]));
            (new SportPrefsService(new SupabaseService))->removePreference($uid, $sport, strtolower(implode(' ', array_slice($p, 2))));
            $tg->sendMessage($cid, '✅ Berhenti memantau.');
        } catch (\Exception $e) {
            $tg->sendMessage($cid, '❌ Error hapus preferensi.');
        }
    }

    private function handleMyTeams(int $uid, int $cid, TelegramService $tg): void
    {
        try {
            $list = (new SportPrefsService(new SupabaseService))->getPreferences($uid);
            if (empty($list)) {
                $tg->sendMessage($cid, '📭 Belum ada yang dipantau.');

                return;
            }
            $m = "📋 *Tim Dipantau:*\n\n";
            foreach ($list as $i => $p) {
                $m .= ($i + 1).". *{$p['entity_name']}* ({$p['sport_type']}) ".($p['notification_enabled'] ? '🔔' : '🔕')."\n";
            }
            $tg->sendMessage($cid, $m);
        } catch (\Exception $e) {
            $tg->sendMessage($cid, '❌ Error ambil daftar.');
        }
    }

    private function handleJadwal(int $uid, int $cid, TelegramService $tg): void
    {
        try {
            $prefs = (new SportPrefsService(new SupabaseService))->getPreferences($uid);
            $prefs = array_values(array_filter($prefs, fn ($p) => ! isset($p['notification_enabled']) || (bool) $p['notification_enabled']));
            if (empty($prefs)) {
                $tg->sendMessage($cid, '📭 No teams followed. Use `/follow [sport] [team]`.');

                return;
            }

            $by = fn (array $types) => array_values(array_filter($prefs, fn ($p) => in_array($p['sport_type'], $types, true)));
            $fp = $by(['football']);
            $vp = $by(['volly']);
            $mp = $by(['motogp', 'moto2', 'moto3', 'baggers']);
            $lp = $by(['mobilelegend']);
            $fp2 = $by(['futsal']);

            $all = [];
            $now = new \DateTimeImmutable;

            // DB-first: fetch match_schedule then per-sport fallback (3 days max free plan)
            $dbRows = [];
            try {
                $raw = (new SupabaseService)->select('match_schedule', ['select' => '*', 'order' => 'match_time.asc', 'limit' => 50]);
                foreach ($raw as $r) {
                    $sid = $r['source_id'] ?? '';
                    if (! str_ends_with($sid, ":u{$uid}")) {
                        continue;
                    }
                    $status = strtolower($r['status'] ?? '');
                    if (! in_array($status, ['ns', 'scheduled'], true)) {
                        continue;
                    }
                    $mt = $r['match_time'] ?? '';
                    if (! MatchHelper::isNext3Days($mt, $now)) {
                        continue;
                    }
                    $dbRows[] = $r;
                }
            } catch (\Throwable) {
                $dbRows = [];
            }

            // Group DB rows by sport for per-sport fallback decision
            $hasDb = [];
            foreach ($dbRows as $r) {
                $st = $r['sport_type'] ?? '';
                $hasDb[$st] = true;
                $all[] = $this->formatJadwalRow($r);
            }

            // Per-sport fallback if DB empty for that sport
            if ($fp && empty(array_filter($dbRows, fn ($r) => ($r['sport_type'] ?? '') === 'football'))) {
                $all = array_merge($all, $this->fetchFootballFallback($fp, $now));
            }
            if ($vp && empty(array_filter($dbRows, fn ($r) => ($r['sport_type'] ?? '') === 'volly'))) {
                $all = array_merge($all, $this->fetchVollyFallback($vp, $now));
            }
            if ($mp) {
                $motoSports = array_unique(array_column($mp, 'sport_type'));
                foreach ($motoSports as $ms) {
                    if (! empty($hasDb[$ms])) {
                        continue;
                    }
                    $mpForClass = array_values(array_filter($mp, fn ($p) => $p['sport_type'] === $ms));
                    $all = array_merge($all, $this->fetchMotoFallback($mpForClass, $ms, $now));
                }
            }
            if ($lp && empty(array_filter($dbRows, fn ($r) => ($r['sport_type'] ?? '') === 'mobilelegend'))) {
                $all = array_merge($all, $this->fetchMobileLegendFallback($lp, $now));
            }
            if ($fp2 && empty(array_filter($dbRows, fn ($r) => ($r['sport_type'] ?? '') === 'futsal'))) {
                $all = array_merge($all, $this->fetchFutsalFallback($fp2, $now));
            }

            if (empty($all)) {
                $tg->sendMessage($cid, '📭 No schedule in the next 3 days.');

                return;
            }

            usort($all, fn ($a, $b) => strcmp($a['iso'], $b['iso']));
            $cap = 10;
            $slice = array_slice($all, 0, $cap);
            $msg = "📅 *Schedule next 3 days*\n\n";
            foreach ($slice as $i => $row) {
                $msg .= ($i + 1).'. '.$row['line']."\n";
            }
            $remaining = count($all) - $cap;
            if ($remaining > 0) {
                $msg .= "\n… and {$remaining} more";
            }
            $tg->sendMessage($cid, $msg);
        } catch (\Throwable $e) {
            $tg->sendMessage($cid, "⚠️ Failed to fetch schedule: {$e->getMessage()}");
        }
    }

    private function formatJadwalRow(array $r): array
    {
        $sport = $r['sport_type'] ?? '';
        $iso = $r['match_time'] ?? '';
        $time = DisplayTime::format($iso);
        if (in_array($sport, ['motogp', 'moto2', 'moto3', 'baggers'], true)) {
            $race = $r['competition'] ?? $sport;
            $circuit = $r['home_team'] ?? '';
            $line = "🏍️ *{$race}*".($circuit ? " @ {$circuit}" : '')." — ⏱️ {$time}";
        } elseif ($sport === 'volly') {
            $line = "🏐 {$r['home_team']} vs {$r['away_team']} — {$r['competition']} — ⏱️ {$time}";
        } elseif ($sport === 'mobilelegend') {
            $line = "🎮 {$r['home_team']} vs {$r['away_team']} — {$r['competition']} — ⏱️ {$time}";
        } elseif ($sport === 'futsal') {
            $line = "⚽ {$r['home_team']} vs {$r['away_team']} — {$r['competition']} — ⏱️ {$time}";
        } else {
            $line = "⚽ {$r['home_team']} vs {$r['away_team']} — {$r['competition']} — ⏱️ {$time}";
        }

        return ['iso' => $iso, 'line' => $line];
    }

    /**
     * @return array<int, array{iso:string,line:string}>
     */
    private function fetchFootballFallback(array $prefs, \DateTimeImmutable $now): array
    {
        try {
            $fixtures = (new FootballService)->getUpcomingFixtures();
            $out = [];
            foreach ($fixtures as $m) {
                $iso = $m['date'] ?? '';
                if (! MatchHelper::isNext3Days($iso, $now)) {
                    continue;
                }
                foreach ($prefs as $p) {
                    if (! NameMatcher::matches($m['home'], $p['entity_name']) && ! NameMatcher::matches($m['away'], $p['entity_name'])) {
                        continue;
                    }
                    $out[] = ['iso' => $iso, 'line' => "⚽ {$m['home']} vs {$m['away']} — {$m['league']} — ⏱️ ".DisplayTime::format($iso)];
                    break;
                }
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<int, array{iso:string,line:string}>
     */
    private function fetchVollyFallback(array $prefs, \DateTimeImmutable $now): array
    {
        try {
            $games = (new VolleyballService)->getUpcomingGames();
            $out = [];
            foreach ($games as $m) {
                $iso = $m['date'] ?? '';
                if (! MatchHelper::isNext3Days($iso, $now)) {
                    continue;
                }
                foreach ($prefs as $p) {
                    if (! NameMatcher::matches($m['home'], $p['entity_name']) && ! NameMatcher::matches($m['away'], $p['entity_name'])) {
                        continue;
                    }
                    $out[] = ['iso' => $iso, 'line' => "🏐 {$m['home']} vs {$m['away']} — {$m['league']} — ⏱️ ".DisplayTime::format($iso)];
                    break;
                }
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array{iso:string,line:string}>
     */
    private function fetchMotoFallback(array $prefs, string $class, \DateTimeImmutable $now): array
    {
        try {
            $moto = new MotoGPService;
            $races = $moto->getCurrentSeasonRaces($class);
            $out = [];
            foreach ($races as $r) {
                $iso = $r['date'].'T'.($r['time'] ?? '00:00:00');
                if (! MatchHelper::isNext3Days($iso, $now)) {
                    continue;
                }
                foreach ($prefs as $p) {
                    if (! $moto->matchesRace($r['raceName'], $p['entity_name'])) {
                        continue;
                    }
                    $out[] = ['iso' => $iso, 'line' => "🏍️ *{$r['raceName']}* @ {$r['Circuit']['circuitName']} — ⏱️ ".DisplayTime::format($iso)];
                    break;
                }
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array{iso:string,line:string}>
     */
    private function fetchMobileLegendFallback(array $prefs, \DateTimeImmutable $now): array
    {
        try {
            $matches = (new MobileLegendService)->getUpcomingMatches();
            $out = [];
            foreach ($matches as $m) {
                $iso = $m['date'] ?? '';
                if (! MatchHelper::isNext3Days($iso, $now)) {
                    continue;
                }
                foreach ($prefs as $p) {
                    if (! NameMatcher::matches($m['home'], $p['entity_name']) && ! NameMatcher::matches($m['away'], $p['entity_name'])) {
                        continue;
                    }
                    $out[] = ['iso' => $iso, 'line' => "🎮 {$m['home']} vs {$m['away']} — {$m['league']} — ⏱️ ".DisplayTime::format($iso)];
                    break;
                }
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array{iso:string,line:string}>
     */
    private function fetchFutsalFallback(array $prefs, \DateTimeImmutable $now): array
    {
        try {
            $matches = (new FutsalService)->getUpcomingMatches();
            $out = [];
            foreach ($matches as $m) {
                $iso = $m['date'] ?? '';
                if (! MatchHelper::isNext3Days($iso, $now)) {
                    continue;
                }
                // futsal timnas single team Indonesia — trivial contains
                foreach ($prefs as $p) {
                    if (! NameMatcher::matches($m['home'], $p['entity_name']) && ! NameMatcher::matches($m['away'], $p['entity_name'])) {
                        // fallback: if either side is Indonesia
                        if (stripos($m['home'].' '.$m['away'], 'Indonesia') === false) {
                            continue;
                        }
                    }
                    $out[] = ['iso' => $iso, 'line' => "⚽ {$m['home']} vs {$m['away']} — {$m['league']} — ⏱️ ".DisplayTime::format($iso)];
                    break;
                }
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }
}
