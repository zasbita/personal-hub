export interface UserPreference {
  id: string;
  user_id: number;
  sport_type: string;
  entity_id: string; // e.g., 'liverpool' or 'mpl-indonesia'
  entity_name: string; // e.g., 'Liverpool' or 'MPL Indonesia'
  notification_enabled: boolean;
  created_at: string;
}

export interface MatchSchedule {
  id: string;
  source_id: string;
  sport_type: string;
  competition: string;
  home_team: string;
  away_team: string;
  match_time: Date; // Full timestamp
  status: 'scheduled' | 'live' | 'finished' | 'postponed';
  notified: boolean;
  created_at: string;
  updated_at: string;
}