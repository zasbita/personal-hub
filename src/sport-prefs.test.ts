import { describe, it, expect, vi, beforeEach } from 'vitest';
import { SupabaseClient } from '@supabase/supabase-js';
import { SportPrefsService } from './services/sport-prefs';

describe('SportPrefsService', () => {
  let mockSupabase: any;
  let service: SportPrefsService;

  beforeEach(() => {
    mockSupabase = {
      from: vi.fn().mockReturnThis(),
      select: vi.fn().mockReturnThis(),
      eq: vi.fn().mockReturnThis(),
      update: vi.fn().mockReturnThis(),
      insert: vi.fn().mockResolvedValue({ error: null }),
      delete: vi.fn().mockReturnThis(),
    };
    service = new SportPrefsService(mockSupabase as unknown as SupabaseClient);
  });

  it('should add a new sport preference', async () => {
    mockSupabase.insert.mockResolvedValue({ error: null });

    await service.addPreference('123', 'football', 'liverpool', 'Liverpool');
    expect(mockSupabase.from).toHaveBeenCalledWith('user_preferences');
    expect(mockSupabase.insert).toHaveBeenCalled();
  });

  it('should remove an existing sport preference', async () => {
    await service.removePreference('123', 'football', 'liverpool');
    expect(mockSupabase.from).toHaveBeenCalledWith('user_preferences');
    expect(mockSupabase.delete).toHaveBeenCalled();
    expect(mockSupabase.eq).toHaveBeenCalledWith('user_id', 123);
  });

  it('should get all sport preferences for a user', async () => {
    const mockData = [{ id: '1', user_id: 123, sport_type: 'football', entity_id: 'liverpool', entity_name: 'Liverpool', notification_enabled: true, created_at: '' }];
    
    // Correct the mock chain for .select().eq()
    mockSupabase.select.mockReturnThis();
    mockSupabase.eq.mockResolvedValue({ data: mockData, error: null });

    const result = await service.getPreferences('123');
    expect(result).toEqual(mockData);
    expect(mockSupabase.from).toHaveBeenCalledWith('user_preferences');
  });

  it('should clear all preferences for a user', async () => {
    await service.clearPreferences('123');
    expect(mockSupabase.from).toHaveBeenCalledWith('user_preferences');
    expect(mockSupabase.delete).toHaveBeenCalled();
    expect(mockSupabase.eq).toHaveBeenCalledWith('user_id', 123);
  });
});
