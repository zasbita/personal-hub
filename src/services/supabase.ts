import { createClient, SupabaseClient } from '@supabase/supabase-js';

// SupabaseClient is strongly typed by schema. If we cache it as a generic
// SupabaseClient without matching schema generics, TS will complain.
//
// Using `SupabaseClient<any, any, any, any, any>` keeps this module resilient
// across different schema configurations (useful for local tests too).
let supabaseInstance: SupabaseClient<any, any, any, any, any> | null = null;

export function getSupabase(url: string, key: string): SupabaseClient<any, any, any, any, any> {
  if (!supabaseInstance) {
    if (url === 'YOUR_SUPABASE_URL' || key === 'YOUR_SUPABASE_KEY') {
      // Return a dummy client or throw warning for local tests
      console.warn('Using placeholder Supabase URL or Key. Queries will fail unless properly configured.');
    }

    supabaseInstance = createClient(url, key, {
      db: { schema: 'public' },
      auth: {
        persistSession: false,
      },
    });
  }

  // TS knows supabaseInstance is set here because of the guard above.
  return supabaseInstance;
}

