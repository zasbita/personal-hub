import { createClient } from '@supabase/supabase-js';
import { FootballService } from '../src/services/football'; // adjust path if needed

async function testNotify() {
  const env = process.env;
  console.log('ENV keys:', Object.keys(env).filter(k => k.includes('SUPABASE')));
  const supabase = createClient(env.SUPABASE_URL || '', env.SUPABASE_KEY || '');
  const service = new FootballService(env);
  
  // Mock upcoming match data
  const mockUpcoming = {
    response: [{
      fixture: { id: 99999, date: new Date(Date.now() + 5 * 60000).toISOString() }, // 5 mins from now
      teams: { home: { name: 'Test Team A' }, away: { name: 'Test Team B' } },
      league: { name: 'Test League' }
    }]
  };

  // Mock preferences
  const prefs = [{ user_id: env.OWNER_ID, entity_name: 'Test Team A', sport_type: 'football' }];

  // Call the function
  console.log('Sending mock notification...');
  // Logic inside notifyUpcomingFootball is coupled, so we replicate the core block
  // to trigger without full service call:
  const msg = `📢 *Test Notification: Pertandingan Akan Segera Dimulai!*\\n\\nTest Team A vs Test Team B\\n⏱️ ${new Date().toLocaleString()}`;
  
  const sendMessage = async (env: any, chatId: string, text: string) => {
    const url = `https://api.telegram.org/bot${env.BOT_TOKEN}/sendMessage`;
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ chat_id: chatId, text: text, parse_mode: 'Markdown' })
    });
  };

  await sendMessage(env, env.OWNER_ID!, msg);
  console.log('Sent. Check your Telegram!');
}

testNotify().catch(console.error);
