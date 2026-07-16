import { ApiFootballResponse, ApiFootballLeaguesResponse } from '../types/football';
import { BotEnv } from '../types/telegram';

const BASE_URL = 'https://v3.football.api-sports.io';

export class FootballService {
  private apiKey: string;

  constructor(env: BotEnv & { FOOTBALL_API_KEY: string }) {
    this.apiKey = env.FOOTBALL_API_KEY;
    if (!this.apiKey || this.apiKey === 'YOUR_FOOTBALL_API_KEY') {
        console.warn("FOOTBALL_API_KEY is not set. Football match data will not be available.");
    }
  }

  private async fetchApi(endpoint: string, params: Record<string, string>): Promise<any> {
    if (!this.apiKey || this.apiKey === 'YOUR_FOOTBALL_API_KEY') {
        throw new Error("Football API Key is not configured.");
    }

    const url = new URL(`${BASE_URL}${endpoint}`);
    Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));

    const response = await fetch(url.toString(), {
      headers: {
        'x-apisports-key': this.apiKey,
      },
    });

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({ message: response.statusText }));
      throw new Error(`API Football Error ${response.status}: ${JSON.stringify(errorData)}`);
    }

    return response.json();
  }

  async searchLeagues(searchQuery: string): Promise<ApiFootballLeaguesResponse> {
    return this.fetchApi('/leagues', { search: searchQuery });
  }

  async searchTeams(searchQuery: string): Promise<ApiFootballResponse> {
    return this.fetchApi('/teams', { search: searchQuery });
  }

  async getFixturesByLeague(leagueId: number, season: number, fromDate: string, toDate: string): Promise<ApiFootballResponse> {
    return this.fetchApi('/fixtures', {
      league: leagueId.toString(),
      season: season.toString(),
      from: fromDate,
      to: toDate,
    });
  }

  async getFixturesByTeam(teamId: number, season: number, fromDate: string, toDate: string): Promise<ApiFootballResponse> {
    return this.fetchApi('/fixtures', {
      team: teamId.toString(),
      season: season.toString(),
      from: fromDate,
      to: toDate,
    });
  }

  async getLiveFixtures(): Promise<ApiFootballResponse> {
    return this.fetchApi('/fixtures', { live: 'all' });
  }

  async getUpcomingFixtures(hoursAhead: number = 24): Promise<ApiFootballResponse> {
    const now = new Date();
    const future = new Date(now.getTime() + hoursAhead * 60 * 60 * 1000);
    
    const fromDate = now.toISOString().split('T')[0];
    const toDate = future.toISOString().split('T')[0];
    
    return this.fetchApi('/fixtures', {
      from: fromDate,
      to: toDate,
      status: 'NS,PST,LIVE',
    });
  }
}
