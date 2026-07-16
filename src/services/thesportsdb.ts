import { SportsDbEventsResponse } from '../types/thesportsdb';

const BASE_URL = 'https://www.thesportsdb.com/api/v1/json/60130162'; // Test API Key (not for production)

export class TheSportsDbService {

  private async fetchApi(endpoint: string, params: Record<string, string> = {}): Promise<any> {
    const url = new URL(`${BASE_URL}${endpoint}`);
    Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));

    const response = await fetch(url.toString());

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({ message: response.statusText }));
      throw new Error(`TheSportsDB API Error ${response.status}: ${JSON.stringify(errorData)}`);
    }

    return response.json();
  }

  /**
   * Get all events for a specific league and date
   * Note: This API key (1) is a demo key. For production, get a proper key.
   * @param leagueName e.g., "Volleyball League"
   * @param date YYYY-MM-DD
   */
  async getEventsByLeagueAndDate(leagueName: string, date: string): Promise<SportsDbEventsResponse> {
    // First, find the league ID
    const searchLeagueResponse = await this.fetchApi('/search_all_leagues.php', { s: leagueName });
    const league = searchLeagueResponse.leagues?.find((l: any) => l.strLeague === leagueName);

    if (!league) {
      return { events: null };
    }

    // Then, get events for that league and date
    return this.fetchApi('/eventsround.php', { id: league.idLeague, d: date });
  }

  /**
   * Get all upcoming events by a specific team
   */
  async getUpcomingEventsByTeam(teamName: string): Promise<SportsDbEventsResponse> {
    // First, find the team ID
    const searchTeamResponse = await this.fetchApi('/searchteams.php', { t: teamName });
    const team = searchTeamResponse.teams?.find((t: any) => t.strTeam === teamName);

    if (!team) {
      return { events: null };
    }
    // Then, get upcoming events for that team
    return this.fetchApi('/eventsnext.php', { id: team.idTeam });
  }

  /**
   * Get all upcoming events in a specific league
   */
  async getUpcomingEventsInLeague(leagueName: string): Promise<SportsDbEventsResponse> {
    const searchLeagueResponse = await this.fetchApi('/search_all_leagues.php', { s: leagueName });
    const league = searchLeagueResponse.leagues?.find((l: any) => l.strLeague === leagueName);

    if (!league) {
      return { events: null };
    }
    return this.fetchApi('/eventsnextleague.php', { id: league.idLeague });
  }

  /**
   * Get all active sports (for general overview, not for specific matches)
   */
  async getAllSports(): Promise<any> {
    return this.fetchApi('/all_sports.php');
  }
}
