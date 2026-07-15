export interface BotEnv {
  BOT_TOKEN: string;
  OWNER_ID: string;
  SUPABASE_URL: string;
  SUPABASE_KEY: string;
}

export interface TelegramUpdate {
  update_id: number;
  message?: {
    message_id: number;
    from: {
      id: number;
      is_bot: boolean;
      first_name: string;
      username?: string;
    };
    chat: {
      id: number;
      type: string;
    };
    text?: string;
  };
}
