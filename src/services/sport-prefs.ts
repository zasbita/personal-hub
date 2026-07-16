import { SupabaseClient } from '@supabase/supabase-js';

export interface UserPreference {
  id: string;
  user_id: number;
  sport_type: string;
  entity_id: string;
  entity_name: string;
  notification_enabled: boolean;
  created_at: string;
}

export class SportPrefsService {
  private client: SupabaseClient;

  constructor(client: SupabaseClient) {
    this.client = client;
  }

  async addPreference(userId: string, sportType: string, entityId: string, entityName: string): Promise<void> {
    const userIdNum = parseInt(userId, 10);
    
    try {
      // Try to insert new preference
      const { error } = await this.client
        .from('user_preferences')
        .insert({
          user_id: userIdNum,
          sport_type: sportType,
          entity_id: entityId,
          entity_name: entityName,
          notification_enabled: true,
        });

      if (error) {
        // If unique constraint violation, update instead
        if (error.code === '23505' || error.message.includes('duplicate')) {
          const { error: updateError } = await this.client
            .from('user_preferences')
            .update({ notification_enabled: true })
            .eq('user_id', userIdNum)
            .eq('sport_type', sportType)
            .eq('entity_id', entityId);

          if (updateError) throw new Error(`Failed to update preference: ${updateError.message}`);
        } else {
          throw new Error(`Failed to add preference: ${error.message}`);
        }
      }
    } catch (err: any) {
      // Handle constraint violation
      if (err.message.includes('duplicate') || err.message.includes('23505')) {
        const { error: updateError } = await this.client
          .from('user_preferences')
          .update({ notification_enabled: true })
          .eq('user_id', userIdNum)
          .eq('sport_type', sportType)
          .eq('entity_id', entityId);

        if (updateError) throw new Error(`Failed to update preference: ${updateError.message}`);
      } else {
        throw err;
      }
    }
  }

  async removePreference(userId: string, sportType: string, entityId: string): Promise<void> {
    const userIdNum = parseInt(userId, 10);
    const { error } = await this.client
      .from('user_preferences')
      .delete()
      .eq('user_id', userIdNum)
      .eq('sport_type', sportType)
      .eq('entity_id', entityId);

    if (error) throw new Error(`Failed to remove preference: ${error.message}`);
  }

  async getPreferences(userId: string): Promise<UserPreference[]> {
    const userIdNum = parseInt(userId, 10);
    const { data, error } = await this.client
      .from('user_preferences')
      .select('*')
      .eq('user_id', userIdNum);

    if (error) throw new Error(`Failed to fetch preferences: ${error.message}`);
    return data as UserPreference[];
  }

  async clearPreferences(userId: string): Promise<void> {
    const userIdNum = parseInt(userId, 10);
    const { error } = await this.client
      .from('user_preferences')
      .delete()
      .eq('user_id', userIdNum);

    if (error) throw new Error(`Failed to clear preferences: ${error.message}`);
  }
}
