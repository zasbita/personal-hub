<?php

namespace App\Console\Commands;

use App\Services\{ExpenseParser, ExpenseSummary, FootballService, MotoGPService, SheetsService, SportPrefsService, SupabaseService, TelegramService, VehicleService, VolleyballService};
use Illuminate\Console\Command;

class TelegramBot extends Command
{
    protected $signature = 'bot:listen';
    protected $description = 'Listen for Telegram bot updates via long-polling';

    private int $offset = 0;

    /** Days a `/summary 30` may ask for. A year of rows is already more than Telegram will show. */
    private const SUMMARY_MAX_DAYS = 365;

    /** Plain chat must name a believable amount before it counts as an expense. */
    private const IMPLICIT_MIN_AMOUNT = 1000;

    /** The command list Telegram shows in its own "/" menu. */
    private const MENU = [
        'log' => 'Catat pengeluaran — /log 50k makan siang',
        'undo' => 'Hapus pengeluaran terakhir',
        'summary' => 'Ringkasan pengeluaran — /summary atau /summary 30',
        'update_km' => 'Update odometer motor — /update_km 12500',
        'check_service' => 'Sisa KM sampai servis berikutnya',
        'follow' => 'Pantau tim atau balapan — /follow volly Indonesia',
        'unfollow' => 'Berhenti memantau — /unfollow volly Indonesia',
        'myteams' => 'Daftar yang sedang dipantau',
    ];

    private const WELCOME = "👋 *Serene Darwin*\n"
        . "Asisten pribadi buat catat duit, motor, dan jadwal pertandingan.\n\n"
        . "💰 *Keuangan*\n"
        . "`50k makan siang` — catat langsung, tanpa perintah\n"
        . "`25k kopi #Jajan` — pakai kategori\n"
        . "`/undo` — hapus catatan terakhir\n"
        . "`/summary` — ringkasan 7 hari\n"
        . "`/summary 30` — ganti jumlah hari\n\n"
        . "🏍️ *Motor*\n"
        . "`/update_km 12500` — update odometer\n"
        . "`/check_service` — sisa KM ke servis\n\n"
        . "🏆 *Olahraga*\n"
        . "`/follow volly Indonesia` — pantau tim\n"
        . "`/unfollow volly Indonesia` — berhenti pantau\n"
        . "`/myteams` — daftar pantauan\n\n"
        . "_Notifikasi 1 jam sebelum pertandingan, skor akhir setelah selesai,_\n"
        . "_laporan mingguan tiap Senin pagi._";

    public function handle(): int
    {
        $tg = new TelegramService();
        $oid = (int) config('services.telegram.owner_id');
        $tg->setCommands(self::MENU);
        $this->info("Bot listening... Owner ID: {$oid}");

        while (true) {
            try {
                $updates = $tg->getUpdates($this->offset, 30);
                foreach ($updates['result'] ?? [] as $u) {
                    $this->offset = $u['update_id'] + 1;
                    $this->handleUpdate($u, $oid, $tg);
                }
            } catch (\Exception $e) { $this->error("Error: {$e->getMessage()}"); sleep(5); }
        }
    }

    private function handleUpdate(array $u, int $oid, TelegramService $tg): void
    {
        $msg = $u['message'] ?? null;
        if (!$msg || !isset($msg['from'])) return;
        $uid = (int) $msg['from']['id'];
        $cid = $msg['chat']['id'];
        $text = $msg['text'] ?? '';

        if ($uid !== $oid) { $tg->sendMessage($cid, "❌ *Unauthorized.* Your ID: `{$uid}`"); return; }

        if ($text === '/start' || $text === '/help') { $tg->sendMessage($cid, self::WELCOME); return; }
        if (str_starts_with($text, '/summary')) { $this->handleSummary($cid, $text, $tg); return; }
        if (str_starts_with($text, '/update_km')) { $this->handleKm($uid, $msg['from']['username'] ?? null, $cid, $text, $tg); return; }
        if ($text === '/check_service') { $this->handleService($uid, $cid, $tg); return; }
        if (str_starts_with($text, '/log') || str_starts_with($text, '/catat')) { $this->handleExpense($cid, $text, $tg); return; }
        if ($text === '/undo') { $this->handleUndo($cid, $tg); return; }
        if (str_starts_with($text, '/follow')) { $this->handleFollow($uid, $cid, $text, $tg); return; }
        if (str_starts_with($text, '/unfollow')) { $this->handleUnfollow($uid, $cid, $text, $tg); return; }
        if ($text === '/myteams') { $this->handleMyTeams($uid, $cid, $tg); return; }
        if (str_starts_with($text, '/')) { $tg->sendMessage($cid, "❓ Perintah tidak dikenal. Lihat `/help`."); return; }
        // Plain text is an expense: typing /log before every note is friction.
        $this->handleExpense($cid, $text, $tg, true);
    }

    private function handleSummary(int $cid, string $text, TelegramService $tg): void
    {
        preg_match('/^\/summary\s+(\d+)/', $text, $dm);
        $days = min(max((int) ($dm[1] ?? 7), 1), self::SUMMARY_MAX_DAYS);
        try {
            $r = (new SheetsService())->getRecentExpenses($days);
            if (empty($r['items'])) { $tg->sendMessage($cid, "📅 Belum ada pengeluaran {$days} hari terakhir."); return; }
            $tg->sendMessage($cid, ExpenseSummary::format($r, $days));
        } catch (\Exception $e) { $tg->sendMessage($cid, "❌ Error mengambil data."); }
    }

    private function handleUndo(int $cid, TelegramService $tg): void
    {
        try {
            $s = new SheetsService();
            $last = $s->lastExpense();
            if (!$last) { $tg->sendMessage($cid, "📭 Belum ada pengeluaran untuk dihapus."); return; }
            $s->deleteExpenseRow($last['row']);
            $tg->sendMessage($cid, "🗑️ *Dihapus:* Rp " . ExpenseSummary::rupiah($last['amount']) . " - {$last['description']}");
        } catch (\Exception $e) { $tg->sendMessage($cid, "❌ Error hapus pengeluaran."); }
    }

    private function handleKm(int $uid, ?string $un, int $cid, string $text, TelegramService $tg): void
    {
        if (!preg_match('/\/update_km\s+(\d+)/', $text, $m)) { $tg->sendMessage($cid, "⚠️ Format: `/update_km [angka]`"); return; }
        try {
            $r = (new VehicleService(new SupabaseService()))->updateOdometer($uid, $un, (int) $m[1]);
            $tg->sendMessage($cid, "🏍️ *Updated!*\n📍 KM: *" . number_format($r['lastKm']) . "*\n🔧 Servis: *" . number_format($r['nextServiceKm']) . " KM*");
        } catch (\Exception $e) { $tg->sendMessage($cid, "❌ Error update odometer."); }
    }

    private function handleService(int $uid, int $cid, TelegramService $tg): void
    {
        try {
            $r = (new VehicleService(new SupabaseService()))->getServiceStatus($uid);
            if (!$r) { $tg->sendMessage($cid, "⚠️ Belum ada data. Gunakan `/update_km` dulu."); return; }
            $s = $r['remainingKm'] <= 0 ? "🚨 *SERVIS SEKARANG!* Lewat " . number_format(abs($r['remainingKm'])) . " KM" : ($r['remainingKm'] <= 200 ? "⚠️ Tinggal *" . number_format($r['remainingKm']) . " KM*" : "✅ Aman. Sisa *" . number_format($r['remainingKm']) . " KM*");
            $tg->sendMessage($cid, "🔧 *Status Servis*\n📍 Terakhir: *" . number_format($r['lastKm']) . " KM*\n🎯 Target: *" . number_format($r['nextServiceKm']) . " KM*\n\n{$s}");
        } catch (\Exception $e) { $tg->sendMessage($cid, "❌ Error cek servis."); }
    }

    private function handleExpense(int $cid, string $text, TelegramService $tg, bool $implicit = false): void
    {
        $p = ExpenseParser::parse($text, $implicit ? self::IMPLICIT_MIN_AMOUNT : 0);
        if (!$p) { $tg->sendMessage($cid, "⚠️ Format: `/log 50k makan siang`"); return; }
        try {
            $s = new SheetsService();
            $s->appendExpense($p['amount'], $p['description'], $p['category']);
            $reply = "✅ *Rp " . ExpenseSummary::rupiah($p['amount']) . "* untuk *{$p['description']}*\n📁 {$p['category']}";
            $budget = ExpenseSummary::budgetLine($s->spentThisMonth());
            $tg->sendMessage($cid, $budget ? "{$reply}\n\n{$budget}" : $reply);
        } catch (\Exception $e) { $tg->sendMessage($cid, "❌ Error simpan ke Sheets."); }
    }

    private function handleFollow(int $uid, int $cid, string $text, TelegramService $tg): void
    {
        $p = explode(' ', $text);
        if (count($p) < 3) { $tg->sendMessage($cid, "⚠️ Format: `/follow [sport] [team]`"); return; }
        if (!in_array(strtolower($p[1]), SportPrefsService::SPORTS, true)) {
            $tg->sendMessage($cid, "⚠️ Sport *{$p[1]}* belum didukung.\nPilihan: `" . implode('`, `', SportPrefsService::SPORTS) . "`");
            return;
        }
        $sport = strtolower($p[1]);
        $wanted = implode(' ', array_slice($p, 2));
        try {
            $name = $this->resolveEntity($sport, $wanted, $cid, $tg);
            if ($name === null) return;
            (new SportPrefsService(new SupabaseService()))->addPreference($uid, $sport, strtolower($name), $name);
            $tg->sendMessage($cid, "✅ Memantau *{$name}* di *{$sport}*");
        } catch (\Exception $e) { $tg->sendMessage($cid, "❌ Error simpan preferensi."); }
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
                $moto = new MotoGPService();
                $races = $moto->upcomingRaceNames();
                foreach ($races as $r) {
                    if ($moto->matchesRace($r, $wanted)) return $wanted;
                }
                $tg->sendMessage($cid, "⚠️ *{$wanted}* tidak cocok dengan sisa balapan musim ini.\nContoh: `" . implode('`, `', array_slice($races, 0, 4)) . "`");
                return null;
            }

            $options = $sport === 'football'
                ? (new FootballService())->searchTeams($wanted)
                : (new VolleyballService())->searchTeams($wanted);
            foreach ($options as $o) {
                if (strcasecmp($o, $wanted) === 0) return $o;
            }
            if (empty($options)) {
                $tg->sendMessage($cid, "⚠️ Tim *{$wanted}* tidak ditemukan di data {$sport}.");
                return null;
            }
            $tg->sendMessage($cid, "⚠️ Tim *{$wanted}* tidak persis ada. Maksudmu:\n`" . implode("`\n`", array_slice($options, 0, 6)) . "`");
            return null;
        } catch (\Throwable $e) {
            $tg->sendMessage($cid, "❌ Gagal cek nama: {$e->getMessage()}");
            return null;
        }
    }

    private function handleUnfollow(int $uid, int $cid, string $text, TelegramService $tg): void
    {
        $p = explode(' ', $text);
        if (count($p) < 3) { $tg->sendMessage($cid, "⚠️ Format: `/unfollow [sport] [team]`"); return; }
        try {
            (new SportPrefsService(new SupabaseService()))->removePreference($uid, strtolower($p[1]), strtolower(implode(' ', array_slice($p, 2))));
            $tg->sendMessage($cid, "✅ Berhenti memantau.");
        } catch (\Exception $e) { $tg->sendMessage($cid, "❌ Error hapus preferensi."); }
    }

    private function handleMyTeams(int $uid, int $cid, TelegramService $tg): void
    {
        try {
            $list = (new SportPrefsService(new SupabaseService()))->getPreferences($uid);
            if (empty($list)) { $tg->sendMessage($cid, "📭 Belum ada yang dipantau."); return; }
            $m = "📋 *Tim Dipantau:*\n\n";
            foreach ($list as $i => $p) { $m .= ($i + 1) . ". *{$p['entity_name']}* ({$p['sport_type']}) " . ($p['notification_enabled'] ? '🔔' : '🔕') . "\n"; }
            $tg->sendMessage($cid, $m);
        } catch (\Exception $e) { $tg->sendMessage($cid, "❌ Error ambil daftar."); }
    }
}
