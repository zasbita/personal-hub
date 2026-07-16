export interface MobileLegendsMatch {
  tournament: string;
  stage: string;
  team1: string;
  team2: string;
  dateTime: Date; // Full timestamp
  sourceUrl: string;
  status: 'upcoming' | 'live' | 'finished';
}

export interface LiquipediaTeam {
  name: string;
  logoUrl: string;
  // Potentially add more details if needed for matching
}

export interface LiquipediaTournament {
  name: string;
  shortName: string; // e.g., MPL ID S13, MSC 2024
  url: string;
}
