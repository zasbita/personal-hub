import { MobileLegendsMatch } from '../types/mobile-legends';

const LIQUIPEDIA_BASE_URL = 'https://liquipedia.net/mobilelegends';

export class MobileLegendsService {

  /**
   * Fetches upcoming Mobile Legends matches from Liquipedia.
   * This is a basic HTML scraping, highly dependent on Liquipedia's DOM structure.
   */
  async getUpcomingMatches(): Promise<MobileLegendsMatch[]> {
    const url = `${LIQUIPEDIA_BASE_URL}/Main_Page`; // Or specific tournament pages
    const response = await fetch(url);

    if (!response.ok) {
      throw new Error(`Failed to fetch Liquipedia MLBB: ${response.statusText}`);
    }

    const html = await response.text();
    const matches: MobileLegendsMatch[] = [];

    // Basic regex to find match cards. This will need refinement.
    // Liquipedia uses tables and divs with specific classes.
    // Example: <div class="match-list-card"> ... </div>
    // Finding upcoming matches typically involves looking for specific elements
    // that contain teams, score, and datetime.
    
    // For a more robust solution, a DOM parser like 'linkedom' or 'html-rewriter' (Cloudflare specific)
    // would be needed, but for KISS, let's try regex for critical data first.
    
    // Example: Looking for patterns like 'Team1 vs Team2' near a timestamp
    // This is a simplified example and might not cover all cases.
    const matchRegex = /<div class="match-list-card"[^>]*>.*?<a[^>]*href="\/mobilelegends\/([^"]+)"[^>]*>([^<]+)<\/a>.*?vs.*?<a[^>]*href="\/mobilelegends\/([^"]+)"[^>]*>([^<]+)<\/a>.*?<span class="timer-object"[^>]*data-timestamp="(\d+)"[^>]*><\/span>.*?<\/div>/gs;

    let match;
    while ((match = matchRegex.exec(html)) !== null) {
      const tournament = "Unknown Tournament"; // Need to parse from context
      const stage = "Unknown Stage"; // Need to parse from context
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

    // This regex is very brittle and likely to break with any small change on Liquipedia.
    // A proper DOM parser is recommended for production.
    // For this project, we prioritize quick implementation.

    return matches;
  }
}