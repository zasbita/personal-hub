<?php

namespace App\Console\Commands;

use App\Services\{ExpenseParser, SheetsService, SportPrefsService, SupabaseService, TelegramService, VehicleService};
use Illuminate\Console\Command;

class TelegramBot extends Command
{
    protected $signature = 'bot:listen';
    protected $description = 'Listen for Telegram bot updates via long-polling';

    private int $offset = 0;

    public function handle(): int
    {
        $tg = new TelegramService();
        $oid = (int) config('services.telegram.owner_id');
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

        if ($text === '/start') { $tg->sendMessage($cid, "👋 *Serene Darwin*\n\n💰 `/log [amount] [desc]`\n📅 `/summary`\n🏍️ `/update_km [km]`\n🔧 `/check_service`\n⚽ `/follow [sport] [team]`\n🚫 `/unfollow [sport] [team]`\n📋 `/myteams`"); return; }
        if ($text === '/summary') { $this->handleSummary($cid, $tg); return; }
        if (str_starts_with($text, '/update_km')) { $this->handleKm($uid, $msg['from']['username'] ?? null, $cid, $text, $tg); return; }
        if ($text === '/check_service') { $this->handleService($uid, $cid, $tg); return; }
        if (str_starts_with($text, '/log') || str_starts_with($text, '/catat')) { $this->handleExpense($cid, $text, $tg); return; }
        if (str_starts_with($text, '/follow')) { $this->handleFollow($uid, $cid, $text, $tg); return; }
        if (str_starts_with($text, '/unfollow')) { $this->handleUnfollow($uid, $cid, $text, $tg); return; }
        if ($text === '/myteams') { $this->handleMyTeams($uid, $cid, $tg); return; }
        $tg->sendMessage($cid, "I don't understand. Try `/log`.");
    }

    private function handleSummary(int $cid, TelegramService $tg): void
    {
        try {
            $r = (new SheetsService())->getWeeklyExpenses();
            if (empty($r['items'])) { $tg->sendMessage($cid, "📅 Belum ada pengeluaran 7 hari terakhir."); return; }
            $m = "📅 *Ringkasan 7 Hari*\n\n";
            foreach ($r['items'] as $i => $it) { $m .= ($i + 1) . ". *{$it['date']}* - {$it['description']}: *Rp " . number_format($it['amount'], 0, ',', '.') . "*\n"; }
            $tg->sendMessage($cid, $m . "\n💰 *Total: Rp " . number_format($r['total'], 0, ',', '.') . "*");
        } catch (\Exception $e) { $tg->sendMessage($cid, "❌ Error mengambil data."); }
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

    private function handleExpense(int $cid, string $text, TelegramService $tg): void
    {
        $p = ExpenseParser::parse($text);
        if (!$p) { $tg->sendMessage($cid, "⚠️ Format: `/log 50k makan siang`"); return; }
        try {
            (new SheetsService())->appendExpense($p['amount'], $p['description'], $p['category']);
            $tg->sendMessage($cid, "✅ *Rp " . number_format($p['amount'], 0, ',', '.') . "* untuk *{$p['description']}*\n📁 {$p['category']}");
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
        try {
            (new SportPrefsService(new SupabaseService()))->addPreference($uid, strtolower($p[1]), strtolower(implode(' ', array_slice($p, 2))), implode(' ', array_slice($p, 2)));
            $tg->sendMessage($cid, "✅ Memantau *" . implode(' ', array_slice($p, 2)) . "* di *{$p[1]}*");
        } catch (\Exception $e) { $tg->sendMessage($cid, "❌ Error simpan preferensi."); }
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
