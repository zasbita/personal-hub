export interface MotoGPRace {
  season: string;
  round: string;
  raceName: string;
  date: string;
  time: string;
  circuit: {
    name: string;
    location: {
      locality: string;
      country: string;
    };
  };
}

export class MotoGPService {
  private baseUrl = 'https://ergast.com/api/moto';

  /**
   * Fetch current season races (all 3 classes: MotoGP, Moto2, Moto3)
   */
  async getCurrentSeasonRaces(classification: 'motogp' | 'moto2' | 'moto3' = 'motogp'): Promise<MotoGPRace[]> {
    const url = `${this.baseUrl}/${classification}/current/races.json`;
    const response = await fetch(url);

    if (!response.ok) {
      throw new Error(`Failed to fetch ${classification} races: ${response.statusText}`);
    }

    const data = await response.json();
    const races = data?.MRData?.RaceTable?.Races || [];

    // Filter for upcoming races (date in future)
    const now = new Date();
    return races.filter((race: any) => {
      const raceDate = new Date(`${race.date}T${race.time || '00:00:00'}`);
      return raceDate > now;
    });
  }

  /**
   * Check if a rider/event name matches a race
   */
  matchesRace(raceName: string, searchTerm: string): boolean {
    const lowerRace = raceName.toLowerCase();
    const lowerSearch = searchTerm.toLowerCase();

    // Match by country, circuit name, or race name
    return (
      lowerRace.includes(lowerSearch) ||
      lowerSearch.includes(lowerRace.split(' ')[0]) // Match first word
    );
  }

  /**
   * Format race info for notification
   */
  formatRaceInfo(race: MotoGPRace): string {
    const raceDate = new Date(`${race.date}T${race.time || '00:00:00'}`);
    const formatted = raceDate.toLocaleString('id-ID');

    return (
      `🏍️ *${race.raceName}*\n` +
      `🏁 ${race.circuit.name}\n` +
      `📍 ${race.circuit.location.locality}, ${race.circuit.location.country}\n` +
      `⏱️ ${formatted}`
    );
  }
}
