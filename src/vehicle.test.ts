import { describe, it, expect, vi, beforeEach } from 'vitest';
import { VehicleService } from './services/vehicle';
import { SupabaseClient } from '@supabase/supabase-js';

describe('VehicleService', () => {
  let mockSupabase: any;
  let service: VehicleService;

  beforeEach(() => {
    mockSupabase = {
      from: vi.fn().mockReturnThis(),
      upsert: vi.fn(),
      select: vi.fn().mockReturnThis(),
      eq: vi.fn().mockReturnThis(),
      maybeSingle: vi.fn(),
      update: vi.fn().mockReturnThis(),
      insert: vi.fn(),
    };
    service = new VehicleService(mockSupabase as unknown as SupabaseClient);
  });

  it('should calculate next service km correctly when updating odometer', async () => {
    // Mock user upsert
    mockSupabase.upsert.mockResolvedValueOnce({ error: null }); // Users upsert
    
    // Mock check existing configuration (returns no custom interval)
    mockSupabase.maybeSingle.mockResolvedValueOnce({ data: null, error: null }); // vehicle_service config fetch
    
    // Mock final upsert success
    mockSupabase.upsert.mockResolvedValueOnce({ error: null }); // vehicle_service upsert

    const result = await service.updateOdometer('811031481', 'zasbita', 15000);
    
    expect(result).toEqual({
      lastKm: 15000,
      nextServiceKm: 17000, // 15000 + 2000 default interval
      interval: 2000
    });
  });

  it('should return correct status when checking service status', async () => {
    mockSupabase.maybeSingle.mockResolvedValueOnce({
      data: { last_km: 15000, next_service_km: 17000 },
      error: null
    });

    const result = await service.getServiceStatus('811031481');
    expect(result).toEqual({
      lastKm: 15000,
      nextServiceKm: 17000,
      remainingKm: 2000
    });
  });
});
