export interface Team {
  id: number;
  name: string;
  code: string;
  country: string;
  founded: number;
  national: boolean;
  logo: string;
}

export interface Venue {
  id: number;
  name: string;
  address: string;
  city: string;
  capacity: number;
  surface: string;
  image: string;
}

export interface League {
  id: number;
  name: string;
  type: string;
  logo: string;
}

export interface MatchStatus {
  long: string;
  short: string;
  elapsed: number | null;
}

export interface Fixture {
  id: number;
  referee: string | null;
  timezone: string;
  date: string; // ISO 8601 date string
  timestamp: number; // Unix timestamp
  periods: { first: number | null; second: number | null };
  venue: Omit<Venue, 'address' | 'city' | 'capacity' | 'surface' | 'image'>;
  status: MatchStatus;
}

export interface Goals {
  home: number | null;
  away: number | null;
}

export interface Score {
  halftime: Goals;
  fulltime: Goals;
  extratime: Goals;
  penalty: Goals;
}

export interface Match {
  fixture: Fixture;
  league: Omit<League, 'type'>;
  teams: {
    home: { id: number; name: string; logo: string; winner: boolean | null };
    away: { id: number; name: string; logo: string; winner: boolean | null };
  };
  goals: Goals;
  score: Score;
}

export interface ApiFootballResponse {
  get: string;
  parameters: {
    live?: string;
    team?: string;
    league?: string;
    season?: string;
    from?: string;
    to?: string;
  };
  errors: any[];
  results: number;
  paging: { current: number; total: number };
  response: Match[];
}

export interface RawLeague {
  league: League;
  country: {
    name: string;
    code: string;
    flag: string;
  };
  seasons: {
    year: number;
    start: string;
    end: string;
    current: boolean;
    coverage: any;
  }[];
}

export interface ApiFootballLeaguesResponse {
  get: string;
  parameters: {
    id?: string;
    name?: string;
    country?: string;
    season?: string;
    type?: string;
    search?: string;
  };
  errors: any[];
  results: number;
  paging: { current: number; total: number };
  response: RawLeague[];
}
