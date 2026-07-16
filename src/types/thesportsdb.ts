export interface SportsDbEvent {
  idEvent: string;
  idSoccerXML: string | null;
  idAPIfootball: string | null;
  strEvent: string; // Event name, e.g., "Chelsea vs Liverpool"
  strEventAlternate: string;
  strFilename: string;
  strSport: string; // e.g., "Soccer", "Volleyball"
  idLeague: string;
  strLeague: string;
  strSeason: string;
  strDescriptionEN: string | null;
  strHomeTeam: string;
  strAwayTeam: string;
  intHomeScore: string | null;
  intAwayScore: string | null;
  strTimestamp: string; // "2026-07-15T19:00:00+00:00"
  dateEvent: string; // "2026-07-15"
  strTime: string; // "19:00:00+00:00"
  strPostponed: string | null;
  strStatus: string; // "Not Started"
  strTVStation: string | null;
  strVenue: string | null;
  strCountry: string; // "England"
  strThumb: string | null; // Thumbnail image
  strBanner: string | null;
  strMap: string | null;
  strTweet1: string | null;
  strHomeTeamFanart1: string | null;
  strAwayTeamFanart1: string | null;
  intRound: string | null;
  strDate: string;
  strTimeLocal: string; // e.g., "14:00:00"
}

export interface SportsDbEventsResponse {
  events: SportsDbEvent[] | null;
}