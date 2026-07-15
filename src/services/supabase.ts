import { createClient, SupabaseClient } from '@supabase/supabase-js';

let supabaseInstance: SupabaseClient | null = null;

export function getSupabase(url: string, key: string): SupabaseClient {
  if (!supabaseInstance) {
    if (url === 'YOUR_SUPABASE_URL' || key === 'YOUR_SUPABASE_KEY') {
      // Return a dummy client or throw warning for local tests
      console.warn('Using placeholder Supabase URL or Key. Queries will fail unless properly configured.');
    }
    // We specify a custom fetch since Cloudflare Workers requires standard fetch
    supabaseInstance = createClient(url, key, {
      auth: {
        persistSession: false,
      },
    });
  }
  return supabaseInstance;
}
