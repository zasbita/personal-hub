import { createClient } from '@supabase/supabase-js';
import { FootballService } from '../services/football';
import { sendMessage } from '../services/telegram';
import { MatchSchedule, UserPreference } from '../types/sport';

const env = {
  SUPABASE_URL: process.env.SUPABASE_URL || '',
  SUPABASE_KEY: process.env.SUPABASE_KEY || '',
  BOT_TOKEN: process.env.BOT_TOKEN || '',
  OWNER_ID: parseInt(process.env.OWNER_ID || '0', 10),
  FOOTBALL_API_KEY: process.env.FOOTBALL_API_KEY || '',
} as any;

async function notifyMatches() {
  if (!env.SUPABASE_URL || !env.SUPABASE_KEY) {
    console.error('Missing Supabase credentials');
    return;
  }

  const supabase = createClient(env.SUPABASE_URL, env.SUPABASE_KEY, {
    auth: { persistSession: false },
  });
  const footballService = new FootballService(env);

  try {
    // 1. Get all active football preferences
    const { data: prefs, error: prefsError } = await supabase
      .from('user_preferences')
      .select('*')
      .eq('sport_type', 'football')
      .eq('notification_enabled', true);

    if (prefsError) throw prefsError;
    if (!prefs || prefs.length === 0) {
      console.log('No active football preferences found');
      return;
    }

    console.log(`Found ${prefs.length} active preferences`);

    // 2. Get live fixtures
    const liveFixtures = await footballService.getLiveFixtures();
    if (!liveFixtures.response || liveFixtures.response.length === 0) {
      console.log('No live fixtures found');
      return;
    }

    console.log(`Found ${liveFixtures.response.length} live fixtures`);

    // 3. For each preference, check if there's a matching fixture
    for (const pref of prefs) {
      const matchingFixture = liveFixtures.response.find(
        (f: any) =>
          f.teams.home.name.toLowerCase() === pref.entity_name.toLowerCase() ||
          f.teams.away.name.toLowerCase() === pref.entity_name.toLowerCase()
      );

      if (matchingFixture) {
        // 4. Check if already notified
        const { data: existingNotif } = await supabase
          .from('match_schedule')
          .select('id')
          .eq('source_id', matchingFixture.fixture.id.toString())
          .eq('sport_type', 'football')
          .single();

        if (!existingNotif) {
          // 5. Send notification
          const homeTeam = matchingFixture.teams.home.name;
          const awayTeam = matchingFixture.teams.away.name;
          const matchTime = new Date(matchingFixture.fixture.date).toLocaleString('id-ID');
          const msg = `🔔 *Pertandingan Live!*\n\n${homeTeam} vs ${awayTeam}\n⏱️ ${matchTime}`;

          await sendMessage(env, pref.user_id, msg);

          // 6. Store in match_schedule to avoid duplicate notifications
          await supabase.from('match_schedule').insert({
            source_id: matchingFixture.fixture.id.toString(),
            sport_type: 'football',
            competition: matchingFixture.league.name,
            home_team: homeTeam,
            away_team: awayTeam,
            match_time: matchingFixture.fixture.date,
            status: matchingFixture.fixture.status.short,
            notified: true,
          });

          console.log(`Notified ${pref.user_id} about ${homeTeam} vs ${awayTeam}`);
        }
      }
    }

    console.log('Match notification cycle complete');
  } catch (error) {
    console.error('Notification Error:', error);
    // Notify owner of error
    if (env.BOT_TOKEN && env.OWNER_ID) {
      await sendMessage(env, env.OWNER_ID, `❌ *Match Notifier Error*\n\n${error}`);
    }
  }
}

// Run
notifyMatches().catch((error) => {
  console.error('Fatal error:', error);
  process.exit(1);
});
