import { BotEnv, TelegramUpdate } from '../types/telegram';
import { sendMessage } from '../services/telegram';
import { SportPrefsService } from './sport-prefs';
import { getSupabase } from './supabase';

export class SportBotHandler {
  static async handleFollow(c: any, chatId: number, text: string, userId: string) {
    const parts = text.split(' ');
    if (parts.length < 3) {
      await sendMessage(c.env, chatId, '⚠️ *Format Salah.*\\nGunakan: `/follow [sport] [team]`\\nContoh: `/follow football liverpool`');
      return;
    }

    const sportType = parts[1].toLowerCase();
    const entityName = parts.slice(2).join(' ');

    try {
      const supabase = getSupabase(c.env.SUPABASE_URL, c.env.SUPABASE_KEY);
      const prefs = new SportPrefsService(supabase);
      
      // For now, we use the name as entity_id since we'll resolve it in the scraper
      await prefs.addPreference(userId, sportType, entityName.toLowerCase(), entityName);
      
      await sendMessage(c.env, chatId, `✅ *Berhasil!* Sekarang saya akan memantau *${entityName}* di kategori *${sportType}*.`);
    } catch (error) {
      console.error('Follow Error:', error);
      await sendMessage(c.env, chatId, '❌ *Error:* Gagal menyimpan preferensi olahraga.');
    }
  }

  static async handleUnfollow(c: any, chatId: number, text: string, userId: string) {
    const parts = text.split(' ');
    if (parts.length < 3) {
      await sendMessage(c.env, chatId, '⚠️ *Format Salah.*\\nGunakan: `/unfollow [sport] [team]`\\nContoh: `/unfollow football liverpool`');
      return;
    }

    const sportType = parts[1].toLowerCase();
    const entityName = parts.slice(2).join(' ').toLowerCase();

    try {
      const supabase = getSupabase(c.env.SUPABASE_URL, c.env.SUPABASE_KEY);
      const prefs = new SportPrefsService(supabase);
      await prefs.removePreference(userId, sportType, entityName);
      await sendMessage(c.env, chatId, `✅ *Berhasil!* Berhenti memantau *${entityName}*.`);
    } catch (error) {
      console.error('Unfollow Error:', error);
      await sendMessage(c.env, chatId, '❌ *Error:* Gagal menghapus preferensi.');
    }
  }

  static async handleMyTeams(c: any, chatId: number, userId: string) {
    try {
      const supabase = getSupabase(c.env.SUPABASE_URL, c.env.SUPABASE_KEY);
      const prefs = new SportPrefsService(supabase);
      const list = await prefs.getPreferences(userId);

      if (list.length === 0) {
        await sendMessage(c.env, chatId, '📭 Anda belum mengikuti tim atau liga apapun.');
        return;
      }

      let msg = '📋 *Tim & Liga yang Dipantau:*\n\n';
      list.forEach((p, i) => {
        msg += `${i+1}. *${p.entity_name}* (${p.sport_type}) ${p.notification_enabled ? '🔔' : '🔕'}\n`;
      });
      
      await sendMessage(c.env, chatId, msg);
    } catch (error) {
      console.error('MyTeams Error:', error);
      await sendMessage(c.env, chatId, '❌ *Error:* Gagal mengambil daftar pantauan.');
    }
  }
}
