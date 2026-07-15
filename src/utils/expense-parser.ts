import { ParsedExpense } from '../types/expense';

export class ExpenseParser {
  /**
   * Parses natural language input like "/log 50k makan siang #makanan"
   * Supported formats: 
   * - 50k, 50rb, 50.000, 50000
   * - Hashtag categories like #makanan, #bensin (Capitalized automatically)
   */
  static parse(text: string): ParsedExpense | null {
    const cleanText = text.replace(/^\/(log|catat)\s+/i, '').trim();
    
    if (!cleanText) return null;

    // Regex: [amount] [description and potential hashtag]
    const regex = /^([\d.,\s]+(?:k|rb)?)\s+(.+)$/i;
    const match = cleanText.match(regex);

    if (!match) return null;

    const rawAmount = match[1].trim();
    let remainingText = match[2].trim();

    const amount = this.normalizeAmount(rawAmount);
    
    // VALIDATION: Reject ambiguous, zero, or negative amounts
    if (amount === null || amount <= 0 || amount > 100000000) {
      return null;
    }

    // Extract hashtag if present
    let category = 'General';
    const hashtagRegex = /#(\w+)\s*$/;
    const hashtagMatch = remainingText.match(hashtagRegex);

    if (hashtagMatch) {
      // Capitalize first letter of category (e.g. makanan -> Makanan)
      const rawCat = hashtagMatch[1];
      category = rawCat.charAt(0).toUpperCase() + rawCat.slice(1).toLowerCase();
      // Remove hashtag from description
      remainingText = remainingText.replace(hashtagRegex, '').trim();
    }

    return {
      amount,
      description: remainingText,
      category,
    };
  }

  private static normalizeAmount(raw: string): number | null {
    let clean = raw.toLowerCase().replace(/,/g, '').trim();
    
    const suffixRegex = /(k|rb)$/;
    let numberPart = clean.replace(suffixRegex, '').trim();
    
    if (/[^\d.]/.test(numberPart)) {
      return null;
    }

    if (clean.endsWith('k') || clean.endsWith('rb')) {
      const value = parseFloat(numberPart);
      return isNaN(value) ? null : value * 1000;
    }

    const value = parseFloat(clean.replace(/\./g, ''));
    return isNaN(value) ? null : value;
  }
}
