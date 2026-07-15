import { describe, it, expect } from 'vitest';
import { ExpenseParser } from './utils/expense-parser';

describe('ExpenseParser Upgrade', () => {
  it('should parse with hashtag category', () => {
    const result = ExpenseParser.parse('/log 50k makan siang #makanan');
    expect(result).toEqual({
      amount: 50000,
      description: 'makan siang',
      category: 'Makanan',
    });
  });

  it('should parse without hashtag (default to General)', () => {
    const result = ExpenseParser.parse('/log 10k parkir');
    expect(result).toEqual({
      amount: 10000,
      description: 'parkir',
      category: 'General',
    });
  });

  it('should handle case-insensitive hashtags and trim whitespace', () => {
    const result = ExpenseParser.parse('/catat 100rb bensin #KENDARAAN');
    expect(result).toEqual({
      amount: 100000,
      description: 'bensin',
      category: 'Kendaraan',
    });
  });

  it('should return null for invalid amount format even with hashtag', () => {
    const result = ExpenseParser.parse('/log hanya teks #makanan');
    expect(result).toBeNull();
  });
});
