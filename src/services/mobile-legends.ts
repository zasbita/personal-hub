import { MobileLegendsMatch } from '../types/mobile-legends';
import { SupabaseClient } from '@supabase/supabase-js';

const LIQUIPEDIA_BASE_URL = 'https://liquipedia.net/mobilelegends';

export class MobileLegendsService {
  constructor(private supabase: SupabaseClient) {}

  /**
   * Fetches upcoming Mobile Legends matches from Supabase cache.
   * Stores are updated via external scraper or manual update.
   */
  async getUpcomingMatches(): Promise<MobileLegendsMatch[]> {
    const now = new Date();
    const in24Hours = new Date(now.getTime() + 24 * 60 * 60 * 1000);

    const { data, error } = await this.supabase
      .from('match_schedules')
      .select('*')
      .eq('sport_type', 'mobile_legends')
      .gte('match_time', now.toISOString())
      .lte('match_time', in24Hours.toISOString())
      .order('match_time');

    if (error) {
      throw new Error(`Failed to fetch Mobile Legends matches: ${error.message}`);
    }

    return (data || []).map(m => ({
      tournament: m.competition,
      stage: 'MPL',
      team1: m.home_team,
      team2: m.away_team,
      dateTime: new Date(m.match_time),
      sourceUrl: 'https://mpl.lolesports.id',
      status: m.status as any,
    }));
  }

  /**
   * Fetches upcoming Mobile Legends matches from Liquipedia (HTML scraping).
   * This is a basic HTML scraping, highly dependent on Liquipedia's DOM structure.
   * Note: This method is a fallback and may break with Liquipedia updates.
   */
  async getUpcomingMatchesByScraped(): Promise<MobileLegendsMatch[]> {
    const url = `${LIQUIPEDIA_BASE_URL}/Main_Page`;
    const response = await fetch(url);

    if (!response.ok) {
      throw new Error(`Failed to fetch Liquipedia MLBB: ${response.statusText}`);
    }

    const html = await response.text();
    const matches: MobileLegendsMatch[] = [];

    // Basic regex to find match cards. This will need refinement.
    // For a more robust solution, a DOM parser like 'linkedom' would be needed.
    // For this project, we prioritize quick implementation with regex.

    const matchRegex = /<div class="match-list-card"[^>]*>.*?<a[^>]*href="\/mobilelegends\/([^"]+)"[^>]*>([^<]+)<\/a>.*?vs.*?<a[^>]*href="\/mobilelegends\/([^"]+)"[^>]*>([^<]+)<\/a>.*?<span class="timer-object"[^>]*data-timestamp="(\d+)"[^>]*><\/span>.*?<\/div>/gs;

    let match;
    while ((match = matchRegex.exec(html)) !== null) {
      const tournament = 'Unknown Tournament'; // Need to parse from context
      const stage = 'Unknown Stage'; // Need to parse from context
      const team1 = match[2].trim();
      const team2 = match[4].trim();
      const timestamp = parseInt(match[5]) * 1000; // Convert to milliseconds
      const dateTime = new Date(timestamp);
      const sourceUrl = LIQUIPEDIA_BASE_URL + match[1]; // Link to match page

      // Filter matches that are in the future
      if (dateTime > new Date()) {
        matches.push({
          tournament,
          stage,
          team1,
          team2,
          dateTime,
          sourceUrl,
          status: 'upcoming',
        });
      }
    }

    return matches;
  }
}
