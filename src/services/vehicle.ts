import { SupabaseClient } from '@supabase/supabase-js';

export class VehicleService {
  private client: SupabaseClient;

  constructor(client: SupabaseClient) {
    this.client = client;
  }

  /**
   * Updates odometer reading and returns next service target
   */
  async updateOdometer(userId: string, username: string | undefined, km: number): Promise<{ lastKm: number; nextServiceKm: number; interval: number }> {
    const userIdNum = parseInt(userId, 10);
    
    // 1. Ensure user exists in 'users' table (upsert whitelisted user)
    const { error: userError } = await this.client
      .from('users')
      .upsert({ 
        user_id: userIdNum, 
        username: username || null,
        pref_language: 'id'
      }, { onConflict: 'user_id' });

    if (userError) {
      throw new Error(`Failed to sync user: ${userError.message}`);
    }

    // 2. Fetch existing service configuration to check custom intervals
    const { data: existing, error: fetchError } = await this.client
      .from('vehicle_service')
      .select('service_interval')
      .eq('user_id', userIdNum)
      .maybeSingle();

    if (fetchError) {
      throw new Error(`Failed to check existing vehicle service: ${fetchError.message}`);
    }

    const interval = existing?.service_interval || 2000;
    const nextServiceKm = km + interval;

    // 3. Upsert odometer record
    const { error: upsertError } = await this.client
      .from('vehicle_service')
      .upsert({
        user_id: userIdNum,
        last_km: km,
        service_interval: interval,
        next_service_km: nextServiceKm,
        updated_at: new Date().toISOString()
      }, { onConflict: 'user_id' }); // Handled by unique constraint or index if we align it to 1 vehicle per user

    if (upsertError) {
      // In case onConflict needs to be explicit or if we fall back to manual check
      // Let's perform a manual check-update since we want it to be robust
      const { data: check } = await this.client
        .from('vehicle_service')
        .select('id')
        .eq('user_id', userIdNum)
        .maybeSingle();

      if (check) {
        const { error: updateErr } = await this.client
          .from('vehicle_service')
          .update({
            last_km: km,
            next_service_km: nextServiceKm,
            updated_at: new Date().toISOString()
          })
          .eq('user_id', userIdNum);
        if (updateErr) throw new Error(`Failed to update odometer: ${updateErr.message}`);
      } else {
        const { error: insertErr } = await this.client
          .from('vehicle_service')
          .insert({
            user_id: userIdNum,
            last_km: km,
            service_interval: interval,
            next_service_km: nextServiceKm
          });
        if (insertErr) throw new Error(`Failed to insert odometer: ${insertErr.message}`);
      }
    }

    return { lastKm: km, nextServiceKm, interval };
  }

  /**
   * Retrieves remaining kilometers for next service
   */
  async getServiceStatus(userId: string): Promise<{ lastKm: number; nextServiceKm: number; remainingKm: number } | null> {
    const userIdNum = parseInt(userId, 10);
    const { data, error } = await this.client
      .from('vehicle_service')
      .select('last_km, next_service_km')
      .eq('user_id', userIdNum)
      .maybeSingle();

    if (error) {
      throw new Error(`Failed to retrieve service status: ${error.message}`);
    }

    if (!data) return null;

    const remainingKm = data.next_service_km - data.last_km;

    return {
      lastKm: data.last_km,
      nextServiceKm: data.next_service_km,
      remainingKm,
    };
  }
}
