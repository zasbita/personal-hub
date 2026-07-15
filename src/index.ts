import { Hono } from 'hono'
import { BotEnv, TelegramUpdate } from './types/telegram'
import { sendMessage } from './services/telegram'
import { SheetsService } from './services/sheets'
import { ExpenseParser } from './utils/expense-parser'
import { getSupabase } from './services/supabase'
import { VehicleService } from './services/vehicle'

const app = new Hono<{ Bindings: BotEnv & { GOOGLE_SHEET_ID: string, GOOGLE_SERVICE_ACCOUNT_EMAIL: string, GOOGLE_PRIVATE_KEY: string, SUPABASE_URL: string, SUPABASE_KEY: string } }>()

app.get('/', (c) => {
  return c.text('Serene Darwin Bot is active ⚡')
})

app.post('/webhook', async (c) => {
  const update = await c.req.json() as TelegramUpdate;
  
  if (!update.message || !update.message.from) {
    return c.json({ ok: true }, { status: 200 });
  }

  const userId = update.message.from.id.toString().trim();
  const username = update.message.from.username;
  const chatId = update.message.chat.id;
  const text = update.message.text || '';

  // SECURITY: Whitelist Check
  const ownerId = c.env.OWNER_ID?.toString().trim();
  if (userId !== ownerId) {
    console.log(`Unauthorized access attempt from ID: ${userId}. Expected: ${ownerId}`);
    await sendMessage(c.env as any, chatId, `❌ *Unauthorized Access.*\n\nYour ID: \`${userId}\`\nOwner ID: \`${ownerId}\``);
    return c.json({ ok: true }, { status: 200 });
  }

  // 1. Handle /start command
  if (text === '/start') {
    await sendMessage(c.env as any, chatId, '👋 Welcome to *Serene Darwin*!\n\n🤖 *Menu Commands:*\n💰 `/log [amount] [description]` - Catat pengeluaran\n📅 `/summary` - Ringkasan pengeluaran mingguan\n🏍️ `/update_km [number]` - Update odometer motor\n🔧 `/check_service` - Cek sisa KM jadwal servis oli');
    return c.json({ ok: true }, { status: 200 });
  }

  // 2. Handle /summary command
  if (text === '/summary') {
    try {
      const sheets = new SheetsService(c.env);
      const { total, items } = await sheets.getWeeklyExpenses();

      const formatIDR = (num: number) => new Intl.NumberFormat('id-ID', { 
        style: 'currency', 
        currency: 'IDR', 
        minimumFractionDigits: 0 
      }).format(num);

      if (items.length === 0) {
        await sendMessage(c.env as any, chatId, '📅 *Ringkasan Mingguan*\n\nBelum ada pengeluaran yang dicatat dalam 7 hari terakhir.');
        return c.json({ ok: true }, { status: 200 });
      }

      let message = `📅 *Ringkasan Mingguan (7 Hari Terakhir)*\n\n`;
      items.forEach((item, idx) => {
        message += `${idx + 1}. *${item.date}* - ${item.description}: *${formatIDR(item.amount)}*\n`;
      });
      message += `\n💰 *Total Pengeluaran:* *${formatIDR(total)}*`;

      await sendMessage(c.env as any, chatId, message);
    } catch (error) {
      console.error('Summary Error:', error);
      await sendMessage(c.env as any, chatId, '❌ *Error:* Gagal mengambil data pengeluaran dari Google Sheets.');
    }
    return c.json({ ok: true }, { status: 200 });
  }

  // 3. Handle /update_km [number] command
  if (text.startsWith('/update_km')) {
    const match = text.match(/\/update_km\s+(\d+)/);
    if (!match) {
      await sendMessage(c.env as any, chatId, '⚠️ *Format Salah.*\nGunakan: `/update_km [angka]`\nContoh: `/update_km 15200`');
      return c.json({ ok: true }, { status: 200 });
    }

    const km = parseInt(match[1], 10);
    try {
      const supabase = getSupabase(c.env.SUPABASE_URL, c.env.SUPABASE_KEY);
      const vehicle = new VehicleService(supabase);
      
      const result = await vehicle.updateOdometer(userId, username, km);

      const formatKM = (num: number) => new Intl.NumberFormat('id-ID').format(num);

      await sendMessage(
        c.env as any,
        chatId,
        `🏍️ *Odometer Diperbarui!*\n\n📍 KM Terkini: *${formatKM(result.lastKm)} KM*\n🔧 Target Servis Berikutnya: *${formatKM(result.nextServiceKm)} KM*\n\n_Selisih interval standar adalah ${formatKM(result.interval)} KM._`
      );
    } catch (error) {
      console.error('Update KM Error:', error);
      await sendMessage(c.env as any, chatId, '❌ *Error:* Gagal mengupdate odometer ke database Supabase.');
    }
    return c.json({ ok: true }, { status: 200 });
  }

  // 4. Handle /check_service command
  if (text === '/check_service') {
    try {
      const supabase = getSupabase(c.env.SUPABASE_URL, c.env.SUPABASE_KEY);
      const vehicle = new VehicleService(supabase);
      
      const result = await vehicle.getServiceStatus(userId);

      if (!result) {
        await sendMessage(c.env as any, chatId, '⚠️ *Data Tidak Ditemukan.*\nAnda belum pernah memasukkan odometer.\nGunakan `/update_km [angka]` terlebih dahulu.');
        return c.json({ ok: true }, { status: 200 });
      }

      const formatKM = (num: number) => new Intl.NumberFormat('id-ID').format(num);

      let statusMsg = '';
      if (result.remainingKm <= 0) {
        statusMsg = `🚨 *WAKTUNYA SERVIS OLI SEKARANG!*\nAnda telah melewati batas target sejauh *${formatKM(Math.abs(result.remainingKm))} KM*!`;
      } else if (result.remainingKm <= 200) {
        statusMsg = `⚠️ *Peringatan:* Jadwal servis oli tinggal *${formatKM(result.remainingKm)} KM* lagi. Siap-siap servis!`;
      } else {
        statusMsg = `✅ Status motor aman. Sisa *${formatKM(result.remainingKm)} KM* lagi menuju jadwal servis berikutnya.`;
      }

      await sendMessage(
        c.env as any,
        chatId,
        `🔧 *Status Servis Motor*\n\n📍 KM Terakhir Dicatat: *${formatKM(result.lastKm)} KM*\n🎯 Target Servis Oli: *${formatKM(result.nextServiceKm)} KM*\n\n${statusMsg}`
      );
    } catch (error) {
      console.error('Check Service Error:', error);
      await sendMessage(c.env as any, chatId, '❌ *Error:* Gagal mengambil status servis dari database.');
    }
    return c.json({ ok: true }, { status: 200 });
  }

  // 5. Handle /log command (Expense Logger)
  if (text.startsWith('/log') || text.startsWith('/catat')) {
    const parsed = ExpenseParser.parse(text);
    
    if (!parsed) {
      await sendMessage(c.env as any, chatId, '⚠️ *Format Salah.*\nContoh: `/log 50k makan siang` atau `/catat 12.000 kopi`');
      return c.json({ ok: true }, { status: 200 });
    }

    try {
      const sheets = new SheetsService(c.env);
      await sheets.appendExpense(parsed.amount, parsed.description, parsed.category);
      
      const formattedAmount = new Intl.NumberFormat('id-ID', { 
        style: 'currency', 
        currency: 'IDR', 
        minimumFractionDigits: 0 
      }).format(parsed.amount);

      await sendMessage(c.env as any, chatId, `✅ *Berhasil dicatat!*\n\n💰 *${formattedAmount}* untuk *${parsed.description}*\n📁 Kategori: *${parsed.category}*\n\nData telah disimpan ke Google Sheets.`);
    } catch (error) {

      console.error('Sheets Error:', error);
      await sendMessage(c.env as any, chatId, '❌ *Error:* Gagal menyimpan data ke Google Sheets. Pastikan Service Account sudah memiliki akses edit ke Spreadsheet.');
    }

    return c.json({ ok: true }, { status: 200 });
  }

  // Default response
  await sendMessage(c.env as any, chatId, `I don't understand that. Try using \`/log\`.`);
  return c.json({ ok: true }, { status: 200 });
})

export default app
