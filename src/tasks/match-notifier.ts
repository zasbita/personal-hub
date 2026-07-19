import { createClient } from '@supabase/supabase-js';
import { FootballService } from '../services/football';
import { MotoGPService } from '../services/motogp';
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
  const motogpService = new MotoGPService();

  try {
    // 1. Get all active preferences
    const { data: prefs, error: prefsError } = await supabase
      .from('user_preferences')
      .select('*')
      .eq('notification_enabled', true);

    if (prefsError) throw prefsError;
    if (!prefs || prefs.length === 0) {
      console.log('No active preferences found');
      return;
    }

    console.log(`Found ${prefs.length} active preferences`);

    // 2. Handle Football
    const footballPrefs = prefs.filter(p => p.sport_type === 'football');
    if (footballPrefs.length > 0) {
      await notifyFootball(supabase, footballService, footballPrefs, env);
    }

    // 3. Handle MotoGP (all classes)
    const motogpPrefs = prefs.filter(p => 
      p.sport_type === 'motogp' || p.sport_type === 'moto2' || p.sport_type === 'moto3'
    );
    if (motogpPrefs.length > 0) {
      await notifyMotoGP(supabase, motogpService, motogpPrefs, env);
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

async function notifyFootball(
  supabase: any,
  footballService: FootballService,
  prefs: any[],
  env: any
) {
  try {
    // Get live fixtures
    const liveFixtures = await footballService.getLiveFixtures();
    if (!liveFixtures.response || liveFixtures.response.length === 0) {
      console.log('No live football fixtures found');
      return;
    }

    console.log(`Found ${liveFixtures.response.length} live football fixtures`);

    // For each preference, check if there's a matching fixture
    for (const pref of prefs) {
      const matchingFixture = liveFixtures.response.find(
        (f: any) =>
          f.teams.home.name.toLowerCase() === pref.entity_name.toLowerCase() ||
          f.teams.away.name.toLowerCase() === pref.entity_name.toLowerCase()
      );

      if (matchingFixture) {
        // Check if already notified
        const { data: existingNotif } = await supabase
          .from('match_schedule')
          .select('id')
          .eq('source_id', matchingFixture.fixture.id.toString())
          .eq('sport_type', 'football')
          .single();

        if (!existingNotif) {
          // Send notification
          const homeTeam = matchingFixture.teams.home.name;
          const awayTeam = matchingFixture.teams.away.name;
          const matchTime = new Date(matchingFixture.fixture.date).toLocaleString('id-ID');
          const msg = `🔔 *Pertandingan Live!*\n\n${homeTeam} vs ${awayTeam}\n⏱️ ${matchTime}`;

          await sendMessage(env, pref.user_id, msg);

          // Store in match_schedule to avoid duplicate notifications
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
  } catch (error) {
    console.error('Football notification error:', error);
  }
}

async function notifyMotoGP(
  supabase: any,
  motogpService: MotoGPService,
  prefs: any[],
  env: any
) {
  try {
    // Get races for each classification
    const classifications = ['motogp', 'moto2', 'moto3'] as const;
    const allRaces: any[] = [];

    for (const classification of classifications) {
      const races = await motogpService.getCurrentSeasonRaces(classification);
      allRaces.push(...races.map(r => ({ ...r, classification })));
    }

    if (allRaces.length === 0) {
      console.log('No upcoming MotoGP races found');
      return;
    }

    console.log(`Found ${allRaces.length} upcoming MotoGP races`);

    // For each preference, check if there's a matching race
    for (const pref of prefs) {
      const matchingRace = allRaces.find(race =>
        motogpService.matchesRace(race.raceName, pref.entity_name) ||
        race.circuit.name.toLowerCase().includes(pref.entity_name.toLowerCase())
      );

      if (matchingRace) {
        // Check if already notified
        const { data: existingNotif } = await supabase
          .from('match_schedule')
          .select('id')
          .eq('source_id', `${matchingRace.classification}-${matchingRace.round}`)
          .eq('sport_type', matchingRace.classification)
          .single();

        if (!existingNotif) {
          // Send notification
          const msg = `🏍️ *${matchingRace.classification.toUpperCase()} Alert!*\n\n${motogpService.formatRaceInfo(matchingRace)}`;

          await sendMessage(env, pref.user_id, msg);

          // Store to avoid duplicate notifications
          await supabase.from('match_schedule').insert({
            source_id: `${matchingRace.classification}-${matchingRace.round}`,
            sport_type: matchingRace.classification,
            competition: matchingRace.raceName,
            home_team: matchingRace.circuit.name,
            away_team: `${matchingRace.circuit.location.locality}, ${matchingRace.circuit.location.country}`,
            match_time: `${matchingRace.date}T${matchingRace.time || '00:00:00'}`,
            status: 'scheduled',
            notified: true,
          });

          console.log(`Notified ${pref.user_id} about ${matchingRace.raceName}`);
        }
      }
    }
  } catch (error) {
    console.error('MotoGP notification error:', error);
  }
}

// Run
notifyMatches().catch((error) => {
  console.error('Fatal error:', error);
  process.exit(1);
});
