import { BotEnv } from '../types/telegram';

export class SheetsService {
  private env: BotEnv & { GOOGLE_SHEET_ID: string; GOOGLE_SERVICE_ACCOUNT_EMAIL: string; GOOGLE_PRIVATE_KEY: string };

  constructor(env: any) {
    this.env = env;
  }

  private async getAccessToken(): Promise<string> {
    const privateKey = this.env.GOOGLE_PRIVATE_KEY.replace(/\\n/g, '\n');
    const email = this.env.GOOGLE_SERVICE_ACCOUNT_EMAIL;
    
    const header = { alg: 'RS256', typ: 'JWT' };
    const now = Math.floor(Date.now() / 1000);
    const claim = {
      iss: email,
      scope: 'https://www.googleapis.com/auth/spreadsheets',
      aud: 'https://oauth2.googleapis.com/token',
      exp: now + 3600,
      iat: now,
    };

    const encodedHeader = btoa(JSON.stringify(header));
    const encodedClaim = btoa(JSON.stringify(claim));
    const signatureInput = `${encodedHeader}.${encodedClaim}`;

    // Import Private Key using Web Crypto API
    const pemContents = privateKey
      .replace('-----BEGIN PRIVATE KEY-----', '')
      .replace('-----END PRIVATE KEY-----', '')
      .replace(/\s+/g, '');
    
    const binaryDer = Uint8Array.from(atob(pemContents), c => c.charCodeAt(0));
    
    const key = await crypto.subtle.importKey(
      'pkcs8',
      binaryDer,
      { name: 'RSASSA-PKCS1-v1_5', hash: 'SHA-256' },
      false,
      ['sign']
    );

    const signature = await crypto.subtle.sign(
      'RSASSA-PKCS1-v1_5',
      key,
      new TextEncoder().encode(signatureInput)
    );

    const encodedSignature = btoa(String.fromCharCode(...new Uint8Array(signature)))
      .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

    const jwt = `${signatureInput}.${encodedSignature}`;

    const response = await fetch('https://oauth2.googleapis.com/token', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        grant_type: 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        assertion: jwt,
      }),
    });

    const data = await response.json() as any;
    if (!data.access_token) {
      throw new Error(`Google Auth Failed: ${JSON.stringify(data)}`);
    }

    return data.access_token;
  }

  async appendExpense(amount: number, description: string, category: string) {
    const token = await this.getAccessToken();
    const sheetId = this.env.GOOGLE_SHEET_ID;
    const range = 'A:D'; 
    
    const date = new Date().toISOString().split('T')[0];
    const values = [[date, amount, description, category]];

    const response = await fetch(
      `https://sheets.googleapis.com/v4/spreadsheets/${sheetId}/values/${range}:append?valueInputOption=USER_ENTERED`,
      {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ values }),
      }
    );

    if (!response.ok) {
      const errorData = await response.json() as any;
      throw new Error(`Google Sheets API Error: ${JSON.stringify(errorData)}`);
    }

    return response.json();
  }

  async getWeeklyExpenses(): Promise<{ total: number; items: { date: string; amount: number; description: string; category: string }[] }> {
    const token = await this.getAccessToken();
    const sheetId = this.env.GOOGLE_SHEET_ID;
    const range = 'A:D'; // Fetch all logs

    const response = await fetch(
      `https://sheets.googleapis.com/v4/spreadsheets/${sheetId}/values/${range}`,
      {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
        },
      }
    );

    if (!response.ok) {
      const errorData = await response.json() as any;
      throw new Error(`Google Sheets API Error: ${JSON.stringify(errorData)}`);
    }

    const data = await response.json() as any;
    const rows = data.values as string[][] || [];
    
    if (rows.length === 0) {
      return { total: 0, items: [] };
    }

    // Filter for expenses recorded in the last 7 days
    const today = new Date();
    const sevenDaysAgo = new Date();
    sevenDaysAgo.setDate(today.getDate() - 7);

    let total = 0;
    const items: { date: string; amount: number; description: string; category: string }[] = [];

    // Skip header row if there is one (assuming first row is not numeric for column 2)
    const startIndex = isNaN(Number(rows[0][1])) ? 1 : 0;

    for (let i = startIndex; i < rows.length; i++) {
      const row = rows[i];
      if (row.length < 3) continue;

      const dateStr = row[0];
      const amount = Number(row[1]);
      const description = row[2];
      const category = row[3] || 'General';

      if (isNaN(amount)) continue;

      const expenseDate = new Date(dateStr);
      
      // Check if within 7 days
      if (expenseDate >= sevenDaysAgo && expenseDate <= today) {
        total += amount;
        items.push({ date: dateStr, amount, description, category });
      }
    }

    return { total, items };
  }
}
