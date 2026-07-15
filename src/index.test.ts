import { describe, it, expect, vi } from 'vitest';
import app from './index';

// Mocking fetch for Telegram API calls
vi.stubGlobal('fetch', vi.fn(() => Promise.resolve({
  json: () => Promise.resolve({ ok: true }),
})));

describe('Telegram Webhook Handler', () => {
  const mockEnv = {
    BOT_TOKEN: 'test-token',
    OWNER_ID: '8770207950',
    SUPABASE_URL: 'test-url',
    SUPABASE_KEY: 'test-key',
  };

  it('should ignore empty updates', async () => {
    const res = await app.request('/webhook', {
      method: 'POST',
      body: JSON.stringify({}),
    });

    expect(res.status).toBe(200);
  });
});
